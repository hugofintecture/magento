<?php

namespace Fintecture\Payment\Gateway\Config;

use Magento\Payment\Gateway\Config\Config as BaseConfig;

class BnplConfig extends BaseConfig
{
    const CODE = 'fintecture_bnpl';

    const KEY_ACTIVE = 'active';
    const KEY_RECOMMEND_BNPL_BADGE = 'recommend_bnpl_badge';

    public function isActive(): bool
    {
        return (bool) $this->getValue(self::KEY_ACTIVE);
    }

    public function isRecommendedBnplBadgeActive(): bool
    {
        return (bool) $this->getValue(self::KEY_RECOMMEND_BNPL_BADGE);
    }
}
