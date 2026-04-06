( function() {
    'use strict';
    
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { createElement } = window.wp.element;
    const { __ } = window.wp.i18n;
    const { decodeEntities } = window.wp.htmlEntities;
    
    // Get settings from localized data
    const settings = window.wc.wcSettings.getSetting( 'nicky_data', {} );
    
    // Define the payment method
    const nickyPaymentMethod = {
        name: 'nicky',
        label: createElement(
            'span',
            { style: { display: 'flex', alignItems: 'center' } },
            settings.icon && createElement( 'img', {
                src: settings.icon,
                alt: 'Nicky',
                style: { height: '20px', marginRight: '8px' }
            }),
            decodeEntities( settings.title || __( 'Nicky Payment', 'nicky-me' ) )
        ),
        content: createElement(
            'div',
            { className: 'nicky-payment-method-content' },
            createElement(
                'p',
                { style: { margin: '0 0 15px 0' } },
                decodeEntities( settings.description || __( 'Pay securely with Nicky', 'nicky-me' ) )
            ),

        ),
        edit: createElement(
            'div',
            {},
            decodeEntities( settings.description || __( 'Pay securely with Nicky', 'nicky-me' ) )
        ),
        canMakePayment: () => true,
        ariaLabel: __( 'Nicky Payment Method', 'nicky-me' ),
        supports: {
            features: settings.supports || [ 'products' ]
        }
    };
    
    // Register the payment method
    registerPaymentMethod( nickyPaymentMethod );
    
    // Handle redirect in new tab for WooCommerce Blocks checkout
    // This intercepts the checkout response and opens Nicky payment in a new tab
    if (window.wp && window.wp.data) {
        const { subscribe, select } = window.wp.data;
        
        // Monitor checkout completion
        let previousIsComplete = false;
        let nickyRedirectHandled = false;
        
        // Store original location setter
        let originalLocationSetter = null;
        try {
            originalLocationSetter = Object.getOwnPropertyDescriptor(window.Location.prototype, 'href').set;
        } catch (e) {
            console.log('Could not access location setter');
        }
        
        subscribe(() => {
            try {
                const store = select('wc/store/checkout');
                if (!store) return;
                
                const isComplete = store.isComplete();
                const redirectUrl = store.getRedirectUrl();
                
                // Check if checkout just completed with Nicky redirect
                if (isComplete && !previousIsComplete && redirectUrl && redirectUrl.indexOf('pay.nicky.me') !== -1 && !nickyRedirectHandled) {
                    nickyRedirectHandled = true;
                    
                    // Open payment in new tab
                    const paymentWindow = window.open(redirectUrl, '_blank', 'noopener,noreferrer');
                    
                    // Check if popup was blocked
                    if (!paymentWindow || paymentWindow.closed || typeof paymentWindow.closed === 'undefined') {
                        // If popup blocked, allow normal redirect
                        console.warn('Popup blocked - allowing normal redirect');
                    } else {
                        console.log('Payment opened in new tab - blocking redirect in current tab');
                        
                        // Override window.location.href to prevent redirect
                        if (originalLocationSetter) {
                            try {
                                Object.defineProperty(window.location, 'href', {
                                    set: function(url) {
                                        if (url.indexOf('pay.nicky.me') !== -1) {
                                            console.log('Nicky Blocks: Blocking redirect to payment URL');
                                            return;
                                        }
                                        originalLocationSetter.call(window.location, url);
                                    },
                                    get: function() {
                                        return window.location.href;
                                    }
                                });
                            } catch (e) {
                                console.log('Could not override location.href:', e);
                            }
                        }
                    }
                }
                
                previousIsComplete = isComplete;
            } catch (error) {
                // Silently handle errors
                console.log('Nicky redirect handler error:', error);
            }
        });
    }
    
} )();
