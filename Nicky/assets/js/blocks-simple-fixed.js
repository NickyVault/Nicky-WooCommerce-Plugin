/**
 * Simple WooCommerce Blocks Integration for Nicky Payment Gateway
 * Compatible with WooCommerce 5.0+
 */

(function() {
    'use strict';
    
    // Wait for WooCommerce Blocks to be ready
    const registerNickyPaymentMethod = () => {
        if (!window.wc || !window.wc.wcBlocksRegistry || !window.wp || !window.wp.element) {
            console.log('WooCommerce Blocks or WordPress dependencies not available');
            return;
        }
        
        const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
        const { createElement } = window.wp.element;
        const { __ } = window.wp.i18n;
        
        if (!registerPaymentMethod) {
            console.log('WooCommerce Blocks registry not available');
            return;
        }
        
        // Get settings from localized data or fallback
        const settings = window.nickyBlocksData || {
            title: 'Nicky Payment',
            description: 'Pay securely with Nicky',
            icon: '',
            enabled: true
        };
        
        if (!settings.enabled) {
            console.log('Nicky payment gateway is disabled');
            return; // Don't register if gateway is disabled
        }
        
        const NickyPaymentMethod = {
            name: 'nicky',
            label: createElement(
                'div',
                { style: { display: 'flex', alignItems: 'center' } },
                settings.icon && createElement('img', {
                    src: settings.icon,
                    alt: 'Nicky',
                    style: { height: '20px', marginRight: '8px', maxWidth: '120px' }
                }),
                createElement('span', {}, settings.title || 'Nicky Payment')
            ),
            content: createElement(
                'div',
                { className: 'nicky-payment-content' },
                createElement('p', {}, settings.description || 'Pay securely with cryptocurrency via Nicky')
            ),
            edit: createElement(
                'div',
                { className: 'nicky-payment-content' },
                createElement('p', {}, settings.description || 'Pay securely with cryptocurrency via Nicky')
            ),
            canMakePayment: () => {
                // Basic validation
                return true;
            },
            ariaLabel: settings.title || 'Nicky Payment',
            supports: {
                features: ['products']
            }
        };
        
        try {
            registerPaymentMethod(NickyPaymentMethod);
            console.log('Nicky payment method registered successfully');
        } catch (error) {
            console.error('Failed to register Nicky payment method:', error);
        }
    };
    
    // Try to register immediately, or wait for DOM ready
    if (window.wc && window.wc.wcBlocksRegistry && window.wp && window.wp.element) {
        registerNickyPaymentMethod();
    } else {
        // Wait for dependencies to load
        const waitForDependencies = () => {
            let attempts = 0;
            const maxAttempts = 100; // 10 seconds max
            
            const checkDependencies = () => {
                attempts++;
                
                if (window.wc && window.wc.wcBlocksRegistry && window.wp && window.wp.element) {
                    registerNickyPaymentMethod();
                } else if (attempts < maxAttempts) {
                    setTimeout(checkDependencies, 100);
                } else {
                    console.warn('WooCommerce Blocks dependencies not found after waiting');
                }
            };
            
            checkDependencies();
        };
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', waitForDependencies);
        } else {
            waitForDependencies();
        }
    }
})();
