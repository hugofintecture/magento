<?php
declare(strict_types=1);

namespace Fintecture\Payment\Block;

use Magento\Framework\View\Element\Template;

class PopulateFpx extends Template
{
    public function getFpxConfig()
    {
        $output                    = [];
        $params                    = array_merge(['_secure' => $this->_request->isSecure()], []);
        $imageUrl                  = $this->_assetRepo->getUrlWithParams('Fintecture_Payment::images/download.jpeg', $params);
        $output['fpxLogoImageUrl'] = $imageUrl;

        return $output;
    }
}
