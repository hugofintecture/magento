<?php

declare(strict_types=1);

namespace Fintecture\Payment\Gateway\Http;

use Fintecture\Payment\Gateway\Config\Config;
use Fintecture\Payment\Logger\Logger;
use Fintecture\PisClient;
use Magento\Framework\Encryption\EncryptorInterface;
use Symfony\Component\HttpClient\Psr18Client;

class Sdk
{
    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $fintectureLogger;

    /** @var PisClient */
    public $pisClient;

    /** @var EncryptorInterface */
    protected $encryptor;

    public function __construct(
        Config $config,
        Logger $fintectureLogger,
        EncryptorInterface $encryptor
    ) {
        $this->config = $config;
        $this->fintectureLogger = $fintectureLogger;
        $this->encryptor = $encryptor;

        if ($this->validateConfigValue()) {
            try {
                $privateKey = null;
                $encryptedPrivateKey = $this->config->getAppPrivateKey();
                if ($encryptedPrivateKey) {
                    $privateKey = $this->encryptor->decrypt($encryptedPrivateKey);
                }

                $this->pisClient = new PisClient([
                    'appId' => $this->config->getAppId(),
                    'appSecret' => $this->config->getAppSecret(),
                    'privateKey' => $privateKey,
                    'environment' => $this->config->getAppEnvironment(),
                ], new Psr18Client());
            } catch (\Exception $e) {
                $this->fintectureLogger->error('Connection', [
                    'exception' => $e,
                    'message' => "Can't create PISClient",
                ]);
            }
        }
    }

    public function isPisClientInstantiated(): bool
    {
        return $this->pisClient instanceof PisClient;
    }

    public function validateConfigValue(): bool
    {
        if (!$this->config->getAppEnvironment()
            || !$this->config->getAppPrivateKey()
            || !$this->config->getAppId()
            || !$this->config->getAppSecret()
        ) {
            return false;
        }

        return true;
    }
}
