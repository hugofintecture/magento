<?php

declare(strict_types=1);

namespace Fintecture\Payment\Block;

use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;

class CustomLogo extends Template
{
    const CUSTOM_LOGO_SHOW = 'payment/fintecture/general/show_logo';

    public function getShowLogo()
    {
        return $this->_scopeConfig->getValue(
            static::CUSTOM_LOGO_SHOW,
            ScopeInterface::SCOPE_STORE
        );
    }
}
