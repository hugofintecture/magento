<?php

declare(strict_types=1);

namespace Fintecture\Payment\Model\Config\File;

use Magento\Framework\Encryption\EncryptorInterface;

class PrivateKeyPem extends \Magento\Config\Model\Config\Backend\File
{
    protected EncryptorInterface $encryptor;

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
                        throw new \Exception("Can't read the private key file");
                    }
                }
            } catch (\Exception $e) {
                throw new \Magento\Framework\Exception\LocalizedException(__('%1', $e->getMessage()));
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
