<?php

declare(strict_types=1);

namespace Fintecture\Payment\Observer\Quote;

use Exception;
use Fintecture\Payment\Model\Fintecture;
use Magento\Sales\Model\Order;

class SubmitObserver
{
    public function beforeExecute($subject, $observer)
    {
        try {
            /** @var Order $order */
            $order = $observer->getEvent()->getOrder();
        } catch (Exception $e) {
            return;
        }

        if ($order->getPayment()->getMethod() === Fintecture::CODE) {
            // Disable email sending
            $order->setCanSendNewEmailFlag(false);
        }

        return [$observer];
    }
}
