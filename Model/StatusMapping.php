<?php

declare(strict_types=1);

namespace Fintecture\Payment\Model;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Sales\Model\ResourceModel\Order\Status\Collection as OrderStatusCollection;

class StatusMapping implements OptionSourceInterface
{
    /** @var OrderStatusCollection */
    private $orderStatusCollection;

    public function __construct(OrderStatusCollection $orderStatusCollection)
    {
        $this->orderStatusCollection = $orderStatusCollection;
    }

    public function toOptionArray(): array
    {
        return $this->orderStatusCollection->toOptionArray();
    }
}
