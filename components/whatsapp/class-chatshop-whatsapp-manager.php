<?php
/**
 * WhatsApp Payment Manager
 * 
 * Orchestrates all WhatsApp payment functionality including link sharing,
 * status notifications, reminders, and commerce integration.
 * 
 * File: components/whatsapp/class-chatshop-whatsapp-payment-manager.php
 *
 * @package ChatShop
 * @subpackage WhatsApp
 * @since 1.0.0
 */

namespace ChatShop\WhatsApp;

use ChatShop\Abstracts\ChatShop_Component;
use ChatShop\Payment\ChatShop_Payment_Manager;
use ChatShop\WhatsApp\ChatShop_WhatsApp_API;
use ChatShop\WhatsApp\ChatShop_Message_Templates;
use ChatShop\Database\ChatShop_Payment_WhatsApp_Table;
use ChatShop\Helper\ChatShop_Helper;
use ChatShop\Logger\ChatShop_Logger;

defined( 'ABSPATH' ) || exit;

/**
 * WhatsApp Payment Manager class
 */
class ChatShop_WhatsApp_Payment_Manager extends ChatShop_Component {

    /**
     * Component name
     *
     * @var string
     */
    protected $component_name = 'whatsapp_payment';

    /**
     * WhatsApp API instance
     *
     * @var ChatShop_WhatsApp_API
     */
    private $whatsapp_api;

    /**
     * Payment manager instance
     *
     * @var ChatShop_Payment_Manager
     */
    private $payment_manager;

    /**
     * Message templates instance
     *
     * @var ChatShop_Message_Templates
     */
    private $message_templates;

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
     * Initialize the component
     */
    public function init() {
        $this->whatsapp_api      = new ChatShop_WhatsApp_API();
        $this->payment_manager   = new ChatShop_Payment_Manager();
        $this->message_templates = new ChatShop_Message_Templates();
        $this->db_table         = new ChatShop_Payment_WhatsApp_Table();
        $this->logger           = new ChatShop_Logger();

        $this->add_hooks();
    }

    /**
     * Add WordPress hooks
     */
    private function add_hooks() {
        // Payment link generation and sharing
        add_action( 'chatshop_payment_link_generated', array( $this, 'handle_payment_link_generated' ), 10, 3 );
        
        // Payment status changes
        add_action( 'chatshop_payment_status_changed', array( $this, 'handle_payment_status_changed' ), 10, 4 );
        
        // Order status changes
        add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_changed' ), 10, 4 );
        
        // Payment reminders
        add_action( 'chatshop_payment_reminder_scheduled', array( $this, 'send_payment_reminder' ), 10, 2 );
        
        // Failed payment recovery
        add_action( 'chatshop_payment_failed', array( $this, 'handle_failed_payment' ), 10, 3 );
        
        // WhatsApp message webhooks
        add_action( 'chatshop_whatsapp_message_received', array( $this, 'handle_incoming_message' ), 10, 2 );
        
        // Scheduled tasks
        add_action( 'chatshop_send_payment_reminders', array( $this, 'process_scheduled_reminders' ) );
        add_action( 'chatshop_cleanup_expired_links', array( $this, 'cleanup_expired_payment_links' ) );
    }

    /**
     * Send payment link via WhatsApp
     *
     * @param int    $order_id Order ID
     * @param string $phone_number Customer phone number
     * @param array  $options Additional options
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function send_payment_link( $order_id, $phone_number, $options = array() ) {
        try {
            // Validate inputs
            if ( empty( $order_id ) || empty( $phone_number ) ) {
                return new WP_Error( 'invalid_params', __( 'Order ID and phone number are required.', 'chatshop' ) );
            }

            // Get order details
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return new WP_Error( 'invalid_order', __( 'Order not found.', 'chatshop' ) );
            }

            // Generate payment link
            $payment_link = $this->payment_manager->generate_payment_link( $order_id, array(
                'source'      => 'whatsapp',
                'phone'       => $phone_number,
                'expires_in'  => $options['expires_in'] ?? 24 * 3600, // 24 hours default
            ) );

            if ( is_wp_error( $payment_link ) ) {
                return $payment_link;
            }

            // Prepare message data
            $message_data = array(
                'order'        => $order,
                'payment_link' => $payment_link,
                'customer'     => $order->get_user(),
                'options'      => $options,
            );

            // Get message template
            $message = $this->message_templates->get_payment_link_message( $message_data );

            // Send WhatsApp message
            $result = $this->whatsapp_api->send_message( $phone_number, $message );

            if ( is_wp_error( $result ) ) {
                $this->logger->error( 'Failed to send payment link via WhatsApp', array(
                    'order_id' => $order_id,
                    'phone'    => $phone_number,
                    'error'    => $result->get_error_message(),
                ) );
                return $result;
            }

            // Log successful send
            $this->db_table->insert_payment_message( array(
                'order_id'           => $order_id,
                'phone_number'       => $phone_number,
                'message_type'       => 'payment_link',
                'payment_link_id'    => $payment_link['id'],
                'whatsapp_message_id' => $result['message_id'],
                'status'             => 'sent',
                'sent_at'            => current_time( 'mysql' ),
            ) );

            // Schedule reminder if configured
            if ( ! empty( $options['reminder_delay'] ) ) {
                $this->schedule_payment_reminder( $order_id, $phone_number, $options['reminder_delay'] );
            }

            return true;

        } catch ( Exception $e ) {
            $this->logger->error( 'Exception in send_payment_link', array(
                'order_id' => $order_id,
                'phone'    => $phone_number,
                'error'    => $e->getMessage(),
            ) );
            return new WP_Error( 'send_failed', $e->getMessage() );
        }
    }

    /**
     * Handle payment link generated event
     *
     * @param string $link_id Payment link ID
     * @param int    $order_id Order ID
     * @param array  $link_data Link data
     */
    public function handle_payment_link_generated( $link_id, $order_id, $link_data ) {
        // Check if this was requested via WhatsApp
        if ( ! empty( $link_data['source'] ) && $link_data['source'] === 'whatsapp' ) {
            $phone_number = $link_data['phone'] ?? '';
            
            if ( ! empty( $phone_number ) ) {
                // Auto-send the link if auto-send is enabled
                $auto_send = get_option( 'chatshop_whatsapp_auto_send_payment_links', 'yes' );
                
                if ( $auto_send === 'yes' ) {
                    $this->send_payment_link( $order_id, $phone_number, array(
                        'link_id' => $link_id,
                    ) );
                }
            }
        }
    }

    /**
     * Handle payment status changes
     *
     * @param string $new_status New payment status
     * @param string $old_status Old payment status
     * @param int    $order_id Order ID
     * @param array  $payment_data Payment data
     */
    public function handle_payment_status_changed( $new_status, $old_status, $order_id, $payment_data ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $phone_number = $this->get_order_phone_number( $order );
        if ( empty( $phone_number ) ) {
            return;
        }

        // Handle different status changes
        switch ( $new_status ) {
            case 'completed':
            case 'success':
                $this->send_payment_success_notification( $order_id, $phone_number, $payment_data );
                break;

            case 'failed':
                $this->send_payment_failed_notification( $order_id, $phone_number, $payment_data );
                break;

            case 'pending':
                $this->send_payment_pending_notification( $order_id, $phone_number, $payment_data );
                break;

            case 'refunded':
                $this->send_payment_refunded_notification( $order_id, $phone_number, $payment_data );
                break;
        }
    }

    /**
     * Send payment success notification
     *
     * @param int    $order_id Order ID
     * @param string $phone_number Customer phone number
     * @param array  $payment_data Payment data
     */
    public function send_payment_success_notification( $order_id, $phone_number, $payment_data ) {
        $order = wc_get_order( $order_id );
        
        $message_data = array(
            'order'        => $order,
            'payment_data' => $payment_data,
            'customer'     => $order->get_user(),
        );

        $message = $this->message_templates->get_payment_success_message( $message_data );
        $result  = $this->whatsapp_api->send_message( $phone_number, $message );

        if ( ! is_wp_error( $result ) ) {
            $this->db_table->insert_payment_message( array(
                'order_id'           => $order_id,
                'phone_number'       => $phone_number,
                'message_type'       => 'payment_success',
                'whatsapp_message_id' => $result['message_id'],
                'status'             => 'sent',
                'sent_at'            => current_time( 'mysql' ),
            ) );

            // Cancel any pending reminders
            $this->cancel_payment_reminders( $order_id );
        }
    }

    /**
     * Send payment failed notification
     *
     * @param int    $order_id Order ID
     * @param string $phone_number Customer phone number
     * @param array  $payment_data Payment data
     */
    public function send_payment_failed_notification( $order_id, $phone_number, $payment_data ) {
        $order = wc_get_order( $order_id );
        
        $message_data = array(
            'order'        => $order,
            'payment_data' => $payment_data,
            'customer'     => $order->get_user(),
        );

        $message = $this->message_templates->get_payment_failed_message( $message_data );
        $result  = $this->whatsapp_api->send_message( $phone_number, $message );

        if ( ! is_wp_error( $result ) ) {
            $this->db_table->insert_payment_message( array(
                'order_id'           => $order_id,
                'phone_number'       => $phone_number,
                'message_type'       => 'payment_failed',
                'whatsapp_message_id' => $result['message_id'],
                'status'             => 'sent',
                'sent_at'            => current_time( 'mysql' ),
            ) );

            // Trigger recovery flow if enabled
            $recovery_enabled = get_option( 'chatshop_whatsapp_failed_payment_recovery', 'yes' );
            if ( $recovery_enabled === 'yes' ) {
                $this->initiate_payment_recovery( $order_id, $phone_number, $payment_data );
            }
        }
    }

    /**
     * Schedule payment reminder
     *
     * @param int    $order_id Order ID
     * @param string $phone_number Customer phone number
     * @param int    $delay_seconds Delay in seconds
     */
    public function schedule_payment_reminder( $order_id, $phone_number, $delay_seconds ) {
        wp_schedule_single_event(
            time() + $delay_seconds,
            'chatshop_payment_reminder_scheduled',
            array( $order_id, $phone_number )
        );

        // Store reminder info
        $this->db_table->insert_payment_reminder( array(
            'order_id'      => $order_id,
            'phone_number'  => $phone_number,
            'scheduled_for' => date( 'Y-m-d H:i:s', time() + $delay_seconds ),
            'status'        => 'scheduled',
        ) );
    }

    /**
     * Send payment reminder
     *
     * @param int    $order_id Order ID
     * @param string $phone_number Customer phone number
     */
    public function send_payment_reminder( $order_id, $phone_number ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || $order->is_paid() ) {
            return; // Order not found or already paid
        }

        // Get existing payment link
        $payment_link = $this->payment_manager->get_order_payment_link( $order_id );
        
        if ( ! $payment_link || $this->payment_manager->is_link_expired( $payment_link['id'] ) ) {
            // Generate new payment link
            $payment_link = $this->payment_manager->generate_payment_link( $order_id, array(
                'source' => 'whatsapp_reminder',
                'phone'  => $phone_number,
            ) );
        }

        $message_data = array(
            'order'        => $order,
            'payment_link' => $payment_link,
            'customer'     => $order->get_user(),
            'is_reminder'  => true,
        );

        $message = $this->message_templates->get_payment_reminder_message( $message_data );
        $result  = $this->whatsapp_api->send_message( $phone_number, $message );

        if ( ! is_wp_error( $result ) ) {
            $this->db_table->update_payment_reminder_status( $order_id, 'sent' );
            
            $this->db_table->insert_payment_message( array(
                'order_id'           => $order_id,
                'phone_number'       => $phone_number,
                'message_type'       => 'payment_reminder',
                'whatsapp_message_id' => $result['message_id'],
                'status'             => 'sent',
                'sent_at'            => current_time( 'mysql' ),
            ) );
        }
    }

    /**
     * Handle incoming WhatsApp messages
     *
     * @param array $message Message data
     * @param array $contact Contact data
     */
    public function handle_incoming_message( $message, $contact ) {
        $phone_number = $contact['phone'] ?? '';
        $message_text = strtolower( trim( $message['text'] ?? '' ) );

        // Check for payment-related keywords
        $payment_keywords = array( 'payment', 'pay', 'order', 'receipt', 'invoice', 'status', 'help' );
        $is_payment_query = false;

        foreach ( $payment_keywords as $keyword ) {
            if ( strpos( $message_text, $keyword ) !== false ) {
                $is_payment_query = true;
                break;
            }
        }

        if ( $is_payment_query ) {
            $this->handle_payment_query( $phone_number, $message_text, $message );
        }
    }

    /**
     * Handle payment queries from customers
     *
     * @param string $phone_number Customer phone number
     * @param string $message_text Message text
     * @param array  $message_data Full message data
     */
    public function handle_payment_query( $phone_number, $message_text, $message_data ) {
        // Find recent orders for this phone number
        $recent_orders = $this->get_customer_recent_orders( $phone_number );

        if ( empty( $recent_orders ) ) {
            $response = $this->message_templates->get_no_orders_message();
            $this->whatsapp_api->send_message( $phone_number, $response );
            return;
        }

        // Determine query type and respond accordingly
        if ( strpos( $message_text, 'status' ) !== false ) {
            $this->send_order_status_info( $phone_number, $recent_orders );
        } elseif ( strpos( $message_text, 'payment' ) !== false || strpos( $message_text, 'pay' ) !== false ) {
            $this->send_payment_info( $phone_number, $recent_orders );
        } elseif ( strpos( $message_text, 'help' ) !== false ) {
            $this->send_payment_help( $phone_number );
        } else {
            // General payment support
            $this->send_general_payment_support( $phone_number, $recent_orders );
        }
    }

    /**
     * Get customer recent orders by phone number
     *
     * @param string $phone_number Customer phone number
     * @return array Array of recent orders
     */
    private function get_customer_recent_orders( $phone_number ) {
        global $wpdb;

        $query = $wpdb->prepare( "
            SELECT DISTINCT pm.post_id as order_id 
            FROM {$wpdb->postmeta} pm 
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
            WHERE pm.meta_key = '_billing_phone' 
            AND pm.meta_value = %s 
            AND p.post_type = 'shop_order' 
            AND p.post_status IN ('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed')
            ORDER BY p.post_date DESC 
            LIMIT 5
        ", $phone_number );

        $order_ids = $wpdb->get_col( $query );
        $orders    = array();

        foreach ( $order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $orders[] = $order;
            }
        }

        return $orders;
    }

    /**
     * Get order phone number
     *
     * @param WC_Order $order Order object
     * @return string Phone number
     */
    private function get_order_phone_number( $order ) {
        $phone = $order->get_billing_phone();
        
        // Clean and format phone number
        $phone = preg_replace( '/[^0-9+]/', '', $phone );
        
        return $phone;
    }

    /**
     * Cancel payment reminders for an order
     *
     * @param int $order_id Order ID
     */
    private function cancel_payment_reminders( $order_id ) {
        // Cancel scheduled reminders
        wp_clear_scheduled_hook( 'chatshop_payment_reminder_scheduled', array( $order_id ) );
        
        // Update database status
        $this->db_table->cancel_payment_reminders( $order_id );
    }

    /**
     * Get component dependencies
     *
     * @return array Array of required components
     */
    public function get_dependencies() {
        return array(
            'payment',
            'whatsapp',
        );
    }

    /**
     * Check if component is enabled
     *
     * @return bool True if enabled
     */
    public function is_enabled() {
        return get_option( 'chatshop_whatsapp_payment_enabled', 'yes' ) === 'yes';
    }
}