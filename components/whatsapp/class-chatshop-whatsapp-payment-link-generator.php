<?php
/**
 * WhatsApp Payment Link Generator
 * 
 * Generates and manages payment links specifically for WhatsApp sharing
 * with tracking, expiration, and WhatsApp-optimized features.
 * 
 * File: components/whatsapp/class-chatshop-whatsapp-payment-link-generator.php
 *
 * @package ChatShop
 * @subpackage WhatsApp
 * @since 1.0.0
 */

namespace ChatShop\WhatsApp;

use ChatShop\Payment\ChatShop_Payment_Link_Generator;
use ChatShop\Helper\ChatShop_Helper;
use ChatShop\Logger\ChatShop_Logger;
use ChatShop\Security\ChatShop_Security;

defined( 'ABSPATH' ) || exit;

/**
 * WhatsApp Payment Link Generator class
 */
class ChatShop_WhatsApp_Payment_Link_Generator {

    /**
     * Payment link generator instance
     *
     * @var ChatShop_Payment_Link_Generator
     */
    private $payment_link_generator;

    /**
     * Logger instance
     *
     * @var ChatShop_Logger
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->payment_link_generator = new ChatShop_Payment_Link_Generator();
        $this->logger                 = new ChatShop_Logger();
    }

    /**
     * Generate WhatsApp-optimized payment link
     *
     * @param int   $order_id Order ID
     * @param array $options Link generation options
     * @return array|WP_Error Payment link data or error
     */
    public function generate_whatsapp_payment_link( $order_id, $options = array() ) {
        try {
            // Validate order
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return new WP_Error( 'invalid_order', __( 'Order not found.', 'chatshop' ) );
            }

            // Default WhatsApp-specific options
            $default_options = array(
                'source'           => 'whatsapp',
                'expires_in'       => 24 * 3600, // 24 hours
                'mobile_optimized' => true,
                'track_whatsapp'   => true,
                'currency'         => $order->get_currency(),
                'customer_phone'   => $order->get_billing_phone(),
                'utm_source'       => 'whatsapp',
                'utm_medium'       => 'social',
                'utm_campaign'     => 'payment_link',
            );

            $options = wp_parse_args( $options, $default_options );

            // Add WhatsApp-specific metadata
            $options['metadata'] = array_merge(
                $options['metadata'] ?? array(),
                array(
                    'whatsapp_phone'     => $options['customer_phone'],
                    'whatsapp_generated' => current_time( 'mysql' ),
                    'user_agent'         => 'WhatsApp',
                    'device_type'        => 'mobile',
                )
            );

            // Generate the base payment link
            $payment_link = $this->payment_link_generator->generate_payment_link( $order_id, $options );

            if ( is_wp_error( $payment_link ) ) {
                return $payment_link;
            }

            // Enhance for WhatsApp
            $payment_link = $this->enhance_for_whatsapp( $payment_link, $order, $options );

            // Store WhatsApp-specific tracking data
            $this->store_whatsapp_link_data( $payment_link['id'], $order_id, $options );

            $this->logger->info( 'WhatsApp payment link generated', array(
                'link_id'  => $payment_link['id'],
                'order_id' => $order_id,
                'phone'    => $options['customer_phone'],
            ) );

            return $payment_link;

        } catch ( Exception $e ) {
            $this->logger->error( 'Failed to generate WhatsApp payment link', array(
                'order_id' => $order_id,
                'error'    => $e->getMessage(),
            ) );
            return new WP_Error( 'generation_failed', $e->getMessage() );
        }
    }

    /**
     * Enhance payment link for WhatsApp sharing
     *
     * @param array    $payment_link Payment link data
     * @param WC_Order $order Order object
     * @param array    $options Generation options
     * @return array Enhanced payment link data
     */
    private function enhance_for_whatsapp( $payment_link, $order, $options ) {
        // Add WhatsApp-specific URL parameters
        $whatsapp_params = array(
            'utm_source'   => 'whatsapp',
            'utm_medium'   => 'social',
            'utm_campaign' => 'payment_link',
            'ref'          => 'whatsapp',
        );

        if ( ! empty( $options['customer_phone'] ) ) {
            $whatsapp_params['phone'] = ChatShop_Security::hash_phone_number( $options['customer_phone'] );
        }

        $enhanced_url = add_query_arg( $whatsapp_params, $payment_link['url'] );

        // Create shortened URL for WhatsApp sharing
        $short_url = $this->create_short_url( $enhanced_url, $payment_link['id'] );

        // Add WhatsApp sharing metadata
        $payment_link['whatsapp'] = array(
            'short_url'       => $short_url,
            'share_text'      => $this->generate_share_text( $order, $short_url ),
            'direct_share_url' => $this->generate_whatsapp_share_url( $options['customer_phone'], $short_url, $order ),
            'qr_code_url'     => $this->generate_qr_code_url( $short_url ),
        );

        // Add mobile-optimized metadata
        $payment_link['mobile'] = array(
            'optimized'     => true,
            'viewport'      => 'width=device-width, initial-scale=1.0',
            'touch_icon'    => get_option( 'chatshop_payment_touch_icon_url' ),
            'apple_capable' => 'yes',
        );

        return $payment_link;
    }

    /**
     * Create shortened URL for WhatsApp sharing
     *
     * @param string $url Original URL
     * @param string $link_id Payment link ID
     * @return string Shortened URL
     */
    private function create_short_url( $url, $link_id ) {
        // Create a short URL using the link ID
        $short_code = ChatShop_Helper::generate_short_code( $link_id );
        $short_url  = home_url( "/pay/{$short_code}" );

        // Store the mapping
        update_option( "chatshop_short_url_{$short_code}", array(
            'url'     => $url,
            'link_id' => $link_id,
            'created' => current_time( 'mysql' ),
            'expires' => date( 'Y-m-d H:i:s', time() + 24 * 3600 ),
        ) );

        return $short_url;
    }

    /**
     * Generate WhatsApp share text
     *
     * @param WC_Order $order Order object
     * @param string   $payment_url Payment URL
     * @return string Share text
     */
    private function generate_share_text( $order, $payment_url ) {
        $template = get_option( 'chatshop_whatsapp_share_template', '' );
        
        if ( empty( $template ) ) {
            $template = "🛒 *{shop_name}*\n\n" .
                       "Hi {customer_name},\n\n" .
                       "Your order #{order_number} is ready for payment!\n\n" .
                       "💰 *Total: {order_total}*\n\n" .
                       "Click here to pay securely:\n{payment_url}\n\n" .
                       "Need help? Just reply to this message!";
        }

        $replacements = array(
            '{shop_name}'     => get_bloginfo( 'name' ),
            '{customer_name}' => $order->get_billing_first_name(),
            '{order_number}'  => $order->get_order_number(),
            '{order_total}'   => $order->get_formatted_order_total(),
            '{payment_url}'   => $payment_url,
            '{order_date}'    => $order->get_date_created()->format( 'M j, Y' ),
        );

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
    }

    /**
     * Generate WhatsApp direct share URL
     *
     * @param string   $phone_number Customer phone number
     * @param string   $payment_url Payment URL
     * @param WC_Order $order Order object
     * @return string WhatsApp share URL
     */
    private function generate_whatsapp_share_url( $phone_number, $payment_url, $order ) {
        $share_text = $this->generate_share_text( $order, $payment_url );
        $encoded_text = urlencode( $share_text );
        
        // Clean phone number for WhatsApp
        $clean_phone = preg_replace( '/[^0-9]/', '', $phone_number );
        
        return "https://wa.me/{$clean_phone}?text={$encoded_text}";
    }

    /**
     * Generate QR code URL for payment link
     *
     * @param string $payment_url Payment URL
     * @return string QR code URL
     */
    private function generate_qr_code_url( $payment_url ) {
        // Use Google Charts API for QR code generation
        $qr_data = urlencode( $payment_url );
        $size = '200x200';
        
        return "https://chart.googleapis.com/chart?chs={$size}&cht=qr&chl={$qr_data}";
    }

    /**
     * Store WhatsApp-specific link data
     *
     * @param string $link_id Payment link ID
     * @param int    $order_id Order ID
     * @param array  $options Generation options
     */
    private function store_whatsapp_link_data( $link_id, $order_id, $options ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_whatsapp_payment_links';

        $wpdb->insert(
            $table_name,
            array(
                'link_id'         => $link_id,
                'order_id'        => $order_id,
                'customer_phone'  => $options['customer_phone'] ?? '',
                'source'          => $options['source'] ?? 'whatsapp',
                'utm_campaign'    => $options['utm_campaign'] ?? '',
                'generated_at'    => current_time( 'mysql' ),
                'expires_at'      => date( 'Y-m-d H:i:s', time() + $options['expires_in'] ),
                'status'          => 'active',
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Get WhatsApp payment link analytics
     *
     * @param string $link_id Payment link ID
     * @return array Analytics data
     */
    public function get_whatsapp_link_analytics( $link_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_whatsapp_payment_links';
        
        $link_data = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE link_id = %s",
            $link_id
        ), ARRAY_A );

        if ( ! $link_data ) {
            return array();
        }

        // Get click analytics
        $clicks = $this->get_link_clicks( $link_id );
        
        // Get conversion data
        $order = wc_get_order( $link_data['order_id'] );
        $is_paid = $order && $order->is_paid();

        return array(
            'link_id'       => $link_id,
            'order_id'      => $link_data['order_id'],
            'phone'         => $link_data['customer_phone'],
            'generated_at'  => $link_data['generated_at'],
            'expires_at'    => $link_data['expires_at'],
            'status'        => $link_data['status'],
            'total_clicks'  => count( $clicks ),
            'unique_clicks' => $this->count_unique_clicks( $clicks ),
            'converted'     => $is_paid,
            'conversion_rate' => count( $clicks ) > 0 ? ( $is_paid ? 100 : 0 ) : 0,
            'last_clicked'  => $this->get_last_click_time( $clicks ),
            'clicks_by_hour' => $this->group_clicks_by_hour( $clicks ),
        );
    }

    /**
     * Get link clicks data
     *
     * @param string $link_id Payment link ID
     * @return array Clicks data
     */
    private function get_link_clicks( $link_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_payment_link_clicks';
        
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE link_id = %s ORDER BY clicked_at DESC",
            $link_id
        ), ARRAY_A );
    }

    /**
     * Count unique clicks by IP/user agent
     *
     * @param array $clicks Clicks data
     * @return int Unique clicks count
     */
    private function count_unique_clicks( $clicks ) {
        $unique = array();
        
        foreach ( $clicks as $click ) {
            $key = md5( $click['ip_address'] . $click['user_agent'] );
            $unique[$key] = true;
        }
        
        return count( $unique );
    }

    /**
     * Get last click time
     *
     * @param array $clicks Clicks data
     * @return string|null Last click time
     */
    private function get_last_click_time( $clicks ) {
        return ! empty( $clicks ) ? $clicks[0]['clicked_at'] : null;
    }

    /**
     * Group clicks by hour for analytics
     *
     * @param array $clicks Clicks data
     * @return array Clicks grouped by hour
     */
    private function group_clicks_by_hour( $clicks ) {
        $grouped = array();
        
        foreach ( $clicks as $click ) {
            $hour = date( 'H', strtotime( $click['clicked_at'] ) );
            $grouped[$hour] = ( $grouped[$hour] ?? 0 ) + 1;
        }
        
        // Fill missing hours with 0
        for ( $i = 0; $i < 24; $i++ ) {
            $hour = sprintf( '%02d', $i );
            if ( ! isset( $grouped[$hour] ) ) {
                $grouped[$hour] = 0;
            }
        }
        
        ksort( $grouped );
        return $grouped;
    }

    /**
     * Validate WhatsApp payment link
     *
     * @param string $link_id Payment link ID
     * @return bool|WP_Error True if valid, WP_Error otherwise
     */
    public function validate_whatsapp_link( $link_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_whatsapp_payment_links';
        
        $link_data = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE link_id = %s",
            $link_id
        ), ARRAY_A );

        if ( ! $link_data ) {
            return new WP_Error( 'link_not_found', __( 'Payment link not found.', 'chatshop' ) );
        }

        // Check expiration
        if ( strtotime( $link_data['expires_at'] ) < time() ) {
            return new WP_Error( 'link_expired', __( 'Payment link has expired.', 'chatshop' ) );
        }

        // Check status
        if ( $link_data['status'] !== 'active' ) {
            return new WP_Error( 'link_inactive', __( 'Payment link is not active.', 'chatshop' ) );
        }

        // Check if order still needs payment
        $order = wc_get_order( $link_data['order_id'] );
        if ( ! $order ) {
            return new WP_Error( 'order_not_found', __( 'Order not found.', 'chatshop' ) );
        }

        if ( $order->is_paid() ) {
            return new WP_Error( 'order_already_paid', __( 'Order has already been paid.', 'chatshop' ) );
        }

        return true;
    }

    /**
     * Track WhatsApp link click
     *
     * @param string $link_id Payment link ID
     * @param array  $click_data Click tracking data
     */
    public function track_whatsapp_link_click( $link_id, $click_data = array() ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_payment_link_clicks';

        $default_data = array(
            'ip_address'  => ChatShop_Helper::get_client_ip(),
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referrer'    => $_SERVER['HTTP_REFERER'] ?? '',
            'utm_source'  => $_GET['utm_source'] ?? '',
            'utm_medium'  => $_GET['utm_medium'] ?? '',
            'utm_campaign' => $_GET['utm_campaign'] ?? '',
        );

        $click_data = wp_parse_args( $click_data, $default_data );

        $wpdb->insert(
            $table_name,
            array(
                'link_id'     => $link_id,
                'ip_address'  => $click_data['ip_address'],
                'user_agent'  => $click_data['user_agent'],
                'referrer'    => $click_data['referrer'],
                'utm_source'  => $click_data['utm_source'],
                'utm_medium'  => $click_data['utm_medium'],
                'utm_campaign' => $click_data['utm_campaign'],
                'clicked_at'  => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Expire WhatsApp payment link
     *
     * @param string $link_id Payment link ID
     * @return bool Success status
     */
    public function expire_whatsapp_link( $link_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_whatsapp_payment_links';

        $result = $wpdb->update(
            $table_name,
            array( 'status' => 'expired' ),
            array( 'link_id' => $link_id ),
            array( '%s' ),
            array( '%s' )
        );

        return $result !== false;
    }

    /**
     * Cleanup expired WhatsApp payment links
     *
     * @return int Number of links cleaned up
     */
    public function cleanup_expired_links() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chatshop_whatsapp_payment_links';

        $result = $wpdb->update(
            $table_name,
            array( 'status' => 'expired' ),
            array(
                'expires_at' => array(
                    'value' => current_time( 'mysql' ),
                    'compare' => '<'
                ),
                'status' => 'active'
            ),
            array( '%s' ),
            array( '%s', '%s' )
        );

        return $result ?: 0;
    }
}