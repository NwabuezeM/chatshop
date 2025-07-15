<?php
/**
 * Paystack WooCommerce Blocks Integration
 *
 * File: components/payment/gateways/paystack/class-chatshop-paystack-blocks.php
 * 
 * @package ChatShop
 * @subpackage Payment\Gateways\Paystack
 * @since 1.0.0
 */

namespace ChatShop\Payment\Gateways\Paystack;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Paystack Blocks Integration
 * 
 * Integrates Paystack with WooCommerce Blocks checkout
 */
class ChatShop_Paystack_Blocks extends AbstractPaymentMethodType {

    /**
     * Payment method name
     *
     * @var string
     */
    protected $name = 'chatshop_paystack';

    /**
     * Gateway instance
     *
     * @var ChatShop_Paystack_Gateway
     */
    private $gateway;

    /**
     * Constructor
     */
    public function __construct() {
        add_action('woocommerce_blocks_payment_method_type_registration', [$this, 'register_payment_method']);
    }

    /**
     * Initialize the payment method
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_chatshop_paystack_settings', []);
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = $gateways['chatshop_paystack'] ?? null;
    }

    /**
     * Check if payment method is active
     *
     * @return bool
     */
    public function is_active() {
        return $this->gateway && $this->gateway->is_available();
    }

    /**
     * Get payment method script handles
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        wp_register_script(
            'chatshop-paystack-blocks-integration',
            CHATSHOP_PLUGIN_URL . 'assets/js/components/payment/paystack-blocks.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            CHATSHOP_VERSION,
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(
                'chatshop-paystack-blocks-integration',
                'chatshop',
                CHATSHOP_PLUGIN_PATH . 'languages'
            );
        }

        return ['chatshop-paystack-blocks-integration'];
    }

    /**
     * Get payment method script handles for admin
     *
     * @return array
     */
    public function get_payment_method_script_handles_for_admin() {
        return $this->get_payment_method_script_handles();
    }

    /**
     * Get payment method data for client side
     *
     * @return array
     */
    public function get_payment_method_data() {
        if (!$this->gateway) {
            return [];
        }

        return [
            'title' => $this->gateway->get_title(),
            'description' => $this->gateway->get_description(),
            'public_key' => $this->gateway->get_public_key(),
            'supports' => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
            'logo_url' => CHATSHOP_PLUGIN_URL . 'assets/images/paystack-logo.png',
            'test_mode' => $this->gateway->get_option('testmode') === 'yes',
            'channels' => $this->gateway->get_option('payment_channels', ['card', 'bank']),
            'currency' => get_woocommerce_currency(),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'supported_currencies' => ChatShop_Paystack_Gateway::SUPPORTED_CURRENCIES,
            'strings' => [
                'pay_button_text' => __('Pay with Paystack', 'chatshop'),
                'processing_text' => __('Processing payment...', 'chatshop'),
                'error_generic' => __('Payment failed. Please try again.', 'chatshop'),
                'error_network' => __('Network error. Please check your connection.', 'chatshop'),
                'error_cancelled' => __('Payment was cancelled.', 'chatshop'),
                'success_message' => __('Payment completed successfully!', 'chatshop'),
                'card_text' => __('Pay with your debit/credit card', 'chatshop'),
                'bank_text' => __('Pay via bank transfer', 'chatshop'),
                'ussd_text' => __('Pay with USSD', 'chatshop'),
                'mobile_money_text' => __('Pay with mobile money', 'chatshop')
            ]
        ];
    }

    /**
     * Register payment method with blocks
     *
     * @param object $payment_method_registry Payment method registry
     */
    public function register_payment_method($payment_method_registry) {
        $payment_method_registry->register($this);
    }

    /**
     * Process payment for blocks checkout
     *
     * @param array $payment_data Payment data
     * @return array
     */
    public function process_payment_for_order($payment_data) {
        if (!$this->gateway) {
            return [
                'result' => 'failure',
                'message' => __('Payment gateway not available', 'chatshop')
            ];
        }

        // Extract order ID from payment data
        $order_id = $payment_data['order_id'] ?? 0;
        
        if (!$order_id) {
            return [
                'result' => 'failure',
                'message' => __('Invalid order', 'chatshop')
            ];
        }

        // Process payment using the gateway
        return $this->gateway->process_payment($order_id);
    }

    /**
     * Get block editor script asset
     *
     * @return array
     */
    private function get_script_asset() {
        $script_asset_path = CHATSHOP_PLUGIN_PATH . 'assets/js/components/payment/paystack-blocks.asset.php';
        
        if (file_exists($script_asset_path)) {
            return require $script_asset_path;
        }

        return [
            'dependencies' => [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            'version' => CHATSHOP_VERSION
        ];
    }

    /**
     * Enqueue block styles
     */
    public function enqueue_block_styles() {
        wp_enqueue_style(
            'chatshop-paystack-blocks-style',
            CHATSHOP_PLUGIN_URL . 'assets/css/components/payment/paystack-blocks.css',
            [],
            CHATSHOP_VERSION
        );
    }

    /**
     * Add inline styles for customization
     */
    public function add_inline_styles() {
        $custom_css = "
            .wc-block-components-payment-method-label__container .chatshop-paystack-logo {
                height: 24px;
                width: auto;
                margin-right: 8px;
                vertical-align: middle;
            }
            
            .chatshop-paystack-payment-method {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .chatshop-paystack-channels {
                display: flex;
                flex-wrap: wrap;
                gap: 4px;
                margin-top: 4px;
            }
            
            .chatshop-paystack-channel {
                background: #f5f5f5;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 11px;
                color: #666;
            }
            
            .chatshop-paystack-test-mode {
                background: #ffa500;
                color: white;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 10px;
                font-weight: bold;
                margin-left: 8px;
            }
        ";

        wp_add_inline_style('chatshop-paystack-blocks-style', $custom_css);
    }

    /**
     * Validate payment method settings for blocks
     *
     * @return bool
     */
    public function validate_settings() {
        if (!$this->gateway) {
            return false;
        }

        // Check if gateway is enabled
        if ($this->gateway->get_option('enabled') !== 'yes') {
            return false;
        }

        // Check if API keys are configured
        $public_key = $this->gateway->get_public_key();
        $secret_key = $this->gateway->get_secret_key();

        if (empty($public_key) || empty($secret_key)) {
            return false;
        }

        // Check if currency is supported
        $current_currency = get_woocommerce_currency();
        if (!in_array($current_currency, ChatShop_Paystack_Gateway::SUPPORTED_CURRENCIES)) {
            return false;
        }

        return true;
    }

    /**
     * Get supported features for blocks
     *
     * @return array
     */
    public function get_supported_features() {
        if (!$this->gateway) {
            return [];
        }

        return array_filter([
            'products' => $this->gateway->supports('products'),
            'subscriptions' => $this->gateway->supports('subscriptions'),
            'subscription_cancellation' => $this->gateway->supports('subscription_cancellation'),
            'subscription_suspension' => $this->gateway->supports('subscription_suspension'),
            'subscription_reactivation' => $this->gateway->supports('subscription_reactivation'),
            'subscription_amount_changes' => $this->gateway->supports('subscription_amount_changes'),
            'subscription_date_changes' => $this->gateway->supports('subscription_date_changes'),
            'multiple_subscriptions' => $this->gateway->supports('multiple_subscriptions'),
            'refunds' => $this->gateway->supports('refunds'),
            'partial_refunds' => $this->gateway->supports('partial_refunds')
        ]);
    }

    /**
     * Add hooks for blocks integration
     */
    public function add_hooks() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_block_styles']);
        add_action('wp_enqueue_scripts', [$this, 'add_inline_styles']);
        
        // Add payment method validation
        add_filter('woocommerce_blocks_payment_method_type_supports', [$this, 'filter_payment_method_supports'], 10, 3);
        
        // Add custom payment method data
        add_filter('woocommerce_blocks_payment_method_data', [$this, 'filter_payment_method_data'], 10, 2);
    }

    /**
     * Filter payment method supports
     *
     * @param array $supports Supported features
     * @param string $payment_method_id Payment method ID
     * @param object $payment_method_type Payment method type instance
     * @return array
     */
    public function filter_payment_method_supports($supports, $payment_method_id, $payment_method_type) {
        if ($payment_method_id === $this->name && $payment_method_type instanceof self) {
            return array_merge($supports, $this->get_supported_features());
        }

        return $supports;
    }

    /**
     * Filter payment method data
     *
     * @param array $data Payment method data
     * @param string $payment_method_id Payment method ID
     * @return array
     */
    public function filter_payment_method_data($data, $payment_method_id) {
        if ($payment_method_id === $this->name) {
            $data = array_merge($data, $this->get_payment_method_data());
        }

        return $data;
    }

    /**
     * Handle payment processing error
     *
     * @param string $error_message Error message
     * @return array
     */
    private function handle_payment_error($error_message) {
        return [
            'result' => 'failure',
            'message' => $error_message,
            'redirect' => ''
        ];
    }

    /**
     * Get gateway settings for blocks
     *
     * @return array
     */
    public function get_gateway_settings() {
        if (!$this->gateway) {
            return [];
        }

        return [
            'enabled' => $this->gateway->get_option('enabled'),
            'title' => $this->gateway->get_option('title'),
            'description' => $this->gateway->get_option('description'),
            'testmode' => $this->gateway->get_option('testmode'),
            'public_key' => $this->gateway->get_public_key(),
            'channels' => $this->gateway->get_option('payment_channels'),
            'auto_complete' => $this->gateway->get_option('auto_complete')
        ];
    }

    /**
     * Check if test mode is enabled
     *
     * @return bool
     */
    public function is_test_mode() {
        return $this->gateway && $this->gateway->get_option('testmode') === 'yes';
    }

    /**
     * Get available payment channels
     *
     * @return array
     */
    public function get_available_channels() {
        if (!$this->gateway) {
            return [];
        }

        $configured_channels = $this->gateway->get_option('payment_channels', ['card', 'bank']);
        $channel_labels = [
            'card' => __('Card', 'chatshop'),
            'bank' => __('Bank Transfer', 'chatshop'),
            'ussd' => __('USSD', 'chatshop'),
            'mobile_money' => __('Mobile Money', 'chatshop'),
            'bank_transfer' => __('Bank Transfer', 'chatshop'),
            'eft' => __('EFT', 'chatshop')
        ];

        $available_channels = [];
        foreach ($configured_channels as $channel) {
            if (isset($channel_labels[$channel])) {
                $available_channels[$channel] = $channel_labels[$channel];
            }
        }

        return $available_channels;
    }

    /**
     * Get localized strings for JavaScript
     *
     * @return array
     */
    public function get_localized_strings() {
        return [
            'pay_button_text' => __('Pay with Paystack', 'chatshop'),
            'processing_text' => __('Processing payment...', 'chatshop'),
            'error_generic' => __('Payment failed. Please try again.', 'chatshop'),
            'error_network' => __('Network error. Please check your connection.', 'chatshop'),
            'error_cancelled' => __('Payment was cancelled.', 'chatshop'),
            'error_invalid_key' => __('Invalid payment configuration.', 'chatshop'),
            'error_unsupported_currency' => __('Currency not supported.', 'chatshop'),
            'success_message' => __('Payment completed successfully!', 'chatshop'),
            'loading_text' => __('Loading payment method...', 'chatshop'),
            'retry_text' => __('Retry Payment', 'chatshop'),
            'cancel_text' => __('Cancel', 'chatshop'),
            'secure_payment_text' => __('Secure payment powered by Paystack', 'chatshop'),
            'test_mode_notice' => __('TEST MODE - No real transactions will occur', 'chatshop'),
            'amount_label' => __('Amount:', 'chatshop'),
            'currency_label' => __('Currency:', 'chatshop'),
            'payment_method_title' => __('Paystack Payment', 'chatshop'),
            'payment_method_description' => __('Pay securely with your card, bank transfer, or mobile money', 'chatshop')
        ];
    }

    /**
     * Validate order data for blocks payment
     *
     * @param array $order_data Order data
     * @return array Validation result
     */
    public function validate_order_data($order_data) {
        $errors = [];

        // Check required fields
        if (empty($order_data['billing']['email'])) {
            $errors[] = __('Billing email is required', 'chatshop');
        }

        if (empty($order_data['total'])) {
            $errors[] = __('Order total is required', 'chatshop');
        }

        // Validate currency
        $currency = $order_data['currency'] ?? get_woocommerce_currency();
        if (!in_array($currency, ChatShop_Paystack_Gateway::SUPPORTED_CURRENCIES)) {
            $errors[] = sprintf(
                __('Currency %s is not supported by Paystack', 'chatshop'),
                $currency
            );
        }

        // Validate amount
        $total = floatval($order_data['total'] ?? 0);
        if ($total <= 0) {
            $errors[] = __('Order total must be greater than zero', 'chatshop');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Format error response for blocks
     *
     * @param string $message Error message
     * @param string $type Error type
     * @return array
     */
    public function format_error_response($message, $type = 'generic') {
        return [
            'result' => 'failure',
            'messages' => [
                [
                    'context' => 'wc/checkout',
                    'text' => $message,
                    'type' => 'error'
                ]
            ],
            'payment_details' => [
                'error_type' => $type,
                'gateway' => $this->name
            ]
        ];
    }

    /**
     * Format success response for blocks
     *
     * @param array $payment_data Payment data
     * @return array
     */
    public function format_success_response($payment_data) {
        return [
            'result' => 'success',
            'redirect' => $payment_data['redirect'] ?? '',
            'payment_details' => [
                'reference' => $payment_data['reference'] ?? '',
                'gateway' => $this->name,
                'status' => 'processing'
            ]
        ];
    }

    /**
     * Get payment form HTML for fallback
     *
     * @param array $args Form arguments
     * @return string
     */
    public function get_payment_form_html($args = []) {
        $defaults = [
            'show_logo' => true,
            'show_description' => true,
            'show_channels' => true,
            'show_test_notice' => $this->is_test_mode()
        ];

        $args = wp_parse_args($args, $defaults);

        ob_start();
        ?>
        <div class="chatshop-paystack-payment-method">
            <?php if ($args['show_logo']): ?>
                <img src="<?php echo esc_url(CHATSHOP_PLUGIN_URL . 'assets/images/paystack-logo.png'); ?>" 
                     alt="<?php esc_attr_e('Paystack', 'chatshop'); ?>" 
                     class="chatshop-paystack-logo">
            <?php endif; ?>

            <div class="chatshop-paystack-content">
                <?php if ($args['show_description']): ?>
                    <p class="chatshop-paystack-description">
                        <?php echo esc_html($this->gateway ? $this->gateway->get_description() : ''); ?>
                    </p>
                <?php endif; ?>

                <?php if ($args['show_test_notice'] && $this->is_test_mode()): ?>
                    <p class="chatshop-paystack-test-mode">
                        <?php esc_html_e('TEST MODE - No real transactions will occur', 'chatshop'); ?>
                    </p>
                <?php endif; ?>

                <?php if ($args['show_channels']): ?>
                    <div class="chatshop-paystack-channels">
                        <?php foreach ($this->get_available_channels() as $channel => $label): ?>
                            <span class="chatshop-paystack-channel"><?php echo esc_html($label); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Add admin notices for blocks compatibility
     */
    public function add_admin_notices() {
        if (!$this->validate_settings()) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p>';
                echo esc_html__('Paystack payment method is not properly configured for WooCommerce Blocks checkout.', 'chatshop');
                echo '</p></div>';
            });
        }
    }

    /**
     * Initialize blocks integration
     */
    public function init() {
        if (!did_action('woocommerce_blocks_loaded')) {
            return;
        }

        $this->initialize();
        $this->add_hooks();
        $this->add_admin_notices();
    }

    /**
     * Check if WooCommerce Blocks is active
     *
     * @return bool
     */
    public function is_blocks_active() {
        return function_exists('woocommerce_store_api_register_payment_method');
    }

    /**
     * Get gateway icon URL
     *
     * @return string
     */
    public function get_icon_url() {
        return CHATSHOP_PLUGIN_URL . 'assets/images/paystack-icon.png';
    }

    /**
     * Get gateway logo URL
     *
     * @return string
     */
    public function get_logo_url() {
        return CHATSHOP_PLUGIN_URL . 'assets/images/paystack-logo.png';
    }

    /**
     * Check if payment method should be available
     *
     * @return bool
     */
    public function should_be_available() {
        // Check if WooCommerce Blocks is available
        if (!$this->is_blocks_active()) {
            return false;
        }

        // Check if gateway is available
        if (!$this->is_active()) {
            return false;
        }

        // Check settings validation
        if (!$this->validate_settings()) {
            return false;
        }

        return true;
    }

    /**
     * Get debug information
     *
     * @return array
     */
    public function get_debug_info() {
        return [
            'blocks_active' => $this->is_blocks_active(),
            'gateway_active' => $this->is_active(),
            'settings_valid' => $this->validate_settings(),
            'test_mode' => $this->is_test_mode(),
            'supported_currency' => in_array(get_woocommerce_currency(), ChatShop_Paystack_Gateway::SUPPORTED_CURRENCIES),
            'available_channels' => $this->get_available_channels(),
            'gateway_settings' => $this->get_gateway_settings()
        ];
    }
}