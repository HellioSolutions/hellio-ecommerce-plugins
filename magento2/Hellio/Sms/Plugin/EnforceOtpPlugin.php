<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Plugin;

use Hellio\Sms\Helper\Config;
use Hellio\Sms\Model\OtpSession;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Block order placement for logged-in customers until the phone is verified.
 *
 * Feature 3: Checkout OTP (server-side enforcement).
 */
class EnforceOtpPlugin
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var OtpSession
     */
    private $otpSession;

    /**
     * @param Config     $config
     * @param OtpSession $otpSession
     */
    public function __construct(Config $config, OtpSession $otpSession)
    {
        $this->config = $config;
        $this->otpSession = $otpSession;
    }

    /**
     * @param PaymentInformationManagementInterface $subject
     * @param int                                   $cartId
     * @param PaymentInterface                      $paymentMethod
     * @param AddressInterface|null                 $billingAddress
     * @return void
     * @throws CouldNotSaveException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagementInterface $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null
    ): void {
        if (!$this->config->isEnabled() || !$this->config->isOtpEnabled()) {
            return;
        }

        if (!$this->otpSession->isVerified()) {
            throw new CouldNotSaveException(
                __('Please verify your phone number before placing the order.')
            );
        }
    }
}
