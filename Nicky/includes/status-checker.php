<?php
/**
 * Plugin Status Checker
 * Checks if the plugin is correctly installed and configured
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function nicky_payment_gateway_status_check() {
    $status = array(
        'plugin_active' => false,
        'woocommerce_active' => false,
        'database_created' => false,
        'gateway_enabled' => false,
        'api_configured' => false,
        'errors' => array(),
        'warnings' => array()
    );

    if (is_plugin_active('nicky-payment-gateway/nicky-me.php')) {
        $status['plugin_active'] = true;
    }

    if (class_exists('WooCommerce')) {
        $status['woocommerce_active'] = true;
    } else {
        $status['errors'][] = 'WooCommerce is not activated.';
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'nicky_payment_transactions';
    $cache_key = 'nicky_table_exists';
    $table_exists = wp_cache_get($cache_key);
    if ($table_exists === false) {
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql($table_name) . "'" ) == $table_name;
        wp_cache_set($cache_key, $table_exists, '', 3600); // Cache for 1 hour
    }
    if ($table_exists) {
        $status['database_created'] = true;
    } else {
        $status['warnings'][] = 'Database table has not been created yet.';
    }

        // Check if gateway is enabled
    if (class_exists('WC_Gateway_Nicky')) {
        $gateway_settings = get_option('woocommerce_nicky_settings', array());
        
        if (isset($gateway_settings['enabled']) && $gateway_settings['enabled'] === 'yes') {
            $status['gateway_enabled'] = true;
        } else {
            $status['warnings'][] = 'Nicky gateway is not enabled.';
        }

        // Check API configuration
        $api_key = isset($gateway_settings['api_key']) ? $gateway_settings['api_key'] : '';
        $blockchain_asset_id = isset($gateway_settings['blockchain_asset_id']) ? $gateway_settings['blockchain_asset_id'] : '';
        
        if (!empty($api_key) && !empty($blockchain_asset_id)) {
            $status['api_configured'] = true;
        } else {
            $status['errors'][] = 'API key or blockchain asset ID is not configured.';
        }
        
        // Check checkout page
        $checkout_page_id = wc_get_page_id('checkout');
        if ($checkout_page_id && get_post_status($checkout_page_id) === 'publish') {
            $status['checkout_page_exists'] = true;
        } else {
            $status['errors'][] = 'WooCommerce checkout page is not configured properly.';
        }
        
        // Check if scripts are enqueued on checkout
        if (is_admin()) {
            $status['scripts_ready'] = file_exists(NICKY_PAYMENT_GATEWAY_PLUGIN_PATH . 'assets/js/checkout.js') &&
                                     file_exists(NICKY_PAYMENT_GATEWAY_PLUGIN_PATH . 'assets/css/checkout.css');
        }
        
    } else {
        $status['errors'][] = 'WC_Gateway_Nicky class is not loaded.';
    }

    return $status;
}

function nicky_payment_gateway_display_status() {
    $status = nicky_payment_gateway_status_check();
    
    echo '<div class="nicky-status-check">';
    echo '<h3>Plugin Status</h3>';
    
    // Success indicators
    echo '<ul class="status-list">';
    echo '<li class="' . ($status['plugin_active'] ? 'success' : 'error') . '">Plugin activated: ' . ($status['plugin_active'] ? '✅' : '❌') . '</li>';
    echo '<li class="' . ($status['woocommerce_active'] ? 'success' : 'error') . '">WooCommerce activated: ' . ($status['woocommerce_active'] ? '✅' : '❌') . '</li>';
    echo '<li class="' . ($status['database_created'] ? 'success' : 'warning') . '">Database created: ' . ($status['database_created'] ? '✅' : '⚠️') . '</li>';
    echo '<li class="' . ($status['gateway_enabled'] ? 'success' : 'warning') . '">Nicky enabled: ' . ($status['gateway_enabled'] ? '✅' : '⚠️') . '</li>';
    echo '<li class="' . ($status['api_configured'] ? 'success' : 'warning') . '">API configured: ' . ($status['api_configured'] ? '✅' : '⚠️') . '</li>';
    echo '</ul>';
    
    // Errors
    if (!empty($status['errors'])) {
        echo '<div class="error-messages">';
        echo '<h4>Errors:</h4>';
        echo '<ul>';
        foreach ($status['errors'] as $error) {
            echo '<li>❌ ' . esc_html($error) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    
    // Warnings
    if (!empty($status['warnings'])) {
        echo '<div class="warning-messages">';
        echo '<h4>Warnings:</h4>';
        echo '<ul>';
        foreach ($status['warnings'] as $warning) {
            echo '<li>⚠️ ' . esc_html($warning) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    
    // Overall status
    $overall_ok = $status['plugin_active'] && $status['woocommerce_active'] && empty($status['errors']);
    echo '<div class="overall-status ' . ($overall_ok ? 'success' : 'error') . '">';
    if ($overall_ok) {
        echo '<strong>✅ Plugin is ready for use!</strong>';
        if (!empty($status['warnings'])) {
            echo '<br><em>Note: There are some warnings you should address.</em>';
        }
    } else {
        echo '<strong>❌ Plugin is not ready. Please fix the errors.</strong>';
    }
    echo '</div>';
    
    echo '</div>';
    
    // Add CSS
    $css = '
        .nicky-status-check {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .status-list {
            list-style: none;
            padding: 0;
        }
        .status-list li {
            padding: 5px 0;
            font-weight: 500;
        }
        .status-list li.success { color: #00a32a; }
        .status-list li.warning { color: #dba617; }
        .status-list li.error { color: #d63638; }
        .error-messages, .warning-messages {
            margin: 15px 0;
        }
        .error-messages { color: #d63638; }
        .warning-messages { color: #dba617; }
        .overall-status {
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
            text-align: center;
        }
        .overall-status.success {
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }
        .overall-status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c2c7;
        }
    ';
    
    wp_add_inline_style('wp-admin', $css);
}

// Add to admin menu
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'Nicky Status',
        'Nicky Status',
        'manage_options',
        'nicky-payment-status',
        'nicky_payment_gateway_display_status'
    );
});

// Add admin notice if there are critical errors
add_action('admin_notices', function() {
    $status = nicky_payment_gateway_status_check();
    
    if (!empty($status['errors'])) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>Nicky:</strong> There are critical errors that need to be fixed.</p>';
        echo '<p><a href="' . esc_url(admin_url('tools.php?page=nicky-payment-status')) . '">Check Status</a></p>';
        echo '</div>';
    }
});
