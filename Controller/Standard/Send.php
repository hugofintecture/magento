<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller\Standard;

use chillerlan\QRCode\QRCode as QRCodeGenerator;
use Fintecture\Payment\Controller\FintectureAbstract;
use Fintecture\Util\Crypto;
use Magento\Framework\View\Element\Template;

class Send extends FintectureAbstract
{
    public function execute()
    {
        if (!$this->paymentMethod->isPisClientInstantiated()) {
            throw new \Exception('PISClient not instantiated');
        }

        $step = (int) $this->request->getParam('step');
        $method = $this->request->getParam('method');
        $quoteId = (int) $this->request->getParam('quoteId');

        $qrCode = '';
        $reference = '';
        $amount = '';
        $currency = '';
        $sessionId = '';
        if ($step === 2) {
            // Call API RTP with method
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $this->quoteRepository->get($quoteId);
            $data = $this->paymentMethod->generatePayload($quote, 'REQUEST_TO_PAY', $method);

            /** @phpstan-ignore-next-line */
            $pisToken = $this->paymentMethod->pisClient->token->generate();
            if (!$pisToken->error) {
                $this->paymentMethod->pisClient->setAccessToken($pisToken); // set token of PIS client
            } else {
                throw new \Exception($pisToken->errorMsg);
            }

            $state = Crypto::encodeToBase64(['order_id' => $quote->getReservedOrderId()]);
            /** @phpstan-ignore-next-line */
            $apiResponse = $this->paymentMethod->pisClient->requestToPay->generate($data, 'fr', null, $state);
            if ($apiResponse->error) {
                $this->fintectureLogger->error('Connect session', [
                    'message' => 'Error building connect URL',
                    'reservedIncrementOrderId' => $quote->getReservedOrderId(),
                    'response' => $apiResponse->errorMsg,
                ]);
                $this->checkoutSession->restoreQuote();
                throw new \Exception($apiResponse->errorMsg);
            }

            $reference = $data['data']['attributes']['communication'];
            $amount = $data['data']['attributes']['amount'];
            $currency = $data['data']['attributes']['currency'];
            $sessionId = $apiResponse->meta->session_id ?? '';
            $url = $apiResponse->meta->url ?? '';

            // chillerlan/php-qrcode is an optional dependency
            if (class_exists(QRCodeGenerator::class)) {
                if (!empty($url)) {
                    $qrCode = (new QRCodeGenerator())->render($url);
                } else {
                    $this->fintectureLogger->error('QR Code', ['message' => 'URL is empty']);
                }
            } else {
                $this->fintectureLogger->error('QR Code', ['message' => 'chillerlan/php-qrcode dependency is not installed']);
            }
        }

        $sendUrl = $this->paymentMethod->getSendUrl() . '?step=2&method=%s&quoteId=' . $quoteId;

        $sendByEmailUrl = sprintf($sendUrl, 'email');
        $sendBySMSUrl = sprintf($sendUrl, 'sms');

        $page = $this->pageFactory->create();

        /** @var Template $block */
        $block = $page->getLayout()->getBlock('fintecture_standard_send');
        $block->setData('step', $step);
        $block->setData('sendByEmailUrl', $sendByEmailUrl);
        $block->setData('sendBySMSUrl', $sendBySMSUrl);
        $block->setData('qrCode', $qrCode);
        $block->setData('reference', $reference);
        $block->setData('amount', $amount);
        $block->setData('currency', $currency);
        $block->setData('sessionId', $sessionId);

        return $page;
    }
}
