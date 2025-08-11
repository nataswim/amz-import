<?php
/**
 * Advanced Cache System
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/utilities
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Advanced Cache Class
 *
 * Provides advanced caching functionality for Amazon Product Importer
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/utilities
 * @author     Your Name <email@example.com>
 */
class Amazon_Product_Importer_Cache {

    /**
     * Cache prefix for all cache keys
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $cache_prefix    Cache key prefix
     */
    private $cache_prefix = 'ams_cache_';

    /**
     * Default cache expiration time in seconds
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $default_expiration    Default expiration time
     */
    private $default_expiration = 3600; // 1 hour

    /**
     * Cache groups for different data types
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $cache_groups    Cache groups configuration
     */
    private $cache_groups = array(
        'products' => array(
            'expiration' => 7200,    // 2 hours
            'prefix' => 'product_'
        ),
        'searches' => array(
            'expiration' => 1800,    // 30 minutes
            'prefix' => 'search_'
        ),
        'variations' => array(
            'expiration' => 7200,    // 2 hours
            'prefix' => 'variation_'
        ),
        'categories' => array(
            'expiration' => 86400,   // 24 hours
            'prefix' => 'category_'
        ),
        'images' => array(
            'expiration' => 604800,  // 7 days
            'prefix' => 'image_'
        ),
        'api_responses' => array(
            'expiration' => 3600,    // 1 hour
            'prefix' => 'api_'
        )
    );

    /**
     * Maximum cache size per group (number of items)
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $max_cache_size    Maximum cache items per group
     */
    private $max_cache_size = 1000;

    /**
     * Cache statistics
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $stats    Cache statistics
     */
    private $stats = array(
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    );

    /**
     * Logger instance
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Logger    $logger    Logger instance
     */
    private $logger;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->logger = new Amazon_Product_Importer_Logger();
        
        // Load settings
        $this->default_expiration = get_option('amazon_product_importer_cache_expiration', 3600);
        $this->max_cache_size = get_option('amazon_product_importer_max_cache_size', 1000);
        
        // Load custom cache group settings
        $this->load_cache_group_settings();
        
        // Initialize cache statistics
        $this->load_cache_statistics();
        
        // Schedule cache cleanup
        $this->schedule_cache_cleanup();
    }

    /**
     * Get cached data by key and group
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $key      Cache key
     * @param    string    $group    Cache group
     * @return   mixed               Cached data or false if not found
     */
    public function get($key, $group = 'default') {
        $cache_key = $this->build_cache_key($key, $group);
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            $this->stats['hits']++;
            
            // Update access time for LRU tracking
            $this->update_access_time($cache_key);
            
            return $this->maybe_unserialize($cached_data);
        }

        $this->stats['misses']++;
        return false;
    }

    /**
     * Set cache data with key and group
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $key         Cache key
     * @param    mixed     $data        Data to cache
     * @param    string    $group       Cache group
     * @param    int       $expiration  Custom expiration time (optional)
     * @return   bool                   True on success, false on failure
     */
    public function set($key, $data, $group = 'default', $expiration = null) {
        $cache_key = $this->build_cache_key($key, $group);
        
        if ($expiration === null) {
            $expiration = $this->get_group_expiration($group);
        }

        // Check cache size limits
        if (!$this->check_cache_size_limit($group)) {
            $this->cleanup_lru_items($group);
        }

        $serialized_data = $this->maybe_serialize($data);
        $result = set_transient($cache_key, $serialized_data, $expiration);

        if ($result) {
            $this->stats['sets']++;
            $this->track_cache_item($cache_key, $group, $expiration);
        }

        return $result;
    }

    /**
     * Delete cached data by key and group
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $key      Cache key
     * @param    string    $group    Cache group
     * @return   bool                True on success, false on failure
     */
    public function delete($key, $group = 'default') {
        $cache_key = $this->build_cache_key($key, $group);
        $result = delete_transient($cache_key);

        if ($result) {
            $this->stats['deletes']++;
            $this->untrack_cache_item($cache_key);
        }

        return $result;
    }

    /**
     * Get product data from cache
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $asin    Product ASIN
     * @return   mixed              Cached product data or false
     */
    public function get_product_data($asin) {
        return $this->get($asin, 'products');
    }

    /**
     * Set product data in cache
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $asin       Product ASIN
     * @param    mixed     $data       Product data
     * @param    int       $expiration Custom expiration (optional)
     * @return   bool                  True on success, false on failure
     */
    public function set_product_data($asin, $data, $expiration = null) {
        return $this->set($asin, $data, 'products', $expiration);
    }

    /**
     * Invalidate product cache
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $asin    Product ASIN
     * @return   bool               True on success, false on failure
     */
    public function invalidate_product_cache($asin) {
        return $this->delete($asin, 'products');
    }

    /**
     * Get search results from cache
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $search_hash    Search parameters hash
     * @return   mixed                     Cached search results or false
     */
    public function get_search_results($search_hash) {
        return $this->get($search_hash, 'searches');
    }

    /**
     * Set search results in cache
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $search_hash    Search parameters hash
     * @param    mixed     $results        Search results
     * @param    int       $expiration     Custom expiration (optional)
     * @return   bool                      True on success, false on failure
     */
    public function set_search_results($search_hash, $results, $expiration = null) {
        return $this->set($search_hash, $results, 'searches', $expiration);
    }

    /**
     * Get variation data from cache
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $parent_asin    Parent product ASIN
     * @return   mixed                     Cached variation data or false
     */
    public function get_variation_data($parent_asin) {
        return $this->get($parent_asin, 'variations');
    }

    /**
     * Set variation data in cache
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $parent_asin    Parent product ASIN
     * @param    mixed     $data           Variation data
     * @param    int       $expiration     Custom expiration (optional)
     * @return   bool                      True on success, false on failure
     */
    public function set_variation_data($parent_asin, $data, $expiration = null) {
        return $this->set($parent_asin, $data, 'variations', $expiration);
    }

    /**
     * Get category data from cache
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $browse_node_id    Browse node ID
     * @return   mixed                        Cached category data or false
     */
    public function get_category_data($browse_node_id) {
        return $this->get($browse_node_id, 'categories');
    }

    /**
     * Set category data in cache
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $browse_node_id    Browse node ID
     * @param    mixed     $data              Category data
     * @param    int       $expiration        Custom expiration (optional)
     * @return   bool                         True on success, false on failure
     */
    public function set_category_data($browse_node_id, $data, $expiration = null) {
        return $this->set($browse_node_id, $data, 'categories', $expiration);
    }

    /**
     * Get API response from cache
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $request_hash    Request hash
     * @return   mixed                      Cached API response or false
     */
    public function get_api_response($request_hash) {
        return $this->get($request_hash, 'api_responses');
    }

    /**
     * Set API response in cache
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $request_hash    Request hash
     * @param    mixed     $response        API response
     * @param    int       $expiration      Custom expiration (optional)
     * @return   bool                       True on success, false on failure
     */
    public function set_api_response($request_hash, $response, $expiration = null) {
        return $this->set($request_hash, $response, 'api_responses', $expiration);
    }

    /**
     * Clear all cache for a specific group
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $group    Cache group to clear
     * @return   int                 Number of items cleared
     */
    public function clear_group_cache($group) {
        global $wpdb;

        $group_prefix = $this->cache_prefix . $this->get_group_prefix($group);
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_' . $group_prefix) . '%'
        ));

        // Also clear timeout transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_timeout_' . $group_prefix) . '%'
        ));

        // Clear tracking data
        $this->clear_group_tracking($group);

        $this->logger->log('info', 'Cache group cleared', array(
            'group' => $group,
            'items_cleared' => $result
        ));

        return $result;
    }

    /**
     * Clear all cache
     *
     * @since    1.0.0
     * @access   public
     * @return   int    Number of items cleared
     */
    public function clear_all_cache() {
        global $wpdb;

        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_' . $this->cache_prefix) . '%'
        ));

        // Also clear timeout transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_timeout_' . $this->cache_prefix) . '%'
        ));

        // Clear all tracking data
        $this->clear_all_tracking();

        // Reset statistics
        $this->reset_cache_statistics();

        $this->logger->log('info', 'All cache cleared', array(
            'items_cleared' => $result
        ));

        return $result;
    }

    /**
     * Get cache statistics
     *
     * @since    1.0.0
     * @access   public
     * @return   array    Cache statistics
     */
    public function get_cache_statistics() {
        $this->save_cache_statistics();
        
        return array_merge($this->stats, array(
            'hit_ratio' => $this->calculate_hit_ratio(),
            'total_size' => $this->calculate_total_cache_size(),
            'group_sizes' => $this->calculate_group_sizes()
        ));
    }

    /**
     * Build cache key from key and group
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $key      Cache key
     * @param    string    $group    Cache group
     * @return   string              Built cache key
     */
    private function build_cache_key($key, $group) {
        $group_prefix = $this->get_group_prefix($group);
        return $this->cache_prefix . $group_prefix . md5($key);
    }

    /**
     * Get cache group prefix
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $group    Cache group
     * @return   string              Group prefix
     */
    private function get_group_prefix($group) {
        if (isset($this->cache_groups[$group])) {
            return $this->cache_groups[$group]['prefix'];
        }
        
        return 'default_';
    }

    /**
     * Get cache group expiration time
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $group    Cache group
     * @return   int                 Expiration time in seconds
     */
    private function get_group_expiration($group) {
        if (isset($this->cache_groups[$group])) {
            return $this->cache_groups[$group]['expiration'];
        }
        
        return $this->default_expiration;
    }

    /**
     * Check if data needs serialization
     *
     * @since    1.0.0
     * @access   private
     * @param    mixed    $data    Data to check
     * @return   string            Serialized data if needed
     */
    private function maybe_serialize($data) {
        if (is_array($data) || is_object($data)) {
            return serialize($data);
        }
        
        return $data;
    }

    /**
     * Unserialize data if needed
     *
     * @since    1.0.0
     * @access   private
     * @param    mixed    $data    Data to unserialize
     * @return   mixed             Unserialized data
     */
    private function maybe_unserialize($data) {
        if (is_string($data) && is_serialized($data)) {
            return unserialize($data);
        }
        
        return $data;
    }

    /**
     * Check cache size limit for a group
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $group    Cache group
     * @return   bool                True if within limit, false otherwise
     */
    private function check_cache_size_limit($group) {
        $group_items = $this->get_group_item_count($group);
        return $group_items < $this->max_cache_size;
    }

    /**
     * Get number of cached items in a group
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $group    Cache group
     * @return   int                 Number of items
     */
    private function get_group_item_count($group) {
        global $wpdb;

        $group_prefix = $this->cache_prefix . $this->get_group_prefix($group);
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_' . $group_prefix) . '%'
        ));
    }

    /**
     * Clean up least recently used items from a group
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $group    Cache group
     * @return   int                 Number of items cleaned up
     */
    private function cleanup_lru_items($group) {
        $tracking_data = get_option($this->cache_prefix . 'tracking_' . $group, array());
        
        if (empty($tracking_data)) {
            return 0;
        }

        // Sort by access time (oldest first)
        uasort($tracking_data, function($a, $b) {
            return $a['access_time'] - $b['access_time'];
        });

        // Remove oldest 25% of items
        $items_to_remove = ceil(count($tracking_data) * 0.25);
        $removed_count = 0;

        foreach (array_slice($tracking_data, 0, $items_to_remove, true) as $cache_key => $data) {
            if (delete_transient($cache_key)) {
                unset($tracking_data[$cache_key]);
                $removed_count++;
            }
        }

        // Update tracking data
        update_option($this->cache_prefix . 'tracking_' . $group, $tracking_data);

        return $removed_count;
    }

    /**
     * Track cache item for LRU management
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $cache_key    Cache key
     * @param    string    $group        Cache group
     * @param    int       $expiration   Expiration time
     */
    private function track_cache_item($cache_key, $group, $expiration) {
        $tracking_data = get_option($this->cache_prefix . 'tracking_' . $group, array());
        
        $tracking_data[$cache_key] = array(
            'access_time' => time(),
            'expiration' => $expiration,
            'size' => strlen(serialize($this->get_raw_cache_data($cache_key)))
        );

        update_option($this->cache_prefix . 'tracking_' . $group, $tracking_data);
    }

    /**
     * Remove item from tracking
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $cache_key    Cache key
     */
    private function untrack_cache_item($cache_key) {
        foreach ($this->cache_groups as $group => $config) {
            $tracking_data = get_option($this->cache_prefix . 'tracking_' . $group, array());
            
            if (isset($tracking_data[$cache_key])) {
                unset($tracking_data[$cache_key]);
                update_option($this->cache_prefix . 'tracking_' . $group, $tracking_data);
                break;
            }
        }
    }

    /**
     * Update access time for cache item
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $cache_key    Cache key
     */
    private function update_access_time($cache_key) {
        foreach ($this->cache_groups as $group => $config) {
            $tracking_data = get_option($this->cache_prefix . 'tracking_' . $group, array());
            
            if (isset($tracking_data[$cache_key])) {
                $tracking_data[$cache_key]['access_time'] = time();
                update_option($this->cache_prefix . 'tracking_' . $group, $tracking_data);
                break;
            }
        }
    }

    /**
     * Load cache group settings from options
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_cache_group_settings() {
        $custom_settings = get_option('amazon_product_importer_cache_groups', array());
        
        foreach ($custom_settings as $group => $settings) {
            if (isset($this->cache_groups[$group])) {
                $this->cache_groups[$group] = array_merge($this->cache_groups[$group], $settings);
            }
        }
    }

    /**
     * Load cache statistics
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_cache_statistics() {
        $saved_stats = get_option($this->cache_prefix . 'statistics', array());
        $this->stats = array_merge($this->stats, $saved_stats);
    }

    /**
     * Save cache statistics
     *
     * @since    1.0.0
     * @access   private
     */
    private function save_cache_statistics() {
        update_option($this->cache_prefix . 'statistics', $this->stats);
    }

    /**
     * Reset cache statistics
     *
     * @since    1.0.0
     * @access   private
     */
    private function reset_cache_statistics() {
        $this->stats = array(
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0
        );
        
        $this->save_cache_statistics();
    }

    /**
     * Calculate cache hit ratio
     *
     * @since    1.0.0
     * @access   private
     * @return   float    Hit ratio percentage
     */
    private function calculate_hit_ratio() {
        $total_requests = $this->stats['hits'] + $this->stats['misses'];
        
        if ($total_requests === 0) {
            return 0;
        }
        
        return round(($this->stats['hits'] / $total_requests) * 100, 2);
    }

    /**
     * Calculate total cache size
     *
     * @since    1.0.0
     * @access   private
     * @return   array    Total cache size information
     */
    private function calculate_total_cache_size() {
        global $wpdb;

        $total_size = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_' . $this->cache_prefix) . '%'
        ));

        $total_items = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_' . $this->cache_prefix) . '%'
        ));

        return array(
            'size_bytes' => intval($total_size),
            'size_mb' => round($total_size / 1024 / 1024, 2),
            'item_count' => intval($total_items)
        );
    }

    /**
     * Calculate cache sizes by group
     *
     * @since    1.0.0
     * @access   private
     * @return   array    Cache sizes by group
     */
    private function calculate_group_sizes() {
        global $wpdb;
        $group_sizes = array();

        foreach ($this->cache_groups as $group => $config) {
            $group_prefix = $this->cache_prefix . $config['prefix'];
            
            $size = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_' . $group_prefix) . '%'
            ));

            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_' . $group_prefix) . '%'
            ));

            $group_sizes[$group] = array(
                'size_bytes' => intval($size),
                'size_mb' => round($size / 1024 / 1024, 2),
                'item_count' => intval($count)
            );
        }

        return $group_sizes;
    }

    /**
     * Clear tracking data for a group
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $group    Cache group
     */
    private function clear_group_tracking($group) {
        delete_option($this->cache_prefix . 'tracking_' . $group);
    }

    /**
     * Clear all tracking data
     *
     * @since    1.0.0
     * @access   private
     */
    private function clear_all_tracking() {
        foreach ($this->cache_groups as $group => $config) {
            $this->clear_group_tracking($group);
        }
    }

    /**
     * Get raw cache data without unserialization
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $cache_key    Cache key
     * @return   mixed                   Raw cache data
     */
    private function get_raw_cache_data($cache_key) {
        return get_transient($cache_key);
    }

    /**
     * Schedule automatic cache cleanup
     *
     * @since    1.0.0
     * @access   private
     */
    private function schedule_cache_cleanup() {
        if (!wp_next_scheduled('amazon_importer_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'amazon_importer_cache_cleanup');
        }
        
        add_action('amazon_importer_cache_cleanup', array($this, 'automatic_cache_cleanup'));
    }

    /**
     * Automatic cache cleanup (called by cron)
     *
     * @since    1.0.0
     * @access   public
     */
    public function automatic_cache_cleanup() {
        // Clean up expired items
        $this->cleanup_expired_items();
        
        // Clean up oversized groups
        foreach ($this->cache_groups as $group => $config) {
            if ($this->get_group_item_count($group) > $this->max_cache_size) {
                $this->cleanup_lru_items($group);
            }
        }
        
        $this->logger->log('info', 'Automatic cache cleanup completed');
    }

    /**
     * Clean up expired cache items
     *
     * @since    1.0.0
     * @access   private
     * @return   int    Number of items cleaned up
     */
    private function cleanup_expired_items() {
        global $wpdb;

        // WordPress automatically cleans up expired transients, but we can force it
        $current_time = time();
        
        $expired_count = $wpdb->query($wpdb->prepare(
            "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b 
             WHERE a.option_name LIKE %s 
             AND a.option_name = CONCAT('_transient_timeout_', SUBSTRING(b.option_name, 12))
             AND b.option_name LIKE %s 
             AND a.option_value < %d",
            $wpdb->esc_like('_transient_timeout_' . $this->cache_prefix) . '%',
            $wpdb->esc_like('_transient_' . $this->cache_prefix) . '%',
            $current_time
        ));

        return $expired_count;
    }
}