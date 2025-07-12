<?php

/**
 * Payment Links Database Table
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
 * Payment Links Table Class
 *
 * Manages the payment links database table
 *
 * @since 1.0.0
 */
class ChatShop_Payment_Links_Table extends ChatShop_Database
{

    /**
     * Table name
     *
     * @var string
     */
    protected $table_name = 'chatshop_payment_links';

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
            link_id varchar(50) NOT NULL,
            gateway_id varchar(50) NOT NULL,
            amount decimal(19,4) NOT NULL,
            currency varchar(3) NOT NULL,
            description text DEFAULT NULL,
            gateway_link text DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            whatsapp_number varchar(50) DEFAULT NULL,
            customer_email varchar(100) DEFAULT NULL,
            customer_name varchar(255) DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            use_count int(11) NOT NULL DEFAULT 0,
            max_uses int(11) DEFAULT NULL,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            expires_at datetime DEFAULT NULL,
            last_accessed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY link_id (link_id),
            KEY gateway_id (gateway_id),
            KEY status (status),
            KEY whatsapp_number (whatsapp_number),
            KEY customer_email (customer_email),
            KEY created_by (created_by),
            KEY created_at (created_at),
            KEY expires_at (expires_at),
            KEY status_expires (status, expires_at)
        ) $charset_collate;";
    }

    /**
     * Create payment link record
     *
     * @param array $data Link data
     * @return int|false Insert ID or false
     */
    public function create($data)
    {
        global $wpdb;

        // Serialize metadata
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = wp_json_encode($data['metadata']);
        }

        // Set timestamps
        if (!isset($data['created_at'])) {
            $data['created_at'] = current_time('mysql');
        }

        if (!isset($data['updated_at'])) {
            $data['updated_at'] = current_time('mysql');
        }

        $result = $wpdb->insert(
            $wpdb->prefix . $this->table_name,
            $data,
            $this->get_field_formats($data)
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update payment link record
     *
     * @param int   $id   Record ID
     * @param array $data Update data
     * @return bool
     */
    public function update($id, $data)
    {
        global $wpdb;

        // Serialize metadata
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = wp_json_encode($data['metadata']);
        }

        // Update timestamp
        $data['updated_at'] = current_time('mysql');

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
     * Get payment link by ID
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
     * Get payment link by link ID
     *
     * @param string $link_id Link ID
     * @return array|null
     */
    public function get_by_link_id($link_id)
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}{$this->table_name} WHERE link_id = %s",
            $link_id
        );

        $result = $wpdb->get_row($query, ARRAY_A);

        if ($result) {
            $result = $this->unserialize_data($result);
        }

        return $result;
    }

    /**
     * Increment use count
     *
     * @param int $id Record ID
     * @return bool
     */
    public function increment_use_count($id)
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "UPDATE {$wpdb->prefix}{$this->table_name} 
             SET use_count = use_count + 1,
                 last_accessed_at = %s
             WHERE id = %d",
            current_time('mysql'),
            $id
        );

        return $wpdb->query($query) !== false;
    }

    /**
     * Get expired links
     *
     * @return array
     */
    public function get_expired_links()
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}{$this->table_name} 
             WHERE status = 'active' 
             AND expires_at IS NOT NULL 
             AND expires_at < %s",
            current_time('mysql')
        );

        $results = $wpdb->get_results($query, ARRAY_A);

        foreach ($results as &$result) {
            $result = $this->unserialize_data($result);
        }

        return $results;
    }

    /**
     * Get exhausted links
     *
     * @return array
     */
    public function get_exhausted_links()
    {
        global $wpdb;

        $query = "SELECT * FROM {$wpdb->prefix}{$this->table_name} 
                  WHERE status = 'active' 
                  AND max_uses IS NOT NULL 
                  AND use_count >= max_uses";

        $results = $wpdb->get_results($query, ARRAY_A);

        foreach ($results as &$result) {
            $result = $this->unserialize_data($result);
        }

        return $results;
    }

    /**
     * Get filtered payment links
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
            $where_clauses[] = 'status = %s';
            $where_values[] = $filters['status'];
        }

        if (!empty($filters['whatsapp_number'])) {
            $where_clauses[] = 'whatsapp_number = %s';
            $where_values[] = $filters['whatsapp_number'];
        }

        if (!empty($filters['customer_email'])) {
            $where_clauses[] = 'customer_email = %s';
            $where_values[] = $filters['customer_email'];
        }

        if (!empty($filters['created_by'])) {
            $where_clauses[] = 'created_by = %d';
            $where_values[] = $filters['created_by'];
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

        // Add limit values
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
     * Count payment links
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
        if (!empty($filters['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $filters['status'];
        }

        if (!empty($filters['gateway_id'])) {
            $where_clauses[] = 'gateway_id = %s';
            $where_values[] = $filters['gateway_id'];
        }

        if (!empty($filters['created_by'])) {
            $where_clauses[] = 'created_by = %d';
            $where_values[] = $filters['created_by'];
        }

        $where_sql = implode(' AND ', $where_clauses);

        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}{$this->table_name} WHERE $where_sql";

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        return (int) $wpdb->get_var($query);
    }

    /**
     * Get link statistics
     *
     * @param array $filters Filter criteria
     * @return array
     */
    public function get_statistics($filters = array())
    {
        global $wpdb;

        $where_clauses = array('1=1');
        $where_values = array();

        // Build WHERE clauses
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
                    COUNT(*) as total_links,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_links,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_links,
                    SUM(CASE WHEN status = 'exhausted' THEN 1 ELSE 0 END) as exhausted_links,
                    SUM(use_count) as total_accesses,
                    AVG(use_count) as average_uses,
                    SUM(amount * use_count) as potential_revenue
                  FROM {$wpdb->prefix}{$this->table_name}
                  WHERE $where_sql";

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        return $wpdb->get_row($query, ARRAY_A);
    }

    /**
     * Delete old links
     *
     * @param string $cutoff_date Cutoff date
     * @return int Number of deleted records
     */
    public function delete_old_links($cutoff_date)
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}{$this->table_name} 
             WHERE created_at < %s 
             AND status IN ('expired', 'exhausted', 'inactive')",
            $cutoff_date
        );

        return $wpdb->query($query);
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
                case 'use_count':
                case 'max_uses':
                case 'created_by':
                    $formats[] = '%d';
                    break;

                case 'amount':
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
        // Unserialize metadata
        if (isset($data['metadata']) && !empty($data['metadata'])) {
            $decoded = json_decode($data['metadata'], true);
            $data['metadata'] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $data['metadata'];
        }

        return $data;
    }

    /**
     * Get links by WhatsApp number
     *
     * @param string $whatsapp_number WhatsApp number
     * @param string $status         Optional status filter
     * @return array
     */
    public function get_by_whatsapp_number($whatsapp_number, $status = null)
    {
        global $wpdb;

        $query = "SELECT * FROM {$wpdb->prefix}{$this->table_name} WHERE whatsapp_number = %s";
        $values = array($whatsapp_number);

        if ($status) {
            $query .= " AND status = %s";
            $values[] = $status;
        }

        $query .= " ORDER BY created_at DESC";

        $prepared_query = $wpdb->prepare($query, $values);
        $results = $wpdb->get_results($prepared_query, ARRAY_A);

        foreach ($results as &$result) {
            $result = $this->unserialize_data($result);
        }

        return $results;
    }
}
