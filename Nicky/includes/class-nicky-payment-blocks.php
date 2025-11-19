<?php
if ( ! defined('ABSPATH') ) exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Gateway_Nicky_Blocks_Support extends AbstractPaymentMethodType {
    
    protected $name = 'nicky';
    
    public function initialize() {
        $this->settings = get_option( 'woocommerce_nicky_settings', [] );
    }
    
    public function is_active() {
        $payment_gateways_class = WC()->payment_gateways();
        $payment_gateways = $payment_gateways_class->payment_gateways();
        return $payment_gateways[ $this->name ]->is_available();
    }
    
    public function get_payment_method_script_handles() {
        wp_register_script(
            'wc-nicky-blocks',
            plugins_url('assets/js/blocks.js', dirname(__FILE__)),
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            filemtime( plugin_dir_path( dirname(__FILE__) ) . 'assets/js/blocks.js' ),
            true
        );
        
        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'wc-nicky-blocks' );
        }
        
        return [ 'wc-nicky-blocks' ];
    }
    
    public function get_payment_method_data() {
        $gateway = $this->get_gateway();
        return [
            'title'       => $this->get_setting( 'title' ),
            'description' => $this->get_setting( 'description' ),
            'supports'    => array_filter( $this->get_supported_features(), [ $gateway, 'supports' ] ),
            'icon'        => $gateway->get_gateway_icon(),
            'instructions' => __('You will be redirected to Nicky.me to complete your payment securely.', 'nicky-payment-gateway'),
        ];
    }
    
    protected function get_gateway() {
        $payment_gateways = WC()->payment_gateways->payment_gateways();
        return $payment_gateways[ $this->name ];
    }
    
    public function get_supported_features() {
        return [
            'products',
        ];
    }
    
    protected function get_setting( $key, $default = '' ) {
        return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
    }
}

class Nicky_Payment_Blocks_Gateway {
    
    public static function init() {
        add_action( 'woocommerce_blocks_loaded', [ __CLASS__, 'woocommerce_gateway_nicky_woocommerce_block_support' ] );
    }
    
    public static function woocommerce_gateway_nicky_woocommerce_block_support() {
        if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                    $payment_method_registry->register( new WC_Gateway_Nicky_Blocks_Support );
                }
            );
        }
    }
}

Nicky_Payment_Blocks_Gateway::init();
