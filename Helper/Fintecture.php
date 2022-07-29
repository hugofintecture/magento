<?php

declare(strict_types=1);

namespace Fintecture\Payment\Helper;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;

class Fintecture extends AbstractHelper
{
    /** @var Session $session */
    protected $session;

    /** @var ScopeConfigInterface $scopeConfig */
    protected $scopeConfig;

    public function __construct(
        Context $context,
        Session $session,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->session = $session;
        $this->scopeConfig = $scopeConfig;

        parent::__construct($context);
    }

    public function restoreQuote()
    {
        $this->session->restoreQuote();
    }

    /**
     * @return array|false
     */
    public function decodeJson($json)
    {
        if ($json && is_string($json)) {
            $decodedJson = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decodedJson;
            }
            return false;
        }
        return false;
    }

    public function getUrl($route, $params = []): string
    {
        return rtrim($this->_getUrl($route, $params), '/');
    }

    public function getOrderStatusBasedOnPaymentStatus(string $status): array
    {
        switch ($status) {
            case 'payment_created':
                return [
                    'status' => $this->getPaymentCreatedStatus(),
                    'state' => Order::STATE_PROCESSING,
                ];
            case 'payment_pending':
                return [
                    'status' => $this->getPaymentPendingStatus(),
                    'state' => Order::STATE_PENDING_PAYMENT,
                ];
            case 'payment_unsuccessful':
            case 'payment_error':
                return [
                    'status' => $this->getPaymentFailedStatus(),
                    'state' => Order::STATE_CANCELED,
                ];
            default:
                return [
                    'status' => Order::STATE_CANCELED,
                    'state' => Order::STATE_CANCELED,
                ];
        }
    }

    public function getStatusHistoryComment(string $status)
    {
        switch ($status) {
            case 'payment_created':
                return __('The payment has been validated by the bank.');
            case 'payment_pending':
                return __('The bank is validating the payment.');
            case 'payment_unsuccessful':
                return __('The payment was rejected by either the payer or the bank.');
            case 'payment_error':
                return __('The payment has failed for technical reasons.');
            case 'sca_required':
                return __('The payer got redirected to their bank and needs to authenticate.');
            case 'provider_required':
                return __('The payment has been dropped by the payer.');
            case 'payment_expired':
                return __('The payment link has expired.');
            default:
                return __('Unknown status.');
        }
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

    public function getPaymentFailedStatus(): string
    {
        $status = $this->scopeConfig->getValue('payment/fintecture/payment_failed_status', ScopeInterface::SCOPE_STORE);
        if (!$status) {
            $status = Order::STATE_CANCELED;
        }
        return $status;
    }
}
