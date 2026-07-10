<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Model;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Store;

/**
 * Render an SMS template against a sales order.
 *
 * Placeholders resolve from the order, its billing address, and the store.
 * Unknown placeholders render as an empty string.
 */
class TemplateRenderer
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @param StoreManagerInterface $storeManager
     * @param TimezoneInterface     $timezone
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        TimezoneInterface $timezone
    ) {
        $this->storeManager = $storeManager;
        $this->timezone = $timezone;
    }

    /**
     * @param string         $template
     * @param OrderInterface $order
     * @return string
     */
    public function render(string $template, OrderInterface $order): string
    {
        if ($template === '') {
            return '';
        }

        return strtr($template, $this->buildTokens($order));
    }

    /**
     * @param OrderInterface $order
     * @return array<string, string>
     */
    private function buildTokens(OrderInterface $order): array
    {
        $billing = $order->getBillingAddress();
        $firstName = $billing ? (string) $billing->getFirstname() : (string) $order->getCustomerFirstname();
        $lastName = $billing ? (string) $billing->getLastname() : (string) $order->getCustomerLastname();
        $fullName = trim($firstName . ' ' . $lastName);

        $currency = (string) $order->getOrderCurrencyCode();
        $total = number_format((float) $order->getGrandTotal(), 2, '.', '');

        return [
            '{order_id}' => (string) $order->getEntityId(),
            '{order_number}' => (string) $order->getIncrementId(),
            '{order_status}' => (string) $order->getStatus(),
            '{order_total}' => $total,
            '{currency}' => $currency,
            '{customer_name}' => $fullName,
            '{customer_first_name}' => $firstName,
            '{store_name}' => $this->getStoreName($order),
            '{shop_url}' => $this->getBaseUrl($order),
            '{tracking_url}' => $this->getTrackingUrl($order),
            '{date}' => $this->timezone->formatDate($order->getCreatedAt()),
        ];
    }

    /**
     * @param OrderInterface $order
     * @return string
     */
    private function getStoreName(OrderInterface $order): string
    {
        try {
            $storeId = (int) $order->getStoreId();
            $store = $this->storeManager->getStore($storeId);
            $name = $store->getFrontendName();

            return $name !== '' ? $name : (string) $order->getStoreName();
        } catch (\Throwable $e) {
            return (string) $order->getStoreName();
        }
    }

    /**
     * @param OrderInterface $order
     * @return string
     */
    private function getBaseUrl(OrderInterface $order): string
    {
        try {
            $store = $this->storeManager->getStore((int) $order->getStoreId());

            return rtrim($store->getBaseUrl(Store::URL_TYPE_WEB), '/');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @param OrderInterface $order
     * @return string
     */
    private function getTrackingUrl(OrderInterface $order): string
    {
        $base = $this->getBaseUrl($order);
        if ($base === '') {
            return '';
        }

        return $base . '/sales/order/view/order_id/' . $order->getEntityId() . '/';
    }
}
