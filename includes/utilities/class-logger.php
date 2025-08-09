<?php

/**
 * Logger utility class
 */
class Amazon_Product_Importer_Logger {

    private $log_table;
    private $debug_mode;

    public function __construct() {
        global $wpdb;
        $this->log_table = $wpdb->prefix . 'amazon_import_logs';
        $this->debug_mode = get_option('amazon_importer_debug_mode', false);
    }

    /**
     * Log message
     */
    public function log($level, $message, $context = array()) {
        // Only log debug messages if debug mode is enabled
        if ($level === 'debug' && !$this->debug_mode) {
            return;
        }

        global $wpdb;

        $log_data = array(
            'asin' => isset($context['asin']) ? $context['asin'] : '',
            'product_id' => isset($context['product_id']) ? $context['product_id'] : 0,
            'action' => isset($context['action']) ? $context['action'] : 'general',
            'status' => $level,
            'message' => $this->format_message($message, $context),
            'created_at' => current_time('mysql')
        );

        $wpdb->insert($this->log_table, $log_data);

        // Also log to WordPress debug log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $formatted_message = sprintf(
                '[Amazon Importer] [%s] %s %s',
                strtoupper($level),
                $message,
                !empty($context) ? '- ' . wp_json_encode($context) : ''
            );
            
            error_log($formatted_message);
        }
    }

    /**
     * Format log message with context
     */
    private function format_message($message, $context) {
        if (empty($context)) {
            return $message;
        }

        // Replace placeholders in message
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $message = str_replace('{' . $key . '}', $value, $message);
            }
        }

        return $message;
    }

    /**
     * Get recent logs
     */
    public function get_recent_logs($limit = 100, $level = null) {
        global $wpdb;

        $where_clause = '';
        $params = array();

        if ($level) {
            $where_clause = 'WHERE status = %s';
            $params[] = $level;
        }

        $params[] = $limit;

        $query = "SELECT * FROM {$this->log_table} {$where_clause} ORDER BY created_at DESC LIMIT %d";

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Clear old logs
     */
    public function clear_old_logs($days = 30) {
        global $wpdb;

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->log_table} WHERE created_at < %s",
            $cutoff_date
        ));

        $this->log('info', 'Old logs cleared', array(
            'deleted_count' => $deleted,
            'cutoff_date' => $cutoff_date
        ));

        return $deleted;
    }

    /**
     * Clear all logs
     */
    public function clear_all_logs() {
        global $wpdb;

        $deleted = $wpdb->query("DELETE FROM {$this->log_table}");

        return $deleted;
    }

    /**
     * Get log statistics
     */
    public function get_log_stats($days = 7) {
        global $wpdb;

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count 
             FROM {$this->log_table} 
             WHERE created_at >= %s 
             GROUP BY status",
            $cutoff_date
        ));

        $formatted_stats = array();
        foreach ($stats as $stat) {
            $formatted_stats[$stat->status] = $stat->count;
        }

        return $formatted_stats;
    }

    /**
     * Log import activity
     */
    public function log_import($asin, $product_id, $action, $status, $message) {
        $this->log($status, $message, array(
            'asin' => $asin,
            'product_id' => $product_id,
            'action' => $action
        ));
    }

    /**
     * Log API activity
     */
    public function log_api($action, $status, $message, $context = array()) {
        $context['action'] = $action;
        $this->log($status, $message, $context);
    }

    /**
     * Log sync activity
     */
    public function log_sync($asin, $product_id, $sync_type, $status, $message) {
        $this->log($status, $message, array(
            'asin' => $asin,
            'product_id' => $product_id,
            'action' => 'sync_' . $sync_type
        ));
    }
}