<?php

declare(strict_types=1);

namespace Fintecture\Payment\Observer\Quote;

use Fintecture\Payment\Model\Fintecture;

class SubmitObserver
{
    public function beforeExecute($subject, $observer)
    {
        $order = $observer->getEvent()->getOrder();

        $paymentMethod = $order->getPayment()->getData('method');
        if ($paymentMethod === Fintecture::PAYMENT_FINTECTURE_CODE) {
            // Disable email sending
            $order->setCanSendNewEmailFlag(false);
        }

        return [$observer];
    }
}
