<?php
declare(strict_types=1);

namespace Fintecture\Payment\Model;

use Magento\Framework\Data\OptionSourceInterface;

class ShowLogo implements OptionSourceInterface
{
    const SHORT = 'short';
    const LONG  = 'long';

    public function toOptionArray(): array
    {
        return [
            [
                'value' => static::SHORT,
                'label' => __('Short'),
            ],
            [
                'value' => static::LONG,
                'label' => __('Long')
            ]
        ];
    }
}
