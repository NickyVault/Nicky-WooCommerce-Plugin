<?php
/**
 * Nicky Payment Gateway Class
 *
 * @package NickyPaymentGateway
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Nicky Payment Gateway class
 */
class Nicky_WC_Gateway_Nicky extends WC_Payment_Gateway {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'nicky';
        $this->icon               = $this->get_gateway_icon();
        // Enable custom payment fields
        $this->has_fields         = true;
        $this->method_title       = 'Nicky.me';
        $this->method_description = 'Accept payments using Nicky.me - secure, fast and reliable payment processing';
        $this->supports           = array(
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation'
        );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title                = $this->get_option('title', 'Nicky.me Payment');
        $this->description          = $this->get_option('description', 'Pay securely with crypto via Nicky.me.');
        $this->enabled              = $this->get_option('enabled', 'no');
        
        // Nicky API settings
        $this->api_base_url         = $this->get_option('api_base_url', 'https://api-public.pay.nicky.me');
        $this->api_key              = $this->get_option('api_key', '');
        $this->blockchain_asset_id  = $this->get_option('blockchain_asset_id', '');
        $this->settlement_currency  = $this->get_option('settlement_currency', '');
        
        // Fallback: use settlement_currency if blockchain_asset_id is empty
        if (empty($this->blockchain_asset_id) && !empty($this->settlement_currency)) {
            $this->blockchain_asset_id = $this->settlement_currency;
        }

        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'webhook_handler'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Add AJAX handlers
        add_action('wp_ajax_nickym_check_nicky_payment_status', array($this, 'ajax_check_payment_status'));
        add_action('wp_ajax_nopriv_nickym_check_nicky_payment_status', array($this, 'ajax_check_payment_status'));
        
        // Add support for WooCommerce Blocks
        add_action('woocommerce_blocks_loaded', array($this, 'register_block_support'));
        
        // Add order status handling
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        
        // Add cron job for automatic payment status checking
        add_action('nicky_check_payment_status_cron', array($this, 'cron_check_payment_status'));
        add_action('init', array($this, 'schedule_payment_status_cron'));
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
        
        // Add hook for order cancellation
        add_action('woocommerce_order_status_cancelled', array($this, 'handle_order_cancellation'));
        add_action('woocommerce_order_status_pending_to_cancelled', array($this, 'handle_order_cancellation'));
        add_action('woocommerce_order_status_on-hold_to_cancelled', array($this, 'handle_order_cancellation'));
    }
    
    /**
     * Register support for WooCommerce Blocks
     */
    public function register_block_support() {
        if (class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            add_filter('woocommerce_blocks_payment_method_type_registration', array($this, 'add_block_support'));
        }
    }
    
    /**
     * Add WooCommerce Blocks support
     */
    public function add_block_support($payment_method_registry) {
        // This is handled by the separate blocks class
        return $payment_method_registry;
    }

    /**
     * Check if gateway is available
     */
    public function is_available() {
        // Always check parent availability first
        if (!parent::is_available()) {
            return false;
        }

        // Check if gateway is enabled
        if ('yes' !== $this->enabled) {
            return false;
        }

        // Skip cart validation during admin or API calls
        if (!is_admin() && !defined('DOING_AJAX') && WC()->cart && WC()->cart->is_empty()) {
            return false;
        }

        // Require API key and blockchain asset id
        if (empty($this->api_key)) {
            return false;
        }
        
        if (empty($this->blockchain_asset_id)) {
            // Try to use settlement_currency as fallback
            if (!empty($this->settlement_currency)) {
                $this->blockchain_asset_id = $this->settlement_currency;
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Get gateway icon
     */
    public function get_gateway_icon() {
        // Check for custom logo in settings first
        $custom_logo = $this->get_option('custom_logo');
        if (!empty($custom_logo)) {
            return esc_url($custom_logo);
        }
        
        // Check if PNG logo exists
        $logo_png_path = NICKY_PAYMENT_GATEWAY_PLUGIN_PATH . 'assets/images/logo.png';
        $logo_png_url = NICKY_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/images/logo.png';
        
        if (file_exists($logo_png_path)) {
            return $logo_png_url;
        }
        
        // Fallback to SVG logo
        $logo_svg_path = NICKY_PAYMENT_GATEWAY_PLUGIN_PATH . 'assets/images/logo.svg';
        $logo_svg_url = NICKY_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/images/logo.svg';
        
        if (file_exists($logo_svg_path)) {
            return $logo_svg_url;
        }
        
        // Return empty if no logo found
        return '';
    }

    /**
     * Process admin options and handle webhook creation
     */
    public function process_admin_options() {
        // Get old and new API key values
        $old_api_key = $this->get_option('api_key', '');
        
        // Process the form fields first
        $result = parent::process_admin_options();
        
        // Get the new API key value after saving
        $new_api_key = $this->get_option('api_key', '');
        
        // Check if API key was just set (from empty to non-empty)
        if (empty($old_api_key) && !empty($new_api_key)) {
            $this->create_payment_status_webhook();
            
            // Clear blockchain asset id if API key changed
            $this->update_option('blockchain_asset_id', '');
        }
        // Check if API key was removed (from non-empty to empty)
        elseif (!empty($old_api_key) && empty($new_api_key)) {
            $this->delete_payment_status_webhook();
            
            // Clear blockchain asset id when API key is removed
            $this->update_option('blockchain_asset_id', '');
        }
        // If API key changed (but both are non-empty), clear blockchain asset
        elseif (!empty($old_api_key) && !empty($new_api_key) && $old_api_key !== $new_api_key) {
            $this->update_option('blockchain_asset_id', '');
        }
        
        return $result;
    }

    /**
     * Create PaymentRequest_StatusChanged webhook
     */
    private function create_payment_status_webhook() {
        // Ensure we have an API key
        $api_key = $this->get_option('api_key', '');
        if (empty($api_key)) {
            return false;
        }
        
        // Check if we already have a webhook configured
        $existing_webhook_id = get_option('nicky_webhook_id', '');
        if (!empty($existing_webhook_id)) {
            // Verify if the webhook still exists by listing webhooks
            if ($this->verify_webhook_exists($existing_webhook_id)) {
                return true;
            } else {
                // Webhook doesn't exist anymore, remove the stored ID
                delete_option('nicky_webhook_id');
            }
        }
        
        // Generate the webhook URL for this WordPress installation
        $webhook_url = $this->get_webhook_url();
        
        // Prepare webhook creation request according to Swagger spec
        $body = array(
            'webHookType' => 'PaymentRequest_StatusChanged',
            'url' => $webhook_url
        );
        
        // Call the API to create webhook with explicit API key
        $response = $this->api_post_with_key('/api/public/WebHookApi/create', $body, $api_key);
        
        if (is_wp_error($response)) {
            // Show admin notice about webhook creation failure
            add_action('admin_notices', function() use ($response) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>Nicky.me:</strong> Webhook creation failed: ' . esc_html($response->get_error_message()) . '</p>';
                echo '</div>';
            });
            return false;
        }
        
        // Store webhook ID in options for future reference
        if (!empty($response['id'])) {
            $webhook_id = $response['id'];
            update_option('nicky_webhook_id', $webhook_id);
            
            // Show success notice
            add_action('admin_notices', function() use ($webhook_id) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>Nicky.me:</strong> Payment status webhook created successfully (ID: ' . esc_html($webhook_id) . ')</p>';
                echo '</div>';
            });
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Verify if a webhook still exists by checking the webhook list
     */
    private function verify_webhook_exists($webhook_id) {
        $api_key = $this->get_option('api_key', '');
        if (empty($api_key)) {
            return false;
        }
        
        $response = $this->api_get_with_key('/api/public/WebHookApi/list', array(), $api_key);
        
        if (is_wp_error($response) || !is_array($response)) {
            return false;
        }
        
        // Check if our webhook ID exists in the list
        foreach ($response as $webhook) {
            if (isset($webhook['id']) && $webhook['id'] === $webhook_id) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Delete the existing PaymentRequest_StatusChanged webhook
     */
    private function delete_payment_status_webhook() {
        $webhook_id = get_option('nicky_webhook_id', '');
        
        if (empty($webhook_id)) {
            return false;
        }
        
        // Use old API key if available (for deletion during key removal)
        $api_key = $this->get_option('api_key', '');
        if (empty($api_key)) {
            // Try to get the API key from POST data if we're in the process of removing it
            $api_key = sanitize_text_field(wp_unslash($_POST['woocommerce_nicky_api_key'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }
        
        if (empty($api_key)) {
            return false;
        }
        
        // Call the API to delete webhook (using POST method with query parameter as per Swagger spec)
        $response = $this->api_post_with_key('/api/public/WebHookApi/delete?id=' . urlencode($webhook_id), array(), $api_key);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        // Remove the stored webhook ID
        delete_option('nicky_webhook_id');
        
        // Show admin notice
        add_action('admin_notices', function() {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>Nicky.me:</strong> Payment status webhook was deleted.</p>';
            echo '</div>';
        });
        
        return true;
    }
    
    /**
     * Get the webhook URL for this WordPress installation
     */
    private function get_webhook_url() {
        // Use the WooCommerce API endpoint that's already set up for webhook handling
        // The webhook_handler method is already configured to handle: ?wc-api=wc_gateway_nicky
        return add_query_arg('wc-api', 'wc_gateway_nicky', home_url('/'));
    }

    /**
     * Handle order cancellation - cancel payment request in Nicky.me
     */
    public function handle_order_cancellation($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Only process Nicky payment method orders
        if ($order->get_payment_method() !== 'nicky') {
            return;
        }
        
        // Get the Nicky shortId for this order (HPOS compatible)
        $short_id = $order->get_meta('_nicky_short_id', true);
        
        if (empty($short_id)) {
            $order->add_order_note('Could not cancel Nicky payment - no payment ID found');
            return;
        }
        
        // Call Nicky.me cancel API
        $result = $this->cancel_payment_request($short_id);
        
        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            $order->add_order_note(sprintf(
                'Failed to cancel Nicky payment (shortId: %s): %s',
                $short_id,
                $error_message
            ));
        } elseif ($result === true) {
            $order->add_order_note(sprintf(
                'Nicky payment cancelled successfully (shortId: %s)',
                $short_id
            ));
        } else {
            $order->add_order_note(sprintf(
                'Nicky payment cancellation response (shortId: %s): %s',
                $short_id,
                is_array($result) ? wp_json_encode($result) : $result
            ));
        }
    }
    
    /**
     * Cancel a payment request in Nicky.me via API
     */
    private function cancel_payment_request($short_id) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'No API key configured');
        }
        
        if (empty($short_id)) {
            return new WP_Error('no_short_id', 'No shortId provided');
        }
        
        // Call the cancel endpoint (POST with shortId as query parameter)
        $response = $this->api_post('/api/public/PaymentRequestPublicApi/cancel?shortId=' . urlencode($short_id), array());
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // The API returns a boolean according to Swagger spec
        if ($response === true || $response === 'true' || (is_array($response) && !empty($response))) {
            return true;
        }
        
        // If we get here, the cancellation might have failed
        return new WP_Error('cancel_failed', 'Payment cancellation returned unexpected result: ' . wp_json_encode($response));
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable Nicky.me',
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default'     => 'Nicky.me Payment',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'Payment method description that the customer will see on your checkout.',
                'default'     => 'Pay securely with crypto via Nicky.me.',
                'desc_tip'    => true,
            ),
            'api_base_url' => array(
                'title'       => 'API Base URL',
                'type'        => 'text',
                'description' => 'Base URL for Nicky.me API.',
                'default'     => 'https://api-public.pay.nicky.me',
                'desc_tip'    => false,
                'custom_attributes' => array(
                    'disabled' => 'disabled',
                    'readonly' => 'readonly'
                ),
            ),
            'api_key' => array(
                'title'       => 'API Key (x-api-key)',
                'type'        => 'text',
                'description' => 'Your Nicky.me API key. Sent in header x-api-key.',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'blockchain_asset_id' => $this->get_blockchain_asset_field_config(),
            'custom_logo' => array(
                'title'       => 'Custom Logo URL',
                'type'        => 'text',
                'description' => 'Enter the URL to your custom logo. If empty, the default logo will be used. Recommended size: 120x24px',
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => 'https://example.com/path/to/logo.png'
            ),
        );
    }

    /**
     * Get blockchain asset options for admin select field
     */
    private function get_blockchain_asset_options() {
        // Default options
        $options = array(
            '' => 'Select blockchain asset...'
        );

        // Get current settings for API URL
        $current_api_url = $this->get_option('api_base_url', 'https://api-public.pay.nicky.me');
        
        // Try to load assets from API
        $assets = $this->fetch_blockchain_assets_for_admin($current_api_url);
        
        if (!empty($assets)) {
            foreach ($assets as $asset) {
                $label = $asset['name'] . ' (' . $asset['symbol'] . ')';
                if (!empty($asset['blockchain']) && $asset['blockchain'] !== 'N/A') {
                    $label .= ' - ' . $asset['blockchain'];
                }
                $options[$asset['id']] = $label;
            }
        } else {
            // Fallback options if API fails - use correct format from Swagger (assetChain.assetTicker)
            $options['BTC.BTC'] = 'Bitcoin (BTC) - BTC';
            $options['ETH.ETH'] = 'Ether (ETH) - ETH';
            $options['USD.USD'] = 'US Dollar (USD) - USD';
            $options['EUR.EUR'] = 'Euro (EUR) - EUR';
        }

        return $options;
    }

    /**
     * Get blockchain asset field configuration based on API key availability
     */
    private function get_blockchain_asset_field_config() {
        $api_key = $this->get_option('api_key', '');
        
        if (empty($api_key)) {
            // No API key - field is disabled and empty
            return array(
                'title'       => 'Settlement Currency / blockchainAssetId',
                'type'        => 'select',
                'description' => 'Please set your API Key first to load available blockchain assets.',
                'default'     => '',
                'desc_tip'    => true,
                'custom_attributes' => array(
                    'disabled' => 'disabled'
                ),
                'options'     => array(
                    '' => 'Please set API Key first...'
                ),
                'class'       => 'nicky-disabled-field'
            );
        } else {
            // API key is set - field is enabled and loads options
            return array(
                'title'       => 'Settlement Currency / blockchainAssetId',
                'type'        => 'select',
                'description' => 'Select the blockchain asset for settlements. Assets are loaded from your Nicky.me account.',
                'default'     => '',
                'desc_tip'    => true,
                'options'     => $this->get_blockchain_asset_options(),
                'class'       => 'nicky-enabled-field'
            );
        }
    }

    /**
     * Fetch blockchain assets from API for admin use
     */
    private function fetch_blockchain_assets_for_admin($api_base_url) {
        // Build API URL - correct endpoint
        $api_url = rtrim($api_base_url, '/') . '/AcceptedAsset/get-for-user';
        
        // Get API key from settings
        $api_key = $this->get_option('api_key', '');
        
        // Make API request with proper headers
        $response = wp_remote_get($api_url, [
            'timeout' => 10,
            'headers' => [
                'accept' => 'text/plain',
                'x-api-key' => $api_key
            ]
        ]);
        
        // Check for errors
        if (is_wp_error($response)) {
            return array();
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!is_array($data)) {
            return array();
        }
        
        // Process accepted assets (include both fiat and crypto for settlement)
        $assets = array();
        foreach ($data as $asset) {
            $assets[] = array(
                'id' => $asset['id'],
                'name' => $asset['assetName'],
                'symbol' => $asset['assetTicker'],
                'blockchain' => $asset['assetChain'],
                'isFiat' => $asset['isFiat'] ?? false,
                'decimalPrecision' => $asset['decimalPrecisionUI'] ?? 8
            );
        }
        
        // No caching - return fresh data
        return $assets;
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if (!is_admin() && is_checkout()) {
            wp_enqueue_style(
                'nicky-payment-gateway-style',
                NICKY_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/css/checkout.css',
                array(),
                NICKY_PAYMENT_GATEWAY_VERSION
            );

            // Add inline CSS for credit card form
            $css = '
                .wc-credit-card-form .form-row {
                    margin-bottom: 15px;
                }
                
                .wc-credit-card-form input[type="text"] {
                    width: 100%;
                    padding: 10px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: 14px;
                }
                
                .wc-credit-card-form input[type="text"]:focus {
                    border-color: #007cba;
                    outline: none;
                    box-shadow: 0 0 0 1px #007cba;
                }
                
                .wc-credit-card-form label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: 600;
                }
                
                .wc-credit-card-form .required {
                    color: #e2401c;
                }
            ';
            
            wp_add_inline_style('nicky-payment-gateway-style', $css);

        wp_enqueue_script(
            'nicky-payment-gateway-script',
            NICKY_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/js/checkout.js',
            array('jquery', 'wc-checkout'),
            NICKY_PAYMENT_GATEWAY_VERSION,
            true
        );
        
        // Enqueue payment fields script
        wp_enqueue_script(
            'nicky-payment-fields-script',
            NICKY_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/js/payment-fields.js',
            array('jquery', 'wc-checkout'),
            NICKY_PAYMENT_GATEWAY_VERSION,
            true
        );            wp_localize_script('nicky-payment-gateway-script', 'nicky_payment_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('nicky_payment_nonce'),
            ));
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook_suffix) {
        // Load on any admin page that might contain WooCommerce settings
        if (!is_admin()) {
            return;
        }
        
        // Check if we're on WooCommerce settings page
        if (strpos($hook_suffix, 'wc-settings') === false && 
            (!isset($_GET['page']) || sanitize_key($_GET['page']) !== 'wc-settings')) {
            return;
        }

        wp_enqueue_style(
            'nicky-payment-gateway-admin-style',
            NICKY_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            NICKY_PAYMENT_GATEWAY_VERSION
        );

        wp_enqueue_script(
            'nicky-payment-gateway-admin-script',
            NICKY_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            NICKY_PAYMENT_GATEWAY_VERSION,
            true
        );

        wp_localize_script('nicky-payment-gateway-admin-script', 'nicky_admin_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('nicky_admin_nonce'),
        ));
        
        // Also make sure global ajaxurl is available
        wp_localize_script('nicky-payment-gateway-admin-script', 'ajaxurl', admin_url('admin-ajax.php'));
    }



    /**
     * Payment form on checkout page
     */
    public function payment_fields() {
        // Show description if set
        if ($this->description) {
            echo esc_html(wpautop(wp_kses_post($this->description)));
        }

        // Add helpful information - no input fields needed, data comes from billing
        echo '<div class="nicky-payment-info" style="margin-top: 15px; padding: 10px; background: #f0f8ff; border-left: 4px solid #0073aa; border-radius: 4px;">';
        echo '<p class="nicky-info-text" style="margin: 0; font-size: 14px;">';
        echo '🔒 ' . esc_html(__('You will be redirected to Nicky.me to complete your payment securely.', 'nicky-me'));
        echo '</p>';
        echo '</div>';
        
        // Add supported payment methods info
        echo '<div class="nicky-supported-methods" style="margin-top: 10px; text-align: center;">';
        echo '<small style="color: #666;">💰 ' . esc_html(__('Supported: Bitcoin, Ethereum, USDT and more cryptocurrencies', 'nicky-me')) . '</small>';
        echo '</div>';
    }

    /**
     * Validate payment fields
     * No custom fields needed - all data comes from WooCommerce billing information
     */
    public function validate_fields() {
        // No custom fields to validate - billing information is validated by WooCommerce
        return true;
    }

    /**
     * Process the payment
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wc_add_notice('Order not found.', 'error');
            return array('result' => 'fail');
        }
        
        if (empty($this->api_key)) {
            wc_add_notice('Payment method configuration error: API Key is missing.', 'error');
            return array('result' => 'fail');
        }
        
        if (empty($this->blockchain_asset_id)) {
            wc_add_notice('Payment method configuration error: Blockchain Asset ID is missing.', 'error');
            return array('result' => 'fail');
        }
        
        // Get data from WooCommerce billing information (no custom fields needed)
        $payer_email = $order->get_billing_email();
        $payer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        
        // Use blockchain_asset_id from settings
        $selected_asset_id = $this->blockchain_asset_id;
        
        // Validate asset ID format - must be in format "CHAIN.TICKER" (e.g., "EUR.EUR", "BTC.BTC")
        if (!empty($selected_asset_id) && strpos($selected_asset_id, '.') === false) {
            // Try to fix common cases
            $selected_asset_id = $selected_asset_id . '.' . $selected_asset_id;
        }
        
        // Get order amount in shop currency
        $amount_shop_currency = floatval($order->get_total());
        $shop_currency = get_woocommerce_currency();
        
        // Get conversion quote from shop currency to target cryptocurrency
        // The API returns the converted amount directly in the 'price' field
        $conversion_quote = $this->get_conversion_quote($amount_shop_currency, $selected_asset_id);
        
        if (is_wp_error($conversion_quote)) {
            $error_message = sprintf(
                'Currency conversion failed: %s. Please try again or contact support.',
                $conversion_quote->get_error_message()
            );
            $order->add_order_note('Nicky conversion error: ' . $conversion_quote->get_error_message());
            wc_add_notice($error_message, 'error');
            return array('result' => 'fail');
        }
        
        // Extract the converted amount from the quote response
        // The 'price' field contains the final converted amount, not the exchange rate!
        $amount_crypto = isset($conversion_quote['price']) ? floatval($conversion_quote['price']) : 0.0;
        $quote_id = isset($conversion_quote['id']) ? $conversion_quote['id'] : null;
        
        // Prepare PaymentRequest according to Swagger API spec
        // amountExpectedNative must be in the target cryptocurrency, not shop currency!
        $body = array(
            'blockchainAssetId' => $selected_asset_id,
            'amountExpectedNative' => $amount_crypto,
            'billDetails' => array(
                'invoiceReference' => $order->get_order_number(),
                'description' => sprintf(
                    'Order #%s from %s', 
                    $order->get_order_number(),
                    get_bloginfo('name')
                )
            ),
            'requester' => array(
                'email' => $payer_email,
                'name' => !empty($payer_name) ? $payer_name : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name()
            ),
            'sendNotification' => false,
            'successUrl' => $this->get_return_url($order),
            'cancelUrl' => wc_get_checkout_url()
        );

        $resp = $this->api_post('/api/public/PaymentRequestPublicApi/create', $body);

        if (is_wp_error($resp)) {
            $order->add_order_note('Nicky API error: ' . $resp->get_error_message());
            wc_add_notice('Payment error: could not create payment request.', 'error');
            return array('result' => 'fail');
        }

        // Extract data from PaymentRequest response (according to Swagger spec)
        // Response structure: PaymentRequest with nested bill.shortId
        $payment_request_id = '';
        $short_id = '';
        
        if (!empty($resp['id'])) {
            $payment_request_id = $resp['id'];
        }
        
        if (!empty($resp['bill']['shortId'])) {
            $short_id = $resp['bill']['shortId'];
        }

        if (empty($short_id)) {
            $order->add_order_note('Nicky PaymentRequest response missing bill.shortId: ' . wp_json_encode($resp));
            wc_add_notice('Payment error: invalid response from payment provider.', 'error');
            return array('result' => 'fail');
        }

        // Store payment details for later reference (HPOS compatible)
        $order->update_meta_data('_nicky_short_id', $short_id);
        $order->update_meta_data('_nicky_payment_request_id', $payment_request_id);
        $order->update_meta_data('_nicky_blockchain_asset_id', $selected_asset_id);
        $order->update_meta_data('_nicky_conversion_quote_id', $quote_id);
        $order->update_meta_data('_nicky_amount_shop_currency', $amount_shop_currency);
        $order->update_meta_data('_nicky_amount_crypto', $amount_crypto);
        $order->update_meta_data('_nicky_shop_currency', $shop_currency);
        $order->save();
        
        $order->add_order_note(sprintf(
            'Nicky PaymentRequest created. ShortId: %s, RequestId: %s, Asset: %s, Amount: %s %s (converted from %s %s), Quote ID: %s',
            $short_id,
            $payment_request_id,
            $selected_asset_id,
            $amount_crypto,
            $selected_asset_id,
            $amount_shop_currency,
            $shop_currency,
            $quote_id
        ));
        
        $order->update_status('on-hold', sprintf('Awaiting Nicky payment (shortId: %s)', $short_id));

        // Empty cart
        WC()->cart->empty_cart();

        $redirect_url = 'https://pay.nicky.me/home?paymentId=' . urlencode($short_id);

        return array(
            'result' => 'success',
            'redirect' => $redirect_url,
            'nicky_payment_url' => $redirect_url,
            'open_in_new_tab' => true,
        );
    }

    /**
     * Process payment request
     */
    private function process_payment_request($order, $card_number, $card_expiry, $card_cvc) {
        // Card processing removed; redirect flow used instead.
        return array(
            'success' => false,
            'transaction_id' => '',
            'message' => 'Card processing not supported. Use redirect flow.'
        );
    }

    /**
     * Save transaction details
     */
    private function save_transaction($order_id, $payment_result) {
        global $wpdb;

        $order = wc_get_order($order_id);
        $table_name = $wpdb->prefix . 'nicky_payment_transactions';

        $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'transaction_id' => $payment_result['transaction_id'],
                'payment_status' => $payment_result['success'] ? 'completed' : 'failed',
                'amount' => $order->get_total(),
                'currency' => $order->get_currency(),
                'gateway_response' => json_encode($payment_result),
            ),
            array('%d', '%s', '%s', '%f', '%s', '%s')
        );
    }

    /**
     * Get the shop's currency as a blockchain asset ID
     * Converts WooCommerce currency to format "CURRENCY.CURRENCY" (e.g., "EUR.EUR")
     * 
     * @return string Blockchain asset ID for shop currency
     */
    private function get_shop_currency_asset_id() {
        $currency = get_woocommerce_currency(); // e.g., "EUR", "USD"
        return $currency . '.' . $currency;
    }

    /**
     * Get conversion quote from Nicky API
     * Calls /api/public/ConversionRate/get-quote to convert amount from shop currency to cryptocurrency
     * 
     * @param float $amount Amount in shop currency to convert
     * @param string $target_asset_id Target blockchain asset ID (e.g., "BTC.BTC")
     * @return array|WP_Error Quote response where 'price' contains the converted amount, or WP_Error on failure
     */
    private function get_conversion_quote($amount, $target_asset_id) {
        $shop_currency_asset_id = $this->get_shop_currency_asset_id();

        $body = array(
            'amount' => floatval($amount),
            'fromBlockchainId' => $shop_currency_asset_id,
            'toBlockchainId' => $target_asset_id
        );

        $response = $this->api_post('/api/public/ConversionRate/get-quote', $body);

        if (is_wp_error($response)) {
            return $response;
        }

        // Validate response structure
        if (!isset($response['price']) || !isset($response['id'])) {
            return new WP_Error('invalid_quote', 'Invalid conversion quote response from API');
        }

        return $response;
    }

    /**
     * API POST wrapper - Swagger/OpenAPI compliant
     */
    private function api_post($path, $body = array()) {
        $url = rtrim($this->api_base_url, '/') . $path;

        $args = array(
            'headers' => array(
                'x-api-key' => $this->api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => wp_json_encode($body),
            'timeout' => 30,
            'method' => 'POST',
        );

        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $decoded = json_decode($response_body, true);

        if ($code < 200 || $code >= 300) {
            $error_msg = 'HTTP ' . $code;
            if (is_array($decoded) && !empty($decoded['message'])) {
                $error_msg .= ' - ' . $decoded['message'];
            } elseif (!empty($response_body)) {
                $error_msg .= ' - ' . $response_body;
            }
            return new WP_Error('nicky_api_error', $error_msg);
        }

        // Return decoded response (can be array or null)
        return is_null($decoded) ? array() : $decoded;
    }

    /**
     * API GET wrapper - Swagger/OpenAPI compliant
     */
    private function api_get($path, $query = array()) {
        $url = rtrim($this->api_base_url, '/') . $path;
        if (!empty($query)) {
            $url = add_query_arg($query, $url);
        }

        $args = array(
            'headers' => array(
                'x-api-key' => $this->api_key,
                'Accept' => 'application/json',
            ),
            'timeout' => 30,
            'method' => 'GET',
        );

        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $decoded = json_decode($response_body, true);

        if ($code < 200 || $code >= 300) {
            $error_msg = 'HTTP ' . $code;
            if (is_array($decoded) && !empty($decoded['message'])) {
                $error_msg .= ' - ' . $decoded['message'];
            } elseif (!empty($response_body)) {
                $error_msg .= ' - ' . $response_body;
            }
            return new WP_Error('nicky_api_error', $error_msg);
        }

        // Return decoded response (can be array or null)
        return is_null($decoded) ? array() : $decoded;
    }

    /**
     * API POST wrapper with explicit API key - for webhook management
     */
    private function api_post_with_key($path, $body = array(), $api_key = '') {
        $url = rtrim($this->api_base_url, '/') . $path;

        $args = array(
            'headers' => array(
                'x-api-key' => $api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => wp_json_encode($body),
            'timeout' => 30,
            'method' => 'POST',
        );

        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $decoded = json_decode($response_body, true);

        if ($code < 200 || $code >= 300) {
            $error_msg = 'HTTP ' . $code;
            if (is_array($decoded) && !empty($decoded['message'])) {
                $error_msg .= ' - ' . $decoded['message'];
            } elseif (!empty($response_body)) {
                $error_msg .= ' - ' . $response_body;
            }
            return new WP_Error('nicky_api_error', $error_msg);
        }

        // Return decoded response (can be array or null)
        return is_null($decoded) ? array() : $decoded;
    }

    /**
     * API GET wrapper with explicit API key - for webhook management
     */
    private function api_get_with_key($path, $query = array(), $api_key = '') {
        $url = rtrim($this->api_base_url, '/') . $path;
        if (!empty($query)) {
            $url = add_query_arg($query, $url);
        }

        $args = array(
            'headers' => array(
                'x-api-key' => $api_key,
                'Accept' => 'application/json',
            ),
            'timeout' => 30,
            'method' => 'GET',
        );

        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $decoded = json_decode($response_body, true);

        if ($code < 200 || $code >= 300) {
            $error_msg = 'HTTP ' . $code;
            if (is_array($decoded) && !empty($decoded['message'])) {
                $error_msg .= ' - ' . $decoded['message'];
            } elseif (!empty($response_body)) {
                $error_msg .= ' - ' . $response_body;
            }
            return new WP_Error('nicky_api_error', $error_msg);
        }

        // Return decoded response (can be array or null)
        return is_null($decoded) ? array() : $decoded;
    }

    /**
     * Webhook handler for payment notifications
     * Endpoint: ?wc-api=wc_gateway_nicky
     * Handles: PaymentRequest_StatusChanged webhook from Nicky API
     */
    public function webhook_handler() {
        // Log webhook call for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook Handler called. Method: ' . sanitize_key(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '')); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('Nicky Webhook GET params: ' . print_r(array_map('sanitize_text_field', $_GET), true)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r
            error_log('Nicky Webhook POST params: ' . print_r(array_map('sanitize_text_field', $_POST), true)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r
        }
        
        $raw_body = file_get_contents('php://input');
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook raw body: ' . $raw_body); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
        
        $data = json_decode($raw_body, true);

        // If JSON decode fails, try to get data from GET/POST
        if (empty($data)) {
            $data = array_map('sanitize_text_field', $_GET + $_POST); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        // Log parsed data
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook parsed data: ' . print_r($data, true)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r
        }

        // Validate webhook type (support both formats: webHookType and WebHookType)
        $webhook_type = $data['WebHookType'] ?? $data['webHookType'] ?? $data['webhookType'] ?? '';

        if (empty($webhook_type)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Webhook error: missing_webhook_type');
            }
            status_header(400);
            echo 'missing_webhook_type';
            exit;
        }
        
        error_log('Nicky Webhook type: ' . $webhook_type);

        if ($webhook_type !== 'PaymentRequest_StatusChanged') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Webhook: ignored (type: ' . $webhook_type . ')');
            }
            status_header(200);
            echo 'ignored';
            exit;
        }

        // Extract itemId (PaymentRequest ID) from webhook payload (support both formats)
        $payment_request_id = $data['ItemId'] ?? $data['itemId'] ?? '';
        
        if (empty($payment_request_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Webhook error: missing_item_id');
            }
            status_header(400);
            echo 'missing_item_id';
            exit;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook PaymentRequest ID: ' . $payment_request_id);
        }

        // Get status change data (support both formats: Data and data)
        $webhook_data = $data['Data'] ?? $data['data'] ?? array();
        $previous_status = $webhook_data['PreviousStatus'] ?? $webhook_data['previousStatus'] ?? '';
        $new_status = $webhook_data['NewStatus'] ?? $webhook_data['newStatus'] ?? '';
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook status change: ' . $previous_status . ' → ' . $new_status);
        }

        // If NewStatus is "Finished", we can directly complete the payment without additional API call
        if ($new_status === 'Finished') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Webhook: Payment finished, fetching details from API');
            }
            
            // Query API to get shortId for finding the order
            $api_resp = $this->api_get('/api/public/PaymentRequestPublicApi/get-by-id', array('id' => $payment_request_id));
            
            if (is_wp_error($api_resp)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Nicky Webhook error: API error - ' . $api_resp->get_error_message());
                }
                status_header(500);
                echo 'api_error';
                exit;
            }

            $short_id = $api_resp['bill']['shortId'] ?? '';
            if (empty($short_id)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Nicky Webhook error: missing shortId in API response');
                }
                status_header(500);
                echo 'missing_short_id';
                exit;
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Webhook: Found shortId: ' . $short_id);
            }

            // Find order by shortId
            $orders = wc_get_orders(array(
                'limit' => 1,
                'meta_key' => '_nicky_short_id',
                'meta_value' => $short_id,
            ));

            if (empty($orders)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Nicky Webhook error: Order not found for shortId: ' . $short_id);
                }
                status_header(404);
                echo 'order_not_found';
                exit;
            }

            $order = $orders[0];
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Webhook: Found order #' . $order->get_id());
            }

            // Complete the payment
            if (!$order->is_paid()) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Nicky Webhook: Completing payment for order #' . $order->get_id());
                }
                $order->payment_complete();
                $order->add_order_note(sprintf(
                    'Payment completed via Nicky Webhook. PaymentRequest ID: %s, Status: %s → %s, ShortId: %s',
                    $payment_request_id,
                    $previous_status,
                    $new_status,
                    $short_id
                ));
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Nicky Webhook: Order #' . $order->get_id() . ' already paid');
                }
            }

            status_header(200);
            echo 'payment_completed';
            exit;
        }

        // For other statuses, get full details and process accordingly
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook: Processing status change, fetching details from API');
        }
        $api_resp = $this->api_get('/api/public/PaymentRequestPublicApi/get-by-id', array('id' => $payment_request_id));
        
        if (is_wp_error($api_resp)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Webhook error: API error - ' . $api_resp->get_error_message());
            }
            status_header(500);
            echo 'api_error';
            exit;
        }

        // Extract bill shortId and payment details from API response
        $short_id = $api_resp['bill']['shortId'] ?? '';
        $status = $api_resp['status'] ?? '';
        $open_amount = $api_resp['openAmountNative'] ?? null;

        if (empty($short_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Webhook error: missing shortId in API response');
            }
            status_header(500);
            echo 'missing_short_id';
            exit;
        }
        
        error_log('Nicky Webhook: Found shortId: ' . $short_id . ', Status: ' . $status);

        // Find order by shortId
        $orders = wc_get_orders(array(
            'limit' => 1,
            'meta_key' => '_nicky_short_id',
            'meta_value' => $short_id,
        ));

        if (empty($orders)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Webhook error: Order not found for shortId: ' . $short_id);
            }
            status_header(404);
            echo 'order_not_found';
            exit;
        }

        $order = $orders[0];
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook: Found order #' . $order->get_id());
        }

        // Process other payment statuses
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook: Processing status: ' . $status . ' for order #' . $order->get_id());
        }
        switch ($status) {
            case 'Finished':
                // This should not happen since we handle it above, but keep for safety
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Nicky Webhook: Handling Finished status (fallback)');
                }
                if (!$order->is_paid()) {
                    $order->payment_complete();
                    $order->add_order_note(sprintf(
                        'Payment completed via Nicky Webhook. ShortId: %s, Status: %s → %s, Open Amount: %s',
                        $short_id,
                        $previous_status,
                        $new_status,
                        $open_amount
                    ));
                }
                break;
                
            case 'Canceled':
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Nicky Webhook: Handling Canceled status');
                }
                if (!$order->is_paid()) {
                    $order->update_status('cancelled', sprintf(
                        'Payment cancelled via Nicky (shortId: %s)',
                        $short_id
                    ));
                }
                break;
                
            case 'PaymentValidationRequired':
                error_log('Nicky Webhook: Handling PaymentValidationRequired status');
                $order->update_status('on-hold', sprintf(
                    'Payment validation required (shortId: %s)',
                    $short_id
                ));
                // Set validation flag for dashboard widget (HPOS compatible)
                $order->update_meta_data('_nicky_requires_validation', 'yes');
                $order->save();
                break;
                
            default:
                error_log('Nicky Webhook: Handling unknown status: ' . $status);
                $order->add_order_note(sprintf(
                    'Nicky status update: %s → %s (shortId: %s)',
                    $previous_status,
                    $new_status,
                    $short_id
                ));
        }

        error_log('Nicky Webhook: Successfully processed webhook');
        status_header(200);
        echo 'ok';
        exit;
    }

    /**
     * Poll PaymentRequest status by shortId (for frontend status checking)
     * Uses Swagger endpoint: GET /api/public/PaymentRequestPublicApi/get-by-short-id
     */
    public function poll_order_by_short_id($short_id) {
        // Use get-by-short-id endpoint (according to Swagger spec)
        $api_resp = $this->api_get('/api/public/PaymentRequestPublicApi/get-by-short-id', array('shortId' => $short_id));
        
        if (is_wp_error($api_resp)) {
            return $api_resp;
        }

        // Extract status from PaymentRequest response
        $status = $api_resp['status'] ?? '';
        $open_amount = $api_resp['openAmountNative'] ?? null;
        $bill_short_id = $api_resp['bill']['shortId'] ?? $short_id;

        $orders = wc_get_orders(array(
            'limit' => 1, 
            'meta_key' => '_nicky_short_id', 
            'meta_value' => $bill_short_id
        ));
        
        if (empty($orders)) {
            return new WP_Error('order_not_found', 'Order not found for shortId ' . $short_id);
        }

        $order = $orders[0];
        
        // Process based on status
        // When status is 'Finished', the payment is considered complete regardless of openAmount
        switch ($status) {
            case 'Finished':
                if (!$order->is_paid()) {
                    $order->payment_complete();
                    $order->add_order_note(sprintf(
                        'Payment completed via Nicky (polled). ShortId: %s, Status: %s, Open Amount: %s',
                        $short_id,
                        $status,
                        $open_amount
                    ));
                }
                break;
                
            case 'Canceled':
                if (!$order->is_paid()) {
                    $order->update_status('cancelled', sprintf(
                        'Payment cancelled (shortId: %s)',
                        $short_id
                    ));
                }
                break;
                
            case 'PaymentValidationRequired':
                if ($order->get_status() !== 'on-hold') {
                    $order->update_status('on-hold', sprintf(
                        'Payment validation required (shortId: %s)',
                        $short_id
                    ));
                }
                // Set validation flag for dashboard widget (HPOS compatible)
                $order->update_meta_data('_nicky_requires_validation', 'yes');
                $order->save();
                break;
        }

        // Refresh order to get updated status
        $order = wc_get_order($order->get_id());
        
        return array(
            'status' => $status,
            'is_paid' => $order->is_paid(),
            'open_amount' => $open_amount
        );
    }

    /**
     * AJAX handler to check payment status (for frontend polling)
     * Called from checkout-handler.js
     */
    public function ajax_check_payment_status() {
        check_ajax_referer('nicky_payment_nonce', 'nonce');
        
        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Order ID missing');
            return;
        }
        
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error('Order not found');
            return;
        }
        
        $short_id = $order->get_meta('_nicky_short_id', true);
        if (!$short_id) {
            wp_send_json_error('No payment ID found');
            return;
        }
        
        // Note: Frontend polling is disabled - this AJAX handler is kept for manual checks only
        
        // Poll status using get-by-short-id endpoint
        // This method also updates the WooCommerce order status automatically
        $result = $this->poll_order_by_short_id($short_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error('API error: ' . $result->get_error_message());
            return;
        }
        
        $status = $result['status'] ?? '';
        $is_paid = $result['is_paid'] ?? false;
        $open_amount = $result['open_amount'] ?? null;
        
        // Refresh order object to get latest status (poll_order_by_short_id may have updated it)
        $order = wc_get_order($order_id);
        $order_status = $order->get_status();
        
        wp_send_json_success(array(
            'status' => $is_paid ? 'completed' : 'pending',
            'nicky_status' => $status,
            'order_status' => $order_status,
            'is_paid' => $is_paid,
            'open_amount' => $open_amount,
            'note' => 'Backend cron handles automatic status updates'
        ));
    }
    
    /**
     * Output for the order received page (Thank you page)
     * Shows payment instructions and deposit address
     */
    public function thankyou_page($order_id) {
        if (!$order_id) return;
        
        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== $this->id) return;
        
        $short_id = $order->get_meta('_nicky_short_id', true);
        $blockchain_asset_id = $order->get_meta('_nicky_blockchain_asset_id', true);
        
        if ($order->has_status('pending')) {
            echo '<div class="nicky-payment-pending" style="background: #f7f7f7; padding: 20px; margin: 20px 0; border-left: 4px solid #2271b1;">';
            echo '<h3 style="margin-top: 0;">⏳ ' . esc_html(__('Payment Pending', 'nicky-me')) . '</h3>';
            echo '<p>' . esc_html(__('Your payment is being processed. Please complete your payment to finalize your order.', 'nicky-me')) . '</p>';
            
            if ($short_id) {
                echo '<div style="background: white; padding: 15px; margin: 15px 0; border-radius: 4px;">';
                echo '<p style="margin: 0;"><strong>' . esc_html(__('Payment ID:', 'nicky-me')) . '</strong> <code style="background: #f0f0f0; padding: 2px 6px;">' . esc_html($short_id) . '</code></p>';
                echo '</div>';
                
                // Try to get deposit address
                if (!empty($blockchain_asset_id)) {
                    $address_resp = $this->api_get('/deposit-address', array('assetId' => $blockchain_asset_id));
                    
                    if (!is_wp_error($address_resp) && !empty($address_resp['address'])) {
                        $address = $address_resp['address'];
                        $asset_ticker = $address_resp['assetTicker'] ?? $blockchain_asset_id;
                        
                        echo '<div style="background: #e8f5e9; padding: 15px; margin: 15px 0; border-radius: 4px; border: 1px solid #4caf50;">';
                        echo '<h4 style="margin-top: 0; color: #2e7d32;">💰 ' . esc_html(__('Deposit Address', 'nicky-me')) . '</h4>';
                        echo '<p><strong>' . esc_html(__('Asset:', 'nicky-me')) . '</strong> ' . esc_html($asset_ticker) . '</p>';
                        echo '<p><strong>' . esc_html(__('Address:', 'nicky-me')) . '</strong><br>';
                        echo '<code style="background: white; padding: 8px; display: inline-block; margin: 5px 0; word-break: break-all; font-size: 12px;">' . esc_html($address) . '</code></p>';
                        echo '<button type="button" onclick="navigator.clipboard.writeText(\'' . esc_js($address) . '\'); alert(\'Address copied to clipboard!\');" class="button" style="margin-top: 10px;">📋 ' . esc_html(__('Copy Address', 'nicky-me')) . '</button>';
                        echo '</div>';
                    }
                }
                
                echo '<p style="margin-top: 20px;"><a href="https://pay.nicky.me/home?paymentId=' . urlencode($short_id) . '" target="_blank" class="button button-primary" style="text-decoration: none;">';
                echo '🔗 ' . esc_html(__('Continue to Payment', 'nicky-me')) . '</a></p>';
            }
            
            echo '<p style="margin-top: 20px; font-size: 13px; color: #666;">';
            echo '🔄 ' . esc_html(__('Payment status is automatically checked every 30 seconds by our backend system. Please refresh this page to see updates.', 'nicky-me'));
            echo '</p>';
            echo '</div>';
            
            // Frontend polling disabled - all handled by backend cron job
        } elseif ($order->has_status('completed') || $order->has_status('processing')) {
            echo '<div class="nicky-payment-complete" style="background: #e8f5e9; padding: 20px; margin: 20px 0; border-left: 4px solid #4caf50;">';
            echo '<h3 style="margin-top: 0; color: #2e7d32;">✅ ' . esc_html(__('Payment Complete', 'nicky-me')) . '</h3>';
            echo '<p>' . esc_html(__('Thank you! Your payment has been received and your order is being processed.', 'nicky-me')) . '</p>';
            if ($short_id) {
                echo '<p style="font-size: 13px; color: #666;"><strong>' . esc_html(__('Payment ID:', 'nicky-me')) . '</strong> ' . esc_html($short_id) . '</p>';
            }
            echo '</div>';
        }
    }
    
    /**
     * Add content to the WC emails.
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false) {
        if (!$sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status('pending')) {
            $short_id = get_post_meta($order->get_id(), '_nicky_short_id', true);
            
            if ($plain_text) {
                echo "\n" . esc_html(__('Payment Instructions:', 'nicky-me')) . "\n";
                echo esc_html(__('Your cryptocurrency payment is being processed.', 'nicky-me')) . "\n";
                if ($short_id) {
                    echo esc_html(__('Payment ID:', 'nicky-me')) . ' ' . esc_html($short_id) . "\n";
                    $payment_url = 'https://pay.nicky.me/home?paymentId=' . urlencode($short_id);
                    echo esc_html(__('You can check the status at:', 'nicky-me')) . ' ' . esc_url($payment_url) . "\n";
                }
                echo "\n";
            } else {
                echo '<h2>' . esc_html(__('Payment Instructions', 'nicky-me')) . '</h2>';
                echo '<p>' . esc_html(__('Your cryptocurrency payment is being processed.', 'nicky-me')) . '</p>';
                if ($short_id) {
                    echo '<p><strong>' . esc_html(__('Payment ID:', 'nicky-me')) . '</strong> ' . esc_html($short_id) . '</p>';
                    $payment_url = 'https://pay.nicky.me/home?paymentId=' . urlencode($short_id);
                    echo '<p><a href="' . esc_url($payment_url) . '" target="_blank">';
                    echo esc_html(__('Check Payment Status', 'nicky-me')) . '</a></p>';
                }
            }
        }
    }

    /**
     * Add custom cron schedule intervals
     */
    public function add_cron_schedules($schedules) {
        $schedules['every_30_seconds'] = array(
            'interval' => 30,
            'display' => __('Every 30 Seconds', 'nicky-me')
        );
        return $schedules;
    }

    /**
     * Schedule WordPress Cron job for automatic payment status checking
     * Runs every 30 seconds to check pending Nicky payments
     */
    public function schedule_payment_status_cron() {
        // Only schedule if gateway is enabled
        if ($this->enabled !== 'yes') {
            return;
        }

        // Check if WP Cron is disabled
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Gateway: WARNING - WP Cron is disabled (DISABLE_WP_CRON = true). Cron job will not run.');
            }
        }

        $next_scheduled = wp_next_scheduled('nicky_check_payment_status_cron');
        if (!$next_scheduled) {
            // Schedule to run every 30 seconds
            $scheduled = wp_schedule_event(time(), 'every_30_seconds', 'nicky_check_payment_status_cron');
            if ($scheduled === false) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Nicky Gateway: ERROR - Failed to schedule cron job');
                }
            }
        }
    }

    /**
     * Cron job handler to check payment status for pending orders
     * Checks all pending Nicky orders and updates their status
     * Runs every 30 seconds - this is the primary method for status updates
     */
    public function cron_check_payment_status() {

        // Find all orders with pending Nicky payments
        // Look for orders that are 'pending' and have Nicky short_id
        $query_args = array(
            'limit' => 50, // Limit to avoid performance issues
            'status' => array('pending', 'on-hold'),
            'payment_method' => $this->id,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_nicky_short_id',
                    'compare' => 'EXISTS'
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_nicky_last_cron_check',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => '_nicky_last_cron_check',
                        'value' => time() - 60, // Check orders not checked in last 1 minute (more frequent)
                        'compare' => '<',
                        'type' => 'NUMERIC'
                    )
                )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $orders = wc_get_orders($query_args);
        
        if (empty($orders)) {
            return;
        }

        foreach ($orders as $order) {
            $short_id = $order->get_meta('_nicky_short_id', true);
            
            if (empty($short_id)) {
                continue;
            }

            // Update last check timestamp to avoid duplicate checks (HPOS compatible)
            $order->update_meta_data('_nicky_last_cron_check', time());
            $order->save();

            // Check payment status via API
            $result = $this->poll_order_by_short_id($short_id);

            if (is_wp_error($result)) {
                continue;
            }

            $status = $result['status'] ?? '';
            $is_paid = $result['is_paid'] ?? false;

            // Update order if payment is finished
            if ($status === 'Finished' && $is_paid) {
                $order->payment_complete();
                $order->update_status('processing', __('Payment completed via Nicky (backend cron)', 'nicky-me'));
                $order->add_order_note(sprintf(
                    'Payment completed via backend status check (ShortId: %s)',
                    $short_id
                ));
            }
        }
    }




}
