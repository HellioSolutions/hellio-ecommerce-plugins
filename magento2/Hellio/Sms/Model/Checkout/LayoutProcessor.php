<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Model\Checkout;

use Hellio\Sms\Helper\Config;
use Magento\Checkout\Block\Checkout\LayoutProcessor as CheckoutLayoutProcessor;

/**
 * Inject the Hellio OTP UI component into the checkout shipping step so the
 * customer can verify their phone before continuing to payment.
 *
 * Feature 3: Checkout OTP (layout wiring). Registered as an afterProcess
 * plugin on Magento\Checkout\Block\Checkout\LayoutProcessor in frontend di.xml.
 */
class LayoutProcessor
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param CheckoutLayoutProcessor $subject
     * @param array                   $jsLayout
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterProcess(CheckoutLayoutProcessor $subject, array $jsLayout): array
    {
        if (!$this->config->isEnabled() || !$this->config->isOtpEnabled()) {
            return $jsLayout;
        }

        $component = [
            'component' => 'Hellio_Sms/js/view/checkout-otp',
            'sortOrder' => 250,
            'config' => [
                'length' => $this->config->getOtpLength(),
                'expiry' => $this->config->getOtpExpiry(),
            ],
        ];

        // Preferred slot: the shipping step, after the address form.
        if (isset($jsLayout['components']['checkout']['children']['steps']['children']
            ['shipping-step']['children']['shippingAddress']['children'])) {
            $jsLayout['components']['checkout']['children']['steps']['children']
                ['shipping-step']['children']['shippingAddress']['children']['hellio_otp'] = $component;

            return $jsLayout;
        }

        // Fallback slot: before the payment methods list.
        if (isset($jsLayout['components']['checkout']['children']['steps']['children']
            ['billing-step']['children']['payment']['children']['beforeMethods']['children'])) {
            $jsLayout['components']['checkout']['children']['steps']['children']
                ['billing-step']['children']['payment']['children']['beforeMethods']['children']['hellio_otp'] = $component;
        }

        return $jsLayout;
    }
}
