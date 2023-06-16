define([
    'Magento_Checkout/js/view/payment/default',
    'mage/url',
], function (Component, url) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Fintecture_Payment/payment/fintecture'
        },
        redirectAfterPlaceOrder: false,
        afterPlaceOrder: function () {
            const afterPlaceUrl = url.build('fintecture/checkout/index');
            window.location.replace(afterPlaceUrl);
        },
        getCheckoutDesign: function () {
            return window.checkoutConfig.payment.fintecture.checkoutDesign;
        }
    });
});
