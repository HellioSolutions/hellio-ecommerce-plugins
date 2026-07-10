<?php
/**
 * Admin new-order alert.
 *
 * On a new order, SMS each configured admin number with the alert template.
 *
 * @package Hellio_SMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hellio_SMS_Admin_Alert
 */
class Hellio_SMS_Admin_Alert {

	/**
	 * Singleton instance.
	 *
	 * @var Hellio_SMS_Admin_Alert|null
	 */
	protected static $instance = null;

	/**
	 * Get the singleton.
	 *
	 * @return Hellio_SMS_Admin_Alert
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hook the new-order events.
	 */
	protected function __construct() {
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_new_order' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'on_new_order' ), 20, 1 );
	}

	/**
	 * Send the admin alert once per order.
	 *
	 * @param int|WC_Order $order_id Order ID or object.
	 */
	public function on_new_order( $order_id ) {
		try {
			if ( ! Hellio_SMS_Settings::is_enabled() ) {
				return;
			}

			if ( 'yes' !== Hellio_SMS_Settings::get( 'admin_alert_enabled', 'no' ) ) {
				return;
			}

			$order = $order_id instanceof WC_Order ? $order_id : wc_get_order( $order_id );

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			// Guard against duplicate sends across the two hooks.
			if ( $order->get_meta( '_hellio_admin_alert_sent' ) ) {
				return;
			}

			$numbers_raw = Hellio_SMS_Settings::get( 'admin_alert_numbers', '' );
			$numbers     = array_filter( array_map( 'trim', explode( ',', $numbers_raw ) ) );

			if ( empty( $numbers ) ) {
				return;
			}

			$template = Hellio_SMS_Settings::get( 'admin_alert_template', '' );
			$message  = Hellio_SMS_Order_SMS::render_template( $template, $order );

			if ( '' === trim( $message ) ) {
				return;
			}

			$sender = Hellio_SMS_Settings::get( 'sender_id', '' );
			$client = Hellio_SMS_Client::from_settings();
			$result = $client->send_sms( array_values( $numbers ), $sender, $message );

			$order->update_meta_data( '_hellio_admin_alert_sent', current_time( 'mysql' ) );
			$order->save();

			if ( empty( $result['success'] ) ) {
				$this->log( 'Admin alert failed for order ' . $order->get_id() . ': ' . $result['message'] );
			}
		} catch ( \Throwable $e ) {
			$this->log( 'Admin alert handler error: ' . $e->getMessage() );
		}
	}

	/**
	 * Log helper.
	 *
	 * @param string $line Message.
	 */
	protected function log( $line ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $line, array( 'source' => 'hellio-sms' ) );

			return;
		}

		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
