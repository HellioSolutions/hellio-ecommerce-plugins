<?php
/**
 * Hellio Messaging API client.
 *
 * Talks to the Hellio REST API using the WordPress HTTP API (wp_remote_*),
 * never raw curl. Every method returns a normalized result array so callers
 * can behave defensively and never break checkout or order save.
 *
 * @package Hellio_SMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hellio_SMS_Client
 */
class Hellio_SMS_Client {

	/**
	 * API base URL, no trailing slash.
	 *
	 * @var string
	 */
	protected $base_url;

	/**
	 * Bearer token.
	 *
	 * @var string
	 */
	protected $token;

	/**
	 * Default dial code, digits only (for example "233").
	 *
	 * @var string
	 */
	protected $dial_code;

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	protected $timeout = 15;

	/**
	 * Constructor.
	 *
	 * @param string $base_url  API base URL.
	 * @param string $token     Bearer token.
	 * @param string $dial_code Default dial code digits.
	 */
	public function __construct( $base_url, $token, $dial_code = '' ) {
		$this->base_url  = untrailingslashit( trim( (string) $base_url ) );
		$this->token     = trim( (string) $token );
		$this->dial_code = preg_replace( '/\D/', '', (string) $dial_code );
	}

	/**
	 * Build a client from stored settings.
	 *
	 * @return Hellio_SMS_Client
	 */
	public static function from_settings() {
		return new self(
			Hellio_SMS_Settings::get( 'api_base_url', 'https://api.helliomessaging.com' ),
			Hellio_SMS_Settings::get( 'api_token', '' ),
			Hellio_SMS_Settings::get( 'default_dial_code', '' )
		);
	}

	/**
	 * Normalize a phone number to international format (digits only, no plus).
	 *
	 * Rules:
	 *  - keep only digits (a leading "+" marks an already international number),
	 *  - a leading "0" is stripped and the default dial code is prepended,
	 *  - numbers with no "+" and no recognizable country code get the dial code.
	 *
	 * @param string $number Raw phone number.
	 * @return string Normalized number, or empty string when nothing usable.
	 */
	public function normalize_phone( $number ) {
		$number = trim( (string) $number );

		if ( '' === $number ) {
			return '';
		}

		$has_plus = ( 0 === strpos( $number, '+' ) );
		$digits   = preg_replace( '/\D/', '', $number );

		if ( '' === $digits ) {
			return '';
		}

		// Already international (started with +): trust the digits as given.
		if ( $has_plus ) {
			return $digits;
		}

		$dial = $this->dial_code;

		// Local number starting with 0: strip leading zeros, prepend dial code.
		if ( 0 === strpos( $digits, '0' ) ) {
			$digits = ltrim( $digits, '0' );

			if ( '' === $digits ) {
				return '';
			}

			return $dial . $digits;
		}

		// No dial code configured: return the digits as-is.
		if ( '' === $dial ) {
			return $digits;
		}

		// Already carries the country code.
		if ( 0 === strpos( $digits, $dial ) ) {
			return $digits;
		}

		return $dial . $digits;
	}

	/**
	 * Send a transactional or marketing SMS to one or more recipients.
	 *
	 * @param array  $recipients Array of phone numbers.
	 * @param string $sender     Sender ID (max 11 chars).
	 * @param string $message    Message body (max 1600 chars).
	 * @return array Normalized result.
	 */
	public function send_sms( array $recipients, $sender, $message ) {
		$normalized = array();

		foreach ( $recipients as $recipient ) {
			$phone = $this->normalize_phone( $recipient );

			if ( '' !== $phone ) {
				$normalized[] = $phone;
			}
		}

		$normalized = array_values( array_unique( $normalized ) );

		if ( empty( $normalized ) ) {
			return $this->error_result( 0, __( 'No valid recipients to send to.', 'hellio-sms' ), 'no_valid_recipients' );
		}

		$body = array(
			'recipients' => $normalized,
			'sender'     => $this->clip( $sender, 11 ),
			'message'    => $this->clip( $message, 1600 ),
		);

		return $this->post( '/v1/sms/send', $body );
	}

	/**
	 * Request a one-time passcode to be sent to a mobile number.
	 *
	 * @param string   $mobile  Mobile number.
	 * @param string   $sender  Sender ID.
	 * @param int|null $length  Code length (4..10) or null for default.
	 * @param int|null $expiry  Expiry minutes (1..1440) or null for default.
	 * @param string   $purpose Purpose tag (max 32).
	 * @return array Normalized result.
	 */
	public function send_otp( $mobile, $sender, $length = null, $expiry = null, $purpose = 'checkout' ) {
		$phone = $this->normalize_phone( $mobile );

		if ( '' === $phone ) {
			return $this->error_result( 0, __( 'A valid mobile number is required.', 'hellio-sms' ), 'invalid_mobile' );
		}

		$body = array(
			'mobile_number' => $phone,
			'sender'        => $this->clip( $sender, 11 ),
			'purpose'       => $this->clip( $purpose, 32 ),
		);

		if ( null !== $length ) {
			$body['length'] = max( 4, min( 10, (int) $length ) );
		}

		if ( null !== $expiry ) {
			$body['expiry'] = max( 1, min( 1440, (int) $expiry ) );
		}

		return $this->post( '/v1/otp/send', $body );
	}

	/**
	 * Verify a one-time passcode.
	 *
	 * @param string $mobile Mobile number.
	 * @param string $code   Code entered by the customer.
	 * @return array Normalized result. Adds 'verified' bool for convenience.
	 */
	public function verify_otp( $mobile, $code ) {
		$phone = $this->normalize_phone( $mobile );

		if ( '' === $phone ) {
			return $this->error_result( 0, __( 'A valid mobile number is required.', 'hellio-sms' ), 'invalid_mobile' );
		}

		$body = array(
			'mobile_number' => $phone,
			'code'          => preg_replace( '/\D/', '', (string) $code ),
		);

		$result = $this->post( '/v1/otp/verify', $body );

		$data                 = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();
		$result['verified']   = ! empty( $data['verified'] );

		return $result;
	}

	/**
	 * Fetch the wallet balance. Used by the Test connection button.
	 *
	 * @return array Normalized result.
	 */
	public function get_balance() {
		return $this->get( '/v1/balance' );
	}

	/**
	 * Exchange account credentials for an API token (POST /v1/auth/token).
	 *
	 * Public endpoint, no Bearer token required. Used by the "Connect with your
	 * Hellio login" flow so the merchant never handles a raw token.
	 *
	 * @param string      $email           Account email.
	 * @param string      $password        Account password.
	 * @param string      $device_name     Label for the issued token.
	 * @param string|null $two_factor_code Optional 2FA code.
	 * @return array Normalized result. On success data holds token/abilities/user.
	 */
	public function create_token( $email, $password, $device_name = 'WooCommerce', $two_factor_code = null ) {
		$email = trim( (string) $email );

		if ( '' === $email || '' === (string) $password ) {
			return $this->error_result( 0, __( 'Email and password are required.', 'hellio-sms' ), 'missing_credentials' );
		}

		$body = array(
			'email'       => $email,
			'password'    => (string) $password,
			'device_name' => $this->clip( $device_name, 255 ),
		);

		if ( null !== $two_factor_code && '' !== trim( (string) $two_factor_code ) ) {
			$body['two_factor_code'] = preg_replace( '/\s+/', '', (string) $two_factor_code );
		}

		return $this->post_public( '/v1/auth/token', $body );
	}

	/**
	 * Perform a POST request.
	 *
	 * @param string $path Endpoint path.
	 * @param array  $body Request body.
	 * @return array Normalized result.
	 */
	protected function post( $path, array $body ) {
		if ( '' === $this->token ) {
			return $this->error_result( 0, __( 'No API token configured.', 'hellio-sms' ), 'missing_token' );
		}

		$args = array(
			'timeout' => $this->timeout,
			'headers' => array(
				'Authorization'   => 'Bearer ' . $this->token,
				'Accept'          => 'application/json',
				'Content-Type'    => 'application/json',
				'Idempotency-Key' => wp_generate_uuid4(),
			),
			'body'    => wp_json_encode( $body ),
		);

		$response = wp_remote_post( $this->base_url . $path, $args );

		return $this->handle_response( $response, $path );
	}

	/**
	 * Perform a POST request to a public endpoint (no Bearer token).
	 *
	 * @param string $path Endpoint path.
	 * @param array  $body Request body.
	 * @return array Normalized result.
	 */
	protected function post_public( $path, array $body ) {
		$args = array(
			'timeout' => $this->timeout,
			'headers' => array(
				'Accept'          => 'application/json',
				'Content-Type'    => 'application/json',
				'Idempotency-Key' => wp_generate_uuid4(),
			),
			'body'    => wp_json_encode( $body ),
		);

		$response = wp_remote_post( $this->base_url . $path, $args );

		return $this->handle_response( $response, $path );
	}

	/**
	 * Perform a GET request.
	 *
	 * @param string $path Endpoint path.
	 * @return array Normalized result.
	 */
	protected function get( $path ) {
		if ( '' === $this->token ) {
			return $this->error_result( 0, __( 'No API token configured.', 'hellio-sms' ), 'missing_token' );
		}

		$args = array(
			'timeout' => $this->timeout,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->token,
				'Accept'        => 'application/json',
			),
		);

		$response = wp_remote_get( $this->base_url . $path, $args );

		return $this->handle_response( $response, $path );
	}

	/**
	 * Turn a wp_remote_* response into a normalized result array.
	 *
	 * @param array|WP_Error $response Raw response.
	 * @param string         $path     Endpoint path (for logging).
	 * @return array
	 */
	protected function handle_response( $response, $path ) {
		if ( is_wp_error( $response ) ) {
			$this->log( $path, 0, $response->get_error_message() );

			return $this->error_result( 0, $response->get_error_message(), 'network_error' );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$parsed = json_decode( $raw, true );

		if ( ! is_array( $parsed ) ) {
			$parsed = array();
		}

		$success = ( $status >= 200 && $status < 300 );

		if ( $success ) {
			return array(
				'success' => true,
				'status'  => $status,
				'data'    => isset( $parsed['data'] ) ? $parsed['data'] : $parsed,
				'message' => isset( $parsed['message'] ) ? (string) $parsed['message'] : '',
				'error'   => null,
				'raw'     => $parsed,
			);
		}

		$message = isset( $parsed['message'] ) ? (string) $parsed['message'] : __( 'The Hellio API returned an error.', 'hellio-sms' );
		$error   = isset( $parsed['error'] ) ? (string) $parsed['error'] : 'http_' . $status;

		$this->log( $path, $status, $message . ' (' . $error . ')' );

		$result             = $this->error_result( $status, $message, $error );
		$result['data']     = isset( $parsed['data'] ) ? $parsed['data'] : null;
		$result['raw']      = $parsed;
		$result['retry_after'] = (int) wp_remote_retrieve_header( $response, 'retry-after' );

		return $result;
	}

	/**
	 * Build a normalized error result.
	 *
	 * @param int    $status  HTTP status.
	 * @param string $message Human message.
	 * @param string $error   Machine error code.
	 * @return array
	 */
	protected function error_result( $status, $message, $error ) {
		return array(
			'success' => false,
			'status'  => (int) $status,
			'data'    => null,
			'message' => (string) $message,
			'error'   => (string) $error,
			'raw'     => array(),
		);
	}

	/**
	 * Clip a string to a maximum length in a multibyte-safe way.
	 *
	 * @param string $value  Input.
	 * @param int    $length Maximum length.
	 * @return string
	 */
	protected function clip( $value, $length ) {
		$value = (string) $value;

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length );
		}

		return substr( $value, 0, $length );
	}

	/**
	 * Log an API problem. Uses the WooCommerce logger when available.
	 *
	 * @param string $path    Endpoint path.
	 * @param int    $status  HTTP status.
	 * @param string $message Message.
	 */
	protected function log( $path, $status, $message ) {
		$line = sprintf( 'Hellio SMS API %s (HTTP %d): %s', $path, (int) $status, $message );

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $line, array( 'source' => 'hellio-sms' ) );

			return;
		}

		// Fallback when the WC logger is unavailable.
		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
