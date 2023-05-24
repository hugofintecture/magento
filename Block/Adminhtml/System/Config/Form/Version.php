<?php

namespace Fintecture\Payment\Block\Adminhtml\System\Config\Form;

use Fintecture\Payment\Gateway\Config\Config;

class Version extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $output = '<div>';
        $output .= __('Module version:') . ' ' . Config::VERSION . '<br><br>';
        $output .= __('This section is intended for advanced users. Changing the settings may impact the proper functioning of your system.');
        $output .= '</div>';

        return '<div id="row_' . $element->getHtmlId() . '">' . $output . '</div>';
    }
}
