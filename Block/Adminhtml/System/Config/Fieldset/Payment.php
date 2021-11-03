<?php

declare(strict_types=1);

namespace Fintecture\Payment\Block\Adminhtml\System\Config\Fieldset;

use Magento\Config\Block\System\Config\Form\Fieldset;

class Payment extends Fieldset
{
    public function _getFrontendClass($element)
    {
        $enabledString = $this->_isPaymentEnabled($element) ? ' enabled' : '';
        return parent::_getFrontendClass($element) . ' with-button' . $enabledString;
    }

    public function _isPaymentEnabled($element)
    {
        $groupConfig = $element->getGroup();
        $activityPaths = $groupConfig['activity_path'] ?? [];

        foreach ($activityPaths as $activityPath) {
            if ($this->_backendConfig->getConfigDataValue($activityPath)) {
                return true;
            }
        }

        return false;
    }

    public function _getHeaderTitleHtml($element)
    {
        $isPaymentEnabled = $this->_isPaymentEnabled($element);
        $logo = $this->getViewFileUrl('Fintecture_Payment::images/logo.png');
        $disabledAttribute = $isPaymentEnabled ? '' : ' disabled="disabled"';
        $disabledClassName = $isPaymentEnabled ? '' : ' disabled';
        $htmlId = $element->getHtmlId();
        $groupConfig = $element->getGroup();
        $classes = ($groupConfig['checkout_com_separator'] ?? ' checkout-com-separator') . $disabledClassName;
        $moreLink = isset($groupConfig['demo_url']) ? '<a class="link-more" href="' . $groupConfig['more_url'] . '" target="_blank">' . __('Learn More') . '</a>' : '';
        $demoLink = isset($groupConfig['demo_url']) ? '<a class="link-demo" href="' . $groupConfig['demo_url'] . '" target="_blank">' . __('View Demo') . '</a>' : '';
        $state = $this->getUrl('adminhtml/*/state');

        $title = __('Instant bank payment');
        $description = __('Pay instantly and securely directly from your bank account. Collect payments without any credit limit. Reduce your transaction fees by 40% !');
        $configure = __('Configure');
        $close = __('Close');

        $html = '
        <div class="config-heading fintecture_section">
            <div class="fintecture_logo">
                <img src="' . $logo . '" alt="Fintecture" title="Fintecture">
            </div>

            <div class="fintecture_title">
                <b>' . $title . '</b>
                <div class="fintecture_tagline">' . $description . '</div>
            </div>

            <div class="button-container">
            <button
            type="button"
            ' . $disabledAttribute . '
            class="button action-configure  ' . $classes . ' "
            id="' . $htmlId . '-head"
            onclick="ckoToggleSolution.call(this, \'' . $htmlId . '\', \'' . $state . '\'); return false;">
                <span class="state-closed">' . $configure . '</span>
                <span class="state-opened">' . $close . '</span>
            </button>

            ' . $demoLink . '
            ' . $moreLink . '

        </div>
        <div class="heading"><strong>' . $element->getLegend() . '</strong>

        <span class="heading-intro">' . $element->getComment() . '</span>

        <div class="config-alt"></div>
        </div>

        </div>
        ';

        return $html;
    }
}
