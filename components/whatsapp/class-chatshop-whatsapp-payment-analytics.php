<?php
/**
 * WhatsApp Payment Analytics Integration
 * 
 * Tracks WhatsApp payment performance, conversion rates, and revenue attribution.
 * 
 * File: components/whatsapp/class-chatshop-whatsapp-payment-analytics.php
 *
 * @package ChatShop
 * @subpackage WhatsApp
 * @since 1.0.0
 */

namespace ChatShop\WhatsApp;

use ChatShop\Analytics\ChatShop_Analytics_Manager;
use ChatShop\Database\ChatShop_Payment_WhatsApp_Table;
use ChatShop\Helper\ChatShop_Helper;
use ChatShop\Logger\ChatShop_Logger;

defined( 'ABSPATH' ) || exit;

/**
 * WhatsApp Payment Analytics Integration class
 */
class ChatShop_WhatsApp_Payment_Analytics {

    /**
     * Analytics manager instance
     *
     * @var ChatShop_Analytics_Manager
     */
    private $analytics_manager;

    /**
     * Database table instance
     *
     * @var ChatShop_Payment_WhatsApp_Table
     */
    private $db_table;

    /**
     * Logger instance
     *
     * @var ChatShop_Logger
     */
    private $logger;

    /**
     * Analytics settings
     *
     * @var array
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->analytics_manager = new ChatShop_Analytics_Manager();
        $this->db_table         = new ChatShop_Payment_WhatsApp_Table();
        $this->logger           = new ChatShop_Logger();
        
        $this->load_settings();
        $this->init_hooks();
    }

    /**
     * Load analytics settings
     */
    private function load_settings() {
        $this->settings = array(
            'enabled'              => get_option( 'chatshop_whatsapp_analytics_enabled', 'yes' ),
            'track_conversions'    => get_option( 'chatshop_whatsapp_track_conversions', 'yes' ),
            'track_engagement'     => get_option( 'chatshop_whatsapp_track_engagement', 'yes' ),
            'track_revenue'        => get_option( 'chatshop_whatsapp_track_revenue', 'yes' ),
            'track_customer_journey' => get_option( 'chatshop_whatsapp_track_customer_journey', 'yes' ),
            'daily_reports'        => get_option( 'chatshop_whatsapp_daily_reports', 'no' ),
            'weekly_reports'       => get_option( 'chatshop_whatsapp_weekly_reports', 'yes' ),
        );
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        if ( $this->settings['enabled'] !== 'yes' ) {
            return;
        }

        // Payment tracking
        add_action( 'chatshop_payment_link_generated', array( $this, 'track_payment_link_generated' ), 10, 3 );
        add_action( 'chatshop_payment_link_clicked', array( $this, 'track_payment_link_clicked' ), 10, 2 );
        add_action( 'woocommerce_payment_complete', array( $this, 'track_payment_completed' ), 10, 1 );
        add_action( 'woocommerce_order_status_failed', array( $this, 'track_payment_failed' ), 10, 2 );

        // WhatsApp message tracking
        add_action( 'chatshop_whatsapp_message_sent', array( $this, 'track_message_sent' ), 10, 3 );
        add_action( 'chatshop_whatsapp_message_received', array( $this, 'track_message_received' ), 10, 2 );

        // Commerce tracking
        add_action( 'chatshop_whatsapp_product_viewed', array( $this, 'track_product_viewed' ), 10, 3 );
        add_action( 'chatshop_whatsapp_cart_updated', array( $this, 'track_cart_updated' ), 10, 3 );
        add_action( 'chatshop_whatsapp_checkout_started', array( $this, 'track_checkout_started' ), 10, 2 );

        // Reports
        add_action( 'chatshop_whatsapp_daily_analytics', array( $this, 'generate_daily_report' ) );
        add_action( 'chatshop_whatsapp_weekly_analytics', array( $this, 'generate_weekly_report' ) );

        $this->schedule_reports();
    }

    /**
     * Track payment link generation
     *
     * @param string $link_id Payment link ID
     * @param int    $order_id Order ID
     * @param array  $link_data Link data
     */
    public function track_payment_link_generated( $link_id, $order_id, $link_data ) {
        if ( empty( $link_data['source'] ) || $link_data['source'] !== 'whatsapp' ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $analytics_data = array(
            'event_type'      => 'payment_link_generated',
            'link_id'         => $link_id,
            'order_id'        => $order_id,
            'customer_phone'  => $link_data['phone'] ?? '',
            'order_total'     => $order->get_total(),
            'currency'        => $order->get_currency(),
            'payment_method'  => $order->get_payment_method(),
            'source'          => 'whatsapp',
            'utm_source'      => $link_data['utm_source'] ?? 'whatsapp',
            'utm_medium'      => $link_data['utm_medium'] ?? 'social',
            'utm_campaign'    => $link_data['utm_campaign'] ?? 'payment_link',
            'session_id'      => $this->get_session_id( $link_data['phone'] ?? '' ),
            'timestamp'       => current_time( 'mysql' ),
        );

        $this->insert_analytics_event( $analytics_data );
        $this->logger->info( 'WhatsApp payment link generation tracked', array(
            'link_id' => $link_id,
            'order_id' => $order_id,
        ) );
    }

    /**
     * Track payment link clicks
     *
     * @param string $link_id Payment link ID
     * @param array  $click_data Click data
     */
    public function track_payment_link_clicked( $link_id, $click_data ) {
        $link_data = $this->db_table->get_payment_link_data( $link_id );
        
        if ( ! $link_data || $link_data['source'] !== 'whatsapp' ) {
            return;
        }

        $analytics_data = array(
            'event_type'      => 'payment_link_clicked',
            'link_id'         => $link_id,
            'order_id'        => $link_data['order_id'],
            'customer_phone'  => $link_data['customer_phone'],
            'ip_address'      => $click_data['ip_address'] ?? '',
            'user_agent'      => $click_data['user_agent'] ?? '',
            'referrer'        => $click_data['referrer'] ?? '',
            'utm_source'      => $click_data['utm_source'] ?? 'whatsapp',
            'utm_medium'      => $click_data['utm_medium'] ?? 'social',
            'utm_campaign'    => $click_data['utm_campaign'] ?? 'payment_link',
            'session_id'      => $this->get_session_id( $link_data['customer_phone'] ),
            'timestamp'       => current_time( 'mysql' ),
        );

        $this->insert_analytics_event( $analytics_data );
        $this->update_conversion_funnel( $link_data['customer_phone'], 'link_clicked' );
    }

    /**
     * Track payment completion
     *
     * @param int $order_id Order ID
     */
    public function track_payment_completed( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $whatsapp_data = $this->get_order_whatsapp_data( $order_id );
        if ( ! $whatsapp_data ) {
            return;
        }

        $analytics_data = array(
            'event_type'      => 'payment_completed',
            'order_id'        => $order_id,
            'customer_phone'  => $whatsapp_data['customer_phone'],
            'order_total'     => $order->get_total(),
            'currency'        => $order->get_currency(),
            'payment_method'  => $order->get_payment_method(),
            'gateway'         => $order->get_payment_method(),
            'source'          => 'whatsapp',
            'utm_source'      => $whatsapp_data['utm_source'] ?? 'whatsapp',
            'utm_medium'      => $whatsapp_data['utm_medium'] ?? 'social',
            'utm_campaign'    => $whatsapp_data['utm_campaign'] ?? 'payment_link',
            'session_id'      => $this->get_session_id( $whatsapp_data['customer_phone'] ),
            'timestamp'       => current_time( 'mysql' ),
        );

        $this->insert_analytics_event( $analytics_data );
        $this->update_conversion_funnel( $whatsapp_data['customer_phone'], 'payment_completed' );

        if ( $this->settings['track_revenue'] === 'yes' ) {
            $this->track_revenue_attribution( $order, $whatsapp_data );
        }

        $this->logger->info( 'WhatsApp payment completion tracked', array(
            'order_id' => $order_id,
            'total' => $order->get_total(),
        ) );
    }

    /**
     * Track payment failure
     *
     * @param int      $order_id Order ID
     * @param WC_Order $order Order object
     */
    public function track_payment_failed( $order_id, $order ) {
        $whatsapp_data = $this->get_order_whatsapp_data( $order_id );
        if ( ! $whatsapp_data ) {
            return;
        }

        $analytics_data = array(
            'event_type'      => 'payment_failed',
            'order_id'        => $order_id,
            'customer_phone'  => $whatsapp_data['customer_phone'],
            'order_total'     => $order->get_total(),
            'currency'        => $order->get_currency(),
            'payment_method'  => $order->get_payment_method(),
            'failure_reason'  => $this->get_failure_reason( $order ),
            'source'          => 'whatsapp',
            'session_id'      => $this->get_session_id( $whatsapp_data['customer_phone'] ),
            'timestamp'       => current_time( 'mysql' ),
        );

        $this->insert_analytics_event( $analytics_data );
        $this->update_conversion_funnel( $whatsapp_data['customer_phone'], 'payment_failed' );
    }

    /**
     * Track message sent via WhatsApp
     *
     * @param string $phone_number Customer phone number
     * @param string $message_type Message type
     * @param array  $message_data Message data
     */
    public function track_message_sent( $phone_number, $message_type, $message_data ) {
        if ( $this->settings['track_engagement'] !== 'yes' ) {
            return;
        }

        $analytics_data = array(
            'event_type'      => 'message_sent',
            'customer_phone'  => $phone_number,
            'message_type'    => $message_type,
            'message_id'      => $message_data['message_id'] ?? '',
            'source'          => 'whatsapp',
            'session_id'      => $this->get_session_id( $phone_number ),
            'timestamp'       => current_time( 'mysql' ),
        );

        $this->insert_analytics_event( $analytics_data );
    }

    /**
     * Track message received from customer
     *
     * @param array $message Message data
     * @param array $contact Contact data
     */
    public function track_message_received( $message, $contact ) {
        if ( $this->settings['track_engagement'] !== 'yes' ) {
            return;
        }

        $phone_number = $contact['phone'] ?? '';

        $analytics_data = array(
            'event_type'      => 'message_received',
            'customer_phone'  => $phone_number,
            'message_type'    => $message['type'] ?? 'text',
            'message_id'      => $message['id'] ?? '',
            'source'          => 'whatsapp',
            'session_id'      => $this->get_session_id( $phone_number ),
            'timestamp'       => current_time( 'mysql' ),
        );

        $this->insert_analytics_event( $analytics_data );
        $this->update_engagement_metrics( $phone_number, 'message_received' );
    }

    /**
     * Insert analytics event
     *
     * @param array $analytics_data Event data
     * @return bool Success status
     */
    private function insert_analytics_event( $analytics_data ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_whatsapp_analytics';
        return $wpdb->insert( $table_name, $analytics_data ) !== false;
    }

    /**
     * Get session ID for customer
     *
     * @param string $phone_number Customer phone number
     * @return string Session ID
     */
    private function get_session_id( $phone_number ) {
        $session_data = $this->db_table->get_customer_session( $phone_number );
        return $session_data['session_id'] ?? ChatShop_Helper::generate_session_id();
    }

    /**
     * Get WhatsApp data for order
     *
     * @param int $order_id Order ID
     * @return array|null WhatsApp data
     */
    private function get_order_whatsapp_data( $order_id ) {
        return $this->db_table->get_order_whatsapp_data( $order_id );
    }

    /**
     * Get failure reason from order
     *
     * @param WC_Order $order Order object
     * @return string Failure reason
     */
    private function get_failure_reason( $order ) {
        return $order->get_meta( '_payment_failure_reason', true ) ?: 'Unknown';
    }

    /**
     * Track revenue attribution
     *
     * @param WC_Order $order Order object
     * @param array    $whatsapp_data WhatsApp data
     */
    private function track_revenue_attribution( $order, $whatsapp_data ) {
        $attribution_data = array(
            'order_id'        => $order->get_id(),
            'customer_phone'  => $whatsapp_data['customer_phone'],
            'revenue'         => $order->get_total(),
            'currency'        => $order->get_currency(),
            'source'          => 'whatsapp',
            'medium'          => $whatsapp_data['utm_medium'] ?? 'social',
            'campaign'        => $whatsapp_data['utm_campaign'] ?? 'payment_link',
            'attribution_model' => 'last_click',
            'timestamp'       => current_time( 'mysql' ),
        );

        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_revenue_attribution';
        $wpdb->insert( $table_name, $attribution_data );
    }

    /**
     * Update conversion funnel
     *
     * @param string $phone_number Customer phone number
     * @param string $stage Funnel stage
     */
    private function update_conversion_funnel( $phone_number, $stage ) {
        $funnel_data = $this->db_table->get_conversion_funnel_data( $phone_number );
        
        if ( ! $funnel_data ) {
            $funnel_data = array(
                'customer_phone' => $phone_number,
                'session_id' => $this->get_session_id( $phone_number ),
                'first_interaction' => current_time( 'mysql' ),
            );
        }

        $funnel_data[$stage] = current_time( 'mysql' );
        $funnel_data['last_updated'] = current_time( 'mysql' );

        $this->db_table->update_conversion_funnel( $phone_number, $funnel_data );
    }

    /**
     * Update engagement metrics
     *
     * @param string $phone_number Customer phone number
     * @param string $event_type Event type
     */
    private function update_engagement_metrics( $phone_number, $event_type ) {
        $metrics = $this->db_table->get_engagement_metrics( $phone_number );
        
        if ( ! $metrics ) {
            $metrics = array(
                'customer_phone' => $phone_number,
                'total_messages' => 0,
                'total_sessions' => 0,
                'first_interaction' => current_time( 'mysql' ),
            );
        }

        if ( $event_type === 'message_received' ) {
            $metrics['total_messages']++;
        }

        $metrics['last_interaction'] = current_time( 'mysql' );
        $this->db_table->update_engagement_metrics( $phone_number, $metrics );
    }

    /**
     * Get analytics dashboard data
     *
     * @param array $args Query arguments
     * @return array Dashboard data
     */
    public function get_dashboard_data( $args = array() ) {
        $default_args = array(
            'date_from' => date( 'Y-m-d', strtotime( '-30 days' ) ),
            'date_to'   => date( 'Y-m-d' ),
        );

        $args = wp_parse_args( $args, $default_args );

        return array(
            'overview' => $this->get_overview_metrics( $args ),
            'conversions' => $this->get_conversion_metrics( $args['date_from'], $args['date_to'] ),
            'revenue' => $this->get_revenue_metrics( $args ),
        );
    }

    /**
     * Get overview metrics
     *
     * @param array $args Query arguments
     * @return array Overview metrics
     */
    private function get_overview_metrics( $args ) {
        global $wpdb;
        $analytics_table = $wpdb->prefix . 'chatshop_whatsapp_analytics';
        
        $date_condition = $wpdb->prepare( 
            "DATE(timestamp) BETWEEN %s AND %s", 
            $args['date_from'], 
            $args['date_to'] 
        );

        $overview = $wpdb->get_row( "
            SELECT 
                COUNT(*) as total_events,
                COUNT(DISTINCT customer_phone) as unique_customers,
                COUNT(DISTINCT session_id) as total_sessions,
                COUNT(CASE WHEN event_type = 'payment_link_generated' THEN 1 END) as links_generated,
                COUNT(CASE WHEN event_type = 'payment_link_clicked' THEN 1 END) as links_clicked,
                COUNT(CASE WHEN event_type = 'payment_completed' THEN 1 END) as payments_completed
            FROM {$analytics_table} 
            WHERE {$date_condition}
        ", ARRAY_A );

        $overview['click_rate'] = $overview['links_generated'] > 0 
            ? round( ( $overview['links_clicked'] / $overview['links_generated'] ) * 100, 2 )
            : 0;

        $overview['conversion_rate'] = $overview['links_generated'] > 0 
            ? round( ( $overview['payments_completed'] / $overview['links_generated'] ) * 100, 2 )
            : 0;

        return $overview;
    }

    /**
     * Get conversion metrics
     *
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Conversion metrics
     */
    private function get_conversion_metrics( $start_date, $end_date ) {
        global $wpdb;
        $analytics_table = $wpdb->prefix . 'chatshop_whatsapp_analytics';
        
        $date_condition = $wpdb->prepare( 
            "DATE(timestamp) BETWEEN %s AND %s", 
            $start_date, 
            $end_date 
        );

        $funnel_data = $wpdb->get_results( "
            SELECT 
                event_type,
                COUNT(DISTINCT customer_phone) as unique_customers,
                COUNT(*) as total_events
            FROM {$analytics_table} 
            WHERE {$date_condition}
            AND event_type IN ('payment_link_generated', 'payment_link_clicked', 'checkout_started', 'payment_completed')
            GROUP BY event_type
        ", ARRAY_A );

        $conversions = array();
        $funnel_counts = array();
        
        foreach ( $funnel_data as $data ) {
            $funnel_counts[$data['event_type']] = $data['unique_customers'];
        }

        if ( ! empty( $funnel_counts['payment_link_generated'] ) ) {
            $link_generated = $funnel_counts['payment_link_generated'];
            
            $conversions['link_to_click'] = ! empty( $funnel_counts['payment_link_clicked'] ) 
                ? round( ( $funnel_counts['payment_link_clicked'] / $link_generated ) * 100, 2 )
                : 0;
                
            $conversions['link_to_payment'] = ! empty( $funnel_counts['payment_completed'] ) 
                ? round( ( $funnel_counts['payment_completed'] / $link_generated ) * 100, 2 )
                : 0;
        }

        return array(
            'funnel_data' => $funnel_counts,
            'conversion_rates' => $conversions,
        );
    }

    /**
     * Get revenue metrics
     *
     * @param array $args Query arguments
     * @return array Revenue metrics
     */
    private function get_revenue_metrics( $args ) {
        global $wpdb;
        $analytics_table = $wpdb->prefix . 'chatshop_whatsapp_analytics';
        
        $date_condition = $wpdb->prepare( 
            "DATE(timestamp) BETWEEN %s AND %s", 
            $args['date_from'], 
            $args['date_to'] 
        );

        $revenue_data = $wpdb->get_row( "
            SELECT 
                COUNT(*) as total_orders,
                SUM(order_total) as total_revenue,
                AVG(order_total) as avg_order_value
            FROM {$analytics_table} 
            WHERE {$date_condition} AND event_type = 'payment_completed'
        ", ARRAY_A );

        return $revenue_data;
    }

    /**
     * Generate daily report
     */
    public function generate_daily_report() {
        if ( $this->settings['daily_reports'] !== 'yes' ) {
            return;
        }

        $date = date( 'Y-m-d', strtotime( '-1 day' ) );
        $report_data = $this->get_dashboard_data( array(
            'date_from' => $date,
            'date_to' => $date,
        ) );

        $this->save_analytics_report( 'daily', $date, $report_data );
    }

    /**
     * Generate weekly report
     */
    public function generate_weekly_report() {
        if ( $this->settings['weekly_reports'] !== 'yes' ) {
            return;
        }

        $start_date = date( 'Y-m-d', strtotime( '-7 days' ) );
        $end_date = date( 'Y-m-d', strtotime( '-1 day' ) );
        
        $report_data = $this->get_dashboard_data( array(
            'date_from' => $start_date,
            'date_to' => $end_date,
        ) );

        $this->save_analytics_report( 'weekly', $start_date, $report_data );
    }

    /**
     * Save analytics report
     *
     * @param string $type Report type
     * @param string $date Report date
     * @param array  $data Report data
     */
    private function save_analytics_report( $type, $date, $data ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatshop_analytics_reports';

        $wpdb->replace(
            $table_name,
            array(
                'report_type' => $type,
                'report_date' => $date,
                'source' => 'whatsapp',
                'data' => wp_json_encode( $data ),
                'generated_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Schedule analytics reports
     */
    private function schedule_reports() {
        if ( $this->settings['daily_reports'] === 'yes' && ! wp_next_scheduled( 'chatshop_whatsapp_daily_analytics' ) ) {
            wp_schedule_event( strtotime( 'tomorrow 6:00 AM' ), 'daily', 'chatshop_whatsapp_daily_analytics' );
        }

        if ( $this->settings['weekly_reports'] === 'yes' && ! wp_next_scheduled( 'chatshop_whatsapp_weekly_analytics' ) ) {
            wp_schedule_event( strtotime( 'next monday 8:00 AM' ), 'weekly', 'chatshop_whatsapp_weekly_analytics' );
        }
    }

    /**
     * Export analytics data
     *
     * @param array $args Export arguments
     * @return string CSV data
     */
    public function export_analytics_data( $args = array() ) {
        $default_args = array(
            'date_from' => date( 'Y-m-d', strtotime( '-30 days' ) ),
            'date_to'   => date( 'Y-m-d' ),
            'format'    => 'csv',
        );

        $args = wp_parse_args( $args, $default_args );

        global $wpdb;
        $analytics_table = $wpdb->prefix . 'chatshop_whatsapp_analytics';

        $date_condition = $wpdb->prepare( 
            "DATE(timestamp) BETWEEN %s AND %s", 
            $args['date_from'], 
            $args['date_to'] 
        );

        $data = $wpdb->get_results( "
            SELECT * FROM {$analytics_table} 
            WHERE {$date_condition}
            ORDER BY timestamp DESC
        ", ARRAY_A );

        if ( $args['format'] === 'csv' ) {
            return $this->convert_to_csv( $data );
        }

        return $data;
    }

    /**
     * Convert data to CSV format
     *
     * @param array $data Data array
     * @return string CSV content
     */
    private function convert_to_csv( $data ) {
        if ( empty( $data ) ) {
            return '';
        }

        $output = fopen( 'php://temp', 'r+' );
        fputcsv( $output, array_keys( $data[0] ) );

        foreach ( $data as $row ) {
            fputcsv( $output, $row );
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        return $csv;
    }

    /**
     * Get real-time analytics data
     *
     * @return array Real-time data
     */
    public function get_realtime_data() {
        global $wpdb;
        $analytics_table = $wpdb->prefix . 'chatshop_whatsapp_analytics';
        
        $realtime_data = $wpdb->get_results( "
            SELECT 
                event_type,
                COUNT(*) as count,
                MAX(timestamp) as last_event
            FROM {$analytics_table} 
            WHERE timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY event_type
            ORDER BY count DESC
        ", ARRAY_A );

        $active_sessions = $wpdb->get_var( "
            SELECT COUNT(DISTINCT session_id)
            FROM {$analytics_table} 
            WHERE timestamp > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        " );

        return array(
            'events' => $realtime_data,
            'active_sessions' => $active_sessions,
            'last_updated' => current_time( 'mysql' ),
        );
    }
}