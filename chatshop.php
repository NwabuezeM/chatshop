<?php

/**
 * Plugin Name: ChatShop - Social Commerce for WooCommerce
 * Plugin URI: https://chatshop.com
 * Description: Bridge WhatsApp marketing with multi-gateway payment processing. Convert social engagement into measurable sales with automated messaging, payment links, and advanced analytics.
 * Version: 1.0.0
 * Author: ChatShop Team
 * Author URI: https://chatshop.com
 * Text Domain: chatshop
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package ChatShop
 * @since   1.0.0
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

// Define plugin constants
if (! defined('CHATSHOP_VERSION')) {
    define('CHATSHOP_VERSION', '1.0.0');
}

if (! defined('CHATSHOP_MINIMUM_PHP_VERSION')) {
    define('CHATSHOP_MINIMUM_PHP_VERSION', '7.4');
}

if (! defined('CHATSHOP_MINIMUM_WP_VERSION')) {
    define('CHATSHOP_MINIMUM_WP_VERSION', '5.0');
}

if (! defined('CHATSHOP_MINIMUM_WC_VERSION')) {
    define('CHATSHOP_MINIMUM_WC_VERSION', '4.0');
}

if (! defined('CHATSHOP_PLUGIN_FILE')) {
    define('CHATSHOP_PLUGIN_FILE', __FILE__);
}

if (! defined('CHATSHOP_PLUGIN_DIR')) {
    define('CHATSHOP_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (! defined('CHATSHOP_PLUGIN_URL')) {
    define('CHATSHOP_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (! defined('CHATSHOP_PLUGIN_BASENAME')) {
    define('CHATSHOP_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

// Database version for migrations
if (! defined('CHATSHOP_DB_VERSION')) {
    define('CHATSHOP_DB_VERSION', '1.0.0');
}

// API configuration
if (! defined('CHATSHOP_API_NAMESPACE')) {
    define('CHATSHOP_API_NAMESPACE', 'chatshop/v1');
}

// Component system constants
if (! defined('CHATSHOP_COMPONENTS_OPTION')) {
    define('CHATSHOP_COMPONENTS_OPTION', 'chatshop_active_components');
}

if (! defined('CHATSHOP_COMPONENTS_DIR')) {
    define('CHATSHOP_COMPONENTS_DIR', CHATSHOP_PLUGIN_DIR . 'components/');
}

// Cache constants
if (! defined('CHATSHOP_CACHE_PREFIX')) {
    define('CHATSHOP_CACHE_PREFIX', 'chatshop_cache_');
}

if (! defined('CHATSHOP_CACHE_EXPIRATION')) {
    define('CHATSHOP_CACHE_EXPIRATION', 12 * HOUR_IN_SECONDS);
}

// Security constants
if (! defined('CHATSHOP_ENCRYPTION_METHOD')) {
    define('CHATSHOP_ENCRYPTION_METHOD', 'AES-256-CBC');
}

// Debug mode
if (! defined('CHATSHOP_DEBUG')) {
    define('CHATSHOP_DEBUG', false);
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-chatshop-activator.php
 */
function activate_chatshop()
{
    require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-activator.php';
    ChatShop_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-chatshop-deactivator.php
 */
function deactivate_chatshop()
{
    require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-deactivator.php';
    ChatShop_Deactivator::deactivate();
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'activate_chatshop');
register_deactivation_hook(__FILE__, 'deactivate_chatshop');

/**
 * Main ChatShop Plugin Class
 *
 * @since 1.0.0
 */
final class ChatShop
{

    /**
     * Single instance of the class
     *
     * @var ChatShop
     * @since 1.0.0
     */
    private static $instance = null;

    /**
     * The loader that's responsible for maintaining and registering all hooks
     *
     * @var ChatShop_Loader
     * @since 1.0.0
     */
    protected $loader;

    /**
     * Component loader instance
     *
     * @var ChatShop_Component_Loader
     * @since 1.0.0
     */
    protected $component_loader;

    /**
     * Component registry instance
     *
     * @var ChatShop_Component_Registry
     * @since 1.0.0
     */
    protected $component_registry;

    /**
     * The unique identifier of this plugin
     *
     * @var string
     * @since 1.0.0
     */
    protected $plugin_name;

    /**
     * The current version of the plugin
     *
     * @var string
     * @since 1.0.0
     */
    protected $version;

    /**
     * Main ChatShop Instance
     *
     * Ensures only one instance of ChatShop is loaded or can be loaded.
     *
     * @static
     * @return ChatShop - Main instance
     * @since 1.0.0
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->plugin_name = 'chatshop';
        $this->version = CHATSHOP_VERSION;

        // Check dependencies before proceeding
        if (! $this->check_dependencies()) {
            return;
        }

        // Load dependencies
        $this->load_dependencies();

        // Set locale for internationalization
        $this->set_locale();

        // Initialize the database
        $this->init_database();

        // Define admin hooks
        $this->define_admin_hooks();

        // Define public hooks
        $this->define_public_hooks();

        // Initialize components
        $this->init_components();

        // Initialize API
        $this->init_api();

        // Initialize cron jobs
        $this->init_cron();

        // Initialize CLI commands
        $this->init_cli();

        // Fire loaded action
        do_action('chatshop_loaded');
    }

    /**
     * Check plugin dependencies
     *
     * @return bool
     * @since 1.0.0
     */
    private function check_dependencies()
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, CHATSHOP_MINIMUM_PHP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return false;
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), CHATSHOP_MINIMUM_WP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'wp_version_notice'));
            return false;
        }

        // Check if WooCommerce is active
        if (! $this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return false;
        }

        return true;
    }

    /**
     * Load required dependencies
     *
     * @since 1.0.0
     */
    private function load_dependencies()
    {
        // Core includes
        require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-loader.php';
        require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-i18n.php';
        require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-logger.php';
        require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-helper.php';
        require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-security.php';
        require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-cache.php';
        require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-database.php';
        require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-sanitizer.php';
        require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-validator.php';

        // Component system
        require_once CHATSHOP_PLUGIN_DIR . 'includes/abstracts/abstract-chatshop-component.php';
        require_once CHATSHOP_PLUGIN_DIR . 'includes/abstracts/abstract-chatshop-payment-gateway.php';
        require_once CHATSHOP_PLUGIN_DIR . 'includes/abstracts/abstract-chatshop-api-client.php';
        require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-component-loader.php';
        require_once CHATSHOP_PLUGIN_DIR . 'includes/class-chatshop-component-registry.php';

        // Admin classes
        if (is_admin()) {
            require_once CHATSHOP_PLUGIN_DIR . 'admin/class-chatshop-admin.php';
            require_once CHATSHOP_PLUGIN_DIR . 'admin/class-chatshop-admin-menu.php';
            require_once CHATSHOP_PLUGIN_DIR . 'admin/class-chatshop-settings.php';
        }

        // Public classes
        require_once CHATSHOP_PLUGIN_DIR . 'public/class-chatshop-public.php';
        require_once CHATSHOP_PLUGIN_DIR . 'public/class-chatshop-shortcodes.php';

        // API classes
        require_once CHATSHOP_PLUGIN_DIR . 'api/class-chatshop-api.php';
        require_once CHATSHOP_PLUGIN_DIR . 'api/class-chatshop-api-auth.php';

        // Initialize loader
        $this->loader = new ChatShop_Loader();
    }

    /**
     * Set plugin locale for internationalization
     *
     * @since 1.0.0
     */
    private function set_locale()
    {
        $plugin_i18n = new ChatShop_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Initialize database tables
     *
     * @since 1.0.0
     */
    private function init_database()
    {
        $database = new ChatShop_Database();

        // Hook database initialization
        $this->loader->add_action('plugins_loaded', $database, 'check_database_version');
        $this->loader->add_action('chatshop_run_migrations', $database, 'run_migrations');
    }

    /**
     * Register all admin hooks
     *
     * @since 1.0.0
     */
    private function define_admin_hooks()
    {
        if (! is_admin()) {
            return;
        }

        $plugin_admin = new ChatShop_Admin($this->get_plugin_name(), $this->get_version());

        // Admin scripts and styles
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

        // Admin menu
        $admin_menu = new ChatShop_Admin_Menu();
        $this->loader->add_action('admin_menu', $admin_menu, 'add_admin_menu');

        // Settings
        $settings = new ChatShop_Settings();
        $this->loader->add_action('admin_init', $settings, 'register_settings');
        $this->loader->add_action('admin_notices', $settings, 'admin_notices');

        // Plugin action links
        $this->loader->add_filter('plugin_action_links_' . CHATSHOP_PLUGIN_BASENAME, $plugin_admin, 'add_action_links');
        $this->loader->add_filter('plugin_row_meta', $plugin_admin, 'add_row_meta', 10, 2);

        // AJAX handlers
        $this->loader->add_action('wp_ajax_chatshop_save_settings', $settings, 'ajax_save_settings');
        $this->loader->add_action('wp_ajax_chatshop_toggle_component', $settings, 'ajax_toggle_component');
        $this->loader->add_action('wp_ajax_chatshop_test_connection', $settings, 'ajax_test_connection');
    }

    /**
     * Register all public hooks
     *
     * @since 1.0.0
     */
    private function define_public_hooks()
    {
        $plugin_public = new ChatShop_Public($this->get_plugin_name(), $this->get_version());

        // Public scripts and styles
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');

        // Shortcodes
        $shortcodes = new ChatShop_Shortcodes();
        $this->loader->add_action('init', $shortcodes, 'register_shortcodes');

        // AJAX handlers for logged in users
        $this->loader->add_action('wp_ajax_chatshop_create_payment_link', $plugin_public, 'ajax_create_payment_link');
        $this->loader->add_action('wp_ajax_chatshop_send_whatsapp', $plugin_public, 'ajax_send_whatsapp');

        // AJAX handlers for non-logged in users
        $this->loader->add_action('wp_ajax_nopriv_chatshop_process_payment', $plugin_public, 'ajax_process_payment');
        $this->loader->add_action('wp_ajax_nopriv_chatshop_verify_payment', $plugin_public, 'ajax_verify_payment');
    }

    /**
     * Initialize component system
     *
     * @since 1.0.0
     */
    private function init_components()
    {
        // Initialize component registry
        $this->component_registry = new ChatShop_Component_Registry();

        // Initialize component loader
        $this->component_loader = new ChatShop_Component_Loader($this->component_registry);

        // Register core components
        $this->register_core_components();

        // Load active components
        $this->loader->add_action('init', $this->component_loader, 'load_components', 5);

        // Allow third-party component registration
        $this->loader->add_action('chatshop_register_components', $this->component_registry, 'register_component', 10);
    }

    /**
     * Register core plugin components
     *
     * @since 1.0.0
     */
    private function register_core_components()
    {
        // Payment component
        $this->component_registry->register_component('payment', array(
            'name'         => __('Payment System', 'chatshop'),
            'description'  => __('Multi-gateway payment processing with link generation', 'chatshop'),
            'version'      => '1.0.0',
            'path'         => CHATSHOP_COMPONENTS_DIR . 'payment/class-chatshop-payment-manager.php',
            'class'        => 'ChatShop\\Components\\Payment\\ChatShop_Payment_Manager',
            'dependencies' => array(),
            'required'     => true,
            'autoload'     => true,
        ));

        // WhatsApp component
        $this->component_registry->register_component('whatsapp', array(
            'name'         => __('WhatsApp Integration', 'chatshop'),
            'description'  => __('WhatsApp Business API for messaging and automation', 'chatshop'),
            'version'      => '1.0.0',
            'path'         => CHATSHOP_COMPONENTS_DIR . 'whatsapp/class-chatshop-whatsapp-manager.php',
            'class'        => 'ChatShop\\Components\\WhatsApp\\ChatShop_WhatsApp_Manager',
            'dependencies' => array(),
            'required'     => true,
            'autoload'     => true,
        ));

        // Analytics component
        $this->component_registry->register_component('analytics', array(
            'name'         => __('Analytics & Reporting', 'chatshop'),
            'description'  => __('Track conversions, revenue attribution, and generate reports', 'chatshop'),
            'version'      => '1.0.0',
            'path'         => CHATSHOP_COMPONENTS_DIR . 'analytics/class-chatshop-analytics-manager.php',
            'class'        => 'ChatShop\\Components\\Analytics\\ChatShop_Analytics_Manager',
            'dependencies' => array('payment', 'whatsapp'),
            'required'     => false,
            'autoload'     => true,
        ));

        // WooCommerce Integration component
        $this->component_registry->register_component('integration', array(
            'name'         => __('WooCommerce Integration', 'chatshop'),
            'description'  => __('Deep integration with WooCommerce orders and customers', 'chatshop'),
            'version'      => '1.0.0',
            'path'         => CHATSHOP_COMPONENTS_DIR . 'integration/class-chatshop-woo-integration.php',
            'class'        => 'ChatShop\\Components\\Integration\\ChatShop_Woo_Integration',
            'dependencies' => array(),
            'required'     => true,
            'autoload'     => true,
        ));

        // Allow components to register payment gateways
        $this->loader->add_action('chatshop_components_loaded', $this, 'register_payment_gateways');
    }

    /**
     * Register payment gateways
     *
     * @since 1.0.0
     */
    public function register_payment_gateways()
    {
        $payment_component = $this->component_loader->get_component('payment');

        if (! $payment_component) {
            return;
        }

        // Register Paystack gateway
        $payment_component->register_gateway('paystack', array(
            'name'        => __('Paystack', 'chatshop'),
            'description' => __('Accept payments via Paystack', 'chatshop'),
            'path'        => CHATSHOP_COMPONENTS_DIR . 'payment/gateways/paystack/class-chatshop-paystack-gateway.php',
            'class'       => 'ChatShop\\Components\\Payment\\Gateways\\ChatShop_Paystack_Gateway',
            'countries'   => array('NG', 'GH', 'ZA', 'KE'),
            'currencies'  => array('NGN', 'GHS', 'ZAR', 'KES', 'USD'),
        ));

        // Register PayPal gateway
        $payment_component->register_gateway('paypal', array(
            'name'        => __('PayPal', 'chatshop'),
            'description' => __('Accept payments via PayPal', 'chatshop'),
            'path'        => CHATSHOP_COMPONENTS_DIR . 'payment/gateways/paypal/class-chatshop-paypal-gateway.php',
            'class'       => 'ChatShop\\Components\\Payment\\Gateways\\ChatShop_PayPal_Gateway',
            'countries'   => array(), // Available globally
            'currencies'  => array('USD', 'EUR', 'GBP', 'CAD', 'AUD'),
        ));

        // Register Flutterwave gateway
        $payment_component->register_gateway('flutterwave', array(
            'name'        => __('Flutterwave', 'chatshop'),
            'description' => __('Accept payments via Flutterwave', 'chatshop'),
            'path'        => CHATSHOP_COMPONENTS_DIR . 'payment/gateways/flutterwave/class-chatshop-flutterwave-gateway.php',
            'class'       => 'ChatShop\\Components\\Payment\\Gateways\\ChatShop_Flutterwave_Gateway',
            'countries'   => array('NG', 'KE', 'GH', 'ZA', 'TZ', 'UG'),
            'currencies'  => array('NGN', 'KES', 'GHS', 'ZAR', 'TZS', 'UGX', 'USD'),
        ));

        // Register Razorpay gateway
        $payment_component->register_gateway('razorpay', array(
            'name'        => __('Razorpay', 'chatshop'),
            'description' => __('Accept payments via Razorpay', 'chatshop'),
            'path'        => CHATSHOP_COMPONENTS_DIR . 'payment/gateways/razorpay/class-chatshop-razorpay-gateway.php',
            'class'       => 'ChatShop\\Components\\Payment\\Gateways\\ChatShop_Razorpay_Gateway',
            'countries'   => array('IN'),
            'currencies'  => array('INR', 'USD'),
        ));

        // Allow third-party gateway registration
        do_action('chatshop_register_payment_gateways', $payment_component);
    }

    /**
     * Initialize REST API
     *
     * @since 1.0.0
     */
    private function init_api()
    {
        $api = new ChatShop_API();
        $this->loader->add_action('rest_api_init', $api, 'register_routes');

        // Initialize API authentication
        $api_auth = new ChatShop_API_Auth();
        $this->loader->add_filter('rest_authentication_errors', $api_auth, 'authenticate');
    }

    /**
     * Initialize cron jobs
     *
     * @since 1.0.0
     */
    private function init_cron()
    {
        // Only load if cron manager exists
        if (! file_exists(CHATSHOP_PLUGIN_DIR . 'cron/class-chatshop-cron-manager.php')) {
            return;
        }

        require_once CHATSHOP_PLUGIN_DIR . 'cron/class-chatshop-cron-manager.php';
        $cron_manager = new ChatShop_Cron_Manager();

        // Schedule cron jobs
        $this->loader->add_action('init', $cron_manager, 'schedule_jobs');

        // Register cron hooks
        $this->loader->add_action('chatshop_cleanup_cron', $cron_manager, 'run_cleanup');
        $this->loader->add_action('chatshop_analytics_cron', $cron_manager, 'run_analytics');
        $this->loader->add_action('chatshop_campaign_cron', $cron_manager, 'run_campaigns');
    }

    /**
     * Initialize WP-CLI commands
     *
     * @since 1.0.0
     */
    private function init_cli()
    {
        // Only load in CLI context
        if (! defined('WP_CLI') || ! WP_CLI) {
            return;
        }

        // Check if CLI files exist
        if (! file_exists(CHATSHOP_PLUGIN_DIR . 'cli/class-chatshop-cli.php')) {
            return;
        }

        require_once CHATSHOP_PLUGIN_DIR . 'cli/class-chatshop-cli.php';

        // Register CLI commands
        WP_CLI::add_command('chatshop', 'ChatShop_CLI');
    }

    /**
     * Run the loader to execute all hooks
     *
     * @since 1.0.0
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * Get plugin name
     *
     * @return string
     * @since 1.0.0
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * Get loader instance
     *
     * @return ChatShop_Loader
     * @since 1.0.0
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Get version
     *
     * @return string
     * @since 1.0.0
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
     * Get component loader
     *
     * @return ChatShop_Component_Loader
     * @since 1.0.0
     */
    public function get_component_loader()
    {
        return $this->component_loader;
    }

    /**
     * Get component registry
     *
     * @return ChatShop_Component_Registry
     * @since 1.0.0
     */
    public function get_component_registry()
    {
        return $this->component_registry;
    }

    /**
     * Get a specific component
     *
     * @param string $component_id Component identifier
     * @return object|null Component instance or null
     * @since 1.0.0
     */
    public function get_component($component_id)
    {
        return $this->component_loader->get_component($component_id);
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     * @since 1.0.0
     */
    private function is_woocommerce_active()
    {
        return in_array(
            'woocommerce/woocommerce.php',
            apply_filters('active_plugins', get_option('active_plugins'))
        ) || (is_multisite() && array_key_exists(
            'woocommerce/woocommerce.php',
            get_site_option('active_sitewide_plugins', array())
        ));
    }

    /**
     * PHP version notice
     *
     * @since 1.0.0
     */
    public function php_version_notice()
    {
        $message = sprintf(
            /* translators: 1: Plugin name 2: Current PHP version 3: Required PHP version */
            __('%1$s requires PHP version %3$s or higher. You are running version %2$s.', 'chatshop'),
            '<strong>ChatShop</strong>',
            PHP_VERSION,
            CHATSHOP_MINIMUM_PHP_VERSION
        );

        printf('<div class="notice notice-error"><p>%s</p></div>', wp_kses_post($message));
    }

    /**
     * WordPress version notice
     *
     * @since 1.0.0
     */
    public function wp_version_notice()
    {
        $message = sprintf(
            /* translators: 1: Plugin name 2: Current WordPress version 3: Required WordPress version */
            __('%1$s requires WordPress version %3$s or higher. You are running version %2$s.', 'chatshop'),
            '<strong>ChatShop</strong>',
            get_bloginfo('version'),
            CHATSHOP_MINIMUM_WP_VERSION
        );

        printf('<div class="notice notice-error"><p>%s</p></div>', wp_kses_post($message));
    }

    /**
     * WooCommerce missing notice
     *
     * @since 1.0.0
     */
    public function woocommerce_missing_notice()
    {
        $message = sprintf(
            /* translators: 1: Plugin name 2: WooCommerce link */
            __('%1$s requires WooCommerce to be installed and activated. %2$s', 'chatshop'),
            '<strong>ChatShop</strong>',
            '<a href="' . esc_url(admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')) . '">' . __('Install WooCommerce', 'chatshop') . '</a>'
        );

        printf('<div class="notice notice-error"><p>%s</p></div>', wp_kses_post($message));
    }

    /**
     * Prevent cloning
     *
     * @since 1.0.0
     */
    private function __clone()
    {
        _doing_it_wrong(__FUNCTION__, __('Cloning is forbidden.', 'chatshop'), '1.0.0');
    }

    /**
     * Prevent unserializing
     *
     * @since 1.0.0
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, __('Unserializing instances of this class is forbidden.', 'chatshop'), '1.0.0');
    }
}

/**
 * Get main ChatShop instance
 *
 * @return ChatShop
 * @since 1.0.0
 */
function ChatShop()
{
    return ChatShop::instance();
}

// Initialize the plugin
$GLOBALS['chatshop'] = ChatShop();

// Run the plugin
add_action('plugins_loaded', array(ChatShop(), 'run'));
