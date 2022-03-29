<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller;

use Fintecture\Payment\Helper\Fintecture as FintectureHelper;
use Fintecture\Payment\Logger\Logger;
use Fintecture\Payment\Model\Fintecture as FintectureModel;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

abstract class FintectureAbstract implements ActionInterface
{
    /** @var CheckoutSession */
    protected $checkoutSession;

    /** @var CustomerSession */
    protected $customerSession;

    /** @var CartRepositoryInterface */
    protected $quoteRepository;

    /** @var Logger */
    protected $fintectureLogger;

    /** @var Quote */
    protected $quote;

    /** @var FintectureModel */
    protected $paymentMethod;

    /** @var FintectureHelper */
    protected $checkoutHelper;

    /** @var JsonFactory */
    protected $resultJsonFactory;

    /** @var RequestInterface */
    protected $request;

    /** @var RedirectFactory */
    protected $resultRedirect;

    /** @var \ManagerInterface */
    protected $messageManager;

    public function __construct(
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $quoteRepository,
        Logger $fintectureLogger,
        FintectureModel $paymentMethod,
        FintectureHelper $checkoutHelper,
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        RedirectFactory $resultRedirect,
        ManagerInterface $messageManager
    ) {
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->paymentMethod = $paymentMethod;
        $this->checkoutHelper = $checkoutHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->fintectureLogger = $fintectureLogger;
        $this->request = $request;
        $this->resultRedirect = $resultRedirect;
        $this->messageManager = $messageManager;
    }

    protected function getOrder()
    {
        return $this->checkoutSession->getLastRealOrder();
    }

    protected function getQuote()
    {
        if (!$this->quote) {
            $this->quote = $this->checkoutSession->getQuote();
        }
        return $this->quote;
    }
}
