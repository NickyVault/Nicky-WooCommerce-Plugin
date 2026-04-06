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
                
                // Show description
                settings.description && createElement('p', { 
                    style: { marginBottom: '15px' } 
                }, settings.description || 'Pay securely with cryptocurrency via Nicky'),
                

                
                // Info box - no input fields needed, data comes from billing
                createElement('div', { 
                    className: 'nicky-payment-info',
                    style: { 
                        marginTop: '15px', 
                        padding: '10px', 
                        background: '#f0f8ff', 
                        borderLeft: '4px solid #0073aa', 
                        borderRadius: '4px' 
                    }
                },
                    createElement('p', { style: { margin: '0', fontSize: '14px' } },
                        '🔒 You will be redirected to Nicky to complete your payment securely.'
                    )
                )
            ),
            edit: createElement(
                'div',
                { className: 'nicky-payment-content' },
                createElement('p', {}, settings.description || 'Pay securely with cryptocurrency via Nicky')
            ),
            canMakePayment: () => {
                console.log('Nicky: canMakePayment called');
                return true;
            },
            paymentMethodId: 'nicky',
            ariaLabel: settings.title || 'Nicky Payment',
            supports: {
                features: ['products'],
                showSavedCards: false,
                showSaveOption: false
            }
        };
        
        try {
            registerPaymentMethod(NickyPaymentMethod);
            console.log('Nicky payment method registered successfully');
        } catch (error) {
            console.error('Failed to register Nicky payment method:', error);
        }
    };
    
    // Global modal functions for Blocks integration
    console.log('Defining global modal functions');
    
    window.nickyCreateAndShowModal = function() {
        console.log('Creating and showing modal');
        
        // Remove any existing modal
        const existingModal = document.getElementById('nicky-buyer-modal-backdrop');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Create modal HTML
        const modalHtml = `
            <div id="nicky-buyer-modal-backdrop" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999999; display: block;">
                <div id="nicky-buyer-modal" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; max-width: 520px; width: 92%; padding: 24px; border-radius: 8px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);">
                    <h3 style="margin: 0 0 20px 0; font-size: 18px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px;">Customer Information</h3>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px; color: #333;">Customer ID <span style="color: #e74c3c;">*</span></label>
                        <input type="text" id="nicky-modal-buyer-id" style="width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;" placeholder="Enter your customer ID" />
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px; color: #333;">Date of Birth (YYYY-MM-DD) <span style="color: #e74c3c;">*</span></label>
                        <input type="date" id="nicky-modal-buyer-dob" style="width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;" />
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px; color: #333;">Additional Notes (Optional)</label>
                        <textarea id="nicky-modal-buyer-comment" rows="3" style="width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box; font-family: inherit;" placeholder="Any additional information or comments"></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
                        <button type="button" id="nicky-modal-cancel" style="padding: 8px 16px; border-radius: 4px; font-size: 14px; cursor: pointer; border: 1px solid #ddd; background: #f7f7f7; color: #333;">Cancel</button>
                        <button type="button" id="nicky-modal-save" style="padding: 8px 16px; border-radius: 4px; font-size: 14px; cursor: pointer; background: #0073aa; color: #fff; border: 1px solid #0073aa;">Save</button>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Load existing data
        const existingId = document.getElementById('nicky_buyer_id');
        const existingDob = document.getElementById('nicky_buyer_dob');
        const existingComment = document.getElementById('nicky_buyer_comment');
        
        if (existingId) document.getElementById('nicky-modal-buyer-id').value = existingId.value || '';
        if (existingDob) document.getElementById('nicky-modal-buyer-dob').value = existingDob.value || '';
        if (existingComment) document.getElementById('nicky-modal-buyer-comment').value = existingComment.value || '';
        
        // Add event listeners
        document.getElementById('nicky-modal-cancel').onclick = window.nickyCloseBuyerModal;
        document.getElementById('nicky-modal-save').onclick = window.nickySaveBuyerData;
        document.getElementById('nicky-buyer-modal-backdrop').onclick = function(e) {
            if (e.target.id === 'nicky-buyer-modal-backdrop') {
                window.nickyCloseBuyerModal();
            }
        };
        
        console.log('Modal created and shown');
    };
    
    window.nickyCloseBuyerModal = function() {
        console.log('Closing buyer modal');
        const backdrop = document.getElementById('nicky-buyer-modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }
    };
    
    window.nickySaveBuyerData = function() {
        console.log('Saving buyer data');
        const buyerId = document.getElementById('nicky-modal-buyer-id').value.trim();
        const buyerDob = document.getElementById('nicky-modal-buyer-dob').value.trim();
        const comment = document.getElementById('nicky-modal-buyer-comment').value.trim();
        
        // Basic validation
        if (!buyerId || buyerId.length < 3) {
            alert('Customer ID is required and must be at least 3 characters long.');
            return;
        }
        
        if (!buyerDob || !buyerDob.match(/^\d{4}-\d{2}-\d{2}$/)) {
            alert('Please enter a valid date of birth (YYYY-MM-DD).');
            return;
        }
        
        // Save to hidden inputs
        const hiddenId = document.getElementById('nicky_buyer_id');
        const hiddenDob = document.getElementById('nicky_buyer_dob');
        const hiddenComment = document.getElementById('nicky_buyer_comment');
        
        if (hiddenId) hiddenId.value = buyerId;
        if (hiddenDob) hiddenDob.value = buyerDob;
        if (hiddenComment) hiddenComment.value = comment;
        
        // Update button text
        const button = document.getElementById('nicky-open-buyer-modal');
        if (button) {
            button.textContent = 'Edit Customer Data';
        }
        
        console.log('Data saved:', {buyerId, buyerDob, comment});
        
        // Close modal
        window.nickyCloseBuyerModal();
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
})();
