<?php

/**
 * The main product importing functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/import
 */

/**
 * The main product importing functionality of the plugin.
 *
 * Orchestrates the entire product import process from Amazon to WooCommerce.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/import
 * @author     Your Name <email@example.com>
 */
class Amazon_Product_Importer_Product_Importer {

    /**
     * The Amazon API instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Amazon_Api    $amazon_api    The Amazon API instance.
     */
    private $amazon_api;

    /**
     * The product mapper instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Product_Mapper    $product_mapper    The product mapper instance.
     */
    private $product_mapper;

    /**
     * The image handler instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Image_Handler    $image_handler    The image handler instance.
     */
    private $image_handler;

    /**
     * The category handler instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Category_Handler    $category_handler    The category handler instance.
     */
    private $category_handler;

    /**
     * The price updater instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Price_Updater    $price_updater    The price updater instance.
     */
    private $price_updater;

    /**
     * The product meta handler instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Product_Meta    $product_meta    The product meta handler instance.
     */
    private $product_meta;

    /**
     * The logger instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Logger    $logger    The logger instance.
     */
    private $logger;

    /**
     * The database instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Database    $database    The database instance.
     */
    private $database;

    /**
     * Import settings.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $settings    Import settings.
     */
    private $settings;

    /**
     * Current import session data.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $import_session    Current import session data.
     */
    private $import_session = array();

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->amazon_api = new Amazon_Product_Importer_Amazon_Api();
        $this->product_mapper = new Amazon_Product_Importer_Product_Mapper();
        $this->image_handler = new Amazon_Product_Importer_Image_Handler();
        $this->category_handler = new Amazon_Product_Importer_Category_Handler();
        $this->price_updater = new Amazon_Product_Importer_Price_Updater();
        $this->product_meta = new Amazon_Product_Importer_Product_Meta();
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->database = new Amazon_Product_Importer_Database();
        
        $this->load_settings();
    }

    /**
     * Load import settings.
     *
     * @since    1.0.0
     */
    private function load_settings() {
        $this->settings = array(
            'import_mode' => get_option('ams_import_mode', 'create_and_update'), // create_only, update_only, create_and_update
            'duplicate_handling' => get_option('ams_duplicate_handling', 'skip'), // skip, update, create_new
            'import_images' => get_option('ams_import_images', true),
            'import_categories' => get_option('ams_import_categories', true),
            'import_variations' => get_option('ams_import_variations', true),
            'auto_publish' => get_option('ams_auto_publish', true),
            'default_status' => get_option('ams_default_product_status', 'publish'),
            'rollback_on_error' => get_option('ams_rollback_on_error', true),
            'batch_size' => get_option('ams_import_batch_size', 5),
            'delay_between_imports' => get_option('ams_import_delay', 2), // seconds
            'max_retries' => get_option('ams_max_import_retries', 3),
            'skip_existing_images' => get_option('ams_skip_existing_images', true),
            'affiliate_tag' => get_option('ams_affiliate_tag', ''),
            'import_reviews' => get_option('ams_import_reviews', false),
            'create_backups' => get_option('ams_create_import_backups', false)
        );
    }

    /**
     * Import a single product by ASIN.
     *
     * @since    1.0.0
     * @param    string    $asin      Product ASIN.
     * @param    string    $region    Amazon region.
     * @param    array     $options   Import options.
     * @return   array     Import result.
     */
    public function import_product($asin, $region = 'US', $options = array()) {
        $start_time = microtime(true);
        
        // Initialize import session
        $this->init_import_session($asin, $region, 'single', $options);
        
        $result = array(
            'success' => false,
            'product_id' => null,
            'asin' => $asin,
            'action' => 'none', // created, updated, skipped
            'message' => '',
            'errors' => array(),
            'warnings' => array(),
            'execution_time' => 0
        );

        try {
            // Validate ASIN
            if (!$this->validate_asin($asin)) {
                throw new Exception('Invalid ASIN format');
            }

            // Check if product already exists
            $existing_product_id = $this->product_meta->get_product_by_asin($asin, $region);
            
            // Determine action based on import mode and existing product
            $action = $this->determine_import_action($existing_product_id);
            
            if ($action === 'skip') {
                $result['action'] = 'skipped';
                $result['message'] = 'Product already exists and import mode is create_only';
                $result['product_id'] = $existing_product_id;
                return $result;
            }

            // Fetch product data from Amazon API
            $api_data = $this->fetch_product_data($asin, $region);
            
            if (empty($api_data)) {
                throw new Exception('Failed to fetch product data from Amazon API');
            }

            // Process the import
            if ($action === 'update' && $existing_product_id) {
                $result = $this->update_existing_product($existing_product_id, $api_data, $result);
            } else {
                $result = $this->create_new_product($api_data, $result);
            }

            // Log successful import
            $this->database->insert_import_log(array(
                'asin' => $asin,
                'product_id' => $result['product_id'],
                'action' => $result['action'],
                'status' => 'success',
                'message' => $result['message'],
                'import_source' => 'manual'
            ));

            $result['success'] = true;

        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            $result['message'] = 'Import failed: ' . $e->getMessage();
            
            // Log failed import
            $this->database->insert_import_log(array(
                'asin' => $asin,
                'product_id' => $result['product_id'],
                'action' => 'failed',
                'status' => 'error',
                'message' => $e->getMessage(),
                'import_source' => 'manual'
            ));

            $this->logger->log("Import failed for ASIN {$asin}: " . $e->getMessage(), 'error');
        }

        $result['execution_time'] = round(microtime(true) - $start_time, 2);
        return $result;
    }

    /**
     * Import multiple products in batch.
     *
     * @since    1.0.0
     * @param    array    $asins     Array of ASINs.
     * @param    string   $region    Amazon region.
     * @param    array    $options   Import options.
     * @return   array    Batch import results.
     */
    public function import_products_batch($asins, $region = 'US', $options = array()) {
        $start_time = microtime(true);
        
        // Initialize batch import session
        $this->init_import_session($asins, $region, 'batch', $options);
        
        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'total' => count($asins),
            'products' => array(),
            'errors' => array(),
            'execution_time' => 0
        );

        // Process ASINs in batches
        $batches = array_chunk($asins, $this->settings['batch_size']);
        
        foreach ($batches as $batch_index => $batch) {
            try {
                // Fetch data for entire batch from API
                $batch_data = $this->fetch_products_batch_data($batch, $region);
                
                // Process each product in the batch
                foreach ($batch as $asin) {
                    $api_data = isset($batch_data[$asin]) ? $batch_data[$asin] : null;
                    
                    if ($api_data) {
                        $product_result = $this->process_single_product_import($asin, $api_data, $region);
                    } else {
                        $product_result = array(
                            'success' => false,
                            'asin' => $asin,
                            'action' => 'failed',
                            'message' => 'Product not found in API response',
                            'errors' => array('Product not found in Amazon API')
                        );
                    }

                    $results['products'][$asin] = $product_result;
                    
                    if ($product_result['success']) {
                        if ($product_result['action'] === 'skipped') {
                            $results['skipped']++;
                        } else {
                            $results['success']++;
                        }
                    } else {
                        $results['failed']++;
                        $results['errors'] = array_merge($results['errors'], $product_result['errors']);
                    }
                }

                // Add delay between batches
                if ($batch_index < count($batches) - 1 && $this->settings['delay_between_imports'] > 0) {
                    sleep($this->settings['delay_between_imports']);
                }

            } catch (Exception $e) {
                $this->logger->log("Batch import error: " . $e->getMessage(), 'error');
                $results['errors'][] = "Batch processing error: " . $e->getMessage();
                
                // Mark all products in this batch as failed
                foreach ($batch as $asin) {
                    if (!isset($results['products'][$asin])) {
                        $results['products'][$asin] = array(
                            'success' => false,
                            'asin' => $asin,
                            'action' => 'failed',
                            'message' => 'Batch processing failed',
                            'errors' => array($e->getMessage())
                        );
                        $results['failed']++;
                    }
                }
            }
        }

        $results['execution_time'] = round(microtime(true) - $start_time, 2);
        
        // Log batch import summary
        $this->database->insert_sync_history(array(
            'sync_type' => 'batch_import',
            'status' => 'completed',
            'products_processed' => $results['total'],
            'products_success' => $results['success'],
            'products_failed' => $results['failed'],
            'products_skipped' => $results['skipped'],
            'execution_time' => $results['execution_time']
        ));

        return $results;
    }

    /**
     * Search and import products from Amazon.
     *
     * @since    1.0.0
     * @param    string    $keywords    Search keywords.
     * @param    string    $region      Amazon region.
     * @param    array     $filters     Search filters.
     * @param    int       $limit       Maximum number of products to import.
     * @return   array     Search and import results.
     */
    public function search_and_import($keywords, $region = 'US', $filters = array(), $limit = 10) {
        try {
            // Search for products
            $search_results = $this->amazon_api->search_items($keywords, array_merge($filters, array(
                'region' => $region,
                'limit' => $limit
            )));

            if (empty($search_results) || !isset($search_results['SearchResult']['Items'])) {
                return array(
                    'success' => false,
                    'message' => 'No products found for the given search criteria',
                    'products' => array()
                );
            }

            // Extract ASINs from search results
            $asins = array();
            foreach ($search_results['SearchResult']['Items'] as $item) {
                if (isset($item['ASIN'])) {
                    $asins[] = $item['ASIN'];
                }
            }

            // Import the found products
            return $this->import_products_batch($asins, $region);

        } catch (Exception $e) {
            $this->logger->log("Search and import failed: " . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'message' => 'Search and import failed: ' . $e->getMessage(),
                'products' => array()
            );
        }
    }

    /**
     * Initialize import session.
     *
     * @since    1.0.0
     * @param    mixed     $asins      ASIN or array of ASINs.
     * @param    string    $region     Amazon region.
     * @param    string    $type       Import type (single, batch).
     * @param    array     $options    Import options.
     */
    private function init_import_session($asins, $region, $type, $options) {
        $this->import_session = array(
            'id' => uniqid('import_'),
            'type' => $type,
            'asins' => is_array($asins) ? $asins : array($asins),
            'region' => $region,
            'options' => array_merge($this->settings, $options),
            'started_at' => current_time('mysql'),
            'user_id' => get_current_user_id()
        );
    }

    /**
     * Determine import action based on existing product and settings.
     *
     * @since    1.0.0
     * @param    int|null    $existing_product_id    Existing product ID.
     * @return   string      Action to take (create, update, skip).
     */
    private function determine_import_action($existing_product_id) {
        if (!$existing_product_id) {
            return 'create';
        }

        switch ($this->settings['import_mode']) {
            case 'create_only':
                return 'skip';
            case 'update_only':
                return 'update';
            case 'create_and_update':
            default:
                return 'update';
        }
    }

    /**
     * Fetch product data from Amazon API.
     *
     * @since    1.0.0
     * @param    string    $asin      Product ASIN.
     * @param    string    $region    Amazon region.
     * @return   array     Product data.
     */
    private function fetch_product_data($asin, $region) {
        $resources = array(
            'ItemInfo.Title',
            'ItemInfo.Features',
            'ItemInfo.ProductInfo',
            'ItemInfo.ByLineInfo',
            'Images.Primary',
            'Images.Variants',
            'Offers.Listings.Price',
            'Offers.Listings.SavingBasis',
            'Offers.Listings.Availability',
            'BrowseNodeInfo.BrowseNodes'
        );

        if ($this->settings['import_variations']) {
            $resources[] = 'VariationSummary';
            $resources[] = 'Variations.Items';
        }

        $api_data = $this->amazon_api->get_items(array($asin), array(
            'resources' => $resources,
            'region' => $region
        ));

        if (isset($api_data['ItemsResult']['Items'][0])) {
            return $api_data['ItemsResult']['Items'][0];
        }

        return null;
    }

    /**
     * Fetch batch product data from Amazon API.
     *
     * @since    1.0.0
     * @param    array     $asins     Array of ASINs.
     * @param    string    $region    Amazon region.
     * @return   array     Batch product data indexed by ASIN.
     */
    private function fetch_products_batch_data($asins, $region) {
        $resources = array(
            'ItemInfo.Title',
            'ItemInfo.Features',
            'ItemInfo.ProductInfo',
            'ItemInfo.ByLineInfo',
            'Images.Primary',
            'Images.Variants',
            'Offers.Listings.Price',
            'Offers.Listings.SavingBasis',
            'Offers.Listings.Availability',
            'BrowseNodeInfo.BrowseNodes'
        );

        $api_data = $this->amazon_api->get_items($asins, array(
            'resources' => $resources,
            'region' => $region
        ));

        $indexed_data = array();
        if (isset($api_data['ItemsResult']['Items'])) {
            foreach ($api_data['ItemsResult']['Items'] as $item) {
                if (isset($item['ASIN'])) {
                    $indexed_data[$item['ASIN']] = $item;
                }
            }
        }

        return $indexed_data;
    }

    /**
     * Process single product import with retry logic.
     *
     * @since    1.0.0
     * @param    string    $asin        Product ASIN.
     * @param    array     $api_data    Amazon API data.
     * @param    string    $region      Amazon region.
     * @return   array     Import result.
     */
    private function process_single_product_import($asin, $api_data, $region) {
        $retries = 0;
        $last_error = '';

        while ($retries <= $this->settings['max_retries']) {
            try {
                // Check if product exists
                $existing_product_id = $this->product_meta->get_product_by_asin($asin, $region);
                $action = $this->determine_import_action($existing_product_id);

                if ($action === 'skip') {
                    return array(
                        'success' => true,
                        'product_id' => $existing_product_id,
                        'asin' => $asin,
                        'action' => 'skipped',
                        'message' => 'Product already exists'
                    );
                }

                $result = array(
                    'success' => false,
                    'product_id' => null,
                    'asin' => $asin,
                    'action' => 'none',
                    'message' => '',
                    'errors' => array()
                );

                if ($action === 'update' && $existing_product_id) {
                    $result = $this->update_existing_product($existing_product_id, $api_data, $result);
                } else {
                    $result = $this->create_new_product($api_data, $result);
                }

                return $result;

            } catch (Exception $e) {
                $last_error = $e->getMessage();
                $retries++;
                
                if ($retries <= $this->settings['max_retries']) {
                    $this->logger->log("Retry {$retries} for ASIN {$asin}: {$last_error}", 'warning');
                    sleep(1); // Wait before retry
                }
            }
        }

        return array(
            'success' => false,
            'asin' => $asin,
            'action' => 'failed',
            'message' => "Failed after {$retries} retries: {$last_error}",
            'errors' => array($last_error)
        );
    }

    /**
     * Create a new product from Amazon data.
     *
     * @since    1.0.0
     * @param    array    $api_data    Amazon API data.
     * @param    array    $result      Current result array.
     * @return   array    Updated result array.
     */
    private function create_new_product($api_data, $result) {
        $asin = $api_data['ASIN'];
        $product_data = $this->product_mapper->map_amazon_to_woocommerce($api_data);

        // Create the product
        $product = new WC_Product_Simple();
        
        // Set basic product data
        $product->set_name($product_data['name']);
        $product->set_description($product_data['description']);
        $product->set_short_description($product_data['short_description']);
        $product->set_sku($product_data['sku']);
        $product->set_status($this->settings['default_status']);

        // Set prices
        if (!empty($product_data['regular_price'])) {
            $product->set_regular_price($product_data['regular_price']);
        }
        if (!empty($product_data['sale_price'])) {
            $product->set_sale_price($product_data['sale_price']);
        }

        // Set stock status
        if (isset($product_data['stock_status'])) {
            $product->set_stock_status($product_data['stock_status']);
        }

        // Handle variations if this is a variable product
        if (isset($api_data['VariationSummary']) && $this->settings['import_variations']) {
            $product = $this->convert_to_variable_product($product, $api_data);
        }

        // Save the product
        $product_id = $product->save();

        if (!$product_id) {
            throw new Exception('Failed to create WooCommerce product');
        }

        // Store Amazon metadata
        $this->store_amazon_metadata($product_id, $api_data);

        // Import images
        if ($this->settings['import_images']) {
            $this->image_handler->process_product_images($product_id, $api_data);
        }

        // Import categories
        if ($this->settings['import_categories'] && isset($api_data['BrowseNodeInfo']['BrowseNodes'])) {
            $this->category_handler->process_product_categories($product_id, $api_data['BrowseNodeInfo']['BrowseNodes']);
        }

        // Set product attributes
        if (!empty($product_data['attributes'])) {
            $this->set_product_attributes($product_id, $product_data['attributes']);
        }

        // Process variations if applicable
        if ($product->is_type('variable') && isset($api_data['Variations']['Items'])) {
            $this->process_product_variations($product_id, $api_data['Variations']['Items']);
        }

        $result['success'] = true;
        $result['product_id'] = $product_id;
        $result['action'] = 'created';
        $result['message'] = 'Product created successfully';

        $this->logger->log("Created new product {$product_id} for ASIN {$asin}", 'info');

        return $result;
    }

    /**
     * Update an existing product with Amazon data.
     *
     * @since    1.0.0
     * @param    int      $product_id    Existing product ID.
     * @param    array    $api_data      Amazon API data.
     * @param    array    $result        Current result array.
     * @return   array    Updated result array.
     */
    private function update_existing_product($product_id, $api_data, $result) {
        $asin = $api_data['ASIN'];
        $product = wc_get_product($product_id);

        if (!$product) {
            throw new Exception("Product {$product_id} not found");
        }

        $updated = false;
        $product_data = $this->product_mapper->map_amazon_to_woocommerce($api_data);

        // Update basic product data based on settings
        $update_fields = get_option('ams_update_fields', array('description', 'price', 'stock_status'));

        if (in_array('name', $update_fields) && !empty($product_data['name'])) {
            if ($product->get_name() !== $product_data['name']) {
                $product->set_name($product_data['name']);
                $updated = true;
            }
        }

        if (in_array('description', $update_fields)) {
            if (!empty($product_data['description']) && $product->get_description() !== $product_data['description']) {
                $product->set_description($product_data['description']);
                $updated = true;
            }
            if (!empty($product_data['short_description']) && $product->get_short_description() !== $product_data['short_description']) {
                $product->set_short_description($product_data['short_description']);
                $updated = true;
            }
        }

        if (in_array('price', $update_fields)) {
            $price_updated = $this->price_updater->update_product_prices($product_id, $api_data, true);
            if ($price_updated) {
                $updated = true;
            }
        }

        if (in_array('stock_status', $update_fields) && isset($product_data['stock_status'])) {
            if ($product->get_stock_status() !== $product_data['stock_status']) {
                $product->set_stock_status($product_data['stock_status']);
                $updated = true;
            }
        }

        // Save product if updated
        if ($updated) {
            $product->save();
        }

        // Update Amazon metadata
        $this->update_amazon_metadata($product_id, $api_data);

        // Update images if enabled
        if ($this->settings['import_images'] && !$this->settings['skip_existing_images']) {
            $this->image_handler->update_product_images($product_id, $api_data);
        }

        // Update categories if enabled
        if ($this->settings['import_categories'] && isset($api_data['BrowseNodeInfo']['BrowseNodes'])) {
            $this->category_handler->update_product_categories($product_id, $api_data['BrowseNodeInfo']['BrowseNodes']);
        }

        $result['success'] = true;
        $result['product_id'] = $product_id;
        $result['action'] = 'updated';
        $result['message'] = $updated ? 'Product updated successfully' : 'Product checked, no updates needed';

        $this->logger->log("Updated product {$product_id} for ASIN {$asin}", 'info');

        return $result;
    }

    /**
     * Convert simple product to variable product.
     *
     * @since    1.0.0
     * @param    WC_Product    $product     Product object.
     * @param    array         $api_data    Amazon API data.
     * @return   WC_Product_Variable    Variable product object.
     */
    private function convert_to_variable_product($product, $api_data) {
        // Create new variable product
        $variable_product = new WC_Product_Variable();
        
        // Copy basic data from simple product
        $variable_product->set_name($product->get_name());
        $variable_product->set_description($product->get_description());
        $variable_product->set_short_description($product->get_short_description());
        $variable_product->set_sku($product->get_sku());
        $variable_product->set_status($product->get_status());

        return $variable_product;
    }

    /**
     * Store Amazon metadata for the product.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $api_data      Amazon API data.
     */
    private function store_amazon_metadata($product_id, $api_data) {
        $metadata = array(
            'asin' => $api_data['ASIN'],
            'region' => $this->import_session['region'],
            'affiliate_tag' => $this->settings['affiliate_tag'],
            'import_date' => current_time('mysql'),
            'import_source' => $this->import_session['type'],
            'detail_page_url' => isset($api_data['DetailPageURL']) ? $api_data['DetailPageURL'] : ''
        );

        // Add variation data if applicable
        if (isset($api_data['ParentASIN'])) {
            $metadata['parent_asin'] = $api_data['ParentASIN'];
        }

        // Add brand information
        if (isset($api_data['ItemInfo']['ByLineInfo']['Brand']['DisplayValue'])) {
            $metadata['brand'] = $api_data['ItemInfo']['ByLineInfo']['Brand']['DisplayValue'];
        }

        // Add manufacturer information
        if (isset($api_data['ItemInfo']['ByLineInfo']['Manufacturer']['DisplayValue'])) {
            $metadata['manufacturer'] = $api_data['ItemInfo']['ByLineInfo']['Manufacturer']['DisplayValue'];
        }

        // Store browse node information
        if (isset($api_data['BrowseNodeInfo']['BrowseNodes'][0])) {
            $browse_node = $api_data['BrowseNodeInfo']['BrowseNodes'][0];
            $metadata['browse_node_id'] = $browse_node['Id'];
            $metadata['browse_node_name'] = $browse_node['DisplayName'];
        }

        $this->product_meta->set_multiple_meta($product_id, $metadata);

        // Store in product mapping table
        $this->database->upsert_product_mapping(array(
            'product_id' => $product_id,
            'asin' => $api_data['ASIN'],
            'parent_asin' => isset($api_data['ParentASIN']) ? $api_data['ParentASIN'] : null,
            'region' => $this->import_session['region'],
            'affiliate_tag' => $this->settings['affiliate_tag'],
            'import_source' => $this->import_session['type']
        ));
    }

    /**
     * Update Amazon metadata for existing product.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $api_data      Amazon API data.
     */
    private function update_amazon_metadata($product_id, $api_data) {
        $metadata = array(
            'last_sync' => current_time('mysql'),
            'last_product_update' => current_time('mysql')
        );

        // Update detail page URL if available
        if (isset($api_data['DetailPageURL'])) {
            $metadata['detail_page_url'] = $api_data['DetailPageURL'];
        }

        $this->product_meta->set_multiple_meta($product_id, $metadata);

        // Update sync timestamp
        $this->product_meta->update_sync_timestamp($product_id, 'all', true);
    }

    /**
     * Set product attributes.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $attributes    Product attributes.
     */
    private function set_product_attributes($product_id, $attributes) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $product_attributes = array();

        foreach ($attributes as $name => $value) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name($name);
            $attribute->set_options(is_array($value) ? $value : array($value));
            $attribute->set_visible(true);
            $attribute->set_variation(false);
            
            $product_attributes[] = $attribute;
        }

        $product->set_attributes($product_attributes);
        $product->save();
    }

    /**
     * Process product variations.
     *
     * @since    1.0.0
     * @param    int      $parent_id     Parent product ID.
     * @param    array    $variations    Variation data from Amazon.
     */
    private function process_product_variations($parent_id, $variations) {
        foreach ($variations as $variation_data) {
            if (!isset($variation_data['ASIN'])) {
                continue;
            }

            $variation_asin = $variation_data['ASIN'];
            
            // Check if variation already exists
            $existing_variation_id = $this->product_meta->get_product_by_asin($variation_asin);
            
            if (!$existing_variation_id) {
                // Create new variation
                $this->create_product_variation($parent_id, $variation_data);
            } else {
                // Update existing variation
                $this->update_product_variation($existing_variation_id, $variation_data);
            }
        }
    }

    /**
     * Create a new product variation.
     *
     * @since    1.0.0
     * @param    int      $parent_id        Parent product ID.
     * @param    array    $variation_data   Variation data from Amazon.
     * @return   int|null Variation ID or null on failure.
     */
    private function create_product_variation($parent_id, $variation_data) {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($parent_id);

        // Map variation data
        $mapped_data = $this->product_mapper->map_variation_data($variation_data);

        // Set variation attributes
        if (!empty($mapped_data['attributes'])) {
            $variation->set_attributes($mapped_data['attributes']);
        }

        // Set prices
        if (!empty($mapped_data['regular_price'])) {
            $variation->set_regular_price($mapped_data['regular_price']);
        }
        if (!empty($mapped_data['sale_price'])) {
            $variation->set_sale_price($mapped_data['sale_price']);
        }

        // Set stock status
        if (isset($mapped_data['stock_status'])) {
            $variation->set_stock_status($mapped_data['stock_status']);
        }

        $variation_id = $variation->save();

        if ($variation_id) {
            // Store Amazon metadata for variation
            $this->store_amazon_metadata($variation_id, $variation_data);
            
            $this->logger->log("Created variation {$variation_id} for ASIN {$variation_data['ASIN']}", 'info');
        }

        return $variation_id;
    }

    /**
     * Update an existing product variation.
     *
     * @since    1.0.0
     * @param    int      $variation_id     Variation ID.
     * @param    array    $variation_data   Variation data from Amazon.
     * @return   bool     True if updated.
     */
    private function update_product_variation($variation_id, $variation_data) {
        $variation = wc_get_product($variation_id);
        if (!$variation || !$variation->is_type('variation')) {
            return false;
        }

        // Update prices
        $this->price_updater->update_product_prices($variation_id, $variation_data, true);

        // Update metadata
        $this->update_amazon_metadata($variation_id, $variation_data);

        return true;
    }

    /**
     * Validate ASIN format.
     *
     * @since    1.0.0
     * @param    string    $asin    ASIN to validate.
     * @return   bool      True if valid.
     */
    private function validate_asin($asin) {
        return preg_match('/^[A-Z0-9]{10}$/', $asin);
    }

    /**
     * Get import statistics.
     *
     * @since    1.0.0
     * @param    string    $period    Time period (day, week, month, all).
     * @return   array     Import statistics.
     */
    public function get_import_statistics($period = 'all') {
        $date_filter = '';
        
        switch ($period) {
            case 'day':
                $date_filter = date('Y-m-d H:i:s', strtotime('-1 day'));
                break;
            case 'week':
                $date_filter = date('Y-m-d H:i:s', strtotime('-1 week'));
                break;
            case 'month':
                $date_filter = date('Y-m-d H:i:s', strtotime('-1 month'));
                break;
        }

        $args = array();
        if ($date_filter) {
            $args['date_from'] = $date_filter;
        }

        $logs = $this->database->get_import_logs($args);
        
        $stats = array(
            'total' => count($logs),
            'success' => 0,
            'failed' => 0,
            'by_action' => array(),
            'by_source' => array()
        );

        foreach ($logs as $log) {
            if ($log->status === 'success') {
                $stats['success']++;
            } else {
                $stats['failed']++;
            }

            $stats['by_action'][$log->action] = isset($stats['by_action'][$log->action]) ? 
                                               $stats['by_action'][$log->action] + 1 : 1;
            
            $stats['by_source'][$log->import_source] = isset($stats['by_source'][$log->import_source]) ? 
                                                      $stats['by_source'][$log->import_source] + 1 : 1;
        }

        return $stats;
    }

    /**
     * Clean up failed imports.
     *
     * @since    1.0.0
     * @param    bool    $dry_run    Whether to perform a dry run.
     * @return   array   Cleanup results.
     */
    public function cleanup_failed_imports($dry_run = true) {
        $results = array('cleaned' => 0, 'products' => array());

        // Get products with import errors
        $failed_products = $this->database->get_import_logs(array(
            'status' => 'error',
            'limit' => 100
        ));

        foreach ($failed_products as $log) {
            if ($log->product_id) {
                $product = wc_get_product($log->product_id);
                
                // Check if product is incomplete (no title, price, etc.)
                if ($product && $this->is_incomplete_product($product)) {
                    $results['products'][] = array(
                        'id' => $log->product_id,
                        'asin' => $log->asin,
                        'title' => $product->get_name()
                    );

                    if (!$dry_run) {
                        wp_delete_post($log->product_id, true);
                        $results['cleaned']++;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Check if product is incomplete.
     *
     * @since    1.0.0
     * @param    WC_Product    $product    Product to check.
     * @return   bool          True if incomplete.
     */
    private function is_incomplete_product($product) {
        // Check for essential product data
        if (empty($product->get_name()) || strlen($product->get_name()) < 3) {
            return true;
        }

        if (empty($product->get_price()) && empty($product->get_regular_price())) {
            return true;
        }

        return false;
    }

    /**
     * Export import results.
     *
     * @since    1.0.0
     * @param    array    $results    Import results to export.
     * @param    string   $format     Export format (csv, json).
     * @return   string   Export data or file path.
     */
    public function export_import_results($results, $format = 'csv') {
        if ($format === 'csv') {
            return $this->export_to_csv($results);
        } elseif ($format === 'json') {
            return $this->export_to_json($results);
        }

        return '';
    }

    /**
     * Export results to CSV format.
     *
     * @since    1.0.0
     * @param    array    $results    Import results.
     * @return   string   CSV content.
     */
    private function export_to_csv($results) {
        $csv_content = "ASIN,Product ID,Action,Status,Message,Execution Time\n";
        
        foreach ($results['products'] as $asin => $result) {
            $csv_content .= sprintf(
                "%s,%s,%s,%s,\"%s\",%s\n",
                $asin,
                isset($result['product_id']) ? $result['product_id'] : '',
                $result['action'],
                $result['success'] ? 'Success' : 'Failed',
                str_replace('"', '""', $result['message']),
                isset($result['execution_time']) ? $result['execution_time'] : ''
            );
        }

        return $csv_content;
    }

    /**
     * Export results to JSON format.
     *
     * @since    1.0.0
     * @param    array    $results    Import results.
     * @return   string   JSON content.
     */
    private function export_to_json($results) {
        return json_encode($results, JSON_PRETTY_PRINT);
    }
}