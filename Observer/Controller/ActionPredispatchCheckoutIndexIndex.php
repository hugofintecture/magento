<?php

declare(strict_types=1);

namespace Fintecture\Payment\Observer\Controller;

use Fintecture\Payment\Model\Fintecture;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ActionPredispatchCheckoutIndexIndex implements ObserverInterface
{
    /** @var Fintecture */
    protected $_paymentMethod;

    public function __construct(Fintecture $paymentMethod)
    {
        $this->_paymentMethod = $paymentMethod;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $configurationSummary = $this->_paymentMethod->getConfigurationSummary();
        $this->_paymentMethod->getGatewayClient()->logAction('checkout', $configurationSummary);
    }
}
