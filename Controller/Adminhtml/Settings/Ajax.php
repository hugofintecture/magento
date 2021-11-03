<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller\Adminhtml\Settings;

use Fintecture\Payment\Gateway\Client;
use Fintecture\Payment\Logger\Logger as FintectureLogger;
use Fintecture\Payment\Model\Environment;
use Fintecture\Payment\Model\Fintecture;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;

class Ajax extends Action
{
    public const CONFIG_PREFIX = 'payment/fintecture/';
    protected $fintectureModel;
    protected $jsonResultFactory;
    protected $scopeConfig;
    protected $environment = Environment::ENVIRONMENT_PRODUCTION;

    /** @var FintectureLogger $fintectureLogger */
    protected $fintectureLogger;

    public function __construct(
        Context $context,
        Fintecture $fintectureModel,
        JsonFactory $jsonResultFactory,
        ScopeConfigInterface $scopeConfig,
        FintectureLogger $fintectureLogger
    ) {
        parent::__construct($context);
        $this->fintectureModel = $fintectureModel;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->scopeConfig = $scopeConfig;
        $this->fintectureLogger = $fintectureLogger;
    }

    public function execute()
    {
        $fintectureConnectParameters = $this->getRequest()->getParams();
        $this->environment = $fintectureConnectParameters['fintectureEnv'] ?? Environment::ENVIRONMENT_PRODUCTION;

        $fintectureAppSecret = $fintectureConnectParameters['fintectureAppSecret'] ?? '';

        if ($fintectureAppSecret === '******') {
            $fintectureAppSecret = $this->scopeConfig->getValue(static::CONFIG_PREFIX . 'fintecture_app_secret_' . $this->environment, ScopeInterface::SCOPE_STORE) ?? '';
        }

        $fintectureAppId = $fintectureConnectParameters['fintectureAppId'] ?? '';
        $fintecturePrivateKey = $fintectureConnectParameters['fintecturePrivateKey'] ?? '';

        if ($fintecturePrivateKey === '') {
            $fintecturePrivateKey = $this->fintectureModel->getAppPrivateKey();
            if (!$fintecturePrivateKey) {
                throw new LocalizedException(__('No private key file found'));
            }
        }

        $clientGateway = new Client(
            [
                'fintectureApiUrl' => $this->getFintectureApiUrl(),
                'fintecturePrivateKey' => $fintecturePrivateKey,
                'fintectureAppId' => $fintectureAppId,
                'fintectureAppSecret' => $fintectureAppSecret,
            ]
        );

        $response = $clientGateway->testConnection();

        $resultJson = $this->jsonResultFactory->create();

        return $resultJson->setData($response);
    }

    public function getFintectureApiUrl()
    {
        return $this->scopeConfig->getValue(
            static::CONFIG_PREFIX . 'fintecture_api_url_' . $this->environment,
            ScopeInterface::SCOPE_STORE
        );
    }
}
