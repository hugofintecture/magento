<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller\Standard;

use Exception;
use Fintecture\Payment\Controller\WebhookAbstract;
use Magento\Framework\Exception\LocalizedException;

class PaymentUnsuccessful extends WebhookAbstract
{
    public function execute()
    {
        try {
            if ($this->validateWebhook()) {
                $body = file_get_contents('php://input');
                parse_str($body, $data);

                $this->fintectureLogger->debug('Webhook payment unsuccessful', $data);

                $fintecturePaymentSessionId = $data['session_id'] ?? '';
                $fintecturePaymentStatus = $data['status'] ?? '';

                if ($fintecturePaymentSessionId) {
                    $orderCollection = $this->orderCollectionFactory->create();
                    $orderCollection->addFieldToFilter('fintecture_payment_session_id', $fintecturePaymentSessionId);

                    $orderHooked = $orderCollection->getFirstItem();

                    if ($orderHooked->getId()) {
                        $order = $this->_orderFactory->create()->load($orderHooked->getId());

                        $data['meta']['session_id'] = $fintecturePaymentSessionId;
                        $data['meta']['status'] = $fintecturePaymentStatus;

                        $this->_paymentMethod->handleFailedTransaction($order, $data);
                    }
                }
            }
        } catch (LocalizedException $e) {
            $this->fintectureLogger->debug('Webhook Payment UnsuccessfulResponse Error 1 ' . $e->getMessage(), $e->getTrace());
        } catch (Exception $e) {
            $this->fintectureLogger->debug('Webhook PaymentUnsuccessful Response Error 2 ' . $e->getMessage(), $e->getTrace());
        }
    }
}
