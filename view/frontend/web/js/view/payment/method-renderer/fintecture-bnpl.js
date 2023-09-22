define([
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'mage/url',
], function (
    Component,
    quote,
    url
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Fintecture_Payment/payment/fintecture-bnpl'
        },
        redirectAfterPlaceOrder: false,
        afterPlaceOrder: function () {
            const afterPlaceUrl = url.build('fintecture/checkout/bnpl');
            window.location.replace(afterPlaceUrl);
        },
        getTodayDate: function () {
            return window.checkoutConfig.payment.fintecture_bnpl.todayDate;
        },
        getLaterDate: function () {
            return window.checkoutConfig.payment.fintecture_bnpl.laterDate;
        },
        getZero: function () {
            const amount = 0;
            const format = window.checkoutConfig.priceFormat.pattern;

            return format.replace(/%s/g, amount);
        },
        getGrandTotal: function () {
            const amount = quote.getTotals()()['base_grand_total'];
            const format = window.checkoutConfig.priceFormat.pattern;

            return format.replace(/%s/g, amount);
        },
        isRecommendedBnplBadgeActive: function () {
            return window.checkoutConfig.payment.fintecture_bnpl.recommendBnplBadge;
        },
        forcePosition: function() {
            var totalCartAmount = quote.getTotals()()['base_grand_total'];
            var firstPositionActive = window.checkoutConfig.payment.fintecture.firstPositionActive;
            var firstPositionAmount = window.checkoutConfig.payment.fintecture.firstPositionAmount;
            
            if (firstPositionActive) {
                if (totalCartAmount > firstPositionAmount) {
                    var paymentModuleIT = document.getElementById('fintecture-it');
                    var paymentModuleBNPL = document.getElementById('fintecture-bnpl');
                    var paymentMethodsList = document.querySelector('.payment-group .step-title');
    
                    if (paymentModuleIT && paymentModuleBNPL && paymentMethodsList) {
                        paymentModuleIT.after(paymentModuleBNPL);
                    } else if (paymentModuleBNPL && paymentMethodsList) {
                        paymentMethodsList.after(paymentModuleBNPL);
                    }
                }
            }
        }
    });
});
