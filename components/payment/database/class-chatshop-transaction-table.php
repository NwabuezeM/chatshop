<?php

/**
 * Transaction Database Table
 *
 * @package ChatShop
 * @subpackage Components/Payment/Database
 * @since 1.0.0
 */

namespace ChatShop\Components\Payment\Database;

use ChatShop\Includes\ChatShop_Database;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Transaction Table Class
 *
 * Manages the transactions database table
 *
 * @since 1.0.0
 */
class ChatShop_Transaction_Table extends ChatShop_Database
{

    /**
     * Table name
     *
     * @var string
     */
    protected $table_name = 'chatshop_transactions';

    /**
     * Table version
     *
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * Create table schema
     *
     * @return string
     */
    protected function get_schema()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$wpdb->prefix}{$this->table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            transaction_id varchar(50) NOT NULL,
            gateway_id varchar(50) NOT NULL,
            amount decimal(19,4) NOT NULL,
            currency varchar(3) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            customer_email varchar(100) NOT NULL,
            customer_name varchar(255) DEFAULT NULL,
            customer_phone varchar(50) DEFAULT NULL,
            order_id bigint(20) UNSIGNED DEFAULT NULL,
            payment_link_id varchar(50) DEFAULT NULL,
            gateway_reference varchar(255) DEFAULT NULL,
            gateway_response longtext DEFAULT NULL,
            verification_result longtext DEFAULT NULL,
            refund_amount decimal(19,4) DEFAULT 0,
            refund_reason text DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            last_webhook longtext DEFAULT NULL,
            processing_time int(11) DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            completed_at datetime DEFAULT NULL,
            failed_at datetime DEFAULT NULL,
            refunded_at datetime DEFAULT NULL,
            verified_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY transaction_id (transaction_id),
            KEY gateway_id (gateway_id),
            KEY status (status),
            KEY customer_email (customer_email),
            KEY order_id (order_id),
            KEY payment_link_id (payment_link_id),
            KEY gateway_reference (gateway_reference),
            KEY created_at (created_at),
            KEY status_created (status, created_at)
        ) $charset_collate;";
    }

    /**
     * Create transaction record
     *
     * @param array $data Transaction data
     * @return int|false Insert ID or false
     */
    public function create($data)
    {
        global $wpdb;

        // Serialize complex data
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = wp_json_encode($data['metadata']);
        }

        if (isset($data['gateway_response']) && is_array($data['gateway_response'])) {
            $data['gateway_response'] = wp_json_encode($data['gateway_response']);
        }

        $result = $wpdb->insert(
            $wpdb->prefix . $this->table_name,
            $data,
            $this->get_field_formats($data)
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update transaction record
     *
     * @param int   $id   Record ID
     * @param array $data Update data
     * @return bool
     */
    public function update($id, $data)
    {
        global $wpdb;

        // Serialize complex data
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = wp_json_encode($data['metadata']);
        }

        if (isset($data['gateway_response']) && is_array($data['gateway_response'])) {
            $data['gateway_response'] = wp_json_encode($data['gateway_response']);
        }

        if (isset($data['verification_result']) && is_array($data['verification_result'])) {
            $data['verification_result'] = wp_json_encode($data['verification_result']);
        }

        if (isset($data['last_webhook']) && is_array($data['last_webhook'])) {
            $data['last_webhook'] = wp_json_encode($data['last_webhook']);
        }

        $result = $wpdb->update(
            $wpdb->prefix . $this->table_name,
            $data,
            array('id' => $id),
            $this->get_field_formats($data),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get transaction by ID
     *
     * @param int $id Record ID
     * @return array|null
     */
    public function get($id)
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}{$this->table_name} WHERE id = %d",
            $id
        );

        $result = $wpdb->get_row($query, ARRAY_A);

        if ($result) {
            $result = $this->unserialize_data($result);
        }

        return $result;
    }

    /**
     * Get transaction by transaction ID
     *
     * @param string $transaction_id Transaction ID
     * @return array|null
     */
    public function get_by_transaction_id($transaction_id)
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}{$this->table_name} WHERE transaction_id = %s",
            $transaction_id
        );

        $result = $wpdb->get_row($query, ARRAY_A);

        if ($result) {
            $result = $this->unserialize_data($result);
        }

        return $result;
    }

    /**
     * Get filtered transactions
     *
     * @param array $filters Filter criteria
     * @param int   $limit   Result limit
     * @param int   $offset  Result offset
     * @return array
     */
    public function get_filtered($filters = array(), $limit = 20, $offset = 0)
    {
        global $wpdb;

        $where_clauses = array('1=1');
        $where_values = array();

        // Build WHERE clauses
        if (!empty($filters['gateway_id'])) {
            $where_clauses[] = 'gateway_id = %s';
            $where_values[] = $filters['gateway_id'];
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = implode(',', array_fill(0, count($filters['status']), '%s'));
                $where_clauses[] = "status IN ($placeholders)";
                $where_values = array_merge($where_values, $filters['status']);
            } else {
                $where_clauses[] = 'status = %s';
                $where_values[] = $filters['status'];
            }
        }

        if (!empty($filters['customer_email'])) {
            $where_clauses[] = 'customer_email = %s';
            $where_values[] = $filters['customer_email'];
        }

        if (!empty($filters['order_id'])) {
            $where_clauses[] = 'order_id = %d';
            $where_values[] = $filters['order_id'];
        }

        if (!empty($filters['payment_link_id'])) {
            $where_clauses[] = 'payment_link_id = %s';
            $where_values[] = $filters['payment_link_id'];
        }

        if (!empty($filters['currency'])) {
            $where_clauses[] = 'currency = %s';
            $where_values[] = strtoupper($filters['currency']);
        }

        if (!empty($filters['created_after'])) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $filters['created_after'];
        }

        if (!empty($filters['created_before'])) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $filters['created_before'];
        }

        if (!empty($filters['amount_min'])) {
            $where_clauses[] = 'amount >= %f';
            $where_values[] = $filters['amount_min'];
        }

        if (!empty($filters['amount_max'])) {
            $where_clauses[] = 'amount <= %f';
            $where_values[] = $filters['amount_max'];
        }

        // Build query
        $where_sql = implode(' AND ', $where_clauses);

        // Add limit values to the end
        $where_values[] = $limit;
        $where_values[] = $offset;

        $query = "SELECT * FROM {$wpdb->prefix}{$this->table_name} 
                  WHERE $where_sql 
                  ORDER BY created_at DESC 
                  LIMIT %d OFFSET %d";

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        $results = $wpdb->get_results($query, ARRAY_A);

        // Unserialize data
        foreach ($results as &$result) {
            $result = $this->unserialize_data($result);
        }

        return $results;
    }

    /**
     * Get all filtered transactions (no limit)
     *
     * @param array $filters Filter criteria
     * @return array
     */
    public function get_all_filtered($filters = array())
    {
        global $wpdb;

        $where_clauses = array('1=1');
        $where_values = array();

        // Build WHERE clauses (same as get_filtered)
        if (!empty($filters['gateway_id'])) {
            $where_clauses[] = 'gateway_id = %s';
            $where_values[] = $filters['gateway_id'];
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = implode(',', array_fill(0, count($filters['status']), '%s'));
                $where_clauses[] = "status IN ($placeholders)";
                $where_values = array_merge($where_values, $filters['status']);
            } else {
                $where_clauses[] = 'status = %s';
                $where_values[] = $filters['status'];
            }
        }

        if (!empty($filters['created_after'])) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $filters['created_after'];
        }

        if (!empty($filters['created_before'])) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $filters['created_before'];
        }

        // Build query
        $where_sql = implode(' AND ', $where_clauses);

        $query = "SELECT * FROM {$wpdb->prefix}{$this->table_name} 
                  WHERE $where_sql 
                  ORDER BY created_at DESC";

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        $results = $wpdb->get_results($query, ARRAY_A);

        // Unserialize data
        foreach ($results as &$result) {
            $result = $this->unserialize_data($result);
        }

        return $results;
    }

    /**
     * Count transactions
     *
     * @param array $filters Filter criteria
     * @return int
     */
    public function count($filters = array())
    {
        global $wpdb;

        $where_clauses = array('1=1');
        $where_values = array();

        // Build WHERE clauses
        if (!empty($filters['gateway_id'])) {
            $where_clauses[] = 'gateway_id = %s';
            $where_values[] = $filters['gateway_id'];
        }

        if (!empty($filters['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $filters['status'];
        }

        if (!empty($filters['created_after'])) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $filters['created_after'];
        }

        $where_sql = implode(' AND ', $where_clauses);

        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}{$this->table_name} WHERE $where_sql";

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        return (int) $wpdb->get_var($query);
    }

    /**
     * Delete old transactions
     *
     * @param string $cutoff_date Cutoff date
     * @return int Number of deleted records
     */
    public function delete_old_transactions($cutoff_date)
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}{$this->table_name} 
             WHERE created_at < %s 
             AND status IN ('completed', 'failed', 'cancelled')",
            $cutoff_date
        );

        return $wpdb->query($query);
    }

    /**
     * Get transaction summary
     *
     * @param array $filters Filter criteria
     * @return array
     */
    public function get_summary($filters = array())
    {
        global $wpdb;

        $where_clauses = array('1=1');
        $where_values = array();

        // Build WHERE clauses
        if (!empty($filters['gateway_id'])) {
            $where_clauses[] = 'gateway_id = %s';
            $where_values[] = $filters['gateway_id'];
        }

        if (!empty($filters['created_after'])) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $filters['created_after'];
        }

        if (!empty($filters['created_before'])) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $filters['created_before'];
        }

        $where_sql = implode(' AND ', $where_clauses);

        $query = "SELECT 
                    COUNT(*) as total_count,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                    SUM(CASE WHEN status IN ('refunded', 'partial_refund') THEN 1 ELSE 0 END) as refunded_count,
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN status IN ('refunded', 'partial_refund') THEN refund_amount ELSE 0 END) as total_refunded,
                    AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as average_amount,
                    AVG(CASE WHEN status = 'completed' AND processing_time IS NOT NULL THEN processing_time ELSE NULL END) as average_processing_time
                  FROM {$wpdb->prefix}{$this->table_name}
                  WHERE $where_sql";

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        return $wpdb->get_row($query, ARRAY_A);
    }

    /**
     * Get field formats
     *
     * @param array $data Data array
     * @return array
     */
    private function get_field_formats($data)
    {
        $formats = array();

        foreach ($data as $field => $value) {
            switch ($field) {
                case 'id':
                case 'order_id':
                case 'processing_time':
                    $formats[] = '%d';
                    break;

                case 'amount':
                case 'refund_amount':
                    $formats[] = '%f';
                    break;

                default:
                    $formats[] = '%s';
                    break;
            }
        }

        return $formats;
    }

    /**
     * Unserialize data fields
     *
     * @param array $data Raw data
     * @return array
     */
    private function unserialize_data($data)
    {
        // Unserialize JSON fields
        $json_fields = array('metadata', 'gateway_response', 'verification_result', 'last_webhook');

        foreach ($json_fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $decoded = json_decode($data[$field], true);
                $data[$field] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $data[$field];
            }
        }

        return $data;
    }

    /**
     * Get transactions by gateway reference
     *
     * @param string $gateway_reference Gateway reference
     * @return array|null
     */
    public function get_by_gateway_reference($gateway_reference)
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}{$this->table_name} WHERE gateway_reference = %s",
            $gateway_reference
        );

        $result = $wpdb->get_row($query, ARRAY_A);

        if ($result) {
            $result = $this->unserialize_data($result);
        }

        return $result;
    }

    /**
     * Get revenue by date range
     *
     * @param string $start_date Start date
     * @param string $end_date   End date
     * @param string $group_by   Group by (day, week, month)
     * @return array
     */
    public function get_revenue_by_date($start_date, $end_date, $group_by = 'day')
    {
        global $wpdb;

        $date_format = '%Y-%m-%d';

        switch ($group_by) {
            case 'week':
                $date_format = '%Y-%u';
                break;
            case 'month':
                $date_format = '%Y-%m';
                break;
        }

        $query = $wpdb->prepare(
            "SELECT 
                DATE_FORMAT(created_at, %s) as period,
                COUNT(*) as transaction_count,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as revenue,
                SUM(CASE WHEN status IN ('refunded', 'partial_refund') THEN refund_amount ELSE 0 END) as refunded
             FROM {$wpdb->prefix}{$this->table_name}
             WHERE created_at BETWEEN %s AND %s
             GROUP BY period
             ORDER BY period ASC",
            $date_format,
            $start_date,
            $end_date
        );

        return $wpdb->get_results($query, ARRAY_A);
    }
}
