<?php

/**
 * Payment Logs Settings Partial
 *
 * @package    ChatShop
 * @subpackage ChatShop/admin/partials
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

// Get log settings
$log_settings = get_option('chatshop_payment_log_settings', array());
$log_retention = isset($log_settings['log_retention']) ? $log_settings['log_retention'] : 30;
$log_level = isset($log_settings['log_level']) ? $log_settings['log_level'] : 'error';
$log_sensitive_data = isset($log_settings['log_sensitive_data']) ? $log_settings['log_sensitive_data'] : false;
$auto_cleanup = isset($log_settings['auto_cleanup']) ? $log_settings['auto_cleanup'] : true;
$export_format = isset($log_settings['export_format']) ? $log_settings['export_format'] : 'csv';

// Get statistics
$log_stats = ChatShop_Payment_Logger::get_statistics();
$total_logs = isset($log_stats['total']) ? $log_stats['total'] : 0;
$logs_by_level = isset($log_stats['by_level']) ? $log_stats['by_level'] : array();
$logs_by_gateway = isset($log_stats['by_gateway']) ? $log_stats['by_gateway'] : array();
$storage_size = isset($log_stats['storage_size']) ? $log_stats['storage_size'] : 0;

// Get filter parameters
$filter_gateway = isset($_GET['gateway']) ? sanitize_text_field($_GET['gateway']) : '';
$filter_level = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
$filter_date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
$search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

// Get logs
$logs = ChatShop_Payment_Logger::get_logs(array(
    'gateway' => $filter_gateway,
    'level' => $filter_level,
    'date_from' => $filter_date_from,
    'date_to' => $filter_date_to,
    'search' => $search_query,
    'per_page' => $per_page,
    'page' => $current_page
));

$total_items = $logs['total'];
$total_pages = ceil($total_items / $per_page);
?>

<div class="chatshop-settings-section">
    <h2><?php esc_html_e('Transaction Log Settings', 'chatshop'); ?></h2>

    <div class="chatshop-log-stats">
        <div class="stat-card">
            <span class="stat-value"><?php echo number_format($total_logs); ?></span>
            <span class="stat-label"><?php esc_html_e('Total Logs', 'chatshop'); ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-value"><?php echo esc_html(ChatShop_Helper::format_bytes($storage_size)); ?></span>
            <span class="stat-label"><?php esc_html_e('Storage Used', 'chatshop'); ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-value"><?php echo number_format($logs_by_level['error'] ?? 0); ?></span>
            <span class="stat-label"><?php esc_html_e('Errors', 'chatshop'); ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-value"><?php echo number_format($logs_by_level['warning'] ?? 0); ?></span>
            <span class="stat-label"><?php esc_html_e('Warnings', 'chatshop'); ?></span>
        </div>
    </div>

    <h3><?php esc_html_e('Log Configuration', 'chatshop'); ?></h3>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="log_level"><?php esc_html_e('Log Level', 'chatshop'); ?></label>
            </th>
            <td>
                <select id="log_level" name="chatshop_payment_log_settings[log_level]">
                    <option value="error" <?php selected($log_level, 'error'); ?>><?php esc_html_e('Errors Only', 'chatshop'); ?></option>
                    <option value="warning" <?php selected($log_level, 'warning'); ?>><?php esc_html_e('Warnings and Errors', 'chatshop'); ?></option>
                    <option value="info" <?php selected($log_level, 'info'); ?>><?php esc_html_e('Info, Warnings, and Errors', 'chatshop'); ?></option>
                    <option value="debug" <?php selected($log_level, 'debug'); ?>><?php esc_html_e('All (Including Debug)', 'chatshop'); ?></option>
                </select>
                <p class="description">
                    <?php esc_html_e('Set the minimum severity level for logging payment events.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="log_retention"><?php esc_html_e('Log Retention', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="number"
                    id="log_retention"
                    name="chatshop_payment_log_settings[log_retention]"
                    value="<?php echo esc_attr($log_retention); ?>"
                    min="1"
                    max="365"
                    class="small-text" />
                <span><?php esc_html_e('days', 'chatshop'); ?></span>
                <p class="description">
                    <?php esc_html_e('How long to keep transaction logs before automatic deletion (1-365 days).', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="auto_cleanup"><?php esc_html_e('Automatic Cleanup', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="chatshop-toggle">
                    <input type="checkbox"
                        id="auto_cleanup"
                        name="chatshop_payment_log_settings[auto_cleanup]"
                        value="1"
                        <?php checked($auto_cleanup, true); ?> />
                    <span class="chatshop-toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Automatically delete old logs based on retention period.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="log_sensitive_data"><?php esc_html_e('Log Sensitive Data', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="chatshop-toggle">
                    <input type="checkbox"
                        id="log_sensitive_data"
                        name="chatshop_payment_log_settings[log_sensitive_data]"
                        value="1"
                        <?php checked($log_sensitive_data, true); ?> />
                    <span class="chatshop-toggle-slider"></span>
                </label>
                <p class="description">
                    <strong><?php esc_html_e('Warning:', 'chatshop'); ?></strong>
                    <?php esc_html_e('Enable this only for debugging. Sensitive data will be masked in production.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="export_format"><?php esc_html_e('Export Format', 'chatshop'); ?></label>
            </th>
            <td>
                <select id="export_format" name="chatshop_payment_log_settings[export_format]">
                    <option value="csv" <?php selected($export_format, 'csv'); ?>><?php esc_html_e('CSV', 'chatshop'); ?></option>
                    <option value="json" <?php selected($export_format, 'json'); ?>><?php esc_html_e('JSON', 'chatshop'); ?></option>
                    <option value="xml" <?php selected($export_format, 'xml'); ?>><?php esc_html_e('XML', 'chatshop'); ?></option>
                </select>
                <p class="description">
                    <?php esc_html_e('Default format for exporting transaction logs.', 'chatshop'); ?>
                </p>
            </td>
        </tr>
    </table>

    <h3><?php esc_html_e('Transaction Logs', 'chatshop'); ?></h3>

    <!-- Filters -->
    <div class="chatshop-log-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>" />
            <input type="hidden" name="tab" value="logs" />

            <div class="filter-row">
                <div class="filter-item">
                    <label for="filter_gateway"><?php esc_html_e('Gateway:', 'chatshop'); ?></label>
                    <select id="filter_gateway" name="gateway">
                        <option value=""><?php esc_html_e('All Gateways', 'chatshop'); ?></option>
                        <?php foreach ($logs_by_gateway as $gateway => $count) : ?>
                            <option value="<?php echo esc_attr($gateway); ?>" <?php selected($filter_gateway, $gateway); ?>>
                                <?php echo esc_html(ucfirst($gateway) . ' (' . $count . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="filter_level"><?php esc_html_e('Level:', 'chatshop'); ?></label>
                    <select id="filter_level" name="level">
                        <option value=""><?php esc_html_e('All Levels', 'chatshop'); ?></option>
                        <option value="error" <?php selected($filter_level, 'error'); ?>><?php esc_html_e('Error', 'chatshop'); ?></option>
                        <option value="warning" <?php selected($filter_level, 'warning'); ?>><?php esc_html_e('Warning', 'chatshop'); ?></option>
                        <option value="info" <?php selected($filter_level, 'info'); ?>><?php esc_html_e('Info', 'chatshop'); ?></option>
                        <option value="debug" <?php selected($filter_level, 'debug'); ?>><?php esc_html_e('Debug', 'chatshop'); ?></option>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="date_from"><?php esc_html_e('From:', 'chatshop'); ?></label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr($filter_date_from); ?>" />
                </div>

                <div class="filter-item">
                    <label for="date_to"><?php esc_html_e('To:', 'chatshop'); ?></label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr($filter_date_to); ?>" />
                </div>

                <div class="filter-item">
                    <label for="search"><?php esc_html_e('Search:', 'chatshop'); ?></label>
                    <input type="text" id="search" name="search" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php esc_attr_e('Transaction ID, Reference...', 'chatshop'); ?>" />
                </div>

                <div class="filter-item">
                    <button type="submit" class="button"><?php esc_html_e('Filter', 'chatshop'); ?></button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $_GET['page'] . '&tab=logs')); ?>" class="button"><?php esc_html_e('Clear', 'chatshop'); ?></a>
                </div>
            </div>
        </form>

        <div class="log-actions">
            <button type="button" class="button export-logs-btn">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Export Logs', 'chatshop'); ?>
            </button>
            <button type="button" class="button button-link-delete clear-logs-btn">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Clear All Logs', 'chatshop'); ?>
            </button>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="chatshop-logs-table">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 150px;"><?php esc_html_e('Timestamp', 'chatshop'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Level', 'chatshop'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Gateway', 'chatshop'); ?></th>
                    <th style="width: 150px;"><?php esc_html_e('Transaction', 'chatshop'); ?></th>
                    <th><?php esc_html_e('Message', 'chatshop'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Details', 'chatshop'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (! empty($logs['items'])) : ?>
                    <?php foreach ($logs['items'] as $log) : ?>
                        <tr class="log-level-<?php echo esc_attr($log->level); ?>">
                            <td>
                                <?php echo esc_html(ChatShop_Helper::format_date($log->created_at, 'M j, Y H:i:s')); ?>
                            </td>
                            <td>
                                <span class="log-level log-level-<?php echo esc_attr($log->level); ?>">
                                    <?php echo esc_html(strtoupper($log->level)); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo esc_html(ucfirst($log->gateway)); ?>
                            </td>
                            <td>
                                <?php if ($log->transaction_id) : ?>
                                    <code><?php echo esc_html($log->transaction_id); ?></code>
                                <?php else : ?>
                                    <span class="description">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($log->message); ?>
                                <?php if ($log->context) :
                                    $context = json_decode($log->context, true);
                                    if (isset($context['amount'])) :
                                ?>
                                        <br><small><?php echo esc_html(ChatShop_Helper::format_price($context['amount'], $context['currency'] ?? 'USD')); ?></small>
                                <?php endif;
                                endif; ?>
                            </td>
                            <td>
                                <button type="button"
                                    class="button button-small view-log-details"
                                    data-log-id="<?php echo esc_attr($log->id); ?>">
                                    <?php esc_html_e('View', 'chatshop'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">
                            <?php esc_html_e('No logs found matching your criteria.', 'chatshop'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;', 'chatshop'),
                        'next_text' => __('&raquo;', 'chatshop'),
                        'total' => $total_pages,
                        'current' => $current_page
                    ));
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Log Details Modal -->
<div id="chatshop-log-details-modal" class="chatshop-modal" style="display: none;">
    <div class="chatshop-modal-content" style="max-width: 800px;">
        <span class="chatshop-modal-close">&times;</span>
        <div class="chatshop-modal-header">
            <h2><?php esc_html_e('Log Details', 'chatshop'); ?></h2>
        </div>
        <div class="chatshop-modal-body">
            <!-- Log details will be loaded here via AJAX -->
        </div>
    </div>
</div>

<style>
    .chatshop-log-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 20px;
        text-align: center;
    }

    .stat-value {
        display: block;
        font-size: 28px;
        font-weight: 600;
        color: #2271b1;
        margin-bottom: 5px;
    }

    .stat-label {
        display: block;
        font-size: 13px;
        color: #666;
        text-transform: uppercase;
    }

    .chatshop-log-filters {
        background: #f9f9f9;
        border: 1px solid #e5e5e5;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
    }

    .filter-row {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: flex-end;
    }

    .filter-item {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .filter-item label {
        font-size: 13px;
        font-weight: 600;
        color: #555;
    }

    .filter-item input[type="text"],
    .filter-item input[type="date"],
    .filter-item select {
        min-width: 150px;
    }

    .log-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .log-actions .dashicons {
        font-size: 16px;
        vertical-align: text-bottom;
    }

    .chatshop-logs-table {
        margin-top: 20px;
    }

    .log-level {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .log-level-error {
        background: #fecaca;
        color: #dc2626;
    }

    .log-level-warning {
        background: #fef3c7;
        color: #d97706;
    }

    .log-level-info {
        background: #dbeafe;
        color: #1e40af;
    }

    .log-level-debug {
        background: #e5e7eb;
        color: #4b5563;
    }

    .log-detail-content {
        font-family: monospace;
        font-size: 13px;
    }

    .log-detail-section {
        margin-bottom: 20px;
    }

    .log-detail-section h4 {
        margin: 0 0 10px 0;
        font-size: 14px;
        font-weight: 600;
        color: #333;
    }

    .log-context {
        background: #f9f9f9;
        border: 1px solid #e5e5e5;
        border-radius: 4px;
        padding: 15px;
        white-space: pre-wrap;
        word-break: break-all;
        max-height: 300px;
        overflow-y: auto;
    }

    .log-stacktrace {
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 4px;
        padding: 15px;
        white-space: pre-wrap;
        font-size: 12px;
        max-height: 300px;
        overflow-y: auto;
    }

    @media screen and (max-width: 782px) {
        .chatshop-log-filters {
            flex-direction: column;
            gap: 15px;
        }

        .filter-row {
            width: 100%;
        }

        .filter-item {
            width: 100%;
        }

        .filter-item input,
        .filter-item select {
            width: 100%;
        }
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // View log details
        $('.view-log-details').on('click', function() {
            var logId = $(this).data('log-id');
            var $modal = $('#chatshop-log-details-modal');
            var $modalBody = $modal.find('.chatshop-modal-body');

            // Show loading
            $modalBody.html('<p><?php esc_html_e('Loading log details...', 'chatshop'); ?></p>');
            $modal.fadeIn();

            // Load log details via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'chatshop_get_log_details',
                    log_id: logId,
                    nonce: '<?php echo wp_create_nonce('chatshop_log_details'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var log = response.data.log;
                        var html = '<div class="log-detail-content">';

                        // Basic info
                        html += '<div class="log-detail-section">';
                        html += '<h4><?php esc_html_e('Log Information', 'chatshop'); ?></h4>';
                        html += '<table class="form-table">';
                        html += '<tr><th><?php esc_html_e('Log ID', 'chatshop'); ?></th><td><code>' + log.id + '</code></td></tr>';
                        html += '<tr><th><?php esc_html_e('Timestamp', 'chatshop'); ?></th><td>' + log.created_at + '</td></tr>';
                        html += '<tr><th><?php esc_html_e('Level', 'chatshop'); ?></th><td><span class="log-level log-level-' + log.level + '">' + log.level.toUpperCase() + '</span></td></tr>';
                        html += '<tr><th><?php esc_html_e('Gateway', 'chatshop'); ?></th><td>' + log.gateway + '</td></tr>';
                        if (log.transaction_id) {
                            html += '<tr><th><?php esc_html_e('Transaction ID', 'chatshop'); ?></th><td><code>' + log.transaction_id + '</code></td></tr>';
                        }
                        html += '<tr><th><?php esc_html_e('Message', 'chatshop'); ?></th><td>' + log.message + '</td></tr>';
                        html += '</table>';
                        html += '</div>';

                        // Context
                        if (log.context) {
                            html += '<div class="log-detail-section">';
                            html += '<h4><?php esc_html_e('Context Data', 'chatshop'); ?></h4>';
                            html += '<div class="log-context">' + JSON.stringify(JSON.parse(log.context), null, 2) + '</div>';
                            html += '</div>';
                        }

                        // Stack trace (for errors)
                        if (log.stack_trace) {
                            html += '<div class="log-detail-section">';
                            html += '<h4><?php esc_html_e('Stack Trace', 'chatshop'); ?></h4>';
                            html += '<div class="log-stacktrace">' + log.stack_trace + '</div>';
                            html += '</div>';
                        }

                        // User info
                        if (log.user_id) {
                            html += '<div class="log-detail-section">';
                            html += '<h4><?php esc_html_e('User Information', 'chatshop'); ?></h4>';
                            html += '<table class="form-table">';
                            html += '<tr><th><?php esc_html_e('User ID', 'chatshop'); ?></th><td>' + log.user_id + '</td></tr>';
                            html += '<tr><th><?php esc_html_e('IP Address', 'chatshop'); ?></th><td>' + (log.ip_address || '—') + '</td></tr>';
                            html += '</table>';
                            html += '</div>';
                        }

                        html += '</div>';

                        $modalBody.html(html);
                    } else {
                        $modalBody.html('<p class="error"><?php esc_html_e('Error loading log details.', 'chatshop'); ?></p>');
                    }
                },
                error: function() {
                    $modalBody.html('<p class="error"><?php esc_html_e('Error loading log details.', 'chatshop'); ?></p>');
                }
            });
        });

        // Close modal
        $('#chatshop-log-details-modal .chatshop-modal-close').on('click', function() {
            $('#chatshop-log-details-modal').fadeOut();
        });

        // Close modal on outside click
        $(window).on('click', function(e) {
            if ($(e.target).hasClass('chatshop-modal')) {
                $('#chatshop-log-details-modal').fadeOut();
            }
        });

        // Export logs
        $('.export-logs-btn').on('click', function() {
            var button = $(this);
            var originalText = button.html();

            // Get current filter parameters
            var params = {
                action: 'chatshop_export_logs',
                nonce: '<?php echo wp_create_nonce('chatshop_export_logs'); ?>',
                gateway: $('#filter_gateway').val(),
                level: $('#filter_level').val(),
                date_from: $('#date_from').val(),
                date_to: $('#date_to').val(),
                search: $('#search').val(),
                format: $('#export_format').val()
            };

            // Show loading state
            button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> <?php esc_html_e('Exporting...', 'chatshop'); ?>');

            // Create and submit form for download
            var form = $('<form>', {
                method: 'POST',
                action: ajaxurl
            });

            $.each(params, function(key, value) {
                form.append($('<input>', {
                    type: 'hidden',
                    name: key,
                    value: value
                }));
            });

            form.appendTo('body').submit().remove();

            // Reset button
            setTimeout(function() {
                button.prop('disabled', false).html(originalText);
            }, 2000);
        });

        // Clear all logs
        $('.clear-logs-btn').on('click', function() {
            if (!confirm('<?php esc_html_e('Are you sure you want to clear all transaction logs? This action cannot be undone.', 'chatshop'); ?>')) {
                return;
            }

            var button = $(this);
            var originalText = button.html();

            button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> <?php esc_html_e('Clearing...', 'chatshop'); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'chatshop_clear_logs',
                    nonce: '<?php echo wp_create_nonce('chatshop_clear_logs'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Reload page to show empty logs
                        window.location.reload();
                    } else {
                        alert('<?php esc_html_e('Error clearing logs: ', 'chatshop'); ?>' + response.data.message);
                    }
                },
                error: function() {
                    alert('<?php esc_html_e('Error clearing logs.', 'chatshop'); ?>');
                },
                complete: function() {
                    button.prop('disabled', false).html(originalText);
                }
            });
        });
    });
</script>