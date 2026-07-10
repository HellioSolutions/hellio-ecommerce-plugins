<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Model;

use Hellio\Sms\Helper\Config;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Thin, defensive client for the Hellio Messaging REST API.
 *
 * Every public method returns a structured result array and never throws on a
 * transport or API error, so callers on the checkout or order-save path stay
 * safe. Shape:
 *   [
 *     'success'    => bool,
 *     'status'     => int,     HTTP status code, 0 on transport failure.
 *     'data'       => array,   Decoded "data" envelope when present.
 *     'raw'        => array,   Full decoded body.
 *     'error'      => string,  Machine error code, empty when none.
 *     'message'    => string,  Human readable message.
 *   ]
 */
class HellioClient
{
    private const TIMEOUT = 15;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Config          $config
     * @param Curl            $curl
     * @param Json            $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        Curl $curl,
        Json $json,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->curl = $curl;
        $this->json = $json;
        $this->logger = $logger;
    }

    /**
     * Send an SMS to one or more recipients.
     *
     * @param string[]    $recipients Raw phone numbers, normalized here.
     * @param string      $message
     * @param string|null $sender     Overrides the configured Sender ID.
     * @param int|null    $storeId
     * @return array
     */
    public function sendSms(array $recipients, string $message, ?string $sender = null, ?int $storeId = null): array
    {
        $normalized = [];
        foreach ($recipients as $recipient) {
            $number = $this->normalizePhone((string) $recipient, $storeId);
            if ($number !== '') {
                $normalized[] = $number;
            }
        }
        $normalized = array_values(array_unique($normalized));

        if (empty($normalized)) {
            return $this->failure(0, 'no_valid_recipients', 'No valid recipients after normalization.');
        }

        $body = [
            'recipients' => $normalized,
            'sender' => $sender !== null && $sender !== '' ? $sender : $this->config->getSenderId($storeId),
            'message' => $message,
        ];

        return $this->post('/v1/sms/send', $body, $storeId);
    }

    /**
     * Request an OTP code be sent to a phone number.
     *
     * @param string      $mobileNumber
     * @param string|null $purpose
     * @param int|null    $storeId
     * @return array
     */
    public function sendOtp(string $mobileNumber, ?string $purpose = 'checkout', ?int $storeId = null): array
    {
        $number = $this->normalizePhone($mobileNumber, $storeId);
        if ($number === '') {
            return $this->failure(0, 'invalid_number', 'The phone number could not be normalized.');
        }

        $body = [
            'mobile_number' => $number,
            'sender' => $this->config->getSenderId($storeId),
            'length' => $this->config->getOtpLength($storeId),
            'expiry' => $this->config->getOtpExpiry($storeId),
            'purpose' => $purpose !== null && $purpose !== '' ? substr($purpose, 0, 32) : 'checkout',
        ];

        return $this->post('/v1/otp/send', $body, $storeId);
    }

    /**
     * Verify a code the customer entered.
     *
     * @param string   $mobileNumber
     * @param string   $code
     * @param int|null $storeId
     * @return array
     */
    public function verifyOtp(string $mobileNumber, string $code, ?int $storeId = null): array
    {
        $number = $this->normalizePhone($mobileNumber, $storeId);
        if ($number === '') {
            return $this->failure(0, 'invalid_number', 'The phone number could not be normalized.');
        }

        $body = [
            'mobile_number' => $number,
            'code' => preg_replace('/\D+/', '', $code),
        ];

        return $this->post('/v1/otp/verify', $body, $storeId);
    }

    /**
     * Fetch the wallet balance. Used by the Test connection button.
     *
     * @param int|null $storeId
     * @return array
     */
    public function getBalance(?int $storeId = null): array
    {
        return $this->get('/v1/balance', $storeId);
    }

    /**
     * Exchange Hellio account credentials for an API token.
     *
     * Public endpoint: sends no bearer token. The password is used once and
     * never stored. Callers persist only the returned token.
     *
     * @param string      $email
     * @param string      $password
     * @param string      $deviceName
     * @param string|null $twoFactorCode
     * @param int|null    $storeId
     * @return array
     */
    public function createToken(
        string $email,
        string $password,
        string $deviceName = 'Magento',
        ?string $twoFactorCode = null,
        ?int $storeId = null
    ): array {
        $body = [
            'email' => $email,
            'password' => $password,
            'device_name' => $deviceName !== '' ? $deviceName : 'Magento',
        ];

        if ($twoFactorCode !== null && $twoFactorCode !== '') {
            $body['two_factor_code'] = $twoFactorCode;
        }

        return $this->postPublic('/v1/auth/token', $body, $storeId);
    }

    /**
     * Normalize a phone number honoring the configured default dial code.
     *
     * Rules: keep an explicit country code, strip a leading 0 and prepend the
     * dial code for local numbers, and drop everything that is not a digit.
     *
     * @param string   $number
     * @param int|null $storeId
     * @return string
     */
    public function normalizePhone(string $number, ?int $storeId = null): string
    {
        $number = trim($number);
        if ($number === '') {
            return '';
        }

        $hasPlus = strpos($number, '+') === 0 || strpos($number, '00') === 0;
        $digits = preg_replace('/\D+/', '', $number);

        if ($digits === '' || $digits === null) {
            return '';
        }

        // International prefix "00" behaves like a leading "+".
        if (strpos($number, '00') === 0) {
            $digits = ltrim(substr($digits, 2), '0');
            return $digits;
        }

        if ($hasPlus) {
            return ltrim($digits, '0');
        }

        $dialCode = $this->config->getDefaultDialCode($storeId);

        // Local number: strip a single leading 0 and prepend the dial code.
        if ($dialCode !== '' && strpos($digits, '0') === 0) {
            return $dialCode . ltrim($digits, '0');
        }

        // Already starts with the country code.
        if ($dialCode !== '' && strpos($digits, $dialCode) === 0) {
            return $digits;
        }

        // No country code and no leading zero: prepend the dial code if set.
        if ($dialCode !== '') {
            return $dialCode . $digits;
        }

        return $digits;
    }

    /**
     * @param string   $path
     * @param array    $body
     * @param int|null $storeId
     * @return array
     */
    private function post(string $path, array $body, ?int $storeId): array
    {
        $token = $this->config->getApiToken($storeId);
        if ($token === '') {
            return $this->failure(0, 'missing_token', 'No Hellio API token is configured.');
        }

        try {
            $curl = $this->curl;
            $curl->setTimeout(self::TIMEOUT);
            $curl->addHeader('Authorization', 'Bearer ' . $token);
            $curl->addHeader('Accept', 'application/json');
            $curl->addHeader('Content-Type', 'application/json');
            $curl->addHeader('Idempotency-Key', $this->uuid());
            $curl->post($this->config->getApiBaseUrl($storeId) . $path, $this->json->serialize($body));

            return $this->parse($curl->getStatus(), $curl->getBody());
        } catch (\Throwable $e) {
            $this->logger->error('Hellio SMS POST ' . $path . ' failed: ' . $e->getMessage());

            return $this->failure(0, 'transport_error', $e->getMessage());
        }
    }

    /**
     * POST to a public endpoint that does not take a bearer token.
     *
     * @param string   $path
     * @param array    $body
     * @param int|null $storeId
     * @return array
     */
    private function postPublic(string $path, array $body, ?int $storeId): array
    {
        try {
            $curl = $this->curl;
            $curl->setTimeout(self::TIMEOUT);
            $curl->addHeader('Accept', 'application/json');
            $curl->addHeader('Content-Type', 'application/json');
            $curl->addHeader('Idempotency-Key', $this->uuid());
            $curl->post($this->config->getApiBaseUrl($storeId) . $path, $this->json->serialize($body));

            return $this->parse($curl->getStatus(), $curl->getBody());
        } catch (\Throwable $e) {
            $this->logger->error('Hellio SMS POST ' . $path . ' failed: ' . $e->getMessage());

            return $this->failure(0, 'transport_error', $e->getMessage());
        }
    }

    /**
     * @param string   $path
     * @param int|null $storeId
     * @return array
     */
    private function get(string $path, ?int $storeId): array
    {
        $token = $this->config->getApiToken($storeId);
        if ($token === '') {
            return $this->failure(0, 'missing_token', 'No Hellio API token is configured.');
        }

        try {
            $curl = $this->curl;
            $curl->setTimeout(self::TIMEOUT);
            $curl->addHeader('Authorization', 'Bearer ' . $token);
            $curl->addHeader('Accept', 'application/json');
            $curl->get($this->config->getApiBaseUrl($storeId) . $path);

            return $this->parse($curl->getStatus(), $curl->getBody());
        } catch (\Throwable $e) {
            $this->logger->error('Hellio SMS GET ' . $path . ' failed: ' . $e->getMessage());

            return $this->failure(0, 'transport_error', $e->getMessage());
        }
    }

    /**
     * Turn an HTTP status and raw body into a structured result.
     *
     * @param int    $status
     * @param string $rawBody
     * @return array
     */
    private function parse(int $status, string $rawBody): array
    {
        $decoded = [];
        if ($rawBody !== '') {
            try {
                $decoded = $this->json->unserialize($rawBody);
            } catch (\InvalidArgumentException $e) {
                $decoded = [];
            }
        }
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $success = $status >= 200 && $status < 300;
        $data = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];

        if (!$success) {
            $error = isset($decoded['error']) ? (string) $decoded['error'] : 'http_' . $status;
            $message = isset($decoded['message']) ? (string) $decoded['message'] : 'Request failed with status ' . $status . '.';
            $this->logger->warning('Hellio SMS API error ' . $status . ': ' . $error . ' ' . $message);

            return [
                'success' => false,
                'status' => $status,
                'data' => $data,
                'raw' => $decoded,
                'error' => $error,
                'message' => $message,
            ];
        }

        return [
            'success' => true,
            'status' => $status,
            'data' => $data,
            'raw' => $decoded,
            'error' => '',
            'message' => isset($decoded['message']) ? (string) $decoded['message'] : '',
        ];
    }

    /**
     * @param int    $status
     * @param string $error
     * @param string $message
     * @return array
     */
    private function failure(int $status, string $error, string $message): array
    {
        return [
            'success' => false,
            'status' => $status,
            'data' => [],
            'raw' => [],
            'error' => $error,
            'message' => $message,
        ];
    }

    /**
     * Generate an RFC 4122 version 4 UUID for the Idempotency-Key header.
     *
     * @return string
     */
    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
