<?php
/**
 * Bulk / marketing SMS admin page.
 *
 * Compose a message, pick an audience (all customers, customers by last order
 * status, or a pasted list), and send via /v1/sms/send in chunks of 500.
 *
 * @package Hellio_SMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hellio_SMS_Bulk
 */
class Hellio_SMS_Bulk {

	const PAGE       = 'hellio-sms-bulk';
	const NONCE      = 'hellio_sms_bulk_send';
	const CHUNK_SIZE = 500;

	/**
	 * Singleton instance.
	 *
	 * @var Hellio_SMS_Bulk|null
	 */
	protected static $instance = null;

	/**
	 * Get the singleton.
	 *
	 * @return Hellio_SMS_Bulk
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register the admin menu.
	 */
	protected function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Add the bulk page under WooCommerce.
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Hellio Bulk SMS', 'hellio-sms' ),
			__( 'Hellio Bulk SMS', 'hellio-sms' ),
			'manage_woocommerce',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the page and handle a submitted send.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'hellio-sms' ) );
		}

		$notice = '';

		if ( isset( $_POST['hellio_bulk_submit'] ) ) {
			$notice = $this->handle_send();
		}

		$statuses = Hellio_SMS_Settings::statuses();
		$message  = isset( $_POST['hellio_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hellio_message'] ) ) : '';
		$numbers  = isset( $_POST['hellio_numbers'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hellio_numbers'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Hellio Bulk SMS', 'hellio-sms' ); ?></h1>

			<?php if ( '' !== $notice ) : ?>
				<?php echo wp_kses_post( $notice ); ?>
			<?php endif; ?>

			<?php if ( ! Hellio_SMS_Settings::is_enabled() ) : ?>
				<div class="notice notice-warning"><p><?php echo esc_html__( 'Hellio SMS is disabled. Enable it in settings before sending.', 'hellio-sms' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="hellio_message"><?php echo esc_html__( 'Message', 'hellio-sms' ); ?></label></th>
						<td>
							<textarea id="hellio_message" name="hellio_message" class="large-text" rows="4" maxlength="1600" required><?php echo esc_textarea( $message ); ?></textarea>
							<p class="description"><?php echo esc_html__( 'Up to 1600 characters. Longer messages are billed as multiple segments.', 'hellio-sms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Audience', 'hellio-sms' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:6px;">
								<input type="radio" name="hellio_audience" value="all" checked />
								<?php echo esc_html__( 'All customers with a billing phone', 'hellio-sms' ); ?>
							</label>
							<label style="display:block;margin-bottom:6px;">
								<input type="radio" name="hellio_audience" value="status" />
								<?php echo esc_html__( 'Customers whose last order has status:', 'hellio-sms' ); ?>
								<select name="hellio_audience_status">
									<?php foreach ( $statuses as $slug => $label ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label style="display:block;margin-bottom:6px;">
								<input type="radio" name="hellio_audience" value="paste" />
								<?php echo esc_html__( 'Pasted numbers (one per line or comma separated)', 'hellio-sms' ); ?>
							</label>
							<textarea name="hellio_numbers" class="large-text" rows="4" placeholder="233241111111&#10;233242222222"><?php echo esc_textarea( $numbers ); ?></textarea>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Send bulk SMS', 'hellio-sms' ), 'primary', 'hellio_bulk_submit' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Validate input, gather recipients, send in chunks, return a notice.
	 *
	 * @return string HTML notice.
	 */
	protected function handle_send() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $this->notice( __( 'You are not allowed to do this.', 'hellio-sms' ), 'error' );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE ) ) {
			return $this->notice( __( 'Security check failed. Please try again.', 'hellio-sms' ), 'error' );
		}

		if ( ! Hellio_SMS_Settings::is_enabled() ) {
			return $this->notice( __( 'Hellio SMS is disabled.', 'hellio-sms' ), 'error' );
		}

		$message = isset( $_POST['hellio_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hellio_message'] ) ) : '';
		$message = trim( $message );

		if ( '' === $message ) {
			return $this->notice( __( 'Enter a message to send.', 'hellio-sms' ), 'error' );
		}

		$audience = isset( $_POST['hellio_audience'] ) ? sanitize_key( wp_unslash( $_POST['hellio_audience'] ) ) : 'all';
		$recipients = array();

		switch ( $audience ) {
			case 'paste':
				$raw        = isset( $_POST['hellio_numbers'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hellio_numbers'] ) ) : '';
				$recipients = preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
				break;

			case 'status':
				$status     = isset( $_POST['hellio_audience_status'] ) ? sanitize_key( wp_unslash( $_POST['hellio_audience_status'] ) ) : '';
				$recipients = $this->collect_phones_by_status( $status );
				break;

			case 'all':
			default:
				$recipients = $this->collect_phones_by_status( '' );
				break;
		}

		$recipients = array_values( array_filter( array_map( 'trim', (array) $recipients ) ) );

		if ( empty( $recipients ) ) {
			return $this->notice( __( 'No recipients matched that audience.', 'hellio-sms' ), 'warning' );
		}

		$sender = Hellio_SMS_Settings::get( 'sender_id', '' );
		$client = Hellio_SMS_Client::from_settings();

		$sent   = 0;
		$failed = 0;
		$errors = array();

		foreach ( array_chunk( $recipients, self::CHUNK_SIZE ) as $chunk ) {
			try {
				$result = $client->send_sms( $chunk, $sender, $message );

				if ( ! empty( $result['success'] ) ) {
					$data     = is_array( $result['data'] ) ? $result['data'] : array();
					$accepted = isset( $data['accepted_recipients'] ) ? (int) $data['accepted_recipients'] : count( $chunk );
					$invalid  = isset( $data['invalid_recipients'] ) ? (int) $data['invalid_recipients'] : 0;

					$sent   += $accepted;
					$failed += $invalid;
				} else {
					$failed  += count( $chunk );
					$errors[] = $result['message'];
				}
			} catch ( \Throwable $e ) {
				$failed  += count( $chunk );
				$errors[] = $e->getMessage();
			}
		}

		$summary = sprintf(
			/* translators: 1: sent count, 2: failed count. */
			__( 'Done. Sent: %1$d. Failed: %2$d.', 'hellio-sms' ),
			$sent,
			$failed
		);

		if ( ! empty( $errors ) ) {
			$summary .= ' ' . esc_html( implode( ' ', array_unique( $errors ) ) );

			return $this->notice( $summary, $sent > 0 ? 'warning' : 'error' );
		}

		return $this->notice( $summary, 'success' );
	}

	/**
	 * Collect unique billing phones from customers, optionally filtered by the
	 * status of a customer's most recent order.
	 *
	 * @param string $status Status slug, or empty for all.
	 * @return array
	 */
	protected function collect_phones_by_status( $status ) {
		$phones = array();

		if ( '' !== $status ) {
			$order_ids = wc_get_orders(
				array(
					'status'  => 'wc-' . $status,
					'limit'   => -1,
					'return'  => 'ids',
					'orderby' => 'date',
					'order'   => 'DESC',
				)
			);

			foreach ( $order_ids as $order_id ) {
				$order = wc_get_order( $order_id );

				if ( $order instanceof WC_Order ) {
					$phone = trim( (string) $order->get_billing_phone() );

					if ( '' !== $phone ) {
						$phones[] = $phone;
					}
				}
			}

			return array_values( array_unique( $phones ) );
		}

		// All customers: pull the most recent orders and dedupe by phone.
		$order_ids = wc_get_orders(
			array(
				'limit'   => -1,
				'return'  => 'ids',
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( $order instanceof WC_Order ) {
				$phone = trim( (string) $order->get_billing_phone() );

				if ( '' !== $phone ) {
					$phones[] = $phone;
				}
			}
		}

		return array_values( array_unique( $phones ) );
	}

	/**
	 * Build an admin notice.
	 *
	 * @param string $message Text.
	 * @param string $type    success|error|warning|info.
	 * @return string
	 */
	protected function notice( $message, $type = 'success' ) {
		$class = 'notice notice-' . sanitize_html_class( $type );

		return '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}
}
