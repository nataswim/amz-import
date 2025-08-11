<?php
/**
 * Product Variations Handler - Optimized Version
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/import
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Product Variations Handler Class
 *
 * Handles the import and management of product variations from Amazon
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/import
 * @author     Your Name <email@example.com>
 */
class Amazon_Product_Importer_Variation_Handler {

    /**
     * Amazon API instance
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Amazon_API    $api    Amazon API instance
     */
    private $api;

    /**
     * Product mapper instance
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Product_Mapper    $mapper    Product mapper instance
     */
    private $mapper;

    /**
     * Logger instance
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Logger    $logger    Logger instance
     */
    private $logger;

    /**
     * Image handler instance
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Image_Handler    $image_handler    Image handler instance
     */
    private $image_handler;

    /**
     * Maximum variations to process per batch
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $batch_size    Batch processing size
     */
    private $batch_size = 5;

    /**
     * Delay between batch processing (microseconds)
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $batch_delay    Delay between batches
     */
    private $batch_delay = 500000; // 0.5 seconds

    /**
     * Maximum retry attempts for failed variations
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $max_retries    Maximum retry attempts
     */
    private $max_retries = 3;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->api = new Amazon_Product_Importer_Amazon_API();
        $this->mapper = new Amazon_Product_Importer_Product_Mapper();
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->image_handler = new Amazon_Product_Importer_Image_Handler();
        
        // Load settings
        $this->batch_size = get_option('amazon_product_importer_variation_batch_size', 5);
        $this->batch_delay = get_option('amazon_product_importer_variation_batch_delay', 500000);
    }

    /**
     * Import product variations with optimized batch processing
     *
     * @since    1.0.0
     * @access   public
     * @param    int       $product_id     WooCommerce product ID
     * @param    string    $parent_asin    Amazon parent ASIN
     * @param    array     $amazon_product Amazon product data
     * @return   array                     Import results
     */
    public function import_variations($product_id, $parent_asin, $amazon_product) {
        try {
            $this->logger->log('info', 'Starting variation import', array(
                'product_id' => $product_id,
                'parent_asin' => $parent_asin
            ));

            // Validate variation data
            if (!$this->validate_variation_data($amazon_product)) {
                $this->logger->log('warning', 'Invalid variation data', array(
                    'product_id' => $product_id,
                    'parent_asin' => $parent_asin
                ));
                return array(
                    'success' => false,
                    'error' => __('Données de variations invalides', 'amazon-product-importer')
                );
            }

            // Get variation ASINs from API
            $variation_asins = $this->get_variation_asins($parent_asin);

            if (empty($variation_asins)) {
                $this->logger->log('warning', 'No variation ASINs found', array(
                    'product_id' => $product_id,
                    'parent_asin' => $parent_asin
                ));
                return array(
                    'success' => false,
                    'error' => __('Aucune variation trouvée', 'amazon-product-importer')
                );
            }

            // Convert product to variable type
            $conversion_result = $this->convert_to_variable_product($product_id);
            if (!$conversion_result['success']) {
                return $conversion_result;
            }

            // Process variations in batches
            $batch_results = $this->process_variations_in_batches($product_id, $variation_asins);
            
            // Update product attributes and settings
            $this->finalize_variable_product($product_id, $batch_results['successful_variations']);

            $success_count = count($batch_results['successful_variations']);
            $total_count = count($variation_asins);

            $this->logger->log('info', 'Variation import completed', array(
                'product_id' => $product_id,
                'total_variations' => $total_count,
                'successful_imports' => $success_count,
                'failed_imports' => $total_count - $success_count
            ));

            return array(
                'success' => $success_count > 0,
                'variations_imported' => $success_count,
                'variations_failed' => $total_count - $success_count,
                'variation_ids' => $batch_results['successful_variations'],
                'errors' => $batch_results['errors']
            );

        } catch (Exception $e) {
            $this->logger->log('error', 'Variation import failed', array(
                'product_id' => $product_id,
                'parent_asin' => $parent_asin,
                'error' => $e->getMessage()
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Validate variation data from Amazon product
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $amazon_product    Amazon product data
     * @return   bool                        True if valid, false otherwise
     */
    private function validate_variation_data($amazon_product) {
        // Check if variation summary exists
        if (!isset($amazon_product['VariationSummary'])) {
            return false;
        }

        $variation_summary = $amazon_product['VariationSummary'];

        // Check if variation dimensions exist
        if (!isset($variation_summary['VariationDimension']) || 
            empty($variation_summary['VariationDimension'])) {
            return false;
        }

        // Validate variation count
        if (isset($variation_summary['VariationCount']) && 
            $variation_summary['VariationCount'] <= 1) {
            return false;
        }

        return true;
    }

    /**
     * Get variation ASINs for a parent product
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $parent_asin    Parent product ASIN
     * @return   array                     Array of variation ASINs
     */
    private function get_variation_asins($parent_asin) {
        $variation_asins = array();

        // Get variations from API
        $variations_response = $this->api->get_variations($parent_asin, array(
            'variation_count' => 10, // Get up to 10 variations per page
            'variation_page' => 1
        ));

        if (!$variations_response['success']) {
            $this->logger->log('warning', 'Failed to get variations from API', array(
                'parent_asin' => $parent_asin,
                'error' => $variations_response['error']
            ));
            return array();
        }

        $variations = $variations_response['variations'];

        foreach ($variations as $variation) {
            if (isset($variation['ASIN']) && !empty($variation['ASIN'])) {
                $variation_asins[] = $variation['ASIN'];
            }
        }

        // If we have more variations, get additional pages
        if (count($variations) === 10) {
            $page = 2;
            while ($page <= 5) { // Limit to 5 pages (50 variations max)
                $additional_response = $this->api->get_variations($parent_asin, array(
                    'variation_count' => 10,
                    'variation_page' => $page
                ));

                if (!$additional_response['success'] || empty($additional_response['variations'])) {
                    break;
                }

                foreach ($additional_response['variations'] as $variation) {
                    if (isset($variation['ASIN']) && !empty($variation['ASIN'])) {
                        $variation_asins[] = $variation['ASIN'];
                    }
                }

                if (count($additional_response['variations']) < 10) {
                    break; // Last page
                }

                $page++;
                usleep(200000); // 0.2 second delay between pages
            }
        }

        return array_unique($variation_asins);
    }

    /**
     * Convert simple product to variable product
     *
     * @since    1.0.0
     * @access   private
     * @param    int    $product_id    WooCommerce product ID
     * @return   array                 Conversion result
     */
    private function convert_to_variable_product($product_id) {
        try {
            // Set product type to variable
            wp_set_object_terms($product_id, 'variable', 'product_type');

            // Remove simple product pricing meta
            $simple_price_meta = array(
                '_regular_price',
                '_sale_price',
                '_price',
                '_stock',
                '_manage_stock',
                '_stock_status'
            );

            foreach ($simple_price_meta as $meta_key) {
                delete_post_meta($product_id, $meta_key);
            }

            // Set variable product meta
            update_post_meta($product_id, '_manage_stock', 'no');
            update_post_meta($product_id, '_stock_status', 'instock');

            $this->logger->log('info', 'Product converted to variable', array(
                'product_id' => $product_id
            ));

            return array('success' => true);

        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to convert product to variable', array(
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ));

            return array(
                'success' => false,
                'error' => __('Échec de la conversion en produit variable', 'amazon-product-importer')
            );
        }
    }

    /**
     * Process variations in batches with retry logic
     *
     * @since    1.0.0
     * @access   private
     * @param    int      $product_id       WooCommerce product ID
     * @param    array    $variation_asins  Array of variation ASINs
     * @return   array                      Processing results
     */
    private function process_variations_in_batches($product_id, $variation_asins) {
        $successful_variations = array();
        $failed_variations = array();
        $errors = array();

        // Split variations into batches
        $variation_batches = array_chunk($variation_asins, $this->batch_size);

        foreach ($variation_batches as $batch_index => $batch_asins) {
            $this->logger->log('info', 'Processing variation batch', array(
                'batch_index' => $batch_index + 1,
                'batch_size' => count($batch_asins),
                'total_batches' => count($variation_batches)
            ));

            // Process each variation in the batch
            foreach ($batch_asins as $asin) {
                $result = $this->process_single_variation($product_id, $asin);
                
                if ($result['success']) {
                    $successful_variations[] = $result['variation_id'];
                } else {
                    $failed_variations[] = $asin;
                    $errors[$asin] = $result['error'];
                }
            }

            // Add delay between batches (except for the last batch)
            if ($batch_index < count($variation_batches) - 1) {
                usleep($this->batch_delay);
            }
        }

        // Retry failed variations
        if (!empty($failed_variations)) {
            $retry_results = $this->retry_failed_variations($product_id, $failed_variations);
            $successful_variations = array_merge($successful_variations, $retry_results['successful']);
            
            // Update errors array with final failures
            foreach ($retry_results['failed'] as $asin => $error) {
                $errors[$asin] = $error;
            }
        }

        return array(
            'successful_variations' => $successful_variations,
            'failed_variations' => array_keys($errors),
            'errors' => $errors
        );
    }

    /**
     * Process a single variation
     *
     * @since    1.0.0
     * @access   private
     * @param    int       $product_id    WooCommerce product ID
     * @param    string    $asin          Variation ASIN
     * @return   array                    Processing result
     */
    private function process_single_variation($product_id, $asin) {
        try {
            // Get variation data from API
            $variation_response = $this->api->get_product($asin);

            if (!$variation_response['success']) {
                return array(
                    'success' => false,
                    'error' => $variation_response['error']
                );
            }

            $variation_data = $variation_response['product'];

            if (!$variation_data) {
                return array(
                    'success' => false,
                    'error' => __('Données de variation non trouvées', 'amazon-product-importer')
                );
            }

            // Create WooCommerce variation
            $variation_id = $this->create_woocommerce_variation($product_id, $variation_data);

            if (!$variation_id) {
                return array(
                    'success' => false,
                    'error' => __('Échec de la création de la variation', 'amazon-product-importer')
                );
            }

            // Import variation images
            $this->import_variation_images($variation_id, $variation_data);

            $this->logger->log('info', 'Variation created successfully', array(
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'asin' => $asin
            ));

            return array(
                'success' => true,
                'variation_id' => $variation_id
            );

        } catch (Exception $e) {
            $this->logger->log('error', 'Variation processing failed', array(
                'product_id' => $product_id,
                'asin' => $asin,
                'error' => $e->getMessage()
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Create WooCommerce product variation
     *
     * @since    1.0.0
     * @access   private
     * @param    int      $product_id      Parent product ID
     * @param    array    $variation_data  Amazon variation data
     * @return   int|false                 Variation ID or false on failure
     */
    private function create_woocommerce_variation($product_id, $variation_data) {
        try {
            // Map variation data to WooCommerce format
            $mapped_data = $this->mapper->map_variation_data($variation_data, $product_id);

            // Create variation post
            $variation_post = array(
                'post_title' => get_the_title($product_id),
                'post_name' => 'product-' . $product_id . '-variation-' . $variation_data['ASIN'],
                'post_status' => 'publish',
                'post_parent' => $product_id,
                'post_type' => 'product_variation',
                'menu_order' => 0
            );

            $variation_id = wp_insert_post($variation_post);

            if (is_wp_error($variation_id)) {
                throw new Exception('Failed to create variation post: ' . $variation_id->get_error_message());
            }

            // Set variation meta data
            $this->set_variation_meta_data($variation_id, $mapped_data);

            // Set variation attributes
            $this->set_variation_attributes($variation_id, $mapped_data['attributes']);

            return $variation_id;

        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to create WooCommerce variation', array(
                'product_id' => $product_id,
                'asin' => $variation_data['ASIN'],
                'error' => $e->getMessage()
            ));

            return false;
        }
    }

    /**
     * Set variation meta data
     *
     * @since    1.0.0
     * @access   private
     * @param    int      $variation_id    Variation ID
     * @param    array    $mapped_data     Mapped variation data
     */
    private function set_variation_meta_data($variation_id, $mapped_data) {
        $meta_data = array(
            '_sku' => $mapped_data['sku'],
            '_regular_price' => $mapped_data['regular_price'],
            '_sale_price' => $mapped_data['sale_price'],
            '_price' => $mapped_data['price'],
            '_manage_stock' => $mapped_data['manage_stock'],
            '_stock_quantity' => $mapped_data['stock_quantity'],
            '_stock_status' => $mapped_data['stock_status'],
            '_weight' => $mapped_data['weight'],
            '_length' => $mapped_data['length'],
            '_width' => $mapped_data['width'],
            '_height' => $mapped_data['height'],
            '_virtual' => $mapped_data['virtual'],
            '_downloadable' => $mapped_data['downloadable'],
            '_amazon_asin' => $mapped_data['asin'],
            '_amazon_last_sync' => current_time('mysql'),
            '_amazon_variation_attributes' => $mapped_data['variation_attributes']
        );

        foreach ($meta_data as $key => $value) {
            if ($value !== null && $value !== '') {
                update_post_meta($variation_id, $key, $value);
            }
        }
    }

    /**
     * Set variation attributes
     *
     * @since    1.0.0
     * @access   private
     * @param    int      $variation_id    Variation ID
     * @param    array    $attributes      Variation attributes
     */
    private function set_variation_attributes($variation_id, $attributes) {
        $variation_attributes = array();

        foreach ($attributes as $attribute_name => $attribute_value) {
            $attribute_slug = 'attribute_pa_' . sanitize_title($attribute_name);
            $variation_attributes[$attribute_slug] = $attribute_value;
        }

        foreach ($variation_attributes as $key => $value) {
            update_post_meta($variation_id, $key, $value);
        }
    }

    /**
     * Import variation images
     *
     * @since    1.0.0
     * @access   private
     * @param    int      $variation_id    Variation ID
     * @param    array    $variation_data  Variation data
     */
    private function import_variation_images($variation_id, $variation_data) {
        if (!isset($variation_data['Images']) || empty($variation_data['Images'])) {
            return;
        }

        try {
            $this->image_handler->import_variation_images($variation_id, $variation_data['Images']);
        } catch (Exception $e) {
            $this->logger->log('warning', 'Failed to import variation images', array(
                'variation_id' => $variation_id,
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Retry failed variations with exponential backoff
     *
     * @since    1.0.0
     * @access   private
     * @param    int      $product_id         Product ID
     * @param    array    $failed_variations  Failed variation ASINs
     * @return   array                        Retry results
     */
    private function retry_failed_variations($product_id, $failed_variations) {
        $successful_retries = array();
        $final_failures = array();

        foreach ($failed_variations as $asin) {
            $retry_count = 0;
            $success = false;

            while ($retry_count < $this->max_retries && !$success) {
                $retry_count++;
                
                // Exponential backoff
                $delay = pow(2, $retry_count) * 1000000; // microseconds
                usleep($delay);

                $result = $this->process_single_variation($product_id, $asin);

                if ($result['success']) {
                    $successful_retries[] = $result['variation_id'];
                    $success = true;
                    
                    $this->logger->log('info', 'Variation retry successful', array(
                        'asin' => $asin,
                        'retry_attempt' => $retry_count
                    ));
                } else {
                    $final_failures[$asin] = $result['error'];
                }
            }

            if (!$success) {
                $this->logger->log('error', 'Variation retry failed after max attempts', array(
                    'asin' => $asin,
                    'max_retries' => $this->max_retries
                ));
            }
        }

        return array(
            'successful' => $successful_retries,
            'failed' => $final_failures
        );
    }

    /**
     * Finalize variable product setup
     *
     * @since    1.0.0
     * @access   private
     * @param    int      $product_id           Product ID
     * @param    array    $successful_variations Successful variation IDs
     */
    private function finalize_variable_product($product_id, $successful_variations) {
        if (empty($successful_variations)) {
            return;
        }

        try {
            // Update product attributes
            $this->update_product_attributes($product_id, $successful_variations);

            // Set default attributes
            $this->set_default_attributes($product_id);

            // Update product price range
            $this->update_product_price_range($product_id);

            // Clear product cache
            wc_delete_product_transients($product_id);

            $this->logger->log('info', 'Variable product finalized', array(
                'product_id' => $product_id,
                'variation_count' => count($successful_variations)
            ));

        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to finalize variable product', array(
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Update product attributes based on variations
     *
     * @since    1.0.0
     * @access   private
     * @param    int      $product_id    Product ID
     * @param    array    $variation_ids Variation IDs
     */
    private function update_product_attributes($product_id, $variation_ids) {
        $product_attributes = array();
        $attribute_values = array();

        // Collect attributes from all variations
        foreach ($variation_ids as $variation_id) {
            $variation_attributes = get_post_meta($variation_id, '_amazon_variation_attributes', true);
            
            if (is_array($variation_attributes)) {
                foreach ($variation_attributes as $name => $value) {
                    if (!isset($attribute_values[$name])) {
                        $attribute_values[$name] = array();
                    }
                    
                    if (!in_array($value, $attribute_values[$name])) {
                        $attribute_values[$name][] = $value;
                    }
                }
            }
        }

        // Create product attributes
        $position = 0;
        foreach ($attribute_values as $attribute_name => $values) {
            $attribute_slug = sanitize_title($attribute_name);
            $taxonomy_name = 'pa_' . $attribute_slug;

            // Create or get attribute taxonomy
            $attribute_id = $this->get_or_create_attribute($attribute_name, $attribute_slug);

            if ($attribute_id) {
                // Create terms
                $term_ids = array();
                foreach ($values as $value) {
                    $term = wp_insert_term($value, $taxonomy_name);
                    if (!is_wp_error($term)) {
                        $term_ids[] = $term['term_id'];
                    } else {
                        // Term might already exist
                        $existing_term = get_term_by('name', $value, $taxonomy_name);
                        if ($existing_term) {
                            $term_ids[] = $existing_term->term_id;
                        }
                    }
                }

                // Set product attribute
                $product_attributes[$taxonomy_name] = array(
                    'name' => $taxonomy_name,
                    'value' => '',
                    'position' => $position,
                    'is_visible' => 1,
                    'is_variation' => 1,
                    'is_taxonomy' => 1
                );

                // Set attribute terms for product
                wp_set_object_terms($product_id, $term_ids, $taxonomy_name);

                $position++;
            }
        }

        // Update product attributes
        update_post_meta($product_id, '_product_attributes', $product_attributes);
    }

    /**
     * Get or create product attribute
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $attribute_name    Attribute name
     * @param    string    $attribute_slug    Attribute slug
     * @return   int|false                    Attribute ID or false on failure
     */
    private function get_or_create_attribute($attribute_name, $attribute_slug) {
        global $wpdb;

        // Check if attribute already exists
        $attribute_id = $wpdb->get_var($wpdb->prepare(
            "SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
            $attribute_slug
        ));

        if ($attribute_id) {
            return $attribute_id;
        }

        // Create new attribute
        $attribute_data = array(
            'attribute_label' => $attribute_name,
            'attribute_name' => $attribute_slug,
            'attribute_type' => 'select',
            'attribute_orderby' => 'menu_order',
            'attribute_public' => 0
        );

        $result = $wpdb->insert(
            $wpdb->prefix . 'woocommerce_attribute_taxonomies',
            $attribute_data
        );

        if ($result === false) {
            return false;
        }

        $attribute_id = $wpdb->insert_id;

        // Register taxonomy
        $taxonomy_name = 'pa_' . $attribute_slug;
        register_taxonomy($taxonomy_name, array('product'), array(
            'hierarchical' => false,
            'show_ui' => false,
            'query_var' => true,
            'rewrite' => false,
        ));

        // Clear cache
        delete_transient('wc_attribute_taxonomies');

        return $attribute_id;
    }

    /**
     * Set default attributes for variable product
     *
     * @since    1.0.0
     * @access   private
     * @param    int    $product_id    Product ID
     */
    private function set_default_attributes($product_id) {
        $product_attributes = get_post_meta($product_id, '_product_attributes', true);
        $default_attributes = array();

        if (is_array($product_attributes)) {
            foreach ($product_attributes as $taxonomy => $attribute) {
                if ($attribute['is_variation']) {
                    // Get first term as default
                    $terms = wp_get_post_terms($product_id, $taxonomy);
                    if (!empty($terms) && !is_wp_error($terms)) {
                        $default_attributes[$taxonomy] = $terms[0]->slug;
                    }
                }
            }
        }

        update_post_meta($product_id, '_default_attributes', $default_attributes);
    }

    /**
     * Update product price range based on variations
     *
     * @since    1.0.0
     * @access   private
     * @param    int    $product_id    Product ID
     */
    private function update_product_price_range($product_id) {
        global $wpdb;

        // Get price range from variations
        $prices = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} 
             WHERE post_id IN (
                 SELECT ID FROM {$wpdb->posts} 
                 WHERE post_parent = %d AND post_type = 'product_variation' AND post_status = 'publish'
             ) AND meta_key = '_price' AND meta_value != ''",
            $product_id
        ));

        if (empty($prices)) {
            return;
        }

        $price_values = array_map(function($price) {
            return floatval($price->meta_value);
        }, $prices);

        $min_price = min($price_values);
        $max_price = max($price_values);

        // Update product price meta
        update_post_meta($product_id, '_price', $min_price);
        update_post_meta($product_id, '_min_variation_price', $min_price);
        update_post_meta($product_id, '_max_variation_price', $max_price);

        // If all variations have the same price, set regular price
        if ($min_price === $max_price) {
            update_post_meta($product_id, '_regular_price', $min_price);
        }
    }

    /**
     * Synchronize existing variations with Amazon
     *
     * @since    1.0.0
     * @access   public
     * @param    int    $product_id    Product ID
     * @return   array                 Sync results
     */
    public function sync_variations($product_id) {
        try {
            $parent_asin = get_post_meta($product_id, '_amazon_asin', true);
            
            if (empty($parent_asin)) {
                return array(
                    'success' => false,
                    'error' => __('ASIN parent non trouvé', 'amazon-product-importer')
                );
            }

            // Get existing variations
            $existing_variations = wc_get_products(array(
                'type' => 'variation',
                'parent' => $product_id,
                'limit' => -1
            ));

            $sync_results = array(
                'updated' => 0,
                'errors' => 0,
                'removed' => 0
            );

            foreach ($existing_variations as $variation) {
                $variation_asin = $variation->get_sku();
                
                if (empty($variation_asin)) {
                    continue;
                }

                $result = $this->sync_single_variation($variation->get_id(), $variation_asin);
                
                if ($result['success']) {
                    $sync_results['updated']++;
                } else {
                    $sync_results['errors']++;
                }
            }

            $this->logger->log('info', 'Variations synchronized', array(
                'product_id' => $product_id,
                'results' => $sync_results
            ));

            return array(
                'success' => true,
                'results' => $sync_results
            );

        } catch (Exception $e) {
            $this->logger->log('error', 'Variation sync failed', array(
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Synchronize a single variation
     *
     * @since    1.0.0
     * @access   private
     * @param    int       $variation_id    Variation ID
     * @param    string    $asin           Variation ASIN
     * @return   array                     Sync result
     */
    private function sync_single_variation($variation_id, $asin) {
        try {
            $variation_response = $this->api->get_product($asin);

            if (!$variation_response['success']) {
                return array(
                    'success' => false,
                    'error' => $variation_response['error']
                );
            }

            $variation_data = $variation_response['product'];
            $mapped_data = $this->mapper->map_variation_data($variation_data, wp_get_post_parent_id($variation_id));

            // Update variation meta data
            $this->set_variation_meta_data($variation_id, $mapped_data);

            // Update last sync time
            update_post_meta($variation_id, '_amazon_last_sync', current_time('mysql'));

            return array('success' => true);

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
}