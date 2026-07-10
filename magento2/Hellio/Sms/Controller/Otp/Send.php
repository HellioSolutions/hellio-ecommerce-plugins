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
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

/**
 * Server-side endpoint that triggers an OTP send. The API token stays on the
 * server; the browser only ever sees success or an error message.
 *
 * Feature 3: Checkout OTP (send half).
 */
class Send implements HttpPostActionInterface
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param RequestInterface $request
     * @param JsonFactory      $resultJsonFactory
     * @param Config           $config
     * @param HellioClient     $client
     * @param LoggerInterface  $logger
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        Config $config,
        HellioClient $client,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config = $config;
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->config->isEnabled() || !$this->config->isOtpEnabled()) {
            return $result->setData([
                'success' => false,
                'message' => __('Phone verification is not enabled.')->render(),
            ]);
        }

        $phone = trim((string) $this->request->getParam('phone', ''));
        if ($phone === '') {
            return $result->setData([
                'success' => false,
                'message' => __('Please provide a phone number.')->render(),
            ]);
        }

        try {
            $response = $this->client->sendOtp($phone, 'checkout');

            if ($response['success']) {
                return $result->setData([
                    'success' => true,
                    'message' => __('We sent a verification code to your phone.')->render(),
                ]);
            }

            return $result->setData([
                'success' => false,
                'message' => $this->friendlyError($response),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Hellio SMS: OTP send failed: ' . $e->getMessage());

            return $result->setData([
                'success' => false,
                'message' => __('We could not send a code right now. Please try again.')->render(),
            ]);
        }
    }

    /**
     * @param array $response
     * @return string
     */
    private function friendlyError(array $response): string
    {
        if ($response['error'] === 'throttled') {
            return (string) __('Too many attempts. Please wait a moment and try again.');
        }
        if ($response['error'] === 'insufficient_balance' || $response['error'] === 'spend_limit_exceeded') {
            return (string) __('Verification is temporarily unavailable. Please try again later.');
        }

        return $response['message'] !== ''
            ? (string) $response['message']
            : (string) __('We could not send a code. Please check the number and try again.');
    }
}
