<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller\Standard;

use Exception;
use Fintecture\Payment\Controller\WebhookAbstract;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

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
                    $sessionId = $data['session_id'];
                    $status = $data['status'];

                    $statuses = $this->fintectureHelper->getOrderStatusBasedOnPaymentStatus($status);

                    $orderCollection = $this->orderCollectionFactory->create();
                    $orderCollection->addFieldToFilter('fintecture_payment_session_id', $sessionId);

                    $order = $orderCollection->getFirstItem();
                    if ($order && $order->getId()) {
                        $this->fintectureLogger->debug('Webhook', [
                            'orderIncrementId' => $order->getIncrementId(),
                            'fintectureStatus' => $status,
                            'status' => $statuses['status']
                        ]);

                        if ($statuses['status'] === Order::STATE_PROCESSING) {
                            $this->paymentMethod->handleSuccessTransaction($order, $status, $sessionId, $statuses, true);
                        } elseif ($statuses['status'] === Order::STATE_PENDING_PAYMENT) {
                            $this->paymentMethod->handleHoldedTransaction($order, $status, $sessionId, $statuses, true);
                        } else {
                            $this->paymentMethod->handleFailedTransaction($order, $status, $sessionId, true);
                        }

                        $result->setHttpResponseCode(200);
                    } else {
                        $this->fintectureLogger->error('Webhook error', [
                            'message' => 'No order found',
                            'sessionId' => $sessionId,
                            'status' => $status
                        ]);
                        $result->setHttpResponseCode(401);
                        $result->setContents('Webhook error: no order found');
                    }
                }
            } else {
                $this->fintectureLogger->error('Webhook error', [
                    'message' => $webhookError,
                    'sessionId' => $data['session_id'] ?? '',
                    'status' => $data['status'] ?? ''
                ]);
                $result->setHttpResponseCode(401);
                $result->setContents('Webhook error: ' . $webhookError);
            }
        } catch (LocalizedException $e) {
            $this->fintectureLogger->error('Webhook error', ['exception' => $e]);
            $result->setHttpResponseCode(500);
            $result->setContents('Webhook error: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->fintectureLogger->error('Webhook error', ['exception' => $e]);
            $result->setHttpResponseCode(500);
            $result->setContents('Webhook error: ' . $e->getMessage());
        }

        return $result;
    }
}
