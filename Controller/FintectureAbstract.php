<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller;

use Fintecture\Payment\Gateway\Config\Config;
use Fintecture\Payment\Gateway\Connect;
use Fintecture\Payment\Gateway\HandlePayment;
use Fintecture\Payment\Gateway\Http\Sdk;
use Fintecture\Payment\Gateway\RequestToPay;
use Fintecture\Payment\Helper\Fintecture as FintectureHelper;
use Fintecture\Payment\Logger\Logger;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

abstract class FintectureAbstract implements CsrfAwareActionInterface
{
    /** @var CheckoutSession */
    protected $checkoutSession;

    /** @var Logger */
    protected $fintectureLogger;

    /** @var FintectureHelper */
    protected $fintectureHelper;

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

    /** @var Sdk */
    protected $sdk;

    /** @var Config */
    protected $config;

    /** @var HandlePayment */
    protected $handlePayment;

    /** @var Connect */
    protected $connect;

    /** @var RequestToPay */
    protected $requestToPay;

    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    public const PIS_TYPE = 'PayByBank';
    public const RTP_TYPE = 'RequestToPay';
    public const BNPL_TYPE = 'BuyNowPayLater';

    public function __construct(
        CheckoutSession $checkoutSession,
        Logger $fintectureLogger,
        FintectureHelper $fintectureHelper,
        Http $request,
        RedirectFactory $resultRedirect,
        ManagerInterface $messageManager,
        CartRepositoryInterface $quoteRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        SessionManagerInterface $coreSession,
        PageFactory $pageFactory,
        UrlInterface $urlInterface,
        Sdk $sdk,
        Config $config,
        HandlePayment $handlePayment,
        Connect $connect,
        RequestToPay $requestToPay,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->fintectureHelper = $fintectureHelper;
        $this->fintectureLogger = $fintectureLogger;
        $this->request = $request;
        $this->resultRedirect = $resultRedirect;
        $this->messageManager = $messageManager;
        $this->quoteRepository = $quoteRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->coreSession = $coreSession;
        $this->pageFactory = $pageFactory;
        $this->urlInterface = $urlInterface;
        $this->sdk = $sdk;
        $this->config = $config;
        $this->handlePayment = $handlePayment;
        $this->connect = $connect;
        $this->requestToPay = $requestToPay;
        $this->orderRepository = $orderRepository;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    protected function getOrder(): ?Order
    {
        $orderId = $this->checkoutSession->getLastRealOrderId();

        if (!isset($orderId)) {
            return null;
        }

        $order = $this->fintectureHelper->getOrderByIncrementId($orderId);

        return $order;
    }

    protected function restoreQuote(?Order $order): void
    {
        if ($order) {
            $this->handlePayment->fail($order);

            $this->checkoutSession->restoreQuote();
        }
    }

    /**
     * In case of error, restore cart and redirect user to checkout
     */
    protected function redirectToCheckoutWithError(string $status = 'cms_internal_error'): Redirect
    {
        $returnUrl = $this->fintectureHelper->getUrl('checkout') . '?status=' . $status . '#payment';

        $this->checkoutSession->restoreQuote();

        return $this->resultRedirect->create()->setPath($returnUrl);
    }
}
