<?php

/**
 * Payment Webhook Handler
 *
 * @package ChatShop
 * @subpackage Components/Payment
 * @since 1.0.0
 */

namespace ChatShop\Components\Payment;

use ChatShop\Includes\ChatShop_Logger;
use ChatShop\Includes\ChatShop_Security;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Payment Webhook Handler Class
 *
 * Unified webhook processing for all payment gateways
 *
 * @since 1.0.0
 */
class ChatShop_Payment_Webhook_Handler
{

    /**
     * Logger instance
     *
     * @var ChatShop_Logger
     */
    private $logger;

    /**
     * Security instance
     *
     * @var ChatShop_Security
     */
    private $security;

    /**
     * Payment factory
     *
     * @var ChatShop_Payment_Factory
     */
    private $factory;

    /**
     * Transaction manager
     *
     * @var ChatShop_Transaction_Manager
     */
    private $transaction_manager;

    /**
     * Webhook processors
     *
     * @var array
     */
    private $processors = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = new ChatShop_Logger();
        $this->security = new ChatShop_Security();
        $this->factory = ChatShop_Payment_Factory::get_instance();
        $this->transaction_manager = new ChatShop_Transaction_Manager();
    }

    /**
     * Initialize webhook handler
     *
     * @return void
     */
    public function init()
    {
        // Register webhook endpoint
        add_action('template_redirect', array($this, 'handle_webhook_request'));

        // Register processors for each gateway
        add_action('init', array($this, 'register_webhook_processors'), 15);

        // Webhook processing hooks
        add_action('chatshop_webhook_received', array($this, 'log_webhook'), 10, 3);
        add_action('chatshop_webhook_processed', array($this, 'update_transaction_from_webhook'), 10, 3);
    }

    /**
     * Handle webhook request
     *
     * @return void
     */
    public function handle_webhook_request()
    {
        // Check if this is a webhook request
        $webhook_type = get_query_var('chatshop_webhook');
        $gateway_id = get_query_var('gateway');

        if ($webhook_type !== 'payment' || !$gateway_id) {
            return;
        }

        // Process webhook
        $this->process_webhook($gateway_id);
    }

    /**
     * Process webhook
     *
     * @param string $gateway_id Gateway identifier
     * @return void
     */
    public function process_webhook($gateway_id)
    {
        // Log webhook receipt
        $this->logger->log(
            'Webhook received',
            'info',
            'webhook',
            array(
                'gateway_id' => $gateway_id,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            )
        );

        // Get raw payload
        $payload = $this->get_webhook_payload();

        if (empty($payload)) {
            $this->send_webhook_response(400, 'Invalid payload');
            return;
        }

        // Fire webhook received event
        do_action('chatshop_webhook_received', $gateway_id, $payload, $_SERVER);

        // Get gateway instance
        $gateway = $this->factory->create_gateway($gateway_id);

        if (!$gateway) {
            $this->send_webhook_response(404, 'Gateway not found');
            return;
        }

        try {
            // Process through gateway-specific handler
            $result = $gateway->handle_webhook($payload);

            if (is_wp_error($result)) {
                throw new \Exception($result->get_error_message());
            }

            // Fire webhook processed event
            do_action('chatshop_webhook_processed', $gateway_id, $payload, $result);

            // Send success response
            $this->send_webhook_response(200, 'OK');
        } catch (\Exception $e) {
            $this->logger->log(
                'Webhook processing failed',
                'error',
                'webhook',
                array(
                    'gateway_id' => $gateway_id,
                    'error'      => $e->getMessage(),
                )
            );

            $this->send_webhook_response(500, 'Processing failed');
        }
    }

    /**
     * Register webhook processor
     *
     * @param string   $gateway_id Gateway identifier
     * @param callable $processor  Processor callback
     * @return void
     */
    public function register_processor($gateway_id, $processor)
    {
        if (!is_callable($processor)) {
            $this->logger->log(
                'Invalid webhook processor registered',
                'error',
                'webhook',
                array('gateway_id' => $gateway_id)
            );
            return;
        }

        $this->processors[$gateway_id] = $processor;
    }

    /**
     * Get webhook payload
     *
     * @return array|null
     */
    private function get_webhook_payload()
    {
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        $raw_input = file_get_contents('php://input');

        if (empty($raw_input)) {
            return null;
        }

        // Store raw payload for signature verification
        $GLOBALS['chatshop_raw_webhook_payload'] = $raw_input;

        // Parse based on content type
        if (strpos($content_type, 'application/json') !== false) {
            $payload = json_decode($raw_input, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->log(
                    'Invalid JSON in webhook payload',
                    'error',
                    'webhook',
                    array('error' => json_last_error_msg())
                );
                return null;
            }

            return $payload;
        }

        // Parse as form data
        parse_str($raw_input, $payload);
        return $payload;
    }

    /**
     * Send webhook response
     *
     * @param int    $status  HTTP status code
     * @param string $message Response message
     * @return void
     */
    private function send_webhook_response($status, $message)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo wp_json_encode(array(
            'status'  => $status,
            'message' => $message,
        ));
        exit;
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip()
    {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];

                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }

                $ip = trim($ip);

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Verify webhook signature
     *
     * @param string $gateway_id Gateway identifier
     * @param array  $headers    Request headers
     * @param string $payload    Raw payload
     * @return bool
     */
    public function verify_webhook_signature($gateway_id, $headers, $payload)
    {
        $gateway = $this->factory->create_gateway($gateway_id);

        if (!$gateway) {
            return false;
        }

        // Each gateway implements its own signature verification
        return $gateway->verify_webhook_signature($headers, $payload);
    }

    /**
     * Log webhook
     *
     * @param string $gateway_id Gateway identifier
     * @param array  $payload    Webhook payload
     * @param array  $headers    Request headers
     * @return void
     */
    public function log_webhook($gateway_id, $payload, $headers)
    {
        // Create webhook log entry
        $log_data = array(
            'gateway_id'   => $gateway_id,
            'payload'      => $payload,
            'headers'      => $this->sanitize_headers($headers),
            'ip_address'   => $this->get_client_ip(),
            'received_at'  => current_time('mysql'),
        );

        // Store in database (implement webhook log table)
        do_action('chatshop_store_webhook_log', $log_data);
    }

    /**
     * Update transaction from webhook
     *
     * @param string $gateway_id Gateway identifier
     * @param array  $payload    Webhook payload
     * @param array  $result     Processing result
     * @return void
     */
    public function update_transaction_from_webhook($gateway_id, $payload, $result)
    {
        if (!isset($result['transaction_id'])) {
            return;
        }

        $transaction_id = $result['transaction_id'];
        $update_data = array();

        // Update status if provided
        if (isset($result['status'])) {
            $update_data['status'] = $result['status'];
        }

        // Store webhook data
        $update_data['last_webhook'] = array(
            'gateway_id'  => $gateway_id,
            'received_at' => current_time('mysql'),
            'type'        => $result['webhook_type'] ?? 'unknown',
            'result'      => $result,
        );

        // Update transaction
        $this->transaction_manager->update_transaction($transaction_id, $update_data);

        // Fire specific events based on webhook type
        if (isset($result['webhook_type'])) {
            switch ($result['webhook_type']) {
                case 'payment.success':
                    do_action('chatshop_payment_completed', $transaction_id, $result);
                    break;

                case 'payment.failed':
                    do_action('chatshop_payment_failed', $transaction_id, $result);
                    break;

                case 'refund.completed':
                    do_action(
                        'chatshop_payment_refunded',
                        $transaction_id,
                        $result['refund_amount'] ?? null,
                        $result['refund_reason'] ?? ''
                    );
                    break;
            }
        }
    }

    /**
     * Sanitize headers for logging
     *
     * @param array $headers Raw headers
     * @return array
     */
    private function sanitize_headers($headers)
    {
        $sanitized = array();
        $allowed_headers = array(
            'CONTENT_TYPE',
            'HTTP_USER_AGENT',
            'HTTP_X_WEBHOOK_SIGNATURE',
            'HTTP_X_PAYSTACK_SIGNATURE',
            'HTTP_X_PAYPAL_TRANSMISSION_SIG',
            'HTTP_X_FLUTTERWAVE_WEBHOOK_SIGNATURE',
            'HTTP_X_RAZORPAY_SIGNATURE',
        );

        foreach ($allowed_headers as $header) {
            if (isset($headers[$header])) {
                $sanitized[$header] = sanitize_text_field($headers[$header]);
            }
        }

        return $sanitized;
    }

    /**
     * Register webhook processors
     *
     * @return void
     */
    public function register_webhook_processors()
    {
        // Allow gateways to register their processors
        do_action('chatshop_register_webhook_processors', $this);
    }

    /**
     * Handle duplicate webhook
     *
     * @param string $webhook_id Unique webhook identifier
     * @return bool True if duplicate
     */
    public function is_duplicate_webhook($webhook_id)
    {
        $cache_key = 'webhook_' . md5($webhook_id);

        // Check if we've seen this webhook recently
        if ($this->cache->get($cache_key)) {
            $this->logger->log(
                'Duplicate webhook detected',
                'warning',
                'webhook',
                array('webhook_id' => $webhook_id)
            );
            return true;
        }

        // Mark as processed (cache for 1 hour)
        $this->cache->set($cache_key, true, 3600);

        return false;
    }

    /**
     * Get webhook statistics
     *
     * @param string $gateway_id Optional gateway filter
     * @param string $period     Time period
     * @return array
     */
    public function get_webhook_statistics($gateway_id = null, $period = 'day')
    {
        $stats = array(
            'total_received'    => 0,
            'successful'        => 0,
            'failed'            => 0,
            'duplicates'        => 0,
            'by_type'           => array(),
            'by_gateway'        => array(),
            'response_times'    => array(),
        );

        // This would query webhook log data
        return apply_filters('chatshop_webhook_statistics', $stats, $gateway_id, $period);
    }

    /**
     * Retry failed webhook
     *
     * @param int $webhook_log_id Webhook log ID
     * @return bool
     */
    public function retry_webhook($webhook_log_id)
    {
        // Get webhook log data
        $log_data = apply_filters('chatshop_get_webhook_log', null, $webhook_log_id);

        if (!$log_data) {
            return false;
        }

        // Reprocess webhook
        $gateway = $this->factory->create_gateway($log_data['gateway_id']);

        if (!$gateway) {
            return false;
        }

        try {
            $result = $gateway->handle_webhook($log_data['payload']);

            // Update log with retry result
            do_action('chatshop_webhook_retry_complete', $webhook_log_id, $result);

            return !is_wp_error($result);
        } catch (\Exception $e) {
            $this->logger->log(
                'Webhook retry failed',
                'error',
                'webhook',
                array(
                    'webhook_log_id' => $webhook_log_id,
                    'error'          => $e->getMessage(),
                )
            );

            return false;
        }
    }
}
