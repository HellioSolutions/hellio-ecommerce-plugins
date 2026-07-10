<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Model\Checkout;

use Hellio\Sms\Helper\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;

/**
 * Expose the OTP feature flag and endpoints to the checkout JS, without ever
 * exposing the API token.
 */
class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @param Config       $config
     * @param UrlInterface $url
     */
    public function __construct(Config $config, UrlInterface $url)
    {
        $this->config = $config;
        $this->url = $url;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        $enabled = $this->config->isEnabled() && $this->config->isOtpEnabled();

        return [
            'hellio_otp' => [
                'enabled' => $enabled,
                'length' => $this->config->getOtpLength(),
                'expiry' => $this->config->getOtpExpiry(),
                'sendUrl' => $this->url->getUrl('hellio_sms/otp/send'),
                'verifyUrl' => $this->url->getUrl('hellio_sms/otp/verify'),
            ],
        ];
    }
}
