<?php
/**
 * Checkout phone OTP verification.
 *
 * Adds a "Send code" button and code field by the checkout phone, verifies
 * server-side (the token never reaches the browser), and blocks order
 * placement until the entered phone is verified in the session.
 *
 * @package Hellio_SMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hellio_SMS_OTP
 */
class Hellio_SMS_OTP {

	const NONCE          = 'hellio_sms_otp';
	const SESSION_KEY    = 'hellio_otp_verified_phone';

	/**
	 * Singleton instance.
	 *
	 * @var Hellio_SMS_OTP|null
	 */
	protected static $instance = null;

	/**
	 * Get the singleton.
	 *
	 * @return Hellio_SMS_OTP
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	protected function __construct() {
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_fields' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_verified' ), 10, 2 );

		add_action( 'wp_ajax_hellio_sms_otp_send', array( $this, 'ajax_send' ) );
		add_action( 'wp_ajax_nopriv_hellio_sms_otp_send', array( $this, 'ajax_send' ) );
		add_action( 'wp_ajax_hellio_sms_otp_verify', array( $this, 'ajax_verify' ) );
		add_action( 'wp_ajax_nopriv_hellio_sms_otp_verify', array( $this, 'ajax_verify' ) );

		// Clear verification once an order is placed.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'clear_session' ), 5 );
	}

	/**
	 * Is the feature usable right now?
	 *
	 * @return bool
	 */
	protected function is_active() {
		return Hellio_SMS_Settings::is_enabled() && 'yes' === Hellio_SMS_Settings::get( 'otp_enabled', 'no' );
	}

	/**
	 * Render the OTP UI under the billing form.
	 */
	public function render_fields() {
		if ( ! $this->is_active() ) {
			return;
		}
		?>
		<div id="hellio-otp" class="hellio-otp" style="margin:1em 0;padding:1em;border:1px solid #e0e0e0;border-radius:4px;">
			<p style="margin-top:0;"><strong><?php echo esc_html__( 'Verify your phone number', 'hellio-sms' ); ?></strong></p>
			<p class="hellio-otp-hint"><?php echo esc_html__( 'We will text you a code to confirm this number before your order is placed.', 'hellio-sms' ); ?></p>
			<p>
				<button type="button" class="button" id="hellio-otp-send"><?php echo esc_html__( 'Send code', 'hellio-sms' ); ?></button>
			</p>
			<p class="hellio-otp-code-row" style="display:none;">
				<label for="hellio-otp-code"><?php echo esc_html__( 'Verification code', 'hellio-sms' ); ?></label><br />
				<input type="text" id="hellio-otp-code" inputmode="numeric" autocomplete="one-time-code" style="max-width:160px;" />
				<button type="button" class="button" id="hellio-otp-verify"><?php echo esc_html__( 'Verify', 'hellio-sms' ); ?></button>
			</p>
			<p class="hellio-otp-status" id="hellio-otp-status" aria-live="polite"></p>
			<input type="hidden" id="hellio-otp-verified" name="hellio_otp_verified" value="<?php echo esc_attr( $this->current_verified_phone() ? '1' : '0' ); ?>" />
		</div>
		<?php
	}

	/**
	 * Enqueue the checkout script only on the checkout page.
	 */
	public function enqueue_assets() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}

		if ( ! $this->is_active() ) {
			return;
		}

		$handle = 'hellio-sms-otp';
		wp_register_script( $handle, '', array( 'jquery' ), HELLIO_SMS_VERSION, true );
		wp_enqueue_script( $handle );
		wp_add_inline_script( $handle, $this->inline_js() );

		wp_localize_script(
			$handle,
			'HellioOtp',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE ),
				'sending'  => esc_html__( 'Sending...', 'hellio-sms' ),
				'sendCode' => esc_html__( 'Send code', 'hellio-sms' ),
				'resend'   => esc_html__( 'Resend code', 'hellio-sms' ),
				'verifying'=> esc_html__( 'Verifying...', 'hellio-sms' ),
				'verify'   => esc_html__( 'Verify', 'hellio-sms' ),
				'noPhone'  => esc_html__( 'Enter your phone number first.', 'hellio-sms' ),
				'verified' => esc_html__( 'Phone verified. You can place your order.', 'hellio-sms' ),
			)
		);
	}

	/**
	 * Inline checkout JS.
	 *
	 * @return string
	 */
	protected function inline_js() {
		return <<<'JS'
jQuery(function ($) {
	var $status = $('#hellio-otp-status');
	var $codeRow = $('.hellio-otp-code-row');
	var $verified = $('#hellio-otp-verified');

	function phone() {
		return $.trim($('#billing_phone').val() || '');
	}
	function setStatus(msg, ok) {
		$status.text(msg).css('color', ok ? '#155724' : '#721c24');
	}
	function markVerified(state) {
		$verified.val(state ? '1' : '0');
	}

	$('#billing_phone').on('input change', function () {
		markVerified(false);
	});

	$('#hellio-otp-send').on('click', function (e) {
		e.preventDefault();
		var p = phone();
		if (!p) { setStatus(HellioOtp.noPhone, false); return; }
		var $btn = $(this).prop('disabled', true).text(HellioOtp.sending);
		$.post(HellioOtp.ajaxUrl, {
			action: 'hellio_sms_otp_send',
			nonce: HellioOtp.nonce,
			phone: p
		}).done(function (res) {
			if (res && res.success) {
				$codeRow.show();
				setStatus(res.data.message, true);
				$btn.text(HellioOtp.resend);
			} else {
				setStatus((res && res.data && res.data.message) ? res.data.message : 'Could not send code.', false);
				$btn.text(HellioOtp.sendCode);
			}
		}).fail(function () {
			setStatus('Could not send code.', false);
			$btn.text(HellioOtp.sendCode);
		}).always(function () {
			$btn.prop('disabled', false);
		});
	});

	$('#hellio-otp-verify').on('click', function (e) {
		e.preventDefault();
		var p = phone();
		var code = $.trim($('#hellio-otp-code').val() || '');
		if (!p || !code) { setStatus('Enter the code you received.', false); return; }
		var $btn = $(this).prop('disabled', true).text(HellioOtp.verifying);
		$.post(HellioOtp.ajaxUrl, {
			action: 'hellio_sms_otp_verify',
			nonce: HellioOtp.nonce,
			phone: p,
			code: code
		}).done(function (res) {
			if (res && res.success && res.data.verified) {
				markVerified(true);
				setStatus(HellioOtp.verified, true);
			} else {
				markVerified(false);
				setStatus((res && res.data && res.data.message) ? res.data.message : 'Invalid code.', false);
			}
		}).fail(function () {
			setStatus('Verification failed.', false);
		}).always(function () {
			$btn.prop('disabled', false).text(HellioOtp.verify);
		});
	});
});
JS;
	}

	/**
	 * AJAX: send an OTP to the posted phone.
	 */
	public function ajax_send() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! $this->is_active() ) {
			wp_send_json_error( array( 'message' => __( 'Phone verification is not enabled.', 'hellio-sms' ) ) );
		}

		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

		if ( '' === $phone ) {
			wp_send_json_error( array( 'message' => __( 'Enter your phone number first.', 'hellio-sms' ) ) );
		}

		$sender = Hellio_SMS_Settings::get( 'sender_id', '' );
		$length = (int) Hellio_SMS_Settings::get( 'otp_length', 6 );
		$expiry = (int) Hellio_SMS_Settings::get( 'otp_expiry', 5 );

		$client = Hellio_SMS_Client::from_settings();
		$result = $client->send_otp( $phone, $sender, $length, $expiry, 'checkout' );

		if ( ! empty( $result['success'] ) ) {
			// A new send invalidates any prior verification for a different number.
			$this->set_verified_phone( '' );

			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %d is the expiry in minutes. */
						__( 'Code sent. It expires in %d minutes.', 'hellio-sms' ),
						$expiry
					),
				)
			);
		}

		$message = ! empty( $result['message'] ) ? $result['message'] : __( 'Could not send the code.', 'hellio-sms' );
		wp_send_json_error( array( 'message' => $message ) );
	}

	/**
	 * AJAX: verify the posted code for the posted phone.
	 */
	public function ajax_verify() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! $this->is_active() ) {
			wp_send_json_error( array( 'message' => __( 'Phone verification is not enabled.', 'hellio-sms' ) ) );
		}

		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$code  = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		if ( '' === $phone || '' === $code ) {
			wp_send_json_error( array( 'message' => __( 'Enter the code you received.', 'hellio-sms' ) ) );
		}

		$client = Hellio_SMS_Client::from_settings();
		$result = $client->verify_otp( $phone, $code );

		if ( ! empty( $result['verified'] ) ) {
			$this->set_verified_phone( $client->normalize_phone( $phone ) );

			wp_send_json_success(
				array(
					'verified' => true,
					'message'  => __( 'Phone verified.', 'hellio-sms' ),
				)
			);
		}

		$message = ! empty( $result['message'] ) ? $result['message'] : __( 'Invalid code.', 'hellio-sms' );
		wp_send_json_error(
			array(
				'verified' => false,
				'message'  => $message,
			)
		);
	}

	/**
	 * Block checkout until the billing phone is verified.
	 *
	 * @param array    $data   Posted checkout data.
	 * @param WP_Error $errors Error bag.
	 */
	public function validate_verified( $data, $errors = null ) {
		if ( ! $this->is_active() ) {
			return;
		}

		$phone = isset( $data['billing_phone'] ) ? $data['billing_phone'] : '';

		if ( '' === trim( (string) $phone ) ) {
			// The core phone-required rule will handle an empty phone.
			return;
		}

		$client     = Hellio_SMS_Client::from_settings();
		$normalized = $client->normalize_phone( $phone );
		$verified   = $this->current_verified_phone();

		if ( $verified && $verified === $normalized ) {
			return;
		}

		if ( $errors instanceof WP_Error ) {
			$errors->add(
				'hellio_otp_required',
				__( 'Please verify your phone number with the code we texted you before placing your order.', 'hellio-sms' )
			);
		} else {
			wc_add_notice(
				__( 'Please verify your phone number with the code we texted you before placing your order.', 'hellio-sms' ),
				'error'
			);
		}
	}

	/**
	 * Store the verified phone in the WC session.
	 *
	 * @param string $phone Normalized phone (empty clears it).
	 */
	protected function set_verified_phone( $phone ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		WC()->session->set( self::SESSION_KEY, (string) $phone );
	}

	/**
	 * Read the verified phone from the WC session.
	 *
	 * @return string
	 */
	protected function current_verified_phone() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return '';
		}

		return (string) WC()->session->get( self::SESSION_KEY, '' );
	}

	/**
	 * Clear the session verification (called once an order is placed).
	 */
	public function clear_session() {
		$this->set_verified_phone( '' );
	}
}
