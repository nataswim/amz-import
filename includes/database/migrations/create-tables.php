<?php

/**
 * Database migration script for Amazon Product Importer plugin.
 *
 * @link       https://mycreanet.fr
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/database/migrations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Amazon Product Importer Database Migration
 *
 * Handles creation and updates of custom database tables.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/database/migrations
 * @author     Your Name <https://mycreanet.fr>
 */
class Amazon_Product_Importer_Create_Tables {

    /**
     * Database version for migration tracking.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $db_version    Current database version.
     */
    private $db_version = '1.0.0';

    /**
     * WordPress database instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      wpdb    $wpdb    WordPress database instance.
     */
    private $wpdb;

    /**
     * Database charset collation.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $charset_collate    Database charset collation.
     */
    private $charset_collate;

    /**
     * Initialize the migration class.
     *
     * @since    1.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
    }

    /**
     * Run the database migration.
     *
     * @since    1.0.0
     * @return   bool    True on success, false on failure.
     */
    public function migrate() {
        try {
            $current_version = get_option('ams_db_version', '0.0.0');
            
            if (version_compare($current_version, $this->db_version, '<')) {
                $this->create_tables();
                $this->create_indexes();
                $this->insert_default_data();
                
                // Update database version
                update_option('ams_db_version', $this->db_version);
                
                return true;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('Amazon Product Importer DB Migration Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create all required database tables.
     *
     * @since    1.0.0
     */
    private function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Create import logs table
        $this->create_import_logs_table();
        
        // Create sync history table
        $this->create_sync_history_table();
        
        // Create price history table
        $this->create_price_history_table();
        
        // Create cron jobs table
        $this->create_cron_jobs_table();
        
        // Create API cache table
        $this->create_api_cache_table();
        
        // Create product mapping table
        $this->create_product_mapping_table();
        
        // Create error logs table
        $this->create_error_logs_table();
    }

    /**
     * Create import logs table.
     *
     * @since    1.0.0
     */
    private function create_import_logs_table() {
        $table_name = $this->wpdb->prefix . 'ams_import_logs';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            asin varchar(20) NOT NULL,
            product_id bigint(20) unsigned DEFAULT NULL,
            action varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            message text DEFAULT NULL,
            api_response longtext DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            import_source varchar(50) DEFAULT 'manual',
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_asin (asin),
            KEY idx_product_id (product_id),
            KEY idx_status (status),
            KEY idx_action (action),
            KEY idx_created_at (created_at)
        ) $this->charset_collate;";

        dbDelta($sql);
    }

    /**
     * Create sync history table.
     *
     * @since    1.0.0
     */
    private function create_sync_history_table() {
        $table_name = $this->wpdb->prefix . 'ams_sync_history';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sync_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            products_processed int(11) DEFAULT 0,
            products_success int(11) DEFAULT 0,
            products_failed int(11) DEFAULT 0,
            products_skipped int(11) DEFAULT 0,
            execution_time int(11) DEFAULT 0,
            memory_usage bigint(20) DEFAULT 0,
            error_message text DEFAULT NULL,
            sync_settings longtext DEFAULT NULL,
            started_at datetime NOT NULL,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_sync_type (sync_type),
            KEY idx_status (status),
            KEY idx_started_at (started_at)
        ) $this->charset_collate;";

        dbDelta($sql);
    }

    /**
     * Create price history table.
     *
     * @since    1.0.0
     */
    private function create_price_history_table() {
        $table_name = $this->wpdb->prefix . 'ams_price_history';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            asin varchar(20) NOT NULL,
            price decimal(10,2) DEFAULT NULL,
            regular_price decimal(10,2) DEFAULT NULL,
            sale_price decimal(10,2) DEFAULT NULL,
            currency varchar(3) DEFAULT 'USD',
            availability varchar(50) DEFAULT NULL,
            prime_eligible tinyint(1) DEFAULT 0,
            offers_count int(11) DEFAULT 0,
            lowest_price decimal(10,2) DEFAULT NULL,
            highest_price decimal(10,2) DEFAULT NULL,
            price_change decimal(10,2) DEFAULT NULL,
            price_change_percent decimal(5,2) DEFAULT NULL,
            recorded_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_product_id (product_id),
            KEY idx_asin (asin),
            KEY idx_recorded_at (recorded_at),
            KEY idx_price (price),
            UNIQUE KEY unique_product_time (product_id, recorded_at)
        ) $this->charset_collate;";

        dbDelta($sql);
    }

    /**
     * Create cron jobs table.
     *
     * @since    1.0.0
     */
    private function create_cron_jobs_table() {
        $table_name = $this->wpdb->prefix . 'ams_cron_jobs';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_name varchar(100) NOT NULL,
            job_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            priority int(11) DEFAULT 10,
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            payload longtext DEFAULT NULL,
            result longtext DEFAULT NULL,
            error_message text DEFAULT NULL,
            scheduled_at datetime NOT NULL,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            next_attempt_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_job_type (job_type),
            KEY idx_scheduled_at (scheduled_at),
            KEY idx_priority (priority),
            KEY idx_next_attempt (next_attempt_at)
        ) $this->charset_collate;";

        dbDelta($sql);
    }

    /**
     * Create API cache table.
     *
     * @since    1.0.0
     */
    private function create_api_cache_table() {
        $table_name = $this->wpdb->prefix . 'ams_api_cache';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            asin varchar(20) DEFAULT NULL,
            region varchar(10) DEFAULT 'US',
            api_endpoint varchar(100) NOT NULL,
            request_params longtext DEFAULT NULL,
            response_data longtext DEFAULT NULL,
            response_status int(11) DEFAULT 200,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL,
            accessed_at datetime NOT NULL,
            access_count int(11) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY unique_cache_key (cache_key),
            KEY idx_asin (asin),
            KEY idx_region (region),
            KEY idx_expires_at (expires_at),
            KEY idx_api_endpoint (api_endpoint)
        ) $this->charset_collate;";

        dbDelta($sql);
    }

    /**
     * Create product mapping table.
     *
     * @since    1.0.0
     */
    private function create_product_mapping_table() {
        $table_name = $this->wpdb->prefix . 'ams_product_mapping';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            asin varchar(20) NOT NULL,
            parent_asin varchar(20) DEFAULT NULL,
            region varchar(10) NOT NULL DEFAULT 'US',
            affiliate_tag varchar(50) DEFAULT NULL,
            import_source varchar(50) DEFAULT 'manual',
            sync_enabled tinyint(1) DEFAULT 1,
            price_sync_enabled tinyint(1) DEFAULT 1,
            last_sync_at datetime DEFAULT NULL,
            last_price_sync_at datetime DEFAULT NULL,
            last_update_at datetime DEFAULT NULL,
            sync_errors int(11) DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_product_asin (product_id, asin),
            KEY idx_asin (asin),
            KEY idx_parent_asin (parent_asin),
            KEY idx_region (region),
            KEY idx_sync_enabled (sync_enabled),
            KEY idx_last_sync (last_sync_at)
        ) $this->charset_collate;";

        dbDelta($sql);
    }

    /**
     * Create error logs table.
     *
     * @since    1.0.0
     */
    private function create_error_logs_table() {
        $table_name = $this->wpdb->prefix . 'ams_error_logs';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            error_type varchar(50) NOT NULL,
            error_code varchar(50) DEFAULT NULL,
            error_message text NOT NULL,
            context varchar(100) DEFAULT NULL,
            asin varchar(20) DEFAULT NULL,
            product_id bigint(20) unsigned DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            request_data longtext DEFAULT NULL,
            response_data longtext DEFAULT NULL,
            stack_trace longtext DEFAULT NULL,
            severity varchar(20) DEFAULT 'error',
            resolved tinyint(1) DEFAULT 0,
            resolved_at datetime DEFAULT NULL,
            resolved_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_error_type (error_type),
            KEY idx_error_code (error_code),
            KEY idx_asin (asin),
            KEY idx_product_id (product_id),
            KEY idx_severity (severity),
            KEY idx_resolved (resolved),
            KEY idx_created_at (created_at)
        ) $this->charset_collate;";

        dbDelta($sql);
    }

    /**
     * Create database indexes for better performance.
     *
     * @since    1.0.0
     */
    private function create_indexes() {
        // Additional composite indexes for better query performance
        
        // Import logs composite indexes
        $this->create_index_if_not_exists(
            'ams_import_logs',
            'idx_asin_status',
            'asin, status'
        );
        
        $this->create_index_if_not_exists(
            'ams_import_logs',
            'idx_product_action',
            'product_id, action'
        );

        // Price history composite indexes
        $this->create_index_if_not_exists(
            'ams_price_history',
            'idx_asin_date',
            'asin, recorded_at'
        );

        // Product mapping composite indexes
        $this->create_index_if_not_exists(
            'ams_product_mapping',
            'idx_region_sync',
            'region, sync_enabled'
        );

        // Cron jobs composite indexes
        $this->create_index_if_not_exists(
            'ams_cron_jobs',
            'idx_status_priority',
            'status, priority'
        );
    }

    /**
     * Create index if it doesn't exist.
     *
     * @since    1.0.0
     * @param    string    $table_name    Table name without prefix.
     * @param    string    $index_name    Index name.
     * @param    string    $columns       Columns for the index.
     */
    private function create_index_if_not_exists($table_name, $index_name, $columns) {
        $full_table_name = $this->wpdb->prefix . $table_name;
        
        $index_exists = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SHOW INDEX FROM {$full_table_name} WHERE Key_name = %s",
                $index_name
            )
        );

        if (empty($index_exists)) {
            $this->wpdb->query(
                "ALTER TABLE {$full_table_name} ADD INDEX {$index_name} ({$columns})"
            );
        }
    }

    /**
     * Insert default data into tables.
     *
     * @since    1.0.0
     */
    private function insert_default_data() {
        // Insert default cron job configurations
        $this->insert_default_cron_jobs();
        
        // Insert default cache cleanup job
        $this->insert_default_cleanup_jobs();
    }

    /**
     * Insert default cron job configurations.
     *
     * @since    1.0.0
     */
    private function insert_default_cron_jobs() {
        $table_name = $this->wpdb->prefix . 'ams_cron_jobs';
        
        $default_jobs = array(
            array(
                'job_name' => 'Daily Price Sync',
                'job_type' => 'price_sync',
                'status' => 'scheduled',
                'priority' => 10,
                'payload' => json_encode(array('type' => 'daily', 'batch_size' => 50)),
                'scheduled_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
                'created_at' => current_time('mysql')
            ),
            array(
                'job_name' => 'Weekly Product Update',
                'job_type' => 'product_update',
                'status' => 'scheduled',
                'priority' => 5,
                'payload' => json_encode(array('type' => 'weekly', 'full_update' => true)),
                'scheduled_at' => date('Y-m-d H:i:s', strtotime('+1 week')),
                'created_at' => current_time('mysql')
            )
        );

        foreach ($default_jobs as $job) {
            $existing = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM {$table_name} WHERE job_name = %s",
                    $job['job_name']
                )
            );

            if (!$existing) {
                $this->wpdb->insert($table_name, $job);
            }
        }
    }

    /**
     * Insert default cleanup jobs.
     *
     * @since    1.0.0
     */
    private function insert_default_cleanup_jobs() {
        $table_name = $this->wpdb->prefix . 'ams_cron_jobs';
        
        $cleanup_jobs = array(
            array(
                'job_name' => 'Cache Cleanup',
                'job_type' => 'cleanup',
                'status' => 'scheduled',
                'priority' => 1,
                'payload' => json_encode(array('target' => 'cache', 'older_than_days' => 7)),
                'scheduled_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
                'created_at' => current_time('mysql')
            ),
            array(
                'job_name' => 'Log Cleanup',
                'job_type' => 'cleanup',
                'status' => 'scheduled',
                'priority' => 1,
                'payload' => json_encode(array('target' => 'logs', 'older_than_days' => 30)),
                'scheduled_at' => date('Y-m-d H:i:s', strtotime('+1 week')),
                'created_at' => current_time('mysql')
            )
        );

        foreach ($cleanup_jobs as $job) {
            $existing = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM {$table_name} WHERE job_name = %s",
                    $job['job_name']
                )
            );

            if (!$existing) {
                $this->wpdb->insert($table_name, $job);
            }
        }
    }

    /**
     * Drop all plugin tables (for uninstall).
     *
     * @since    1.0.0
     */
    public function drop_tables() {
        $tables = array(
            'ams_import_logs',
            'ams_sync_history',
            'ams_price_history',
            'ams_cron_jobs',
            'ams_api_cache',
            'ams_product_mapping',
            'ams_error_logs'
        );

        foreach ($tables as $table) {
            $table_name = $this->wpdb->prefix . $table;
            $this->wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        }

        // Remove database version option
        delete_option('ams_db_version');
    }

    /**
     * Get database statistics.
     *
     * @since    1.0.0
     * @return   array    Database statistics.
     */
    public function get_database_stats() {
        $stats = array();
        
        $tables = array(
            'ams_import_logs' => 'Import Logs',
            'ams_sync_history' => 'Sync History',
            'ams_price_history' => 'Price History',
            'ams_cron_jobs' => 'Cron Jobs',
            'ams_api_cache' => 'API Cache',
            'ams_product_mapping' => 'Product Mapping',
            'ams_error_logs' => 'Error Logs'
        );

        foreach ($tables as $table => $label) {
            $table_name = $this->wpdb->prefix . $table;
            $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            $stats[$table] = array(
                'label' => $label,
                'count' => intval($count)
            );
        }

        return $stats;
    }

    /**
     * Optimize database tables.
     *
     * @since    1.0.0
     * @return   bool    True on success.
     */
    public function optimize_tables() {
        $tables = array(
            'ams_import_logs',
            'ams_sync_history',
            'ams_price_history',
            'ams_cron_jobs',
            'ams_api_cache',
            'ams_product_mapping',
            'ams_error_logs'
        );

        $success = true;

        foreach ($tables as $table) {
            $table_name = $this->wpdb->prefix . $table;
            $result = $this->wpdb->query("OPTIMIZE TABLE {$table_name}");
            
            if ($result === false) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Clean up old records from all tables.
     *
     * @since    1.0.0
     * @param    int    $days    Number of days to keep.
     * @return   int    Number of records deleted.
     */
    public function cleanup_old_records($days = 30) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $total_deleted = 0;

        // Clean up import logs
        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->wpdb->prefix}ams_import_logs WHERE created_at < %s",
                $cutoff_date
            )
        );
        $total_deleted += $deleted;

        // Clean up sync history
        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->wpdb->prefix}ams_sync_history WHERE started_at < %s",
                $cutoff_date
            )
        );
        $total_deleted += $deleted;

        // Clean up price history (keep more records)
        $price_cutoff = date('Y-m-d H:i:s', strtotime("-90 days"));
        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->wpdb->prefix}ams_price_history WHERE recorded_at < %s",
                $price_cutoff
            )
        );
        $total_deleted += $deleted;

        // Clean up completed cron jobs
        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->wpdb->prefix}ams_cron_jobs WHERE status = 'completed' AND completed_at < %s",
                $cutoff_date
            )
        );
        $total_deleted += $deleted;

        // Clean up expired cache
        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->wpdb->prefix}ams_api_cache WHERE expires_at < %s",
                current_time('mysql')
            )
        );
        $total_deleted += $deleted;

        // Clean up resolved error logs
        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->wpdb->prefix}ams_error_logs WHERE resolved = 1 AND resolved_at < %s",
                $cutoff_date
            )
        );
        $total_deleted += $deleted;

        return $total_deleted;
    }
}