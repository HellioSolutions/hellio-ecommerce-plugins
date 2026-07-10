/**
 * Hellio Messaging for Magento 2.
 *
 * Checkout OTP UI component. Sends a code and verifies it through the
 * server-side endpoints. The API token never reaches the browser.
 */
define([
    'uiComponent',
    'ko',
    'jquery',
    'mage/url',
    'Magento_Customer/js/customer-data',
    'mage/translate'
], function (Component, ko, $, url, customerData, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Hellio_Sms/checkout-otp'
        },

        /**
         * @returns {Object}
         */
        initialize: function () {
            this._super();

            var otpConfig = (window.checkoutConfig && window.checkoutConfig.hellio_otp) || {};

            this.enabled = otpConfig.enabled === true;
            this.length = otpConfig.length || 6;
            this.phone = ko.observable('');
            this.code = ko.observable('');
            this.codeSent = ko.observable(false);
            this.verified = ko.observable(false);
            this.busy = ko.observable(false);
            this.message = ko.observable('');
            this.messageClass = ko.observable('');

            this.sendUrl = otpConfig.sendUrl || url.build('hellio_sms/otp/send');
            this.verifyUrl = otpConfig.verifyUrl || url.build('hellio_sms/otp/verify');

            return this;
        },

        /**
         * @returns {Boolean}
         */
        isVisible: function () {
            return this.enabled;
        },

        /**
         * @param {Object} data
         */
        setMessage: function (data) {
            this.message(data.message || '');
            this.messageClass(data.success ? 'message-success' : 'message-error');
        },

        /**
         * Ask the server to dispatch a code.
         */
        sendCode: function () {
            var self = this;

            if (self.busy() || !self.phone()) {
                self.setMessage({ success: false, message: $t('Please enter your phone number.') });
                return;
            }

            self.busy(true);
            self.message('');

            $.ajax({
                url: self.sendUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    phone: self.phone(),
                    form_key: $.mage.cookies.get('form_key')
                }
            }).done(function (response) {
                self.setMessage(response);
                if (response.success) {
                    self.codeSent(true);
                }
            }).fail(function () {
                self.setMessage({ success: false, message: $t('Could not send a code. Please try again.') });
            }).always(function () {
                self.busy(false);
            });
        },

        /**
         * Verify the entered code server-side.
         */
        verifyCode: function () {
            var self = this;

            if (self.busy() || !self.code()) {
                self.setMessage({ success: false, message: $t('Please enter the code we sent you.') });
                return;
            }

            self.busy(true);
            self.message('');

            $.ajax({
                url: self.verifyUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    phone: self.phone(),
                    code: self.code(),
                    form_key: $.mage.cookies.get('form_key')
                }
            }).done(function (response) {
                self.setMessage(response);
                if (response.verified) {
                    self.verified(true);
                }
            }).fail(function () {
                self.setMessage({ success: false, message: $t('Could not verify the code. Please try again.') });
            }).always(function () {
                self.busy(false);
            });
        }
    });
});
