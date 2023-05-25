<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller\Standard;

use Fintecture\Payment\Controller\FintectureAbstract;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;

class Response extends FintectureAbstract
{
    public function execute()
    {
        try {
            if (!$this->sdk->isPisClientInstantiated()) {
                throw new \Exception('PISClient not instantiated');
            }

            $state = $this->request->getParam('state');
            $sessionId = $this->request->getParam('session_id');
            if (!$state || !$sessionId) {
                $this->fintectureLogger->error('Response', [
                    'message' => 'Invalid params',
                ]);

                return $this->redirectToCart();
            }

            $decodedState = json_decode(base64_decode($state));
            if (!is_object($decodedState) || !property_exists($decodedState, 'order_id')) {
                $this->fintectureLogger->error('Response', [
                    'message' => "Can't find an order id in the state",
                ]);

                return $this->redirectToCart();
            }

            $orderId = $decodedState->order_id;
            $order = $this->fintectureHelper->getOrderByIncrementId($orderId);
            if (!$order) {
                $this->fintectureLogger->error('Response', [
                    'message' => "Can't find an order associated with this state",
                    'orderId' => $orderId,
                ]);

                return $this->redirectToCart();
            }

            /** @phpstan-ignore-next-line */
            $pisToken = $this->sdk->pisClient->token->generate();
            if (!$pisToken->error) {
                $this->sdk->pisClient->setAccessToken($pisToken); // set token of PIS client
            } else {
                throw new \Exception($pisToken->errorMsg);
            }

            /** @phpstan-ignore-next-line */
            $apiResponse = $this->sdk->pisClient->payment->get($sessionId);
            if (!$apiResponse->error) {
                $params = [
                    'status' => $apiResponse->meta->status ?? '',
                    'sessionId' => $sessionId,
                    'transferState' => $apiResponse->data->transfer_state ?? '',
                    'type' => $apiResponse->meta->type ?? '',
                ];

                $statuses = $this->fintectureHelper->getOrderStatus($params);

                $this->fintectureLogger->debug('Response', [
                    'orderIncrementId' => $order->getIncrementId(),
                    'fintectureStatus' => $params['status'],
                    'status' => $statuses['status'] ?? 'Unhandled status',
                ]);

                if ($statuses && in_array($statuses['status'], [
                    $this->config->getPaymentCreatedStatus(),
                    $this->config->getPaymentPendingStatus(),
                ])) {
                    if ($statuses['status'] === $this->config->getPaymentCreatedStatus()) {
                        $this->handlePayment->create($order, $params, $statuses);
                    } else {
                        $this->handlePayment->changeOrderState($order, $params, $statuses);
                    }

                    try {
                        $this->checkoutSession->setLastQuoteId($order->getQuoteId());
                        $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                        $this->checkoutSession->setLastOrderId($order->getId());
                        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
                        $this->checkoutSession->setLastOrderStatus($order->getStatus());

                        if ($statuses['status'] === $this->config->getPaymentPendingStatus()) {
                            $this->messageManager->addSuccessMessage(__('Payment was initiated but has not been confirmed yet. Merchant will send confirmation once the transaction is settled.')->render());
                        }

                        return $this->resultRedirect->create()->setPath(
                            $this->fintectureHelper->getUrl('checkout/onepage/success')
                        );
                    } catch (\Exception $e) {
                        $this->fintectureLogger->error('Response', [
                            'exception' => $e,
                            'orderIncrementId' => $order->getIncrementId(),
                            'status' => $order->getStatus(),
                        ]);
                    }
                } else {
                    $this->handlePayment->fail($order, $params, $statuses);
                    $this->messageManager->addErrorMessage(__('The payment was unsuccessful. Please choose a different bank or different payment method.')->render());

                    return $this->redirectToCart();
                }
            } else {
                $this->fintectureLogger->error('Response', [
                    'message' => 'Invalid payment API response',
                    'response' => $apiResponse->errorMsg,
                ]);
                $this->messageManager->addErrorMessage(__("We can't place the order.")->render());
            }
        } catch (LocalizedException $e) {
            $this->fintectureLogger->error('Response', ['exception' => $e]);
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        } catch (\Exception $e) {
            $this->fintectureLogger->error('Response', ['exception' => $e]);
            $this->messageManager->addExceptionMessage($e, __("We can't place the order.")->render());
        }

        return $this->redirectToCart();
    }

    /**
     * In case of error, restore cart and redirect user to it
     */
    private function redirectToCart(string $returnUrl = null): Redirect
    {
        if (!$returnUrl) {
            $returnUrl = $this->fintectureHelper->getUrl('checkout') . '#payment';
        }

        $this->checkoutSession->restoreQuote();

        return $this->resultRedirect->create()->setPath($returnUrl);
    }
}
