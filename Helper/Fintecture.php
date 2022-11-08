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

    public function restoreQuote(): void
    {
        $this->session->restoreQuote();
    }

    public function getUrl(string $route, array $params = []): string
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

    public function getStatusHistoryComment(string $status): string
    {
        $note = '';
        switch ($status) {
            case 'payment_created':
                $note = __('The payment has been validated by the bank.');
                break;
            case 'payment_pending':
                $note = __('The bank is validating the payment.');
                break;
            case 'payment_unsuccessful':
                $note = __('The payment was rejected by either the payer or the bank.');
                break;
            case 'payment_error':
                $note = __('The payment has failed for technical reasons.');
                break;
            case 'sca_required':
                $note = __('The payer got redirected to their bank and needs to authenticate.');
                break;
            case 'provider_required':
                $note = __('The payment has been dropped by the payer.');
                break;
            case 'payment_expired':
                $note = __('The payment link has expired.');
                break;
            default:
                $note = __('Unknown status.');
        }

        return $note->render();
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
