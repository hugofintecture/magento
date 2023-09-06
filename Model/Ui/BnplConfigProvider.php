<?php

declare(strict_types=1);

namespace Fintecture\Payment\Model\Ui;

use Fintecture\Payment\Gateway\Config\BnplConfig;
use Fintecture\Payment\Helper\BeautifulDate;
use Magento\Checkout\Model\ConfigProviderInterface;

class BnplConfigProvider implements ConfigProviderInterface
{
    /** @var BnplConfig */
    protected $gatewayConfig;

    /** @var BeautifulDate */
    protected $beautifulDate;

    public function __construct(
        BnplConfig $gatewayConfig,
        BeautifulDate $beautifulDate
    ) {
        $this->gatewayConfig = $gatewayConfig;
        $this->beautifulDate = $beautifulDate;
    }

    public function getConfig()
    {
        $today = new \DateTime('now');

        $later = new \DateTime('now');
        $later->modify('+30 day');

        $config = [
            'payment' => [
                BnplConfig::CODE => [
                    'active' => $this->gatewayConfig->isActive(),
                    'todayDate' => $this->beautifulDate->formatDatetime($today),
                    'laterDate' => $this->beautifulDate->formatDatetime($later),
                    'recommendBnplBadge' => $this->gatewayConfig->isRecommendedBnplBadgeActive(),
                ],
            ],
        ];

        return $config;
    }
}
