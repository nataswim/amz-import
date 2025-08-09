<?php

/**
 * Fired during plugin deactivation.
 */
class Amazon_Product_Importer_Deactivator {

    /**
     * Plugin deactivation.
     */
    public static function deactivate() {
        // Clear scheduled cron events
        self::clear_cron_events();
        
        // Clear transients
        self::clear_transients();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Clear all scheduled cron events
     */
    private static function clear_cron_events() {
        wp_clear_scheduled_hook('api_price_sync_hourly');
        wp_clear_scheduled_hook('api_product_update_daily');
    }

    /**
     * Clear plugin transients
     */
    private static function clear_transients() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_amazon_api_%' 
             OR option_name LIKE '_transient_timeout_amazon_api_%'"
        );
    }
}