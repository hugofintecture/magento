<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller;

use Fintecture\Payment\Helper\Fintecture as FintectureHelper;
use Fintecture\Payment\Logger\Logger as FintectureLogger;
use Fintecture\Payment\Model\Fintecture;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use function base64_decode;
use function base64_encode;
use function explode;
use function hash;
use function openssl_private_decrypt;
use function str_replace;
use const OPENSSL_PKCS1_OAEP_PADDING;

abstract class WebhookAbstract extends Action
{
    /** @var OrderFactory */
    protected $_orderFactory;

    /** @var FintectureLogger $fintectureLogger */
    protected $fintectureLogger;

    /** @var Fintecture */
    protected $_paymentMethod;

    /** @var FintectureHelper $_checkoutHelper */
    protected $_checkoutHelper;

    /** @var JsonFactory */
    protected $resultJsonFactory;

    /** @var CollectionFactory */
    protected $orderCollectionFactory;

    public function __construct(
        Context $context,
        OrderFactory $orderFactory,
        FintectureLogger $finlogger,
        Fintecture $paymentMethod,
        FintectureHelper $checkoutHelper,
        JsonFactory $resultJsonFactory,
        CollectionFactory $orderCollectionFactory
    ) {
        $this->_orderFactory          = $orderFactory;
        $this->_paymentMethod         = $paymentMethod;
        $this->_checkoutHelper        = $checkoutHelper;
        $this->resultJsonFactory      = $resultJsonFactory;
        $this->fintectureLogger       = $finlogger;
        $this->orderCollectionFactory = $orderCollectionFactory;
        parent::__construct($context);
    }

    /*
     * session_id=b2bca2bcd3b64a32a7da0766df59a7d2
     * &status=payment_created
     * &customer_id=1ef74051a77673de120820fb370dc382
     * &provider=provider
     * &state=thisisastate
     */
    public function validateWebhook()
    {
        $body = file_get_contents('php://input');
        parse_str($body, $data);
        $this->fintectureLogger->debug('Validating Webhook', $data);

        $response = $this->getResponse();

        if (!count($data)) {
            $this->fintectureLogger->debug('Empty hook data');
            $response->setStatusHeader(406, '1.1', 'Empty hook data');

            return false;
        }

        $pkeyid = openssl_pkey_get_private($this->_paymentMethod->getAppPrivateKey());

        if ($pkeyid === false) {
            $this->fintectureLogger->debug('Invalid private key');
            $response->setStatusHeader(401, '1.1', 'Invalid private key');

            return false;
        }

        if (!isset($_SERVER['HTTP_DIGEST'], $_SERVER['HTTP_SIGNATURE'])) {
            $this->fintectureLogger->debug('Missing HTTP_DIGEST or HTTP_SIGNATURE', [$_SERVER]);
            $response->setStatusHeader(400, '1.1', 'Missing HTTP_DIGEST or HTTP_SIGNATURE');

            return false;
        }

        $digestBody   = 'SHA-256=' . base64_encode(hash('sha256', $body, true));
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
            $this->fintectureLogger->debug('Mismatching digest signatures');
            $response->setStatusHeader(410, '1.1', 'Mismatching digest signatures');

            return false;
        }

        return true;
    }

    abstract public function execute();
}
