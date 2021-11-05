<?php

declare(strict_types=1);

namespace Fintecture\Payment\Block\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Button extends Field
{
    /** @var string */
    protected $_template = 'Fintecture_Payment::system/config/button.phtml';

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    public function getCustomUrl()
    {
        return $this->getUrl('fintecture/settings/ajax');
    }

    public function getButtonHtml()
    {
        $button = $this->getLayout()
                       ->createBlock('Magento\Backend\Block\Widget\Button')
                       ->setData(
                           [
                               'id'    => 'test-connection',
                               'label' => __('Test Connection')
                           ]
                       );

        return $button->toHtml();
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }
}
