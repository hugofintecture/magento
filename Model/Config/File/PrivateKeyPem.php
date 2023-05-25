<?php

declare(strict_types=1);

namespace Fintecture\Payment\Model\Config\File;

use Fintecture\Payment\Logger\Logger;
use Magento\Framework\Encryption\EncryptorInterface;

class PrivateKeyPem extends \Magento\Config\Model\Config\Backend\File
{
    /** @var EncryptorInterface */
    protected $encryptor;

    /** @var Logger */
    protected $fintectureLogger;

    /** @phpstan-ignore-next-line : ignore error for deprecated registry (Magento side) */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\MediaStorage\Model\File\UploaderFactory $uploaderFactory,
        \Magento\Config\Model\Config\Backend\File\RequestData\RequestDataInterface $requestData,
        \Magento\Framework\Filesystem $filesystem,
        EncryptorInterface $encryptor,
        Logger $fintectureLogger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $uploaderFactory,
            $requestData,
            $filesystem,
            $resource,
            $resourceCollection,
            $data
        );

        $this->encryptor = $encryptor;
        $this->fintectureLogger = $fintectureLogger;
    }

    public function beforeSave()
    {
        $value = $this->getValue();
        $file = $this->getFileData();
        if (!empty($file)) {
            try {
                $uploader = $this->_uploaderFactory->create(['fileId' => $file]);
                $uploader->setAllowedExtensions($this->_getAllowedExtensions());
                $uploader->setAllowRenameFiles(true);
                $uploader->addValidateCallback('size', $this, 'validateMaxSize');
                if ($uploader->validateFile()) {
                    $privateKey = file_get_contents($value['tmp_name']);
                    if ($privateKey) {
                        $this->setValue($this->encryptor->encrypt($privateKey));
                    } else {
                        $this->fintectureLogger->error('Private Key', [
                            'message' => "Can't read the private key file",
                        ]);
                        throw new \Exception("Can't read the private key file");
                    }
                }
            } catch (\Exception $e) {
                $this->fintectureLogger->error('Private Key', [
                    'message' => "Can't save the private key",
                    'exception' => $e,
                ]);

                throw new \Magento\Framework\Exception\LocalizedException(__("Can't save the private key"));
            }
        } else {
            $this->unsValue();
        }

        return $this;
    }

    public function _getAllowedExtensions()
    {
        return ['pem'];
    }
}
