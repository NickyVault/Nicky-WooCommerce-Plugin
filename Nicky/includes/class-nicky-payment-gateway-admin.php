<?php
/**
 * Nicky Payment Gateway Admin Class
 *
 * @package NickyPaymentGateway
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class for Nicky Payment Gateway
 */
class Nicky_Payment_Gateway_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Nicky',
            'Nciky',
            'manage_woocommerce',
            'nicky-me',
            array($this, 'admin_page')
        );
    }

    /**
     * Initialize admin settings
     */
    public function admin_init() {
        // Register settings if needed
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook === 'woocommerce_page_nicky-me') {
            wp_enqueue_style('nicky-payment-admin', NICKY_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/css/admin.css', array(), NICKY_PAYMENT_GATEWAY_VERSION);
            wp_enqueue_script('nicky-payment-admin', NICKY_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), NICKY_PAYMENT_GATEWAY_VERSION, true);
        }
    }

    /**
     * Admin page content
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php
            // Environment checks
            if (!class_exists('WooCommerce')) {
                echo '<div class="notice notice-error"><p>WooCommerce is not active!</p></div>';
                echo '</div>';
                return;
            }
            
            if (!class_exists('Nicky_WC_Gateway_Nicky')) {
                echo '<div class="notice notice-error"><p>Gateway class Nicky_WC_Gateway_Nicky not found! Please deactivate and reactivate the plugin.</p></div>';
                echo '</div>';
                return;
            }
            ?>
            
            <div class="nicky-admin-dashboard">
                <div class="nicky-admin-section">
                    <h2>Status</h2>
                    <?php $this->display_gateway_status(); ?>
                </div>

                <div class="nicky-admin-section">
                    <h2>Recent Transactions</h2>
                    <?php $this->display_recent_transactions(); ?>
                </div>

                <div class="nicky-admin-section">
                    <h2>Configuration</h2>
                    <?php $this->display_gateway_configuration(); ?>
                </div>

                <div class="nicky-admin-section">
                    <h2>Settings</h2>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=nicky')); ?>" class="button button-primary">
                            Configure Nicky
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Display gateway status
     */
    private function display_gateway_status() {
        // Get settings directly from WordPress options
        $gateway_settings = get_option('woocommerce_nicky_settings', array());
        
        if (empty($gateway_settings)) {
            echo '<div class="notice notice-warning"><p>No gateway settings found. Please configure the gateway first.</p></div>';
            return;
        }
        
        $is_enabled = isset($gateway_settings['enabled']) && $gateway_settings['enabled'] === 'yes';
        $api_key = isset($gateway_settings['api_key']) ? $gateway_settings['api_key'] : '';
        $blockchain_asset_id = isset($gateway_settings['blockchain_asset_id']) ? $gateway_settings['blockchain_asset_id'] : '';
        $has_api_keys = !empty($api_key) && !empty($blockchain_asset_id);

        echo '<div class="nicky-status-grid">';
        
        echo '<div class="nicky-status-item">';
        echo '<div class="status-indicator ' . ($is_enabled ? 'status-enabled' : 'status-disabled') . '"></div>';
        echo '<div class="status-text">';
        echo '<strong>Status</strong><br>';
        echo $is_enabled ? 'Enabled' : 'Disabled';
        echo '</div>';
        echo '</div>';

        echo '<div class="nicky-status-item">';
        echo '<div class="status-indicator ' . ($has_api_keys ? 'status-configured' : 'status-error') . '"></div>';
        echo '<div class="status-text">';
        echo '<strong>API Configuration</strong><br>';
        echo $has_api_keys ? 'Configured' : 'Not Configured';
        
        // Debug information (only show if WP_DEBUG is true)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<br><small style="color: #666;">';
            echo 'Debug: API Key: ' . (empty($api_key) ? 'empty' : 'length ' . esc_html(strlen($api_key)));
            echo ', Asset ID: ' . (empty($blockchain_asset_id) ? 'empty' : 'length ' . esc_html(strlen($blockchain_asset_id)));
            echo '</small>';
        }
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Display recent transactions
     */
    private function display_recent_transactions() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'nicky_payment_transactions';
        $cache_key_transactions = 'nicky_recent_transactions';
        $transactions = wp_cache_get($cache_key_transactions);
        if ($transactions === false) {
            $transactions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %s ORDER BY created_at DESC LIMIT 10", $table_name ), ARRAY_A );
            wp_cache_set($cache_key_transactions, $transactions, '', 60); // Cache for 1 minute
        }

        if (empty($transactions)) {
            echo '<p>' . esc_html(__('No transactions found.', 'nicky-me')) . '</p>';
            return;
        }

        echo '<div class="nicky-transactions-table">';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html(__('Order ID', 'nicky-me')) . '</th>';
        echo '<th>' . esc_html(__('Transaction ID', 'nicky-me')) . '</th>';
        echo '<th>' . esc_html(__('Amount', 'nicky-me')) . '</th>';
        echo '<th>' . esc_html(__('Status', 'nicky-me')) . '</th>';
        echo '<th>' . esc_html(__('Date', 'nicky-me')) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($transactions as $transaction) {
            echo '<tr>';
            echo '<td><a href="' . esc_url(admin_url('post.php?post=' . $transaction['order_id'] . '&action=edit')) . '">#' . esc_html($transaction['order_id']) . '</a></td>';
            echo '<td>' . esc_html($transaction['transaction_id']) . '</td>';
            echo '<td>' . esc_html(wc_price($transaction['amount'], array('currency' => $transaction['currency']))) . '</td>';
            echo '<td><span class="status-badge status-' . esc_attr($transaction['payment_status']) . '">' . esc_html(ucfirst($transaction['payment_status'])) . '</span></td>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transaction['created_at']))) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    /**
     * Display gateway configuration
     */
    private function display_gateway_configuration() {
        // Debug: Check if class exists
        if (!class_exists('Nicky_WC_Gateway_Nicky')) {
            echo '<div class="notice notice-error"><p>Error: Nicky_WC_Gateway_Nicky class not found!</p></div>';
            return;
        }
        
        try {
            $gateway = new Nicky_WC_Gateway_Nicky();
            $logo_url = $gateway->get_gateway_icon();
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>Error creating gateway: ' . esc_html($e->getMessage()) . '</p></div>';
            return;
        }
        
        echo '<div class="nicky-config-grid">';
        
        // Logo preview
        echo '<div class="nicky-config-item">';
        echo '<h4>' . esc_html(__('Gateway Logo', 'nicky-me')) . '</h4>';
        if (!empty($logo_url)) {
            echo '<div class="logo-preview">';
            echo '<img src="' . esc_url($logo_url) . '" alt="Gateway Logo" style="max-height: 40px; max-width: 200px; border: 1px solid #ddd; padding: 10px; background: white;">';
            echo '</div>';
            echo '<p class="description">' . esc_html(__('This logo will be displayed in the checkout.', 'nicky-me')) . '</p>';
        } else {
            echo '<div class="logo-preview no-logo">';
            echo '<div style="padding: 20px; border: 2px dashed #ddd; text-align: center; color: #666;">';
            echo esc_html(__('No logo configured', 'nicky-me'));
            echo '</div>';
            echo '</div>';
            echo '<p class="description">' . esc_html(__('Add your logo.png to assets/images/ or configure a custom logo URL.', 'nicky-me')) . '</p>';
        }
        echo '</div>';
        
        // Gateway title and description
        echo '<div class="nicky-config-item">';
        echo '<h4>' . esc_html(__('Display Settings', 'nicky-me')) . '</h4>';
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th>' . esc_html(__('Title', 'nicky-me')) . '</th>';
        echo '<td>' . esc_html($gateway->title) . '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th>' . esc_html(__('Description', 'nicky-me')) . '</th>';
        echo '<td>' . esc_html($gateway->description) . '</td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';
        
        echo '</div>';
    }
}

// Initialize admin class
new Nicky_Payment_Gateway_Admin();
