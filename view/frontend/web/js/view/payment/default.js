define(['jquery', 'Magento_Checkout/js/view/payment/default'], function ($, Parent) {
        'use strict';
        return Parent.extend(
            {
                redirectAfterPlaceOrder: false,
                afterPlaceOrder: function () {
                    console.log("after place order");
                }
            }
        );
    }
);
