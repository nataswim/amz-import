<?php
/**
 * The admin settings functionality of the plugin.
 *
 * Defines the plugin settings, validation, and admin interface
 * for configuring the Amazon Product Importer plugin.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/admin
 */

/**
 * The admin settings functionality of the plugin.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/admin
 * @author     Your Name <your.email@example.com>
 */
class Amazon_Product_Importer_Admin_Settings {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * The settings sections.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $sections    The settings sections.
     */
    private $sections;

    /**
     * The settings fields.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $fields    The settings fields.
     */
    private $fields;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name    The name of this plugin.
     * @param    string    $version        The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        $this->init_settings();
        $this->register_settings();
    }

    /**
     * Initialize settings sections and fields.
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_settings() {
        
        // Define settings sections
        $this->sections = array(
            'api' => array(
                'id'    => 'amazon_product_importer_api_settings',
                'title' => esc_html__('Amazon API Settings', 'amazon-product-importer'),
                'desc'  => esc_html__('Configure your Amazon Product Advertising API credentials.', 'amazon-product-importer')
            ),
            'import' => array(
                'id'    => 'amazon_product_importer_import_settings', 
                'title' => esc_html__('Import Settings', 'amazon-product-importer'),
                'desc'  => esc_html__('Configure how products are imported from Amazon.', 'amazon-product-importer')
            ),
            'sync' => array(
                'id'    => 'amazon_product_importer_sync_settings',
                'title' => esc_html__('Sync Settings', 'amazon-product-importer'),
                'desc'  => esc_html__('Configure automatic synchronization with Amazon.', 'amazon-product-importer')
            ),
            'categories' => array(
                'id'    => 'amazon_product_importer_category_settings',
                'title' => esc_html__('Category Settings', 'amazon-product-importer'),
                'desc'  => esc_html__('Configure how Amazon categories are handled.', 'amazon-product-importer')
            ),
            'advanced' => array(
                'id'    => 'amazon_product_importer_advanced_settings',
                'title' => esc_html__('Advanced Settings', 'amazon-product-importer'),
                'desc'  => esc_html__('Advanced configuration options.', 'amazon-product-importer')
            )
        );

        // Define settings fields
        $this->fields = array(
            
            // API Settings
            'access_key_id' => array(
                'section'     => 'api',
                'id'          => 'amazon_product_importer_access_key_id',
                'title'       => esc_html__('Access Key ID', 'amazon-product-importer'),
                'type'        => 'password',
                'desc'        => esc_html__('Your Amazon Product Advertising API Access Key ID.', 'amazon-product-importer'),
                'required'    => true,
                'validation'  => 'alphanumeric'
            ),
            'secret_access_key' => array(
                'section'     => 'api',
                'id'          => 'amazon_product_importer_secret_access_key',
                'title'       => esc_html__('Secret Access Key', 'amazon-product-importer'),
                'type'        => 'password',
                'desc'        => esc_html__('Your Amazon Product Advertising API Secret Access Key.', 'amazon-product-importer'),
                'required'    => true,
                'validation'  => 'alphanumeric'
            ),
            'associate_tag' => array(
                'section'     => 'api',
                'id'          => 'amazon_product_importer_associate_tag',
                'title'       => esc_html__('Associate Tag', 'amazon-product-importer'),
                'type'        => 'text',
                'desc'        => esc_html__('Your Amazon Associate Tag (Tracking ID).', 'amazon-product-importer'),
                'required'    => true,
                'validation'  => 'associate_tag'
            ),
            'marketplace' => array(
                'section'     => 'api',
                'id'          => 'amazon_product_importer_marketplace',
                'title'       => esc_html__('Marketplace', 'amazon-product-importer'),
                'type'        => 'select',
                'desc'        => esc_html__('Select your Amazon marketplace.', 'amazon-product-importer'),
                'options'     => $this->get_marketplace_options(),
                'default'     => 'www.amazon.com'
            ),
            'region' => array(
                'section'     => 'api',
                'id'          => 'amazon_product_importer_region',
                'title'       => esc_html__('Region', 'amazon-product-importer'),
                'type'        => 'select',
                'desc'        => esc_html__('Select the AWS region for API requests.', 'amazon-product-importer'),
                'options'     => $this->get_region_options(),
                'default'     => 'us-east-1'
            ),

            // Import Settings
            'default_status' => array(
                'section'     => 'import',
                'id'          => 'amazon_product_importer_default_status',
                'title'       => esc_html__('Default Product Status', 'amazon-product-importer'),
                'type'        => 'select',
                'desc'        => esc_html__('Default status for imported products.', 'amazon-product-importer'),
                'options'     => array(
                    'draft'   => esc_html__('Draft', 'amazon-product-importer'),
                    'publish' => esc_html__('Published', 'amazon-product-importer'),
                    'private' => esc_html__('Private', 'amazon-product-importer')
                ),
                'default'     => 'draft'
            ),
            'default_visibility' => array(
                'section'     => 'import',
                'id'          => 'amazon_product_importer_default_visibility',
                'title'       => esc_html__('Default Product Visibility', 'amazon-product-importer'),
                'type'        => 'select',
                'desc'        => esc_html__('Default visibility for imported products.', 'amazon-product-importer'),
                'options'     => array(
                    'visible'   => esc_html__('Shop and search results', 'amazon-product-importer'),
                    'catalog'   => esc_html__('Shop only', 'amazon-product-importer'),
                    'search'    => esc_html__('Search results only', 'amazon-product-importer'),
                    'hidden'    => esc_html__('Hidden', 'amazon-product-importer')
                ),
                'default'     => 'visible'
            ),
            'thumbnail_size' => array(
                'section'     => 'import',
                'id'          => 'amazon_product_importer_thumbnail_size',
                'title'       => esc_html__('Image Size', 'amazon-product-importer'),
                'type'        => 'select',
                'desc'        => esc_html__('Default size for imported product images.', 'amazon-product-importer'),
                'options'     => array(
                    'SL75'   => esc_html__('Small (75px)', 'amazon-product-importer'),
                    'SL110'  => esc_html__('Medium (110px)', 'amazon-product-importer'),
                    'SL160'  => esc_html__('Large (160px)', 'amazon-product-importer'),
                    'SL200'  => esc_html__('Extra Large (200px)', 'amazon-product-importer'),
                    'SL500'  => esc_html__('XXL (500px)', 'amazon-product-importer')
                ),
                'default'     => 'SL200'
            ),
            'import_images' => array(
                'section'     => 'import',
                'id'          => 'amazon_product_importer_import_images',
                'title'       => esc_html__('Import Images', 'amazon-product-importer'),
                'type'        => 'checkbox',
                'desc'        => esc_html__('Import product images from Amazon.', 'amazon-product-importer'),
                'default'     => '1'
            ),
            'max_images' => array(
                'section'     => 'import',
                'id'          => 'amazon_product_importer_max_images',
                'title'       => esc_html__('Maximum Images', 'amazon-product-importer'),
                'type'        => 'number',
                'desc'        => esc_html__('Maximum number of images to import per product (0 = unlimited).', 'amazon-product-importer'),
                'min'         => 0,
                'max'         => 50,
                'default'     => 10
            ),

            // Sync Settings
            'auto_sync_enabled' => array(
                'section'     => 'sync',
                'id'          => 'amazon_product_importer_auto_sync_enabled',
                'title'       => esc_html__('Enable Auto Sync', 'amazon-product-importer'),
                'type'        => 'checkbox',
                'desc'        => esc_html__('Enable automatic synchronization with Amazon.', 'amazon-product-importer'),
                'default'     => '1'
            ),
            'sync_interval' => array(
                'section'     => 'sync',
                'id'          => 'amazon_product_importer_sync_interval',
                'title'       => esc_html__('Sync Interval', 'amazon-product-importer'),
                'type'        => 'select',
                'desc'        => esc_html__('How often to sync products with Amazon.', 'amazon-product-importer'),
                'options'     => array(
                    'every_15_minutes' => esc_html__('Every 15 Minutes', 'amazon-product-importer'),
                    'every_30_minutes' => esc_html__('Every 30 Minutes', 'amazon-product-importer'),
                    'hourly'           => esc_html__('Hourly', 'amazon-product-importer'),
                    'every_6_hours'    => esc_html__('Every 6 Hours', 'amazon-product-importer'),
                    'twicedaily'       => esc_html__('Twice Daily', 'amazon-product-importer'),
                    'daily'            => esc_html__('Daily', 'amazon-product-importer')
                ),
                'default'     => 'every_6_hours'
            ),
            'sync_price' => array(
                'section'     => 'sync',
                'id'          => 'amazon_product_importer_sync_price',
                'title'       => esc_html__('Sync Prices', 'amazon-product-importer'),
                'type'        => 'checkbox',
                'desc'        => esc_html__('Automatically update product prices from Amazon.', 'amazon-product-importer'),
                'default'     => '1'
            ),
            'sync_stock' => array(
                'section'     => 'sync',
                'id'          => 'amazon_product_importer_sync_stock',
                'title'       => esc_html__('Sync Stock Status', 'amazon-product-importer'),
                'type'        => 'checkbox',
                'desc'        => esc_html__('Automatically update stock status from Amazon.', 'amazon-product-importer'),
                'default'     => '1'
            ),
            'sync_title' => array(
                'section'     => 'sync',
                'id'          => 'amazon_product_importer_sync_title',
                'title'       => esc_html__('Sync Product Titles', 'amazon-product-importer'),
                'type'        => 'checkbox',
                'desc'        => esc_html__('Automatically update product titles from Amazon.', 'amazon-product-importer'),
                'default'     => '0'
            ),
            'sync_description' => array(
                'section'     => 'sync',
                'id'          => 'amazon_product_importer_sync_description',
                'title'       => esc_html__('Sync Descriptions', 'amazon-product-importer'),
                'type'        => 'checkbox',
                'desc'        => esc_html__('Automatically update product descriptions from Amazon.', 'amazon-product-importer'),
                'default'     => '0'
            ),
            'sync_images' => array(
                'section'     => 'sync',
                'id'          => 'amazon_product_importer_sync_images',
                'title'       => esc_html__('Sync Images', 'amazon-product-importer'),
                'type'        => 'checkbox',
                'desc'        => esc_html__('Automatically update product images from Amazon.', 'amazon-product-importer'),
                'default'     => '0'
            ),

            // Category Settings
            'auto_categories' => array(
                'section'     => 'categories',
                'id'          => 'amazon_product_importer_auto_categories',
                'title'       => esc_html__('Auto Import Categories', 'amazon-product-importer'),
                'type'        => 'checkbox',
                'desc'        => esc_html__('Automatically create categories based on Amazon browse nodes.', 'amazon-product-importer'),
                'default'     => '1'
            ),
            'category_min_depth' => array(
                'section'     => 'categories',
                'id'          => 'amazon_product_importer_category_min_depth',
                'title'       => esc_html__('Minimum Category Depth', 'amazon-product-importer'),
                'type'        => 'number',
                'desc'        => esc_html__('Minimum depth level for category import.', 'amazon-product-importer'),
                'min'         => 0,
                'max'         => 10,
                'default'     => 1
            ),
            'category_max_depth' => array(
                'section'     => 'categories',
                'id'          => 'amazon_product_importer_category_max_depth',
                'title'       => esc_html__('Maximum Category Depth', 'amazon-product-importer'),
                'type'        => 'number',
                'desc'        => esc_html__('Maximum depth level for category import.', 'amazon-product-importer'),
                'min'         => 1,
                'max'         => 10,
                'default'     => 5
            ),
            'default_category' => array(
                'section'     => 'categories',
                'id'          => 'amazon_product_importer_default_category',
                'title'       => esc_html__('Default Category', 'amazon-product-importer'),
                'type'        => 'select',
                'desc'        => esc_html__('Default category for products without Amazon categories.', 'amazon-product-importer'),
                'options'     => $this->get_product_categories(),
                'default'     => ''
            ),

            // Advanced Settings
            'api_rate_limit' => array(
                'section'     => 'advanced',
                'id'          => 'amazon_product_importer_api_rate_limit',
                'title'       => esc_html__('API Rate Limit (per hour)', 'amazon-product-importer'),
                'type'        => 'number',
                'desc'        => esc_html__('Maximum number of API calls per hour (Amazon limit is typically 8640).', 'amazon-product-importer'),
                'min'         => 1,
                'max'         => 10000,
                'default'     => 1000
            ),
            'cache_duration' => array(
                'section'     => 'advanced',
                'id'          => 'amazon_product_importer_cache_duration',
                'title'       => esc_html__('Cache Duration (minutes)', 'amazon-product-importer'),
                'type'        => 'number',
                'desc'        => esc_html__('How long to cache Amazon API responses.', 'amazon-product-importer'),
                'min'         => 5,
                'max'         => 1440,
                'default'     => 60
            ),
            'debug_mode' => array(
                'section'     => 'advanced',
                'id'          => 'amazon_product_importer_debug_mode',
                'title'       => esc_html__('Debug Mode', 'amazon-product-importer'),
                'type'        => 'checkbox',
                'desc'        => esc_html__('Enable debug logging for troubleshooting.', 'amazon-product-importer'),
                'default'     => '0'
            ),
            'log_level' => array(
                'section'     => 'advanced',
                'id'          => 'amazon_product_importer_log_level',
                'title'       => esc_html__('Log Level', 'amazon-product-importer'),
                'type'        => 'select',
                'desc'        => esc_html__('Minimum level of messages to log.', 'amazon-product-importer'),
                'options'     => array(
                    'error'   => esc_html__('Error Only', 'amazon-product-importer'),
                    'warning' => esc_html__('Warning & Error', 'amazon-product-importer'),
                    'info'    => esc_html__('Info, Warning & Error', 'amazon-product-importer'),
                    'debug'   => esc_html__('All Messages', 'amazon-product-importer')
                ),
                'default'     => 'warning'
            ),
            'keep_data_on_uninstall' => array(
                'section'     => 'advanced',
                'id'          => 'amazon_product_importer_keep_data_on_uninstall',
                'title'       => esc_html__('Keep Data on Uninstall', 'amazon-product-importer'),
                'type'        => 'checkbox',
                'desc'        => esc_html__('Keep all plugin data when uninstalling the plugin.', 'amazon-product-importer'),
                'default'     => '0'
            )
        );
    }

    /**
     * Register all settings with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function register_settings() {
        
        // Register each field
        foreach ($this->fields as $field) {
            register_setting(
                $field['section'],
                $field['id'],
                array(
                    'sanitize_callback' => array($this, 'sanitize_field'),
                    'default' => isset($field['default']) ? $field['default'] : ''
                )
            );
        }
    }

    /**
     * Display the settings page.
     *
     * @since    1.0.0
     */
    public function display_settings_page() {
        
        // Check user capabilities
        if (!current_user_can('manage_amazon_product_importer')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'amazon-product-importer'));
        }

        // Handle form submission
        if (isset($_POST['submit'])) {
            $this->handle_form_submission();
        }

        // Test API connection if requested
        if (isset($_GET['test_api']) && wp_verify_nonce($_GET['_wpnonce'], 'test_api')) {
            $this->test_api_connection();
        }

        include_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'admin/partials/admin-settings-display.php';
    }

    /**
     * Handle form submission.
     *
     * @since    1.0.0
     * @access   private
     */
    private function handle_form_submission() {
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['amazon_product_importer_settings_nonce'], 'amazon_product_importer_settings_save')) {
            add_settings_error('amazon_product_importer_settings', 'nonce_error', 
                esc_html__('Security check failed. Please try again.', 'amazon-product-importer'), 'error');
            return;
        }

        $errors = array();
        $updated = 0;

        // Process each field
        foreach ($this->fields as $field_key => $field) {
            
            if (!isset($_POST[$field['id']])) {
                // Handle checkboxes that aren't checked
                if ($field['type'] === 'checkbox') {
                    $_POST[$field['id']] = '0';
                } else {
                    continue;
                }
            }

            $value = $_POST[$field['id']];
            $sanitized_value = $this->sanitize_field($value, $field);

            // Validate required fields
            if (!empty($field['required']) && empty($sanitized_value)) {
                $errors[] = sprintf(
                    esc_html__('%s is required.', 'amazon-product-importer'),
                    $field['title']
                );
                continue;
            }

            // Additional validation
            if (!empty($sanitized_value)) {
                $validation_result = $this->validate_field($sanitized_value, $field);
                if ($validation_result !== true) {
                    $errors[] = sprintf(
                        esc_html__('%s: %s', 'amazon-product-importer'),
                        $field['title'],
                        $validation_result
                    );
                    continue;
                }
            }

            // Update the option
            if (update_option($field['id'], $sanitized_value)) {
                $updated++;
            }
        }

        // Display results
        if (!empty($errors)) {
            foreach ($errors as $error) {
                add_settings_error('amazon_product_importer_settings', 'validation_error', $error, 'error');
            }
        }

        if ($updated > 0) {
            add_settings_error('amazon_product_importer_settings', 'settings_updated', 
                esc_html__('Settings saved successfully.', 'amazon-product-importer'), 'success');
            
            // Update cron jobs if sync settings changed
            $this->update_cron_jobs();
        }
    }

    /**
     * Sanitize field values.
     *
     * @since    1.0.0
     * @param    mixed    $value    The field value.
     * @param    array    $field    The field configuration.
     * @return   mixed    Sanitized value.
     */
    public function sanitize_field($value, $field = null) {
        
        if (is_array($value)) {
            return array_map(array($this, 'sanitize_field'), $value);
        }

        // If no field info provided, try to get it
        if ($field === null) {
            // This is called by WordPress, try to determine field type
            return sanitize_text_field($value);
        }

        switch ($field['type']) {
            case 'text':
            case 'password':
                return sanitize_text_field($value);
                
            case 'textarea':
                return sanitize_textarea_field($value);
                
            case 'email':
                return sanitize_email($value);
                
            case 'url':
                return esc_url_raw($value);
                
            case 'number':
                $sanitized = intval($value);
                if (isset($field['min']) && $sanitized < $field['min']) {
                    $sanitized = $field['min'];
                }
                if (isset($field['max']) && $sanitized > $field['max']) {
                    $sanitized = $field['max'];
                }
                return $sanitized;
                
            case 'checkbox':
                return $value ? '1' : '0';
                
            case 'select':
                if (isset($field['options']) && array_key_exists($value, $field['options'])) {
                    return $value;
                }
                return isset($field['default']) ? $field['default'] : '';
                
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Validate field values.
     *
     * @since    1.0.0
     * @param    mixed    $value    The sanitized field value.
     * @param    array    $field    The field configuration.
     * @return   mixed    True if valid, error message if invalid.
     */
    private function validate_field($value, $field) {
        
        if (empty($field['validation'])) {
            return true;
        }

        switch ($field['validation']) {
            case 'alphanumeric':
                if (!preg_match('/^[a-zA-Z0-9]+$/', $value)) {
                    return esc_html__('Only alphanumeric characters are allowed.', 'amazon-product-importer');
                }
                break;
                
            case 'associate_tag':
                if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $value) || strlen($value) > 128) {
                    return esc_html__('Invalid associate tag format.', 'amazon-product-importer');
                }
                break;
        }

        return true;
    }

    /**
     * Test Amazon API connection.
     *
     * @since    1.0.0
     * @access   private
     */
    private function test_api_connection() {
        
        try {
            // Load Amazon API class
            require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/api/class-amazon-api.php';
            
            $api = new Amazon_Product_Importer_Amazon_API();
            $result = $api->test_connection();
            
            if ($result) {
                add_settings_error('amazon_product_importer_settings', 'api_test_success',
                    esc_html__('API connection successful!', 'amazon-product-importer'), 'success');
            } else {
                add_settings_error('amazon_product_importer_settings', 'api_test_failed',
                    esc_html__('API connection failed. Please check your credentials.', 'amazon-product-importer'), 'error');
            }
            
        } catch (Exception $e) {
            add_settings_error('amazon_product_importer_settings', 'api_test_error',
                sprintf(esc_html__('API test error: %s', 'amazon-product-importer'), $e->getMessage()), 'error');
        }
    }

    /**
     * Update cron jobs based on settings.
     *
     * @since    1.0.0
     * @access   private
     */
    private function update_cron_jobs() {
        
        $auto_sync = get_option('amazon_product_importer_auto_sync_enabled', '1');
        $interval = get_option('amazon_product_importer_sync_interval', 'every_6_hours');

        // Clear existing schedules
        wp_clear_scheduled_hook('amazon_product_importer_sync_prices');
        wp_clear_scheduled_hook('amazon_product_importer_sync_stock');
        
        // Schedule new jobs if auto sync is enabled
        if ($auto_sync === '1') {
            if (!wp_next_scheduled('amazon_product_importer_sync_prices')) {
                wp_schedule_event(time(), $interval, 'amazon_product_importer_sync_prices');
            }
            
            if (!wp_next_scheduled('amazon_product_importer_sync_stock')) {
                wp_schedule_event(time() + 300, $interval, 'amazon_product_importer_sync_stock'); // Offset by 5 minutes
            }
        }
    }

    /**
     * Get marketplace options.
     *
     * @since    1.0.0
     * @access   private
     * @return   array    Marketplace options.
     */
    private function get_marketplace_options() {
        return array(
            'www.amazon.com'    => esc_html__('United States (amazon.com)', 'amazon-product-importer'),
            'www.amazon.ca'     => esc_html__('Canada (amazon.ca)', 'amazon-product-importer'),
            'www.amazon.co.uk'  => esc_html__('United Kingdom (amazon.co.uk)', 'amazon-product-importer'),
            'www.amazon.de'     => esc_html__('Germany (amazon.de)', 'amazon-product-importer'),
            'www.amazon.fr'     => esc_html__('France (amazon.fr)', 'amazon-product-importer'),
            'www.amazon.it'     => esc_html__('Italy (amazon.it)', 'amazon-product-importer'),
            'www.amazon.es'     => esc_html__('Spain (amazon.es)', 'amazon-product-importer'),
            'www.amazon.co.jp'  => esc_html__('Japan (amazon.co.jp)', 'amazon-product-importer'),
            'www.amazon.com.au' => esc_html__('Australia (amazon.com.au)', 'amazon-product-importer'),
            'www.amazon.in'     => esc_html__('India (amazon.in)', 'amazon-product-importer'),
        );
    }

    /**
     * Get region options.
     *
     * @since    1.0.0
     * @access   private
     * @return   array    Region options.
     */
    private function get_region_options() {
        return array(
            'us-east-1'      => esc_html__('US East (N. Virginia)', 'amazon-product-importer'),
            'us-west-2'      => esc_html__('US West (Oregon)', 'amazon-product-importer'),
            'eu-west-1'      => esc_html__('Europe (Ireland)', 'amazon-product-importer'),
            'ap-northeast-1' => esc_html__('Asia Pacific (Tokyo)', 'amazon-product-importer'),
            'ap-southeast-2' => esc_html__('Asia Pacific (Sydney)', 'amazon-product-importer'),
        );
    }

    /**
     * Get WooCommerce product categories.
     *
     * @since    1.0.0
     * @access   private
     * @return   array    Product categories.
     */
    private function get_product_categories() {
        $categories = array('' => esc_html__('-- Select Category --', 'amazon-product-importer'));
        
        $terms = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ));

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $categories[$term->term_id] = $term->name;
            }
        }

        return $categories;
    }

    /**
     * Get settings sections.
     *
     * @since    1.0.0
     * @return   array    Settings sections.
     */
    public function get_sections() {
        return $this->sections;
    }

    /**
     * Get settings fields.
     *
     * @since    1.0.0
     * @return   array    Settings fields.
     */
    public function get_fields() {
        return $this->fields;
    }

    /**
     * Get fields for a specific section.
     *
     * @since    1.0.0
     * @param    string    $section    Section ID.
     * @return   array     Fields for the section.
     */
    public function get_section_fields($section) {
        return array_filter($this->fields, function($field) use ($section) {
            return $field['section'] === $section;
        });
    }

    /**
     * Get field value.
     *
     * @since    1.0.0
     * @param    string    $field_id    Field ID.
     * @param    mixed     $default     Default value.
     * @return   mixed     Field value.
     */
    public function get_field_value($field_id, $default = '') {
        return get_option($field_id, $default);
    }

    /**
     * Check if API credentials are configured.
     *
     * @since    1.0.0
     * @return   bool    True if configured, false otherwise.
     */
    public function are_api_credentials_configured() {
        $access_key = $this->get_field_value('amazon_product_importer_access_key_id');
        $secret_key = $this->get_field_value('amazon_product_importer_secret_access_key');
        $associate_tag = $this->get_field_value('amazon_product_importer_associate_tag');
        
        return !empty($access_key) && !empty($secret_key) && !empty($associate_tag);
    }
}