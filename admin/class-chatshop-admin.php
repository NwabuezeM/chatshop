<?php

/**
 * The admin-specific functionality of the plugin
 *
 * @link       https://chatshop.com
 * @since      1.0.0
 *
 * @package    ChatShop
 * @subpackage ChatShop/admin
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    ChatShop
 * @subpackage ChatShop/admin
 * @author     ChatShop Team <support@chatshop.com>
 */
class ChatShop_Admin
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Component loader instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      ChatShop_Component_Loader    $component_loader
     */
    private $component_loader;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     * @param      ChatShop_Component_Loader    $component_loader    Component loader instance.
     */
    public function __construct($plugin_name, $version, $component_loader)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->component_loader = $component_loader;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in ChatShop_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The ChatShop_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'css/chatshop-admin.css',
            array(),
            $this->version,
            'all'
        );

        // Enqueue additional styles for specific pages
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'chatshop') !== false) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_style('dashicons');
        }
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in ChatShop_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The ChatShop_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'js/chatshop-admin.js',
            array('jquery', 'wp-color-picker'),
            $this->version,
            false
        );

        // Localize script with AJAX data
        wp_localize_script(
            $this->plugin_name,
            'chatshop_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('chatshop_ajax_nonce'),
                'current_screen' => get_current_screen()->id ?? '',
                'plugin_url' => plugin_dir_url(dirname(__FILE__)),
                'is_premium' => $this->is_premium_active(),
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this item?', 'chatshop'),
                    'save_success' => __('Settings saved successfully!', 'chatshop'),
                    'save_error' => __('Error saving settings. Please try again.', 'chatshop'),
                    'test_success' => __('Test completed successfully!', 'chatshop'),
                    'test_error' => __('Test failed. Please check your settings.', 'chatshop'),
                    'loading' => __('Loading...', 'chatshop'),
                    'processing' => __('Processing...', 'chatshop'),
                )
            )
        );

        // Enqueue component-specific scripts
        $this->enqueue_component_scripts();
    }

    /**
     * Add admin menu and submenus.
     *
     * @since    1.0.0
     */
    public function add_admin_menu()
    {
        // Check user capabilities
        if (! current_user_can('manage_options')) {
            return;
        }

        // Main menu
        add_menu_page(
            __('ChatShop', 'chatshop'),           // Page title
            __('ChatShop', 'chatshop'),           // Menu title  
            'manage_options',                        // Capability
            'chatshop',                              // Menu slug
            array($this, 'display_dashboard'),     // Function
            $this->get_menu_icon(),                  // Icon
            30                                       // Position
        );

        // Dashboard submenu (same as main menu)
        add_submenu_page(
            'chatshop',
            __('Dashboard', 'chatshop'),
            __('Dashboard', 'chatshop'),
            'manage_options',
            'chatshop',
            array($this, 'display_dashboard')
        );

        // Contacts submenu
        add_submenu_page(
            'chatshop',
            __('Contacts', 'chatshop'),
            __('Contacts', 'chatshop'),
            'manage_options',
            'chatshop-contacts',
            array($this, 'display_contacts')
        );

        // Campaigns submenu
        add_submenu_page(
            'chatshop',
            __('Campaigns', 'chatshop'),
            __('Campaigns', 'chatshop'),
            'manage_options',
            'chatshop-campaigns',
            array($this, 'display_campaigns')
        );

        // Payment Links submenu
        add_submenu_page(
            'chatshop',
            __('Payment Links', 'chatshop'),
            __('Payment Links', 'chatshop'),
            'manage_options',
            'chatshop-payment-links',
            array($this, 'display_payment_links')
        );

        // Payment Gateways submenu
        add_submenu_page(
            'chatshop',
            __('Payment Gateways', 'chatshop'),
            __('Payment Gateways', 'chatshop'),
            'manage_options',
            'chatshop-gateways',
            array($this, 'display_gateways')
        );

        // Templates submenu
        add_submenu_page(
            'chatshop',
            __('Templates', 'chatshop'),
            __('Templates', 'chatshop'),
            'manage_options',
            'chatshop-templates',
            array($this, 'display_templates')
        );

        // Form Builder submenu
        add_submenu_page(
            'chatshop',
            __('Form Builder', 'chatshop'),
            __('Form Builder', 'chatshop'),
            'manage_options',
            'chatshop-forms',
            array($this, 'display_forms')
        );

        // Analytics submenu (Premium)
        if ($this->is_premium_active()) {
            add_submenu_page(
                'chatshop',
                __('Analytics', 'chatshop'),
                __('Analytics', 'chatshop') . ' <span class="chatshop-premium-badge">Pro</span>',
                'manage_options',
                'chatshop-analytics',
                array($this, 'display_analytics')
            );

            // Product Notifications submenu (Premium)
            add_submenu_page(
                'chatshop',
                __('Product Notifications', 'chatshop'),
                __('Product Notifications', 'chatshop') . ' <span class="chatshop-premium-badge">Pro</span>',
                'manage_options',
                'chatshop-product-notifications',
                array($this, 'display_product_notifications')
            );
        }

        // Components submenu
        add_submenu_page(
            'chatshop',
            __('Components', 'chatshop'),
            __('Components', 'chatshop'),
            'manage_options',
            'chatshop-components',
            array($this, 'display_components')
        );

        // Settings submenu
        add_submenu_page(
            'chatshop',
            __('Settings', 'chatshop'),
            __('Settings', 'chatshop'),
            'manage_options',
            'chatshop-settings',
            array($this, 'display_settings')
        );

        // System Info submenu
        add_submenu_page(
            'chatshop',
            __('System Info', 'chatshop'),
            __('System Info', 'chatshop'),
            'manage_options',
            'chatshop-system-info',
            array($this, 'display_system_info')
        );
    }

    /**
     * Display dashboard page.
     *
     * @since    1.0.0
     */
    public function display_dashboard()
    {
        include_once 'partials/dashboard.php';
    }

    /**
     * Display contacts page.
     *
     * @since    1.0.0
     */
    public function display_contacts()
    {
        include_once 'partials/contacts.php';
    }

    /**
     * Display campaigns page.
     *
     * @since    1.0.0
     */
    public function display_campaigns()
    {
        include_once 'partials/campaigns.php';
    }

    /**
     * Display payment links page.
     *
     * @since    1.0.0
     */
    public function display_payment_links()
    {
        include_once 'partials/payment-links.php';
    }

    /**
     * Display payment gateways page.
     *
     * @since    1.0.0
     */
    public function display_gateways()
    {
        include_once 'partials/payment-gateways.php';
    }

    /**
     * Display templates page.
     *
     * @since    1.0.0
     */
    public function display_templates()
    {
        include_once 'partials/templates.php';
    }

    /**
     * Display forms page.
     *
     * @since    1.0.0
     */
    public function display_forms()
    {
        include_once 'partials/forms.php';
    }

    /**
     * Display analytics page.
     *
     * @since    1.0.0
     */
    public function display_analytics()
    {
        if (! $this->is_premium_active()) {
            include_once 'partials/premium-required.php';
            return;
        }
        include_once 'partials/analytics.php';
    }

    /**
     * Display product notifications page.
     *
     * @since    1.0.0
     */
    public function display_product_notifications()
    {
        if (! $this->is_premium_active()) {
            include_once 'partials/premium-required.php';
            return;
        }
        include_once 'partials/product-notifications.php';
    }

    /**
     * Display components page.
     *
     * @since    1.0.0
     */
    public function display_components()
    {
        include_once 'partials/components.php';
    }

    /**
     * Display settings page.
     *
     * @since    1.0.0
     */
    public function display_settings()
    {
        include_once 'partials/settings.php';
    }

    /**
     * Display system info page.
     *
     * @since    1.0.0
     */
    public function display_system_info()
    {
        include_once 'partials/system-info.php';
    }

    /**
     * Add admin notices.
     *
     * @since    1.0.0
     */
    public function admin_notices()
    {
        // Check WooCommerce dependency
        if (! $this->is_woocommerce_active()) {
            $this->show_woocommerce_notice();
        }

        // Check component status
        $this->check_component_notices();

        // Show success/error messages
        $this->show_admin_messages();
    }

    /**
     * Handle AJAX requests.
     *
     * @since    1.0.0
     */
    public function handle_ajax()
    {
        // Verify nonce
        if (! wp_verify_nonce($_POST['nonce'] ?? '', 'chatshop_ajax_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        // Check user capabilities
        if (! current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $action = sanitize_text_field($_POST['chatshop_action'] ?? '');

        switch ($action) {
            case 'test_connection':
                $this->ajax_test_connection();
                break;
            case 'save_settings':
                $this->ajax_save_settings();
                break;
            case 'toggle_component':
                $this->ajax_toggle_component();
                break;
            case 'get_stats':
                $this->ajax_get_stats();
                break;
            case 'send_test_message':
                $this->ajax_send_test_message();
                break;
            default:
                wp_send_json_error(__('Invalid action', 'chatshop'));
        }
    }

    /**
     * Get menu icon (base64 encoded SVG).
     *
     * @since    1.0.0
     * @return   string
     */
    private function get_menu_icon()
    {
        return 'data:image/svg+xml;base64,' . base64_encode(
            '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 0C4.48 0 0 4.48 0 10C0 15.52 4.48 20 10 20C15.52 20 20 15.52 20 10C20 4.48 15.52 0 10 0ZM14.5 14L13 15.5L10 12.5L7 15.5L5.5 14L8.5 11L5.5 8L7 6.5L10 9.5L13 6.5L14.5 8L11.5 11L14.5 14Z" fill="#9ca3af"/>
            </svg>'
        );
    }

    /**
     * Check if WooCommerce is active.
     *
     * @since    1.0.0
     * @return   bool
     */
    private function is_woocommerce_active()
    {
        return class_exists('WooCommerce');
    }

    /**
     * Check if premium version is active.
     *
     * @since    1.0.0
     * @return   bool
     */
    private function is_premium_active()
    {
        return defined('CHATSHOP_PREMIUM_VERSION');
    }

    /**
     * Show WooCommerce dependency notice.
     *
     * @since    1.0.0
     */
    private function show_woocommerce_notice()
    {
        $class = 'notice notice-error';
        $message = sprintf(
            __('ChatShop requires WooCommerce to be installed and activated. <a href="%s">Install WooCommerce</a>', 'chatshop'),
            admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')
        );

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
    }

    /**
     * Check and show component notices.
     *
     * @since    1.0.0
     */
    private function check_component_notices()
    {
        $disabled_components = $this->component_loader->get_disabled_components();

        if (! empty($disabled_components)) {
            $message = sprintf(
                __('Some ChatShop components are disabled: %s. <a href="%s">Manage Components</a>', 'chatshop'),
                implode(', ', $disabled_components),
                admin_url('admin.php?page=chatshop-components')
            );

            printf('<div class="notice notice-warning"><p>%s</p></div>', $message);
        }
    }

    /**
     * Show admin messages.
     *
     * @since    1.0.0
     */
    private function show_admin_messages()
    {
        $messages = get_transient('chatshop_admin_messages');

        if (! empty($messages)) {
            foreach ($messages as $message) {
                printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr($message['type']),
                    esc_html($message['message'])
                );
            }
            delete_transient('chatshop_admin_messages');
        }
    }

    /**
     * Enqueue component-specific scripts.
     *
     * @since    1.0.0
     */
    private function enqueue_component_scripts()
    {
        $screen = get_current_screen();

        if (! $screen || strpos($screen->id, 'chatshop') === false) {
            return;
        }

        // Enqueue scripts based on current page
        switch ($screen->id) {
            case 'toplevel_page_chatshop':
                wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array(), '3.9.1', true);
                break;
            case 'chatshop_page_chatshop-payment-links':
                wp_enqueue_script('qrcode-js', 'https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js', array(), '1.4.4', true);
                break;
            case 'chatshop_page_chatshop-forms':
                wp_enqueue_script('sortable-js', 'https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js', array(), '1.15.0', true);
                break;
        }
    }

    /**
     * AJAX: Test connection.
     *
     * @since    1.0.0
     */
    private function ajax_test_connection()
    {
        $type = sanitize_text_field($_POST['connection_type'] ?? '');

        switch ($type) {
            case 'whatsapp':
                $result = $this->test_whatsapp_connection();
                break;
            case 'payment':
                $gateway = sanitize_text_field($_POST['gateway'] ?? '');
                $result = $this->test_payment_gateway($gateway);
                break;
            default:
                wp_send_json_error(__('Invalid connection type', 'chatshop'));
        }

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX: Save settings.
     *
     * @since    1.0.0
     */
    private function ajax_save_settings()
    {
        $settings = $_POST['settings'] ?? array();

        if (empty($settings)) {
            wp_send_json_error(__('No settings provided', 'chatshop'));
        }

        // Sanitize and save settings
        $sanitized_settings = $this->sanitize_settings($settings);
        $saved = update_option('chatshop_settings', $sanitized_settings);

        if ($saved) {
            wp_send_json_success(__('Settings saved successfully!', 'chatshop'));
        } else {
            wp_send_json_error(__('Error saving settings', 'chatshop'));
        }
    }

    /**
     * AJAX: Toggle component.
     *
     * @since    1.0.0
     */
    private function ajax_toggle_component()
    {
        $component = sanitize_text_field($_POST['component'] ?? '');
        $enabled = (bool) ($_POST['enabled'] ?? false);

        $result = $this->component_loader->toggle_component($component, $enabled);

        if ($result) {
            wp_send_json_success(
                sprintf(
                    __('Component %s %s successfully!', 'chatshop'),
                    $component,
                    $enabled ? 'enabled' : 'disabled'
                )
            );
        } else {
            wp_send_json_error(__('Error toggling component', 'chatshop'));
        }
    }

    /**
     * AJAX: Get statistics.
     *
     * @since    1.0.0
     */
    private function ajax_get_stats()
    {
        $stats = array(
            'contacts' => $this->get_contacts_count(),
            'campaigns' => $this->get_campaigns_count(),
            'payment_links' => $this->get_payment_links_count(),
            'revenue' => $this->get_revenue_stats(),
            'messages' => $this->get_messages_count(),
            'conversion_rate' => $this->get_conversion_rate(),
        );

        wp_send_json_success($stats);
    }

    /**
     * AJAX: Send test message.
     *
     * @since    1.0.0
     */
    private function ajax_send_test_message()
    {
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');

        if (empty($phone) || empty($message)) {
            wp_send_json_error(__('Phone number and message are required', 'chatshop'));
        }

        // Send test message via WhatsApp component
        $whatsapp_manager = $this->component_loader->get_component('whatsapp');

        if (! $whatsapp_manager) {
            wp_send_json_error(__('WhatsApp component not available', 'chatshop'));
        }

        $result = $whatsapp_manager->send_message($phone, $message);

        if ($result['success']) {
            wp_send_json_success(__('Test message sent successfully!', 'chatshop'));
        } else {
            wp_send_json_error($result['message'] ?? __('Failed to send test message', 'chatshop'));
        }
    }

    /**
     * Test WhatsApp connection.
     *
     * @since    1.0.0
     * @return   array
     */
    private function test_whatsapp_connection()
    {
        // Implementation would test WhatsApp API connection
        return array(
            'success' => true,
            'message' => __('WhatsApp connection successful!', 'chatshop')
        );
    }

    /**
     * Test payment gateway connection.
     *
     * @since    1.0.0
     * @param    string    $gateway
     * @return   array
     */
    private function test_payment_gateway($gateway)
    {
        // Implementation would test specific gateway connection
        return array(
            'success' => true,
            'message' => sprintf(__('%s gateway connection successful!', 'chatshop'), ucfirst($gateway))
        );
    }

    /**
     * Sanitize settings array.
     *
     * @since    1.0.0
     * @param    array    $settings
     * @return   array
     */
    private function sanitize_settings($settings)
    {
        $sanitized = array();

        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_settings($value);
            } else {
                // Sanitize based on key type
                if (strpos($key, 'email') !== false) {
                    $sanitized[$key] = sanitize_email($value);
                } elseif (strpos($key, 'url') !== false) {
                    $sanitized[$key] = esc_url_raw($value);
                } elseif (strpos($key, 'phone') !== false) {
                    $sanitized[$key] = preg_replace('/[^0-9+\-\s]/', '', $value);
                } else {
                    $sanitized[$key] = sanitize_text_field($value);
                }
            }
        }

        return $sanitized;
    }

    /**
     * Get contacts count.
     *
     * @since    1.0.0
     * @return   int
     */
    private function get_contacts_count()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_contacts';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    }

    /**
     * Get campaigns count.
     *
     * @since    1.0.0
     * @return   int
     */
    private function get_campaigns_count()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_campaigns';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    }

    /**
     * Get payment links count.
     *
     * @since    1.0.0
     * @return   int
     */
    private function get_payment_links_count()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_payment_links';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    }

    /**
     * Get revenue statistics.
     *
     * @since    1.0.0
     * @return   array
     */
    private function get_revenue_stats()
    {
        // Implementation would calculate revenue from payment transactions
        return array(
            'total' => 0,
            'this_month' => 0,
            'last_month' => 0
        );
    }

    /**
     * Get messages count.
     *
     * @since    1.0.0
     * @return   int
     */
    private function get_messages_count()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_messages';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    }

    /**
     * Get conversion rate.
     *
     * @since    1.0.0
     * @return   float
     */
    private function get_conversion_rate()
    {
        // Implementation would calculate conversion rate from analytics
        return 0.0;
    }
}
