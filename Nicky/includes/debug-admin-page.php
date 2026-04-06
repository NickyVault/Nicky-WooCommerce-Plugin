<?php
/**
 * Sicheres Debug Script für Nicky Payment Gateway
 * Dieses Script wird als Admin-Seite ausgeführt
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu for debugging
add_action('admin_menu', 'nicky_debug_menu');
add_action('admin_enqueue_scripts', 'nicky_debug_enqueue_scripts');

function nicky_debug_menu() {
    add_submenu_page(
        'woocommerce',
        'Nicky Debug',
        'Nicky Debug',
        'manage_woocommerce',
        'nicky-debug',
        'nicky_debug_page'
    );
}

function nicky_debug_enqueue_scripts($hook) {
    if ($hook !== 'woocommerce_page_nicky-debug') {
        return;
    }
    
    $css = '
        .nicky-debug .success { color: green; }
        .nicky-debug .error { color: red; }
        .nicky-debug .warning { color: orange; }
        .nicky-debug .debug-section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa; }
        .nicky-debug pre { background: #fff; padding: 10px; border: 1px solid #ccd0d4; overflow: auto; }
    ';
    
    wp_add_inline_style('wp-admin', $css);
}

function nicky_debug_page() {
    if (!class_exists('WooCommerce')) {
        echo '<div class="error"><p>WooCommerce is not installed or active</p></div>';
        return;
    }

    echo '<div class="wrap">';
    echo '<h1>Nicky Payment Gateway - Debug</h1>';
    
    echo '<div class="nicky-debug">';

    // 1. WooCommerce Status
    echo '<div class="debug-section">';
    echo '<h2>1. WooCommerce Status</h2>';
    echo '<p class="success">✓ WooCommerce is loaded</p>';
    echo '<p>Version: ' . esc_html(WC_VERSION) . '</p>';
    echo '</div>';

    // 2. Gateway Registration
    echo '<div class="debug-section">';
    echo '<h2>2. Nicky Gateway Registration</h2>';
    
    if (class_exists('WC_Gateway_Nicky')) {
        echo '<p class="success">✓ WC_Gateway_Nicky Klasse existiert</p>';
        
        $gateway = new WC_Gateway_Nicky();
        echo '<p>Gateway ID: ' . esc_html($gateway->id) . '</p>';
        echo '<p>Gateway Title: ' . esc_html($gateway->title) . '</p>';
        echo '<p>Gateway Enabled: ' . ($gateway->enabled === 'yes' ? 'Ja' : 'Nein') . '</p>';
        echo '<p>Test Mode: ' . ($gateway->testmode ? 'Ja' : 'Nein') . '</p>';
        
    } else {
        echo '<p class="error">✗ WC_Gateway_Nicky Klasse existiert NICHT</p>';
    }
    echo '</div>';

    // 3. Available Payment Gateways
    echo '<div class="debug-section">';
    echo '<h2>3. Available Payment Gateways</h2>';
    
    $payment_gateways = WC()->payment_gateways()->payment_gateways();
    echo '<p>Number of registered gateways: ' . count($payment_gateways) . '</p>';
    
    $nicky_found = false;
    foreach ($payment_gateways as $id => $gateway) {
        if ($id === 'nicky') {
            $nicky_found = true;
            echo '<p class="success">✓ Nicky Gateway found: ' . esc_html($gateway->get_title()) . '</p>';
            echo '<p>  - Enabled: ' . ($gateway->enabled === 'yes' ? 'Yes' : 'No') . '</p>';
            echo '<p>  - Available: ' . ($gateway->is_available() ? 'Yes' : 'No') . '</p>';
            break;
        }
    }
    
    if (!$nicky_found) {
        echo '<p class="error">✗ Nicky Gateway is NOT in the list of gateways</p>';
    }
    
    echo '<p>Alle Gateways:</p>';
    echo '<ul>';
    foreach ($payment_gateways as $id => $gateway) {
        $enabled = ($gateway->enabled === 'yes') ? 'Ja' : 'Nein';
        $available = $gateway->is_available() ? 'Ja' : 'Nein';
        echo '<li>' . esc_html($id) . ' - ' . esc_html($gateway->get_title()) . ' (Enabled: ' . esc_html($enabled) . ', Available: ' . esc_html($available) . ')</li>';
    }
    echo '</ul>';
    echo '</div>';

    // 4. Gateway Availability Check
    echo '<div class="debug-section">';
    echo '<h2>4. Gateway Verfügbarkeit Prüfung</h2>';
    
    if (class_exists('WC_Gateway_Nicky')) {
        $gateway = new WC_Gateway_Nicky();
        
        echo '<p>Ausführliche Verfügbarkeits-Prüfung:</p>';
        echo '<ul>';
        
        // Enabled check
        echo '<li>Gateway aktiviert: ' . ($gateway->enabled === 'yes' ? 'Ja' : 'Nein') . '</li>';
        
        // Test mode
        echo '<li>Test Modus: ' . ($gateway->testmode ? 'Ja' : 'Nein') . '</li>';
        
        // API configuration
        echo '<li>API Key gesetzt: ' . (!empty($gateway->api_key) ? 'Ja' : 'Nein') . '</li>';
        echo '<li>Blockchain Asset ID gesetzt: ' . (!empty($gateway->blockchain_asset_id) ? 'Ja' : 'Nein') . '</li>';
        
        echo '</ul>';
        
        // Final availability
        echo '<p><strong>Final verfügbar: ' . ($gateway->is_available() ? 'Ja' : 'Nein') . '</strong></p>';
    }
    echo '</div>';

    // 5. Gateway Settings
    echo '<div class="debug-section">';
    echo '<h2>5. Gateway Settings</h2>';
    
    $settings = get_option('woocommerce_nicky_settings', array());
    if (!empty($settings)) {
        echo '<p class="success">✓ Gateway settings found</p>';
        // Hide sensitive data
        $safe_settings = $settings;
        if (isset($safe_settings['api_key']) && !empty($safe_settings['api_key'])) {
            $safe_settings['api_key'] = '***configured***';
        }
        echo '<pre>' . esc_html(var_export($safe_settings, true)) . '</pre>';
    } else {
        echo '<p class="error">✗ No gateway settings found</p>';
    }
    echo '</div>';

    // 6. Quick Fix Actions
    echo '<div class="debug-section">';
    echo '<h2>6. Quick Fixes</h2>';
    
    if (isset($_POST['enable_gateway']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'nicky_debug_enable')) {
        $settings = get_option('woocommerce_nicky_settings', array());
        $settings['enabled'] = 'yes';
        if (empty($settings['title'])) {
            $settings['title'] = 'Nicky Payment';
        }
        if (empty($settings['description'])) {
            $settings['description'] = 'Pay securely with crypto via Nicky.';
        }
        update_option('woocommerce_nicky_settings', $settings);
        echo '<div class="notice notice-success"><p>Gateway has been activated and set to test mode!</p></div>';
    }
    
    echo '<form method="post">';
    wp_nonce_field('nicky_debug_enable');
    echo '<p>';
    echo '<input type="submit" name="enable_gateway" class="button button-primary" value="Activate Gateway (Test Mode)" />';
    echo '</p>';
    echo '</form>';
    
    echo '<p><strong>Manual steps:</strong></p>';
    echo '<ol>';
    echo '<li>Go to <a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=nicky')) . '">WooCommerce > Settings > Payments > Nicky</a></li>';
    echo '<li>Activate the gateway</li>';
    echo '<li>Set it to test mode for initial tests</li>';
    echo '<li>Add items to cart and test the checkout</li>';
    echo '</ol>';
    echo '</div>';

    echo '</div>'; // .nicky-debug
    echo '</div>'; // .wrap
}