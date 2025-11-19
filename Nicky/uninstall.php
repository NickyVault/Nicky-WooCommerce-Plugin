<?php
/**
 * Uninstall script for Nicky Payment Gateway
 *
 * @package NickyPaymentGateway
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
delete_option('woocommerce_nicky_payment_gateway_settings');

// Remove custom database table
global $wpdb;
$table_name = $wpdb->prefix . 'nicky_payment_transactions';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Clear any cached data
wp_cache_flush();
