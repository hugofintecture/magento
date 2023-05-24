<?php

namespace Fintecture\Payment\Gateway;

use Fintecture\Payment\Gateway\Http\Sdk;
use Fintecture\Payment\Helper\Fintecture as FintectureHelper;
use Fintecture\Payment\Logger\Logger;
use Fintecture\Util\Crypto;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\RefundAdapterInterface;

class HandleRefund
{
    private const REFUND_COMMUNICATION = 'REFUND FINTECTURE-';

    /** @var Logger */
    protected $fintectureLogger;

    /** @var FintectureHelper */
    protected $fintectureHelper;

    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    /** @var CreditmemoRepositoryInterface */
    protected $creditmemoRepository;

    /** @var RefundAdapterInterface */
    protected $refundAdapter;

    /** @var Sdk */
    protected $sdk;

    public function __construct(
        Logger $fintectureLogger,
        FintectureHelper $fintectureHelper,
        OrderRepositoryInterface $orderRepository,
        CreditmemoRepositoryInterface $creditmemoRepository,
        RefundAdapterInterface $refundAdapter,
        Sdk $sdk
    ) {
        $this->fintectureLogger = $fintectureLogger;
        $this->fintectureHelper = $fintectureHelper;
        $this->orderRepository = $orderRepository;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->refundAdapter = $refundAdapter;
        $this->sdk = $sdk;
    }

    public function create(OrderInterface $order, CreditmemoInterface $creditmemo): void
    {
        if (!$this->sdk->isPisClientInstantiated()) {
            throw new \Exception('PISClient not instantiated');
        }

        /** @var Order $order */
        $incrementOrderId = $order->getIncrementId();

        $sessionId = $this->fintectureHelper->getSessionIdByOrderId($order->getId());
        if (!$sessionId) {
            $this->fintectureLogger->error('Refund', [
                'message' => "Can't get session id of order",
                'incrementOrderId' => $incrementOrderId,
            ]);

            throw new \Exception("Can't get session id of order");
        }

        $creditmemos = $order->getCreditmemosCollection();
        if (!$creditmemos) {
            $this->fintectureLogger->error('Refund', [
                'message' => 'No creditmemos found',
                'incrementOrderId' => $incrementOrderId,
            ]);

            throw new \Exception('No creditmemos found');
        }
        $nbCreditmemos = $creditmemos->count() + 1;

        $amount = (float) $creditmemo->getBaseGrandTotal();
        if (!$amount) {
            $this->fintectureLogger->error('Refund', [
                'message' => 'No amount on creditmemo',
                'incrementOrderId' => $incrementOrderId,
            ]);

            throw new \Exception('No amount on creditmemo');
        }

        $this->fintectureLogger->info('Refund', [
            'message' => 'Refund started',
            'incrementOrderId' => $incrementOrderId,
            'amount' => $amount,
            'sessionId' => $sessionId,
        ]);

        $data = [
            'meta' => [
                'session_id' => $sessionId,
            ],
            'data' => [
                'attributes' => [
                    'amount' => (string) round($amount, 2),
                    'communication' => self::REFUND_COMMUNICATION . $incrementOrderId . '-' . $nbCreditmemos,
                ],
            ],
        ];

        try {
            $creditmemoTransactionId = $creditmemo->getTransactionId();
            if ($creditmemoTransactionId) {
                $state = Crypto::encodeToBase64([
                    'order_id' => $order->getIncrementId(),
                    'creditmemo_transaction_id' => $creditmemoTransactionId,
                ]);

                /** @phpstan-ignore-next-line */
                $pisToken = $this->sdk->pisClient->token->generate();
                if (!$pisToken->error) {
                    $this->sdk->pisClient->setAccessToken($pisToken); // set token of PIS client
                } else {
                    throw new \Exception($pisToken->errorMsg);
                }

                /** @phpstan-ignore-next-line */
                $apiResponse = $this->sdk->pisClient->refund->generate($data, $state);
                if (!$apiResponse->error) {
                    if ($order->canHold()) {
                        $order->hold();
                    }
                    $order->addCommentToStatusHistory(__('The refund link has been send.')->render());
                    $this->orderRepository->save($order);

                    $this->fintectureLogger->info('Refund', [
                        'message' => 'The refund link has been send',
                        'incrementOrderId' => $incrementOrderId,
                    ]);
                } else {
                    $this->fintectureLogger->error('Refund', [
                        'message' => 'Invalid API response',
                        'incrementOrderId' => $incrementOrderId,
                        'response' => $apiResponse->errorMsg,
                    ]);
                    throw new \Exception($apiResponse->errorMsg);
                }
            } else {
                $this->fintectureLogger->error('Refund', [
                    'message' => 'State of creditmemo if empty',
                    'incrementOrderId' => $incrementOrderId,
                ]);
            }
        } catch (\Exception $e) {
            $this->fintectureLogger->error('Refund', [
                'exception' => $e,
                'incrementOrderId' => $incrementOrderId,
            ]);
            throw new LocalizedException(
                __('Sorry, something went wrong. Please try again later.')
            );
        }
    }

    public function apply(OrderInterface $order, string $creditmemoTransactionId): bool
    {
        try {
            /** @var Order $order */
            $creditmemos = $order->getCreditmemosCollection();
            if (!$creditmemos) {
                throw new \Exception("Can't find any creditmemo on the order");
            }

            /** @var Creditmemo $creditmemo */
            $creditmemo = $creditmemos
                ->addFieldToFilter('transaction_id', $creditmemoTransactionId)
                ->getLastItem();
        } catch (\Exception $e) {
            $order->addCommentToStatusHistory(__('The refund has failed.')->render());
            $this->orderRepository->save($order);

            $this->fintectureLogger->error('Apply refund', [
                'message' => "Can't find credit memo associated to order",
                'creditmemoId' => $creditmemoTransactionId,
                'orderIncrementId' => $order->getIncrementId(),
                'exception' => $e,
            ]);

            return false;
        }

        try {
            $creditmemo->setState(Creditmemo::STATE_REFUNDED);

            /** @var Order $order */
            $order = $this->refundAdapter->refund($creditmemo, $creditmemo->getOrder(), true);

            if ($order->canUnhold()) {
                $order->unhold();
            }
            $order->addCommentToStatusHistory(__('The refund has been made.')->render());

            $this->orderRepository->save($order);
            $this->creditmemoRepository->save($creditmemo);

            $this->fintectureLogger->info('Refund completed', [
                'creditmemoId' => $creditmemoTransactionId,
                'orderIncrementId' => $order->getIncrementId(),
            ]);

            return true;
        } catch (\Exception $e) {
            $order->addCommentToStatusHistory(__('The refund has failed.')->render());
            $this->orderRepository->save($order);

            $this->fintectureLogger->error('Apply refund', [
                'message' => "Can't apply refund",
                'creditmemoId' => $creditmemoTransactionId,
                'orderIncrementId' => $order->getIncrementId(),
                'exception' => $e,
            ]);
        }

        return false;
    }
}
