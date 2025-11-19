jQuery(document).ready(function($) {
    'use strict';

    var nickyAdminGateway = {
        init: function() {
            this.bindEvents();
            this.initAssetRefresh();
        },

        bindEvents: function() {
            // Handle API key changes for Settlement Currency field
            $(document).on('input change', '#woocommerce_nicky_api_key', function() {
                nickyAdminGateway.updateSettlementCurrencyField();
            });
        },

        initAssetRefresh: function() {
            // Initialize Settlement Currency field state
            this.updateSettlementCurrencyField();
        },

        updateSettlementCurrencyField: function() {
            var $apiKeyField = $('#woocommerce_nicky_api_key');
            var $settlementField = $('#woocommerce_nicky_blockchain_asset_id');
            
            if ($settlementField.length && $apiKeyField.length) {
                var apiKey = $apiKeyField.val().trim();
                
                if (apiKey === '') {
                    // No API key - disable field and clear value
                    $settlementField.prop('disabled', true)
                                  .val('')
                                  .css('background-color', '#f5f5f5')
                                  .css('color', '#999');
                    
                    // Add helper text if not already present
                    var helperId = 'nicky-settlement-helper';
                    if (!$('#' + helperId).length) {
                        var helperText = '<p id="' + helperId + '" style="color: #666; font-style: italic; margin-top: 5px;">Enter an API Key first to enable Settlement Currency selection.</p>';
                        $settlementField.closest('tr').find('td').append(helperText);
                    }
                } else {
                    // API key present - enable field
                    $settlementField.prop('disabled', false)
                                  .css('background-color', '')
                                  .css('color', '');
                    
                    // Remove helper text
                    $('#nicky-settlement-helper').remove();
                }
            }
        }
    };

    // Initialize admin functionality
    nickyAdminGateway.init();
});
