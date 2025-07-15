<?php
/**
 * Paystack Validator Class
 *
 * File: components/payment/gateways/paystack/class-chatshop-paystack-validator.php
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
 * ChatShop Paystack Validator Class
 * 
 * Handles validation of Paystack transactions and data
 */
class ChatShop_Paystack_Validator {

    /**
     * Gateway instance
     *
     * @var ChatShop_Paystack_Gateway
     */
    private $gateway;

    /**
     * API client
     *
     * @var ChatShop_Paystack_API
     */
    private $api_client;

    /**
     * Logger instance
     *
     * @var ChatShop_Logger
     */
    private $logger;

    /**
     * Supported currencies
     */
    const SUPPORTED_CURRENCIES = ['NGN', 'USD', 'GHS', 'ZAR', 'KES', 'XOF', 'EGP'];

    /**
     * Minimum amounts per currency (in smallest unit)
     */
    const MIN_AMOUNTS = [
        'NGN' => 100,    // 1 NGN
        'USD' => 50,     // $0.50
        'GHS' => 100,    // 1 GHS
        'ZAR' => 100,    // 1 ZAR
        'KES' => 100,    // 1 KES
        'XOF' => 100,    // 100 XOF
        'EGP' => 100     // 1 EGP
    ];

    /**
     * Maximum amounts per currency (in smallest unit)
     */
    const MAX_AMOUNTS = [
        'NGN' => 500000000,  // 5,000,000 NGN
        'USD' => 1000000,    // $10,000
        'GHS' => 500000000,  // 5,000,000 GHS
        'ZAR' => 500000000,  // 5,000,000 ZAR
        'KES' => 500000000,  // 5,000,000 KES
        'XOF' => 500000000,  // 5,000,000 XOF
        'EGP' => 500000000   // 5,000,000 EGP
    ];

    /**
     * Constructor
     *
     * @param ChatShop_Paystack_Gateway $gateway Gateway instance
     */
    public function __construct($gateway) {
        $this->gateway = $gateway;
        $this->api_client = new ChatShop_Paystack_API($gateway);
        $this->logger = new ChatShop_Logger('paystack-validator');
    }

    /**
     * Validate transaction before processing
     *
     * @param array $transaction_data Transaction data
     * @return array Validation result
     */
    public function validate_transaction($transaction_data) {
        $errors = [];

        try {
            // Validate required fields
            $required_fields = ['email', 'amount', 'currency', 'reference'];
            foreach ($required_fields as $field) {
                if (empty($transaction_data[$field])) {
                    $errors[] = sprintf(__('Field %s is required', 'chatshop'), $field);
                }
            }

            if (!empty($errors)) {
                return $this->format_validation_result(false, $errors);
            }

            // Validate email
            if (!$this->validate_email($transaction_data['email'])) {
                $errors[] = __('Invalid email address', 'chatshop');
            }

            // Validate currency
            if (!$this->validate_currency($transaction_data['currency'])) {
                $errors[] = sprintf(
                    __('Currency %s is not supported', 'chatshop'),
                    $transaction_data['currency']
                );
            }

            // Validate amount
            $amount_validation = $this->validate_amount(
                $transaction_data['amount'],
                $transaction_data['currency']
            );

            if (!$amount_validation['valid']) {
                $errors[] = $amount_validation['message'];
            }

            // Validate reference uniqueness
            if (!$this->validate_reference_uniqueness($transaction_data['reference'])) {
                $errors[] = __('Transaction reference already exists', 'chatshop');
            }

            // Validate callback URL
            if (!empty($transaction_data['callback_url'])) {
                if (!$this->validate_url($transaction_data['callback_url'])) {
                    $errors[] = __('Invalid callback URL', 'chatshop');
                }
            }

            // Validate channels
            if (!empty($transaction_data['channels'])) {
                $channel_validation = $this->validate_channels($transaction_data['channels']);
                if (!$channel_validation['valid']) {
                    $errors[] = $channel_validation['message'];
                }
            }

            // Validate metadata
            if (!empty($transaction_data['metadata'])) {
                $metadata_validation = $this->validate_metadata($transaction_data['metadata']);
                if (!$metadata_validation['valid']) {
                    $errors[] = $metadata_validation['message'];
                }
            }

            return $this->format_validation_result(empty($errors), $errors);

        } catch (\Exception $e) {
            $this->logger->error('Transaction validation failed', [
                'error' => $e->getMessage(),
                'data' => $transaction_data
            ]);

            return $this->format_validation_result(false, [
                __('Validation error occurred', 'chatshop')
            ]);
        }
    }

    /**
     * Validate payment confirmation
     *
     * @param string $reference Transaction reference
     * @param float $expected_amount Expected amount
     * @param string $expected_currency Expected currency
     * @return array Validation result
     */
    public function validate_payment_confirmation($reference, $expected_amount, $expected_currency) {
        try {
            if (empty($reference)) {
                return $this->format_validation_result(false, [
                    __('Transaction reference is required', 'chatshop')
                ]);
            }

            // Verify transaction with Paystack
            $verification = $this->api_client->verify_transaction($reference);

            if (!$verification['status']) {
                return $this->format_validation_result(false, [
                    $verification['message'] ?? __('Transaction verification failed', 'chatshop')
                ]);
            }

            $transaction_data = $verification['data'];

            // Check transaction status
            if ($transaction_data['status'] !== 'success') {
                return $this->format_validation_result(false, [
                    sprintf(
                        __('Transaction status is %s, expected success', 'chatshop'),
                        $transaction_data['status']
                    )
                ]);
            }

            // Validate amount
            $api_amount = $transaction_data['amount'] / $this->get_currency_multiplier($expected_currency);
            if (abs($api_amount - $expected_amount) > 0.01) { // Allow small rounding differences
                return $this->format_validation_result(false, [
                    sprintf(
                        __('Amount mismatch. Expected: %s, Received: %s', 'chatshop'),
                        number_format($expected_amount, 2),
                        number_format($api_amount, 2)
                    )
                ]);
            }

            // Validate currency
            if ($transaction_data['currency'] !== $expected_currency) {
                return $this->format_validation_result(false, [
                    sprintf(
                        __('Currency mismatch. Expected: %s, Received: %s', 'chatshop'),
                        $expected_currency,
                        $transaction_data['currency']
                    )
                ]);
            }

            // Check for fraud indicators
            $fraud_check = $this->check_fraud_indicators($transaction_data);
            if (!$fraud_check['passed']) {
                return $this->format_validation_result(false, $fraud_check['warnings']);
            }

            return $this->format_validation_result(true, [], [
                'transaction_id' => $transaction_data['id'],
                'reference' => $transaction_data['reference'],
                'amount' => $api_amount,
                'currency' => $transaction_data['currency'],
                'paid_at' => $transaction_data['paid_at'],
            return $this->format_validation_result(true, [], [
                'transaction_id' => $transaction_data['id'],
                'reference' => $transaction_data['reference'],
                'amount' => $api_amount,
                'currency' => $transaction_data['currency'],
                'paid_at' => $transaction_data['paid_at'],
                'channel' => $transaction_data['channel'],
                'authorization' => $transaction_data['authorization'] ?? [],
                'customer' => $transaction_data['customer'] ?? [],
                'gateway_response' => $transaction_data['gateway_response'] ?? ''
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Payment confirmation validation failed', [
                'reference' => $reference,
                'error' => $e->getMessage()
            ]);

            return $this->format_validation_result(false, [
                __('Payment validation error occurred', 'chatshop')
            ]);
        }
    }

    /**
     * Validate refund request
     *
     * @param array $refund_data Refund data
     * @return array Validation result
     */
    public function validate_refund_request($refund_data) {
        $errors = [];

        try {
            // Validate required fields
            if (empty($refund_data['transaction'])) {
                $errors[] = __('Transaction reference is required', 'chatshop');
            }

            // Validate amount if provided
            if (!empty($refund_data['amount'])) {
                if (!is_numeric($refund_data['amount']) || $refund_data['amount'] <= 0) {
                    $errors[] = __('Refund amount must be a positive number', 'chatshop');
                }
            }

            // Validate currency
            if (!empty($refund_data['currency'])) {
                if (!$this->validate_currency($refund_data['currency'])) {
                    $errors[] = sprintf(
                        __('Currency %s is not supported', 'chatshop'),
                        $refund_data['currency']
                    );
                }
            }

            // Check if transaction exists and is refundable
            if (!empty($refund_data['transaction']) && empty($errors)) {
                $transaction_check = $this->check_transaction_refundability($refund_data['transaction']);
                if (!$transaction_check['refundable']) {
                    $errors[] = $transaction_check['message'];
                }
            }

            return $this->format_validation_result(empty($errors), $errors);

        } catch (\Exception $e) {
            $this->logger->error('Refund validation failed', [
                'error' => $e->getMessage(),
                'data' => $refund_data
            ]);

            return $this->format_validation_result(false, [
                __('Refund validation error occurred', 'chatshop')
            ]);
        }
    }

    /**
     * Validate customer data
     *
     * @param array $customer_data Customer data
     * @return array Validation result
     */
    public function validate_customer_data($customer_data) {
        $errors = [];

        try {
            // Validate email (required)
            if (empty($customer_data['email'])) {
                $errors[] = __('Customer email is required', 'chatshop');
            } elseif (!$this->validate_email($customer_data['email'])) {
                $errors[] = __('Invalid customer email address', 'chatshop');
            }

            // Validate phone if provided
            if (!empty($customer_data['phone'])) {
                if (!$this->validate_phone($customer_data['phone'])) {
                    $errors[] = __('Invalid phone number format', 'chatshop');
                }
            }

            // Validate names
            if (!empty($customer_data['first_name'])) {
                if (strlen($customer_data['first_name']) > 50) {
                    $errors[] = __('First name is too long (max 50 characters)', 'chatshop');
                }
            }

            if (!empty($customer_data['last_name'])) {
                if (strlen($customer_data['last_name']) > 50) {
                    $errors[] = __('Last name is too long (max 50 characters)', 'chatshop');
                }
            }

            return $this->format_validation_result(empty($errors), $errors);

        } catch (\Exception $e) {
            $this->logger->error('Customer validation failed', [
                'error' => $e->getMessage(),
                'data' => $customer_data
            ]);

            return $this->format_validation_result(false, [
                __('Customer validation error occurred', 'chatshop')
            ]);
        }
    }

    /**
     * Validate API credentials
     *
     * @return array Validation result
     */
    public function validate_api_credentials() {
        try {
            $public_key = $this->gateway->get_public_key();
            $secret_key = $this->gateway->get_secret_key();

            $errors = [];

            // Check if keys are provided
            if (empty($public_key)) {
                $errors[] = __('Public key is required', 'chatshop');
            }

            if (empty($secret_key)) {
                $errors[] = __('Secret key is required', 'chatshop');
            }

            if (!empty($errors)) {
                return $this->format_validation_result(false, $errors);
            }

            // Validate key formats
            if (!$this->validate_public_key_format($public_key)) {
                $errors[] = __('Invalid public key format', 'chatshop');
            }

            if (!$this->validate_secret_key_format($secret_key)) {
                $errors[] = __('Invalid secret key format', 'chatshop');
            }

            if (!empty($errors)) {
                return $this->format_validation_result(false, $errors);
            }

            // Test API connectivity
            $api_test = $this->api_client->get_api_health();
            
            if (!$api_test['status']) {
                $errors[] = sprintf(
                    __('API connection failed: %s', 'chatshop'),
                    $api_test['message']
                );
            }

            return $this->format_validation_result(empty($errors), $errors, [
                'connection_status' => $api_test['status'],
                'response_time' => $api_test['response_time'] ?? 0
            ]);

        } catch (\Exception $e) {
            $this->logger->error('API credentials validation failed', [
                'error' => $e->getMessage()
            ]);

            return $this->format_validation_result(false, [
                __('API credentials validation error occurred', 'chatshop')
            ]);
        }
    }

    /**
     * Validate webhook payload
     *
     * @param string $payload Raw payload
     * @param string $signature Webhook signature
     * @return array Validation result
     */
    public function validate_webhook_payload($payload, $signature) {
        try {
            $errors = [];

            // Check payload
            if (empty($payload)) {
                $errors[] = __('Webhook payload is empty', 'chatshop');
            }

            // Check signature
            if (empty($signature)) {
                $errors[] = __('Webhook signature is missing', 'chatshop');
            }

            if (!empty($errors)) {
                return $this->format_validation_result(false, $errors);
            }

            // Validate JSON format
            $decoded_payload = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = __('Invalid JSON payload', 'chatshop');
            }

            // Validate signature
            if (!$this->api_client->validate_webhook_signature($payload, $signature)) {
                $errors[] = __('Invalid webhook signature', 'chatshop');
            }

            // Validate payload structure
            if (empty($decoded_payload['event'])) {
                $errors[] = __('Webhook event type is missing', 'chatshop');
            }

            if (empty($decoded_payload['data'])) {
                $errors[] = __('Webhook data is missing', 'chatshop');
            }

            return $this->format_validation_result(empty($errors), $errors, $decoded_payload);

        } catch (\Exception $e) {
            $this->logger->error('Webhook validation failed', [
                'error' => $e->getMessage()
            ]);

            return $this->format_validation_result(false, [
                __('Webhook validation error occurred', 'chatshop')
            ]);
        }
    }

    /**
     * Validate email address
     *
     * @param string $email Email address
     * @return bool
     */
    private function validate_email($email) {
        return is_email($email) && strlen($email) <= 255;
    }

    /**
     * Validate currency
     *
     * @param string $currency Currency code
     * @return bool
     */
    private function validate_currency($currency) {
        return in_array(strtoupper($currency), self::SUPPORTED_CURRENCIES, true);
    }

    /**
     * Validate amount
     *
     * @param float $amount Amount
     * @param string $currency Currency code
     * @return array
     */
    private function validate_amount($amount, $currency) {
        if (!is_numeric($amount) || $amount <= 0) {
            return [
                'valid' => false,
                'message' => __('Amount must be a positive number', 'chatshop')
            ];
        }

        $currency = strtoupper($currency);
        $amount_in_subunit = $amount * $this->get_currency_multiplier($currency);

        $min_amount = self::MIN_AMOUNTS[$currency] ?? 100;
        $max_amount = self::MAX_AMOUNTS[$currency] ?? 500000000;

        if ($amount_in_subunit < $min_amount) {
            return [
                'valid' => false,
                'message' => sprintf(
                    __('Amount is too small. Minimum: %s %s', 'chatshop'),
                    number_format($min_amount / $this->get_currency_multiplier($currency), 2),
                    $currency
                )
            ];
        }

        if ($amount_in_subunit > $max_amount) {
            return [
                'valid' => false,
                'message' => sprintf(
                    __('Amount is too large. Maximum: %s %s', 'chatshop'),
                    number_format($max_amount / $this->get_currency_multiplier($currency), 2),
                    $currency
                )
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate reference uniqueness
     *
     * @param string $reference Transaction reference
     * @return bool
     */
    private function validate_reference_uniqueness($reference) {
        global $wpdb;

        // Check in WordPress meta tables
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_paystack_reference' 
                 AND meta_value = %s",
                sanitize_text_field($reference)
            )
        );

        return $exists == 0;
    }

    /**
     * Validate URL
     *
     * @param string $url URL to validate
     * @return bool
     */
    private function validate_url($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate payment channels
     *
     * @param array $channels Payment channels
     * @return array
     */
    private function validate_channels($channels) {
        $valid_channels = ['card', 'bank', 'ussd', 'mobile_money', 'bank_transfer', 'eft'];
        $invalid_channels = array_diff($channels, $valid_channels);

        if (!empty($invalid_channels)) {
            return [
                'valid' => false,
                'message' => sprintf(
                    __('Invalid payment channels: %s', 'chatshop'),
                    implode(', ', $invalid_channels)
                )
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate metadata
     *
     * @param array $metadata Metadata array
     * @return array
     */
    private function validate_metadata($metadata) {
        if (!is_array($metadata)) {
            return [
                'valid' => false,
                'message' => __('Metadata must be an array', 'chatshop')
            ];
        }

        // Check metadata size (Paystack limit)
        $metadata_json = wp_json_encode($metadata);
        if (strlen($metadata_json) > 5000) { // 5KB limit
            return [
                'valid' => false,
                'message' => __('Metadata is too large (max 5KB)', 'chatshop')
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate phone number
     *
     * @param string $phone Phone number
     * @return bool
     */
    private function validate_phone($phone) {
        // Basic phone validation (can be enhanced)
        $clean_phone = preg_replace('/[^0-9+]/', '', $phone);
        return strlen($clean_phone) >= 10 && strlen($clean_phone) <= 15;
    }

    /**
     * Validate public key format
     *
     * @param string $key Public key
     * @return bool
     */
    private function validate_public_key_format($key) {
        return preg_match('/^pk_(test_|live_)[a-zA-Z0-9]+$/', $key);
    }

    /**
     * Validate secret key format
     *
     * @param string $key Secret key
     * @return bool
     */
    private function validate_secret_key_format($key) {
        return preg_match('/^sk_(test_|live_)[a-zA-Z0-9]+$/', $key);
    }

    /**
     * Check transaction refundability
     *
     * @param string $reference Transaction reference
     * @return array
     */
    private function check_transaction_refundability($reference) {
        try {
            $verification = $this->api_client->verify_transaction($reference);

            if (!$verification['status']) {
                return [
                    'refundable' => false,
                    'message' => __('Transaction not found', 'chatshop')
                ];
            }

            $transaction_data = $verification['data'];

            // Check transaction status
            if ($transaction_data['status'] !== 'success') {
                return [
                    'refundable' => false,
                    'message' => __('Only successful transactions can be refunded', 'chatshop')
                ];
            }

            // Check transaction age (Paystack allows refunds within a certain period)
            $transaction_date = strtotime($transaction_data['created_at']);
            $days_old = (time() - $transaction_date) / (24 * 60 * 60);

            if ($days_old > 365) { // 1 year limit (adjust as needed)
                return [
                    'refundable' => false,
                    'message' => __('Transaction is too old to refund', 'chatshop')
                ];
            }

            return [
                'refundable' => true,
                'message' => __('Transaction is refundable', 'chatshop')
            ];

        } catch (\Exception $e) {
            return [
                'refundable' => false,
                'message' => __('Unable to verify transaction refundability', 'chatshop')
            ];
        }
    }

    /**
     * Check for fraud indicators
     *
     * @param array $transaction_data Transaction data
     * @return array
     */
    private function check_fraud_indicators($transaction_data) {
        $warnings = [];
        $passed = true;

        // Check for suspicious patterns
        if (isset($transaction_data['gateway_response'])) {
            $risky_responses = ['Approved by Financial Institution', 'Approved'];
            // Add more sophisticated fraud detection logic here
        }

        // Check customer data consistency
        if (isset($transaction_data['customer'])) {
            // Add customer verification logic
        }

        // Check IP geolocation if available
        if (isset($transaction_data['ip_address'])) {
            // Add IP-based fraud detection
        }

        return [
            'passed' => $passed,
            'warnings' => $warnings
        ];
    }

    /**
     * Get currency multiplier
     *
     * @param string $currency Currency code
     * @return int
     */
    private function get_currency_multiplier($currency) {
        $multipliers = [
            'NGN' => 100, 'USD' => 100, 'GHS' => 100,
            'ZAR' => 100, 'KES' => 100, 'EGP' => 100,
            'XOF' => 1
        ];

        return $multipliers[strtoupper($currency)] ?? 100;
    }

    /**
     * Format validation result
     *
     * @param bool $valid Is valid
     * @param array $errors Error messages
     * @param array $data Additional data
     * @return array
     */
    private function format_validation_result($valid, $errors = [], $data = []) {
        return [
            'valid' => $valid,
            'errors' => $errors,
            'data' => $data
        ];
    }
}