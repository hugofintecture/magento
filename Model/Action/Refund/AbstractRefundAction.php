<?php

declare(strict_types=1);

namespace Fintecture\Payment\Model\Action\Refund;

use Fintecture\Payment\Gateway\Config\Config;
use Fintecture\Payment\Gateway\HandleRefund;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;

/**
 * Abstract class to handle creditmemo and order persistence for
 * refund actions
 */
abstract class AbstractRefundAction
{
    /** @var CreditmemoRepositoryInterface */
    private $creditmemoRepository;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var HandleRefund */
    protected $handleRefund;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CreditmemoRepositoryInterface $creditmemoRepository,
        HandleRefund $handleRefund
    ) {
        $this->orderRepository = $orderRepository;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->handleRefund = $handleRefund;
    }

    /**
     * @param CreditmemoInterface $creditmemo
     *
     * @return void
     *
     * @throws LocalizedException
     */
    public function process(CreditmemoInterface $creditmemo)
    {
        $order = $creditmemo instanceof Creditmemo
            ? $creditmemo->getOrder()
            : $this->orderRepository->get($creditmemo->getOrderId());

        $this->validatePaymentMethod($order);
        $this->performRefundAction($order, $creditmemo);
        $this->persist($order, $creditmemo);
    }

    /**
     * @param OrderInterface $order
     * @param CreditmemoInterface $creditmemo
     *
     * @return void
     *
     * @throws LocalizedException
     */
    abstract protected function performRefundAction(
        OrderInterface $order,
        CreditmemoInterface $creditmemo
    );

    protected function persist(OrderInterface $order, CreditmemoInterface $creditmemo): void
    {
        $this->creditmemoRepository->save($creditmemo);
        $this->orderRepository->save($order);
    }

    /**
     * @param OrderInterface $order
     *
     * @throws LocalizedException
     */
    private function validatePaymentMethod(OrderInterface $order): void
    {
        $payment = $order->getPayment();
        if ($payment && $payment->getMethod() !== Config::CODE) {
            throw new LocalizedException(__('Order is not paid with Fintecture'));
        }
    }
}
