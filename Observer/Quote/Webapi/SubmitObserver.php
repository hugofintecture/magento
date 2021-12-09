<?php

declare(strict_types=1);

namespace Fintecture\Payment\Observer\Quote\Webapi;

use Exception;
use Fintecture\Payment\Model\Fintecture;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Observer\Webapi\SubmitObserver as MagentoSubmitObserver;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Psr\Log\LoggerInterface;

class SubmitObserver extends MagentoSubmitObserver
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var OrderSender */
    protected $orderSender;

    public function __construct(
        LoggerInterface $logger,
        OrderSender $orderSender
    ) {
        $this->logger = $logger;
        $this->orderSender = $orderSender;
        parent::__construct($logger, $orderSender);
    }

    public function execute(Observer $observer)
    {
        /** @var  Quote $quote */
        $quote = $observer->getEvent()->getQuote();

        /** @var  Order $order */
        $order = $observer->getEvent()->getOrder();

        $paymentMethod = $order->getPayment()->getData('method');
        $redirectUrl = $quote->getPayment()->getOrderPlaceRedirectUrl();
        if (
            $paymentMethod !== Fintecture::PAYMENT_FINTECTURE_CODE
            && !$redirectUrl
            && $order->getCanSendNewEmailFlag()
        ) {
            try {
                $this->orderSender->send($order);
            } catch (Exception $e) {
                $this->logger->critical($e);
            }
        }
    }
}
