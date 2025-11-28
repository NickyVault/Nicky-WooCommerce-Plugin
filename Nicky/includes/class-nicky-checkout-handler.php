<?php
/**
 * Nicky Payment Gateway Checkout Handler
 * Extended checkout functionality and user guidance
 *
 * @package NickyPaymentGateway
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Nicky Checkout Handler Class
 */
class Nicky_Checkout_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        // Checkout-spezifische Hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_scripts'));
        add_action('woocommerce_checkout_process', array($this, 'validate_checkout'));
        add_action('woocommerce_checkout_order_processed', array($this, 'handle_order_processed'), 10, 3);
        
        // Order status handling
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 3);
        
        // Customer communication
        add_filter('woocommerce_email_subject_customer_on_hold_order', array($this, 'custom_email_subject'), 10, 2);
        add_filter('woocommerce_email_subject_customer_processing_order', array($this, 'custom_email_subject'), 10, 2);
        
        // Shortcodes
        add_shortcode('nicky_payment_status', array($this, 'payment_status_shortcode'));
    }

    /**
     * Enqueue checkout-specific scripts
     */
    public function enqueue_checkout_scripts() {
        if (is_checkout() && !is_wc_endpoint_url()) {
            wp_enqueue_script(
                'nicky-checkout-handler',
                NICKY_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/js/checkout-handler.js',
                array('jquery', 'wc-checkout'),
                NICKY_PAYMENT_GATEWAY_VERSION,
                true
            );

            wp_localize_script('nicky-checkout-handler', 'nicky_checkout_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('nicky_checkout_nonce'),
                'currency' => get_woocommerce_currency(),
                'currency_symbol' => get_woocommerce_currency_symbol(),
                'i18n' => array(
                    'processing' => __('Processing your payment...', 'nicky-me'),
                    'redirecting' => __('Redirecting to Nicky.me...', 'nicky-me'),
                    'please_wait' => __('Please wait while we prepare your payment.', 'nicky-me'),
                    'error_occurred' => __('An error occurred. Please try again.', 'nicky-me'),
                )
            ));

            // Add inline CSS for checkout instructions
            $css = '
                .nicky-checkout-instructions {
                    margin-top: 15px;
                    padding: 20px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    border-left: 4px solid #007cba;
                }
                
                .nicky-payment-flow h4 {
                    margin-top: 0;
                    margin-bottom: 15px;
                    color: #333;
                    font-size: 16px;
                }
                
                .nicky-steps {
                    margin-bottom: 15px;
                }
                
                .nicky-steps .step {
                    display: flex;
                    align-items: center;
                    margin-bottom: 10px;
                    padding: 8px 0;
                }
                
                .nicky-steps .step-number {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 24px;
                    height: 24px;
                    background: #007cba;
                    color: white;
                    border-radius: 50%;
                    font-size: 12px;
                    font-weight: bold;
                    margin-right: 12px;
                    flex-shrink: 0;
                }
                
                .nicky-steps .step-text {
                    color: #555;
                    font-size: 14px;
                }
                
                .nicky-security-note {
                    text-align: center;
                    color: #666;
                    font-style: italic;
                    margin-top: 15px;
                    font-size: 13px;
                }
            ';
            
            wp_add_inline_style('woocommerce-general', $css);
        }
    }

    /**
     * Validate checkout for Nicky payments
     */
    public function validate_checkout() {
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : '';
        
        if ($payment_method !== 'nicky') {
            return;
        }

        // Additional validation for Nicky payments
        $gateways = WC()->payment_gateways()->payment_gateways();
        
        if (!isset($gateways['nicky'])) {
            wc_add_notice(__('Nicky.me payment method is not available.', 'nicky-me'), 'error');
            return;
        }
        
        $gateway = $gateways['nicky'];
        
        if (!$gateway->is_available()) {
            wc_add_notice(__('Nicky.me payment method is currently unavailable.', 'nicky-me'), 'error');
            return;
        }

        // Check if API is configured
        if (empty($gateway->api_key) || empty($gateway->blockchain_asset_id)) {
            wc_add_notice(__('Payment method is not properly configured.', 'nicky-me'), 'error');
            return;
        }
    }

    /**
     * Handle order processed event
     */
    public function handle_order_processed($order_id, $posted_data, $order) {
        if ($order->get_payment_method() !== 'nicky') {
            return;
        }

        // Add order meta for tracking
        $order->update_meta_data('_nicky_payment_initiated_at', current_time('mysql'));
        $order->update_meta_data('_nicky_payment_customer_ip', WC_Geolocation::get_ip_address());
        $order->update_meta_data('_nicky_payment_user_agent', isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '');
        $order->save();

        // Log the payment initiation
        $gateway = WC()->payment_gateways()->payment_gateways()['nicky'];
        /* translators: %s: URL to Nicky.me */
        $order->add_order_note(sprintf(
            __('Nicky.me payment initiated. Customer will be redirected to %s', 'nicky-me'),
            'https://pay.nicky.me'
        ));
    }

    /**
     * Handle order status changes for Nicky payments
     */
    public function handle_order_status_change($order_id, $old_status, $new_status) {
        $order = wc_get_order($order_id);
        
        if (!$order || $order->get_payment_method() !== 'nicky') {
            return;
        }

        $short_id = $order->get_meta('_nicky_short_id');

        switch ($new_status) {
            case 'processing':
                $this->send_payment_confirmation($order);
                break;
                
            case 'completed':
                $this->send_payment_completion($order);
                break;
                
            case 'cancelled':
                $this->handle_payment_cancellation($order);
                break;
                
            case 'failed':
                $this->handle_payment_failure($order);
                break;
        }
    }

    /**
     * Send payment confirmation email
     */
    private function send_payment_confirmation($order) {
        $mailer = WC()->mailer();
        $message = $mailer->wrap_message(
            __('Payment Confirmed', 'nicky-me'),
            /* translators: %s: order number */
            sprintf(
                __('Your cryptocurrency payment for order #%s has been confirmed and is being processed.', 'nicky-me'),
                $order->get_order_number()
            )
        );
        
        $mailer->send(
            $order->get_billing_email(),
            /* translators: %s: order number */
            sprintf(__('Payment Confirmed - Order #%s', 'nicky-me'), $order->get_order_number()),
            $message
        );
    }

    /**
     * Send payment completion notification
     */
    private function send_payment_completion($order) {
        // WooCommerce will handle the standard completion email
        $order->add_order_note(__('Nicky.me payment completed successfully.', 'nicky-me'));
    }

    /**
     * Handle payment cancellation
     */
    private function handle_payment_cancellation($order) {
        $order->add_order_note(__('Nicky.me payment was cancelled by customer.', 'nicky-me'));
        
        // Optionally send cancellation email
        $this->send_cancellation_email($order);
    }

    /**
     * Handle payment failure
     */
    private function handle_payment_failure($order) {
        $order->add_order_note(__('Nicky.me payment failed.', 'nicky-me'));
        
        // Optionally send failure email with retry instructions
        $this->send_failure_email($order);
    }

    /**
     * Send cancellation email
     */
    private function send_cancellation_email($order) {
        $mailer = WC()->mailer();
        $retry_url = $order->get_checkout_payment_url();
        
        $message = $mailer->wrap_message(
            __('Payment Cancelled', 'nicky-me'),
            /* translators: %1$s: order number, %2$s: retry URL */
            sprintf(
                __('Your payment for order #%1$s was cancelled. You can retry your payment using this link: %2$s', 'nicky-me'),
                $order->get_order_number(),
                $retry_url
            )
        );
        
        $mailer->send(
            $order->get_billing_email(),
            /* translators: %s: order number */
            sprintf(__('Payment Cancelled - Order #%s', 'nicky-me'), $order->get_order_number()),
            $message
        );
    }

    /**
     * Send failure email
     */
    private function send_failure_email($order) {
        $mailer = WC()->mailer();
        $retry_url = $order->get_checkout_payment_url();
        
        $message = $mailer->wrap_message(
            __('Payment Failed', 'nicky-me'),
            /* translators: %1$s: order number, %2$s: retry URL */
            sprintf(
                __('Your payment for order #%1$s failed. Please try again using this link: %2$s', 'nicky-me'),
                $order->get_order_number(),
                $retry_url
            )
        );
        
        $mailer->send(
            $order->get_billing_email(),
            /* translators: %s: order number */
            sprintf(__('Payment Failed - Order #%s', 'nicky-me'), $order->get_order_number()),
            $message
        );
    }

    /**
     * Custom email subjects for Nicky payments
     */
    public function custom_email_subject($subject, $order) {
        if ($order && $order->get_payment_method() === 'nicky') {
            if (strpos($subject, 'on-hold') !== false) {
                /* translators: %s: order number */
                return sprintf(__('Your cryptocurrency payment is being processed - Order #%s', 'nicky-me'), $order->get_order_number());
            }
        }
        return $subject;
    }

    /**
     * Payment status shortcode
     * Usage: [nicky_payment_status order_id="123"]
     */
    public function payment_status_shortcode($atts) {
        $atts = shortcode_atts(array(
            'order_id' => 0,
        ), $atts);

        $order_id = intval($atts['order_id']);
        if (!$order_id) {
            return '<p>' . __('Invalid order ID.', 'nicky-me') . '</p>';
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== 'nicky') {
            return '<p>' . __('Order not found or not a Nicky payment.', 'nicky-me') . '</p>';
        }

        $short_id = $order->get_meta('_nicky_short_id');
        $status = $order->get_status();
        
        ob_start();
        ?>
        <div class="nicky-payment-status-widget">
            <h4><?php esc_html_e('Payment Status', 'nicky-me'); ?></h4>
            <p><strong><?php esc_html_e('Order:', 'nicky-me'); ?></strong> #<?php echo esc_html($order->get_order_number()); ?></p>
            <p><strong><?php esc_html_e('Status:', 'nicky-me'); ?></strong> <?php echo esc_html(wc_get_order_status_name($status)); ?></p>
            
            <?php if ($short_id): ?>
                <p><strong><?php esc_html_e('Payment ID:', 'nicky-me'); ?></strong> <?php echo esc_html($short_id); ?></p>
                <p><a href="https://pay.nicky.me/home?paymentId=<?php echo urlencode($short_id); ?>" target="_blank" class="button">
                    <?php esc_html_e('Check Payment on Nicky.me', 'nicky-me'); ?>
                </a></p>
            <?php endif; ?>
            
            <?php if ($status === 'pending'): ?>
                <div class="nicky-pending-notice">
                    <p><?php esc_html_e('Your payment is being processed. This page will update automatically.', 'nicky-me'); ?></p>
                </div>
                <?php
                // Enqueue auto-refresh script
                wp_add_inline_script('jquery', '
                    setTimeout(function() {
                        location.reload();
                    }, 30000);
                ');
                ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize the checkout handler
new Nicky_Checkout_Handler();
