<?php
/**
 * Customer order-status SMS.
 *
 * On an order reaching an enabled status, render that status template and
 * text the billing phone. Also hosts the shared placeholder renderer used
 * by the admin-alert feature.
 *
 * @package Hellio_SMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hellio_SMS_Order_SMS
 */
class Hellio_SMS_Order_SMS {

	/**
	 * Singleton instance.
	 *
	 * @var Hellio_SMS_Order_SMS|null
	 */
	protected static $instance = null;

	/**
	 * Get the singleton.
	 *
	 * @return Hellio_SMS_Order_SMS
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hook the status-change event.
	 */
	protected function __construct() {
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 10, 4 );
	}

	/**
	 * Handle an order status transition.
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $from     Old status slug.
	 * @param string   $to       New status slug.
	 * @param WC_Order $order    Order object.
	 */
	public function on_status_changed( $order_id, $from, $to, $order = null ) {
		try {
			if ( ! Hellio_SMS_Settings::is_enabled() ) {
				return;
			}

			$to = sanitize_key( $to );

			if ( 'yes' !== Hellio_SMS_Settings::get( 'status_' . $to . '_enabled', 'no' ) ) {
				return;
			}

			if ( ! $order instanceof WC_Order ) {
				$order = wc_get_order( $order_id );
			}

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			$phone = $order->get_billing_phone();

			if ( '' === trim( (string) $phone ) ) {
				return;
			}

			$template = Hellio_SMS_Settings::get( 'status_' . $to . '_template', '' );
			$message  = self::render_template( $template, $order );

			if ( '' === trim( $message ) ) {
				return;
			}

			$sender = Hellio_SMS_Settings::get( 'sender_id', '' );
			$client = Hellio_SMS_Client::from_settings();
			$result = $client->send_sms( array( $phone ), $sender, $message );

			if ( empty( $result['success'] ) ) {
				$order->add_order_note(
					sprintf(
						/* translators: %s is the API error message. */
						__( 'Hellio SMS not sent: %s', 'hellio-sms' ),
						$result['message']
					)
				);
			}
		} catch ( \Throwable $e ) {
			// Never break order processing on a messaging failure.
			self::log_exception( $e );
		}
	}

	/**
	 * Replace template placeholders with order data. Unknown tokens render empty.
	 *
	 * @param string   $template Raw template text.
	 * @param WC_Order $order    Order object.
	 * @return string
	 */
	public static function render_template( $template, $order ) {
		$template = (string) $template;

		if ( '' === $template || ! $order instanceof WC_Order ) {
			return $template;
		}

		$store_name = get_bloginfo( 'name' );
		$first_name = $order->get_billing_first_name();
		$last_name  = $order->get_billing_last_name();
		$full_name  = trim( $first_name . ' ' . $last_name );

		if ( '' === $full_name ) {
			$full_name = $order->get_formatted_billing_full_name();
		}

		$total = html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES );
		// Prefer a plain numeric total for SMS.
		$plain_total = wc_format_decimal( $order->get_total(), wc_get_price_decimals() );

		$map = array(
			'{order_id}'            => $order->get_id(),
			'{order_number}'        => $order->get_order_number(),
			'{order_status}'        => wc_get_order_status_name( $order->get_status() ),
			'{order_total}'         => $plain_total,
			'{currency}'            => $order->get_currency(),
			'{customer_name}'       => $full_name,
			'{customer_first_name}' => $first_name,
			'{store_name}'          => $store_name,
			'{shop_url}'            => home_url( '/' ),
			'{tracking_url}'        => $order->get_view_order_url(),
			'{date}'                => $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : date_i18n( wc_date_format() ),
		);

		/**
		 * Filter the placeholder map before rendering.
		 *
		 * @param array    $map   Placeholder to value.
		 * @param WC_Order $order Order object.
		 */
		$map = apply_filters( 'hellio_sms_template_placeholders', $map, $order );

		$rendered = strtr( $template, array_map( 'strval', $map ) );

		// Any remaining unknown placeholders render empty.
		$rendered = preg_replace( '/\{[a-z0-9_]+\}/i', '', $rendered );

		return trim( $rendered );
	}

	/**
	 * Log an exception via the WC logger when available.
	 *
	 * @param \Throwable $e Exception.
	 */
	protected static function log_exception( $e ) {
		$line = 'Hellio SMS order status handler: ' . $e->getMessage();

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $line, array( 'source' => 'hellio-sms' ) );

			return;
		}

		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
