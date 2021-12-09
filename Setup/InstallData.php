<?php

declare(strict_types=1);

namespace Fintecture\Payment\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class InstallData implements InstallDataInterface
{
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $connection = $setup->getConnection();
        $coreConfigData = $setup->getTable('core_config_data');

        $connection->insertOnDuplicate(
            $coreConfigData,
            [
                'scope' => 'default',
                'scope_id' => 0,
                'path' => 'payment/fintecture/fintecture_api_url_sandbox',
                'value' => 'https://api-sandbox.fintecture.com/',
            ]
        );

        $connection->insertOnDuplicate(
            $coreConfigData,
            [
                'scope' => 'default',
                'scope_id' => 0,
                'path' => 'payment/fintecture/fintecture_api_url_production',
                'value' => 'https://api.fintecture.com/',
            ]
        );

        $setup->endSetup();
    }
}
