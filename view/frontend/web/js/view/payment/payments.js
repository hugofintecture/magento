define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, renderList) {
    'use strict';
    renderList.push(
        {
            type: 'fintecture',
            component: 'Fintecture_Payment/js/view/payment/method-renderer/fintecture-payment',
        },
        {
            type: 'fintecture_bnpl',
            component: 'Fintecture_Payment/js/view/payment/method-renderer/fintecture-bnpl',
        }
    );
    return Component.extend({});
});
