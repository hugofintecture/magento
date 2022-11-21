<?php

declare(strict_types=1);

namespace Fintecture\Payment\Model;

use Exception;
use Fintecture\Payment\Helper\Fintecture as FintectureHelper;
use Fintecture\Payment\Logger\Logger as FintectureLogger;
use Fintecture\PisClient;
use Fintecture\Util\Crypto;
use Magento\Checkout\Model\Session;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\LoginAsCustomerApi\Api\GetLoggedAsCustomerAdminIdInterface;
use Magento\Payment\Helper\Data as PaymentData;
use Magento\Payment\Model\Config as PaymentConfig;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\RefundAdapterInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\HttpClient\Psr18Client;

/** @phpstan-ignore-next-line : we will refactor the plugin without AbstractMethod */
class Fintecture extends AbstractMethod
{
    public const CODE = 'fintecture';
    public const CONFIG_PREFIX = 'payment/fintecture/';
    public const MODULE_VERSION = '2.1.1';

    private const PAYMENT_COMMUNICATION = 'FINTECTURE-';
    private const REFUND_COMMUNICATION = 'REFUND FINTECTURE-';

    public $_code = 'fintecture';

    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;

    /** @var PisClient $pisClient */
    public $pisClient;

    /** @var EncryptorInterface */
    protected $encryptor;

    /** @var FintectureHelper */
    protected $fintectureHelper;

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

    /** @var RefundAdapterInterface */
    private $refundAdapter;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var InvoiceRepositoryInterface */
    private $invoiceRepository;

    /** @var CreditmemoRepositoryInterface */
    private $creditmemoRepository;

    /** @phpstan-ignore-next-line : ignore error for deprecated registry (Magento side) */
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
        RefundAdapterInterface $refundAdapter,
        OrderRepositoryInterface $orderRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        CreditmemoRepositoryInterface $creditmemoRepository
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
        $this->refundAdapter = $refundAdapter;
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->creditmemoRepository = $creditmemoRepository;

        /** @phpstan-ignore-next-line : we will refactor the plugin without AbstractMethod */
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

        try {
            $this->pisClient = new PisClient([
                'appId' => $this->getAppId(),
                'appSecret' => $this->getAppSecret(),
                'privateKey' => $this->getAppPrivateKey(),
                'environment' => $this->getAppEnvironment(),
            ], new Psr18Client());
        } catch (\Exception $e) {
            $this->fintectureLogger->error('Connection error', [
                'exception' => $e,
                'message' => "Can't create PISClient"
            ]);
        }
    }

    public function handleSuccessTransaction(
        Order $order,
        string $status,
        string $sessionId,
        array $statuses,
        bool $webhook = false
    ): void {
        if (!$order->getId()) {
            $this->fintectureLogger->error('Error', ['message' => 'There is no order id found']);
            return;
        }

        /** @var Payment $payment */
        $payment = $order->getPayment();

        $payment->setTransactionId($sessionId);
        $payment->setLastTransId($sessionId);
        $payment->addTransaction(TransactionInterface::TYPE_ORDER);
        $payment->setIsTransactionClosed(false);
        $payment->setAdditionalInformation(['status' => $status, 'sessionId' => $sessionId]);
        $payment->place();

        $order->setState($statuses['state']);
        $order->setStatus($statuses['status']);

        $note = $this->fintectureHelper->getStatusHistoryComment($status);
        $note = $webhook ? 'webhook: ' . $note : $note;
        $order->addCommentToStatusHistory($note);

        $this->orderRepository->save($order);

        $this->orderSender->send($order);

        if ($order->canInvoice() && $this->getInvoicingActive()) {
            // Re-enable email sending
            $order->setCanSendNewEmailFlag(true);
            $this->orderRepository->save($order);

            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setTransactionId($sessionId);
            $invoice->register();
            $this->invoiceRepository->save($invoice);
            $transactionSave = $this->transaction
                ->addObject(
                    $invoice
                )->addObject(
                    $invoice->getOrder()
                );
            $transactionSave->save();
            // Send Invoice mail to customer
            $this->invoiceSender->send($invoice);

            $order->setIsCustomerNotified(true);
            $this->orderRepository->save($order);
        }
    }

    public function handleFailedTransaction(
        Order $order,
        string $status,
        string $sessionId,
        array $statuses,
        bool $webhook = false
    ): void {
        /** @var Order $order */
        if (!$order->getId()) {
            $this->fintectureLogger->error('Error', ['message' => 'There is no order id found']);
            return;
        }

        try {
            if ($order->canCancel()) {
                if ($this->orderManagement->cancel($order->getEntityId())) {
                    /** @var Payment $payment */
                    $payment = $order->getPayment();
                    $payment->setTransactionId($sessionId);
                    $payment->setLastTransId($sessionId);
                    $payment->setAdditionalInformation(['status' => $status, 'sessionId' => $sessionId]);

                    $order->setStatus($statuses['status']);

                    $note = $this->fintectureHelper->getStatusHistoryComment($status);
                    $note = $webhook ? 'webhook: ' . $note : $note;
                    $order->addCommentToStatusHistory($note);

                    $this->orderRepository->save($order);
                }
            }
        } catch (Exception $e) {
            $this->fintectureLogger->error('Error', ['exception' => $e]);
        }
    }

    public function handleHoldedTransaction(
        Order $order,
        string $status,
        string $sessionId,
        array $statuses,
        bool $webhook = false
    ): void {
        if (!$order->getId()) {
            $this->fintectureLogger->error('Error', ['message' => 'There is no order id found']);
            return;
        }

        try {
            /** @var Payment $payment */
            $payment = $order->getPayment();
            $payment->setTransactionId($sessionId);
            $payment->setLastTransId($sessionId);
            $payment->setAdditionalInformation(['status' => $status, 'sessionId' => $sessionId]);

            $order->setState($statuses['state']);
            $order->setStatus($statuses['status']);

            $note = $this->fintectureHelper->getStatusHistoryComment($status);
            $note = $webhook ? 'webhook: ' . $note : $note;
            $order->addCommentToStatusHistory($note);

            $order->setCustomerNoteNotify(0);

            $this->orderRepository->save($order);
        } catch (Exception $e) {
            $this->fintectureLogger->error('Error', ['exception' => $e]);
        }
    }

    public function createRefund(OrderInterface $order, CreditmemoInterface $creditmemo): void
    {
        /** @var Order $order */

        $amount = $creditmemo->getBaseGrandTotal();
        $sessionId = $order->getFintecturePaymentSessionId();

        $incrementOrderId = $order->getIncrementId();

        $nbCreditmemos = count($order->getCreditmemosCollection()) + 1;

        $this->fintectureLogger->info('Refund started', [
            'incrementOrderId' => $incrementOrderId,
            'amount' => $amount,
            'sessionId' => $sessionId,
        ]);

        $data = [
            'meta' => [
                'session_id' => $sessionId,
            ],
            'data' => [
                'attributes' => [
                    'amount' => number_format((float) $amount, 2, '.', ''),
                    'communication' => self::REFUND_COMMUNICATION . $incrementOrderId . '-' . $nbCreditmemos,
                ],
            ],
        ];

        try {
            $state = $creditmemo->getTransactionId();

            if ($state) {
                /** @phpstan-ignore-next-line */
                $pisToken = $this->pisClient->token->generate();
                if (!$pisToken->error) {
                    $this->pisClient->setAccessToken($pisToken); // set token of PIS client
                } else {
                    throw new Exception($pisToken->errorMsg);
                }

                /** @phpstan-ignore-next-line */
                $apiResponse = $this->pisClient->refund->generate($data, $state);
                if (!$apiResponse->error) {
                    if ($order->canHold()) {
                        $order->hold();
                    }
                    $order->addCommentToStatusHistory(__('The refund link has been send.')->render());
                    $this->orderRepository->save($order);

                    $this->fintectureLogger->info('The refund link has been send', [
                        'incrementOrderId' => $incrementOrderId
                    ]);
                } else {
                    $this->fintectureLogger->error('Refund error', [
                        'message' => 'Invalid API response',
                        'incrementOrderId' => $incrementOrderId,
                        'response' => $apiResponse->errorMsg
                    ]);
                    throw new Exception($apiResponse->errorMsg);
                }
            } else {
                $this->fintectureLogger->error('Refund error', [
                    'message' => "State of creditmemo if empty",
                    'incrementOrderId' => $incrementOrderId,
                ]);
            }
        } catch (Exception $e) {
            $this->fintectureLogger->error('Refund error', [
                'exception' => $e,
                'incrementOrderId' => $incrementOrderId,
            ]);
            throw new LocalizedException(
                __('Sorry, something went wrong. Please try again later.')
            );
        }
    }

    public function applyRefund(OrderInterface $order, string $state): bool
    {
        /** @var Order $order */

        try {
            /** @var Creditmemo $creditmemo */
            $creditmemo = $order
                ->getCreditmemosCollection()
                ->addFieldToFilter('transaction_id', $state)
                ->getLastItem();
        } catch (Exception $e) {
            $order->addCommentToStatusHistory(__('The refund has failed.')->render());
            $this->orderRepository->save($order);

            $this->fintectureLogger->error('Apply refund error', [
                'message' => "Can't find credit memo associated to order",
                'creditmemoId' => $state,
                'incrementOrderId' => $order->getIncrementId(),
                'exception' => $e
            ]);

            return false;
        }

        try {
            $creditmemo->setState(Creditmemo::STATE_REFUNDED);

            /** @var Order $order */
            $order = $this->refundAdapter->refund($creditmemo, $creditmemo->getOrder(), true);

            if ($order->canUnhold()) {
                $order->unhold();
            }
            $order->addCommentToStatusHistory(__('The refund has been made.')->render());

            $this->orderRepository->save($order);
            $this->creditmemoRepository->save($creditmemo);

            $this->fintectureLogger->info('Refund completed', [
                'creditmemoId' => $state,
                'incrementOrderId' => $order->getIncrementId()
            ]);

            return true;
        } catch (Exception $e) {
            $order->addCommentToStatusHistory(__('The refund has failed.')->render());
            $this->orderRepository->save($order);

            $this->fintectureLogger->error('Apply refund error', [
                'message' => "Can't apply refund",
                'creditmemoId' => $state,
                'incrementOrderId' => $order->getIncrementId(),
                'exception' => $e,
            ]);
        }

        return false;
    }

    public function getShopName(): ?string
    {
        return $this->_scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE);
    }

    public function getAppId(string $environment = null): ?string
    {
        $environment = $environment ?: $this->getAppEnvironment();
        return $this->_scopeConfig->getValue(static::CONFIG_PREFIX . 'fintecture_app_id_' . $environment, ScopeInterface::SCOPE_STORE);
    }

    public function getAppSecret(string $environment = null, string $scope = ScopeInterface::SCOPE_STORE, int $scopeId = null): ?string
    {
        $environment = $environment ?: $this->getAppEnvironment();
        return $this->_scopeConfig->getValue(static::CONFIG_PREFIX . 'fintecture_app_secret_' . $environment, $scope, $scopeId);
    }

    public function getAppPrivateKey(string $environment = null, string $scope = ScopeInterface::SCOPE_STORE, int $scopeId = null): ?string
    {
        $environment = $environment ?: $this->getAppEnvironment();
        $privateKey = $this->_scopeConfig->getValue(self::CONFIG_PREFIX . 'custom_file_upload_' . $environment, $scope, $scopeId);
        return $privateKey ? $this->encryptor->decrypt($privateKey) : null;
    }

    public function getAppEnvironment(): ?string
    {
        return $this->_scopeConfig->getValue(static::CONFIG_PREFIX . 'environment', ScopeInterface::SCOPE_STORE);
    }

    public function getBankType(): ?string
    {
        return $this->_scopeConfig->getValue('payment/fintecture/general/bank_type', ScopeInterface::SCOPE_STORE);
    }

    public function getActive(): int
    {
        return (int) $this->_scopeConfig->isSetFlag('payment/fintecture/active', ScopeInterface::SCOPE_STORE);
    }

    public function getShowLogo(): int
    {
        return (int) $this->_scopeConfig->isSetFlag('payment/fintecture/general/show_logo', ScopeInterface::SCOPE_STORE);
    }

    public function getExpirationActive(): bool
    {
        return $this->_scopeConfig->isSetFlag('payment/fintecture/expiration_active', ScopeInterface::SCOPE_STORE);
    }

    public function getExpirationAfter(): ?int
    {
        return (int) $this->_scopeConfig->getValue('payment/fintecture/expiration_after', ScopeInterface::SCOPE_STORE);
    }

    public function getInvoicingActive(): bool
    {
        return $this->_scopeConfig->isSetFlag('payment/fintecture/invoicing_active', ScopeInterface::SCOPE_STORE);
    }

    public function getQrCodeActive(): bool
    {
        return $this->_scopeConfig->isSetFlag('payment/fintecture/qrcode_active', ScopeInterface::SCOPE_STORE);
    }

    public function generatePayload(Order $lastRealOrder, string $type): array
    {
        /** @var \Magento\Sales\Model\Order\Address|null $billingAddress */
        $billingAddress = $lastRealOrder->getBillingAddress();
        if (!$billingAddress) {
            $this->fintectureLogger->error('Error', [
                'message' => 'No billing address found in order, please try again',
                'incrementOrderId' => $lastRealOrder->getIncrementId(),
            ]);
            throw new LocalizedException(__('No billing address found in order, please try again'));
        }

        return [
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
                'type' => $type,
                'attributes' => [
                    'amount' => number_format($lastRealOrder->getBaseTotalDue(), 2, '.', ''),
                    'currency' => $lastRealOrder->getOrderCurrencyCode(),
                    'communication' => self::PAYMENT_COMMUNICATION . $lastRealOrder->getIncrementId()
                ],
            ],
        ];
    }

    public function getPaymentRedirectUrl(): string
    {
        $this->validateConfigValue();

        /** @var Order|null $lastRealOrder */
        $lastRealOrder = $this->checkoutSession->getLastRealOrder();
        if (!$lastRealOrder) {
            $this->fintectureLogger->error('Error', ['message' => 'No order found in session, please try again']);
            throw new LocalizedException(__('No order found in session, please try again'));
        }

        $qrCodeActive = false;
        if (interface_exists(GetLoggedAsCustomerAdminIdInterface::class)) {
            $getLoggedAsCustomerAdminId = ObjectManager::getInstance()->get(GetLoggedAsCustomerAdminIdInterface::class);
            $qrCodeActive = (bool) $getLoggedAsCustomerAdminId->execute() && $this->getQrCodeActive();
        }

        $type = $qrCodeActive ? 'REQUEST_TO_PAY' : 'PIS';
        $data = $this->generatePayload($lastRealOrder, $type);

        // Handle order expiration if enabled
        if ($this->getExpirationActive()) {
            $minutes = $this->getExpirationAfter();
            if (is_int($minutes) && $minutes >= 1) {
                $data['meta']['expiry'] = $minutes * 60;
            }
        }

        try {
            $state = Crypto::uuid4();
            $redirectUrl = $this->getResponseUrl();
            $originUrl = $this->getOriginUrl();
            $psuType = $this->getBankType();

            /** @phpstan-ignore-next-line */
            $pisToken = $this->pisClient->token->generate();
            if (!$pisToken->error) {
                $this->pisClient->setAccessToken($pisToken); // set token of PIS client
            } else {
                throw new Exception($pisToken->errorMsg);
            }

            if ($qrCodeActive) {
                // QR Code -> RTP
                /** @phpstan-ignore-next-line */
                $apiResponse = $this->pisClient->requestToPay->generate($data, 'fr');
            } else {
                // Classic connect
                /** @phpstan-ignore-next-line */
                $apiResponse = $this->pisClient->connect->generate(
                    $data,
                    $state,
                    $redirectUrl,
                    $originUrl,
                    null,
                    [
                        'x-psu-type' => $psuType
                    ]
                );
            }

            if (!$apiResponse->error) {
                $sessionId = $apiResponse->meta->session_id ?? '';

                $lastRealOrder->setFintecturePaymentSessionId($sessionId);
                try {
                    $this->orderRepository->save($lastRealOrder);
                } catch (Exception $e) {
                    $this->fintectureLogger->error('Error', [
                        'exception' => $e,
                        'incrementOrderId' => $lastRealOrder->getIncrementId(),
                    ]);
                }

                /** @phpstan-ignore-next-line : dynamic session var get */
                $this->coreSession->setPaymentSessionId($sessionId);

                $connectUrl = $apiResponse->meta->url ?? '';

                if ($qrCodeActive) {
                    // QR Code page
                    $params = [
                        'url' => urlencode($connectUrl),
                        'reference' => $data['data']['attributes']['communication'],
                        'amount' => $data['data']['attributes']['amount'],
                        'currency' => $data['data']['attributes']['currency'],
                        'session_id' => $sessionId,
                        'confirm' => 0
                    ];
                    return $this->getQrCodeUrl() . '?' . http_build_query($params);
                } else {
                    // Classic connect redirection
                    return $connectUrl;
                }
            } else {
                $this->fintectureLogger->error('Error', [
                    'message' => 'Error building connect URL',
                    'incrementOrderId' => $lastRealOrder->getIncrementId(),
                    'response' => $apiResponse->errorMsg
                ]);
                $this->checkoutSession->restoreQuote();
                throw new Exception($apiResponse->errorMsg);
            }
        } catch (Exception $e) {
            $this->fintectureLogger->error('Error', [
                'exception' => $e,
                'incrementOrderId' => $lastRealOrder->getIncrementId(),
            ]);

            $this->checkoutSession->restoreQuote();
            throw new Exception($e->getMessage());
        }
    }

    public function validateConfigValue(): void
    {
        if (!$this->getAppEnvironment()
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

    public function getQrCodeUrl(): string
    {
        return $this->fintectureHelper->getUrl('fintecture/standard/qrcode');
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
            'module_production' => $this->getAppEnvironment() === Environment::ENVIRONMENT_PRODUCTION ? 1 : 0,
            'module_sandbox_app_id' => $this->getAppId(Environment::ENVIRONMENT_SANDBOX),
            'module_production_app_id' => $this->getAppId(Environment::ENVIRONMENT_PRODUCTION),
            'module_branding' => $this->getShowLogo()
        ];
    }

    public function getMagentoVersion(): string
    {
        $version = $this->productMetadata->getVersion();
        if ($version === 'UNKNOWN') {
            $this->fintectureLogger->debug("Can't detect Magento version.");
            return 'UNKNOWN';
        }
        return $version;
    }
}
