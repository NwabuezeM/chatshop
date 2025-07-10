<?php

/**
 * ChatShop Settings Templates and Views - Complete
 *
 * File: admin/class-chatshop-settings-templates.php
 *
 * @package ChatShop
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Settings Templates Class
 */
class ChatShop_Settings_Templates
{

    /**
     * Settings instance
     *
     * @var ChatShop_Settings
     */
    private $settings;

    /**
     * Field renderer instance
     *
     * @var ChatShop_Settings_Renderer
     */
    private $renderer;

    /**
     * Constructor
     */
    public function __construct($settings, $renderer)
    {
        $this->settings = $settings;
        $this->renderer = $renderer;
    }

    /**
     * Render main settings page
     */
    public function render_settings_page()
    {
        $current_tab = $this->settings->get_current_tab();
        $tabs = $this->settings->get_tabs();

?>
        <div class="wrap chatshop-settings-wrap">
            <?php $this->render_page_header(); ?>

            <div class="chatshop-settings-container">
                <div class="chatshop-settings-nav">
                    <?php $this->render_tab_navigation($tabs, $current_tab); ?>
                </div>

                <div class="chatshop-settings-content">
                    <?php $this->render_notices(); ?>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="chatshop-settings-form">
                        <?php wp_nonce_field('chatshop_save_settings', 'chatshop_settings_nonce'); ?>
                        <input type="hidden" name="action" value="chatshop_save_settings" />
                        <input type="hidden" name="current_tab" value="<?php echo esc_attr($current_tab); ?>" />

                        <div class="chatshop-settings-sections">
                            <?php $this->render_tab_content($current_tab); ?>
                        </div>

                        <div class="chatshop-settings-actions">
                            <?php $this->render_form_actions($current_tab); ?>
                        </div>
                    </form>
                </div>

                <div class="chatshop-settings-sidebar">
                    <?php $this->render_sidebar(); ?>
                </div>
            </div>
        </div>

        <?php $this->render_modals(); ?>
        <?php $this->render_styles(); ?>
    <?php
    }

    /**
     * Render page header
     */
    private function render_page_header()
    {
    ?>
        <div class="chatshop-page-header">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php esc_html_e('ChatShop Settings', 'chatshop'); ?>
            </h1>

            <div class="chatshop-header-actions">
                <button type="button" class="page-title-action chatshop-export-settings" data-nonce="<?php echo esc_attr(wp_create_nonce('chatshop_export_settings')); ?>">
                    <?php esc_html_e('Export Settings', 'chatshop'); ?>
                </button>
            </div>

            <p class="description">
                <?php esc_html_e('Configure ChatShop to connect WhatsApp marketing with payment processing for your WooCommerce store.', 'chatshop'); ?>
            </p>
        </div>
    <?php
    }

    /**
     * Render tab navigation
     */
    private function render_tab_navigation($tabs, $current_tab)
    {
    ?>
        <div class="chatshop-nav-tabs">
            <?php foreach ($tabs as $tab_key => $tab): ?>
                <a href="<?php echo esc_url($this->settings->get_settings_url($tab_key)); ?>"
                    class="chatshop-nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons <?php echo esc_attr($tab['icon']); ?>"></span>
                    <span class="tab-label"><?php echo esc_html($tab['title']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php
    }

    /**
     * Render notices
     */
    private function render_notices()
    {
        // Success message
        $success_message = get_transient('chatshop_settings_message');
        if ($success_message) {
            printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($success_message));
            delete_transient('chatshop_settings_message');
        }

        // Error message
        $error_message = get_transient('chatshop_settings_error');
        if ($error_message) {
            printf('<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html($error_message));
            delete_transient('chatshop_settings_error');
        }

        // Validation errors
        $validation_errors = get_transient('chatshop_settings_errors');
        if ($validation_errors && is_array($validation_errors)) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>' . esc_html__('Please fix the following errors:', 'chatshop') . '</strong></p>';
            echo '<ul>';
            foreach ($validation_errors as $error) {
                printf('<li>%s</li>', esc_html($error));
            }
            echo '</ul>';
            echo '</div>';
            delete_transient('chatshop_settings_errors');
        }

        // Component warnings
        $this->render_component_warnings();
    }

    /**
     * Render component warnings
     */
    private function render_component_warnings()
    {
        $warnings = array();

        if (!$this->settings->is_component_enabled('payment')) {
            $warnings[] = __('Payment component is disabled. Payment processing will not work.', 'chatshop');
        }

        if (!$this->settings->is_component_enabled('whatsapp')) {
            $warnings[] = __('WhatsApp component is disabled. WhatsApp integration will not work.', 'chatshop');
        }

        if (!empty($warnings)) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>' . esc_html__('Configuration Issues:', 'chatshop') . '</strong></p>';
            echo '<ul>';
            foreach ($warnings as $warning) {
                printf('<li>%s</li>', esc_html($warning));
            }
            echo '</ul>';
            echo '</div>';
        }
    }

    /**
     * Render tab content
     */
    private function render_tab_content($current_tab)
    {
        $fields = $this->settings->get_settings_fields($current_tab);

        if (empty($fields)) {
            $this->render_empty_tab_content($current_tab);
            return;
        }

        // Group fields by section
        $sections = array();
        foreach ($fields as $field_id => $field) {
            $section = isset($field['section']) ? $field['section'] : 'default';
            if (!isset($sections[$section])) {
                $sections[$section] = array();
            }
            $sections[$section][$field_id] = $field;
        }

        foreach ($sections as $section_id => $section_fields) {
            $this->render_settings_section($current_tab, $section_id, $section_fields);
        }
    }

    /**
     * Render settings section
     */
    private function render_settings_section($tab, $section_id, $fields)
    {
        $section_title = $this->get_section_title($tab, $section_id);

    ?>
        <div class="chatshop-settings-section">
            <h3><?php echo esc_html($section_title); ?></h3>

            <table class="form-table">
                <tbody>
                    <?php foreach ($fields as $field_id => $field): ?>
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr("{$tab}_{$field_id}"); ?>">
                                    <?php echo esc_html($field['title']); ?>
                                    <?php if (isset($field['required']) && $field['required']): ?>
                                        <span class="required">*</span>
                                    <?php endif; ?>
                                </label>
                            </th>
                            <td>
                                <?php
                                $field_name = "{$tab}_{$field_id}";
                                $field_value = $this->settings->get($field_id, $field['default'], $tab);
                                $this->renderer->render_field($field_name, $field_value, $field, $tab);
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php
    }

    /**
     * Render empty tab content
     */
    private function render_empty_tab_content($tab)
    {
    ?>
        <div class="chatshop-empty-tab">
            <div class="chatshop-empty-icon">
                <span class="dashicons dashicons-admin-settings"></span>
            </div>
            <h3><?php esc_html_e('No Settings Available', 'chatshop'); ?></h3>
            <p><?php esc_html_e('This section will be populated with settings once components are registered.', 'chatshop'); ?></p>
        </div>
    <?php
    }

    /**
     * Render form actions
     */
    private function render_form_actions($current_tab)
    {
    ?>
        <div class="chatshop-form-actions">
            <div class="chatshop-primary-actions">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e('Save Settings', 'chatshop'); ?>
                </button>

                <button type="button" class="button button-secondary chatshop-validate-settings">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e('Validate Settings', 'chatshop'); ?>
                </button>
            </div>

            <div class="chatshop-secondary-actions">
                <button type="button" class="button chatshop-reset-tab" data-tab="<?php echo esc_attr($current_tab); ?>">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Reset Tab', 'chatshop'); ?>
                </button>

                <button type="button" class="button chatshop-import-settings">
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e('Import Settings', 'chatshop'); ?>
                </button>
            </div>
        </div>
    <?php
    }

    /**
     * Render sidebar
     */
    private function render_sidebar()
    {
    ?>
        <div class="chatshop-sidebar-widgets">
            <?php
            $this->render_system_status_widget();
            $this->render_quick_actions_widget();
            $this->render_help_widget();
            ?>
        </div>
    <?php
    }

    /**
     * Render system status widget
     */
    private function render_system_status_widget()
    {
        $plugin_version = defined('CHATSHOP_VERSION') ? CHATSHOP_VERSION : '1.0.0';
        $wp_version = get_bloginfo('version');
        $wc_version = defined('WC_VERSION') ? WC_VERSION : 'Not installed';

    ?>
        <div class="chatshop-sidebar-widget">
            <div class="chatshop-widget-header">
                <h3>
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php esc_html_e('System Status', 'chatshop'); ?>
                </h3>
            </div>
            <div class="chatshop-widget-content">
                <div class="chatshop-status-grid">
                    <div class="status-item">
                        <span class="label"><?php esc_html_e('ChatShop:', 'chatshop'); ?></span>
                        <span class="value"><?php echo esc_html($plugin_version); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="label"><?php esc_html_e('WordPress:', 'chatshop'); ?></span>
                        <span class="value"><?php echo esc_html($wp_version); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="label"><?php esc_html_e('WooCommerce:', 'chatshop'); ?></span>
                        <span class="value"><?php echo esc_html($wc_version); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="label"><?php esc_html_e('PHP:', 'chatshop'); ?></span>
                        <span class="value"><?php echo esc_html(PHP_VERSION); ?></span>
                    </div>
                </div>

                <div class="chatshop-component-status">
                    <h4><?php esc_html_e('Components', 'chatshop'); ?></h4>
                    <?php
                    $components = array('payment', 'whatsapp', 'analytics', 'integration');
                    foreach ($components as $component):
                        $enabled = $this->settings->is_component_enabled($component);
                        $status_class = $enabled ? 'enabled' : 'disabled';
                        $status_text = $enabled ? __('Enabled', 'chatshop') : __('Disabled', 'chatshop');
                    ?>
                        <div class="component-status <?php echo esc_attr($status_class); ?>">
                            <span class="component-name"><?php echo esc_html(ucfirst($component)); ?></span>
                            <span class="component-indicator">
                                <span class="dashicons dashicons-<?php echo $enabled ? 'yes' : 'no'; ?>"></span>
                                <?php echo esc_html($status_text); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Render quick actions widget
     */
    private function render_quick_actions_widget()
    {
    ?>
        <div class="chatshop-sidebar-widget">
            <div class="chatshop-widget-header">
                <h3>
                    <span class="dashicons dashicons-performance"></span>
                    <?php esc_html_e('Quick Actions', 'chatshop'); ?>
                </h3>
            </div>
            <div class="chatshop-widget-content">
                <div class="chatshop-quick-actions">
                    <button type="button" class="button button-small chatshop-clear-cache">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Clear Cache', 'chatshop'); ?>
                    </button>

                    <button type="button" class="button button-small chatshop-test-connections">
                        <span class="dashicons dashicons-admin-links"></span>
                        <?php esc_html_e('Test Connections', 'chatshop'); ?>
                    </button>

                    <a href="<?php echo esc_url(admin_url('admin.php?page=chatshop-logs')); ?>" class="button button-small">
                        <span class="dashicons dashicons-text-page"></span>
                        <?php esc_html_e('View Logs', 'chatshop'); ?>
                    </a>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Render help widget
     */
    private function render_help_widget()
    {
    ?>
        <div class="chatshop-sidebar-widget">
            <div class="chatshop-widget-header">
                <h3>
                    <span class="dashicons dashicons-editor-help"></span>
                    <?php esc_html_e('Need Help?', 'chatshop'); ?>
                </h3>
            </div>
            <div class="chatshop-widget-content">
                <p><?php esc_html_e('Get started with these helpful resources:', 'chatshop'); ?></p>

                <div class="chatshop-help-links">
                    <a href="#" class="help-link" target="_blank">
                        <span class="dashicons dashicons-book"></span>
                        <?php esc_html_e('Documentation', 'chatshop'); ?>
                    </a>

                    <a href="#" class="help-link" target="_blank">
                        <span class="dashicons dashicons-video-alt3"></span>
                        <?php esc_html_e('Video Tutorials', 'chatshop'); ?>
                    </a>

                    <a href="#" class="help-link" target="_blank">
                        <span class="dashicons dashicons-sos"></span>
                        <?php esc_html_e('Get Support', 'chatshop'); ?>
                    </a>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Render modals
     */
    private function render_modals()
    {
        $this->render_import_modal();
        $this->render_reset_modal();
        $this->render_validation_modal();
    }

    /**
     * Render import modal
     */
    private function render_import_modal()
    {
    ?>
        <div id="chatshop-import-modal" class="chatshop-modal" style="display: none;">
            <div class="chatshop-modal-content">
                <div class="chatshop-modal-header">
                    <h3><?php esc_html_e('Import Settings', 'chatshop'); ?></h3>
                    <button type="button" class="chatshop-modal-close">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>

                <div class="chatshop-modal-body">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field('chatshop_import_settings', 'chatshop_import_nonce'); ?>
                        <input type="hidden" name="action" value="chatshop_import_settings" />

                        <p><?php esc_html_e('Select a ChatShop settings file to import:', 'chatshop'); ?></p>

                        <input type="file" name="settings_file" accept=".json" required />

                        <div class="chatshop-modal-actions">
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e('Import Settings', 'chatshop'); ?>
                            </button>
                            <button type="button" class="button chatshop-modal-close">
                                <?php esc_html_e('Cancel', 'chatshop'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Render reset modal
     */
    private function render_reset_modal()
    {
    ?>
        <div id="chatshop-reset-modal" class="chatshop-modal" style="display: none;">
            <div class="chatshop-modal-content">
                <div class="chatshop-modal-header">
                    <h3><?php esc_html_e('Reset Settings', 'chatshop'); ?></h3>
                    <button type="button" class="chatshop-modal-close">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>

                <div class="chatshop-modal-body">
                    <div class="chatshop-warning">
                        <span class="dashicons dashicons-warning"></span>
                        <p><?php esc_html_e('This will reset all settings to their default values. This action cannot be undone.', 'chatshop'); ?></p>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('chatshop_reset_settings', 'chatshop_reset_nonce'); ?>
                        <input type="hidden" name="action" value="chatshop_reset_settings" />
                        <input type="hidden" name="current_tab" id="reset_current_tab" value="" />

                        <div class="chatshop-modal-actions">
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e('Reset Settings', 'chatshop'); ?>
                            </button>
                            <button type="button" class="button chatshop-modal-close">
                                <?php esc_html_e('Cancel', 'chatshop'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Render validation modal
     */
    private function render_validation_modal()
    {
    ?>
        <div id="chatshop-validation-modal" class="chatshop-modal" style="display: none;">
            <div class="chatshop-modal-content">
                <div class="chatshop-modal-header">
                    <h3><?php esc_html_e('Settings Validation', 'chatshop'); ?></h3>
                    <button type="button" class="chatshop-modal-close">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>

                <div class="chatshop-modal-body">
                    <div id="validation-results">
                        <div class="chatshop-validation-loading">
                            <span class="spinner is-active"></span>
                            <p><?php esc_html_e('Validating settings...', 'chatshop'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Get section title
     */
    private function get_section_title($tab, $section_id)
    {
        $section_titles = array(
            'general' => array(
                'basic' => __('Basic Settings', 'chatshop'),
            ),
            'security' => array(
                'encryption' => __('Data Encryption', 'chatshop'),
                'webhooks' => __('Webhook Security', 'chatshop'),
                'rate_limiting' => __('Rate Limiting', 'chatshop'),
                'access_control' => __('Access Control', 'chatshop'),
                'ssl' => __('SSL/TLS Settings', 'chatshop'),
            ),
            'notifications' => array(
                'admin' => __('Admin Notifications', 'chatshop'),
                'email' => __('Email Notifications', 'chatshop'),
            ),
            'performance' => array(
                'caching' => __('Caching Configuration', 'chatshop'),
                'processing' => __('Background Processing', 'chatshop'),
                'api' => __('API Performance', 'chatshop'),
            ),
            'components' => array(
                'core_components' => __('Core Components', 'chatshop'),
            ),
        );

        if (isset($section_titles[$tab][$section_id])) {
            return $section_titles[$tab][$section_id];
        }

        return ucfirst(str_replace('_', ' ', $section_id));
    }

    /**
     * Render styles
     */
    private function render_styles()
    {
    ?>
        <style>
            .chatshop-settings-wrap {
                margin: 20px 20px 0 2px;
            }

            .chatshop-page-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px 0;
                border-bottom: 1px solid #ccd0d4;
            }

            .chatshop-page-header h1 {
                display: flex;
                align-items: center;
                gap: 10px;
                margin: 0;
            }

            .chatshop-settings-container {
                display: flex;
                gap: 20px;
                margin-top: 20px;
            }

            .chatshop-settings-nav {
                flex: 0 0 200px;
            }

            .chatshop-nav-tabs {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 3px;
            }

            .chatshop-nav-tab {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 12px 15px;
                text-decoration: none;
                color: #50575e;
                border-bottom: 1px solid #f0f0f0;
                transition: all 0.2s ease;
            }

            .chatshop-nav-tab:hover {
                background: #f8f9fa;
                color: #0073aa;
            }

            .chatshop-nav-tab.nav-tab-active {
                background: #0073aa;
                color: #fff;
                font-weight: 600;
            }

            .chatshop-nav-tab:last-child {
                border-bottom: none;
            }

            .chatshop-settings-content {
                flex: 1;
                background: #fff;
                padding: 20px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            }

            .chatshop-settings-sidebar {
                flex: 0 0 280px;
            }

            .chatshop-hidden {
                display: none !important;
            }

            .chatshop-field-wrapper {
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 1px solid #f0f0f0;
            }

            .chatshop-field-wrapper:last-child {
                border-bottom: none;
            }

            .chatshop-settings-section {
                margin-bottom: 30px;
            }

            .chatshop-settings-section h3 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 2px solid #0073aa;
                color: #0073aa;
            }

            .chatshop-form-actions {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
            }

            .chatshop-primary-actions,
            .chatshop-secondary-actions {
                display: flex;
                gap: 10px;
            }

            .chatshop-form-actions .button {
                display: flex;
                align-items: center;
                gap: 5px;
            }

            .chatshop-sidebar-widgets {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }

            .chatshop-sidebar-widget {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 3px;
            }

            .chatshop-widget-header {
                padding: 15px;
                border-bottom: 1px solid #f0f0f0;
                background: #f8f9fa;
            }

            .chatshop-widget-header h3 {
                margin: 0;
                font-size: 14px;
                color: #1d2327;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .chatshop-widget-content {
                padding: 15px;
            }

            .chatshop-status-grid {
                display: grid;
                gap: 8px;
                margin-bottom: 15px;
            }

            .status-item {
                display: flex;
                justify-content: space-between;
                padding: 5px 0;
                border-bottom: 1px solid #f0f0f0;
            }

            .status-item:last-child {
                border-bottom: none;
            }

            .status-item .label {
                font-weight: 500;
                color: #50575e;
            }

            .status-item .value {
                color: #1d2327;
                font-family: monospace;
                font-size: 12px;
            }

            .chatshop-component-status h4 {
                margin: 15px 0 10px;
                padding-top: 15px;
                border-top: 1px solid #f0f0f0;
                font-size: 13px;
                color: #50575e;
            }

            .component-status {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 5px 0;
            }

            .component-indicator {
                display: flex;
                align-items: center;
                gap: 4px;
                font-size: 12px;
            }

            .component-status.enabled .component-indicator {
                color: #00a32a;
            }

            .component-status.disabled .component-indicator {
                color: #d63638;
            }

            .chatshop-quick-actions {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .chatshop-quick-actions .button {
                display: flex;
                align-items: center;
                justify-content: flex-start;
                gap: 8px;
                text-align: left;
                padding: 8px 12px;
            }

            .chatshop-help-links {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .help-link {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 8px;
                text-decoration: none;
                color: #0073aa;
                border-radius: 3px;
                transition: background-color 0.2s ease;
            }

            .help-link:hover {
                background-color: #f0f6fc;
                color: #005a87;
            }

            .chatshop-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .chatshop-modal-content {
                background: #fff;
                border-radius: 5px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                max-width: 500px;
                width: 90%;
                max-height: 80vh;
                overflow: hidden;
            }

            .chatshop-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px;
                border-bottom: 1px solid #ddd;
                background: #f8f9fa;
            }

            .chatshop-modal-header h3 {
                margin: 0;
            }

            .chatshop-modal-close {
                background: none;
                border: none;
                font-size: 18px;
                cursor: pointer;
                padding: 5px;
                border-radius: 3px;
            }

            .chatshop-modal-close:hover {
                background: #e0e0e0;
            }

            .chatshop-modal-body {
                padding: 20px;
                overflow-y: auto;
                max-height: calc(80vh - 140px);
            }

            .chatshop-modal-actions {
                display: flex;
                gap: 10px;
                justify-content: flex-end;
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid #f0f0f0;
            }

            .chatshop-warning {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                padding: 15px;
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 3px;
                margin-bottom: 20px;
            }

            .chatshop-warning .dashicons {
                color: #856404;
                font-size: 20px;
                width: 20px;
                height: 20px;
                margin-top: 2px;
            }

            .chatshop-warning p {
                margin: 0;
                color: #856404;
            }

            .chatshop-validation-loading {
                text-align: center;
                padding: 20px;
            }

            .chatshop-validation-loading .spinner {
                float: none;
                margin: 0 auto 10px;
            }

            .chatshop-empty-tab {
                text-align: center;
                padding: 60px 20px;
                color: #50575e;
            }

            .chatshop-empty-icon .dashicons {
                font-size: 48px;
                width: 48px;
                height: 48px;
                color: #c3c4c7;
            }

            .chatshop-empty-tab h3 {
                margin: 20px 0 10px;
                color: #1d2327;
            }

            .chatshop-password-toggle {
                margin-left: 5px;
            }

            .required {
                color: #d63638;
            }

            .chatshop-field-validation .error {
                color: #d63638;
                font-size: 12px;
            }

            input.invalid,
            textarea.invalid,
            select.invalid {
                border-color: #d63638;
            }
        </style>
<?php
    }
}
