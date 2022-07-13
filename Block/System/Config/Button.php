<?php

declare(strict_types=1);

namespace Fintecture\Payment\Block\System\Config;

use Magento\Backend\Block\Widget\Button as WidgetButton;
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
        /** @var WidgetButton $button */
        $button = $this->getLayout()->createBlock(WidgetButton::class);
        $button->setData([
            'id' => 'test-connection',
            'label' => __('Test Connection')
        ]);

        return $button->toHtml();
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }
}
