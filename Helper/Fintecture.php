<?php

declare(strict_types=1);

namespace Fintecture\Payment\Helper;

use Magento\Checkout\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Status\History;
use Magento\Sales\Model\ResourceModel\Order\Status\History\CollectionFactory;
use Magento\Store\Model\ScopeInterface;

class Fintecture extends AbstractHelper
{
    /** @var Session */
    protected $session;

    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    /** @var CollectionFactory */
    protected $historyCollectionFactory;

    /** @var SearchCriteriaBuilder */
    protected $searchCriteriaBuilder;

    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    /** @var TransactionRepositoryInterface */
    protected $transactionRepository;

    public function __construct(
        Context $context,
        Session $session,
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $historyCollectionFactory,
        OrderRepositoryInterface $orderRepository,
        TransactionRepositoryInterface $transactionRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->session = $session;
        $this->scopeConfig = $scopeConfig;
        $this->historyCollectionFactory = $historyCollectionFactory;
        $this->orderRepository = $orderRepository;
        $this->transactionRepository = $transactionRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;

        parent::__construct($context);
    }

    public function restoreQuote(): void
    {
        $this->session->restoreQuote();
    }

    public function getUrl(string $route, array $params = []): string
    {
        return rtrim($this->_getUrl($route, $params), '/');
    }

    public function getOrderByIncrementId(string $incrementId): ?Order
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', $incrementId)->create();
        $orderList = $this->orderRepository->getList($searchCriteria)->getItems();
        /** @var Order|null $order */
        $order = array_pop($orderList);

        return $order;
    }

    public function getSessionIdByOrderId(string $orderId): ?string
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('order_id', $orderId)->create();
        $transactionList = $this->transactionRepository->getList($searchCriteria)->getItems();
        /** @var Transaction|null $transaction */
        $transaction = array_pop($transactionList);
        if ($transaction) {
            $extraInfos = $transaction->getAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS);
            if ($extraInfos && isset($extraInfos['sessionId'])) {
                return $extraInfos['sessionId'];
            }
        }

        return null;
    }

    /**
     * Get Magento statuses associated with our params
     *
     * @return array|null
     */
    public function getOrderStatus(array $params)
    {
        // Mapping by payment_status
        $statusMapping = [
            'payment_created' => [
                'status' => $this->getPaymentCreatedStatus(),
                'state' => Order::STATE_PROCESSING,
            ],
            'payment_pending' => [
                'status' => $this->getPaymentPendingStatus(),
                'state' => Order::STATE_PENDING_PAYMENT,
            ],
            'payment_partial' => [
                'status' => $this->getPaymentPartialStatus(),
                'state' => Order::STATE_NEW,
            ],
            'payment_unsuccessful' => [
                'status' => $this->getPaymentFailedStatus(),
                'state' => Order::STATE_CANCELED,
            ],
            'payment_error' => [
                'status' => $this->getPaymentFailedStatus(),
                'state' => Order::STATE_CANCELED,
            ],
            'payment_expired' => [
                'status' => $this->getPaymentFailedStatus(),
                'state' => Order::STATE_CANCELED,
            ],
            'sca_required' => [
                'status' => $this->getPaymentFailedStatus(),
                'state' => Order::STATE_CANCELED,
            ],
            'provider_required' => [
                'status' => $this->getPaymentFailedStatus(),
                'state' => Order::STATE_CANCELED,
            ],
        ];

        // Mapping by transfer_state
        if ($params['transferState'] === 'overpaid') {
            $statusMapping['payment_created'] = [
                'status' => $this->getPaymentOverpaidStatus(),
                'state' => Order::STATE_PROCESSING,
            ];
        }

        if (isset($statusMapping[$params['status']])) {
            return $statusMapping[$params['status']];
        }

        return null;
    }

    /**
     * Get an history comment associated with our params
     */
    public function getHistoryComment(array $params, bool $webhook = false): string
    {
        // Mapping by payment_status
        $notesMapping = [
            'payment_created' => __('The payment has been validated by the bank.'),
            'payment_pending' => __('The bank is validating the payment.'),
            'payment_partial' => __('A partial payment has been made.'),
            'payment_unsuccessful' => __('The payment was rejected by either the payer or the bank.'),
            'payment_error' => __('The payment has failed for technical reasons.'),
            'sca_required' => __('The payer got redirected to their bank and needs to authenticate.'),
            'provider_required' => __('The payment has been dropped by the payer.'),
            'payment_expired' => __('The payment link has expired.'),
        ];

        // Mapping by transfer_state
        if ($params['transferState'] === 'overpaid') {
            $notesMapping['payment_created'] = __('The payment has been completed with a higher amount.');
        }

        if (isset($notesMapping[$params['status']])) {
            $note = $notesMapping[$params['status']];
        } else {
            $note = __('Unhandled status.');
        }

        $note = $webhook ? 'Webhook: ' . $note->render() : $note->render();

        return $note;
    }

    public function isStatusInHistory(Order $order, string $status): bool
    {
        $historyCollection = $this->historyCollectionFactory->create();
        $historyCollection->addFieldToFilter('parent_id', $order->getEntityId());
        if ($historyCollection->count() > 0) {
            /** @var History $history */
            foreach ($historyCollection->getItems() as $history) {
                if ($status === $history->getStatus()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isStatusAlreadyFinal(Order $order): bool
    {
        return $this->isStatusInHistory($order, $this->getPaymentCreatedStatus()) ||
            $this->isStatusInHistory($order, $this->getPaymentOverpaidStatus());
    }

    public function getNewOrderStatus(): string
    {
        $status = $this->scopeConfig->getValue('payment/fintecture/new_order_status', ScopeInterface::SCOPE_STORE);
        if (!$status) {
            $status = Order::STATE_NEW;
        }

        return $status;
    }

    public function getPaymentCreatedStatus(): string
    {
        $status = $this->scopeConfig->getValue('payment/fintecture/payment_created_status', ScopeInterface::SCOPE_STORE);
        if (!$status) {
            $status = Order::STATE_PROCESSING;
        }

        return $status;
    }

    public function getPaymentPendingStatus(): string
    {
        $status = $this->scopeConfig->getValue('payment/fintecture/payment_pending_status', ScopeInterface::SCOPE_STORE);
        if (!$status) {
            $status = Order::STATE_PENDING_PAYMENT;
        }

        return $status;
    }

    public function getPaymentOverpaidStatus(): string
    {
        $status = $this->scopeConfig->getValue('payment/fintecture/payment_overpaid_status', ScopeInterface::SCOPE_STORE);
        if (!$status) {
            $status = Order::STATE_CANCELED;
        }

        return $status;
    }

    public function getPaymentPartialStatus(): string
    {
        $status = $this->scopeConfig->getValue('payment/fintecture/payment_partial_status', ScopeInterface::SCOPE_STORE);
        if (!$status) {
            $status = Order::STATE_CANCELED;
        }

        return $status;
    }

    public function getPaymentFailedStatus(): string
    {
        $status = $this->scopeConfig->getValue('payment/fintecture/payment_failed_status', ScopeInterface::SCOPE_STORE);
        if (!$status) {
            $status = Order::STATE_CANCELED;
        }

        return $status;
    }
}
