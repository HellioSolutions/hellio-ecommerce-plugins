<?php
/**
 * Settings screen and stored-option access.
 *
 * Registers a "Hellio SMS" page under the WooCommerce menu using the
 * WordPress Settings API. All values live in a single option array.
 *
 * @package Hellio_SMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hellio_SMS_Settings
 */
class Hellio_SMS_Settings {

	const OPTION       = 'hellio_sms_settings';
	const OPTION_GROUP = 'hellio_sms_settings_group';
	const PAGE         = 'hellio-sms-settings';
	const NONCE_TEST   = 'hellio_sms_test_connection';
	const NONCE_CONNECT = 'hellio_sms_connect';
	const NONCE_SEND_TEST = 'hellio_sms_send_test';

	/**
	 * Singleton instance.
	 *
	 * @var Hellio_SMS_Settings|null
	 */
	protected static $instance = null;

	/**
	 * Get the singleton.
	 *
	 * @return Hellio_SMS_Settings
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hook up admin screens and AJAX.
	 */
	protected function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_hellio_sms_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_hellio_sms_connect', array( $this, 'ajax_connect' ) );
		add_action( 'wp_ajax_hellio_sms_disconnect', array( $this, 'ajax_disconnect' ) );
		add_action( 'wp_ajax_hellio_sms_send_test', array( $this, 'ajax_send_test' ) );
	}

	/**
	 * Persist a subset of settings keys without disturbing the rest.
	 *
	 * @param array $changes Map of key to value.
	 */
	public static function update_keys( array $changes ) {
		$current = get_option( self::OPTION, array() );

		if ( ! is_array( $current ) ) {
			$current = array();
		}

		foreach ( $changes as $key => $value ) {
			$current[ $key ] = $value;
		}

		update_option( self::OPTION, $current );
	}

	/**
	 * WooCommerce order statuses this plugin can message on.
	 *
	 * @return array Map of status slug to label.
	 */
	public static function statuses() {
		return array(
			'pending'    => __( 'Pending payment', 'hellio-sms' ),
			'processing' => __( 'Processing', 'hellio-sms' ),
			'on-hold'    => __( 'On hold', 'hellio-sms' ),
			'completed'  => __( 'Completed', 'hellio-sms' ),
			'cancelled'  => __( 'Cancelled', 'hellio-sms' ),
			'refunded'   => __( 'Refunded', 'hellio-sms' ),
			'failed'     => __( 'Failed', 'hellio-sms' ),
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		$defaults = array(
			'enabled'              => 'no',
			'api_base_url'         => 'https://api.helliomessaging.com',
			'api_token'            => '',
			'connected_email'      => '',
			'sender_id'            => '',
			'default_dial_code'    => '233',
			'admin_alert_enabled'  => 'no',
			'admin_alert_numbers'  => '',
			'admin_alert_template' => 'New order {order_number} for {order_total} {currency} from {customer_name} on {store_name}.',
			'otp_enabled'          => 'no',
			'otp_length'           => 6,
			'otp_expiry'           => 5,
		);

		foreach ( array_keys( self::statuses() ) as $status ) {
			$defaults[ 'status_' . $status . '_enabled' ]  = 'no';
			$defaults[ 'status_' . $status . '_template' ] = 'Hi {customer_first_name}, your {store_name} order {order_number} is now {order_status}. Total: {order_total} {currency}.';
		}

		return $defaults;
	}

	/**
	 * Read the full settings array merged over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Read a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when unset.
	 * @return mixed
	 */
	public static function get( $key, $default = '' ) {
		$all = self::all();

		if ( array_key_exists( $key, $all ) && '' !== $all[ $key ] && null !== $all[ $key ] ) {
			return $all[ $key ];
		}

		return $default;
	}

	/**
	 * True when the master toggle is on.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return 'yes' === self::get( 'enabled', 'no' );
	}

	/**
	 * Register the WooCommerce submenu page.
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Hellio SMS', 'hellio-sms' ),
			__( 'Hellio SMS', 'hellio-sms' ),
			'manage_woocommerce',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the option and its sanitizer.
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize the whole settings array on save.
	 *
	 * @param array $input Raw posted values.
	 * @return array
	 */
	public function sanitize( $input ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return self::all();
		}

		$input    = is_array( $input ) ? $input : array();
		$out      = array();
		$existing = self::all();

		// Preserve the connected account label. It is set by the Connect flow,
		// not by this form, so keep whatever was stored.
		$out['connected_email'] = isset( $existing['connected_email'] ) ? sanitize_email( $existing['connected_email'] ) : '';

		$out['enabled']           = ( isset( $input['enabled'] ) && 'yes' === $input['enabled'] ) ? 'yes' : 'no';
		$out['api_base_url']      = esc_url_raw( isset( $input['api_base_url'] ) ? trim( $input['api_base_url'] ) : '' );
		if ( '' === $out['api_base_url'] ) {
			$out['api_base_url'] = 'https://api.helliomessaging.com';
		}
		$out['api_token']         = sanitize_text_field( isset( $input['api_token'] ) ? trim( $input['api_token'] ) : '' );
		$out['sender_id']         = sanitize_text_field( isset( $input['sender_id'] ) ? $input['sender_id'] : '' );
		$out['sender_id']         = substr( $out['sender_id'], 0, 11 );
		$out['default_dial_code'] = preg_replace( '/\D/', '', isset( $input['default_dial_code'] ) ? $input['default_dial_code'] : '' );

		foreach ( array_keys( self::statuses() ) as $status ) {
			$en_key  = 'status_' . $status . '_enabled';
			$tpl_key = 'status_' . $status . '_template';

			$out[ $en_key ]  = ( isset( $input[ $en_key ] ) && 'yes' === $input[ $en_key ] ) ? 'yes' : 'no';
			$out[ $tpl_key ] = sanitize_textarea_field( isset( $input[ $tpl_key ] ) ? $input[ $tpl_key ] : '' );
		}

		$out['admin_alert_enabled']  = ( isset( $input['admin_alert_enabled'] ) && 'yes' === $input['admin_alert_enabled'] ) ? 'yes' : 'no';
		$out['admin_alert_numbers']  = sanitize_text_field( isset( $input['admin_alert_numbers'] ) ? $input['admin_alert_numbers'] : '' );
		$out['admin_alert_template'] = sanitize_textarea_field( isset( $input['admin_alert_template'] ) ? $input['admin_alert_template'] : '' );

		$out['otp_enabled'] = ( isset( $input['otp_enabled'] ) && 'yes' === $input['otp_enabled'] ) ? 'yes' : 'no';
		$out['otp_length']  = max( 4, min( 10, (int) ( isset( $input['otp_length'] ) ? $input['otp_length'] : 6 ) ) );
		$out['otp_expiry']  = max( 1, min( 1440, (int) ( isset( $input['otp_expiry'] ) ? $input['otp_expiry'] : 5 ) ) );

		return $out;
	}

	/**
	 * Enqueue the tiny admin script for the Test connection button.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_' . self::PAGE !== $hook ) {
			return;
		}

		$handle = 'hellio-sms-admin';
		wp_register_script( $handle, '', array( 'jquery' ), HELLIO_SMS_VERSION, true );
		wp_enqueue_script( $handle );

		$inline = $this->admin_inline_js();
		wp_add_inline_script( $handle, $inline );

		wp_localize_script(
			$handle,
			'HellioSmsAdmin',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( self::NONCE_TEST ),
				'nonceConnect'    => wp_create_nonce( self::NONCE_CONNECT ),
				'nonceSendTest'   => wp_create_nonce( self::NONCE_SEND_TEST ),
				'testing'         => esc_html__( 'Testing...', 'hellio-sms' ),
				'test'            => esc_html__( 'Test connection', 'hellio-sms' ),
				'connecting'      => esc_html__( 'Connecting...', 'hellio-sms' ),
				'connect'         => esc_html__( 'Connect', 'hellio-sms' ),
				'disconnecting'   => esc_html__( 'Disconnecting...', 'hellio-sms' ),
				'confirmDisc'     => esc_html__( 'Disconnect this Hellio account? Your stored token will be cleared.', 'hellio-sms' ),
				'sending'         => esc_html__( 'Sending...', 'hellio-sms' ),
				'sendTest'        => esc_html__( 'Send', 'hellio-sms' ),
			)
		);
	}

	/**
	 * Inline JS for the Test connection button.
	 *
	 * @return string
	 */
	protected function admin_inline_js() {
		return <<<'JS'
jQuery(function ($) {
	var $btn = $('#hellio-sms-test-connection');
	var $out = $('#hellio-sms-test-result');
	$btn.on('click', function (e) {
		e.preventDefault();
		$btn.prop('disabled', true).text(HellioSmsAdmin.testing);
		$out.removeClass('notice-success notice-error').text('');
		$.post(HellioSmsAdmin.ajaxUrl, {
			action: 'hellio_sms_test_connection',
			nonce: HellioSmsAdmin.nonce,
			api_base_url: $('#hellio_api_base_url').val(),
			api_token: $('#hellio_api_token').val()
		}).done(function (res) {
			if (res && res.success) {
				$out.addClass('notice notice-success').css('padding', '8px 12px').text(res.data.message);
			} else {
				var msg = (res && res.data && res.data.message) ? res.data.message : 'Request failed.';
				$out.addClass('notice notice-error').css('padding', '8px 12px').text(msg);
			}
		}).fail(function () {
			$out.addClass('notice notice-error').css('padding', '8px 12px').text('Request failed.');
		}).always(function () {
			$btn.prop('disabled', false).text(HellioSmsAdmin.test);
		});
	});

	// Connect with Hellio login.
	var $connectOut = $('#hellio-connect-result');
	function connectMsg(msg, ok) {
		$connectOut.attr('class', 'notice ' + (ok ? 'notice-success' : 'notice-error')).css('padding', '8px 12px').text(msg);
	}
	$('#hellio-connect-btn').on('click', function (e) {
		e.preventDefault();
		var $btn = $(this).prop('disabled', true).text(HellioSmsAdmin.connecting);
		$.post(HellioSmsAdmin.ajaxUrl, {
			action: 'hellio_sms_connect',
			nonce: HellioSmsAdmin.nonceConnect,
			api_base_url: $('#hellio_api_base_url').val(),
			email: $('#hellio_connect_email').val(),
			password: $('#hellio_connect_password').val(),
			two_factor_code: $('#hellio_connect_2fa').val()
		}).done(function (res) {
			if (res && res.success) {
				connectMsg(res.data.message, true);
				setTimeout(function () { window.location.reload(); }, 900);
			} else if (res && res.data && res.data.two_factor_required) {
				$('#hellio-2fa-row').show();
				connectMsg(res.data.message, false);
			} else {
				connectMsg((res && res.data && res.data.message) ? res.data.message : 'Could not connect.', false);
			}
		}).fail(function () {
			connectMsg('Could not connect.', false);
		}).always(function () {
			$btn.prop('disabled', false).text(HellioSmsAdmin.connect);
		});
	});
	$('#hellio-disconnect-btn').on('click', function (e) {
		e.preventDefault();
		if (!window.confirm(HellioSmsAdmin.confirmDisc)) { return; }
		var $btn = $(this).prop('disabled', true).text(HellioSmsAdmin.disconnecting);
		$.post(HellioSmsAdmin.ajaxUrl, {
			action: 'hellio_sms_disconnect',
			nonce: HellioSmsAdmin.nonceConnect
		}).done(function () {
			window.location.reload();
		}).fail(function () {
			$btn.prop('disabled', false);
		});
	});

	// Send test SMS.
	var $testOut = $('#hellio-sendtest-result');
	$('#hellio-sendtest-btn').on('click', function (e) {
		e.preventDefault();
		var $btn = $(this).prop('disabled', true).text(HellioSmsAdmin.sending);
		$testOut.attr('class', '').text('');
		$.post(HellioSmsAdmin.ajaxUrl, {
			action: 'hellio_sms_send_test',
			nonce: HellioSmsAdmin.nonceSendTest,
			recipients: $('#hellio_test_recipients').val(),
			sender: $('#hellio_test_sender').val(),
			message: $('#hellio_test_message').val()
		}).done(function (res) {
			var ok = !!(res && res.success);
			var msg = (res && res.data && res.data.message) ? res.data.message : (ok ? 'Sent.' : 'Failed.');
			$testOut.attr('class', 'notice ' + (ok ? 'notice-success' : 'notice-error')).css('padding', '8px 12px').text(msg);
		}).fail(function () {
			$testOut.attr('class', 'notice notice-error').css('padding', '8px 12px').text('Request failed.');
		}).always(function () {
			$btn.prop('disabled', false).text(HellioSmsAdmin.sendTest);
		});
	});
});
JS;
	}

	/**
	 * AJAX handler for the Test connection button. Calls GET /v1/balance.
	 */
	public function ajax_test_connection() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'hellio-sms' ) ), 403 );
		}

		check_ajax_referer( self::NONCE_TEST, 'nonce' );

		$base_url = isset( $_POST['api_base_url'] ) ? esc_url_raw( wp_unslash( $_POST['api_base_url'] ) ) : '';
		$token    = isset( $_POST['api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['api_token'] ) ) : '';

		if ( '' === $base_url ) {
			$base_url = self::get( 'api_base_url', 'https://api.helliomessaging.com' );
		}

		// Allow testing without retyping a saved token.
		if ( '' === $token ) {
			$token = self::get( 'api_token', '' );
		}

		if ( '' === $token ) {
			wp_send_json_error( array( 'message' => __( 'Enter an API token first.', 'hellio-sms' ) ) );
		}

		$client = new Hellio_SMS_Client( $base_url, $token, self::get( 'default_dial_code', '' ) );
		$result = $client->get_balance();

		if ( ! empty( $result['success'] ) ) {
			$data    = is_array( $result['data'] ) ? $result['data'] : array();
			$balance = '';

			if ( isset( $data['balance'] ) ) {
				$currency = isset( $data['currency'] ) ? ' ' . $data['currency'] : '';
				$balance  = ' ' . sprintf(
					/* translators: %s is the wallet balance. */
					__( 'Balance: %s', 'hellio-sms' ),
					$data['balance'] . $currency
				);
			}

			wp_send_json_success(
				array(
					'message' => __( 'Connection successful.', 'hellio-sms' ) . $balance,
				)
			);
		}

		$message = ! empty( $result['message'] ) ? $result['message'] : __( 'Connection failed.', 'hellio-sms' );
		wp_send_json_error( array( 'message' => $message ) );
	}

	/**
	 * AJAX: connect with a Hellio login. POSTs to /v1/auth/token and stores the
	 * returned token. The password is never stored.
	 */
	public function ajax_connect() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'hellio-sms' ) ), 403 );
		}

		check_ajax_referer( self::NONCE_CONNECT, 'nonce' );

		$base_url = isset( $_POST['api_base_url'] ) ? esc_url_raw( wp_unslash( $_POST['api_base_url'] ) ) : '';
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		// Password is passed straight to the API and never stored. Only unslash it.
		$password = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';
		$two_fa   = isset( $_POST['two_factor_code'] ) ? sanitize_text_field( wp_unslash( $_POST['two_factor_code'] ) ) : '';

		if ( '' === $base_url ) {
			$base_url = self::get( 'api_base_url', 'https://api.helliomessaging.com' );
		}

		if ( '' === $email || '' === $password ) {
			wp_send_json_error( array( 'message' => __( 'Enter your Hellio email and password.', 'hellio-sms' ) ) );
		}

		$client = new Hellio_SMS_Client( $base_url, '', self::get( 'default_dial_code', '' ) );
		$result = $client->create_token( $email, $password, 'WooCommerce', '' !== $two_fa ? $two_fa : null );

		if ( ! empty( $result['success'] ) ) {
			$data  = is_array( $result['data'] ) ? $result['data'] : array();
			$token = isset( $data['token'] ) ? (string) $data['token'] : '';

			if ( '' === $token ) {
				wp_send_json_error( array( 'message' => __( 'The server did not return a token.', 'hellio-sms' ) ) );
			}

			$user_email = isset( $data['user']['email'] ) ? sanitize_email( $data['user']['email'] ) : $email;

			self::update_keys(
				array(
					'api_token'       => sanitize_text_field( $token ),
					'connected_email' => $user_email,
					'api_base_url'    => $base_url,
				)
			);

			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %s is the connected account email. */
						__( 'Connected as %s.', 'hellio-sms' ),
						$user_email
					),
					'email'   => $user_email,
				)
			);
		}

		// Surface the 2FA requirement so the browser can reveal the code field.
		if ( 'two_factor_required' === $result['error'] ) {
			wp_send_json_error(
				array(
					'two_factor_required' => true,
					'message'             => ! empty( $result['message'] ) ? $result['message'] : __( 'Enter your two-factor code to continue.', 'hellio-sms' ),
				)
			);
		}

		$message = ! empty( $result['message'] ) ? $result['message'] : __( 'Could not connect. Check your credentials.', 'hellio-sms' );
		wp_send_json_error( array( 'message' => $message ) );
	}

	/**
	 * AJAX: disconnect. Clears the stored token and connected email.
	 */
	public function ajax_disconnect() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'hellio-sms' ) ), 403 );
		}

		check_ajax_referer( self::NONCE_CONNECT, 'nonce' );

		self::update_keys(
			array(
				'api_token'       => '',
				'connected_email' => '',
			)
		);

		wp_send_json_success( array( 'message' => __( 'Disconnected.', 'hellio-sms' ) ) );
	}

	/**
	 * AJAX: send an ad-hoc SMS to one or many pasted numbers. Renders the
	 * placeholders once against the most recent order (or blank) and sends via
	 * send_sms, chunking at 500 for very long pasted lists.
	 */
	public function ajax_send_test() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'hellio-sms' ) ), 403 );
		}

		check_ajax_referer( self::NONCE_SEND_TEST, 'nonce' );

		$recipients_raw = isset( $_POST['recipients'] ) ? sanitize_textarea_field( wp_unslash( $_POST['recipients'] ) ) : '';
		$sender         = isset( $_POST['sender'] ) ? sanitize_text_field( wp_unslash( $_POST['sender'] ) ) : '';
		$message        = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		// Split on comma, whitespace or newline, then trim and dedupe.
		$recipients = preg_split( '/[\s,]+/', $recipients_raw, -1, PREG_SPLIT_NO_EMPTY );
		$recipients = array_values( array_unique( array_filter( array_map( 'trim', (array) $recipients ) ) ) );

		if ( empty( $recipients ) ) {
			wp_send_json_error( array( 'message' => __( 'Enter at least one recipient number.', 'hellio-sms' ) ) );
		}

		if ( '' === trim( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'Enter a message to send.', 'hellio-sms' ) ) );
		}

		if ( '' === $sender ) {
			$sender = self::get( 'sender_id', '' );
		}

		// Render placeholders once against the store's most recent order, if any.
		$orders = function_exists( 'wc_get_orders' )
			? wc_get_orders(
				array(
					'limit'   => 1,
					'orderby' => 'date',
					'order'   => 'DESC',
				)
			)
			: array();

		$sample = ( ! empty( $orders ) && $orders[0] instanceof WC_Order ) ? $orders[0] : null;
		$body   = $sample ? Hellio_SMS_Order_SMS::render_template( $message, $sample ) : $this->strip_placeholders( $message );

		$client    = Hellio_SMS_Client::from_settings();
		$accepted  = 0;
		$invalid   = 0;
		$reference = '';
		$status    = '';
		$errors    = array();
		$any_ok    = false;

		foreach ( array_chunk( $recipients, 500 ) as $chunk ) {
			$result = $client->send_sms( $chunk, $sender, $body );

			if ( ! empty( $result['success'] ) ) {
				$any_ok = true;
				$data   = is_array( $result['data'] ) ? $result['data'] : array();

				$accepted += isset( $data['accepted_recipients'] ) ? (int) $data['accepted_recipients'] : count( $chunk );
				$invalid  += isset( $data['invalid_recipients'] ) ? (int) $data['invalid_recipients'] : 0;

				if ( '' === $reference && isset( $data['reference'] ) ) {
					$reference = (string) $data['reference'];
				}
				if ( '' === $status && isset( $data['status'] ) ) {
					$status = (string) $data['status'];
				}
			} else {
				$invalid += count( $chunk );
				$errors[] = $result['message'];
			}
		}

		if ( $any_ok ) {
			$detail = array();

			$detail[] = sprintf(
				/* translators: %d is the number of accepted recipients. */
				_n( 'Accepted: %d recipient.', 'Accepted: %d recipients.', $accepted, 'hellio-sms' ),
				$accepted
			);
			if ( '' !== $status ) {
				$detail[] = sprintf(
					/* translators: %s is the campaign status. */
					__( 'Status: %s', 'hellio-sms' ),
					$status
				);
			}
			if ( '' !== $reference ) {
				$detail[] = sprintf(
					/* translators: %s is the message reference. */
					__( 'Reference: %s', 'hellio-sms' ),
					$reference
				);
			}
			if ( ! empty( $errors ) ) {
				$detail[] = implode( ' ', array_unique( $errors ) );
			}

			wp_send_json_success(
				array(
					'message' => trim( __( 'SMS sent.', 'hellio-sms' ) . ' ' . implode( ' ', $detail ) ),
					'preview' => $body,
				)
			);
		}

		$message_out = ! empty( $errors ) ? implode( ' ', array_unique( $errors ) ) : __( 'Could not send the SMS.', 'hellio-sms' );
		wp_send_json_error( array( 'message' => $message_out ) );
	}

	/**
	 * Remove any placeholder token from a string (used when no sample order).
	 *
	 * @param string $text Input.
	 * @return string
	 */
	protected function strip_placeholders( $text ) {
		return trim( preg_replace( '/\{[a-z0-9_]+\}/i', '', (string) $text ) );
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$s        = self::all();
		$statuses = self::statuses();
		$name     = self::OPTION;
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Hellio Messaging for WooCommerce', 'hellio-sms' ); ?></h1>
			<p><?php echo esc_html__( 'Send order-status SMS, admin alerts, checkout OTP and bulk messages through Hellio Messaging.', 'hellio-sms' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<h2><?php echo esc_html__( 'API connection', 'hellio-sms' ); ?></h2>

				<?php $connected_email = isset( $s['connected_email'] ) ? $s['connected_email'] : ''; ?>
				<div class="hellio-connect-panel" style="max-width:640px;padding:12px 16px;margin:0 0 12px;border:1px solid #e0e0e0;border-radius:4px;background:#fbfbfb;">
					<h3 style="margin-top:0;"><?php echo esc_html__( 'Connect with your Hellio login', 'hellio-sms' ); ?></h3>
					<?php if ( '' !== $connected_email && '' !== $s['api_token'] ) : ?>
						<p>
							<?php
							printf(
								/* translators: %s is the connected account email. */
								esc_html__( 'Connected as %s.', 'hellio-sms' ),
								'<strong>' . esc_html( $connected_email ) . '</strong>'
							);
							?>
						</p>
						<p>
							<button type="button" class="button" id="hellio-disconnect-btn"><?php echo esc_html__( 'Disconnect', 'hellio-sms' ); ?></button>
						</p>
					<?php else : ?>
						<p class="description"><?php echo esc_html__( 'Sign in once with your Hellio account and we will fetch an API token for you. Your password is never stored.', 'hellio-sms' ); ?></p>
						<p>
							<label for="hellio_connect_email"><?php echo esc_html__( 'Email', 'hellio-sms' ); ?></label><br />
							<input type="email" class="regular-text" id="hellio_connect_email" autocomplete="off" />
						</p>
						<p>
							<label for="hellio_connect_password"><?php echo esc_html__( 'Password', 'hellio-sms' ); ?></label><br />
							<input type="password" class="regular-text" id="hellio_connect_password" autocomplete="off" />
						</p>
						<p id="hellio-2fa-row" style="display:none;">
							<label for="hellio_connect_2fa"><?php echo esc_html__( 'Two-factor code', 'hellio-sms' ); ?></label><br />
							<input type="text" class="regular-text" id="hellio_connect_2fa" inputmode="numeric" autocomplete="off" />
						</p>
						<p>
							<button type="button" class="button button-primary" id="hellio-connect-btn"><?php echo esc_html__( 'Connect', 'hellio-sms' ); ?></button>
							<span id="hellio-connect-result" style="display:inline-block;margin-left:10px;"></span>
						</p>
						<p class="description"><?php echo esc_html__( 'Prefer a token? Paste one into the API token field below instead.', 'hellio-sms' ); ?></p>
					<?php endif; ?>
				</div>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Enable Hellio SMS', 'hellio-sms' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="yes" <?php checked( 'yes', $s['enabled'] ); ?> />
								<?php echo esc_html__( 'Master switch. When off, no messages are sent.', 'hellio-sms' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hellio_api_base_url"><?php echo esc_html__( 'API base URL', 'hellio-sms' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="hellio_api_base_url" name="<?php echo esc_attr( $name ); ?>[api_base_url]" value="<?php echo esc_attr( $s['api_base_url'] ); ?>" placeholder="https://api.helliomessaging.com" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hellio_api_token"><?php echo esc_html__( 'API token', 'hellio-sms' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="hellio_api_token" name="<?php echo esc_attr( $name ); ?>[api_token]" value="<?php echo esc_attr( $s['api_token'] ); ?>" autocomplete="off" />
							<p class="description"><?php echo esc_html__( 'A Sanctum personal access token from your Hellio dashboard. Stored server-side, never exposed to the browser.', 'hellio-sms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hellio_sender_id"><?php echo esc_html__( 'Sender ID', 'hellio-sms' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="hellio_sender_id" name="<?php echo esc_attr( $name ); ?>[sender_id]" value="<?php echo esc_attr( $s['sender_id'] ); ?>" maxlength="11" />
							<p class="description"><?php echo esc_html__( 'Approved Sender ID, up to 11 characters.', 'hellio-sms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hellio_dial_code"><?php echo esc_html__( 'Default dial code', 'hellio-sms' ); ?></label></th>
						<td>
							<input type="text" class="small-text" id="hellio_dial_code" name="<?php echo esc_attr( $name ); ?>[default_dial_code]" value="<?php echo esc_attr( $s['default_dial_code'] ); ?>" />
							<p class="description"><?php echo esc_html__( 'Country code (digits only, for example 233). Local numbers lose a leading 0 and gain this code.', 'hellio-sms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Test connection', 'hellio-sms' ); ?></th>
						<td>
							<button type="button" class="button" id="hellio-sms-test-connection"><?php echo esc_html__( 'Test connection', 'hellio-sms' ); ?></button>
							<span id="hellio-sms-test-result" style="display:inline-block;margin-left:10px;"></span>
							<p class="description"><?php echo esc_html__( 'Checks your token against GET /v1/balance.', 'hellio-sms' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Send SMS', 'hellio-sms' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'Send a message to one number or many. Use it to test, or to fire off a quick ad-hoc send. Placeholders render against your most recent order, or blank if you have none yet. For audience-based sends, use the Bulk SMS page.', 'hellio-sms' ); ?></p>
				<?php echo wp_kses_post( $this->placeholder_help() ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="hellio_test_recipients"><?php echo esc_html__( 'Recipients', 'hellio-sms' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="3" id="hellio_test_recipients" placeholder="233241111111&#10;233242222222"></textarea>
							<p class="description"><?php echo esc_html__( 'One number, or many separated by comma, space or newline.', 'hellio-sms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hellio_test_sender"><?php echo esc_html__( 'Sender ID', 'hellio-sms' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="hellio_test_sender" maxlength="11" value="<?php echo esc_attr( $s['sender_id'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hellio_test_message"><?php echo esc_html__( 'Message', 'hellio-sms' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="3" id="hellio_test_message" maxlength="1600"><?php echo esc_textarea( __( 'Hi {customer_first_name}, this is a test from {store_name}.', 'hellio-sms' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">&nbsp;</th>
						<td>
							<button type="button" class="button" id="hellio-sendtest-btn"><?php echo esc_html__( 'Send', 'hellio-sms' ); ?></button>
							<div id="hellio-sendtest-result" style="margin-top:8px;"></div>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Customer order-status SMS', 'hellio-sms' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'Enable a status and edit its message. The customer billing phone receives it when an order reaches that status.', 'hellio-sms' ); ?></p>
				<?php echo wp_kses_post( $this->placeholder_help() ); ?>
				<table class="form-table" role="presentation">
					<?php foreach ( $statuses as $slug => $label ) : ?>
						<?php
						$en_key  = 'status_' . $slug . '_enabled';
						$tpl_key = 'status_' . $slug . '_template';
						?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $name . '[' . $en_key . ']' ); ?>" value="yes" <?php checked( 'yes', $s[ $en_key ] ); ?> />
									<?php echo esc_html__( 'Send SMS on this status', 'hellio-sms' ); ?>
								</label>
								<br />
								<textarea class="large-text" rows="2" name="<?php echo esc_attr( $name . '[' . $tpl_key . ']' ); ?>"><?php echo esc_textarea( $s[ $tpl_key ] ); ?></textarea>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php echo esc_html__( 'Admin new-order alert', 'hellio-sms' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Enable admin alert', 'hellio-sms' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[admin_alert_enabled]" value="yes" <?php checked( 'yes', $s['admin_alert_enabled'] ); ?> />
								<?php echo esc_html__( 'SMS staff when a new order is placed.', 'hellio-sms' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hellio_admin_numbers"><?php echo esc_html__( 'Admin numbers', 'hellio-sms' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="hellio_admin_numbers" name="<?php echo esc_attr( $name ); ?>[admin_alert_numbers]" value="<?php echo esc_attr( $s['admin_alert_numbers'] ); ?>" />
							<p class="description"><?php echo esc_html__( 'Comma separated phone numbers.', 'hellio-sms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Alert template', 'hellio-sms' ); ?></th>
						<td>
							<textarea class="large-text" rows="2" name="<?php echo esc_attr( $name ); ?>[admin_alert_template]"><?php echo esc_textarea( $s['admin_alert_template'] ); ?></textarea>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Checkout OTP', 'hellio-sms' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Enable checkout OTP', 'hellio-sms' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[otp_enabled]" value="yes" <?php checked( 'yes', $s['otp_enabled'] ); ?> />
								<?php echo esc_html__( 'Require the customer to verify their phone before placing an order.', 'hellio-sms' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hellio_otp_length"><?php echo esc_html__( 'Code length', 'hellio-sms' ); ?></label></th>
						<td>
							<input type="number" class="small-text" id="hellio_otp_length" name="<?php echo esc_attr( $name ); ?>[otp_length]" value="<?php echo esc_attr( $s['otp_length'] ); ?>" min="4" max="10" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hellio_otp_expiry"><?php echo esc_html__( 'Expiry (minutes)', 'hellio-sms' ); ?></label></th>
						<td>
							<input type="number" class="small-text" id="hellio_otp_expiry" name="<?php echo esc_attr( $name ); ?>[otp_expiry]" value="<?php echo esc_attr( $s['otp_expiry'] ); ?>" min="1" max="1440" />
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr />
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=hellio-sms-bulk' ) ); ?>"><?php echo esc_html__( 'Open bulk SMS', 'hellio-sms' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Reusable placeholder help block.
	 *
	 * @return string
	 */
	public function placeholder_help() {
		$tokens = array(
			'{order_id}',
			'{order_number}',
			'{order_status}',
			'{order_total}',
			'{currency}',
			'{customer_name}',
			'{customer_first_name}',
			'{store_name}',
			'{shop_url}',
			'{tracking_url}',
			'{date}',
		);

		$html  = '<p class="description"><strong>' . esc_html__( 'Placeholders:', 'hellio-sms' ) . '</strong> ';
		$html .= esc_html( implode( ' ', $tokens ) );
		$html .= '</p>';

		return $html;
	}
}
