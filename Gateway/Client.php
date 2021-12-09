<?php

declare(strict_types=1);

namespace Fintecture\Payment\Gateway;

use function base64_encode;
use function chr;
use const CURLOPT_HTTP_VERSION;
use const CURLOPT_MAXREDIRS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT;
use DateTime;
use function hash;
use function http_build_query;
use function json_decode;
use function json_encode;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use Magento\Framework\HTTP\Client\Curl;
use const OPENSSL_ALGO_SHA256;
use function openssl_random_pseudo_bytes;
use function openssl_sign;
use const PHP_EOL;
use function str_split;
use function vsprintf;

class Client
{
    public $client;
    public $fintectureApiUrl;
    public $fintectureAppId;
    public $fintectureAppSecret;
    public $fintecturePrivateKey;
    public $curlOptions;

    const STATS_URL = 'https://api.fintecture.com/ext/v1/activity';

    public function __construct($params)
    {
        $this->fintectureApiUrl = $params['fintectureApiUrl'];
        $this->fintectureAppId = $params['fintectureAppId'];
        $this->fintectureAppSecret = $params['fintectureAppSecret'];
        $this->fintecturePrivateKey = $params['fintecturePrivateKey'];
        $this->client = new Curl();
        $this->curlOptions = [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        ];
    }

    public function logAction(string $action, array $systemInfos): bool
    {
        $headers = [
            'Content-Type' => 'application/json'
        ];

        $data = array_merge($systemInfos, ['action' => $action]);

        $this->client->setHeaders($headers);
        $this->client->setOptions($this->curlOptions);
        $this->client->post(self::STATS_URL, json_encode($data));

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
        $this->client->post($this->fintectureApiUrl . 'oauth/secure/accesstoken', http_build_query($data));
        $response = $this->client->getBody();

        $responseObject = json_decode($response, true);
        return $responseObject['access_token'] ?? '';
    }

    public function getUid(): string
    {
        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function generateConnectURL($data, bool $isRewriteModeActive, string $redirectUrl, string $originUrl, string $psuType, string $state = '')
    {
        $accessToken = $this->getAccessToken();
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
        $this->client->post($url, json_encode($data));
        $response = $this->client->getBody();

        return json_decode($response, true) ?? [];
    }

    public function getAccessToken(): string
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
        $this->client->post($this->fintectureApiUrl . '/oauth/accesstoken', http_build_query($data));
        $response = $this->client->getBody();

        $responseObject = json_decode($response, true);
        return $responseObject['access_token'] ?? '';
    }

    public function getPayment($sessionId)
    {
        $accessToken = $this->getAccessToken();

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

        return json_decode($response, true) ?? [];
    }
}
