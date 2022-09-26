<?php

declare(strict_types=1);

namespace Fintecture\Payment\Observer\Controller;

use Fintecture\Config\Telemetry;
use Fintecture\Payment\Helper\Cookie as CookieHelper;
use Fintecture\Payment\Model\Fintecture;
use Magento\Checkout\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ActionPredispatchCheckoutIndexIndex implements ObserverInterface
{
    /** @var Fintecture $_paymentMethod */
    protected $_paymentMethod;

    /** @var Session $_checkoutSession */
    protected $_checkoutSession;

    /** @var CookieHelper $_cookieHelper */
    protected $_cookieHelper;

    public function __construct(
        Fintecture $paymentMethod,
        Session $checkoutSession,
        CookieHelper $cookieHelper
    ) {
        $this->_paymentMethod = $paymentMethod;
        $this->_checkoutSession = $checkoutSession;
        $this->_cookieHelper = $cookieHelper;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $quoteId = (int) $this->_checkoutSession->getQuote()->getId();
        $storedQuoteId = $this->_cookieHelper->getCookie('fintecture-cartId');

        if ($storedQuoteId && $quoteId !== (int) $storedQuoteId) {
            $configurationSummary = $this->_paymentMethod->getConfigurationSummary();
            Telemetry::logAction('checkout', $configurationSummary);

            $this->_cookieHelper->setCookie('fintecture-cartId', $quoteId, 3600 * 24 * 7);
        }
    }
}
