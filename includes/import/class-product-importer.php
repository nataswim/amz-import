<?php

/**
 * Main product importer class
 */
class Amazon_Product_Importer_Product_Importer {

    private $api;
    private $mapper;
    private $image_handler;
    private $category_handler;
    private $variation_handler;
    private $logger;
    private $database;

    public function __construct() {
        $this->api = new Amazon_Product_Importer_Amazon_API();
        $this->mapper = new Amazon_Product_Importer_Product_Mapper();
        $this->image_handler = new Amazon_Product_Importer_Image_Handler();
        $this->category_handler = new Amazon_Product_Importer_Category_Handler();
        $this->variation_handler = new Amazon_Product_Importer_Variation_Handler();
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->database = new Amazon_Product_Importer_Database();
    }

    /**
     * Import a single product by ASIN
     */
    public function import_product($asin, $force_update = false) {
        try {
            $this->logger->log('info', 'Starting product import', array('asin' => $asin));

            // Check if product already exists
            $existing_product_id = $this->get_existing_product_by_asin($asin);
            
            if ($existing_product_id && !$force_update) {
                return array(
                    'success' => false,
                    'error' => __('Produit déjà importé', 'amazon-product-importer'),
                    'product_id' => $existing_product_id
                );
            }

            // Get product data from Amazon
            $api_response = $this->api->get_product($asin);
            
            if (!$api_response['success']) {
                throw new Exception($api_response['error']);
            }

            $amazon_product = $api_response['product'];
            
            if (!$amazon_product) {
                throw new Exception(__('Produit non trouvé sur Amazon', 'amazon-product-importer'));
            }

            // Map Amazon data to WooCommerce format
            $mapped_data = $this->mapper->map_product_data($amazon_product);

            // Create or update WooCommerce product
            if ($existing_product_id) {
                $product_id = $this->update_woocommerce_product($existing_product_id, $mapped_data);
            } else {
                $product_id = $this->create_woocommerce_product($mapped_data);
            }

            // Import images
            $this->import_product_images($product_id, $amazon_product);

            // Import categories
            $this->import_product_categories($product_id, $amazon_product);

            // Handle variations if present
            if ($this->has_variations($amazon_product)) {
                $this->import_product_variations($product_id, $asin, $amazon_product);
            }

            // Log successful import
            $this->database->log_import($asin, $product_id, 'import', 'success', 'Product imported successfully');

            $this->logger->log('info', 'Product import completed', array(
                'asin' => $asin,
                'product_id' => $product_id
            ));

            return array(
                'success' => true,
                'product_id' => $product_id,
                'message' => __('Produit importé avec succès', 'amazon-product-importer')
            );

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            
            $this->logger->log('error', 'Product import failed', array(
                'asin' => $asin,
                'error' => $error_message
            ));

            if (isset($product_id)) {
                $this->database->log_import($asin, $product_id, 'import', 'error', $error_message);
            }

            return array(
                'success' => false,
                'error' => $error_message
            );
        }
    }

    /**
     * Create new WooCommerce product
     */
    private function create_woocommerce_product($mapped_data) {
        $product = new WC_Product_Simple();

        // Basic product information
        $product->set_name($mapped_data['title']);
        $product->set_description($mapped_data['description']);
        $product->set_short_description($mapped_data['short_description']);
        $product->set_sku($mapped_data['sku']);
        $product->set_status('publish');

        // Price information
        if (isset($mapped_data['regular_price'])) {
            $product->set_regular_price($mapped_data['regular_price']);
        }
        
        if (isset($mapped_data['sale_price'])) {
            $product->set_sale_price($mapped_data['sale_price']);
        }

        // Stock management
        $product->set_manage_stock(false);
        $product->set_stock_status('instock');

        // Save the product
        $product_id = $product->save();

        // Add Amazon-specific meta data
        update_post_meta($product_id, '_amazon_asin', $mapped_data['asin']);
        update_post_meta($product_id, '_amazon_region', get_option('amazon_importer_api_region'));
        update_post_meta($product_id, '_amazon_associate_tag', get_option('amazon_importer_api_associate_tag'));
        update_post_meta($product_id, '_amazon_import_date', current_time('mysql'));
        update_post_meta($product_id, '_amazon_sync_enabled', true);

        // Add product attributes
        if (!empty($mapped_data['attributes'])) {
            $this->add_product_attributes($product_id, $mapped_data['attributes']);
        }

        return $product_id;
    }

    /**
     * Update existing WooCommerce product
     */
    private function update_woocommerce_product($product_id, $mapped_data) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            throw new Exception(__('Produit non trouvé', 'amazon-product-importer'));
        }

        // Update product information based on sync settings
        if (get_option('amazon_importer_product_name_cron')) {
            $product->set_name($mapped_data['title']);
        }

        $product->set_description($mapped_data['description']);
        $product->set_short_description($mapped_data['short_description']);

        // Update price information
        if (isset($mapped_data['regular_price'])) {
            $product->set_regular_price($mapped_data['regular_price']);
        }
        
        if (isset($mapped_data['sale_price'])) {
            $product->set_sale_price($mapped_data['sale_price']);
        }

        // Save the product
        $product->save();

        // Update Amazon meta data
        update_post_meta($product_id, '_amazon_last_sync', current_time('mysql'));

        // Update attributes
        if (!empty($mapped_data['attributes'])) {
            $this->add_product_attributes($product_id, $mapped_data['attributes']);
        }

        return $product_id;
    }

    /**
     * Import product images
     */
    private function import_product_images($product_id, $amazon_product) {
        if (!isset($amazon_product['Images'])) {
            return;
        }

        $this->image_handler->import_product_images($product_id, $amazon_product['Images']);
    }

    /**
     * Import product categories
     */
    private function import_product_categories($product_id, $amazon_product) {
        if (!isset($amazon_product['BrowseNodeInfo'])) {
            return;
        }

        $this->category_handler->import_product_categories($product_id, $amazon_product['BrowseNodeInfo']);
    }

    /**
     * Check if product has variations
     */
    private function has_variations($amazon_product) {
        return isset($amazon_product['VariationSummary']) && 
               !empty($amazon_product['VariationSummary']['VariationDimension']);
    }

    /**
     * Import product variations
     */
    private function import_product_variations($product_id, $parent_asin, $amazon_product) {
        $this->variation_handler->import_variations($product_id, $parent_asin, $amazon_product);
    }

    /**
     * Add product attributes
     */
    private function add_product_attributes($product_id, $attributes) {
        $product_attributes = array();

        foreach ($attributes as $attribute_name => $attribute_value) {
            $attribute_slug = sanitize_title($attribute_name);
            
            // Create or get attribute taxonomy
            $attribute_id = $this->get_or_create_attribute($attribute_name, $attribute_slug);
            
            if ($attribute_id) {
                $product_attributes['pa_' . $attribute_slug] = array(
                    'name' => 'pa_' . $attribute_slug,
                    'value' => $attribute_value,
                    'position' => 0,
                    'is_visible' => 1,
                    'is_variation' => 0,
                    'is_taxonomy' => 1
                );

                // Set product attribute terms
                wp_set_object_terms($product_id, $attribute_value, 'pa_' . $attribute_slug, true);
            }
        }

        if (!empty($product_attributes)) {
            update_post_meta($product_id, '_product_attributes', $product_attributes);
        }
    }

    /**
     * Get or create product attribute
     */
    private function get_or_create_attribute($name, $slug) {
        global $wpdb;

        $attribute_id = $wpdb->get_var($wpdb->prepare(
            "SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
            $slug
        ));

        if (!$attribute_id) {
            $attribute_data = array(
                'attribute_label' => $name,
                'attribute_name' => $slug,
                'attribute_type' => 'select',
                'attribute_orderby' => 'menu_order',
                'attribute_public' => 0
            );

            $wpdb->insert($wpdb->prefix . 'woocommerce_attribute_taxonomies', $attribute_data);
            $attribute_id = $wpdb->insert_id;

            // Create taxonomy
            $taxonomy_name = 'pa_' . $slug;
            register_taxonomy($taxonomy_name, 'product');

            // Clear cache
            delete_transient('wc_attribute_taxonomies');
        }

        return $attribute_id;
    }

    /**
     * Get existing product by ASIN
     */
    private function get_existing_product_by_asin($asin) {
        global $wpdb;

        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_amazon_asin' AND meta_value = %s 
             LIMIT 1",
            $asin
        ));

        return $product_id;
    }

    /**
     * Search products on Amazon
     */
    public function search_products($keywords, $category = null, $page = 1, $items_per_page = 20) {
        try {
            $response = $this->api->search_products($keywords, $category, $page, $items_per_page);
            
            if ($response['success'] && !empty($response['items'])) {
                // Check which products are already imported
                foreach ($response['items'] as &$item) {
                    $item['is_imported'] = $this->get_existing_product_by_asin($item['asin']) ? true : false;
                }
            }

            return $response;

        } catch (Exception $e) {
            $this->logger->log('error', 'Product search failed', array(
                'keywords' => $keywords,
                'error' => $e->getMessage()
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'items' => array()
            );
        }
    }

    /**
     * Import multiple products
     */
    public function import_multiple_products($asins, $force_update = false) {
        $results = array();
        $success_count = 0;
        $error_count = 0;

        foreach ($asins as $asin) {
            $result = $this->import_product($asin, $force_update);
            $results[$asin] = $result;

            if ($result['success']) {
                $success_count++;
            } else {
                $error_count++;
            }

            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 seconds
        }

        return array(
            'results' => $results,
            'summary' => array(
                'total' => count($asins),
                'success' => $success_count,
                'errors' => $error_count
            )
        );
    }
}