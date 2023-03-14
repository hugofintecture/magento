<?php

declare(strict_types=1);

namespace Fintecture\Payment\Model\Action\Refund;

use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Class CreateRefund
 */
class CreateRefund extends AbstractRefundAction
{
    protected function performRefundAction(OrderInterface $order, CreditmemoInterface $creditmemo)
    {
        // Create a unique reference to identify the credit memo in webhooks
        $creditmemo->setTransactionId('fintecture-' . $order->getEntityId() . '-' . uniqid());

        $this->paymentMethod->createRefund($order, $creditmemo);
    }
}
