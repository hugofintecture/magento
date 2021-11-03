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
        if (!$this->getRequest()->isAjax()) {
            $this->_cancelPayment();
            $this->getCheckoutSession()->restoreQuote();
            $this->getResponse()
                 ->setRedirect(
                     $this->getCheckoutHelper()->getUrl('checkout') . "#payment"
                 )->sendResponse();
        }

        try {
            $quote = $this->getQuote();
            $email = $this->getRequest()->getParam('email');
            $quote->setCheckoutMethod(Onepage::METHOD_GUEST);

            if ($this->getCustomerSession()->isLoggedIn()) {
                $quote->setCheckoutMethod(Onepage::METHOD_CUSTOMER);
                $this->getCheckoutSession()->loadCustomerQuote();
                $quote->updateCustomerData($this->getQuote()->getCustomer());
            }

            $quote->setCustomerEmail($email);
            $quote->save();

            $params = [];
            $response = $this->getPaymentMethod()->getPaymentGatewayRedirectUrl();

            $this->fintectureLogger->debug('Redirection', [$response]);

            $params['url'] = $response;

            return $this->resultJsonFactory->create()->setData($params);
        } catch (Exception $e) {
            $this->fintectureLogger->debug('Error Redirection : ' . $e->getMessage(), $e->getTrace());

            $this->getCheckoutSession()->restoreQuote();
            throw new LocalizedException(
                __($e->getMessage())
            );
        }
    }
}
