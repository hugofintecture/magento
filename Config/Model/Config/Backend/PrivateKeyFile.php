<?php

declare(strict_types=1);

namespace Fintecture\Payment\Config\Model\Config\Backend;

use Exception;
use Magento\Config\Model\Config\Backend\File;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\MediaStorage\Model\File\Uploader;

class PrivateKeyFile extends File
{
    public function beforeSave()
    {
        $value = $this->getValue();
        $file = $this->getFileData();

        if (!empty($file)) {
            if (!isset($file['name'])) {
                throw new LocalizedException(__('%1', 'Private Key file name was not specified'));
            }

            $uploadDir = $this->_getUploadDir();
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            try {
                /** @var Uploader $uploader */
                $uploader = $this->_uploaderFactory->create(['fileId' => $file]);
                $uploader->setAllowedExtensions($this->_getAllowedExtensions());
                $uploader->setAllowRenameFiles(false);
                $uploader->addValidateCallback('size', $this, 'validateMaxSize');
                $result = $uploader->save($uploadDir);
            } catch (Exception $e) {
                throw new LocalizedException(__('%1', $e->getMessage()));
            }
            $filename = $result['file'];
            if (($filename)) {
                if ($this->_addWhetherScopeInfo()) {
                    $filename = $this->_prependScopeInfo($filename);
                }
                $this->setValue($filename);
            }
        } else {
            if (is_array($value) && !empty($value['value'])) {
                $this->setValue($value['value']);
            } else {
                $this->unsValue();
            }
        }

        return $this;
    }

    public function _getAllowedExtensions()
    {
        return ['pem'];
    }

    public function getUploadDirPath($uploadDir)
    {
        $configReader = (ObjectManager::getInstance())->create('Magento\Framework\Module\Dir\Reader');
        return $configReader->getModuleDir('etc', 'Fintecture_Payment') . DIRECTORY_SEPARATOR . $uploadDir;
    }
}
