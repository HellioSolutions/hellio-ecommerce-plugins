<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Controller\Adminhtml\Bulk;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * Render the Bulk SMS compose page.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Hellio_Sms::bulk';

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * @param Context     $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Hellio_Sms::bulk');
        $resultPage->getConfig()->getTitle()->prepend(__('Bulk SMS'));

        return $resultPage;
    }
}
