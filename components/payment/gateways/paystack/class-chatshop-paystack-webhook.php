<?php
/**
 * Paystack Webhook Handler
 *
 * File: components/payment/gateways/paystack/class-chatshop-paystack-webhook.php
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
 * ChatShop Paystack Webhook Handler
 * 
 * Processes webhook notifications from Paystack
 */
class ChatShop_Paystack_Webhook {

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
     * API client
     *
     * @var ChatShop_Paystack_API
     */
    private $api_client;

    /**
     * Constructor
     *
     * @param ChatShop_Paystack_Gateway $gateway Gateway instance
     */
    public function __construct($gateway) {
        $this->gateway = $gateway;
        $this->logger = new ChatShop_Logger('paystack-webhook');
        $this->api_client = new ChatShop_Paystack_API($gateway);
    }

    /**
     * Process incoming webhook
     */
    public function process_webhook() {
        try {
            // Get raw POST data
            $raw_payload = file_get_contents('php://input');
            
            if (empty($raw_payload)) {
                $this->send_response(400, 'Empty payload');
                return;
            }

            // Get headers
            $headers = $this->get_request_headers();
            $signature = $headers['x-paystack-signature'] ?? '';

            // Validate signature
            if (!$this->validate_signature($raw_payload, $signature)) {
                $this->logger->error('Invalid webhook signature', [
                    'signature' => $signature,
                    'headers' => $headers
                ]);
                $this->send_response(401, 'Invalid signature');
                return;
            }

            // Decode payload
            $payload = json_decode($raw_payload, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Invalid JSON payload', [
                    'error' => json_last_error_msg(),
                    'payload' => $raw_payload
                ]);
                $this->send_response(400, 'Invalid JSON');
                return;
            }

            // Log webhook received
            $this->logger->info('Webhook received', [
                'event' => $payload['event'] ?? 'unknown',
                'reference' => $payload['data']['reference'] ?? 'unknown'
            ]);

            // Process event
            $this->process_event($payload);
            
            $this->send_response(200, 'OK');

        } catch (\Exception $e) {
            $this->logger->error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->send_response(500, 'Internal server error');
        }
    }

    /**
     * Process webhook event
     *
     * @param array $payload Webhook payload
     */
    private function process_event($payload) {
        $event = $payload['event'] ?? '';
        $data = $payload['data'] ?? [];

        switch ($event) {
            case 'charge.success':
                $this->handle_charge_success($data);
                break;
                
            case 'charge.failed':
                $this->handle_charge_failed($data);
                break;
                
            case 'charge.dispute.create':
                $this->handle_dispute_created($data);
                break;
                
            case 'charge.dispute.remind':
                $this->handle_dispute_reminder($data);
                break;
                
            case 'charge.dispute.resolve':
                $this->handle_dispute_resolved($data);
                break;
                
            case 'subscription.create':
                $this->handle_subscription_created($data);
                break;
                
            case 'subscription.disable':
                $this->handle_subscription_disabled($data);
                break;
                
            case 'subscription.not_renew':
                $this->handle_subscription_not_renew($data);
                break;
                
            case 'invoice.create':
                $this->handle_invoice_created($data);
                break;
                
            case 'invoice.payment_failed':
                $this->handle_invoice_payment_failed($data);
                break;
                
            case 'refund.failed':
                $this->handle_refund_failed($data);
                break;
                
            case 'refund.pending':
                $this->handle_refund_pending($data);
                break;
                
            case 'refund.processed':
                $this->handle_refund_processed($data);
                break;
                
            case 'transfer.success':
                $this->handle_transfer_success($data);
                break;
                
            case 'transfer.failed':
                $this->handle_transfer_failed($data);
                break;
                
            case 'transfer.reversed':
                $this->handle_transfer_reversed($data);
                break;
                
            default:
                $this->logger->info('Unhandled webhook event', [
                    'event' => $event,
                    'data' => $data
                ]);
                break;
        }

        // Fire action for custom handling
        do_action('chatshop_paystack_webhook_' . str_replace('.', '_', $event), $data, $payload);
        do_action('chatshop_paystack_webhook', $event, $data, $payload);
    }

    /**
     * Handle successful charge
     *
     * @param array $data Webhook data
     */
    private function handle_charge_success($data) {
        $reference = $data['reference'] ?? '';
        $order = $this->get_order_by_reference($reference);

        if (!$order) {
            $this->logger->warning('Order not found for successful charge', [
                'reference' => $reference
            ]);
            return;
        }

        // Verify transaction with Paystack
        $verification = $this->api_client->verify_transaction($reference);
        
        if (!$verification['status'] || $verification['data']['status'] !== 'success') {
            $this->logger->error('Transaction verification failed for successful charge', [
                'reference' => $reference,
                'verification' => $verification
            ]);
            return;
        }

        $transaction_data = $verification['data'];
        
        // Check if already processed
        if ($order->is_paid()) {
            $this->logger->info('Order already marked as paid', [
                'order_id' => $order->get_id(),
                'reference' => $reference
            ]);
            return;
        }

        // Update order
        $order->payment_complete($reference);
        
        // Add order note
        $order->add_order_note(
            sprintf(
                __('Paystack payment completed. Transaction ID: %s, Amount: %s %s', 'chatshop'),
                $transaction_data['id'],
                number_format($transaction_data['amount'] / 100, 2),
                $transaction_data['currency']
            )
        );

        // Save transaction metadata
        $order->update_meta_data('_paystack_transaction_id', $transaction_data['id']);
        $order->update_meta_data('_paystack_authorization_code', $data['authorization']['authorization_code'] ?? '');
        $order->update_meta_data('_paystack_customer_code', $data['customer']['customer_code'] ?? '');
        $order->update_meta_data('_paystack_gateway_response', $data['gateway_response'] ?? '');
        $order->update_meta_data('_paystack_channel', $data['channel'] ?? '');
        $order->save();

        // Auto-complete order if enabled
        if ($this->gateway->get_option('auto_complete') === 'yes') {
            $order->update_status('completed', __('Order auto-completed after successful payment.', 'chatshop'));
        }

        $this->logger->info('Payment completed successfully', [
            'order_id' => $order->get_id(),
            'reference' => $reference,
            'transaction_id' => $transaction_data['id']
        ]);

        // Fire action
        do_action('chatshop_paystack_payment_completed', $order, $transaction_data);
    }

    /**
     * Handle failed charge
     *
     * @param array $data Webhook data
     */
    private function handle_charge_failed($data) {
        $reference = $data['reference'] ?? '';
        $order = $this->get_order_by_reference($reference);

        if (!$order) {
            $this->logger->warning('Order not found for failed charge', [
                'reference' => $reference
            ]);
            return;
        }

        $failure_reason = $data['gateway_response'] ?? __('Payment failed', 'chatshop');
        
        $order->update_status('failed', sprintf(
            __('Paystack payment failed: %s', 'chatshop'),
            $failure_reason
        ));

        $this->logger->info('Payment failed', [
            'order_id' => $order->get_id(),
            'reference' => $reference,
            'reason' => $failure_reason
        ]);

        // Fire action
        do_action('chatshop_paystack_payment_failed', $order, $data);
    }

    /**
     * Handle dispute created
     *
     * @param array $data Webhook data
     */
    private function handle_dispute_created($data) {
        $reference = $data['transaction']['reference'] ?? '';
        $order = $this->get_order_by_reference($reference);

        if (!$order) {
            $this->logger->warning('Order not found for dispute', [
                'reference' => $reference
            ]);
            return;
        }

        $dispute_reason = $data['reason'] ?? __('Unknown reason', 'chatshop');
        
        $order->add_order_note(
            sprintf(
                __('Payment dispute created. Reason: %s. Please respond within the required timeframe.', 'chatshop'),
                $dispute_reason
            )
        );

        $order->update_status('on-hold', __('Payment disputed', 'chatshop'));

        $this->logger->info('Payment dispute created', [
            'order_id' => $order->get_id(),
            'reference' => $reference,
            'reason' => $dispute_reason
        ]);

        // Fire action
        do_action('chatshop_paystack_dispute_created', $order, $data);
    }

    /**
     * Handle dispute resolved
     *
     * @param array $data Webhook data
     */
    private function handle_dispute_resolved($data) {
        $reference = $data['transaction']['reference'] ?? '';
        $order = $this->get_order_by_reference($reference);

        if (!$order) {
            return;
        }

        $resolution = $data['resolution'] ?? '';
        
        if ($resolution === 'merchant-won') {
            $order->add_order_note(__('Payment dispute resolved in your favor.', 'chatshop'));
            $order->update_status('processing');
        } else {
            $order->add_order_note(__('Payment dispute resolved against you.', 'chatshop'));
        }

        $this->logger->info('Payment dispute resolved', [
            'order_id' => $order->get_id(),
            'reference' => $reference,
            'resolution' => $resolution
        ]);

        // Fire action
        do_action('chatshop_paystack_dispute_resolved', $order, $data);
    }

    /**
     * Handle subscription created
     *
     * @param array $data Webhook data
     */
    private function handle_subscription_created($data) {
        $subscription_code = $data['subscription_code'] ?? '';
        $customer_email = $data['customer']['email'] ?? '';

        $this->logger->info('Subscription created', [
            'subscription_code' => $subscription_code,
            'customer_email' => $customer_email
        ]);

        // Fire action for custom handling
        do_action('chatshop_paystack_subscription_created', $data);
    }

    /**
     * Handle subscription disabled
     *
     * @param array $data Webhook data
     */
    private function handle_subscription_disabled($data) {
        $subscription_code = $data['subscription_code'] ?? '';
        
        $this->logger->info('Subscription disabled', [
            'subscription_code' => $subscription_code
        ]);

        // Fire action for custom handling
        do_action('chatshop_paystack_subscription_disabled', $data);
    }

    /**
     * Handle refund processed
     *
     * @param array $data Webhook data
     */
    private function handle_refund_processed($data) {
        $reference = $data['transaction']['reference'] ?? '';
        $order = $this->get_order_by_reference($reference);

        if (!$order) {
            return;
        }

        $refund_amount = $data['amount'] / 100; // Convert from kobo to naira
        
        $order->add_order_note(
            sprintf(
                __('Paystack refund processed: %s %s', 'chatshop'),
                number_format($refund_amount, 2),
                $data['currency'] ?? 'NGN'
            )
        );

        $this->logger->info('Refund processed', [
            'order_id' => $order->get_id(),
            'reference' => $reference,
            'amount' => $refund_amount
        ]);

        // Fire action
        do_action('chatshop_paystack_refund_processed', $order, $data);
    }

    /**
     * Handle refund failed
     *
     * @param array $data Webhook data
     */
    private function handle_refund_failed($data) {
        $reference = $data['transaction']['reference'] ?? '';
        $order = $this->get_order_by_reference($reference);

        if (!$order) {
            return;
        }

        $order->add_order_note(__('Paystack refund failed. Please process manually.', 'chatshop'));

        $this->logger->error('Refund failed', [
            'order_id' => $order->get_id(),
            'reference' => $reference,
            'data' => $data
        ]);

        // Fire action
        do_action('chatshop_paystack_refund_failed', $order, $data);
    }

    /**
     * Get order by Paystack reference
     *
     * @param string $reference Transaction reference
     * @return WC_Order|false
     */
    private function get_order_by_reference($reference) {
        if (empty($reference)) {
            return false;
        }

        $orders = wc_get_orders([
            'meta_key' => '_paystack_reference',
            'meta_value' => sanitize_text_field($reference),
            'limit' => 1
        ]);

        return !empty($orders) ? $orders[0] : false;
    }

    /**
     * Validate webhook signature
     *
     * @param string $payload Raw payload
     * @param string $signature Signature header
     * @return bool
     */
    private function validate_signature($payload, $signature) {
        if (empty($signature)) {
            return false;
        }

        return $this->api_client->validate_webhook_signature($payload, $signature);
    }

    /**
     * Get request headers
     *
     * @return array
     */
    private function get_request_headers() {
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header_name = strtolower(str_replace(['HTTP_', '_'], ['', '-'], $key));
                $headers[$header_name] = sanitize_text_field($value);
            }
        }

        return $headers;
    }

    /**
     * Send HTTP response
     *
     * @param int $status_code HTTP status code
     * @param string $message Response message
     */
    private function send_response($status_code, $message) {
        status_header($status_code);
        
        wp_send_json([
            'status' => $status_code < 400 ? 'success' : 'error',
            'message' => $message
        ], $status_code);
    }

    /**
     * Handle other webhook events (stubs for future implementation)
     */
    private function handle_dispute_reminder($data) {
        // Implement dispute reminder handling
        do_action('chatshop_paystack_dispute_reminder', $data);
    }

    private function handle_subscription_not_renew($data) {
        // Implement subscription non-renewal handling
        do_action('chatshop_paystack_subscription_not_renew', $data);
    }

    private function handle_invoice_created($data) {
        // Implement invoice creation handling
        do_action('chatshop_paystack_invoice_created', $data);
    }

    private function handle_invoice_payment_failed($data) {
        // Implement invoice payment failure handling
        do_action('chatshop_paystack_invoice_payment_failed', $data);
    }

    private function handle_refund_pending($data) {
        // Implement refund pending handling
        do_action('chatshop_paystack_refund_pending', $data);
    }

    private function handle_transfer_success($data) {
        // Implement transfer success handling
        do_action('chatshop_paystack_transfer_success', $data);
    }

    private function handle_transfer_failed($data) {
        // Implement transfer failure handling
        do_action('chatshop_paystack_transfer_failed', $data);
    }

    private function handle_transfer_reversed($data) {
        // Implement transfer reversal handling
        do_action('chatshop_paystack_transfer_reversed', $data);
    }
}