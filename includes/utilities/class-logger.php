<?php

/**
 * The logging functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/utilities
 */

/**
 * The logging functionality of the plugin.
 *
 * Provides comprehensive logging capabilities for the plugin.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/utilities
 * @author     Your Name <email@example.com>
 */
class Amazon_Product_Importer_Logger {

    /**
     * Log levels.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $log_levels    Available log levels.
     */
    private static $log_levels = array(
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7
    );

    /**
     * Logger settings.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $settings    Logger configuration settings.
     */
    private $settings;

    /**
     * Database instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Database    $database    Database instance.
     */
    private $database;

    /**
     * Log handlers.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $handlers    Log handlers for different outputs.
     */
    private $handlers = array();

    /**
     * Current session ID.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $session_id    Current logging session ID.
     */
    private $session_id;

    /**
     * Log buffer for batch processing.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $log_buffer    Log entries buffer.
     */
    private $log_buffer = array();

    /**
     * Performance metrics.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $metrics    Performance metrics.
     */
    private $metrics = array(
        'logs_written' => 0,
        'start_time' => 0,
        'memory_peak' => 0
    );

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->load_settings();
        $this->database = new Amazon_Product_Importer_Database();
        $this->session_id = $this->generate_session_id();
        $this->metrics['start_time'] = microtime(true);
        
        $this->setup_handlers();
        $this->init_hooks();
    }

    /**
     * Load logger settings.
     *
     * @since    1.0.0
     */
    private function load_settings() {
        $this->settings = array(
            'enabled' => get_option('ams_logging_enabled', true),
            'min_level' => get_option('ams_log_min_level', 'info'),
            'max_file_size' => get_option('ams_log_max_file_size', 10), // MB
            'max_files' => get_option('ams_log_max_files', 5),
            'log_to_file' => get_option('ams_log_to_file', true),
            'log_to_database' => get_option('ams_log_to_database', true),
            'log_to_email' => get_option('ams_log_to_email', false),
            'email_recipients' => get_option('ams_log_email_recipients', array()),
            'email_min_level' => get_option('ams_log_email_min_level', 'error'),
            'log_rotation' => get_option('ams_log_rotation', true),
            'auto_cleanup' => get_option('ams_log_auto_cleanup', true),
            'cleanup_days' => get_option('ams_log_cleanup_days', 30),
            'buffer_size' => get_option('ams_log_buffer_size', 100),
            'enable_performance_logging' => get_option('ams_log_performance', false),
            'log_format' => get_option('ams_log_format', 'default'), // default, json, csv
            'include_trace' => get_option('ams_log_include_trace', false),
            'context_data' => get_option('ams_log_context_data', true),
            'log_user_actions' => get_option('ams_log_user_actions', true)
        );
    }

    /**
     * Setup log handlers.
     *
     * @since    1.0.0
     */
    private function setup_handlers() {
        if ($this->settings['log_to_file']) {
            $this->handlers['file'] = new Amazon_Product_Importer_File_Log_Handler($this->settings);
        }

        if ($this->settings['log_to_database']) {
            $this->handlers['database'] = new Amazon_Product_Importer_Database_Log_Handler($this->database, $this->settings);
        }

        if ($this->settings['log_to_email']) {
            $this->handlers['email'] = new Amazon_Product_Importer_Email_Log_Handler($this->settings);
        }
    }

    /**
     * Initialize WordPress hooks.
     *
     * @since    1.0.0
     */
    private function init_hooks() {
        // Cleanup hook
        if ($this->settings['auto_cleanup']) {
            add_action('ams_log_cleanup', array($this, 'cleanup_old_logs'));
            
            if (!wp_next_scheduled('ams_log_cleanup')) {
                wp_schedule_event(time(), 'daily', 'ams_log_cleanup');
            }
        }

        // Shutdown hook to flush buffer
        add_action('shutdown', array($this, 'flush_buffer'));

        // User action logging
        if ($this->settings['log_user_actions']) {
            add_action('wp_login', array($this, 'log_user_login'));
            add_action('wp_logout', array($this, 'log_user_logout'));
        }
    }

    /**
     * Log a message.
     *
     * @since    1.0.0
     * @param    string    $message    Log message.
     * @param    string    $level      Log level.
     * @param    array     $context    Additional context data.
     * @return   bool      True if logged successfully.
     */
    public function log($message, $level = 'info', $context = array()) {
        if (!$this->should_log($level)) {
            return false;
        }

        try {
            $log_entry = $this->create_log_entry($message, $level, $context);
            
            if ($this->settings['buffer_size'] > 0) {
                $this->add_to_buffer($log_entry);
            } else {
                $this->write_log_entry($log_entry);
            }

            $this->metrics['logs_written']++;
            $this->update_memory_peak();

            return true;

        } catch (Exception $e) {
            // Fail silently to prevent breaking the application
            error_log("Logger error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log emergency message.
     *
     * @since    1.0.0
     * @param    string    $message    Log message.
     * @param    array     $context    Context data.
     */
    public function emergency($message, $context = array()) {
        $this->log($message, 'emergency', $context);
    }

    /**
     * Log alert message.
     *
     * @since    1.0.0
     * @param    string    $message    Log message.
     * @param    array     $context    Context data.
     */
    public function alert($message, $context = array()) {
        $this->log($message, 'alert', $context);
    }

    /**
     * Log critical message.
     *
     * @since    1.0.0
     * @param    string    $message    Log message.
     * @param    array     $context    Context data.
     */
    public function critical($message, $context = array()) {
        $this->log($message, 'critical', $context);
    }

    /**
     * Log error message.
     *
     * @since    1.0.0
     * @param    string    $message    Log message.
     * @param    array     $context    Context data.
     */
    public function error($message, $context = array()) {
        $this->log($message, 'error', $context);
    }

    /**
     * Log warning message.
     *
     * @since    1.0.0
     * @param    string    $message    Log message.
     * @param    array     $context    Context data.
     */
    public function warning($message, $context = array()) {
        $this->log($message, 'warning', $context);
    }

    /**
     * Log notice message.
     *
     * @since    1.0.0
     * @param    string    $message    Log message.
     * @param    array     $context    Context data.
     */
    public function notice($message, $context = array()) {
        $this->log($message, 'notice', $context);
    }

    /**
     * Log info message.
     *
     * @since    1.0.0
     * @param    string    $message    Log message.
     * @param    array     $context    Context data.
     */
    public function info($message, $context = array()) {
        $this->log($message, 'info', $context);
    }

    /**
     * Log debug message.
     *
     * @since    1.0.0
     * @param    string    $message    Log message.
     * @param    array     $context    Context data.
     */
    public function debug($message, $context = array()) {
        $this->log($message, 'debug', $context);
    }

    /**
     * Log API request/response.
     *
     * @since    1.0.0
     * @param    string    $endpoint    API endpoint.
     * @param    array     $request     Request data.
     * @param    array     $response    Response data.
     * @param    string    $level       Log level.
     */
    public function log_api_call($endpoint, $request = array(), $response = array(), $level = 'debug') {
        $context = array(
            'type' => 'api_call',
            'endpoint' => $endpoint,
            'request' => $request,
            'response' => $response,
            'request_time' => microtime(true)
        );

        $message = "API Call to {$endpoint}";
        
        if (isset($response['status_code'])) {
            $message .= " - Status: {$response['status_code']}";
        }

        $this->log($message, $level, $context);
    }

    /**
     * Log performance metrics.
     *
     * @since    1.0.0
     * @param    string    $operation     Operation name.
     * @param    float     $start_time    Operation start time.
     * @param    array     $additional    Additional metrics.
     */
    public function log_performance($operation, $start_time, $additional = array()) {
        if (!$this->settings['enable_performance_logging']) {
            return;
        }

        $execution_time = microtime(true) - $start_time;
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);

        $context = array_merge(array(
            'type' => 'performance',
            'operation' => $operation,
            'execution_time' => $execution_time,
            'memory_usage' => $memory_usage,
            'memory_peak' => $memory_peak,
            'memory_usage_mb' => round($memory_usage / 1024 / 1024, 2),
            'memory_peak_mb' => round($memory_peak / 1024 / 1024, 2)
        ), $additional);

        $message = "Performance: {$operation} completed in " . number_format($execution_time, 3) . "s";
        $this->log($message, 'info', $context);
    }

    /**
     * Log import operation.
     *
     * @since    1.0.0
     * @param    string    $asin       Product ASIN.
     * @param    string    $action     Import action.
     * @param    string    $status     Operation status.
     * @param    string    $message    Log message.
     * @param    array     $context    Additional context.
     */
    public function log_import($asin, $action, $status, $message, $context = array()) {
        $context = array_merge($context, array(
            'type' => 'import',
            'asin' => $asin,
            'action' => $action,
            'status' => $status
        ));

        $level = ($status === 'success') ? 'info' : 'error';
        $full_message = "Import {$action} for {$asin}: {$message}";
        
        $this->log($full_message, $level, $context);

        // Also log to database import logs
        if ($this->database) {
            $this->database->insert_import_log(array(
                'asin' => $asin,
                'action' => $action,
                'status' => $status,
                'message' => $message
            ));
        }
    }

    /**
     * Log sync operation.
     *
     * @since    1.0.0
     * @param    string    $sync_type    Type of sync.
     * @param    array     $results      Sync results.
     * @param    array     $context      Additional context.
     */
    public function log_sync($sync_type, $results, $context = array()) {
        $context = array_merge($context, array(
            'type' => 'sync',
            'sync_type' => $sync_type,
            'results' => $results
        ));

        $message = "Sync {$sync_type} completed: {$results['success']} success, {$results['failed']} failed";
        $this->log($message, 'info', $context);
    }

    /**
     * Check if message should be logged based on level.
     *
     * @since    1.0.0
     * @param    string    $level    Log level.
     * @return   bool      True if should log.
     */
    private function should_log($level) {
        if (!$this->settings['enabled']) {
            return false;
        }

        $min_level = $this->settings['min_level'];
        
        if (!isset(self::$log_levels[$level]) || !isset(self::$log_levels[$min_level])) {
            return false;
        }

        return self::$log_levels[$level] <= self::$log_levels[$min_level];
    }

    /**
     * Create log entry structure.
     *
     * @since    1.0.0
     * @param    string    $message    Log message.
     * @param    string    $level      Log level.
     * @param    array     $context    Context data.
     * @return   array     Log entry.
     */
    private function create_log_entry($message, $level, $context) {
        $entry = array(
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'message' => $this->interpolate_message($message, $context),
            'session_id' => $this->session_id,
            'user_id' => get_current_user_id(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $this->get_client_ip(),
            'memory_usage' => memory_get_usage(true),
            'context' => $this->settings['context_data'] ? $context : array()
        );

        // Add stack trace for errors if enabled
        if ($this->settings['include_trace'] && in_array($level, array('emergency', 'alert', 'critical', 'error'))) {
            $entry['stack_trace'] = $this->get_stack_trace();
        }

        return $entry;
    }

    /**
     * Interpolate message with context values.
     *
     * @since    1.0.0
     * @param    string    $message    Message with placeholders.
     * @param    array     $context    Context values.
     * @return   string    Interpolated message.
     */
    private function interpolate_message($message, $context) {
        if (empty($context)) {
            return $message;
        }

        $replace = array();
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $replace['{' . $key . '}'] = $value;
            }
        }

        return strtr($message, $replace);
    }

    /**
     * Add log entry to buffer.
     *
     * @since    1.0.0
     * @param    array    $log_entry    Log entry.
     */
    private function add_to_buffer($log_entry) {
        $this->log_buffer[] = $log_entry;

        if (count($this->log_buffer) >= $this->settings['buffer_size']) {
            $this->flush_buffer();
        }
    }

    /**
     * Flush log buffer to handlers.
     *
     * @since    1.0.0
     */
    public function flush_buffer() {
        if (empty($this->log_buffer)) {
            return;
        }

        foreach ($this->log_buffer as $log_entry) {
            $this->write_log_entry($log_entry);
        }

        $this->log_buffer = array();
    }

    /**
     * Write log entry to all handlers.
     *
     * @since    1.0.0
     * @param    array    $log_entry    Log entry.
     */
    private function write_log_entry($log_entry) {
        foreach ($this->handlers as $handler) {
            try {
                $handler->handle($log_entry);
            } catch (Exception $e) {
                // Fail silently for individual handlers
                error_log("Log handler error: " . $e->getMessage());
            }
        }
    }

    /**
     * Generate unique session ID.
     *
     * @since    1.0.0
     * @return   string    Session ID.
     */
    private function generate_session_id() {
        return 'ams_' . uniqid() . '_' . time();
    }

    /**
     * Get client IP address.
     *
     * @since    1.0.0
     * @return   string    Client IP address.
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }

        return 'unknown';
    }

    /**
     * Get stack trace.
     *
     * @since    1.0.0
     * @return   string    Stack trace.
     */
    private function get_stack_trace() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        
        // Remove logger calls from trace
        $filtered_trace = array();
        foreach ($trace as $frame) {
            if (!isset($frame['class']) || $frame['class'] !== __CLASS__) {
                $filtered_trace[] = $frame;
            }
        }

        return json_encode($filtered_trace);
    }

    /**
     * Update memory peak metric.
     *
     * @since    1.0.0
     */
    private function update_memory_peak() {
        $current_memory = memory_get_peak_usage(true);
        if ($current_memory > $this->metrics['memory_peak']) {
            $this->metrics['memory_peak'] = $current_memory;
        }
    }

    /**
     * Get log entries from database.
     *
     * @since    1.0.0
     * @param    array    $args    Query arguments.
     * @return   array    Log entries.
     */
    public function get_logs($args = array()) {
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'level' => null,
            'date_from' => null,
            'date_to' => null,
            'search' => null,
            'context_type' => null,
            'session_id' => null,
            'order_by' => 'timestamp',
            'order' => 'DESC'
        );

        $args = array_merge($defaults, $args);

        if (isset($this->handlers['database'])) {
            return $this->handlers['database']->get_logs($args);
        }

        return array();
    }

    /**
     * Get log statistics.
     *
     * @since    1.0.0
     * @param    string    $period    Time period (day, week, month, all).
     * @return   array     Log statistics.
     */
    public function get_statistics($period = 'week') {
        $stats = array(
            'total_logs' => 0,
            'by_level' => array(),
            'by_type' => array(),
            'errors_count' => 0,
            'session_stats' => $this->metrics
        );

        if (isset($this->handlers['database'])) {
            $db_stats = $this->handlers['database']->get_statistics($period);
            $stats = array_merge($stats, $db_stats);
        }

        return $stats;
    }

    /**
     * Clean up old log entries.
     *
     * @since    1.0.0
     * @param    int    $days    Number of days to keep.
     * @return   int    Number of entries cleaned.
     */
    public function cleanup_old_logs($days = null) {
        if ($days === null) {
            $days = $this->settings['cleanup_days'];
        }

        $cleaned = 0;

        foreach ($this->handlers as $handler) {
            if (method_exists($handler, 'cleanup')) {
                $cleaned += $handler->cleanup($days);
            }
        }

        $this->log("Log cleanup completed: {$cleaned} entries removed", 'info', array(
            'type' => 'system',
            'operation' => 'cleanup',
            'entries_removed' => $cleaned
        ));

        return $cleaned;
    }

    /**
     * Export logs to file.
     *
     * @since    1.0.0
     * @param    array     $args      Export arguments.
     * @param    string    $format    Export format (csv, json, txt).
     * @return   string    File path or content.
     */
    public function export_logs($args = array(), $format = 'csv') {
        $logs = $this->get_logs($args);
        
        if (empty($logs)) {
            return '';
        }

        switch ($format) {
            case 'csv':
                return $this->export_to_csv($logs);
            case 'json':
                return $this->export_to_json($logs);
            case 'txt':
                return $this->export_to_text($logs);
            default:
                return $this->export_to_csv($logs);
        }
    }

    /**
     * Export logs to CSV format.
     *
     * @since    1.0.0
     * @param    array    $logs    Log entries.
     * @return   string   CSV content.
     */
    private function export_to_csv($logs) {
        $csv_content = "Timestamp,Level,Message,User ID,IP Address,Session ID\n";
        
        foreach ($logs as $log) {
            $csv_content .= sprintf(
                "%s,%s,\"%s\",%s,%s,%s\n",
                $log->timestamp,
                $log->level,
                str_replace('"', '""', $log->message),
                $log->user_id ?? '',
                $log->ip_address ?? '',
                $log->session_id ?? ''
            );
        }

        return $csv_content;
    }

    /**
     * Export logs to JSON format.
     *
     * @since    1.0.0
     * @param    array    $logs    Log entries.
     * @return   string   JSON content.
     */
    private function export_to_json($logs) {
        return json_encode($logs, JSON_PRETTY_PRINT);
    }

    /**
     * Export logs to text format.
     *
     * @since    1.0.0
     * @param    array    $logs    Log entries.
     * @return   string   Text content.
     */
    private function export_to_text($logs) {
        $content = '';
        
        foreach ($logs as $log) {
            $content .= sprintf(
                "[%s] [%s] %s\n",
                $log->timestamp,
                strtoupper($log->level),
                $log->message
            );
        }

        return $content;
    }

    /**
     * Log user login.
     *
     * @since    1.0.0
     * @param    string    $user_login    Username.
     */
    public function log_user_login($user_login) {
        $this->log("User login: {$user_login}", 'info', array(
            'type' => 'user_action',
            'action' => 'login',
            'username' => $user_login
        ));
    }

    /**
     * Log user logout.
     *
     * @since    1.0.0
     */
    public function log_user_logout() {
        $user = wp_get_current_user();
        $this->log("User logout: {$user->user_login}", 'info', array(
            'type' => 'user_action',
            'action' => 'logout',
            'username' => $user->user_login
        ));
    }

    /**
     * Set minimum log level.
     *
     * @since    1.0.0
     * @param    string    $level    Minimum log level.
     */
    public function set_min_level($level) {
        if (isset(self::$log_levels[$level])) {
            $this->settings['min_level'] = $level;
            update_option('ams_log_min_level', $level);
        }
    }

    /**
     * Enable or disable logging.
     *
     * @since    1.0.0
     * @param    bool    $enabled    Whether to enable logging.
     */
    public function set_enabled($enabled) {
        $this->settings['enabled'] = (bool) $enabled;
        update_option('ams_logging_enabled', $enabled);
    }

    /**
     * Get logger configuration.
     *
     * @since    1.0.0
     * @return   array    Logger configuration.
     */
    public function get_configuration() {
        return array(
            'settings' => $this->settings,
            'log_levels' => self::$log_levels,
            'handlers' => array_keys($this->handlers),
            'session_id' => $this->session_id,
            'metrics' => $this->metrics
        );
    }

    /**
     * Update logger configuration.
     *
     * @since    1.0.0
     * @param    array    $config    New configuration.
     * @return   bool     True on success.
     */
    public function update_configuration($config) {
        if (isset($config['settings'])) {
            foreach ($config['settings'] as $key => $value) {
                if (array_key_exists($key, $this->settings)) {
                    $this->settings[$key] = $value;
                    update_option("ams_log_{$key}", $value);
                }
            }

            // Reinitialize handlers with new settings
            $this->handlers = array();
            $this->setup_handlers();
            
            return true;
        }

        return false;
    }

    /**
     * Get log file paths.
     *
     * @since    1.0.0
     * @return   array    Array of log file paths.
     */
    public function get_log_files() {
        if (isset($this->handlers['file'])) {
            return $this->handlers['file']->get_log_files();
        }

        return array();
    }

    /**
     * Rotate log files.
     *
     * @since    1.0.0
     * @return   bool    True on success.
     */
    public function rotate_logs() {
        if (isset($this->handlers['file'])) {
            return $this->handlers['file']->rotate_logs();
        }

        return false;
    }

    /**
     * Get available log levels.
     *
     * @since    1.0.0
     * @return   array    Log levels.
     */
    public static function get_log_levels() {
        return array_keys(self::$log_levels);
    }

    /**
     * Check if level is valid.
     *
     * @since    1.0.0
     * @param    string    $level    Log level to check.
     * @return   bool      True if valid.
     */
    public static function is_valid_level($level) {
        return isset(self::$log_levels[$level]);
    }

    /**
     * Get current session metrics.
     *
     * @since    1.0.0
     * @return   array    Session metrics.
     */
    public function get_session_metrics() {
        $this->metrics['current_memory'] = memory_get_usage(true);
        $this->metrics['execution_time'] = microtime(true) - $this->metrics['start_time'];
        
        return $this->metrics;
    }

    /**
     * Create a child logger with specific context.
     *
     * @since    1.0.0
     * @param    array    $context    Default context for child logger.
     * @return   Amazon_Product_Importer_Child_Logger    Child logger instance.
     */
    public function create_child_logger($context = array()) {
        return new Amazon_Product_Importer_Child_Logger($this, $context);
    }
}

/**
 * Child logger class for contextual logging.
 *
 * @since    1.0.0
 */
class Amazon_Product_Importer_Child_Logger {
    
    /**
     * Parent logger instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Logger    $parent_logger    Parent logger.
     */
    private $parent_logger;

    /**
     * Default context for this child logger.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $default_context    Default context.
     */
    private $default_context;

    /**
     * Initialize child logger.
     *
     * @since    1.0.0
     * @param    Amazon_Product_Importer_Logger    $parent_logger     Parent logger.
     * @param    array                             $default_context   Default context.
     */
    public function __construct($parent_logger, $default_context = array()) {
        $this->parent_logger = $parent_logger;
        $this->default_context = $default_context;
    }

    /**
     * Log message with merged context.
     *
     * @since    1.0.0
     * @param    string    $message    Log message.
     * @param    string    $level      Log level.
     * @param    array     $context    Additional context.
     * @return   bool      True if logged.
     */
    public function log($message, $level = 'info', $context = array()) {
        $merged_context = array_merge($this->default_context, $context);
        return $this->parent_logger->log($message, $level, $merged_context);
    }

    /**
     * Magic method to forward calls to parent logger.
     *
     * @since    1.0.0
     * @param    string    $method    Method name.
     * @param    array     $args      Method arguments.
     * @return   mixed     Method result.
     */
    public function __call($method, $args) {
        if (method_exists($this->parent_logger, $method)) {
            // If it's a logging method, merge context
            if (in_array($method, array('emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'))) {
                $context = isset($args[1]) ? $args[1] : array();
                $args[1] = array_merge($this->default_context, $context);
            }
            
            return call_user_func_array(array($this->parent_logger, $method), $args);
        }

        throw new BadMethodCallException("Method {$method} does not exist");
    }
}