<?php
declare(strict_types=1);

namespace Fintecture\Payment\Observer\Quote;

use Exception;
use Fintecture\Payment\Model\Fintecture;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Observer\SubmitObserver as MagentoSubmitObserver;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Psr\Log\LoggerInterface;

class SubmitObserver extends MagentoSubmitObserver
{
    /** @var LoggerInterface */
    private $logger;

    /** @var OrderSender */
    private $orderSender;

    /** @var InvoiceSender */
    private $invoiceSender;

    public function __construct(
        LoggerInterface $logger,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender
    )
    {
        $this->logger        = $logger;
        $this->orderSender   = $orderSender;
        $this->invoiceSender = $invoiceSender;
        parent::__construct($logger, $orderSender, $invoiceSender);
    }

    public function execute(Observer $observer)
    {
        /** @var  Quote $quote */
        $quote = $observer->getEvent()->getQuote();

        /** @var  Order $order */
        $order = $observer->getEvent()->getOrder();

        $paymentMethod = $order->getPayment()->getData('method');
        $redirectUrl   = $quote->getPayment()->getOrderPlaceRedirectUrl();
        if (
            $paymentMethod !== Fintecture::PAYMENT_FINTECTURE_CODE
            && !$redirectUrl
            && $order->getCanSendNewEmailFlag()
        ) {
            try {
                $this->orderSender->send($order);

                /** @var Order\Invoice $invoice */
                $invoice = current($order->getInvoiceCollection()->getItems());
                if ($invoice) {
                    $this->invoiceSender->send($invoice);
                }
            }
            catch (Exception $e) {
                $this->logger->critical($e);
            }
        }
    }
}
