<?php

/**
 * The database interface for the plugin.
 *
 * @link       https://mycreanet.fr
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/database
 */

/**
 * The database interface for the plugin.
 *
 * Provides a unified interface for all database operations.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/database
 * @author     Your Name <https://mycreanet.fr>
 */
class Amazon_Product_Importer_Database {

    /**
     * WordPress database instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      wpdb    $wpdb    WordPress database instance.
     */
    private $wpdb;

    /**
     * Table names.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $tables    Array of table names.
     */
    private $tables;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        $this->tables = array(
            'import_logs' => $wpdb->prefix . 'ams_import_logs',
            'sync_history' => $wpdb->prefix . 'ams_sync_history',
            'price_history' => $wpdb->prefix . 'ams_price_history',
            'cron_jobs' => $wpdb->prefix . 'ams_cron_jobs',
            'api_cache' => $wpdb->prefix . 'ams_api_cache',
            'product_mapping' => $wpdb->prefix . 'ams_product_mapping',
            'error_logs' => $wpdb->prefix . 'ams_error_logs'
        );
    }

    /**
     * Get table name by key.
     *
     * @since    1.0.0
     * @param    string    $table_key    Table key.
     * @return   string    Full table name.
     */
    public function get_table_name($table_key) {
        return isset($this->tables[$table_key]) ? $this->tables[$table_key] : '';
    }

    // ======================================
    // IMPORT LOGS METHODS
    // ======================================

    /**
     * Insert import log entry.
     *
     * @since    1.0.0
     * @param    array    $data    Log data.
     * @return   int|false    Insert ID on success, false on failure.
     */
    public function insert_import_log($data) {
        $defaults = array(
            'asin' => '',
            'product_id' => null,
            'action' => 'import',
            'status' => 'pending',
            'message' => null,
            'api_response' => null,
            'user_id' => get_current_user_id(),
            'import_source' => 'manual',
            'created_at' => current_time('mysql'),
            'updated_at' => null
        );

        $data = wp_parse_args($data, $defaults);
        
        if (empty($data['asin'])) {
            return false;
        }

        $result = $this->wpdb->insert(
            $this->tables['import_logs'],
            $data,
            array('%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update import log entry.
     *
     * @since    1.0.0
     * @param    int      $log_id    Log ID.
     * @param    array    $data      Data to update.
     * @return   bool     True on success, false on failure.
     */
    public function update_import_log($log_id, $data) {
        $data['updated_at'] = current_time('mysql');
        
        $result = $this->wpdb->update(
            $this->tables['import_logs'],
            $data,
            array('id' => $log_id),
            null,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get import logs.
     *
     * @since    1.0.0
     * @param    array    $args    Query arguments.
     * @return   array    Array of log entries.
     */
    public function get_import_logs($args = array()) {
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'asin' => null,
            'product_id' => null,
            'status' => null,
            'action' => null,
            'date_from' => null,
            'date_to' => null,
            'order_by' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $where_conditions = array('1=1');
        $where_values = array();

        if (!empty($args['asin'])) {
            $where_conditions[] = 'asin = %s';
            $where_values[] = $args['asin'];
        }

        if (!empty($args['product_id'])) {
            $where_conditions[] = 'product_id = %d';
            $where_values[] = $args['product_id'];
        }

        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if (!empty($args['action'])) {
            $where_conditions[] = 'action = %s';
            $where_values[] = $args['action'];
        }

        if (!empty($args['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }

        $where_clause = implode(' AND ', $where_conditions);

        $query = "SELECT * FROM {$this->tables['import_logs']} 
                  WHERE {$where_clause} 
                  ORDER BY {$args['order_by']} {$args['order']} 
                  LIMIT %d OFFSET %d";

        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];

        if (!empty($where_values)) {
            $query = $this->wpdb->prepare($query, $where_values);
        }

        return $this->wpdb->get_results($query);
    }

    // ======================================
    // SYNC HISTORY METHODS
    // ======================================

    /**
     * Insert sync history entry.
     *
     * @since    1.0.0
     * @param    array    $data    Sync data.
     * @return   int|false    Insert ID on success, false on failure.
     */
    public function insert_sync_history($data) {
        $defaults = array(
            'sync_type' => 'manual',
            'status' => 'started',
            'products_processed' => 0,
            'products_success' => 0,
            'products_failed' => 0,
            'products_skipped' => 0,
            'execution_time' => 0,
            'memory_usage' => 0,
            'error_message' => null,
            'sync_settings' => null,
            'started_at' => current_time('mysql'),
            'completed_at' => null
        );

        $data = wp_parse_args($data, $defaults);

        $result = $this->wpdb->insert(
            $this->tables['sync_history'],
            $data,
            array('%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update sync history entry.
     *
     * @since    1.0.0
     * @param    int      $sync_id    Sync ID.
     * @param    array    $data       Data to update.
     * @return   bool     True on success, false on failure.
     */
    public function update_sync_history($sync_id, $data) {
        $result = $this->wpdb->update(
            $this->tables['sync_history'],
            $data,
            array('id' => $sync_id),
            null,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get sync history.
     *
     * @since    1.0.0
     * @param    array    $args    Query arguments.
     * @return   array    Array of sync entries.
     */
    public function get_sync_history($args = array()) {
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'sync_type' => null,
            'status' => null,
            'date_from' => null,
            'date_to' => null,
            'order_by' => 'started_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $where_conditions = array('1=1');
        $where_values = array();

        if (!empty($args['sync_type'])) {
            $where_conditions[] = 'sync_type = %s';
            $where_values[] = $args['sync_type'];
        }

        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if (!empty($args['date_from'])) {
            $where_conditions[] = 'started_at >= %s';
            $where_values[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where_conditions[] = 'started_at <= %s';
            $where_values[] = $args['date_to'];
        }

        $where_clause = implode(' AND ', $where_conditions);

        $query = "SELECT * FROM {$this->tables['sync_history']} 
                  WHERE {$where_clause} 
                  ORDER BY {$args['order_by']} {$args['order']} 
                  LIMIT %d OFFSET %d";

        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];

        if (!empty($where_values)) {
            $query = $this->wpdb->prepare($query, $where_values);
        }

        return $this->wpdb->get_results($query);
    }

    // ======================================
    // PRICE HISTORY METHODS
    // ======================================

    /**
     * Insert price history entry.
     *
     * @since    1.0.0
     * @param    array    $data    Price data.
     * @return   int|false    Insert ID on success, false on failure.
     */
    public function insert_price_history($data) {
        $defaults = array(
            'product_id' => 0,
            'asin' => '',
            'price' => null,
            'regular_price' => null,
            'sale_price' => null,
            'currency' => 'USD',
            'availability' => null,
            'prime_eligible' => 0,
            'offers_count' => 0,
            'lowest_price' => null,
            'highest_price' => null,
            'price_change' => null,
            'price_change_percent' => null,
            'recorded_at' => current_time('mysql')
        );

        $data = wp_parse_args($data, $defaults);

        if (empty($data['product_id']) || empty($data['asin'])) {
            return false;
        }

        // Calculate price change if previous price exists
        if (isset($data['price']) && $data['price'] !== null) {
            $previous_price = $this->get_latest_price($data['product_id']);
            if ($previous_price && $previous_price->price !== null) {
                $data['price_change'] = $data['price'] - $previous_price->price;
                if ($previous_price->price > 0) {
                    $data['price_change_percent'] = (($data['price'] - $previous_price->price) / $previous_price->price) * 100;
                }
            }
        }

        $result = $this->wpdb->insert(
            $this->tables['price_history'],
            $data,
            array('%d', '%s', '%f', '%f', '%f', '%s', '%s', '%d', '%d', '%f', '%f', '%f', '%f', '%s')
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Get price history for a product.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $args          Query arguments.
     * @return   array    Array of price entries.
     */
    public function get_price_history($product_id, $args = array()) {
        $defaults = array(
            'limit' => 30,
            'days' => 30,
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $date_from = date('Y-m-d H:i:s', strtotime("-{$args['days']} days"));

        $query = $this->wpdb->prepare("
            SELECT * FROM {$this->tables['price_history']} 
            WHERE product_id = %d AND recorded_at >= %s
            ORDER BY recorded_at {$args['order']} 
            LIMIT %d",
            $product_id,
            $date_from,
            $args['limit']
        );

        return $this->wpdb->get_results($query);
    }

    /**
     * Get latest price for a product.
     *
     * @since    1.0.0
     * @param    int    $product_id    Product ID.
     * @return   object|null    Latest price entry or null.
     */
    public function get_latest_price($product_id) {
        $query = $this->wpdb->prepare("
            SELECT * FROM {$this->tables['price_history']} 
            WHERE product_id = %d 
            ORDER BY recorded_at DESC 
            LIMIT 1",
            $product_id
        );

        return $this->wpdb->get_row($query);
    }

    // ======================================
    // CRON JOBS METHODS
    // ======================================

    /**
     * Insert cron job entry.
     *
     * @since    1.0.0
     * @param    array    $data    Job data.
     * @return   int|false    Insert ID on success, false on failure.
     */
    public function insert_cron_job($data) {
        $defaults = array(
            'job_name' => '',
            'job_type' => '',
            'status' => 'pending',
            'priority' => 10,
            'attempts' => 0,
            'max_attempts' => 3,
            'payload' => null,
            'result' => null,
            'error_message' => null,
            'scheduled_at' => current_time('mysql'),
            'started_at' => null,
            'completed_at' => null,
            'next_attempt_at' => null,
            'created_at' => current_time('mysql')
        );

        $data = wp_parse_args($data, $defaults);

        if (empty($data['job_name']) || empty($data['job_type'])) {
            return false;
        }

        $result = $this->wpdb->insert(
            $this->tables['cron_jobs'],
            $data,
            array('%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Get pending cron jobs.
     *
     * @since    1.0.0
     * @param    int    $limit    Maximum number of jobs to return.
     * @return   array    Array of pending jobs.
     */
    public function get_pending_cron_jobs($limit = 10) {
        $query = $this->wpdb->prepare("
            SELECT * FROM {$this->tables['cron_jobs']} 
            WHERE status = 'pending' 
            AND (next_attempt_at IS NULL OR next_attempt_at <= %s)
            AND attempts < max_attempts
            ORDER BY priority DESC, scheduled_at ASC 
            LIMIT %d",
            current_time('mysql'),
            $limit
        );

        return $this->wpdb->get_results($query);
    }

    /**
     * Update cron job status.
     *
     * @since    1.0.0
     * @param    int      $job_id    Job ID.
     * @param    string   $status    New status.
     * @param    array    $data      Additional data to update.
     * @return   bool     True on success, false on failure.
     */
    public function update_cron_job_status($job_id, $status, $data = array()) {
        $update_data = array_merge($data, array('status' => $status));

        if ($status === 'running') {
            $update_data['started_at'] = current_time('mysql');
        } elseif (in_array($status, array('completed', 'failed'))) {
            $update_data['completed_at'] = current_time('mysql');
        }

        $result = $this->wpdb->update(
            $this->tables['cron_jobs'],
            $update_data,
            array('id' => $job_id),
            null,
            array('%d')
        );

        return $result !== false;
    }

    // ======================================
    // API CACHE METHODS
    // ======================================

    /**
     * Insert or update API cache entry.
     *
     * @since    1.0.0
     * @param    string   $cache_key       Cache key.
     * @param    array    $data            Cache data.
     * @param    int      $expiry_hours    Cache expiry in hours.
     * @return   bool     True on success, false on failure.
     */
    public function set_api_cache($cache_key, $data, $expiry_hours = 24) {
        $cache_data = array(
            'cache_key' => $cache_key,
            'asin' => isset($data['asin']) ? $data['asin'] : null,
            'region' => isset($data['region']) ? $data['region'] : 'US',
            'api_endpoint' => isset($data['endpoint']) ? $data['endpoint'] : '',
            'request_params' => isset($data['request_params']) ? json_encode($data['request_params']) : null,
            'response_data' => isset($data['response_data']) ? json_encode($data['response_data']) : null,
            'response_status' => isset($data['response_status']) ? $data['response_status'] : 200,
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$expiry_hours} hours")),
            'created_at' => current_time('mysql'),
            'accessed_at' => current_time('mysql'),
            'access_count' => 1
        );

        // Check if cache entry exists
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->tables['api_cache']} WHERE cache_key = %s",
                $cache_key
            )
        );

        if ($existing) {
            // Update existing entry
            unset($cache_data['cache_key'], $cache_data['created_at']);
            $cache_data['access_count'] = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT access_count FROM {$this->tables['api_cache']} WHERE cache_key = %s",
                    $cache_key
                )
            ) + 1;

            $result = $this->wpdb->update(
                $this->tables['api_cache'],
                $cache_data,
                array('cache_key' => $cache_key),
                null,
                array('%s')
            );
        } else {
            // Insert new entry
            $result = $this->wpdb->insert(
                $this->tables['api_cache'],
                $cache_data
            );
        }

        return $result !== false;
    }

    /**
     * Get API cache entry.
     *
     * @since    1.0.0
     * @param    string    $cache_key    Cache key.
     * @return   object|null    Cache entry or null if not found/expired.
     */
    public function get_api_cache($cache_key) {
        $query = $this->wpdb->prepare("
            SELECT * FROM {$this->tables['api_cache']} 
            WHERE cache_key = %s AND expires_at > %s",
            $cache_key,
            current_time('mysql')
        );

        $cache_entry = $this->wpdb->get_row($query);

        if ($cache_entry) {
            // Update access count and last accessed time
            $this->wpdb->update(
                $this->tables['api_cache'],
                array(
                    'accessed_at' => current_time('mysql'),
                    'access_count' => $cache_entry->access_count + 1
                ),
                array('id' => $cache_entry->id),
                array('%s', '%d'),
                array('%d')
            );

            // Decode JSON data
            if ($cache_entry->request_params) {
                $cache_entry->request_params = json_decode($cache_entry->request_params, true);
            }
            if ($cache_entry->response_data) {
                $cache_entry->response_data = json_decode($cache_entry->response_data, true);
            }
        }

        return $cache_entry;
    }

    /**
     * Clear expired cache entries.
     *
     * @since    1.0.0
     * @return   int    Number of entries cleared.
     */
    public function clear_expired_cache() {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->tables['api_cache']} WHERE expires_at < %s",
                current_time('mysql')
            )
        );

        return $result ?: 0;
    }

    // ======================================
    // PRODUCT MAPPING METHODS
    // ======================================

    /**
     * Insert or update product mapping.
     *
     * @since    1.0.0
     * @param    array    $data    Mapping data.
     * @return   int|bool    Insert ID on insert, true on update, false on failure.
     */
    public function upsert_product_mapping($data) {
        $defaults = array(
            'product_id' => 0,
            'asin' => '',
            'parent_asin' => null,
            'region' => 'US',
            'affiliate_tag' => null,
            'import_source' => 'manual',
            'sync_enabled' => 1,
            'price_sync_enabled' => 1,
            'last_sync_at' => null,
            'last_price_sync_at' => null,
            'last_update_at' => null,
            'sync_errors' => 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $data = wp_parse_args($data, $defaults);

        if (empty($data['product_id']) || empty($data['asin'])) {
            return false;
        }

        // Check if mapping exists
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->tables['product_mapping']} WHERE product_id = %d AND asin = %s",
                $data['product_id'],
                $data['asin']
            )
        );

        if ($existing) {
            // Update existing mapping
            unset($data['created_at']);
            $result = $this->wpdb->update(
                $this->tables['product_mapping'],
                $data,
                array('product_id' => $data['product_id'], 'asin' => $data['asin']),
                null,
                array('%d', '%s')
            );
            return $result !== false;
        } else {
            // Insert new mapping
            $result = $this->wpdb->insert(
                $this->tables['product_mapping'],
                $data
            );
            return $result ? $this->wpdb->insert_id : false;
        }
    }

    /**
     * Get product mapping by product ID.
     *
     * @since    1.0.0
     * @param    int    $product_id    Product ID.
     * @return   object|null    Mapping data or null.
     */
    public function get_product_mapping($product_id) {
        $query = $this->wpdb->prepare("
            SELECT * FROM {$this->tables['product_mapping']} 
            WHERE product_id = %d",
            $product_id
        );

        return $this->wpdb->get_row($query);
    }

    /**
     * Get product mapping by ASIN.
     *
     * @since    1.0.0
     * @param    string    $asin      ASIN.
     * @param    string    $region    Region (optional).
     * @return   object|null    Mapping data or null.
     */
    public function get_product_mapping_by_asin($asin, $region = null) {
        if ($region) {
            $query = $this->wpdb->prepare("
                SELECT * FROM {$this->tables['product_mapping']} 
                WHERE asin = %s AND region = %s",
                $asin, $region
            );
        } else {
            $query = $this->wpdb->prepare("
                SELECT * FROM {$this->tables['product_mapping']} 
                WHERE asin = %s",
                $asin
            );
        }

        return $this->wpdb->get_row($query);
    }

    // ======================================
    // ERROR LOGS METHODS
    // ======================================

    /**
     * Insert error log entry.
     *
     * @since    1.0.0
     * @param    array    $data    Error data.
     * @return   int|false    Insert ID on success, false on failure.
     */
    public function insert_error_log($data) {
        $defaults = array(
            'error_type' => 'general',
            'error_code' => null,
            'error_message' => '',
            'context' => null,
            'asin' => null,
            'product_id' => null,
            'user_id' => get_current_user_id(),
            'request_data' => null,
            'response_data' => null,
            'stack_trace' => null,
            'severity' => 'error',
            'resolved' => 0,
            'resolved_at' => null,
            'resolved_by' => null,
            'created_at' => current_time('mysql')
        );

        $data = wp_parse_args($data, $defaults);

        if (empty($data['error_message'])) {
            return false;
        }

        // Encode arrays/objects to JSON
        if (is_array($data['request_data']) || is_object($data['request_data'])) {
            $data['request_data'] = json_encode($data['request_data']);
        }
        if (is_array($data['response_data']) || is_object($data['response_data'])) {
            $data['response_data'] = json_encode($data['response_data']);
        }

        $result = $this->wpdb->insert(
            $this->tables['error_logs'],
            $data,
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s')
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Mark error as resolved.
     *
     * @since    1.0.0
     * @param    int    $error_id    Error ID.
     * @param    int    $resolved_by User ID who resolved the error.
     * @return   bool   True on success, false on failure.
     */
    public function resolve_error($error_id, $resolved_by = null) {
        $data = array(
            'resolved' => 1,
            'resolved_at' => current_time('mysql'),
            'resolved_by' => $resolved_by ?: get_current_user_id()
        );

        $result = $this->wpdb->update(
            $this->tables['error_logs'],
            $data,
            array('id' => $error_id),
            array('%d', '%s', '%d'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get error logs.
     *
     * @since    1.0.0
     * @param    array    $args    Query arguments.
     * @return   array    Array of error entries.
     */
    public function get_error_logs($args = array()) {
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'error_type' => null,
            'severity' => null,
            'resolved' => null,
            'date_from' => null,
            'date_to' => null,
            'order_by' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $where_conditions = array('1=1');
        $where_values = array();

        if (!empty($args['error_type'])) {
            $where_conditions[] = 'error_type = %s';
            $where_values[] = $args['error_type'];
        }

        if (!empty($args['severity'])) {
            $where_conditions[] = 'severity = %s';
            $where_values[] = $args['severity'];
        }

        if (isset($args['resolved']) && $args['resolved'] !== null) {
            $where_conditions[] = 'resolved = %d';
            $where_values[] = $args['resolved'];
        }

        if (!empty($args['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }

        $where_clause = implode(' AND ', $where_conditions);

        $query = "SELECT * FROM {$this->tables['error_logs']} 
                  WHERE {$where_clause} 
                  ORDER BY {$args['order_by']} {$args['order']} 
                  LIMIT %d OFFSET %d";

        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];

        if (!empty($where_values)) {
            $query = $this->wpdb->prepare($query, $where_values);
        }

        return $this->wpdb->get_results($query);
    }

    // ======================================
    // UTILITY METHODS
    // ======================================

    /**
     * Get database statistics.
     *
     * @since    1.0.0
     * @return   array    Database statistics.
     */
    public function get_statistics() {
        $stats = array();

        foreach ($this->tables as $key => $table_name) {
            $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            $stats[$key] = intval($count);
        }

        return $stats;
    }

    /**
     * Clean up old records from all tables.
     *
     * @since    1.0.0
     * @param    int    $days    Number of days to keep.
     * @return   array  Cleanup results.
     */
    public function cleanup_old_records($days = 30) {
        $results = array();
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Clean up import logs
        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->tables['import_logs']} WHERE created_at < %s",
                $cutoff_date
            )
        );
        $results['import_logs'] = $deleted ?: 0;

        // Clean up sync history
        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->tables['sync_history']} WHERE started_at < %s AND status = 'completed'",
                $cutoff_date
            )
        );
        $results['sync_history'] = $deleted ?: 0;

        // Clean up completed cron jobs
        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->tables['cron_jobs']} WHERE completed_at < %s AND status = 'completed'",
                $cutoff_date
            )
        );
        $results['cron_jobs'] = $deleted ?: 0;

        // Clean up expired cache
        $deleted = $this->clear_expired_cache();
        $results['api_cache'] = $deleted;

        // Clean up resolved error logs
        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->tables['error_logs']} WHERE resolved = 1 AND resolved_at < %s",
                $cutoff_date
            )
        );
        $results['error_logs'] = $deleted ?: 0;

        return $results;
    }

    /**
     * Check if tables exist.
     *
     * @since    1.0.0
     * @return   array    Array of table existence status.
     */
    public function check_tables_exist() {
        $status = array();

        foreach ($this->tables as $key => $table_name) {
            $exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $table_name
                )
            );
            $status[$key] = !empty($exists);
        }

        return $status;
    }

    /**
     * Get last database error.
     *
     * @since    1.0.0
     * @return   string    Last error message.
     */
    public function get_last_error() {
        return $this->wpdb->last_error;
    }
}