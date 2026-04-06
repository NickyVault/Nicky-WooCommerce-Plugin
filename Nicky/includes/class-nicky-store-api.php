<?php
/**
 * WooCommerce Blocks Store API Integration for Nicky Payment Gateway
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Nicky Gateway with WooCommerce Store API
 */
class Nicky_Store_API_Integration {
    
    public function __construct() {
        add_action('woocommerce_blocks_loaded', array($this, 'register_payment_method_type'));
        add_filter('woocommerce_store_api_checkout_update_order_from_request', array($this, 'update_order_from_request'), 10, 2);
        // Removed: This hook caused duplicate payment requests. Payment processing is handled by woocommerce_rest_checkout_process_payment_with_context in main plugin file.
        // add_filter('woocommerce_store_api_checkout_order_processed', array($this, 'process_payment_for_order'), 10, 1);
    }
    
    /**
     * Register payment method type with blocks
     */
    public function register_payment_method_type() {
        if (!class_exists('Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema')) {
            return;
        }
        
        // Ensure our gateway is available for the Store API
        add_filter('woocommerce_available_payment_gateways', array($this, 'make_gateway_available_for_api'));
    }
    
    /**
     * Ensure gateway is available for Store API requests
     */
    public function make_gateway_available_for_api($gateways) {
        if ($this->is_store_api_request()) {
            if (class_exists('Nicky_WC_Gateway_Nicky') && !isset($gateways['nicky'])) {
                $nicky_gateway = new Nicky_WC_Gateway_Nicky();
                if ($nicky_gateway->enabled === 'yes') {
                    $gateways['nicky'] = $nicky_gateway;
                }
            }
        }
        return $gateways;
    }
    
    /**
     * Update order from Store API request
     */
    public function update_order_from_request($order, $request) {
        if (isset($request['payment_method']) && $request['payment_method'] === 'nicky') {
            $order->set_payment_method('nicky');
            $order->set_payment_method_title('Nicky Payment');
        }
        return $order;
    }
    
    /**
     * Check if this is a Store API request
     */
    private function is_store_api_request() {
        return defined('REST_REQUEST') && REST_REQUEST && 
               isset($_SERVER['REQUEST_URI']) && 
               strpos(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])), '/wc/store/') !== false;
    }
}

// Initialize the Store API integration
new Nicky_Store_API_Integration();