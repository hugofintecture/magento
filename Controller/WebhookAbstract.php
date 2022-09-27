<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller;

use Fintecture\Payment\Helper\Fintecture as FintectureHelper;
use Fintecture\Payment\Logger\Logger as FintectureLogger;
use Fintecture\Payment\Model\Fintecture;
use Fintecture\Util\Validation;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

abstract class WebhookAbstract implements ActionInterface
{
    /** @var FintectureLogger $fintectureLogger */
    protected $fintectureLogger;

    /** @var FintectureHelper */
    protected $fintectureHelper;

    /** @var Fintecture */
    protected $paymentMethod;

    /** @var JsonFactory */
    protected $resultJsonFactory;

    /** @var RawFactory */
    protected $resultRawFactory;

    /** @var ResultFactory */
    protected $resultFactory;

    /** @var CollectionFactory */
    protected $orderCollectionFactory;

    public function __construct(
        FintectureLogger $fintectureLogger,
        FintectureHelper $fintectureHelper,
        Fintecture $paymentMethod,
        JsonFactory $resultJsonFactory,
        RawFactory $resultRawFactory,
        CollectionFactory $orderCollectionFactory
    ) {
        $this->fintectureLogger = $fintectureLogger;
        $this->fintectureHelper = $fintectureHelper;
        $this->paymentMethod = $paymentMethod;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resultRawFactory = $resultRawFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * @param string|false $body
     * @return array{bool, string}
     *
     * session_id=b2bca2bcd3b64a32a7da0766df59a7d2
     * &status=payment_created
     * &customer_id=1ef74051a77673de120820fb370dc382
     * &provider=provider
     * &state=thisisastate
     */
    public function validateWebhook($body): array
    {
        if (!$body) {
            return [false, 'Empty hook data'];
        }

        if (!isset($_SERVER['HTTP_DIGEST']) || !isset($_SERVER['HTTP_SIGNATURE'])) {
            return [false, 'Missing HTTP_DIGEST or HTTP_SIGNATURE'];
        }

        $digest = $_SERVER['HTTP_DIGEST'];
        $signature = $_SERVER['HTTP_SIGNATURE'];

        return [Validation::validSignature($body, $digest, $signature), ''];
    }

    abstract public function execute();
}
