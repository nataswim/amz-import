<?php

/**
 * Fired during plugin activation.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes
 * @author     Your Name <email@example.com>
 */
class Amazon_Product_Importer_Activator {

    /**
     * Plugin version.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    Plugin version.
     */
    private static $version = '1.0.0';

    /**
     * Minimum required WordPress version.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $min_wp_version    Minimum WordPress version.
     */
    private static $min_wp_version = '5.0';

    /**
     * Minimum required PHP version.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $min_php_version    Minimum PHP version.
     */
    private static $min_php_version = '7.4';

    /**
     * Minimum required WooCommerce version.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $min_wc_version    Minimum WooCommerce version.
     */
    private static $min_wc_version = '4.0';

    /**
     * Required PHP extensions.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $required_extensions    Required PHP extensions.
     */
    private static $required_extensions = array(
        'curl',
        'json',
        'mbstring',
        'openssl'
    );

    /**
     * Activation errors.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $activation_errors    Activation errors.
     */
    private static $activation_errors = array();

    /**
     * Activate the plugin.
     *
     * @since    1.0.0
     * @param    bool    $network_wide    Whether to enable the plugin for all sites in the network.
     */
    public static function activate($network_wide = false) {
        try {
            // Start activation process
            self::log_activation_start();

            // Check system requirements
            if (!self::check_system_requirements()) {
                self::handle_activation_error('System requirements not met.');
                return;
            }

            // Handle multisite activation
            if ($network_wide && is_multisite()) {
                self::activate_multisite();
            } else {
                self::activate_single_site();
            }

            // Set activation flag
            self::set_activation_flag();

            // Log successful activation
            self::log_activation_success();

        } catch (Exception $e) {
            self::handle_activation_error('Activation failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Activate plugin for multisite.
     *
     * @since    1.0.0
     */
    private static function activate_multisite() {
        global $wpdb;

        // Get all blog IDs
        $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");

        foreach ($blog_ids as $blog_id) {
            switch_to_blog($blog_id);
            self::activate_single_site();
            restore_current_blog();
        }
    }

    /**
     * Activate plugin for single site.
     *
     * @since    1.0.0
     */
    private static function activate_single_site() {
        // Create database tables
        self::create_database_tables();

        // Set default options
        self::set_default_options();

        // Setup capabilities
        self::setup_capabilities();

        // Schedule cron jobs
        self::schedule_cron_jobs();

        // Create required directories
        self::create_directories();

        // Setup rewrite rules
        self::setup_rewrite_rules();

        // Migrate from previous versions if needed
        self::migrate_from_previous_version();

        // Initialize default data
        self::initialize_default_data();

        // Clear any existing cache
        self::clear_cache();
    }

    /**
     * Check system requirements.
     *
     * @since    1.0.0
     * @return   bool    True if requirements are met.
     */
    private static function check_system_requirements() {
        $errors = array();

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), self::$min_wp_version, '<')) {
            $errors[] = sprintf(
                'WordPress version %s or higher is required. You are running version %s.',
                self::$min_wp_version,
                get_bloginfo('version')
            );
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, self::$min_php_version, '<')) {
            $errors[] = sprintf(
                'PHP version %s or higher is required. You are running version %s.',
                self::$min_php_version,
                PHP_VERSION
            );
        }

        // Check WooCommerce
        if (!class_exists('WooCommerce')) {
            $errors[] = 'WooCommerce plugin is required and must be activated.';
        } elseif (defined('WC_VERSION') && version_compare(WC_VERSION, self::$min_wc_version, '<')) {
            $errors[] = sprintf(
                'WooCommerce version %s or higher is required. You are running version %s.',
                self::$min_wc_version,
                WC_VERSION
            );
        }

        // Check required PHP extensions
        foreach (self::$required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $errors[] = sprintf('PHP extension "%s" is required.', $extension);
            }
        }

        // Check file permissions
        $upload_dir = wp_upload_dir();
        if (!wp_is_writable($upload_dir['basedir'])) {
            $errors[] = 'Upload directory is not writable.';
        }

        // Check memory limit
        $memory_limit = self::get_memory_limit();
        if ($memory_limit < 128 * 1024 * 1024) { // 128MB
            $errors[] = 'PHP memory limit should be at least 128MB for optimal performance.';
        }

        // Check max execution time
        $max_execution_time = ini_get('max_execution_time');
        if ($max_execution_time > 0 && $max_execution_time < 60) {
            $errors[] = 'PHP max_execution_time should be at least 60 seconds for large imports.';
        }

        // Store errors
        self::$activation_errors = $errors;

        return empty($errors);
    }

    /**
     * Create database tables.
     *
     * @since    1.0.0
     */
    private static function create_database_tables() {
        require_once AMAZON_PRODUCT_IMPORTER_PATH . 'includes/database/migrations/create-tables.php';
        
        $migration = new Amazon_Product_Importer_Create_Tables();
        $success = $migration->migrate();

        if (!$success) {
            throw new Exception('Failed to create database tables.');
        }
    }

    /**
     * Set default plugin options.
     *
     * @since    1.0.0
     */
    private static function set_default_options() {
        $default_options = array(
            // General settings
            'ams_plugin_version' => self::$version,
            'ams_activation_date' => current_time('mysql'),
            'ams_debug_mode' => false,
            
            // API settings
            'ams_access_key_id' => '',
            'ams_secret_access_key' => '',
            'ams_affiliate_tag' => '',
            'ams_default_region' => 'US',
            'ams_api_timeout' => 30,
            'ams_api_retry_attempts' => 3,
            
            // Import settings
            'ams_import_mode' => 'create_and_update',
            'ams_duplicate_handling' => 'skip',
            'ams_import_images' => true,
            'ams_import_categories' => true,
            'ams_import_variations' => true,
            'ams_auto_publish' => true,
            'ams_default_product_status' => 'draft',
            'ams_import_batch_size' => 5,
            'ams_import_delay' => 2,
            'ams_max_import_retries' => 3,
            
            // Image settings
            'ams_product_thumbnail_size' => 'large',
            'ams_max_product_images' => 10,
            'ams_max_image_file_size' => 5, // MB
            'ams_image_download_timeout' => 30,
            'ams_replace_existing_images' => false,
            'ams_preserve_original_names' => false,
            'ams_compress_images' => true,
            'ams_image_quality' => 85,
            
            // Category settings
            'ams_auto_create_categories' => true,
            'ams_category_min_depth' => 1,
            'ams_category_max_depth' => 4,
            'ams_category_prefix' => '',
            'ams_merge_similar_categories' => true,
            
            // Price settings
            'ams_auto_update_prices' => true,
            'ams_price_markup' => 0,
            'ams_price_markup_type' => 'percentage',
            'ams_round_prices' => true,
            'ams_price_round_precision' => 2,
            'ams_min_price' => 0,
            'ams_max_price' => 0,
            'ams_auto_currency_conversion' => true,
            
            // Sync settings
            'ams_price_sync_frequency' => 24, // hours
            'ams_product_update_frequency' => 48, // hours
            'ams_product_name_cron' => false,
            'ams_product_description_cron' => false,
            'ams_product_images_cron' => false,
            'ams_product_category_cron' => false,
            'ams_product_sku_cron' => false,
            'ams_product_attributes_cron' => false,
            'ams_product_variations_cron' => false,
            'ams_check_product_availability' => true,
            
            // Cache settings
            'ams_cache_enabled' => true,
            'ams_cache_default_expiry' => 3600,
            'ams_cache_max_memory' => 10, // MB
            'ams_cache_auto_cleanup' => true,
            'ams_cache_cleanup_probability' => 5, // %
            'ams_cache_compression' => true,
            
            // Logging settings
            'ams_logging_enabled' => true,
            'ams_log_min_level' => 'info',
            'ams_log_max_file_size' => 10, // MB
            'ams_log_max_files' => 5,
            'ams_log_to_file' => true,
            'ams_log_to_database' => true,
            'ams_log_to_email' => false,
            'ams_log_rotation' => true,
            'ams_log_auto_cleanup' => true,
            'ams_log_cleanup_days' => 30,
            
            // Email settings
            'ams_email_notifications' => false,
            'ams_notification_email' => get_option('admin_email'),
            'ams_email_on_import_complete' => false,
            'ams_email_on_sync_errors' => true,
            'ams_email_on_api_errors' => true,
            
            // Advanced settings
            'ams_enable_meta_logging' => false,
            'ams_store_price_history' => true,
            'ams_price_comparison_enabled' => true,
            'ams_price_alert_threshold' => 20, // %
            'ams_rollback_on_error' => true,
            'ams_create_import_backups' => false,
            
            // Product mapping settings
            'ams_title_max_length' => 100,
            'ams_description_max_length' => 5000,
            'ams_short_description_max_length' => 300,
            'ams_remove_html_tags' => true,
            'ams_clean_description' => true,
            'ams_use_features_as_short_desc' => true,
            'ams_feature_list_format' => 'bullets',
            'ams_include_brand_in_title' => false,
            'ams_title_case_conversion' => 'title',
            'ams_sku_format' => 'asin',
            'ams_sku_prefix' => 'AMZ-',
            'ams_weight_unit' => 'kg',
            'ams_dimension_unit' => 'cm',
            'ams_auto_create_attributes' => true,
            'ams_exclude_common_words' => true,
            'ams_default_weight' => 0.1,
            'ams_map_technical_details' => true,
            
            // Variation settings
            'ams_max_variations_per_product' => 50,
            'ams_auto_create_variation_attributes' => true,
            'ams_variation_image_handling' => 'individual',
            'ams_variation_price_inheritance' => false,
            'ams_variation_stock_management' => 'individual',
            'ams_variation_attribute_visibility' => true,
            'ams_attributes_for_variations' => true,
            'ams_variation_description_source' => 'parent',
            'ams_sync_variation_status' => true,
            'ams_remove_unused_variations' => false,
            'ams_variation_title_format' => '%parent_title% - %attributes%',
            'ams_attribute_term_limit' => 100
        );

        // Set options only if they don't exist
        foreach ($default_options as $option_name => $default_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $default_value);
            }
        }

        // Set plugin installed flag
        add_option('ams_plugin_installed', true);
    }

    /**
     * Setup user capabilities.
     *
     * @since    1.0.0
     */
    private static function setup_capabilities() {
        $capabilities = array(
            'manage_amazon_imports',
            'view_amazon_logs',
            'configure_amazon_settings',
            'export_amazon_data'
        );

        // Add capabilities to administrator role
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($capabilities as $capability) {
                $admin_role->add_cap($capability);
            }
        }

        // Add capabilities to shop manager role (WooCommerce)
        $shop_manager_role = get_role('shop_manager');
        if ($shop_manager_role) {
            $shop_manager_capabilities = array(
                'manage_amazon_imports',
                'view_amazon_logs'
            );
            
            foreach ($shop_manager_capabilities as $capability) {
                $shop_manager_role->add_cap($capability);
            }
        }
    }

    /**
     * Schedule cron jobs.
     *
     * @since    1.0.0
     */
    private static function schedule_cron_jobs() {
        // Schedule price sync
        if (!wp_next_scheduled('ams_price_sync_cron')) {
            wp_schedule_event(time(), 'daily', 'ams_price_sync_cron');
        }

        // Schedule product update
        if (!wp_next_scheduled('ams_product_update_cron')) {
            wp_schedule_event(time(), 'twicedaily', 'ams_product_update_cron');
        }

        // Schedule cache cleanup
        if (!wp_next_scheduled('ams_cache_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'ams_cache_cleanup');
        }

        // Schedule log cleanup
        if (!wp_next_scheduled('ams_log_cleanup')) {
            wp_schedule_event(time(), 'daily', 'ams_log_cleanup');
        }

        // Schedule database cleanup
        if (!wp_next_scheduled('ams_database_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'ams_database_cleanup');
        }
    }

    /**
     * Create required directories.
     *
     * @since    1.0.0
     */
    private static function create_directories() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/amazon-product-importer';

        $directories = array(
            $plugin_upload_dir,
            $plugin_upload_dir . '/logs',
            $plugin_upload_dir . '/cache',
            $plugin_upload_dir . '/imports',
            $plugin_upload_dir . '/exports',
            $plugin_upload_dir . '/temp'
        );

        foreach ($directories as $directory) {
            if (!file_exists($directory)) {
                wp_mkdir_p($directory);
                
                // Create index.php to prevent directory browsing
                $index_file = $directory . '/index.php';
                if (!file_exists($index_file)) {
                    file_put_contents($index_file, '<?php // Silence is golden');
                }
                
                // Create .htaccess for additional security
                $htaccess_file = $directory . '/.htaccess';
                if (!file_exists($htaccess_file)) {
                    file_put_contents($htaccess_file, 'deny from all');
                }
            }
        }
    }

    /**
     * Setup rewrite rules if needed.
     *
     * @since    1.0.0
     */
    private static function setup_rewrite_rules() {
        // Add any custom rewrite rules here if needed
        // For now, just flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Migrate from previous version.
     *
     * @since    1.0.0
     */
    private static function migrate_from_previous_version() {
        $installed_version = get_option('ams_plugin_version', '0.0.0');
        
        if (version_compare($installed_version, self::$version, '<')) {
            // Perform version-specific migrations
            self::perform_version_migrations($installed_version);
            
            // Update version
            update_option('ams_plugin_version', self::$version);
        }
    }

    /**
     * Perform version-specific migrations.
     *
     * @since    1.0.0
     * @param    string    $from_version    Version to migrate from.
     */
    private static function perform_version_migrations($from_version) {
        // Example migration logic
        if (version_compare($from_version, '0.9.0', '<')) {
            // Migrate from version < 0.9.0
            self::migrate_from_0_9_0();
        }

        // Add more version-specific migrations as needed
    }

    /**
     * Migration from version 0.9.0.
     *
     * @since    1.0.0
     */
    private static function migrate_from_0_9_0() {
        // Example: Rename old options
        $old_options = array(
            'amazon_importer_api_key' => 'ams_access_key_id',
            'amazon_importer_secret' => 'ams_secret_access_key',
            'amazon_importer_tag' => 'ams_affiliate_tag'
        );

        foreach ($old_options as $old_option => $new_option) {
            $old_value = get_option($old_option);
            if ($old_value !== false) {
                update_option($new_option, $old_value);
                delete_option($old_option);
            }
        }
    }

    /**
     * Initialize default data.
     *
     * @since    1.0.0
     */
    private static function initialize_default_data() {
        // Create default attribute mappings
        $default_mappings = array(
            'Color' => 'pa_color',
            'Size' => 'pa_size',
            'Style' => 'pa_style',
            'Material' => 'pa_material',
            'Pattern' => 'pa_pattern'
        );

        add_option('ams_attribute_mappings', $default_mappings);

        // Create default category mappings
        $default_category_mappings = array();
        add_option('ams_category_mapping', $default_category_mappings);

        // Create default cron schedules
        add_option('ams_cron_schedules', array(
            'price_sync' => array(
                'interval' => DAY_IN_SECONDS,
                'display' => 'Daily Price Sync'
            ),
            'product_update' => array(
                'interval' => 2 * DAY_IN_SECONDS,
                'display' => 'Bi-daily Product Update'
            )
        ));
    }

    /**
     * Clear cache.
     *
     * @since    1.0.0
     */
    private static function clear_cache() {
        // Clear WordPress object cache
        wp_cache_flush();

        // Clear WooCommerce cache if available
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients();
        }

        // Clear plugin-specific cache
        delete_transient('ams_system_status');
        delete_transient('ams_api_status');
    }

    /**
     * Set activation flag and timestamp.
     *
     * @since    1.0.0
     */
    private static function set_activation_flag() {
        update_option('ams_plugin_activated', true);
        update_option('ams_activation_timestamp', time());
        
        // Set flag for showing welcome notice
        update_option('ams_show_welcome_notice', true);
    }

    /**
     * Handle activation errors.
     *
     * @since    1.0.0
     * @param    string     $message     Error message.
     * @param    Exception  $exception   Exception object (optional).
     */
    private static function handle_activation_error($message, $exception = null) {
        // Log the error
        error_log('Amazon Product Importer Activation Error: ' . $message);
        
        if ($exception) {
            error_log('Exception details: ' . $exception->getTraceAsString());
        }

        // Store activation errors for display
        update_option('ams_activation_errors', self::$activation_errors);

        // Deactivate the plugin
        deactivate_plugins(plugin_basename(AMAZON_PRODUCT_IMPORTER_FILE));

        // Show error message
        $error_message = 'Amazon Product Importer activation failed: ' . $message;
        
        if (!empty(self::$activation_errors)) {
            $error_message .= "\n\nSystem requirements not met:\n";
            $error_message .= "• " . implode("\n• ", self::$activation_errors);
        }

        wp_die($error_message, 'Plugin Activation Error', array('back_link' => true));
    }

    /**
     * Log activation start.
     *
     * @since    1.0.0
     */
    private static function log_activation_start() {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event' => 'activation_start',
            'version' => self::$version,
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'user_id' => get_current_user_id(),
            'multisite' => is_multisite() ? 'yes' : 'no'
        );

        // Store in option temporarily (will be moved to proper logging later)
        update_option('ams_last_activation_log', $log_entry);
    }

    /**
     * Log successful activation.
     *
     * @since    1.0.0
     */
    private static function log_activation_success() {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event' => 'activation_success',
            'version' => self::$version,
            'execution_time' => microtime(true) - (self::get_activation_start_time() ?: microtime(true)),
            'memory_usage' => memory_get_peak_usage(true),
            'user_id' => get_current_user_id()
        );

        update_option('ams_activation_success_log', $log_entry);

        // Clean up activation errors if any
        delete_option('ams_activation_errors');
    }

    /**
     * Get memory limit in bytes.
     *
     * @since    1.0.0
     * @return   int    Memory limit in bytes.
     */
    private static function get_memory_limit() {
        $memory_limit = ini_get('memory_limit');
        
        if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
            if ($matches[2] == 'M') {
                $memory_limit = $matches[1] * 1024 * 1024; // nnnM -> nnn MB
            } else if ($matches[2] == 'K') {
                $memory_limit = $matches[1] * 1024; // nnnK -> nnn KB
            }
        }

        return (int) $memory_limit;
    }

    /**
     * Get activation start time.
     *
     * @since    1.0.0
     * @return   float|null    Activation start time or null.
     */
    private static function get_activation_start_time() {
        $log = get_option('ams_last_activation_log');
        return isset($log['timestamp']) ? strtotime($log['timestamp']) : null;
    }

    /**
     * Check if plugin is being activated for the first time.
     *
     * @since    1.0.0
     * @return   bool    True if first activation.
     */
    public static function is_first_activation() {
        return get_option('ams_plugin_installed') === false;
    }

    /**
     * Get system status for activation.
     *
     * @since    1.0.0
     * @return   array    System status information.
     */
    public static function get_system_status() {
        return array(
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : 'Not installed',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'allow_url_fopen' => ini_get('allow_url_fopen') ? 'Yes' : 'No',
            'curl_available' => extension_loaded('curl') ? 'Yes' : 'No',
            'json_available' => extension_loaded('json') ? 'Yes' : 'No',
            'mbstring_available' => extension_loaded('mbstring') ? 'Yes' : 'No',
            'openssl_available' => extension_loaded('openssl') ? 'Yes' : 'No',
            'requirements_met' => empty(self::$activation_errors) ? 'Yes' : 'No',
            'activation_errors' => self::$activation_errors
        );
    }

    /**
     * Run activation health check.
     *
     * @since    1.0.0
     * @return   array    Health check results.
     */
    public static function run_health_check() {
        $health_check = array(
            'status' => 'good',
            'checks' => array(),
            'recommendations' => array()
        );

        // Check database tables
        if (!self::verify_database_tables()) {
            $health_check['status'] = 'critical';
            $health_check['checks']['database'] = 'Failed - Some database tables are missing';
            $health_check['recommendations'][] = 'Deactivate and reactivate the plugin to recreate missing tables';
        } else {
            $health_check['checks']['database'] = 'Passed - All database tables exist';
        }

        // Check directories
        if (!self::verify_directories()) {
            $health_check['status'] = 'warning';
            $health_check['checks']['directories'] = 'Warning - Some directories are missing or not writable';
            $health_check['recommendations'][] = 'Check file permissions for the uploads directory';
        } else {
            $health_check['checks']['directories'] = 'Passed - All required directories exist and are writable';
        }

        // Check cron jobs
        if (!self::verify_cron_jobs()) {
            $health_check['status'] = 'warning';
            $health_check['checks']['cron'] = 'Warning - Some cron jobs are not scheduled';
            $health_check['recommendations'][] = 'Check if WordPress cron is working properly';
        } else {
            $health_check['checks']['cron'] = 'Passed - All cron jobs are scheduled';
        }

        return $health_check;
    }

    /**
     * Verify database tables exist.
     *
     * @since    1.0.0
     * @return   bool    True if all tables exist.
     */
    private static function verify_database_tables() {
        global $wpdb;

        $required_tables = array(
            'ams_import_logs',
            'ams_sync_history',
            'ams_price_history',
            'ams_cron_jobs',
            'ams_api_cache',
            'ams_product_mapping',
            'ams_error_logs'
        );

        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
            
            if (!$table_exists) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verify required directories exist.
     *
     * @since    1.0.0
     * @return   bool    True if all directories exist and are writable.
     */
    private static function verify_directories() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/amazon-product-importer';

        $required_directories = array(
            $plugin_upload_dir,
            $plugin_upload_dir . '/logs',
            $plugin_upload_dir . '/cache',
            $plugin_upload_dir . '/imports',
            $plugin_upload_dir . '/exports',
            $plugin_upload_dir . '/temp'
        );

        foreach ($required_directories as $directory) {
            if (!file_exists($directory) || !is_writable($directory)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verify cron jobs are scheduled.
     *
     * @since    1.0.0
     * @return   bool    True if all cron jobs are scheduled.
     */
    private static function verify_cron_jobs() {
        $required_crons = array(
            'ams_price_sync_cron',
            'ams_product_update_cron',
            'ams_cache_cleanup',
            'ams_log_cleanup',
            'ams_database_cleanup'
        );

        foreach ($required_crons as $cron) {
            if (!wp_next_scheduled($cron)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get activation summary.
     *
     * @since    1.0.0
     * @return   array    Activation summary.
     */
    public static function get_activation_summary() {
        return array(
            'version' => self::$version,
            'activated_on' => get_option('ams_activation_date'),
            'first_activation' => get_option('ams_plugin_installed') ? 'No' : 'Yes',
            'system_status' => self::get_system_status(),
            'health_check' => self::run_health_check(),
            'activation_log' => get_option('ams_activation_success_log'),
            'multisite' => is_multisite() ? 'Yes' : 'No',
            'network_activated' => is_plugin_active_for_network(plugin_basename(AMAZON_PRODUCT_IMPORTER_FILE)) ? 'Yes' : 'No'
        );
    }
}