<?php
/**
 * Cron Manager Class
 *
 * This class handles all cron job scheduling, execution, and monitoring
 * for the Amazon Product Importer plugin. It manages synchronization tasks,
 * maintenance operations, and background processing.
 *
 * @link       https://mycreanet.fr
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/cron
 */

/**
 * Cron manager class for handling scheduled tasks
 *
 * Manages all cron jobs including product synchronization, price updates,
 * stock monitoring, and maintenance tasks.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/cron
 * @author     Your Name <your.https://mycreanet.fr>
 */
class Amazon_Product_Importer_Cron_Manager {

    /**
     * Cron configuration
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $config    Cron configuration
     */
    private static $config;

    /**
     * Logger instance
     *
     * @since    1.0.0
     * @access   private
     * @var      object    $logger    Logger instance
     */
    private static $logger;

    /**
     * Active locks tracking
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $active_locks    Active cron locks
     */
    private static $active_locks = array();

    /**
     * Execution statistics
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $stats    Execution statistics
     */
    private static $stats = array();

    /**
     * Initialize the cron manager
     *
     * @since    1.0.0
     */
    public static function init() {
        self::load_config();
        self::init_logger();
        self::register_hooks();
        self::init_stats();
    }

    /**
     * Load cron configuration
     *
     * @since    1.0.0
     * @access   private
     */
    private static function load_config() {
        $config_file = AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'config/cron-schedules.php';
        if (file_exists($config_file)) {
            self::$config = include $config_file;
        } else {
            self::$config = array();
        }
    }

    /**
     * Initialize logger
     *
     * @since    1.0.0
     * @access   private
     */
    private static function init_logger() {
        if (class_exists('Amazon_Product_Importer_Logger')) {
            self::$logger = new Amazon_Product_Importer_Logger('cron');
        }
    }

    /**
     * Register WordPress hooks
     *
     * @since    1.0.0
     * @access   private
     */
    private static function register_hooks() {
        // Register cron job hooks
        if (isset(self::$config['cron_jobs'])) {
            foreach (self::$config['cron_jobs'] as $job_key => $job_config) {
                if (isset($job_config['hook']) && isset($job_config['callback'])) {
                    add_action($job_config['hook'], array(__CLASS__, 'execute_job'), 10, 3);
                }
            }
        }

        // Register custom schedules
        add_filter('cron_schedules', array(__CLASS__, 'add_custom_schedules'));

        // Add action for manual cron execution
        add_action('wp_ajax_amazon_execute_cron', array(__CLASS__, 'ajax_execute_cron'));

        // Register shutdown hook for cleanup
        add_action('shutdown', array(__CLASS__, 'cleanup'));
    }

    /**
     * Initialize statistics
     *
     * @since    1.0.0
     * @access   private
     */
    private static function init_stats() {
        self::$stats = array(
            'jobs_executed' => 0,
            'jobs_failed' => 0,
            'total_execution_time' => 0,
            'last_execution' => null,
            'errors' => array()
        );
    }

    /**
     * Add custom cron schedules
     *
     * @since    1.0.0
     * @param    array    $schedules    Existing schedules
     * @return   array                  Modified schedules
     */
    public static function add_custom_schedules($schedules) {
        if (isset(self::$config['custom_schedules'])) {
            foreach (self::$config['custom_schedules'] as $key => $schedule) {
                $schedules[$key] = $schedule;
            }
        }

        return $schedules;
    }

    /**
     * Schedule all cron jobs
     *
     * @since    1.0.0
     */
    public static function schedule_all_jobs() {
        if (!isset(self::$config['cron_jobs'])) {
            return;
        }

        foreach (self::$config['cron_jobs'] as $job_key => $job_config) {
            if (isset($job_config['enabled']) && $job_config['enabled']) {
                self::schedule_job($job_key);
            }
        }

        self::log_info('All cron jobs scheduled');
    }

    /**
     * Schedule individual cron job
     *
     * @since    1.0.0
     * @param    string    $job_key    Job key
     * @return   bool                  True if scheduled, false otherwise
     */
    public static function schedule_job($job_key) {
        if (!isset(self::$config['cron_jobs'][$job_key])) {
            return false;
        }

        $job_config = self::$config['cron_jobs'][$job_key];

        // Check if job is enabled
        if (!isset($job_config['enabled']) || !$job_config['enabled']) {
            return false;
        }

        // Check dependencies
        if (!self::check_job_dependencies($job_config)) {
            return false;
        }

        // Check conditions
        if (!self::check_job_conditions($job_config)) {
            return false;
        }

        $hook = $job_config['hook'];
        $schedule = isset($job_config['schedule']) ? $job_config['schedule'] : 'hourly';

        // Clear existing schedule
        wp_clear_scheduled_hook($hook);

        // Schedule the job
        if ($schedule === 'single_event') {
            // Single event jobs are scheduled on-demand
            return true;
        } else {
            $scheduled = wp_schedule_event(time(), $schedule, $hook, array($job_key));
            
            if ($scheduled !== false) {
                self::log_info("Job scheduled: {$job_key}", array(
                    'hook' => $hook,
                    'schedule' => $schedule,
                    'next_run' => wp_next_scheduled($hook)
                ));
                return true;
            }
        }

        return false;
    }

    /**
     * Unschedule cron job
     *
     * @since    1.0.0
     * @param    string    $job_key    Job key
     * @return   bool                  True if unscheduled, false otherwise
     */
    public static function unschedule_job($job_key) {
        if (!isset(self::$config['cron_jobs'][$job_key])) {
            return false;
        }

        $job_config = self::$config['cron_jobs'][$job_key];
        $hook = $job_config['hook'];

        wp_clear_scheduled_hook($hook);

        self::log_info("Job unscheduled: {$job_key}", array('hook' => $hook));

        return true;
    }

    /**
     * Execute cron job
     *
     * @since    1.0.0
     * @param    string    $job_key    Job key
     * @param    array     $args       Additional arguments
     * @param    bool      $force      Force execution regardless of locks
     * @return   bool                  True if executed successfully, false otherwise
     */
    public static function execute_job($job_key = null, $args = array(), $force = false) {
        $start_time = microtime(true);

        try {
            // Determine job key from current action if not provided
            if (!$job_key) {
                $job_key = self::get_job_key_from_action();
            }

            if (!$job_key || !isset(self::$config['cron_jobs'][$job_key])) {
                return false;
            }

            $job_config = self::$config['cron_jobs'][$job_key];

            self::log_info("Starting cron job: {$job_key}");

            // Check execution conditions
            if (!$force && !self::can_execute_job($job_key, $job_config)) {
                self::log_info("Job execution skipped: {$job_key} (conditions not met)");
                return false;
            }

            // Acquire lock
            if (!self::acquire_lock($job_key, $job_config)) {
                self::log_info("Job execution skipped: {$job_key} (lock acquisition failed)");
                return false;
            }

            // Set memory and time limits
            self::set_execution_limits($job_config);

            // Execute the job
            $result = self::execute_job_callback($job_key, $job_config, $args);

            // Release lock
            self::release_lock($job_key);

            $execution_time = microtime(true) - $start_time;

            // Update statistics
            self::update_execution_stats($job_key, $result, $execution_time);

            // Log result
            if ($result) {
                self::log_info("Job completed successfully: {$job_key}", array(
                    'execution_time' => round($execution_time, 2)
                ));
            } else {
                self::log_error("Job failed: {$job_key}", array(
                    'execution_time' => round($execution_time, 2)
                ));
            }

            return $result;

        } catch (Exception $e) {
            self::release_lock($job_key);
            
            self::log_error("Job exception: {$job_key}", array(
                'error' => $e->getMessage(),
                'execution_time' => round(microtime(true) - $start_time, 2)
            ));

            return false;
        }
    }

    /**
     * Execute job callback method
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $job_key      Job key
     * @param    array     $job_config   Job configuration
     * @param    array     $args         Arguments
     * @return   bool                    True if successful, false otherwise
     */
    private static function execute_job_callback($job_key, $job_config, $args) {
        $callback = $job_config['callback'];

        // Parse callback method
        if (strpos($callback, '::') !== false) {
            list($class, $method) = explode('::', $callback);
            
            if ($class === __CLASS__ && method_exists(__CLASS__, $method)) {
                return call_user_func(array(__CLASS__, $method), $args);
            }
        }

        // Fallback to direct method call if callback format matches our methods
        $method_name = str_replace('Amazon_Product_Importer_Cron_Manager::', '', $callback);
        if (method_exists(__CLASS__, $method_name)) {
            return call_user_func(array(__CLASS__, $method_name), $args);
        }

        self::log_error("Invalid callback for job: {$job_key}", array('callback' => $callback));
        return false;
    }

    /**
     * Synchronize product prices
     *
     * @since    1.0.0
     * @param    array    $args    Arguments
     * @return   bool             True if successful, false otherwise
     */
    public static function sync_product_prices($args = array()) {
        try {
            self::log_info('Starting price synchronization');

            // Get products that need price sync
            $products = self::get_products_for_sync('price');
            if (empty($products)) {
                self::log_info('No products found for price sync');
                return true;
            }

            $batch_size = self::get_batch_size('amazon_product_importer_sync_prices');
            $processed = 0;
            $success_count = 0;
            $error_count = 0;

            // Load required classes
            require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/api/class-amazon-api.php';
            require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/import/class-price-updater.php';

            $api = new Amazon_Product_Importer_Amazon_API();
            $price_updater = new Amazon_Product_Importer_Price_Updater();

            // Process products in batches
            $product_batches = array_chunk($products, $batch_size);
            
            foreach ($product_batches as $batch) {
                $asins = array_column($batch, 'asin');
                
                // Get current prices from Amazon
                $amazon_data = $api->get_product_details($asins);
                
                if (!is_wp_error($amazon_data)) {
                    foreach ($batch as $product) {
                        $asin = $product['asin'];
                        $product_id = $product['product_id'];
                        
                        if (isset($amazon_data['items']) && is_array($amazon_data['items'])) {
                            foreach ($amazon_data['items'] as $amazon_product) {
                                if ($amazon_product['asin'] === $asin) {
                                    $result = $price_updater->update_product_price($product_id, $amazon_product);
                                    
                                    if ($result) {
                                        $success_count++;
                                    } else {
                                        $error_count++;
                                    }
                                    break;
                                }
                            }
                        }
                        
                        $processed++;
                        
                        // Update last sync timestamp
                        update_post_meta($product_id, '_amazon_last_sync', current_time('mysql'));
                    }
                }

                // Throttle API requests
                sleep(1);

                // Check memory usage
                if (self::should_stop_for_memory()) {
                    break;
                }
            }

            self::log_info('Price synchronization completed', array(
                'processed' => $processed,
                'success' => $success_count,
                'errors' => $error_count
            ));

            return true;

        } catch (Exception $e) {
            self::log_error('Price synchronization failed', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Synchronize stock status
     *
     * @since    1.0.0
     * @param    array    $args    Arguments
     * @return   bool             True if successful, false otherwise
     */
    public static function sync_stock_status($args = array()) {
        try {
            self::log_info('Starting stock synchronization');

            $products = self::get_products_for_sync('stock');
            if (empty($products)) {
                self::log_info('No products found for stock sync');
                return true;
            }

            $batch_size = self::get_batch_size('amazon_product_importer_sync_stock');
            $processed = 0;
            $success_count = 0;

            require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/api/class-amazon-api.php';

            $api = new Amazon_Product_Importer_Amazon_API();

            foreach (array_chunk($products, $batch_size) as $batch) {
                $asins = array_column($batch, 'asin');
                $amazon_data = $api->get_product_details($asins);

                if (!is_wp_error($amazon_data) && isset($amazon_data['items'])) {
                    foreach ($batch as $product) {
                        $asin = $product['asin'];
                        $product_id = $product['product_id'];

                        foreach ($amazon_data['items'] as $amazon_product) {
                            if ($amazon_product['asin'] === $asin) {
                                $availability = $amazon_product['availability'] ?? array();
                                $stock_status = self::determine_stock_status($availability);

                                // Update WooCommerce stock status
                                $wc_product = wc_get_product($product_id);
                                if ($wc_product) {
                                    $wc_product->set_stock_status($stock_status);
                                    $wc_product->save();
                                    $success_count++;
                                }

                                update_post_meta($product_id, '_amazon_last_sync', current_time('mysql'));
                                break;
                            }
                        }
                        $processed++;
                    }
                }

                sleep(1);

                if (self::should_stop_for_memory()) {
                    break;
                }
            }

            self::log_info('Stock synchronization completed', array(
                'processed' => $processed,
                'success' => $success_count
            ));

            return true;

        } catch (Exception $e) {
            self::log_error('Stock synchronization failed', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Update product information
     *
     * @since    1.0.0
     * @param    array    $args    Arguments
     * @return   bool             True if successful, false otherwise
     */
    public static function update_product_info($args = array()) {
        try {
            self::log_info('Starting product info update');

            $products = self::get_products_for_sync('info');
            if (empty($products)) {
                return true;
            }

            $batch_size = self::get_batch_size('amazon_product_importer_update_products');
            
            require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/api/class-amazon-api.php';
            require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/import/class-product-importer.php';

            $api = new Amazon_Product_Importer_Amazon_API();
            $importer = new Amazon_Product_Importer_Product_Importer();

            $processed = 0;
            $success_count = 0;

            foreach (array_chunk($products, $batch_size) as $batch) {
                $asins = array_column($batch, 'asin');
                $amazon_data = $api->get_product_details($asins);

                if (!is_wp_error($amazon_data) && isset($amazon_data['items'])) {
                    foreach ($batch as $product) {
                        $asin = $product['asin'];
                        $product_id = $product['product_id'];

                        foreach ($amazon_data['items'] as $amazon_product) {
                            if ($amazon_product['asin'] === $asin) {
                                $result = $importer->update_product_info($product_id, $amazon_product);
                                
                                if (!is_wp_error($result)) {
                                    $success_count++;
                                }

                                update_post_meta($product_id, '_amazon_last_sync', current_time('mysql'));
                                break;
                            }
                        }
                        $processed++;
                    }
                }

                sleep(1);

                if (self::should_stop_for_memory()) {
                    break;
                }
            }

            self::log_info('Product info update completed', array(
                'processed' => $processed,
                'success' => $success_count
            ));

            return true;

        } catch (Exception $e) {
            self::log_error('Product info update failed', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Synchronize product images
     *
     * @since    1.0.0
     * @param    array    $args    Arguments
     * @return   bool             True if successful, false otherwise
     */
    public static function sync_product_images($args = array()) {
        try {
            self::log_info('Starting image synchronization');

            $products = self::get_products_for_sync('images');
            if (empty($products)) {
                return true;
            }

            require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/api/class-amazon-api.php';
            require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/import/class-image-handler.php';

            $api = new Amazon_Product_Importer_Amazon_API();
            $image_handler = new Amazon_Product_Importer_Image_Handler();

            $batch_size = self::get_batch_size('amazon_product_importer_sync_images');
            $processed = 0;
            $success_count = 0;

            foreach (array_chunk($products, $batch_size) as $batch) {
                $asins = array_column($batch, 'asin');
                $amazon_data = $api->get_product_details($asins);

                if (!is_wp_error($amazon_data) && isset($amazon_data['items'])) {
                    foreach ($batch as $product) {
                        $asin = $product['asin'];
                        $product_id = $product['product_id'];

                        foreach ($amazon_data['items'] as $amazon_product) {
                            if ($amazon_product['asin'] === $asin) {
                                $result = $image_handler->sync_product_images($product_id, $amazon_product['images']);
                                
                                if ($result) {
                                    $success_count++;
                                }

                                update_post_meta($product_id, '_amazon_last_sync', current_time('mysql'));
                                break;
                            }
                        }
                        $processed++;
                    }
                }

                sleep(2); // Longer delay for image processing

                if (self::should_stop_for_memory()) {
                    break;
                }
            }

            self::log_info('Image synchronization completed', array(
                'processed' => $processed,
                'success' => $success_count
            ));

            return true;

        } catch (Exception $e) {
            self::log_error('Image synchronization failed', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Synchronize product variations
     *
     * @since    1.0.0
     * @param    array    $args    Arguments
     * @return   bool             True if successful, false otherwise
     */
    public static function sync_product_variations($args = array()) {
        try {
            self::log_info('Starting variation synchronization');

            $parent_products = self::get_parent_products_for_sync();
            if (empty($parent_products)) {
                return true;
            }

            require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/api/class-amazon-api.php';
            require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/import/class-variation-handler.php';

            $api = new Amazon_Product_Importer_Amazon_API();
            $variation_handler = new Amazon_Product_Importer_Variation_Handler();

            $processed = 0;
            $success_count = 0;

            foreach ($parent_products as $product) {
                $parent_asin = $product['asin'];
                $product_id = $product['product_id'];

                $variations_data = $api->get_product_variations($parent_asin);

                if (!is_wp_error($variations_data)) {
                    $result = $variation_handler->sync_variations($product_id, $variations_data);
                    
                    if ($result) {
                        $success_count++;
                    }

                    update_post_meta($product_id, '_amazon_last_sync', current_time('mysql'));
                }

                $processed++;
                sleep(1);

                if (self::should_stop_for_memory()) {
                    break;
                }
            }

            self::log_info('Variation synchronization completed', array(
                'processed' => $processed,
                'success' => $success_count
            ));

            return true;

        } catch (Exception $e) {
            self::log_error('Variation synchronization failed', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Update product categories
     *
     * @since    1.0.0
     * @param    array    $args    Arguments
     * @return   bool             True if successful, false otherwise
     */
    public static function update_product_categories($args = array()) {
        try {
            self::log_info('Starting category update');

            $products = self::get_products_for_category_update();
            if (empty($products)) {
                return true;
            }

            require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/api/class-amazon-api.php';
            require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/import/class-category-handler.php';

            $api = new Amazon_Product_Importer_Amazon_API();
            $category_handler = new Amazon_Product_Importer_Category_Handler();

            $processed = 0;
            $success_count = 0;

            foreach ($products as $product) {
                $asin = $product['asin'];
                $product_id = $product['product_id'];

                $product_data = $api->get_product_details($asin);

                if (!is_wp_error($product_data) && isset($product_data['browse_nodes'])) {
                    $result = $category_handler->update_product_categories($product_id, $product_data['browse_nodes']);
                    
                    if ($result) {
                        $success_count++;
                    }
                }

                $processed++;
                update_post_meta($product_id, '_amazon_categories_last_update', current_time('mysql'));

                if ($processed % 10 === 0) {
                    sleep(1);
                }

                if (self::should_stop_for_memory()) {
                    break;
                }
            }

            self::log_info('Category update completed', array(
                'processed' => $processed,
                'success' => $success_count
            ));

            return true;

        } catch (Exception $e) {
            self::log_error('Category update failed', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Perform health check
     *
     * @since    1.0.0
     * @param    array    $args    Arguments
     * @return   bool             True if successful, false otherwise
     */
    public static function perform_health_check($args = array()) {
        try {
            $health_status = array(
                'api_connection' => false,
                'database_connection' => false,
                'wp_cron_status' => false,
                'memory_usage' => 0,
                'disk_space' => 0,
                'error_rate' => 0,
                'timestamp' => current_time('mysql')
            );

            // Check API connection
            try {
                require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/api/class-amazon-api.php';
                $api = new Amazon_Product_Importer_Amazon_API();
                $health_status['api_connection'] = $api->test_connection();
            } catch (Exception $e) {
                $health_status['api_connection'] = false;
            }

            // Check database connection
            $health_status['database_connection'] = (bool) $GLOBALS['wpdb']->check_connection();

            // Check WP Cron status
            $health_status['wp_cron_status'] = !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON;

            // Check memory usage
            $health_status['memory_usage'] = memory_get_usage(true);

            // Check disk space
            $health_status['disk_space'] = disk_free_space(ABSPATH);

            // Calculate error rate
            $health_status['error_rate'] = self::calculate_error_rate();

            // Store health status
            update_option('amazon_product_importer_health_status', $health_status);

            // Send alerts if needed
            self::send_health_alerts($health_status);

            self::log_info('Health check completed', $health_status);

            return true;

        } catch (Exception $e) {
            self::log_error('Health check failed', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Retry failed imports
     *
     * @since    1.0.0
     * @param    array    $args    Arguments
     * @return   bool             True if successful, false otherwise
     */
    public static function retry_failed_imports($args = array()) {
        try {
            self::log_info('Starting failed import retry');

            // Get failed imports from the last 24 hours
            global $wpdb;
            $table_name = $wpdb->prefix . AMAZON_PRODUCT_IMPORTER_TABLE_IMPORTS;
            
            $failed_imports = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_name} 
                WHERE status = 'failed' 
                AND import_date >= %s 
                AND retry_count < %d
                ORDER BY import_date DESC 
                LIMIT %d",
                date('Y-m-d H:i:s', strtotime('-24 hours')),
                3, // Max retry count
                10  // Limit retries per execution
            ));

            if (empty($failed_imports)) {
                self::log_info('No failed imports to retry');
                return true;
            }

            require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/api/class-amazon-api.php';
            require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/import/class-product-importer.php';

            $api = new Amazon_Product_Importer_Amazon_API();
            $importer = new Amazon_Product_Importer_Product_Importer();

            $retry_count = 0;
            $success_count = 0;

            foreach ($failed_imports as $failed_import) {
                try {
                    $asin = $failed_import->asin;
                    
                    // Get fresh product data
                    $product_data = $api->get_product_details($asin);
                    
                    if (!is_wp_error($product_data)) {
                        $result = $importer->import_product($product_data);
                        
                        if (!is_wp_error($result)) {
                            // Update import record as successful
                            $wpdb->update(
                                $table_name,
                                array(
                                    'status' => 'success',
                                    'product_id' => $result['product_id'],
                                    'retry_count' => $failed_import->retry_count + 1,
                                    'import_date' => current_time('mysql')
                                ),
                                array('id' => $failed_import->id),
                                array('%s', '%d', '%d', '%s'),
                                array('%d')
                            );
                            
                            $success_count++;
                        } else {
                            // Increment retry count
                            $wpdb->update(
                                $table_name,
                                array('retry_count' => $failed_import->retry_count + 1),
                                array('id' => $failed_import->id),
                                array('%d'),
                                array('%d')
                            );
                        }
                    }

                    $retry_count++;
                    sleep(1);

                } catch (Exception $e) {
                    self::log_error("Retry failed for ASIN {$failed_import->asin}", array('error' => $e->getMessage()));
                }
            }

            self::log_info('Failed import retry completed', array(
                'retried' => $retry_count,
                'success' => $success_count
            ));

            return true;

        } catch (Exception $e) {
            self::log_error('Failed import retry failed', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Clean up cache
     *
     * @since    1.0.0
     * @param    array    $args    Arguments
     * @return   bool             True if successful, false otherwise
     */
    public static function cleanup_cache($args = array()) {
        try {
            self::log_info('Starting cache cleanup');

            // Clean expired transients
            global $wpdb;
            
            $deleted_transients = $wpdb->query(
                "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b 
                WHERE a.option_name LIKE '_transient_%' 
                AND a.option_name NOT LIKE '_transient_timeout_%' 
                AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
                AND b.option_value < UNIX_TIMESTAMP()"
            );

            // Clean Amazon-specific cache
            $amazon_cache_deleted = $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_amazon_%' 
                OR option_name LIKE '_transient_timeout_amazon_%'"
            );

            // Clean object cache if available
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }

            self::log_info('Cache cleanup completed', array(
                'deleted_transients' => $deleted_transients,
                'amazon_cache_deleted' => $amazon_cache_deleted
            ));

            return true;

        } catch (Exception $e) {
            self::log_error('Cache cleanup failed', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Clean up logs
     *
     * @since    1.0.0
     * @param    array    $args    Arguments
     * @return   bool             True if successful, false otherwise
     */
    public static function cleanup_logs($args = array()) {
        try {
            self::log_info('Starting log cleanup');

            $retention_days = isset(self::$config['cron_jobs']['amazon_product_importer_log_cleanup']['retention_days']) 
                ? self::$config['cron_jobs']['amazon_product_importer_log_cleanup']['retention_days'] 
                : 30;

            // Clean import logs
            global $wpdb;
            $import_table = $wpdb->prefix . AMAZON_PRODUCT_IMPORTER_TABLE_IMPORTS;
            $sync_table = $wpdb->prefix . AMAZON_PRODUCT_IMPORTER_TABLE_SYNC_LOG;

            $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

            // Clean old import records
            $deleted_imports = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$import_table} WHERE import_date < %s",
                $cutoff_date
            ));

            // Clean old sync logs
            $deleted_sync_logs = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$sync_table} WHERE sync_date < %s",
                $cutoff_date
            ));

            // Clean WordPress error logs
            $error_log_file = ini_get('error_log');
            if ($error_log_file && file_exists($error_log_file)) {
                $log_size_before = filesize($error_log_file);
                
                // Rotate log if it's too large (> 10MB)
                if ($log_size_before > 10 * 1024 * 1024) {
                    $backup_file = $error_log_file . '.old';
                    if (file_exists($backup_file)) {
                        unlink($backup_file);
                    }
                    rename($error_log_file, $backup_file);
                    touch($error_log_file);
                }
            }

            self::log_info('Log cleanup completed', array(
                'deleted_imports' => $deleted_imports,
                'deleted_sync_logs' => $deleted_sync_logs,
                'retention_days' => $retention_days
            ));

            return true;

        } catch (Exception $e) {
            self::log_error('Log cleanup failed', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Reset rate limits
     *
     * @since    1.0.0
     * @param    array    $args    Arguments
     * @return   bool             True if successful, false otherwise
     */
    public static function reset_rate_limits($args = array()) {
        try {
            // Reset API rate limit counters
            delete_option('amazon_api_rate_limiter');
            delete_option('amazon_api_request_count');
            delete_option('amazon_api_last_reset');
            
            // Set new reset timestamp
            update_option('amazon_api_last_reset', current_time('mysql'));

            self::log_info('Rate limits reset');

            return true;

        } catch (Exception $e) {
            self::log_error('Rate limit reset failed', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Clean up orphaned products
     *
     * @since    1.0.0
     * @param    array    $args    Arguments
     * @return   bool             True if successful, false otherwise
     */
    public static function cleanup_orphaned_products($args = array()) {
        try {
            self::log_info('Starting orphaned product cleanup');

            global $wpdb;

            // Find products with Amazon metadata but no longer in import table
            $orphaned_products = $wpdb->get_results("
                SELECT p.ID, pm.meta_value as asin 
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                LEFT JOIN {$wpdb->prefix}" . AMAZON_PRODUCT_IMPORTER_TABLE_IMPORTS . " i ON pm.meta_value = i.asin
                WHERE p.post_type = 'product'
                AND pm.meta_key = '_amazon_asin'
                AND i.asin IS NULL
                AND p.post_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");

            $cleaned_count = 0;

            foreach ($orphaned_products as $product) {
                // Remove Amazon-specific metadata
                $amazon_meta_keys = array(
                    '_amazon_asin',
                    '_amazon_parent_asin',
                    '_amazon_region',
                    '_amazon_associate_tag',
                    '_amazon_imported_date',
                    '_amazon_last_sync',
                    '_amazon_sync_enabled'
                );

                foreach ($amazon_meta_keys as $meta_key) {
                    delete_post_meta($product->ID, $meta_key);
                }

                $cleaned_count++;
            }

            self::log_info('Orphaned product cleanup completed', array(
                'cleaned_products' => $cleaned_count
            ));

            return true;

        } catch (Exception $e) {
            self::log_error('Orphaned product cleanup failed', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Update statistics
     *
     * @since    1.0.0
     * @param    array    $args    Arguments
     * @return   bool             True if successful, false otherwise
     */
    public static function update_statistics($args = array()) {
        try {
            global $wpdb;

            $stats = array(
                'total_products' => 0,
                'amazon_products' => 0,
                'synced_products' => 0,
                'failed_imports' => 0,
                'last_sync_time' => null,
                'api_calls_today' => 0,
                'error_rate' => 0,
                'updated_at' => current_time('mysql')
            );

            // Count total products
            $stats['total_products'] = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status != 'trash'"
            );

            // Count Amazon products
            $stats['amazon_products'] = $wpdb->get_var(
                "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p 
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                WHERE p.post_type = 'product' AND pm.meta_key = '_amazon_asin'"
            );

            // Count synced products (synced in last 7 days)
            $stats['synced_products'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p 
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                WHERE p.post_type = 'product' 
                AND pm.meta_key = '_amazon_last_sync' 
                AND pm.meta_value > %s",
                date('Y-m-d H:i:s', strtotime('-7 days'))
            ));

            // Count failed imports (last 24 hours)
            $import_table = $wpdb->prefix . AMAZON_PRODUCT_IMPORTER_TABLE_IMPORTS;
            $stats['failed_imports'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$import_table} 
                WHERE status = 'failed' AND import_date > %s",
                date('Y-m-d H:i:s', strtotime('-24 hours'))
            ));

            // Get last sync time
            $stats['last_sync_time'] = $wpdb->get_var(
                "SELECT MAX(pm.meta_value) FROM {$wpdb->postmeta} pm 
                WHERE pm.meta_key = '_amazon_last_sync'"
            );

            // Calculate API calls today
            $stats['api_calls_today'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$import_table} WHERE DATE(import_date) = %s",
                current_time('Y-m-d')
            ));

            // Calculate error rate
            $stats['error_rate'] = self::calculate_error_rate();

            // Update statistics
            update_option('amazon_product_importer_statistics', $stats);

            self::log_info('Statistics updated', $stats);

            return true;

        } catch (Exception $e) {
            self::log_error('Statistics update failed', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Sync single product
     *
     * @since    1.0.0
     * @param    array    $args    Arguments including product_id
     * @return   bool             True if successful, false otherwise
     */
    public static function sync_single_product($args = array()) {
        try {
            $product_id = isset($args[0]) ? intval($args[0]) : 0;
            
            if (!$product_id) {
                return false;
            }

            $asin = get_post_meta($product_id, '_amazon_asin', true);
            if (!$asin) {
                return false;
            }

            self::log_info("Starting single product sync: {$product_id} (ASIN: {$asin})");

            require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/api/class-amazon-api.php';
            require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/import/class-product-importer.php';

            $api = new Amazon_Product_Importer_Amazon_API();
            $importer = new Amazon_Product_Importer_Product_Importer();

            $product_data = $api->get_product_details($asin);

            if (!is_wp_error($product_data)) {
                $result = $importer->update_product_info($product_id, $product_data);
                
                if (!is_wp_error($result)) {
                    update_post_meta($product_id, '_amazon_last_sync', current_time('mysql'));
                    update_post_meta($product_id, '_amazon_force_sync', 'no');
                    
                    self::log_info("Single product sync completed: {$product_id}");
                    return true;
                }
            }

            return false;

        } catch (Exception $e) {
            self::log_error('Single product sync failed', array(
                'product_id' => $product_id ?? 0,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    // Helper methods...

    /**
     * Get products for synchronization
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $sync_type    Type of sync (price, stock, info, images)
     * @return   array                   Products to sync
     */
    private static function get_products_for_sync($sync_type) {
        global $wpdb;

        $where_conditions = array(
            "p.post_type = 'product'",
            "p.post_status = 'publish'",
            "pm_asin.meta_key = '_amazon_asin'",
            "pm_sync.meta_key = '_amazon_sync_enabled'",
            "pm_sync.meta_value = 'yes'"
        );

        // Add sync type specific conditions
        switch ($sync_type) {
            case 'price':
                if (get_option('amazon_product_importer_sync_price') !== '1') {
                    return array();
                }
                break;
            case 'stock':
                if (get_option('amazon_product_importer_sync_stock') !== '1') {
                    return array();
                }
                break;
            case 'info':
                if (get_option('amazon_product_importer_sync_title') !== '1' && 
                    get_option('amazon_product_importer_sync_description') !== '1') {
                    return array();
                }
                break;
            case 'images':
                if (get_option('amazon_product_importer_sync_images') !== '1') {
                    return array();
                }
                break;
        }

        // Add time-based filtering for last sync
        $sync_interval = get_option('amazon_product_importer_sync_interval', 'every_6_hours');
        $hours_map = array(
            'every_15_minutes' => 0.25,
            'every_30_minutes' => 0.5,
            'hourly' => 1,
            'every_6_hours' => 6,
            'twicedaily' => 12,
            'daily' => 24
        );
        
        $hours_ago = isset($hours_map[$sync_interval]) ? $hours_map[$sync_interval] : 6;
        $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$hours_ago} hours"));

        $where_conditions[] = $wpdb->prepare(
            "(pm_last_sync.meta_key = '_amazon_last_sync' AND pm_last_sync.meta_value < %s) OR pm_last_sync.meta_value IS NULL",
            $cutoff_time
        );

        $where_clause = implode(' AND ', $where_conditions);

        $query = "
            SELECT DISTINCT p.ID as product_id, pm_asin.meta_value as asin
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_asin ON p.ID = pm_asin.post_id
            INNER JOIN {$wpdb->postmeta} pm_sync ON p.ID = pm_sync.post_id
            LEFT JOIN {$wpdb->postmeta} pm_last_sync ON p.ID = pm_last_sync.post_id
            WHERE {$where_clause}
            ORDER BY pm_last_sync.meta_value ASC
            LIMIT 100
        ";

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Get parent products for variation sync
     *
     * @since    1.0.0
     * @access   private
     * @return   array    Parent products
     */
    private static function get_parent_products_for_sync() {
        global $wpdb;

        $query = "
            SELECT DISTINCT p.ID as product_id, pm_asin.meta_value as asin
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_asin ON p.ID = pm_asin.post_id
            INNER JOIN {$wpdb->postmeta} pm_sync ON p.ID = pm_sync.post_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm_asin.meta_key = '_amazon_asin'
            AND pm_sync.meta_key = '_amazon_sync_enabled'
            AND pm_sync.meta_value = 'yes'
            AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm_parent 
                WHERE pm_parent.post_id = p.ID 
                AND pm_parent.meta_key = '_amazon_parent_asin'
                AND pm_parent.meta_value = pm_asin.meta_value
            )
            LIMIT 20
        ";

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Get products for category update
     *
     * @since    1.0.0
     * @access   private
     * @return   array    Products for category update
     */
    private static function get_products_for_category_update() {
        global $wpdb;

        $cutoff_time = date('Y-m-d H:i:s', strtotime('-7 days'));

        $query = $wpdb->prepare("
            SELECT DISTINCT p.ID as product_id, pm_asin.meta_value as asin
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_asin ON p.ID = pm_asin.post_id
            LEFT JOIN {$wpdb->postmeta} pm_cat_update ON p.ID = pm_cat_update.post_id AND pm_cat_update.meta_key = '_amazon_categories_last_update'
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm_asin.meta_key = '_amazon_asin'
            AND (pm_cat_update.meta_value < %s OR pm_cat_update.meta_value IS NULL)
            LIMIT 30
        ", $cutoff_time);

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Check if job can be executed
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $job_key      Job key
     * @param    array     $job_config   Job configuration
     * @return   bool                    True if can execute, false otherwise
     */
    private static function can_execute_job($job_key, $job_config) {
        // Check if job is enabled
        if (!isset($job_config['enabled']) || !$job_config['enabled']) {
            return false;
        }

        // Check dependencies
        if (!self::check_job_dependencies($job_config)) {
            return false;
        }

        // Check conditions
        if (!self::check_job_conditions($job_config)) {
            return false;
        }

        // Check system resources
        if (!self::check_system_resources()) {
            return false;
        }

        return true;
    }

    /**
     * Check job dependencies
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $job_config    Job configuration
     * @return   bool                    True if dependencies met, false otherwise
     */
    private static function check_job_dependencies($job_config) {
        if (!isset($job_config['dependencies'])) {
            return true;
        }

        foreach ($job_config['dependencies'] as $dependency) {
            $option_value = get_option($dependency, '');
            if (empty($option_value) || $option_value === '0') {
                return false;
            }
        }

        return true;
    }

    /**
     * Check job conditions
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $job_config    Job configuration
     * @return   bool                    True if conditions met, false otherwise
     */
    private static function check_job_conditions($job_config) {
        if (!isset($job_config['conditions'])) {
            return true;
        }

        $conditions = $job_config['conditions'];

        // Handle single condition
        if (isset($conditions['setting'])) {
            $conditions = array($conditions);
        }

        $results = array();
        foreach ($conditions as $condition) {
            if (!isset($condition['setting']) || !isset($condition['value'])) {
                continue;
            }

            $option_value = get_option($condition['setting'], '');
            $results[] = ($option_value === $condition['value']);
        }

        // Handle OR operator
        if (isset($job_config['conditions'][0]['operator']) && $job_config['conditions'][0]['operator'] === 'OR') {
            return in_array(true, $results);
        }

        // Default AND operator
        return !in_array(false, $results);
    }

    /**
     * Check system resources
     *
     * @since    1.0.0
     * @access   private
     * @return   bool    True if resources available, false otherwise
     */
    private static function check_system_resources() {
        // Check memory usage
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $memory_usage = memory_get_usage(true);
        
        if ($memory_usage / $memory_limit > 0.8) {
            return false;
        }

        // Check if maintenance mode is enabled
        if (get_option('amazon_product_importer_maintenance_mode', false)) {
            return false;
        }

        return true;
    }

    /**
     * Acquire execution lock
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $job_key      Job key
     * @param    array     $job_config   Job configuration
     * @return   bool                    True if lock acquired, false otherwise
     */
    private static function acquire_lock($job_key, $job_config) {
        $lock_key = "amazon_cron_lock_{$job_key}";
        $lock_timeout = isset($job_config['timeout']) ? $job_config['timeout'] : 300;

        // Check if lock already exists
        $existing_lock = get_transient($lock_key);
        if ($existing_lock) {
            return false;
        }

        // Set lock
        $lock_data = array(
            'job_key' => $job_key,
            'start_time' => current_time('mysql'),
            'pid' => getmypid(),
            'timeout' => $lock_timeout
        );

        $lock_acquired = set_transient($lock_key, $lock_data, $lock_timeout);

        if ($lock_acquired) {
            self::$active_locks[$job_key] = $lock_key;
        }

        return $lock_acquired;
    }

    /**
     * Release execution lock
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $job_key    Job key
     */
    private static function release_lock($job_key) {
        if (isset(self::$active_locks[$job_key])) {
            delete_transient(self::$active_locks[$job_key]);
            unset(self::$active_locks[$job_key]);
        }
    }

    /**
     * Set execution limits for job
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $job_config    Job configuration
     */
    private static function set_execution_limits($job_config) {
        // Set memory limit if specified
        if (isset(self::$config['sync_config']['memory_limit'])) {
            ini_set('memory_limit', self::$config['sync_config']['memory_limit']);
        }

        // Set execution time limit
        if (isset(self::$config['sync_config']['execution_time_limit'])) {
            set_time_limit(self::$config['sync_config']['execution_time_limit']);
        }

        // Set error reporting for cron jobs
        if (!WP_DEBUG) {
            error_reporting(E_ERROR | E_PARSE);
        }
    }

    /**
     * Get batch size for job
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $job_key    Job key
     * @return   int                   Batch size
     */
    private static function get_batch_size($job_key) {
        if (isset(self::$config['cron_jobs'][$job_key]['batch_size'])) {
            return self::$config['cron_jobs'][$job_key]['batch_size'];
        }

        return isset(self::$config['sync_config']['batch_processing_delay']) ? 25 : 25;
    }

    /**
     * Determine stock status from availability data
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $availability    Availability data
     * @return   string                    Stock status
     */
    private static function determine_stock_status($availability) {
        if (empty($availability)) {
            return 'outofstock';
        }

        $type = isset($availability['type']) ? strtolower($availability['type']) : '';
        $message = isset($availability['message']) ? strtolower($availability['message']) : '';

        // Map Amazon availability to WooCommerce stock status
        if (in_array($type, array('now', 'available'))) {
            return 'instock';
        }

        if (strpos($message, 'out of stock') !== false || 
            strpos($message, 'unavailable') !== false ||
            strpos($message, 'discontinued') !== false) {
            return 'outofstock';
        }

        if (strpos($message, 'back order') !== false || 
            strpos($message, 'pre-order') !== false) {
            return 'onbackorder';
        }

        return 'instock'; // Default to in stock
    }

    /**
     * Check if should stop for memory
     *
     * @since    1.0.0
     * @access   private
     * @return   bool    True if should stop, false otherwise
     */
    private static function should_stop_for_memory() {
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $memory_usage = memory_get_usage(true);
        
        return ($memory_usage / $memory_limit) > 0.9;
    }

    /**
     * Calculate error rate
     *
     * @since    1.0.0
     * @access   private
     * @return   float    Error rate percentage
     */
    private static function calculate_error_rate() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . AMAZON_PRODUCT_IMPORTER_TABLE_IMPORTS;
        
        $total_imports = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE import_date > %s",
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));

        if ($total_imports == 0) {
            return 0;
        }

        $failed_imports = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE status = 'failed' AND import_date > %s",
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));

        return round(($failed_imports / $total_imports) * 100, 2);
    }

    /**
     * Send health alerts
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $health_status    Health status data
     */
    private static function send_health_alerts($health_status) {
        $alerts = array();

        if (!$health_status['api_connection']) {
            $alerts[] = 'Amazon API connection failed';
        }

        if (!$health_status['database_connection']) {
            $alerts[] = 'Database connection failed';
        }

        if ($health_status['error_rate'] > 10) {
            $alerts[] = "High error rate: {$health_status['error_rate']}%";
        }

        if ($health_status['memory_usage'] > (512 * 1024 * 1024)) { // 512MB
            $alerts[] = 'High memory usage detected';
        }

        if (!empty($alerts)) {
            self::log_error('Health check alerts', array('alerts' => $alerts));
            
            // Send notification if enabled
            if (get_option('amazon_product_importer_email_notifications', false)) {
                // Implementation for email notifications
            }
        }
    }

    /**
     * Update execution statistics
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $job_key         Job key
     * @param    bool      $result          Execution result
     * @param    float     $execution_time  Execution time
     */
    private static function update_execution_stats($job_key, $result, $execution_time) {
        self::$stats['jobs_executed']++;
        self::$stats['total_execution_time'] += $execution_time;
        self::$stats['last_execution'] = current_time('mysql');

        if (!$result) {
            self::$stats['jobs_failed']++;
            self::$stats['errors'][] = array(
                'job' => $job_key,
                'time' => current_time('mysql'),
                'execution_time' => $execution_time
            );
        }

        // Keep only last 10 errors
        if (count(self::$stats['errors']) > 10) {
            self::$stats['errors'] = array_slice(self::$stats['errors'], -10);
        }

        // Update persistent stats
        update_option('amazon_cron_stats', self::$stats);
    }

    /**
     * Get job key from current action
     *
     * @since    1.0.0
     * @access   private
     * @return   string|null    Job key or null
     */
    private static function get_job_key_from_action() {
        $current_action = current_action();
        
        if (isset(self::$config['cron_jobs'])) {
            foreach (self::$config['cron_jobs'] as $job_key => $job_config) {
                if (isset($job_config['hook']) && $job_config['hook'] === $current_action) {
                    return $job_key;
                }
            }
        }

        return null;
    }

    /**
     * AJAX handler for manual cron execution
     *
     * @since    1.0.0
     */
    public static function ajax_execute_cron() {
        check_ajax_referer('amazon_cron_nonce', 'nonce');

        if (!current_user_can('manage_amazon_product_importer')) {
            wp_die('Insufficient permissions');
        }

        $job_key = sanitize_text_field($_POST['job_key']);
        
        if (!isset(self::$config['cron_jobs'][$job_key])) {
            wp_send_json_error('Invalid job key');
        }

        $result = self::execute_job($job_key, array(), true);

        if ($result) {
            wp_send_json_success('Job executed successfully');
        } else {
            wp_send_json_error('Job execution failed');
        }
    }

    /**
     * Cleanup on shutdown
     *
     * @since    1.0.0
     */
    public static function cleanup() {
        // Release any remaining locks
        foreach (self::$active_locks as $job_key => $lock_key) {
            delete_transient($lock_key);
        }
        
        self::$active_locks = array();
    }

    /**
     * Get cron status
     *
     * @since    1.0.0
     * @return   array    Cron status information
     */
    public static function get_cron_status() {
        $status = array(
            'wp_cron_enabled' => !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON,
            'scheduled_jobs' => array(),
            'active_locks' => self::$active_locks,
            'statistics' => self::$stats,
            'next_runs' => array()
        );

        if (isset(self::$config['cron_jobs'])) {
            foreach (self::$config['cron_jobs'] as $job_key => $job_config) {
                $hook = $job_config['hook'];
                $next_run = wp_next_scheduled($hook);
                
                $status['scheduled_jobs'][$job_key] = array(
                    'enabled' => $job_config['enabled'] ?? false,
                    'hook' => $hook,
                    'schedule' => $job_config['schedule'] ?? 'unknown',
                    'next_run' => $next_run,
                    'next_run_human' => $next_run ? human_time_diff($next_run) : 'Not scheduled'
                );
            }
        }

        return $status;
    }

    /**
     * Force execute all pending jobs
     *
     * @since    1.0.0
     * @return   array    Execution results
     */
    public static function force_execute_all() {
        $results = array();

        if (isset(self::$config['cron_jobs'])) {
            foreach (self::$config['cron_jobs'] as $job_key => $job_config) {
                if (isset($job_config['enabled']) && $job_config['enabled']) {
                    $result = self::execute_job($job_key, array(), true);
                    $results[$job_key] = $result;
                }
            }
        }

        return $results;
    }

    // Logging helper methods
    private static function log_info($message, $context = array()) {
        if (self::$logger) {
            self::$logger->info($message, $context);
        }
    }

    private static function log_error($message, $context = array()) {
        if (self::$logger) {
            self::$logger->error($message, $context);
        }
    }
}

// Initialize the cron manager
add_action('init', array('Amazon_Product_Importer_Cron_Manager', 'init'));