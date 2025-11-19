jQuery(document).ready(function($) {
    'use strict';
    
    console.log('Nicky Payment Fields script loaded'); // Debug

    var nickyPaymentForm = {
        
        init: function() {
            console.log('Nicky Payment Form initializing'); // Debug
            
            // Only initialize for classic checkout, not WooCommerce Blocks
            if (document.querySelector('.wp-block-woocommerce-checkout')) {
                console.log('WooCommerce Blocks detected, skipping classic checkout logic');
                return;
            }
            
            this.bindEvents();
            this.autoFillFields();
        },

        bindEvents: function() {
            var self = this;
            console.log('Binding events'); // Debug
            
            // Auto-fill fields when Nicky payment is selected
            $(document).on('change', 'input[name="payment_method"]', function() {
                console.log('Payment method changed to:', $(this).val()); // Debug
                if ($(this).val() === 'nicky') {
                    self.autoFillFields();
                    self.showPaymentFields();
                } else {
                    self.hidePaymentFields();
                }
            });
            

            
            // Initialize visibility on page load
            this.togglePaymentFieldsVisibility();
            
            // Real-time validation
            $('#nicky-payer-email').on('blur', this.validateEmail);
            $('#nicky-payer-first-name, #nicky-payer-last-name').on('blur', this.validateName);
            $('#nicky-return-address').on('blur', this.validateWalletAddress);
            
            // Modal events - multiple selectors to ensure it works
            $(document).on('click', '#nicky-open-buyer-modal', function(e) {
                e.preventDefault();
                console.log('Modal button clicked'); // Debug
                self.openBuyerModal();
            });
            
            // Alternative selector in case the ID doesn't work
            $(document).on('click', 'a[href="#"]:contains("Enter Customer Data"), a[href="#"]:contains("Edit Customer Data")', function(e) {
                if ($(this).closest('.nicky-buyer-section').length > 0) {
                    e.preventDefault();
                    console.log('Alternative modal button clicked'); // Debug
                    self.openBuyerModal();
                }
            });
            
            $(document).on('click', '#nicky-modal-cancel, #nicky-buyer-modal-backdrop', function(e) {
                if (e.target.id === 'nicky-modal-cancel' || e.target.id === 'nicky-buyer-modal-backdrop') {
                    self.closeBuyerModal();
                }
            });
            
            $(document).on('click', '#nicky-modal-save', function(e) {
                e.preventDefault();
                self.saveBuyerData();
            });
            
            // Form submission validation
            $('form.checkout').on('checkout_place_order_nicky', function() {
                return self.validateAllFields();
            });
        },

        autoFillFields: function() {
            // Auto-fill email from billing email
            var billingEmail = $('#billing_email').val();
            if (billingEmail && !$('#nicky-payer-email').val()) {
                $('#nicky-payer-email').val(billingEmail);
            }
            
            // Auto-fill names from billing
            var billingFirstName = $('#billing_first_name').val();
            var billingLastName = $('#billing_last_name').val();
            
            if (billingFirstName && !$('#nicky-payer-first-name').val()) {
                $('#nicky-payer-first-name').val(billingFirstName);
            }
            
            if (billingLastName && !$('#nicky-payer-last-name').val()) {
                $('#nicky-payer-last-name').val(billingLastName);
            }
        },

        validateEmail: function() {
            var email = $(this).val();
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                $(this).addClass('error');
                $(this).after('<span class="nicky-error">Please enter a valid email address</span>');
                return false;
            } else {
                $(this).removeClass('error');
                $(this).next('.nicky-error').remove();
                return true;
            }
        },

        validateName: function() {
            var name = $(this).val().trim();
            
            if (!name || name.length < 2) {
                $(this).addClass('error');
                return false;
            } else {
                $(this).removeClass('error');
                return true;
            }
        },

        validateWalletAddress: function() {
            var address = $(this).val().trim();
            
            // Only validate if address is provided (it's optional)
            if (address && address.length < 10) {
                $(this).addClass('error');
                $(this).next('.nicky-error').remove();
                $(this).after('<span class="nicky-error">Wallet address seems too short</span>');
                return false;
            } else {
                $(this).removeClass('error');
                $(this).next('.nicky-error').remove();
                return true;
            }
        },

        togglePaymentFieldsVisibility: function() {
            var selectedPayment = $('input[name="payment_method"]:checked').val();
            if (selectedPayment === 'nicky') {
                this.showPaymentFields();
            } else {
                this.hidePaymentFields();
            }
        },

        showPaymentFields: function() {
            console.log('Showing payment fields, sections found:', $('.nicky-buyer-section').length); // Debug
            $('.nicky-buyer-section').removeClass('hidden').slideDown(200);
        },

        hidePaymentFields: function() {
            $('.nicky-buyer-section').slideUp(200, function() {
                $(this).addClass('hidden');
            });
        },

        openBuyerModal: function() {
            console.log('openBuyerModal called'); // Debug
            console.log('Modal backdrop exists:', $('#nicky-buyer-modal-backdrop').length); // Debug
            
            // Load existing data into modal fields
            $('#nicky-modal-buyer-id').val($('#nicky_buyer_id').val());
            $('#nicky-modal-buyer-dob').val($('#nicky_buyer_dob').val());
            $('#nicky-modal-buyer-comment').val($('#nicky_buyer_comment').val());
            
            $('#nicky-buyer-modal-backdrop').fadeIn(150);
        },

        closeBuyerModal: function() {
            $('#nicky-buyer-modal-backdrop').fadeOut(100);
        },

        saveBuyerData: function() {
            var buyerId = $('#nicky-modal-buyer-id').val().trim();
            var buyerDob = $('#nicky-modal-buyer-dob').val().trim();
            var comment = $('#nicky-modal-buyer-comment').val().trim();
            
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
            $('#nicky_buyer_id').val(buyerId);
            $('#nicky_buyer_dob').val(buyerDob);
            $('#nicky_buyer_comment').val(comment);
            
            // Update button text to show data has been entered
            $('#nicky-open-buyer-modal').text('Edit Customer Data');
            
            // Close modal
            this.closeBuyerModal();
        },



        validateAllFields: function() {
            var isValid = true;
            
            // Remove previous errors
            $('.nicky-error').remove();
            $('#wc-nicky-form .input-text, #wc-nicky-form .select').removeClass('error');
            
            // Validate email
            var email = $('#nicky-payer-email').val();
            if (!email || !this.isValidEmail(email)) {
                this.showFieldError('#nicky-payer-email', 'Please enter a valid email address');
                isValid = false;
            }
            
            // Validate first name
            var firstName = $('#nicky-payer-first-name').val().trim();
            if (!firstName || firstName.length < 2) {
                this.showFieldError('#nicky-payer-first-name', 'Please enter your first name');
                isValid = false;
            }
            
            // Validate last name
            var lastName = $('#nicky-payer-last-name').val().trim();
            if (!lastName || lastName.length < 2) {
                this.showFieldError('#nicky-payer-last-name', 'Please enter your last name');
                isValid = false;
            }
            
            // Validate wallet address if provided
            var walletAddress = $('#nicky-return-address').val().trim();
            if (walletAddress && walletAddress.length < 10) {
                this.showFieldError('#nicky-return-address', 'Wallet address seems too short');
                isValid = false;
            }
            
            // Validate buyer ID (from hidden field)
            var buyerId = $('#nicky_buyer_id').val().trim();
            if (!buyerId || buyerId.length < 3) {
                alert('Customer ID is required. Please click "Enter Customer Data" to provide this information.');
                isValid = false;
            }
            
            // Validate date of birth (from hidden field)
            var dob = $('#nicky_buyer_dob').val();
            var dateRegex = /^\d{4}-\d{2}-\d{2}$/;
            if (!dob || !dateRegex.test(dob)) {
                alert('Date of birth is required. Please click "Enter Customer Data" to provide this information.');
                isValid = false;
            } else {
                // Check if date is reasonable
                var enteredDate = new Date(dob);
                var today = new Date();
                var hundredYearsAgo = new Date(today.getFullYear() - 100, today.getMonth(), today.getDate());
                
                if (enteredDate > today || enteredDate < hundredYearsAgo) {
                    alert('Please enter a valid birth date in the customer data form.');
                    isValid = false;
                }
            }
            
            if (!isValid) {
                $('html, body').animate({
                    scrollTop: $('#wc-nicky-form').offset().top - 100
                }, 500);
            }
            
            return isValid;
        },

        isValidEmail: function(email) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        showFieldError: function(fieldSelector, message) {
            var $field = $(fieldSelector);
            $field.addClass('error');
            $field.after('<span class="nicky-error" style="display: block; color: #e74c3c; font-size: 12px; margin-top: 5px;">' + message + '</span>');
        }
    };

    // Initialize
    nickyPaymentForm.init();
    
    // Additional debug checks
    setTimeout(function() {
        console.log('Debug check - Payment methods found:', $('input[name="payment_method"]').length);
        console.log('Debug check - Nicky payment method found:', $('input[name="payment_method"][value="nicky"]').length);
        console.log('Debug check - Buyer sections found:', $('.nicky-buyer-section').length);
        console.log('Debug check - Modal backdrop found:', $('#nicky-buyer-modal-backdrop').length);
        console.log('Debug check - Modal button found:', $('#nicky-open-buyer-modal').length);
    }, 2000);
});