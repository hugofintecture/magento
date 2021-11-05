<?php

declare(strict_types=1);

namespace Fintecture\Payment\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;

class UpgradeData implements UpgradeDataInterface
{
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        if (version_compare($context->getVersion(), '1.0.3', '>')) {
            $setup->startSetup();

            $connection     = $setup->getConnection();
            $coreConfigData = $setup->getTable('core_config_data');

            $connection->delete($coreConfigData, ['value' => 'payment/fintecture/fintecture_pis_url_sandbox']);
            $connection->delete($coreConfigData, ['value' => 'payment/fintecture/fintecture_oauth_url_sandbox']);
            $connection->delete($coreConfigData, ['value' => 'payment/fintecture/fintecture_connect_url_sandbox']);
            $connection->delete($coreConfigData, ['value' => 'payment/fintecture/fintecture_oauth_url_production']);
            $connection->delete($coreConfigData, ['value' => 'payment/fintecture/fintecture_pis_url_production']);
            $connection->delete($coreConfigData, ['value' => 'payment/fintecture/fintecture_connect_url_production']);

            $connection->update(
                $coreConfigData,
                ['value' => 'Fintecture'],
                ['path' => 'payment/fintecture/title',]
            );

            $connection->update(
                $coreConfigData,
                ['value' => 'all'],
                [
                    'path'  => 'payment/fintecture/general/bank_type',
                    'value' => 'All',
                ]
            );

            $connection->update(
                $coreConfigData,
                ['value' => 'corporate'],
                [
                    'path'  => 'payment/fintecture/general/bank_type',
                    'value' => 'Corporate',
                ]
            );

            $connection->update(
                $coreConfigData,
                ['value' => 'retail'],
                [
                    'path'  => 'payment/fintecture/general/bank_type',
                    'value' => 'Retail',
                ]
            );

            $connection->insertOnDuplicate(
                $coreConfigData,
                [
                    'scope'    => 'default',
                    'scope_id' => 0,
                    'path'     => 'payment/fintecture/fintecture_api_url_sandbox',
                    'value'    => 'https://api-sandbox.fintecture.com/',
                ]
            );

            $connection->insertOnDuplicate(
                $coreConfigData,
                [
                    'scope'    => 'default',
                    'scope_id' => 0,
                    'path'     => 'payment/fintecture/fintecture_api_url_production',
                    'value'    => 'https://api.fintecture.com/',
                ]
            );

            $setup->endSetup();
        }
    }
}
