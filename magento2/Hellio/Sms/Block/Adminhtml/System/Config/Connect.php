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
 * Renders the "Connect with your Hellio login" panel in the config form.
 */
class Connect extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Hellio_Sms::system/config/connect.phtml';

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
    public function getConnectUrl(): string
    {
        return $this->getUrl('hellio_sms/system/connect');
    }

    /**
     * @return string
     */
    public function getDisconnectUrl(): string
    {
        return $this->getUrl('hellio_sms/system/disconnect');
    }

    /**
     * @return string
     */
    public function getConnectedEmail(): string
    {
        return $this->config->getConnectedEmail();
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->config->hasApiToken() && $this->getConnectedEmail() !== '';
    }

    /**
     * @param string $id
     * @param string $label
     * @return string
     */
    public function getButtonHtml(string $id, string $label): string
    {
        /** @var Button $button */
        $button = $this->getLayout()->createBlock(Button::class);
        $button->setData([
            'id' => $id,
            'label' => __($label),
        ]);

        return $button->toHtml();
    }
}
