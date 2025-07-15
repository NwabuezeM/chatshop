<?php
/**
 * Paystack Payment Gateway Implementation
 *
 * File: components/payment/gateways/paystack/class-chatshop-paystack-gateway.php
 * 
 * @package ChatShop
 * @subpackage Payment\Gateways\Paystack
 * @since 1.0.0
 */

namespace ChatShop\Payment\Gateways\Paystack;

use ChatShop\Payment\Abstracts\Abstract_ChatShop_Payment_Gateway;
use ChatShop\Core\ChatShop_Logger;
use ChatShop\Core\ChatShop_Security;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Paystack Gateway Class
 * 
 * Handles Paystack payment processing with comprehensive feature support
 */
class ChatShop_Paystack_Gateway extends Abstract_ChatShop_Payment_Gateway {

    /**
     * Gateway ID
     */
    const GATEWAY_ID = 'paystack';

    /**
     * Supported currencies
     */
    const SUPPORTED_CURRENCIES = ['NGN', 'USD', 'GHS', 'ZAR', 'KES', 'XOF', 'EGP'];

    /**
     * Paystack API client
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
     */
    public function __construct() {
        $this->gateway_id = self::GATEWAY_ID;
        $this->method_title = __('Paystack', 'chatshop');
        $this->method_description = __('Accept payments via Paystack - Cards, Bank Transfer, Mobile Money', 'chatshop');
        $this->title = $this->get_option('title', 'Paystack');
        $this->description = $this->get_option('description', 'Pay securely with your card, bank transfer, or mobile money');
        
        // Load dependencies
        $this->api_client = new ChatShop_Paystack_API($this);
        $this->logger = new ChatShop_Logger('paystack');
        
        // Set supported features
        $this->supports = [
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'multiple_subscriptions',
            'refunds',
            'partial_refunds'
        ];

        parent::__construct();
        
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('woocommerce_api_chatshop_paystack_webhook', [$this, 'handle_webhook']);
        add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
        add_action('admin_notices', [$this, 'admin_notices']);
    }

    /**
     * Get gateway configuration fields
     *
     * @return array
     */
    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'chatshop'),
                'type' => 'checkbox',
                'label' => __('Enable Paystack Gateway', 'chatshop'),
                'default' => 'no'
            ],
            'title' => [
                'title' => __('Title', 'chatshop'),
                'type' => 'text',
                'description' => __('This controls the title customers see during checkout.', 'chatshop'),
                'default' => __('Paystack', 'chatshop'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'chatshop'),
                'type' => 'textarea',
                'description' => __('Payment method description that customers see during checkout.', 'chatshop'),
                'default' => __('Pay securely with your card, bank transfer, or mobile money', 'chatshop'),
                'desc_tip' => true,
            ],
            'testmode' => [
                'title' => __('Test Mode', 'chatshop'),
                'type' => 'checkbox',
                'label' => __('Enable Test Mode', 'chatshop'),
                'default' => 'yes',
                'description' => __('Use test mode for testing payments. No real transactions will occur.', 'chatshop'),
            ],
            'test_public_key' => [
                'title' => __('Test Public Key', 'chatshop'),
                'type' => 'text',
                'description' => __('Your Paystack test public key', 'chatshop'),
                'default' => '',
                'desc_tip' => true,
            ],
            'test_secret_key' => [
                'title' => __('Test Secret Key', 'chatshop'),
                'type' => 'password',
                'description' => __('Your Paystack test secret key', 'chatshop'),
                'default' => '',
                'desc_tip' => true,
            ],
            'live_public_key' => [
                'title' => __('Live Public Key', 'chatshop'),
                'type' => 'text',
                'description' => __('Your Paystack live public key', 'chatshop'),
                'default' => '',
                'desc_tip' => true,
            ],
            'live_secret_key' => [
                'title' => __('Live Secret Key', 'chatshop'),
                'type' => 'password',
                'description' => __('Your Paystack live secret key', 'chatshop'),
                'default' => '',
                'desc_tip' => true,
            ],
            'webhook_url' => [
                'title' => __('Webhook URL', 'chatshop'),
                'type' => 'text',
                'description' => sprintf(
                    __('Set this URL in your Paystack dashboard: %s', 'chatshop'),
                    $this->get_webhook_url()
                ),
                'default' => $this->get_webhook_url(),
                'desc_tip' => true,
                'custom_attributes' => ['readonly' => 'readonly']
            ],
            'payment_channels' => [
                'title' => __('Payment Channels', 'chatshop'),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'description' => __('Select payment channels to enable', 'chatshop'),
                'default' => ['card', 'bank', 'ussd', 'mobile_money'],
                'options' => [
                    'card' => __('Card', 'chatshop'),
                    'bank' => __('Bank Transfer', 'chatshop'),
                    'ussd' => __('USSD', 'chatshop'),
                    'mobile_money' => __('Mobile Money', 'chatshop'),
                    'bank_transfer' => __('Bank Transfer', 'chatshop'),
                    'eft' => __('EFT', 'chatshop')
                ],
                'desc_tip' => true,
            ],
            'auto_complete' => [
                'title' => __('Auto Complete Orders', 'chatshop'),
                'type' => 'checkbox',
                'label' => __('Automatically complete orders after successful payment', 'chatshop'),
                'default' => 'no',
                'description' => __('Orders will be marked as completed automatically.', 'chatshop'),
            ],
            'logging' => [
                'title' => __('Debug Logging', 'chatshop'),
                'type' => 'checkbox',
                'label' => __('Enable logging', 'chatshop'),
                'default' => 'no',
                'description' => __('Log Paystack events for debugging purposes.', 'chatshop'),
            ],
        ];
    }

    /**
     * Process payment
     *
     * @param int $order_id Order ID
     * @return array
     */
    public function process_payment($order_id) {
        try {
            $order = wc_get_order($order_id);
            
            if (!$order) {
                throw new \Exception(__('Order not found', 'chatshop'));
            }

            // Validate currency
            if (!$this->is_currency_supported($order->get_currency())) {
                throw new \Exception(
                    sprintf(
                        __('Currency %s is not supported by Paystack', 'chatshop'),
                        $order->get_currency()
                    )
                );
            }

            // Initialize transaction
            $transaction_data = $this->prepare_transaction_data($order);
            $response = $this->api_client->initialize_transaction($transaction_data);

            if (!$response['status']) {
                throw new \Exception($response['message'] ?? __('Transaction initialization failed', 'chatshop'));
            }

            // Save transaction reference
            $order->update_meta_data('_paystack_access_code', $response['data']['access_code']);
            $order->update_meta_data('_paystack_reference', $response['data']['reference']);
            $order->save();

            $this->logger->info('Transaction initialized', [
                'order_id' => $order_id,
                'reference' => $response['data']['reference']
            ]);

            return [
                'result' => 'success',
                'redirect' => $response['data']['authorization_url']
            ];

        } catch (\Exception $e) {
            $this->logger->error('Payment processing failed', [
                'order_id' => $order_id,
                'error' => $e->getMessage()
            ]);

            wc_add_notice($e->getMessage(), 'error');
            return ['result' => 'fail'];
        }
    }

    /**
     * Verify transaction
     *
     * @param string $reference Transaction reference
     * @return array|false
     */
    public function verify_transaction($reference) {
        return $this->api_client->verify_transaction($reference);
    }

    /**
     * Handle webhook notifications
     */
    public function handle_webhook() {
        $webhook_handler = new ChatShop_Paystack_Webhook($this);
        $webhook_handler->process_webhook();
    }

    /**
     * Process refund
     *
     * @param int $order_id Order ID
     * @param float $amount Refund amount
     * @param string $reason Refund reason
     * @return bool|WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        try {
            $order = wc_get_order($order_id);
            
            if (!$order) {
                return new \WP_Error('invalid_order', __('Order not found', 'chatshop'));
            }

            $transaction_reference = $order->get_meta('_paystack_reference');
            
            if (!$transaction_reference) {
                return new \WP_Error('no_reference', __('Paystack transaction reference not found', 'chatshop'));
            }

            $refund_data = [
                'transaction' => $transaction_reference,
                'amount' => $amount ? $this->format_amount($amount, $order->get_currency()) : null,
                'currency' => $order->get_currency(),
                'customer_note' => $reason,
                'merchant_note' => sprintf(__('Refund for order %s', 'chatshop'), $order->get_order_number())
            ];

            $response = $this->api_client->create_refund($refund_data);

            if ($response['status']) {
                $order->add_order_note(
                    sprintf(
                        __('Paystack refund completed. Refund ID: %s', 'chatshop'),
                        $response['data']['id']
                    )
                );
                
                $this->logger->info('Refund processed', [
                    'order_id' => $order_id,
                    'amount' => $amount,
                    'refund_id' => $response['data']['id']
                ]);

                return true;
            }

            return new \WP_Error('refund_failed', $response['message'] ?? __('Refund failed', 'chatshop'));

        } catch (\Exception $e) {
            $this->logger->error('Refund processing failed', [
                'order_id' => $order_id,
                'error' => $e->getMessage()
            ]);

            return new \WP_Error('refund_error', $e->getMessage());
        }
    }

    /**
     * Prepare transaction data for API
     *
     * @param WC_Order $order Order object
     * @return array
     */
    private function prepare_transaction_data($order) {
        $callback_url = $this->get_return_url($order);
        $cancel_url = wc_get_checkout_url();

        return [
            'email' => $order->get_billing_email(),
            'amount' => $this->format_amount($order->get_total(), $order->get_currency()),
            'currency' => $order->get_currency(),
            'reference' => $this->generate_reference($order),
            'callback_url' => $callback_url,
            'metadata' => [
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'customer_name' => $order->get_formatted_billing_full_name(),
                'customer_phone' => $order->get_billing_phone(),
                'plugin' => 'chatshop',
                'cancel_action' => $cancel_url
            ],
            'channels' => $this->get_option('payment_channels', ['card', 'bank']),
            'split_code' => $this->get_split_code($order),
            'subaccount' => $this->get_subaccount($order)
        ];
    }

    /**
     * Generate unique transaction reference
     *
     * @param WC_Order $order Order object
     * @return string
     */
    private function generate_reference($order) {
        return 'chatshop_' . $order->get_id() . '_' . time() . '_' . wp_generate_password(8, false);
    }

    /**
     * Format amount for Paystack API (in kobo/cents)
     *
     * @param float $amount Amount
     * @param string $currency Currency code
     * @return int
     */
    private function format_amount($amount, $currency) {
        // Convert to smallest currency unit
        $multiplier = $this->get_currency_multiplier($currency);
        return intval($amount * $multiplier);
    }

    /**
     * Get currency multiplier
     *
     * @param string $currency Currency code
     * @return int
     */
    private function get_currency_multiplier($currency) {
        $multipliers = [
            'NGN' => 100, // Kobo
            'USD' => 100, // Cents
            'GHS' => 100, // Pesewas
            'ZAR' => 100, // Cents
            'KES' => 100, // Cents
            'XOF' => 1,   // No subdivision
            'EGP' => 100  // Piastres
        ];

        return $multipliers[$currency] ?? 100;
    }

    /**
     * Check if currency is supported
     *
     * @param string $currency Currency code
     * @return bool
     */
    public function is_currency_supported($currency) {
        return in_array($currency, self::SUPPORTED_CURRENCIES, true);
    }

    /**
     * Get webhook URL
     *
     * @return string
     */
    public function get_webhook_url() {
        return add_query_arg('wc-api', 'chatshop_paystack_webhook', home_url('/'));
    }

    /**
     * Get public key
     *
     * @return string
     */
    public function get_public_key() {
        $is_test_mode = $this->get_option('testmode') === 'yes';
        return $is_test_mode ? $this->get_option('test_public_key') : $this->get_option('live_public_key');
    }

    /**
     * Get secret key
     *
     * @return string
     */
    public function get_secret_key() {
        $is_test_mode = $this->get_option('testmode') === 'yes';
        return $is_test_mode ? $this->get_option('test_secret_key') : $this->get_option('live_secret_key');
    }

    /**
     * Check if gateway is available
     *
     * @return bool
     */
    public function is_available() {
        if (!parent::is_available()) {
            return false;
        }

        if (!$this->get_secret_key() || !$this->get_public_key()) {
            return false;
        }

        if (!$this->is_currency_supported(get_woocommerce_currency())) {
            return false;
        }

        return true;
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        if ($this->enabled !== 'yes') {
            return;
        }

        if (!$this->get_secret_key() || !$this->get_public_key()) {
            echo '<div class="error"><p>' . 
                 esc_html__('Paystack gateway is enabled but API keys are not configured.', 'chatshop') . 
                 '</p></div>';
        }

        if (!$this->is_currency_supported(get_woocommerce_currency())) {
            echo '<div class="error"><p>' . 
                 sprintf(
                     esc_html__('Paystack does not support your store currency (%s).', 'chatshop'),
                     get_woocommerce_currency()
                 ) . 
                 '</p></div>';
        }
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        if (!is_checkout() || !$this->is_available()) {
            return;
        }

        wp_enqueue_script(
            'chatshop-paystack-checkout',
            CHATSHOP_PLUGIN_URL . 'assets/js/components/payment/paystack-checkout.js',
            ['jquery'],
            CHATSHOP_VERSION,
            true
        );

        wp_localize_script('chatshop-paystack-checkout', 'chatshop_paystack_params', [
            'public_key' => $this->get_public_key(),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatshop_paystack_nonce')
        ]);
    }

    /**
     * Get split code for transactions (for marketplace functionality)
     *
     * @param WC_Order $order Order object
     * @return string|null
     */
    private function get_split_code($order) {
        // Implement marketplace split logic if needed
        return apply_filters('chatshop_paystack_split_code', null, $order);
    }

    /**
     * Get subaccount for transactions
     *
     * @param WC_Order $order Order object
     * @return string|null
     */
    private function get_subaccount($order) {
        // Implement subaccount logic if needed
        return apply_filters('chatshop_paystack_subaccount', null, $order);
    }

    /**
     * Receipt page output
     *
     * @param int $order_id Order ID
     */
    public function receipt_page($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }

        echo '<p>' . esc_html__('Thank you for your order, please click the button below to pay with Paystack.', 'chatshop') . '</p>';
        echo $this->generate_paystack_form($order);
    }

    /**
     * Generate payment form
     *
     * @param WC_Order $order Order object
     * @return string
     */
    private function generate_paystack_form($order) {
        $access_code = $order->get_meta('_paystack_access_code');
        
        if (!$access_code) {
            return '<p>' . esc_html__('Payment session expired. Please try again.', 'chatshop') . '</p>';
        }

        ob_start();
        include CHATSHOP_PLUGIN_PATH . 'components/payment/gateways/paystack/partials/payment-form.php';
        return ob_get_clean();
    }
}