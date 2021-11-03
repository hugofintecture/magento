<?php

declare(strict_types=1);

namespace Fintecture\Payment\Helper;

use Fintecture\Payment\Logger\Logger as FintectureLogger;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Model\Order;

class Fintecture extends AbstractHelper
{
    /** @var Session $session */
    protected $session;

    /** @var Quote $quote */
    protected $quote;

    /** @var QuoteManagement $quoteManagement */
    protected $quoteManagement;

    /** @var FintectureLogger $fintectureLogger */
    protected $fintectureLogger;

    public function __construct(
        Context $context,
        Session $session,
        Quote $quote,
        QuoteManagement $quoteManagement,
        FintectureLogger $fintectureLogger
    ) {
        $this->session = $session;
        $this->quote = $quote;
        $this->quoteManagement = $quoteManagement;
        $this->fintectureLogger = $fintectureLogger;
        parent::__construct($context);
    }

    public function restoreQuote()
    {
        $this->session->restoreQuote();
    }

    public function getUrl($route, $params = []): string
    {
        return $this->_getUrl($route, $params);
    }

    public function getOrderStatusBasedOnPaymentStatus($apiResponse): array
    {
        $status = $apiResponse['meta']['status'] ?? '';
        $return = [];

        switch ($status) {
            case 'payment_created':
                $return['state'] = Order::STATE_PROCESSING;
                $return['status'] = 'processing';
                break;
            case 'payment_pending':
                $return['state'] = Order::STATE_PENDING_PAYMENT;
                $return['status'] = 'pending_payment';
                break;
            default:
                $return['state'] = Order::STATE_NEW;
                $return['status'] = 'pending';
                break;
        }

        return $return;
    }

    public function getStatusHistoryComment($apiResponse)
    {
        $status = $apiResponse['meta']['status'] ?? '';

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
            default:
                return __('Payment pending');
        }
    }
}
