<?php

declare(strict_types=1);

namespace Fintecture\Payment\Gateway;

use DateTime;
use Fintecture\Payment\Helper\Fintecture as FintectureHelper;
use Fintecture\Payment\Logger\Logger as FintectureLogger;
use Magento\Framework\HTTP\Client\Curl;

class Client
{
    /** @var FintectureHelper */
    protected $fintectureHelper;

    /** @var FintectureLogger */
    protected $fintectureLogger;

    /** @var Curl */
    public $client;

    /** @var string */
    public $fintectureApiUrl;

    /** @var string */
    public $fintectureAppId;

    /** @var string */
    public $fintectureAppSecret;

    /** @var string */
    public $fintecturePrivateKey;

    /** @var array */
    public $curlOptions;

    public const STATS_URL = 'https://api.fintecture.com/ext/v1/activity';
    public const MAX_ATTEMPTS = 3;

    public function __construct($fintectureHelper, $fintectureLogger, $params)
    {
        $this->fintectureHelper = $fintectureHelper;
        $this->fintectureLogger = $fintectureLogger;
        $this->fintectureApiUrl = $params['fintectureApiUrl'];
        $this->fintectureAppId = $params['fintectureAppId'];
        $this->fintectureAppSecret = $params['fintectureAppSecret'];
        $this->fintecturePrivateKey = $params['fintecturePrivateKey'];
        $this->client = new Curl();
        $this->curlOptions = [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30
        ];
    }

    /**
     * @param array|string $params
     */
    private function post(string $uri, $params): void
    {
        $retry = true;
        $error = true;
        $attempts = 0;

        while ($retry && $attempts < self::MAX_ATTEMPTS) {
            $attempts++;

            try {
                $this->client->post($uri, $params);
            } catch (\Exception $e) {
                $this->fintectureLogger->warning('Failed curl attempt', [
                    'exception' => $e
                ]);
                continue; // go to next attempt
            }

            $error = false;
            $retry = false;
        }

        if ($error) {
            $this->fintectureLogger->error('Error', ['message' => 'All curl attempts failed']);
        }
    }

    public function logAction(string $action, array $systemInfos): bool
    {
        $headers = [
            'Content-Type' => 'application/json'
        ];

        $data = array_merge($systemInfos, ['action' => $action]);

        $this->client->setHeaders($headers);
        $this->client->setOptions($this->curlOptions);
        $this->post(self::STATS_URL, json_encode($data));

        return $this->client->getStatus() === 204;
    }

    public function testConnection(): string
    {
        $data = [
            'grant_type' => 'client_credentials',
            'app_id' => $this->fintectureAppId,
            'scope' => 'PIS',
        ];

        $basicToken = base64_encode($this->fintectureAppId . ':' . $this->fintectureAppSecret);
        $xRequestId = $this->getUid();
        $date = (new DateTime('now'))->format('r');

        $digest = 'SHA-256=' . base64_encode(hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true));
        $signingString = 'date: ' . $date . PHP_EOL . 'digest: ' . $digest . PHP_EOL . 'x-request-id: ' . $xRequestId;
        openssl_sign($signingString, $cryptedString, $this->fintecturePrivateKey, OPENSSL_ALGO_SHA256);
        $signature = 'keyId="' . $this->fintectureAppId . '",algorithm="rsa-sha256",headers="date digest x-request-id",signature="' . base64_encode($cryptedString) . '"';

        $headers = [
            'accept' => 'application/json',
            'cache-control' => 'no-cache',
            'content-type' => 'application/x-www-form-urlencoded',
            'app_id' => $this->fintectureAppId,
            'digest' => $digest,
            'date' => $date,
            'x-request-id' => $xRequestId,
            'signature' => $signature,
            'authorization' => 'Basic ' . $basicToken,
        ];

        $this->client->setHeaders($headers);
        $this->client->setOptions($this->curlOptions);
        $this->post($this->fintectureApiUrl . 'oauth/secure/accesstoken', http_build_query($data));
        $response = $this->client->getBody();

        $responseObject = $this->fintectureHelper->decodeJson($response);
        if ($responseObject && isset($responseObject['access_token'])) {
            return $responseObject['access_token'];
        }
        return '';
    }

    public function getUid(): string
    {
        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function generateConnectURL(
        array $data,
        bool $isRewriteModeActive,
        string $redirectUrl,
        string $originUrl,
        string $psuType,
        string $state = ''
    ): array {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [];
        }

        $xRequestId = $this->getUid();
        $date = (new DateTime('now'))->format('r');

        $digest = 'SHA-256=' . base64_encode(hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true));
        $signingString = 'date: ' . $date . PHP_EOL . 'digest: ' . $digest . PHP_EOL . 'x-request-id: ' . $xRequestId;
        openssl_sign($signingString, $cryptedString, $this->fintecturePrivateKey, OPENSSL_ALGO_SHA256);
        $signature = 'keyId="' . $this->fintectureAppId . '",algorithm="rsa-sha256",headers="date digest x-request-id",signature="' . base64_encode($cryptedString) . '"';

        $url = $this->fintectureApiUrl . 'pis/v2/connect?state=' . $state . '&origin_uri=' . $originUrl;

        if ($isRewriteModeActive) {
            $url .= '&redirect_uri=' . $redirectUrl;
        }

        $headers = [
            'accept' => ' application/json',
            'authorization' => 'Bearer ' . $accessToken,
            'cache-control' => 'no-cache',
            'content-type' => 'application/json',
            'app_id' => $this->fintectureAppId,
            'digest' => $digest,
            'date' => $date,
            'x-request-id' => $xRequestId,
            'x-psu-type' => $psuType,
            'signature' => $signature,
        ];

        $this->client->setHeaders($headers);
        $this->client->setOptions($this->curlOptions);
        $this->post($url, json_encode($data));
        $response = $this->client->getBody();

        $responseObject = $this->fintectureHelper->decodeJson($response);
        return $responseObject ?? [];
    }

    /**
     * @return string|false
     */
    public function getAccessToken()
    {
        $data = [
            'grant_type' => 'client_credentials',
            'app_id' => $this->fintectureAppId,
            'scope' => 'PIS',
        ];

        $basicToken = base64_encode($this->fintectureAppId . ':' . $this->fintectureAppSecret);
        $xRequestId = $this->getUid();
        $date = (new DateTime('now'))->format('r');

        $digest = 'SHA-256=' . base64_encode(hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true));
        $signingString = 'date: ' . $date . PHP_EOL . 'digest: ' . $digest . PHP_EOL . 'x-request-id: ' . $xRequestId;
        openssl_sign($signingString, $cryptedString, $this->fintecturePrivateKey, OPENSSL_ALGO_SHA256);
        $signature = 'keyId="' . $this->fintectureAppId . '",algorithm="rsa-sha256",headers="date digest x-request-id",signature="' . base64_encode($cryptedString) . '"';

        $headers = [
            'accept' => 'application/json',
            'cache-control' => 'no-cache',
            'content-type' => 'application/x-www-form-urlencoded',
            'app_id' => $this->fintectureAppId,
            'digest' => $digest,
            'date' => $date,
            'x-request-id' => $xRequestId,
            'signature' => $signature,
            'authorization' => 'Basic ' . $basicToken,
        ];

        $this->client->setHeaders($headers);
        $this->client->setOptions($this->curlOptions);
        $this->post($this->fintectureApiUrl . '/oauth/accesstoken', http_build_query($data));
        $response = $this->client->getBody();

        $responseObject = $this->fintectureHelper->decodeJson($response);
        if ($responseObject && isset($responseObject['access_token'])) {
            return $responseObject['access_token'];
        }

        $this->fintectureLogger->error('Error', [
            'message' => "Can't generate an access token"
        ]);
        return false;
    }

    public function getPayment($sessionId): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [];
        }

        $data = [];
        $xRequestId = $this->getUid();
        $date = (new DateTime('now'))->format('r');

        $digest = 'SHA-256=' . base64_encode(hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true));
        $signingString = 'date: ' . $date . PHP_EOL . 'digest: ' . $digest . PHP_EOL . 'x-request-id: ' . $xRequestId;
        openssl_sign($signingString, $cryptedString, $this->fintecturePrivateKey, OPENSSL_ALGO_SHA256);
        $signature = 'keyId="' . $this->fintectureAppId . '",algorithm="rsa-sha256",headers="date digest x-request-id",signature="' . base64_encode($cryptedString) . '"';

        $headers = [
            'accept' => 'application/json',
            'cache-control' => 'no-cache',
            'content-type' => 'application/json',
            'app_id' => $this->fintectureAppId,
            'digest' => $digest,
            'date' => $date,
            'x-request-id' => $xRequestId,
            'signature' => $signature,
            'authorization' => 'Bearer ' . $accessToken,
        ];

        $this->client->setHeaders($headers);
        $this->client->setOptions($this->curlOptions);
        $this->client->get($this->fintectureApiUrl . '/pis/v2/payments/' . $sessionId);
        $response = $this->client->getBody();

        $responseObject = $this->fintectureHelper->decodeJson($response);
        return $responseObject ?? [];
    }
}
