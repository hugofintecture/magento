<?php

namespace Fintecture\Payment\Gateway\Request;

use Fintecture\Payment\Gateway\Config\Config;
use Magento\Checkout\Model\Session;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class InitializationRequest implements BuilderInterface
{
    /** @var Config */
    protected $config;

    /** @var Session */
    protected $session;

    public function __construct(
        Config $config,
        Session $session
    ) {
        $this->config = $config;
        $this->session = $session;
    }

    /**
     * Checks the quote for validity
     */
    private function validateQuote(OrderAdapterInterface $order): bool
    {
        if ($this->config->allowSpecific()) {
            $allowedCountries = $this->config->getSpecificCountries();
            if ($allowedCountries) {
                $billingAddress = $order->getBillingAddress();
                if ($billingAddress && !in_array($billingAddress->getCountryId(), $allowedCountries)) {
                    $this->session->setFintectureErrorMessage(__('Orders from this country are not supported by Fintecture. Please select a different payment option.'));

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Builds ENV request
     * From: https://github.com/magento/magento2/blob/2.1.3/app/code/Magento/Payment/Model/Method/Adapter.php
     * The $buildSubject contains:
     * 'payment' => $this->getInfoInstance()
     * 'paymentAction' => $paymentAction
     * 'stateObject' => $stateObject
     */
    public function build(array $buildSubject): array
    {
        $payment = $buildSubject['payment'];
        $stateObject = $buildSubject['stateObject'];

        $order = $payment->getOrder();

        if ($this->validateQuote($order)) {
            $stateObject->setState(Order::STATE_NEW);
            $stateObject->setStatus($this->config->getNewOrderStatus());
            $stateObject->setIsNotified(false);
        } else {
            $stateObject->setState(Order::STATE_CANCELED);
            $stateObject->setStatus(Order::STATE_CANCELED);
            $stateObject->setIsNotified(false);
        }

        return ['IGNORED' => ['IGNORED']];
    }
}
