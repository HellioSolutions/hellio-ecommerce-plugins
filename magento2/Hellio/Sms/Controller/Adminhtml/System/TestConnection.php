<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Controller\Adminhtml\System;

use Hellio\Sms\Model\HellioClient;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Backs the "Test connection" config button. Calls GET /v1/balance and returns
 * the wallet balance or the API error as JSON.
 */
class TestConnection extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Hellio_Sms::config';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var HellioClient
     */
    private $client;

    /**
     * @param Context      $context
     * @param JsonFactory  $resultJsonFactory
     * @param HellioClient $client
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        HellioClient $client
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->client = $client;
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();

        try {
            $response = $this->client->getBalance();

            if ($response['success']) {
                return $result->setData([
                    'success' => true,
                    'message' => __('Connection OK. %1', $this->describeBalance($response))->render(),
                ]);
            }

            return $result->setData([
                'success' => false,
                'message' => __('Connection failed: %1', $response['message'] ?: $response['error'])->render(),
            ]);
        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => __('Connection failed: %1', $e->getMessage())->render(),
            ]);
        }
    }

    /**
     * Build a friendly balance summary from the response body.
     *
     * @param array $response
     * @return string
     */
    private function describeBalance(array $response): string
    {
        $data = !empty($response['data']) ? $response['data'] : $response['raw'];

        $balance = null;
        foreach (['balance', 'wallet_balance', 'amount', 'available'] as $key) {
            if (isset($data[$key])) {
                $balance = $data[$key];
                break;
            }
        }

        $currency = '';
        foreach (['currency', 'currency_code'] as $key) {
            if (isset($data[$key])) {
                $currency = (string) $data[$key];
                break;
            }
        }

        if ($balance === null) {
            return (string) __('Wallet reachable.');
        }

        return trim((string) __('Balance: %1 %2', $balance, $currency));
    }
}
