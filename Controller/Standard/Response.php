<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller\Standard;

use Exception;
use Fintecture\Payment\Controller\FintectureAbstract;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

class Response extends FintectureAbstract
{
    public function execute()
    {
        try {
            $lastPaymentSessionId = $this->coreSession->getPaymentSessionId();
            if (!$lastPaymentSessionId) {
                $this->fintectureLogger->error("Can't find last payment session id");
                return $this->redirectToCart();
            }

            $order = $this->getOrder();
            $orderSessionId = $order->getFintecturePaymentSessionId();

            if ($lastPaymentSessionId !== $orderSessionId) {
                $this->fintectureLogger->error('Error', [
                    'message' => 'Session id not matching',
                    'lastPaymentSessionId' => $lastPaymentSessionId,
                    'orderSessionId' => $orderSessionId
                ]);
                return $this->redirectToCart();
            }

            $gatewayClient = $this->paymentMethod->getGatewayClient();
            $apiResponse = $gatewayClient->getPayment($lastPaymentSessionId);

            if (isset($apiResponse['meta']['status']) && isset($apiResponse['meta']['session_id'])) {
                $status = $apiResponse['meta']['status'];
                $sessionId = $apiResponse['meta']['session_id'];

                $statuses = $this->fintectureHelper->getOrderStatusBasedOnPaymentStatus($status);

                $this->fintectureLogger->debug('Response', [
                    'orderIncrementId' => $order->getIncrementId(),
                    'fintectureStatus' => $status,
                    'status' => $statuses['status']
                ]);

                if ($statuses['status'] === Order::STATE_PROCESSING) {
                    $this->paymentMethod->handleSuccessTransaction($order, $status, $sessionId, $statuses);

                    try {
                        $this->checkoutSession->setLastQuoteId($order->getQuoteId());
                        $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                        $this->checkoutSession->setLastOrderId($order->getId());
                        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
                        $this->checkoutSession->setLastOrderStatus($order->getStatus());

                        return $this->resultRedirect->create()->setPath(
                            $this->fintectureHelper->getUrl('checkout/onepage/success')
                        );
                    } catch (Exception $e) {
                        $this->fintectureLogger->error('Error', [
                            'exception' => $e,
                            'incrementOrderId' => $order->getIncrementId(),
                            'status' => $order->getStatus()
                        ]);
                    }
                } elseif ($statuses['status'] === Order::STATE_PENDING_PAYMENT) {
                    $this->paymentMethod->handleHoldedTransaction($order, $status, $sessionId, $statuses);
                    $this->messageManager->addSuccessMessage(__('Payment was initiated but has not been confirmed yet. Merchant will send confirmation once the transaction is settled.'));
                    return $this->resultRedirect->create()->setPath(
                        $this->fintectureHelper->getUrl('checkout/onepage/success')
                    );
                } else {
                    $this->paymentMethod->handleFailedTransaction($order, $status, $sessionId);
                    $this->messageManager->addErrorMessage(__('The payment was unsuccessful. Please choose a different bank or different payment method.'));
                    return $this->redirectToCart();
                }
            } else {
                $this->fintectureLogger->error('Error', [
                    'message' => 'Invalid payment API response',
                    'response' => json_encode($apiResponse),
                ]);
                $this->messageManager->addErrorMessage(__("We can't place the order."));
            }
        } catch (LocalizedException $e) {
            $this->fintectureLogger->error('Response error', ['exception' => $e]);
            $this->messageManager->addExceptionMessage($e, __($e->getMessage()));
        } catch (Exception $e) {
            $this->fintectureLogger->error('Response error', ['exception' => $e]);
            $this->messageManager->addExceptionMessage($e, __("We can't place the order."));
        }

        return $this->redirectToCart();
    }

    /**
     * In case of error, restore cart and redirect user to it
     */
    private function redirectToCart(string $returnUrl = null)
    {
        if (!$returnUrl) {
            $returnUrl = $this->fintectureHelper->getUrl('checkout') . "#payment";
        }

        $this->checkoutSession->restoreQuote();
        return $this->resultRedirect->create()->setPath($returnUrl);
    }
}
