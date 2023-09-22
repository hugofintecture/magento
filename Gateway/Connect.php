<?php

namespace Fintecture\Payment\Gateway;

use Fintecture\Api\ApiResponse;
use Fintecture\Payment\Gateway\Config\Config;
use Fintecture\Payment\Gateway\Http\Sdk;
use Fintecture\Payment\Helper\Fintecture as FintectureHelper;
use Fintecture\Payment\Logger\Logger;
use Fintecture\Util\Crypto;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class Connect
{
    /** @var FintectureHelper */
    protected $fintectureHelper;

    /** @var Logger */
    protected $fintectureLogger;

    /** @var Sdk */
    protected $sdk;

    /** @var Config */
    protected $config;

    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    public function __construct(
        FintectureHelper $fintectureHelper,
        Logger $fintectureLogger,
        Sdk $sdk,
        Config $config,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->fintectureHelper = $fintectureHelper;
        $this->fintectureLogger = $fintectureLogger;
        $this->sdk = $sdk;
        $this->config = $config;
        $this->orderRepository = $orderRepository;
    }

    public function get(Order $order, array $data): ApiResponse
    {
        $pisToken = $this->sdk->pisClient->token->generate();
        if (!$pisToken->error) {
            $this->sdk->pisClient->setAccessToken($pisToken); // set token of PIS client
        } else {
            throw new \Exception($pisToken->errorMsg);
        }

        $state = Crypto::encodeToBase64(['order_id' => $order->getIncrementId()]);
        $redirectUrl = $this->fintectureHelper->getResponseUrl();
        $originUrl = $this->fintectureHelper->getOriginUrl();
        $psuType = $this->config->getBankType();
        if (!$psuType) {
            $psuType = 'all';
        }

        $apiResponse = $this->sdk->pisClient->connect->generate(
            $data,
            $state,
            $redirectUrl,
            $originUrl,
            null,
            null,
            [
                'x-psu-type' => $psuType,
            ]
        );

        if ($apiResponse->error) {
            $this->fintectureLogger->error('Connect session', [
                'message' => 'Error building Connect URL',
                'orderIncrementId' => $order->getIncrementId(),
                'response' => $apiResponse->errorMsg,
            ]);
            throw new \Exception($apiResponse->errorMsg);
        }

        $sessionId = $apiResponse->meta->session_id ?? '';
        $order->setExtOrderId($sessionId);
        $this->orderRepository->save($order);

        return $apiResponse;
    }
}
