<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Security check - make sure this is called from WordPress admin
if (!defined('ABSPATH')) {
    exit;
}

// Check if user has the capability to uninstall plugins
if (!current_user_can('activate_plugins')) {
    exit;
}

// Additional security check
check_admin_referer('bulk-plugins');

// Make sure it's our plugin being uninstalled
if (__FILE__ != WP_UNINSTALL_PLUGIN) {
    exit;
}

/**
 * Define constants if not already defined
 */
if (!defined('AMAZON_PRODUCT_IMPORTER_TABLE_IMPORTS')) {
    define('AMAZON_PRODUCT_IMPORTER_TABLE_IMPORTS', 'amazon_product_imports');
}

if (!defined('AMAZON_PRODUCT_IMPORTER_TABLE_SYNC_LOG')) {
    define('AMAZON_PRODUCT_IMPORTER_TABLE_SYNC_LOG', 'amazon_sync_log');
}

/**
 * Get the uninstall options
 * Check if user wants to keep data or remove everything
 */
$keep_data = get_option('amazon_product_importer_keep_data_on_uninstall', false);

// If user chose to keep data, exit early
if ($keep_data) {
    return;
}

/**
 * Delete all plugin options from wp_options table
 */
function amazon_product_importer_delete_options() {
    global $wpdb;
    
    // List of all plugin options
    $options_to_delete = array(
        // API Settings
        'amazon_product_importer_access_key_id',
        'amazon_product_importer_secret_access_key',
        'amazon_product_importer_associate_tag',
        'amazon_product_importer_marketplace',
        'amazon_product_importer_region',
        
        // Import Settings
        'amazon_product_importer_default_status',
        'amazon_product_importer_default_visibility',
        'amazon_product_importer_thumbnail_size',
        'amazon_product_importer_import_images',
        'amazon_product_importer_max_images',
        
        // Sync Settings
        'amazon_product_importer_auto_sync_enabled',
        'amazon_product_importer_sync_interval',
        'amazon_product_importer_sync_price',
        'amazon_product_importer_sync_stock',
        'amazon_product_importer_sync_title',
        'amazon_product_importer_sync_description',
        'amazon_product_importer_sync_images',
        
        // Category Settings
        'amazon_product_importer_auto_categories',
        'amazon_product_importer_category_min_depth',
        'amazon_product_importer_category_max_depth',
        'amazon_product_importer_default_category',
        
        // Cron Settings
        'amazon_product_importer_product_name_cron',
        'amazon_product_importer_product_category_cron',
        'amazon_product_importer_product_sku_cron',
        
        // System Settings
        'amazon_product_importer_version',
        'amazon_product_importer_db_version',
        'amazon_product_importer_activation_date',
        'amazon_product_importer_keep_data_on_uninstall',
        'amazon_product_importer_debug_mode',
        'amazon_product_importer_log_level',
        
        // Rate Limiting
        'amazon_product_importer_api_rate_limit',
        'amazon_product_importer_last_api_call',
        'amazon_product_importer_api_call_count',
        
        // Cache Settings
        'amazon_product_importer_cache_duration',
        'amazon_product_importer_enable_cache',
    );
    
    // Delete each option
    foreach ($options_to_delete as $option) {
        delete_option($option);
        
        // For multisite, delete from all sites
        if (is_multisite()) {
            delete_site_option($option);
        }
    }
    
    // Delete any options with our prefix that might have been missed
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'amazon_product_importer_%'));
    
    if (is_multisite()) {
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", 'amazon_product_importer_%'));
    }
}

/**
 * Delete custom database tables
 */
function amazon_product_importer_delete_tables() {
    global $wpdb;
    
    $tables_to_delete = array(
        $wpdb->prefix . AMAZON_PRODUCT_IMPORTER_TABLE_IMPORTS,
        $wpdb->prefix . AMAZON_PRODUCT_IMPORTER_TABLE_SYNC_LOG,
    );
    
    foreach ($tables_to_delete as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

/**
 * Delete all product meta data created by the plugin
 */
function amazon_product_importer_delete_product_meta() {
    global $wpdb;
    
    $meta_keys_to_delete = array(
        '_amazon_asin',
        '_amazon_parent_asin',
        '_amazon_region',
        '_amazon_associate_tag',
        '_amazon_imported_date',
        '_amazon_last_sync',
        '_amazon_sync_enabled',
        '_amazon_original_price',
        '_amazon_original_sale_price',
        '_amazon_original_title',
        '_amazon_original_description',
        '_amazon_browse_nodes',
        '_amazon_brand',
        '_amazon_model',
        '_amazon_features',
        '_amazon_dimensions',
        '_amazon_weight',
        '_amazon_availability',
        '_amazon_image_urls',
        '_amazon_variation_data',
    );
    
    foreach ($meta_keys_to_delete as $meta_key) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
            $meta_key
        ));
    }
    
    // Delete any meta with our prefix
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
        '_amazon_%'
    ));
}

/**
 * Delete transients and cached data
 */
function amazon_product_importer_delete_transients() {
    global $wpdb;
    
    // Delete specific transients
    $transients = array(
        'amazon_product_importer_api_cache',
        'amazon_product_importer_categories_cache',
        'amazon_product_importer_browse_nodes',
        'amazon_product_importer_rate_limit',
    );
    
    foreach ($transients as $transient) {
        delete_transient($transient);
        delete_site_transient($transient);
    }
    
    // Delete all transients with our prefix
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        '_transient_amazon_product_importer_%',
        '_transient_timeout_amazon_product_importer_%'
    ));
    
    if (is_multisite()) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
            '_site_transient_amazon_product_importer_%',
            '_site_transient_timeout_amazon_product_importer_%'
        ));
    }
}

/**
 * Remove scheduled cron jobs
 */
function amazon_product_importer_remove_cron_jobs() {
    // List of all cron hooks used by the plugin
    $cron_hooks = array(
        'amazon_product_importer_sync_prices',
        'amazon_product_importer_sync_stock',
        'amazon_product_importer_update_products',
        'amazon_product_importer_cleanup_logs',
        'amazon_product_importer_cache_cleanup',
    );
    
    foreach ($cron_hooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }
    
    // Remove any scheduled events for specific products
    $cron_array = _get_cron_array();
    if (!empty($cron_array)) {
        foreach ($cron_array as $timestamp => $cron) {
            foreach ($cron as $hook => $events) {
                if (strpos($hook, 'amazon_product_importer_') === 0) {
                    foreach ($events as $key => $event) {
                        wp_unschedule_event($timestamp, $hook, $event['args']);
                    }
                }
            }
        }
    }
}

/**
 * Remove custom capabilities
 */
function amazon_product_importer_remove_capabilities() {
    $roles = array('administrator', 'shop_manager', 'editor');
    $capabilities = array(
        'manage_amazon_product_importer',
        'import_amazon_products',
        'sync_amazon_products',
        'view_amazon_import_logs',
    );
    
    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($capabilities as $cap) {
                $role->remove_cap($cap);
            }
        }
    }
}

/**
 * Delete uploaded Amazon images (optional - might want to keep them)
 */
function amazon_product_importer_delete_amazon_images() {
    global $wpdb;
    
    // This is optional - you might want to keep the images
    // Get all attachments that were imported from Amazon
    $amazon_images = $wpdb->get_results($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
        '_amazon_imported_image'
    ));
    
    foreach ($amazon_images as $image) {
        wp_delete_attachment($image->post_id, true);
    }
}

/**
 * Clean up user meta if any
 */
function amazon_product_importer_delete_user_meta() {
    global $wpdb;
    
    $user_meta_keys = array(
        'amazon_product_importer_preferences',
        'amazon_product_importer_last_import',
        'amazon_product_importer_import_count',
    );
    
    foreach ($user_meta_keys as $meta_key) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
            $meta_key
        ));
    }
}

/**
 * Log the uninstallation
 */
function amazon_product_importer_log_uninstall() {
    error_log('[Amazon Product Importer] Plugin uninstalled and data cleaned up.');
}

/**
 * Main uninstall process
 */
function amazon_product_importer_uninstall() {
    // Remove cron jobs first to prevent any running tasks
    amazon_product_importer_remove_cron_jobs();
    
    // Delete plugin options
    amazon_product_importer_delete_options();
    
    // Delete custom tables
    amazon_product_importer_delete_tables();
    
    // Delete product meta data
    amazon_product_importer_delete_product_meta();
    
    // Delete transients and cache
    amazon_product_importer_delete_transients();
    
    // Remove capabilities
    amazon_product_importer_remove_capabilities();
    
    // Delete user meta
    amazon_product_importer_delete_user_meta();
    
    // Optionally delete Amazon images (uncomment if desired)
    // amazon_product_importer_delete_amazon_images();
    
    // Clear any object cache
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Log the uninstallation
    amazon_product_importer_log_uninstall();
}

/**
 * For multisite installations, handle network-wide uninstall
 */
if (is_multisite()) {
    // Get all blog IDs
    $blog_ids = get_sites(array('fields' => 'ids'));
    
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        amazon_product_importer_uninstall();
        restore_current_blog();
    }
    
    // Delete network-wide options
    delete_site_option('amazon_product_importer_network_settings');
} else {
    // Single site uninstall
    amazon_product_importer_uninstall();
}

/**
 * Final cleanup - remove any remaining traces
 */
flush_rewrite_rules();

// Clear opcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
}