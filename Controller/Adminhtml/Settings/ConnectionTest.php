<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller\Adminhtml\Settings;

use Fintecture\Payment\Helper\Fintecture as FintectureHelper;
use Fintecture\Payment\Logger\Logger as FintectureLogger;
use Fintecture\Payment\Model\Environment;
use Fintecture\Payment\Model\Fintecture;
use Fintecture\Util\Validation;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;

class ConnectionTest extends Action
{
    public const CONFIG_PREFIX = 'payment/fintecture/';

    /** @var Fintecture */
    protected $fintectureModel;

    /** @var JsonFactory */
    protected $jsonResultFactory;

    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    /** @var FintectureHelper */
    protected $fintectureHelper;

    /** @var FintectureLogger */
    protected $fintectureLogger;

    /** @var Validator*/
    protected $formKeyValidator;

    /** @var string */
    protected $environment = Environment::ENVIRONMENT_PRODUCTION;

    public function __construct(
        Context $context,
        Fintecture $fintectureModel,
        JsonFactory $jsonResultFactory,
        ScopeConfigInterface $scopeConfig,
        FintectureHelper $fintectureHelper,
        FintectureLogger $fintectureLogger,
        Validator $formKeyValidator
    ) {
        parent::__construct($context);
        $this->fintectureModel = $fintectureModel;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->scopeConfig = $scopeConfig;
        $this->fintectureHelper = $fintectureHelper;
        $this->fintectureLogger = $fintectureLogger;
        $this->formKeyValidator = $formKeyValidator;
    }

    public function execute()
    {
        /** @var Http $request */
        $request = $this->getRequest();
        if (!$request->isPost() || !$request->isAjax() || !$this->formKeyValidator->validate($request)) {
            throw new LocalizedException(__('Invalid request'));
        }

        $scope = $request->getParam('scope');
        $scopeId = (int) $request->getParam('scopeId');
        $jsParams = $request->getParams();

        // Check infos
        if (empty($jsParams['appId']) || empty($jsParams['appSecret']) || empty($jsParams['environment'])) {
            throw new LocalizedException(__('Some fields are empty'));
        }

        // Handle already saved app secret
        if ($jsParams['appSecret'] === '******') {
            $jsParams['appSecret'] = $this->fintectureModel->getAppSecret($jsParams['environment'], $scope, $scopeId);
        }

        // Handle already saved private key
        if (empty($jsParams['privateKey'])) {
            $jsParams['privateKey'] = $this->fintectureModel->getAppPrivateKey($jsParams['environment'], $scope, $scopeId);
            if (!$jsParams['privateKey']) {
                throw new LocalizedException(__('No private key file found'));
            }
        }

        $response = Validation::validCredentials(
            'pis',
            [
                'appId' => $jsParams['appId'],
                'appSecret' => $jsParams['appSecret'],
                'privateKey' => $jsParams['privateKey']
            ],
            $jsParams['environment']
        );

        $resultJson = $this->jsonResultFactory->create();

        return $resultJson->setData($response);
    }
}
