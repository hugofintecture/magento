define([
    'Magento_Customer/js/model/address-list',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/action/create-shipping-address',
    'Magento_Checkout/js/action/select-shipping-address',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/model/payment-service',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Checkout/js/model/address-converter',
    'Magento_Checkout/js/action/select-billing-address',
    'Magento_Checkout/js/action/create-billing-address',
    'underscore'
], function (
    addressList,
    quote,
    checkoutData,
    createShippingAddress,
    selectShippingAddress,
    selectShippingMethodAction,
    paymentService,
    selectPaymentMethodAction,
    addressConverter,
    selectBillingAddress,
    createBillingAddress,
    _
) {
    'use strict';

    return {
        resolveEstimationAddress: function () {
            var address;

            if (checkoutData.getShippingAddressFromData()) {
                address = addressConverter.formAddressDataToQuoteAddress(checkoutData.getShippingAddressFromData());
                selectShippingAddress(address);
            } else {
                this.resolveShippingAddress();
            }

            if (quote.isVirtual()) {
                if (checkoutData.getBillingAddressFromData()) {
                    address = addressConverter.formAddressDataToQuoteAddress(
                        checkoutData.getBillingAddressFromData()
                    );
                    selectBillingAddress(address);
                } else {
                    this.resolveBillingAddress();
                }
            }
        },

        resolveShippingAddress: function () {
            var newCustomerShippingAddress;

            if (!checkoutData.getShippingAddressFromData() &&
                window.checkoutConfig.shippingAddressFromData
            ) {
                checkoutData.setShippingAddressFromData(window.checkoutConfig.shippingAddressFromData);
            }

            newCustomerShippingAddress = checkoutData.getNewCustomerShippingAddress();

            if (newCustomerShippingAddress) {
                createShippingAddress(newCustomerShippingAddress);
            }
            this.applyShippingAddress();
        },

        applyShippingAddress: function (isEstimatedAddress) {
            var address,
                shippingAddress,
                isConvertAddress,
                addressData,
                isShippingAddressInitialized;

            if (addressList().length === 0) {
                address = addressConverter.formAddressDataToQuoteAddress(
                    checkoutData.getShippingAddressFromData()
                );
                selectShippingAddress(address);
            }
            shippingAddress = quote.shippingAddress();
            isConvertAddress = isEstimatedAddress || false;

            if (!shippingAddress) {
                isShippingAddressInitialized = addressList.some(function (addressFromList) {
                    if (checkoutData.getSelectedShippingAddress() === addressFromList.getKey()) {
                        addressData = isConvertAddress ?
                            addressConverter.addressToEstimationAddress(addressFromList)
                            : addressFromList;
                        selectShippingAddress(addressData);
                        return true;
                    }
                    return false;
                });

                if (!isShippingAddressInitialized) {
                    isShippingAddressInitialized = addressList.some(function (addrs) {
                        if (addrs.isDefaultShipping()) {
                            addressData = isConvertAddress ?
                                addressConverter.addressToEstimationAddress(addrs)
                                : addrs;
                            selectShippingAddress(addressData);
                            return true;
                        }
                        return false;
                    });
                }

                if (!isShippingAddressInitialized && addressList().length === 1) {
                    addressData = isConvertAddress ?
                        addressConverter.addressToEstimationAddress(addressList()[0])
                        : addressList()[0];
                    selectShippingAddress(addressData);
                }
            }
        },

        resolveShippingRates: function (ratesData) {
            var selectedShippingRate = checkoutData.getSelectedShippingRate(),
                availableRate = false;

            if (ratesData.length === 1 && !quote.shippingMethod()) {
                //set shipping rate if we have only one available shipping rate
                selectShippingMethodAction(ratesData[0]);
                return;
            }

            if (quote.shippingMethod()) {
                availableRate = _.find(ratesData, function (rate) {
                    return rate['carrier_code'] === quote.shippingMethod()['carrier_code'] &&
                        rate['method_code'] === quote.shippingMethod()['method_code'];
                });
            }

            if (!availableRate && selectedShippingRate) {
                availableRate = _.find(ratesData, function (rate) {
                    return rate['carrier_code'] + '_' + rate['method_code'] === selectedShippingRate;
                });
            }

            if (!availableRate && window.checkoutConfig.selectedShippingMethod) {
                availableRate = _.find(ratesData, function (rate) {
                    var selectedShippingMethod = window.checkoutConfig.selectedShippingMethod;

                    return rate['carrier_code'] === selectedShippingMethod['carrier_code'] &&
                        rate['method_code'] === selectedShippingMethod['method_code'];
                });
            }

            if (!availableRate) {
                selectShippingMethodAction(null);
            } else {
                selectShippingMethodAction(availableRate);
            }
        },

        /**
         * Resolve payment method. Used local storage
         */
        resolvePaymentMethod: function () {
            var availablePaymentMethods = paymentService.getAvailablePaymentMethods(),
                selectedPaymentMethod = checkoutData.getSelectedPaymentMethod();

            if (selectedPaymentMethod) {
                availablePaymentMethods.some(function (payment) {
                    if (payment.method === selectedPaymentMethod) {
                        selectPaymentMethodAction(payment);
                    }
                });
            }
        },

        resolveBillingAddress: function () {
            var selectedBillingAddress,
                newCustomerBillingAddressData;

            if (!checkoutData.getBillingAddressFromData() &&
                window.checkoutConfig.billingAddressFromData
            ) {
                checkoutData.setBillingAddressFromData(window.checkoutConfig.billingAddressFromData);
            }

            selectedBillingAddress = checkoutData.getSelectedBillingAddress();
            newCustomerBillingAddressData = checkoutData.getNewCustomerBillingAddress();

            if (selectedBillingAddress) {
                if (selectedBillingAddress === 'new-customer-billing-address' && newCustomerBillingAddressData) {
                    selectBillingAddress(createBillingAddress(newCustomerBillingAddressData));
                } else {
                    addressList.some(function (address) {
                        if (selectedBillingAddress === address.getKey()) {
                            selectBillingAddress(address);
                        }
                    });
                }
            } else {
                this.applyBillingAddress();
            }
        },

        applyBillingAddress: function () {
            var shippingAddress,
                isBillingAddressInitialized;

            if (quote.billingAddress()) {
                selectBillingAddress(quote.billingAddress());
                return;
            }

            if (quote.isVirtual() || !quote.billingAddress()) {
                isBillingAddressInitialized = addressList.some(function (addrs) {
                    if (addrs.isDefaultBilling()) {
                        selectBillingAddress(addrs);
                        return true;
                    }
                    return true;
                });
            }

            shippingAddress = quote.shippingAddress();

            if (!isBillingAddressInitialized &&
                shippingAddress &&
                shippingAddress.canUseForBilling() &&
                (shippingAddress.isDefaultShipping() || !quote.isVirtual())
            ) {
                selectBillingAddress(quote.shippingAddress());
            }
        }
    };
});
