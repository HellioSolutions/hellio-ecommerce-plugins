<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Controller\Otp;

use Hellio\Sms\Helper\Config;
use Hellio\Sms\Model\HellioClient;
use Hellio\Sms\Model\OtpSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

/**
 * Server-side endpoint that verifies the code the customer entered. On success
 * it records a verified flag in the checkout session so order placement is
 * allowed to proceed.
 *
 * Feature 3: Checkout OTP (verify half).
 */
class Verify implements HttpPostActionInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var HellioClient
     */
    private $client;

    /**
     * @var OtpSession
     */
    private $otpSession;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param RequestInterface $request
     * @param JsonFactory      $resultJsonFactory
     * @param Config           $config
     * @param HellioClient     $client
     * @param OtpSession       $otpSession
     * @param LoggerInterface  $logger
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        Config $config,
        HellioClient $client,
        OtpSession $otpSession,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config = $config;
        $this->client = $client;
        $this->otpSession = $otpSession;
        $this->logger = $logger;
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->config->isEnabled() || !$this->config->isOtpEnabled()) {
            // Nothing to verify; treat as verified so checkout is not blocked.
            $this->otpSession->markVerified('');

            return $result->setData([
                'success' => true,
                'verified' => true,
                'message' => __('Phone verification is not required.')->render(),
            ]);
        }

        $phone = trim((string) $this->request->getParam('phone', ''));
        $code = trim((string) $this->request->getParam('code', ''));

        if ($phone === '' || $code === '') {
            return $result->setData([
                'success' => false,
                'verified' => false,
                'message' => __('Please enter the code we sent to your phone.')->render(),
            ]);
        }

        try {
            $response = $this->client->verifyOtp($phone, $code);
            $verified = $response['success']
                && isset($response['data']['verified'])
                && $response['data']['verified'] === true;

            if ($verified) {
                $this->otpSession->markVerified($this->client->normalizePhone($phone));

                return $result->setData([
                    'success' => true,
                    'verified' => true,
                    'message' => __('Your phone number is verified.')->render(),
                ]);
            }

            return $result->setData([
                'success' => false,
                'verified' => false,
                'message' => __('That code is invalid or has expired. Please try again.')->render(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Hellio SMS: OTP verify failed: ' . $e->getMessage());

            return $result->setData([
                'success' => false,
                'verified' => false,
                'message' => __('We could not verify the code right now. Please try again.')->render(),
            ]);
        }
    }
}
