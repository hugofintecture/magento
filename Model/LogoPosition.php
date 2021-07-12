<?php
declare(strict_types=1);

namespace Fintecture\Payment\Model;

use Magento\Framework\Data\OptionSourceInterface;

class LogoPosition implements OptionSourceInterface
{
    const LEFT  = 'left';
    const RIGHT = 'right';

    public function toOptionArray(): array
    {
        return [
            [
                'value' => static::LEFT,
                'label' => __('Left of Title'),
            ],
            [
                'value' => static::RIGHT,
                'label' => __('Right of Title')
            ]
        ];
    }
}
