<?php

declare(strict_types=1);

namespace Fintecture\Payment\Model\Ui;

use Fintecture\Payment\Gateway\Config\Config;
use Magento\Checkout\Model\ConfigProviderInterface;

class ConfigProvider implements ConfigProviderInterface
{
    /** @var Config */
    protected $gatewayConfig;

    public function __construct(Config $gatewayConfig)
    {
        $this->gatewayConfig = $gatewayConfig;
    }

    public function getConfig()
    {
        $config = [
            'payment' => [
                Config::CODE => [
                    'active' => $this->gatewayConfig->isActive(),
                ],
            ],
        ];

        return $config;
    }
}
