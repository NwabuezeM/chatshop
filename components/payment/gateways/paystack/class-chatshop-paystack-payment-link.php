<?php
/**
 * Paystack Payment Link Generator
 *
 * File: components/payment/gateways/paystack/class-chatshop-paystack-payment-link.php
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
 * ChatShop Paystack Payment Link Generator
 * 
 * Generates payment links for WhatsApp and social commerce
 */
class ChatShop_Paystack_Payment_Link {

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
     * Constructor
     *
     * @param ChatShop_Paystack_Gateway $gateway Gateway instance
     */
    public function __construct($gateway) {
        $this->gateway = $gateway;
        $this->api_client = new ChatShop_Paystack_API($gateway);
        $this->logger = new ChatShop_Logger('paystack-payment-link');
    }

    /**
     * Generate payment link for order
     *
     * @param WC_Order $order Order object
     * @param array $options Additional options
     * @return array
     */
    public function generate_order_link($order, $options = []) {
        try {
            if (!$order || !is_a($order, 'WC_Order')) {
                throw new \Exception(__('Invalid order object', 'chatshop'));
            }

            // Prepare transaction data
            $transaction_data = $this->prepare_order_transaction_data($order, $options);
            
            // Initialize transaction
            $response = $this->api_client->initialize_transaction($transaction_data);

            if (!$response['status']) {
                throw new \Exception($response['message'] ?? __('Failed to create payment link', 'chatshop'));
            }

            // Save link data
            $link_data = [
                'order_id' => $order->get_id(),
                'reference' => $response['data']['reference'],
                'access_code' => $response['data']['access_code'],
                'authorization_url' => $response['data']['authorization_url'],
                'created_at' => current_time('mysql'),
                'expires_at' => $this->calculate_expiry_time($options),
                'status' => 'active',
                'metadata' => wp_json_encode($options)
            ];

            $this->save_payment_link($link_data);

            // Update order meta
            $order->update_meta_data('_paystack_payment_link', $response['data']['authorization_url']);
            $order->update_meta_data('_paystack_access_code', $response['data']['access_code']);
            $order->update_meta_data('_paystack_reference', $response['data']['reference']);
            $order->save();

            $this->logger->info('Payment link generated', [
                'order_id' => $order->get_id(),
                'reference' => $response['data']['reference']
            ]);

            return [
                'success' => true,
                'data' => [
                    'payment_url' => $response['data']['authorization_url'],
                    'reference' => $response['data']['reference'],
                    'access_code' => $response['data']['access_code'],
                    'whatsapp_message' => $this->generate_whatsapp_message($order, $response['data']['authorization_url'], $options)
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Payment link generation failed', [
                'order_id' => $order ? $order->get_id() : 'unknown',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate payment link for custom amount
     *
     * @param array $data Payment data
     * @return array
     */
    public function generate_custom_link($data) {
        try {
            $required_fields = ['amount', 'currency', 'email', 'description'];
            
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    throw new \Exception(sprintf(__('Field %s is required', 'chatshop'), $field));
                }
            }

            // Prepare transaction data
            $transaction_data = $this->prepare_custom_transaction_data($data);
            
            // Initialize transaction
            $response = $this->api_client->initialize_transaction($transaction_data);

            if (!$response['status']) {
                throw new \Exception($response['message'] ?? __('Failed to create payment link', 'chatshop'));
            }

            // Save link data
            $link_data = [
                'order_id' => 0, // Custom link, no order
                'reference' => $response['data']['reference'],
                'access_code' => $response['data']['access_code'],
                'authorization_url' => $response['data']['authorization_url'],
                'created_at' => current_time('mysql'),
                'expires_at' => $this->calculate_expiry_time($data),
                'status' => 'active',
                'metadata' => wp_json_encode($data)
            ];

            $this->save_payment_link($link_data);

            $this->logger->info('Custom payment link generated', [
                'reference' => $response['data']['reference'],
                'amount' => $data['amount']
            ]);

            return [
                'success' => true,
                'data' => [
                    'payment_url' => $response['data']['authorization_url'],
                    'reference' => $response['data']['reference'],
                    'access_code' => $response['data']['access_code'],
                    'whatsapp_message' => $this->generate_custom_whatsapp_message($data, $response['data']['authorization_url'])
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Custom payment link generation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate payment request link
     *
     * @param array $data Payment request data
     * @return array
     */
    public function generate_payment_request($data) {
        try {
            // Prepare payment request data
            $request_data = [
                'description' => sanitize_text_field($data['description']),
                'amount' => $this->format_amount($data['amount'], $data['currency']),
                'currency' => sanitize_text_field($data['currency']),
                'due_date' => $data['due_date'] ?? '',
                'send_notification' => (bool) ($data['send_notification'] ?? false),
                'draft' => (bool) ($data['draft'] ?? false),
                'has_invoice' => (bool) ($data['has_invoice'] ?? false),
                'line_items' => $data['line_items'] ?? [],
                'tax' => $data['tax'] ?? [],
                'metadata' => [
                    'source' => 'chatshop',
                    'created_by' => get_current_user_id(),
                    'custom_data' => $data['metadata'] ?? []
                ]
            ];

            // Create payment request via API
            $response = $this->api_client->create_payment_request($request_data);

            if (!$response['status']) {
                throw new \Exception($response['message'] ?? __('Failed to create payment request', 'chatshop'));
            }

            $payment_request = $response['data'];

            // Save payment request data
            $link_data = [
                'order_id' => 0,
                'reference' => $payment_request['request_code'],
                'access_code' => $payment_request['request_code'],
                'authorization_url' => $payment_request['url'],
                'created_at' => current_time('mysql'),
                'expires_at' => $payment_request['due_date'] ?? $this->calculate_expiry_time($data),
                'status' => $payment_request['status'] ?? 'active',
                'metadata' => wp_json_encode(array_merge($data, ['type' => 'payment_request']))
            ];

            $this->save_payment_link($link_data);

            $this->logger->info('Payment request generated', [
                'request_code' => $payment_request['request_code'],
                'amount' => $data['amount']
            ]);

            return [
                'success' => true,
                'data' => [
                    'payment_url' => $payment_request['url'],
                    'request_code' => $payment_request['request_code'],
                    'invoice_number' => $payment_request['invoice_number'] ?? '',
                    'whatsapp_message' => $this->generate_payment_request_whatsapp_message($data, $payment_request['url'])
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Payment request generation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get payment link status
     *
     * @param string $reference Payment reference
     * @return array
     */
    public function get_link_status($reference) {
        try {
            if (empty($reference)) {
                throw new \Exception(__('Invalid reference', 'chatshop'));
            }

            // Get from database
            $link_data = $this->get_payment_link_by_reference($reference);
            
            if (!$link_data) {
                throw new \Exception(__('Payment link not found', 'chatshop'));
            }

            // Verify with Paystack
            $verification = $this->api_client->verify_transaction($reference);
            
            $status = [
                'reference' => $reference,
                'status' => $link_data['status'],
                'created_at' => $link_data['created_at'],
                'expires_at' => $link_data['expires_at'],
                'payment_status' => 'pending'
            ];

            if ($verification['status'] && isset($verification['data']['status'])) {
                $status['payment_status'] = $verification['data']['status'];
                $status['gateway_response'] = $verification['data']['gateway_response'] ?? '';
                $status['paid_at'] = $verification['data']['paid_at'] ?? '';
                $status['amount'] = $verification['data']['amount'] ?? 0;
                $status['currency'] = $verification['data']['currency'] ?? '';
            }

            return [
                'success' => true,
                'data' => $status
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Expire payment link
     *
     * @param string $reference Payment reference
     * @return bool
     */
    public function expire_link($reference) {
        try {
            global $wpdb;

            $table_name = $wpdb->prefix . 'chatshop_payment_links';
            
            $result = $wpdb->update(
                $table_name,
                ['status' => 'expired'],
                ['reference' => sanitize_text_field($reference)],
                ['%s'],
                ['%s']
            );

            if ($result !== false) {
                $this->logger->info('Payment link expired', ['reference' => $reference]);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            $this->logger->error('Failed to expire payment link', [
                'reference' => $reference,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Prepare transaction data for order
     *
     * @param WC_Order $order Order object
     * @param array $options Additional options
     * @return array
     */
    private function prepare_order_transaction_data($order, $options = []) {
        $callback_url = $this->get_callback_url($order, $options);
        $cancel_url = wc_get_checkout_url();

        return [
            'email' => $order->get_billing_email(),
            'amount' => $this->format_amount($order->get_total(), $order->get_currency()),
            'currency' => $order->get_currency(),
            'reference' => $this->generate_reference('order', $order->get_id()),
            'callback_url' => $callback_url,
            'metadata' => [
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'customer_name' => $order->get_formatted_billing_full_name(),
                'customer_phone' => $order->get_billing_phone(),
                'source' => 'chatshop_payment_link',
                'link_type' => 'order',
                'cancel_action' => $cancel_url,
                'options' => $options
            ],
            'channels' => $options['channels'] ?? $this->gateway->get_option('payment_channels', ['card', 'bank'])
        ];
    }

    /**
     * Prepare transaction data for custom payment
     *
     * @param array $data Custom payment data
     * @return array
     */
    private function prepare_custom_transaction_data($data) {
        $callback_url = $this->get_custom_callback_url($data);

        return [
            'email' => sanitize_email($data['email']),
            'amount' => $this->format_amount($data['amount'], $data['currency']),
            'currency' => sanitize_text_field($data['currency']),
            'reference' => $this->generate_reference('custom'),
            'callback_url' => $callback_url,
            'metadata' => [
                'description' => sanitize_text_field($data['description']),
                'customer_name' => sanitize_text_field($data['customer_name'] ?? ''),
                'customer_phone' => sanitize_text_field($data['customer_phone'] ?? ''),
                'source' => 'chatshop_payment_link',
                'link_type' => 'custom',
                'custom_data' => $data['metadata'] ?? []
            ],
            'channels' => $data['channels'] ?? $this->gateway->get_option('payment_channels', ['card', 'bank'])
        ];
    }

    /**
     * Generate unique reference
     *
     * @param string $type Reference type
     * @param int|null $id Optional ID
     * @return string
     */
    private function generate_reference($type, $id = null) {
        $prefix = 'chatshop_' . $type . '_';
        $suffix = time() . '_' . wp_generate_password(8, false);
        
        if ($id) {
            return $prefix . $id . '_' . $suffix;
        }
        
        return $prefix . $suffix;
    }

    /**
     * Format amount for Paystack API
     *
     * @param float $amount Amount
     * @param string $currency Currency code
     * @return int
     */
    private function format_amount($amount, $currency) {
        $multipliers = [
            'NGN' => 100, 'USD' => 100, 'GHS' => 100,
            'ZAR' => 100, 'KES' => 100, 'EGP' => 100,
            'XOF' => 1
        ];

        $multiplier = $multipliers[$currency] ?? 100;
        return intval($amount * $multiplier);
    }

    /**
     * Calculate expiry time
     *
     * @param array $options Options array
     * @return string
     */
    private function calculate_expiry_time($options) {
        $expiry_hours = $options['expiry_hours'] ?? 24; // Default 24 hours
        return date('Y-m-d H:i:s', strtotime('+' . $expiry_hours . ' hours'));
    }

    /**
     * Get callback URL for order
     *
     * @param WC_Order $order Order object
     * @param array $options Additional options
     * @return string
     */
    private function get_callback_url($order, $options = []) {
        $callback_url = $this->gateway->get_return_url($order);
        
        if (!empty($options['custom_callback'])) {
            $callback_url = esc_url_raw($options['custom_callback']);
        }

        return add_query_arg([
            'utm_source' => 'chatshop',
            'utm_medium' => 'payment_link',
            'utm_campaign' => 'order_payment'
        ], $callback_url);
    }

    /**
     * Get callback URL for custom payment
     *
     * @param array $data Custom payment data
     * @return string
     */
    private function get_custom_callback_url($data) {
        $default_url = home_url('/payment-success/');
        $callback_url = $data['callback_url'] ?? $default_url;

        return add_query_arg([
            'utm_source' => 'chatshop',
            'utm_medium' => 'payment_link',
            'utm_campaign' => 'custom_payment'
        ], esc_url_raw($callback_url));
    }

    /**
     * Generate WhatsApp message for order
     *
     * @param WC_Order $order Order object
     * @param string $payment_url Payment URL
     * @param array $options Additional options
     * @return string
     */
    private function generate_whatsapp_message($order, $payment_url, $options = []) {
        $template = $options['message_template'] ?? $this->get_default_order_template();
        
        $placeholders = [
            '{customer_name}' => $order->get_formatted_billing_full_name(),
            '{order_number}' => $order->get_order_number(),
            '{amount}' => wc_price($order->get_total()),
            '{currency}' => $order->get_currency(),
            '{payment_url}' => $payment_url,
            '{store_name}' => get_bloginfo('name'),
            '{expiry_time}' => $options['expiry_hours'] ?? 24
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }

    /**
     * Generate WhatsApp message for custom payment
     *
     * @param array $data Payment data
     * @param string $payment_url Payment URL
     * @return string
     */
    private function generate_custom_whatsapp_message($data, $payment_url) {
        $template = $data['message_template'] ?? $this->get_default_custom_template();
        
        $placeholders = [
            '{customer_name}' => $data['customer_name'] ?? 'Customer',
            '{description}' => $data['description'],
            '{amount}' => number_format($data['amount'], 2),
            '{currency}' => $data['currency'],
            '{payment_url}' => $payment_url,
            '{store_name}' => get_bloginfo('name')
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }

    /**
     * Generate WhatsApp message for payment request
     *
     * @param array $data Payment request data
     * @param string $payment_url Payment URL
     * @return string
     */
    private function generate_payment_request_whatsapp_message($data, $payment_url) {
        $template = $data['message_template'] ?? $this->get_default_payment_request_template();
        
        $placeholders = [
            '{description}' => $data['description'],
            '{amount}' => number_format($data['amount'], 2),
            '{currency}' => $data['currency'],
            '{payment_url}' => $payment_url,
            '{store_name}' => get_bloginfo('name'),
            '{due_date}' => $data['due_date'] ?? 'Not specified'
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }

    /**
     * Save payment link to database
     *
     * @param array $data Link data
     * @return int|false
     */
    private function save_payment_link($data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_payment_links';
        
        $result = $wpdb->insert($table_name, $data);
        
        return $result !== false ? $wpdb->insert_id : false;
    }

    /**
     * Get payment link by reference
     *
     * @param string $reference Payment reference
     * @return array|null
     */
    private function get_payment_link_by_reference($reference) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_payment_links';
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE reference = %s",
                sanitize_text_field($reference)
            ),
            ARRAY_A
        );
    }

    /**
     * Get default order message template
     *
     * @return string
     */
    private function get_default_order_template() {
        return __('Hi {customer_name}! 👋

Your order #{order_number} is ready for payment.

Amount: {amount}

Please complete your payment using this secure link:
{payment_url}

This link expires in {expiry_time} hours.

Thank you for choosing {store_name}! 🙏', 'chatshop');
    }

    /**
     * Get default custom payment template
     *
     * @return string
     */
    private function get_default_custom_template() {
        return __('Hi {customer_name}! 👋

Payment Request: {description}

Amount: {amount} {currency}

Please complete your payment using this secure link:
{payment_url}

Thank you for choosing {store_name}! 🙏', 'chatshop');
    }

    /**
     * Get default payment request template
     *
     * @return string
     */
    private function get_default_payment_request_template() {
        return __('Payment Request 💰

{description}

Amount: {amount} {currency}
Due Date: {due_date}

Pay securely using this link:
{payment_url}

From {store_name}', 'chatshop');
    }
}