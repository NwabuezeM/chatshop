<?php
/**
 * Paystack Payment Form Template
 *
 * File: components/payment/gateways/paystack/partials/payment-form.php
 * 
 * @package ChatShop
 * @subpackage Payment\Gateways\Paystack
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get order data (should be available in scope)
if (!isset($order) || !isset($access_code)) {
    return;
}

$gateway = wc_get_payment_gateway_by_order($order);
if (!$gateway || $gateway->id !== 'chatshop_paystack') {
    return;
}

$public_key = $gateway->get_public_key();
$test_mode = $gateway->get_option('testmode') === 'yes';
$channels = $gateway->get_option('payment_channels', ['card', 'bank']);

// Order details
$amount = $order->get_total();
$currency = $order->get_currency();
$email = $order->get_billing_email();
$customer_name = $order->get_formatted_billing_full_name();
$reference = $order->get_meta('_paystack_reference');

// Callback URLs
$callback_url = $gateway->get_return_url($order);
$cancel_url = wc_get_checkout_url();
?>

<div id="chatshop-paystack-payment-form" class="chatshop-payment-form">
    
    <!-- Payment Summary -->
    <div class="payment-summary">
        <h3><?php esc_html_e('Payment Summary', 'chatshop'); ?></h3>
        <div class="summary-details">
            <div class="summary-row">
                <span class="label"><?php esc_html_e('Order Number:', 'chatshop'); ?></span>
                <span class="value"><?php echo esc_html($order->get_order_number()); ?></span>
            </div>
            <div class="summary-row">
                <span class="label"><?php esc_html_e('Amount:', 'chatshop'); ?></span>
                <span class="value amount"><?php echo wp_kses_post(wc_price($amount, ['currency' => $currency])); ?></span>
            </div>
            <div class="summary-row">
                <span class="label"><?php esc_html_e('Customer:', 'chatshop'); ?></span>
                <span class="value"><?php echo esc_html($customer_name); ?></span>
            </div>
        </div>
    </div>

    <!-- Test Mode Notice -->
    <?php if ($test_mode): ?>
    <div class="test-mode-notice">
        <p><strong><?php esc_html_e('TEST MODE:', 'chatshop'); ?></strong> <?php esc_html_e('No real transaction will occur.', 'chatshop'); ?></p>
    </div>
    <?php endif; ?>

    <!-- Payment Channels -->
    <div class="payment-channels">
        <h4><?php esc_html_e('Available Payment Methods:', 'chatshop'); ?></h4>
        <div class="channels-list">
            <?php if (in_array('card', $channels)): ?>
            <div class="channel-item">
                <span class="channel-icon">💳</span>
                <span class="channel-name"><?php esc_html_e('Credit/Debit Card', 'chatshop'); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (in_array('bank', $channels)): ?>
            <div class="channel-item">
                <span class="channel-icon">🏦</span>
                <span class="channel-name"><?php esc_html_e('Bank Transfer', 'chatshop'); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (in_array('ussd', $channels)): ?>
            <div class="channel-item">
                <span class="channel-icon">📱</span>
                <span class="channel-name"><?php esc_html_e('USSD', 'chatshop'); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (in_array('mobile_money', $channels)): ?>
            <div class="channel-item">
                <span class="channel-icon">💰</span>
                <span class="channel-name"><?php esc_html_e('Mobile Money', 'chatshop'); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Button -->
    <div class="payment-actions">
        <button type="button" id="chatshop-pay-now-btn" class="button button-primary button-large">
            <span class="button-text"><?php esc_html_e('Pay Now', 'chatshop'); ?></span>
            <span class="button-spinner" style="display: none;">
                <span class="spinner"></span>
                <?php esc_html_e('Processing...', 'chatshop'); ?>
            </span>
        </button>
        
        <a href="<?php echo esc_url($cancel_url); ?>" class="button button-secondary button-large">
            <?php esc_html_e('Cancel', 'chatshop'); ?>
        </a>
    </div>

    <!-- Security Notice -->
    <div class="security-notice">
        <p>
            <span class="security-icon">🔒</span>
            <?php esc_html_e('Your payment information is secure and encrypted.', 'chatshop'); ?>
        </p>
    </div>

    <!-- Error Messages -->
    <div id="chatshop-payment-errors" class="payment-errors" style="display: none;">
        <div class="error-message"></div>
    </div>

</div>

<!-- Hidden form data -->
<script type="text/javascript">
window.chatshopPaystackData = {
    publicKey: '<?php echo esc_js($public_key); ?>',
    email: '<?php echo esc_js($email); ?>',
    amount: <?php echo absint($amount * 100); ?>, // Convert to kobo/cents
    currency: '<?php echo esc_js($currency); ?>',
    reference: '<?php echo esc_js($reference); ?>',
    accessCode: '<?php echo esc_js($access_code); ?>',
    callbackUrl: '<?php echo esc_js($callback_url); ?>',
    cancelUrl: '<?php echo esc_js($cancel_url); ?>',
    customerName: '<?php echo esc_js($customer_name); ?>',
    channels: <?php echo wp_json_encode($channels); ?>,
    testMode: <?php echo $test_mode ? 'true' : 'false'; ?>,
    orderNumber: '<?php echo esc_js($order->get_order_number()); ?>',
    nonce: '<?php echo wp_create_nonce('chatshop_paystack_payment'); ?>',
    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
    strings: {
        processing: '<?php esc_html_e('Processing payment...', 'chatshop'); ?>',
        success: '<?php esc_html_e('Payment successful! Redirecting...', 'chatshop'); ?>',
        cancelled: '<?php esc_html_e('Payment was cancelled.', 'chatshop'); ?>',
        failed: '<?php esc_html_e('Payment failed. Please try again.', 'chatshop'); ?>',
        networkError: '<?php esc_html_e('Network error. Please check your connection.', 'chatshop'); ?>',
        invalidConfig: '<?php esc_html_e('Payment configuration error.', 'chatshop'); ?>',
        retryPayment: '<?php esc_html_e('Retry Payment', 'chatshop'); ?>',
        payNow: '<?php esc_html_e('Pay Now', 'chatshop'); ?>'
    }
};
</script>

<style>
.chatshop-payment-form {
    max-width: 500px;
    margin: 0 auto;
    padding: 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.payment-summary {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    border: 1px solid #e9ecef;
}

.payment-summary h3 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 18px;
}

.summary-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.summary-row .label {
    font-weight: 500;
    color: #666;
}

.summary-row .value {
    font-weight: 600;
    color: #333;
}

.summary-row .value.amount {
    font-size: 18px;
    color: #0073aa;
}

.test-mode-notice {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
    padding: 12px;
    border-radius: 4px;
    margin-bottom: 20px;
    text-align: center;
}

.test-mode-notice p {
    margin: 0;
}

.payment-channels {
    margin-bottom: 20px;
}

.payment-channels h4 {
    margin: 0 0 12px 0;
    color: #333;
    font-size: 16px;
}

.channels-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
}

.channel-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 12px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    text-align: center;
}

.channel-icon {
    font-size: 24px;
    margin-bottom: 6px;
}

.channel-name {
    font-size: 12px;
    font-weight: 500;
    color: #666;
}

.payment-actions {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
}

.payment-actions .button {
    flex: 1;
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    text-decoration: none;
    text-align: center;
    border: 2px solid;
    cursor: pointer;
    transition: all 0.3s ease;
}

.button-primary {
    background: #0073aa;
    border-color: #0073aa;
    color: #fff;
}

.button-primary:hover {
    background: #005177;
    border-color: #005177;
}

.button-primary:disabled {
    background: #ccc;
    border-color: #ccc;
    cursor: not-allowed;
}

.button-secondary {
    background: #fff;
    border-color: #ccc;
    color: #666;
}

.button-secondary:hover {
    background: #f8f9fa;
    border-color: #999;
    color: #333;
}

.button-spinner {
    display: flex;
    align-items: center;
    gap: 8px;
}

.spinner {
    width: 16px;
    height: 16px;
    border: 2px solid #fff;
    border-top: 2px solid transparent;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.security-notice {
    text-align: center;
    padding: 12px;
    background: #e8f5e8;
    border: 1px solid #c3e6c3;
    border-radius: 4px;
    margin-bottom: 20px;
}

.security-notice p {
    margin: 0;
    font-size: 14px;
    color: #2d5a2d;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.security-icon {
    font-size: 16px;
}

.payment-errors {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    padding: 12px;
    border-radius: 4px;
    margin-top: 15px;
}

.error-message {
    font-weight: 500;
}

/* Responsive Design */
@media (max-width: 600px) {
    .chatshop-payment-form {
        margin: 10px;
        padding: 15px;
    }
    
    .channels-list {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .payment-actions {
        flex-direction: column;
    }
    
    .summary-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .chatshop-payment-form {
        background: #1e1e1e;
        color: #fff;
    }
    
    .payment-summary {
        background: #2d2d2d;
        border-color: #404040;
    }
    
    .channel-item {
        background: #2d2d2d;
        border-color: #404040;
    }
    
    .button-secondary {
        background: #2d2d2d;
        border-color: #555;
        color: #ccc;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    const data = window.chatshopPaystackData;
    const $payButton = $('#chatshop-pay-now-btn');
    const $buttonText = $payButton.find('.button-text');
    const $buttonSpinner = $payButton.find('.button-spinner');
    const $errorContainer = $('#chatshop-payment-errors');
    const $errorMessage = $errorContainer.find('.error-message');

    // Initialize Paystack (load script if needed)
    function initializePaystack() {
        if (typeof PaystackPop !== 'undefined') {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://js.paystack.co/v1/inline.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    // Show error message
    function showError(message) {
        $errorMessage.text(message);
        $errorContainer.show();
        $('html, body').animate({
            scrollTop: $errorContainer.offset().top - 20
        }, 500);
    }

    // Hide error message
    function hideError() {
        $errorContainer.hide();
    }

    // Set button state
    function setButtonState(loading) {
        if (loading) {
            $payButton.prop('disabled', true);
            $buttonText.hide();
            $buttonSpinner.show();
        } else {
            $payButton.prop('disabled', false);
            $buttonText.show();
            $buttonSpinner.hide();
        }
    }

    // Handle payment success
    function handlePaymentSuccess(response) {
        hideError();
        
        // Verify payment on server
        $.post(data.ajaxUrl, {
            action: 'chatshop_verify_paystack_payment',
            reference: response.reference,
            order_id: data.orderNumber,
            nonce: data.nonce
        })
        .done(function(result) {
            if (result.success) {
                // Redirect to success page
                window.location.href = data.callbackUrl;
            } else {
                showError(result.data.message || data.strings.failed);
                setButtonState(false);
            }
        })
        .fail(function() {
            showError(data.strings.networkError);
            setButtonState(false);
        });
    }

    // Handle payment cancellation
    function handlePaymentCancellation() {
        showError(data.strings.cancelled);
        setButtonState(false);
    }

    // Handle payment error
    function handlePaymentError(error) {
        let message = data.strings.failed;
        
        if (error && error.message) {
            message = error.message;
        }
        
        showError(message);
        setButtonState(false);
    }

    // Process payment
    function processPayment() {
        hideError();
        setButtonState(true);

        initializePaystack()
            .then(() => {
                const handler = PaystackPop.setup({
                    key: data.publicKey,
                    email: data.email,
                    amount: data.amount,
                    currency: data.currency,
                    ref: data.reference,
                    channels: data.channels,
                    metadata: {
                        order_number: data.orderNumber,
                        customer_name: data.customerName,
                        source: 'chatshop'
                    },
                    callback: handlePaymentSuccess,
                    onClose: handlePaymentCancellation
                });

                handler.openIframe();
            })
            .catch(() => {
                showError(data.strings.invalidConfig);
                setButtonState(false);
            });
    }

    // Bind pay button click
    $payButton.on('click', processPayment);

    // Auto-focus pay button
    $payButton.focus();
});
</script>