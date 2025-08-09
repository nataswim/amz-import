<?php

/**
 * The product update functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/cron
 */

/**
 * The product update functionality of the plugin.
 *
 * Handles automatic synchronization of product information from Amazon API.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/cron
 * @author     Your Name <email@example.com>
 */
class Amazon_Product_Importer_Product_Updater {

    /**
     * The Amazon API instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Amazon_Api    $amazon_api    The Amazon API instance.
     */
    private $amazon_api;

    /**
     * The logger instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Logger    $logger    The logger instance.
     */
    private $logger;

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
     * Maximum number of products to update per batch.
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $batch_size    Maximum products per batch.
     */
    private $batch_size = 5;

    /**
     * Update configuration settings.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $update_settings    Update configuration.
     */
    private $update_settings;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->amazon_api = new Amazon_Product_Importer_Amazon_Api();
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->product_mapper = new Amazon_Product_Importer_Product_Mapper();
        $this->image_handler = new Amazon_Product_Importer_Image_Handler();
        $this->category_handler = new Amazon_Product_Importer_Category_Handler();
        
        // Get batch size from settings
        $this->batch_size = get_option('ams_product_update_batch_size', 5);
        
        // Load update settings
        $this->load_update_settings();
    }

    /**
     * Load update configuration settings.
     *
     * @since    1.0.0
     */
    private function load_update_settings() {
        $this->update_settings = array(
            'update_name' => get_option('ams_product_name_cron', false),
            'update_description' => get_option('ams_product_description_cron', false),
            'update_images' => get_option('ams_product_images_cron', false),
            'update_categories' => get_option('ams_product_category_cron', false),
            'update_sku' => get_option('ams_product_sku_cron', false),
            'update_attributes' => get_option('ams_product_attributes_cron', false),
            'update_variations' => get_option('ams_product_variations_cron', false),
            'check_availability' => get_option('ams_check_product_availability', true),
            'frequency_hours' => get_option('ams_product_update_frequency', 48)
        );
    }

    /**
     * Execute the product update process.
     *
     * @since    1.0.0
     * @return   array    Results of the update operation.
     */
    public function update_products() {
        $start_time = time();
        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'unavailable' => 0,
            'errors' => array()
        );

        try {
            // Check if API credentials are configured
            if (!$this->amazon_api->is_configured()) {
                throw new Exception('Amazon API credentials not configured.');
            }

            // Check if any updates are enabled
            if (!$this->is_any_update_enabled()) {
                $this->logger->log('No product updates are enabled in settings.', 'info');
                return $results;
            }

            // Get products that need updates
            $products = $this->get_products_for_update();
            
            if (empty($products)) {
                $this->logger->log('No products found for update.', 'info');
                return $results;
            }

            $this->logger->log(sprintf('Starting product update for %d products.', count($products)), 'info');

            // Process products in batches
            $batches = array_chunk($products, $this->batch_size);
            
            foreach ($batches as $batch_index => $batch) {
                $batch_results = $this->process_update_batch($batch);
                
                $results['success'] += $batch_results['success'];
                $results['failed'] += $batch_results['failed'];
                $results['skipped'] += $batch_results['skipped'];
                $results['unavailable'] += $batch_results['unavailable'];
                $results['errors'] = array_merge($results['errors'], $batch_results['errors']);

                // Rate limiting - wait between batches
                if ($batch_index < count($batches) - 1) {
                    sleep(2); // 2 second delay between batches
                }
            }

            $execution_time = time() - $start_time;
            $this->logger->log(sprintf(
                'Product update completed. Success: %d, Failed: %d, Skipped: %d, Unavailable: %d, Time: %ds',
                $results['success'],
                $results['failed'], 
                $results['skipped'],
                $results['unavailable'],
                $execution_time
            ), 'info');

        } catch (Exception $e) {
            $this->logger->log('Product update failed: ' . $e->getMessage(), 'error');
            $results['errors'][] = $e->getMessage();
        }

        // Update last update timestamp
        update_option('ams_last_product_update', current_time('mysql'));

        return $results;
    }

    /**
     * Get products that need updates.
     *
     * @since    1.0.0
     * @return   array    Array of product data.
     */
    private function get_products_for_update() {
        global $wpdb;

        $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$this->update_settings['frequency_hours']} hours"));

        // Query products with Amazon ASINs that need updating
        $query = "
            SELECT p.ID, pm_asin.meta_value as asin, pm_region.meta_value as region,
                   pm_parent_asin.meta_value as parent_asin
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_asin ON p.ID = pm_asin.post_id AND pm_asin.meta_key = '_amazon_asin'
            LEFT JOIN {$wpdb->postmeta} pm_region ON p.ID = pm_region.post_id AND pm_region.meta_key = '_amazon_region'
            LEFT JOIN {$wpdb->postmeta} pm_parent_asin ON p.ID = pm_parent_asin.post_id AND pm_parent_asin.meta_key = '_amazon_parent_asin'
            LEFT JOIN {$wpdb->postmeta} pm_last_update ON p.ID = pm_last_update.post_id AND pm_last_update.meta_key = '_amazon_last_product_update'
            WHERE p.post_type IN ('product', 'product_variation')
            AND p.post_status = 'publish'
            AND (pm_last_update.meta_value IS NULL OR pm_last_update.meta_value < %s)
            ORDER BY pm_last_update.meta_value ASC
            LIMIT %d
        ";

        $results = $wpdb->get_results(
            $wpdb->prepare($query, $cutoff_time, $this->batch_size * 3)
        );

        $products = array();
        foreach ($results as $row) {
            $products[] = array(
                'product_id' => $row->ID,
                'asin' => $row->asin,
                'parent_asin' => $row->parent_asin,
                'region' => $row->region ?: 'US'
            );
        }

        return $products;
    }

    /**
     * Process a batch of products for updates.
     *
     * @since    1.0.0
     * @param    array    $batch    Array of products to process.
     * @return   array    Results of the batch processing.
     */
    private function process_update_batch($batch) {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'unavailable' => 0,
            'errors' => array()
        );

        // Group ASINs by region for efficient API calls
        $asin_groups = array();
        foreach ($batch as $product_data) {
            $region = $product_data['region'];
            if (!isset($asin_groups[$region])) {
                $asin_groups[$region] = array();
            }
            $asin_groups[$region][] = $product_data;
        }

        // Process each region group
        foreach ($asin_groups as $region => $products) {
            $asins = array_column($products, 'asin');
            
            try {
                // Define which resources to fetch based on update settings
                $resources = $this->get_required_api_resources();
                
                // Get product data from Amazon API
                $api_data = $this->amazon_api->get_items_batch($asins, array(
                    'resources' => $resources,
                    'region' => $region
                ));

                if ($api_data && isset($api_data['ItemsResult']['Items'])) {
                    foreach ($api_data['ItemsResult']['Items'] as $item) {
                        $asin = $item['ASIN'];
                        $product_data = $this->find_product_by_asin($products, $asin);
                        
                        if ($product_data) {
                            $update_result = $this->update_single_product($product_data['product_id'], $item);
                            
                            if ($update_result === 'success') {
                                $results['success']++;
                            } elseif ($update_result === 'unavailable') {
                                $results['unavailable']++;
                            } elseif ($update_result === 'skipped') {
                                $results['skipped']++;
                            } else {
                                $results['failed']++;
                                $results['errors'][] = "Failed to update product ID {$product_data['product_id']}";
                            }
                        }
                    }

                    // Handle products not found in API response (potentially unavailable)
                    $found_asins = array_column($api_data['ItemsResult']['Items'], 'ASIN');
                    $missing_asins = array_diff($asins, $found_asins);
                    
                    foreach ($missing_asins as $missing_asin) {
                        $product_data = $this->find_product_by_asin($products, $missing_asin);
                        if ($product_data) {
                            $this->handle_unavailable_product($product_data['product_id']);
                            $results['unavailable']++;
                        }
                    }

                } else {
                    $results['failed'] += count($products);
                    $results['errors'][] = "API request failed for region {$region}";
                }

            } catch (Exception $e) {
                $results['failed'] += count($products);
                $results['errors'][] = "Error processing region {$region}: " . $e->getMessage();
                $this->logger->log("Product update error for region {$region}: " . $e->getMessage(), 'error');
            }
        }

        return $results;
    }

    /**
     * Update a single product with Amazon data.
     *
     * @since    1.0.0
     * @param    int      $product_id    WooCommerce product ID.
     * @param    array    $item_data     Amazon API item data.
     * @return   string   Update result status.
     */
    private function update_single_product($product_id, $item_data) {
        try {
            $product = wc_get_product($product_id);
            
            if (!$product) {
                return 'failed';
            }

            $updated = false;
            $changes = array();

            // Update product name
            if ($this->update_settings['update_name'] && isset($item_data['ItemInfo']['Title']['DisplayValue'])) {
                $new_title = sanitize_text_field($item_data['ItemInfo']['Title']['DisplayValue']);
                $current_title = $product->get_name();
                
                if ($new_title !== $current_title && !empty($new_title)) {
                    $product->set_name($new_title);
                    $changes[] = 'title';
                    $updated = true;
                }
            }

            // Update product description
            if ($this->update_settings['update_description']) {
                $updated_desc = $this->update_product_descriptions($product, $item_data);
                if ($updated_desc) {
                    $changes[] = 'description';
                    $updated = true;
                }
            }

            // Update SKU
            if ($this->update_settings['update_sku']) {
                $new_sku = $item_data['ASIN'];
                $current_sku = $product->get_sku();
                
                if ($new_sku !== $current_sku) {
                    $product->set_sku($new_sku);
                    $changes[] = 'sku';
                    $updated = true;
                }
            }

            // Update product attributes
            if ($this->update_settings['update_attributes']) {
                $updated_attrs = $this->update_product_attributes($product, $item_data);
                if ($updated_attrs) {
                    $changes[] = 'attributes';
                    $updated = true;
                }
            }

            // Save product changes
            if ($updated) {
                $product->save();
            }

            // Update images (after product save)
            if ($this->update_settings['update_images']) {
                $updated_images = $this->update_product_images($product_id, $item_data);
                if ($updated_images) {
                    $changes[] = 'images';
                    $updated = true;
                }
            }

            // Update categories
            if ($this->update_settings['update_categories']) {
                $updated_cats = $this->update_product_categories($product_id, $item_data);
                if ($updated_cats) {
                    $changes[] = 'categories';
                    $updated = true;
                }
            }

            // Update variations if this is a variable product
            if ($this->update_settings['update_variations'] && $product->is_type('variable')) {
                $updated_vars = $this->update_product_variations($product_id, $item_data);
                if ($updated_vars) {
                    $changes[] = 'variations';
                    $updated = true;
                }
            }

            // Update last update timestamp
            update_post_meta($product_id, '_amazon_last_product_update', current_time('mysql'));

            // Log changes
            if ($updated && !empty($changes)) {
                $this->logger->log(sprintf(
                    'Product %d updated. Changes: %s',
                    $product_id,
                    implode(', ', $changes)
                ), 'info');
            }

            return $updated ? 'success' : 'skipped';

        } catch (Exception $e) {
            $this->logger->log("Error updating product {$product_id}: " . $e->getMessage(), 'error');
            return 'failed';
        }
    }

    /**
     * Update product descriptions.
     *
     * @since    1.0.0
     * @param    WC_Product    $product      WooCommerce product.
     * @param    array         $item_data    Amazon API item data.
     * @return   bool          True if updated.
     */
    private function update_product_descriptions($product, $item_data) {
        $updated = false;

        // Update short description from features
        if (isset($item_data['ItemInfo']['Features']['DisplayValues'])) {
            $features = $item_data['ItemInfo']['Features']['DisplayValues'];
            $short_description = implode("\n• ", $features);
            $short_description = "• " . $short_description;
            
            if ($product->get_short_description() !== $short_description) {
                $product->set_short_description($short_description);
                $updated = true;
            }
        }

        // Update long description
        if (isset($item_data['ItemInfo']['ProductInfo']['ProductDescription'])) {
            $long_description = wp_kses_post($item_data['ItemInfo']['ProductInfo']['ProductDescription']);
            
            if ($product->get_description() !== $long_description) {
                $product->set_description($long_description);
                $updated = true;
            }
        }

        return $updated;
    }

    /**
     * Update product attributes.
     *
     * @since    1.0.0
     * @param    WC_Product    $product      WooCommerce product.
     * @param    array         $item_data    Amazon API item data.
     * @return   bool          True if updated.
     */
    private function update_product_attributes($product, $item_data) {
        $updated = false;
        $attributes = $product->get_attributes();

        // Update brand attribute
        if (isset($item_data['ItemInfo']['ByLineInfo']['Brand']['DisplayValue'])) {
            $brand = $item_data['ItemInfo']['ByLineInfo']['Brand']['DisplayValue'];
            
            if (!isset($attributes['pa_brand']) || $attributes['pa_brand']->get_options()[0] !== $brand) {
                $brand_attr = new WC_Product_Attribute();
                $brand_attr->set_name('pa_brand');
                $brand_attr->set_options(array($brand));
                $brand_attr->set_visible(true);
                $brand_attr->set_variation(false);
                
                $attributes['pa_brand'] = $brand_attr;
                $updated = true;
            }
        }

        // Update other item attributes if available
        if (isset($item_data['ItemInfo']['ProductInfo']['ItemDimensions'])) {
            $dimensions = $item_data['ItemInfo']['ProductInfo']['ItemDimensions'];
            
            // Add dimensions as attributes
            foreach ($dimensions as $key => $dimension) {
                if (isset($dimension['DisplayValue'])) {
                    $attr_name = 'pa_' . strtolower($key);
                    $attr_value = $dimension['DisplayValue'];
                    
                    if (!isset($attributes[$attr_name])) {
                        $dim_attr = new WC_Product_Attribute();
                        $dim_attr->set_name($attr_name);
                        $dim_attr->set_options(array($attr_value));
                        $dim_attr->set_visible(true);
                        $dim_attr->set_variation(false);
                        
                        $attributes[$attr_name] = $dim_attr;
                        $updated = true;
                    }
                }
            }
        }

        if ($updated) {
            $product->set_attributes($attributes);
        }

        return $updated;
    }

    /**
     * Update product images.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $item_data     Amazon API item data.
     * @return   bool     True if updated.
     */
    private function update_product_images($product_id, $item_data) {
        if (!isset($item_data['Images']['Primary']) && !isset($item_data['Images']['Variants'])) {
            return false;
        }

        return $this->image_handler->update_product_images($product_id, $item_data);
    }

    /**
     * Update product categories.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $item_data     Amazon API item data.
     * @return   bool     True if updated.
     */
    private function update_product_categories($product_id, $item_data) {
        if (!isset($item_data['BrowseNodeInfo']['BrowseNodes'])) {
            return false;
        }

        return $this->category_handler->update_product_categories($product_id, $item_data['BrowseNodeInfo']['BrowseNodes']);
    }

    /**
     * Update product variations.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $item_data     Amazon API item data.
     * @return   bool     True if updated.
     */
    private function update_product_variations($product_id, $item_data) {
        if (!isset($item_data['VariationSummary']['VariationDimensions'])) {
            return false;
        }

        // This would require integration with variation handler
        // For now, just return false as variations are complex
        return false;
    }

    /**
     * Handle unavailable products.
     *
     * @since    1.0.0
     * @param    int    $product_id    Product ID.
     */
    private function handle_unavailable_product($product_id) {
        if (!$this->update_settings['check_availability']) {
            return;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        // Set product as out of stock
        $product->set_stock_status('outofstock');
        $product->save();

        // Update unavailable timestamp
        update_post_meta($product_id, '_amazon_unavailable_since', current_time('mysql'));
        update_post_meta($product_id, '_amazon_last_product_update', current_time('mysql'));

        $this->logger->log("Product {$product_id} marked as unavailable.", 'warning');
    }

    /**
     * Get required API resources based on update settings.
     *
     * @since    1.0.0
     * @return   array    Array of API resources to fetch.
     */
    private function get_required_api_resources() {
        $resources = array();

        if ($this->update_settings['update_name']) {
            $resources[] = 'ItemInfo.Title';
        }

        if ($this->update_settings['update_description']) {
            $resources[] = 'ItemInfo.Features';
            $resources[] = 'ItemInfo.ProductInfo';
        }

        if ($this->update_settings['update_attributes']) {
            $resources[] = 'ItemInfo.ByLineInfo';
            $resources[] = 'ItemInfo.ProductInfo';
        }

        if ($this->update_settings['update_images']) {
            $resources[] = 'Images.Primary';
            $resources[] = 'Images.Variants';
        }

        if ($this->update_settings['update_categories']) {
            $resources[] = 'BrowseNodeInfo.BrowseNodes';
        }

        if ($this->update_settings['update_variations']) {
            $resources[] = 'VariationSummary';
        }

        // Always include basic info
        $resources[] = 'Offers.Listings.Availability';

        return array_unique($resources);
    }

    /**
     * Check if any update is enabled.
     *
     * @since    1.0.0
     * @return   bool    True if any update is enabled.
     */
    private function is_any_update_enabled() {
        return $this->update_settings['update_name'] ||
               $this->update_settings['update_description'] ||
               $this->update_settings['update_images'] ||
               $this->update_settings['update_categories'] ||
               $this->update_settings['update_sku'] ||
               $this->update_settings['update_attributes'] ||
               $this->update_settings['update_variations'] ||
               $this->update_settings['check_availability'];
    }

    /**
     * Find product data by ASIN in the batch.
     *
     * @since    1.0.0
     * @param    array    $products    Array of product data.
     * @param    string   $asin        ASIN to find.
     * @return   array|null            Product data or null if not found.
     */
    private function find_product_by_asin($products, $asin) {
        foreach ($products as $product_data) {
            if ($product_data['asin'] === $asin) {
                return $product_data;
            }
        }
        return null;
    }

    /**
     * Manual update for specific products.
     *
     * @since    1.0.0
     * @param    array    $product_ids    Array of product IDs to update.
     * @return   array    Update results.
     */
    public function manual_update($product_ids) {
        $products = array();
        
        foreach ($product_ids as $product_id) {
            $asin = get_post_meta($product_id, '_amazon_asin', true);
            $parent_asin = get_post_meta($product_id, '_amazon_parent_asin', true);
            $region = get_post_meta($product_id, '_amazon_region', true) ?: 'US';
            
            if ($asin) {
                $products[] = array(
                    'product_id' => $product_id,
                    'asin' => $asin,
                    'parent_asin' => $parent_asin,
                    'region' => $region
                );
            }
        }

        if (empty($products)) {
            return array('success' => 0, 'failed' => 0, 'errors' => array('No valid Amazon products found.'));
        }

        return $this->process_update_batch($products);
    }

    /**
     * Get the next scheduled update time.
     *
     * @since    1.0.0
     * @return   string    Next update time in MySQL format.
     */
    public function get_next_update_time() {
        $frequency = $this->update_settings['frequency_hours'];
        $last_update = get_option('ams_last_product_update');
        
        if ($last_update) {
            return date('Y-m-d H:i:s', strtotime($last_update . " +{$frequency} hours"));
        } else {
            return current_time('mysql');
        }
    }

    /**
     * Check if product update is due.
     *
     * @since    1.0.0
     * @return   bool    True if update is due.
     */
    public function is_update_due() {
        $next_update = $this->get_next_update_time();
        return (strtotime($next_update) <= time());
    }
}