<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller\Standard;

use Exception;
use Fintecture\Payment\Controller\FintectureAbstract;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\Exception\LocalizedException;

class Redirect extends FintectureAbstract
{
    public function execute()
    {
        if (!$this->request->isAjax()) {
            $this->fintectureLogger->debug('Error Redirection : non ajax request');
            throw new LocalizedException(__('Error Redirection : non ajax request'));
        }

        try {
            $quote = $this->getQuote();
            $email = $this->request->getParam('email');

            if ($this->customerSession->isLoggedIn()) {
                $quote->setCheckoutMethod(Onepage::METHOD_CUSTOMER);
                $this->checkoutSession->loadCustomerQuote();
                $quote->updateCustomerData($this->getQuote()->getCustomer());
            } else {
                $quote->setCheckoutMethod(Onepage::METHOD_GUEST);
            }

            $quote->setCustomerEmail($email);
            $quote->save();

            $response = $this->paymentMethod->getPaymentGatewayRedirectUrl();
            $params = [
                'url' => $response
            ];

            return $this->resultJsonFactory->create()->setData($params);
        } catch (Exception $e) {
            $this->fintectureLogger->debug('Error Redirection : ' . $e->getMessage(), $e->getTrace());
            throw new LocalizedException(__($e->getMessage()));
        }
    }
}
