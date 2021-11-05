<?php

namespace Fintecture\Payment\Model\Cmsblock;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Cms\Block\Widget\Block;

class ConfigProvider implements ConfigProviderInterface
{
    protected $cmsBlockWidget;

    public function __construct(Block $block, $blockId)
    {
        $this->cmsBlockWidget = $block;
        $block->setData('block_id', 'checkout_payment_block');
        $block->setTemplate('Magento_Cms::widget/static_block/default.phtml');
    }

    public function getConfig()
    {
        return [
           'cms_block' => $this->cmsBlockWidget->toHtml()
        ];
    }
}
