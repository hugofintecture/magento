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
    protected $fintectureHelper;

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
        FintectureHelper $fintectureHelper,
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        RedirectFactory $resultRedirect,
        ManagerInterface $messageManager,
        QuoteFactory $quoteFactory,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->paymentMethod = $paymentMethod;
        $this->fintectureHelper = $fintectureHelper;
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
