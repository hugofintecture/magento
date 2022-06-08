<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller;

use Fintecture\Payment\Helper\Fintecture as FintectureHelper;
use Fintecture\Payment\Logger\Logger;
use Fintecture\Payment\Model\Fintecture as FintectureModel;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\QuoteFactory;

abstract class FintectureAbstract implements ActionInterface
{
    /** @var CheckoutSession */
    protected $checkoutSession;

    /** @var Logger */
    protected $fintectureLogger;

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

    /** @var QuoteFactory */
    protected $quoteFactory;

    /** @var ManagerInterface */
    protected $messageManager;

    /** @var MaskedQuoteIdToQuoteIdInterface */
    protected $maskedQuoteIdToQuoteId;

    public function __construct(
        CheckoutSession $checkoutSession,
        Logger $fintectureLogger,
        FintectureModel $paymentMethod,
        FintectureHelper $checkoutHelper,
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        RedirectFactory $resultRedirect,
        ManagerInterface $messageManager,
        QuoteFactory $quoteFactory,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->paymentMethod = $paymentMethod;
        $this->checkoutHelper = $checkoutHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->fintectureLogger = $fintectureLogger;
        $this->request = $request;
        $this->resultRedirect = $resultRedirect;
        $this->messageManager = $messageManager;
        $this->quoteFactory = $quoteFactory;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
    }

    protected function getOrder()
    {
        return $this->checkoutSession->getLastRealOrder();
    }
}
