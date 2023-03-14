<?php

namespace Fintecture\Payment\Observer;

use Fintecture\Config\Telemetry;
use Fintecture\Payment\Model\Fintecture;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ConfigObserver implements ObserverInterface
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
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        $configurationSummary = $this->_paymentMethod->getConfigurationSummary();
        Telemetry::logAction('save', $configurationSummary);
    }
}
