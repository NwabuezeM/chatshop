<?php

/**
 * General settings tab template
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

$general_settings = $settings['general'] ?? array();
?>

<div class="section-description">
    <h4><?php _e('General Settings', 'chatshop'); ?></h4>
    <p><?php _e('Configure basic settings for your ChatShop plugin. These settings control the overall behavior and appearance of your WhatsApp integration.', 'chatshop'); ?></p>
</div>

<table class="form-table" role="presentation">
    <tbody>
        <!-- Plugin Status -->
        <tr>
            <th scope="row">
                <label for="plugin_enabled"><?php _e('Enable ChatShop', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="toggle-switch">
                    <input type="checkbox"
                        id="plugin_enabled"
                        name="chatshop_settings[general][plugin_enabled]"
                        value="1"
                        <?php checked($general_settings['plugin_enabled'] ?? 0, 1); ?>>
                    <span class="toggle-slider"></span>
                </label>
                <p class="description">
                    <?php _e('Enable or disable the ChatShop plugin functionality.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <!-- Store Name -->
        <tr>
            <th scope="row">
                <label for="store_name"><?php _e('Store Name', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="text"
                    id="store_name"
                    name="chatshop_settings[general][store_name]"
                    value="<?php echo esc_attr($general_settings['store_name'] ?? get_bloginfo('name')); ?>"
                    class="regular-text">
                <p class="description">
                    <?php _e('Your store name that will appear in WhatsApp messages and payment links.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <!-- Store Logo -->
        <tr>
            <th scope="row">
                <label for="store_logo"><?php _e('Store Logo', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="text"
                    id="store_logo"
                    name="chatshop_settings[general][store_logo]"
                    value="<?php echo esc_url($general_settings['store_logo'] ?? ''); ?>"
                    class="regular-text">
                <button type="button"
                    class="button upload-button"
                    data-title="<?php _e('Select Store Logo', 'chatshop'); ?>"
                    data-button-text="<?php _e('Use this logo', 'chatshop'); ?>"
                    data-media-type="image">
                    <?php _e('Upload Logo', 'chatshop'); ?>
                </button>
                <button type="button" class="button remove-file"><?php _e('Remove', 'chatshop'); ?></button>
                <div class="file-preview">
                    <?php if (! empty($general_settings['store_logo'])): ?>
                        <img src="<?php echo esc_url($general_settings['store_logo']); ?>"
                            style="max-width: 100px; max-height: 100px; margin-top: 10px;">
                    <?php endif; ?>
                </div>
                <p class="description">
                    <?php _e('Upload your store logo to be used in WhatsApp messages and payment pages. Recommended size: 200x200px.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <!-- Default Currency -->
        <tr>
            <th scope="row">
                <label for="default_currency"><?php _e('Default Currency', 'chatshop'); ?></label>
            </th>
            <td>
                <select id="default_currency"
                    name="chatshop_settings[general][default_currency]">
                    <?php
                    $currencies = get_woocommerce_currencies();
                    $selected_currency = $general_settings['default_currency'] ?? get_woocommerce_currency();

                    foreach ($currencies as $code => $name) {
                        printf(
                            '<option value="%s" %s>%s (%s)</option>',
                            esc_attr($code),
                            selected($selected_currency, $code, false),
                            esc_html($name),
                            esc_html($code)
                        );
                    }
                    ?>
                </select>
                <p class="description">
                    <?php _e('Default currency for payment links and transactions.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <!-- Timezone -->
        <tr>
            <th scope="row">
                <label for="timezone"><?php _e('Timezone', 'chatshop'); ?></label>
            </th>
            <td>
                <select id="timezone"
                    name="chatshop_settings[general][timezone]">
                    <?php
                    $selected_timezone = $general_settings['timezone'] ?? wp_timezone_string();
                    $timezones = timezone_identifiers_list();

                    foreach ($timezones as $timezone) {
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr($timezone),
                            selected($selected_timezone, $timezone, false),
                            esc_html($timezone)
                        );
                    }
                    ?>
                </select>
                <p class="description">
                    <?php _e('Timezone for scheduling campaigns and displaying timestamps.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <!-- Contact Form Integration -->
        <tr>
            <th scope="row">
                <label for="contact_form_enabled"><?php _e('Contact Form Integration', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="toggle-switch">
                    <input type="checkbox"
                        id="contact_form_enabled"
                        name="chatshop_settings[general][contact_form_enabled]"
                        value="1"
                        <?php checked($general_settings['contact_form_enabled'] ?? 1, 1); ?>>
                    <span class="toggle-slider"></span>
                </label>
                <p class="description">
                    <?php _e('Enable automatic contact collection from WooCommerce orders and contact forms.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <!-- Auto Import WooCommerce Customers -->
        <tr>
            <th scope="row">
                <label for="auto_import_customers"><?php _e('Auto Import Customers', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="toggle-switch">
                    <input type="checkbox"
                        id="auto_import_customers"
                        name="chatshop_settings[general][auto_import_customers]"
                        value="1"
                        <?php checked($general_settings['auto_import_customers'] ?? 1, 1); ?>>
                    <span class="toggle-slider"></span>
                </label>
                <p class="description">
                    <?php _e('Automatically import WooCommerce customers as WhatsApp contacts when they place orders.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <!-- Debug Mode -->
        <tr>
            <th scope="row">
                <label for="debug_mode"><?php _e('Debug Mode', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="toggle-switch">
                    <input type="checkbox"
                        id="debug_mode"
                        name="chatshop_settings[general][debug_mode]"
                        value="1"
                        <?php checked($general_settings['debug_mode'] ?? 0, 1); ?>>
                    <span class="toggle-slider"></span>
                </label>
                <p class="description">
                    <?php _e('Enable debug mode to log detailed information for troubleshooting. Only enable when needed.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <!-- Data Retention -->
        <tr>
            <th scope="row">
                <label for="data_retention_days"><?php _e('Data Retention (Days)', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="number"
                    id="data_retention_days"
                    name="chatshop_settings[general][data_retention_days]"
                    value="<?php echo esc_attr($general_settings['data_retention_days'] ?? 365); ?>"
                    min="30"
                    max="3650"
                    class="small-text">
                <p class="description">
                    <?php _e('Number of days to retain message logs and analytics data. Minimum 30 days, maximum 10 years.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <!-- Rate Limiting -->
        <tr>
            <th scope="row">
                <label for="rate_limit_enabled"><?php _e('Rate Limiting', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="toggle-switch">
                    <input type="checkbox"
                        id="rate_limit_enabled"
                        name="chatshop_settings[general][rate_limit_enabled]"
                        value="1"
                        data-toggle-target="#rate_limit_options"
                        <?php checked($general_settings['rate_limit_enabled'] ?? 1, 1); ?>>
                    <span class="toggle-slider"></span>
                </label>
                <p class="description">
                    <?php _e('Enable rate limiting to prevent API abuse and comply with WhatsApp Business API limits.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <!-- Rate Limit Options -->
        <tr id="rate_limit_options">
            <th scope="row">
                <label for="messages_per_minute"><?php _e('Messages Per Minute', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="number"
                    id="messages_per_minute"
                    name="chatshop_settings[general][messages_per_minute]"
                    value="<?php echo esc_attr($general_settings['messages_per_minute'] ?? 80); ?>"
                    min="1"
                    max="1000"
                    class="small-text">
                <p class="description">
                    <?php _e('Maximum number of messages that can be sent per minute. WhatsApp Business API allows up to 80 messages per minute by default.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <!-- Privacy Settings -->
        <tr>
            <th scope="row">
                <label for="privacy_policy_url"><?php _e('Privacy Policy URL', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="url"
                    id="privacy_policy_url"
                    name="chatshop_settings[general][privacy_policy_url]"
                    value="<?php echo esc_url($general_settings['privacy_policy_url'] ?? ''); ?>"
                    class="regular-text">
                <p class="description">
                    <?php _e('URL to your privacy policy page. This will be included in opt-in messages and payment pages.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <!-- Terms of Service URL -->
        <tr>
            <th scope="row">
                <label for="terms_url"><?php _e('Terms of Service URL', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="url"
                    id="terms_url"
                    name="chatshop_settings[general][terms_url]"
                    value="<?php echo esc_url($general_settings['terms_url'] ?? ''); ?>"
                    class="regular-text">
                <p class="description">
                    <?php _e('URL to your terms of service page. This will be included in payment pages and legal communications.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <!-- Support Contact -->
        <tr>
            <th scope="row">
                <label for="support_contact"><?php _e('Support Contact', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="text"
                    id="support_contact"
                    name="chatshop_settings[general][support_contact]"
                    value="<?php echo esc_attr($general_settings['support_contact'] ?? ''); ?>"
                    class="regular-text"
                    placeholder="+1234567890">
                <p class="description">
                    <?php _e('WhatsApp number for customer support. Include country code (e.g., +1234567890).', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <!-- Business Hours -->
        <tr>
            <th scope="row">
                <label for="business_hours_enabled"><?php _e('Business Hours', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="toggle-switch">
                    <input type="checkbox"
                        id="business_hours_enabled"
                        name="chatshop_settings[general][business_hours_enabled]"
                        value="1"
                        data-toggle-target="#business_hours_settings"
                        <?php checked($general_settings['business_hours_enabled'] ?? 0, 1); ?>>
                    <span class="toggle-slider"></span>
                </label>
                <p class="description">
                    <?php _e('Enable business hours to control when campaigns can be sent and auto-responses are active.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <!-- Business Hours Settings -->
        <tr id="business_hours_settings">
            <th scope="row">
                <label><?php _e('Operating Hours', 'chatshop'); ?></label>
            </th>
            <td>
                <table class="business-hours-table">
                    <?php
                    $days = array(
                        'monday' => __('Monday', 'chatshop'),
                        'tuesday' => __('Tuesday', 'chatshop'),
                        'wednesday' => __('Wednesday', 'chatshop'),
                        'thursday' => __('Thursday', 'chatshop'),
                        'friday' => __('Friday', 'chatshop'),
                        'saturday' => __('Saturday', 'chatshop'),
                        'sunday' => __('Sunday', 'chatshop'),
                    );

                    foreach ($days as $day_key => $day_name):
                        $day_settings = $general_settings['business_hours'][$day_key] ?? array();
                    ?>
                        <tr>
                            <td style="width: 100px;"><?php echo $day_name; ?></td>
                            <td>
                                <label>
                                    <input type="checkbox"
                                        name="chatshop_settings[general][business_hours][<?php echo $day_key; ?>][enabled]"
                                        value="1"
                                        <?php checked($day_settings['enabled'] ?? 1, 1); ?>>
                                    <?php _e('Open', 'chatshop'); ?>
                                </label>
                            </td>
                            <td>
                                <input type="time"
                                    name="chatshop_settings[general][business_hours][<?php echo $day_key; ?>][open]"
                                    value="<?php echo esc_attr($day_settings['open'] ?? '09:00'); ?>"
                                    style="width: 80px;">
                            </td>
                            <td><?php _e('to', 'chatshop'); ?></td>
                            <td>
                                <input type="time"
                                    name="chatshop_settings[general][business_hours][<?php echo $day_key; ?>][close]"
                                    value="<?php echo esc_attr($day_settings['close'] ?? '17:00'); ?>"
                                    style="width: 80px;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <p class="description">
                    <?php _e('Set your business operating hours. Campaigns will only be sent during these hours, and auto-responses will indicate when you\'re available.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <!-- Uninstall Data -->
        <tr>
            <th scope="row">
                <label for="delete_data_on_uninstall"><?php _e('Delete Data on Uninstall', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="toggle-switch">
                    <input type="checkbox"
                        id="delete_data_on_uninstall"
                        name="chatshop_settings[general][delete_data_on_uninstall]"
                        value="1"
                        <?php checked($general_settings['delete_data_on_uninstall'] ?? 0, 1); ?>>
                    <span class="toggle-slider"></span>
                </label>
                <p class="description">
                    <?php _e('WARNING: Enable this to delete ALL ChatShop data when the plugin is uninstalled. This action cannot be undone.', 'chatshop'); ?>
                </p>
            </td>
        </tr>
    </tbody>
</table>

<style>
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        -webkit-transition: .4s;
        transition: .4s;
        border-radius: 24px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        -webkit-transition: .4s;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked+.toggle-slider {
        background-color: #2196F3;
    }

    input:focus+.toggle-slider {
        box-shadow: 0 0 1px #2196F3;
    }

    input:checked+.toggle-slider:before {
        -webkit-transform: translateX(26px);
        -ms-transform: translateX(26px);
        transform: translateX(26px);
    }

    .business-hours-table {
        border-collapse: collapse;
        margin-top: 10px;
    }

    .business-hours-table td {
        padding: 5px 10px;
        vertical-align: middle;
    }

    .business-hours-table tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    .file-preview {
        margin-top: 10px;
    }

    .file-preview img {
        border: 1px solid #ddd;
        border-radius: 4px;
        display: block;
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
        border-radius: 0 4px 4px 0;
    }

    .section-description h4 {
        margin-top: 0;
        color: #2196F3;
        font-size: 16px;
    }

    .section-description p {
        margin-bottom: 0;
        color: #666;
    }

    .form-table tr.disabled {
        opacity: 0.5;
    }

    .form-table tr.disabled input,
    .form-table tr.disabled select,
    .form-table tr.disabled textarea {
        background-color: #f5f5f5;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {

        .form-table th,
        .form-table td {
            display: block;
            width: 100%;
            padding: 10px 0;
        }

        .form-table th {
            border-bottom: none;
            font-weight: bold;
        }

        .business-hours-table {
            width: 100%;
        }

        .business-hours-table td {
            padding: 3px 5px;
            font-size: 14px;
        }
    }

    /* Field validation styles */
    .form-table input:invalid {
        border-color: #dc3232;
        box-shadow: 0 0 2px rgba(220, 50, 50, 0.3);
    }

    .form-table input:valid {
        border-color: #46b450;
    }

    /* Help text improvements */
    .description {
        font-size: 13px;
        line-height: 1.4;
        margin-top: 5px;
        color: #666;
    }

    .description code {
        background: #f1f1f1;
        padding: 2px 4px;
        border-radius: 3px;
        font-size: 12px;
    }

    /* Warning styles for dangerous settings */
    tr:has(#delete_data_on_uninstall) .description {
        color: #dc3232;
        font-weight: 500;
    }

    tr:has(#delete_data_on_uninstall) {
        background-color: #fef7f7;
    }
</style>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Handle business hours checkbox changes
        $('.business-hours-table input[type="checkbox"]').on('change', function() {
            const $row = $(this).closest('tr');
            const $timeInputs = $row.find('input[type="time"]');

            if ($(this).is(':checked')) {
                $timeInputs.prop('disabled', false);
                $row.removeClass('disabled');
            } else {
                $timeInputs.prop('disabled', true);
                $row.addClass('disabled');
            }
        }).trigger('change');

        // Validate phone number format
        $('#support_contact').on('blur', function() {
            const phoneNumber = $(this).val();
            const phoneRegex = /^\+[1-9]\d{1,14}$/;

            if (phoneNumber && !phoneRegex.test(phoneNumber)) {
                $(this).addClass('error');
                if (!$(this).siblings('.validation-error').length) {
                    $(this).after('<p class="validation-error" style="color: #dc3232; font-size: 12px; margin-top: 3px;"><?php _e('Please enter a valid phone number with country code (e.g., +1234567890)', 'chatshop'); ?></p>');
                }
            } else {
                $(this).removeClass('error');
                $(this).siblings('.validation-error').remove();
            }
        });

        // Validate data retention days
        $('#data_retention_days').on('input', function() {
            const days = parseInt($(this).val());

            if (days < 30) {
                $(this).val(30);
            } else if (days > 3650) {
                $(this).val(3650);
            }
        });

        // Validate messages per minute
        $('#messages_per_minute').on('input', function() {
            const rate = parseInt($(this).val());

            if (rate < 1) {
                $(this).val(1);
            } else if (rate > 1000) {
                $(this).val(1000);
            }

            // Show warning if rate is too high
            if (rate > 80) {
                if (!$(this).siblings('.rate-warning').length) {
                    $(this).after('<p class="rate-warning" style="color: #ff8800; font-size: 12px; margin-top: 3px;"><?php _e('High rate limits may exceed WhatsApp API limits and could result in throttling.', 'chatshop'); ?></p>');
                }
            } else {
                $(this).siblings('.rate-warning').remove();
            }
        });

        // Auto-populate store name if empty
        if (!$('#store_name').val()) {
            $('#store_name').val('<?php echo esc_js(get_bloginfo('name')); ?>');
        }

        // Handle logo preview
        $('#store_logo').on('input', function() {
            const url = $(this).val();
            const $preview = $(this).siblings('.file-preview');

            if (url && url.match(/\.(jpeg|jpg|gif|png)$/)) {
                $preview.html('<img src="' + url + '" style="max-width: 100px; max-height: 100px; margin-top: 10px;">');
            } else {
                $preview.empty();
            }
        });

        // Confirm dangerous settings
        $('#delete_data_on_uninstall').on('change', function() {
            if ($(this).is(':checked')) {
                const confirmed = confirm('<?php _e('WARNING: This will delete ALL ChatShop data when the plugin is uninstalled. Are you sure?', 'chatshop'); ?>');

                if (!confirmed) {
                    $(this).prop('checked', false);
                }
            }
        });

        // Form validation before submit
        $('#chatshop-settings-form').on('submit', function(e) {
            let hasErrors = false;

            // Check required fields
            const requiredFields = ['store_name'];

            requiredFields.forEach(function(fieldId) {
                const $field = $('#' + fieldId);

                if (!$field.val().trim()) {
                    $field.addClass('error');
                    hasErrors = true;

                    if (!$field.siblings('.validation-error').length) {
                        $field.after('<p class="validation-error" style="color: #dc3232; font-size: 12px; margin-top: 3px;"><?php _e('This field is required.', 'chatshop'); ?></p>');
                    }
                } else {
                    $field.removeClass('error');
                    $field.siblings('.validation-error').remove();
                }
            });

            // Check phone number if provided
            const phoneNumber = $('#support_contact').val();
            if (phoneNumber) {
                const phoneRegex = /^\+[1-9]\d{1,14}$/;
                if (!phoneRegex.test(phoneNumber)) {
                    hasErrors = true;
                    $('#support_contact').focus();
                }
            }

            if (hasErrors) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: $('.error').first().offset().top - 100
                }, 500);
            }
        });
    });
</script>