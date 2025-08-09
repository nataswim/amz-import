<?php

/**
 * Fired during plugin activation.
 */
class Amazon_Product_Importer_Activator {

    /**
     * Plugin activation.
     */
    public static function activate() {
        // Create database tables if needed
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Schedule cron events
        self::schedule_cron_events();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create custom database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table for import logs
        $table_name = $wpdb->prefix . 'amazon_import_logs';

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            asin varchar(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY asin (asin),
            KEY product_id (product_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Table for sync queue
        $table_name = $wpdb->prefix . 'amazon_sync_queue';

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            asin varchar(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            sync_type varchar(50) NOT NULL,
            priority int(11) DEFAULT 5,
            scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime NULL,
            status varchar(20) DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY asin (asin),
            KEY status (status),
            KEY sync_type (sync_type)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $defaults = array(
            'api_access_key_id' => '',
            'api_secret_access_key' => '',
            'api_associate_tag' => '',
            'api_region' => 'com',
            'ams_product_thumbnail_size' => 'large',
            'product_name_cron' => false,
            'product_category_cron' => false,
            'product_sku_cron' => false,
            'price_sync_enabled' => true,
            'price_sync_frequency' => 'hourly',
            'category_min_depth' => 1,
            'category_max_depth' => 3,
            'import_reviews' => false,
            'debug_mode' => false
        );

        foreach ($defaults as $key => $value) {
            if (get_option('amazon_importer_' . $key) === false) {
                add_option('amazon_importer_' . $key, $value);
            }
        }
    }

    /**
     * Schedule cron events
     */
    private static function schedule_cron_events() {
        if (!wp_next_scheduled('api_price_sync_hourly')) {
            wp_schedule_event(time(), 'hourly', 'api_price_sync_hourly');
        }

        if (!wp_next_scheduled('api_product_update_daily')) {
            wp_schedule_event(time(), 'daily', 'api_product_update_daily');
        }
    }
}