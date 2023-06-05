<?php

namespace Fintecture\Payment\Gateway;

use Fintecture\Api\ApiResponse;
use Fintecture\Payment\Gateway\Config\Config;
use Fintecture\Payment\Gateway\Http\Sdk;
use Fintecture\Payment\Helper\Fintecture as FintectureHelper;
use Fintecture\Payment\Logger\Logger;
use Fintecture\Util\Crypto;
use Magento\Sales\Model\Order;

class RequestToPay
{
    /** @var FintectureHelper */
    protected $fintectureHelper;

    /** @var Logger */
    protected $fintectureLogger;

    /** @var Sdk */
    protected $sdk;

    /** @var Config */
    protected $config;

    public function __construct(
        FintectureHelper $fintectureHelper,
        Logger $fintectureLogger,
        Sdk $sdk,
        Config $config
    ) {
        $this->fintectureHelper = $fintectureHelper;
        $this->fintectureLogger = $fintectureLogger;
        $this->sdk = $sdk;
        $this->config = $config;
    }

    public function get(Order $order, array $data): ApiResponse
    {
        /** @phpstan-ignore-next-line */
        $pisToken = $this->sdk->pisClient->token->generate();
        if (!$pisToken->error) {
            $this->sdk->pisClient->setAccessToken($pisToken); // set token of PIS client
        } else {
            throw new \Exception($pisToken->errorMsg);
        }

        $state = Crypto::encodeToBase64(['order_id' => $order->getIncrementId()]);

        /** @phpstan-ignore-next-line */
        $apiResponse = $this->sdk->pisClient->requestToPay->generate($data, 'fr', null, $state);

        if ($apiResponse->error) {
            $this->fintectureLogger->error('RequestToPay session', [
                'message' => 'Error building RTP URL',
                'orderIncrementId' => $order->getIncrementId(),
                'response' => $apiResponse->errorMsg,
            ]);
            throw new \Exception($apiResponse->errorMsg);
        }

        return $apiResponse;
    }
}
