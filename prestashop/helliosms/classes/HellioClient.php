<?php
/**
 * Hellio Messaging API client.
 *
 * Talks to the Hellio Messaging REST API over cURL. Every call is wrapped so a
 * network or API failure returns a structured error array instead of throwing.
 *
 * @author    Hellio Solutions
 * @copyright Hellio Solutions
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class HellioClient
{
    /** @var string */
    private $baseUrl;

    /** @var string */
    private $token;

    /** @var string */
    private $defaultDialCode;

    /** @var int */
    private $timeout = 15;

    /**
     * @param string $baseUrl         API base URL, no trailing slash required.
     * @param string $token           Sanctum personal access token.
     * @param string $defaultDialCode Country dial code applied to local numbers.
     */
    public function __construct($baseUrl = null, $token = null, $defaultDialCode = null)
    {
        $this->baseUrl = rtrim(($baseUrl !== null ? $baseUrl : Configuration::get('HELLIOSMS_API_BASE_URL')), '/');
        if ($this->baseUrl === '') {
            $this->baseUrl = 'https://api.helliomessaging.com';
        }
        $this->token = $token !== null ? $token : Configuration::get('HELLIOSMS_API_TOKEN');
        $this->defaultDialCode = $defaultDialCode !== null
            ? $defaultDialCode
            : Configuration::get('HELLIOSMS_DEFAULT_DIAL_CODE');
    }

    /**
     * Send an SMS to one or more recipients.
     *
     * @param array  $recipients Phone strings (already normalized or raw).
     * @param string $message    Message body, max 1600 chars.
     * @param string $sender     Sender ID, max 11 chars.
     *
     * @return array Structured result: ['success' => bool, 'status' => int, 'data' => array|null, 'error' => string|null, 'message' => string|null]
     */
    public function sendSms(array $recipients, $message, $sender = null)
    {
        $clean = array();
        foreach ($recipients as $recipient) {
            $normalized = $this->normalizePhone($recipient);
            if ($normalized !== '') {
                $clean[] = $normalized;
            }
        }
        $clean = array_values(array_unique($clean));

        if (empty($clean)) {
            return $this->fail(0, 'no_valid_recipients', 'No valid recipients after normalization.');
        }

        $payload = array(
            'recipients' => $clean,
            'sender' => $sender !== null ? $sender : Configuration::get('HELLIOSMS_SENDER_ID'),
            'message' => Tools::substr((string) $message, 0, 1600),
        );

        return $this->request('POST', '/v1/sms/send', $payload);
    }

    /**
     * Request an OTP code be sent to a mobile number.
     *
     * @param string $mobileNumber Recipient number.
     * @param int    $length       Code length 4..10.
     * @param int    $expiry       Expiry in minutes 1..1440.
     * @param string $purpose      Short purpose label.
     * @param string $sender       Sender ID.
     *
     * @return array
     */
    public function sendOtp($mobileNumber, $length = 6, $expiry = 5, $purpose = 'checkout', $sender = null)
    {
        $normalized = $this->normalizePhone($mobileNumber);
        if ($normalized === '') {
            return $this->fail(0, 'no_valid_recipients', 'The mobile number is empty or invalid.');
        }

        $payload = array(
            'mobile_number' => $normalized,
            'sender' => $sender !== null ? $sender : Configuration::get('HELLIOSMS_SENDER_ID'),
            'length' => (int) $length,
            'expiry' => (int) $expiry,
            'purpose' => Tools::substr((string) $purpose, 0, 32),
        );

        return $this->request('POST', '/v1/otp/send', $payload);
    }

    /**
     * Verify an OTP code for a mobile number.
     *
     * @param string $mobileNumber Recipient number.
     * @param string $code         The code the customer entered.
     *
     * @return array
     */
    public function verifyOtp($mobileNumber, $code)
    {
        $normalized = $this->normalizePhone($mobileNumber);
        if ($normalized === '') {
            return $this->fail(0, 'no_valid_recipients', 'The mobile number is empty or invalid.');
        }

        $payload = array(
            'mobile_number' => $normalized,
            'code' => preg_replace('/\D+/', '', (string) $code),
        );

        return $this->request('POST', '/v1/otp/verify', $payload);
    }

    /**
     * Fetch the wallet balance. Used by the Test connection button.
     *
     * @return array
     */
    public function getBalance()
    {
        return $this->request('GET', '/v1/balance');
    }

    /**
     * Exchange Hellio account credentials for an API token.
     *
     * Public endpoint, no bearer token is sent. The password is used for this
     * one request only and is never stored or logged.
     *
     * @param string      $email
     * @param string      $password
     * @param string      $deviceName    Label for the created token.
     * @param string|null $twoFactorCode Only when the account has 2FA enabled.
     *
     * @return array Structured result. On success data holds token, abilities, user.
     */
    public function createToken($email, $password, $deviceName = 'PrestaShop', $twoFactorCode = null)
    {
        $payload = array(
            'email' => trim((string) $email),
            'password' => (string) $password,
            'device_name' => Tools::substr((string) $deviceName, 0, 255),
        );
        if ($twoFactorCode !== null && trim((string) $twoFactorCode) !== '') {
            $payload['two_factor_code'] = preg_replace('/\D+/', '', (string) $twoFactorCode);
        }

        return $this->request('POST', '/v1/auth/token', $payload, false);
    }

    /**
     * Normalize a phone number honoring the configured default dial code.
     *
     * Rules: keep a leading "+" and the digits behind it. Otherwise, if the
     * number has no country code, strip a single leading "0" and prepend the
     * default dial code.
     *
     * @param string $raw
     *
     * @return string Digits only (no plus), or empty string when unusable.
     */
    public function normalizePhone($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        $hasPlus = (Tools::substr($raw, 0, 1) === '+');
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return '';
        }

        if ($hasPlus) {
            // Already international, drop the plus for the API.
            return $digits;
        }

        $dial = preg_replace('/\D+/', '', (string) $this->defaultDialCode);

        if ($dial !== '') {
            if (Tools::substr($digits, 0, 1) === '0') {
                // Local format: strip the trunk zero and prepend the dial code.
                $digits = $dial . Tools::substr($digits, 1);
            } elseif (Tools::substr($digits, 0, Tools::strlen($dial)) !== $dial) {
                // No leading zero and no country code yet, prepend the dial code.
                $digits = $dial . $digits;
            }
        }

        return $digits;
    }

    /**
     * Execute an HTTP request against the API.
     *
     * @param string     $method  HTTP verb.
     * @param string     $path    Path beginning with a slash.
     * @param array|null $body    JSON body for POST requests.
     * @param bool       $auth    Whether to send the bearer token.
     *
     * @return array Structured result.
     */
    private function request($method, $path, $body = null, $auth = true)
    {
        if (!function_exists('curl_init')) {
            return $this->fail(0, 'curl_unavailable', 'The PHP cURL extension is not available.');
        }

        if ($auth && empty($this->token)) {
            return $this->fail(0, 'missing_token', 'No API token is configured.');
        }

        $url = $this->baseUrl . $path;
        $headers = array(
            'Accept: application/json',
        );
        if ($auth) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if (Tools::strtoupper($method) === 'POST') {
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Idempotency-Key: ' . $this->uuid();
            $json = json_encode($body === null ? new stdClass() : $body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $retryAfter = null;
        curl_close($ch);

        if ($raw === false || $curlErrno !== 0) {
            $this->log('Hellio request transport error on ' . $path . ': ' . $curlError, 3);

            return $this->fail(0, 'network_error', $curlError !== '' ? $curlError : 'Network error.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = array();
        }

        if ($status >= 200 && $status < 300) {
            return array(
                'success' => true,
                'status' => $status,
                'data' => isset($decoded['data']) ? $decoded['data'] : $decoded,
                'error' => null,
                'message' => null,
                'retry_after' => null,
            );
        }

        $errorCode = isset($decoded['error']) ? (string) $decoded['error'] : 'http_' . $status;
        $errorMessage = isset($decoded['message']) ? (string) $decoded['message'] : 'Request failed with status ' . $status . '.';
        $this->log('Hellio request failed on ' . $path . ' (' . $status . '): ' . $errorMessage, 3);

        $result = $this->fail($status, $errorCode, $errorMessage);
        $result['data'] = isset($decoded['data']) ? $decoded['data'] : null;
        $result['retry_after'] = $retryAfter;

        return $result;
    }

    /**
     * Build a failed-result array.
     *
     * @param int    $status
     * @param string $error
     * @param string $message
     *
     * @return array
     */
    private function fail($status, $error, $message)
    {
        return array(
            'success' => false,
            'status' => (int) $status,
            'data' => null,
            'error' => $error,
            'message' => $message,
            'retry_after' => null,
        );
    }

    /**
     * Generate a random RFC 4122 version 4 UUID for the Idempotency-Key header.
     *
     * @return string
     */
    private function uuid()
    {
        $data = null;
        if (function_exists('random_bytes')) {
            try {
                $data = random_bytes(16);
            } catch (Exception $e) {
                $data = null;
            }
        }
        if ($data === null || Tools::strlen($data) !== 16) {
            $data = '';
            for ($i = 0; $i < 16; ++$i) {
                $data .= chr(mt_rand(0, 255));
            }
        }

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Log a message through PrestaShopLogger.
     *
     * @param string $message
     * @param int    $severity 1 info, 2 warning, 3 error.
     *
     * @return void
     */
    private function log($message, $severity = 1)
    {
        if (class_exists('PrestaShopLogger')) {
            PrestaShopLogger::addLog($message, $severity, null, 'HellioSms', null, true);
        }
    }
}
