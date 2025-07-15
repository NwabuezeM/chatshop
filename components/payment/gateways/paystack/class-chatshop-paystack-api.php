<?php
/**
 * Paystack API Client
 *
 * File: components/payment/gateways/paystack/class-chatshop-paystack-api.php
 * 
 * @package ChatShop
 * @subpackage Payment\Gateways\Paystack
 * @since 1.0.0
 */

namespace ChatShop\Payment\Gateways\Paystack;

use ChatShop\Core\ChatShop_Logger;
use ChatShop\Core\ChatShop_Security;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Paystack API Client
 * 
 * Handles all API communication with Paystack
 */
class ChatShop_Paystack_API {

    /**
     * Paystack API base URL
     */
    const API_BASE_URL = 'https://api.paystack.co';

    /**
     * Gateway instance
     *
     * @var ChatShop_Paystack_Gateway
     */
    private $gateway;

    /**
     * Logger instance
     *
     * @var ChatShop_Logger
     */
    private $logger;

    /**
     * Rate limiter
     *
     * @var array
     */
    private $rate_limits = [];

    /**
     * Constructor
     *
     * @param ChatShop_Paystack_Gateway $gateway Gateway instance
     */
    public function __construct($gateway) {
        $this->gateway = $gateway;
        $this->logger = new ChatShop_Logger('paystack-api');
    }

    /**
     * Initialize transaction
     *
     * @param array $data Transaction data
     * @return array
     */
    public function initialize_transaction($data) {
        $endpoint = '/transaction/initialize';
        
        $payload = $this->sanitize_transaction_data($data);
        
        $this->logger->info('Initializing transaction', ['payload' => $payload]);
        
        return $this->make_request('POST', $endpoint, $payload);
    }

    /**
     * Verify transaction
     *
     * @param string $reference Transaction reference
     * @return array
     */
    public function verify_transaction($reference) {
        if (empty($reference)) {
            return ['status' => false, 'message' => 'Invalid reference'];
        }

        $endpoint = '/transaction/verify/' . sanitize_text_field($reference);
        
        $this->logger->info('Verifying transaction', ['reference' => $reference]);
        
        return $this->make_request('GET', $endpoint);
    }

    /**
     * Create refund
     *
     * @param array $data Refund data
     * @return array
     */
    public function create_refund($data) {
        $endpoint = '/refund';
        
        $payload = [
            'transaction' => sanitize_text_field($data['transaction']),
            'amount' => absint($data['amount']),
            'currency' => sanitize_text_field($data['currency']),
            'customer_note' => sanitize_textarea_field($data['customer_note'] ?? ''),
            'merchant_note' => sanitize_textarea_field($data['merchant_note'] ?? '')
        ];

        // Remove empty values
        $payload = array_filter($payload, function($value) {
            return $value !== '' && $value !== null;
        });
        
        $this->logger->info('Creating refund', ['payload' => $payload]);
        
        return $this->make_request('POST', $endpoint, $payload);
    }

    /**
     * Create customer
     *
     * @param array $data Customer data
     * @return array
     */
    public function create_customer($data) {
        $endpoint = '/customer';
        
        $payload = [
            'email' => sanitize_email($data['email']),
            'first_name' => sanitize_text_field($data['first_name'] ?? ''),
            'last_name' => sanitize_text_field($data['last_name'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'metadata' => $data['metadata'] ?? []
        ];

        // Remove empty values
        $payload = array_filter($payload, function($value) {
            return $value !== '' && $value !== null;
        });
        
        $this->logger->info('Creating customer', ['email' => $payload['email']]);
        
        return $this->make_request('POST', $endpoint, $payload);
    }

    /**
     * Get customer by email or ID
     *
     * @param string $identifier Customer email or ID
     * @return array
     */
    public function get_customer($identifier) {
        if (empty($identifier)) {
            return ['status' => false, 'message' => 'Invalid identifier'];
        }

        $endpoint = '/customer/' . urlencode(sanitize_text_field($identifier));
        
        return $this->make_request('GET', $endpoint);
    }

    /**
     * Create payment request
     *
     * @param array $data Payment request data
     * @return array
     */
    public function create_payment_request($data) {
        $endpoint = '/paymentrequest';
        
        $payload = [
            'description' => sanitize_text_field($data['description']),
            'amount' => absint($data['amount']),
            'currency' => sanitize_text_field($data['currency']),
            'due_date' => sanitize_text_field($data['due_date'] ?? ''),
            'send_notification' => (bool) ($data['send_notification'] ?? false),
            'draft' => (bool) ($data['draft'] ?? false),
            'has_invoice' => (bool) ($data['has_invoice'] ?? false),
            'invoice_number' => absint($data['invoice_number'] ?? 0),
            'line_items' => $data['line_items'] ?? [],
            'tax' => $data['tax'] ?? [],
            'metadata' => $data['metadata'] ?? []
        ];

        // Remove empty values
        $payload = array_filter($payload, function($value) {
            return $value !== '' && $value !== null && $value !== 0;
        });
        
        $this->logger->info('Creating payment request', ['description' => $payload['description']]);
        
        return $this->make_request('POST', $endpoint, $payload);
    }

    /**
     * Get payment request
     *
     * @param string $id_or_code Payment request ID or code
     * @return array
     */
    public function get_payment_request($id_or_code) {
        if (empty($id_or_code)) {
            return ['status' => false, 'message' => 'Invalid ID or code'];
        }

        $endpoint = '/paymentrequest/' . urlencode(sanitize_text_field($id_or_code));
        
        return $this->make_request('GET', $endpoint);
    }

    /**
     * Create subscription
     *
     * @param array $data Subscription data
     * @return array
     */
    public function create_subscription($data) {
        $endpoint = '/subscription';
        
        $payload = [
            'customer' => sanitize_text_field($data['customer']),
            'plan' => sanitize_text_field($data['plan']),
            'authorization' => sanitize_text_field($data['authorization'] ?? ''),
            'start_date' => sanitize_text_field($data['start_date'] ?? '')
        ];

        // Remove empty values
        $payload = array_filter($payload, function($value) {
            return $value !== '' && $value !== null;
        });
        
        $this->logger->info('Creating subscription', ['customer' => $payload['customer']]);
        
        return $this->make_request('POST', $endpoint, $payload);
    }

    /**
     * Cancel subscription
     *
     * @param string $code Subscription code
     * @param string $token Email token
     * @return array
     */
    public function cancel_subscription($code, $token) {
        if (empty($code) || empty($token)) {
            return ['status' => false, 'message' => 'Invalid subscription code or token'];
        }

        $endpoint = '/subscription/disable';
        
        $payload = [
            'code' => sanitize_text_field($code),
            'token' => sanitize_text_field($token)
        ];
        
        $this->logger->info('Cancelling subscription', ['code' => $code]);
        
        return $this->make_request('POST', $endpoint, $payload);
    }

    /**
     * Get transaction timeline
     *
     * @param string $id_or_reference Transaction ID or reference
     * @return array
     */
    public function get_transaction_timeline($id_or_reference) {
        if (empty($id_or_reference)) {
            return ['status' => false, 'message' => 'Invalid transaction identifier'];
        }

        $endpoint = '/transaction/timeline/' . urlencode(sanitize_text_field($id_or_reference));
        
        return $this->make_request('GET', $endpoint);
    }

    /**
     * Charge authorization
     *
     * @param array $data Charge data
     * @return array
     */
    public function charge_authorization($data) {
        $endpoint = '/transaction/charge_authorization';
        
        $payload = [
            'authorization_code' => sanitize_text_field($data['authorization_code']),
            'email' => sanitize_email($data['email']),
            'amount' => absint($data['amount']),
            'currency' => sanitize_text_field($data['currency'] ?? 'NGN'),
            'reference' => sanitize_text_field($data['reference'] ?? ''),
            'metadata' => $data['metadata'] ?? []
        ];

        // Remove empty values
        $payload = array_filter($payload, function($value) {
            return $value !== '' && $value !== null;
        });
        
        $this->logger->info('Charging authorization', ['email' => $payload['email']]);
        
        return $this->make_request('POST', $endpoint, $payload);
    }

    /**
     * List banks
     *
     * @param string $country Country code
     * @param string $currency Currency code
     * @return array
     */
    public function list_banks($country = 'nigeria', $currency = 'NGN') {
        $endpoint = '/bank';
        
        $params = [
            'country' => sanitize_text_field($country),
            'currency' => sanitize_text_field($currency)
        ];
        
        $endpoint .= '?' . http_build_query($params);
        
        return $this->make_request('GET', $endpoint);
    }

    /**
     * Resolve account number
     *
     * @param string $account_number Account number
     * @param string $bank_code Bank code
     * @return array
     */
    public function resolve_account($account_number, $bank_code) {
        $endpoint = '/bank/resolve';
        
        $params = [
            'account_number' => sanitize_text_field($account_number),
            'bank_code' => sanitize_text_field($bank_code)
        ];
        
        $endpoint .= '?' . http_build_query($params);
        
        return $this->make_request('GET', $endpoint);
    }

    /**
     * Make HTTP request to Paystack API
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array
     */
    private function make_request($method, $endpoint, $data = []) {
        try {
            // Check rate limits
            if (!$this->check_rate_limit($endpoint)) {
                return [
                    'status' => false,
                    'message' => 'Rate limit exceeded. Please try again later.'
                ];
            }

            $url = self::API_BASE_URL . $endpoint;
            $secret_key = $this->gateway->get_secret_key();

            if (empty($secret_key)) {
                throw new \Exception('Paystack secret key not configured');
            }

            $headers = [
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache',
                'User-Agent' => 'ChatShop/' . CHATSHOP_VERSION . ' (WordPress/' . get_bloginfo('version') . ')'
            ];

            $args = [
                'method' => strtoupper($method),
                'headers' => $headers,
                'timeout' => 30,
                'sslverify' => true,
                'user-agent' => 'ChatShop/' . CHATSHOP_VERSION
            ];

            if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $args['body'] = wp_json_encode($data);
            }

            $this->logger->debug('Making API request', [
                'method' => $method,
                'url' => $url,
                'data' => $data
            ]);

            $response = wp_remote_request($url, $args);

            // Update rate limit tracking
            $this->update_rate_limit($endpoint);

            if (is_wp_error($response)) {
                throw new \Exception('HTTP Error: ' . $response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            $this->logger->debug('API response received', [
                'status_code' => $response_code,
                'response' => $response_body
            ]);

            if ($response_code >= 400) {
                $error_data = json_decode($response_body, true);
                $error_message = $error_data['message'] ?? 'API Error: ' . $response_code;
                throw new \Exception($error_message);
            }

            $decoded_response = json_decode($response_body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from Paystack');
            }

            return $decoded_response;

        } catch (\Exception $e) {
            $this->logger->error('API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Sanitize transaction data
     *
     * @param array $data Raw transaction data
     * @return array
     */
    private function sanitize_transaction_data($data) {
        $sanitized = [
            'email' => sanitize_email($data['email']),
            'amount' => absint($data['amount']),
            'currency' => sanitize_text_field($data['currency']),
            'reference' => sanitize_text_field($data['reference']),
            'callback_url' => esc_url_raw($data['callback_url']),
            'metadata' => $this->sanitize_metadata($data['metadata'] ?? []),
            'channels' => array_map('sanitize_text_field', $data['channels'] ?? [])
        ];

        // Optional fields
        if (!empty($data['split_code'])) {
            $sanitized['split_code'] = sanitize_text_field($data['split_code']);
        }

        if (!empty($data['subaccount'])) {
            $sanitized['subaccount'] = sanitize_text_field($data['subaccount']);
        }

        if (!empty($data['transaction_charge'])) {
            $sanitized['transaction_charge'] = absint($data['transaction_charge']);
        }

        if (!empty($data['bearer'])) {
            $sanitized['bearer'] = sanitize_text_field($data['bearer']);
        }

        return $sanitized;
    }

    /**
     * Sanitize metadata
     *
     * @param array $metadata Raw metadata
     * @return array
     */
    private function sanitize_metadata($metadata) {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            $clean_key = sanitize_key($key);
            
            if (is_array($value)) {
                $sanitized[$clean_key] = $this->sanitize_metadata($value);
            } elseif (is_string($value)) {
                $sanitized[$clean_key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[$clean_key] = $value;
            } elseif (is_bool($value)) {
                $sanitized[$clean_key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Check rate limit for endpoint
     *
     * @param string $endpoint API endpoint
     * @return bool
     */
    private function check_rate_limit($endpoint) {
        $key = 'paystack_rate_limit_' . md5($endpoint);
        $limit_data = get_transient($key);

        if (!$limit_data) {
            return true;
        }

        // Allow 100 requests per minute per endpoint
        $max_requests = 100;
        $time_window = 60; // seconds

        if ($limit_data['count'] >= $max_requests) {
            $this->logger->warning('Rate limit exceeded', [
                'endpoint' => $endpoint,
                'count' => $limit_data['count']
            ]);
            return false;
        }

        return true;
    }

    /**
     * Update rate limit tracking
     *
     * @param string $endpoint API endpoint
     */
    private function update_rate_limit($endpoint) {
        $key = 'paystack_rate_limit_' . md5($endpoint);
        $limit_data = get_transient($key);

        if (!$limit_data) {
            $limit_data = ['count' => 0, 'start_time' => time()];
        }

        $limit_data['count']++;
        set_transient($key, $limit_data, 60); // 1 minute expiry
    }

    /**
     * Validate webhook signature
     *
     * @param string $payload Webhook payload
     * @param string $signature Webhook signature
     * @return bool
     */
    public function validate_webhook_signature($payload, $signature) {
        $secret_key = $this->gateway->get_secret_key();
        
        if (empty($secret_key)) {
            return false;
        }

        $expected_signature = hash_hmac('sha512', $payload, $secret_key);
        
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Get API health status
     *
     * @return array
     */
    public function get_api_health() {
        $endpoint = '/bank'; // Simple endpoint to test connectivity
        
        $start_time = microtime(true);
        $response = $this->make_request('GET', $endpoint);
        $end_time = microtime(true);
        
        $response_time = round(($end_time - $start_time) * 1000, 2); // milliseconds

        return [
            'status' => $response['status'] ?? false,
            'response_time' => $response_time,
            'message' => $response['message'] ?? 'Unknown error'
        ];
    }

    /**
     * Clear rate limit cache
     */
    public function clear_rate_limits() {
        global $wpdb;
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_paystack_rate_limit_%'
            )
        );
        
        $this->logger->info('Rate limit cache cleared');
    }
}