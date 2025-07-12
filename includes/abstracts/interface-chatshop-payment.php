<?php

/**
 * Payment Gateway Interface
 *
 * @package ChatShop
 * @subpackage Includes/Abstracts
 * @since 1.0.0
 */

namespace ChatShop\Includes\Abstracts;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Interface ChatShop_Payment_Gateway_Interface
 *
 * Defines the contract for all payment gateway implementations
 *
 * @since 1.0.0
 */
interface ChatShop_Payment_Gateway_Interface
{

    /**
     * Process a payment
     *
     * @param float  $amount       Payment amount
     * @param string $currency     Currency code (e.g., 'USD', 'NGN')
     * @param array  $customer_data Customer information
     * @param array  $order_data   Order details
     * @return array               Payment result with status and transaction details
     */
    public function process_payment($amount, $currency, $customer_data, $order_data = array());

    /**
     * Verify a transaction
     *
     * @param string $transaction_id Transaction reference
     * @return array                 Verification result
     */
    public function verify_transaction($transaction_id);

    /**
     * Handle webhook notifications
     *
     * @param array $payload Webhook payload
     * @return array         Processing result
     */
    public function handle_webhook($payload);

    /**
     * Generate payment link
     *
     * @param float  $amount      Payment amount
     * @param string $currency    Currency code
     * @param array  $metadata    Additional payment metadata
     * @return string|false       Payment link URL or false on failure
     */
    public function generate_payment_link($amount, $currency, $metadata = array());

    /**
     * Process refund
     *
     * @param string $transaction_id Transaction to refund
     * @param float  $amount        Amount to refund (null for full refund)
     * @param string $reason        Refund reason
     * @return array                Refund result
     */
    public function process_refund($transaction_id, $amount = null, $reason = '');

    /**
     * Get gateway information
     *
     * @return array Gateway details (id, name, description, supported currencies, etc.)
     */
    public function get_gateway_info();

    /**
     * Check if gateway is available
     *
     * @return bool True if gateway can process payments
     */
    public function is_available();

    /**
     * Get supported currencies
     *
     * @return array List of supported currency codes
     */
    public function get_supported_currencies();

    /**
     * Get supported features
     *
     * @return array List of supported features (refunds, recurring, etc.)
     */
    public function get_supported_features();

    /**
     * Validate gateway configuration
     *
     * @return bool|WP_Error True if valid, WP_Error on validation failure
     */
    public function validate_configuration();
}
