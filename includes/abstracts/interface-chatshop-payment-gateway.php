<?php

/**
 * ChatShop Payment Gateway Interface
 *
 * This interface defines the contract that all payment gateways must implement
 * for the ChatShop plugin.
 *
 * @package ChatShop
 * @subpackage Interfaces
 * @since 1.0.0
 */

namespace ChatShop\Includes\Abstracts;

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Payment Gateway Interface
 *
 * Defines the required methods that all payment gateway implementations
 * must provide for the ChatShop plugin.
 *
 * @since 1.0.0
 */
interface ChatShop_Payment_Gateway_Interface
{

    /**
     * Process a payment
     *
     * @param float  $amount        The amount to charge
     * @param string $currency      The currency code (e.g., 'USD', 'NGN')
     * @param array  $customer_data Customer information
     * @return array Processing result with 'success' boolean and 'data' or 'error' array
     */
    public function process_payment($amount, $currency, $customer_data);

    /**
     * Verify a transaction
     *
     * @param string $transaction_id The transaction ID to verify
     * @return array Verification result with 'success' boolean and transaction details
     */
    public function verify_transaction($transaction_id);

    /**
     * Handle webhook notifications
     *
     * @param array $payload The webhook payload data
     * @return array Processing result with 'success' boolean
     */
    public function handle_webhook($payload);

    /**
     * Get the gateway ID
     *
     * @return string The unique identifier for this gateway
     */
    public function get_gateway_id();

    /**
     * Get the gateway name
     *
     * @return string The display name for this gateway
     */
    public function get_gateway_name();

    /**
     * Check if the gateway is available
     *
     * @return bool True if the gateway is properly configured and available
     */
    public function is_available();

    /**
     * Get supported features
     *
     * @return array List of supported features (e.g., 'refunds', 'subscriptions', 'tokenization')
     */
    public function get_supported_features();

    /**
     * Initialize the gateway
     *
     * This method should handle any initialization logic needed
     * when the gateway is loaded.
     *
     * @return void
     */
    public function initialize();

    /**
     * Get gateway settings
     *
     * @return array The gateway configuration settings
     */
    public function get_settings();

    /**
     * Update gateway settings
     *
     * @param array $settings The new settings to save
     * @return bool True if settings were saved successfully
     */
    public function update_settings($settings);

    /**
     * Generate payment link
     *
     * @param array $payment_data Payment information including amount, customer data, etc.
     * @return array Result with 'success' boolean and 'link' URL or 'error' message
     */
    public function generate_payment_link($payment_data);

    /**
     * Process refund
     *
     * @param string $transaction_id The original transaction ID
     * @param float  $amount         The amount to refund (null for full refund)
     * @param string $reason         The reason for the refund
     * @return array Result with 'success' boolean and refund details or error
     */
    public function process_refund($transaction_id, $amount = null, $reason = '');

    /**
     * Get transaction details
     *
     * @param string $transaction_id The transaction ID
     * @return array Transaction details or empty array if not found
     */
    public function get_transaction_details($transaction_id);

    /**
     * Validate API credentials
     *
     * @return array Validation result with 'valid' boolean and optional 'message'
     */
    public function validate_credentials();

    /**
     * Get supported currencies
     *
     * @return array List of supported currency codes
     */
    public function get_supported_currencies();

    /**
     * Get gateway icon URL
     *
     * @return string URL to the gateway's icon/logo
     */
    public function get_icon_url();

    /**
     * Render payment form fields
     *
     * This method should output any custom form fields needed
     * for the payment gateway on the checkout page.
     *
     * @param array $args Optional arguments for rendering
     * @return void
     */
    public function render_payment_fields($args = array());

    /**
     * Validate payment fields
     *
     * @param array $data The submitted payment data
     * @return array Validation result with 'valid' boolean and optional 'errors' array
     */
    public function validate_payment_fields($data);

    /**
     * Get gateway status
     *
     * @return array Status information including 'active', 'configured', and 'test_mode'
     */
    public function get_status();

    /**
     * Handle payment notification
     *
     * Process asynchronous payment notifications/callbacks
     *
     * @param array $notification_data The notification data
     * @return array Processing result
     */
    public function handle_payment_notification($notification_data);

    /**
     * Get payment method title
     *
     * @return string The title to display to customers
     */
    public function get_payment_method_title();

    /**
     * Get payment method description
     *
     * @return string The description to display to customers
     */
    public function get_payment_method_description();
}
