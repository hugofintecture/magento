<?php

declare(strict_types=1);

namespace Fintecture\Payment\Helper;

use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;

class Cookie
{
    /**
     * @var CookieManagerInterface $cookieManager
     */
    private $cookieManager;

    /**
     * @var CookieMetadataFactory $cookieMetadataFactory
     */
    private $cookieMetadataFactory;

    public function __construct(
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory
    ) {
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
    }

    public function setCookie(string $name, $value, int $duration = 3600)
    {
        $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
        $publicCookieMetadata->setDuration($duration);
        $publicCookieMetadata->setPath('/');
        $publicCookieMetadata->setHttpOnly(true);

        return $this->cookieManager->setPublicCookie($name, $value, $publicCookieMetadata);
    }

    public function getCookie(string $name)
    {
        return $this->cookieManager->getCookie($name);
    }
}
