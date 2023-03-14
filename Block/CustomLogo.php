<?php

declare(strict_types=1);

namespace Fintecture\Payment\Block;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class CustomLogo extends Template
{
    public const CUSTOM_LOGO_SHOW = 'payment/fintecture/general/show_logo';

    /** @var StoreManagerInterface */
    protected $_storeManager;

    /** @var UrlInterface */
    protected $_urlInterface;

    /** @var Reader */
    protected $moduleReader;

    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    /**
     * CustomLogo constructor.
     *
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlInterface
     * @param Reader $moduleReader
     * @param array $data
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        UrlInterface $urlInterface,
        Reader $moduleReader,
        array $data = []
    ) {
        $this->_storeManager = $storeManager;
        $this->_urlInterface = $urlInterface;
        $this->moduleReader = $moduleReader;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    /**
     * @return bool
     */
    public function getShowLogo(): bool
    {
        return $this->scopeConfig->isSetFlag(
            static::CUSTOM_LOGO_SHOW,
            ScopeInterface::SCOPE_STORE
        );
    }
}
