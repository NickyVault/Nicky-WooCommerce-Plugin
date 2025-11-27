jQuery(document).ready(function($) {
    'use strict';

    var nickyCheckoutHandler = {
        processing: false,
        pollingInterval: null,
        
        init: function() {
            this.bindEvents();
            this.initializePaymentMethod();
            this.handleUrlParameters();
        },

        bindEvents: function() {
            var self = this;
            
            // Enhanced checkout form submission
            $('form.checkout').on('checkout_place_order_nicky', function(e) {
                if (self.processing) {
                    e.preventDefault();
                    return false;
                }
                
                return self.handleOrderSubmission();
            });

            // Payment method selection
            $(document).on('change', 'input[name="payment_method"]', function() {
                if ($(this).val() === 'nicky') {
                    self.showNickyInstructions();
                } else {
                    self.hideNickyInstructions();
                }
            });

            // Handle successful checkout redirect
            $(document).on('checkout_place_order_success', function(event, result) {
                if (result.payment_method === 'nicky') {
                    self.handleRedirectToNicky(result);
                }
            });

            // Intercept WooCommerce checkout success to open payment in new tab
            $(document.body).on('checkout_error', function() {
                self.processing = false;
            });
        },

        initializePaymentMethod: function() {
            // Check if Nicky is already selected
            if ($('input[name="payment_method"]:checked').val() === 'nicky') {
                this.showNickyInstructions();
            }
        },

        handleOrderSubmission: function() {
            var self = this;
            
            // Prevent double submission
            if (this.processing) {
                return false;
            }
            
            this.processing = true;
            this.showProcessingIndicator();
            
            // Allow WooCommerce to process the order
            return true;
        },

        showProcessingIndicator: function() {
            var $form = $('form.checkout');
            var $submitButton = $form.find('#place_order');
            
            // Disable submit button and show processing state
            $submitButton.prop('disabled', true);
            $submitButton.text(nicky_checkout_params.i18n.processing);
            
            // Show overlay
            $form.addClass('processing');
            
            // Add processing message
            this.showMessage(nicky_checkout_params.i18n.please_wait, 'info');
        },

        showNickyInstructions: function() {
            if ($('.nicky-checkout-instructions').length > 0) {
                return;
            }

            var instructions = $('<div class="nicky-checkout-instructions"></div>');
            var content = '<div class="nicky-payment-flow">' +
                '<h4>' + this.getTranslation('payment_process', 'Payment Process') + '</h4>' +
                '<div class="nicky-steps">' +
                '<div class="step">' +
                '<span class="step-number">1</span>' +
                '<span class="step-text">' + this.getTranslation('click_place_order', 'Click "Place Order"') + '</span>' +
                '</div>' +
                '<div class="step">' +
                '<span class="step-number">2</span>' +
                '<span class="step-text">' + this.getTranslation('redirect_to_nicky', 'Redirect to Nicky.me') + '</span>' +
                '</div>' +
                '<div class="step">' +
                '<span class="step-number">3</span>' +
                '<span class="step-text">' + this.getTranslation('complete_payment', 'Complete cryptocurrency payment') + '</span>' +
                '</div>' +
                '<div class="step">' +
                '<span class="step-number">4</span>' +
                '<span class="step-text">' + this.getTranslation('return_to_store', 'Return to store') + '</span>' +
                '</div>' +
                '</div>' +
                '<div class="nicky-security-note">' +
                '<small>' + this.getTranslation('secure_payment', 'Secure cryptocurrency payment processing') + '</small>' +
                '</div>' +
                '</div>';

            instructions.html(content);
            $('.payment_method_nicky').append(instructions);
        },

        hideNickyInstructions: function() {
            $('.nicky-checkout-instructions').remove();
        },

        handleRedirectToNicky: function(result) {
            // Show redirect message
            this.showMessage(nicky_checkout_params.i18n.redirecting, 'info');
            
            // Optional: Add countdown or progress indicator
            var countdown = 3;
            var countdownElement = $('<span class="redirect-countdown">(' + countdown + ')</span>');
            $('.woocommerce-info').append(countdownElement);
            
            var countdownInterval = setInterval(function() {
                countdown--;
                countdownElement.text('(' + countdown + ')');
                
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                }
            }, 1000);
        },

        handleUrlParameters: function() {
            var urlParams = new URLSearchParams(window.location.search);
            var status = urlParams.get('payment_status');
            var message = urlParams.get('message');
            
            if (status) {
                switch (status) {
                    case 'success':
                        this.showMessage(
                            message || this.getTranslation('payment_successful', 'Payment completed successfully!'),
                            'success'
                        );
                        break;
                    case 'cancelled':
                        this.showMessage(
                            message || this.getTranslation('payment_cancelled', 'Payment was cancelled.'),
                            'error'
                        );
                        break;
                    case 'failed':
                        this.showMessage(
                            message || this.getTranslation('payment_failed', 'Payment failed. Please try again.'),
                            'error'
                        );
                        break;
                }
            }
        },

        startOrderStatusPolling: function(orderId) {
            // Frontend polling disabled - all status checking is handled by backend cron job
            // Payment status updates will be processed server-side every 30 seconds
            console.log('Nicky: Frontend polling disabled. Status updates handled by backend.');
        },

        showMessage: function(message, type) {
            var className = 'woocommerce-message';
            
            switch (type) {
                case 'error':
                    className = 'woocommerce-error';
                    break;
                case 'info':
                    className = 'woocommerce-info';
                    break;
                case 'success':
                    className = 'woocommerce-message';
                    break;
            }

            var messageHtml = '<div class="' + className + '">' + message + '</div>';
            
            // Remove existing messages of the same type
            $('.' + className).remove();
            
            // Add message at top of form or page
            if ($('.woocommerce-notices-wrapper').length) {
                $('.woocommerce-notices-wrapper').prepend(messageHtml);
            } else if ($('form.checkout').length) {
                $('form.checkout').prepend(messageHtml);
            } else {
                $('body').prepend('<div class="woocommerce-notices-wrapper">' + messageHtml + '</div>');
            }
            
            // Auto-remove info messages after 5 seconds
            if (type === 'info') {
                setTimeout(function() {
                    $('.' + className).fadeOut();
                }, 5000);
            }
        },

        getTranslation: function(key, fallback) {
            if (typeof nicky_checkout_params !== 'undefined' && 
                nicky_checkout_params.i18n && 
                nicky_checkout_params.i18n[key]) {
                return nicky_checkout_params.i18n[key];
            }
            return fallback;
        },

        // Cleanup method
        cleanup: function() {
            if (this.pollingInterval) {
                clearInterval(this.pollingInterval);
            }
        }
    };

    // Initialize the checkout handler
    nickyCheckoutHandler.init();

    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        nickyCheckoutHandler.cleanup();
    });

    // Store state for redirect blocking
    var nickyPaymentUrl = null;
    var shouldBlockRedirect = false;
    var originalLocationSetter = null;

    // Try to store original location setter
    try {
        originalLocationSetter = Object.getOwnPropertyDescriptor(window.Location.prototype, 'href').set;
    } catch (e) {
        console.log('Could not access location setter');
    }

    // Override WooCommerce checkout redirect for Nicky payment method
    // This intercepts the redirect and opens payment in a new tab
    if (typeof wc_checkout_params !== 'undefined') {
        $(document.body).on('checkout_place_order_success', function(event, data) {
            // Check if this is a Nicky payment
            if (data && data.redirect && data.redirect.indexOf('pay.nicky.me') !== -1) {
                // Store payment URL
                nickyPaymentUrl = data.redirect;
                
                // Open payment URL in new tab
                var paymentWindow = window.open(data.redirect, '_blank', 'noopener,noreferrer');
                
                // Check if popup was blocked
                if (!paymentWindow || paymentWindow.closed || typeof paymentWindow.closed === 'undefined') {
                    // Fallback: if popup blocked, let normal redirect happen
                    nickyCheckoutHandler.showMessage(
                        '<strong>Popup blockiert!</strong> Sie werden zur Zahlungsseite weitergeleitet...',
                        'error'
                    );
                    nickyPaymentUrl = null;
                    shouldBlockRedirect = false;
                    return true;
                } else {
                    // Show success message
                    nickyCheckoutHandler.showMessage(
                        nickyCheckoutHandler.getTranslation('payment_opened', 'Zahlungsseite wurde in einem neuen Tab geöffnet. Bitte schließen Sie dort Ihre Zahlung ab.'),
                        'info'
                    );
                    
                    // Block redirect
                    shouldBlockRedirect = true;
                    
                    // Remove redirect from data
                    data.redirect = '';
                    
                    // Unblock form
                    setTimeout(function() {
                        var $form = $('form.checkout');
                        $form.removeClass('processing').unblock();
                        nickyCheckoutHandler.processing = false;
                        
                        // Reset block after delay
                        setTimeout(function() {
                            shouldBlockRedirect = false;
                            nickyPaymentUrl = null;
                        }, 2000);
                    }, 100);
                    
                    return false;
                }
            }
        });

        // Override window.location.href to block redirects
        if (originalLocationSetter) {
            try {
                Object.defineProperty(window.location, 'href', {
                    set: function(url) {
                        if (shouldBlockRedirect && nickyPaymentUrl && url.indexOf('pay.nicky.me') !== -1) {
                            console.log('Nicky: Blocking redirect to payment URL');
                            return;
                        }
                        originalLocationSetter.call(window.location, url);
                    },
                    get: function() {
                        return window.location.href;
                    }
                });
            } catch (e) {
                console.log('Could not override location.href');
            }
        }
    }
