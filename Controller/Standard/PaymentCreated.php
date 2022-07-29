<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller\Standard;

use Exception;
use Fintecture\Payment\Controller\WebhookAbstract;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;

class PaymentCreated extends WebhookAbstract
{
    public function execute()
    {
        $result = $this->resultRawFactory->create();
        $result->setHeader('Content-Type', 'text/plain');

        try {
            $body = file_get_contents('php://input');
            list($validWebhook, $webhookError) = $this->validateWebhook($body);
            if ($validWebhook) {
                parse_str($body, $data);

                if (isset($data['session_id']) && isset($data['status'])
                    && !empty($data['session_id']) && !empty($data['status'])) {
                    $isRefund = isset($data['refunded_session_id']);
                    if ($isRefund) {
                        $sessionId = $data['refunded_session_id']; // Original session id to find order
                    } else {
                        $sessionId = $data['session_id'];
                    }
                    $status = $data['status'];
                    $state = $data['state'];

                    $orderCollection = $this->orderCollectionFactory->create();
                    $orderCollection->addFieldToFilter('fintecture_payment_session_id', $sessionId);
                    /** @var OrderInterface $order */
                    $order = $orderCollection->getFirstItem();
                    if ($order && $order->getEntityId()) {
                        if ($isRefund) {
                            return $this->refund($order, $status, $state);
                        } else {
                            return $this->payment($order, $status, $sessionId);
                        }
                    } else {
                        $this->fintectureLogger->error('Webhook error', [
                            'message' => 'No order found',
                            'sessionId' => $sessionId,
                            'status' => $status
                        ]);
                        $result->setHttpResponseCode(400);
                        $result->setContents('Error: no order found');
                    }
                }
            } else {
                $this->fintectureLogger->error('Webhook error', [
                    'message' => $webhookError,
                ]);
                $result->setHttpResponseCode(401);
                $result->setContents('Error: ' . $webhookError);
            }
        } catch (LocalizedException $e) {
            $this->fintectureLogger->error('Webhook error', ['exception' => $e]);
            $result->setHttpResponseCode(500);
            $result->setContents('Error: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->fintectureLogger->error('Webhook error', ['exception' => $e]);
            $result->setHttpResponseCode(500);
            $result->setContents('Error: ' . $e->getMessage());
        }

        return $result;
    }

    private function payment(OrderInterface $order, string $status, string $sessionId): Raw
    {
        $result = $this->resultRawFactory->create();
        $result->setHeader('Content-Type', 'text/plain');

        $statuses = $this->fintectureHelper->getOrderStatusBasedOnPaymentStatus($status);

        $this->fintectureLogger->debug('Webhook', [
            'orderIncrementId' => $order->getIncrementId(),
            'fintectureStatus' => $status,
            'status' => $statuses['status']
        ]);

        if ($statuses['status'] === $this->fintectureHelper->getPaymentCreatedStatus()) {
            $this->paymentMethod->handleSuccessTransaction($order, $status, $sessionId, $statuses, true);
        } elseif ($statuses['status'] === $this->fintectureHelper->getPaymentPendingStatus()) {
            $this->paymentMethod->handleHoldedTransaction($order, $status, $sessionId, $statuses, true);
        } elseif ($statuses['status'] === $this->fintectureHelper->getPaymentFailedStatus()) {
            $this->paymentMethod->handleFailedTransaction($order, $status, $sessionId, $statuses, true);
        } else {
            $this->paymentMethod->handleFailedTransaction($order, $status, $sessionId, $statuses, true);
        }

        $result->setHttpResponseCode(200);
        return $result;
    }

    private function refund(OrderInterface $order, string $status, string $state): Raw
    {
        $result = $this->resultRawFactory->create();
        $result->setHeader('Content-Type', 'text/plain');

        if ($status === 'payment_created') {
            $appliedRefund = $this->paymentMethod->applyRefund($order, $state);
            if ($appliedRefund) {
                $result->setHttpResponseCode(200);
            } else {
                $result->setHttpResponseCode(400);
                $result->setContents('Error: refund not applied');
            }
        } else {
            $result->setHttpResponseCode(400);
            $result->setContents('Error: refund status is not payment_created: ' . $status);
        }

        return $result;
    }
}
