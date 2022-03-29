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
        $returnUrl = $this->checkoutHelper->getUrl('checkout');

        try {
            $response = $this->paymentMethod->getLastPaymentStatusResponse();
            $status = $response['meta']['status'];
            $sessionId = $response['meta']['session_id'];

            $quote = $this->getQuote();
            $order = $this->getOrder();

            $statuses = $this->checkoutHelper->getOrderStatusBasedOnPaymentStatus($status);

            if ($statuses['status'] === Order::STATE_PROCESSING) {
                $returnUrl = $this->checkoutHelper->getUrl('checkout/onepage/success');

                $this->paymentMethod->handleSuccessTransaction($order, $status, $sessionId, $statuses);

                try {
                    $this->checkoutSession->setLastQuoteId($quote->getId());
                    $this->checkoutSession->setLastSuccessQuoteId($quote->getId());

                    $this->checkoutSession->setLastOrderId($order->getId());
                    $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
                    $this->checkoutSession->setLastOrderStatus($order->getStatus());

                    $quote->setIsActive(false);
                    $this->quoteRepository->save($quote);
                    return $this->resultRedirect->create()->setPath($returnUrl);
                } catch (Exception $e) {
                    $this->fintectureLogger->error($e, $e->getTrace());
                }
            } elseif ($statuses['status'] === Order::STATE_PENDING_PAYMENT) {
                $returnUrl = $this->checkoutHelper->getUrl('checkout/onepage/success');
                $this->paymentMethod->handleHoldedTransaction($order, $status, $sessionId, $statuses);
                $this->messageManager->addSuccessMessage(__('Payment was initiated but has not been confirmed yet. Merchant will send confirmation once the transaction is settled.'));
                return $this->resultRedirect->create()->setPath($returnUrl);
            } else {
                $returnUrl = $this->checkoutHelper->getUrl('checkout') . "#payment";
                $this->paymentMethod->handleFailedTransaction($order, $status, $sessionId);
                $this->messageManager->addErrorMessage(__('The payment was unsuccessful. Please choose a different bank or different payment method.'));
            }
        } catch (LocalizedException $e) {
            $this->fintectureLogger->debug('Response error: ' . $e->getMessage(), $e->getTrace());
            $this->messageManager->addExceptionMessage($e, __($e->getMessage()));
        } catch (Exception $e) {
            $this->fintectureLogger->debug('Response error: ' . $e->getMessage(), $e->getTrace());
            $this->messageManager->addExceptionMessage($e, __('We can\'t place the order.'));
        }

        $this->checkoutSession->restoreQuote(); // restore cart in case of error
        return $this->resultRedirect->create()->setPath($returnUrl);
    }
}
