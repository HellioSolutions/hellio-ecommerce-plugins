<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders the "Test connection" button in the config form.
 */
class TestConnection extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Hellio_Sms::system/config/test_connection.phtml';

    /**
     * @param Context $context
     * @param array   $data
     */
    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    /**
     * Hide the scope label column for this row.
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    /**
     * URL the button posts to.
     *
     * @return string
     */
    public function getAjaxUrl(): string
    {
        return $this->getUrl('hellio_sms/system/testconnection');
    }

    /**
     * @return string
     */
    public function getButtonHtml(): string
    {
        /** @var Button $button */
        $button = $this->getLayout()->createBlock(Button::class);
        $button->setData([
            'id' => 'hellio_sms_test_connection_button',
            'label' => __('Test Connection'),
        ]);

        return $button->toHtml();
    }
}
