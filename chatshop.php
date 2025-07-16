<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://chatshop.com
 * @since             1.0.0
 * @package           ChatShop
 *
 * @wordpress-plugin
 * Plugin Name:       ChatShop
 * Plugin URI:        https://chatshop.com
 * Description:       Comprehensive social commerce plugin that bridges WhatsApp marketing with multi-gateway payment processing for WooCommerce stores.
 * Version:           1.0.0
 * Author:            ChatShop Team
 * Author URI:        https://chatshop.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       chatshop
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * WC requires at least: 4.0
 * WC tested up to:   8.5
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('CHATSHOP_VERSION', '1.0.0');

/**
 * Plugin constants
 */
define('CHATSHOP_PLUGIN_FILE', __FILE__);
define('CHATSHOP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHATSHOP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CHATSHOP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-chatshop-activator.php
 */
function activate_chatshop()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-chatshop-activator.php';
    ChatShop_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-chatshop-deactivator.php
 */
function deactivate_chatshop()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-chatshop-deactivator.php';
    ChatShop_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_chatshop');
register_deactivation_hook(__FILE__, 'deactivate_chatshop');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-chatshop.php';

/**
 * Main ChatShop Class
 *
 * @class ChatShop
 * @version 1.0.0
 */
final class ChatShop
{

    /**
     * The single instance of the class.
     *
     * @var ChatShop
     * @since 1.0.0
     */
    protected static $_instance = null;

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      ChatShop_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Component loader instance
     *
     * @var ChatShop_Component_Loader
     */
    protected $component_loader;

    /**
     * Component registry instance
     *
     * @var ChatShop_Component_Registry
     */
    protected $component_registry;

    /**
     * Main ChatShop Instance.
     *
     * Ensures only one instance of ChatShop is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @see ChatShop()
     * @return ChatShop - Main instance.
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone()
    {
        wc_doing_it_wrong(__FUNCTION__, __('Cloning is forbidden.', 'chatshop'), '1.0.0');
    }

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup()
    {
        wc_doing_it_wrong(__FUNCTION__, __('Unserializing instances of this class is forbidden.', 'chatshop'), '1.0.0');
    }

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        if (defined('CHATSHOP_VERSION')) {
            $this->version = CHATSHOP_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'chatshop';

        $this->check_dependencies();
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->init_components();
        $this->init_api();
    }

    /**
     * Check if required dependencies are active
     */
    private function check_dependencies()
    {
        if (! class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return false;
        }
        return true;
    }

    /**
     * Admin notice for missing WooCommerce
     */
    public function woocommerce_missing_notice()
    {
?>
        <div class="error">
            <p><?php _e('ChatShop requires WooCommerce to be installed and active.', 'chatshop'); ?></p>
        </div>
<?php
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - ChatShop_Loader. Orchestrates the hooks of the plugin.
     * - ChatShop_i18n. Defines internationalization functionality.
     * - ChatShop_Admin. Defines all hooks for the admin area.
     * - ChatShop_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies()
    {

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path(__FILE__) . 'includes/class-chatshop-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once plugin_dir_path(__FILE__) . 'includes/class-chatshop-i18n.php';

        /**
         * Core utility classes
         */
        require_once plugin_dir_path(__FILE__) . 'includes/class-chatshop-database.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-chatshop-security.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-chatshop-cache.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-chatshop-logger.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-chatshop-helper.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-chatshop-sanitizer.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-chatshop-validator.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-chatshop-exporter.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-chatshop-importer.php';

        /**
         * Component system classes
         */
        require_once plugin_dir_path(__FILE__) . 'includes/class-chatshop-component-loader.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-chatshop-component-registry.php';

        /**
         * Abstract classes and interfaces
         * IMPORTANT: Load interfaces before the classes that implement them
         */
        require_once plugin_dir_path(__FILE__) . 'includes/abstracts/abstract-chatshop-component.php';
        require_once plugin_dir_path(__FILE__) . 'includes/abstracts/abstract-chatshop-api-client.php';

        // Load the payment gateway interface FIRST
        require_once plugin_dir_path(__FILE__) . 'includes/abstracts/interface-chatshop-payment-gateway.php';

        // Then load the abstract class that implements the interface
        require_once plugin_dir_path(__FILE__) . 'includes/abstracts/abstract-chatshop-payment-gateway.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path(__FILE__) . 'admin/class-chatshop-admin.php';
        require_once plugin_dir_path(__FILE__) . 'admin/class-chatshop-admin-menu.php';
        require_once plugin_dir_path(__FILE__) . 'admin/class-chatshop-settings.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once plugin_dir_path(__FILE__) . 'public/class-chatshop-public.php';
        require_once plugin_dir_path(__FILE__) . 'public/class-chatshop-shortcodes.php';

        /**
         * Load component dependencies
         */
        $this->load_component_dependencies();

        /**
         * API classes
         */
        require_once plugin_dir_path(__FILE__) . 'api/class-chatshop-api.php';
        require_once plugin_dir_path(__FILE__) . 'api/class-chatshop-api-auth.php';

        /**
         * Cron jobs
         */
        require_once plugin_dir_path(__FILE__) . 'cron/class-chatshop-cron-manager.php';
        require_once plugin_dir_path(__FILE__) . 'cron/class-chatshop-cleanup-cron.php';
        require_once plugin_dir_path(__FILE__) . 'cron/class-chatshop-analytics-cron.php';
        require_once plugin_dir_path(__FILE__) . 'cron/class-chatshop-campaign-cron.php';

        /**
         * CLI commands
         */
        if (defined('WP_CLI') && WP_CLI) {
            require_once plugin_dir_path(__FILE__) . 'cli/class-chatshop-cli.php';
            require_once plugin_dir_path(__FILE__) . 'cli/class-chatshop-payment-cli.php';
            require_once plugin_dir_path(__FILE__) . 'cli/class-chatshop-whatsapp-cli.php';
        }

        $this->loader = new ChatShop_Loader();
    }

    /**
     * Load component-specific dependencies
     */
    private function load_component_dependencies()
    {

        // Payment component
        require_once plugin_dir_path(__FILE__) . 'components/payment/class-chatshop-payment-manager.php';
        require_once plugin_dir_path(__FILE__) . 'components/payment/class-chatshop-payment-factory.php';
        require_once plugin_dir_path(__FILE__) . 'components/payment/class-chatshop-payment-link-generator.php';
        require_once plugin_dir_path(__FILE__) . 'components/payment/class-chatshop-payment-webhook-handler.php';
        require_once plugin_dir_path(__FILE__) . 'components/payment/class-chatshop-payment-validator.php';
        require_once plugin_dir_path(__FILE__) . 'components/payment/class-chatshop-payment-logger.php';
        require_once plugin_dir_path(__FILE__) . 'components/payment/class-chatshop-payment-encryption.php';
        require_once plugin_dir_path(__FILE__) . 'components/payment/class-chatshop-payment-status.php';
        require_once plugin_dir_path(__FILE__) . 'components/payment/class-chatshop-refund-handler.php';
        require_once plugin_dir_path(__FILE__) . 'components/payment/class-chatshop-subscription-handler.php';
        require_once plugin_dir_path(__FILE__) . 'components/payment/class-chatshop-payment-queue.php';

        // Payment gateways
        $this->load_payment_gateways();

        // WhatsApp component
        require_once plugin_dir_path(__FILE__) . 'components/whatsapp/class-chatshop-whatsapp-manager.php';
        require_once plugin_dir_path(__FILE__) . 'components/whatsapp/class-chatshop-whatsapp-api.php';
        require_once plugin_dir_path(__FILE__) . 'components/whatsapp/class-chatshop-message-sender.php';
        require_once plugin_dir_path(__FILE__) . 'components/whatsapp/class-chatshop-message-templates.php';
        require_once plugin_dir_path(__FILE__) . 'components/whatsapp/class-chatshop-contact-manager.php';
        require_once plugin_dir_path(__FILE__) . 'components/whatsapp/class-chatshop-whatsapp-webhook.php';
        require_once plugin_dir_path(__FILE__) . 'components/whatsapp/class-chatshop-campaign-manager.php';
        require_once plugin_dir_path(__FILE__) . 'components/whatsapp/class-chatshop-automation.php';
        require_once plugin_dir_path(__FILE__) . 'components/whatsapp/class-chatshop-whatsapp-session.php';
        require_once plugin_dir_path(__FILE__) . 'components/whatsapp/class-chatshop-media-handler.php';
        require_once plugin_dir_path(__FILE__) . 'components/whatsapp/class-chatshop-group-manager.php';
        require_once plugin_dir_path(__FILE__) . 'components/whatsapp/class-chatshop-rate-limiter.php';

        // Analytics component
        require_once plugin_dir_path(__FILE__) . 'components/analytics/class-chatshop-analytics-manager.php';
        require_once plugin_dir_path(__FILE__) . 'components/analytics/class-chatshop-metrics-collector.php';
        require_once plugin_dir_path(__FILE__) . 'components/analytics/class-chatshop-report-generator.php';
        require_once plugin_dir_path(__FILE__) . 'components/analytics/class-chatshop-conversion-tracker.php';

        // Integration component
        require_once plugin_dir_path(__FILE__) . 'components/integration/class-chatshop-woo-integration.php';
        require_once plugin_dir_path(__FILE__) . 'components/integration/class-chatshop-order-handler.php';
        require_once plugin_dir_path(__FILE__) . 'components/integration/class-chatshop-product-sync.php';
        require_once plugin_dir_path(__FILE__) . 'components/integration/class-chatshop-customer-sync.php';
        require_once plugin_dir_path(__FILE__) . 'components/integration/class-chatshop-inventory-tracker.php';

        // Database tables
        require_once plugin_dir_path(__FILE__) . 'components/payment/database/class-chatshop-payment-table.php';
        require_once plugin_dir_path(__FILE__) . 'components/payment/database/class-chatshop-transaction-table.php';
        require_once plugin_dir_path(__FILE__) . 'components/payment/database/class-chatshop-payment-meta-table.php';
        require_once plugin_dir_path(__FILE__) . 'components/whatsapp/database/class-chatshop-contact-table.php';
        require_once plugin_dir_path(__FILE__) . 'components/whatsapp/database/class-chatshop-message-table.php';
        require_once plugin_dir_path(__FILE__) . 'components/whatsapp/database/class-chatshop-campaign-table.php';
        require_once plugin_dir_path(__FILE__) . 'components/analytics/database/class-chatshop-analytics-table.php';
        require_once plugin_dir_path(__FILE__) . 'components/analytics/database/class-chatshop-conversion-table.php';
    }

    /**
     * Load payment gateway implementations
     */
    private function load_payment_gateways()
    {
        $gateways = array('paystack', 'paypal', 'flutterwave', 'razorpay');

        foreach ($gateways as $gateway) {
            $gateway_path = plugin_dir_path(__FILE__) . 'components/payment/gateways/' . $gateway . '/';

            if (file_exists($gateway_path . 'class-chatshop-' . $gateway . '-gateway.php')) {
                require_once $gateway_path . 'class-chatshop-' . $gateway . '-gateway.php';
            }

            if (file_exists($gateway_path . 'class-chatshop-' . $gateway . '-api.php')) {
                require_once $gateway_path . 'class-chatshop-' . $gateway . '-api.php';
            }

            if (file_exists($gateway_path . 'class-chatshop-' . $gateway . '-webhook.php')) {
                require_once $gateway_path . 'class-chatshop-' . $gateway . '-webhook.php';
            }

            if (file_exists($gateway_path . 'class-chatshop-' . $gateway . '-blocks.php')) {
                require_once $gateway_path . 'class-chatshop-' . $gateway . '-blocks.php';
            }

            if (file_exists($gateway_path . 'class-chatshop-' . $gateway . '-validator.php')) {
                require_once $gateway_path . 'class-chatshop-' . $gateway . '-validator.php';
            }
        }
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the ChatShop_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale()
    {

        $plugin_i18n = new ChatShop_i18n();

        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks()
    {

        $plugin_admin = new ChatShop_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

        // Admin menu
        $admin_menu = new ChatShop_Admin_Menu();
        $this->loader->add_action('admin_menu', $admin_menu, 'add_admin_menu');

        // Settings
        $settings = new ChatShop_Settings();
        $this->loader->add_action('admin_init', $settings, 'init_settings');

        // Add action links
        $this->loader->add_filter('plugin_action_links_' . CHATSHOP_PLUGIN_BASENAME, $this, 'add_action_links');
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks()
    {

        $plugin_public = new ChatShop_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');

        // Shortcodes
        $shortcodes = new ChatShop_Shortcodes();
        $shortcodes->init();
    }

    /**
     * Initialize components
     */
    private function init_components()
    {
        $this->component_loader = new ChatShop_Component_Loader();
        $this->component_registry = new ChatShop_Component_Registry();

        // Register and initialize components
        $this->component_loader->init();

        // Initialize component-specific hooks
        $this->init_payment_component();
        $this->init_whatsapp_component();
        $this->init_analytics_component();
        $this->init_integration_component();
    }

    /**
     * Initialize payment component
     */
    private function init_payment_component()
    {
        $payment_manager = new ChatShop_Payment_Manager();
        $payment_manager->init();

        // Register payment gateways
        add_action('plugins_loaded', array($this, 'register_payment_gateways'), 11);
    }

    /**
     * Register payment gateways with WooCommerce
     */
    public function register_payment_gateways()
    {
        if (! class_exists('WC_Payment_Gateway')) {
            return;
        }

        add_filter('woocommerce_payment_gateways', array($this, 'add_payment_gateways'));
    }

    /**
     * Add payment gateways to WooCommerce
     */
    public function add_payment_gateways($gateways)
    {
        $gateways[] = 'ChatShop_Paystack_Gateway';
        $gateways[] = 'ChatShop_PayPal_Gateway';
        $gateways[] = 'ChatShop_Flutterwave_Gateway';
        $gateways[] = 'ChatShop_Razorpay_Gateway';

        return $gateways;
    }

    /**
     * Initialize WhatsApp component
     */
    private function init_whatsapp_component()
    {
        $whatsapp_manager = new ChatShop_WhatsApp_Manager();
        $whatsapp_manager->init();
    }

    /**
     * Initialize analytics component
     */
    private function init_analytics_component()
    {
        $analytics_manager = new ChatShop_Analytics_Manager();
        $analytics_manager->init();
    }

    /**
     * Initialize WooCommerce integration component
     */
    private function init_integration_component()
    {
        $woo_integration = new ChatShop_Woo_Integration();
        $woo_integration->init();
    }

    /**
     * Initialize API
     */
    private function init_api()
    {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        $api = new ChatShop_API();
        $api->register_routes();
    }

    /**
     * Add action links to plugin page
     */
    public function add_action_links($links)
    {
        $action_links = array(
            'settings' => '<a href="' . admin_url('admin.php?page=chatshop') . '" aria-label="' . esc_attr__('View ChatShop settings', 'chatshop') . '">' . esc_html__('Settings', 'chatshop') . '</a>',
        );

        return array_merge($action_links, $links);
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    ChatShop_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
     * Get component loader
     *
     * @return ChatShop_Component_Loader
     */
    public function get_component_loader()
    {
        return $this->component_loader;
    }

    /**
     * Get component registry
     *
     * @return ChatShop_Component_Registry
     */
    public function get_component_registry()
    {
        return $this->component_registry;
    }
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function chatshop()
{
    return ChatShop::instance();
}

// Initialize the plugin
chatshop()->run();
