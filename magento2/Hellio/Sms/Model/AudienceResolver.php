<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Model;

use Magento\Sales\Model\ResourceModel\Order\Address\CollectionFactory as AddressCollectionFactory;

/**
 * Resolve a bulk-SMS audience into a de-duplicated list of phone numbers.
 *
 * Recipients are read from order billing addresses, which covers both
 * registered customers and guests. Numbers are normalized by the client
 * before sending.
 */
class AudienceResolver
{
    public const AUDIENCE_ALL = 'all';
    public const AUDIENCE_STATUS = 'status';
    public const AUDIENCE_LIST = 'list';

    /**
     * @var AddressCollectionFactory
     */
    private $addressCollectionFactory;

    /**
     * @param AddressCollectionFactory $addressCollectionFactory
     */
    public function __construct(AddressCollectionFactory $addressCollectionFactory)
    {
        $this->addressCollectionFactory = $addressCollectionFactory;
    }

    /**
     * @param string $audience One of the AUDIENCE_* constants.
     * @param string $status   Order status code, used when audience is "status".
     * @param string $pasted   Newline or comma separated numbers, used for "list".
     * @return string[] Unique, non-empty phone numbers.
     */
    public function resolve(string $audience, string $status = '', string $pasted = ''): array
    {
        switch ($audience) {
            case self::AUDIENCE_LIST:
                return $this->fromPastedList($pasted);
            case self::AUDIENCE_STATUS:
                return $this->fromOrders($status);
            case self::AUDIENCE_ALL:
            default:
                return $this->fromOrders('');
        }
    }

    /**
     * @param string $pasted
     * @return string[]
     */
    private function fromPastedList(string $pasted): array
    {
        $parts = preg_split('/[\r\n,;]+/', $pasted) ?: [];
        $numbers = array_map('trim', $parts);

        return $this->unique($numbers);
    }

    /**
     * Collect billing telephones from orders, optionally filtered by status.
     *
     * @param string $status
     * @return string[]
     */
    private function fromOrders(string $status): array
    {
        $collection = $this->addressCollectionFactory->create();
        $collection->addFieldToSelect('telephone');
        $collection->addFieldToFilter('address_type', 'billing');
        $collection->addFieldToFilter('telephone', ['notnull' => true]);
        $collection->addFieldToFilter('telephone', ['neq' => '']);

        if ($status !== '') {
            $collection->getSelect()->join(
                ['hellio_order' => $collection->getTable('sales_order')],
                'main_table.parent_id = hellio_order.entity_id',
                []
            )->where('hellio_order.status = ?', $status);
        }

        $numbers = [];
        foreach ($collection as $address) {
            $numbers[] = (string) $address->getData('telephone');
        }

        return $this->unique($numbers);
    }

    /**
     * @param string[] $numbers
     * @return string[]
     */
    private function unique(array $numbers): array
    {
        $filtered = array_filter(array_map('trim', $numbers), static function ($n) {
            return $n !== '';
        });

        return array_values(array_unique($filtered));
    }
}
