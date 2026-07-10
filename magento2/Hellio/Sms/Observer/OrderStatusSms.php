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
 * Send the customer an SMS when an order moves to an enabled status.
 *
 * Feature 1: Customer order-status SMS. Fires on sales_order_save_after and
 * compares the persisted status against the loaded one so a message is sent
 * once per transition.
 */
class OrderStatusSms implements ObserverInterface
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
            if (!$this->config->isEnabled($storeId) || !$this->config->isOrderStatusSmsEnabled($storeId)) {
                return;
            }

            $status = (string) $order->getStatus();
            if ($status === '') {
                return;
            }

            // Only act on a real status transition.
            $previous = method_exists($order, 'getOrigData') ? (string) $order->getOrigData('status') : '';
            if ($previous === $status) {
                return;
            }

            $templates = $this->config->getStatusTemplates($storeId);
            if (!isset($templates[$status]) || trim($templates[$status]) === '') {
                return;
            }

            $billing = $order->getBillingAddress();
            $phone = $billing ? (string) $billing->getTelephone() : '';
            if (trim($phone) === '') {
                $this->logger->info('Hellio SMS: order ' . $order->getIncrementId() . ' has no billing phone, skipping status SMS.');
                return;
            }

            $message = $this->renderer->render($templates[$status], $order);
            if (trim($message) === '') {
                return;
            }

            $result = $this->client->sendSms([$phone], $message, null, $storeId);
            if (!$result['success']) {
                $this->logger->warning(
                    'Hellio SMS: status SMS for order ' . $order->getIncrementId()
                    . ' failed: ' . $result['error'] . ' ' . $result['message']
                );
            }
        } catch (\Throwable $e) {
            // Never break order save.
            $this->logger->error('Hellio SMS: order-status observer error: ' . $e->getMessage());
        }
    }
}
