<?php

declare(strict_types=1);

namespace Fintecture\Payment\Model;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

class CustomReconciliationField implements OptionSourceInterface
{
    /** @var OrderCollectionFactory */
    protected $orderCollectionFactory;

    public function __construct(OrderCollectionFactory $orderCollectionFactory)
    {
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * Get all order fields for the select field
     */
    public function toOptionArray(): array
    {
        $fields = [];

        // all fields (native & custom)
        $orderCollection = $this->orderCollectionFactory->create();
        $orderCollection->addAttributeToSelect('*');

        $attributes = $orderCollection->getConnection()
            ->describeTable($orderCollection->getMainTable());

        foreach ($attributes as $attributeCode => $attribute) {
            $fields[] = ['value' => $attributeCode, 'label' => __($attributeCode)];
        }

        // Sort alphabeticaly
        $keys = array_column($fields, 'value');
        array_multisort($keys, SORT_ASC, $fields);

        return $fields;
    }
}
