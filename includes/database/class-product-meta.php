<?php

/**
 * The product meta management functionality of the plugin.
 *
 * @link       https://mycreanet.fr
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/database
 */

/**
 * The product meta management functionality of the plugin.
 *
 * Handles all Amazon-specific product metadata operations.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/database
 * @author     Your Name <https://mycreanet.fr>
 */
class Amazon_Product_Importer_Product_Meta {

    /**
     * Meta key prefix for all Amazon-related metadata.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $meta_prefix    Meta key prefix.
     */
    private $meta_prefix = '_amazon_';

    /**
     * Available meta keys and their default values.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $meta_keys    Available meta keys.
     */
    private $meta_keys = array(
        // Basic Amazon Product Info
        'asin' => '',
        'parent_asin' => '',
        'region' => 'US',
        'affiliate_tag' => '',
        'product_url' => '',
        'detail_page_url' => '',
        
        // Import/Sync Information
        'import_date' => '',
        'import_source' => 'manual',
        'last_sync' => '',
        'last_price_sync' => '',
        'last_product_update' => '',
        'sync_enabled' => true,
        'price_sync_enabled' => true,
        'auto_update_enabled' => true,
        
        // Product Status
        'availability' => '',
        'availability_type' => '',
        'prime_eligible' => false,
        'unavailable_since' => '',
        'sync_errors' => 0,
        'last_error' => '',
        
        // Amazon Specific Data
        'browse_node_id' => '',
        'browse_node_name' => '',
        'brand' => '',
        'manufacturer' => '',
        'model' => '',
        'part_number' => '',
        'package_dimensions' => '',
        'item_dimensions' => '',
        'weight' => '',
        
        // Pricing Information
        'original_price' => '',
        'original_currency' => 'USD',
        'price_history' => array(),
        'lowest_price_30days' => '',
        'highest_price_30days' => '',
        'price_change_percent' => '',
        
        // Variations
        'variation_theme' => '',
        'variation_dimensions' => '',
        'parent_product_id' => '',
        'child_asins' => array(),
        
        // Images
        'original_images' => array(),
        'image_mapping' => array(),
        'primary_image_url' => '',
        
        // Reviews and Ratings
        'customer_reviews_url' => '',
        'rating' => '',
        'review_count' => '',
        
        // API Response Cache
        'api_response_cache' => '',
        'cache_timestamp' => '',
        'cache_expires' => '',
        
        // Custom Settings
        'custom_mapping' => array(),
        'import_notes' => '',
        'tags' => array()
    );

    /**
     * WordPress database instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      wpdb    $wpdb    WordPress database instance.
     */
    private $wpdb;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Get full meta key with prefix.
     *
     * @since    1.0.0
     * @param    string    $key    Meta key without prefix.
     * @return   string    Full meta key with prefix.
     */
    private function get_meta_key($key) {
        return $this->meta_prefix . $key;
    }

    /**
     * Set Amazon meta data for a product.
     *
     * @since    1.0.0
     * @param    int       $product_id    Product ID.
     * @param    string    $key           Meta key (without prefix).
     * @param    mixed     $value         Meta value.
     * @param    bool      $unique        Whether the meta should be unique.
     * @return   bool      True on success, false on failure.
     */
    public function set_meta($product_id, $key, $value, $unique = true) {
        if (!$this->is_valid_meta_key($key)) {
            return false;
        }

        $meta_key = $this->get_meta_key($key);
        
        // Serialize arrays and objects
        if (is_array($value) || is_object($value)) {
            $value = maybe_serialize($value);
        }

        // Update or add meta
        if ($unique) {
            $result = update_post_meta($product_id, $meta_key, $value);
        } else {
            $result = add_post_meta($product_id, $meta_key, $value, false);
        }

        // Log the meta update for audit purposes
        $this->log_meta_change($product_id, $key, $value, 'set');

        return $result !== false;
    }

    /**
     * Get Amazon meta data for a product.
     *
     * @since    1.0.0
     * @param    int       $product_id    Product ID.
     * @param    string    $key           Meta key (without prefix).
     * @param    bool      $single        Whether to return a single value.
     * @return   mixed     Meta value or default value if not found.
     */
    public function get_meta($product_id, $key, $single = true) {
        if (!$this->is_valid_meta_key($key)) {
            return isset($this->meta_keys[$key]) ? $this->meta_keys[$key] : '';
        }

        $meta_key = $this->get_meta_key($key);
        $value = get_post_meta($product_id, $meta_key, $single);

        // Return default value if empty
        if (empty($value) && isset($this->meta_keys[$key])) {
            return $this->meta_keys[$key];
        }

        // Unserialize if needed
        return maybe_unserialize($value);
    }

    /**
     * Delete Amazon meta data for a product.
     *
     * @since    1.0.0
     * @param    int       $product_id    Product ID.
     * @param    string    $key           Meta key (without prefix).
     * @param    mixed     $value         Meta value to delete (optional).
     * @return   bool      True on success, false on failure.
     */
    public function delete_meta($product_id, $key, $value = '') {
        if (!$this->is_valid_meta_key($key)) {
            return false;
        }

        $meta_key = $this->get_meta_key($key);
        $result = delete_post_meta($product_id, $meta_key, $value);

        // Log the meta deletion
        $this->log_meta_change($product_id, $key, $value, 'delete');

        return $result;
    }

    /**
     * Set multiple Amazon meta data for a product.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $meta_data     Array of key-value pairs.
     * @return   bool     True if all meta were set successfully.
     */
    public function set_multiple_meta($product_id, $meta_data) {
        $success = true;

        foreach ($meta_data as $key => $value) {
            if (!$this->set_meta($product_id, $key, $value)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get multiple Amazon meta data for a product.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $keys          Array of meta keys to retrieve.
     * @return   array    Array of key-value pairs.
     */
    public function get_multiple_meta($product_id, $keys = null) {
        if ($keys === null) {
            $keys = array_keys($this->meta_keys);
        }

        $meta_data = array();

        foreach ($keys as $key) {
            $meta_data[$key] = $this->get_meta($product_id, $key);
        }

        return $meta_data;
    }

    /**
     * Get all Amazon meta data for a product.
     *
     * @since    1.0.0
     * @param    int    $product_id    Product ID.
     * @return   array  Array of all Amazon meta data.
     */
    public function get_all_meta($product_id) {
        return $this->get_multiple_meta($product_id);
    }

    /**
     * Check if a product has Amazon meta data.
     *
     * @since    1.0.0
     * @param    int    $product_id    Product ID.
     * @return   bool   True if product has Amazon meta data.
     */
    public function has_amazon_meta($product_id) {
        $asin = $this->get_meta($product_id, 'asin');
        return !empty($asin);
    }

    /**
     * Get product by ASIN.
     *
     * @since    1.0.0
     * @param    string    $asin      ASIN to search for.
     * @param    string    $region    Region (optional).
     * @return   int|null  Product ID or null if not found.
     */
    public function get_product_by_asin($asin, $region = null) {
        $meta_query = array(
            array(
                'key' => $this->get_meta_key('asin'),
                'value' => $asin,
                'compare' => '='
            )
        );

        if ($region) {
            $meta_query[] = array(
                'key' => $this->get_meta_key('region'),
                'value' => $region,
                'compare' => '='
            );
        }

        $args = array(
            'post_type' => array('product', 'product_variation'),
            'post_status' => 'any',
            'meta_query' => $meta_query,
            'posts_per_page' => 1,
            'fields' => 'ids'
        );

        $products = get_posts($args);
        return !empty($products) ? $products[0] : null;
    }

    /**
     * Get products by parent ASIN.
     *
     * @since    1.0.0
     * @param    string    $parent_asin    Parent ASIN.
     * @param    string    $region         Region (optional).
     * @return   array     Array of product IDs.
     */
    public function get_products_by_parent_asin($parent_asin, $region = null) {
        $meta_query = array(
            array(
                'key' => $this->get_meta_key('parent_asin'),
                'value' => $parent_asin,
                'compare' => '='
            )
        );

        if ($region) {
            $meta_query[] = array(
                'key' => $this->get_meta_key('region'),
                'value' => $region,
                'compare' => '='
            );
        }

        $args = array(
            'post_type' => array('product', 'product_variation'),
            'post_status' => 'any',
            'meta_query' => $meta_query,
            'posts_per_page' => -1,
            'fields' => 'ids'
        );

        return get_posts($args);
    }

    /**
     * Get products that need synchronization.
     *
     * @since    1.0.0
     * @param    string    $sync_type    Type of sync ('price', 'product', 'all').
     * @param    int       $hours_old    Hours since last sync.
     * @param    int       $limit        Maximum number of products.
     * @return   array     Array of product IDs.
     */
    public function get_products_needing_sync($sync_type = 'all', $hours_old = 24, $limit = 50) {
        $meta_query = array(
            'relation' => 'AND',
            array(
                'key' => $this->get_meta_key('asin'),
                'value' => '',
                'compare' => '!='
            )
        );

        // Add sync enabled check
        if ($sync_type === 'price') {
            $meta_query[] = array(
                'key' => $this->get_meta_key('price_sync_enabled'),
                'value' => true,
                'compare' => '='
            );
            $last_sync_key = 'last_price_sync';
        } elseif ($sync_type === 'product') {
            $meta_query[] = array(
                'key' => $this->get_meta_key('auto_update_enabled'),
                'value' => true,
                'compare' => '='
            );
            $last_sync_key = 'last_product_update';
        } else {
            $meta_query[] = array(
                'key' => $this->get_meta_key('sync_enabled'),
                'value' => true,
                'compare' => '='
            );
            $last_sync_key = 'last_sync';
        }

        // Add time-based filter
        $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$hours_old} hours"));
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key' => $this->get_meta_key($last_sync_key),
                'value' => $cutoff_time,
                'compare' => '<',
                'type' => 'DATETIME'
            ),
            array(
                'key' => $this->get_meta_key($last_sync_key),
                'compare' => 'NOT EXISTS'
            )
        );

        $args = array(
            'post_type' => array('product', 'product_variation'),
            'post_status' => 'publish',
            'meta_query' => $meta_query,
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'orderby' => 'meta_value',
            'meta_key' => $this->get_meta_key($last_sync_key),
            'order' => 'ASC'
        );

        return get_posts($args);
    }

    /**
     * Update sync timestamp.
     *
     * @since    1.0.0
     * @param    int       $product_id    Product ID.
     * @param    string    $sync_type     Type of sync.
     * @param    bool      $success       Whether sync was successful.
     * @return   bool      True on success.
     */
    public function update_sync_timestamp($product_id, $sync_type = 'all', $success = true) {
        $timestamp = current_time('mysql');
        $result = true;

        if ($sync_type === 'price' || $sync_type === 'all') {
            $result &= $this->set_meta($product_id, 'last_price_sync', $timestamp);
        }

        if ($sync_type === 'product' || $sync_type === 'all') {
            $result &= $this->set_meta($product_id, 'last_product_update', $timestamp);
        }

        if ($sync_type === 'all') {
            $result &= $this->set_meta($product_id, 'last_sync', $timestamp);
        }

        // Update error count
        if ($success) {
            $this->set_meta($product_id, 'sync_errors', 0);
            $this->set_meta($product_id, 'last_error', '');
        } else {
            $error_count = intval($this->get_meta($product_id, 'sync_errors'));
            $this->set_meta($product_id, 'sync_errors', $error_count + 1);
        }

        return $result;
    }

    /**
     * Add price to history.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    float    $price         Price to add.
     * @param    string   $currency      Currency code.
     * @return   bool     True on success.
     */
    public function add_price_to_history($product_id, $price, $currency = 'USD') {
        $history = $this->get_meta($product_id, 'price_history');
        if (!is_array($history)) {
            $history = array();
        }

        $history[] = array(
            'price' => floatval($price),
            'currency' => $currency,
            'date' => current_time('mysql')
        );

        // Keep only last 30 entries
        if (count($history) > 30) {
            $history = array_slice($history, -30);
        }

        return $this->set_meta($product_id, 'price_history', $history);
    }

    /**
     * Get price history statistics.
     *
     * @since    1.0.0
     * @param    int    $product_id    Product ID.
     * @param    int    $days          Number of days to analyze.
     * @return   array  Price statistics.
     */
    public function get_price_statistics($product_id, $days = 30) {
        $history = $this->get_meta($product_id, 'price_history');
        if (!is_array($history) || empty($history)) {
            return array();
        }

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $recent_prices = array();

        foreach ($history as $entry) {
            if (isset($entry['date']) && $entry['date'] >= $cutoff_date) {
                $recent_prices[] = floatval($entry['price']);
            }
        }

        if (empty($recent_prices)) {
            return array();
        }

        return array(
            'min_price' => min($recent_prices),
            'max_price' => max($recent_prices),
            'avg_price' => round(array_sum($recent_prices) / count($recent_prices), 2),
            'current_price' => end($recent_prices),
            'price_count' => count($recent_prices),
            'first_price' => reset($recent_prices)
        );
    }

    /**
     * Set product availability status.
     *
     * @since    1.0.0
     * @param    int       $product_id        Product ID.
     * @param    string    $availability      Availability status.
     * @param    string    $availability_type Availability type.
     * @return   bool      True on success.
     */
    public function set_availability($product_id, $availability, $availability_type = '') {
        $result = $this->set_meta($product_id, 'availability', $availability);
        
        if (!empty($availability_type)) {
            $result &= $this->set_meta($product_id, 'availability_type', $availability_type);
        }

        // If product becomes unavailable, record timestamp
        if (in_array(strtolower($availability), array('unavailable', 'outofstock', 'temporarily unavailable'))) {
            $unavailable_since = $this->get_meta($product_id, 'unavailable_since');
            if (empty($unavailable_since)) {
                $result &= $this->set_meta($product_id, 'unavailable_since', current_time('mysql'));
            }
        } else {
            // Clear unavailable timestamp if product becomes available
            $this->delete_meta($product_id, 'unavailable_since');
        }

        return $result;
    }

    /**
     * Check if meta key is valid.
     *
     * @since    1.0.0
     * @param    string    $key    Meta key to validate.
     * @return   bool      True if valid.
     */
    private function is_valid_meta_key($key) {
        return array_key_exists($key, $this->meta_keys);
    }

    /**
     * Log meta changes for audit purposes.
     *
     * @since    1.0.0
     * @param    int       $product_id    Product ID.
     * @param    string    $key           Meta key.
     * @param    mixed     $value         Meta value.
     * @param    string    $action        Action performed.
     */
    private function log_meta_change($product_id, $key, $value, $action) {
        if (!get_option('ams_enable_meta_logging', false)) {
            return;
        }

        $log_entry = array(
            'product_id' => $product_id,
            'meta_key' => $key,
            'meta_value' => is_array($value) || is_object($value) ? json_encode($value) : $value,
            'action' => $action,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql')
        );

        // Store in transient for performance (could be moved to custom table if needed)
        $logs = get_transient('ams_meta_changes_log') ?: array();
        $logs[] = $log_entry;

        // Keep only last 100 entries
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }

        set_transient('ams_meta_changes_log', $logs, HOUR_IN_SECONDS);
    }

    /**
     * Get meta change logs.
     *
     * @since    1.0.0
     * @param    int    $product_id    Product ID (optional).
     * @return   array  Array of meta change logs.
     */
    public function get_meta_change_logs($product_id = null) {
        $logs = get_transient('ams_meta_changes_log') ?: array();

        if ($product_id) {
            $logs = array_filter($logs, function($log) use ($product_id) {
                return $log['product_id'] == $product_id;
            });
        }

        return array_reverse($logs); // Most recent first
    }

    /**
     * Migrate old meta data structure.
     *
     * @since    1.0.0
     * @param    int    $product_id    Product ID.
     * @return   bool   True on success.
     */
    public function migrate_meta_data($product_id) {
        // This method can be used to migrate from old meta structure
        // Example: moving from individual meta keys to serialized arrays
        
        $migrated = false;

        // Check if migration is needed
        $migration_flag = get_post_meta($product_id, '_amazon_meta_migrated', true);
        if ($migration_flag) {
            return true; // Already migrated
        }

        // Perform migration logic here
        // This is a placeholder for future migration needs

        // Mark as migrated
        update_post_meta($product_id, '_amazon_meta_migrated', true);

        return $migrated;
    }

    /**
     * Validate meta data integrity.
     *
     * @since    1.0.0
     * @param    int    $product_id    Product ID.
     * @return   array  Array of validation results.
     */
    public function validate_meta_integrity($product_id) {
        $issues = array();
        $meta_data = $this->get_all_meta($product_id);

        // Check required fields
        if (empty($meta_data['asin'])) {
            $issues[] = 'Missing ASIN';
        }

        // Validate ASIN format
        if (!empty($meta_data['asin']) && !preg_match('/^[A-Z0-9]{10}$/', $meta_data['asin'])) {
            $issues[] = 'Invalid ASIN format';
        }

        // Check for orphaned variations
        if (!empty($meta_data['parent_asin']) && empty($meta_data['parent_product_id'])) {
            $issues[] = 'Orphaned variation - parent product not found';
        }

        // Validate price history
        if (!empty($meta_data['price_history']) && !is_array($meta_data['price_history'])) {
            $issues[] = 'Invalid price history format';
        }

        // Check sync timestamps
        $timestamps = array('last_sync', 'last_price_sync', 'last_product_update');
        foreach ($timestamps as $timestamp_key) {
            if (!empty($meta_data[$timestamp_key]) && !strtotime($meta_data[$timestamp_key])) {
                $issues[] = "Invalid {$timestamp_key} timestamp";
            }
        }

        return array(
            'valid' => empty($issues),
            'issues' => $issues
        );
    }

    /**
     * Export product meta data.
     *
     * @since    1.0.0
     * @param    int    $product_id    Product ID.
     * @return   array  Exported meta data.
     */
    public function export_meta_data($product_id) {
        $meta_data = $this->get_all_meta($product_id);
        
        return array(
            'product_id' => $product_id,
            'export_date' => current_time('mysql'),
            'meta_data' => $meta_data,
            'version' => '1.0.0'
        );
    }

    /**
     * Import product meta data.
     *
     * @since    1.0.0
     * @param    int      $product_id      Product ID.
     * @param    array    $exported_data   Exported meta data.
     * @param    bool     $overwrite       Whether to overwrite existing data.
     * @return   bool     True on success.
     */
    public function import_meta_data($product_id, $exported_data, $overwrite = false) {
        if (!isset($exported_data['meta_data']) || !is_array($exported_data['meta_data'])) {
            return false;
        }

        $success = true;

        foreach ($exported_data['meta_data'] as $key => $value) {
            if (!$overwrite && !empty($this->get_meta($product_id, $key))) {
                continue; // Skip if data exists and not overwriting
            }

            if (!$this->set_meta($product_id, $key, $value)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get available meta keys and their descriptions.
     *
     * @since    1.0.0
     * @return   array    Array of meta keys with descriptions.
     */
    public function get_available_meta_keys() {
        return array(
            'asin' => 'Amazon Standard Identification Number',
            'parent_asin' => 'Parent product ASIN for variations',
            'region' => 'Amazon marketplace region',
            'affiliate_tag' => 'Amazon affiliate tag',
            'import_date' => 'Date when product was imported',
            'last_sync' => 'Last synchronization timestamp',
            'sync_enabled' => 'Whether automatic sync is enabled',
            'availability' => 'Product availability status',
            'brand' => 'Product brand',
            'price_history' => 'Historical price data',
            // Add more descriptions as needed
        );
    }

    /**
     * Bulk update meta for multiple products.
     *
     * @since    1.0.0
     * @param    array    $product_ids    Array of product IDs.
     * @param    array    $meta_data      Meta data to update.
     * @return   array    Results array with success/failure counts.
     */
    public function bulk_update_meta($product_ids, $meta_data) {
        $results = array('success' => 0, 'failed' => 0, 'errors' => array());

        foreach ($product_ids as $product_id) {
            if ($this->set_multiple_meta($product_id, $meta_data)) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to update product ID: {$product_id}";
            }
        }

        return $results;
    }
}