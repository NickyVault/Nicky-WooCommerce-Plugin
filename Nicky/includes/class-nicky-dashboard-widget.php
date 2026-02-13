<?php
/**
 * Nicky Payment Gateway Dashboard Widget
 * Shows orders requiring payment validation
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard Widget for Nicky Payment Validation
 */
class Nicky_Payment_Dashboard_Widget {
    
    public function __construct() {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('wp_ajax_nicky_mark_order_paid', array($this, 'ajax_mark_order_paid'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_scripts'));
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        // Only show to users who can manage WooCommerce
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'nicky_payment_validation_widget',
            'Nicky.me Payments',
            array($this, 'display_dashboard_widget'),
            null,
            null,
            'normal',
            'default'
        );
    }
    
    /**
     * Display the dashboard widget content
     */
    public function display_dashboard_widget() {
        // Get orders requiring validation
        $validation_orders = $this->get_validation_required_orders();
        
        echo '<div id="nicky-dashboard-widget">';
        
        if (empty($validation_orders)) {
            echo '<p class="nicky-no-validation">✅ No orders requiring payment validation.</p>';
        } else {
            echo '<p class="nicky-validation-info">ℹ️ <strong>' . count($validation_orders) . '</strong> order(s) require payment validation:</p>';
            
            echo '<table class="nicky-validation-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Order</th>';
            echo '<th>Amount</th>';
            echo '<th>Customer</th>';
            echo '<th>Date</th>';
            echo '<th>Actions</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($validation_orders as $order) {
                $this->display_validation_order_row($order);
            }
            
            echo '</tbody>';
            echo '</table>';
        }
        
        echo '</div>';
    }
    
    /**
     * Display a single order row
     */
    private function display_validation_order_row($order) {
        $order_id = $order->get_id();
        $short_id = $order->get_meta('_nicky_short_id', true);
        $payment_request_id = $order->get_meta('_nicky_payment_request_id', true);
        
        echo '<tr>';
        
        // Order number with link
        echo '<td>';
        echo '<a href="' . esc_url(admin_url('post.php?post=' . $order_id . '&action=edit')) . '" target="_blank">';
        echo '<strong>#' . esc_html($order->get_order_number()) . '</strong>';
        echo '</a>';
        if ($short_id) {
            echo '<br><small>ID: ' . esc_html($short_id) . '</small>';
        }
        echo '</td>';
        
        // Amount
        echo '<td>';
        echo '<strong>' . esc_html($order->get_formatted_order_total()) . '</strong>';
        echo '</td>';
        
        // Customer
        echo '<td>';
        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if (empty($customer_name)) {
            $customer_name = $order->get_billing_email();
        }
        echo esc_html($customer_name);
        echo '</td>';
        
        // Date
        echo '<td>';
        echo esc_html($order->get_date_created()->format('Y-m-d H:i'));
        echo '</td>';
        
        // Actions
        echo '<td class="nicky-actions">';
        
        // Mark as Paid button
        echo '<button class="button button-primary nicky-mark-paid" data-order-id="' . esc_attr($order_id) . '">';
        echo '✓ Mark Paid';
        echo '</button>';
        
        // Link to Nicky Payment Report
        if ($short_id) {
            $nicky_report_url = 'https://pay.nicky.me/bill/' . urlencode($short_id);
            echo '<a href="' . esc_url($nicky_report_url) . '" target="_blank" class="button">';
            echo '📊 Nicky Report';
            echo '</a>';
        }
        
        echo '</td>';
        echo '</tr>';
    }
    
    /**
     * Get orders that require payment validation
     */
    private function get_validation_required_orders() {
        // Get orders with Nicky payment method that are on-hold and have validation flag
        $orders = wc_get_orders(array(
            'payment_method' => 'nicky',
            'status' => 'on-hold',
            'limit' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_nicky_requires_validation',
                    'value' => 'yes',
                    'compare' => '='
                )
            )
        ));
        
        return $orders;
    }
    
    /**
     * AJAX handler to mark order as paid
     */
    public function ajax_mark_order_paid() {
        // Check nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'nicky_dashboard_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error('Invalid order ID');
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order not found');
            return;
        }
        
        // Verify this is a Nicky order requiring validation
        if ($order->get_payment_method() !== 'nicky') {
            wp_send_json_error('Not a Nicky order');
            return;
        }
        
        $requires_validation = $order->get_meta('_nicky_requires_validation', true);
        if ($requires_validation !== 'yes') {
            wp_send_json_error('Order does not require validation');
            return;
        }
        
        // Mark order as completed
        $order->payment_complete();
        $order->add_order_note('Payment manually validated and marked as completed via dashboard widget.');
        
        // Remove validation flag (HPOS compatible)
        $order->delete_meta_data('_nicky_requires_validation');
        $order->save();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nicky Dashboard: Order #' . $order_id . ' manually marked as paid by user ' . get_current_user_id());
        }
        
        wp_send_json_success(array(
            'message' => 'Order #' . $order->get_order_number() . ' has been marked as paid.',
            'order_id' => $order_id
        ));
    }
    
    /**
     * Enqueue scripts and styles for dashboard widget
     */
    public function enqueue_dashboard_scripts($hook) {
        // Only load on dashboard
        if ($hook !== 'index.php') {
            return;
        }
        
        // Add inline CSS for the widget
        $css = '
        .nicky-validation-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .nicky-validation-table th,
        .nicky-validation-table td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .nicky-validation-table th {
            background-color: #f9f9f9;
            font-weight: 600;
            color: #555;
        }
        
        .nicky-validation-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .nicky-actions {
            white-space: nowrap;
        }
        
        .nicky-actions .button {
            margin-right: 5px;
            font-size: 11px;
            padding: 4px 8px;
            line-height: 1.4;
        }
        
        .nicky-validation-info {
            color: #d63638;
            font-weight: 500;
            margin: 0 0 10px 0;
        }
        
        .nicky-no-validation {
            color: #00a32a;
            font-weight: 500;
            margin: 0;
            text-align: center;
            padding: 20px;
        }
        
        #nicky-dashboard-widget a {
            text-decoration: none;
        }
        
        #nicky-dashboard-widget a:hover {
            text-decoration: underline;
        }
        ';
        
        wp_add_inline_style('wp-admin', $css);
        
        // Add inline JavaScript for AJAX functionality
        $nonce = wp_create_nonce('nicky_dashboard_nonce');
        $ajax_url = admin_url('admin-ajax.php');
        $js = "
        jQuery(document).ready(function($) {
            var nickyAjaxUrl = '" . esc_js($ajax_url) . "';
            
            $('.nicky-mark-paid').on('click', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var orderId = button.data('order-id');
                var originalText = button.text();
                
                if (!confirm('Are you sure you want to mark this order as paid?')) {
                    return;
                }
                
                button.prop('disabled', true).text('Processing...');
                
                $.ajax({
                    url: nickyAjaxUrl,
                    type: 'POST',
                    data: {
                        action: 'nicky_mark_order_paid',
                        order_id: orderId,
                        nonce: '" . $nonce . "'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Remove the row or refresh the widget
                            button.closest('tr').fadeOut(function() {
                                $(this).remove();
                                
                                // Check if table is empty
                                var remainingRows = $('.nicky-validation-table tbody tr').length;
                                if (remainingRows === 0) {
                                    $('#nicky-dashboard-widget').html('<p class=\"nicky-no-validation\">✅ No orders requiring payment validation.</p>');
                                }
                            });
                            
                            // Show success message
                            $('<div class=\"notice notice-success is-dismissible\"><p>' + response.data.message + '</p></div>')
                                .insertAfter('.wp-header-end');
                        } else {
                            alert('Error: ' + response.data);
                            button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function() {
                        alert('An error occurred while processing the request.');
                        button.prop('disabled', false).text(originalText);
                    }
                });
            });
        });
        ";
        
        wp_add_inline_script('jquery', $js);
    }
    
}

// Initialize the dashboard widget
new Nicky_Payment_Dashboard_Widget();