<?php

/**
 * Abstract Payment Gateway Class
 *
 * @package ChatShop
 * @subpackage Includes/Abstracts
 * @since 1.0.0
 */

namespace ChatShop\Includes\Abstracts;

use ChatShop\Includes\ChatShop_Logger;
use ChatShop\Includes\ChatShop_Security;
use ChatShop\Includes\ChatShop_Cache;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Abstract class for payment gateways
 *
 * @since 1.0.0
 */
abstract class ChatShop_Payment_Gateway implements ChatShop_Payment_Gateway_Interface
{

    /**
     * Gateway ID
     *
     * @var string
     */
    protected $id = '';

    /**
     * Gateway display name
     *
     * @var string
     */
    protected $name = '';

    /**
     * Gateway description
     *
     * @var string
     */
    protected $description = '';

    /**
     * Gateway settings
     *
     * @var array
     */
    protected $settings = array();

    /**
     * Logger instance
     *
     * @var ChatShop_Logger
     */
    protected $logger;

    /**
     * Security instance
     *
     * @var ChatShop_Security
     */
    protected $security;

    /**
     * Cache instance
     *
     * @var ChatShop_Cache
     */
    protected $cache;

    /**
     * Supported currencies
     *
     * @var array
     */
    protected $supported_currencies = array();

    /**
     * Supported features
     *
     * @var array
     */
    protected $supported_features = array();

    /**
     * Gateway icon URL
     *
     * @var string
     */
    protected $icon = '';

    /**
     * Test mode flag
     *
     * @var bool
     */
    protected $test_mode = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = new ChatShop_Logger();
        $this->security = new ChatShop_Security();
        $this->cache = new ChatShop_Cache();

        $this->init();
        $this->load_settings();
        $this->register_hooks();
    }

    /**
     * Initialize gateway
     *
     * @return void
     */
    protected function init()
    {
        // Override in child classes to set gateway properties
    }

    /**
     * Load gateway settings
     *
     * @return void
     */
    protected function load_settings()
    {
        $this->settings = get_option('chatshop_gateway_' . $this->id . '_settings', array());
        $this->test_mode = isset($this->settings['test_mode']) && $this->settings['test_mode'] === 'yes';
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    protected function register_hooks()
    {
        add_filter('chatshop_payment_gateways', array($this, 'register_gateway'));
        add_action('chatshop_payment_' . $this->id . '_webhook', array($this, 'process_webhook'));
    }

    /**
     * Register this gateway
     *
     * @param array $gateways Existing gateways
     * @return array
     */
    public function register_gateway($gateways)
    {
        $gateways[$this->id] = $this;
        return $gateways;
    }

    /**
     * Get gateway information
     *
     * @return array
     */
    public function get_gateway_info()
    {
        return array(
            'id'                    => $this->id,
            'name'                  => $this->name,
            'description'           => $this->description,
            'icon'                  => $this->icon,
            'supported_currencies'  => $this->supported_currencies,
            'supported_features'    => $this->supported_features,
            'test_mode'            => $this->test_mode,
        );
    }

    /**
     * Check if gateway is available
     *
     * @return bool
     */
    public function is_available()
    {
        // Check if gateway is enabled
        if (!$this->is_enabled()) {
            return false;
        }

        // Validate configuration
        $validation = $this->validate_configuration();
        if (is_wp_error($validation)) {
            return false;
        }

        return true;
    }

    /**
     * Check if gateway is enabled
     *
     * @return bool
     */
    protected function is_enabled()
    {
        return isset($this->settings['enabled']) && $this->settings['enabled'] === 'yes';
    }

    /**
     * Get supported currencies
     *
     * @return array
     */
    public function get_supported_currencies()
    {
        return apply_filters('chatshop_gateway_' . $this->id . '_supported_currencies', $this->supported_currencies);
    }

    /**
     * Get supported features
     *
     * @return array
     */
    public function get_supported_features()
    {
        return apply_filters('chatshop_gateway_' . $this->id . '_supported_features', $this->supported_features);
    }

    /**
     * Check if feature is supported
     *
     * @param string $feature Feature name
     * @return bool
     */
    public function supports($feature)
    {
        return in_array($feature, $this->supported_features, true);
    }

    /**
     * Get setting value
     *
     * @param string $key     Setting key
     * @param mixed  $default Default value
     * @return mixed
     */
    protected function get_setting($key, $default = null)
    {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * Generate unique transaction reference
     *
     * @param string $prefix Optional prefix
     * @return string
     */
    protected function generate_transaction_reference($prefix = '')
    {
        $reference = $prefix . 'CS_' . $this->id . '_' . time() . '_' . wp_generate_password(8, false);
        return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', $reference));
    }

    /**
     * Log gateway activity
     *
     * @param string $message Log message
     * @param string $level   Log level
     * @param array  $context Additional context
     * @return void
     */
    protected function log($message, $level = 'info', $context = array())
    {
        $context['gateway'] = $this->id;
        $this->logger->log($message, $level, 'payment', $context);
    }

    /**
     * Send API request
     *
     * @param string $endpoint API endpoint
     * @param array  $args     Request arguments
     * @param string $method   HTTP method
     * @return array|WP_Error
     */
    protected function api_request($endpoint, $args = array(), $method = 'POST')
    {
        $this->log('API Request to ' . $endpoint, 'debug', array('method' => $method));

        $response = wp_remote_request($endpoint, array(
            'method'  => $method,
            'body'    => $method !== 'GET' ? wp_json_encode($args) : null,
            'headers' => $this->get_api_headers(),
            'timeout' => 45,
        ));

        if (is_wp_error($response)) {
            $this->log('API Request Failed', 'error', array('error' => $response->get_error_message()));
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('json_decode_error', __('Invalid JSON response', 'chatshop'));
        }

        return $data;
    }

    /**
     * Get API headers
     *
     * @return array
     */
    protected function get_api_headers()
    {
        return array(
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        );
    }

    /**
     * Process webhook request
     *
     * @return void
     */
    public function process_webhook()
    {
        $payload = $this->get_webhook_payload();

        if (empty($payload)) {
            $this->send_webhook_response(400, 'Invalid payload');
            return;
        }

        // Verify webhook authenticity
        if (!$this->verify_webhook($payload)) {
            $this->send_webhook_response(401, 'Unauthorized');
            return;
        }

        // Process the webhook
        $result = $this->handle_webhook($payload);

        if (is_wp_error($result)) {
            $this->send_webhook_response(400, $result->get_error_message());
            return;
        }

        $this->send_webhook_response(200, 'OK');
    }

    /**
     * Get webhook payload
     *
     * @return array|null
     */
    protected function get_webhook_payload()
    {
        $raw_input = file_get_contents('php://input');
        return json_decode($raw_input, true);
    }

    /**
     * Verify webhook authenticity
     *
     * @param array $payload Webhook payload
     * @return bool
     */
    protected function verify_webhook($payload)
    {
        // Override in child classes to implement gateway-specific verification
        return true;
    }

    /**
     * Send webhook response
     *
     * @param int    $status  HTTP status code
     * @param string $message Response message
     * @return void
     */
    protected function send_webhook_response($status, $message)
    {
        http_response_code($status);
        echo wp_json_encode(array('message' => $message));
        exit;
    }

    /**
     * Sanitize amount
     *
     * @param float $amount Amount to sanitize
     * @return float
     */
    protected function sanitize_amount($amount)
    {
        return round(floatval($amount), 2);
    }

    /**
     * Format amount for gateway
     *
     * @param float  $amount   Amount to format
     * @param string $currency Currency code
     * @return mixed
     */
    protected function format_amount($amount, $currency)
    {
        // Some gateways need amounts in cents/kobo
        return $amount;
    }

    /**
     * Fire payment event
     *
     * @param string $event Event name
     * @param array  $data  Event data
     * @return void
     */
    protected function fire_event($event, $data = array())
    {
        $data['gateway'] = $this->id;
        $data['timestamp'] = current_time('mysql');

        do_action('chatshop_payment_' . $event, $data);
        do_action('chatshop_payment_' . $this->id . '_' . $event, $data);
    }
}
