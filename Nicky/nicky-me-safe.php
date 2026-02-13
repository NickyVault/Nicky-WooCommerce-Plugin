<?php
/**
 * Nicky Payment Gateway - Minimal Safe Version
 * Diese Version kann als Notfall-Backup verwendet werden
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NICKY_PAYMENT_GATEWAY_VERSION_SAFE', '1.0.0-safe');

/**
 * Safe initialization - minimal dependencies
 */
function nicky_payment_gateway_safe_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce') || !class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', 'nicky_payment_gateway_wc_missing_notice');
        return;
    }

    // Include only the essential gateway class
    include_once plugin_dir_path(__FILE__) . 'includes/class-nicky-payment-gateway.php';

    // Add the gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'nicky_add_payment_gateway_safe');
}

/**
 * Add the gateway to WooCommerce (safe version)
 */
function nicky_add_payment_gateway_safe($gateways) {
    if (class_exists('Nicky_WC_Gateway_Nicky')) {
        $gateways[] = 'Nicky_WC_Gateway_Nicky';
    }
    return $gateways;
}

/**
 * Admin notice if WooCommerce is not active
 */
function nicky_payment_gateway_wc_missing_notice() {
    echo '<div class="error"><p><strong>Nicky.me</strong> requires WooCommerce to be installed and active.</p></div>';
}

// Initialize in safe mode
add_action('plugins_loaded', 'nicky_payment_gateway_safe_init');

/**
 * Plugin activation hook (safe version)
 */
function nicky_payment_gateway_safe_activate() {
    // Enable the gateway by default
    $default_settings = array(
        'enabled' => 'yes',
        'title' => 'Nicky.me Payment',
        'description' => 'Pay securely with crypto via Nicky.me.',
        'testmode' => 'yes'
    );
    
    $existing_settings = get_option('woocommerce_nicky_settings', array());
    $settings = array_merge($default_settings, $existing_settings);
    update_option('woocommerce_nicky_settings', $settings);
}
register_activation_hook(__FILE__, 'nicky_payment_gateway_safe_activate');