<?php

declare(strict_types=1);

namespace Fintecture\Payment\Model;

use Magento\Framework\Data\OptionSourceInterface;

class BankType implements OptionSourceInterface
{
    public const BANK_RETAIL = 'retail';
    public const BANK_CORPORATE = 'corporate';
    public const BANK_ALL = 'all';

    public function toOptionArray(): array
    {
        return [
            [
                'value' => static::BANK_ALL,
                'label' => __('All')
            ],
            [
                'value' => static::BANK_RETAIL,
                'label' => __('Retail')
            ],
            [
                'value' => static::BANK_CORPORATE,
                'label' => __('Corporate')
            ]
        ];
    }
}
