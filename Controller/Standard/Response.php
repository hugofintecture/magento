<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller\Standard;

use Exception;
use Fintecture\Payment\Controller\FintectureAbstract;
use Magento\Framework\Exception\LocalizedException;

class Response extends FintectureAbstract
{
    public function execute()
    {
        try {
            /** @phpstan-ignore-next-line : dynamic session var set */
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

            /** @phpstan-ignore-next-line */
            $pisToken = $this->paymentMethod->pisClient->token->generate();
            if (!$pisToken->error) {
                $this->paymentMethod->pisClient->setAccessToken($pisToken); // set token of PIS client
            } else {
                throw new Exception($pisToken->errorMsg);
            }

            /** @phpstan-ignore-next-line */
            $apiResponse = $this->paymentMethod->pisClient->payment->get($lastPaymentSessionId);
            if (!$apiResponse->error) {
                $status = $apiResponse->meta->status;
                $sessionId = $apiResponse->meta->session_id;

                $statuses = $this->fintectureHelper->getOrderStatusBasedOnPaymentStatus($status);

                $this->fintectureLogger->debug('Response', [
                    'orderIncrementId' => $order->getIncrementId(),
                    'fintectureStatus' => $status,
                    'status' => $statuses['status']
                ]);

                if ($statuses['status'] === $this->fintectureHelper->getPaymentCreatedStatus()) {
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
                } elseif ($statuses['status'] === $this->fintectureHelper->getPaymentPendingStatus()) {
                    $this->paymentMethod->handleHoldedTransaction($order, $status, $sessionId, $statuses);
                    $this->messageManager->addSuccessMessage(__('Payment was initiated but has not been confirmed yet. Merchant will send confirmation once the transaction is settled.')->render());
                    return $this->resultRedirect->create()->setPath(
                        $this->fintectureHelper->getUrl('checkout/onepage/success')
                    );
                } elseif ($statuses['status'] === $this->fintectureHelper->getPaymentFailedStatus()) {
                    $this->paymentMethod->handleFailedTransaction($order, $status, $sessionId, $statuses);
                    $this->messageManager->addErrorMessage(__('The payment was unsuccessful. Please choose a different bank or different payment method.')->render());
                    return $this->redirectToCart();
                } else {
                    $this->paymentMethod->handleFailedTransaction($order, $status, $sessionId, $statuses);
                    $this->messageManager->addErrorMessage(__('The payment was unsuccessful. Please choose a different bank or different payment method.')->render());
                    return $this->redirectToCart();
                }
            } else {
                $this->fintectureLogger->error('Error', [
                    'message' => 'Invalid payment API response',
                    'response' => $apiResponse->errorMsg,
                ]);
                $this->messageManager->addErrorMessage(__("We can't place the order.")->render());
            }
        } catch (LocalizedException $e) {
            $this->fintectureLogger->error('Response error', ['exception' => $e]);
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        } catch (Exception $e) {
            $this->fintectureLogger->error('Response error', ['exception' => $e]);
            $this->messageManager->addExceptionMessage($e, __("We can't place the order.")->render());
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
