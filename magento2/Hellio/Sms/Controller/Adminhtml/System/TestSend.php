<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Controller\Adminhtml\System;

use Hellio\Sms\Helper\Config;
use Hellio\Sms\Model\HellioClient;
use Hellio\Sms\Model\TemplateRenderer;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Send an SMS to one or many pasted numbers. Placeholders in the message render
 * once against the most recent order (or stay blank when there is none), then
 * the message goes out through the Hellio API in chunks of 500. The accepted
 * count with a status and reference, or the error, is returned as JSON.
 */
class TestSend extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Hellio_Sms::config';

    private const CHUNK_SIZE = 500;

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
     * @var TemplateRenderer
     */
    private $renderer;

    /**
     * @var OrderCollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Context                $context
     * @param JsonFactory            $resultJsonFactory
     * @param Config                 $config
     * @param HellioClient           $client
     * @param TemplateRenderer       $renderer
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param LoggerInterface        $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Config $config,
        HellioClient $client,
        TemplateRenderer $renderer,
        OrderCollectionFactory $orderCollectionFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config = $config;
        $this->client = $client;
        $this->renderer = $renderer;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->config->isEnabled()) {
            return $result->setData([
                'success' => false,
                'message' => __('Enable Hellio Messaging before sending a test.')->render(),
            ]);
        }

        $recipients = $this->parseRecipients((string) $this->getRequest()->getParam('recipient', ''));
        $sender = trim((string) $this->getRequest()->getParam('sender', ''));
        $message = (string) $this->getRequest()->getParam('message', '');

        if (empty($recipients) || trim($message) === '') {
            return $result->setData([
                'success' => false,
                'message' => __('Enter at least one recipient and a message.')->render(),
            ]);
        }

        if (mb_strlen($message) > 1600) {
            return $result->setData([
                'success' => false,
                'message' => __('The message may not exceed 1600 characters.')->render(),
            ]);
        }

        try {
            $order = $this->getSampleOrder();
            $rendered = $order !== null ? $this->renderer->render($message, $order) : $this->stripPlaceholders($message);

            $senderId = $sender !== '' ? $sender : null;

            $accepted = 0;
            $failed = 0;
            $reference = '';
            $status = '';
            $lastError = '';

            foreach (array_chunk($recipients, self::CHUNK_SIZE) as $chunk) {
                $response = $this->client->sendSms($chunk, $rendered, $senderId);

                if ($response['success']) {
                    $chunkAccepted = isset($response['data']['accepted_recipients'])
                        ? (int) $response['data']['accepted_recipients']
                        : count($chunk);
                    $accepted += $chunkAccepted;
                    $failed += max(0, count($chunk) - $chunkAccepted);

                    if ($reference === '' && !empty($response['data']['reference'])) {
                        $reference = (string) $response['data']['reference'];
                    }
                    if ($status === '' && !empty($response['data']['status'])) {
                        $status = (string) $response['data']['status'];
                    }
                } else {
                    $failed += count($chunk);
                    $lastError = $response['message'] !== '' ? $response['message'] : $response['error'];
                }
            }

            if ($accepted > 0) {
                return $result->setData([
                    'success' => true,
                    'message' => __(
                        'Sent to %1 of %2 recipient(s). Status: %3. Reference: %4.',
                        $accepted,
                        count($recipients),
                        $status !== '' ? $status : 'accepted',
                        $reference !== '' ? $reference : '-'
                    )->render(),
                ]);
            }

            return $result->setData([
                'success' => false,
                'message' => __('Send failed: %1', $lastError !== '' ? $lastError : __('no recipients were accepted'))->render(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Hellio SMS: test send failed: ' . $e->getMessage());

            return $result->setData([
                'success' => false,
                'message' => __('We could not send the SMS right now. Please try again.')->render(),
            ]);
        }
    }

    /**
     * Split a raw recipients string into a trimmed, de-duplicated list.
     *
     * @param string $raw
     * @return string[]
     */
    private function parseRecipients(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', $raw) ?: [];
        $numbers = array_filter(array_map('trim', $parts), static function ($n) {
            return $n !== '';
        });

        return array_values(array_unique($numbers));
    }

    /**
     * Most recent order, or null when the store has none.
     *
     * @return OrderInterface|null
     */
    private function getSampleOrder(): ?OrderInterface
    {
        try {
            $collection = $this->orderCollectionFactory->create();
            $collection->setOrder('entity_id', 'DESC');
            $collection->setPageSize(1);
            $collection->setCurPage(1);

            $order = $collection->getFirstItem();

            return $order && $order->getId() ? $order : null;
        } catch (\Throwable $e) {
            $this->logger->warning('Hellio SMS: could not load a sample order: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Blank out placeholders when there is no order to render against.
     *
     * @param string $message
     * @return string
     */
    private function stripPlaceholders(string $message): string
    {
        return preg_replace('/\{[a-z_]+\}/', '', $message) ?? $message;
    }
}
