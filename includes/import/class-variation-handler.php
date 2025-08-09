<?php

/**
 * Handles product variations import from Amazon
 */
class Amazon_Product_Importer_Variation_Handler {

    private $logger;
    private $api;
    private $mapper;

    public function __construct() {
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->api = new Amazon_Product_Importer_Amazon_API();
        $this->mapper = new Amazon_Product_Importer_Product_Mapper();
    }

    /**
     * Import product variations
     */
    public function import_variations($product_id, $parent_asin, $amazon_product) {
        try {
            // Convert simple product to variable product
            $this->convert_to_variable_product($product_id);

            // Get variation data from Amazon
            $variations_data = $this->get_variations_data($parent_asin, $amazon_product);

            if (empty($variations_data)) {
                $this->logger->log('warning', 'No variations found for product', array(
                    'product_id' => $product_id,
                    'asin' => $parent_asin
                ));
                return array();
            }

            // Create product attributes for variations
            $this->create_variation_attributes($product_id, $variations_data);

            // Create individual variations
            $variation_ids = array();
            foreach ($variations_data as $variation_data) {
                $variation_id = $this->create_variation($product_id, $variation_data);
                if ($variation_id) {
                    $variation_ids[] = $variation_id;
                }
            }

            $this->logger->log('info', 'Product variations imported', array(
                'product_id' => $product_id,
                'variations_count' => count($variation_ids)
            ));

            return $variation_ids;

        } catch (Exception $e) {
            $this->logger->log('error', 'Variations import failed', array(
                'product_id' => $product_id,
                'asin' => $parent_asin,
                'error' => $e->getMessage()
            ));

            return array();
        }
    }

    /**
     * Convert simple product to variable product
     */
    private function convert_to_variable_product($product_id) {
        wp_set_object_terms($product_id, 'variable', 'product_type');
        
        // Remove simple product price meta
        delete_post_meta($product_id, '_regular_price');
        delete_post_meta($product_id, '_sale_price');
        delete_post_meta($product_id, '_price');
    }

    /**
     * Get variations data from Amazon
     */
    private function get_variations_data($parent_asin, $amazon_product) {
        $variations_data = array();

        // Try to get variations from API
        $variations_response = $this->api->get_variations($parent_asin);

        if ($variations_response['success'] && !empty($variations_response['variations'])) {
            return $variations_response['variations'];
        }

        // Fallback: try to extract from product data
        if (isset($amazon_product['VariationSummary']['VariationDimension'])) {
            // This is a simplified approach - in reality, you'd need to make separate API calls
            // for each variation ASIN if they're available
            $variation_dimensions = $amazon_product['VariationSummary']['VariationDimension'];
            
            // Create a single variation for demo purposes
            $variations_data[] = array(
                'ASIN' => $parent_asin,
                'VariationSummary' => array(
                    'VariationDimension' => $variation_dimensions
                ),
                'Offers' => $amazon_product['Offers'] ?? array()
            );
        }

        return $variations_data;
    }

    /**
     * Create variation attributes
     */
    private function create_variation_attributes($product_id, $variations_data) {
        $attributes = array();
        $all_attribute_values = array();

        // Collect all unique attributes and their values
        foreach ($variations_data as $variation) {
            if (isset($variation['VariationSummary']['VariationDimension'])) {
                foreach ($variation['VariationSummary']['VariationDimension'] as $dimension) {
                    $attribute_name = $dimension['Name'];
                    $attribute_value = $dimension['DisplayValue'];
                    
                    if (!isset($all_attribute_values[$attribute_name])) {
                        $all_attribute_values[$attribute_name] = array();
                    }
                    
                    if (!in_array($attribute_value, $all_attribute_values[$attribute_name])) {
                        $all_attribute_values[$attribute_name][] = $attribute_value;
                    }
                }
            }
        }

        // Create WooCommerce attributes
        foreach ($all_attribute_values as $attribute_name => $values) {
            $attribute_slug = 'pa_' . sanitize_title($attribute_name);
            
            // Create or get attribute taxonomy
            $attribute_id = $this->get_or_create_attribute($attribute_name, $attribute_slug);
            
            if ($attribute_id) {
                // Create attribute terms
                $term_ids = array();
                foreach ($values as $value) {
                    $term = wp_insert_term($value, $attribute_slug);
                    if (!is_wp_error($term)) {
                        $term_ids[] = $term['term_id'];
                    }
                }

                // Set product attribute
                $attributes[$attribute_slug] = array(
                    'name' => $attribute_slug,
                    'value' => '',
                    'position' => count($attributes),
                    'is_visible' => 1,
                    'is_variation' => 1,
                    'is_taxonomy' => 1
                );

                // Set attribute terms for product
                wp_set_object_terms($product_id, $term_ids, $attribute_slug);
            }
        }

        // Update product attributes
        update_post_meta($product_id, '_product_attributes', $attributes);
    }

    /**
     * Create individual variation
     */
    private function create_variation($product_id, $variation_data) {
        // Map variation data
        $mapped_data = $this->mapper->map_variation_data($variation_data, $product_id);

        // Create variation post
        $variation_post = array(
            'post_title' => get_the_title($product_id),
            'post_name' => 'product-' . $product_id . '-variation',
            'post_status' => 'publish',
            'post_parent' => $product_id,
            'post_type' => 'product_variation',
            'menu_order' => 0
        );

        $variation_id = wp_insert_post($variation_post);

        if (is_wp_error($variation_id)) {
            throw new Exception('Failed to create variation: ' . $variation_id->get_error_message());
        }

        // Set variation data
        $variation = new WC_Product_Variation($variation_id);

        // Set prices
        if (isset($mapped_data['regular_price'])) {
            $variation->set_regular_price($mapped_data['regular_price']);
        }

        if (isset($mapped_data['sale_price'])) {
            $variation->set_sale_price($mapped_data['sale_price']);
        }

        // Set SKU
        if (isset($mapped_data['sku'])) {
            $variation->set_sku($mapped_data['sku']);
        }

        // Set stock status
        if (isset($mapped_data['stock_status'])) {
            $variation->set_stock_status($mapped_data['stock_status']);
        }

        // Set variation attributes
        if (isset($mapped_data['variation_attributes'])) {
            $variation->set_attributes($mapped_data['variation_attributes']);
        }

        $variation->save();

        // Store Amazon meta data
        update_post_meta($variation_id, '_amazon_asin', $mapped_data['asin']);
        update_post_meta($variation_id, '_amazon_parent_asin', $variation_data['ParentASIN'] ?? '');

        return $variation_id;
    }

    /**
     * Get or create product attribute
     */
    private function get_or_create_attribute($name, $slug) {
        global $wpdb;

        $slug = str_replace('pa_', '', $slug);

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

            // Register taxonomy
            $taxonomy_name = 'pa_' . $slug;
            
            if (!taxonomy_exists($taxonomy_name)) {
                register_taxonomy($taxonomy_name, array('product'), array(
                    'labels' => array(
                        'name' => $name,
                        'singular_name' => $name,
                    ),
                    'public' => false,
                    'show_ui' => false,
                    'show_in_menu' => false,
                    'query_var' => false,
                    'rewrite' => false,
                ));
            }

            // Clear cache
            delete_transient('wc_attribute_taxonomies');
        }

        return $attribute_id;
    }

    /**
     * Update variations
     */
    public function update_variations($product_id, $parent_asin, $force_update = false) {
        if (!$force_update) {
            // Check if variations were recently updated
            $last_variations_update = get_post_meta($product_id, '_amazon_variations_last_update', true);
            
            if ($last_variations_update && (time() - strtotime($last_variations_update)) < 86400) {
                return false; // Skip if updated within last 24 hours
            }
        }

        // Get current Amazon product data
        $api_response = $this->api->get_product($parent_asin);
        
        if (!$api_response['success']) {
            return false;
        }

        // Import variations
        $variation_ids = $this->import_variations($product_id, $parent_asin, $api_response['product']);

        // Update timestamp
        update_post_meta($product_id, '_amazon_variations_last_update', current_time('mysql'));

        return $variation_ids;
    }

    /**
     * Delete unused variations
     */
    public function cleanup_unused_variations($product_id) {
        $variations = wc_get_product($product_id)->get_children();
        $deleted_count = 0;

        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);
            
            if (!$variation) {
                continue;
            }

            // Check if variation has valid Amazon ASIN
            $asin = get_post_meta($variation_id, '_amazon_asin', true);
            
            if (empty($asin)) {
                // Delete variation without ASIN
                wp_delete_post($variation_id, true);
                $deleted_count++;
            }
        }

        return $deleted_count;
    }
}