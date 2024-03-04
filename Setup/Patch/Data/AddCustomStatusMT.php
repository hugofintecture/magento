<?php

namespace Fintecture\Payment\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;

class AddCustomStatusMT implements DataPatchInterface
{
    /** Custom overpaid order-status code */
    public const ORDER_STATUS_OVERPAID_CODE = 'fintecture_overpaid';

    /** Custom overpaid order-status label */
    public const ORDER_STATUS_OVERPAID_LABEL = 'Fintecture Overpaid Payment';

    /** Custom partial order-status code */
    public const ORDER_STATUS_PARTIAL_CODE = 'fintecture_partial';

    /** Custom partial order-status label */
    public const ORDER_STATUS_PARTIAL_LABEL = 'Fintecture Partial Payment';

    /** @var StatusFactory */
    protected $statusFactory;

    /** @var StatusResourceFactory */
    protected $statusResourceFactory;

    public function __construct(
        StatusFactory $statusFactory,
        StatusResourceFactory $statusResourceFactory
    ) {
        $this->statusFactory = $statusFactory;
        $this->statusResourceFactory = $statusResourceFactory;
    }

    public function apply()
    {
        $this->addCustomStatus(self::ORDER_STATUS_OVERPAID_CODE, self::ORDER_STATUS_OVERPAID_LABEL, Order::STATE_PROCESSING);
        $this->addCustomStatus(self::ORDER_STATUS_PARTIAL_CODE, self::ORDER_STATUS_PARTIAL_LABEL);

        return $this;
    }

    public function addCustomStatus(string $id, string $label, ?string $state = null): bool
    {
        $status = $this->statusFactory->create();
        $statusResourceFactory = $this->statusResourceFactory->create();

        $status->setData([
            'status' => $id,
            'label' => $label,
        ]);
        try {
            $statusResourceFactory->save($status);
        } catch (\Exception $e) {
            return false;
        }

        if ($state) {
            $status->assignState($state, false, true);
        }

        return true;
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
