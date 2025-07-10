<?php

/**
 * Payment Settings Page
 *
 * This file is used to display the payment settings page in the admin area.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    ChatShop
 * @subpackage ChatShop/admin/partials
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

// Get all registered payment gateways
$payment_manager = ChatShop_Payment_Manager::get_instance();
$registered_gateways = $payment_manager->get_registered_gateways();
$active_gateways = get_option('chatshop_active_payment_gateways', array());

// Get general payment settings
$payment_settings = get_option('chatshop_payment_settings', array());
$test_mode = isset($payment_settings['test_mode']) ? $payment_settings['test_mode'] : false;
$debug_mode = isset($payment_settings['debug_mode']) ? $payment_settings['debug_mode'] : false;
$auto_capture = isset($payment_settings['auto_capture']) ? $payment_settings['auto_capture'] : true;
$payment_link_expiry = isset($payment_settings['payment_link_expiry']) ? $payment_settings['payment_link_expiry'] : 24;
$currency = isset($payment_settings['currency']) ? $payment_settings['currency'] : 'USD';
$multi_currency = isset($payment_settings['multi_currency']) ? $payment_settings['multi_currency'] : false;

// Get security settings
$security_settings = get_option('chatshop_payment_security', array());
$enable_fraud_check = isset($security_settings['enable_fraud_check']) ? $security_settings['enable_fraud_check'] : true;
$require_3ds = isset($security_settings['require_3ds']) ? $security_settings['require_3ds'] : false;
$encryption_enabled = isset($security_settings['encryption_enabled']) ? $security_settings['encryption_enabled'] : true;

// Available currencies
$currencies = ChatShop_Helper::get_supported_currencies();
?>

<div class="wrap chatshop-payment-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if (isset($_GET['settings-updated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Payment settings saved successfully.', 'chatshop'); ?></p>
        </div>
    <?php endif; ?>

    <div class="chatshop-settings-container">
        <!-- Tab Navigation -->
        <nav class="nav-tab-wrapper chatshop-nav-tab-wrapper">
            <a href="#general" class="nav-tab nav-tab-active" data-tab="general">
                <span class="dashicons dashicons-admin-generic"></span>
                <?php esc_html_e('General Settings', 'chatshop'); ?>
            </a>
            <a href="#gateways" class="nav-tab" data-tab="gateways">
                <span class="dashicons dashicons-cart"></span>
                <?php esc_html_e('Payment Gateways', 'chatshop'); ?>
            </a>
            <a href="#payment-links" class="nav-tab" data-tab="payment-links">
                <span class="dashicons dashicons-admin-links"></span>
                <?php esc_html_e('Payment Links', 'chatshop'); ?>
            </a>
            <a href="#security" class="nav-tab" data-tab="security">
                <span class="dashicons dashicons-shield"></span>
                <?php esc_html_e('Security', 'chatshop'); ?>
            </a>
            <a href="#webhooks" class="nav-tab" data-tab="webhooks">
                <span class="dashicons dashicons-rest-api"></span>
                <?php esc_html_e('Webhooks', 'chatshop'); ?>
            </a>
            <a href="#logs" class="nav-tab" data-tab="logs">
                <span class="dashicons dashicons-text-page"></span>
                <?php esc_html_e('Transaction Logs', 'chatshop'); ?>
            </a>
        </nav>

        <form method="post" action="options.php" class="chatshop-settings-form">
            <?php settings_fields('chatshop_payment_settings_group'); ?>

            <!-- General Settings Tab -->
            <div id="general-tab" class="tab-content active">
                <div class="chatshop-settings-section">
                    <h2><?php esc_html_e('General Payment Settings', 'chatshop'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="test_mode"><?php esc_html_e('Test Mode', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <label class="chatshop-toggle">
                                    <input type="checkbox"
                                        id="test_mode"
                                        name="chatshop_payment_settings[test_mode]"
                                        value="1"
                                        <?php checked($test_mode, true); ?> />
                                    <span class="chatshop-toggle-slider"></span>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Enable test mode to use sandbox environments for all payment gateways.', 'chatshop'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="debug_mode"><?php esc_html_e('Debug Mode', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <label class="chatshop-toggle">
                                    <input type="checkbox"
                                        id="debug_mode"
                                        name="chatshop_payment_settings[debug_mode]"
                                        value="1"
                                        <?php checked($debug_mode, true); ?> />
                                    <span class="chatshop-toggle-slider"></span>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Enable detailed logging for debugging payment issues.', 'chatshop'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="currency"><?php esc_html_e('Default Currency', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <select id="currency" name="chatshop_payment_settings[currency]" class="regular-text">
                                    <?php foreach ($currencies as $code => $name) : ?>
                                        <option value="<?php echo esc_attr($code); ?>" <?php selected($currency, $code); ?>>
                                            <?php echo esc_html($code . ' - ' . $name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Default currency for payment transactions.', 'chatshop'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="multi_currency"><?php esc_html_e('Multi-Currency Support', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <label class="chatshop-toggle">
                                    <input type="checkbox"
                                        id="multi_currency"
                                        name="chatshop_payment_settings[multi_currency]"
                                        value="1"
                                        <?php checked($multi_currency, true); ?> />
                                    <span class="chatshop-toggle-slider"></span>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Allow customers to pay in their preferred currency.', 'chatshop'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="auto_capture"><?php esc_html_e('Auto Capture Payments', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <label class="chatshop-toggle">
                                    <input type="checkbox"
                                        id="auto_capture"
                                        name="chatshop_payment_settings[auto_capture]"
                                        value="1"
                                        <?php checked($auto_capture, true); ?> />
                                    <span class="chatshop-toggle-slider"></span>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Automatically capture authorized payments.', 'chatshop'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="payment_link_expiry"><?php esc_html_e('Payment Link Expiry', 'chatshop'); ?></label>
                            </th>
                            <td>
                                <input type="number"
                                    id="payment_link_expiry"
                                    name="chatshop_payment_settings[payment_link_expiry]"
                                    value="<?php echo esc_attr($payment_link_expiry); ?>"
                                    min="1"
                                    max="720"
                                    class="small-text" />
                                <span><?php esc_html_e('hours', 'chatshop'); ?></span>
                                <p class="description">
                                    <?php esc_html_e('How long payment links remain valid (1-720 hours).', 'chatshop'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Payment Gateways Tab -->
            <div id="gateways-tab" class="tab-content">
                <div class="chatshop-settings-section">
                    <h2><?php esc_html_e('Payment Gateway Configuration', 'chatshop'); ?></h2>

                    <div class="chatshop-gateways-grid">
                        <?php foreach ($registered_gateways as $gateway_id => $gateway) :
                            $is_active = in_array($gateway_id, $active_gateways);
                            $gateway_settings = get_option('chatshop_gateway_' . $gateway_id, array());
                        ?>
                            <div class="chatshop-gateway-card <?php echo $is_active ? 'active' : ''; ?>" data-gateway="<?php echo esc_attr($gateway_id); ?>">
                                <div class="gateway-header">
                                    <div class="gateway-info">
                                        <?php if (! empty($gateway['icon'])) : ?>
                                            <img src="<?php echo esc_url($gateway['icon']); ?>"
                                                alt="<?php echo esc_attr($gateway['name']); ?>"
                                                class="gateway-icon" />
                                        <?php endif; ?>
                                        <div>
                                            <h3><?php echo esc_html($gateway['name']); ?></h3>
                                            <p class="gateway-description"><?php echo esc_html($gateway['description']); ?></p>
                                        </div>
                                    </div>
                                    <label class="chatshop-toggle">
                                        <input type="checkbox"
                                            name="chatshop_active_payment_gateways[]"
                                            value="<?php echo esc_attr($gateway_id); ?>"
                                            <?php checked($is_active); ?>
                                            class="gateway-toggle" />
                                        <span class="chatshop-toggle-slider"></span>
                                    </label>
                                </div>

                                <div class="gateway-settings" style="display: <?php echo $is_active ? 'block' : 'none'; ?>;">
                                    <button type="button" class="button button-secondary gateway-configure-btn" data-gateway="<?php echo esc_attr($gateway_id); ?>">
                                        <span class="dashicons dashicons-admin-generic"></span>
                                        <?php esc_html_e('Configure', 'chatshop'); ?>
                                    </button>

                                    <?php if ($is_active) : ?>
                                        <span class="gateway-status">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <?php esc_html_e('Active', 'chatshop'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="chatshop-gateway-notice">
                        <p>
                            <span class="dashicons dashicons-info"></span>
                            <?php esc_html_e('Enable payment gateways and configure their settings. Each gateway requires valid API credentials to function properly.', 'chatshop'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Payment Links Tab -->
            <div id="payment-links-tab" class="tab-content">
                <?php include dirname(__FILE__) . '/settings-payment-links.php'; ?>
            </div>

            <!-- Security Tab -->
            <div id="security-tab" class="tab-content">
                <?php include dirname(__FILE__) . '/settings-payment-security.php'; ?>
            </div>

            <!-- Webhooks Tab -->
            <div id="webhooks-tab" class="tab-content">
                <?php include dirname(__FILE__) . '/settings-payment-webhooks.php'; ?>
            </div>

            <!-- Transaction Logs Tab -->
            <div id="logs-tab" class="tab-content">
                <?php include dirname(__FILE__) . '/settings-payment-logs.php'; ?>
            </div>

            <?php submit_button(esc_html__('Save Payment Settings', 'chatshop'), 'primary', 'submit', true, array('id' => 'chatshop-save-payment-settings')); ?>
        </form>
    </div>
</div>

<!-- Gateway Configuration Modal -->
<div id="chatshop-gateway-modal" class="chatshop-modal" style="display: none;">
    <div class="chatshop-modal-content">
        <span class="chatshop-modal-close">&times;</span>
        <div class="chatshop-modal-header">
            <h2 id="gateway-modal-title"><?php esc_html_e('Configure Gateway', 'chatshop'); ?></h2>
        </div>
        <div class="chatshop-modal-body">
            <!-- Gateway-specific settings will be loaded here via AJAX -->
        </div>
    </div>
</div>

<style>
    .chatshop-payment-settings {
        max-width: 1200px;
        margin: 20px auto;
    }

    .chatshop-settings-container {
        background: #fff;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
        margin-top: 20px;
    }

    .chatshop-nav-tab-wrapper {
        margin: 0;
        padding: 0;
        border-bottom: 1px solid #ccd0d4;
        background: #fafafa;
    }

    .chatshop-nav-tab-wrapper .nav-tab {
        margin: 0;
        padding: 10px 20px;
        font-size: 14px;
        border: none;
        border-right: 1px solid #ccd0d4;
        background: transparent;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .chatshop-nav-tab-wrapper .nav-tab:hover {
        background: #fff;
    }

    .chatshop-nav-tab-wrapper .nav-tab-active {
        background: #fff;
        border-bottom: 1px solid #fff;
        margin-bottom: -1px;
    }

    .tab-content {
        display: none;
        padding: 20px;
    }

    .tab-content.active {
        display: block;
    }

    .chatshop-settings-section {
        margin-bottom: 30px;
    }

    .chatshop-settings-section h2 {
        margin-top: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #e5e5e5;
    }

    /* Toggle Switch Styles */
    .chatshop-toggle {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
    }

    .chatshop-toggle input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .chatshop-toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 24px;
    }

    .chatshop-toggle-slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    .chatshop-toggle input:checked+.chatshop-toggle-slider {
        background-color: #2271b1;
    }

    .chatshop-toggle input:checked+.chatshop-toggle-slider:before {
        transform: translateX(26px);
    }

    /* Gateway Grid Styles */
    .chatshop-gateways-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }

    .chatshop-gateway-card {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 20px;
        background: #f9f9f9;
        transition: all 0.3s ease;
    }

    .chatshop-gateway-card.active {
        background: #fff;
        border-color: #2271b1;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .gateway-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .gateway-info {
        display: flex;
        gap: 15px;
        flex: 1;
    }

    .gateway-icon {
        width: 48px;
        height: 48px;
        object-fit: contain;
    }

    .gateway-info h3 {
        margin: 0 0 5px 0;
        font-size: 16px;
    }

    .gateway-description {
        margin: 0;
        color: #666;
        font-size: 13px;
    }

    .gateway-settings {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e5e5e5;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .gateway-configure-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .gateway-status {
        margin-left: auto;
        color: #008a20;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 13px;
    }

    .chatshop-gateway-notice {
        background: #f0f6fc;
        border-left: 4px solid #2271b1;
        padding: 12px;
        margin-top: 20px;
    }

    .chatshop-gateway-notice p {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Modal Styles */
    .chatshop-modal {
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .chatshop-modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 0;
        border: 1px solid #888;
        width: 80%;
        max-width: 600px;
        border-radius: 4px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .chatshop-modal-header {
        padding: 20px;
        border-bottom: 1px solid #e5e5e5;
        background: #f9f9f9;
    }

    .chatshop-modal-header h2 {
        margin: 0;
    }

    .chatshop-modal-body {
        padding: 20px;
        max-height: 60vh;
        overflow-y: auto;
    }

    .chatshop-modal-close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        margin: 10px 15px;
        cursor: pointer;
    }

    .chatshop-modal-close:hover,
    .chatshop-modal-close:focus {
        color: #000;
    }

    /* Responsive adjustments */
    @media screen and (max-width: 782px) {
        .chatshop-gateways-grid {
            grid-template-columns: 1fr;
        }

        .chatshop-nav-tab-wrapper .nav-tab {
            font-size: 12px;
            padding: 8px 12px;
        }

        .chatshop-nav-tab-wrapper .nav-tab .dashicons {
            font-size: 16px;
        }
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Tab navigation
        $('.chatshop-nav-tab-wrapper .nav-tab').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');

            // Update active tab
            $('.chatshop-nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            // Show corresponding content
            $('.tab-content').removeClass('active');
            $('#' + tab + '-tab').addClass('active');

            // Update URL hash
            window.location.hash = tab;
        });

        // Load tab from hash
        if (window.location.hash) {
            var hash = window.location.hash.substring(1);
            $('.chatshop-nav-tab-wrapper .nav-tab[data-tab="' + hash + '"]').trigger('click');
        }

        // Gateway toggle
        $('.gateway-toggle').on('change', function() {
            var $card = $(this).closest('.chatshop-gateway-card');
            var $settings = $card.find('.gateway-settings');

            if ($(this).is(':checked')) {
                $card.addClass('active');
                $settings.slideDown();
            } else {
                $card.removeClass('active');
                $settings.slideUp();
            }
        });

        // Gateway configuration modal
        $('.gateway-configure-btn').on('click', function() {
            var gateway = $(this).data('gateway');
            var $modal = $('#chatshop-gateway-modal');
            var $modalBody = $modal.find('.chatshop-modal-body');

            // Show loading
            $modalBody.html('<p>Loading gateway settings...</p>');
            $modal.fadeIn();

            // Load gateway settings via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'chatshop_load_gateway_settings',
                    gateway: gateway,
                    nonce: '<?php echo wp_create_nonce('chatshop_gateway_settings'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $modalBody.html(response.data.html);
                        $('#gateway-modal-title').text(response.data.title);
                    } else {
                        $modalBody.html('<p class="error">Error loading gateway settings.</p>');
                    }
                },
                error: function() {
                    $modalBody.html('<p class="error">Error loading gateway settings.</p>');
                }
            });
        });

        // Close modal
        $('.chatshop-modal-close').on('click', function() {
            $('#chatshop-gateway-modal').fadeOut();
        });

        // Close modal on outside click
        $(window).on('click', function(e) {
            if ($(e.target).hasClass('chatshop-modal')) {
                $('#chatshop-gateway-modal').fadeOut();
            }
        });

        // Save gateway settings via AJAX
        $(document).on('submit', '#chatshop-gateway-settings-form', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.text();

            // Show loading state
            $submitBtn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        $form.prepend('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');

                        // Close modal after delay
                        setTimeout(function() {
                            $('#chatshop-gateway-modal').fadeOut();
                        }, 1500);
                    } else {
                        $form.prepend('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $form.prepend('<div class="notice notice-error"><p>Error saving settings.</p></div>');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);

                    // Remove notices after delay
                    setTimeout(function() {
                        $form.find('.notice').fadeOut();
                    }, 3000);
                }
            });
        });
    });
</script>