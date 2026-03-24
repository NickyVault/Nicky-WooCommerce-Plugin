<?php
if (!defined('ABSPATH')) {
    exit;
}

class Nicky_WC_Gateway_Nicky extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'nicky';
        $this->icon               = $this->get_gateway_icon();
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

        $this->init_form_fields();
        $this->init_settings();

        $this->title                = $this->get_option('title', 'Nicky.me Payment');
        $this->description          = $this->get_option('description', 'Pay securely with crypto via Nicky.me.');
        $this->enabled              = $this->get_option('enabled', 'no');
        
        $this->api_base_url         = $this->get_option('api_base_url', 'https://api-public.pay.nicky.me');
        $this->api_key              = $this->get_option('api_key', '');
        $this->blockchain_asset_id  = $this->get_option('blockchain_asset_id', '');
        $this->settlement_currency  = $this->get_option('settlement_currency', '');
        
        if (empty($this->blockchain_asset_id) && !empty($this->settlement_currency)) {
            $this->blockchain_asset_id = $this->settlement_currency;
        }

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'webhook_handler'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        add_action('wp_ajax_nickym_check_nicky_payment_status', array($this, 'ajax_check_payment_status'));
        add_action('wp_ajax_nopriv_nickym_check_nicky_payment_status', array($this, 'ajax_check_payment_status'));
        
        add_action('woocommerce_blocks_loaded', array($this, 'register_block_support'));
        
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        
        add_action('woocommerce_order_status_cancelled', array($this, 'handle_order_cancellation'));
        add_action('woocommerce_order_status_pending_to_cancelled', array($this, 'handle_order_cancellation'));
        add_action('woocommerce_order_status_on-hold_to_cancelled', array($this, 'handle_order_cancellation'));
    }
    
    public function register_block_support() {
        if (class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            add_filter('woocommerce_blocks_payment_method_type_registration', array($this, 'add_block_support'));
        }
    }
    
    public function add_block_support($payment_method_registry) {
        return $payment_method_registry;
    }

    public function is_available() {
        if (!parent::is_available()) {
            return false;
        }

        if ('yes' !== $this->enabled) {
            return false;
        }

        if (!is_admin() && !defined('DOING_AJAX') && WC()->cart && WC()->cart->is_empty()) {
            return false;
        }

        if (empty($this->api_key)) {
            return false;
        }
        
        if (empty($this->blockchain_asset_id)) {
            if (!empty($this->settlement_currency)) {
                $this->blockchain_asset_id = $this->settlement_currency;
            } else {
                return false;
            }
        }

        return true;
    }

    public function get_gateway_icon() {
        $custom_logo = $this->get_option('custom_logo');
        if (!empty($custom_logo)) {
            return esc_url($custom_logo);
        }
        
        $logo_png_path = NICKY_PAYMENT_GATEWAY_PLUGIN_PATH . 'assets/images/logo.png';
        $logo_png_url = NICKY_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/images/logo.png';
        
        if (file_exists($logo_png_path)) {
            return $logo_png_url;
        }
        
        $logo_svg_path = NICKY_PAYMENT_GATEWAY_PLUGIN_PATH . 'assets/images/logo.svg';
        $logo_svg_url = NICKY_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/images/logo.svg';
        
        if (file_exists($logo_svg_path)) {
            return $logo_svg_url;
        }
        
        return '';
    }

    public function process_admin_options() {
        $old_api_key = $this->get_option('api_key', '');
        
        $result = parent::process_admin_options();
        
        $new_api_key = $this->get_option('api_key', '');
        
        if (empty($old_api_key) && !empty($new_api_key)) {
            $this->create_payment_status_webhook();
            
            $this->update_option('blockchain_asset_id', '');
        }
        elseif (!empty($old_api_key) && empty($new_api_key)) {
            $this->delete_payment_status_webhook();
            
            $this->update_option('blockchain_asset_id', '');
        }
        elseif (!empty($old_api_key) && !empty($new_api_key) && $old_api_key !== $new_api_key) {
            $this->update_option('blockchain_asset_id', '');
        }
        
        return $result;
    }

    private function create_payment_status_webhook() {
        $api_key = $this->get_option('api_key', '');
        if (empty($api_key)) {
            return false;
        }
        
        $existing_webhook_id = get_option('nicky_webhook_id', '');
        if (!empty($existing_webhook_id)) {
            if ($this->verify_webhook_exists($existing_webhook_id)) {
                return true;
            } else {
                delete_option('nicky_webhook_id');
            }
        }
        
        $webhook_url = $this->get_webhook_url();
        
        $body = array(
            'webHookType' => 'PaymentRequest_StatusChanged',
            'url' => $webhook_url
        );
        
        $response = $this->api_post_with_key('/api/public/WebHookApi/create', $body, $api_key);
        
        if (is_wp_error($response)) {
            add_action('admin_notices', function() use ($response) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>Nicky.me:</strong> Webhook creation failed: ' . esc_html($response->get_error_message()) . '</p>';
                echo '</div>';
            });
            return false;
        }
        
        if (!empty($response['id'])) {
            $webhook_id = $response['id'];
            update_option('nicky_webhook_id', $webhook_id);
            
            add_action('admin_notices', function() use ($webhook_id) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>Nicky.me:</strong> Payment status webhook created successfully (ID: ' . esc_html($webhook_id) . ')</p>';
                echo '</div>';
            });
            
            return true;
        }
        
        return false;
    }
    
    private function verify_webhook_exists($webhook_id) {
        $api_key = $this->get_option('api_key', '');
        if (empty($api_key)) {
            return false;
        }
        
        $response = $this->api_get_with_key('/api/public/WebHookApi/list', array(), $api_key);
        
        if (is_wp_error($response) || !is_array($response)) {
            return false;
        }
        
        foreach ($response as $webhook) {
            if (isset($webhook['id']) && $webhook['id'] === $webhook_id) {
                return true;
            }
        }
        
        return false;
    }
    
    private function delete_payment_status_webhook() {
        $webhook_id = get_option('nicky_webhook_id', '');
        
        if (empty($webhook_id)) {
            return false;
        }
        
        $api_key = $this->get_option('api_key', '');
        if (empty($api_key)) {
            $api_key = sanitize_text_field(wp_unslash($_POST['woocommerce_nicky_api_key'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }
        
        if (empty($api_key)) {
            return false;
        }
        
        $response = $this->api_post_with_key('/api/public/WebHookApi/delete?id=' . urlencode($webhook_id), array(), $api_key);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        delete_option('nicky_webhook_id');
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>Nicky.me:</strong> Payment status webhook was deleted.</p>';
            echo '</div>';
        });
        
        return true;
    }
    
    private function get_webhook_url() {
        return add_query_arg('wc-api', 'wc_gateway_nicky', home_url('/'));
    }

    public function handle_order_cancellation($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        if ($order->get_payment_method() !== 'nicky') {
            return;
        }
        
        $short_id = $order->get_meta('_nicky_short_id', true);
        
        if (empty($short_id)) {
            $order->add_order_note('Could not cancel Nicky payment - no payment ID found');
            return;
        }
        
        $result = $this->cancel_payment_request($short_id);
        
        if (is_wp_error($result)) {
            $error_message = sanitize_text_field($result->get_error_message());
            $order->add_order_note(sprintf(
                'Failed to cancel Nicky payment (shortId: %s): %s',
                sanitize_text_field($short_id),
                $error_message
            ));
        } elseif ($result === true) {
            $order->add_order_note(sprintf(
                'Nicky payment cancelled successfully (shortId: %s)',
                sanitize_text_field($short_id)
            ));
        } else {
            $order->add_order_note(sprintf(
                'Nicky payment cancellation response (shortId: %s): %s',
                sanitize_text_field($short_id),
                is_array($result) ? wp_json_encode($result) : $result
            ));
        }
    }
    
    private function cancel_payment_request($short_id) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'No API key configured');
        }
        
        if (empty($short_id)) {
            return new WP_Error('no_short_id', 'No shortId provided');
        }
        
        $response = $this->api_post('/api/public/PaymentRequestPublicApi/cancel?shortId=' . urlencode($short_id), array());
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if ($response === true || $response === 'true' || (is_array($response) && !empty($response))) {
            return true;
        }
        
        return new WP_Error('cancel_failed', 'Payment cancellation returned unexpected result: ' . wp_json_encode($response));
    }

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

    private function get_blockchain_asset_options() {
        $options = array(
            '' => 'Select blockchain asset...'
        );

        $current_api_url = $this->get_option('api_base_url', 'https://api-public.pay.nicky.me');
        
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
            $options['BTC.BTC'] = 'Bitcoin (BTC) - BTC';
            $options['ETH.ETH'] = 'Ether (ETH) - ETH';
            $options['USD.USD'] = 'US Dollar (USD) - USD';
            $options['EUR.EUR'] = 'Euro (EUR) - EUR';
        }

        return $options;
    }

    private function get_blockchain_asset_field_config() {
        $api_key = $this->get_option('api_key', '');
        
        if (empty($api_key)) {
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

    private function fetch_blockchain_assets_for_admin($api_base_url) {
        $api_url = rtrim($api_base_url, '/') . '/AcceptedAsset/get-for-user';
        
        $api_key = $this->get_option('api_key', '');
        
        $response = wp_remote_get($api_url, [
            'timeout' => 10,
            'headers' => [
                'accept' => 'text/plain',
                'x-api-key' => $api_key
            ]
        ]);
        
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
        
        return $assets;
    }

    public function enqueue_scripts() {
        if (!is_admin() && is_checkout()) {
            wp_enqueue_style(
                'nicky-payment-gateway-style',
                NICKY_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/css/checkout.css',
                array(),
                NICKY_PAYMENT_GATEWAY_VERSION
            );

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

    public function admin_enqueue_scripts($hook_suffix) {
        if (!is_admin()) {
            return;
        }
        
        if (strpos($hook_suffix, 'wc-settings') === false && 
            (!isset($_GET['page']) || sanitize_key($_GET['page']) !== 'wc-settings')) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking current admin page, not processing form data
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
    }



    public function payment_fields() {
        if ($this->description) {
            echo esc_html(wpautop(wp_kses_post($this->description)));
        }

        echo '<div class="nicky-payment-info" style="margin-top: 15px; padding: 10px; background: #f0f8ff; border-left: 4px solid #0073aa; border-radius: 4px;">';
        echo '<p class="nicky-info-text" style="margin: 0; font-size: 14px;">';
        echo '🔒 ' . esc_html(__('You will be redirected to Nicky.me to complete your payment securely.', 'nicky-me'));
        echo '</p>';
        echo '</div>';
        
        echo '<div class="nicky-supported-methods" style="margin-top: 10px; text-align: center;">';
        echo '<small style="color: #666;">💰 ' . esc_html(__('Supported: Bitcoin, Ethereum, USDT and more cryptocurrencies', 'nicky-me')) . '</small>';
        echo '</div>';
    }

    public function validate_fields() {
        return true;
    }

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
        
        $payer_email = $order->get_billing_email();
        $payer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        
        $selected_asset_id = $this->blockchain_asset_id;
        
        if (!empty($selected_asset_id) && strpos($selected_asset_id, '.') === false) {
            $selected_asset_id = $selected_asset_id . '.' . $selected_asset_id;
        }
        
        $amount_shop_currency = floatval($order->get_total());
        $shop_currency = get_woocommerce_currency();
        
        $conversion_quote = $this->get_conversion_quote($amount_shop_currency, $selected_asset_id);
        
        if (is_wp_error($conversion_quote)) {
            $error_message = sprintf(
                'Currency conversion failed: %s. Please try again or contact support.',
                sanitize_text_field($conversion_quote->get_error_message())
            );
            $order->add_order_note('Nicky conversion error: ' . sanitize_text_field($conversion_quote->get_error_message()));
            wc_add_notice($error_message, 'error');
            return array('result' => 'fail');
        }
        
        $amount_crypto = isset($conversion_quote['price']) ? floatval($conversion_quote['price']) : 0.0;
        $quote_id = isset($conversion_quote['id']) ? sanitize_text_field($conversion_quote['id']) : null;
        
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
            $order->add_order_note('Nicky API error: ' . sanitize_text_field($resp->get_error_message()));
            wc_add_notice('Payment error: could not create payment request.', 'error');
            return array('result' => 'fail');
        }

        $payment_request_id = '';
        $short_id = '';
        
        if (!empty($resp['id'])) {
            $payment_request_id = sanitize_text_field($resp['id']);
        }
        
        if (!empty($resp['bill']['shortId'])) {
            $short_id = sanitize_text_field($resp['bill']['shortId']);
        }

        if (empty($short_id)) {
            $order->add_order_note('Nicky PaymentRequest response missing bill.shortId: ' . sanitize_text_field(wp_json_encode($resp)));
            wc_add_notice('Payment error: invalid response from payment provider.', 'error');
            return array('result' => 'fail');
        }

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
            sanitize_text_field($short_id),
            sanitize_text_field($payment_request_id),
            sanitize_text_field($selected_asset_id),
            floatval($amount_crypto),
            sanitize_text_field($selected_asset_id),
            floatval($amount_shop_currency),
            sanitize_text_field($shop_currency),
            sanitize_text_field($quote_id)
        ));
        
        $order->update_status('on-hold', sprintf('Awaiting Nicky payment (shortId: %s)', sanitize_text_field($short_id)));

        WC()->cart->empty_cart();

        $redirect_url = 'https://pay.nicky.me/home?paymentId=' . urlencode($short_id);

        return array(
            'result' => 'success',
            'redirect' => $redirect_url,
            'nicky_payment_url' => $redirect_url,
            'open_in_new_tab' => true,
        );
    }

    private function process_payment_request($order, $card_number, $card_expiry, $card_cvc) {
        return array(
            'success' => false,
            'transaction_id' => '',
            'message' => 'Card processing not supported. Use redirect flow.'
        );
    }

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

    private function get_shop_currency_asset_id() {
        $currency = get_woocommerce_currency(); // e.g., "EUR", "USD"
        return $currency . '.' . $currency;
    }

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

        if (!isset($response['price']) || !isset($response['id'])) {
            return new WP_Error('invalid_quote', 'Invalid conversion quote response from API');
        }

        return $response;
    }

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

        return is_null($decoded) ? array() : $decoded;
    }

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

        return is_null($decoded) ? array() : $decoded;
    }

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

        return is_null($decoded) ? array() : $decoded;
    }

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

        return is_null($decoded) ? array() : $decoded;
    }

    public function webhook_handler() {
        // Whitelist: only accept webhooks from Nicky.me server IP
        $allowed_ip = '20.76.240.81';
        $remote_ip  = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        if ($remote_ip !== $allowed_ip) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Webhook blocked: unauthorized IP ' . $remote_ip); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            status_header(403);
            exit;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook Handler called. Method: ' . sanitize_key(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '')); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('Nicky Webhook GET params: ' . print_r(array_map('sanitize_text_field', $_GET), true)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r,WordPress.Security.NonceVerification.Recommended -- Webhook from external service, no nonce
            error_log('Nicky Webhook POST params: ' . print_r(array_map('sanitize_text_field', $_POST), true)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r,WordPress.Security.NonceVerification.Missing -- Webhook from external service, no nonce
        }
        
        $raw_body = file_get_contents('php://input');
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $sanitized_body = sanitize_text_field(substr($raw_body, 0, 500));
            error_log('Nicky Webhook raw body: ' . $sanitized_body); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
        
        $data = json_decode($raw_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE && !empty($raw_body)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Webhook error: Invalid JSON - ' . json_last_error_msg()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
        }

        if (empty($data)) {
            $data = array_map('sanitize_text_field', $_GET + $_POST); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing -- Webhook from external service (Nicky.me API), no nonce verification needed
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook parsed data: ' . print_r($data, true)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r
        }

        $webhook_type = sanitize_key($data['WebHookType'] ?? $data['webHookType'] ?? $data['webhookType'] ?? '');

        if (empty($webhook_type)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Webhook error: missing_webhook_type'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            status_header(400);
            echo 'missing_webhook_type';
            exit;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook type: ' . $webhook_type); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }

        if ($webhook_type !== 'PaymentRequest_StatusChanged') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Webhook: ignored (type: ' . $webhook_type . ')'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            status_header(200);
            echo 'ignored';
            exit;
        }

        $payment_request_id = sanitize_text_field($data['ItemId'] ?? $data['itemId'] ?? '');
        
        if (empty($payment_request_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Webhook error: missing_item_id'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            status_header(400);
            echo 'missing_item_id';
            exit;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook PaymentRequest ID: ' . $payment_request_id); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }

        $webhook_data = $data['Data'] ?? $data['data'] ?? array();
        $previous_status = sanitize_text_field($webhook_data['PreviousStatus'] ?? $webhook_data['previousStatus'] ?? '');
        $new_status = sanitize_text_field($webhook_data['NewStatus'] ?? $webhook_data['newStatus'] ?? '');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook status change: ' . $previous_status . ' → ' . $new_status); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }

        // Always fetch full payment details from API to handle all status changes
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook: Processing status change, fetching details from API'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
        $api_resp = $this->api_get('/api/public/PaymentRequestPublicApi/get-by-id', array('id' => $payment_request_id));
        
        if (is_wp_error($api_resp)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Webhook error: API error - ' . $api_resp->get_error_message()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            status_header(500);
            echo 'api_error';
            exit;
        }

        $short_id = sanitize_text_field($api_resp['bill']['shortId'] ?? '');
        $status = sanitize_text_field($api_resp['status'] ?? '');
        $open_amount = isset($api_resp['openAmountNative']) ? floatval($api_resp['openAmountNative']) : null;

        if (empty($short_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Webhook error: missing shortId in API response'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            status_header(500);
            echo 'missing_short_id';
            exit;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook: Found shortId: ' . $short_id . ', Status: ' . $status); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }

        $orders = wc_get_orders(array(
            'limit' => 1,
            'meta_key' => '_nicky_short_id',
            'meta_value' => $short_id,
        ));

        if (empty($orders)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Nicky Webhook error: Order not found for shortId: ' . $short_id); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            status_header(404);
            echo 'order_not_found';
            exit;
        }

        $order = $orders[0];
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook: Found order #' . $order->get_id()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }

        // Process payment status - handle all statuses including for already paid orders
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook: Processing status: ' . $status . ' for order #' . $order->get_id() . ' (current order status: ' . $order->get_status() . ', is_paid: ' . ($order->is_paid() ? 'yes' : 'no') . ')'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
        
        switch ($status) {
            case 'Finished':
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Nicky Webhook: Handling Finished status'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                }
                // Always complete payment, even if already marked as paid
                if (!$order->is_paid()) {
                    $order->payment_complete();
                    $order->add_order_note(sprintf(
                        'Payment completed via Nicky Webhook. ShortId: %s, Status: %s → %s, Open Amount: %s',
                        $short_id,
                        $previous_status,
                        $new_status,
                        $open_amount
                    ));
                } else {
                    // Order already paid, just add note
                    $order->add_order_note(sprintf(
                        'Nicky Webhook received for already paid order. Status: %s → %s (shortId: %s)',
                        $previous_status,
                        $new_status,
                        $short_id
                    ));
                }
                break;
                
            case 'Canceled':
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Nicky Webhook: Handling Canceled status'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                }
                // Cancel order even if already paid (allows reversing completed orders)
                $order->update_status('cancelled', sprintf(
                    'Payment cancelled via Nicky Webhook. Previous status: %s → %s (shortId: %s)',
                    $previous_status,
                    $new_status,
                    $short_id
                ));
                break;
                
            case 'PaymentValidationRequired':
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Nicky Webhook: Handling PaymentValidationRequired status'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                }
                $order->update_status('on-hold', sprintf(
                    'Payment validation required (shortId: %s)',
                    sanitize_text_field($short_id)
                ));
                $order->update_meta_data('_nicky_requires_validation', 'yes');
                $order->save();
                break;
                
            case 'PaymentPending':
            case 'None':
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Nicky Webhook: Handling PaymentPending/None status - resetting order'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                }
                // Reset order back to pending, even if it was previously completed
                $order->update_status('pending', sprintf(
                    'Payment reset to pending via Nicky Webhook. Previous status: %s → %s (shortId: %s)',
                    $previous_status,
                    $new_status,
                    $short_id
                ));
                break;
                
            default:
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Nicky Webhook: Handling unknown status: ' . $status); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                }
                $order->add_order_note(sprintf(
                    'Nicky status update: %s → %s (shortId: %s)',
                    sanitize_text_field($previous_status),
                    sanitize_text_field($new_status),
                    sanitize_text_field($short_id)
                ));
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Webhook: Successfully processed webhook'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
        status_header(200);
        echo 'ok';
        exit;
    }

    public function poll_order_by_short_id($short_id) {
        $short_id = sanitize_text_field($short_id);
        
        $api_resp = $this->api_get('/api/public/PaymentRequestPublicApi/get-by-short-id', array('shortId' => $short_id));
        
        if (is_wp_error($api_resp)) {
            return $api_resp;
        }

        $status = sanitize_text_field($api_resp['status'] ?? '');
        $open_amount = isset($api_resp['openAmountNative']) ? floatval($api_resp['openAmountNative']) : null;
        $bill_short_id = sanitize_text_field($api_resp['bill']['shortId'] ?? $short_id);

        $orders = wc_get_orders(array(
            'limit' => 1, 
            'meta_key' => '_nicky_short_id', 
            'meta_value' => $bill_short_id
        ));
        
        if (empty($orders)) {
            return new WP_Error('order_not_found', 'Order not found for shortId ' . sanitize_text_field($short_id));
        }

        $order = $orders[0];
        
        switch ($status) {
            case 'Finished':
                if (!$order->is_paid()) {
                    $order->payment_complete();
                    $order->add_order_note(sprintf(
                        'Payment completed via Nicky (polled). ShortId: %s, Status: %s, Open Amount: %s',
                        sanitize_text_field($short_id),
                        sanitize_text_field($status),
                        $open_amount !== null ? floatval($open_amount) : 'N/A'
                    ));
                }
                break;
                
            case 'Canceled':
                if (!$order->is_paid()) {
                    $order->update_status('cancelled', sprintf(
                        'Payment cancelled (shortId: %s)',
                        sanitize_text_field($short_id)
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
                $order->update_meta_data('_nicky_requires_validation', 'yes');
                $order->save();
                break;
        }

        $order = wc_get_order($order->get_id());
        
        return array(
            'status' => $status,
            'is_paid' => $order->is_paid(),
            'open_amount' => $open_amount
        );
    }

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
        
        
        $result = $this->poll_order_by_short_id($short_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error('API error: ' . sanitize_text_field($result->get_error_message()));
            return;
        }
        
        $status = $result['status'] ?? '';
        $is_paid = $result['is_paid'] ?? false;
        $open_amount = $result['open_amount'] ?? null;
        
        $order = wc_get_order($order_id);
        $order_status = $order->get_status();
        
        wp_send_json_success(array(
            'status' => $is_paid ? 'completed' : 'pending',
            'nicky_status' => $status,
            'order_status' => $order_status,
            'is_paid' => $is_paid,
            'open_amount' => $open_amount,
            'note' => 'Webhooks handle automatic status updates'
        ));
    }
    
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
            echo '🔄 ' . esc_html(__('Payment status is automatically updated via webhooks. Please refresh this page to see updates.', 'nicky-me'));
            echo '</p>';
            echo '</div>';
        } else if ($order->has_status('completed') || $order->has_status('processing')) {
            echo '<div class="nicky-payment-complete" style="background: #e8f5e9; padding: 20px; margin: 20px 0; border-left: 4px solid #4caf50;">';
            echo '<h3 style="margin-top: 0; color: #2e7d32;">✅ ' . esc_html(__('Payment Complete', 'nicky-me')) . '</h3>';
            echo '<p>' . esc_html(__('Thank you! Your payment has been received and your order is being processed.', 'nicky-me')) . '</p>';
            if ($short_id) {
                echo '<p style="font-size: 13px; color: #666;"><strong>' . esc_html(__('Payment ID:', 'nicky-me')) . '</strong> ' . esc_html($short_id) . '</p>';
            }
            echo '</div>';
        }
    }
    
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






}
