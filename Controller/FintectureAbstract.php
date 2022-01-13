<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;

abstract class FintectureAbstract extends Action
{
    /** @var \Magento\Checkout\Model\Session */
    protected $_checkoutSession;

    /** @var \Magento\Customer\Model\Session */
    protected $_customerSession;

    /** @var \Magento\Quote\Api\CartRepositoryInterface */
    protected $quoteRepository;

    /** @var \Fintecture\Payment\Logger\Logger */
    protected $fintectureLogger;

    /** @var \Magento\Quote\Model\Quote */
    protected $_quote;

    /** @var \Fintecture\Payment\Model\Fintecture */
    protected $_paymentMethod;

    /** @var \Fintecture\Payment\Helper\Fintecture */
    protected $_checkoutHelper;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $resultJsonFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Fintecture\Payment\Logger\Logger $finlogger,
        \Fintecture\Payment\Model\Fintecture $paymentMethod,
        \Fintecture\Payment\Helper\Fintecture $checkoutHelper,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->_customerSession = $customerSession;
        $this->_checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->_paymentMethod = $paymentMethod;
        $this->_checkoutHelper = $checkoutHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->fintectureLogger = $finlogger;
        parent::__construct($context);
    }

    protected function initCheckout()
    {
        $quote = $this->getQuote();
        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->getResponse()->setStatusHeader(403, '1.1', 'Forbidden');
            throw new LocalizedException(__('We can\'t initialize checkout.'));
        }
    }

    protected function _cancelPayment($errorMsg = '')
    {
        $gotoSection = false;
        $this->_checkoutHelper->cancelCurrentOrder($errorMsg);
        if ($this->_checkoutSession->restoreQuote()) {
            //Redirect to payment step
            $gotoSection = 'paymentMethod';
        }

        return $gotoSection;
    }

    protected function getOrderById($order_id)
    {
        $objectManager = ObjectManager::getInstance();
        $order = $objectManager->get('Magento\Sales\Model\Order');
        $order_info = $order->loadByIncrementId($order_id);
        return $order_info;
    }

    protected function getOrder()
    {
        return $this->getCheckoutSession()->getLastRealOrder();
    }

    protected function getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = $this->getCheckoutSession()->getQuote();
        }
        return $this->_quote;
    }

    protected function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    public function getCustomerSession()
    {
        return $this->_customerSession;
    }

    public function getPaymentMethod()
    {
        return $this->_paymentMethod;
    }

    protected function getCheckoutHelper()
    {
        return $this->_checkoutHelper;
    }
}
