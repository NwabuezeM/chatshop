<?php
/**
 * WhatsApp Payment Status Notifications
 * 
 * Handles all payment status change notifications via WhatsApp including
 * success, failure, pending, refunds, and custom status messages.
 * 
 * File: components/whatsapp/class-chatshop-whatsapp-payment-notifications.php
 *
 * @package ChatShop
 * @subpackage WhatsApp
 * @since 1.0.0
 */

namespace ChatShop\WhatsApp;

use ChatShop\WhatsApp\ChatShop_WhatsApp_API;
use ChatShop\WhatsApp\ChatShop_Message_Templates;
use ChatShop\Database\ChatShop_Payment_WhatsApp_Table;
use ChatShop\Helper\ChatShop_Helper;
use ChatShop\Logger\ChatShop_Logger;

defined( 'ABSPATH' ) || exit;

/**
 * WhatsApp Payment Status Notifications class
 */
class ChatShop_WhatsApp_Payment_Notifications {

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
     * Notification settings
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
        $this->db_table         = new ChatShop_Payment_WhatsApp_Table();
        $this->logger           = new ChatShop_Logger();
        
        $this->load_settings();
        $this->init_hooks();
    }

    /**
     * Load notification settings
     */
    private function load_settings() {
        $this->settings = array(
            'enabled'              => get_option( 'chatshop_whatsapp_notifications_enabled', 'yes' ),
            'success_enabled'      => get_option( 'chatshop_whatsapp_notify_success', 'yes' ),
            'failed_enabled'       => get_option( 'chatshop_whatsapp_notify_failed', 'yes' ),
            'pending_enabled'      => get_option( 'chatshop_whatsapp_notify_pending', 'no' ),
            'refund_enabled'       => get_option( 'chatshop_whatsapp_notify_refund', 'yes' ),
            'processing_enabled'   => get_option( 'chatshop_whatsapp_notify_processing', 'yes' ),
            'cancelled_enabled'    => get_option( 'chatshop_whatsapp_notify_cancelled', 'no' ),
            'include_receipt'      => get_option( 'chatshop_whatsapp_include_receipt', 'yes' ),
            'include_support_info' => get_option( 'chatshop_whatsapp_include_support', 'yes' ),
            'delay_seconds'        => intval( get_option( 'chatshop_whatsapp_notification_delay', 30 ) ),
        );
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        if ( $this->settings['enabled'] !== 'yes' ) {
            return;
        }

        // Payment status hooks
        add_action( 'woocommerce_payment_complete', array( $this, 'handle_payment_complete' ), 10, 1 );
        add_action( 'woocommerce_order_status_failed', array( $this, 'handle_payment_failed' ), 10, 2 );
        add_action( 'woocommerce_order_status_pending', array( $this, 'handle_payment_pending' ), 10, 2 );
        add_action( 'woocommerce_order_status_processing', array( $this, 'handle_payment_processing' ), 10, 2 );
        add_action( 'woocommerce_order_status_cancelled', array( $this, 'handle_payment_cancelled' ), 10, 2 );
        add_action( 'woocommerce_order_refunded', array( $this, 'handle_payment_refunded' ), 10, 2 );

        // Custom payment gateway hooks
        add_action( 'chatshop_payment_completed', array( $this, 'handle_custom_payment_complete' ), 10, 2 );
        add_action( 'chatshop_payment_failed', array( $this, 'handle_custom_payment_failed' ), 10, 2 );

        // Scheduled notification hooks
        add_action( 'chatshop_send_whatsapp_payment_notification', array( $this, 'send_scheduled_notification' ), 10, 3 );
    }

    /**
     * Handle payment completion
     *
     * @param int $order_id Order ID
     */
    public function handle_payment_complete( $order_id ) {
        if ( $this->settings['success_enabled'] !== 'yes' ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $this->schedule_notification( $order_id, 'payment_complete', array(
            'order' => $order,
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method_title(),
        ) );
    }

    /**
     * Handle payment failure
     *
     * @param int $order_id Order ID
     * @param WC_Order $order Order object
     */
    public function handle_payment_failed( $order_id, $order ) {
        if ( $this->settings['failed_enabled'] !== 'yes' ) {
            return;
        }

        $this->schedule_notification( $order_id, 'payment_failed', array(
            'order' => $order,
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'failure_reason' => $this->get_failure_reason( $order ),
        ) );
    }

    /**
     * Handle payment pending status
     *
     * @param int $order_id Order ID
     * @param WC_Order $order Order object
     */
    public function handle_payment_pending( $order_id, $order ) {
        if ( $this->settings['pending_enabled'] !== 'yes' ) {
            return;
        }

        $this->schedule_notification( $order_id, 'payment_pending', array(
            'order' => $order,
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method_title(),
        ) );
    }

    /**
     * Handle payment processing status
     *
     * @param int $order_id Order ID
     * @param WC_Order $order Order object
     */
    public function handle_payment_processing( $order_id, $order ) {
        if ( $this->settings['processing_enabled'] !== 'yes' ) {
            return;
        }

        $this->schedule_notification( $order_id, 'payment_processing', array(
            'order' => $order,
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'estimated_delivery' => $this->get_estimated_delivery( $order ),
        ) );
    }

    /**
     * Handle payment cancellation
     *
     * @param int $order_id Order ID
     * @param WC_Order $order Order object
     */
    public function handle_payment_cancelled( $order_id, $order ) {
        if ( $this->settings['cancelled_enabled'] !== 'yes' ) {
            return;
        }

        $this->schedule_notification( $order_id, 'payment_cancelled', array(
            'order' => $order,
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'cancellation_reason' => $this->get_cancellation_reason( $order ),
        ) );
    }

    /**
     * Handle payment refund
     *
     * @param int $order_id Order ID
     * @param int $refund_id Refund ID
     */
    public function handle_payment_refunded( $order_id, $refund_id ) {
        if ( $this->settings['refund_enabled'] !== 'yes' ) {
            return;
        }

        $order = wc_get_order( $order_id );
        $refund = wc_get_order( $refund_id );

        if ( ! $order || ! $refund ) {
            return;
        }

        $this->schedule_notification( $order_id, 'payment_refunded', array(
            'order' => $order,
            'refund' => $refund,
            'refund_amount' => abs( $refund->get_amount() ),
            'currency' => $order->get_currency(),
            'refund_reason' => $refund->get_reason(),
        ) );
    }

    /**
     * Schedule notification with delay
     *
     * @param int    $order_id Order ID
     * @param string $notification_type Notification type
     * @param array  $data Notification data
     */
    private function schedule_notification( $order_id, $notification_type, $data ) {
        $delay = $this->settings['delay_seconds'];
        
        wp_schedule_single_event(
            time() + $delay,
            'chatshop_send_whatsapp_payment_notification',
            array( $order_id, $notification_type, $data )
        );

        $this->logger->info( 'WhatsApp payment notification scheduled', array(
            'order_id' => $order_id,
            'type' => $notification_type,
            'delay' => $delay,
        ) );
    }

    /**
     * Send scheduled notification
     *
     * @param int    $order_id Order ID
     * @param string $notification_type Notification type
     * @param array  $data Notification data
     */
    public function send_scheduled_notification( $order_id, $notification_type, $data ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $phone_number = $this->get_order_phone_number( $order );
        if ( empty( $phone_number ) ) {
            $this->logger->warning( 'No phone number found for WhatsApp notification', array(
                'order_id' => $order_id,
                'type' => $notification_type,
            ) );
            return;
        }

        // Check if notification already sent
        if ( $this->is_notification_already_sent( $order_id, $notification_type ) ) {
            return;
        }

        $success = $this->send_notification( $order_id, $phone_number, $notification_type, $data );

        if ( $success ) {
            $this->mark_notification_sent( $order_id, $notification_type );
        }
    }

    /**
     * Send notification message
     *
     * @param int    $order_id Order ID
     * @param string $phone_number Customer phone number
     * @param string $notification_type Notification type
     * @param array  $data Notification data
     * @return bool Success status
     */
    private function send_notification( $order_id, $phone_number, $notification_type, $data ) {
        try {
            // Get message content
            $message = $this->generate_notification_message( $notification_type, $data );
            
            if ( empty( $message ) ) {
                $this->logger->error( 'Empty notification message generated', array(
                    'order_id' => $order_id,
                    'type' => $notification_type,
                ) );
                return false;
            }

            // Send message
            $result = $this->whatsapp_api->send_message( $phone_number, $message );

            if ( is_wp_error( $result ) ) {
                $this->logger->error( 'Failed to send WhatsApp payment notification', array(
                    'order_id' => $order_id,
                    'phone' => $phone_number,
                    'type' => $notification_type,
                    'error' => $result->get_error_message(),
                ) );
                return false;
            }

            // Log successful notification
            $this->db_table->insert_payment_message( array(
                'order_id' => $order_id,
                'phone_number' => $phone_number,
                'message_type' => $notification_type,
                'whatsapp_message_id' => $result['message_id'] ?? '',
                'status' => 'sent',
                'sent_at' => current_time( 'mysql' ),
                'content' => wp_json_encode( array(
                    'message' => $message,
                    'data' => $data,
                ) ),
            ) );

            // Send receipt if enabled
            if ( $this->settings['include_receipt'] === 'yes' && in_array( $notification_type, array( 'payment_complete', 'payment_processing' ) ) ) {
                $this->send_payment_receipt( $order_id, $phone_number, $data );
            }

            $this->logger->info( 'WhatsApp payment notification sent successfully', array(
                'order_id' => $order_id,
                'phone' => $phone_number,
                'type' => $notification_type,
                'message_id' => $result['message_id'] ?? '',
            ) );

            return true;

        } catch ( Exception $e ) {
            $this->logger->error( 'Exception in send_notification', array(
                'order_id' => $order_id,
                'type' => $notification_type,
                'error' => $e->getMessage(),
            ) );
            return false;
        }
    }

    /**
     * Generate notification message
     *
     * @param string $notification_type Notification type
     * @param array  $data Notification data
     * @return string Message content
     */
    private function generate_notification_message( $notification_type, $data ) {
        switch ( $notification_type ) {
            case 'payment_complete':
                return $this->message_templates->get_payment_success_message( $data );

            case 'payment_failed':
                return $this->message_templates->get_payment_failed_message( $data );

            case 'payment_pending':
                return $this->message_templates->get_payment_pending_message( $data );

            case 'payment_processing':
                return $this->message_templates->get_payment_processing_message( $data );

            case 'payment_cancelled':
                return $this->message_templates->get_payment_cancelled_message( $data );

            case 'payment_refunded':
                return $this->message_templates->get_payment_refunded_message( $data );

            default:
                return '';
        }
    }

    /**
     * Send payment receipt
     *
     * @param int    $order_id Order ID
     * @param string $phone_number Customer phone number
     * @param array  $data Payment data
     */
    private function send_payment_receipt( $order_id, $phone_number, $data ) {
        try {
            $order = $data['order'];
            
            // Generate receipt content
            $receipt = $this->generate_payment_receipt( $order );
            
            // Send as document or formatted message
            $receipt_format = get_option( 'chatshop_whatsapp_receipt_format', 'message' );
            
            if ( $receipt_format === 'document' ) {
                // Generate PDF receipt and send as document
                $pdf_path = $this->generate_pdf_receipt( $order );
                if ( $pdf_path ) {
                    $this->whatsapp_api->send_document( $phone_number, $pdf_path, 'Payment Receipt' );
                    unlink( $pdf_path ); // Clean up temporary file
                }
            } else {
                // Send as formatted text message
                $this->whatsapp_api->send_message( $phone_number, $receipt );
            }

        } catch ( Exception $e ) {
            $this->logger->error( 'Failed to send payment receipt', array(
                'order_id' => $order_id,
                'error' => $e->getMessage(),
            ) );
        }
    }

    /**
     * Generate payment receipt text
     *
     * @param WC_Order $order Order object
     * @return string Receipt content
     */
    private function generate_payment_receipt( $order ) {
        $receipt = "🧾 *PAYMENT RECEIPT*\n\n";
        $receipt .= "📋 Order: #{$order->get_order_number()}\n";
        $receipt .= "📅 Date: " . $order->get_date_created()->format( 'M j, Y g:i A' ) . "\n";
        $receipt .= "💳 Method: " . $order->get_payment_method_title() . "\n\n";

        $receipt .= "🛒 *ITEMS:*\n";
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $receipt .= "• {$item->get_name()} x{$item->get_quantity()} - " . wc_price( $item->get_total() ) . "\n";
        }

        $receipt .= "\n💰 *PAYMENT SUMMARY:*\n";
        $receipt .= "Subtotal: " . $order->get_subtotal_to_display() . "\n";
        
        if ( $order->get_total_tax() > 0 ) {
            $receipt .= "Tax: " . wc_price( $order->get_total_tax() ) . "\n";
        }
        
        if ( $order->get_shipping_total() > 0 ) {
            $receipt .= "Shipping: " . wc_price( $order->get_shipping_total() ) . "\n";
        }
        
        $receipt .= "*Total Paid: " . $order->get_formatted_order_total() . "*\n\n";

        // Add support information if enabled
        if ( $this->settings['include_support_info'] === 'yes' ) {
            $receipt .= "❓ *Need Help?*\n";
            $receipt .= "Reply to this message or contact us:\n";
            $receipt .= "📧 " . get_option( 'admin_email' ) . "\n";
            $receipt .= "🌐 " . home_url() . "\n\n";
        }

        $receipt .= "Thank you for your business! 🙏";

        return $receipt;
    }

    /**
     * Generate PDF receipt
     *
     * @param WC_Order $order Order object
     * @return string|false PDF file path or false on failure
     */
    private function generate_pdf_receipt( $order ) {
        // This would integrate with a PDF generation library
        // For now, return false to use text receipt
        return false;
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
     * Check if notification already sent
     *
     * @param int    $order_id Order ID
     * @param string $notification_type Notification type
     * @return bool True if already sent
     */
    private function is_notification_already_sent( $order_id, $notification_type ) {
        return $this->db_table->notification_exists( $order_id, $notification_type );
    }

    /**
     * Mark notification as sent
     *
     * @param int    $order_id Order ID
     * @param string $notification_type Notification type
     */
    private function mark_notification_sent( $order_id, $notification_type ) {
        update_post_meta( $order_id, "_chatshop_whatsapp_notification_{$notification_type}", current_time( 'mysql' ) );
    }

    /**
     * Get failure reason from order
     *
     * @param WC_Order $order Order object
     * @return string Failure reason
     */
    private function get_failure_reason( $order ) {
        $failure_reason = $order->get_meta( '_payment_failure_reason', true );
        
        if ( empty( $failure_reason ) ) {
            $failure_reason = __( 'Payment could not be processed', 'chatshop' );
        }

        return $failure_reason;
    }

    /**
     * Get cancellation reason from order
     *
     * @param WC_Order $order Order object
     * @return string Cancellation reason
     */
    private function get_cancellation_reason( $order ) {
        $cancellation_reason = $order->get_meta( '_cancellation_reason', true );
        
        if ( empty( $cancellation_reason ) ) {
            $cancellation_reason = __( 'Order was cancelled', 'chatshop' );
        }

        return $cancellation_reason;
    }

    /**
     * Get estimated delivery date
     *
     * @param WC_Order $order Order object
     * @return string Estimated delivery
     */
    private function get_estimated_delivery( $order ) {
        $estimated_delivery = $order->get_meta( '_estimated_delivery', true );
        
        if ( empty( $estimated_delivery ) ) {
            // Calculate based on shipping method or default
            $shipping_methods = $order->get_shipping_methods();
            $estimated_days = 3; // Default
            
            foreach ( $shipping_methods as $shipping_method ) {
                $method_id = $shipping_method->get_method_id();
                
                // Customize based on shipping method
                switch ( $method_id ) {
                    case 'free_shipping':
                        $estimated_days = 5;
                        break;
                    case 'flat_rate':
                        $estimated_days = 3;
                        break;
                    case 'local_pickup':
                        $estimated_days = 0;
                        break;
                }
                break;
            }
            
            if ( $estimated_days > 0 ) {
                $estimated_delivery = date( 'M j, Y', strtotime( "+{$estimated_days} days" ) );
            } else {
                $estimated_delivery = __( 'Ready for pickup', 'chatshop' );
            }
        }

        return $estimated_delivery;
    }

    /**
     * Send custom notification
     *
     * @param int    $order_id Order ID
     * @param string $message Custom message
     * @param array  $options Additional options
     * @return bool|WP_Error Success status or error
     */
    public function send_custom_notification( $order_id, $message, $options = array() ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'invalid_order', __( 'Order not found.', 'chatshop' ) );
        }

        $phone_number = $this->get_order_phone_number( $order );
        if ( empty( $phone_number ) ) {
            return new WP_Error( 'no_phone', __( 'No phone number found for this order.', 'chatshop' ) );
        }

        // Send message
        $result = $this->whatsapp_api->send_message( $phone_number, $message );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Log custom notification
        $this->db_table->insert_payment_message( array(
            'order_id' => $order_id,
            'phone_number' => $phone_number,
            'message_type' => 'custom_notification',
            'whatsapp_message_id' => $result['message_id'] ?? '',
            'status' => 'sent',
            'sent_at' => current_time( 'mysql' ),
            'content' => wp_json_encode( array(
                'message' => $message,
                'options' => $options,
            ) ),
        ) );

        return true;
    }

    /**
     * Get notification history for order
     *
     * @param int $order_id Order ID
     * @return array Notification history
     */
    public function get_notification_history( $order_id ) {
        return $this->db_table->get_order_notifications( $order_id );
    }

    /**
     * Resend failed notifications
     *
     * @param int $order_id Order ID
     * @return array Results of resend attempts
     */
    public function resend_failed_notifications( $order_id ) {
        $failed_notifications = $this->db_table->get_failed_notifications( $order_id );
        $results = array();

        foreach ( $failed_notifications as $notification ) {
            $content = json_decode( $notification['content'], true );
            
            if ( ! empty( $content['message'] ) ) {
                $result = $this->whatsapp_api->send_message( 
                    $notification['phone_number'], 
                    $content['message'] 
                );

                if ( ! is_wp_error( $result ) ) {
                    // Update notification status
                    $this->db_table->update_notification_status( 
                        $notification['id'], 
                        'sent',
                        $result['message_id'] ?? ''
                    );
                    $results[] = array( 'id' => $notification['id'], 'status' => 'success' );
                } else {
                    $results[] = array( 
                        'id' => $notification['id'], 
                        'status' => 'failed',
                        'error' => $result->get_error_message()
                    );
                }
            }
        }

        return $results;
    }

    /**
     * Bulk send notifications to multiple orders
     *
     * @param array  $order_ids Array of order IDs
     * @param string $message Message to send
     * @param array  $options Additional options
     * @return array Results array
     */
    public function bulk_send_notifications( $order_ids, $message, $options = array() ) {
        $results = array();
        
        foreach ( $order_ids as $order_id ) {
            $result = $this->send_custom_notification( $order_id, $message, $options );
            
            $results[$order_id] = array(
                'status' => is_wp_error( $result ) ? 'failed' : 'success',
                'error' => is_wp_error( $result ) ? $result->get_error_message() : null,
            );

            // Add delay between messages to avoid rate limiting
            if ( ! empty( $options['delay_between_messages'] ) ) {
                sleep( $options['delay_between_messages'] );
            }
        }

        return $results;
    }

    /**
     * Get notification statistics
     *
     * @param array $args Query arguments
     * @return array Statistics data
     */
    public function get_notification_statistics( $args = array() ) {
        $default_args = array(
            'date_from' => date( 'Y-m-d', strtotime( '-30 days' ) ),
            'date_to'   => date( 'Y-m-d' ),
        );

        $args = wp_parse_args( $args, $default_args );

        return $this->db_table->get_notification_statistics( $args );
    }
}