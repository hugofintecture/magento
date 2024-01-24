<?php

namespace Fintecture\Payment\Gateway;

use Fintecture\Payment\Gateway\Config\Config;
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
use Magento\Sales\Model\Order\CreditmemoFactory;
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

    /** @var CreditmemoFactory */
    protected $creditmemoFactory;

    /** @var RefundAdapterInterface */
    protected $refundAdapter;

    /** @var Sdk */
    protected $sdk;

    /** @var Config */
    protected $config;

    public function __construct(
        Logger $fintectureLogger,
        FintectureHelper $fintectureHelper,
        OrderRepositoryInterface $orderRepository,
        CreditmemoRepositoryInterface $creditmemoRepository,
        CreditmemoFactory $creditmemoFactory,
        RefundAdapterInterface $refundAdapter,
        Sdk $sdk,
        Config $config
    ) {
        $this->fintectureLogger = $fintectureLogger;
        $this->fintectureHelper = $fintectureHelper;
        $this->orderRepository = $orderRepository;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->refundAdapter = $refundAdapter;
        $this->sdk = $sdk;
        $this->config = $config;
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

                $pisToken = $this->sdk->pisClient->token->generate();
                if (!$pisToken->error) {
                    $this->sdk->pisClient->setAccessToken($pisToken); // set token of PIS client
                } else {
                    throw new \Exception($pisToken->errorMsg);
                }

                $apiResponse = $this->sdk->pisClient->refund->generate($data, $state);
                if (!$apiResponse->error) {
                    if (isset($apiResponse->meta->status) && $apiResponse->meta->status === 'refund_waiting') {
                        $comment = __('You must proceed to the refund directly from the Fintecture Console with this type of account.');
                    } else {
                        if ($order->canHold()) {
                            $order->hold();
                        }
                        $comment = __('The refund link has been send.');
                    }
                    $order->addCommentToStatusHistory($comment->render());
                    $this->orderRepository->save($order);

                    $this->fintectureLogger->info('Refund', [
                        'message' => $comment,
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

    public function apply(Order $order, Creditmemo $creditmemo, float $amount): bool
    {
        $refundedAmount = (float) $order->getData('fintecture_payment_refund_amount');
        $isFullRefund = ($amount === (float) $order->getBaseGrandTotal())
            || ($refundedAmount + $amount === (float) $order->getBaseGrandTotal());

        return $this->completeRefund($order, $creditmemo, $isFullRefund, $amount);
    }

    public function applyWithoutCreditmemo(Order $order, float $amount): bool
    {
        $refundedAmount = (float) $order->getData('fintecture_payment_refund_amount');
        $isFullRefund = ($amount === (float) $order->getBaseGrandTotal())
            || ($refundedAmount + $amount === (float) $order->getBaseGrandTotal());

        // Create a credit memo only for a full refund
        if ($isFullRefund) {
            $invoice = $this->fintectureHelper->getInvoiceByOrder($order);
            if (!$invoice) {
                $this->fintectureLogger->error('Apply refund', [
                    'message' => 'No invoice found',
                    'orderIncrementId' => $order->getIncrementId(),
                ]);

                return false;
            }
            $creditmemo = $this->creditmemoFactory->createByInvoice($invoice);

            return $this->apply($order, $creditmemo, $amount);
        }

        return $this->completeRefund($order, null, $isFullRefund, $amount);
    }

    private function completeRefund(Order $order, ?Creditmemo $creditmemo, bool $isFullRefund, float $amount): bool
    {
        try {
            if (!is_null($creditmemo)) {
                $creditmemo->setState(Creditmemo::STATE_REFUNDED);

                /** @var Order $order */
                $order = $this->refundAdapter->refund($creditmemo, $order);
            }

            if ($order->canUnhold()) {
                $order->unhold();
            }

            $order->addCommentToStatusHistory(__('The refund of %1€ has been made.', number_format($amount, 2, ',', ' '))->render());

            if ($this->config->isRefundStatusesActive()) {
                if (!$isFullRefund) {
                    // Partial refund
                    $order->setStatus($this->config->getPartialRefundStatus());
                }
            }

            $refundedAmount = (float) $order->getData('fintecture_payment_refund_amount');
            $order->setData('fintecture_payment_refund_amount', $refundedAmount + $amount);

            $this->orderRepository->save($order);

            if (!is_null($creditmemo)) {
                $this->creditmemoRepository->save($creditmemo);
            }

            $this->fintectureLogger->info('Refund completed', [
                'creditmemoId' => !is_null($creditmemo) ? $creditmemo->getTransactionId() : '',
                'orderIncrementId' => $order->getIncrementId(),
            ]);

            return true;
        } catch (\Exception $e) {
            $order->addCommentToStatusHistory(__('The refund has failed.')->render());
            $this->orderRepository->save($order);

            $this->fintectureLogger->error('Apply refund', [
                'message' => "Can't apply refund",
                'creditmemoId' => !is_null($creditmemo) ? $creditmemo->getTransactionId() : '',
                'orderIncrementId' => $order->getIncrementId(),
                'exception' => $e,
            ]);

            return false;
        }
    }
}
