<?php

/**
 * Payment Links Settings Partial
 *
 * @package    ChatShop
 * @subpackage ChatShop/admin/partials
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

// Get payment link settings
$link_settings = get_option('chatshop_payment_link_settings', array());
$link_prefix = isset($link_settings['link_prefix']) ? $link_settings['link_prefix'] : 'pay';
$link_length = isset($link_settings['link_length']) ? $link_settings['link_length'] : 8;
$qr_enabled = isset($link_settings['qr_enabled']) ? $link_settings['qr_enabled'] : true;
$custom_branding = isset($link_settings['custom_branding']) ? $link_settings['custom_branding'] : false;
$redirect_url = isset($link_settings['redirect_url']) ? $link_settings['redirect_url'] : '';
$thank_you_message = isset($link_settings['thank_you_message']) ? $link_settings['thank_you_message'] : '';
$branding_logo = isset($link_settings['branding_logo']) ? $link_settings['branding_logo'] : '';
$branding_color = isset($link_settings['branding_color']) ? $link_settings['branding_color'] : '#2271b1';
$enable_notifications = isset($link_settings['enable_notifications']) ? $link_settings['enable_notifications'] : true;
$notification_email = isset($link_settings['notification_email']) ? $link_settings['notification_email'] : get_option('admin_email');
?>

<div class="chatshop-settings-section">
    <h2><?php esc_html_e('Payment Link Configuration', 'chatshop'); ?></h2>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="link_prefix"><?php esc_html_e('Link Prefix', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="text"
                    id="link_prefix"
                    name="chatshop_payment_link_settings[link_prefix]"
                    value="<?php echo esc_attr($link_prefix); ?>"
                    class="regular-text"
                    pattern="[a-z0-9-]+"
                    maxlength="20" />
                <p class="description">
                    <?php
                    /* translators: %s: Example payment link URL */
                    printf(
                        esc_html__('Payment links will be formatted as: %s', 'chatshop'),
                        '<code>' . esc_url(home_url('/' . $link_prefix . '/XXXXXXXX')) . '</code>'
                    );
                    ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="link_length"><?php esc_html_e('Link ID Length', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="number"
                    id="link_length"
                    name="chatshop_payment_link_settings[link_length]"
                    value="<?php echo esc_attr($link_length); ?>"
                    min="6"
                    max="16"
                    class="small-text" />
                <span><?php esc_html_e('characters', 'chatshop'); ?></span>
                <p class="description">
                    <?php esc_html_e('Length of the unique payment link identifier (6-16 characters).', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="qr_enabled"><?php esc_html_e('Enable QR Codes', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="chatshop-toggle">
                    <input type="checkbox"
                        id="qr_enabled"
                        name="chatshop_payment_link_settings[qr_enabled]"
                        value="1"
                        <?php checked($qr_enabled, true); ?> />
                    <span class="chatshop-toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Generate QR codes for payment links to enable easy mobile payments.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="enable_notifications"><?php esc_html_e('Payment Notifications', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="chatshop-toggle">
                    <input type="checkbox"
                        id="enable_notifications"
                        name="chatshop_payment_link_settings[enable_notifications]"
                        value="1"
                        <?php checked($enable_notifications, true); ?> />
                    <span class="chatshop-toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Send email notifications when payment links are used.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr class="notification-email-row" style="display: <?php echo $enable_notifications ? 'table-row' : 'none'; ?>;">
            <th scope="row">
                <label for="notification_email"><?php esc_html_e('Notification Email', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="email"
                    id="notification_email"
                    name="chatshop_payment_link_settings[notification_email]"
                    value="<?php echo esc_attr($notification_email); ?>"
                    class="regular-text" />
                <p class="description">
                    <?php esc_html_e('Email address to receive payment notifications.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="redirect_url"><?php esc_html_e('Success Redirect URL', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="url"
                    id="redirect_url"
                    name="chatshop_payment_link_settings[redirect_url]"
                    value="<?php echo esc_url($redirect_url); ?>"
                    class="large-text"
                    placeholder="<?php echo esc_url(home_url('/thank-you')); ?>" />
                <p class="description">
                    <?php esc_html_e('URL to redirect customers after successful payment. Leave empty to use default thank you page.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="thank_you_message"><?php esc_html_e('Thank You Message', 'chatshop'); ?></label>
            </th>
            <td>
                <?php
                wp_editor(
                    $thank_you_message,
                    'thank_you_message',
                    array(
                        'textarea_name' => 'chatshop_payment_link_settings[thank_you_message]',
                        'textarea_rows' => 5,
                        'media_buttons' => false,
                        'teeny' => true,
                        'quicktags' => false
                    )
                );
                ?>
                <p class="description">
                    <?php esc_html_e('Custom message to display after successful payment. Supports basic HTML.', 'chatshop'); ?>
                </p>
            </td>
        </tr>
    </table>

    <h3><?php esc_html_e('Payment Page Branding', 'chatshop'); ?></h3>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="custom_branding"><?php esc_html_e('Custom Branding', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="chatshop-toggle">
                    <input type="checkbox"
                        id="custom_branding"
                        name="chatshop_payment_link_settings[custom_branding]"
                        value="1"
                        <?php checked($custom_branding, true); ?> />
                    <span class="chatshop-toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Customize the appearance of payment pages with your brand.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr class="branding-row" style="display: <?php echo $custom_branding ? 'table-row' : 'none'; ?>;">
            <th scope="row">
                <label for="branding_logo"><?php esc_html_e('Brand Logo', 'chatshop'); ?></label>
            </th>
            <td>
                <div class="chatshop-media-upload">
                    <input type="hidden"
                        id="branding_logo"
                        name="chatshop_payment_link_settings[branding_logo]"
                        value="<?php echo esc_attr($branding_logo); ?>" />
                    <div class="logo-preview">
                        <?php if ($branding_logo) : ?>
                            <img src="<?php echo esc_url($branding_logo); ?>" alt="Brand Logo" />
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button upload-logo-btn">
                        <?php esc_html_e('Upload Logo', 'chatshop'); ?>
                    </button>
                    <button type="button" class="button remove-logo-btn" style="display: <?php echo $branding_logo ? 'inline-block' : 'none'; ?>;">
                        <?php esc_html_e('Remove', 'chatshop'); ?>
                    </button>
                </div>
                <p class="description">
                    <?php esc_html_e('Logo to display on payment pages. Recommended size: 200x60 pixels.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr class="branding-row" style="display: <?php echo $custom_branding ? 'table-row' : 'none'; ?>;">
            <th scope="row">
                <label for="branding_color"><?php esc_html_e('Brand Color', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="color"
                    id="branding_color"
                    name="chatshop_payment_link_settings[branding_color]"
                    value="<?php echo esc_attr($branding_color); ?>" />
                <p class="description">
                    <?php esc_html_e('Primary color for payment page elements.', 'chatshop'); ?>
                </p>
            </td>
        </tr>
    </table>
</div>

<div class="chatshop-settings-section">
    <h3><?php esc_html_e('Recent Payment Links', 'chatshop'); ?></h3>

    <div id="chatshop-recent-payment-links">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Link ID', 'chatshop'); ?></th>
                    <th><?php esc_html_e('Amount', 'chatshop'); ?></th>
                    <th><?php esc_html_e('Status', 'chatshop'); ?></th>
                    <th><?php esc_html_e('Created', 'chatshop'); ?></th>
                    <th><?php esc_html_e('Actions', 'chatshop'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Get recent payment links
                $payment_links = ChatShop_Payment_Link_Generator::get_recent_links(5);

                if (! empty($payment_links)) :
                    foreach ($payment_links as $link) :
                ?>
                        <tr>
                            <td>
                                <code><?php echo esc_html($link->link_id); ?></code>
                            </td>
                            <td>
                                <?php echo esc_html(ChatShop_Helper::format_price($link->amount, $link->currency)); ?>
                            </td>
                            <td>
                                <span class="chatshop-status chatshop-status-<?php echo esc_attr($link->status); ?>">
                                    <?php echo esc_html(ucfirst($link->status)); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo esc_html(ChatShop_Helper::format_date($link->created_at)); ?>
                            </td>
                            <td>
                                <button type="button"
                                    class="button button-small copy-link-btn"
                                    data-link="<?php echo esc_url(home_url('/' . $link_prefix . '/' . $link->link_id)); ?>">
                                    <?php esc_html_e('Copy Link', 'chatshop'); ?>
                                </button>
                                <?php if ($qr_enabled) : ?>
                                    <button type="button"
                                        class="button button-small show-qr-btn"
                                        data-link-id="<?php echo esc_attr($link->link_id); ?>">
                                        <?php esc_html_e('QR Code', 'chatshop'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php
                    endforeach;
                else :
                    ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">
                            <?php esc_html_e('No payment links created yet.', 'chatshop'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <p style="margin-top: 10px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-payment-links')); ?>" class="button">
                <?php esc_html_e('View All Payment Links', 'chatshop'); ?>
            </a>
            <button type="button" class="button button-primary" id="create-payment-link-btn">
                <?php esc_html_e('Create New Payment Link', 'chatshop'); ?>
            </button>
        </p>
    </div>
</div>

<style>
    .chatshop-media-upload {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .logo-preview {
        width: 200px;
        height: 60px;
        border: 1px dashed #ccc;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f9f9f9;
    }

    .logo-preview img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .chatshop-status {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 500;
    }

    .chatshop-status-active,
    .chatshop-status-paid {
        background: #d4f4dd;
        color: #00a32a;
    }

    .chatshop-status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .chatshop-status-expired,
    .chatshop-status-failed {
        background: #fecaca;
        color: #dc2626;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Toggle notification email field
        $('#enable_notifications').on('change', function() {
            if ($(this).is(':checked')) {
                $('.notification-email-row').show();
            } else {
                $('.notification-email-row').hide();
            }
        });

        // Toggle branding fields
        $('#custom_branding').on('change', function() {
            if ($(this).is(':checked')) {
                $('.branding-row').show();
            } else {
                $('.branding-row').hide();
            }
        });

        // Media upload
        $('.upload-logo-btn').on('click', function(e) {
            e.preventDefault();

            var button = $(this);
            var mediaUploader = wp.media({
                title: '<?php esc_html_e('Choose Logo', 'chatshop'); ?>',
                button: {
                    text: '<?php esc_html_e('Use this logo', 'chatshop'); ?>'
                },
                multiple: false
            });

            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#branding_logo').val(attachment.url);
                $('.logo-preview').html('<img src="' + attachment.url + '" alt="Brand Logo" />');
                $('.remove-logo-btn').show();
            });

            mediaUploader.open();
        });

        // Remove logo
        $('.remove-logo-btn').on('click', function(e) {
            e.preventDefault();
            $('#branding_logo').val('');
            $('.logo-preview').empty();
            $(this).hide();
        });

        // Copy payment link
        $('.copy-link-btn').on('click', function() {
            var link = $(this).data('link');
            var button = $(this);
            var originalText = button.text();

            // Create temporary input element
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(link).select();

            // Copy to clipboard
            document.execCommand('copy');
            $temp.remove();

            // Update button text
            button.text('<?php esc_html_e('Copied!', 'chatshop'); ?>');
            setTimeout(function() {
                button.text(originalText);
            }, 2000);
        });

        // Show QR code
        $('.show-qr-btn').on('click', function() {
            var linkId = $(this).data('link-id');

            // Show QR code in modal
            // This would be implemented with the QR generation logic
            alert('QR Code for link: ' + linkId);
        });

        // Create new payment link
        $('#create-payment-link-btn').on('click', function() {
            // Redirect to payment link creation page
            window.location.href = '<?php echo esc_url(admin_url('admin.php?page=chatshop-payment-links&action=new')); ?>';
        });
    });
</script>