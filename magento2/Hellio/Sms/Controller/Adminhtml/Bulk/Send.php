<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Controller\Adminhtml\Bulk;

use Hellio\Sms\Helper\Config;
use Hellio\Sms\Model\AudienceResolver;
use Hellio\Sms\Model\HellioClient;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

/**
 * Handle the Bulk SMS form submission: resolve the audience, send in chunks of
 * 500, and report sent and failed counts.
 */
class Send extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Hellio_Sms::bulk';

    private const CHUNK_SIZE = 500;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var AudienceResolver
     */
    private $audienceResolver;

    /**
     * @var HellioClient
     */
    private $client;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Context          $context
     * @param Config           $config
     * @param AudienceResolver $audienceResolver
     * @param HellioClient     $client
     * @param LoggerInterface  $logger
     */
    public function __construct(
        Context $context,
        Config $config,
        AudienceResolver $audienceResolver,
        HellioClient $client,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->config = $config;
        $this->audienceResolver = $audienceResolver;
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath('hellio_sms/bulk/index');

        $message = trim((string) $this->getRequest()->getParam('message', ''));
        $audience = (string) $this->getRequest()->getParam('audience', AudienceResolver::AUDIENCE_ALL);
        $status = (string) $this->getRequest()->getParam('order_status', '');
        $pasted = (string) $this->getRequest()->getParam('numbers', '');

        if (!$this->config->isEnabled()) {
            $this->messageManager->addErrorMessage(__('Hellio Messaging is disabled. Enable it in the configuration first.'));
            return $redirect;
        }

        if ($message === '') {
            $this->messageManager->addErrorMessage(__('Please enter a message to send.'));
            return $redirect;
        }

        if (mb_strlen($message) > 1600) {
            $this->messageManager->addErrorMessage(__('The message may not exceed 1600 characters.'));
            return $redirect;
        }

        try {
            $recipients = $this->audienceResolver->resolve($audience, $status, $pasted);
        } catch (\Throwable $e) {
            $this->logger->error('Hellio SMS: bulk audience resolution failed: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('Could not build the recipient list. Check the logs.'));
            return $redirect;
        }

        if (empty($recipients)) {
            $this->messageManager->addWarningMessage(__('No recipients matched the selected audience.'));
            return $redirect;
        }

        $sent = 0;
        $failed = 0;

        foreach (array_chunk($recipients, self::CHUNK_SIZE) as $chunk) {
            $result = $this->client->sendSms($chunk, $message);

            if ($result['success']) {
                $accepted = isset($result['data']['accepted_recipients'])
                    ? (int) $result['data']['accepted_recipients']
                    : count($chunk);
                $sent += $accepted;
                $failed += max(0, count($chunk) - $accepted);
            } else {
                $failed += count($chunk);
                $this->logger->warning(
                    'Hellio SMS: bulk chunk failed: ' . $result['error'] . ' ' . $result['message']
                );
            }
        }

        $this->messageManager->addSuccessMessage(
            __('Bulk SMS complete. Sent: %1. Failed: %2. Recipients: %3.', $sent, $failed, count($recipients))
        );

        return $redirect;
    }
}
