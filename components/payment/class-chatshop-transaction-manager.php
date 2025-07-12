<?php

/**
 * Transaction Manager
 *
 * @package ChatShop
 * @subpackage Components/Payment
 * @since 1.0.0
 */

namespace ChatShop\Components\Payment;

use ChatShop\Includes\ChatShop_Logger;
use ChatShop\Includes\ChatShop_Cache;
use ChatShop\Components\Payment\Database\ChatShop_Transaction_Table;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Transaction Manager Class
 *
 * Handles payment transaction processing and tracking
 *
 * @since 1.0.0
 */
class ChatShop_Transaction_Manager
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
     * Transaction table instance
     *
     * @var ChatShop_Transaction_Table
     */
    private $transaction_table;

    /**
     * Transaction statuses
     *
     * @var array
     */
    private $statuses = array(
        'pending'     => 'Pending',
        'processing'  => 'Processing',
        'completed'   => 'Completed',
        'failed'      => 'Failed',
        'cancelled'   => 'Cancelled',
        'refunded'    => 'Refunded',
        'partial_refund' => 'Partially Refunded',
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = new ChatShop_Logger();
        $this->cache = new ChatShop_Cache();
        $this->transaction_table = new ChatShop_Transaction_Table();
    }

    /**
     * Initialize
     *
     * @return void
     */
    public function init()
    {
        // Register hooks
        add_action('chatshop_payment_completed', array($this, 'handle_payment_completed'), 10, 2);
        add_action('chatshop_payment_failed', array($this, 'handle_payment_failed'), 10, 2);
        add_action('chatshop_payment_refunded', array($this, 'handle_payment_refunded'), 10, 3);

        // Cleanup old transactions
        add_action('chatshop_daily_cleanup', array($this, 'cleanup_old_transactions'));
    }

    /**
     * Create transaction
     *
     * @param array $data Transaction data
     * @return string|false Transaction ID or false on failure
     */
    public function create_transaction($data)
    {
        // Validate required fields
        $required = array('gateway_id', 'amount', 'currency', 'customer_data');

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $this->logger->log(
                    sprintf('Transaction creation failed - missing field: %s', $field),
                    'error',
                    'payment'
                );
                return false;
            }
        }

        // Generate transaction ID
        $transaction_id = $this->generate_transaction_id();

        // Prepare transaction record
        $transaction = array(
            'transaction_id'   => $transaction_id,
            'gateway_id'       => sanitize_key($data['gateway_id']),
            'amount'           => floatval($data['amount']),
            'currency'         => strtoupper($data['currency']),
            'status'           => isset($data['status']) ? $data['status'] : 'pending',
            'customer_email'   => $data['customer_data']['email'],
            'customer_name'    => isset($data['customer_data']['name']) ? $data['customer_data']['name'] : '',
            'customer_phone'   => isset($data['customer_data']['phone']) ? $data['customer_data']['phone'] : '',
            'order_id'         => isset($data['order_data']['order_id']) ? $data['order_data']['order_id'] : null,
            'payment_link_id'  => isset($data['payment_link_id']) ? $data['payment_link_id'] : null,
            'metadata'         => isset($data['metadata']) ? $data['metadata'] : array(),
            'created_at'       => current_time('mysql'),
            'updated_at'       => current_time('mysql'),
        );

        // Save to database
        $saved = $this->transaction_table->create($transaction);

        if (!$saved) {
            $this->logger->log(
                'Failed to save transaction to database',
                'error',
                'payment',
                array('transaction_id' => $transaction_id)
            );
            return false;
        }

        // Fire event
        do_action('chatshop_transaction_created', $transaction_id, $transaction);

        $this->logger->log(
            'Transaction created',
            'info',
            'payment',
            array(
                'transaction_id' => $transaction_id,
                'gateway_id'     => $data['gateway_id'],
                'amount'         => $data['amount'],
                'currency'       => $data['currency'],
            )
        );

        return $transaction_id;
    }

    /**
     * Update transaction
     *
     * @param string $transaction_id Transaction ID
     * @param array  $data          Update data
     * @return bool
     */
    public function update_transaction($transaction_id, $data)
    {
        $transaction = $this->get_transaction($transaction_id);

        if (!$transaction) {
            return false;
        }

        // Add updated timestamp
        $data['updated_at'] = current_time('mysql');

        // Update in database
        $updated = $this->transaction_table->update($transaction['id'], $data);

        if ($updated) {
            // Clear cache
            $this->cache->delete('transaction_' . $transaction_id);

            // Log status changes
            if (isset($data['status']) && $data['status'] !== $transaction['status']) {
                $this->log_status_change($transaction_id, $transaction['status'], $data['status']);
            }

            // Fire event
            do_action('chatshop_transaction_updated', $transaction_id, $data);
        }

        return $updated;
    }

    /**
     * Get transaction
     *
     * @param string $transaction_id Transaction ID
     * @return array|null
     */
    public function get_transaction($transaction_id)
    {
        // Check cache
        $cached = $this->cache->get('transaction_' . $transaction_id);
        if ($cached !== false) {
            return $cached;
        }

        // Get from database
        $transaction = $this->transaction_table->get_by_transaction_id($transaction_id);

        if ($transaction) {
            // Cache result
            $this->cache->set('transaction_' . $transaction_id, $transaction, 3600);
        }

        return $transaction;
    }

    /**
     * Get transactions by filter
     *
     * @param array $filters Filter criteria
     * @param int   $limit   Result limit
     * @param int   $offset  Result offset
     * @return array
     */
    public function get_transactions($filters = array(), $limit = 20, $offset = 0)
    {
        return $this->transaction_table->get_filtered($filters, $limit, $offset);
    }

    /**
     * Update transaction status
     *
     * @param string $transaction_id Transaction ID
     * @param string $status        New status
     * @param array  $additional    Additional data to update
     * @return bool
     */
    public function update_status($transaction_id, $status, $additional = array())
    {
        if (!in_array($status, array_keys($this->statuses), true)) {
            $this->logger->log(
                sprintf('Invalid transaction status: %s', $status),
                'error',
                'payment'
            );
            return false;
        }

        $update_data = array_merge($additional, array('status' => $status));

        return $this->update_transaction($transaction_id, $update_data);
    }

    /**
     * Process refund
     *
     * @param string $transaction_id Transaction ID
     * @param float  $amount        Refund amount (null for full refund)
     * @param string $reason        Refund reason
     * @return bool
     */
    public function process_refund($transaction_id, $amount = null, $reason = '')
    {
        $transaction = $this->get_transaction($transaction_id);

        if (!$transaction) {
            return false;
        }

        // Determine refund type
        $is_full_refund = ($amount === null || $amount >= $transaction['amount']);
        $refund_amount = $is_full_refund ? $transaction['amount'] : floatval($amount);

        // Update transaction
        $update_data = array(
            'status'         => $is_full_refund ? 'refunded' : 'partial_refund',
            'refund_amount'  => $refund_amount,
            'refund_reason'  => $reason,
            'refunded_at'    => current_time('mysql'),
        );

        $updated = $this->update_transaction($transaction_id, $update_data);

        if ($updated) {
            // Fire refund event
            do_action('chatshop_transaction_refunded', $transaction_id, $refund_amount, $reason);
        }

        return $updated;
    }

    /**
     * Get transaction statistics
     *
     * @param array  $filters Filter criteria
     * @param string $period  Time period (day, week, month, year)
     * @return array
     */
    public function get_statistics($filters = array(), $period = 'month')
    {
        $stats = array(
            'total_transactions' => 0,
            'total_amount'       => 0,
            'completed_count'    => 0,
            'completed_amount'   => 0,
            'failed_count'       => 0,
            'refunded_count'     => 0,
            'refunded_amount'    => 0,
            'conversion_rate'    => 0,
            'by_gateway'         => array(),
            'by_currency'        => array(),
        );

        // Get transactions for period
        $start_date = $this->get_period_start_date($period);
        $filters['created_after'] = $start_date;

        $transactions = $this->transaction_table->get_all_filtered($filters);

        foreach ($transactions as $transaction) {
            $stats['total_transactions']++;
            $stats['total_amount'] += $transaction['amount'];

            // Status breakdown
            if ($transaction['status'] === 'completed') {
                $stats['completed_count']++;
                $stats['completed_amount'] += $transaction['amount'];
            } elseif ($transaction['status'] === 'failed') {
                $stats['failed_count']++;
            } elseif (in_array($transaction['status'], array('refunded', 'partial_refund'))) {
                $stats['refunded_count']++;
                $stats['refunded_amount'] += isset($transaction['refund_amount'])
                    ? $transaction['refund_amount']
                    : $transaction['amount'];
            }

            // Gateway breakdown
            if (!isset($stats['by_gateway'][$transaction['gateway_id']])) {
                $stats['by_gateway'][$transaction['gateway_id']] = array(
                    'count'  => 0,
                    'amount' => 0,
                );
            }
            $stats['by_gateway'][$transaction['gateway_id']]['count']++;
            $stats['by_gateway'][$transaction['gateway_id']]['amount'] += $transaction['amount'];

            // Currency breakdown
            if (!isset($stats['by_currency'][$transaction['currency']])) {
                $stats['by_currency'][$transaction['currency']] = array(
                    'count'  => 0,
                    'amount' => 0,
                );
            }
            $stats['by_currency'][$transaction['currency']]['count']++;
            $stats['by_currency'][$transaction['currency']]['amount'] += $transaction['amount'];
        }

        // Calculate conversion rate
        if ($stats['total_transactions'] > 0) {
            $stats['conversion_rate'] = ($stats['completed_count'] / $stats['total_transactions']) * 100;
        }

        return $stats;
    }

    /**
     * Generate unique transaction ID
     *
     * @return string
     */
    private function generate_transaction_id()
    {
        do {
            $transaction_id = 'TXN_' . time() . '_' . strtoupper(wp_generate_password(8, false));
        } while ($this->transaction_table->get_by_transaction_id($transaction_id));

        return $transaction_id;
    }

    /**
     * Log status change
     *
     * @param string $transaction_id Transaction ID
     * @param string $old_status    Previous status
     * @param string $new_status    New status
     * @return void
     */
    private function log_status_change($transaction_id, $old_status, $new_status)
    {
        $this->logger->log(
            'Transaction status changed',
            'info',
            'payment',
            array(
                'transaction_id' => $transaction_id,
                'old_status'     => $old_status,
                'new_status'     => $new_status,
            )
        );

        do_action('chatshop_transaction_status_changed', $transaction_id, $old_status, $new_status);
    }

    /**
     * Get period start date
     *
     * @param string $period Time period
     * @return string
     */
    private function get_period_start_date($period)
    {
        switch ($period) {
            case 'day':
                return date('Y-m-d 00:00:00');
            case 'week':
                return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case 'month':
                return date('Y-m-d 00:00:00', strtotime('-30 days'));
            case 'year':
                return date('Y-m-d 00:00:00', strtotime('-365 days'));
            default:
                return date('Y-m-d 00:00:00', strtotime('-30 days'));
        }
    }

    /**
     * Handle payment completed
     *
     * @param string $transaction_id Transaction ID
     * @param array  $data          Payment data
     * @return void
     */
    public function handle_payment_completed($transaction_id, $data)
    {
        $this->update_status($transaction_id, 'completed', array(
            'completed_at'     => current_time('mysql'),
            'gateway_response' => $data,
        ));
    }

    /**
     * Handle payment failed
     *
     * @param string $transaction_id Transaction ID
     * @param array  $data          Failure data
     * @return void
     */
    public function handle_payment_failed($transaction_id, $data)
    {
        $this->update_status($transaction_id, 'failed', array(
            'failed_at'        => current_time('mysql'),
            'failure_reason'   => isset($data['reason']) ? $data['reason'] : '',
            'gateway_response' => $data,
        ));
    }

    /**
     * Handle payment refunded
     *
     * @param string $transaction_id Transaction ID
     * @param float  $amount        Refund amount
     * @param string $reason        Refund reason
     * @return void
     */
    public function handle_payment_refunded($transaction_id, $amount, $reason)
    {
        $this->process_refund($transaction_id, $amount, $reason);
    }

    /**
     * Cleanup old transactions
     *
     * @return void
     */
    public function cleanup_old_transactions()
    {
        $retention_days = apply_filters('chatshop_transaction_retention_days', 365);
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . $retention_days . ' days'));

        $deleted = $this->transaction_table->delete_old_transactions($cutoff_date);

        if ($deleted > 0) {
            $this->logger->log(
                sprintf('Cleaned up %d old transactions', $deleted),
                'info',
                'payment'
            );
        }
    }
}
