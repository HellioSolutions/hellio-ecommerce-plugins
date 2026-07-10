<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Block\Adminhtml;

use Hellio\Sms\Model\AudienceResolver;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory as StatusCollectionFactory;

/**
 * View model for the Bulk SMS admin form.
 */
class Bulk extends Template
{
    /**
     * @var StatusCollectionFactory
     */
    private $statusCollectionFactory;

    /**
     * @param Context                 $context
     * @param StatusCollectionFactory $statusCollectionFactory
     * @param array                   $data
     */
    public function __construct(
        Context $context,
        StatusCollectionFactory $statusCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->statusCollectionFactory = $statusCollectionFactory;
    }

    /**
     * Form post target.
     *
     * @return string
     */
    public function getFormActionUrl(): string
    {
        return $this->getUrl('hellio_sms/bulk/send');
    }

    /**
     * Audience choices for the select.
     *
     * @return array<string, string>
     */
    public function getAudienceOptions(): array
    {
        return [
            AudienceResolver::AUDIENCE_ALL => (string) __('All customers'),
            AudienceResolver::AUDIENCE_STATUS => (string) __('Customers by order status'),
            AudienceResolver::AUDIENCE_LIST => (string) __('Pasted list of numbers'),
        ];
    }

    /**
     * Order status choices for the "by status" audience.
     *
     * @return array<string, string>
     */
    public function getStatusOptions(): array
    {
        $options = [];
        try {
            $collection = $this->statusCollectionFactory->create();
            foreach ($collection as $status) {
                $code = (string) $status->getData('status');
                $label = (string) $status->getData('label');
                if ($code !== '') {
                    $options[$code] = $label !== '' ? $label : $code;
                }
            }
        } catch (\Throwable $e) {
            $options = [];
        }

        return $options;
    }
}
