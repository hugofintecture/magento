<?php

declare(strict_types=1);

namespace Fintecture\Payment\Observer\Quote;

use Fintecture\Payment\Model\Fintecture;
use Magento\Sales\Model\Order;

class SubmitObserver
{
    public function beforeExecute($subject, $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();

        if ($order) {
            if ($order->getPayment()->getMethod() === Fintecture::CODE) {
                // Disable email sending
                $order->setCanSendNewEmailFlag(false);
            }
        }

        return [$observer];
    }
}
