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
 * @author     ChatShop Team
 */
class ChatShop_Activator
{

    /**
     * Plugin activation handler
     *
     * Creates database tables, sets default options, and performs initial setup.
     *
     * @since    1.0.0
     */
    public static function activate()
    {
        // Create database tables
        self::create_database_tables();

        // Set default options
        self::set_default_options();

        // Create default data
        self::create_default_data();

        // Schedule cron jobs
        self::schedule_cron_jobs();

        // Create upload directories
        self::create_directories();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Log activation
        self::log_activation();
    }

    /**
     * Create all database tables
     *
     * @since    1.0.0
     */
    private static function create_database_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Table prefix
        $prefix = $wpdb->prefix . 'chatshop_';

        // 1. Contacts table
        $sql_contacts = "CREATE TABLE IF NOT EXISTS {$prefix}contacts (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            phone varchar(20) NOT NULL,
            country_code varchar(5) NOT NULL DEFAULT '+1',
            email varchar(255) DEFAULT NULL,
            source varchar(50) DEFAULT 'manual',
            tags text DEFAULT NULL,
            custom_fields longtext DEFAULT NULL,
            last_interaction datetime DEFAULT NULL,
            total_spent decimal(10,2) DEFAULT 0.00,
            status enum('active','inactive','blocked','unsubscribed') DEFAULT 'active',
            opted_in tinyint(1) DEFAULT 1,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY phone_unique (phone, country_code),
            KEY status_idx (status),
            KEY created_date_idx (created_date),
            KEY last_interaction_idx (last_interaction),
            KEY email_idx (email)
        ) $charset_collate;";

        // 2. Campaigns table
        $sql_campaigns = "CREATE TABLE IF NOT EXISTS {$prefix}campaigns (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            message longtext NOT NULL,
            media_url varchar(500) DEFAULT NULL,
            media_type varchar(50) DEFAULT NULL,
            contact_filters longtext DEFAULT NULL,
            total_contacts int(11) DEFAULT 0,
            sent_count int(11) DEFAULT 0,
            delivered_count int(11) DEFAULT 0,
            read_count int(11) DEFAULT 0,
            clicked_count int(11) DEFAULT 0,
            failed_count int(11) DEFAULT 0,
            status enum('draft','scheduled','sending','paused','completed','failed') DEFAULT 'draft',
            scheduled_date datetime DEFAULT NULL,
            started_date datetime DEFAULT NULL,
            completed_date datetime DEFAULT NULL,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status_idx (status),
            KEY scheduled_date_idx (scheduled_date),
            KEY created_date_idx (created_date),
            KEY created_by_idx (created_by)
        ) $charset_collate;";

        // 3. Messages table
        $sql_messages = "CREATE TABLE IF NOT EXISTS {$prefix}messages (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) UNSIGNED NOT NULL,
            campaign_id bigint(20) UNSIGNED DEFAULT NULL,
            conversation_id varchar(100) DEFAULT NULL,
            message_type enum('text','image','video','document','audio','location','template') DEFAULT 'text',
            message longtext NOT NULL,
            media_url varchar(500) DEFAULT NULL,
            direction enum('inbound','outbound') DEFAULT 'outbound',
            whatsapp_message_id varchar(100) DEFAULT NULL,
            status enum('pending','queued','sent','delivered','read','failed','deleted') DEFAULT 'pending',
            error_message text DEFAULT NULL,
            sent_date datetime DEFAULT NULL,
            delivered_date datetime DEFAULT NULL,
            read_date datetime DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY contact_id_idx (contact_id),
            KEY campaign_id_idx (campaign_id),
            KEY conversation_id_idx (conversation_id),
            KEY status_idx (status),
            KEY direction_idx (direction),
            KEY sent_date_idx (sent_date),
            KEY whatsapp_message_id_idx (whatsapp_message_id),
            CONSTRAINT fk_message_contact FOREIGN KEY (contact_id) REFERENCES {$prefix}contacts(id) ON DELETE CASCADE,
            CONSTRAINT fk_message_campaign FOREIGN KEY (campaign_id) REFERENCES {$prefix}campaigns(id) ON DELETE SET NULL
        ) $charset_collate;";

        // 4. Analytics table
        $sql_analytics = "CREATE TABLE IF NOT EXISTS {$prefix}analytics (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id bigint(20) UNSIGNED DEFAULT NULL,
            campaign_id bigint(20) UNSIGNED DEFAULT NULL,
            contact_id bigint(20) UNSIGNED DEFAULT NULL,
            event_type enum('sent','delivered','read','clicked','replied','opted_out','failed') NOT NULL,
            event_data longtext DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY message_id_idx (message_id),
            KEY campaign_id_idx (campaign_id),
            KEY contact_id_idx (contact_id),
            KEY event_type_idx (event_type),
            KEY timestamp_idx (timestamp),
            KEY event_timestamp_idx (event_type, timestamp),
            CONSTRAINT fk_analytics_message FOREIGN KEY (message_id) REFERENCES {$prefix}messages(id) ON DELETE CASCADE,
            CONSTRAINT fk_analytics_campaign FOREIGN KEY (campaign_id) REFERENCES {$prefix}campaigns(id) ON DELETE CASCADE,
            CONSTRAINT fk_analytics_contact FOREIGN KEY (contact_id) REFERENCES {$prefix}contacts(id) ON DELETE CASCADE
        ) $charset_collate;";

        // 5. Settings table
        $sql_settings = "CREATE TABLE IF NOT EXISTS {$prefix}settings (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key varchar(255) NOT NULL,
            setting_value longtext DEFAULT NULL,
            setting_group varchar(100) DEFAULT 'general',
            autoload enum('yes','no') DEFAULT 'yes',
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key_unique (setting_key),
            KEY setting_group_idx (setting_group),
            KEY autoload_idx (autoload)
        ) $charset_collate;";

        // 6. Templates table
        $sql_templates = "CREATE TABLE IF NOT EXISTS {$prefix}templates (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            template_name varchar(255) NOT NULL,
            template_type enum('message','invoice','receipt','notification','reminder') NOT NULL,
            template_category varchar(100) DEFAULT NULL,
            subject varchar(255) DEFAULT NULL,
            content longtext NOT NULL,
            variables text DEFAULT NULL,
            media_url varchar(500) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            usage_count int(11) DEFAULT 0,
            last_used datetime DEFAULT NULL,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY template_type_idx (template_type),
            KEY template_category_idx (template_category),
            KEY is_active_idx (is_active),
            KEY created_by_idx (created_by)
        ) $charset_collate;";

        // 7. Payment Links table
        $sql_payment_links = "CREATE TABLE IF NOT EXISTS {$prefix}payment_links (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            link_code varchar(32) NOT NULL,
            product_id bigint(20) UNSIGNED DEFAULT NULL,
            order_id bigint(20) UNSIGNED DEFAULT NULL,
            contact_id bigint(20) UNSIGNED DEFAULT NULL,
            gateway varchar(50) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            description text DEFAULT NULL,
            custom_fields longtext DEFAULT NULL,
            redirect_url varchar(500) DEFAULT NULL,
            webhook_url varchar(500) DEFAULT NULL,
            link_url varchar(500) NOT NULL,
            short_url varchar(255) DEFAULT NULL,
            qr_code_url varchar(500) DEFAULT NULL,
            status enum('pending','paid','expired','cancelled','failed') DEFAULT 'pending',
            click_count int(11) DEFAULT 0,
            last_clicked datetime DEFAULT NULL,
            expires_date datetime DEFAULT NULL,
            paid_date datetime DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY link_code_unique (link_code),
            KEY product_id_idx (product_id),
            KEY order_id_idx (order_id),
            KEY contact_id_idx (contact_id),
            KEY gateway_idx (gateway),
            KEY status_idx (status),
            KEY expires_date_idx (expires_date),
            KEY created_date_idx (created_date),
            CONSTRAINT fk_payment_link_contact FOREIGN KEY (contact_id) REFERENCES {$prefix}contacts(id) ON DELETE SET NULL
        ) $charset_collate;";

        // 8. Payment Transactions table
        $sql_transactions = "CREATE TABLE IF NOT EXISTS {$prefix}payment_transactions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            link_id bigint(20) UNSIGNED DEFAULT NULL,
            order_id bigint(20) UNSIGNED DEFAULT NULL,
            contact_id bigint(20) UNSIGNED DEFAULT NULL,
            gateway varchar(50) NOT NULL,
            gateway_transaction_id varchar(255) DEFAULT NULL,
            gateway_reference varchar(255) DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            gateway_fee decimal(10,2) DEFAULT 0.00,
            net_amount decimal(10,2) DEFAULT NULL,
            payment_method varchar(50) DEFAULT NULL,
            payment_details longtext DEFAULT NULL,
            status enum('pending','processing','completed','failed','refunded','partially_refunded','cancelled') DEFAULT 'pending',
            failure_reason text DEFAULT NULL,
            customer_name varchar(255) DEFAULT NULL,
            customer_email varchar(255) DEFAULT NULL,
            customer_phone varchar(20) DEFAULT NULL,
            customer_data longtext DEFAULT NULL,
            billing_address longtext DEFAULT NULL,
            shipping_address longtext DEFAULT NULL,
            refund_amount decimal(10,2) DEFAULT 0.00,
            refund_reason text DEFAULT NULL,
            refunded_date datetime DEFAULT NULL,
            gateway_response longtext DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            completed_date datetime DEFAULT NULL,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY gateway_transaction_unique (gateway, gateway_transaction_id),
            KEY link_id_idx (link_id),
            KEY order_id_idx (order_id),
            KEY contact_id_idx (contact_id),
            KEY gateway_idx (gateway),
            KEY status_idx (status),
            KEY customer_email_idx (customer_email),
            KEY created_date_idx (created_date),
            KEY completed_date_idx (completed_date),
            CONSTRAINT fk_transaction_link FOREIGN KEY (link_id) REFERENCES {$prefix}payment_links(id) ON DELETE SET NULL,
            CONSTRAINT fk_transaction_contact FOREIGN KEY (contact_id) REFERENCES {$prefix}contacts(id) ON DELETE SET NULL
        ) $charset_collate;";

        // 9. Payment Gateways table
        $sql_gateways = "CREATE TABLE IF NOT EXISTS {$prefix}payment_gateways (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            gateway_name varchar(100) NOT NULL,
            gateway_slug varchar(50) NOT NULL,
            gateway_class varchar(100) NOT NULL,
            description text DEFAULT NULL,
            icon_url varchar(500) DEFAULT NULL,
            supported_currencies text DEFAULT NULL,
            supported_countries text DEFAULT NULL,
            transaction_fees longtext DEFAULT NULL,
            settings longtext DEFAULT NULL,
            capabilities longtext DEFAULT NULL,
            is_active tinyint(1) DEFAULT 0,
            is_test_mode tinyint(1) DEFAULT 0,
            display_order int(11) DEFAULT 0,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY gateway_slug_unique (gateway_slug),
            KEY is_active_idx (is_active),
            KEY display_order_idx (display_order)
        ) $charset_collate;";

        // 10. Payment Analytics table
        $sql_payment_analytics = "CREATE TABLE IF NOT EXISTS {$prefix}payment_analytics (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            transaction_id bigint(20) UNSIGNED DEFAULT NULL,
            link_id bigint(20) UNSIGNED DEFAULT NULL,
            campaign_id bigint(20) UNSIGNED DEFAULT NULL,
            gateway varchar(50) DEFAULT NULL,
            event_type enum('link_created','link_clicked','payment_initiated','payment_completed','payment_failed','refund_initiated','refund_completed') NOT NULL,
            source varchar(100) DEFAULT NULL,
            source_medium varchar(100) DEFAULT NULL,
            source_campaign varchar(255) DEFAULT NULL,
            conversion_value decimal(10,2) DEFAULT NULL,
            conversion_data longtext DEFAULT NULL,
            revenue decimal(10,2) DEFAULT NULL,
            currency varchar(3) DEFAULT NULL,
            device_type varchar(50) DEFAULT NULL,
            browser varchar(100) DEFAULT NULL,
            operating_system varchar(100) DEFAULT NULL,
            country varchar(2) DEFAULT NULL,
            region varchar(100) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            referrer_url varchar(500) DEFAULT NULL,
            landing_page varchar(500) DEFAULT NULL,
            session_id varchar(100) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY transaction_id_idx (transaction_id),
            KEY link_id_idx (link_id),
            KEY campaign_id_idx (campaign_id),
            KEY gateway_idx (gateway),
            KEY event_type_idx (event_type),
            KEY source_idx (source),
            KEY timestamp_idx (timestamp),
            KEY event_timestamp_idx (event_type, timestamp),
            KEY session_id_idx (session_id),
            CONSTRAINT fk_payment_analytics_transaction FOREIGN KEY (transaction_id) REFERENCES {$prefix}payment_transactions(id) ON DELETE CASCADE,
            CONSTRAINT fk_payment_analytics_link FOREIGN KEY (link_id) REFERENCES {$prefix}payment_links(id) ON DELETE CASCADE,
            CONSTRAINT fk_payment_analytics_campaign FOREIGN KEY (campaign_id) REFERENCES {$prefix}campaigns(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Additional tables for comprehensive functionality

        // 11. API Logs table
        $sql_api_logs = "CREATE TABLE IF NOT EXISTS {$prefix}api_logs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            api_provider varchar(50) NOT NULL,
            endpoint varchar(255) NOT NULL,
            method varchar(10) NOT NULL,
            request_data longtext DEFAULT NULL,
            response_data longtext DEFAULT NULL,
            status_code int(11) DEFAULT NULL,
            error_message text DEFAULT NULL,
            execution_time float DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY api_provider_idx (api_provider),
            KEY endpoint_idx (endpoint),
            KEY status_code_idx (status_code),
            KEY timestamp_idx (timestamp),
            KEY user_id_idx (user_id)
        ) $charset_collate;";

        // 12. Webhooks table
        $sql_webhooks = "CREATE TABLE IF NOT EXISTS {$prefix}webhooks (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            webhook_id varchar(100) NOT NULL,
            provider varchar(50) NOT NULL,
            event_type varchar(100) NOT NULL,
            payload longtext NOT NULL,
            signature varchar(255) DEFAULT NULL,
            processed tinyint(1) DEFAULT 0,
            retry_count int(11) DEFAULT 0,
            error_message text DEFAULT NULL,
            received_date datetime DEFAULT CURRENT_TIMESTAMP,
            processed_date datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY webhook_id_unique (webhook_id, provider),
            KEY provider_idx (provider),
            KEY event_type_idx (event_type),
            KEY processed_idx (processed),
            KEY received_date_idx (received_date)
        ) $charset_collate;";

        // 13. Queue Jobs table
        $sql_queue_jobs = "CREATE TABLE IF NOT EXISTS {$prefix}queue_jobs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            job_type varchar(100) NOT NULL,
            job_data longtext NOT NULL,
            priority int(11) DEFAULT 10,
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            status enum('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
            error_message text DEFAULT NULL,
            scheduled_at datetime DEFAULT NULL,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_type_idx (job_type),
            KEY status_idx (status),
            KEY priority_idx (priority),
            KEY scheduled_at_idx (scheduled_at),
            KEY status_scheduled_idx (status, scheduled_at)
        ) $charset_collate;";

        // 14. Component Settings table
        $sql_component_settings = "CREATE TABLE IF NOT EXISTS {$prefix}component_settings (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            component_name varchar(100) NOT NULL,
            component_slug varchar(100) NOT NULL,
            settings longtext DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            version varchar(20) DEFAULT NULL,
            last_activated datetime DEFAULT NULL,
            last_deactivated datetime DEFAULT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY component_slug_unique (component_slug),
            KEY is_active_idx (is_active)
        ) $charset_collate;";

        // Execute all SQL queries
        dbDelta($sql_contacts);
        dbDelta($sql_campaigns);
        dbDelta($sql_messages);
        dbDelta($sql_analytics);
        dbDelta($sql_settings);
        dbDelta($sql_templates);
        dbDelta($sql_payment_links);
        dbDelta($sql_transactions);
        dbDelta($sql_gateways);
        dbDelta($sql_payment_analytics);
        dbDelta($sql_api_logs);
        dbDelta($sql_webhooks);
        dbDelta($sql_queue_jobs);
        dbDelta($sql_component_settings);

        // Store database version
        update_option('chatshop_db_version', CHATSHOP_DB_VERSION);
    }

    /**
     * Set default plugin options
     *
     * @since    1.0.0
     */
    private static function set_default_options()
    {
        // General settings
        add_option('chatshop_version', CHATSHOP_VERSION);
        add_option('chatshop_db_version', CHATSHOP_DB_VERSION);
        add_option('chatshop_activation_date', current_time('mysql'));

        // WhatsApp settings
        add_option('chatshop_whatsapp_enabled', 'yes');
        add_option('chatshop_whatsapp_api_url', '');
        add_option('chatshop_whatsapp_api_key', '');
        add_option('chatshop_whatsapp_phone_number', '');
        add_option('chatshop_whatsapp_business_id', '');
        add_option('chatshop_whatsapp_rate_limit', 80); // Messages per minute

        // Payment settings
        add_option('chatshop_payment_enabled', 'yes');
        add_option('chatshop_default_currency', get_woocommerce_currency());
        add_option('chatshop_payment_link_expiry', 24); // Hours
        add_option('chatshop_payment_test_mode', 'yes');

        // Analytics settings
        add_option('chatshop_analytics_enabled', 'yes');
        add_option('chatshop_analytics_retention_days', 90);
        add_option('chatshop_analytics_anonymize_ip', 'yes');

        // Email notifications
        add_option('chatshop_email_notifications', 'yes');
        add_option('chatshop_admin_email', get_option('admin_email'));

        // API settings
        add_option('chatshop_api_enabled', 'yes');
        add_option('chatshop_api_rate_limit', 100); // Requests per minute

        // Security settings
        add_option('chatshop_enable_logging', 'yes');
        add_option('chatshop_log_retention_days', 30);
        add_option('chatshop_encryption_key', wp_generate_password(32, true, true));

        // Component settings
        add_option('chatshop_active_components', array(
            'payment' => true,
            'whatsapp' => true,
            'analytics' => true,
            'integration' => true
        ));
    }

    /**
     * Create default data
     *
     * @since    1.0.0
     */
    private static function create_default_data()
    {
        global $wpdb;
        $prefix = $wpdb->prefix . 'chatshop_';

        // Insert default payment gateways
        $gateways = array(
            array(
                'gateway_name' => 'Paystack',
                'gateway_slug' => 'paystack',
                'gateway_class' => 'ChatShop_Paystack_Gateway',
                'description' => 'Accept payments via Paystack - Cards, Bank Transfer, Mobile Money',
                'icon_url' => CHATSHOP_PLUGIN_URL . 'assets/icons/paystack.svg',
                'supported_currencies' => json_encode(array('NGN', 'GHS', 'ZAR', 'USD')),
                'supported_countries' => json_encode(array('NG', 'GH', 'ZA', 'KE')),
                'is_active' => 0,
                'display_order' => 1
            ),
            array(
                'gateway_name' => 'PayPal',
                'gateway_slug' => 'paypal',
                'gateway_class' => 'ChatShop_PayPal_Gateway',
                'description' => 'Accept payments via PayPal - Credit Cards, PayPal Balance',
                'icon_url' => CHATSHOP_PLUGIN_URL . 'assets/icons/paypal.svg',
                'supported_currencies' => json_encode(array('USD', 'EUR', 'GBP', 'CAD', 'AUD')),
                'supported_countries' => json_encode(array('US', 'GB', 'CA', 'AU', 'EU')),
                'is_active' => 0,
                'display_order' => 2
            ),
            array(
                'gateway_name' => 'Flutterwave',
                'gateway_slug' => 'flutterwave',
                'gateway_class' => 'ChatShop_Flutterwave_Gateway',
                'description' => 'Accept payments via Flutterwave - Cards, Bank Transfer, Mobile Money',
                'icon_url' => CHATSHOP_PLUGIN_URL . 'assets/icons/flutterwave.svg',
                'supported_currencies' => json_encode(array('NGN', 'USD', 'EUR', 'GBP', 'KES', 'GHS', 'ZAR')),
                'supported_countries' => json_encode(array('NG', 'KE', 'GH', 'ZA', 'UG', 'TZ')),
                'is_active' => 0,
                'display_order' => 3
            ),
            array(
                'gateway_name' => 'Razorpay',
                'gateway_slug' => 'razorpay',
                'gateway_class' => 'ChatShop_Razorpay_Gateway',
                'description' => 'Accept payments via Razorpay - Cards, UPI, Wallets, Bank Transfer',
                'icon_url' => CHATSHOP_PLUGIN_URL . 'assets/icons/razorpay.svg',
                'supported_currencies' => json_encode(array('INR', 'USD', 'EUR', 'GBP', 'AED')),
                'supported_countries' => json_encode(array('IN', 'MY', 'SG')),
                'is_active' => 0,
                'display_order' => 4
            )
        );

        foreach ($gateways as $gateway) {
            $wpdb->insert($prefix . 'payment_gateways', $gateway);
        }

        // Insert default message templates
        $templates = array(
            array(
                'template_name' => 'Order Confirmation',
                'template_type' => 'notification',
                'template_category' => 'order',
                'subject' => 'Order Confirmation - #{order_number}',
                'content' => "Hello {customer_name},\n\nThank you for your order! Your order #{order_number} has been confirmed.\n\nOrder Total: {order_total}\nPayment Method: {payment_method}\n\nYou can track your order status here: {order_link}\n\nThank you for shopping with us!",
                'variables' => json_encode(array('customer_name', 'order_number', 'order_total', 'payment_method', 'order_link')),
                'is_active' => 1
            ),
            array(
                'template_name' => 'Payment Reminder',
                'template_type' => 'reminder',
                'template_category' => 'payment',
                'subject' => 'Payment Reminder - Complete Your Order',
                'content' => "Hi {customer_name},\n\nWe noticed you haven't completed your payment for order #{order_number}.\n\nAmount Due: {amount}\n\nClick here to complete your payment: {payment_link}\n\nThis link will expire in {expiry_hours} hours.\n\nNeed help? Reply to this message.",
                'variables' => json_encode(array('customer_name', 'order_number', 'amount', 'payment_link', 'expiry_hours')),
                'is_active' => 1
            ),
            array(
                'template_name' => 'Payment Receipt',
                'template_type' => 'receipt',
                'template_category' => 'payment',
                'subject' => 'Payment Receipt - #{transaction_id}',
                'content' => "Payment Receipt\n\nTransaction ID: {transaction_id}\nDate: {payment_date}\nAmount Paid: {amount}\nPayment Method: {payment_method}\n\nThank you for your payment!\n\nThis is an automated receipt for your records.",
                'variables' => json_encode(array('transaction_id', 'payment_date', 'amount', 'payment_method')),
                'is_active' => 1
            ),
            array(
                'template_name' => 'Welcome Message',
                'template_type' => 'message',
                'template_category' => 'customer',
                'subject' => 'Welcome to {shop_name}!',
                'content' => "Hi {customer_name}! 👋\n\nWelcome to {shop_name}! We're excited to have you as our customer.\n\nHere's what you can do:\n✅ Browse our products\n✅ Get exclusive offers\n✅ Track your orders\n✅ 24/7 customer support\n\nReply with 'MENU' to see our options or 'HELP' for assistance.",
                'variables' => json_encode(array('customer_name', 'shop_name')),
                'is_active' => 1
            )
        );

        foreach ($templates as $template) {
            $template['created_by'] = get_current_user_id();
            $wpdb->insert($prefix . 'templates', $template);
        }

        // Insert default settings
        $settings = array(
            array('setting_key' => 'whatsapp_welcome_message', 'setting_value' => 'Welcome to our store! How can we help you today?', 'setting_group' => 'whatsapp'),
            array('setting_key' => 'whatsapp_offline_message', 'setting_value' => 'We are currently offline. We will respond to your message as soon as possible.', 'setting_group' => 'whatsapp'),
            array('setting_key' => 'payment_success_message', 'setting_value' => 'Payment successful! Thank you for your purchase.', 'setting_group' => 'payment'),
            array('setting_key' => 'payment_failed_message', 'setting_value' => 'Payment failed. Please try again or contact support.', 'setting_group' => 'payment'),
            array('setting_key' => 'analytics_tracking_enabled', 'setting_value' => 'yes', 'setting_group' => 'analytics'),
            array('setting_key' => 'api_rate_limit_per_minute', 'setting_value' => '60', 'setting_group' => 'api'),
            array('setting_key' => 'queue_batch_size', 'setting_value' => '50', 'setting_group' => 'queue'),
            array('setting_key' => 'queue_retry_delay', 'setting_value' => '300', 'setting_group' => 'queue'),
            array('setting_key' => 'webhook_timeout', 'setting_value' => '30', 'setting_group' => 'webhook'),
            array('setting_key' => 'webhook_retry_attempts', 'setting_value' => '3', 'setting_group' => 'webhook')
        );

        foreach ($settings as $setting) {
            $wpdb->insert($prefix . 'settings', $setting);
        }

        // Insert default component settings
        $components = array(
            array(
                'component_name' => 'Payment System',
                'component_slug' => 'payment',
                'settings' => json_encode(array(
                    'enabled' => true,
                    'test_mode' => true,
                    'link_expiry_hours' => 24,
                    'auto_capture' => true,
                    'send_receipts' => true
                )),
                'is_active' => 1,
                'version' => '1.0.0'
            ),
            array(
                'component_name' => 'WhatsApp Integration',
                'component_slug' => 'whatsapp',
                'settings' => json_encode(array(
                    'enabled' => true,
                    'auto_reply' => true,
                    'business_hours' => array(
                        'monday' => array('start' => '09:00', 'end' => '18:00'),
                        'tuesday' => array('start' => '09:00', 'end' => '18:00'),
                        'wednesday' => array('start' => '09:00', 'end' => '18:00'),
                        'thursday' => array('start' => '09:00', 'end' => '18:00'),
                        'friday' => array('start' => '09:00', 'end' => '18:00'),
                        'saturday' => array('start' => '10:00', 'end' => '14:00'),
                        'sunday' => array('closed' => true)
                    )
                )),
                'is_active' => 1,
                'version' => '1.0.0'
            ),
            array(
                'component_name' => 'Analytics System',
                'component_slug' => 'analytics',
                'settings' => json_encode(array(
                    'enabled' => true,
                    'tracking_enabled' => true,
                    'anonymize_ip' => true,
                    'retention_days' => 90
                )),
                'is_active' => 1,
                'version' => '1.0.0'
            ),
            array(
                'component_name' => 'WooCommerce Integration',
                'component_slug' => 'integration',
                'settings' => json_encode(array(
                    'enabled' => true,
                    'sync_products' => true,
                    'sync_customers' => true,
                    'sync_orders' => true,
                    'auto_update_stock' => true
                )),
                'is_active' => 1,
                'version' => '1.0.0'
            )
        );

        foreach ($components as $component) {
            $wpdb->insert($prefix . 'component_settings', $component);
        }
    }

    /**
     * Schedule cron jobs
     *
     * @since    1.0.0
     */
    private static function schedule_cron_jobs()
    {
        // Schedule hourly cleanup
        if (! wp_next_scheduled('chatshop_hourly_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'chatshop_hourly_cleanup');
        }

        // Schedule daily analytics aggregation
        if (! wp_next_scheduled('chatshop_daily_analytics')) {
            wp_schedule_event(time(), 'daily', 'chatshop_daily_analytics');
        }

        // Schedule queue processor (every 5 minutes)
        if (! wp_next_scheduled('chatshop_process_queue')) {
            wp_schedule_event(time(), 'chatshop_five_minutes', 'chatshop_process_queue');
        }

        // Schedule webhook retry (every 10 minutes)
        if (! wp_next_scheduled('chatshop_retry_webhooks')) {
            wp_schedule_event(time(), 'chatshop_ten_minutes', 'chatshop_retry_webhooks');
        }

        // Schedule payment link expiry check (every 30 minutes)
        if (! wp_next_scheduled('chatshop_check_payment_expiry')) {
            wp_schedule_event(time(), 'chatshop_thirty_minutes', 'chatshop_check_payment_expiry');
        }
    }

    /**
     * Create necessary directories
     *
     * @since    1.0.0
     */
    private static function create_directories()
    {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/chatshop';

        $directories = array(
            $base_dir,
            $base_dir . '/logs',
            $base_dir . '/temp',
            $base_dir . '/exports',
            $base_dir . '/imports',
            $base_dir . '/qr-codes',
            $base_dir . '/media',
            $base_dir . '/backups'
        );

        foreach ($directories as $directory) {
            if (! file_exists($directory)) {
                wp_mkdir_p($directory);

                // Add .htaccess for security
                $htaccess_content = "Options -Indexes\nDeny from all";
                file_put_contents($directory . '/.htaccess', $htaccess_content);

                // Add index.php for extra security
                file_put_contents($directory . '/index.php', '<?php // Silence is golden');
            }
        }
    }

    /**
     * Log plugin activation
     *
     * @since    1.0.0
     */
    private static function log_activation()
    {
        $logger = new ChatShop_Logger();
        $logger->log('info', 'ChatShop plugin activated', array(
            'version' => CHATSHOP_VERSION,
            'php_version' => phpversion(),
            'wp_version' => get_bloginfo('version'),
            'wc_version' => defined('WC_VERSION') ? WC_VERSION : 'Not installed',
            'site_url' => get_site_url(),
            'user_id' => get_current_user_id()
        ));

        // Send activation telemetry (if enabled)
        if (get_option('chatshop_telemetry_enabled', 'no') === 'yes') {
            self::send_activation_telemetry();
        }
    }

    /**
     * Send activation telemetry
     *
     * @since    1.0.0
     */
    private static function send_activation_telemetry()
    {
        $telemetry_data = array(
            'event' => 'plugin_activated',
            'version' => CHATSHOP_VERSION,
            'environment' => array(
                'php_version' => phpversion(),
                'wp_version' => get_bloginfo('version'),
                'wc_version' => defined('WC_VERSION') ? WC_VERSION : null,
                'locale' => get_locale(),
                'multisite' => is_multisite(),
                'theme' => get_option('stylesheet')
            ),
            'timestamp' => current_time('timestamp')
        );

        // Queue telemetry for sending
        wp_schedule_single_event(time() + 10, 'chatshop_send_telemetry', array($telemetry_data));
    }

    /**
     * Run database upgrade if needed
     *
     * @since    1.0.0
     */
    public static function maybe_upgrade()
    {
        $current_db_version = get_option('chatshop_db_version', '0');

        if (version_compare($current_db_version, CHATSHOP_DB_VERSION, '<')) {
            self::upgrade_database($current_db_version);
        }
    }

    /**
     * Upgrade database schema
     *
     * @since    1.0.0
     * @param string $from_version Current database version
     */
    private static function upgrade_database($from_version)
    {
        global $wpdb;
        $prefix = $wpdb->prefix . 'chatshop_';

        // Future database upgrades will be handled here
        // Example:
        // if ( version_compare( $from_version, '1.1.0', '<' ) ) {
        //     // Upgrade to version 1.1.0
        // }

        // Update database version
        update_option('chatshop_db_version', CHATSHOP_DB_VERSION);
    }

    /**
     * Check system requirements
     *
     * @since    1.0.0
     * @return bool True if all requirements are met
     */
    public static function check_requirements()
    {
        $errors = array();

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $errors[] = sprintf(
                __('ChatShop requires PHP version 7.4 or higher. Your server is running PHP %s.', 'chatshop'),
                PHP_VERSION
            );
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            $errors[] = sprintf(
                __('ChatShop requires WordPress version 5.0 or higher. You are running WordPress %s.', 'chatshop'),
                get_bloginfo('version')
            );
        }

        // Check if WooCommerce is active
        if (! class_exists('WooCommerce')) {
            $errors[] = __('ChatShop requires WooCommerce to be installed and activated.', 'chatshop');
        } elseif (defined('WC_VERSION') && version_compare(WC_VERSION, '4.0', '<')) {
            $errors[] = sprintf(
                __('ChatShop requires WooCommerce version 4.0 or higher. You are running WooCommerce %s.', 'chatshop'),
                WC_VERSION
            );
        }

        // Check MySQL version
        $mysql_version = $wpdb->db_version();
        if (version_compare($mysql_version, '5.7', '<')) {
            $errors[] = sprintf(
                __('ChatShop requires MySQL version 5.7 or higher. Your server is running MySQL %s.', 'chatshop'),
                $mysql_version
            );
        }

        // Check for required PHP extensions
        $required_extensions = array('curl', 'json', 'mbstring', 'openssl');
        foreach ($required_extensions as $extension) {
            if (! extension_loaded($extension)) {
                $errors[] = sprintf(
                    __('ChatShop requires the PHP %s extension to be installed.', 'chatshop'),
                    $extension
                );
            }
        }

        // Check write permissions
        $upload_dir = wp_upload_dir();
        if (! wp_is_writable($upload_dir['basedir'])) {
            $errors[] = __('ChatShop requires write permissions to the uploads directory.', 'chatshop');
        }

        // Display errors if any
        if (! empty($errors)) {
            deactivate_plugins(plugin_basename(CHATSHOP_PLUGIN_FILE));
            wp_die(
                '<h1>' . __('Plugin Activation Failed', 'chatshop') . '</h1>' .
                    '<p>' . implode('</p><p>', $errors) . '</p>' .
                    '<p><a href="' . admin_url('plugins.php') . '">' . __('Return to Plugins', 'chatshop') . '</a></p>',
                __('Plugin Activation Failed', 'chatshop'),
                array('response' => 200, 'back_link' => true)
            );
            return false;
        }

        return true;
    }
}
