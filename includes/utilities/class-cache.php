<?php

/**
 * The cache management functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/utilities
 */

/**
 * The cache management functionality of the plugin.
 *
 * Handles caching of API responses, product data, and other frequently accessed information.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/utilities
 * @author     Your Name <email@example.com>
 */
class Amazon_Product_Importer_Cache {

    /**
     * Cache prefix for all cache keys.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $cache_prefix    Cache key prefix.
     */
    private $cache_prefix = 'ams_cache_';

    /**
     * Cache settings.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $settings    Cache settings.
     */
    private $settings;

    /**
     * Cache statistics.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $stats    Cache statistics.
     */
    private $stats = array(
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    );

    /**
     * Available cache types.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $cache_types    Available cache types with their settings.
     */
    private $cache_types = array(
        'api_response' => array(
            'default_expiry' => 3600, // 1 hour
            'max_size' => 1024 * 1024, // 1MB
            'compress' => true,
            'storage' => 'database'
        ),
        'product_data' => array(
            'default_expiry' => 1800, // 30 minutes
            'max_size' => 512 * 1024, // 512KB
            'compress' => true,
            'storage' => 'transient'
        ),
        'currency_rates' => array(
            'default_expiry' => 86400, // 24 hours
            'max_size' => 1024, // 1KB
            'compress' => false,
            'storage' => 'transient'
        ),
        'category_data' => array(
            'default_expiry' => 7200, // 2 hours
            'max_size' => 256 * 1024, // 256KB
            'compress' => true,
            'storage' => 'transient'
        ),
        'search_results' => array(
            'default_expiry' => 900, // 15 minutes
            'max_size' => 2048 * 1024, // 2MB
            'compress' => true,
            'storage' => 'database'
        ),
        'image_urls' => array(
            'default_expiry' => 43200, // 12 hours
            'max_size' => 10240, // 10KB
            'compress' => false,
            'storage' => 'transient'
        )
    );

    /**
     * Database instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Database    $database    Database instance.
     */
    private $database;

    /**
     * Logger instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Logger    $logger    Logger instance.
     */
    private $logger;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->database = new Amazon_Product_Importer_Database();
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->load_settings();
        $this->init_hooks();
    }

    /**
     * Load cache settings.
     *
     * @since    1.0.0
     */
    private function load_settings() {
        $this->settings = array(
            'enabled' => get_option('ams_cache_enabled', true),
            'default_expiry' => get_option('ams_cache_default_expiry', 3600),
            'max_memory_usage' => get_option('ams_cache_max_memory', 10), // MB
            'auto_cleanup' => get_option('ams_cache_auto_cleanup', true),
            'cleanup_probability' => get_option('ams_cache_cleanup_probability', 5), // %
            'compression_enabled' => get_option('ams_cache_compression', true),
            'statistics_enabled' => get_option('ams_cache_statistics', true),
            'debug_mode' => get_option('ams_cache_debug', false)
        );

        // Load custom cache type settings
        $custom_types = get_option('ams_cache_types_config', array());
        if (!empty($custom_types)) {
            $this->cache_types = array_merge($this->cache_types, $custom_types);
        }
    }

    /**
     * Initialize WordPress hooks.
     *
     * @since    1.0.0
     */
    private function init_hooks() {
        // Auto cleanup hook
        if ($this->settings['auto_cleanup']) {
            add_action('ams_cache_cleanup', array($this, 'cleanup_expired_cache'));
            
            if (!wp_next_scheduled('ams_cache_cleanup')) {
                wp_schedule_event(time(), 'hourly', 'ams_cache_cleanup');
            }
        }

        // Product update hooks for cache invalidation
        add_action('woocommerce_update_product', array($this, 'invalidate_product_cache'));
        add_action('woocommerce_delete_product', array($this, 'invalidate_product_cache'));
        
        // Settings change hook
        add_action('update_option_ams_cache_enabled', array($this, 'on_cache_settings_change'));
    }

    /**
     * Get cached data.
     *
     * @since    1.0.0
     * @param    string    $key           Cache key.
     * @param    string    $type          Cache type.
     * @param    mixed     $default       Default value if not found.
     * @return   mixed     Cached data or default value.
     */
    public function get($key, $type = 'product_data', $default = null) {
        if (!$this->is_cache_enabled()) {
            $this->increment_stat('misses');
            return $default;
        }

        try {
            $cache_key = $this->generate_cache_key($key, $type);
            $cache_data = $this->retrieve_from_storage($cache_key, $type);

            if ($cache_data === null) {
                $this->increment_stat('misses');
                return $default;
            }

            // Validate cache data
            if (!$this->validate_cache_data($cache_data)) {
                $this->delete($key, $type);
                $this->increment_stat('misses');
                return $default;
            }

            // Check expiry
            if ($this->is_expired($cache_data)) {
                $this->delete($key, $type);
                $this->increment_stat('misses');
                return $default;
            }

            $this->increment_stat('hits');
            
            // Decompress if needed
            $data = $this->decompress_data($cache_data['data'], $type);
            
            if ($this->settings['debug_mode']) {
                $this->logger->log("Cache HIT for key: {$cache_key}", 'debug');
            }

            return $data;

        } catch (Exception $e) {
            $this->logger->log("Cache get error for key {$key}: " . $e->getMessage(), 'error');
            $this->increment_stat('misses');
            return $default;
        }
    }

    /**
     * Set cached data.
     *
     * @since    1.0.0
     * @param    string    $key        Cache key.
     * @param    mixed     $data       Data to cache.
     * @param    string    $type       Cache type.
     * @param    int       $expiry     Expiry time in seconds (optional).
     * @return   bool      True on success, false on failure.
     */
    public function set($key, $data, $type = 'product_data', $expiry = null) {
        if (!$this->is_cache_enabled()) {
            return false;
        }

        try {
            // Validate input
            if (empty($key) || $data === null) {
                return false;
            }

            $cache_key = $this->generate_cache_key($key, $type);
            
            // Check data size limits
            if (!$this->validate_data_size($data, $type)) {
                $this->logger->log("Cache data too large for key: {$cache_key}", 'warning');
                return false;
            }

            // Determine expiry time
            if ($expiry === null) {
                $expiry = $this->get_default_expiry($type);
            }

            // Compress data if enabled
            $compressed_data = $this->compress_data($data, $type);

            // Prepare cache entry
            $cache_entry = array(
                'data' => $compressed_data,
                'created_at' => time(),
                'expires_at' => time() + $expiry,
                'type' => $type,
                'size' => strlen(serialize($compressed_data)),
                'hits' => 0,
                'compressed' => $this->should_compress($type)
            );

            // Store in appropriate storage
            $success = $this->store_in_storage($cache_key, $cache_entry, $type, $expiry);

            if ($success) {
                $this->increment_stat('sets');
                
                if ($this->settings['debug_mode']) {
                    $this->logger->log("Cache SET for key: {$cache_key}", 'debug');
                }

                // Probabilistic cleanup
                $this->maybe_cleanup();
                
                return true;
            }

            return false;

        } catch (Exception $e) {
            $this->logger->log("Cache set error for key {$key}: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Delete cached data.
     *
     * @since    1.0.0
     * @param    string    $key     Cache key.
     * @param    string    $type    Cache type.
     * @return   bool      True on success, false on failure.
     */
    public function delete($key, $type = 'product_data') {
        try {
            $cache_key = $this->generate_cache_key($key, $type);
            $success = $this->delete_from_storage($cache_key, $type);

            if ($success) {
                $this->increment_stat('deletes');
                
                if ($this->settings['debug_mode']) {
                    $this->logger->log("Cache DELETE for key: {$cache_key}", 'debug');
                }
            }

            return $success;

        } catch (Exception $e) {
            $this->logger->log("Cache delete error for key {$key}: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Flush all cache data of a specific type.
     *
     * @since    1.0.0
     * @param    string    $type    Cache type to flush (optional).
     * @return   bool      True on success, false on failure.
     */
    public function flush($type = null) {
        try {
            if ($type === null) {
                // Flush all cache types
                $success = true;
                foreach (array_keys($this->cache_types) as $cache_type) {
                    if (!$this->flush_cache_type($cache_type)) {
                        $success = false;
                    }
                }
                return $success;
            } else {
                return $this->flush_cache_type($type);
            }

        } catch (Exception $e) {
            $this->logger->log("Cache flush error: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Check if data exists in cache.
     *
     * @since    1.0.0
     * @param    string    $key     Cache key.
     * @param    string    $type    Cache type.
     * @return   bool      True if exists and not expired.
     */
    public function exists($key, $type = 'product_data') {
        if (!$this->is_cache_enabled()) {
            return false;
        }

        $cache_key = $this->generate_cache_key($key, $type);
        $cache_data = $this->retrieve_from_storage($cache_key, $type);

        if ($cache_data === null) {
            return false;
        }

        if (!$this->validate_cache_data($cache_data) || $this->is_expired($cache_data)) {
            $this->delete($key, $type);
            return false;
        }

        return true;
    }

    /**
     * Get cache statistics.
     *
     * @since    1.0.0
     * @return   array    Cache statistics.
     */
    public function get_statistics() {
        $stats = $this->stats;
        
        // Calculate hit ratio
        $total_requests = $stats['hits'] + $stats['misses'];
        $stats['hit_ratio'] = $total_requests > 0 ? round(($stats['hits'] / $total_requests) * 100, 2) : 0;

        // Get storage statistics
        foreach (array_keys($this->cache_types) as $type) {
            $stats['by_type'][$type] = $this->get_type_statistics($type);
        }

        // Get memory usage
        $stats['memory_usage'] = $this->get_memory_usage();
        $stats['total_entries'] = $this->get_total_entries();

        return $stats;
    }

    /**
     * Generate cache key.
     *
     * @since    1.0.0
     * @param    string    $key     Original key.
     * @param    string    $type    Cache type.
     * @return   string    Generated cache key.
     */
    private function generate_cache_key($key, $type) {
        $normalized_key = sanitize_key($key);
        return $this->cache_prefix . $type . '_' . md5($normalized_key);
    }

    /**
     * Retrieve data from storage.
     *
     * @since    1.0.0
     * @param    string    $cache_key    Cache key.
     * @param    string    $type         Cache type.
     * @return   mixed     Cache data or null if not found.
     */
    private function retrieve_from_storage($cache_key, $type) {
        $storage_type = $this->get_storage_type($type);

        switch ($storage_type) {
            case 'database':
                return $this->retrieve_from_database($cache_key);
            
            case 'transient':
                return $this->retrieve_from_transient($cache_key);
            
            case 'object':
                return $this->retrieve_from_object_cache($cache_key);
            
            default:
                return $this->retrieve_from_transient($cache_key);
        }
    }

    /**
     * Store data in storage.
     *
     * @since    1.0.0
     * @param    string    $cache_key     Cache key.
     * @param    array     $cache_entry   Cache entry data.
     * @param    string    $type          Cache type.
     * @param    int       $expiry        Expiry time.
     * @return   bool      True on success.
     */
    private function store_in_storage($cache_key, $cache_entry, $type, $expiry) {
        $storage_type = $this->get_storage_type($type);

        switch ($storage_type) {
            case 'database':
                return $this->store_in_database($cache_key, $cache_entry, $expiry);
            
            case 'transient':
                return $this->store_in_transient($cache_key, $cache_entry, $expiry);
            
            case 'object':
                return $this->store_in_object_cache($cache_key, $cache_entry, $expiry);
            
            default:
                return $this->store_in_transient($cache_key, $cache_entry, $expiry);
        }
    }

    /**
     * Delete data from storage.
     *
     * @since    1.0.0
     * @param    string    $cache_key    Cache key.
     * @param    string    $type         Cache type.
     * @return   bool      True on success.
     */
    private function delete_from_storage($cache_key, $type) {
        $storage_type = $this->get_storage_type($type);

        switch ($storage_type) {
            case 'database':
                return $this->delete_from_database($cache_key);
            
            case 'transient':
                return delete_transient($cache_key);
            
            case 'object':
                return wp_cache_delete($cache_key, 'ams_cache');
            
            default:
                return delete_transient($cache_key);
        }
    }

    /**
     * Retrieve from database.
     *
     * @since    1.0.0
     * @param    string    $cache_key    Cache key.
     * @return   mixed     Cache data or null.
     */
    private function retrieve_from_database($cache_key) {
        $cache_entry = $this->database->get_api_cache($cache_key);
        
        if (!$cache_entry) {
            return null;
        }

        return array(
            'data' => $cache_entry->response_data,
            'created_at' => strtotime($cache_entry->created_at),
            'expires_at' => strtotime($cache_entry->expires_at),
            'compressed' => false // Database storage handles compression differently
        );
    }

    /**
     * Store in database.
     *
     * @since    1.0.0
     * @param    string    $cache_key     Cache key.
     * @param    array     $cache_entry   Cache entry.
     * @param    int       $expiry        Expiry time.
     * @return   bool      True on success.
     */
    private function store_in_database($cache_key, $cache_entry, $expiry) {
        $data = array(
            'cache_key' => $cache_key,
            'response_data' => $cache_entry['data'],
            'expires_at' => date('Y-m-d H:i:s', $cache_entry['expires_at']),
            'api_endpoint' => 'cache_api',
            'region' => 'global'
        );

        return $this->database->set_api_cache($cache_key, $data, ceil($expiry / 3600));
    }

    /**
     * Delete from database.
     *
     * @since    1.0.0
     * @param    string    $cache_key    Cache key.
     * @return   bool      True on success.
     */
    private function delete_from_database($cache_key) {
        global $wpdb;
        
        $table_name = $this->database->get_table_name('api_cache');
        $result = $wpdb->delete($table_name, array('cache_key' => $cache_key));
        
        return $result !== false;
    }

    /**
     * Retrieve from transient.
     *
     * @since    1.0.0
     * @param    string    $cache_key    Cache key.
     * @return   mixed     Cache data or null.
     */
    private function retrieve_from_transient($cache_key) {
        return get_transient($cache_key);
    }

    /**
     * Store in transient.
     *
     * @since    1.0.0
     * @param    string    $cache_key     Cache key.
     * @param    array     $cache_entry   Cache entry.
     * @param    int       $expiry        Expiry time.
     * @return   bool      True on success.
     */
    private function store_in_transient($cache_key, $cache_entry, $expiry) {
        return set_transient($cache_key, $cache_entry, $expiry);
    }

    /**
     * Retrieve from object cache.
     *
     * @since    1.0.0
     * @param    string    $cache_key    Cache key.
     * @return   mixed     Cache data or null.
     */
    private function retrieve_from_object_cache($cache_key) {
        return wp_cache_get($cache_key, 'ams_cache');
    }

    /**
     * Store in object cache.
     *
     * @since    1.0.0
     * @param    string    $cache_key     Cache key.
     * @param    array     $cache_entry   Cache entry.
     * @param    int       $expiry        Expiry time.
     * @return   bool      True on success.
     */
    private function store_in_object_cache($cache_key, $cache_entry, $expiry) {
        return wp_cache_set($cache_key, $cache_entry, 'ams_cache', $expiry);
    }

    /**
     * Compress data if compression is enabled for the type.
     *
     * @since    1.0.0
     * @param    mixed     $data    Data to compress.
     * @param    string    $type    Cache type.
     * @return   mixed     Compressed or original data.
     */
    private function compress_data($data, $type) {
        if (!$this->should_compress($type) || !$this->settings['compression_enabled']) {
            return $data;
        }

        if (function_exists('gzcompress')) {
            $serialized = serialize($data);
            $compressed = gzcompress($serialized, 6);
            
            // Only use compression if it actually reduces size
            if (strlen($compressed) < strlen($serialized)) {
                return array(
                    'compressed' => true,
                    'data' => base64_encode($compressed)
                );
            }
        }

        return $data;
    }

    /**
     * Decompress data if it was compressed.
     *
     * @since    1.0.0
     * @param    mixed     $data    Data to decompress.
     * @param    string    $type    Cache type.
     * @return   mixed     Decompressed data.
     */
    private function decompress_data($data, $type) {
        if (is_array($data) && isset($data['compressed']) && $data['compressed'] === true) {
            if (function_exists('gzuncompress')) {
                $compressed = base64_decode($data['data']);
                $decompressed = gzuncompress($compressed);
                return unserialize($decompressed);
            }
        }

        return $data;
    }

    /**
     * Check if compression should be used for cache type.
     *
     * @since    1.0.0
     * @param    string    $type    Cache type.
     * @return   bool      True if should compress.
     */
    private function should_compress($type) {
        return isset($this->cache_types[$type]['compress']) ? 
               $this->cache_types[$type]['compress'] : false;
    }

    /**
     * Get storage type for cache type.
     *
     * @since    1.0.0
     * @param    string    $type    Cache type.
     * @return   string    Storage type.
     */
    private function get_storage_type($type) {
        return isset($this->cache_types[$type]['storage']) ? 
               $this->cache_types[$type]['storage'] : 'transient';
    }

    /**
     * Get default expiry for cache type.
     *
     * @since    1.0.0
     * @param    string    $type    Cache type.
     * @return   int       Expiry time in seconds.
     */
    private function get_default_expiry($type) {
        if (isset($this->cache_types[$type]['default_expiry'])) {
            return $this->cache_types[$type]['default_expiry'];
        }
        return $this->settings['default_expiry'];
    }

    /**
     * Validate cache data structure.
     *
     * @since    1.0.0
     * @param    mixed    $cache_data    Cache data to validate.
     * @return   bool     True if valid.
     */
    private function validate_cache_data($cache_data) {
        if (!is_array($cache_data)) {
            return false;
        }

        $required_keys = array('data', 'created_at', 'expires_at');
        foreach ($required_keys as $key) {
            if (!isset($cache_data[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if cache data is expired.
     *
     * @since    1.0.0
     * @param    array    $cache_data    Cache data.
     * @return   bool     True if expired.
     */
    private function is_expired($cache_data) {
        return isset($cache_data['expires_at']) && time() > $cache_data['expires_at'];
    }

    /**
     * Validate data size against limits.
     *
     * @since    1.0.0
     * @param    mixed     $data    Data to validate.
     * @param    string    $type    Cache type.
     * @return   bool      True if size is acceptable.
     */
    private function validate_data_size($data, $type) {
        $serialized_size = strlen(serialize($data));
        $max_size = isset($this->cache_types[$type]['max_size']) ? 
                   $this->cache_types[$type]['max_size'] : 1024 * 1024; // 1MB default

        return $serialized_size <= $max_size;
    }

    /**
     * Check if cache is enabled.
     *
     * @since    1.0.0
     * @return   bool    True if enabled.
     */
    private function is_cache_enabled() {
        return $this->settings['enabled'];
    }

    /**
     * Increment cache statistics.
     *
     * @since    1.0.0
     * @param    string    $stat    Statistic to increment.
     */
    private function increment_stat($stat) {
        if ($this->settings['statistics_enabled'] && isset($this->stats[$stat])) {
            $this->stats[$stat]++;
        }
    }

    /**
     * Maybe perform cleanup based on probability.
     *
     * @since    1.0.0
     */
    private function maybe_cleanup() {
        if (!$this->settings['auto_cleanup']) {
            return;
        }

        $probability = $this->settings['cleanup_probability'];
        if (rand(1, 100) <= $probability) {
            $this->cleanup_expired_cache();
        }
    }

    /**
     * Cleanup expired cache entries.
     *
     * @since    1.0.0
     * @return   int    Number of entries cleaned.
     */
    public function cleanup_expired_cache() {
        $cleaned = 0;

        try {
            // Cleanup database cache
            $cleaned += $this->database->clear_expired_cache();

            // Cleanup transients (WordPress handles this automatically, but we can force it)
            $cleaned += $this->cleanup_expired_transients();

            $this->logger->log("Cleaned up {$cleaned} expired cache entries", 'info');

        } catch (Exception $e) {
            $this->logger->log("Cache cleanup error: " . $e->getMessage(), 'error');
        }

        return $cleaned;
    }

    /**
     * Cleanup expired transients.
     *
     * @since    1.0.0
     * @return   int    Number of transients cleaned.
     */
    private function cleanup_expired_transients() {
        global $wpdb;

        $cleaned = 0;
        $prefix = $this->cache_prefix;

        // Get expired transients
        $expired_transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 AND option_value < %d",
                '_transient_timeout_' . $prefix . '%',
                time()
            )
        );

        foreach ($expired_transients as $transient_timeout) {
            $transient_name = str_replace('_transient_timeout_', '', $transient_timeout);
            delete_transient($transient_name);
            $cleaned++;
        }

        return $cleaned;
    }

    /**
     * Flush cache type.
     *
     * @since    1.0.0
     * @param    string    $type    Cache type.
     * @return   bool      True on success.
     */
    private function flush_cache_type($type) {
        $storage_type = $this->get_storage_type($type);

        switch ($storage_type) {
            case 'database':
                return $this->flush_database_cache($type);
            
            case 'transient':
                return $this->flush_transient_cache($type);
            
            case 'object':
                return $this->flush_object_cache($type);
            
            default:
                return false;
        }
    }

    /**
     * Flush database cache for type.
     *
     * @since    1.0.0
     * @param    string    $type    Cache type.
     * @return   bool      True on success.
     */
    private function flush_database_cache($type) {
        global $wpdb;
        
        $table_name = $this->database->get_table_name('api_cache');
        $pattern = $this->cache_prefix . $type . '_%';
        
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE cache_key LIKE %s",
                $pattern
            )
        );

        return $result !== false;
    }

    /**
     * Flush transient cache for type.
     *
     * @since    1.0.0
     * @param    string    $type    Cache type.
     * @return   bool      True on success.
     */
    private function flush_transient_cache($type) {
        global $wpdb;

        $pattern = '_transient_' . $this->cache_prefix . $type . '_%';
        
        $transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            )
        );

        $deleted = 0;
        foreach ($transients as $transient) {
            $transient_name = str_replace('_transient_', '', $transient);
            if (delete_transient($transient_name)) {
                $deleted++;
            }
        }

        return $deleted > 0;
    }

    /**
     * Flush object cache for type.
     *
     * @since    1.0.0
     * @param    string    $type    Cache type.
     * @return   bool      True on success.
     */
    private function flush_object_cache($type) {
        // Object cache doesn't have pattern-based flushing
        // This would require maintaining a list of keys
        wp_cache_flush_group('ams_cache');
        return true;
    }

    /**
     * Get statistics for specific cache type.
     *
     * @since    1.0.0
     * @param    string    $type    Cache type.
     * @return   array     Type statistics.
     */
    private function get_type_statistics($type) {
        $stats = array(
            'entries' => 0,
            'size' => 0,
            'expired' => 0
        );

        $storage_type = $this->get_storage_type($type);

        switch ($storage_type) {
            case 'database':
                return $this->get_database_type_stats($type);
            
            case 'transient':
                return $this->get_transient_type_stats($type);
            
            default:
                return $stats;
        }
    }

    /**
     * Get database type statistics.
     *
     * @since    1.0.0
     * @param    string    $type    Cache type.
     * @return   array     Statistics.
     */
    private function get_database_type_stats($type) {
        global $wpdb;
        
        $table_name = $this->database->get_table_name('api_cache');
        $pattern = $this->cache_prefix . $type . '_%';
        
        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(*) as entries,
                    SUM(LENGTH(response_data)) as size,
                    SUM(CASE WHEN expires_at < NOW() THEN 1 ELSE 0 END) as expired
                 FROM {$table_name} 
                 WHERE cache_key LIKE %s",
                $pattern
            ),
            ARRAY_A
        );

        return $stats ?: array('entries' => 0, 'size' => 0, 'expired' => 0);
    }

    /**
     * Get transient type statistics.
     *
     * @since    1.0.0
     * @param    string    $type    Cache type.
     * @return   array     Statistics.
     */
    private function get_transient_type_stats($type) {
        global $wpdb;

        $pattern = '_transient_' . $this->cache_prefix . $type . '_%';
        
        $transients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, LENGTH(option_value) as size 
                 FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                $pattern
            )
        );

        $stats = array(
            'entries' => count($transients),
            'size' => array_sum(array_column($transients, 'size')),
            'expired' => 0
        );

        // Check for expired entries
        foreach ($transients as $transient) {
            $transient_name = str_replace('_transient_', '', $transient->option_name);
            if (get_transient($transient_name) === false) {
                $stats['expired']++;
            }
        }

        return $stats;
    }

    /**
     * Get total memory usage of cache.
     *
     * @since    1.0.0
     * @return   int    Memory usage in bytes.
     */
    private function get_memory_usage() {
        $total_size = 0;
        
        foreach (array_keys($this->cache_types) as $type) {
            $type_stats = $this->get_type_statistics($type);
            $total_size += isset($type_stats['size']) ? $type_stats['size'] : 0;
        }

        return $total_size;
    }

    /**
     * Get total number of cache entries.
     *
     * @since    1.0.0
     * @return   int    Total entries.
     */
    private function get_total_entries() {
        $total_entries = 0;
        
        foreach (array_keys($this->cache_types) as $type) {
            $type_stats = $this->get_type_statistics($type);
            $total_entries += isset($type_stats['entries']) ? $type_stats['entries'] : 0;
        }

        return $total_entries;
    }

    /**
     * Invalidate product cache when product is updated.
     *
     * @since    1.0.0
     * @param    int    $product_id    Product ID.
     */
    public function invalidate_product_cache($product_id) {
        $asin = get_post_meta($product_id, '_amazon_asin', true);
        
        if (!empty($asin)) {
            // Delete product-specific cache
            $this->delete($asin, 'product_data');
            $this->delete($asin, 'api_response');
            
            // Delete search cache that might contain this product
            $this->flush('search_results');
        }
    }

    /**
     * Handle cache settings change.
     *
     * @since    1.0.0
     */
    public function on_cache_settings_change() {
        $this->load_settings();
        
        if (!$this->settings['enabled']) {
            $this->flush();
        }
    }

    /**
     * Get cache configuration.
     *
     * @since    1.0.0
     * @return   array    Cache configuration.
     */
    public function get_configuration() {
        return array(
            'settings' => $this->settings,
            'cache_types' => $this->cache_types,
            'statistics' => $this->get_statistics()
        );
    }

    /**
     * Update cache configuration.
     *
     * @since    1.0.0
     * @param    array    $config    New configuration.
     * @return   bool     True on success.
     */
    public function update_configuration($config) {
        if (isset($config['settings'])) {
            foreach ($config['settings'] as $key => $value) {
                update_option("ams_cache_{$key}", $value);
            }
        }

        if (isset($config['cache_types'])) {
            update_option('ams_cache_types_config', $config['cache_types']);
        }

        $this->load_settings();
        return true;
    }

    /**
     * Get cache health status.
     *
     * @since    1.0.0
     * @return   array    Health status information.
     */
    public function get_health_status() {
        $stats = $this->get_statistics();
        $memory_usage_mb = $stats['memory_usage'] / (1024 * 1024);
        
        $health = array(
            'status' => 'good',
            'issues' => array(),
            'recommendations' => array()
        );

        // Check memory usage
        if ($memory_usage_mb > $this->settings['max_memory_usage']) {
            $health['status'] = 'warning';
            $health['issues'][] = 'Cache memory usage exceeds limit';
            $health['recommendations'][] = 'Consider increasing memory limit or reducing cache expiry times';
        }

        // Check hit ratio
        if ($stats['hit_ratio'] < 50) {
            $health['status'] = 'warning';
            $health['issues'][] = 'Low cache hit ratio';
            $health['recommendations'][] = 'Consider increasing cache expiry times';
        }

        // Check for expired entries
        foreach ($stats['by_type'] as $type => $type_stats) {
            if (isset($type_stats['expired']) && $type_stats['expired'] > 0) {
                $health['issues'][] = "Expired entries found in {$type} cache";
                $health['recommendations'][] = 'Run cache cleanup to remove expired entries';
            }
        }

        if (!empty($health['issues'])) {
            $health['status'] = count($health['issues']) > 2 ? 'critical' : 'warning';
        }

        return $health;
    }
}