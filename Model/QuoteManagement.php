<?php

declare(strict_types=1);

namespace Fintecture\Payment\Model;

use Exception;
use Fintecture\Payment\Logger\Logger;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\CustomerManagement;
use Magento\Quote\Model\Quote\Address\ToOrder as ToOrderConverter;
use Magento\Quote\Model\Quote\Address\ToOrderAddress as ToOrderAddressConverter;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Quote\Model\Quote as QuoteEntity;
use Magento\Quote\Model\Quote\Item\ToOrderItem as ToOrderItemConverter;
use Magento\Quote\Model\Quote\Payment\ToOrderPayment as ToOrderPaymentConverter;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\SubmitQuoteValidator;
use Magento\Sales\Api\Data\OrderInterfaceFactory as OrderFactory;
use Magento\Sales\Api\OrderManagementInterface as OrderManagement;
use Magento\Store\Model\StoreManagerInterface;

class QuoteManagement extends \Magento\Quote\Model\QuoteManagement
{
    /** @var Logger */
    public $fintectureLogger;

    /** @var EventManager */
    protected $eventManager;

    /** @var OrderFactory */
    protected $orderFactory;

    /** @var OrderManagement */
    protected $orderManagement;

    /** @var CustomerManagement */
    protected $customerManagement;

    /** @var ToOrderConverter */
    protected $quoteAddressToOrder;

    /** @var ToOrderAddressConverter */
    protected $quoteAddressToOrderAddress;

    /** @var ToOrderItemConverter */
    protected $quoteItemToOrderItem;

    /** @var ToOrderPaymentConverter */
    protected $quotePaymentToOrderPayment;

    /** @var UserContextInterface */
    protected $userContext;

    /** @var CartRepositoryInterface */
    protected $quoteRepository;

    /** @var CustomerRepositoryInterface */
    protected $customerRepository;

    /** @var CustomerFactory */
    protected $customerModelFactory;

    /** @var AddressFactory */
    protected $quoteAddressFactory;

    /** @var DataObjectHelper */
    protected $dataObjectHelper;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /** @var \Magento\Checkout\Model\Session */
    protected $checkoutSession;

    /** @var Session */
    protected $customerSession;

    /** @var AccountManagementInterface */
    protected $accountManagement;

    /** @var QuoteFactory */
    protected $quoteFactory;

    /** @var SubmitQuoteValidator */
    private $submitQuoteValidator;

    /** @var QuoteIdMaskFactory */
    private $quoteIdMaskFactory;

    /** @var AddressRepositoryInterface */
    private $addressRepository;

    /** @var array */
    private $addressesToSync = [];

    /** @var Fintecture\Payment\Model\Order */
    private $fintectureOrder;

    public function __construct(
        EventManager $eventManager,
        SubmitQuoteValidator $submitQuoteValidator,
        OrderFactory $orderFactory,
        OrderManagement $orderManagement,
        CustomerManagement $customerManagement,
        ToOrderConverter $quoteAddressToOrder,
        ToOrderAddressConverter $quoteAddressToOrderAddress,
        ToOrderItemConverter $quoteItemToOrderItem,
        ToOrderPaymentConverter $quotePaymentToOrderPayment,
        UserContextInterface $userContext,
        CartRepositoryInterface $quoteRepository,
        CustomerRepositoryInterface $customerRepository,
        CustomerFactory $customerModelFactory,
        AddressFactory $quoteAddressFactory,
        DataObjectHelper $dataObjectHelper,
        StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\Session $checkoutSession,
        Session $customerSession,
        AccountManagementInterface $accountManagement,
        QuoteFactory $quoteFactory,
        Order $fintectureOrder,
        QuoteIdMaskFactory $quoteIdMaskFactory = null,
        AddressRepositoryInterface $addressRepository = null
    ) {
        parent::__construct(
            $eventManager,
            $submitQuoteValidator,
            $orderFactory,
            $orderManagement,
            $customerManagement,
            $quoteAddressToOrder,
            $quoteAddressToOrderAddress,
            $quoteItemToOrderItem,
            $quotePaymentToOrderPayment,
            $userContext,
            $quoteRepository,
            $customerRepository,
            $customerModelFactory,
            $quoteAddressFactory,
            $dataObjectHelper,
            $storeManager,
            $checkoutSession,
            $customerSession,
            $accountManagement,
            $quoteFactory,
            $quoteIdMaskFactory,
            $addressRepository
        );
        $this->submitQuoteValidator = $submitQuoteValidator;
        $this->fintectureOrder = $fintectureOrder;
        $this->fintectureLogger = new Logger('fintecture');
    }

    protected function submitQuote(QuoteEntity $quote, $orderData = [])
    {
        $paymentMethod = $quote->getPayment()->getMethod();
        $fintectureState = $this->checkoutSession->getFintectureState();
        $this->updateState($paymentMethod);

        $useExistingOrder = $this->useExistingOrder($fintectureState);

        if ($useExistingOrder) {
            $order = $this->fintectureOrder->getOrderForQuote($quote);
            return $order;
        }

        return parent::submitQuote($quote, $orderData);
    }

    public function useExistingOrder(?string $fintectureState): bool
    {
        return $fintectureState !== null;
    }

    public function updateState(string $paymentType): bool
    {
        try {
            if ($paymentType === Fintecture::PAYMENT_FINTECTURE_CODE) {
                $this->checkoutSession->setFintectureState('created');
            } else {
                $this->checkoutSession->setFintectureState(null);
            }
        } catch (Exception $e) {
            $this->fintectureLogger->debug($e->getMessage(), $e->getTrace());
        }

        return true;
    }
}
