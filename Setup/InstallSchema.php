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

        $salesOrderTable = $setup->getTable('sales_order');
        $connection      = $setup->getConnection();
        $connection->addColumn(
            $salesOrderTable, 'fintecture_payment_session_id', [
                                'type'     => Table::TYPE_TEXT,
                                'nullable' => true,
                                'length'   => 255,
                                'after'    => null, // column name to insert new column after
                                'comment'  => 'Fintecture payment session id'
                            ]
        );
        $connection->addColumn(
            $salesOrderTable, 'fintecture_payment_customer_id', [
                                'type'     => Table::TYPE_TEXT,
                                'nullable' => true,
                                'length'   => 255,
                                'after'    => null, // column name to insert new column after
                                'comment'  => 'Fintecture payment customer id'
                            ]
        );
        $setup->endSetup();
    }
}
