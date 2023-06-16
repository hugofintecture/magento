<?php

namespace Fintecture\Payment\Observer\Sales;

use Fintecture\Payment\Gateway\Http\Sdk;
use Fintecture\Payment\Logger\Logger;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class OrderSaveAfter implements ObserverInterface
{
    /** @var Sdk */
    private $sdk;

    /** @var Logger */
    private $fintectureLogger;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    public function __construct(
        Sdk $sdk,
        Logger $fintectureLogger,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->sdk = $sdk;
        $this->fintectureLogger = $fintectureLogger;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Execute observer on order status change
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->sdk->isPisClientInstantiated()) {
            $this->fintectureLogger->error('Cancellation', [
                'message' => 'PISClient not instantiated',
            ]);

            return;
        }

        $order = $observer->getEvent()->getOrder();
        $state = $order->getState();
        $incrementOrderId = $order->getIncrementId();

        if ($state === Order::STATE_CANCELED) {
            $sessionId = $order->getExtOrderId();
            if (!$sessionId) {
                // no session id, so payment flow has not started, just return
                return;
            }

            $pisToken = $this->sdk->pisClient->token->generate();
            if (!$pisToken->error) {
                $this->sdk->pisClient->setAccessToken($pisToken); // set token of PIS client
            } else {
                $this->fintectureLogger->error('Cancellation', [
                    'message' => "Can't set access token",
                    'incrementOrderId' => $incrementOrderId,
                ]);

                return;
            }

            $data = [
                'meta' => [
                    'status' => 'payment_cancelled',
                    'origin' => 'cms',
                    'transfer_reason' => 'cancelled_order',
                ],
            ];

            try {
                $this->sdk->pisClient->payment->update($sessionId, $data);
                $this->orderRepository->save($order);
            } catch (\Exception $e) {
                $this->fintectureLogger->error('Cancellation', [
                    'exception' => $e,
                    'incrementOrderId' => $incrementOrderId,
                ]);

                return;
            }
        }
    }
}
