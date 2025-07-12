<?php
/**
 * Payment Link Generator
 *
 * @package ChatShop
 * @subpackage Components/Payment
 * @since 1.0.0
 */

namespace ChatShop\Components\Payment;

use ChatShop\Includes\ChatShop_Logger;
use ChatShop\Includes\ChatShop_Security;
use ChatShop\Includes\ChatShop_Cache;
use ChatShop\Components\Payment\Database\ChatShop_Payment_Links_Table;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Payment Link Generator Class
 *
 * Handles creation and management of payment links
 *
 * @since 1.0.0
 */
class ChatShop_Payment_Link_Generator {
    
    /**
     * Logger instance
     *
     * @var ChatShop_Logger
     */
    private $logger;
    
    /**
     * Security instance
     *
     * @var ChatShop_Security
     */
    private $security;
    
    /**
     * Cache instance
     *
     * @var ChatShop_Cache
     */
    private $cache;
    
    /**
     * Payment factory
     *
     * @var ChatShop_Payment_Factory
     */
    private $factory;
    
    /**
     * Payment links table
     *
     * @var ChatShop_Payment_Links_Table
     */
    private $links_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new ChatShop_Logger();
        $this->security = new ChatShop_Security();
        $this->cache = new ChatShop_Cache();
        $this->factory = ChatShop_Payment_Factory::get_instance();
        $this->links_table = new ChatShop_Payment_Links_Table();
    }
    
    /**
     * Initialize
     *
     * @return void
     */
    public function init() {
        // Register routes
        add_action('init', array($this, 'register_link_routes'));
        add_action('template_redirect', array($this, 'handle_payment_link'));
        
        // Cleanup expired links
        add_action('chatshop_daily_cleanup', array($this, 'cleanup_expired_links'));
    }
    
    /**
     * Generate payment link
     *
     * @param string $gateway_id Gateway to use
     * @param array  $link_data  Link configuration
     * @return string|WP_Error  Payment link URL or error
     */
    public function generate_link($gateway_id, $link_data) {
        // Validate gateway
        $gateway = $this->factory->create_gateway($gateway_id);
        if (!$gateway) {
            return new \WP_Error(
                'invalid_gateway',
                __('Invalid payment gateway specified', 'chatshop')
            );
        }
        
        // Check if gateway supports payment links
        if (!$gateway->supports('payment_links')) {
            return new \WP_Error(
                'feature_not_supported',
                __('This gateway does not support payment links', 'chatshop')
            );
        }
        
        // Validate link data
        $validation = $this->validate_link_data($link_data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Generate unique link ID
        $link_id = $this->generate_link_id();
        
        // Prepare link metadata
        $metadata = array_merge($link_data, array(
            'link_id'     => $link_id,
            'gateway_id'  => $gateway_id,
            'created_at'  => current_time('mysql'),
            'created_by'  => get_current_user_id(),
            'status'      => 'active',
        ));
        
        try {
            // Generate gateway-specific payment link
            $gateway_link = $gateway->generate_payment_link(
                $link_data['amount'],
                $link_data['currency'],
                $metadata
            );
            
            if (!$gateway_link) {
                throw new \Exception(__('Failed to generate gateway payment link', 'chatshop'));
            }
            
            // Create link record
            $link_record = array(
                'link_id'         => $link_id,
                'gateway_id'      => $gateway_id,
                'amount'          => $link_data['amount'],
                'currency'        => $link_data['currency'],
                'description'     => isset($link_data['description']) ? $link_data['description'] : '',
                'metadata'        => $metadata,
                'gateway_link'    => $gateway_link,
                'status'          => 'active',
                'expires_at'      => $this->calculate_expiry($link_data),
                'max_uses'        => isset($link_data['max_uses']) ? absint($link_data['max_uses']) : null,
                'use_count'       => 0,
                'whatsapp_number' => isset($link_data['whatsapp_number']) ? $link_data['whatsapp_number'] : '',
            );
            
            // Save to database
            $saved = $this->links_table->create($link_record);
            
            if (!$saved) {
                throw new \Exception(__('Failed to save payment link', 'chatshop'));
            }
            
            // Generate shareable link
            $shareable_link = $this->generate_shareable_link($link_id);
            
            // Fire event
            do_action('chatshop_payment_link_created', $link_id, $gateway_id, $link_data);
            
            $this->logger->log(
                'Payment link created',
                'info',
                'payment',
                array(
                    'link_id'    => $link_id,
                    'gateway_id' => $gateway_id,
                    'amount'     => $link_data['amount'],
                    'currency'   => $link_data['currency'],
                )
            );
            
            return array(
                'link_id'        => $link_id,
                'payment_url'    => $shareable_link,
                'gateway_url'    => $gateway_link,
                'expires_at'     => $link_record['expires_at'],
                'whatsapp_share' => $this->generate_whatsapp_share_link($shareable_link, $link_data),
            );
            
        } catch (\Exception $e) {
            $this->logger->log(
                'Payment link generation failed',
                'error',
                'payment',
                array(
                    'gateway_id' => $gateway_id,
                    'error'      => $e->getMessage(),
                )
            );
            
            return new \WP_Error(
                'link_generation_failed',
                $e->getMessage()
            );
        }
    }
    
    /**
     * Get payment link
     *
     * @param string $link_id Link identifier
     * @return array|null
     */
    public function get_link($link_id) {
        $link = $this->links_table->get_by_link_id($link_id);
        
        if (!$link) {
            return null;
        }
        
        // Check if link is valid
        if (!$this->is_link_valid($link)) {
            return null;
        }
        
        return $link;
    }
    
    /**
     * Update payment link
     *
     * @param string $link_id Link identifier
     * @param array  $data    Update data
     * @return bool
     */
    public function update_link($link_id, $data) {
        $link = $this->get_link($link_id);
        
        if (!$link) {
            return false;
        }
        
        // Sanitize update data
        $allowed_fields = array('status', 'description', 'metadata', 'max_uses');
        $update_data = array_intersect_key($data, array_flip($allowed_fields));
        
        if (empty($update_data)) {
            return false;
        }
        
        $updated = $this->links_table->update($link['id'], $update_data);
        
        if ($updated) {
            // Clear cache
            $this->cache->delete('payment_link_' . $link_id);
            
            // Fire event
            do_action('chatshop_payment_link_updated', $link_id, $update_data);
        }
        
        return $updated;
    }
    
    /**
     * Deactivate payment link
     *
     * @param string $link_id Link identifier
     * @return bool
     */
    public function deactivate_link($link_id) {
        return $this->update_link($link_id, array('status' => 'inactive'));
    }
    
    /**
     * Validate link data
     *
     * @param array $data Link data
     * @return bool|WP_Error
     */
    private function validate_link_data($data) {
        // Required fields
        $required = array('amount', 'currency');
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return new \WP_Error(
                    'missing_field',
                    sprintf(__('Required field missing: %s', 'chatshop'), $field)
                );
            }
        }
        
        // Validate amount
        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            return new \WP_Error(
                'invalid_amount',
                __('Invalid payment amount', 'chatshop')
            );
        }
        
        // Validate currency
        if (!preg_match('/^[A-Z]{3}$/', $data['currency'])) {
            return new \WP_Error(
                'invalid_currency',
                __('Invalid currency code', 'chatshop')
            );
        }
        
        // Validate expiry if set
        if (isset($data['expires_in']) && (!is_numeric($data['expires_in']) || $data['expires_in'] < 0)) {
            return new \WP_Error(
                'invalid_expiry',
                __('Invalid expiry duration', 'chatshop')
            );
        }
        
        return true;
    }
    
    /**
     * Generate unique link ID
     *
     * @return string
     */
    private function generate_link_id() {
        do {
            $link_id = 'CSL_' . strtoupper(wp_generate_password(12, false));
        } while ($this->links_table->get_by_link_id($link_id));
        
        return $link_id;
    }
    
    /**
     * Calculate link expiry
     *
     * @param array $link_data Link configuration
     * @return string|null
     */
    private function calculate_expiry($link_data) {
        if (!isset($link_data['expires_in'])) {
            return null;
        }
        
        $expires_in = absint($link_data['expires_in']);
        
        if ($expires_in === 0) {
            return null;
        }
        
        return date('Y-m-d H:i:s', time() + $expires_in);
    }
    
    /**
     * Generate shareable link
     *
     * @param string $link_id Link identifier
     * @return string
     */
    private function generate_shareable_link($link_id) {
        return home_url('chatshop-pay/' . $link_id);
    }
    
    /**
     * Generate WhatsApp share link
     *
     * @param string $payment_url Payment URL
     * @param array  $link_data   Link data
     * @return string
     */
    private function generate_whatsapp_share_link($payment_url, $link_data) {
        $message = sprintf(
            __('Payment Request: %s %s', 'chatshop'),
            $link_data['currency'],
            number_format($link_data['amount'], 2)
        );
        
        if (isset($link_data['description'])) {
            $message .= "\n" . $link_data['description'];
        }
        
        $message .= "\n\n" . __('Click to pay:', 'chatshop') . ' ' . $payment_url;
        
        return 'https://wa.me/?text=' . urlencode($message);
    }
    
    /**
     * Check if link is valid
     *
     * @param array $link Link record
     * @return bool
     */
    private function is_link_valid($link) {
        // Check status
        if ($link['status'] !== 'active') {
            return false;
        }
        
        // Check expiry
        if ($link['expires_at'] && strtotime($link['expires_at']) < time()) {
            // Update status
            $this->links_table->update($link['id'], array('status' => 'expired'));
            return false;
        }
        
        // Check usage limit
        if ($link['max_uses'] && $link['use_count'] >= $link['max_uses']) {
            // Update status
            $this->links_table->update($link['id'], array('status' => 'exhausted'));
            return false;
        }
        
        return true;
    }
    
    /**
     * Register link routes
     *
     * @return void
     */
    public function register_link_routes() {
        add_rewrite_rule(
            '^chatshop-pay/([A-Z0-9_]+)/?,
            'index.php?chatshop_payment_link=$matches[1]',
            'top'
        );
        
        add_filter('query_vars', function($vars) {
            $vars[] = 'chatshop_payment_link';
            return $vars;
        });
    }
    
    /**
     * Handle payment link request
     *
     * @return void
     */
    public function handle_payment_link() {
        $link_id = get_query_var('chatshop_payment_link');
        
        if (!$link_id) {
            return;
        }
        
        // Get link
        $link = $this->get_link($link_id);
        
        if (!$link) {
            wp_die(__('Invalid or expired payment link', 'chatshop'), 404);
        }
        
        // Increment use count
        $this->links_table->increment_use_count($link['id']);
        
        // Track access
        do_action('chatshop_payment_link_accessed', $link_id);
        
        // Load payment template or redirect
        if (isset($link['gateway_link'])) {
            wp_redirect($link['gateway_link']);
            exit;
        }
        
        // Load custom payment template
        $this->load_payment_template($link);
    }
    
    /**
     * Load payment template
     *
     * @param array $link Link data
     * @return void
     */
    private function load_payment_template($link) {
        // Set up template data
        $template_data = array(
            'link'      => $link,
            'gateway'   => $this->factory->create_gateway($link['gateway_id']),
            'amount'    => $link['amount'],
            'currency'  => $link['currency'],
            'metadata'  => $link['metadata'],
        );
        
        // Load template
        $template = locate_template('chatshop/payment-link.php');
        
        if (!$template) {
            $template = CHATSHOP_PLUGIN_DIR . 'public/partials/payment-link.php';
        }
        
        // Extract data and include template
        extract($template_data);
        include $template;
        exit;
    }
    
    /**
     * Cleanup expired links
     *
     * @return void
     */
    public function cleanup_expired_links() {
        $expired = $this->links_table->get_expired_links();
        
        foreach ($expired as $link) {
            $this->links_table->update($link['id'], array('status' => 'expired'));
        }
        
        $this->logger->log(
            sprintf('Cleaned up %d expired payment links', count($expired)),
            'info',
            'payment'
        );
    }
    
    /**
     * Get link statistics
     *
     * @param string $link_id Link identifier
     * @return array
     */
    public function get_link_stats($link_id) {
        $link = $this->get_link($link_id);
        
        if (!$link) {
            return array();
        }
        
        return array(
            'created_at'    => $link['created_at'],
            'expires_at'    => $link['expires_at'],
            'use_count'     => $link['use_count'],
            'max_uses'      => $link['max_uses'],
            'status'        => $link['status'],
            'amount'        => $link['amount'],
            'currency'      => $link['currency'],
            'conversions'   => $this->get_link_conversions($link_id),
        );
    }
    
    /**
     * Get link conversions
     *
     * @param string $link_id Link identifier
     * @return array
     */
    private function get_link_conversions($link_id) {
        // This would query transaction data
        return apply_filters('chatshop_payment_link_conversions', array(), $link_id);
    }
}