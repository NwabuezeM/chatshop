<?php

/**
 * Payment Webhooks Settings Partial
 *
 * @package    ChatShop
 * @subpackage ChatShop/admin/partials
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

// Get webhook settings
$webhook_settings = get_option('chatshop_webhook_settings', array());
$webhook_secret = isset($webhook_settings['webhook_secret']) ? $webhook_settings['webhook_secret'] : '';
$webhook_retry = isset($webhook_settings['webhook_retry']) ? $webhook_settings['webhook_retry'] : true;
$webhook_retry_count = isset($webhook_settings['webhook_retry_count']) ? $webhook_settings['webhook_retry_count'] : 3;
$webhook_timeout = isset($webhook_settings['webhook_timeout']) ? $webhook_settings['webhook_timeout'] : 30;
$webhook_logging = isset($webhook_settings['webhook_logging']) ? $webhook_settings['webhook_logging'] : true;
$webhook_verification = isset($webhook_settings['webhook_verification']) ? $webhook_settings['webhook_verification'] : true;

// Get webhook URLs for each gateway
$webhook_urls = array();
$payment_manager = ChatShop_Payment_Manager::get_instance();
$registered_gateways = $payment_manager->get_registered_gateways();

foreach ($registered_gateways as $gateway_id => $gateway) {
    $webhook_urls[$gateway_id] = array(
        'name' => $gateway['name'],
        'url' => home_url('/wp-json/chatshop/v1/webhooks/' . $gateway_id),
        'status' => ChatShop_Payment_Webhook_Handler::get_webhook_status($gateway_id)
    );
}

// Get recent webhook events
$recent_webhooks = ChatShop_Payment_Webhook_Handler::get_recent_events(10);
?>

<div class="chatshop-settings-section">
    <h2><?php esc_html_e('Webhook Configuration', 'chatshop'); ?></h2>

    <div class="chatshop-webhook-notice">
        <span class="dashicons dashicons-info"></span>
        <p>
            <?php esc_html_e('Webhooks allow payment gateways to notify your site about payment events in real-time. Configure the webhook URLs in your payment gateway dashboards.', 'chatshop'); ?>
        </p>
    </div>

    <h3><?php esc_html_e('General Webhook Settings', 'chatshop'); ?></h3>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="webhook_secret"><?php esc_html_e('Webhook Secret', 'chatshop'); ?></label>
            </th>
            <td>
                <div class="webhook-secret-field">
                    <input type="text"
                        id="webhook_secret"
                        name="chatshop_webhook_settings[webhook_secret]"
                        value="<?php echo esc_attr($webhook_secret); ?>"
                        class="regular-text"
                        readonly />
                    <button type="button" class="button generate-secret-btn">
                        <?php esc_html_e('Generate New', 'chatshop'); ?>
                    </button>
                </div>
                <p class="description">
                    <?php esc_html_e('Secret key used to verify webhook authenticity. Keep this secure.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="webhook_verification"><?php esc_html_e('Signature Verification', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="chatshop-toggle">
                    <input type="checkbox"
                        id="webhook_verification"
                        name="chatshop_webhook_settings[webhook_verification]"
                        value="1"
                        <?php checked($webhook_verification, true); ?> />
                    <span class="chatshop-toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Verify webhook signatures to ensure requests are from legitimate sources.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="webhook_retry"><?php esc_html_e('Enable Retry', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="chatshop-toggle">
                    <input type="checkbox"
                        id="webhook_retry"
                        name="chatshop_webhook_settings[webhook_retry]"
                        value="1"
                        <?php checked($webhook_retry, true); ?> />
                    <span class="chatshop-toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Automatically retry failed webhook processing.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr class="retry-settings" style="display: <?php echo $webhook_retry ? 'table-row' : 'none'; ?>;">
            <th scope="row">
                <label for="webhook_retry_count"><?php esc_html_e('Retry Attempts', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="number"
                    id="webhook_retry_count"
                    name="chatshop_webhook_settings[webhook_retry_count]"
                    value="<?php echo esc_attr($webhook_retry_count); ?>"
                    min="1"
                    max="10"
                    class="small-text" />
                <p class="description">
                    <?php esc_html_e('Number of times to retry failed webhook processing.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="webhook_timeout"><?php esc_html_e('Processing Timeout', 'chatshop'); ?></label>
            </th>
            <td>
                <input type="number"
                    id="webhook_timeout"
                    name="chatshop_webhook_settings[webhook_timeout]"
                    value="<?php echo esc_attr($webhook_timeout); ?>"
                    min="5"
                    max="300"
                    class="small-text" />
                <span><?php esc_html_e('seconds', 'chatshop'); ?></span>
                <p class="description">
                    <?php esc_html_e('Maximum time allowed for webhook processing before timeout.', 'chatshop'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="webhook_logging"><?php esc_html_e('Enable Logging', 'chatshop'); ?></label>
            </th>
            <td>
                <label class="chatshop-toggle">
                    <input type="checkbox"
                        id="webhook_logging"
                        name="chatshop_webhook_settings[webhook_logging]"
                        value="1"
                        <?php checked($webhook_logging, true); ?> />
                    <span class="chatshop-toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Log all webhook events for debugging and audit purposes.', 'chatshop'); ?>
                </p>
            </td>
        </tr>
    </table>

    <h3><?php esc_html_e('Gateway Webhook URLs', 'chatshop'); ?></h3>

    <div class="chatshop-webhook-urls">
        <p><?php esc_html_e('Configure these webhook URLs in your payment gateway dashboards:', 'chatshop'); ?></p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Gateway', 'chatshop'); ?></th>
                    <th><?php esc_html_e('Webhook URL', 'chatshop'); ?></th>
                    <th><?php esc_html_e('Status', 'chatshop'); ?></th>
                    <th><?php esc_html_e('Actions', 'chatshop'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($webhook_urls as $gateway_id => $webhook_data) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($webhook_data['name']); ?></strong>
                        </td>
                        <td>
                            <code class="webhook-url"><?php echo esc_url($webhook_data['url']); ?></code>
                        </td>
                        <td>
                            <?php if ($webhook_data['status']['configured']) : ?>
                                <span class="chatshop-status chatshop-status-active">
                                    <?php esc_html_e('Configured', 'chatshop'); ?>
                                </span>
                            <?php else : ?>
                                <span class="chatshop-status chatshop-status-pending">
                                    <?php esc_html_e('Not Configured', 'chatshop'); ?>
                                </span>
                            <?php endif; ?>

                            <?php if (! empty($webhook_data['status']['last_received'])) : ?>
                                <br>
                                <small>
                                    <?php
                                    /* translators: %s: Time since last webhook */
                                    printf(
                                        esc_html__('Last received: %s ago', 'chatshop'),
                                        human_time_diff(strtotime($webhook_data['status']['last_received']))
                                    );
                                    ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button"
                                class="button button-small copy-webhook-btn"
                                data-url="<?php echo esc_url($webhook_data['url']); ?>">
                                <?php esc_html_e('Copy URL', 'chatshop'); ?>
                            </button>
                            <button type="button"
                                class="button button-small test-webhook-btn"
                                data-gateway="<?php echo esc_attr($gateway_id); ?>">
                                <?php esc_html_e('Test', 'chatshop'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h3><?php esc_html_e('Recent Webhook Events', 'chatshop'); ?></h3>

    <div class="chatshop-webhook-events">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 150px;"><?php esc_html_e('Timestamp', 'chatshop'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Gateway', 'chatshop'); ?></th>
                    <th style="width: 120px;"><?php esc_html_e('Event Type', 'chatshop'); ?></th>
                    <th><?php esc_html_e('Details', 'chatshop'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Status', 'chatshop'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Actions', 'chatshop'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (! empty($recent_webhooks)) : ?>
                    <?php foreach ($recent_webhooks as $event) : ?>
                        <tr>
                            <td>
                                <?php echo esc_html(ChatShop_Helper::format_date($event->created_at, 'M j, Y H:i:s')); ?>
                            </td>
                            <td>
                                <?php echo esc_html(ucfirst($event->gateway)); ?>
                            </td>
                            <td>
                                <?php echo esc_html($event->event_type); ?>
                            </td>
                            <td>
                                <?php
                                $details = json_decode($event->payload, true);
                                if (isset($details['reference'])) {
                                    echo '<code>' . esc_html($details['reference']) . '</code>';
                                }
                                if (isset($details['amount'])) {
                                    echo ' - ' . esc_html(ChatShop_Helper::format_price($details['amount'], $details['currency'] ?? 'USD'));
                                }
                                ?>
                            </td>
                            <td>
                                <span class="chatshop-status chatshop-status-<?php echo esc_attr($event->status); ?>">
                                    <?php echo esc_html(ucfirst($event->status)); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button"
                                    class="button button-small view-webhook-details"
                                    data-event-id="<?php echo esc_attr($event->id); ?>">
                                    <?php esc_html_e('View', 'chatshop'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">
                            <?php esc_html_e('No webhook events recorded yet.', 'chatshop'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if (! empty($recent_webhooks)) : ?>
            <p style="margin-top: 10px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-webhook-logs')); ?>" class="button">
                    <?php esc_html_e('View All Webhook Events', 'chatshop'); ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Webhook Details Modal -->
<div id="chatshop-webhook-details-modal" class="chatshop-modal" style="display: none;">
    <div class="chatshop-modal-content" style="max-width: 800px;">
        <span class="chatshop-modal-close">&times;</span>
        <div class="chatshop-modal-header">
            <h2><?php esc_html_e('Webhook Event Details', 'chatshop'); ?></h2>
        </div>
        <div class="chatshop-modal-body">
            <!-- Webhook details will be loaded here via AJAX -->
        </div>
    </div>
</div>

<style>
    .chatshop-webhook-notice {
        background: #f0f6fc;
        border-left: 4px solid #2271b1;
        padding: 15px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 15px;
    }

    .chatshop-webhook-notice .dashicons {
        font-size: 24px;
        color: #2271b1;
        margin-top: 2px;
    }

    .chatshop-webhook-notice p {
        margin: 0;
        flex: 1;
    }

    .webhook-secret-field {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .chatshop-webhook-urls {
        margin-top: 20px;
    }

    .chatshop-webhook-urls code {
        background: #f5f5f5;
        padding: 2px 5px;
        border-radius: 3px;
        font-size: 12px;
        user-select: all;
    }

    .chatshop-webhook-events {
        margin-top: 20px;
    }

    .webhook-payload {
        background: #f9f9f9;
        border: 1px solid #e5e5e5;
        border-radius: 4px;
        padding: 15px;
        margin-top: 15px;
        font-family: monospace;
        font-size: 12px;
        white-space: pre-wrap;
        word-break: break-all;
        max-height: 400px;
        overflow-y: auto;
    }

    .webhook-headers {
        background: #f9f9f9;
        border: 1px solid #e5e5e5;
        border-radius: 4px;
        padding: 15px;
        margin-top: 15px;
    }

    .webhook-headers table {
        width: 100%;
        border-collapse: collapse;
    }

    .webhook-headers td {
        padding: 5px 10px;
        border-bottom: 1px solid #e5e5e5;
    }

    .webhook-headers td:first-child {
        font-weight: 600;
        width: 200px;
    }

    .test-webhook-status {
        margin-top: 15px;
        padding: 15px;
        border-radius: 4px;
    }

    .test-webhook-status.success {
        background: #d4f4dd;
        border: 1px solid #00a32a;
        color: #00a32a;
    }

    .test-webhook-status.error {
        background: #fecaca;
        border: 1px solid #dc2626;
        color: #dc2626;
    }

    .chatshop-status-processing {
        background: #e0e7ff;
        color: #3730a3;
    }

    .chatshop-status-success {
        background: #d4f4dd;
        color: #00a32a;
    }

    .chatshop-status-failed {
        background: #fecaca;
        color: #dc2626;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Toggle retry settings
        $('#webhook_retry').on('change', function() {
            if ($(this).is(':checked')) {
                $('.retry-settings').show();
            } else {
                $('.retry-settings').hide();
            }
        });

        // Generate webhook secret
        $('.generate-secret-btn').on('click', function() {
            if (confirm('<?php esc_html_e('Generate a new webhook secret? This will invalidate the current secret.', 'chatshop'); ?>')) {
                // Generate random secret
                var secret = '';
                var possible = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                for (var i = 0; i < 32; i++) {
                    secret += possible.charAt(Math.floor(Math.random() * possible.length));
                }
                $('#webhook_secret').val(secret);
            }
        });

        // Copy webhook URL
        $('.copy-webhook-btn').on('click', function() {
            var url = $(this).data('url');
            var button = $(this);
            var originalText = button.text();

            // Create temporary input element
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(url).select();

            // Copy to clipboard
            document.execCommand('copy');
            $temp.remove();

            // Update button text
            button.text('<?php esc_html_e('Copied!', 'chatshop'); ?>');
            setTimeout(function() {
                button.text(originalText);
            }, 2000);
        });

        // Test webhook
        $('.test-webhook-btn').on('click', function() {
            var button = $(this);
            var gateway = button.data('gateway');
            var originalText = button.text();

            button.prop('disabled', true).text('<?php esc_html_e('Testing...', 'chatshop'); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'chatshop_test_webhook',
                    gateway: gateway,
                    nonce: '<?php echo wp_create_nonce('chatshop_test_webhook'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php esc_html_e('Test webhook sent successfully!', 'chatshop'); ?>');
                    } else {
                        alert('<?php esc_html_e('Test webhook failed: ', 'chatshop'); ?>' + response.data.message);
                    }
                },
                error: function() {
                    alert('<?php esc_html_e('Error sending test webhook.', 'chatshop'); ?>');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        });

        // View webhook details
        $('.view-webhook-details').on('click', function() {
            var eventId = $(this).data('event-id');
            var $modal = $('#chatshop-webhook-details-modal');
            var $modalBody = $modal.find('.chatshop-modal-body');

            // Show loading
            $modalBody.html('<p><?php esc_html_e('Loading webhook details...', 'chatshop'); ?></p>');
            $modal.fadeIn();

            // Load webhook details via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'chatshop_get_webhook_details',
                    event_id: eventId,
                    nonce: '<?php echo wp_create_nonce('chatshop_webhook_details'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var event = response.data.event;
                        var html = '<div class="webhook-detail-content">';

                        // Basic info
                        html += '<h3><?php esc_html_e('Event Information', 'chatshop'); ?></h3>';
                        html += '<table class="form-table">';
                        html += '<tr><th><?php esc_html_e('Event ID', 'chatshop'); ?></th><td><code>' + event.id + '</code></td></tr>';
                        html += '<tr><th><?php esc_html_e('Gateway', 'chatshop'); ?></th><td>' + event.gateway + '</td></tr>';
                        html += '<tr><th><?php esc_html_e('Event Type', 'chatshop'); ?></th><td>' + event.event_type + '</td></tr>';
                        html += '<tr><th><?php esc_html_e('Status', 'chatshop'); ?></th><td><span class="chatshop-status chatshop-status-' + event.status + '">' + event.status + '</span></td></tr>';
                        html += '<tr><th><?php esc_html_e('Timestamp', 'chatshop'); ?></th><td>' + event.created_at + '</td></tr>';
                        html += '</table>';

                        // Headers
                        if (event.headers) {
                            html += '<h3><?php esc_html_e('Request Headers', 'chatshop'); ?></h3>';
                            html += '<div class="webhook-headers"><table>';
                            var headers = JSON.parse(event.headers);
                            for (var key in headers) {
                                html += '<tr><td>' + key + '</td><td>' + headers[key] + '</td></tr>';
                            }
                            html += '</table></div>';
                        }

                        // Payload
                        html += '<h3><?php esc_html_e('Payload', 'chatshop'); ?></h3>';
                        html += '<div class="webhook-payload">' + JSON.stringify(JSON.parse(event.payload), null, 2) + '</div>';

                        // Response
                        if (event.response) {
                            html += '<h3><?php esc_html_e('Response', 'chatshop'); ?></h3>';
                            html += '<div class="webhook-payload">' + event.response + '</div>';
                        }

                        html += '</div>';

                        $modalBody.html(html);
                    } else {
                        $modalBody.html('<p class="error"><?php esc_html_e('Error loading webhook details.', 'chatshop'); ?></p>');
                    }
                },
                error: function() {
                    $modalBody.html('<p class="error"><?php esc_html_e('Error loading webhook details.', 'chatshop'); ?></p>');
                }
            });
        });

        // Close modal
        $('#chatshop-webhook-details-modal .chatshop-modal-close').on('click', function() {
            $('#chatshop-webhook-details-modal').fadeOut();
        });

        // Close modal on outside click
        $(window).on('click', function(e) {
            if ($(e.target).hasClass('chatshop-modal')) {
                $('#chatshop-webhook-details-modal').fadeOut();
            }
        });
    });
</script>