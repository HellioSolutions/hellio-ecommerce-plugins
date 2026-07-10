<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Block\Adminhtml\System\Config;

use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;

/**
 * Select element listing every order status, used as the first column of the
 * status-templates dynamic rows grid.
 */
class StatusColumn extends Select
{
    /**
     * @var CollectionFactory
     */
    private $statusCollectionFactory;

    /**
     * @var array|null
     */
    private $options;

    /**
     * @param Context           $context
     * @param CollectionFactory $statusCollectionFactory
     * @param array             $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $statusCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->statusCollectionFactory = $statusCollectionFactory;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setInputId($value)
    {
        return $this->setId($value);
    }

    /**
     * @return string
     */
    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            foreach ($this->loadOptions() as $value => $label) {
                $this->addOption($value, $label);
            }
        }

        return parent::_toHtml();
    }

    /**
     * @return array<string, string>
     */
    private function loadOptions(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $this->options = [];
        try {
            $collection = $this->statusCollectionFactory->create();
            foreach ($collection as $status) {
                $code = (string) $status->getData('status');
                $label = (string) $status->getData('label');
                if ($code !== '') {
                    $this->options[$code] = $label !== '' ? $label : $code;
                }
            }
        } catch (\Throwable $e) {
            $this->options = [];
        }

        return $this->options;
    }
}
