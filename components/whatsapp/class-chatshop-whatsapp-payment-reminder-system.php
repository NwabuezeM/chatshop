<?php
/**
 * WhatsApp Payment Reminder System
 * 
 * Automated payment reminder system that sends intelligent reminders
 * via WhatsApp based on configurable rules and customer behavior.
 * 
 * File: components/whatsapp/class-chatshop-whatsapp-payment-reminder-system.php
 *
 * @package ChatShop
 * @subpackage WhatsApp
 * @since 1.0.0
 */

namespace ChatShop\WhatsApp;

use ChatShop\WhatsApp\ChatShop_WhatsApp_API;
use ChatShop\WhatsApp\ChatShop_Message_Templates;
use ChatShop\WhatsApp\ChatShop_WhatsApp_Payment_Link_Generator;
use ChatShop\Database\ChatShop_Payment_WhatsApp_Table;
use ChatShop\Helper\ChatShop_Helper;
use ChatShop\Logger\ChatShop_Logger;

defined( 'ABSPATH' ) || exit;

/**
 * WhatsApp Payment Reminder System class
 */
class ChatShop_WhatsApp_Payment_Reminder_System {

    /**
     * WhatsApp API instance
     *
     * @var ChatShop_WhatsApp_API
     */
    private $whatsapp_api;

    /**
     * Message templates instance
     *
     * @var ChatShop_Message_Templates
     */
    private $message_templates;

    /**
     * Payment link generator instance
     *
     * @var ChatShop_WhatsApp_Payment_Link_Generator
     */
    private $link_generator;

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
     * Reminder settings
     *
     * @var array
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->whatsapp_api      = new ChatShop_WhatsApp_API();
        $this->message_templates = new ChatShop_Message_Templates();
        $this->link_generator    = new ChatShop_WhatsApp_Payment_Link_Generator();
        $this->db_table         = new ChatShop_Payment_WhatsApp_Table();
        $this->logger           = new ChatShop_Logger();
        
        $this->load_settings();
        $this->init_hooks();
    }

    /**
     * Load reminder settings
     */
    private function load_settings() {
        $this->settings = array(
            'enabled'              => get_option( 'chatshop_whatsapp_reminders_enabled', 'yes' ),
            'max_reminders'        => intval( get_option( 'chatshop_whatsapp_max_reminders', 3 ) ),
            'reminder_intervals'   => get_option( 'chatshop_whatsapp_reminder_intervals', array( 3600, 86400, 259200 ) ), // 1h, 1d, 3d
            'reminder_types'       => get_option( 'chatshop_whatsapp_reminder_types', array( 'gentle', 'urgent', 'final' ) ),
            'stop_on_payment'      => get_option( 'chatshop_whatsapp_stop_on_payment', 'yes' ),
            'stop_on_cancellation' => get_option( 'chatshop_whatsapp_stop_on_cancellation', 'yes' ),
            'personalize_messages' => get_option( 'chatshop_whatsapp_personalize_reminders', 'yes' ),
            'include_incentives'   => get_option( 'chatshop_whatsapp_include_incentives', 'no' ),
            'track_engagement'     => get_option( 'chatshop_whatsapp_track_engagement', 'yes' ),
        );

        // Ensure reminder intervals is an array
        if ( ! is_array( $this->settings['reminder_intervals'] ) ) {
            $this->settings['reminder_intervals'] = array( 3600, 86400, 259200 );
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        if ( $this->settings['enabled'] !== 'yes' ) {
            return;
        }

        // Order creation and status change hooks
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'schedule_initial_reminders' ), 10, 1 );
        add_action( 'woocommerce_order_status_pending', array( $this, 'handle_order_pending' ), 10, 2 );
        add_action( 'woocommerce_payment_complete', array( $this, 'cancel_order_reminders' ), 10, 1 );
        add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_order_reminders' ), 10, 1 );

        // Scheduled reminder hooks
        add_action( 'chatshop_send_payment_reminder', array( $this, 'send_scheduled_reminder' ), 10, 3 );
        add_action( 'chatshop_process_reminder_queue', array( $this, 'process_reminder_queue' ) );

        // Daily cleanup
        add_action( 'chatshop_daily_reminder_cleanup', array( $this, 'cleanup_expired_reminders' ) );

        // Schedule daily cleanup if not already scheduled
        if ( ! wp_next_scheduled( 'chatshop_daily_reminder_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'chatshop_daily_reminder_cleanup' );
        }
    }

    /**
     * Schedule initial reminders for a new order
     *
     * @param int $order_id Order ID
     */
    public function schedule_initial_reminders( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || $order->is_paid() ) {
            return;
        }

        // Check if reminders should be scheduled for this payment method
        if ( ! $this->should_schedule_reminders( $order ) ) {
            return;
        }

        $phone_number = $this->get_order_phone_number( $order );
        if ( empty( $phone_number ) ) {
            return;
        }

        // Schedule reminders based on intervals
        $reminder_count = 0;
        foreach ( $this->settings['reminder_intervals'] as $interval ) {
            if ( $reminder_count >= $this->settings['max_reminders'] ) {
                break;
            }

            $this->schedule_reminder( $order_id, $phone_number, $interval, $reminder_count + 1 );
            $reminder_count++;
        }

        $this->logger->info( 'Payment reminders scheduled', array(
            'order_id' => $order_id,
            'phone' => $phone_number,
            'reminder_count' => $reminder_count,
        ) );
    }

    /**
     * Schedule a single reminder
     *
     * @param int    $order_id Order ID
     * @param string $phone_number Customer phone number
     * @param int    $interval Delay in seconds
     * @param int    $reminder_number Reminder sequence number
     */
    private function schedule_reminder( $order_id, $phone_number, $interval, $reminder_number ) {
        $scheduled_time = time() + $interval;
        
        wp_schedule_single_event(
            $scheduled_time,
            'chatshop_send_payment_reminder',
            array( $order_id, $phone_number, $reminder_number )
        );

        // Store reminder in database
        $this->db_table->insert_payment_reminder( array(
            'order_id'        => $order_id,
            'phone_number'    => $phone_number,
            'reminder_number' => $reminder_number,
            'scheduled_for'   => date( 'Y-m-d H:i:s', $scheduled_time ),
            'status'          => 'scheduled',
            'reminder_type'   => $this->get_reminder_type( $reminder_number ),
        ) );
    }

    /**
     * Send scheduled reminder
     *
     * @param int    $order_id Order ID
     * @param string $phone_number Customer phone number
     * @param int    $reminder_number Reminder sequence number
     */
    public function send_scheduled_reminder( $order_id, $phone_number, $reminder_number ) {
        try {
            $order = wc_get_order( $order_id );
            
            // Validate order and payment status
            if ( ! $this->should_send_reminder( $order, $reminder_number ) ) {
                $this->cancel_reminder( $order_id, $reminder_number );
                return;
            }

            // Get or generate payment link
            $payment_link = $this->get_payment_link_for_reminder( $order_id );
            if ( is_wp_error( $payment_link ) ) {
                $this->logger->error( 'Failed to get payment link for reminder', array(
                    'order_id' => $order_id,
                    'reminder_number' => $reminder_number,
                    'error' => $payment_link->get_error_message(),
                ) );
                return;
            }

            // Generate personalized reminder message
            $message = $this->generate_reminder_message( $order, $payment_link, $reminder_number );

            // Send reminder via WhatsApp
            $result = $this->whatsapp_api->send_message( $phone_number, $message );

            if ( is_wp_error( $result ) ) {
                $this->handle_reminder_failure( $order_id, $phone_number, $reminder_number, $result );
                return;
            }

            // Mark reminder as sent
            $this->mark_reminder_sent( $order_id, $reminder_number, $result );

            // Track engagement if enabled
            if ( $this->settings['track_engagement'] === 'yes' ) {
                $this->track_reminder_sent( $order_id, $reminder_number, $payment_link );
            }

            $this->logger->info( 'Payment reminder sent successfully', array(
                'order_id' => $order_id,
                'phone' => $phone_number,
                'reminder_number' => $reminder_number,
                'message_id' => $result['message_id'] ?? '',
            ) );

        } catch ( Exception $e ) {
            $this->logger->error( 'Exception in send_scheduled_reminder', array(
                'order_id' => $order_id,
                'reminder_number' => $reminder_number,
                'error' => $e->getMessage(),
            ) );
        }
    }

    /**
     * Generate personalized reminder message
     *
     * @param WC_Order $order Order object
     * @param array    $payment_link Payment link data
     * @param int      $reminder_number Reminder sequence number
     * @return string Reminder message
     */
    private function generate_reminder_message( $order, $payment_link, $reminder_number ) {
        $reminder_type = $this->get_reminder_type( $reminder_number );
        
        $message_data = array(
            'order'           => $order,
            'payment_link'    => $payment_link,
            'reminder_number' => $reminder_number,
            'reminder_type'   => $reminder_type,
            'customer'        => $order->get_user(),
            'personalized'    => $this->settings['personalize_messages'] === 'yes',
        );

        // Add incentives for later reminders if enabled
        if ( $this->settings['include_incentives'] === 'yes' && $reminder_number > 1 ) {
            $message_data['incentive'] = $this->get_reminder_incentive( $order, $reminder_number );
        }

        // Add urgency indicators for final reminders
        if ( $reminder_number >= $this->settings['max_reminders'] ) {
            $message_data['is_final'] = true;
        }

        return $this->message_templates->get_payment_reminder_message( $message_data );
    }

    /**
     * Get reminder type based on sequence number
     *
     * @param int $reminder_number Reminder sequence number
     * @return string Reminder type
     */
    private function get_reminder_type( $reminder_number ) {
        $types = $this->settings['reminder_types'];
        $index = min( $reminder_number - 1, count( $types ) - 1 );
        
        return $types[$index] ?? 'gentle';
    }

    /**
     * Get payment link for reminder
     *
     * @param int $order_id Order ID
     * @return array|WP_Error Payment link data or error
     */
    private function get_payment_link_for_reminder( $order_id ) {
        // Try to get existing active link
        $existing_link = $this->db_table->get_active_payment_link( $order_id );
        
        if ( $existing_link && ! $this->is_link_expired( $existing_link ) ) {
            return $existing_link;
        }

        // Generate new payment link
        $order = wc_get_order( $order_id );
        $phone_number = $this->get_order_phone_number( $order );

        return $this->link_generator->generate_whatsapp_payment_link( $order_id, array(
            'source'        => 'whatsapp_reminder',
            'customer_phone' => $phone_number,
            'expires_in'    => 48 * 3600, // 48 hours for reminders
        ) );
    }

    /**
     * Check if should send reminder
     *
     * @param WC_Order|null $order Order object
     * @param int           $reminder_number Reminder sequence number
     * @return bool True if should send
     */
    private function should_send_reminder( $order, $reminder_number ) {
        if ( ! $order ) {
            return false;
        }

        // Don't send if order is paid
        if ( $order->is_paid() ) {
            return false;
        }

        // Don't send if order is cancelled
        if ( $order->has_status( 'cancelled' ) ) {
            return false;
        }

        // Don't send if customer has opted out
        if ( $this->has_customer_opted_out( $order ) ) {
            return false;
        }

        // Check if reminder was already sent
        if ( $this->was_reminder_already_sent( $order->get_id(), $reminder_number ) ) {
            return false;
        }

        return true;
    }

    /**
     * Check if should schedule reminders for order
     *
     * @param WC_Order $order Order object
     * @return bool True if should schedule
     */
    private function should_schedule_reminders( $order ) {
        // Check payment method eligibility
        $payment_method = $order->get_payment_method();
        $excluded_methods = get_option( 'chatshop_whatsapp_reminder_excluded_methods', array() );
        
        if ( in_array( $payment_method, $excluded_methods ) ) {
            return false;
        }

        // Check order total threshold
        $min_total = floatval( get_option( 'chatshop_whatsapp_reminder_min_total', 0 ) );
        if ( $min_total > 0 && $order->get_total() < $min_total ) {
            return false;
        }

        // Check customer preferences
        if ( $this->has_customer_opted_out( $order ) ) {
            return false;
        }

        return true;
    }

    /**
     * Check if customer has opted out of reminders
     *
     * @param WC_Order $order Order object
     * @return bool True if opted out
     */
    private function has_customer_opted_out( $order ) {
        $customer_id = $order->get_customer_id();
        
        if ( $customer_id ) {
            $opted_out = get_user_meta( $customer_id, 'chatshop_whatsapp_reminders_opt_out', true );
            return $opted_out === 'yes';
        }

        // Check by phone number for guest customers
        $phone_number = $this->get_order_phone_number( $order );
        if ( $phone_number ) {
            return $this->db_table->is_phone_opted_out( $phone_number );
        }

        return false;
    }

    /**
     * Check if reminder was already sent
     *
     * @param int $order_id Order ID
     * @param int $reminder_number Reminder sequence number
     * @return bool True if already sent
     */
    private function was_reminder_already_sent( $order_id, $reminder_number ) {
        return $this->db_table->reminder_exists( $order_id, $reminder_number, 'sent' );
    }

    /**
     * Mark reminder as sent
     *
     * @param int   $order_id Order ID
     * @param int   $reminder_number Reminder sequence number
     * @param array $whatsapp_result WhatsApp API result
     */
    private function mark_reminder_sent( $order_id, $reminder_number, $whatsapp_result ) {
        $this->db_table->update_reminder_status( $order_id, $reminder_number, 'sent', array(
            'whatsapp_message_id' => $whatsapp_result['message_id'] ?? '',
            'sent_at' => current_time( 'mysql' ),
        ) );

        // Log in payment messages table
        $order = wc_get_order( $order_id );
        $phone_number = $this->get_order_phone_number( $order );

        $this->db_table->insert_payment_message( array(
            'order_id' => $order_id,
            'phone_number' => $phone_number,
            'message_type' => "payment_reminder_{$reminder_number}",
            'whatsapp_message_id' => $whatsapp_result['message_id'] ?? '',
            'status' => 'sent',
            'sent_at' => current_time( 'mysql' ),
        ) );
    }

    /**
     * Handle reminder sending failure
     *
     * @param int      $order_id Order ID
     * @param string   $phone_number Customer phone number
     * @param int      $reminder_number Reminder sequence number
     * @param WP_Error $error Error object
     */
    private function handle_reminder_failure( $order_id, $phone_number, $reminder_number, $error ) {
        $this->db_table->update_reminder_status( $order_id, $reminder_number, 'failed', array(
            'error_message' => $error->get_error_message(),
            'failed_at' => current_time( 'mysql' ),
        ) );

        $this->logger->error( 'Payment reminder failed', array(
            'order_id' => $order_id,
            'phone' => $phone_number,
            'reminder_number' => $reminder_number,
            'error' => $error->get_error_message(),
        ) );

        // Schedule retry if appropriate
        $retry_count = $this->db_table->get_reminder_retry_count( $order_id, $reminder_number );
        if ( $retry_count < 2 ) { // Max 2 retries
            $this->schedule_reminder_retry( $order_id, $phone_number, $reminder_number, $retry_count + 1 );
        }
    }

    /**
     * Schedule reminder retry
     *
     * @param int $order_id Order ID
     * @param string $phone_number Customer phone number
     * @param int $reminder_number Reminder sequence number
     * @param int $retry_count Retry attempt number
     */
    private function schedule_reminder_retry( $order_id, $phone_number, $reminder_number, $retry_count ) {
        $retry_delay = 3600 * $retry_count; // 1 hour * retry count
        
        wp_schedule_single_event(
            time() + $retry_delay,
            'chatshop_send_payment_reminder',
            array( $order_id, $phone_number, $reminder_number )
        );

        $this->db_table->increment_reminder_retry_count( $order_id, $reminder_number );
    }

    /**
     * Cancel all reminders for an order
     *
     * @param int $order_id Order ID
     */
    public function cancel_order_reminders( $order_id ) {
        // Cancel scheduled WordPress events
        $scheduled_reminders = $this->db_table->get_scheduled_reminders( $order_id );
        
        foreach ( $scheduled_reminders as $reminder ) {
            wp_clear_scheduled_hook( 'chatshop_send_payment_reminder', array( 
                $order_id, 
                $reminder['phone_number'], 
                $reminder['reminder_number'] 
            ) );
        }

        // Update database status
        $this->db_table->cancel_order_reminders( $order_id );

        $this->logger->info( 'Payment reminders cancelled', array(
            'order_id' => $order_id,
            'cancelled_count' => count( $scheduled_reminders ),
        ) );
    }

    /**
     * Cancel specific reminder
     *
     * @param int $order_id Order ID
     * @param int $reminder_number Reminder sequence number
     */
    private function cancel_reminder( $order_id, $reminder_number ) {
        $this->db_table->cancel_reminder( $order_id, $reminder_number );
    }

    /**
     * Handle order pending status
     *
     * @param int $order_id Order ID
     * @param WC_Order $order Order object
     */
    public function handle_order_pending( $order_id, $order ) {
        // Check if reminders are already scheduled
        if ( ! $this->db_table->has_scheduled_reminders( $order_id ) ) {
            $this->schedule_initial_reminders( $order_id );
        }
    }

    /**
     * Get reminder incentive for later reminders
     *
     * @param WC_Order $order Order object
     * @param int $reminder_number Reminder sequence number
     * @return array|null Incentive data
     */
    private function get_reminder_incentive( $order, $reminder_number ) {
        $incentives = get_option( 'chatshop_whatsapp_reminder_incentives', array() );
        
        if ( empty( $incentives ) ) {
            return null;
        }

        // Simple incentive selection based on reminder number
        foreach ( $incentives as $incentive ) {
            if ( $reminder_number >= $incentive['trigger_reminder'] ) {
                return array(
                    'type' => $incentive['type'], // 'discount', 'free_shipping', 'bonus'
                    'value' => $incentive['value'],
                    'description' => $incentive['description'],
                    'expires_in' => $incentive['expires_in'] ?? 24, // hours
                );
            }
        }

        return null;
    }

    /**
     * Track reminder engagement
     *
     * @param int   $order_id Order ID
     * @param int   $reminder_number Reminder sequence number
     * @param array $payment_link Payment link data
     */
    private function track_reminder_sent( $order_id, $reminder_number, $payment_link ) {
        $this->db_table->insert_reminder_tracking( array(
            'order_id' => $order_id,
            'reminder_number' => $reminder_number,
            'payment_link_id' => $payment_link['id'],
            'sent_at' => current_time( 'mysql' ),
            'status' => 'sent',
        ) );
    }

    /**
     * Process reminder queue (for bulk processing)
     */
    public function process_reminder_queue() {
        $pending_reminders = $this->db_table->get_pending_reminders( 50 ); // Process 50 at a time
        
        foreach ( $pending_reminders as $reminder ) {
            $this->send_scheduled_reminder( 
                $reminder['order_id'], 
                $reminder['phone_number'], 
                $reminder['reminder_number'] 
            );

            // Add small delay to avoid rate limiting
            usleep( 500000 ); // 0.5 seconds
        }

        $this->logger->info( 'Reminder queue processed', array(
            'processed_count' => count( $pending_reminders ),
        ) );
    }

    /**
     * Cleanup expired reminders
     */
    public function cleanup_expired_reminders() {
        $deleted_count = $this->db_table->delete_expired_reminders();
        
        $this->logger->info( 'Expired reminders cleaned up', array(
            'deleted_count' => $deleted_count,
        ) );
    }

    /**
     * Get order phone number
     *
     * @param WC_Order $order Order object
     * @return string Phone number
     */
    private function get_order_phone_number( $order ) {
        $phone = $order->get_billing_phone();
        return preg_replace( '/[^0-9+]/', '', $phone );
    }

    /**
     * Check if payment link is expired
     *
     * @param array $link_data Link data
     * @return bool True if expired
     */
    private function is_link_expired( $link_data ) {
        if ( ! isset( $link_data['expires_at'] ) ) {
            return false;
        }
        
        return strtotime( $link_data['expires_at'] ) < time();
    }

    /**
     * Get reminder statistics
     *
     * @param array $args Query arguments
     * @return array Statistics data
     */
    public function get_reminder_statistics( $args = array() ) {
        $default_args = array(
            'date_from' => date( 'Y-m-d', strtotime( '-30 days' ) ),
            'date_to'   => date( 'Y-m-d' ),
        );

        $args = wp_parse_args( $args, $default_args );

        $stats = $this->db_table->get_reminder_statistics( $args );

        // Calculate conversion rates
        if ( $stats['total_sent'] > 0 ) {
            $stats['conversion_rate'] = round( ( $stats['converted'] / $stats['total_sent'] ) * 100, 2 );
        } else {
            $stats['conversion_rate'] = 0;
        }

        // Add effectiveness by reminder number
        $stats['effectiveness_by_reminder'] = $this->get_effectiveness_by_reminder_number( $args );

        return $stats;
    }

    /**
     * Get effectiveness statistics by reminder number
     *
     * @param array $args Query arguments
     * @return array Effectiveness data
     */
    private function get_effectiveness_by_reminder_number( $args ) {
        return $this->db_table->get_reminder_effectiveness( $args );
    }

    /**
     * Send immediate reminder
     *
     * @param int    $order_id Order ID
     * @param string $message_override Optional custom message
     * @return bool|WP_Error Success status or error
     */
    public function send_immediate_reminder( $order_id, $message_override = '' ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'invalid_order', __( 'Order not found.', 'chatshop' ) );
        }

        if ( $order->is_paid() ) {
            return new WP_Error( 'order_paid', __( 'Order is already paid.', 'chatshop' ) );
        }

        $phone_number = $this->get_order_phone_number( $order );
        if ( empty( $phone_number ) ) {
            return new WP_Error( 'no_phone', __( 'No phone number found.', 'chatshop' ) );
        }

        // Get payment link
        $payment_link = $this->get_payment_link_for_reminder( $order_id );
        if ( is_wp_error( $payment_link ) ) {
            return $payment_link;
        }

        // Generate message
        if ( ! empty( $message_override ) ) {
            $message = $message_override;
        } else {
            $message = $this->generate_reminder_message( $order, $payment_link, 1 );
        }

        // Send message
        $result = $this->whatsapp_api->send_message( $phone_number, $message );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Log immediate reminder
        $this->db_table->insert_payment_message( array(
            'order_id' => $order_id,
            'phone_number' => $phone_number,
            'message_type' => 'immediate_reminder',
            'whatsapp_message_id' => $result['message_id'] ?? '',
            'status' => 'sent',
            'sent_at' => current_time( 'mysql' ),
        ) );

        return true;
    }

    /**
     * Opt customer out of reminders
     *
     * @param string $phone_number Customer phone number
     * @param string $reason Opt-out reason
     * @return bool Success status
     */
    public function opt_out_customer( $phone_number, $reason = '' ) {
        // Cancel any pending reminders for this phone
        $this->cancel_reminders_by_phone( $phone_number );

        // Store opt-out preference
        $result = $this->db_table->insert_opt_out( array(
            'phone_number' => $phone_number,
            'reason' => $reason,
            'opted_out_at' => current_time( 'mysql' ),
        ) );

        if ( $result ) {
            $this->logger->info( 'Customer opted out of reminders', array(
                'phone' => $phone_number,
                'reason' => $reason,
            ) );
        }

        return $result;
    }

    /**
     * Cancel reminders by phone number
     *
     * @param string $phone_number Customer phone number
     */
    private function cancel_reminders_by_phone( $phone_number ) {
        $active_reminders = $this->db_table->get_active_reminders_by_phone( $phone_number );
        
        foreach ( $active_reminders as $reminder ) {
            $this->cancel_order_reminders( $reminder['order_id'] );
        }
    }

    /**
     * Opt customer back in to reminders
     *
     * @param string $phone_number Customer phone number
     * @return bool Success status
     */
    public function opt_in_customer( $phone_number ) {
        $result = $this->db_table->remove_opt_out( $phone_number );

        if ( $result ) {
            $this->logger->info( 'Customer opted back in to reminders', array(
                'phone' => $phone_number,
            ) );
        }

        return $result;
    }

    /**
     * Get reminder history for order
     *
     * @param int $order_id Order ID
     * @return array Reminder history
     */
    public function get_order_reminder_history( $order_id ) {
        return $this->db_table->get_order_reminder_history( $order_id );
    }

    /**
     * Update reminder settings
     *
     * @param array $new_settings New settings array
     * @return bool Success status
     */
    public function update_reminder_settings( $new_settings ) {
        foreach ( $new_settings as $key => $value ) {
            if ( array_key_exists( $key, $this->settings ) ) {
                update_option( "chatshop_whatsapp_{$key}", $value );
                $this->settings[$key] = $value;
            }
        }

        $this->logger->info( 'Reminder settings updated', $new_settings );
        return true;
    }
}