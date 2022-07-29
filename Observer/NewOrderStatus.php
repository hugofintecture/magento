<?php

namespace Fintecture\Payment\Observer;

use Fintecture\Payment\Helper\Fintecture as FintectureHelper;
use Fintecture\Payment\Model\Fintecture;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class NewOrderStatus implements ObserverInterface
{
    /** @var FintectureHelper */
    private $fintectureHelper;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    public function __construct(
        FintectureHelper $fintectureHelper,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->fintectureHelper = $fintectureHelper;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();

        if ($order) {
            if ($order->getPayment()->getMethod() === Fintecture::CODE) {
                $order->setState(Order::STATE_NEW);
                $order->setStatus($this->fintectureHelper->getNewOrderStatus());
                $this->orderRepository->save($order);
            }
        }
    }
}
