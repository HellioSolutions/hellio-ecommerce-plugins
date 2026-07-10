<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Block\Adminhtml\System\Config;

use Hellio\Sms\Helper\Config;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders the "Send test SMS" panel in the config form.
 */
class TestSend extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Hellio_Sms::system/config/test_send.phtml';

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Context $context
     * @param Config  $config
     * @param array   $data
     */
    public function __construct(Context $context, Config $config, array $data = [])
    {
        parent::__construct($context, $data);
        $this->config = $config;
    }

    /**
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
     * @return string
     */
    public function getAjaxUrl(): string
    {
        return $this->getUrl('hellio_sms/system/testsend');
    }

    /**
     * Pre-fill value for the Sender ID field.
     *
     * @return string
     */
    public function getDefaultSender(): string
    {
        return $this->config->getSenderId();
    }

    /**
     * @return string
     */
    public function getButtonHtml(): string
    {
        /** @var Button $button */
        $button = $this->getLayout()->createBlock(Button::class);
        $button->setData([
            'id' => 'hellio_sms_test_send_button',
            'label' => __('Send SMS'),
        ]);

        return $button->toHtml();
    }
}
