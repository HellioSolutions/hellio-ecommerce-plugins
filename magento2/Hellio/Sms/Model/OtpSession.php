<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Model;

use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Track the checkout OTP verification state in the checkout session.
 *
 * Only a boolean flag and the verified phone are stored server-side. The API
 * token and codes never touch the session or the browser.
 */
class OtpSession
{
    private const KEY_VERIFIED = 'hellio_otp_verified';
    private const KEY_PHONE = 'hellio_otp_phone';

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(CheckoutSession $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Record that a phone number has passed verification.
     *
     * @param string $normalizedPhone
     * @return void
     */
    public function markVerified(string $normalizedPhone): void
    {
        $this->checkoutSession->setData(self::KEY_VERIFIED, true);
        $this->checkoutSession->setData(self::KEY_PHONE, $normalizedPhone);
    }

    /**
     * @return bool
     */
    public function isVerified(): bool
    {
        return (bool) $this->checkoutSession->getData(self::KEY_VERIFIED);
    }

    /**
     * @return string
     */
    public function getVerifiedPhone(): string
    {
        return (string) $this->checkoutSession->getData(self::KEY_PHONE);
    }

    /**
     * Clear the verification flag, for example after a successful order.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->checkoutSession->unsetData(self::KEY_VERIFIED);
        $this->checkoutSession->unsetData(self::KEY_PHONE);
    }
}
