<?php
declare(strict_types=1);

namespace Fintecture\Payment\Model;

use Magento\Framework\Data\OptionSourceInterface;

class Environment implements OptionSourceInterface
{
    const ENVIRONMENT_PRODUCTION = 'production';
    const ENVIRONMENT_SANDBOX    = 'sandbox';

    public function toOptionArray(): array
    {
        return [
            [
                'value' => static::ENVIRONMENT_SANDBOX,
                'label' => __('Sandbox'),
            ],
            [
                'value' => static::ENVIRONMENT_PRODUCTION,
                'label' => __('Production')
            ]
        ];
    }
}
