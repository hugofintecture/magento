<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller;

use Fintecture\Payment\Helper\Fintecture as FintectureHelper;
use Fintecture\Payment\Logger\Logger as FintectureLogger;
use Fintecture\Payment\Model\Fintecture;
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

        $pkeyid = openssl_pkey_get_private($this->paymentMethod->getAppPrivateKey());
        if ($pkeyid === false) {
            return [false, 'Invalid private key'];
        }

        if (!isset($_SERVER['HTTP_DIGEST']) || !isset($_SERVER['HTTP_SIGNATURE'])) {
            return [false, 'Missing HTTP_DIGEST or HTTP_SIGNATURE'];
        }

        $digestBody = 'SHA-256=' . base64_encode(hash('sha256', $body, true));
        $digestHeader = stripslashes($_SERVER['HTTP_DIGEST']);

        $signature = stripslashes($_SERVER['HTTP_SIGNATURE']);
        $signature = str_replace('"', '', $signature);
        $signature = explode(',', $signature)[3] ?? ''; // 0: keyId, 1: algorithm, 2: headers, 3: signature
        $signature = explode('signature=', $signature)[1] ?? ''; // just keep the part after "signature="
        openssl_private_decrypt(base64_decode($signature), $decrypted, $pkeyid, OPENSSL_PKCS1_OAEP_PADDING);
        $signingString = preg_split('/\n|\r\n?/', $decrypted);
        $digestSignature = str_replace('"', '', substr($signingString[1] ?? '', 8)); // 0: date, 1: digest, 2: x-request-id

        // match the digest calculated from the received payload, the digest found in the headers and the digest uncoded from the signature
        $matchDigest = $digestBody === $digestSignature && $digestBody === $digestHeader;
        if (!$matchDigest) {
            return [false, 'Mismatching digest signatures'];
        }

        return [true, ''];
    }

    abstract public function execute();
}
