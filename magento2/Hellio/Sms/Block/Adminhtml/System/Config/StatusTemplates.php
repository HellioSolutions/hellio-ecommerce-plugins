<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\Exception\LocalizedException;

/**
 * Dynamic rows editor mapping an order status to an SMS template.
 */
class StatusTemplates extends AbstractFieldArray
{
    /**
     * @var StatusColumn|null
     */
    private $statusRenderer;

    /**
     * Build the grid columns.
     *
     * @return void
     */
    protected function _prepareToRender(): void
    {
        $this->addColumn('status', [
            'label' => __('Order Status'),
            'renderer' => $this->getStatusRenderer(),
        ]);
        $this->addColumn('template', [
            'label' => __('SMS Template'),
            'class' => 'required-entry',
        ]);
        $this->_addAfter = false;
        $this->_addButtonLabel = (string) __('Add Status');
    }

    /**
     * Preselect the status dropdown for each existing row.
     *
     * @param \Magento\Framework\DataObject $row
     * @return void
     */
    protected function _prepareArrayRow(\Magento\Framework\DataObject $row): void
    {
        $status = $row->getData('status');
        $options = [];
        if ($status !== null && $status !== '') {
            $key = 'option_' . $this->getStatusRenderer()->calcOptionHash((string) $status);
            $options[$key] = 'selected="selected"';
        }
        $row->setData('option_extra_attrs', $options);
    }

    /**
     * @return StatusColumn
     * @throws LocalizedException
     */
    private function getStatusRenderer(): StatusColumn
    {
        if ($this->statusRenderer === null) {
            $renderer = $this->getLayout()->createBlock(
                StatusColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            if (!$renderer instanceof StatusColumn) {
                throw new LocalizedException(__('Unable to create the status column renderer.'));
            }
            $this->statusRenderer = $renderer;
        }

        return $this->statusRenderer;
    }
}
