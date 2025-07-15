<?php
/**
 * Paystack Admin Settings Template
 *
 * File: components/payment/gateways/paystack/partials/admin-settings.php
 * 
 * @package ChatShop
 * @subpackage Payment\Gateways\Paystack
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$settings = get_option('woocommerce_chatshop_paystack_settings', []);
$test_mode = ($settings['testmode'] ?? 'yes') === 'yes';
$enabled = ($settings['enabled'] ?? 'no') === 'yes';

// Get gateway instance for validation
$gateways = WC()->payment_gateways->payment_gateways();
$gateway = $gateways['chatshop_paystack'] ?? null;

// Check API credentials
$public_key = $gateway ? $gateway->get_public_key() : '';
$secret_key = $gateway ? $gateway->get_secret_key() : '';
$credentials_configured = !empty($public_key) && !empty($secret_key);

// Check currency support
$current_currency = get_woocommerce_currency();
$currency_supported = in_array($current_currency, \ChatShop\Payment\Gateways\Paystack\ChatShop_Paystack_Gateway::SUPPORTED_CURRENCIES);

// Get webhook URL
$webhook_url = $gateway ? $gateway->get_webhook_url() : '';
?>

<div class="chatshop-paystack-settings">
    <h2><?php esc_html_e('Paystack Payment Gateway', 'chatshop'); ?></h2>
    
    <!-- Status Overview -->
    <div class="chatshop-status-overview">
        <h3><?php esc_html_e('Configuration Status', 'chatshop'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e('Gateway Status', 'chatshop'); ?></strong></td>
                    <td>
                        <?php if ($enabled): ?>
                            <span class="chatshop-status-enabled">✓ <?php esc_html_e('Enabled', 'chatshop'); ?></span>
                        <?php else: ?>
                            <span class="chatshop-status-disabled">✗ <?php esc_html_e('Disabled', 'chatshop'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('API Credentials', 'chatshop'); ?></strong></td>
                    <td>
                        <?php if ($credentials_configured): ?>
                            <span class="chatshop-status-enabled">✓ <?php esc_html_e('Configured', 'chatshop'); ?></span>
                        <?php else: ?>
                            <span class="chatshop-status-disabled">✗ <?php esc_html_e('Missing', 'chatshop'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Currency Support', 'chatshop'); ?></strong></td>
                    <td>
                        <?php if ($currency_supported): ?>
                            <span class="chatshop-status-enabled">✓ <?php echo esc_html($current_currency); ?> <?php esc_html_e('Supported', 'chatshop'); ?></span>
                        <?php else: ?>
                            <span class="chatshop-status-disabled">✗ <?php echo esc_html($current_currency); ?> <?php esc_html_e('Not Supported', 'chatshop'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Mode', 'chatshop'); ?></strong></td>
                    <td>
                        <?php if ($test_mode): ?>
                            <span class="chatshop-status-test"><?php esc_html_e('Test Mode', 'chatshop'); ?></span>
                        <?php else: ?>
                            <span class="chatshop-status-live"><?php esc_html_e('Live Mode', 'chatshop'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Quick Setup Guide -->
    <?php if (!$credentials_configured): ?>
    <div class="chatshop-setup-guide notice notice-info">
        <h3><?php esc_html_e('Quick Setup Guide', 'chatshop'); ?></h3>
        <ol>
            <li>
                <strong><?php esc_html_e('Create Paystack Account', 'chatshop'); ?></strong><br>
                <?php esc_html_e('Sign up at', 'chatshop'); ?> 
                <a href="https://paystack.com" target="_blank">paystack.com</a>
            </li>
            <li>
                <strong><?php esc_html_e('Get API Keys', 'chatshop'); ?></strong><br>
                <?php esc_html_e('Navigate to Settings > API Keys in your Paystack dashboard', 'chatshop'); ?>
            </li>
            <li>
                <strong><?php esc_html_e('Configure Webhook', 'chatshop'); ?></strong><br>
                <?php esc_html_e('Add this webhook URL to your Paystack dashboard:', 'chatshop'); ?><br>
                <code><?php echo esc_url($webhook_url); ?></code>
            </li>
            <li>
                <strong><?php esc_html_e('Test Payment', 'chatshop'); ?></strong><br>
                <?php esc_html_e('Use test mode to verify everything works correctly', 'chatshop'); ?>
            </li>
        </ol>
    </div>
    <?php endif; ?>

    <!-- Webhook Configuration -->
    <div class="chatshop-webhook-info">
        <h3><?php esc_html_e('Webhook Configuration', 'chatshop'); ?></h3>
        <p><?php esc_html_e('Add this URL to your Paystack webhook settings to receive payment notifications:', 'chatshop'); ?></p>
        <div class="chatshop-webhook-url">
            <input type="text" value="<?php echo esc_url($webhook_url); ?>" readonly class="large-text">
            <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($webhook_url); ?>')">
                <?php esc_html_e('Copy', 'chatshop'); ?>
            </button>
        </div>
        <p class="description">
            <?php esc_html_e('Events to enable:', 'chatshop'); ?>
            <code>charge.success</code>, <code>charge.failed</code>, <code>charge.dispute.create</code>, <code>refund.processed</code>
        </p>
    </div>

    <!-- Supported Currencies -->
    <div class="chatshop-currency-info">
        <h3><?php esc_html_e('Supported Currencies', 'chatshop'); ?></h3>
        <div class="chatshop-currency-list">
            <?php foreach (\ChatShop\Payment\Gateways\Paystack\ChatShop_Paystack_Gateway::SUPPORTED_CURRENCIES as $currency): ?>
                <span class="chatshop-currency-item <?php echo $currency === $current_currency ? 'current' : ''; ?>">
                    <?php echo esc_html($currency); ?>
                    <?php if ($currency === $current_currency): ?>
                        <span class="current-label"><?php esc_html_e('(Current)', 'chatshop'); ?></span>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Payment Channels -->
    <div class="chatshop-channels-info">
        <h3><?php esc_html_e('Available Payment Channels', 'chatshop'); ?></h3>
        <div class="chatshop-channels-grid">
            <div class="channel-item">
                <h4><?php esc_html_e('Cards', 'chatshop'); ?></h4>
                <p><?php esc_html_e('Visa, Mastercard, Verve', 'chatshop'); ?></p>
            </div>
            <div class="channel-item">
                <h4><?php esc_html_e('Bank Transfer', 'chatshop'); ?></h4>
                <p><?php esc_html_e('Direct bank transfers', 'chatshop'); ?></p>
            </div>
            <div class="channel-item">
                <h4><?php esc_html_e('USSD', 'chatshop'); ?></h4>
                <p><?php esc_html_e('Pay with mobile USSD', 'chatshop'); ?></p>
            </div>
            <div class="channel-item">
                <h4><?php esc_html_e('Mobile Money', 'chatshop'); ?></h4>
                <p><?php esc_html_e('Mobile wallet payments', 'chatshop'); ?></p>
            </div>
        </div>
    </div>

    <!-- Test Credentials -->
    <?php if ($test_mode): ?>
    <div class="chatshop-test-credentials">
        <h3><?php esc_html_e('Test Credentials', 'chatshop'); ?></h3>
        <p><?php esc_html_e('Use these test card details for testing:', 'chatshop'); ?></p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Card Number', 'chatshop'); ?></th>
                    <th><?php esc_html_e('CVV', 'chatshop'); ?></th>
                    <th><?php esc_html_e('Expiry', 'chatshop'); ?></th>
                    <th><?php esc_html_e('Result', 'chatshop'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>4084084084084081</code></td>
                    <td><code>408</code></td>
                    <td><code>12/30</code></td>
                    <td><?php esc_html_e('Successful', 'chatshop'); ?></td>
                </tr>
                <tr>
                    <td><code>4084084084084081</code></td>
                    <td><code>408</code></td>
                    <td><code>12/30</code></td>
                    <td><?php esc_html_e('Failed', 'chatshop'); ?></td>
                </tr>
                <tr>
                    <td><code>5060666666666666666</code></td>
                    <td><code>123</code></td>
                    <td><code>12/30</code></td>
                    <td><?php esc_html_e('Successful', 'chatshop'); ?></td>
                </tr>
            </tbody>
        </table>
        <p class="description">
            <?php esc_html_e('OTP for test transactions: 123456', 'chatshop'); ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Troubleshooting -->
    <div class="chatshop-troubleshooting">
        <h3><?php esc_html_e('Troubleshooting', 'chatshop'); ?></h3>
        <div class="chatshop-accordion">
            <div class="accordion-item">
                <h4 class="accordion-header"><?php esc_html_e('Payment not completing', 'chatshop'); ?></h4>
                <div class="accordion-content">
                    <ul>
                        <li><?php esc_html_e('Verify API keys are correct', 'chatshop'); ?></li>
                        <li><?php esc_html_e('Check webhook URL is configured', 'chatshop'); ?></li>
                        <li><?php esc_html_e('Ensure SSL is enabled on your site', 'chatshop'); ?></li>
                        <li><?php esc_html_e('Check payment logs for errors', 'chatshop'); ?></li>
                    </ul>
                </div>
            </div>
            <div class="accordion-item">
                <h4 class="accordion-header"><?php esc_html_e('Currency not supported error', 'chatshop'); ?></h4>
                <div class="accordion-content">
                    <ul>
                        <li><?php esc_html_e('Change your store currency to a supported one', 'chatshop'); ?></li>
                        <li><?php esc_html_e('Supported currencies: NGN, USD, GHS, ZAR, KES, XOF, EGP', 'chatshop'); ?></li>
                    </ul>
                </div>
            </div>
            <div class="accordion-item">
                <h4 class="accordion-header"><?php esc_html_e('Webhook not receiving notifications', 'chatshop'); ?></h4>
                <div class="accordion-content">
                    <ul>
                        <li><?php esc_html_e('Verify webhook URL in Paystack dashboard', 'chatshop'); ?></li>
                        <li><?php esc_html_e('Check if your server can receive external requests', 'chatshop'); ?></li>
                        <li><?php esc_html_e('Ensure WordPress REST API is functioning', 'chatshop'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- API Connection Test -->
    <div class="chatshop-api-test">
        <h3><?php esc_html_e('API Connection Test', 'chatshop'); ?></h3>
        <button type="button" class="button button-secondary" id="chatshop-test-api">
            <?php esc_html_e('Test Connection', 'chatshop'); ?>
        </button>
        <div id="chatshop-api-test-result" style="margin-top: 10px;"></div>
    </div>

    <!-- Documentation Links -->
    <div class="chatshop-documentation">
        <h3><?php esc_html_e('Documentation & Support', 'chatshop'); ?></h3>
        <div class="chatshop-doc-links">
            <a href="https://paystack.com/docs" target="_blank" class="button button-secondary">
                <?php esc_html_e('Paystack Documentation', 'chatshop'); ?>
            </a>
            <a href="https://paystack.com/docs/payments/webhooks" target="_blank" class="button button-secondary">
                <?php esc_html_e('Webhook Setup Guide', 'chatshop'); ?>
            </a>
            <a href="https://paystack.com/docs/api" target="_blank" class="button button-secondary">
                <?php esc_html_e('API Reference', 'chatshop'); ?>
            </a>
        </div>
    </div>
</div>

<style>
.chatshop-paystack-settings h2 {
    margin-bottom: 20px;
}

.chatshop-status-overview {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
}

.chatshop-status-enabled {
    color: #46b450;
    font-weight: bold;
}

.chatshop-status-disabled {
    color: #dc3232;
    font-weight: bold;
}

.chatshop-status-test {
    color: #ffb900;
    font-weight: bold;
}

.chatshop-status-live {
    color: #46b450;
    font-weight: bold;
}

.chatshop-setup-guide {
    padding: 15px;
    margin-bottom: 20px;
}

.chatshop-webhook-info,
.chatshop-currency-info,
.chatshop-channels-info,
.chatshop-test-credentials,
.chatshop-troubleshooting,
.chatshop-api-test,
.chatshop-documentation {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
}

.chatshop-webhook-url {
    display: flex;
    gap: 10px;
    align-items: center;
    margin: 10px 0;
}

.chatshop-currency-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.chatshop-currency-item {
    background: #f7f7f7;
    padding: 5px 10px;
    border-radius: 3px;
    border: 1px solid #ddd;
}

.chatshop-currency-item.current {
    background: #e7f3ff;
    border-color: #0073aa;
}

.current-label {
    font-size: 0.9em;
    color: #0073aa;
}

.chatshop-channels-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.channel-item {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #e5e5e5;
}

.channel-item h4 {
    margin: 0 0 5px 0;
    color: #0073aa;
}

.channel-item p {
    margin: 0;
    font-size: 0.9em;
    color: #666;
}

.chatshop-accordion .accordion-item {
    border: 1px solid #ddd;
    margin-bottom: 5px;
    border-radius: 3px;
}

.accordion-header {
    background: #f7f7f7;
    padding: 10px 15px;
    margin: 0;
    cursor: pointer;
    border-bottom: 1px solid #ddd;
}

.accordion-header:hover {
    background: #e7e7e7;
}

.accordion-content {
    padding: 15px;
    display: none;
}

.accordion-content ul {
    margin: 0;
    padding-left: 20px;
}

.chatshop-doc-links {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

#chatshop-api-test-result.success {
    color: #46b450;
    font-weight: bold;
}

#chatshop-api-test-result.error {
    color: #dc3232;
    font-weight: bold;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Accordion functionality
    $('.accordion-header').click(function() {
        $(this).next('.accordion-content').slideToggle();
    });

    // API connection test
    $('#chatshop-test-api').click(function() {
        var $button = $(this);
        var $result = $('#chatshop-api-test-result');
        
        $button.prop('disabled', true).text('<?php esc_js_e('Testing...', 'chatshop'); ?>');
        $result.removeClass('success error').text('');

        $.post(ajaxurl, {
            action: 'chatshop_test_paystack_connection',
            nonce: '<?php echo wp_create_nonce('chatshop_paystack_test'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                $result.addClass('success').text(response.data.message);
            } else {
                $result.addClass('error').text(response.data.message || '<?php esc_js_e('Connection failed', 'chatshop'); ?>');
            }
        })
        .fail(function() {
            $result.addClass('error').text('<?php esc_js_e('Request failed', 'chatshop'); ?>');
        })
        .always(function() {
            $button.prop('disabled', false).text('<?php esc_js_e('Test Connection', 'chatshop'); ?>');
        });
    });

    // Copy webhook URL functionality
    $('.chatshop-webhook-url button').click(function() {
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text('<?php esc_js_e('Copied!', 'chatshop'); ?>');
        setTimeout(function() {
            $button.text(originalText);
        }, 2000);
    });
});
</script>