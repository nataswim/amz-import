<?php

/**
 * Cache management utility
 */
class Amazon_Product_Importer_Cache {

    private $prefix = 'amazon_api_';
    private $default_expiration = 3600; // 1 hour

    /**
     * Get cached value
     */
    public function get($key) {
        $cache_key = $this->prefix . $key;
        return get_transient($cache_key);
    }

    /**
     * Set cached value
     */
    public function set($key, $value, $expiration = null) {
        if ($expiration === null) {
            $expiration = $this->default_expiration;
        }

        $cache_key = $this->prefix . $key;
        return set_transient($cache_key, $value, $expiration);
    }

    /**
     * Delete cached value
     */
    public function delete($key) {
        $cache_key = $this->prefix . $key;
        return delete_transient($cache_key);
    }

    /**
     * Clear all plugin cache
     */
    public function clear_all() {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 OR option_name LIKE %s",
                '_transient_' . $this->prefix . '%',
                '_transient_timeout_' . $this->prefix . '%'
            )
        );

        return true;
    }

    /**
     * Get cache statistics
     */
    public function get_stats() {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                '_transient_' . $this->prefix . '%'
            )
        );

        return array(
            'cached_items' => $count,
            'prefix' => $this->prefix
        );
    }

    /**
     * Check if cache is enabled
     */
    public function is_enabled() {
        return get_option('amazon_importer_cache_enabled', true);
    }

    /**
     * Get cache with fallback
     */
    public function get_or_set($key, $callback, $expiration = null) {
        if (!$this->is_enabled()) {
            return call_user_func($callback);
        }

        $cached_value = $this->get($key);
        
        if ($cached_value !== false) {
            return $cached_value;
        }

        $value = call_user_func($callback);
        $this->set($key, $value, $expiration);

        return $value;
    }
}