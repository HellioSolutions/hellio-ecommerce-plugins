<?php
/**
 * Hellio Messaging API client.
 *
 * A plain, framework free PHP class (cURL based) shared by the admin and
 * catalog sides of the extension. It never throws into checkout or order
 * processing: every public method returns a structured array and logs on
 * failure instead of raising.
 *
 * Return shape (all methods):
 *   array(
 *     'success' => bool,     // true only on a 2xx response
 *     'status'  => int,      // HTTP status, 0 on a transport error
 *     'data'    => mixed,    // decoded "data" payload (or full body)
 *     'error'   => string,   // machine error code, null on success
 *     'message' => string,   // human readable message, null on success
 *   )
 *
 * @package Hellio Messaging
 */
class HellioClient {
	private $baseUrl;
	private $token;
	private $sender;
	private $dialCode;
	private $timeout;
	private $logger;

	/**
	 * @param array $config Keys: base_url, token, sender, dial_code, timeout.
	 * @param callable|null $logger Optional callable that receives a string line.
	 */
	public function __construct(array $config, $logger = null) {
		$this->baseUrl  = isset($config['base_url']) && $config['base_url'] !== '' ? $config['base_url'] : 'https://api.helliomessaging.com';
		$this->token    = isset($config['token']) ? (string)$config['token'] : '';
		$this->sender   = isset($config['sender']) ? (string)$config['sender'] : '';
		$this->dialCode = isset($config['dial_code']) ? (string)$config['dial_code'] : '';
		$this->timeout  = isset($config['timeout']) ? (int)$config['timeout'] : 15;
		$this->logger   = is_callable($logger) ? $logger : null;
	}

	/**
	 * Send an SMS to one or more recipients.
	 *
	 * @param array  $recipients Raw phone strings (normalised here).
	 * @param string $message
	 * @param string $sender Optional override for the default Sender ID.
	 */
	public function sendSms($recipients, $message, $sender = null) {
		$sender = $sender !== null && $sender !== '' ? $sender : $this->sender;

		$normalised = array();

		foreach ((array)$recipients as $recipient) {
			$phone = $this->normalize($recipient);

			if ($phone !== '') {
				$normalised[] = $phone;
			}
		}

		$normalised = array_values(array_unique($normalised));

		if (empty($normalised)) {
			return $this->fail('no_valid_recipients', 'No valid recipients after normalisation.');
		}

		return $this->request('POST', '/v1/sms/send', array(
			'recipients' => $normalised,
			'sender'     => $sender,
			'message'    => (string)$message
		));
	}

	/**
	 * Request an OTP for a single mobile number.
	 */
	public function sendOtp($mobileNumber, $length = null, $expiry = null, $purpose = 'checkout', $sender = null) {
		$sender = $sender !== null && $sender !== '' ? $sender : $this->sender;
		$phone  = $this->normalize($mobileNumber);

		if ($phone === '') {
			return $this->fail('no_valid_recipients', 'A valid mobile number is required.');
		}

		$body = array(
			'mobile_number' => $phone,
			'sender'        => $sender,
			'purpose'       => (string)$purpose
		);

		if ($length !== null && $length !== '') {
			$body['length'] = (int)$length;
		}

		if ($expiry !== null && $expiry !== '') {
			$body['expiry'] = (int)$expiry;
		}

		return $this->request('POST', '/v1/otp/send', $body);
	}

	/**
	 * Verify a code the customer entered.
	 */
	public function verifyOtp($mobileNumber, $code) {
		$phone = $this->normalize($mobileNumber);

		if ($phone === '') {
			return $this->fail('no_valid_recipients', 'A valid mobile number is required.');
		}

		return $this->request('POST', '/v1/otp/verify', array(
			'mobile_number' => $phone,
			'code'          => (string)$code
		));
	}

	/**
	 * Fetch the wallet balance. Used by the "Test connection" button.
	 */
	public function getBalance() {
		return $this->request('GET', '/v1/balance');
	}

	/**
	 * Exchange account credentials for an API token so the merchant can connect
	 * once with their Hellio login instead of pasting a token. Public endpoint,
	 * no Bearer token required. The password is only ever sent, never stored.
	 *
	 * @param string $email
	 * @param string $password
	 * @param string $deviceName    Labels the token, for example "OpenCart".
	 * @param string $twoFactorCode Only when the account has 2FA enabled.
	 */
	public function createToken($email, $password, $deviceName = 'OpenCart', $twoFactorCode = null) {
		$body = array(
			'email'       => (string)$email,
			'password'    => (string)$password,
			'device_name' => (string)$deviceName
		);

		if ($twoFactorCode !== null && $twoFactorCode !== '') {
			$body['two_factor_code'] = (string)$twoFactorCode;
		}

		return $this->request('POST', '/v1/auth/token', $body);
	}

	/**
	 * Normalise a phone number, honouring the configured default dial code.
	 *
	 * Rules:
	 *  - Strip everything except digits and a leading plus.
	 *  - A leading "+" or "00" means the country code is already present.
	 *  - A number already prefixed with the dial code is left as is.
	 *  - Otherwise strip a single leading zero and prepend the dial code.
	 */
	public function normalize($number) {
		$n = preg_replace('/[^0-9+]/', '', (string)$number);

		if ($n === '' || $n === '+') {
			return '';
		}

		if (strpos($n, '+') === 0) {
			return preg_replace('/[^0-9]/', '', $n);
		}

		if (strpos($n, '00') === 0) {
			return substr($n, 2);
		}

		$dial = preg_replace('/[^0-9]/', '', $this->dialCode);

		if ($dial !== '' && strpos($n, $dial) === 0 && strlen($n) > strlen($dial)) {
			return $n;
		}

		if (strpos($n, '0') === 0) {
			$n = ltrim($n, '0');
		}

		return $dial . $n;
	}

	/**
	 * Perform an HTTP request and return the structured result.
	 */
	private function request($method, $path, $body = null) {
		$url = rtrim($this->baseUrl, '/') . $path;

		$headers = array(
			'Accept: application/json',
			'Authorization: Bearer ' . $this->token
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);

		if ($method === 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

			$headers[] = 'Content-Type: application/json';
			$headers[] = 'Idempotency-Key: ' . $this->uuid();
		}

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$raw    = curl_exec($ch);
		$errno  = curl_errno($ch);
		$errstr = curl_error($ch);
		$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($errno) {
			$this->log('Hellio ' . $method . ' ' . $path . ' transport error: ' . $errstr);

			return $this->fail('network_error', $errstr, 0);
		}

		$decoded = json_decode($raw, true);

		if ($status >= 200 && $status < 300) {
			return array(
				'success' => true,
				'status'  => $status,
				'data'    => (is_array($decoded) && array_key_exists('data', $decoded)) ? $decoded['data'] : $decoded,
				'error'   => null,
				'message' => null,
				'raw'     => $decoded
			);
		}

		$error   = (is_array($decoded) && isset($decoded['error'])) ? $decoded['error'] : ('http_' . $status);
		$message = (is_array($decoded) && isset($decoded['message'])) ? $decoded['message'] : 'The request could not be completed.';

		$this->log('Hellio ' . $method . ' ' . $path . ' failed (' . $status . ' ' . $error . '): ' . $message);

		return array(
			'success' => false,
			'status'  => $status,
			'data'    => $decoded,
			'error'   => $error,
			'message' => $message,
			'raw'     => $decoded
		);
	}

	private function fail($error, $message, $status = 0) {
		$this->log('Hellio client error (' . $error . '): ' . $message);

		return array(
			'success' => false,
			'status'  => $status,
			'data'    => null,
			'error'   => $error,
			'message' => $message,
			'raw'     => null
		);
	}

	/**
	 * RFC 4122 version 4 UUID for the Idempotency-Key header.
	 */
	private function uuid() {
		$data = function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16);
		$data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
		$data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}

	private function log($line) {
		if ($this->logger) {
			call_user_func($this->logger, $line);
		} else {
			error_log($line);
		}
	}
}

/**
 * Renders order-status and alert templates. Kept next to the client so both
 * the admin and catalog event handlers can share one implementation.
 */
class HellioMessage {
	/**
	 * Replace {placeholder} tokens from a map. Unknown tokens render empty.
	 *
	 * @param string $template
	 * @param array  $data Keyed by placeholder name without the braces.
	 */
	public static function render($template, array $data) {
		if ($template === null || $template === '') {
			return '';
		}

		return preg_replace_callback('/\{([a-z_]+)\}/', function ($matches) use ($data) {
			$key = $matches[1];

			return isset($data[$key]) ? (string)$data[$key] : '';
		}, $template);
	}

	/**
	 * Build the placeholder map from an OpenCart order array.
	 *
	 * @param array  $order       Result of the checkout/order or sale/order model.
	 * @param string $storeName
	 * @param string $storeUrl
	 * @param string $catalogBase Catalog base URL for building a tracking link.
	 */
	public static function fromOrder(array $order, $storeName, $storeUrl, $catalogBase = '') {
		$first = isset($order['firstname']) ? $order['firstname'] : '';
		$last  = isset($order['lastname']) ? $order['lastname'] : '';
		$total = isset($order['total']) ? $order['total'] : '';

		if (isset($order['currency_value']) && isset($order['currency_code']) && $order['total'] !== '') {
			$total = number_format((float)$order['total'] * (float)$order['currency_value'], 2);
		}

		$trackingUrl = '';

		if ($catalogBase !== '' && isset($order['order_id'])) {
			$trackingUrl = rtrim($catalogBase, '/') . '/index.php?route=account/order/info&order_id=' . (int)$order['order_id'];
		}

		return array(
			'order_id'            => isset($order['order_id']) ? $order['order_id'] : '',
			'order_number'        => isset($order['order_id']) ? $order['order_id'] : '',
			'order_status'        => isset($order['order_status']) ? $order['order_status'] : '',
			'order_total'         => $total,
			'currency'            => isset($order['currency_code']) ? $order['currency_code'] : '',
			'customer_name'       => trim($first . ' ' . $last),
			'customer_first_name' => $first,
			'store_name'          => $storeName,
			'shop_url'            => $storeUrl,
			'tracking_url'        => $trackingUrl,
			'date'                => date('Y-m-d')
		);
	}
}
