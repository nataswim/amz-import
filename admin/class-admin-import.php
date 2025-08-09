<?php
/**
 * The admin import functionality of the plugin.
 *
 * Defines the plugin import interface, product search,
 * and import functionality for Amazon products.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/admin
 */

/**
 * The admin import functionality of the plugin.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/admin
 * @author     Your Name <your.email@example.com>
 */
class Amazon_Product_Importer_Admin_Import {

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
     * The Amazon API instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Amazon_API    $amazon_api
     */
    private $amazon_api;

    /**
     * The product importer instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Product_Importer    $product_importer
     */
    private $product_importer;

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
        
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load the required dependencies for the import functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        
        /**
         * Amazon API class
         */
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/api/class-amazon-api.php';
        
        /**
         * Product importer class
         */
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/import/class-product-importer.php';
        
        // Initialize API and importer
        $this->amazon_api = new Amazon_Product_Importer_Amazon_API();
        $this->product_importer = new Amazon_Product_Importer_Product_Importer();
    }

    /**
     * Initialize hooks for import functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_hooks() {
        
        // AJAX hooks for logged-in users
        add_action('wp_ajax_amazon_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_amazon_import_product', array($this, 'ajax_import_product'));
        add_action('wp_ajax_amazon_import_batch', array($this, 'ajax_import_batch'));
        add_action('wp_ajax_amazon_get_import_status', array($this, 'ajax_get_import_status'));
        add_action('wp_ajax_amazon_cancel_import', array($this, 'ajax_cancel_import'));
        add_action('wp_ajax_amazon_get_product_details', array($this, 'ajax_get_product_details'));
        add_action('wp_ajax_amazon_check_existing_product', array($this, 'ajax_check_existing_product'));
    }

    /**
     * Display the import page.
     *
     * @since    1.0.0
     */
    public function display_import_page() {
        
        // Check if API credentials are configured
        if (!$this->are_api_credentials_configured()) {
            $this->display_api_configuration_notice();
            return;
        }

        // Handle direct ASIN import if provided
        if (isset($_GET['import_asin']) && !empty($_GET['import_asin'])) {
            $this->handle_direct_asin_import($_GET['import_asin']);
        }

        include_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'admin/partials/admin-import-display.php';
    }

    /**
     * AJAX handler for searching Amazon products.
     *
     * @since    1.0.0
     */
    public function ajax_search_products() {
        
        // Security check
        check_ajax_referer('amazon_product_importer_nonce', 'nonce');
        
        if (!current_user_can('import_amazon_products')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'amazon-product-importer'));
        }

        $search_term = sanitize_text_field($_POST['search_term']);
        $search_type = sanitize_text_field($_POST['search_type']);
        $page = intval($_POST['page']) ?: 1;

        if (empty($search_term)) {
            wp_send_json_error(array(
                'message' => esc_html__('Search term is required.', 'amazon-product-importer')
            ));
        }

        try {
            
            $search_params = array(
                'Keywords' => $search_term,
                'ItemPage' => $page,
                'ItemCount' => 10
            );

            // Handle ASIN search differently
            if ($search_type === 'asin') {
                $search_params = array(
                    'ItemIds' => array($search_term)
                );
            }

            $results = $this->amazon_api->search_products($search_params);
            
            if (empty($results)) {
                wp_send_json_error(array(
                    'message' => esc_html__('No products found for your search.', 'amazon-product-importer')
                ));
            }

            // Process results for display
            $processed_results = array();
            foreach ($results as $product) {
                $processed_results[] = $this->process_search_result($product);
            }

            wp_send_json_success(array(
                'products' => $processed_results,
                'total_pages' => $this->amazon_api->get_total_pages(),
                'current_page' => $page
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(
                    esc_html__('Search failed: %s', 'amazon-product-importer'),
                    $e->getMessage()
                )
            ));
        }
    }

    /**
     * AJAX handler for importing a single product.
     *
     * @since    1.0.0
     */
    public function ajax_import_product() {
        
        // Security check
        check_ajax_referer('amazon_product_importer_nonce', 'nonce');
        
        if (!current_user_can('import_amazon_products')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'amazon-product-importer'));
        }

        $asin = sanitize_text_field($_POST['asin']);
        $force_update = isset($_POST['force_update']) && $_POST['force_update'] === 'true';

        if (empty($asin)) {
            wp_send_json_error(array(
                'message' => esc_html__('ASIN is required.', 'amazon-product-importer')
            ));
        }

        try {
            
            // Check if product already exists
            $existing_product_id = $this->get_existing_product_by_asin($asin);
            
            if ($existing_product_id && !$force_update) {
                wp_send_json_error(array(
                    'message' => esc_html__('Product already exists. Use force update to reimport.', 'amazon-product-importer'),
                    'existing_product_id' => $existing_product_id,
                    'existing_product_url' => get_edit_post_link($existing_product_id)
                ));
            }

            // Get detailed product information
            $product_data = $this->amazon_api->get_product_details($asin);
            
            if (empty($product_data)) {
                wp_send_json_error(array(
                    'message' => esc_html__('Could not retrieve product details from Amazon.', 'amazon-product-importer')
                ));
            }

            // Import the product
            $import_result = $this->product_importer->import_product($product_data, $existing_product_id);
            
            if (is_wp_error($import_result)) {
                wp_send_json_error(array(
                    'message' => $import_result->get_error_message()
                ));
            }

            // Log the import
            $this->log_import_action($asin, $import_result['product_id'], $existing_product_id ? 'update' : 'create');

            wp_send_json_success(array(
                'message' => $existing_product_id 
                    ? esc_html__('Product updated successfully!', 'amazon-product-importer')
                    : esc_html__('Product imported successfully!', 'amazon-product-importer'),
                'product_id' => $import_result['product_id'],
                'product_url' => get_edit_post_link($import_result['product_id']),
                'product_title' => get_the_title($import_result['product_id']),
                'import_details' => $import_result
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(
                    esc_html__('Import failed: %s', 'amazon-product-importer'),
                    $e->getMessage()
                )
            ));
        }
    }

    /**
     * AJAX handler for batch importing products.
     *
     * @since    1.0.0
     */
    public function ajax_import_batch() {
        
        // Security check
        check_ajax_referer('amazon_product_importer_nonce', 'nonce');
        
        if (!current_user_can('import_amazon_products')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'amazon-product-importer'));
        }

        $asins = array_map('sanitize_text_field', $_POST['asins']);
        $batch_id = sanitize_text_field($_POST['batch_id']);

        if (empty($asins)) {
            wp_send_json_error(array(
                'message' => esc_html__('No products selected for import.', 'amazon-product-importer')
            ));
        }

        // Initialize batch import
        $batch_data = array(
            'batch_id' => $batch_id,
            'total' => count($asins),
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'start_time' => current_time('timestamp'),
            'status' => 'running'
        );

        set_transient('amazon_import_batch_' . $batch_id, $batch_data, HOUR_IN_SECONDS);

        // Process in background using WP Cron
        wp_schedule_single_event(time(), 'amazon_product_importer_process_batch', array($batch_id, $asins));

        wp_send_json_success(array(
            'message' => esc_html__('Batch import started. You can monitor progress below.', 'amazon-product-importer'),
            'batch_id' => $batch_id
        ));
    }

    /**
     * AJAX handler for getting import status.
     *
     * @since    1.0.0
     */
    public function ajax_get_import_status() {
        
        // Security check
        check_ajax_referer('amazon_product_importer_nonce', 'nonce');
        
        if (!current_user_can('import_amazon_products')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'amazon-product-importer'));
        }

        $batch_id = sanitize_text_field($_POST['batch_id']);
        $batch_data = get_transient('amazon_import_batch_' . $batch_id);

        if (!$batch_data) {
            wp_send_json_error(array(
                'message' => esc_html__('Batch import not found or expired.', 'amazon-product-importer')
            ));
        }

        wp_send_json_success($batch_data);
    }

    /**
     * AJAX handler for canceling import.
     *
     * @since    1.0.0
     */
    public function ajax_cancel_import() {
        
        // Security check
        check_ajax_referer('amazon_product_importer_nonce', 'nonce');
        
        if (!current_user_can('import_amazon_products')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'amazon-product-importer'));
        }

        $batch_id = sanitize_text_field($_POST['batch_id']);
        $batch_data = get_transient('amazon_import_batch_' . $batch_id);

        if ($batch_data) {
            $batch_data['status'] = 'cancelled';
            set_transient('amazon_import_batch_' . $batch_id, $batch_data, HOUR_IN_SECONDS);
        }

        wp_send_json_success(array(
            'message' => esc_html__('Import cancelled successfully.', 'amazon-product-importer')
        ));
    }

    /**
     * AJAX handler for getting detailed product information.
     *
     * @since    1.0.0
     */
    public function ajax_get_product_details() {
        
        // Security check
        check_ajax_referer('amazon_product_importer_nonce', 'nonce');
        
        if (!current_user_can('import_amazon_products')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'amazon-product-importer'));
        }

        $asin = sanitize_text_field($_POST['asin']);

        if (empty($asin)) {
            wp_send_json_error(array(
                'message' => esc_html__('ASIN is required.', 'amazon-product-importer')
            ));
        }

        try {
            
            $product_data = $this->amazon_api->get_product_details($asin);
            
            if (empty($product_data)) {
                wp_send_json_error(array(
                    'message' => esc_html__('Could not retrieve product details.', 'amazon-product-importer')
                ));
            }

            $processed_data = $this->process_detailed_product_data($product_data);

            wp_send_json_success($processed_data);

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(
                    esc_html__('Failed to get product details: %s', 'amazon-product-importer'),
                    $e->getMessage()
                )
            ));
        }
    }

    /**
     * AJAX handler for checking if product already exists.
     *
     * @since    1.0.0
     */
    public function ajax_check_existing_product() {
        
        // Security check
        check_ajax_referer('amazon_product_importer_nonce', 'nonce');
        
        if (!current_user_can('import_amazon_products')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'amazon-product-importer'));
        }

        $asin = sanitize_text_field($_POST['asin']);
        $existing_product_id = $this->get_existing_product_by_asin($asin);

        if ($existing_product_id) {
            $product = get_post($existing_product_id);
            wp_send_json_success(array(
                'exists' => true,
                'product_id' => $existing_product_id,
                'product_title' => $product->post_title,
                'product_url' => get_edit_post_link($existing_product_id),
                'last_sync' => get_post_meta($existing_product_id, '_amazon_last_sync', true)
            ));
        } else {
            wp_send_json_success(array(
                'exists' => false
            ));
        }
    }

    /**
     * Process search result for display.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $product    Raw product data from Amazon API.
     * @return   array    Processed product data.
     */
    private function process_search_result($product) {
        
        $processed = array(
            'asin' => $product['ASIN'] ?? '',
            'title' => $product['ItemInfo']['Title']['DisplayValue'] ?? esc_html__('No title available', 'amazon-product-importer'),
            'image' => '',
            'price' => '',
            'url' => $product['DetailPageURL'] ?? '',
            'brand' => '',
            'rating' => '',
            'features' => array(),
            'exists_locally' => false
        );

        // Get primary image
        if (!empty($product['Images']['Primary']['Large']['URL'])) {
            $processed['image'] = $product['Images']['Primary']['Large']['URL'];
        } elseif (!empty($product['Images']['Primary']['Medium']['URL'])) {
            $processed['image'] = $product['Images']['Primary']['Medium']['URL'];
        }

        // Get price information
        if (!empty($product['Offers']['Listings'][0]['Price']['DisplayAmount'])) {
            $processed['price'] = $product['Offers']['Listings'][0]['Price']['DisplayAmount'];
        }

        // Get brand
        if (!empty($product['ItemInfo']['ByLineInfo']['Brand']['DisplayValue'])) {
            $processed['brand'] = $product['ItemInfo']['ByLineInfo']['Brand']['DisplayValue'];
        }

        // Get rating
        if (!empty($product['CustomerReviews']['StarRating']['Value'])) {
            $processed['rating'] = $product['CustomerReviews']['StarRating']['Value'];
        }

        // Get features
        if (!empty($product['ItemInfo']['Features']['DisplayValues'])) {
            $processed['features'] = array_slice($product['ItemInfo']['Features']['DisplayValues'], 0, 3);
        }

        // Check if product already exists locally
        $processed['exists_locally'] = (bool) $this->get_existing_product_by_asin($processed['asin']);

        return $processed;
    }

    /**
     * Process detailed product data for preview.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $product_data    Detailed product data from Amazon API.
     * @return   array    Processed product data.
     */
    private function process_detailed_product_data($product_data) {
        
        $processed = array(
            'asin' => $product_data['ASIN'] ?? '',
            'title' => $product_data['ItemInfo']['Title']['DisplayValue'] ?? '',
            'description' => '',
            'short_description' => '',
            'images' => array(),
            'price' => array(),
            'attributes' => array(),
            'variations' => array(),
            'categories' => array(),
            'dimensions' => array()
        );

        // Process description
        if (!empty($product_data['ItemInfo']['ProductInfo']['ProductDescription'])) {
            $processed['description'] = $product_data['ItemInfo']['ProductInfo']['ProductDescription'];
        }

        // Process short description (features)
        if (!empty($product_data['ItemInfo']['Features']['DisplayValues'])) {
            $processed['short_description'] = implode("\n", $product_data['ItemInfo']['Features']['DisplayValues']);
        }

        // Process images
        if (!empty($product_data['Images']['Primary'])) {
            $processed['images'][] = $product_data['Images']['Primary']['Large']['URL'] ?? $product_data['Images']['Primary']['Medium']['URL'];
        }
        
        if (!empty($product_data['Images']['Variants'])) {
            foreach ($product_data['Images']['Variants'] as $variant) {
                if (!empty($variant['Large']['URL'])) {
                    $processed['images'][] = $variant['Large']['URL'];
                }
            }
        }

        // Process price
        if (!empty($product_data['Offers']['Listings'][0])) {
            $listing = $product_data['Offers']['Listings'][0];
            $processed['price'] = array(
                'amount' => $listing['Price']['Amount'] ?? 0,
                'currency' => $listing['Price']['Currency'] ?? '',
                'display' => $listing['Price']['DisplayAmount'] ?? '',
                'savings' => $listing['SavingBasis']['Amount'] ?? 0
            );
        }

        // Process attributes
        if (!empty($product_data['ItemInfo']['ProductInfo']['ItemDimensions'])) {
            $processed['dimensions'] = $product_data['ItemInfo']['ProductInfo']['ItemDimensions'];
        }

        // Process categories (browse nodes)
        if (!empty($product_data['BrowseNodeInfo']['BrowseNodes'])) {
            foreach ($product_data['BrowseNodeInfo']['BrowseNodes'] as $node) {
                $processed['categories'][] = array(
                    'id' => $node['Id'],
                    'name' => $node['DisplayName']
                );
            }
        }

        return $processed;
    }

    /**
     * Get existing product by ASIN.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $asin    Amazon ASIN.
     * @return   int|false Product ID if exists, false otherwise.
     */
    private function get_existing_product_by_asin($asin) {
        global $wpdb;
        
        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_amazon_asin' AND meta_value = %s LIMIT 1",
            $asin
        ));
        
        return $product_id ? intval($product_id) : false;
    }

    /**
     * Log import action.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $asin           Amazon ASIN.
     * @param    int       $product_id     WooCommerce product ID.
     * @param    string    $action_type    Type of action (create/update).
     */
    private function log_import_action($asin, $product_id, $action_type) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . AMAZON_PRODUCT_IMPORTER_TABLE_IMPORTS;
        
        $wpdb->insert(
            $table_name,
            array(
                'asin' => $asin,
                'product_id' => $product_id,
                'action_type' => $action_type,
                'user_id' => get_current_user_id(),
                'import_date' => current_time('mysql'),
                'status' => 'success'
            ),
            array('%s', '%d', '%s', '%d', '%s', '%s')
        );
    }

    /**
     * Handle direct ASIN import from URL parameter.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $asin    Amazon ASIN to import.
     */
    private function handle_direct_asin_import($asin) {
        
        // Validate ASIN format
        if (!preg_match('/^[A-Z0-9]{10}$/', $asin)) {
            add_settings_error('amazon_product_importer', 'invalid_asin', 
                esc_html__('Invalid ASIN format.', 'amazon-product-importer'), 'error');
            return;
        }

        try {
            
            // Check if already exists
            $existing_product_id = $this->get_existing_product_by_asin($asin);
            if ($existing_product_id) {
                add_settings_error('amazon_product_importer', 'product_exists', 
                    sprintf(
                        esc_html__('Product already exists: %s', 'amazon-product-importer'),
                        sprintf('<a href="%s">%s</a>', get_edit_post_link($existing_product_id), get_the_title($existing_product_id))
                    ), 'info');
                return;
            }

            // Get product data and import
            $product_data = $this->amazon_api->get_product_details($asin);
            if ($product_data) {
                $import_result = $this->product_importer->import_product($product_data);
                
                if (!is_wp_error($import_result)) {
                    add_settings_error('amazon_product_importer', 'import_success', 
                        sprintf(
                            esc_html__('Product imported successfully: %s', 'amazon-product-importer'),
                            sprintf('<a href="%s">%s</a>', get_edit_post_link($import_result['product_id']), get_the_title($import_result['product_id']))
                        ), 'success');
                } else {
                    add_settings_error('amazon_product_importer', 'import_failed', 
                        $import_result->get_error_message(), 'error');
                }
            }

        } catch (Exception $e) {
            add_settings_error('amazon_product_importer', 'import_error', 
                sprintf(esc_html__('Import failed: %s', 'amazon-product-importer'), $e->getMessage()), 'error');
        }
    }

    /**
     * Display API configuration notice.
     *
     * @since    1.0.0
     * @access   private
     */
    private function display_api_configuration_notice() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Amazon Product Importer', 'amazon-product-importer') . '</h1>';
        echo '<div class="notice notice-warning">';
        echo '<p>';
        printf(
            esc_html__('Please configure your Amazon API credentials in the %s before importing products.', 'amazon-product-importer'),
            sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=amazon-product-importer-settings'),
                esc_html__('settings page', 'amazon-product-importer')
            )
        );
        echo '</p>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Check if API credentials are configured.
     *
     * @since    1.0.0
     * @access   private
     * @return   bool    True if configured, false otherwise.
     */
    private function are_api_credentials_configured() {
        $access_key = get_option('amazon_product_importer_access_key_id', '');
        $secret_key = get_option('amazon_product_importer_secret_access_key', '');
        $associate_tag = get_option('amazon_product_importer_associate_tag', '');
        
        return !empty($access_key) && !empty($secret_key) && !empty($associate_tag);
    }

    /**
     * Get import statistics.
     *
     * @since    1.0.0
     * @return   array    Import statistics.
     */
    public function get_import_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . AMAZON_PRODUCT_IMPORTER_TABLE_IMPORTS;
        
        $stats = array(
            'total_imports' => 0,
            'successful_imports' => 0,
            'failed_imports' => 0,
            'recent_imports' => 0
        );

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            return $stats;
        }

        $stats['total_imports'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $stats['successful_imports'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'success'");
        $stats['failed_imports'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'failed'");
        $stats['recent_imports'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE import_date >= %s",
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));

        return $stats;
    }

    /**
     * Get recent import history.
     *
     * @since    1.0.0
     * @param    int    $limit    Number of records to retrieve.
     * @return   array  Recent import records.
     */
    public function get_recent_imports($limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . AMAZON_PRODUCT_IMPORTER_TABLE_IMPORTS;
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            return array();
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} ORDER BY import_date DESC LIMIT %d",
            $limit
        ));
    }

    /**
     * Get the Amazon API instance.
     *
     * @since    1.0.0
     * @return   Amazon_Product_Importer_Amazon_API
     */
    public function get_amazon_api() {
        return $this->amazon_api;
    }

    /**
     * Get the product importer instance.
     *
     * @since    1.0.0
     * @return   Amazon_Product_Importer_Product_Importer
     */
    public function get_product_importer() {
        return $this->product_importer;
    }
}