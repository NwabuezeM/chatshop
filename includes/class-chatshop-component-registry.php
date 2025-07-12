<?php
/**
 * Payment Gateway Registry
 *
 * @package ChatShop
 * @subpackage Components/Payment
 * @since 1.0.0
 */

namespace ChatShop\Components\Payment;

use ChatShop\Includes\ChatShop_Logger;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Payment Gateway Registry Class
 *
 * Manages registration and discovery of payment gateways
 *
 * @since 1.0.0
 */
class ChatShop_Payment_Registry {
    
    /**
     * Singleton instance
     *
     * @var ChatShop_Payment_Registry
     */
    private static $instance = null;
    
    /**
     * Registered gateways
     *
     * @var array
     */
    private $gateways = array();
    
    /**
     * Gateway capabilities index
     *
     * @var array
     */
    private $capabilities_index = array();
    
    /**
     * Currency support index
     *
     * @var array
     */
    private $currency_index = array();
    
    /**
     * Logger instance
     *
     * @var ChatShop_Logger
     */
    private $logger;
    
    /**
     * Private constructor
     */
    private function __construct() {
        $this->logger = new ChatShop_Logger();
        $this->init();
    }
    
    /**
     * Get singleton instance
     *
     * @return ChatShop_Payment_Registry
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize registry
     *
     * @return void
     */
    private function init() {
        // Register core gateways
        add_action('init', array($this, 'register_core_gateways'), 5);
        
        // Allow third-party gateway registration
        add_action('chatshop_register_payment_gateways', array($this, 'register_third_party_gateways'), 10);
        
        // Build indexes after registration
        add_action('init', array($this, 'build_indexes'), 20);
    }
    
    /**
     * Register a payment gateway
     *
     * @param string $gateway_id    Gateway identifier
     * @param array  $gateway_info  Gateway information
     * @return bool                 Success status
     */
    public function register_gateway($gateway_id, $gateway_info) {
        // Validate required fields
        $required_fields = array('class', 'name', 'supported_currencies', 'supported_features');
        
        foreach ($required_fields as $field) {
            if (!isset($gateway_info[$field])) {
                $this->logger->log(
                    sprintf('Gateway registration failed - missing field: %s', $field),
                    'error',
                    'payment',
                    array('gateway_id' => $gateway_id)
                );
                return false;
            }
        }
        
        // Sanitize gateway ID
        $gateway_id = sanitize_key($gateway_id);
        
        // Check for duplicates
        if (isset($this->gateways[$gateway_id])) {
            $this->logger->log(
                sprintf('Gateway already registered: %s', $gateway_id),
                'warning',
                'payment'
            );
            return false;
        }
        
        // Register gateway
        $this->gateways[$gateway_id] = array(
            'id'                    => $gateway_id,
            'class'                 => $gateway_info['class'],
            'name'                  => sanitize_text_field($gateway_info['name']),
            'description'           => isset($gateway_info['description']) 
                                      ? sanitize_text_field($gateway_info['description']) 
                                      : '',
            'supported_currencies'  => (array) $gateway_info['supported_currencies'],
            'supported_features'    => (array) $gateway_info['supported_features'],
            'icon'                  => isset($gateway_info['icon']) 
                                      ? esc_url_raw($gateway_info['icon']) 
                                      : '',
            'priority'              => isset($gateway_info['priority']) 
                                      ? absint($gateway_info['priority']) 
                                      : 10,
            'enabled'               => isset($gateway_info['enabled']) 
                                      ? (bool) $gateway_info['enabled'] 
                                      : true,
        );
        
        $this->logger->log(
            sprintf('Gateway registered: %s', $gateway_id),
            'info',
            'payment'
        );
        
        return true;
    }
    
    /**
     * Unregister a payment gateway
     *
     * @param string $gateway_id Gateway identifier
     * @return bool             Success status
     */
    public function unregister_gateway($gateway_id) {
        if (!isset($this->gateways[$gateway_id])) {
            return false;
        }
        
        unset($this->gateways[$gateway_id]);
        
        // Rebuild indexes
        $this->build_indexes();
        
        $this->logger->log(
            sprintf('Gateway unregistered: %s', $gateway_id),
            'info',
            'payment'
        );
        
        return true;
    }
    
    /**
     * Get gateway class name
     *
     * @param string $gateway_id Gateway identifier
     * @return string|null      Class name or null if not found
     */
    public function get_gateway_class($gateway_id) {
        return isset($this->gateways[$gateway_id]['class']) 
            ? $this->gateways[$gateway_id]['class'] 
            : null;
    }
    
    /**
     * Get gateway information
     *
     * @param string $gateway_id Gateway identifier
     * @return array|null       Gateway info or null if not found
     */
    public function get_gateway_info($gateway_id) {
        return isset($this->gateways[$gateway_id]) 
            ? $this->gateways[$gateway_id] 
            : null;
    }
    
    /**
     * Get all registered gateways
     *
     * @return array All registered gateways
     */
    public function get_all_gateways() {
        // Sort by priority
        uasort($this->gateways, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        
        return $this->gateways;
    }
    
    /**
     * Get enabled gateways
     *
     * @return array Enabled gateways
     */
    public function get_enabled_gateways() {
        return array_filter($this->gateways, function($gateway) {
            return isset($gateway['enabled']) && $gateway['enabled'];
        });
    }
    
    /**
     * Get gateways by capability
     *
     * @param string $capability Required capability
     * @return array            Gateways supporting the capability
     */
    public function get_gateways_by_capability($capability) {
        return isset($this->capabilities_index[$capability]) 
            ? $this->capabilities_index[$capability] 
            : array();
    }
    
    /**
     * Get gateways by currency
     *
     * @param string $currency Currency code
     * @return array          Gateways supporting the currency
     */
    public function get_gateways_by_currency($currency) {
        $currency = strtoupper($currency);
        return isset($this->currency_index[$currency]) 
            ? $this->currency_index[$currency] 
            : array();
    }
    
    /**
     * Check if gateway exists
     *
     * @param string $gateway_id Gateway identifier
     * @return bool
     */
    public function gateway_exists($gateway_id) {
        return isset($this->gateways[$gateway_id]);
    }
    
    /**
     * Register core gateways
     *
     * @return void
     */
    public function register_core_gateways() {
        // Paystack Gateway
        $this->register_gateway('paystack', array(
            'class'                 => 'ChatShop\Components\Payment\Gateways\Paystack\ChatShop_Paystack_Gateway',
            'name'                  => __('Paystack', 'chatshop'),
            'description'           => __('Accept payments via Paystack', 'chatshop'),
            'supported_currencies'  => array('NGN', 'GHS', 'ZAR', 'USD'),
            'supported_features'    => array(
                'products',
                'refunds',
                'payment_links',
                'recurring',
                'webhooks',
                'payment_verification',
                'split_payments',
                'subaccounts',
            ),
            'icon'                  => CHATSHOP_PLUGIN_URL . 'assets/icons/paystack.svg',
            'priority'              => 5,
        ));
        
        // PayPal Gateway
        $this->register_gateway('paypal', array(
            'class'                 => 'ChatShop\Components\Payment\Gateways\PayPal\ChatShop_PayPal_Gateway',
            'name'                  => __('PayPal', 'chatshop'),
            'description'           => __('Accept payments via PayPal', 'chatshop'),
            'supported_currencies'  => array('USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'),
            'supported_features'    => array(
                'products',
                'refunds',
                'payment_links',
                'recurring',
                'webhooks',
                'payment_verification',
                'express_checkout',
            ),
            'icon'                  => CHATSHOP_PLUGIN_URL . 'assets/icons/paypal.svg',
            'priority'              => 10,
        ));
        
        // Flutterwave Gateway
        $this->register_gateway('flutterwave', array(
            'class'                 => 'ChatShop\Components\Payment\Gateways\Flutterwave\ChatShop_Flutterwave_Gateway',
            'name'                  => __('Flutterwave', 'chatshop'),
            'description'           => __('Accept payments via Flutterwave', 'chatshop'),
            'supported_currencies'  => array('NGN', 'USD', 'GBP', 'EUR', 'GHS', 'KES', 'UGX', 'TZS', 'ZAR'),
            'supported_features'    => array(
                'products',
                'refunds',
                'payment_links',
                'recurring',
                'webhooks',
                'payment_verification',
                'split_payments',
                'subaccounts',
            ),
            'icon'                  => CHATSHOP_PLUGIN_URL . 'assets/icons/flutterwave.svg',
            'priority'              => 15,
        ));
        
        // Razorpay Gateway
        $this->register_gateway('razorpay', array(
            'class'                 => 'ChatShop\Components\Payment\Gateways\Razorpay\ChatShop_Razorpay_Gateway',
            'name'                  => __('Razorpay', 'chatshop'),
            'description'           => __('Accept payments via Razorpay', 'chatshop'),
            'supported_currencies'  => array('INR', 'USD', 'EUR', 'GBP', 'SGD', 'AED'),
            'supported_features'    => array(
                'products',
                'refunds',
                'payment_links',
                'recurring',
                'webhooks',
                'payment_verification',
                'smart_collect',
            ),
            'icon'                  => CHATSHOP_PLUGIN_URL . 'assets/icons/razorpay.svg',
            'priority'              => 20,
        ));
        
        do_action('chatshop_core_gateways_registered', $this);
    }
    
    /**
     * Register third-party gateways
     *
     * @return void
     */
    public function register_third_party_gateways() {
        // This method is called by the action hook to allow
        // third-party developers to register their gateways
        do_action('chatshop_register_gateways', $this);
    }
    
    /**
     * Build capability and currency indexes
     *
     * @return void
     */
    public function build_indexes() {
        $this->capabilities_index = array();
        $this->currency_index = array();
        
        foreach ($this->gateways as $gateway_id => $gateway_info) {
            // Build capabilities index
            foreach ($gateway_info['supported_features'] as $feature) {
                if (!isset($this->capabilities_index[$feature])) {
                    $this->capabilities_index[$feature] = array();
                }
                $this->capabilities_index[$feature][$gateway_id] = $gateway_info;
            }
            
            // Build currency index
            foreach ($gateway_info['supported_currencies'] as $currency) {
                $currency = strtoupper($currency);
                if (!isset($this->currency_index[$currency])) {
                    $this->currency_index[$currency] = array();
                }
                $this->currency_index[$currency][$gateway_id] = $gateway_info;
            }
        }
        
        $this->logger->log(
            'Gateway indexes built',
            'debug',
            'payment',
            array(
                'total_gateways' => count($this->gateways),
                'capabilities'   => count($this->capabilities_index),
                'currencies'     => count($this->currency_index),
            )
        );
    }
    
    /**
     * Get gateway priority
     *
     * @param string $gateway_id Gateway identifier
     * @return int              Priority value
     */
    public function get_gateway_priority($gateway_id) {
        return isset($this->gateways[$gateway_id]['priority']) 
            ? $this->gateways[$gateway_id]['priority'] 
            : 10;
    }
    
    /**
     * Update gateway settings
     *
     * @param string $gateway_id Gateway identifier
     * @param array  $settings   New settings
     * @return bool             Success status
     */
    public function update_gateway_settings($gateway_id, $settings) {
        if (!isset($this->gateways[$gateway_id])) {
            return false;
        }
        
        // Update enabled status if provided
        if (isset($settings['enabled'])) {
            $this->gateways[$gateway_id]['enabled'] = (bool) $settings['enabled'];
        }
        
        // Update priority if provided
        if (isset($settings['priority'])) {
            $this->gateways[$gateway_id]['priority'] = absint($settings['priority']);
        }
        
        // Rebuild indexes after update
        $this->build_indexes();
        
        $this->logger->log(
            sprintf('Gateway settings updated: %s', $gateway_id),
            'info',
            'payment'
        );
        
        return true;
    }
    
    /**
     * Export registry data
     *
     * @return array Registry data for export
     */
    public function export_data() {
        return array(
            'gateways'           => $this->gateways,
            'capabilities_index' => $this->capabilities_index,
            'currency_index'     => $this->currency_index,
            'timestamp'          => current_time('timestamp'),
        );
    }
    
    /**
     * Import registry data
     *
     * @param array $data Registry data to import
     * @return bool      Success status
     */
    public function import_data($data) {
        if (!isset($data['gateways']) || !is_array($data['gateways'])) {
            return false;
        }
        
        $this->gateways = $data['gateways'];
        $this->build_indexes();
        
        return true;
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }