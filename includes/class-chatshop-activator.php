<?php
/**
 * Fired during plugin activation
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    ChatShop
 * @subpackage ChatShop/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    ChatShop
 * @subpackage ChatShop/includes
 * @author     Your Name <email@example.com>
 */
class ChatShop_Activator {

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function activate() {
        // Check if WooCommerce is active
        if (!self::is_woocommerce_active()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('ChatShop requires WooCommerce to be installed and active.', 'chatshop'),
                __('Plugin Activation Error', 'chatshop'),
                array('back_link' => true)
            );
        }

        // Create database tables
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Create default pages
        self::create_pages();
        
        // Clear the permalinks
        flush_rewrite_rules();
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private static function is_woocommerce_active() {
        // Multi-site compatible check
        if (is_multisite()) {
            // Check if WooCommerce is network activated
            if (array_key_exists('woocommerce/woocommerce.php', get_site_option('active_sitewide_plugins', array()))) {
                return true;
            }
        }
        
        // Check if WooCommerce is active on single site or current site in multisite
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins', array())));
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        // Get default currency - with fallback if WooCommerce function is not available
        $default_currency = 'USD'; // Default fallback
        
        // Check if WooCommerce is loaded and function exists
        if (function_exists('get_woocommerce_currency')) {
            $default_currency = get_woocommerce_currency();
        } else {
            // Try to get from WooCommerce options directly
            $wc_currency = get_option('woocommerce_currency');
            if ($wc_currency) {
                $default_currency = $wc_currency;
            }
        }

        // ChatShop general settings
        $general_defaults = array(
            'enabled' => 'yes',
            'debug_mode' => 'no',
            'default_currency' => $default_currency,
            'cache_expiry' => 3600,
        );

        // WhatsApp settings defaults
        $whatsapp_defaults = array(
            'enabled' => 'yes',
            'phone_number' => '',
            'api_key' => '',
            'webhook_url' => home_url('/wp-json/chatshop/v1/whatsapp/webhook'),
            'message_template' => __('Hello! Thank you for your interest in our products.', 'chatshop'),
        );

        // Payment gateway defaults
        $payment_defaults = array(
            'paystack_enabled' => 'no',
            'paystack_test_mode' => 'yes',
            'paystack_test_public_key' => '',
            'paystack_test_secret_key' => '',
            'paystack_live_public_key' => '',
            'paystack_live_secret_key' => '',
            'payment_link_expiry' => 24, // hours
        );

        // Analytics defaults
        $analytics_defaults = array(
            'track_conversions' => 'yes',
            'track_messages' => 'yes',
            'data_retention_days' => 90,
        );

        // Add options with defaults if they don't exist
        add_option('chatshop_general_settings', $general_defaults);
        add_option('chatshop_whatsapp_settings', $whatsapp_defaults);
        add_option('chatshop_payment_settings', $payment_defaults);
        add_option('chatshop_analytics_settings', $analytics_defaults);
        
        // Set plugin version
        add_option('chatshop_version', CHATSHOP_VERSION);
        
        // Set installation date
        add_option('chatshop_installed', time());
    }

    /**
     * Create required database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Payment links table
        $table_name = $wpdb->prefix . 'chatshop_payment_links';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            link_code varchar(50) NOT NULL,
            order_id bigint(20) DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL,
            customer_phone varchar(20) NOT NULL,
            customer_email varchar(100) DEFAULT NULL,
            gateway varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            metadata longtext DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY link_code (link_code),
            KEY order_id (order_id),
            KEY customer_phone (customer_phone),
            KEY status (status),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        dbDelta($sql);

        // WhatsApp messages table
        $table_name = $wpdb->prefix . 'chatshop_messages';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            message_id varchar(100) NOT NULL,
            conversation_id varchar(100) DEFAULT NULL,
            phone_number varchar(20) NOT NULL,
            direction varchar(10) NOT NULL,
            message_type varchar(20) NOT NULL,
            content longtext DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY message_id (message_id),
            KEY conversation_id (conversation_id),
            KEY phone_number (phone_number),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);

        // Analytics table
        $table_name = $wpdb->prefix . 'chatshop_analytics';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data longtext DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            customer_phone varchar(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY customer_phone (customer_phone),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);

        // Transactions table
        $table_name = $wpdb->prefix . 'chatshop_transactions';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            transaction_id varchar(100) NOT NULL,
            payment_link_id bigint(20) DEFAULT NULL,
            order_id bigint(20) DEFAULT NULL,
            gateway varchar(50) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            gateway_response longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY transaction_id (transaction_id),
            KEY payment_link_id (payment_link_id),
            KEY order_id (order_id),
            KEY gateway (gateway),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }

    /**
     * Create default pages
     */
    private static function create_pages() {
        $pages = array(
            'payment' => array(
                'title' => __('Payment', 'chatshop'),
                'content' => '[chatshop_payment]',
                'option' => 'chatshop_payment_page_id'
            ),
            'payment-success' => array(
                'title' => __('Payment Successful', 'chatshop'),
                'content' => '[chatshop_payment_success]',
                'option' => 'chatshop_payment_success_page_id'
            ),
            'payment-failed' => array(
                'title' => __('Payment Failed', 'chatshop'),
                'content' => '[chatshop_payment_failed]',
                'option' => 'chatshop_payment_failed_page_id'
            )
        );

        foreach ($pages as $key => $page) {
            $page_id = wp_insert_post(array(
                'post_title' => $page['title'],
                'post_content' => $page['content'],
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => $key,
            ));

            if ($page_id && !is_wp_error($page_id)) {
                update_option($page['option'], $page_id);
            }
        }
    }
}