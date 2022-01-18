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
        $returnUrl = $this->getCheckoutHelper()->getUrl('checkout');

        try {
            $paymentMethod = $this->getPaymentMethod();
            $response = $paymentMethod->getLastPaymentStatusResponse();

            $quote = $this->getQuote();
            $order = $this->getOrder();

            $orderStatus = $this->getCheckoutHelper()->getOrderStatusBasedOnPaymentStatus($response);
            $status = $orderStatus['status'] ?? '';

            if ($status === Order::STATE_PROCESSING) {
                $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/success');

                $paymentMethod->handleSuccessTransaction($order, $response);

                try {
                    $this->getCheckoutSession()->setLastQuoteId($quote->getId());
                    $this->getCheckoutSession()->setLastSuccessQuoteId($quote->getId());

                    $this->getCheckoutSession()->setLastOrderId($order->getId());
                    $this->getCheckoutSession()->setLastRealOrderId($order->getIncrementId());
                    $this->getCheckoutSession()->setLastOrderStatus($order->getStatus());

                    $this->getCheckoutSession()->setFintectureState(null);

                    $quote->setIsActive(false);
                    $this->quoteRepository->save($quote);
                } catch (Exception $e) {
                    $this->fintectureLogger->error($e, $e->getTrace());
                }
            } elseif ($status === Order::STATE_PENDING_PAYMENT) {
                $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/success');
                $paymentMethod->handleHoldedTransaction($order, $response);
                $this->getCheckoutSession()->setFintectureState(null);
                $this->messageManager->addSuccessMessage(__('Payment was initiated but has not been confirmed yet. Merchant will send confirmation once the transaction is settled.'));
            } else {
                $returnUrl = $this->getCheckoutHelper()->getUrl('checkout') . "#payment";
                $paymentMethod->handleFailedTransaction($order, $response);
                $this->getCheckoutSession()->setFintectureState('failed');
                $this->getCheckoutSession()->restoreQuote();
                $this->messageManager->addErrorMessage(__('The payment was unsuccessful. Please choose a different bank or different payment method.'));
            }
        } catch (LocalizedException $e) {
            $this->fintectureLogger->debug('Response Error 1 : ' . $e->getMessage(), $e->getTrace());
            $this->messageManager->addExceptionMessage($e, __($e->getMessage()));
        } catch (Exception $e) {
            $this->fintectureLogger->debug('Response Error 2 : ' . $e->getMessage(), $e->getTrace());
            $this->messageManager->addExceptionMessage($e, __('We can\'t place the order.'));
        }

        $this->_redirect($returnUrl);
        return;
    }
}
