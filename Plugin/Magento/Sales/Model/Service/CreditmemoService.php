<?php

declare(strict_types=1);

namespace Fintecture\Payment\Plugin\Magento\Sales\Model\Service;

use Exception;
use Fintecture\Payment\Model\Action\Refund\CreateRefund;
use Fintecture\Payment\Model\Fintecture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Service\CreditmemoService as MagentoCreditmemoService;

class CreditmemoService
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CreateRefund
     */
    private $createRefundAction;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CreateRefund $createRefundAction,
        ResourceConnection $resourceConnection
    ) {
        $this->orderRepository = $orderRepository;
        $this->createRefundAction = $createRefundAction;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * A refund in Fintecture is actually a request for a refund.
     *
     * @param MagentoCreditmemoService $subject
     * @param callable $proceed
     * @param CreditmemoInterface $creditmemo
     * @param bool $offlineRequested
     * @return CreditmemoInterface
     * @throws LocalizedException
     * @link MagentoCreditmemoService::refund()
     */
    public function aroundRefund(
        MagentoCreditmemoService $subject,
        callable $proceed,
        CreditmemoInterface $creditmemo,
        $offlineRequested = false
    ) {
        if (!$this->isOrderPaidWithFintecture((int) $creditmemo->getOrderId()) || $offlineRequested) {
            return $proceed($creditmemo, $offlineRequested);
        }

        // Wrap in transaction, just like the original refund()-method:
        $connection = $this->resourceConnection->getConnection('sales');
        $connection->beginTransaction();
        try {
            $this->createRefundAction->process($creditmemo);
            $connection->commit();
        } catch (Exception $exception) {
            $connection->rollBack();
            throw new LocalizedException(__($exception->getMessage()));
        }

        return $creditmemo;
    }

    private function isOrderPaidWithFintecture(int $orderId): bool
    {
        $order = $this->orderRepository->get($orderId);
        return $order->getPayment()->getMethod() === Fintecture::CODE;
    }
}
