<?php

declare(strict_types=1);

namespace Fintecture\Payment\Model;

use Exception;
use Fintecture\Payment\Gateway\Client;
use Fintecture\Payment\Helper\Fintecture as FintectureHelper;
use Fintecture\Payment\Logger\Logger as FintectureLogger;
use Magento\Checkout\Model\Session;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Payment\Helper\Data as PaymentData;
use Magento\Payment\Model\Config as PaymentConfig;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Fintecture extends AbstractMethod
{
    private const MODULE_VERSION = '1.2.15';
    public const PAYMENT_FINTECTURE_CODE = 'fintecture';
    public const CONFIG_PREFIX = 'payment/fintecture/';

    public $_code = 'fintecture';

    /** @var EncryptorInterface */
    protected $encryptor;

    /** @var FintectureHelper */
    protected $fintectureHelper;

    protected $environment = Environment::ENVIRONMENT_PRODUCTION;

    /** @var Session $checkoutSession */
    protected $checkoutSession;

    /**  @var FintectureLogger */
    protected $fintectureLogger;

    /** @var SessionManagerInterface $coreSession */
    protected $coreSession;

    /** @var OrderSender $orderSender */
    protected $orderSender;

    /** @var InvoiceSender $invoiceSender */
    protected $invoiceSender;

    /** @var InvoiceService $invoiceService */
    protected $invoiceService;

    /** @var ProductMetadataInterface $productMetadata */
    protected $productMetadata;

    /** @var StoreManagerInterface $storeManager */
    protected $storeManager;

    /** @var PaymentConfig $paymentConfig */
    protected $paymentConfig;

    /** @var Transaction $transaction */
    protected $transaction;

    /** @var OrderManagementInterface $orderManagement */
    protected $orderManagement;

    /** @var HistoryFactory orderStatusHistoryFactory */
    private $orderStatusHistoryFactory;

    public function __construct(
        Context $context,
        Registry $registry,
        EncryptorInterface $encryptor,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        PaymentData $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        FintectureHelper $fintectureHelper,
        Session $checkoutSession,
        FintectureLogger $fintectureLogger,
        SessionManagerInterface $coreSession,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender,
        InvoiceService $invoiceService,
        ProductMetadataInterface $productMetadata,
        StoreManagerInterface $storeManager,
        PaymentConfig $paymentConfig,
        Transaction $transaction,
        OrderManagementInterface $orderManagement,
        HistoryFactory $orderStatusHistoryFactory
    ) {
        $this->fintectureHelper = $fintectureHelper;
        $this->checkoutSession = $checkoutSession;
        $this->fintectureLogger = $fintectureLogger;
        $this->coreSession = $coreSession;
        $this->productMetadata = $productMetadata;
        $this->orderSender = $orderSender;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->storeManager = $storeManager;
        $this->paymentConfig = $paymentConfig;
        $this->transaction = $transaction;
        $this->orderManagement = $orderManagement;
        $this->orderStatusHistoryFactory = $orderStatusHistoryFactory;

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger
        );

        $this->encryptor = $encryptor;
        $this->environment = $this->_scopeConfig->getValue(static::CONFIG_PREFIX . 'environment', ScopeInterface::SCOPE_STORE);
    }

    public function handleSuccessTransaction($order, $status, $sessionId, $statuses, $webhook = false)
    {
        /** @var Order $order */
        if (!$order->getId()) {
            $this->fintectureLogger->error('Error', ['message' => 'There is no order id found']);
            return;
        }

        // Don't update order if state has already been set
        if ($order->getState() === $statuses['state']) {
            $this->fintectureLogger->info('Error', [
                'message' => 'State is already set',
                'incrementOrderId' => $order->getIncrementId(),
                'currentState' => $order->getState(),
                'state' => $statuses['state']
            ]);
            return;
        }

        $order->getPayment()->setTransactionId($sessionId);
        $order->getPayment()->setLastTransId($sessionId);
        $order->getPayment()->addTransaction(TransactionInterface::TYPE_ORDER);
        $order->getPayment()->setIsTransactionClosed(0);
        $order->getPayment()->setAdditionalInformation(['status' => $status, 'sessionId' => $sessionId]);
        $order->getPayment()->place();

        $order->setState($statuses['state']);
        $order->setStatus($statuses['status']);

        $order->save();

        $this->orderSender->send($order);

        if ($order->canInvoice()) {
            // Re-enable email sending
            $order->setCanSendNewEmailFlag(true);
            $order->save();

            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->register();
            $invoice->save();
            $transactionSave = $this->transaction
                ->addObject(
                    $invoice
                )->addObject(
                    $invoice->getOrder()
                );
            $transactionSave->save();
            // Send Invoice mail to customer
            $this->invoiceSender->send($invoice);

            $note = $this->fintectureHelper->getStatusHistoryComment($status);
            $note = $webhook ? 'webhook: ' . $note : $note;

            $order->addStatusHistoryComment($note)
                ->setIsCustomerNotified(true)
                ->save();
        }
    }

    public function handleFailedTransaction($order, $status, $sessionId, $webhook = false)
    {
        /** @var Order $order */
        if (!$order->getId()) {
            $this->fintectureLogger->error('Error', ['message' => 'There is no order id found']);
            return;
        }

        try {
            if ($order->canCancel()) {
                if ($this->orderManagement->cancel($order->getEntityId())) {
                    $order->getPayment()->setTransactionId($sessionId);
                    $order->getPayment()->setLastTransId($sessionId);
                    $order->getPayment()->setAdditionalInformation(['status' => $status, 'sessionId' => $sessionId]);

                    $note = $this->fintectureHelper->getStatusHistoryComment($status);
                    $note = $webhook ? 'webhook: ' . $note : $note;

                    $orderStatusHistory = $this->orderStatusHistoryFactory->create()
                            ->setParentId($order->getEntityId())
                            ->setEntityName('order')
                            ->setStatus(Order::STATE_CANCELED)
                            ->setComment($note);
                    $this->orderManagement->addComment($order->getEntityId(), $orderStatusHistory);
                }
            }
        } catch (Exception $e) {
            $this->fintectureLogger->error('Error', ['exception' => $e]);
        }
    }

    public function handleHoldedTransaction($order, $status, $sessionId, $statuses, $webhook = false)
    {
        /** @var Order $order */
        if (!$order->getId()) {
            $this->fintectureLogger->error('Error', ['message' => 'There is no order id found']);
            return;
        }

        // Don't update order if state has already been set
        if ($order->getState() === $statuses['state']) {
            $this->fintectureLogger->info('Error', [
                'message' => 'State is already set',
                'incrementOrderId' => $order->getIncrementId(),
                'currentState' => $order->getState(),
                'state' => $statuses['state']
            ]);
            return;
        } elseif ($order->getState() === 'processing') {
            $this->fintectureLogger->info('Error', [
                'message' => 'State is already set to processing',
                'incrementOrderId' => $order->getIncrementId(),
                'currentState' => $order->getState(),
                'state' => $statuses['state']
            ]);
            return;
        }

        try {
            $order->getPayment()->setTransactionId($sessionId);
            $order->getPayment()->setLastTransId($sessionId);
            $order->getPayment()->setAdditionalInformation(['status' => $status, 'sessionId' => $sessionId]);

            $note = $this->fintectureHelper->getStatusHistoryComment($status);
            $note = $webhook ? 'webhook: ' . $note : $note;
            $order->addStatusHistoryComment($note);

            $order->setState($statuses['state']);
            $order->setStatus($statuses['status']);

            $order->setCustomerNoteNotify(false);
            $order->save();
        } catch (Exception $e) {
            $this->fintectureLogger->error('Error', ['exception' => $e]);
        }
    }

    public function getGatewayClient()
    {
        $gatewayClient = new Client(
            $this->fintectureHelper,
            $this->fintectureLogger,
            [
                'fintectureApiUrl' => $this->getFintectureApiUrl(),
                'fintecturePrivateKey' => $this->getAppPrivateKey(),
                'fintectureAppId' => $this->getAppId(),
                'fintectureAppSecret' => $this->getAppSecret(),
            ]
        );
        return $gatewayClient;
    }

    public function getFintectureApiUrl(): string
    {
        return $this->environment === 'sandbox'
            ? 'https://api-sandbox.fintecture.com/' : 'https://api.fintecture.com/';
    }

    public function getAppPrivateKey(): ?string
    {
        $privateKey = $this->_scopeConfig->getValue(self::CONFIG_PREFIX . 'custom_file_upload_' . $this->environment, ScopeInterface::SCOPE_STORE);
        return $privateKey ? $this->encryptor->decrypt($privateKey) : null;
    }

    public function getShopName(): ?string
    {
        return $this->_scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE);
    }

    public function getAppId(?string $environment = null): ?string
    {
        $environment = $environment ?: $this->environment;
        return $this->_scopeConfig->getValue(static::CONFIG_PREFIX . 'fintecture_app_id_' . $environment, ScopeInterface::SCOPE_STORE);
    }

    public function getAppSecret(): ?string
    {
        return $this->_scopeConfig->getValue(static::CONFIG_PREFIX . 'fintecture_app_secret_' . $this->environment, ScopeInterface::SCOPE_STORE);
    }

    public function isRewriteModeActive(): bool
    {
        return $this->_scopeConfig->getValue('web/seo/use_rewrites', ScopeInterface::SCOPE_STORE) === "1";
    }

    public function getBankType(): ?string
    {
        return $this->_scopeConfig->getValue('payment/fintecture/general/bank_type', ScopeInterface::SCOPE_STORE);
    }

    public function getActive(): ?int
    {
        return (int) $this->_scopeConfig->isSetFlag('payment/fintecture/active', ScopeInterface::SCOPE_STORE);
    }

    public function getShowLogo(): ?int
    {
        return (int) $this->_scopeConfig->isSetFlag('payment/fintecture/general/show_logo', ScopeInterface::SCOPE_STORE);
    }

    public function getPaymentGatewayRedirectUrl(): string
    {
        $this->validateConfigValue();

        $lastRealOrder = $this->checkoutSession->getLastRealOrder();
        if (!$lastRealOrder) {
            $this->fintectureLogger->error('Error', ['message' => 'No order found in session, please try again']);
            throw new LocalizedException(__('No order found in session, please try again'));
        }

        $billingAddress = $lastRealOrder->getBillingAddress();
        if (!$billingAddress) {
            $this->fintectureLogger->error('Error', [
                'message' => 'No billing address found in order, please try again',
                'incrementOrderId' => $lastRealOrder->getIncrementId(),
            ]);
            throw new LocalizedException(__('No billing address found in order, please try again'));
        }

        $data = [
            'meta' => [
                'psu_name' => $billingAddress->getName(),
                'psu_email' => $billingAddress->getEmail(),
                'psu_company' => $billingAddress->getCompany(),
                'psu_phone' => $billingAddress->getTelephone(),
                'psu_ip' => $lastRealOrder->getRemoteIp(),
                'psu_address' => [
                    'street' => implode(' ', $billingAddress->getStreet()),
                    'number' => '',
                    'complement' => '',
                    'zip' => $billingAddress->getPostcode(),
                    'city' => $billingAddress->getCity(),
                    'country' => $billingAddress->getCountryId(),
                ],
            ],
            'data' => [
                'type' => 'PIS',
                'attributes' => [
                    'amount' => number_format($lastRealOrder->getBaseTotalDue(), 2, '.', ''),
                    'currency' => $lastRealOrder->getOrderCurrencyCode(),
                    'communication' => 'FINTECTURE-' . $lastRealOrder->getIncrementId()
                ],
            ],
        ];

        try {
            $gatewayClient = $this->getGatewayClient();
            $state = $gatewayClient->getUid();
            $isRewriteModeActive = $this->isRewriteModeActive();
            $redirectUrl = $this->getResponseUrl();
            $originUrl = $this->getOriginUrl();
            $psuType = $this->getBankType();

            $apiResponse = $gatewayClient->generateConnectURL($data, $isRewriteModeActive, $redirectUrl, $originUrl, $psuType, $state);

            if (!isset($apiResponse['meta'])) {
                $this->fintectureLogger->error('Error', [
                    'message' => 'Error building connect URL',
                    'incrementOrderId' => $lastRealOrder->getIncrementId(),
                    'response' => json_encode($apiResponse['meta']['errors'] ?? '', JSON_UNESCAPED_UNICODE)
                ]);
                $this->checkoutSession->restoreQuote();
                throw new LocalizedException(
                    __('Sorry, something went wrong. Please try again later.')
                );
            } else {
                $sessionId = $apiResponse['meta']['session_id'] ?? '';

                $lastRealOrder->setFintecturePaymentSessionId($sessionId);
                try {
                    $lastRealOrder->save();
                } catch (Exception $e) {
                    $this->fintectureLogger->error('Error', [
                        'exception' => $e,
                        'incrementOrderId' => $lastRealOrder->getIncrementId(),
                    ]);
                }

                $this->coreSession->setPaymentSessionId($sessionId);
                return $apiResponse['meta']['url'] ?? '';
            }
        } catch (Exception $e) {
            $this->fintectureLogger->error('Error', [
                'exception' => $e,
                'incrementOrderId' => $lastRealOrder->getIncrementId(),
            ]);

            $this->checkoutSession->restoreQuote();
            throw new LocalizedException(
                __($e->getMessage())
            );
        }
    }

    public function validateConfigValue(): void
    {
        if (!$this->getFintectureApiUrl()
            || !$this->getAppPrivateKey()
            || !$this->getAppId()
            || !$this->getAppSecret()
        ) {
            throw new LocalizedException(
                __('Something went wrong try another payment method!')
            );
        }
    }

    public function getResponseUrl(): string
    {
        return $this->fintectureHelper->getUrl('fintecture/standard/response');
    }

    public function getOriginUrl(): string
    {
        return $this->fintectureHelper->getUrl('fintecture/standard/response');
    }

    public function getRedirectUrl(): string
    {
        return $this->fintectureHelper->getUrl('fintecture/standard/redirect');
    }

    public function getBeneficiaryName(): ?string
    {
        return $this->_scopeConfig->getValue('beneficiary_name', ScopeInterface::SCOPE_STORE);
    }

    public function getBeneficiaryStreet(): ?string
    {
        return $this->_scopeConfig->getValue('beneficiary_street', ScopeInterface::SCOPE_STORE);
    }

    public function getBeneficiaryNumber(): ?string
    {
        return $this->_scopeConfig->getValue('beneficiary_number', ScopeInterface::SCOPE_STORE);
    }

    public function getBeneficiaryCity(): ?string
    {
        return $this->_scopeConfig->getValue('beneficiary_city', ScopeInterface::SCOPE_STORE);
    }

    public function getBeneficiaryZip(): ?string
    {
        return $this->_scopeConfig->getValue('beneficiary_zip', ScopeInterface::SCOPE_STORE);
    }

    public function getBeneficiaryCountry(): ?string
    {
        return $this->_scopeConfig->getValue('beneficiary_country', ScopeInterface::SCOPE_STORE);
    }

    public function getBeneficiaryIban(): ?string
    {
        return $this->_scopeConfig->getValue('beneficiary_iban', ScopeInterface::SCOPE_STORE);
    }

    public function getBeneficiarySwiftBic(): ?string
    {
        return $this->_scopeConfig->getValue('beneficiary_swift_bic', ScopeInterface::SCOPE_STORE);
    }

    public function getBeneficiaryBankName(): ?string
    {
        return $this->_scopeConfig->getValue('beneficiary_bank_name', ScopeInterface::SCOPE_STORE);
    }

    public function getNumberOfActivePaymentMethods(): int
    {
        return count($this->paymentConfig->getActiveMethods());
    }

    public function getConfigurationSummary(): array
    {
        return [
            'type' => 'php-mg-1',
            'php_version' => PHP_VERSION,
            'shop_name' => $this->getShopName(),
            'shop_domain' => $this->storeManager->getStore()->getBaseUrl(),
            'shop_cms' => 'magento',
            'shop_cms_version' => $this->getMagentoVersion(),
            'module_version' => self::MODULE_VERSION,
            'module_position' => '', // TODO: find way to get to find position
            'shop_payment_methods' => $this->getNumberOfActivePaymentMethods(),
            'module_enabled' => $this->getActive(),
            'module_production' => $this->environment === Environment::ENVIRONMENT_PRODUCTION ? 1 : 0,
            'module_sandbox_app_id' => $this->getAppId(Environment::ENVIRONMENT_SANDBOX),
            'module_production_app_id' => $this->getAppId(Environment::ENVIRONMENT_PRODUCTION),
            'module_branding' => $this->getShowLogo()
        ];
    }

    public function getMagentoVersion(): string
    {
        $version = $this->productMetadata->getVersion();
        if ($version === 'UNKNOWN') {
            $this->fintectureLogger->debug("Can't detect Magento version. It may cause some errors.");
            return '2.4.0'; // assume that the version is the lowest possible
        }
        return $version;
    }
}
