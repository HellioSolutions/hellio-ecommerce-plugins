<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed accessor for every Hellio Messaging configuration value.
 *
 * All getters resolve against the store scope so per-website overrides work.
 */
class Config
{
    public const XML_PATH_ENABLED = 'hellio_sms/general/enabled';
    public const XML_PATH_API_BASE_URL = 'hellio_sms/general/api_base_url';
    public const XML_PATH_API_TOKEN = 'hellio_sms/general/api_token';
    public const XML_PATH_SENDER_ID = 'hellio_sms/general/sender_id';
    public const XML_PATH_DIAL_CODE = 'hellio_sms/general/default_dial_code';
    public const XML_PATH_CONNECTED_EMAIL = 'hellio_sms/general/connected_email';

    public const XML_PATH_STATUS_ENABLED = 'hellio_sms/order_status/enabled';
    public const XML_PATH_STATUS_TEMPLATES = 'hellio_sms/order_status/templates';

    public const XML_PATH_ALERT_ENABLED = 'hellio_sms/admin_alert/enabled';
    public const XML_PATH_ALERT_RECIPIENTS = 'hellio_sms/admin_alert/recipients';
    public const XML_PATH_ALERT_TEMPLATE = 'hellio_sms/admin_alert/template';

    public const XML_PATH_OTP_ENABLED = 'hellio_sms/otp/enabled';
    public const XML_PATH_OTP_LENGTH = 'hellio_sms/otp/length';
    public const XML_PATH_OTP_EXPIRY = 'hellio_sms/otp/expiry';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var Json
     */
    private $json;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface   $encryptor
     * @param Json                 $json
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        Json $json
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->json = $json;
    }

    /**
     * @param string      $path
     * @param int|null    $storeId
     * @return string
     */
    private function getValue(string $path, ?int $storeId = null): string
    {
        $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);

        return $value === null ? '' : (string) $value;
    }

    /**
     * @param string   $path
     * @param int|null $storeId
     * @return bool
     */
    private function isFlag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->isFlag(self::XML_PATH_ENABLED, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getApiBaseUrl(?int $storeId = null): string
    {
        $url = trim($this->getValue(self::XML_PATH_API_BASE_URL, $storeId));

        if ($url === '') {
            $url = 'https://api.helliomessaging.com';
        }

        return rtrim($url, '/');
    }

    /**
     * Decrypt and return the stored API token.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiToken(?int $storeId = null): string
    {
        $stored = $this->getValue(self::XML_PATH_API_TOKEN, $storeId);

        if ($stored === '') {
            return '';
        }

        return trim((string) $this->encryptor->decrypt($stored));
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getSenderId(?int $storeId = null): string
    {
        return trim($this->getValue(self::XML_PATH_SENDER_ID, $storeId));
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getDefaultDialCode(?int $storeId = null): string
    {
        return preg_replace('/\D+/', '', $this->getValue(self::XML_PATH_DIAL_CODE, $storeId));
    }

    /**
     * Email of the Hellio account this store is connected to, if any.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getConnectedEmail(?int $storeId = null): string
    {
        return trim($this->getValue(self::XML_PATH_CONNECTED_EMAIL, $storeId));
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function hasApiToken(?int $storeId = null): bool
    {
        return $this->getApiToken($storeId) !== '';
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isOrderStatusSmsEnabled(?int $storeId = null): bool
    {
        return $this->isFlag(self::XML_PATH_STATUS_ENABLED, $storeId);
    }

    /**
     * Return the status to template map.
     *
     * @param int|null $storeId
     * @return array<string, string> Keyed by status code, value is the template.
     */
    public function getStatusTemplates(?int $storeId = null): array
    {
        $raw = $this->getValue(self::XML_PATH_STATUS_TEMPLATES, $storeId);

        if ($raw === '') {
            return [];
        }

        try {
            $rows = $this->json->unserialize($raw);
        } catch (\InvalidArgumentException $e) {
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['status'])) {
                continue;
            }
            $map[(string) $row['status']] = isset($row['template']) ? (string) $row['template'] : '';
        }

        return $map;
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isAdminAlertEnabled(?int $storeId = null): bool
    {
        return $this->isFlag(self::XML_PATH_ALERT_ENABLED, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return string[]
     */
    public function getAdminRecipients(?int $storeId = null): array
    {
        $raw = $this->getValue(self::XML_PATH_ALERT_RECIPIENTS, $storeId);

        if ($raw === '') {
            return [];
        }

        $numbers = preg_split('/[,;\s]+/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $numbers), static function ($n) {
            return $n !== '';
        }));
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getAdminAlertTemplate(?int $storeId = null): string
    {
        return $this->getValue(self::XML_PATH_ALERT_TEMPLATE, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isOtpEnabled(?int $storeId = null): bool
    {
        return $this->isFlag(self::XML_PATH_OTP_ENABLED, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return int
     */
    public function getOtpLength(?int $storeId = null): int
    {
        $length = (int) $this->getValue(self::XML_PATH_OTP_LENGTH, $storeId);

        if ($length < 4 || $length > 10) {
            return 6;
        }

        return $length;
    }

    /**
     * @param int|null $storeId
     * @return int
     */
    public function getOtpExpiry(?int $storeId = null): int
    {
        $expiry = (int) $this->getValue(self::XML_PATH_OTP_EXPIRY, $storeId);

        if ($expiry < 1 || $expiry > 1440) {
            return 5;
        }

        return $expiry;
    }
}
