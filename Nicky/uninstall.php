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
$nicky_table_name = $wpdb->prefix . 'nicky_payment_transactions';
$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %s", $nicky_table_name ) );

// Clear any cached data
wp_cache_flush();
