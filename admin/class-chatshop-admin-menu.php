<?php

/**
 * Admin menu management for ChatShop
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
 * Admin menu management class.
 *
 * Handles the creation and management of admin menus and submenus
 * with proper capability checks and premium feature separation.
 *
 * @package    ChatShop
 * @subpackage ChatShop/admin
 * @author     ChatShop Team <support@chatshop.com>
 */
class ChatShop_Admin_Menu
{

    /**
     * Menu items configuration.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $menu_items    Array of menu configurations.
     */
    private $menu_items;

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
     * @param    ChatShop_Component_Loader    $component_loader    Component loader instance.
     */
    public function __construct($component_loader)
    {
        $this->component_loader = $component_loader;
        $this->setup_menu_items();
    }

    /**
     * Setup menu items configuration.
     *
     * @since    1.0.0
     */
    private function setup_menu_items()
    {
        $this->menu_items = array(
            'main' => array(
                'page_title' => __('ChatShop Dashboard', 'chatshop'),
                'menu_title' => __('ChatShop', 'chatshop'),
                'capability' => 'manage_options',
                'menu_slug'  => 'chatshop',
                'function'   => 'display_dashboard',
                'icon_url'   => $this->get_menu_icon(),
                'position'   => 30,
            ),
            'submenus' => array(
                'dashboard' => array(
                    'parent_slug' => 'chatshop',
                    'page_title'  => __('Dashboard', 'chatshop'),
                    'menu_title'  => __('Dashboard', 'chatshop'),
                    'capability'  => 'manage_options',
                    'menu_slug'   => 'chatshop',
                    'function'    => 'display_dashboard',
                    'position'    => 1,
                    'component'   => null,
                ),
                'contacts' => array(
                    'parent_slug' => 'chatshop',
                    'page_title'  => __('Contacts Management', 'chatshop'),
                    'menu_title'  => __('Contacts', 'chatshop'),
                    'capability'  => 'manage_options',
                    'menu_slug'   => 'chatshop-contacts',
                    'function'    => 'display_contacts',
                    'position'    => 10,
                    'component'   => 'whatsapp',
                    'badge'       => $this->get_contacts_badge(),
                ),
                'campaigns' => array(
                    'parent_slug' => 'chatshop',
                    'page_title'  => __('Campaign Management', 'chatshop'),
                    'menu_title'  => __('Campaigns', 'chatshop'),
                    'capability'  => 'manage_options',
                    'menu_slug'   => 'chatshop-campaigns',
                    'function'    => 'display_campaigns',
                    'position'    => 15,
                    'component'   => 'whatsapp',
                ),
                'payment_links' => array(
                    'parent_slug' => 'chatshop',
                    'page_title'  => __('Payment Links Management', 'chatshop'),
                    'menu_title'  => __('Payment Links', 'chatshop'),
                    'capability'  => 'manage_options',
                    'menu_slug'   => 'chatshop-payment-links',
                    'function'    => 'display_payment_links',
                    'position'    => 20,
                    'component'   => 'payment',
                ),
                'gateways' => array(
                    'parent_slug' => 'chatshop',
                    'page_title'  => __('Payment Gateways', 'chatshop'),
                    'menu_title'  => __('Payment Gateways', 'chatshop'),
                    'capability'  => 'manage_options',
                    'menu_slug'   => 'chatshop-gateways',
                    'function'    => 'display_gateways',
                    'position'    => 25,
                    'component'   => 'payment',
                ),
                'templates' => array(
                    'parent_slug' => 'chatshop',
                    'page_title'  => __('Template Management', 'chatshop'),
                    'menu_title'  => __('Templates', 'chatshop'),
                    'capability'  => 'manage_options',
                    'menu_slug'   => 'chatshop-templates',
                    'function'    => 'display_templates',
                    'position'    => 30,
                    'component'   => 'whatsapp',
                ),
                'forms' => array(
                    'parent_slug' => 'chatshop',
                    'page_title'  => __('Form Builder', 'chatshop'),
                    'menu_title'  => __('Form Builder', 'chatshop'),
                    'capability'  => 'manage_options',
                    'menu_slug'   => 'chatshop-forms',
                    'function'    => 'display_forms',
                    'position'    => 35,
                    'component'   => null,
                ),
                'analytics' => array(
                    'parent_slug' => 'chatshop',
                    'page_title'  => __('Analytics Dashboard', 'chatshop'),
                    'menu_title'  => __('Analytics', 'chatshop'),
                    'capability'  => 'manage_options',
                    'menu_slug'   => 'chatshop-analytics',
                    'function'    => 'display_analytics',
                    'position'    => 40,
                    'component'   => 'analytics',
                    'premium'     => true,
                ),
                'product_notifications' => array(
                    'parent_slug' => 'chatshop',
                    'page_title'  => __('Product Notifications', 'chatshop'),
                    'menu_title'  => __('Product Notifications', 'chatshop'),
                    'capability'  => 'manage_options',
                    'menu_slug'   => 'chatshop-product-notifications',
                    'function'    => 'display_product_notifications',
                    'position'    => 45,
                    'component'   => 'integration',
                    'premium'     => true,
                ),
                'components' => array(
                    'parent_slug' => 'chatshop',
                    'page_title'  => __('Component Management', 'chatshop'),
                    'menu_title'  => __('Components', 'chatshop'),
                    'capability'  => 'manage_options',
                    'menu_slug'   => 'chatshop-components',
                    'function'    => 'display_components',
                    'position'    => 80,
                    'component'   => null,
                ),
                'settings' => array(
                    'parent_slug' => 'chatshop',
                    'page_title'  => __('ChatShop Settings', 'chatshop'),
                    'menu_title'  => __('Settings', 'chatshop'),
                    'capability'  => 'manage_options',
                    'menu_slug'   => 'chatshop-settings',
                    'function'    => 'display_settings',
                    'position'    => 90,
                    'component'   => null,
                ),
                'system_info' => array(
                    'parent_slug' => 'chatshop',
                    'page_title'  => __('System Information', 'chatshop'),
                    'menu_title'  => __('System Info', 'chatshop'),
                    'capability'  => 'manage_options',
                    'menu_slug'   => 'chatshop-system-info',
                    'function'    => 'display_system_info',
                    'position'    => 95,
                    'component'   => null,
                ),
            ),
        );
    }

    /**
     * Register all menu items.
     *
     * @since    1.0.0
     */
    public function register_menus()
    {
        // Check user capabilities
        if (! current_user_can('manage_options')) {
            return;
        }

        // Add main menu
        $main = $this->menu_items['main'];
        add_menu_page(
            $main['page_title'],
            $main['menu_title'],
            $main['capability'],
            $main['menu_slug'],
            array($this, $main['function']),
            $main['icon_url'],
            $main['position']
        );

        // Add submenus
        foreach ($this->menu_items['submenus'] as $key => $submenu) {
            // Check if component is required and active
            if (isset($submenu['component']) && $submenu['component']) {
                if (! $this->component_loader->is_component_active($submenu['component'])) {
                    continue;
                }
            }

            // Check premium requirement
            if (isset($submenu['premium']) && $submenu['premium'] && ! $this->is_premium_active()) {
                // Add with premium badge
                $submenu['menu_title'] .= ' <span class="chatshop-premium-badge">Pro</span>';
            }

            // Add badge if set
            if (isset($submenu['badge']) && $submenu['badge']) {
                $submenu['menu_title'] .= ' ' . $submenu['badge'];
            }

            add_submenu_page(
                $submenu['parent_slug'],
                $submenu['page_title'],
                $submenu['menu_title'],
                $submenu['capability'],
                $submenu['menu_slug'],
                array($this, $submenu['function'])
            );
        }

        // Remove duplicate dashboard menu item
        remove_submenu_page('chatshop', 'chatshop');
    }

    /**
     * Get menu icon (base64 encoded SVG).
     *
     * @since    1.0.0
     * @return   string
     */
    private function get_menu_icon()
    {
        $svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H5.17L4 17.17V4H20V16Z" fill="#9ca3af"/>
            <circle cx="8" cy="10" r="1" fill="#9ca3af"/>
            <circle cx="12" cy="10" r="1" fill="#9ca3af"/>
            <circle cx="16" cy="10" r="1" fill="#9ca3af"/>
        </svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Get contacts badge count.
     *
     * @since    1.0.0
     * @return   string|null
     */
    private function get_contacts_badge()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_contacts';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return null;
        }

        $new_contacts = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE created_at >= %s",
                date('Y-m-d H:i:s', strtotime('-7 days'))
            )
        );

        if ($new_contacts > 0) {
            return '<span class="update-plugins count-' . $new_contacts . '"><span class="update-count">' . $new_contacts . '</span></span>';
        }

        return null;
    }

    /**
     * Check if premium version is active.
     *
     * @since    1.0.0
     * @return   bool
     */
    private function is_premium_active()
    {
        return defined('CHATSHOP_PREMIUM_VERSION') || get_option('chatshop_premium_license_key');
    }

    /**
     * Get current menu slug.
     *
     * @since    1.0.0
     * @return   string|null
     */
    public function get_current_menu_slug()
    {
        return $_GET['page'] ?? null;
    }

    /**
     * Check if current page is a ChatShop admin page.
     *
     * @since    1.0.0
     * @return   bool
     */
    public function is_chatshop_admin_page()
    {
        $current_slug = $this->get_current_menu_slug();

        if (! $current_slug) {
            return false;
        }

        return strpos($current_slug, 'chatshop') === 0;
    }

    /**
     * Get menu item by slug.
     *
     * @since    1.0.0
     * @param    string    $slug    Menu slug.
     * @return   array|null
     */
    public function get_menu_item($slug)
    {
        if ($slug === 'chatshop') {
            return $this->menu_items['main'];
        }

        foreach ($this->menu_items['submenus'] as $key => $submenu) {
            if ($submenu['menu_slug'] === $slug) {
                return $submenu;
            }
        }

        return null;
    }

    /**
     * Get all available menu items.
     *
     * @since    1.0.0
     * @return   array
     */
    public function get_all_menu_items()
    {
        return $this->menu_items;
    }

    /**
     * Add custom admin body classes.
     *
     * @since    1.0.0
     * @param    string    $classes    Current body classes.
     * @return   string
     */
    public function add_admin_body_classes($classes)
    {
        if (! $this->is_chatshop_admin_page()) {
            return $classes;
        }

        $current_slug = $this->get_current_menu_slug();
        $classes .= ' chatshop-admin';

        if ($current_slug) {
            $classes .= ' chatshop-page-' . str_replace('chatshop-', '', $current_slug);
        }

        // Add premium class if active
        if ($this->is_premium_active()) {
            $classes .= ' chatshop-premium';
        }

        return $classes;
    }

    /**
     * Customize admin footer text on ChatShop pages.
     *
     * @since    1.0.0
     * @param    string    $footer_text    Current footer text.
     * @return   string
     */
    public function customize_admin_footer($footer_text)
    {
        if (! $this->is_chatshop_admin_page()) {
            return $footer_text;
        }

        return sprintf(
            __('Thank you for using <a href="%1$s" target="_blank">ChatShop</a>. Please <a href="%2$s" target="_blank">rate us</a> on WordPress.org', 'chatshop'),
            'https://chatshop.com',
            'https://wordpress.org/plugins/chatshop/'
        );
    }

    /**
     * Add help tabs to admin pages.
     *
     * @since    1.0.0
     */
    public function add_help_tabs()
    {
        if (! $this->is_chatshop_admin_page()) {
            return;
        }

        $screen = get_current_screen();
        $current_slug = $this->get_current_menu_slug();

        // Add common help tab
        $screen->add_help_tab(array(
            'id'      => 'chatshop-overview',
            'title'   => __('Overview', 'chatshop'),
            'content' => $this->get_overview_help_content(),
        ));

        // Add page-specific help tabs
        switch ($current_slug) {
            case 'chatshop':
                $screen->add_help_tab(array(
                    'id'      => 'chatshop-dashboard-help',
                    'title'   => __('Dashboard', 'chatshop'),
                    'content' => $this->get_dashboard_help_content(),
                ));
                break;
            case 'chatshop-contacts':
                $screen->add_help_tab(array(
                    'id'      => 'chatshop-contacts-help',
                    'title'   => __('Contacts', 'chatshop'),
                    'content' => $this->get_contacts_help_content(),
                ));
                break;
            case 'chatshop-campaigns':
                $screen->add_help_tab(array(
                    'id'      => 'chatshop-campaigns-help',
                    'title'   => __('Campaigns', 'chatshop'),
                    'content' => $this->get_campaigns_help_content(),
                ));
                break;
            case 'chatshop-payment-links':
                $screen->add_help_tab(array(
                    'id'      => 'chatshop-payment-links-help',
                    'title'   => __('Payment Links', 'chatshop'),
                    'content' => $this->get_payment_links_help_content(),
                ));
                break;
            case 'chatshop-gateways':
                $screen->add_help_tab(array(
                    'id'      => 'chatshop-gateways-help',
                    'title'   => __('Payment Gateways', 'chatshop'),
                    'content' => $this->get_gateways_help_content(),
                ));
                break;
        }

        // Set help sidebar
        $screen->set_help_sidebar($this->get_help_sidebar_content());
    }

    /**
     * Get overview help content.
     *
     * @since    1.0.0
     * @return   string
     */
    private function get_overview_help_content()
    {
        return '<p>' . __('ChatShop is a comprehensive social commerce plugin that bridges WhatsApp marketing with multi-gateway payment processing for WooCommerce stores.', 'chatshop') . '</p>' .
            '<p>' . __('Use the menu items to navigate between different features:', 'chatshop') . '</p>' .
            '<ul>' .
            '<li><strong>' . __('Dashboard:', 'chatshop') . '</strong> ' . __('Overview of your ChatShop statistics and performance.', 'chatshop') . '</li>' .
            '<li><strong>' . __('Contacts:', 'chatshop') . '</strong> ' . __('Manage your WhatsApp contacts and customer data.', 'chatshop') . '</li>' .
            '<li><strong>' . __('Campaigns:', 'chatshop') . '</strong> ' . __('Create and manage WhatsApp marketing campaigns.', 'chatshop') . '</li>' .
            '<li><strong>' . __('Payment Links:', 'chatshop') . '</strong> ' . __('Generate and track payment links for WhatsApp sharing.', 'chatshop') . '</li>' .
            '<li><strong>' . __('Payment Gateways:', 'chatshop') . '</strong> ' . __('Configure payment processing methods.', 'chatshop') . '</li>' .
            '</ul>';
    }

    /**
     * Get dashboard help content.
     *
     * @since    1.0.0
     * @return   string
     */
    private function get_dashboard_help_content()
    {
        return '<p>' . __('The dashboard provides an overview of your ChatShop performance including:', 'chatshop') . '</p>' .
            '<ul>' .
            '<li>' . __('Contact growth and engagement metrics', 'chatshop') . '</li>' .
            '<li>' . __('Campaign performance statistics', 'chatshop') . '</li>' .
            '<li>' . __('Payment link conversion rates', 'chatshop') . '</li>' .
            '<li>' . __('Revenue tracking and gateway performance', 'chatshop') . '</li>' .
            '</ul>';
    }

    /**
     * Get contacts help content.
     *
     * @since    1.0.0
     * @return   string
     */
    private function get_contacts_help_content()
    {
        return '<p>' . __('Manage your WhatsApp contacts effectively:', 'chatshop') . '</p>' .
            '<ul>' .
            '<li>' . __('Import contacts from CSV files or WooCommerce customers', 'chatshop') . '</li>' .
            '<li>' . __('Segment contacts based on purchase history and behavior', 'chatshop') . '</li>' .
            '<li>' . __('Track engagement and message history', 'chatshop') . '</li>' .
            '<li>' . __('Manage opt-ins and preferences', 'chatshop') . '</li>' .
            '</ul>';
    }

    /**
     * Get campaigns help content.
     *
     * @since    1.0.0
     * @return   string
     */
    private function get_campaigns_help_content()
    {
        return '<p>' . __('Create effective WhatsApp marketing campaigns:', 'chatshop') . '</p>' .
            '<ul>' .
            '<li>' . __('Design message templates with personalization', 'chatshop') . '</li>' .
            '<li>' . __('Schedule campaigns for optimal timing', 'chatshop') . '</li>' .
            '<li>' . __('Target specific contact segments', 'chatshop') . '</li>' .
            '<li>' . __('Track delivery rates and engagement', 'chatshop') . '</li>' .
            '</ul>';
    }

    /**
     * Get payment links help content.
     *
     * @since    1.0.0
     * @return   string
     */
    private function get_payment_links_help_content()
    {
        return '<p>' . __('Generate and manage payment links for WhatsApp commerce:', 'chatshop') . '</p>' .
            '<ul>' .
            '<li>' . __('Create custom payment links for products or services', 'chatshop') . '</li>' .
            '<li>' . __('Generate QR codes for easy sharing', 'chatshop') . '</li>' .
            '<li>' . __('Track link performance and conversions', 'chatshop') . '</li>' .
            '<li>' . __('Integrate with WhatsApp campaigns', 'chatshop') . '</li>' .
            '</ul>';
    }

    /**
     * Get gateways help content.
     *
     * @since    1.0.0
     * @return   string
     */
    private function get_gateways_help_content()
    {
        return '<p>' . __('Configure payment gateways for your store:', 'chatshop') . '</p>' .
            '<ul>' .
            '<li>' . __('Set up Paystack, PayPal, Flutterwave, and Razorpay', 'chatshop') . '</li>' .
            '<li>' . __('Test gateway connections and webhooks', 'chatshop') . '</li>' .
            '<li>' . __('Monitor transaction success rates', 'chatshop') . '</li>' .
            '<li>' . __('Configure gateway-specific settings', 'chatshop') . '</li>' .
            '</ul>';
    }

    /**
     * Get help sidebar content.
     *
     * @since    1.0.0
     * @return   string
     */
    private function get_help_sidebar_content()
    {
        return '<p><strong>' . __('For more information:', 'chatshop') . '</strong></p>' .
            '<p><a href="https://docs.chatshop.com" target="_blank">' . __('Documentation', 'chatshop') . '</a></p>' .
            '<p><a href="https://chatshop.com/support" target="_blank">' . __('Support Forum', 'chatshop') . '</a></p>' .
            '<p><a href="https://chatshop.com/contact" target="_blank">' . __('Contact Support', 'chatshop') . '</a></p>';
    }

    // Page display methods
    public function display_dashboard()
    {
        include_once 'partials/dashboard.php';
    }
    public function display_contacts()
    {
        include_once 'partials/contacts.php';
    }
    public function display_campaigns()
    {
        include_once 'partials/campaigns.php';
    }
    public function display_payment_links()
    {
        include_once 'partials/payment-links.php';
    }
    public function display_gateways()
    {
        include_once 'partials/payment-gateways.php';
    }
    public function display_templates()
    {
        include_once 'partials/templates.php';
    }
    public function display_forms()
    {
        include_once 'partials/forms.php';
    }
    public function display_analytics()
    {
        if (! $this->is_premium_active()) {
            include_once 'partials/premium-required.php';
            return;
        }
        include_once 'partials/analytics.php';
    }
    public function display_product_notifications()
    {
        if (! $this->is_premium_active()) {
            include_once 'partials/premium-required.php';
            return;
        }
        include_once 'partials/product-notifications.php';
    }
    public function display_components()
    {
        include_once 'partials/components.php';
    }
    public function display_settings()
    {
        include_once 'partials/settings.php';
    }
    public function display_system_info()
    {
        include_once 'partials/system-info.php';
    }
}
