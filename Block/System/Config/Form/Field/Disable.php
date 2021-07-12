<?php
declare(strict_types=1);

namespace Fintecture\Payment\Block\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Disable extends Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $html = '<script type="text/javascript">
            require(["jquery", "jquery/ui"], function (jQuery) {
                jQuery(document).ready(function () {
                    jQuery("#payment_us_fintecture_general_fintecture_pis_url_sandbox").prop("disabled", true);
                });
            });
            </script>';
        return $html;
    }
}
