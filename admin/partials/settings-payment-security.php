<?php

/**
 * Payment Security Settings Partial
 *
 * @package    ChatShop
 * @subpackage ChatShop/admin/partials
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

// Get security settings
$security_settings = get_option('chatshop_payment_security', array());
$enable_fraud_check = isset($security_settings['enable_fraud_check']) ? $security_settings['enable_fraud_check'] : true;
$fraud_score_threshold = isset($security_settings['fraud_score_threshold']) ? $security_settings['fraud_score_threshold'] : 70;
$require_3ds = isset($security_settings['require_3ds']) ? $security_settings['require_3ds'] : false;
$enable_velocity_check = isset($security_settings['enable_velocity_check']) ? $security_settings['enable_velocity_check'] : true;
$max_transactions_per_hour = isset($security_settings['max_transactions_per_hour']) ? $security_settings['max_transactions_per_hour'] : 5;
$max_amount_per_day = isset($security_settings['max_amount_per_day']) ? $security_settings['max_amount_per_day'] : 10000;
$encryption_enabled = isset($security_settings['encryption_enabled']) ? $security_settings['encryption_enabled'] : true;
$pci_compliance_mode = isset($security_settings['pci_compliance_mode']) ? $security_settings['pci_compliance_mode'] : true;
$enable_ip_blocking = isset($security_settings['enable_ip_blocking']) ? $security_settings['enable_ip_blocking'] : true;
$blocked_ips = isset($security_settings['blocked_ips']) ? $security_settings['blocked_ips'] : '';
$enable_country_blocking = isset($security_settings['enable_country_blocking']) ? $security_settings['enable_country_blocking'] : false;
$blocked_countries = isset($security_settings['blocked_countries']) ? $security_settings['blocked_countries'] : array();
$enable_bin_check = isset($security_settings['enable_bin_check']) ? $security_settings['enable_bin_check'] : true;
$suspicious_bins = isset($security_settings['suspicious_bins']) ? $security_settings['suspicious_bins'] : '';
$enable_duplicate_check = isset($security_settings['enable_duplicate_check']) ? $security_settings['enable_duplicate_check'] : true;
$duplicate_window = isset($security_settings['duplicate_window']) ? $security_settings['duplicate_window'] : 24;

// Get available countries
$countries = ChatShop_Helper::get_countries();
?>

<div class="chatshop-settings-section">
    <h2><?php esc_html_e('Payment Security Settings', 'chatshop'); ?></h2>

    <div class="chatshop-security-notice">
        <span class="dashicons dashicons-shield"></span>
        <p>
            <?php esc_html_e('Configure security settings to protect against fraudulent transactions and ensure compliance with payment industry standards.', 'chatshop'); ?>
        </p>
    </div>

    <h3><?php esc_html_e('Fraud Prevention', 'chatshop'); ?></h3>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="enable_fraud_check"><?php esc_html_e('Enable Fraud Detection', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="chatshop-toggle">
                    <input type="checkbox"
                        id="enable_fraud_check"
                        name="chatshop_payment_security[enable_fraud_check]"
                        value="1"
                        <?php checked($enable_fraud_check, true); ?> />
                    <span class="chatshop-toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Automatically screen transactions for potential fraud using advanced algorithms.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr class="fraud-settings" style="display: <?php echo $enable_fraud_check ? 'table-row' : 'none'; ?>;">
            <th scope="row">
                <label for="fraud_score_threshold"><?php esc_html_e('Fraud Score Threshold', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="number"
                    id="fraud_score_threshold"
                    name="chatshop_payment_security[fraud_score_threshold]"
                    value="<?php echo esc_attr($fraud_score_threshold); ?>"
                    min="0"
                    max="100"
                    class="small-text" />
                <span><?php esc_html_e('(0-100)', 'chatshop'); ?></span>
                <p class="description">
                    <?php esc_html_e('Transactions with fraud scores above this threshold will be flagged for review.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="require_3ds"><?php esc_html_e('Require 3D Secure', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="chatshop-toggle">
                    <input type="checkbox"
                        id="require_3ds"
                        name="chatshop_payment_security[require_3ds]"
                        value="1"
                        <?php checked($require_3ds, true); ?> />
                    <span class="chatshop-toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Require 3D Secure authentication for all card transactions when available.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="enable_bin_check"><?php esc_html_e('BIN/IIN Checking', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="chatshop-toggle">
                    <input type="checkbox"
                        id="enable_bin_check"
                        name="chatshop_payment_security[enable_bin_check]"
                        value="1"
                        <?php checked($enable_bin_check, true); ?> />
                    <span class="chatshop-toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Check card BIN/IIN numbers against known suspicious patterns.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr class="bin-settings" style="display: <?php echo $enable_bin_check ? 'table-row' : 'none'; ?>;">
            <th scope="row">
                <label for="suspicious_bins"><?php esc_html_e('Suspicious BINs', 'chatshop'); ?></label>
            </th>
            <td>
                <textarea id="suspicious_bins"
                    name="chatshop_payment_security[suspicious_bins]"
                    rows="4"
                    class="large-text"
                    placeholder="<?php esc_attr_e('Enter BIN numbers, one per line', 'chatshop'); ?>"><?php echo esc_textarea($suspicious_bins); ?></textarea>
                <p class="description">
                    <?php esc_html_e('List of card BIN numbers to flag as suspicious (first 6 digits of card number).', 'chatshop'); ?>
                </p>
            </td>
        </tr>
    </table>

    <h3><?php esc_html_e('Velocity Checks', 'chatshop'); ?></h3>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="enable_velocity_check"><?php esc_html_e('Enable Velocity Checking', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="chatshop-toggle">
                    <input type="checkbox"
                        id="enable_velocity_check"
                        name="chatshop_payment_security[enable_velocity_check]"
                        value="1"
                        <?php checked($enable_velocity_check, true); ?> />
                    <span class="chatshop-toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Limit the frequency and volume of transactions to prevent abuse.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr class="velocity-settings" style="display: <?php echo $enable_velocity_check ? 'table-row' : 'none'; ?>;">
            <th scope="row">
                <label for="max_transactions_per_hour"><?php esc_html_e('Max Transactions Per Hour', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="number"
                    id="max_transactions_per_hour"
                    name="chatshop_payment_security[max_transactions_per_hour]"
                    value="<?php echo esc_attr($max_transactions_per_hour); ?>"
                    min="1"
                    max="100"
                    class="small-text" />
                <p class="description">
                    <?php esc_html_e('Maximum number of transactions allowed per customer per hour.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr class="velocity-settings" style="display: <?php echo $enable_velocity_check ? 'table-row' : 'none'; ?>;">
            <th scope="row">
                <label for="max_amount_per_day"><?php esc_html_e('Max Amount Per Day', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="number"
                    id="max_amount_per_day"
                    name="chatshop_payment_security[max_amount_per_day]"
                    value="<?php echo esc_attr($max_amount_per_day); ?>"
                    min="100"
                    step="100"
                    class="regular-text" />
                <span><?php echo esc_html(get_woocommerce_currency_symbol()); ?></span>
                <p class="description">
                    <?php esc_html_e('Maximum total transaction amount allowed per customer per day.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="enable_duplicate_check"><?php esc_html_e('Duplicate Transaction Check', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="chatshop-toggle">
                    <input type="checkbox"
                        id="enable_duplicate_check"
                        name="chatshop_payment_security[enable_duplicate_check]"
                        value="1"
                        <?php checked($enable_duplicate_check, true); ?> />
                    <span class="chatshop-toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Prevent duplicate transactions within a specified time window.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr class="duplicate-settings" style="display: <?php echo $enable_duplicate_check ? 'table-row' : 'none'; ?>;">
            <th scope="row">
                <label for="duplicate_window"><?php esc_html_e('Duplicate Check Window', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="number"
                    id="duplicate_window"
                    name="chatshop_payment_security[duplicate_window]"
                    value="<?php echo esc_attr($duplicate_window); ?>"
                    min="1"
                    max="168"
                    class="small-text" />
                <span><?php esc_html_e('hours', 'chatshop'); ?></span>
                <p class="description">
                    <?php esc_html_e('Time window to check for duplicate transactions (1-168 hours).', 'chatshop'); ?>
                </p>
            </td>
        </tr>
    </table>

    <h3><?php esc_html_e('Access Control', 'chatshop'); ?></h3>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="enable_ip_blocking"><?php esc_html_e('IP Address Blocking', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="chatshop-toggle">
                    <input type="checkbox"
                        id="enable_ip_blocking"
                        name="chatshop_payment_security[enable_ip_blocking]"
                        value="1"
                        <?php checked($enable_ip_blocking, true); ?> />
                    <span class="chatshop-toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Block specific IP addresses from making payments.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr class="ip-settings" style="display: <?php echo $enable_ip_blocking ? 'table-row' : 'none'; ?>;">
            <th scope="row">
                <label for="blocked_ips"><?php esc_html_e('Blocked IP Addresses', 'chatshop'); ?></label>
            </th>
            <td>
                <textarea id="blocked_ips"
                    name="chatshop_payment_security[blocked_ips]"
                    rows="4"
                    class="large-text"
                    placeholder="<?php esc_attr_e('Enter IP addresses, one per line', 'chatshop'); ?>"><?php echo esc_textarea($blocked_ips); ?></textarea>
                <p class="description">
                    <?php esc_html_e('List of IP addresses to block. Supports CIDR notation (e.g., 192.168.1.0/24).', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="enable_country_blocking"><?php esc_html_e('Country Blocking', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="chatshop-toggle">
                    <input type="checkbox"
                        id="enable_country_blocking"
                        name="chatshop_payment_security[enable_country_blocking]"
                        value="1"
                        <?php checked($enable_country_blocking, true); ?> />
                    <span class="chatshop-toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Block payments from specific countries.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr class="country-settings" style="display: <?php echo $enable_country_blocking ? 'table-row' : 'none'; ?>;">
            <th scope="row">
                <label for="blocked_countries"><?php esc_html_e('Blocked Countries', 'chatshop'); ?></label>
            </th>
            <td>
                <select id="blocked_countries"
                    name="chatshop_payment_security[blocked_countries][]"
                    multiple="multiple"
                    class="chatshop-select2"
                    style="width: 100%; max-width: 400px;">
                    <?php foreach ($countries as $code => $name) : ?>
                        <option value="<?php echo esc_attr($code); ?>"
                            <?php selected(in_array($code, $blocked_countries)); ?>>
                            <?php echo esc_html($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php esc_html_e('Select countries from which payments will not be accepted.', 'chatshop'); ?>
                </p>
            </td>
        </tr>
    </table>

    <h3><?php esc_html_e('Data Security', 'chatshop'); ?></h3>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="encryption_enabled"><?php esc_html_e('Data Encryption', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="chatshop-toggle">
                    <input type="checkbox"
                        id="encryption_enabled"
                        name="chatshop_payment_security[encryption_enabled]"
                        value="1"
                        <?php checked($encryption_enabled, true); ?> />
                    <span class="chatshop-toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Encrypt sensitive payment data at rest using AES-256 encryption.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="pci_compliance_mode"><?php esc_html_e('PCI Compliance Mode', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="chatshop-toggle">
                    <input type="checkbox"
                        id="pci_compliance_mode"
                        name="chatshop_payment_security[pci_compliance_mode]"
                        value="1"
                        <?php checked($pci_compliance_mode, true); ?> />
                    <span class="chatshop-toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Enable strict PCI DSS compliance features including tokenization and secure data handling.', 'chatshop'); ?>
                </p>
            </td>
        </tr>
    </table>
</div>

<div class="chatshop-settings-section">
    <h3><?php esc_html_e('Security Status', 'chatshop'); ?></h3>

    <div class="chatshop-security-status">
        <div class="security-status-grid">
            <div class="status-card">
                <span class="dashicons dashicons-shield-alt"></span>
                <h4><?php esc_html_e('Fraud Prevention', 'chatshop'); ?></h4>
                <span class="status-indicator <?php echo $enable_fraud_check ? 'active' : 'inactive'; ?>">
                    <?php echo $enable_fraud_check ? esc_html__('Active', 'chatshop') : esc_html__('Inactive', 'chatshop'); ?>
                </span>
            </div>

            <div class="status-card">
                <span class="dashicons dashicons-lock"></span>
                <h4><?php esc_html_e('Data Encryption', 'chatshop'); ?></h4>
                <span class="status-indicator <?php echo $encryption_enabled ? 'active' : 'inactive'; ?>">
                    <?php echo $encryption_enabled ? esc_html__('Enabled', 'chatshop') : esc_html__('Disabled', 'chatshop'); ?>
                </span>
            </div>

            <div class="status-card">
                <span class="dashicons dashicons-awards"></span>
                <h4><?php esc_html_e('PCI Compliance', 'chatshop'); ?></h4>
                <span class="status-indicator <?php echo $pci_compliance_mode ? 'active' : 'inactive'; ?>">
                    <?php echo $pci_compliance_mode ? esc_html__('Enabled', 'chatshop') : esc_html__('Disabled', 'chatshop'); ?>
                </span>
            </div>

            <div class="status-card">
                <span class="dashicons dashicons-admin-site-alt3"></span>
                <h4><?php esc_html_e('Access Control', 'chatshop'); ?></h4>
                <span class="status-indicator <?php echo ($enable_ip_blocking || $enable_country_blocking) ? 'active' : 'inactive'; ?>">
                    <?php echo ($enable_ip_blocking || $enable_country_blocking) ? esc_html__('Active', 'chatshop') : esc_html__('Inactive', 'chatshop'); ?>
                </span>
            </div>
        </div>

        <div class="security-recommendations">
            <h4><?php esc_html_e('Security Recommendations', 'chatshop'); ?></h4>
            <ul>
                <?php if (! $encryption_enabled) : ?>
                    <li class="warning">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('Enable data encryption to protect sensitive payment information.', 'chatshop'); ?>
                    </li>
                <?php endif; ?>

                <?php if (! $require_3ds) : ?>
                    <li class="info">
                        <span class="dashicons dashicons-info"></span>
                        <?php esc_html_e('Consider enabling 3D Secure for additional card payment protection.', 'chatshop'); ?>
                    </li>
                <?php endif; ?>

                <?php if (! $enable_fraud_check) : ?>
                    <li class="warning">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('Fraud detection is disabled. Enable it to protect against fraudulent transactions.', 'chatshop'); ?>
                    </li>
                <?php endif; ?>

                <?php if ($encryption_enabled && $pci_compliance_mode && $enable_fraud_check) : ?>
                    <li class="success">
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e('Your payment security configuration looks good!', 'chatshop'); ?>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<style>
    .chatshop-security-notice {
        background: #f0f6fc;
        border-left: 4px solid #2271b1;
        padding: 15px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .chatshop-security-notice .dashicons {
        font-size: 24px;
        color: #2271b1;
    }

    .chatshop-security-notice p {
        margin: 0;
        flex: 1;
    }

    .security-status-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .status-card {
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 20px;
        text-align: center;
    }

    .status-card .dashicons {
        font-size: 36px;
        color: #666;
        margin-bottom: 10px;
    }

    .status-card h4 {
        margin: 0 0 10px 0;
        font-size: 14px;
    }

    .status-indicator {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-indicator.active {
        background: #d4f4dd;
        color: #00a32a;
    }

    .status-indicator.inactive {
        background: #fef2f2;
        color: #dc2626;
    }

    .security-recommendations {
        background: #fafafa;
        border: 1px solid #e5e5e5;
        border-radius: 4px;
        padding: 20px;
    }

    .security-recommendations h4 {
        margin-top: 0;
    }

    .security-recommendations ul {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .security-recommendations li {
        padding: 8px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .security-recommendations li.warning {
        color: #b32d2e;
    }

    .security-recommendations li.info {
        color: #996800;
    }

    .security-recommendations li.success {
        color: #00a32a;
    }

    .chatshop-select2 {
        min-width: 300px;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Initialize Select2 for country selection
        if ($.fn.select2) {
            $('.chatshop-select2').select2({
                placeholder: '<?php esc_html_e('Select countries...', 'chatshop'); ?>',
                allowClear: true
            });
        }

        // Toggle fraud settings
        $('#enable_fraud_check').on('change', function() {
            if ($(this).is(':checked')) {
                $('.fraud-settings').show();
            } else {
                $('.fraud-settings').hide();
            }
        });

        // Toggle velocity settings
        $('#enable_velocity_check').on('change', function() {
            if ($(this).is(':checked')) {
                $('.velocity-settings').show();
            } else {
                $('.velocity-settings').hide();
            }
        });

        // Toggle duplicate settings
        $('#enable_duplicate_check').on('change', function() {
            if ($(this).is(':checked')) {
                $('.duplicate-settings').show();
            } else {
                $('.duplicate-settings').hide();
            }
        });

        // Toggle IP settings
        $('#enable_ip_blocking').on('change', function() {
            if ($(this).is(':checked')) {
                $('.ip-settings').show();
            } else {
                $('.ip-settings').hide();
            }
        });

        // Toggle country settings
        $('#enable_country_blocking').on('change', function() {
            if ($(this).is(':checked')) {
                $('.country-settings').show();
            } else {
                $('.country-settings').hide();
            }
        });

        // Toggle BIN settings
        $('#enable_bin_check').on('change', function() {
            if ($(this).is(':checked')) {
                $('.bin-settings').show();
            } else {
                $('.bin-settings').hide();
            }
        });
    });
</script>