<?php

/**
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete plugin options
 */
function amazon_product_importer_delete_options() {
    $options_to_delete = array(
        'amazon_importer_api_access_key_id',
        'amazon_importer_api_secret_access_key',
        'amazon_importer_api_associate_tag',
        'amazon_importer_api_region',
        'amazon_importer_ams_product_thumbnail_size',
        'amazon_importer_product_name_cron',
        'amazon_importer_product_category_cron',
        'amazon_importer_product_sku_cron',
        'amazon_importer_price_sync_enabled',
        'amazon_importer_price_sync_frequency',
        'amazon_importer_category_min_depth',
        'amazon_importer_category_max_depth',
        'amazon_importer_import_reviews',
        'amazon_importer_debug_mode',
        'amazon_importer_stats'
    );

    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
}

/**
 * Delete custom database tables
 */
function amazon_product_importer_delete_tables() {
    global $wpdb;

    $tables_to_delete = array(
        $wpdb->prefix . 'amazon_import_logs',
        $wpdb->prefix . 'amazon_sync_queue'
    );

    foreach ($tables_to_delete as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

/**
 * Clear scheduled cron events
 */
function amazon_product_importer_clear_cron() {
    wp_clear_scheduled_hook('api_price_sync_hourly');
    wp_clear_scheduled_hook('api_product_update_daily');
}

/**
 * Delete transients
 */
function amazon_product_importer_delete_transients() {
    global $wpdb;
    
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_amazon_api_%' 
         OR option_name LIKE '_transient_timeout_amazon_api_%'"
    );
}

/**
 * Remove Amazon metadata from products (optional)
 */
function amazon_product_importer_cleanup_product_meta() {
    global $wpdb;

    // Get user confirmation for this action
    $remove_meta = get_option('amazon_importer_remove_meta_on_uninstall', false);
    
    if ($remove_meta) {
        $amazon_meta_keys = array(
            '_amazon_asin',
            '_amazon_region',
            '_amazon_associate_tag',
            '_amazon_import_date',
            '_amazon_last_sync',
            '_amazon_sync_enabled',
            '_amazon_images_last_update'
        );

        foreach ($amazon_meta_keys as $meta_key) {
            $wpdb->delete($wpdb->postmeta, array('meta_key' => $meta_key));
        }
    }
}

// Execute cleanup functions
amazon_product_importer_delete_options();
amazon_product_importer_delete_tables();
amazon_product_importer_clear_cron();
amazon_product_importer_delete_transients();
amazon_product_importer_cleanup_product_meta();