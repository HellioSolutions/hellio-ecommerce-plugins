<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package Hellio_Sms
 */

declare(strict_types=1);

namespace Hellio\Sms\Controller\Adminhtml\System;

use Hellio\Sms\Helper\Config;
use Hellio\Sms\Model\HellioClient;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Psr\Log\LoggerInterface;

/**
 * Exchange a Hellio login for an API token and store it encrypted.
 *
 * The password is used once for the exchange and never persisted. Only the
 * returned token (encrypted) and the account email are saved.
 */
class Connect extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Hellio_Sms::config';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var HellioClient
     */
    private $client;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Context            $context
     * @param JsonFactory        $resultJsonFactory
     * @param HellioClient       $client
     * @param WriterInterface    $configWriter
     * @param EncryptorInterface $encryptor
     * @param TypeListInterface  $cacheTypeList
     * @param LoggerInterface    $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        HellioClient $client,
        WriterInterface $configWriter,
        EncryptorInterface $encryptor,
        TypeListInterface $cacheTypeList,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->client = $client;
        $this->configWriter = $configWriter;
        $this->encryptor = $encryptor;
        $this->cacheTypeList = $cacheTypeList;
        $this->logger = $logger;
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();

        $email = trim((string) $this->getRequest()->getParam('email', ''));
        $password = (string) $this->getRequest()->getParam('password', '');
        $twoFactor = trim((string) $this->getRequest()->getParam('two_factor_code', ''));

        if ($email === '' || $password === '') {
            return $result->setData([
                'success' => false,
                'message' => __('Please enter your email and password.')->render(),
            ]);
        }

        try {
            $response = $this->client->createToken(
                $email,
                $password,
                'Magento',
                $twoFactor !== '' ? $twoFactor : null
            );

            if ($response['success']) {
                $token = isset($response['data']['token']) ? (string) $response['data']['token'] : '';
                if ($token === '') {
                    return $result->setData([
                        'success' => false,
                        'message' => __('Connected, but no token was returned. Please try again.')->render(),
                    ]);
                }

                $connectedEmail = $this->resolveEmail($response, $email);
                $this->storeToken($token, $connectedEmail);

                return $result->setData([
                    'success' => true,
                    'email' => $connectedEmail,
                    'message' => __('Connected as %1.', $connectedEmail)->render(),
                ]);
            }

            if ($response['error'] === 'two_factor_required') {
                return $result->setData([
                    'success' => false,
                    'two_factor_required' => true,
                    'message' => __('Enter your two-factor code, then connect again.')->render(),
                ]);
            }

            return $result->setData([
                'success' => false,
                'message' => $this->friendlyError($response),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Hellio SMS: connect failed: ' . $e->getMessage());

            return $result->setData([
                'success' => false,
                'message' => __('We could not connect right now. Please try again.')->render(),
            ]);
        }
    }

    /**
     * Persist the token (encrypted) and the connected email at the default scope.
     *
     * @param string $token
     * @param string $email
     * @return void
     */
    private function storeToken(string $token, string $email): void
    {
        $this->configWriter->save(Config::XML_PATH_API_TOKEN, $this->encryptor->encrypt($token));
        $this->configWriter->save(Config::XML_PATH_CONNECTED_EMAIL, $email);
        $this->cacheTypeList->cleanType('config');
    }

    /**
     * @param array  $response
     * @param string $fallback
     * @return string
     */
    private function resolveEmail(array $response, string $fallback): string
    {
        if (isset($response['data']['user']['email']) && $response['data']['user']['email'] !== '') {
            return (string) $response['data']['user']['email'];
        }

        return $fallback;
    }

    /**
     * @param array $response
     * @return string
     */
    private function friendlyError(array $response): string
    {
        switch ($response['error']) {
            case 'invalid_credentials':
                return (string) __('That email or password was not recognized.');
            case 'email_unverified':
                return (string) __('Please verify your Hellio account email first.');
            case 'account_locked':
                return (string) __('Your account is locked. Contact Hellio support.');
            case 'throttled':
                return (string) __('Too many attempts. Please wait a moment and try again.');
            default:
                return $response['message'] !== ''
                    ? (string) $response['message']
                    : (string) __('Connection failed. Please try again.');
        }
    }
}
