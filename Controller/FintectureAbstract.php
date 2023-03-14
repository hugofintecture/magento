<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller;

use Fintecture\Payment\Helper\Fintecture as FintectureHelper;
use Fintecture\Payment\Logger\Logger;
use Fintecture\Payment\Model\Fintecture as FintectureModel;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

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

    /** @var Http */
    protected $request;

    /** @var RedirectFactory */
    protected $resultRedirect;

    /** @var CartRepositoryInterface */
    protected $quoteRepository;

    /** @var ManagerInterface */
    protected $messageManager;

    /** @var MaskedQuoteIdToQuoteIdInterface */
    protected $maskedQuoteIdToQuoteId;

    /** @var SessionManagerInterface */
    protected $coreSession;

    /** @var PageFactory */
    protected $pageFactory;

    /** @var UrlInterface */
    protected $urlInterface;

    public function __construct(
        CheckoutSession $checkoutSession,
        Logger $fintectureLogger,
        FintectureModel $paymentMethod,
        FintectureHelper $fintectureHelper,
        JsonFactory $resultJsonFactory,
        Http $request,
        RedirectFactory $resultRedirect,
        ManagerInterface $messageManager,
        CartRepositoryInterface $quoteRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        SessionManagerInterface $coreSession,
        PageFactory $pageFactory,
        UrlInterface $urlInterface
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->paymentMethod = $paymentMethod;
        $this->fintectureHelper = $fintectureHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->fintectureLogger = $fintectureLogger;
        $this->request = $request;
        $this->resultRedirect = $resultRedirect;
        $this->messageManager = $messageManager;
        $this->quoteRepository = $quoteRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->coreSession = $coreSession;
        $this->pageFactory = $pageFactory;
        $this->urlInterface = $urlInterface;
    }
}
