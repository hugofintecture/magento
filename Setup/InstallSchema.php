<?php

declare(strict_types=1);

namespace Fintecture\Payment\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $connection = $setup->getConnection();
        $connection->addColumn(
            $setup->getTable('sales_order'),
            'fintecture_payment_refund_amount',
            [
                'type' => Table::TYPE_DECIMAL,
                'nullable' => true,
                'length' => '20,4',
                'comment' => 'Total amount refunded by Fintecture',
            ]
        );

        $setup->endSetup();
    }
}
