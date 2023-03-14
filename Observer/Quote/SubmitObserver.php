<?php

declare(strict_types=1);

namespace Fintecture\Payment\Observer\Quote;

use Fintecture\Payment\Model\Fintecture;
use Magento\Sales\Model\Order;

class SubmitObserver
{
    /**
     * @return array|void
     */
    public function beforeExecute(
        \Magento\Quote\Observer\SubmitObserver $subject,
        \Magento\Framework\Event\Observer $observer
    ) {
        try {
            /** @var Order $order */
            $order = $observer->getEvent()->getOrder();
        } catch (\Exception $e) {
            return;
        }

        $payment = $order->getPayment();
        if ($payment && $payment->getMethod() === Fintecture::CODE) {
            // Disable email sending
            $order->setCanSendNewEmailFlag(false);
        }

        return [$observer];
    }
}
