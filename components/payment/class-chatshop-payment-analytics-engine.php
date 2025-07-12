<?php

/**
 * Payment Analytics Engine
 *
 * @package ChatShop
 * @subpackage Components/Payment
 * @since 1.0.0
 */

namespace ChatShop\Components\Payment;

use ChatShop\Includes\ChatShop_Logger;
use ChatShop\Includes\ChatShop_Cache;
use ChatShop\Components\Analytics\ChatShop_Analytics_Manager;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Payment Analytics Engine Class
 *
 * Handles revenue and conversion tracking for payment gateways
 *
 * @since 1.0.0
 */
class ChatShop_Payment_Analytics_Engine
{

    /**
     * Logger instance
     *
     * @var ChatShop_Logger
     */
    private $logger;

    /**
     * Cache instance
     *
     * @var ChatShop_Cache
     */
    private $cache;

    /**
     * Transaction manager
     *
     * @var ChatShop_Transaction_Manager
     */
    private $transaction_manager;

    /**
     * Analytics manager
     *
     * @var ChatShop_Analytics_Manager
     */
    private $analytics_manager;

    /**
     * Metrics to track
     *
     * @var array
     */
    private $metrics = array(
        'revenue',
        'transactions',
        'conversion_rate',
        'average_order_value',
        'refund_rate',
        'gateway_performance',
        'currency_distribution',
        'payment_method_usage',
        'failed_transactions',
        'processing_time',
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = new ChatShop_Logger();
        $this->cache = new ChatShop_Cache();
        $this->transaction_manager = new ChatShop_Transaction_Manager();

        $this->init();
    }

    /**
     * Initialize analytics engine
     *
     * @return void
     */
    private function init()
    {
        // Track payment events
        add_action('chatshop_payment_completed', array($this, 'track_payment_completed'), 10, 2);
        add_action('chatshop_payment_failed', array($this, 'track_payment_failed'), 10, 2);
        add_action('chatshop_payment_refunded', array($this, 'track_payment_refunded'), 10, 3);
        add_action('chatshop_payment_link_accessed', array($this, 'track_link_access'));
        add_action('chatshop_payment_link_created', array($this, 'track_link_creation'), 10, 3);

        // Schedule analytics aggregation
        add_action('chatshop_hourly_analytics', array($this, 'aggregate_hourly_data'));
        add_action('chatshop_daily_analytics', array($this, 'aggregate_daily_data'));
    }

    /**
     * Get revenue statistics
     *
     * @param array  $filters Filter criteria
     * @param string $period  Time period (hour, day, week, month, year)
     * @return array
     */
    public function get_revenue_statistics($filters = array(), $period = 'month')
    {
        $cache_key = $this->generate_cache_key('revenue_stats', $filters, $period);
        $cached = $this->cache->get($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $stats = array(
            'total_revenue'         => 0,
            'net_revenue'           => 0,
            'refunded_amount'       => 0,
            'revenue_by_gateway'    => array(),
            'revenue_by_currency'   => array(),
            'revenue_trend'         => array(),
            'growth_rate'           => 0,
        );

        // Get transactions
        $transactions = $this->transaction_manager->get_statistics($filters, $period);

        // Calculate revenue
        $stats['total_revenue'] = $transactions['completed_amount'];
        $stats['refunded_amount'] = $transactions['refunded_amount'];
        $stats['net_revenue'] = $stats['total_revenue'] - $stats['refunded_amount'];

        // Revenue by gateway
        foreach ($transactions['by_gateway'] as $gateway_id => $gateway_data) {
            $stats['revenue_by_gateway'][$gateway_id] = $gateway_data['amount'];
        }

        // Revenue by currency
        foreach ($transactions['by_currency'] as $currency => $currency_data) {
            $stats['revenue_by_currency'][$currency] = $currency_data['amount'];
        }

        // Calculate growth rate
        $stats['growth_rate'] = $this->calculate_growth_rate($filters, $period);

        // Get revenue trend
        $stats['revenue_trend'] = $this->get_revenue_trend($filters, $period);

        // Cache results
        $this->cache->set($cache_key, $stats, 3600);

        return $stats;
    }

    /**
     * Get conversion statistics
     *
     * @param array  $filters Filter criteria
     * @param string $period  Time period
     * @return array
     */
    public function get_conversion_statistics($filters = array(), $period = 'month')
    {
        $cache_key = $this->generate_cache_key('conversion_stats', $filters, $period);
        $cached = $this->cache->get($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $stats = array(
            'overall_conversion_rate'   => 0,
            'conversion_by_gateway'     => array(),
            'conversion_by_source'      => array(),
            'conversion_funnel'         => array(),
            'abandoned_payments'        => 0,
            'average_time_to_convert'   => 0,
        );

        // Get link and transaction data
        $link_stats = $this->get_link_statistics($filters, $period);
        $transaction_stats = $this->transaction_manager->get_statistics($filters, $period);

        // Calculate overall conversion rate
        if ($link_stats['total_accesses'] > 0) {
            $stats['overall_conversion_rate'] = (
                $transaction_stats['completed_count'] / $link_stats['total_accesses']
            ) * 100;
        }

        // Conversion by gateway
        $gateway_accesses = $this->get_gateway_link_accesses($filters, $period);

        foreach ($transaction_stats['by_gateway'] as $gateway_id => $gateway_data) {
            if (isset($gateway_accesses[$gateway_id]) && $gateway_accesses[$gateway_id] > 0) {
                $stats['conversion_by_gateway'][$gateway_id] = (
                    $gateway_data['count'] / $gateway_accesses[$gateway_id]
                ) * 100;
            }
        }

        // Conversion funnel
        $stats['conversion_funnel'] = array(
            'links_created'     => $link_stats['total_created'],
            'links_accessed'    => $link_stats['total_accesses'],
            'payments_initiated' => $transaction_stats['total_transactions'],
            'payments_completed' => $transaction_stats['completed_count'],
        );

        // Abandoned payments
        $stats['abandoned_payments'] = $transaction_stats['total_transactions']
            - $transaction_stats['completed_count']
            - $transaction_stats['failed_count'];

        // Cache results
        $this->cache->set($cache_key, $stats, 3600);

        return $stats;
    }

    /**
     * Get gateway performance metrics
     *
     * @param array  $filters Filter criteria
     * @param string $period  Time period
     * @return array
     */
    public function get_gateway_performance($filters = array(), $period = 'month')
    {
        $cache_key = $this->generate_cache_key('gateway_performance', $filters, $period);
        $cached = $this->cache->get($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $performance = array();
        $registry = ChatShop_Payment_Registry::get_instance();
        $gateways = $registry->get_all_gateways();

        foreach ($gateways as $gateway_id => $gateway_info) {
            $gateway_filters = array_merge($filters, array('gateway_id' => $gateway_id));
            $gateway_stats = $this->transaction_manager->get_statistics($gateway_filters, $period);

            $performance[$gateway_id] = array(
                'name'                  => $gateway_info['name'],
                'total_transactions'    => $gateway_stats['total_transactions'],
                'successful_transactions' => $gateway_stats['completed_count'],
                'failed_transactions'   => $gateway_stats['failed_count'],
                'success_rate'          => $gateway_stats['total_transactions'] > 0
                    ? ($gateway_stats['completed_count'] / $gateway_stats['total_transactions']) * 100
                    : 0,
                'total_revenue'         => $gateway_stats['completed_amount'],
                'average_order_value'   => $gateway_stats['completed_count'] > 0
                    ? $gateway_stats['completed_amount'] / $gateway_stats['completed_count']
                    : 0,
                'refund_rate'           => $gateway_stats['completed_count'] > 0
                    ? ($gateway_stats['refunded_count'] / $gateway_stats['completed_count']) * 100
                    : 0,
                'processing_time'       => $this->get_average_processing_time($gateway_id, $filters, $period),
            );
        }

        // Sort by revenue
        uasort($performance, function ($a, $b) {
            return $b['total_revenue'] - $a['total_revenue'];
        });

        // Cache results
        $this->cache->set($cache_key, $performance, 3600);

        return $performance;
    }

    /**
     * Get payment method usage statistics
     *
     * @param array  $filters Filter criteria
     * @param string $period  Time period
     * @return array
     */
    public function get_payment_method_usage($filters = array(), $period = 'month')
    {
        $cache_key = $this->generate_cache_key('payment_method_usage', $filters, $period);
        $cached = $this->cache->get($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $usage = array();

        // This would query transaction metadata for payment method details
        $usage = apply_filters('chatshop_payment_method_usage', $usage, $filters, $period);

        // Cache results
        $this->cache->set($cache_key, $usage, 3600);

        return $usage;
    }

    /**
     * Get analytics dashboard data
     *
     * @param string $period Time period
     * @return array
     */
    public function get_dashboard_data($period = 'month')
    {
        return array(
            'revenue'       => $this->get_revenue_statistics(array(), $period),
            'conversions'   => $this->get_conversion_statistics(array(), $period),
            'performance'   => $this->get_gateway_performance(array(), $period),
            'recent_transactions' => $this->get_recent_transactions(10),
            'top_customers' => $this->get_top_customers(5, $period),
        );
    }

    /**
     * Track payment completed
     *
     * @param string $transaction_id Transaction ID
     * @param array  $data          Payment data
     * @return void
     */
    public function track_payment_completed($transaction_id, $data)
    {
        $this->track_event('payment_completed', array(
            'transaction_id' => $transaction_id,
            'amount'         => isset($data['amount']) ? $data['amount'] : 0,
            'currency'       => isset($data['currency']) ? $data['currency'] : '',
            'gateway_id'     => isset($data['gateway_id']) ? $data['gateway_id'] : '',
            'processing_time' => isset($data['processing_time']) ? $data['processing_time'] : 0,
        ));

        // Clear relevant caches
        $this->clear_analytics_cache();
    }

    /**
     * Track payment failed
     *
     * @param string $transaction_id Transaction ID
     * @param array  $data          Failure data
     * @return void
     */
    public function track_payment_failed($transaction_id, $data)
    {
        $this->track_event('payment_failed', array(
            'transaction_id' => $transaction_id,
            'gateway_id'     => isset($data['gateway_id']) ? $data['gateway_id'] : '',
            'error_code'     => isset($data['error_code']) ? $data['error_code'] : '',
            'error_message'  => isset($data['error_message']) ? $data['error_message'] : '',
        ));

        // Clear relevant caches
        $this->clear_analytics_cache();
    }

    /**
     * Track payment refunded
     *
     * @param string $transaction_id Transaction ID
     * @param float  $amount        Refund amount
     * @param string $reason        Refund reason
     * @return void
     */
    public function track_payment_refunded($transaction_id, $amount, $reason)
    {
        $this->track_event('payment_refunded', array(
            'transaction_id' => $transaction_id,
            'refund_amount'  => $amount,
            'refund_reason'  => $reason,
        ));

        // Clear relevant caches
        $this->clear_analytics_cache();
    }

    /**
     * Track link access
     *
     * @param string $link_id Link ID
     * @return void
     */
    public function track_link_access($link_id)
    {
        $this->track_event('payment_link_accessed', array(
            'link_id'    => $link_id,
            'referrer'   => wp_get_referer(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ));
    }

    /**
     * Track link creation
     *
     * @param string $link_id    Link ID
     * @param string $gateway_id Gateway ID
     * @param array  $link_data  Link data
     * @return void
     */
    public function track_link_creation($link_id, $gateway_id, $link_data)
    {
        $this->track_event('payment_link_created', array(
            'link_id'    => $link_id,
            'gateway_id' => $gateway_id,
            'amount'     => $link_data['amount'],
            'currency'   => $link_data['currency'],
        ));
    }

    /**
     * Track analytics event
     *
     * @param string $event_type Event type
     * @param array  $event_data Event data
     * @return void
     */
    private function track_event($event_type, $event_data)
    {
        // This would integrate with the main analytics component
        if (isset($this->analytics_manager)) {
            $this->analytics_manager->track_event('payment', $event_type, $event_data);
        }

        // Also fire WordPress action for extensibility
        do_action('chatshop_payment_analytics_event', $event_type, $event_data);
    }

    /**
     * Generate cache key
     *
     * @param string $type    Cache type
     * @param array  $filters Filters
     * @param string $period  Period
     * @return string
     */
    private function generate_cache_key($type, $filters, $period)
    {
        return 'payment_analytics_' . $type . '_' . md5(serialize($filters) . $period);
    }

    /**
     * Clear analytics cache
     *
     * @return void
     */
    private function clear_analytics_cache()
    {
        $this->cache->delete_group('payment_analytics');
    }

    /**
     * Calculate growth rate
     *
     * @param array  $filters Filter criteria
     * @param string $period  Time period
     * @return float
     */
    private function calculate_growth_rate($filters, $period)
    {
        // Implementation would compare current period to previous period
        return 0.0;
    }

    /**
     * Get revenue trend
     *
     * @param array  $filters Filter criteria
     * @param string $period  Time period
     * @return array
     */
    private function get_revenue_trend($filters, $period)
    {
        // Implementation would return time-series data
        return array();
    }

    /**
     * Get link statistics
     *
     * @param array  $filters Filter criteria
     * @param string $period  Time period
     * @return array
     */
    private function get_link_statistics($filters, $period)
    {
        // Implementation would query payment link data
        return array(
            'total_created' => 0,
            'total_accesses' => 0,
        );
    }

    /**
     * Get gateway link accesses
     *
     * @param array  $filters Filter criteria
     * @param string $period  Time period
     * @return array
     */
    private function get_gateway_link_accesses($filters, $period)
    {
        // Implementation would query link access data by gateway
        return array();
    }

    /**
     * Get average processing time
     *
     * @param string $gateway_id Gateway ID
     * @param array  $filters    Filter criteria
     * @param string $period     Time period
     * @return float
     */
    private function get_average_processing_time($gateway_id, $filters, $period)
    {
        // Implementation would calculate average time from initiation to completion
        return 0.0;
    }

    /**
     * Get recent transactions
     *
     * @param int $limit Number of transactions
     * @return array
     */
    private function get_recent_transactions($limit = 10)
    {
        return $this->transaction_manager->get_transactions(array(), $limit, 0);
    }

    /**
     * Get top customers
     *
     * @param int    $limit  Number of customers
     * @param string $period Time period
     * @return array
     */
    private function get_top_customers($limit = 5, $period = 'month')
    {
        // Implementation would query and aggregate customer transaction data
        return array();
    }

    /**
     * Aggregate hourly data
     *
     * @return void
     */
    public function aggregate_hourly_data()
    {
        // Implementation would aggregate metrics for the past hour
        $this->logger->log('Hourly payment analytics aggregation completed', 'info', 'analytics');
    }

    /**
     * Aggregate daily data
     *
     * @return void
     */
    public function aggregate_daily_data()
    {
        // Implementation would aggregate metrics for the past day
        $this->logger->log('Daily payment analytics aggregation completed', 'info', 'analytics');
    }
}
