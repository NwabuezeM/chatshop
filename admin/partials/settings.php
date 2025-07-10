<?php

/**
 * Settings page template
 *
 * @link       https://chatshop.com
 * @since      1.0.0
 *
 * @package    ChatShop
 * @subpackage ChatShop/admin/partials
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

// Get current settings
$settings = get_option('chatshop_settings', array());
$active_tab = $_GET['tab'] ?? 'general';
$is_premium = $this->is_premium_active();

// Define tabs
$tabs = array(
    'general' => __('General', 'chatshop'),
    'whatsapp' => __('WhatsApp API', 'chatshop'),
    'notifications' => __('Notifications', 'chatshop'),
    'gateways' => __('Payment Gateways', 'chatshop'),
    'advanced' => __('Advanced', 'chatshop'),
);

if (! $is_premium) {
    $tabs['premium'] = __('Premium', 'chatshop');
}
?>

<div class="wrap chatshop-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Settings Navigation -->
    <nav class="nav-tab-wrapper wp-clearfix">
        <?php foreach ($tabs as $tab_key => $tab_name): ?>
            <a href="?page=chatshop-settings&tab=<?php echo $tab_key; ?>"
                class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($tab_name); ?>
                <?php if ($tab_key === 'premium'): ?>
                    <span class="chatshop-premium-badge">Pro</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="options.php" id="chatshop-settings-form">
        <?php settings_fields('chatshop_settings'); ?>

        <div class="tab-content">
            <?php
            switch ($active_tab) {
                case 'general':
                    include_once 'settings-general.php';
                    break;
                case 'whatsapp':
                    include_once 'settings-whatsapp.php';
                    break;
                case 'notifications':
                    include_once 'settings-notifications.php';
                    break;
                case 'gateways':
                    include_once 'settings-gateways.php';
                    break;
                case 'advanced':
                    include_once 'settings-advanced.php';
                    break;
                case 'premium':
                    include_once 'settings-premium.php';
                    break;
            }
            ?>
        </div>

        <?php if ($active_tab !== 'premium'): ?>
            <?php submit_button(__('Save Settings', 'chatshop'), 'primary', 'submit', false, array('id' => 'chatshop-save-settings')); ?>
        <?php endif; ?>
    </form>
</div>

<!-- Test Connection Modal -->
<div id="test-connection-modal" class="chatshop-modal hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Test Connection', 'chatshop'); ?></h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="test-results"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="button" id="close-test-modal"><?php _e('Close', 'chatshop'); ?></button>
        </div>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Handle form submission via AJAX
        $('#chatshop-settings-form').on('submit', function(e) {
            e.preventDefault();
            saveSettings();
        });

        // Handle test connection buttons
        $('.test-connection').on('click', function(e) {
            e.preventDefault();
            testConnection($(this).data('type'));
        });

        // Handle modal close
        $('.modal-close, #close-test-modal').on('click', function() {
            $('#test-connection-modal').addClass('hidden');
        });

        // Handle field dependencies
        handleFieldDependencies();

        // Handle file uploads
        handleFileUploads();
    });

    function saveSettings() {
        const $form = $('#chatshop-settings-form');
        const $submitButton = $('#chatshop-save-settings');
        const originalText = $submitButton.val();

        $submitButton.prop('disabled', true).val('<?php _e('Saving...', 'chatshop'); ?>');

        const formData = new FormData($form[0]);
        formData.append('action', 'chatshop_ajax');
        formData.append('chatshop_action', 'save_settings');
        formData.append('nonce', chatshop_ajax.nonce);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $submitButton.prop('disabled', false).val(originalText);

                if (response.success) {
                    showNotice(response.data, 'success');
                } else {
                    showNotice(response.data, 'error');
                }
            },
            error: function() {
                $submitButton.prop('disabled', false).val(originalText);
                showNotice('<?php _e('An error occurred while saving settings.', 'chatshop'); ?>', 'error');
            }
        });
    }

    function testConnection(type) {
        const $modal = $('#test-connection-modal');
        const $results = $('#test-results');

        $modal.removeClass('hidden');
        $results.html('<div class="spinner is-active"></div><p><?php _e('Testing connection...', 'chatshop'); ?></p>');

        const data = {
            action: 'chatshop_ajax',
            chatshop_action: 'test_connection',
            connection_type: type,
            nonce: chatshop_ajax.nonce
        };

        // Add specific data based on connection type
        if (type === 'whatsapp') {
            data.api_key = $('#whatsapp_api_key').val();
            data.phone_number = $('#whatsapp_phone_number').val();
        } else if (type === 'payment') {
            data.gateway = $('.gateway-tab.active').data('gateway');
            data.api_key = $('.gateway-tab.active .api-key-field').val();
        }

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                $results.html('<div class="notice notice-success inline"><p><span class="dashicons dashicons-yes-alt"></span> ' + response.data + '</p></div>');
            } else {
                $results.html('<div class="notice notice-error inline"><p><span class="dashicons dashicons-no-alt"></span> ' + response.data + '</p></div>');
            }
        }).fail(function() {
            $results.html('<div class="notice notice-error inline"><p><span class="dashicons dashicons-no-alt"></span> <?php _e('Connection test failed.', 'chatshop'); ?></p></div>');
        });
    }

    function handleFieldDependencies() {
        const $ = jQuery;

        // Enable/disable fields based on checkboxes
        $('input[type="checkbox"]').on('change', function() {
            const $checkbox = $(this);
            const targetSelector = $checkbox.data('toggle-target');

            if (targetSelector) {
                const $target = $(targetSelector);
                if ($checkbox.is(':checked')) {
                    $target.prop('disabled', false).closest('tr').removeClass('disabled');
                } else {
                    $target.prop('disabled', true).closest('tr').addClass('disabled');
                }
            }
        }).trigger('change');

        // Show/hide sections based on select values
        $('select[data-toggle-section]').on('change', function() {
            const $select = $(this);
            const targetSelector = $select.data('toggle-section');
            const showValues = $select.data('show-values').split(',');

            if (targetSelector) {
                const $target = $(targetSelector);
                if (showValues.includes($select.val())) {
                    $target.show();
                } else {
                    $target.hide();
                }
            }
        }).trigger('change');
    }

    function handleFileUploads() {
        const $ = jQuery;

        $('.upload-button').on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const $input = $button.siblings('input[type="text"]');
            const $preview = $button.siblings('.file-preview');

            const frame = wp.media({
                title: $button.data('title') || '<?php _e('Select File', 'chatshop'); ?>',
                button: {
                    text: $button.data('button-text') || '<?php _e('Use this file', 'chatshop'); ?>'
                },
                multiple: false,
                library: {
                    type: $button.data('media-type') || 'image'
                }
            });

            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                $input.val(attachment.url);

                if ($preview.length) {
                    if (attachment.type === 'image') {
                        $preview.html('<img src="' + attachment.url + '" style="max-width: 100px; max-height: 100px;">');
                    } else {
                        $preview.html('<a href="' + attachment.url + '" target="_blank">' + attachment.filename + '</a>');
                    }
                }
            });

            frame.open();
        });

        $('.remove-file').on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const $input = $button.siblings('input[type="text"]');
            const $preview = $button.siblings('.file-preview');

            $input.val('');
            $preview.empty();
        });
    }

    function showNotice(message, type) {
        const $ = jQuery;
        const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after(notice);

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 5000);

        // Handle manual dismiss
        notice.on('click', '.notice-dismiss', function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        });
    }

    // Color picker initialization
    jQuery(document).ready(function($) {
        if ($.fn.wpColorPicker) {
            $('.color-picker').wpColorPicker();
        }
    });
</script>

<style>
    .chatshop-settings .form-table {
        margin-top: 20px;
    }

    .chatshop-settings .form-table th {
        width: 200px;
        padding: 15px 10px 15px 0;
    }

    .chatshop-settings .form-table td {
        padding: 15px 10px;
    }

    .chatshop-settings .description {
        color: #666;
        font-style: italic;
        margin-top: 5px;
    }

    .chatshop-premium-badge {
        background: linear-gradient(45deg, #ff6b6b, #feca57);
        color: white;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 10px;
        font-weight: bold;
        margin-left: 5px;
    }

    .chatshop-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 100000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .chatshop-modal.hidden {
        display: none;
    }

    .chatshop-modal .modal-content {
        background: white;
        border-radius: 4px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
    }

    .chatshop-modal .modal-header {
        padding: 20px;
        border-bottom: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .chatshop-modal .modal-header h3 {
        margin: 0;
    }

    .chatshop-modal .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #666;
    }

    .chatshop-modal .modal-body {
        padding: 20px;
    }

    .chatshop-modal .modal-footer {
        padding: 20px;
        border-top: 1px solid #ddd;
        text-align: right;
    }

    .form-table tr.disabled {
        opacity: 0.5;
    }

    .file-preview {
        margin-top: 10px;
    }

    .file-preview img {
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .upload-button,
    .remove-file {
        margin-left: 10px;
    }

    .section-description {
        background: #f9f9f9;
        border-left: 4px solid #2196F3;
        padding: 15px;
        margin: 20px 0;
    }

    .section-description h4 {
        margin-top: 0;
        color: #2196F3;
    }

    .gateway-tabs {
        border-bottom: 1px solid #ccd0d4;
        margin-bottom: 20px;
    }

    .gateway-tab {
        display: inline-block;
        padding: 10px 15px;
        background: #f1f1f1;
        border: 1px solid #ccd0d4;
        border-bottom: none;
        cursor: pointer;
        margin-right: 5px;
        border-radius: 4px 4px 0 0;
    }

    .gateway-tab.active {
        background: white;
        border-bottom: 1px solid white;
        margin-bottom: -1px;
    }

    .gateway-content {
        display: none;
        padding: 20px;
        border: 1px solid #ccd0d4;
        border-top: none;
        background: white;
    }

    .gateway-content.active {
        display: block;
    }

    .status-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 5px;
    }

    .status-indicator.connected {
        background: #4CAF50;
    }

    .status-indicator.disconnected {
        background: #f44336;
    }

    .status-indicator.testing {
        background: #ff9800;
    }

    .test-connection {
        margin-left: 10px;
    }

    .feature-comparison {
        margin-top: 20px;
    }

    .feature-comparison table {
        width: 100%;
        border-collapse: collapse;
    }

    .feature-comparison th,
    .feature-comparison td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    .feature-comparison th {
        background: #f9f9f9;
        font-weight: 600;
    }

    .feature-comparison .checkmark {
        color: #4CAF50;
        font-weight: bold;
    }

    .feature-comparison .cross {
        color: #f44336;
        font-weight: bold;
    }

    .pricing-card {
        border: 2px solid #2196F3;
        border-radius: 8px;
        padding: 30px;
        text-align: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        margin: 20px 0;
    }

    .pricing-card h3 {
        color: white;
        margin-top: 0;
    }

    .pricing-card .price {
        font-size: 48px;
        font-weight: bold;
        margin: 20px 0;
    }

    .pricing-card .price small {
        font-size: 16px;
        opacity: 0.8;
    }

    .pricing-card ul {
        list-style: none;
        padding: 0;
        margin: 20px 0;
    }

    .pricing-card li {
        padding: 5px 0;
    }

    .pricing-card .button {
        background: white;
        color: #2196F3;
        border: none;
        padding: 15px 30px;
        font-size: 16px;
        font-weight: bold;
        border-radius: 4px;
        text-decoration: none;
        display: inline-block;
        margin-top: 20px;
    }
</style>