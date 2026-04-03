<?php
/**
 * Plugin Name: Nicky.me
 * Plugin URI: https://github.com/NickyVault/Nicky-WooCommerce-Plugin
 * Description: Secure and reliable payment processing for WooCommerce powered by Nicky.me.
 * Version: 1.0.9
 * Author: Nicky.me
 * Author URI: https://nicky.me
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nicky-me
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.9
 * WC requires at least: 5.0
 * WC tested up to: 9.5
 *
 * @package NickyPaymentGateway
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NICKY_PAYMENT_GATEWAY_VERSION', '1.0.9');
define('NICKY_PAYMENT_GATEWAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NICKY_PAYMENT_GATEWAY_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Declare HPOS compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Cleanup old testmode settings on plugin load
 */
function nicky_payment_gateway_cleanup_old_settings() {
    $gateway_settings = get_option('woocommerce_nicky_settings', array());
    
    if (!empty($gateway_settings)) {
        $old_keys = array('testmode', 'test_api_key', 'test_api_secret', 'live_api_key', 'live_api_secret');
        $needs_update = false;
        
        foreach ($old_keys as $old_key) {
            if (isset($gateway_settings[$old_key])) {
                unset($gateway_settings[$old_key]);
                $needs_update = true;
            }
        }
        
        if ($needs_update) {
            update_option('woocommerce_nicky_settings', $gateway_settings);
        }
    }
}
add_action('admin_init', 'nicky_payment_gateway_cleanup_old_settings');

/**
 * Initialize the payment gateway
 */
function nicky_payment_gateway_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce') || !class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', 'nicky_payment_gateway_wc_missing_notice');
        return;
    }

    // Include the gateway class
    include_once NICKY_PAYMENT_GATEWAY_PLUGIN_PATH . 'includes/class-nicky-payment-gateway.php';
    
    // Include the checkout handler
    include_once NICKY_PAYMENT_GATEWAY_PLUGIN_PATH . 'includes/class-nicky-checkout-handler.php';
    
    // Include the admin class
    if (is_admin()) {
        include_once NICKY_PAYMENT_GATEWAY_PLUGIN_PATH . 'includes/class-nicky-payment-gateway-admin.php';
        include_once NICKY_PAYMENT_GATEWAY_PLUGIN_PATH . 'includes/class-nicky-dashboard-widget.php';
    }

    // Load Blocks support - both versions for maximum compatibility
    if ( function_exists( 'register_block_type' ) ) {
        include_once NICKY_PAYMENT_GATEWAY_PLUGIN_PATH . 'includes/class-nicky-payment-blocks-simple.php';
        
        // Store API Integration for WooCommerce Blocks Checkout
        include_once NICKY_PAYMENT_GATEWAY_PLUGIN_PATH . 'includes/class-nicky-store-api.php';
        
        // Also load the extended version if available
        if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            include_once NICKY_PAYMENT_GATEWAY_PLUGIN_PATH . 'includes/class-nicky-payment-blocks.php';
        }
    }

    // Add the gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'nicky_add_payment_gateway');
}
add_action('plugins_loaded', 'nicky_payment_gateway_init');

/**
 * Add the gateway to WooCommerce
 */
function nicky_add_payment_gateway($gateways) {
    if (!class_exists('Nicky_WC_Gateway_Nicky')) {
        return $gateways;
    }
    
    $gateways[] = 'Nicky_WC_Gateway_Nicky';
    return $gateways;
}

/**
 * Ensure gateway is available for checkout process
 */
function nicky_ensure_gateway_available($available_gateways) {
    // If Nicky gateway is registered but not in available list, add it
    if (class_exists('Nicky_WC_Gateway_Nicky')) {
        $gateway = new Nicky_WC_Gateway_Nicky();
        if ($gateway->enabled === 'yes' && $gateway->is_available()) {
            if (!isset($available_gateways['nicky'])) {
                $available_gateways['nicky'] = $gateway;
            }
        }
    }
    return $available_gateways;
}
add_filter('woocommerce_available_payment_gateways', 'nicky_ensure_gateway_available');



/**
 * Handle WooCommerce Store API checkout for Nicky payments
 */
function nicky_handle_store_api_checkout() {
    add_filter('woocommerce_rest_checkout_process_payment_with_context', function($payment_context, $payment_result) {
        // Get payment method from context
        $payment_method = $payment_context->payment_method ?? null;
        
        if ($payment_method === 'nicky') {
            // Get the order from context
            $order = $payment_context->order ?? null;
            if (!$order) {
                return $payment_result;
            }
            
            $order_id = $order->get_id();
            
            // Process with our gateway
            $gateway = new Nicky_WC_Gateway_Nicky();
            $result = $gateway->process_payment($order_id);
            
            if ($result['result'] === 'success') {
                $payment_result->set_status('success');
                if (isset($result['redirect'])) {
                    $payment_result->set_redirect_url($result['redirect']);
                }
            } else {
                $payment_result->set_status('failure');
                $payment_result->set_payment_details(array('errorMessage' => 'Payment processing failed'));
            }
        }
        
        return $payment_result;
    }, 10, 2);
}
add_action('rest_api_init', 'nicky_handle_store_api_checkout');

/**
 * Admin notice if WooCommerce is not active
 */
function nicky_payment_gateway_wc_missing_notice() {
    echo '<div class="error"><p><strong>Nicky.me</strong> requires WooCommerce to be installed and active. You can download <a href="https://woocommerce.com/" target="_blank">WooCommerce</a> here.</p></div>';
}

/**
 * Admin notice after activation if WooCommerce is missing
 */
function nicky_payment_gateway_activation_notice() {
    if (get_transient('nicky_wc_missing_notice')) {
        delete_transient('nicky_wc_missing_notice');
        deactivate_plugins(plugin_basename(__FILE__));
        echo '<div class="error"><p><strong>Nicky.me Payment Gateway</strong> requires WooCommerce to be installed and active. The plugin has been deactivated.</p></div>';
    }
}
add_action('admin_notices', 'nicky_payment_gateway_activation_notice');

/**
 * Plugin activation hook
 */
function nicky_payment_gateway_activate() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        // Set transient for admin notice instead of wp_die()
        set_transient('nicky_wc_missing_notice', true, 60);
        return;
    }

    // Create plugin database tables if needed
    nicky_payment_gateway_create_tables();
    
    // Enable the gateway by default and cleanup old settings
    $gateway_settings = get_option('woocommerce_nicky_settings', array());
    
    // Remove old testmode-related settings if they exist
    $old_keys = array('testmode', 'test_api_key', 'test_api_secret', 'live_api_key', 'live_api_secret');
    foreach ($old_keys as $old_key) {
        if (isset($gateway_settings[$old_key])) {
            unset($gateway_settings[$old_key]);
        }
    }
    
    if (empty($gateway_settings)) {
        $default_settings = array(
            'enabled' => 'yes',
            'title' => 'Nicky.me Payment',
            'description' => 'Pay securely with crypto via Nicky.me.',
            'api_key' => '',
            'api_secret' => '',
            'custom_logo' => ''
        );
        update_option('woocommerce_nicky_settings', $default_settings);
    } else {
        // Ensure gateway is enabled and save cleaned settings
        $gateway_settings['enabled'] = 'yes';
        update_option('woocommerce_nicky_settings', $gateway_settings);
    }
    
    // Force refresh WooCommerce payment gateways
    delete_transient('woocommerce_payment_gateway_ids');
}
register_activation_hook(__FILE__, 'nicky_payment_gateway_activate');

/**
 * Plugin deactivation hook
 */
function nicky_payment_gateway_deactivate() {
    // Clean up cron job
    $timestamp = wp_next_scheduled('nicky_check_payment_status_cron');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'nicky_check_payment_status_cron');
    }
}
register_deactivation_hook(__FILE__, 'nicky_payment_gateway_deactivate');

/**
 * Create database tables
 */
function nicky_payment_gateway_create_tables() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'nicky_payment_transactions';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        transaction_id varchar(100) NOT NULL,
        payment_status varchar(20) NOT NULL,
        amount decimal(10,2) NOT NULL,
        currency varchar(3) NOT NULL,
        gateway_response text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY order_id (order_id),
        KEY transaction_id (transaction_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Add plugin action links
 */
function nicky_payment_gateway_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=nicky_payment_gateway') . '">Settings</a>',
    );
    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'nicky_payment_gateway_action_links');


