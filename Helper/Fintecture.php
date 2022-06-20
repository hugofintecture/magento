<?php

declare(strict_types=1);

namespace Fintecture\Payment\Helper;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;

class Fintecture extends AbstractHelper
{
    /** @var Session $session */
    protected $session;

    public function __construct(
        Context $context,
        Session $session
    ) {
        $this->session = $session;
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
                    'state' => Order::STATE_PROCESSING,
                    'status' => 'processing'
                ];
            case 'payment_pending':
                return [
                    'state' => Order::STATE_PENDING_PAYMENT,
                    'status' => 'pending_payment'
                ];
            default:
                return [
                    'state' => Order::STATE_CANCELED,
                    'status' => 'canceled'
                ];
        }
    }

    public function getStatusHistoryComment(string $status)
    {
        switch ($status) {
            case 'payment_created':
                return __('The payment initiation has been created successfully');
            case 'payment_unsuccessful':
                return __('The buyer has either abandoned the payment flow on the banks web page, or the payment has not been accepted due to an authentication failure or insufficient funds in his bank account');
            case 'sca_required':
                return __('The buyer has selected a Bank in Connect and has been redirected to the Authentication page of the bank');
            case 'provider_required':
                return __('The buyer has select *Pay By Bank* as payment method, got redirected to Connect but has not selected any banks.');
            case 'payment_error':
                return __('Technical Error, the bank has rejected the payment initiation or has timeout');
            case 'payment_pending':
                return __('Payment pending');
            default:
                return __('Unknown status');
        }
    }
}
