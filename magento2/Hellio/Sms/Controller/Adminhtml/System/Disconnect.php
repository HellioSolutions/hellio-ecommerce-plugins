<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Controller\Adminhtml\System;

use Hellio\Sms\Helper\Config;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

/**
 * Clear the stored token and connected email.
 */
class Disconnect extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Hellio_Sms::config';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Context           $context
     * @param JsonFactory       $resultJsonFactory
     * @param WriterInterface   $configWriter
     * @param TypeListInterface $cacheTypeList
     * @param LoggerInterface   $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->logger = $logger;
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();

        try {
            $this->configWriter->delete(Config::XML_PATH_API_TOKEN);
            $this->configWriter->delete(Config::XML_PATH_CONNECTED_EMAIL);
            $this->cacheTypeList->cleanType('config');

            return $result->setData([
                'success' => true,
                'message' => __('Disconnected. The stored token was removed.')->render(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Hellio SMS: disconnect failed: ' . $e->getMessage());

            return $result->setData([
                'success' => false,
                'message' => __('Could not disconnect. Please try again.')->render(),
            ]);
        }
    }
}
