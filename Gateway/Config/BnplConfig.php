<?php

namespace Fintecture\Payment\Gateway\Config;

use Magento\Payment\Gateway\Config\Config as BaseConfig;

class BnplConfig extends BaseConfig
{
    const CODE = 'fintecture_bnpl';

    const KEY_ACTIVE = 'active';

    public function isActive(): bool
    {
        return (bool) $this->getValue(self::KEY_ACTIVE);
    }
}
