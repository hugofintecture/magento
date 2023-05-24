<?php

namespace Fintecture\Payment\Observer;

use Fintecture\Config\Telemetry;
use Fintecture\Payment\Helper\Stats;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ConfigObserver implements ObserverInterface
{
    /** @var Stats */
    protected $stats;

    public function __construct(Stats $stats)
    {
        $this->stats = $stats;
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
        $configurationSummary = $this->stats->getConfigurationSummary();
        try {
            Telemetry::logAction('save', $configurationSummary);
        } catch (\Exception $e) {
            // do nothing
        }
    }
}
