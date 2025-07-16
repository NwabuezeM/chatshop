<?php

/**
 * Payment Gateway Interface
 *
 * Defines the contract that all payment gateways must implement.
 * This interface ensures consistency across all payment gateway implementations.
 *
 * @package ChatShop
 * @subpackage Payment\Interfaces
 * @since 1.0.0
 */

namespace ChatShop\Payment\Interfaces;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Payment Gateway Interface
 * 
 * Contract for all payment gateway implementations
 */
interface ChatShop_Payment_Gateway_Interface
{

    /**
     * Process payment
     *
     * @param float $amount Payment amount
     * @param string $currency Currency code
     * @param array $customer_data Customer information
     * @param array $order_data Order information
     * @return array|WP_Error Payment response
     */
    public function process_payment($amount, $currency, $customer_data, $order_data = []);

    /**
     * Verify transaction
     *
     * @param string $transaction_id Transaction ID
     * @return array|WP_Error Verification response
     */
    public function verify_transaction($transaction_id);

    /**
     * Handle webhook
     *
     * @param array $payload Webhook payload
     * @return array|WP_Error Webhook response
     */
    public function handle_webhook($payload);

    /**
     * Process refund
     *
     * @param string $transaction_id Original transaction ID
     * @param float $amount Refund amount
     * @param string $reason Refund reason
     * @return array|WP_Error Refund response
     */
    public function process_refund($transaction_id, $amount, $reason = '');

    /**
     * Get supported currencies
     *
     * @return array Array of supported currency codes
     */
    public function get_supported_currencies();

    /**
     * Get supported countries
     *
     * @return array Array of supported country codes
     */
    public function get_supported_countries();

    /**
     * Check if gateway supports feature
     *
     * @param string $feature Feature name
     * @return bool
     */
    public function supports($feature);

    /**
     * Get gateway configuration
     *
     * @return array Gateway configuration
     */
    public function get_configuration();

    /**
     * Validate gateway configuration
     *
     * @return bool|WP_Error
     */
    public function validate_configuration();

    /**
     * Get payment methods
     *
     * @return array Available payment methods
     */
    public function get_payment_methods();

    /**
     * Create payment link
     *
     * @param array $payment_data Payment information
     * @return array|WP_Error Payment link response
     */
    public function create_payment_link($payment_data);

    /**
     * Cancel payment
     *
     * @param string $transaction_id Transaction ID
     * @return array|WP_Error Cancellation response
     */
    public function cancel_payment($transaction_id);

    /**
     * Get transaction details
     *
     * @param string $transaction_id Transaction ID
     * @return array|WP_Error Transaction details
     */
    public function get_transaction_details($transaction_id);

    /**
     * Test gateway connection
     *
     * @return bool|WP_Error
     */
    public function test_connection();

    /**
     * Get gateway status
     *
     * @return array Gateway status information
     */
    public function get_gateway_status();

    /**
     * Initialize gateway
     *
     * @return void
     */
    public function init();

    /**
     * Get gateway fees
     *
     * @param float $amount Transaction amount
     * @param string $currency Currency code
     * @return array Fee information
     */
    public function get_fees($amount, $currency);

    /**
     * Format amount for gateway
     *
     * @param float $amount Amount to format
     * @param string $currency Currency code
     * @return string|int Formatted amount
     */
    public function format_amount($amount, $currency);

    /**
     * Get minimum amount
     *
     * @param string $currency Currency code
     * @return float Minimum amount
     */
    public function get_minimum_amount($currency);

    /**
     * Get maximum amount
     *
     * @param string $currency Currency code
     * @return float Maximum amount
     */
    public function get_maximum_amount($currency);

    /**
     * Validate amount
     *
     * @param float $amount Amount to validate
     * @param string $currency Currency code
     * @return bool|WP_Error
     */
    public function validate_amount($amount, $currency);

    /**
     * Get webhook URL
     *
     * @return string Webhook URL
     */
    public function get_webhook_url();

    /**
     * Validate webhook signature
     *
     * @param string $payload Webhook payload
     * @param string $signature Webhook signature
     * @return bool
     */
    public function validate_webhook_signature($payload, $signature);
}
