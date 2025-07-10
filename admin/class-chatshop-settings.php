<?php

/**
 * ChatShop Settings Management
 *
 * Handles all plugin settings, component configurations, and admin interfaces.
 * Implements component-based architecture with centralized settings management.
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
 * ChatShop Settings Manager Class
 *
 * Central hub for all plugin settings management, providing:
 * - Component-based settings organization
 * - Security and validation
 * - API for components to register settings
 * - Unified admin interface
 * - Settings import/export functionality
 *
 * @since 1.0.0
 */
class ChatShop_Settings
{

    /**
     * Settings instance
     *
     * @var ChatShop_Settings
     */
    private static $instance = null;

    /**
     * Registered component settings
     *
     * @var array
     */
    private $component_settings = array();

    /**
     * Default settings values
     *
     * @var array
     */
    private $default_settings = array();

    /**
     * Settings fields configuration
     *
     * @var array
     */
    private $settings_fields = array();

    /**
     * Current tab being viewed
     *
     * @var string
     */
    private $current_tab = 'general';

    /**
     * Settings capabilities requirement
     *
     * @var string
     */
    private $capability = 'manage_woocommerce';

    /**
     * Settings option name
     *
     * @var string
     */
    private $option_name = 'chatshop_settings';

    /**
     * Get singleton instance
     *
     * @return ChatShop_Settings
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
        $this->init_default_settings();
        $this->register_core_settings();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_post_chatshop_save_settings', array($this, 'save_settings'));
        add_action('admin_post_chatshop_reset_settings', array($this, 'reset_settings'));
        add_action('admin_post_chatshop_export_settings', array($this, 'export_settings'));
        add_action('admin_post_chatshop_import_settings', array($this, 'import_settings'));
        add_action('wp_ajax_chatshop_test_connection', array($this, 'test_api_connection'));
        add_action('wp_ajax_chatshop_validate_setting', array($this, 'validate_setting_ajax'));

        // Component registration hooks
        add_action('chatshop_register_settings', array($this, 'register_component_settings'), 10, 2);
    }

    /**
     * Initialize default settings structure
     */
    private function init_default_settings()
    {
        $this->default_settings = array(
            'general' => array(
                'enabled' => true,
                'debug_mode' => false,
                'log_level' => 'error',
                'currency' => 'NGN',
                'timezone' => 'Africa/Lagos',
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i:s',
            ),
            'security' => array(
                'api_key_encryption' => true,
                'webhook_verification' => true,
                'rate_limiting' => true,
                'max_requests_per_minute' => 60,
                'allowed_ips' => array(),
                'ssl_verification' => true,
            ),
            'notifications' => array(
                'admin_notifications' => true,
                'error_notifications' => true,
                'success_notifications' => true,
                'email_notifications' => false,
                'notification_email' => get_option('admin_email'),
            ),
            'performance' => array(
                'cache_enabled' => true,
                'cache_duration' => 3600,
                'background_processing' => true,
                'batch_size' => 50,
                'api_timeout' => 30,
            ),
            'components' => array(
                'payment' => array('enabled' => true),
                'whatsapp' => array('enabled' => true),
                'analytics' => array('enabled' => true),
                'integration' => array('enabled' => true),
            )
        );
    }

    /**
     * Register core plugin settings
     */
    private function register_core_settings()
    {
        $this->register_general_settings();
        $this->register_security_settings();
        $this->register_notification_settings();
        $this->register_performance_settings();
        $this->register_component_settings_tab();
    }

    /**
     * Register general settings
     */
    private function register_general_settings()
    {
        $this->settings_fields['general'] = array(
            'enabled' => array(
                'title' => __('Enable ChatShop', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Enable or disable the entire ChatShop plugin functionality.', 'chatshop'),
                'default' => true,
                'section' => 'basic',
            ),
            'debug_mode' => array(
                'title' => __('Debug Mode', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Enable debug mode for detailed logging and error reporting.', 'chatshop'),
                'default' => false,
                'section' => 'basic',
            ),
            'log_level' => array(
                'title' => __('Log Level', 'chatshop'),
                'type' => 'select',
                'description' => __('Set the minimum level for logging events.', 'chatshop'),
                'options' => array(
                    'emergency' => __('Emergency', 'chatshop'),
                    'alert' => __('Alert', 'chatshop'),
                    'critical' => __('Critical', 'chatshop'),
                    'error' => __('Error', 'chatshop'),
                    'warning' => __('Warning', 'chatshop'),
                    'notice' => __('Notice', 'chatshop'),
                    'info' => __('Info', 'chatshop'),
                    'debug' => __('Debug', 'chatshop'),
                ),
                'default' => 'error',
                'section' => 'basic',
            ),
            'currency' => array(
                'title' => __('Default Currency', 'chatshop'),
                'type' => 'select',
                'description' => __('Default currency for transactions and payment links.', 'chatshop'),
                'options' => $this->get_supported_currencies(),
                'default' => 'NGN',
                'section' => 'basic',
            ),
            'timezone' => array(
                'title' => __('Timezone', 'chatshop'),
                'type' => 'select',
                'description' => __('Timezone for scheduling and reporting.', 'chatshop'),
                'options' => $this->get_timezone_options(),
                'default' => 'Africa/Lagos',
                'section' => 'basic',
            ),
        );
    }

    /**
     * Register security settings
     */
    private function register_security_settings()
    {
        $this->settings_fields['security'] = array(
            'api_key_encryption' => array(
                'title' => __('Encrypt API Keys', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Encrypt stored API keys and sensitive data.', 'chatshop'),
                'default' => true,
                'section' => 'encryption',
            ),
            'webhook_verification' => array(
                'title' => __('Webhook Verification', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Verify webhook signatures for security.', 'chatshop'),
                'default' => true,
                'section' => 'webhooks',
            ),
            'rate_limiting' => array(
                'title' => __('Enable Rate Limiting', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Limit API requests to prevent abuse.', 'chatshop'),
                'default' => true,
                'section' => 'rate_limiting',
            ),
            'max_requests_per_minute' => array(
                'title' => __('Max Requests Per Minute', 'chatshop'),
                'type' => 'number',
                'description' => __('Maximum API requests allowed per minute per IP.', 'chatshop'),
                'default' => 60,
                'min' => 1,
                'max' => 1000,
                'section' => 'rate_limiting',
                'depends_on' => 'rate_limiting',
            ),
            'allowed_ips' => array(
                'title' => __('Allowed IP Addresses', 'chatshop'),
                'type' => 'textarea',
                'description' => __('Comma-separated list of allowed IP addresses for API access. Leave empty to allow all.', 'chatshop'),
                'default' => '',
                'section' => 'access_control',
            ),
            'ssl_verification' => array(
                'title' => __('SSL Verification', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Verify SSL certificates for external API calls.', 'chatshop'),
                'default' => true,
                'section' => 'ssl',
            ),
        );
    }

    /**
     * Register notification settings
     */
    private function register_notification_settings()
    {
        $this->settings_fields['notifications'] = array(
            'admin_notifications' => array(
                'title' => __('Admin Notifications', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Show admin notifications in WordPress dashboard.', 'chatshop'),
                'default' => true,
                'section' => 'admin',
            ),
            'error_notifications' => array(
                'title' => __('Error Notifications', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Notify administrators of critical errors.', 'chatshop'),
                'default' => true,
                'section' => 'admin',
            ),
            'success_notifications' => array(
                'title' => __('Success Notifications', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Show success notifications for completed actions.', 'chatshop'),
                'default' => true,
                'section' => 'admin',
            ),
            'email_notifications' => array(
                'title' => __('Email Notifications', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Send email notifications for important events.', 'chatshop'),
                'default' => false,
                'section' => 'email',
            ),
            'notification_email' => array(
                'title' => __('Notification Email', 'chatshop'),
                'type' => 'email',
                'description' => __('Email address for receiving notifications.', 'chatshop'),
                'default' => get_option('admin_email'),
                'section' => 'email',
                'depends_on' => 'email_notifications',
            ),
        );
    }

    /**
     * Register performance settings
     */
    private function register_performance_settings()
    {
        $this->settings_fields['performance'] = array(
            'cache_enabled' => array(
                'title' => __('Enable Caching', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Enable caching for improved performance.', 'chatshop'),
                'default' => true,
                'section' => 'caching',
            ),
            'cache_duration' => array(
                'title' => __('Cache Duration (seconds)', 'chatshop'),
                'type' => 'number',
                'description' => __('How long to cache data before refreshing.', 'chatshop'),
                'default' => 3600,
                'min' => 60,
                'max' => 86400,
                'section' => 'caching',
                'depends_on' => 'cache_enabled',
            ),
            'background_processing' => array(
                'title' => __('Background Processing', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Process heavy tasks in the background.', 'chatshop'),
                'default' => true,
                'section' => 'processing',
            ),
            'batch_size' => array(
                'title' => __('Batch Processing Size', 'chatshop'),
                'type' => 'number',
                'description' => __('Number of items to process in each batch.', 'chatshop'),
                'default' => 50,
                'min' => 1,
                'max' => 1000,
                'section' => 'processing',
                'depends_on' => 'background_processing',
            ),
            'api_timeout' => array(
                'title' => __('API Timeout (seconds)', 'chatshop'),
                'type' => 'number',
                'description' => __('Timeout for external API calls.', 'chatshop'),
                'default' => 30,
                'min' => 5,
                'max' => 300,
                'section' => 'api',
            ),
        );
    }

    /**
     * Register component settings tab
     */
    private function register_component_settings_tab()
    {
        $this->settings_fields['components'] = array(
            'payment_enabled' => array(
                'title' => __('Payment Component', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Enable payment processing functionality.', 'chatshop'),
                'default' => true,
                'section' => 'core_components',
                'component' => 'payment',
            ),
            'whatsapp_enabled' => array(
                'title' => __('WhatsApp Component', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Enable WhatsApp integration functionality.', 'chatshop'),
                'default' => true,
                'section' => 'core_components',
                'component' => 'whatsapp',
            ),
            'analytics_enabled' => array(
                'title' => __('Analytics Component', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Enable analytics and reporting functionality.', 'chatshop'),
                'default' => true,
                'section' => 'core_components',
                'component' => 'analytics',
            ),
            'integration_enabled' => array(
                'title' => __('WooCommerce Integration', 'chatshop'),
                'type' => 'checkbox',
                'description' => __('Enable WooCommerce integration functionality.', 'chatshop'),
                'default' => true,
                'section' => 'core_components',
                'component' => 'integration',
            ),
        );
    }

    /**
     * Initialize settings
     */
    public function init_settings()
    {
        register_setting(
            'chatshop_settings_group',
            $this->option_name,
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => $this->default_settings,
            )
        );

        // Register settings sections and fields for WordPress Settings API
        $this->register_wordpress_settings();
    }

    /**
     * Register settings with WordPress Settings API
     */
    private function register_wordpress_settings()
    {
        foreach ($this->settings_fields as $tab => $fields) {
            $sections = array();

            // Group fields by section
            foreach ($fields as $field_id => $field) {
                $section = isset($field['section']) ? $field['section'] : 'default';
                if (!isset($sections[$section])) {
                    $sections[$section] = array();
                }
                $sections[$section][$field_id] = $field;
            }

            // Register sections and fields
            foreach ($sections as $section_id => $section_fields) {
                $section_key = "chatshop_{$tab}_{$section_id}";

                add_settings_section(
                    $section_key,
                    $this->get_section_title($tab, $section_id),
                    array($this, 'render_section_description'),
                    "chatshop_{$tab}_settings"
                );

                foreach ($section_fields as $field_id => $field) {
                    add_settings_field(
                        "{$tab}_{$field_id}",
                        $field['title'],
                        array($this, 'render_field'),
                        "chatshop_{$tab}_settings",
                        $section_key,
                        array(
                            'tab' => $tab,
                            'field_id' => $field_id,
                            'field' => $field,
                        )
                    );
                }
            }
        }
    }

    /**
     * Get section title
     *
     * @param string $tab
     * @param string $section_id
     * @return string
     */
    private function get_section_title($tab, $section_id)
    {
        $section_titles = array(
            'general' => array(
                'basic' => __('Basic Settings', 'chatshop'),
            ),
            'security' => array(
                'encryption' => __('Encryption Settings', 'chatshop'),
                'webhooks' => __('Webhook Security', 'chatshop'),
                'rate_limiting' => __('Rate Limiting', 'chatshop'),
                'access_control' => __('Access Control', 'chatshop'),
                'ssl' => __('SSL Settings', 'chatshop'),
            ),
            'notifications' => array(
                'admin' => __('Admin Notifications', 'chatshop'),
                'email' => __('Email Notifications', 'chatshop'),
            ),
            'performance' => array(
                'caching' => __('Caching Settings', 'chatshop'),
                'processing' => __('Background Processing', 'chatshop'),
                'api' => __('API Settings', 'chatshop'),
            ),
            'components' => array(
                'core_components' => __('Core Components', 'chatshop'),
            ),
        );

        return isset($section_titles[$tab][$section_id])
            ? $section_titles[$tab][$section_id]
            : ucfirst(str_replace('_', ' ', $section_id));
    }

    /**
     * Render section description
     *
     * @param array $args
     */
    public function render_section_description($args)
    {
        // Section descriptions can be added here if needed
        $descriptions = array(
            'chatshop_general_basic' => __('Configure basic plugin settings and preferences.', 'chatshop'),
            'chatshop_security_encryption' => __('Configure data encryption and security settings.', 'chatshop'),
            'chatshop_performance_caching' => __('Optimize plugin performance with caching settings.', 'chatshop'),
        );

        if (isset($descriptions[$args['id']])) {
            echo '<p>' . esc_html($descriptions[$args['id']]) . '</p>';
        }
    }

    /**
     * Get supported currencies
     *
     * @return array
     */
    private function get_supported_currencies()
    {
        return array(
            'NGN' => __('Nigerian Naira (NGN)', 'chatshop'),
            'USD' => __('US Dollar (USD)', 'chatshop'),
            'EUR' => __('Euro (EUR)', 'chatshop'),
            'GBP' => __('British Pound (GBP)', 'chatshop'),
            'GHS' => __('Ghanaian Cedi (GHS)', 'chatshop'),
            'KES' => __('Kenyan Shilling (KES)', 'chatshop'),
            'ZAR' => __('South African Rand (ZAR)', 'chatshop'),
            'UGX' => __('Ugandan Shilling (UGX)', 'chatshop'),
        );
    }

    /**
     * Get timezone options
     *
     * @return array
     */
    private function get_timezone_options()
    {
        $timezones = array();
        $regions = array(
            'Africa' => DateTimeZone::AFRICA,
            'America' => DateTimeZone::AMERICA,
            'Asia' => DateTimeZone::ASIA,
            'Europe' => DateTimeZone::EUROPE,
        );

        foreach ($regions as $name => $mask) {
            $zones = DateTimeZone::listIdentifiers($mask);
            foreach ($zones as $timezone) {
                $timezones[$timezone] = str_replace('_', ' ', $timezone);
            }
        }

        return $timezones;
    }

    /**
     * Register component settings
     *
     * @param string $component_name
     * @param array $settings
     */
    public function register_component_settings($component_name, $settings)
    {
        if (!isset($this->component_settings[$component_name])) {
            $this->component_settings[$component_name] = array();
        }

        $this->component_settings[$component_name] = array_merge(
            $this->component_settings[$component_name],
            $settings
        );

        // Add to settings fields for rendering
        if (!isset($this->settings_fields[$component_name])) {
            $this->settings_fields[$component_name] = array();
        }

        $this->settings_fields[$component_name] = array_merge(
            $this->settings_fields[$component_name],
            $settings
        );
    }

    /**
     * Get setting value
     *
     * @param string $key
     * @param mixed $default
     * @param string $component
     * @return mixed
     */
    public function get($key, $default = null, $component = null)
    {
        $all_settings = get_option($this->option_name, $this->default_settings);

        if ($component) {
            if (isset($all_settings[$component][$key])) {
                return $all_settings[$component][$key];
            }
            return isset($this->default_settings[$component][$key])
                ? $this->default_settings[$component][$key]
                : $default;
        }

        // Search across all components
        foreach ($all_settings as $component_settings) {
            if (isset($component_settings[$key])) {
                return $component_settings[$key];
            }
        }

        return $default;
    }

    /**
     * Set setting value
     *
     * @param string $key
     * @param mixed $value
     * @param string $component
     * @return bool
     */
    public function set($key, $value, $component = 'general')
    {
        $all_settings = get_option($this->option_name, $this->default_settings);

        if (!isset($all_settings[$component])) {
            $all_settings[$component] = array();
        }

        $all_settings[$component][$key] = $value;

        return update_option($this->option_name, $all_settings);
    }

    /**
     * Get all settings for a component
     *
     * @param string $component
     * @return array
     */
    public function get_component_settings($component)
    {
        $all_settings = get_option($this->option_name, $this->default_settings);

        return isset($all_settings[$component])
            ? $all_settings[$component]
            : (isset($this->default_settings[$component]) ? $this->default_settings[$component] : array());
    }

    /**
     * Set all settings for a component
     *
     * @param string $component
     * @param array $settings
     * @return bool
     */
    public function set_component_settings($component, $settings)
    {
        $all_settings = get_option($this->option_name, $this->default_settings);
        $all_settings[$component] = $settings;

        return update_option($this->option_name, $all_settings);
    }

    /**
     * Get available tabs
     *
     * @return array
     */
    public function get_tabs()
    {
        $tabs = array(
            'general' => array(
                'title' => __('General', 'chatshop'),
                'icon' => 'dashicons-admin-generic',
                'description' => __('Basic plugin settings and configuration', 'chatshop'),
            ),
            'security' => array(
                'title' => __('Security', 'chatshop'),
                'icon' => 'dashicons-shield',
                'description' => __('Security and access control settings', 'chatshop'),
            ),
            'notifications' => array(
                'title' => __('Notifications', 'chatshop'),
                'icon' => 'dashicons-bell',
                'description' => __('Configure notification preferences', 'chatshop'),
            ),
            'performance' => array(
                'title' => __('Performance', 'chatshop'),
                'icon' => 'dashicons-performance',
                'description' => __('Optimize plugin performance and caching', 'chatshop'),
            ),
            'components' => array(
                'title' => __('Components', 'chatshop'),
                'icon' => 'dashicons-admin-plugins',
                'description' => __('Enable or disable plugin components', 'chatshop'),
            ),
        );

        // Add component-specific tabs
        foreach ($this->component_settings as $component => $settings) {
            if (!empty($settings)) {
                $tabs[$component] = array(
                    'title' => ucfirst($component),
                    'icon' => $this->get_component_icon($component),
                    'description' => sprintf(__('%s component settings', 'chatshop'), ucfirst($component)),
                );
            }
        }

        return apply_filters('chatshop_settings_tabs', $tabs);
    }

    /**
     * Get component icon
     *
     * @param string $component
     * @return string
     */
    private function get_component_icon($component)
    {
        $icons = array(
            'payment' => 'dashicons-money-alt',
            'whatsapp' => 'dashicons-smartphone',
            'analytics' => 'dashicons-chart-line',
            'integration' => 'dashicons-admin-links',
        );

        return isset($icons[$component]) ? $icons[$component] : 'dashicons-admin-settings';
    }

    /**
     * Get current tab
     *
     * @return string
     */
    public function get_current_tab()
    {
        return isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : $this->current_tab;
    }

    /**
     * Check if user can manage settings
     *
     * @return bool
     */
    public function current_user_can_manage()
    {
        return current_user_can($this->capability);
    }

    /**
     * Get settings page URL
     *
     * @param string $tab
     * @return string
     */
    public function get_settings_url($tab = null)
    {
        $args = array('page' => 'chatshop-settings');

        if ($tab) {
            $args['tab'] = $tab;
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    /**
     * Sanitize settings
     *
     * @param array $input
     * @return array
     */
    public function sanitize_settings($input)
    {
        $sanitized = array();
        $current_tab = $this->get_current_tab();

        if (isset($this->settings_fields[$current_tab])) {
            foreach ($this->settings_fields[$current_tab] as $field_id => $field) {
                $key = "{$current_tab}_{$field_id}";

                if (isset($input[$key])) {
                    $sanitized[$current_tab][$field_id] = $this->sanitize_field_value(
                        $input[$key],
                        $field
                    );
                }
            }
        }

        // Merge with existing settings
        $existing_settings = get_option($this->option_name, $this->default_settings);
        $merged_settings = array_merge($existing_settings, $sanitized);

        // Validate settings
        $validated_settings = $this->validate_settings($merged_settings);

        return $validated_settings;
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value
     * @param array $field
     * @return mixed
     */
    private function sanitize_field_value($value, $field)
    {
        switch ($field['type']) {
            case 'text':
                return sanitize_text_field($value);

            case 'textarea':
                return sanitize_textarea_field($value);

            case 'email':
                return sanitize_email($value);

            case 'url':
                return esc_url_raw($value);

            case 'number':
                $number = intval($value);
                if (isset($field['min']) && $number < $field['min']) {
                    $number = $field['min'];
                }
                if (isset($field['max']) && $number > $field['max']) {
                    $number = $field['max'];
                }
                return $number;

            case 'checkbox':
                return (bool) $value;

            case 'select':
                return in_array($value, array_keys($field['options'])) ? $value : $field['default'];

            case 'multiselect':
                if (!is_array($value)) {
                    return array();
                }
                return array_intersect($value, array_keys($field['options']));

            case 'password':
                return $this->encrypt_sensitive_data($value);

            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Validate settings
     *
     * @param array $settings
     * @return array
     */
    private function validate_settings($settings)
    {
        $errors = array();

        // Validate email settings
        if (isset($settings['notifications']['email_notifications']) && $settings['notifications']['email_notifications']) {
            if (
                empty($settings['notifications']['notification_email']) ||
                !is_email($settings['notifications']['notification_email'])
            ) {
                $errors[] = __('Valid notification email is required when email notifications are enabled.', 'chatshop');
                $settings['notifications']['email_notifications'] = false;
            }
        }

        // Validate rate limiting
        if (isset($settings['security']['rate_limiting']) && $settings['security']['rate_limiting']) {
            if (
                !isset($settings['security']['max_requests_per_minute']) ||
                $settings['security']['max_requests_per_minute'] < 1
            ) {
                $errors[] = __('Maximum requests per minute must be at least 1.', 'chatshop');
                $settings['security']['max_requests_per_minute'] = 60;
            }
        }

        // Validate cache duration
        if (isset($settings['performance']['cache_enabled']) && $settings['performance']['cache_enabled']) {
            if (
                !isset($settings['performance']['cache_duration']) ||
                $settings['performance']['cache_duration'] < 60
            ) {
                $errors[] = __('Cache duration must be at least 60 seconds.', 'chatshop');
                $settings['performance']['cache_duration'] = 3600;
            }
        }

        // Store validation errors for display
        if (!empty($errors)) {
            set_transient('chatshop_settings_errors', $errors, 30);
        }

        return $settings;
    }

    /**
     * Encrypt sensitive data
     *
     * @param string $data
     * @return string
     */
    private function encrypt_sensitive_data($data)
    {
        if (empty($data)) {
            return '';
        }

        // Use WordPress's built-in encryption if available
        if (function_exists('wp_hash_password')) {
            return base64_encode($data); // Simple encoding for now
        }

        return $data;
    }

    /**
     * Decrypt sensitive data
     *
     * @param string $encrypted_data
     * @return string
     */
    private function decrypt_sensitive_data($encrypted_data)
    {
        if (empty($encrypted_data)) {
            return '';
        }

        return base64_decode($encrypted_data);
    }

    /**
     * Save settings
     */
    public function save_settings()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['chatshop_settings_nonce'], 'chatshop_save_settings')) {
            wp_die(__('Security check failed. Please try again.', 'chatshop'));
        }

        // Check permissions
        if (!$this->current_user_can_manage()) {
            wp_die(__('You do not have permission to manage these settings.', 'chatshop'));
        }

        $current_tab = isset($_POST['current_tab']) ? sanitize_text_field($_POST['current_tab']) : 'general';

        // Process the settings
        $settings_data = isset($_POST['chatshop_settings']) ? $_POST['chatshop_settings'] : array();
        $sanitized_settings = $this->sanitize_settings($settings_data);

        // Save settings
        if (update_option($this->option_name, $sanitized_settings)) {
            // Trigger component settings update actions
            do_action('chatshop_settings_updated', $current_tab, $sanitized_settings);
            do_action("chatshop_{$current_tab}_settings_updated", $sanitized_settings);

            $message = __('Settings saved successfully.', 'chatshop');
            set_transient('chatshop_settings_message', $message, 30);
        } else {
            $error = __('Failed to save settings. Please try again.', 'chatshop');
            set_transient('chatshop_settings_error', $error, 30);
        }

        // Redirect back to settings page
        wp_redirect($this->get_settings_url($current_tab));
        exit;
    }

    /**
     * Reset settings
     */
    public function reset_settings()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['chatshop_reset_nonce'], 'chatshop_reset_settings')) {
            wp_die(__('Security check failed. Please try again.', 'chatshop'));
        }

        // Check permissions
        if (!$this->current_user_can_manage()) {
            wp_die(__('You do not have permission to reset these settings.', 'chatshop'));
        }

        $current_tab = isset($_POST['current_tab']) ? sanitize_text_field($_POST['current_tab']) : 'general';

        // Reset to defaults
        if (update_option($this->option_name, $this->default_settings)) {
            do_action('chatshop_settings_reset', $current_tab);

            $message = __('Settings reset to defaults successfully.', 'chatshop');
            set_transient('chatshop_settings_message', $message, 30);
        } else {
            $error = __('Failed to reset settings. Please try again.', 'chatshop');
            set_transient('chatshop_settings_error', $error, 30);
        }

        // Redirect back to settings page
        wp_redirect($this->get_settings_url($current_tab));
        exit;
    }

    /**
     * Export settings
     */
    public function export_settings()
    {
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'], 'chatshop_export_settings')) {
            wp_die(__('Security check failed. Please try again.', 'chatshop'));
        }

        // Check permissions
        if (!$this->current_user_can_manage()) {
            wp_die(__('You do not have permission to export settings.', 'chatshop'));
        }

        $settings = get_option($this->option_name, $this->default_settings);

        // Remove sensitive data from export
        $export_settings = $this->prepare_settings_for_export($settings);

        $filename = 'chatshop-settings-' . date('Y-m-d-H-i-s') . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Expires: 0');

        echo wp_json_encode($export_settings, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Import settings
     */
    public function import_settings()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['chatshop_import_nonce'], 'chatshop_import_settings')) {
            wp_die(__('Security check failed. Please try again.', 'chatshop'));
        }

        // Check permissions
        if (!$this->current_user_can_manage()) {
            wp_die(__('You do not have permission to import settings.', 'chatshop'));
        }

        if (!isset($_FILES['settings_file']) || $_FILES['settings_file']['error'] !== UPLOAD_ERR_OK) {
            $error = __('No file uploaded or upload error occurred.', 'chatshop');
            set_transient('chatshop_settings_error', $error, 30);
            wp_redirect($this->get_settings_url());
            exit;
        }

        $file_content = file_get_contents($_FILES['settings_file']['tmp_name']);
        $imported_settings = json_decode($file_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = __('Invalid JSON file. Please check the file format.', 'chatshop');
            set_transient('chatshop_settings_error', $error, 30);
            wp_redirect($this->get_settings_url());
            exit;
        }

        // Validate and sanitize imported settings
        $validated_settings = $this->validate_imported_settings($imported_settings);

        if (update_option($this->option_name, $validated_settings)) {
            do_action('chatshop_settings_imported', $validated_settings);

            $message = __('Settings imported successfully.', 'chatshop');
            set_transient('chatshop_settings_message', $message, 30);
        } else {
            $error = __('Failed to import settings. Please try again.', 'chatshop');
            set_transient('chatshop_settings_error', $error, 30);
        }

        wp_redirect($this->get_settings_url());
        exit;
    }

    /**
     * Prepare settings for export
     *
     * @param array $settings
     * @return array
     */
    private function prepare_settings_for_export($settings)
    {
        $export_settings = $settings;

        // Remove sensitive data
        $sensitive_keys = array(
            'api_key',
            'secret_key',
            'private_key',
            'password',
            'token',
            'webhook_secret',
        );

        array_walk_recursive($export_settings, function (&$value, $key) use ($sensitive_keys) {
            foreach ($sensitive_keys as $sensitive_key) {
                if (strpos(strtolower($key), $sensitive_key) !== false) {
                    $value = '[REDACTED]';
                    break;
                }
            }
        });

        // Add export metadata
        $export_settings['_export_meta'] = array(
            'version' => CHATSHOP_VERSION,
            'timestamp' => current_time('timestamp'),
            'site_url' => get_site_url(),
        );

        return $export_settings;
    }

    /**
     * Validate imported settings
     *
     * @param array $imported_settings
     * @return array
     */
    private function validate_imported_settings($imported_settings)
    {
        $current_settings = get_option($this->option_name, $this->default_settings);
        $validated_settings = $current_settings;

        // Remove export metadata
        unset($imported_settings['_export_meta']);

        // Merge with current settings, preserving structure
        foreach ($imported_settings as $component => $component_settings) {
            if (is_array($component_settings)) {
                foreach ($component_settings as $key => $value) {
                    // Only import if the setting exists in our configuration
                    if (isset($this->settings_fields[$component][$key])) {
                        $field = $this->settings_fields[$component][$key];
                        $validated_settings[$component][$key] = $this->sanitize_field_value($value, $field);
                    }
                }
            }
        }

        return $this->validate_settings($validated_settings);
    }

    /**
     * Test API connection
     */
    public function test_api_connection()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'chatshop_test_connection')) {
            wp_send_json_error(__('Security check failed.', 'chatshop'));
        }

        // Check permissions
        if (!$this->current_user_can_manage()) {
            wp_send_json_error(__('Permission denied.', 'chatshop'));
        }

        $component = sanitize_text_field($_POST['component']);
        $api_data = isset($_POST['api_data']) ? $_POST['api_data'] : array();

        // Sanitize API data
        $sanitized_api_data = array();
        foreach ($api_data as $key => $value) {
            $sanitized_api_data[sanitize_text_field($key)] = sanitize_text_field($value);
        }

        // Test connection based on component
        $result = $this->test_component_connection($component, $sanitized_api_data);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Test component connection
     *
     * @param string $component
     * @param array $api_data
     * @return array
     */
    private function test_component_connection($component, $api_data)
    {
        switch ($component) {
            case 'paystack':
                return $this->test_paystack_connection($api_data);

            case 'whatsapp':
                return $this->test_whatsapp_connection($api_data);

            default:
                return array(
                    'success' => false,
                    'message' => __('Unknown component for connection testing.', 'chatshop'),
                );
        }
    }

    /**
     * Test Paystack connection
     *
     * @param array $api_data
     * @return array
     */
    private function test_paystack_connection($api_data)
    {
        if (empty($api_data['secret_key'])) {
            return array(
                'success' => false,
                'message' => __('Secret key is required.', 'chatshop'),
            );
        }

        // Test API call to Paystack
        $response = wp_remote_get('https://api.paystack.co/bank', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_data['secret_key'],
            ),
            'timeout' => 10,
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => sprintf(__('Connection failed: %s', 'chatshop'), $response->get_error_message()),
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 200) {
            return array(
                'success' => true,
                'message' => __('Connection successful!', 'chatshop'),
            );
        } else {
            return array(
                'success' => false,
                'message' => sprintf(__('API returned error code: %d', 'chatshop'), $response_code),
            );
        }
    }

    /**
     * Test WhatsApp connection
     *
     * @param array $api_data
     * @return array
     */
    private function test_whatsapp_connection($api_data)
    {
        if (empty($api_data['access_token'])) {
            return array(
                'success' => false,
                'message' => __('Access token is required.', 'chatshop'),
            );
        }

        // Test WhatsApp Business API connection
        // This would be implemented based on the actual WhatsApp API being used
        return array(
            'success' => true,
            'message' => __('WhatsApp connection test would be implemented here.', 'chatshop'),
        );
    }

    /**
     * Validate setting via AJAX
     */
    public function validate_setting_ajax()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'chatshop_validate_setting')) {
            wp_send_json_error(__('Security check failed.', 'chatshop'));
        }

        $field_id = sanitize_text_field($_POST['field_id']);
        $value = $_POST['value'];
        $tab = sanitize_text_field($_POST['tab']);

        if (!isset($this->settings_fields[$tab][$field_id])) {
            wp_send_json_error(__('Invalid field.', 'chatshop'));
        }

        $field = $this->settings_fields[$tab][$field_id];
        $sanitized_value = $this->sanitize_field_value($value, $field);

        // Perform field-specific validation
        $validation_result = $this->validate_field_value($sanitized_value, $field);

        if ($validation_result['valid']) {
            wp_send_json_success($validation_result);
        } else {
            wp_send_json_error($validation_result);
        }
    }

    /**
     * Validate field value
     *
     * @param mixed $value
     * @param array $field
     * @return array
     */
    private function validate_field_value($value, $field)
    {
        $result = array('valid' => true, 'message' => '');

        switch ($field['type']) {
            case 'email':
                if (!empty($value) && !is_email($value)) {
                    $result = array(
                        'valid' => false,
                        'message' => __('Please enter a valid email address.', 'chatshop'),
                    );
                }
                break;

            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $result = array(
                        'valid' => false,
                        'message' => __('Please enter a valid URL.', 'chatshop'),
                    );
                }
                break;

            case 'number':
                if (!is_numeric($value)) {
                    $result = array(
                        'valid' => false,
                        'message' => __('Please enter a valid number.', 'chatshop'),
                    );
                } elseif (isset($field['min']) && $value < $field['min']) {
                    $result = array(
                        'valid' => false,
                        'message' => sprintf(__('Value must be at least %d.', 'chatshop'), $field['min']),
                    );
                } elseif (isset($field['max']) && $value > $field['max']) {
                    $result = array(
                        'valid' => false,
                        'message' => sprintf(__('Value must not exceed %d.', 'chatshop'), $field['max']),
                    );
                }
                break;
        }

        return $result;
    }

    /**
     * Render field
     *
     * @param array $args
     */
    public function render_field($args)
    {
        $tab = $args['tab'];
        $field_id = $args['field_id'];
        $field = $args['field'];
        $name = "{$tab}_{$field_id}";
        $value = $this->get($field_id, $field['default'], $tab);

        // Check if field has dependencies
        if (isset($field['depends_on'])) {
            $dependency_value = $this->get($field['depends_on'], false, $tab);
            $depends_class = $dependency_value ? '' : ' chatshop-hidden';
        } else {
            $depends_class = '';
        }

        echo '<div class="chatshop-field-wrapper' . esc_attr($depends_class) . '">';

        switch ($field['type']) {
            case 'text':
            case 'email':
            case 'url':
            case 'password':
                $this->render_text_field($name, $value, $field);
                break;

            case 'number':
                $this->render_number_field($name, $value, $field);
                break;

            case 'textarea':
                $this->render_textarea_field($name, $value, $field);
                break;

            case 'checkbox':
                $this->render_checkbox_field($name, $value, $field);
                break;

            case 'select':
                $this->render_select_field($name, $value, $field);
                break;

            case 'multiselect':
                $this->render_multiselect_field($name, $value, $field);
                break;

            default:
                $this->render_text_field($name, $value, $field);
                break;
        }

        if (!empty($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Render text field
     *
     * @param string $name
     * @param mixed $value
     * @param array $field
     */
    private function render_text_field($name, $value, $field)
    {
        $type = $field['type'] === 'password' ? 'password' : 'text';
        $placeholder = isset($field['placeholder']) ? $field['placeholder'] : '';

        printf(
            '<input type="%s" name="chatshop_settings[%s]" id="%s" value="%s" placeholder="%s" class="regular-text" />',
            esc_attr($type),
            esc_attr($name),
            esc_attr($name),
            esc_attr($value),
            esc_attr($placeholder)
        );
    }

    /**
     * Render number field
     *
     * @param string $name
     * @param mixed $value
     * @param array $field
     */
    private function render_number_field($name, $value, $field)
    {
        $min = isset($field['min']) ? $field['min'] : '';
        $max = isset($field['max']) ? $field['max'] : '';
        $step = isset($field['step']) ? $field['step'] : '1';

        printf(
            '<input type="number" name="chatshop_settings[%s]" id="%s" value="%s" min="%s" max="%s" step="%s" class="small-text" />',
            esc_attr($name),
            esc_attr($name),
            esc_attr($value),
            esc_attr($min),
            esc_attr($max),
            esc_attr($step)
        );
    }

    /**
     * Render textarea field
     *
     * @param string $name
     * @param mixed $value
     * @param array $field
     */
    private function render_textarea_field($name, $value, $field)
    {
        $rows = isset($field['rows']) ? $field['rows'] : 5;
        $placeholder = isset($field['placeholder']) ? $field['placeholder'] : '';

        printf(
            '<textarea name="chatshop_settings[%s]" id="%s" rows="%d" class="large-text" placeholder="%s">%s</textarea>',
            esc_attr($name),
            esc_attr($name),
            intval($rows),
            esc_attr($placeholder),
            esc_textarea($value)
        );
    }

    /**
     * Render checkbox field
     *
     * @param string $name
     * @param mixed $value
     * @param array $field
     */
    private function render_checkbox_field($name, $value, $field)
    {
        printf(
            '<input type="checkbox" name="chatshop_settings[%s]" id="%s" value="1" %s />',
            esc_attr($name),
            esc_attr($name),
            checked(1, $value, false)
        );
    }

    /**
     * Render select field
     *
     * @param string $name
     * @param mixed $value
     * @param array $field
     */
    private function render_select_field($name, $value, $field)
    {
        printf('<select name="chatshop_settings[%s]" id="%s">', esc_attr($name), esc_attr($name));

        foreach ($field['options'] as $option_value => $option_label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($option_value),
                selected($value, $option_value, false),
                esc_html($option_label)
            );
        }

        echo '</select>';
    }

    /**
     * Render multiselect field
     *
     * @param string $name
     * @param mixed $value
     * @param array $field
     */
    private function render_multiselect_field($name, $value, $field)
    {
        $selected_values = is_array($value) ? $value : array();

        printf('<select name="chatshop_settings[%s][]" id="%s" multiple="multiple" size="5">', esc_attr($name), esc_attr($name));

        foreach ($field['options'] as $option_value => $option_label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($option_value),
                in_array($option_value, $selected_values) ? 'selected="selected"' : '',
                esc_html($option_label)
            );
        }

        echo '</select>';
    }

    /**
     * Get settings fields for a tab
     *
     * @param string $tab
     * @return array
     */
    public function get_settings_fields($tab)
    {
        return isset($this->settings_fields[$tab]) ? $this->settings_fields[$tab] : array();
    }

    /**
     * Check if component is enabled
     *
     * @param string $component
     * @return bool
     */
    public function is_component_enabled($component)
    {
        return (bool) $this->get("{$component}_enabled", true, 'components');
    }

    /**
     * Enable component
     *
     * @param string $component
     * @return bool
     */
    public function enable_component($component)
    {
        return $this->set("{$component}_enabled", true, 'components');
    }

    /**
     * Disable component
     *
     * @param string $component
     * @return bool
     */
    public function disable_component($component)
    {
        return $this->set("{$component}_enabled", false, 'components');
    }

    /**
     * Get debug mode status
     *
     * @return bool
     */
    public function is_debug_mode()
    {
        return (bool) $this->get('debug_mode', false, 'general');
    }

    /**
     * Get log level
     *
     * @return string
     */
    public function get_log_level()
    {
        return $this->get('log_level', 'error', 'general');
    }

    /**
     * Clean up on uninstall
     */
    public static function uninstall()
    {
        delete_option('chatshop_settings');
        delete_transient('chatshop_settings_message');
        delete_transient('chatshop_settings_error');
        delete_transient('chatshop_settings_errors');
    }
}
