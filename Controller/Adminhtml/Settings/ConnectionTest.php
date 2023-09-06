<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller\Adminhtml\Settings;

use Fintecture\Payment\Gateway\Config\Config;
use Fintecture\Payment\Helper\Fintecture as FintectureHelper;
use Fintecture\Payment\Logger\Logger as FintectureLogger;
use Fintecture\Payment\Model\Environment;
use Fintecture\Util\Validation;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;

class ConnectionTest extends Action
{
    public const CONFIG_PREFIX = 'payment/fintecture/';

    /** @var JsonFactory */
    protected $jsonResultFactory;

    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    /** @var FintectureHelper */
    protected $fintectureHelper;

    /** @var FintectureLogger */
    protected $fintectureLogger;

    /** @var Validator */
    protected $formKeyValidator;

    /** @var Config */
    protected $config;

    /** @var EncryptorInterface */
    protected $encryptor;

    /** @var string */
    protected $environment = Environment::ENVIRONMENT_PRODUCTION;

    public function __construct(
        Context $context,
        JsonFactory $jsonResultFactory,
        ScopeConfigInterface $scopeConfig,
        FintectureHelper $fintectureHelper,
        FintectureLogger $fintectureLogger,
        Validator $formKeyValidator,
        Config $config,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        $this->jsonResultFactory = $jsonResultFactory;
        $this->scopeConfig = $scopeConfig;
        $this->fintectureHelper = $fintectureHelper;
        $this->fintectureLogger = $fintectureLogger;
        $this->formKeyValidator = $formKeyValidator;
        $this->config = $config;
        $this->encryptor = $encryptor;
    }

    public function execute()
    {
        /** @var Http $request */
        $request = $this->getRequest();
        if (!$request->isPost() || !$request->isAjax() || !$this->formKeyValidator->validate($request)) {
            throw new LocalizedException(__('Invalid request'));
        }

        $scopeId = (int) $request->getParam('scopeId');
        $jsParams = $request->getParams();

        // Check infos
        if (empty($jsParams['appId']) || empty($jsParams['appSecret']) || empty($jsParams['environment'])) {
            throw new LocalizedException(__('Some fields are empty'));
        }

        // Handle already saved APP secret
        if ($jsParams['appSecret'] === '******') {
            $jsParams['appSecret'] = $this->config->getAppSecret($jsParams['environment'], $scopeId);
        }

        // Handle already saved private key
        if (empty($jsParams['privateKey'])) {
            $privateKey = $this->config->getAppPrivateKey($jsParams['environment'], $scopeId);
            if ($privateKey) {
                $jsParams['privateKey'] = $this->encryptor->decrypt($privateKey);
            }

            if (!$jsParams['privateKey']) {
                throw new LocalizedException(__('No private key file found'));
            }
        }

        $response = Validation::validCredentials(
            'pis',
            [
                'appId' => $jsParams['appId'],
                'appSecret' => $jsParams['appSecret'],
                'privateKey' => $jsParams['privateKey'],
            ],
            $jsParams['environment']
        );

        $resultJson = $this->jsonResultFactory->create();

        return $resultJson->setData($response);
    }
}
