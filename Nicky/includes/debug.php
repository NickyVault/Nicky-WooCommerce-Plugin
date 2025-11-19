<?php
/**
 * Debug and troubleshooting functions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Debug payment gateway status
 */
function nicky_debug_gateway_status() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    echo '<div class="notice notice-info">';
    echo '<h3>Nicky.me Debug Information</h3>';
    
    // Check if WooCommerce is active
    if (class_exists('WooCommerce')) {
        echo '<p>✅ WooCommerce is active</p>';
    } else {
        echo '<p>❌ WooCommerce is not active</p>';
        echo '</div>';
        return;
    }
    
    // Check if WC_Payment_Gateway exists
    if (class_exists('WC_Payment_Gateway')) {
        echo '<p>✅ WC_Payment_Gateway class exists</p>';
    } else {
        echo '<p>❌ WC_Payment_Gateway class missing</p>';
    }
    
    // Check if our gateway class exists
    if (class_exists('WC_Gateway_Nicky')) {
        echo '<p>✅ WC_Gateway_Nicky class exists</p>';
    } else {
        echo '<p>❌ WC_Gateway_Nicky class missing</p>';
    }
    
    // Check available payment gateways
    $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
    echo '<p>Available payment gateways: ' . count($available_gateways) . '</p>';
    
    if (isset($available_gateways['nicky'])) {
        echo '<p>✅ Nicky.me gateway is available</p>';
        $gateway = $available_gateways['nicky'];
        echo '<p>Gateway enabled: ' . ($gateway->enabled === 'yes' ? 'Yes' : 'No') . '</p>';
        echo '<p>Gateway title: ' . $gateway->title . '</p>';
    } else {
        echo '<p>❌ Nicky.me gateway is NOT available</p>';
        
        // Check all registered gateways
        $all_gateways = WC()->payment_gateways->payment_gateways();
        echo '<p>All registered gateways:</p><ul>';
        foreach ($all_gateways as $id => $gateway) {
            echo '<li>' . $id . ' (' . get_class($gateway) . ') - Enabled: ' . ($gateway->enabled === 'yes' ? 'Yes' : 'No') . '</li>';
        }
        echo '</ul>';
    }
    
    // Check gateway settings
    $settings = get_option('woocommerce_nicky_settings', array());
    echo '<p>Gateway settings:</p>';
    echo '<pre>' . print_r($settings, true) . '</pre>';
    
    echo '</div>';
}

// Add debug info to admin if debug mode is enabled
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('admin_notices', 'nicky_debug_gateway_status');
}

/**
 * Add debug menu item
 */
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'Nicky.me Debug',
        'Nicky.me Debug',
        'manage_options',
        'nicky-debug',
        function() {
            echo '<div class="wrap">';
            echo '<h1>Nicky.me Debug Information</h1>';
            nicky_debug_gateway_status();
            echo '</div>';
        }
    );
});
