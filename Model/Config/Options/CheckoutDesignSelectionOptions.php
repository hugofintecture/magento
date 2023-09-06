<?php

namespace Fintecture\Payment\Model\Config\Options;

class CheckoutDesignSelectionOptions implements \Magento\Framework\Data\OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'it', 'label' => __('Immediate Transfer')],
            ['value' => 'ist', 'label' => __('Immediate Transfer & Smart Transfer')],
            ['value' => 'ist_long', 'label' => __('Long version')],
            ['value' => 'ist_short', 'label' => __('Short version')],
        ];
    }
}
