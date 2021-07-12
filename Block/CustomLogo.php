<?php
declare(strict_types=1);

namespace Fintecture\Payment\Block;

use Fintecture\Payment\Model\ShowLogo;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;

class CustomLogo extends Template
{
    const CUSTOM_LOGO_TYPE     = 'payment/fintecture/general/show_logo';
    const CUSTOM_LOGO_POSITION = 'payment/fintecture/general/logo_position';

    public function getImageUrl()
    {
        $mediaUrl = $this->getViewFileUrl('Fintecture_Payment::images');
        $logoType = $this->_scopeConfig->getValue(static::CUSTOM_LOGO_TYPE, ScopeInterface::SCOPE_STORE);
        $imageUrl = $mediaUrl . '/133x29_horizontal_gif.gif';

        if ($logoType === ShowLogo::SHORT) {
            $imageUrl = $mediaUrl . '/29x29_square_gif.gif';
        }

        return $imageUrl;
    }

    public function getLogoType()
    {
        return $this->_scopeConfig->getValue(
            static::CUSTOM_LOGO_TYPE,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getLogoPosition()
    {
        return $this->_scopeConfig->getValue(
            static::CUSTOM_LOGO_POSITION,
            ScopeInterface::SCOPE_STORE
        );
    }
}
