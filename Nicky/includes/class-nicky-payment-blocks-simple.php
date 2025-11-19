<?php
/**
 * Simplified Blocks Support - Fallback Version
 * This version uses a more basic approach that should work with all WooCommerce versions
 */

if ( ! defined('ABSPATH') ) exit;

class Nicky_Payment_Simple_Blocks {
    
    public static function init() {
        // Only load if WooCommerce Blocks is available
        if (self::is_blocks_available()) {
            add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_block_scripts']);
            add_action('woocommerce_blocks_loaded', [__CLASS__, 'register_block_integration']);
        }
    }
    
    private static function is_blocks_available() {
        return function_exists('register_block_type') && 
               class_exists('WooCommerce') && 
               version_compare(WC_VERSION, '5.0', '>=');
    }
    
    public static function enqueue_block_scripts() {
        if (!self::should_load_scripts()) {
            return;
        }
        
        wp_register_script(
            'nicky-blocks-integration',
            plugins_url('assets/js/blocks-simple.js', dirname(__FILE__)),
            ['wc-blocks-registry', 'wp-element', 'wp-i18n'],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/blocks-simple.js') . '-v2', // Force cache refresh
            true
        );
        
        // Get gateway settings
        $settings = get_option('woocommerce_nicky_settings', []);
        
        // Get cart total
        $cart_total = 0;
        if (WC() && WC()->cart) {
            $cart_total = WC()->cart->get_total('edit');
        }
        
        // Get available blockchain assets from API
        $blockchain_assets = self::get_blockchain_assets($settings);
        
        // Localize script with gateway data
        wp_localize_script('nicky-blocks-integration', 'nickyBlocksData', [
            'title' => $settings['title'] ?? 'Nicky.me Payment',
            'description' => $settings['description'] ?? 'Pay securely with cryptocurrency via Nicky.me. You will be redirected to complete your payment.',
            'icon' => plugins_url('assets/images/logo.png', dirname(__FILE__)),
            'enabled' => ($settings['enabled'] ?? 'no') === 'yes',
            'cartTotal' => number_format($cart_total, 2, '.', '')
        ]);
        
        wp_enqueue_script('nicky-blocks-integration');
    }
    
    /**
     * Get blockchain assets from Nicky API
     */
    private static function get_blockchain_assets($settings) {
        // Get API settings
        $api_base_url = $settings['api_base_url'] ?? 'https://api-public.pay.nicky.me';
        
        // Build API URL
        $api_url = rtrim($api_base_url, '/') . '/AcceptedAsset/get-for-user';
        
        // No caching - fetch fresh data on every request
        
        // Get API key from settings
        $api_key = $settings['api_key'] ?? '';
        
        // Make API request
        $response = wp_remote_get($api_url, [
            'timeout' => 10,
            'headers' => [
                'accept' => 'text/plain',
                'x-api-key' => $api_key
            ]
        ]);
        
        // Check for errors
        if (is_wp_error($response)) {
            error_log('Nicky API Error: ' . $response->get_error_message());
            return self::get_fallback_assets();
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('Nicky API Error: HTTP ' . $response_code);
            return self::get_fallback_assets();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!is_array($data)) {
            error_log('Nicky API Error: Invalid JSON response');
            return self::get_fallback_assets();
        }
        
        // Filter only non-fiat assets for payment selection
        $assets = [];
        foreach ($data as $asset) {
            if (isset($asset['isFiat']) && !$asset['isFiat']) {
                $assets[] = [
                    'id' => $asset['id'],
                    'name' => $asset['assetName'],
                    'symbol' => $asset['assetTicker'],
                    'blockchain' => $asset['assetChain'],
                    'decimalPrecision' => $asset['decimalPrecisionUI'] ?? 8
                ];
            }
        }
        
        // No caching - return fresh data
        return $assets;
    }
    
    /**
     * Get fallback assets if API fails
     * Use correct format from Swagger: assetChain.assetTicker
     */
    private static function get_fallback_assets() {
        return [
            [
                'id' => 'BTC.BTC',
                'name' => 'Bitcoin',
                'symbol' => 'BTC',
                'blockchain' => 'BTC',
                'decimalPrecision' => 8
            ],
            [
                'id' => 'ETH.ETH',
                'name' => 'Ether',
                'symbol' => 'ETH',
                'blockchain' => 'ETH',
                'decimalPrecision' => 8
            ]
        ];
    }
    
    private static function should_load_scripts() {
        // Only load on checkout pages or if WooCommerce blocks are detected
        return is_checkout() || 
               is_cart() || 
               (function_exists('has_block') && (has_block('woocommerce/checkout') || has_block('woocommerce/cart')));
    }
    
    public static function register_block_integration() {
        // Basic integration - this is called when blocks are loaded
        // The actual registration happens in JavaScript
        do_action('nicky_blocks_integration_loaded');
    }
}

// Initialize only if WooCommerce is active
if (class_exists('WooCommerce')) {
    Nicky_Payment_Simple_Blocks::init();
}
