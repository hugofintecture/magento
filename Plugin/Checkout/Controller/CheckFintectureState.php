<?php

declare(strict_types=1);

namespace Fintecture\Payment\Plugin\Checkout\Controller;

use Fintecture\Payment\Helper\Fintecture;
use Fintecture\Payment\Model\Fintecture as FintectureModel;
use Fintecture\Payment\Model\Order;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;

class CheckFintectureState
{
    /** @var Session */
    protected $checkoutSession;

    /** @var Fintecture */
    protected $checkoutHelper;

    /** @var RedirectInterface */
    protected $redirect;

    /** @var StoreManagerInterface */
    protected $storeManager;

    public function __construct(
        Session $checkoutSession,
        StoreManagerInterface $storeManager,
        Validator $formKeyValidator,
        Cart $cart,
        PageFactory $resultPageFactory,
        Fintecture $checkoutHelper,
        Order $fintectureOrder,
        RedirectInterface $redirect
    ) {
        $this->storeManager    = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->checkoutHelper  = $checkoutHelper;
        $this->fintectureOrder = $fintectureOrder;
        $this->redirect        = $redirect;
    }

    public function beforeExecute($subject)
    {
        $quote         = $this->checkoutSession->getQuote();
        $paymentMethod = $quote->getPayment()->getMethod();
        $order         = $this->getOrder();
        if (null === $paymentMethod // payment type not available on bank site
            && 'created' === $this->checkoutSession->getFintectureState()
            && 'pending' === $order->getStatus()
        ) {
            $this->checkoutSession->restoreQuote();
            $returnUrl = $this->checkoutHelper->getUrl('checkout') . "#payment";
            $this->checkoutSession->setFintectureState(null);
            $subject->getResponse()->setRedirect($returnUrl);
        } elseif (FintectureModel::PAYMENT_FINTECTURE_CODE === $paymentMethod
            && 'created' === $this->checkoutSession->getFintectureState()
            && 'pending' === $order->getStatus()
        ) {
            $this->checkoutSession->restoreQuote();
            $returnUrl = $this->checkoutHelper->getUrl('checkout') . "#payment";
            $this->checkoutSession->setFintectureState(null);
            $subject->getResponse()
                    ->setRedirect($returnUrl);
        } elseif (FintectureModel::PAYMENT_FINTECTURE_CODE === $paymentMethod
            && 'created' === $this->checkoutSession->getFintectureState()
            && 'pending' !== $order->getStatus()
        ) {
            $this->checkoutSession->clearStorage();
            $this->checkoutSession->setFintectureState(null);
        }
    }

    protected function getOrder(): Order
    {
        return $this->fintectureOrder->loadByIncrementId(
            $this->checkoutSession->getLastRealOrderId()
        );
    }
}
