define(['Magento_Checkout/js/view/payment/default', 'Fintecture_Payment/js/action/set-payment-method',], function (Component, setPaymentMethod) {
        'use strict';
        return Component.extend(
            {
                defaults: {
                    'template': 'Fintecture_Payment/payment/fintecture'
                },
                redirectAfterPlaceOrder: false,
                afterPlaceOrder: function () {
                    setPaymentMethod();
                }

            }
        );
    }
);
