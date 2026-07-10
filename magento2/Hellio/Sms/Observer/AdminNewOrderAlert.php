<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Observer;

use Hellio\Sms\Helper\Config;
use Hellio\Sms\Model\HellioClient;
use Hellio\Sms\Model\TemplateRenderer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Alert the configured admin numbers when a new order is placed.
 *
 * Feature 2: Admin new-order alert. Fires on sales_order_place_after.
 */
class AdminNewOrderAlert implements ObserverInterface
{
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Config           $config
     * @param HellioClient     $client
     * @param TemplateRenderer $renderer
     * @param LoggerInterface  $logger
     */
    public function __construct(
        Config $config,
        HellioClient $client,
        TemplateRenderer $renderer,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->client = $client;
        $this->renderer = $renderer;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $order = $observer->getEvent()->getData('order');
            if (!$order instanceof OrderInterface) {
                return;
            }

            $storeId = (int) $order->getStoreId();
            if (!$this->config->isEnabled($storeId) || !$this->config->isAdminAlertEnabled($storeId)) {
                return;
            }

            $recipients = $this->config->getAdminRecipients($storeId);
            if (empty($recipients)) {
                return;
            }

            $template = $this->config->getAdminAlertTemplate($storeId);
            $message = $this->renderer->render($template, $order);
            if (trim($message) === '') {
                return;
            }

            $result = $this->client->sendSms($recipients, $message, null, $storeId);
            if (!$result['success']) {
                $this->logger->warning(
                    'Hellio SMS: admin alert for order ' . $order->getIncrementId()
                    . ' failed: ' . $result['error'] . ' ' . $result['message']
                );
            }
        } catch (\Throwable $e) {
            // Never break order placement.
            $this->logger->error('Hellio SMS: admin-alert observer error: ' . $e->getMessage());
        }
    }
}
