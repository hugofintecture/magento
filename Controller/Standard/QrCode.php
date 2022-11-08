<?php

declare(strict_types=1);

namespace Fintecture\Payment\Controller\Standard;

use chillerlan\QRCode\QRCode as QRCodeGenerator;
use Fintecture\Payment\Controller\FintectureAbstract;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;

class QrCode extends FintectureAbstract
{
    public function execute()
    {
        $encodedUrl = $this->request->getParam('url');
        $reference = $this->request->getParam('reference');
        $amount = $this->request->getParam('amount');
        $currency = $this->request->getParam('currency');
        $sessionId = $this->request->getParam('session_id');
        $confirm = (int) $this->request->getParam('confirm');

        if (empty($encodedUrl)) {
            $this->fintectureLogger->error('QR Code error', ['message' => 'no URL provided']);
            throw new LocalizedException(__('QR Code error: no URL provided'));
        }

        $url = urldecode($encodedUrl);

        // chillerlan/php-qrcode is an optional dependency
        if (class_exists(QRCodeGenerator::class)) {
            $qrCode = (new QRCodeGenerator())->render($url);
        } else {
            $qrCode = '';
            $this->fintectureLogger->error('QR Code error', ['message' => 'chillerlan/php-qrcode dependency is not installed']);
        }

        $params = [
            'url' => $encodedUrl,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'session_id' => $sessionId,
            'confirm' => 1
        ];
        $confirmUrl = $this->paymentMethod->getQrCodeUrl() . '?' . http_build_query($params);

        $page = $this->pageFactory->create();

        /** @var Template $block */
        $block = $page->getLayout()->getBlock('fintecture_standard_qrcode');
        $block->setData('qrcode', $qrCode);
        $block->setData('reference', $reference);
        $block->setData('amount', $amount);
        $block->setData('currency', $currency);
        $block->setData('session_id', $sessionId);
        $block->setData('baseUrl', $this->urlInterface->getBaseUrl());
        $block->setData('confirmUrl', $confirmUrl);
        $block->setData('confirm', $confirm);

        return $page;
    }
}
