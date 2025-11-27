jQuery(document).ready(function($) {
    'use strict';

    var nickyPaymentGateway = {
        processing: false,
        
        init: function() {
            this.bindEvents();
            this.formatCardInputs();
            this.setupOrderStatusPolling();
        },

        bindEvents: function() {
            var self = this;
            
            // Handle payment method selection
            $(document).on('change', 'input[name="payment_method"]', function() {
                if ($(this).val() === 'nicky') {
                    self.showNickyPaymentInfo();
                } else {
                    self.hideNickyPaymentInfo();
                }
            });

            // Validate form on submission
            $('form.checkout').on('checkout_place_order_nicky', function() {
                if (self.processing) {
                    return false;
                }
                return self.validateAndProcessPayment();
            });
            
            // Handle return from Nicky payment
            this.handlePaymentReturn();
        },

        formatCardInputs: function() {
            // Add maxlength attributes
            $('#nicky-card-number').attr('maxlength', 19); // 16 digits + 3 spaces
            $('#nicky-card-expiry').attr('maxlength', 7);  // MM / YY
            $('#nicky-card-cvc').attr('maxlength', 4);
        },

        validateForm: function() {
            var isValid = true;
            var cardNumber = $('#nicky-card-number').val().replace(/\s/g, '');
            var cardExpiry = $('#nicky-card-expiry').val().replace(/\s/g, '');
            var cardCvc = $('#nicky-card-cvc').val();

            // Clear previous errors
            $('.nicky-payment-error').remove();

            // Validate card number
            if (!this.validateCardNumber(cardNumber)) {
                this.showError('#nicky-card-number', 'Please enter a valid card number.');
                isValid = false;
            }

            // Validate expiry date
            if (!this.validateExpiryDate(cardExpiry)) {
                this.showError('#nicky-card-expiry', 'Please enter a valid expiry date.');
                isValid = false;
            }

            // Validate CVC
            if (!this.validateCvc(cardCvc)) {
                this.showError('#nicky-card-cvc', 'Please enter a valid security code.');
                isValid = false;
            }

            return isValid;
        },

        validateCardNumber: function(cardNumber) {
            // Basic Luhn algorithm check
            if (cardNumber.length < 13 || cardNumber.length > 19) {
                return false;
            }

            var sum = 0;
            var alternate = false;
            
            for (var i = cardNumber.length - 1; i >= 0; i--) {
                var n = parseInt(cardNumber.charAt(i), 10);
                
                if (alternate) {
                    n *= 2;
                    if (n > 9) {
                        n = (n % 10) + 1;
                    }
                }
                
                sum += n;
                alternate = !alternate;
            }
            
            return (sum % 10) === 0;
        },

        validateExpiryDate: function(expiry) {
            if (expiry.length !== 5 || !expiry.includes('/')) {
                return false;
            }

            var parts = expiry.split('/');
            var month = parseInt(parts[0], 10);
            var year = parseInt('20' + parts[1], 10);
            var currentDate = new Date();
            var currentMonth = currentDate.getMonth() + 1;
            var currentYear = currentDate.getFullYear();

            if (month < 1 || month > 12) {
                return false;
            }

            if (year < currentYear || (year === currentYear && month < currentMonth)) {
                return false;
            }

            return true;
        },

        validateCvc: function(cvc) {
            return cvc.length >= 3 && cvc.length <= 4 && /^\d+$/.test(cvc);
        },

        showNickyPaymentInfo: function() {
            if (!$('.nicky-payment-instructions').length) {
                $('#payment_method_nicky').closest('li').append(instructions);
            }
        },

        hideNickyPaymentInfo: function() {
            $('.nicky-payment-instructions').remove();
        },

        validateAndProcessPayment: function() {
            this.processing = true;
            this.showProcessingState();
            return true; // Let WooCommerce handle the actual processing
        },

        showProcessingState: function() {
            var $checkoutForm = $('form.checkout');
            $checkoutForm.addClass('processing').block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        },

        handlePaymentReturn: function() {
            // Check URL parameters for payment status
            var urlParams = new URLSearchParams(window.location.search);
            var paymentStatus = urlParams.get('payment_status');
            var orderId = urlParams.get('order_id');
            
            if (paymentStatus && orderId) {
                if (paymentStatus === 'success') {
                    this.showSuccessMessage('Payment completed successfully!');
                } else if (paymentStatus === 'cancelled') {
                    this.showErrorMessage('Payment was cancelled. You can try again.');
                }
            }
        },

        setupOrderStatusPolling: function() {
            // Frontend polling disabled - status checking is handled by backend cron job
            // The page will be automatically refreshed when payment is complete via backend processing
        },

        pollOrderStatus: function() {
            // Frontend polling disabled - all status checking is handled by backend cron job
            // Users will see updated status when they refresh the page or navigate back
        },

        getOrderIdFromUrl: function() {
            var urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('order') || urlParams.get('order_id');
        },

        showSuccessMessage: function(message) {
            this.showMessage(message, 'success');
        },

        showErrorMessage: function(message) {
            this.showMessage(message, 'error');
        },

        showMessage: function(message, type) {
            var className = type === 'success' ? 'woocommerce-message' : 'woocommerce-error';
            var messageHtml = '<div class="' + className + '" style="margin: 15px 0;">' + message + '</div>';
            
            // Remove existing messages
            $('.woocommerce-message, .woocommerce-error').remove();
            
            // Add new message at the top of the page
            if ($('.woocommerce-notices-wrapper').length) {
                $('.woocommerce-notices-wrapper').html(messageHtml);
            } else {
                $('body').prepend('<div class="woocommerce-notices-wrapper">' + messageHtml + '</div>');
            }
        },

        showError: function(fieldSelector, message) {
            var errorHtml = '<div class="nicky-payment-error" style="color: #e2401c; font-size: 0.875em; margin-top: 5px;">' + message + '</div>';
            $(fieldSelector).closest('.form-row').append(errorHtml);
        }
    };

    // Initialize the payment gateway
    nickyPaymentGateway.init();

    // Store original window.location setter to intercept redirects
    var originalLocationSetter = Object.getOwnPropertyDescriptor(window.Location.prototype, 'href').set;
    var nickyPaymentUrl = null;
    var shouldBlockRedirect = false;

    // Intercept WooCommerce checkout success to open Nicky payment in new tab
    $(document.body).on('checkout_place_order_success', function(event, result) {
        // Check if this is a Nicky payment with redirect URL
        if (result && result.redirect && result.redirect.indexOf('pay.nicky.me') !== -1) {
            // Store the payment URL
            nickyPaymentUrl = result.redirect;
            
            // Open payment URL in new tab
            var paymentWindow = window.open(result.redirect, '_blank', 'noopener,noreferrer');
            
            // Check if popup was blocked
            if (!paymentWindow || paymentWindow.closed || typeof paymentWindow.closed === 'undefined') {
                // Fallback: if popup blocked, let WooCommerce redirect normally
                var message = '<strong>Popup wurde blockiert!</strong> Sie werden zur Zahlungsseite weitergeleitet...';
                nickyPaymentGateway.showMessage(message, 'error');
                nickyPaymentUrl = null;
                shouldBlockRedirect = false;
                return true;
            } else {
                // Show success message
                var successMessage = 'Zahlungsseite wurde in einem neuen Tab geöffnet. Bitte schließen Sie dort Ihre Zahlung ab.';
                nickyPaymentGateway.showMessage(successMessage, 'success');
                
                // Block the redirect for this payment URL
                shouldBlockRedirect = true;
                
                // Remove redirect URL from result
                result.redirect = '';
                
                // Unblock the form
                setTimeout(function() {
                    var $form = $('form.checkout');
                    $form.removeClass('processing').unblock();
                    nickyPaymentGateway.processing = false;
                    
                    // Reset after a delay
                    setTimeout(function() {
                        shouldBlockRedirect = false;
                        nickyPaymentUrl = null;
                    }, 2000);
                }, 100);
                
                return false;
            }
        }
    });

    // Override window.location.href to block Nicky payment redirects
    Object.defineProperty(window.location, 'href', {
        set: function(url) {
            if (shouldBlockRedirect && nickyPaymentUrl && url.indexOf('pay.nicky.me') !== -1) {
                console.log('Nicky: Blocking redirect to payment URL (already opened in new tab)');
                return;
            }
            originalLocationSetter.call(window.location, url);
        },
        get: function() {
            return window.location.href;
        }
    });
