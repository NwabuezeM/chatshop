<?php

/**
 * ChatShop Admin Dashboard
 *
 * This file displays the main dashboard for the ChatShop plugin, providing
 * an overview of key metrics, recent activities, and quick actions.
 *
 * @link       https://www.chatshop.com
 * @since      1.0.0
 *
 * @package    ChatShop
 * @subpackage ChatShop/admin/partials
 */

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

// Get dashboard data
$dashboard_data = $this->get_dashboard_data();
$active_components = $this->get_active_components();
$recent_transactions = $this->get_recent_transactions(5);
$whatsapp_metrics = $this->get_whatsapp_metrics();
$gateway_performance = $this->get_gateway_performance();
?>

<div class="wrap chatshop-dashboard">
    <h1 class="wp-heading-inline">
        <?php echo esc_html(get_admin_page_title()); ?>
        <span class="chatshop-version">v<?php echo esc_html(CHATSHOP_VERSION); ?></span>
    </h1>

    <?php
    // Display admin notices
    settings_errors('chatshop_messages');
    ?>

    <!-- Quick Stats Overview -->
    <div class="chatshop-stats-overview">
        <div class="chatshop-stat-box">
            <div class="stat-icon">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="stat-content">
                <h3><?php esc_html_e('Total Revenue', 'chatshop'); ?></h3>
                <p class="stat-number">
                    <?php echo esc_html(wc_price($dashboard_data['total_revenue'])); ?>
                </p>
                <span class="stat-change <?php echo $dashboard_data['revenue_change'] >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $dashboard_data['revenue_change'] >= 0 ? '+' : ''; ?>
                    <?php echo esc_html($dashboard_data['revenue_change']); ?>%
                </span>
            </div>
        </div>

        <div class="chatshop-stat-box">
            <div class="stat-icon">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div class="stat-content">
                <h3><?php esc_html_e('Transactions', 'chatshop'); ?></h3>
                <p class="stat-number"><?php echo esc_html(number_format($dashboard_data['total_transactions'])); ?></p>
                <span class="stat-subtitle"><?php esc_html_e('Last 30 days', 'chatshop'); ?></span>
            </div>
        </div>

        <div class="chatshop-stat-box">
            <div class="stat-icon">
                <span class="dashicons dashicons-format-chat"></span>
            </div>
            <div class="stat-content">
                <h3><?php esc_html_e('WhatsApp Messages', 'chatshop'); ?></h3>
                <p class="stat-number"><?php echo esc_html(number_format($whatsapp_metrics['messages_sent'])); ?></p>
                <span class="stat-subtitle"><?php esc_html_e('Sent this month', 'chatshop'); ?></span>
            </div>
        </div>

        <div class="chatshop-stat-box">
            <div class="stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="stat-content">
                <h3><?php esc_html_e('Conversion Rate', 'chatshop'); ?></h3>
                <p class="stat-number"><?php echo esc_html($dashboard_data['conversion_rate']); ?>%</p>
                <span class="stat-subtitle"><?php esc_html_e('WhatsApp to Payment', 'chatshop'); ?></span>
            </div>
        </div>
    </div>

    <div class="chatshop-dashboard-grid">
        <!-- Left Column -->
        <div class="chatshop-dashboard-left">

            <!-- Active Components -->
            <div class="chatshop-card">
                <h2><?php esc_html_e('Active Components', 'chatshop'); ?></h2>
                <div class="chatshop-components-status">
                    <?php foreach ($active_components as $component) : ?>
                        <div class="component-item <?php echo $component['active'] ? 'active' : 'inactive'; ?>">
                            <span class="component-icon dashicons <?php echo esc_attr($component['icon']); ?>"></span>
                            <div class="component-info">
                                <h4><?php echo esc_html($component['name']); ?></h4>
                                <p><?php echo esc_html($component['status']); ?></p>
                            </div>
                            <span class="component-status">
                                <?php if ($component['active']) : ?>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                <?php else : ?>
                                    <span class="dashicons dashicons-warning"></span>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="chatshop-card-footer">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-settings')); ?>">
                        <?php esc_html_e('Manage Components', 'chatshop'); ?> →
                    </a>
                </p>
            </div>

            <!-- Recent Transactions -->
            <div class="chatshop-card">
                <h2>
                    <?php esc_html_e('Recent Transactions', 'chatshop'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-transactions')); ?>" class="view-all">
                        <?php esc_html_e('View All', 'chatshop'); ?>
                    </a>
                </h2>
                <?php if (! empty($recent_transactions)) : ?>
                    <table class="chatshop-transactions-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Customer', 'chatshop'); ?></th>
                                <th><?php esc_html_e('Amount', 'chatshop'); ?></th>
                                <th><?php esc_html_e('Gateway', 'chatshop'); ?></th>
                                <th><?php esc_html_e('Status', 'chatshop'); ?></th>
                                <th><?php esc_html_e('Date', 'chatshop'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_transactions as $transaction) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($transaction['customer_name']); ?></strong>
                                        <br>
                                        <small><?php echo esc_html($transaction['customer_phone']); ?></small>
                                    </td>
                                    <td><?php echo esc_html(wc_price($transaction['amount'])); ?></td>
                                    <td>
                                        <span class="gateway-badge <?php echo esc_attr($transaction['gateway']); ?>">
                                            <?php echo esc_html(ucfirst($transaction['gateway'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo esc_attr($transaction['status']); ?>">
                                            <?php echo esc_html(ucfirst($transaction['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo esc_html(human_time_diff(strtotime($transaction['date']), current_time('timestamp'))); ?>
                                        <?php esc_html_e('ago', 'chatshop'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="chatshop-no-data">
                        <?php esc_html_e('No transactions yet. Start by setting up your payment gateways and WhatsApp integration.', 'chatshop'); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- WhatsApp Performance -->
            <div class="chatshop-card">
                <h2><?php esc_html_e('WhatsApp Performance', 'chatshop'); ?></h2>
                <div class="chatshop-whatsapp-metrics">
                    <div class="metric-row">
                        <span class="metric-label"><?php esc_html_e('Messages Sent Today', 'chatshop'); ?></span>
                        <span class="metric-value"><?php echo esc_html(number_format($whatsapp_metrics['messages_today'])); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label"><?php esc_html_e('Delivery Rate', 'chatshop'); ?></span>
                        <span class="metric-value"><?php echo esc_html($whatsapp_metrics['delivery_rate']); ?>%</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label"><?php esc_html_e('Read Rate', 'chatshop'); ?></span>
                        <span class="metric-value"><?php echo esc_html($whatsapp_metrics['read_rate']); ?>%</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label"><?php esc_html_e('Response Rate', 'chatshop'); ?></span>
                        <span class="metric-value"><?php echo esc_html($whatsapp_metrics['response_rate']); ?>%</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label"><?php esc_html_e('Active Campaigns', 'chatshop'); ?></span>
                        <span class="metric-value"><?php echo esc_html($whatsapp_metrics['active_campaigns']); ?></span>
                    </div>
                </div>
                <p class="chatshop-card-footer">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-whatsapp')); ?>">
                        <?php esc_html_e('Manage WhatsApp', 'chatshop'); ?> →
                    </a>
                </p>
            </div>
        </div>

        <!-- Right Column -->
        <div class="chatshop-dashboard-right">

            <!-- Quick Actions -->
            <div class="chatshop-card">
                <h2><?php esc_html_e('Quick Actions', 'chatshop'); ?></h2>
                <div class="chatshop-quick-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-create-campaign')); ?>" class="quick-action-button">
                        <span class="dashicons dashicons-megaphone"></span>
                        <?php esc_html_e('Create Campaign', 'chatshop'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-payment-link')); ?>" class="quick-action-button">
                        <span class="dashicons dashicons-admin-links"></span>
                        <?php esc_html_e('Generate Payment Link', 'chatshop'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-import-contacts')); ?>" class="quick-action-button">
                        <span class="dashicons dashicons-upload"></span>
                        <?php esc_html_e('Import Contacts', 'chatshop'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-analytics')); ?>" class="quick-action-button">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <?php esc_html_e('View Analytics', 'chatshop'); ?>
                    </a>
                </div>
            </div>

            <!-- Payment Gateway Performance -->
            <div class="chatshop-card">
                <h2><?php esc_html_e('Gateway Performance', 'chatshop'); ?></h2>
                <div class="chatshop-gateway-performance">
                    <?php foreach ($gateway_performance as $gateway) : ?>
                        <div class="gateway-item">
                            <div class="gateway-header">
                                <img src="<?php echo esc_url(CHATSHOP_PLUGIN_URL . 'assets/icons/' . $gateway['id'] . '.svg'); ?>"
                                    alt="<?php echo esc_attr($gateway['name']); ?>"
                                    class="gateway-logo">
                                <h4><?php echo esc_html($gateway['name']); ?></h4>
                            </div>
                            <div class="gateway-stats">
                                <div class="stat-item">
                                    <span class="stat-label"><?php esc_html_e('Volume', 'chatshop'); ?></span>
                                    <span class="stat-value"><?php echo esc_html(wc_price($gateway['volume'])); ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label"><?php esc_html_e('Success Rate', 'chatshop'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($gateway['success_rate']); ?>%</span>
                                </div>
                            </div>
                            <div class="gateway-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo esc_attr($gateway['usage_percentage']); ?>%"></div>
                                </div>
                                <span class="progress-label"><?php echo esc_html($gateway['usage_percentage']); ?>% <?php esc_html_e('of total volume', 'chatshop'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- System Health -->
            <div class="chatshop-card">
                <h2><?php esc_html_e('System Health', 'chatshop'); ?></h2>
                <div class="chatshop-system-health">
                    <?php
                    $health_checks = $this->get_system_health_checks();
                    foreach ($health_checks as $check) :
                    ?>
                        <div class="health-check-item <?php echo esc_attr($check['status']); ?>">
                            <span class="dashicons dashicons-<?php echo $check['status'] === 'good' ? 'yes-alt' : ($check['status'] === 'warning' ? 'warning' : 'dismiss'); ?>"></span>
                            <div class="health-check-content">
                                <strong><?php echo esc_html($check['label']); ?></strong>
                                <p><?php echo esc_html($check['message']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="chatshop-card-footer">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-system-status')); ?>">
                        <?php esc_html_e('View System Status', 'chatshop'); ?> →
                    </a>
                </p>
            </div>

            <!-- Getting Started Guide -->
            <?php if ($this->is_new_installation()) : ?>
                <div class="chatshop-card chatshop-getting-started">
                    <h2><?php esc_html_e('Getting Started', 'chatshop'); ?></h2>
                    <ol class="getting-started-steps">
                        <li class="<?php echo $this->is_step_completed('whatsapp_setup') ? 'completed' : ''; ?>">
                            <span class="step-number">1</span>
                            <div class="step-content">
                                <h4><?php esc_html_e('Connect WhatsApp', 'chatshop'); ?></h4>
                                <p><?php esc_html_e('Set up your WhatsApp Business API credentials.', 'chatshop'); ?></p>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-settings&tab=whatsapp')); ?>">
                                    <?php esc_html_e('Configure', 'chatshop'); ?> →
                                </a>
                            </div>
                        </li>
                        <li class="<?php echo $this->is_step_completed('payment_setup') ? 'completed' : ''; ?>">
                            <span class="step-number">2</span>
                            <div class="step-content">
                                <h4><?php esc_html_e('Configure Payment Gateway', 'chatshop'); ?></h4>
                                <p><?php esc_html_e('Add at least one payment gateway.', 'chatshop'); ?></p>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-settings&tab=payments')); ?>">
                                    <?php esc_html_e('Set Up', 'chatshop'); ?> →
                                </a>
                            </div>
                        </li>
                        <li class="<?php echo $this->is_step_completed('first_campaign') ? 'completed' : ''; ?>">
                            <span class="step-number">3</span>
                            <div class="step-content">
                                <h4><?php esc_html_e('Create Your First Campaign', 'chatshop'); ?></h4>
                                <p><?php esc_html_e('Start engaging customers via WhatsApp.', 'chatshop'); ?></p>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-create-campaign')); ?>">
                                    <?php esc_html_e('Create', 'chatshop'); ?> →
                                </a>
                            </div>
                        </li>
                    </ol>
                    <div class="getting-started-footer">
                        <a href="<?php echo esc_url('https://docs.chatshop.com'); ?>" target="_blank" class="button">
                            <span class="dashicons dashicons-book"></span>
                            <?php esc_html_e('View Documentation', 'chatshop'); ?>
                        </a>
                        <a href="<?php echo esc_url('https://chatshop.com/support'); ?>" target="_blank" class="button">
                            <span class="dashicons dashicons-sos"></span>
                            <?php esc_html_e('Get Support', 'chatshop'); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- News & Updates -->
            <div class="chatshop-card">
                <h2><?php esc_html_e('News & Updates', 'chatshop'); ?></h2>
                <div class="chatshop-news">
                    <?php
                    $news_items = $this->get_news_updates();
                    if (! empty($news_items)) :
                        foreach ($news_items as $news) :
                    ?>
                            <div class="news-item">
                                <span class="news-date"><?php echo esc_html($news['date']); ?></span>
                                <h4><?php echo esc_html($news['title']); ?></h4>
                                <p><?php echo esc_html($news['excerpt']); ?></p>
                                <a href="<?php echo esc_url($news['link']); ?>" target="_blank">
                                    <?php esc_html_e('Read more', 'chatshop'); ?> →
                                </a>
                            </div>
                        <?php
                        endforeach;
                    else :
                        ?>
                        <p><?php esc_html_e('No updates available at this time.', 'chatshop'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Chart -->
    <div class="chatshop-card chatshop-full-width">
        <h2>
            <?php esc_html_e('Revenue Overview', 'chatshop'); ?>
            <div class="chart-controls">
                <select id="chatshop-chart-period" class="chatshop-select">
                    <option value="7days"><?php esc_html_e('Last 7 Days', 'chatshop'); ?></option>
                    <option value="30days" selected><?php esc_html_e('Last 30 Days', 'chatshop'); ?></option>
                    <option value="90days"><?php esc_html_e('Last 90 Days', 'chatshop'); ?></option>
                    <option value="12months"><?php esc_html_e('Last 12 Months', 'chatshop'); ?></option>
                </select>
            </div>
        </h2>
        <div class="chatshop-chart-container">
            <canvas id="chatshop-revenue-chart" width="400" height="100"></canvas>
        </div>
        <div class="chart-legend">
            <div class="legend-item">
                <span class="legend-color" style="background-color: #4CAF50;"></span>
                <?php esc_html_e('WhatsApp Payments', 'chatshop'); ?>
            </div>
            <div class="legend-item">
                <span class="legend-color" style="background-color: #2196F3;"></span>
                <?php esc_html_e('Direct Payments', 'chatshop'); ?>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Initialize revenue chart
        if (typeof ChatShopAdmin !== 'undefined' && ChatShopAdmin.initRevenueChart) {
            ChatShopAdmin.initRevenueChart();
        }

        // Auto-refresh dashboard data every 60 seconds
        setInterval(function() {
            if (typeof ChatShopAdmin !== 'undefined' && ChatShopAdmin.refreshDashboard) {
                ChatShopAdmin.refreshDashboard();
            }
        }, 60000);
    });
</script>