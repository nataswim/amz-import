<?php

/**
 * Handles category import from Amazon
 */
class Amazon_Product_Importer_Category_Handler {

    private $logger;
    private $api;

    public function __construct() {
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->api = new Amazon_Product_Importer_Amazon_API();
    }

    /**
     * Import product categories
     */
    public function import_product_categories($product_id, $browse_node_info) {
        try {
            if (empty($browse_node_info['BrowseNodes'])) {
                return array();
            }

            $category_ids = array();

            foreach ($browse_node_info['BrowseNodes'] as $node) {
                $category_hierarchy = $this->build_category_hierarchy($node);
                
                if (!empty($category_hierarchy)) {
                    $category_id = $this->create_category_hierarchy($category_hierarchy);
                    if ($category_id) {
                        $category_ids[] = $category_id;
                    }
                }
            }

            // Assign categories to product
            if (!empty($category_ids)) {
                wp_set_object_terms($product_id, $category_ids, 'product_cat');
            }

            $this->logger->log('info', 'Categories imported for product', array(
                'product_id' => $product_id,
                'categories_count' => count($category_ids)
            ));

            return $category_ids;

        } catch (Exception $e) {
            $this->logger->log('error', 'Category import failed', array(
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ));

            return array();
        }
    }

    /**
     * Build category hierarchy from browse node
     */
    private function build_category_hierarchy($node) {
        $hierarchy = array();
        $current_node = $node;

        // Build hierarchy from current node to root
        while ($current_node) {
            if (isset($current_node['DisplayName']) && !empty($current_node['DisplayName'])) {
                array_unshift($hierarchy, array(
                    'name' => $current_node['DisplayName'],
                    'id' => $current_node['Id'] ?? null
                ));
            }

            // Move to parent if available
            $current_node = null;
            if (isset($current_node['Ancestors']) && !empty($current_node['Ancestors'])) {
                $current_node = $current_node['Ancestors'][0];
            }
        }

        // Apply depth limits
        $min_depth = get_option('amazon_importer_category_min_depth', 1);
        $max_depth = get_option('amazon_importer_category_max_depth', 3);

        if (count($hierarchy) < $min_depth) {
            return array();
        }

        if (count($hierarchy) > $max_depth) {
            $hierarchy = array_slice($hierarchy, 0, $max_depth);
        }

        return $hierarchy;
    }

    /**
     * Create category hierarchy in WooCommerce
     */
    private function create_category_hierarchy($hierarchy) {
        $parent_id = 0;

        foreach ($hierarchy as $category_data) {
            $category_name = $category_data['name'];
            
            // Check if category already exists
            $existing_category = $this->get_category_by_name($category_name, $parent_id);
            
            if ($existing_category) {
                $parent_id = $existing_category->term_id;
            } else {
                // Create new category
                $result = wp_insert_term(
                    $category_name,
                    'product_cat',
                    array(
                        'parent' => $parent_id,
                        'slug' => sanitize_title($category_name)
                    )
                );

                if (is_wp_error($result)) {
                    $this->logger->log('error', 'Category creation failed', array(
                        'category_name' => $category_name,
                        'error' => $result->get_error_message()
                    ));
                    return false;
                }

                $parent_id = $result['term_id'];

                // Store Amazon browse node ID
                if (isset($category_data['id'])) {
                    update_term_meta($parent_id, '_amazon_browse_node_id', $category_data['id']);
                }
            }
        }

        return $parent_id;
    }

    /**
     * Get category by name and parent
     */
    private function get_category_by_name($name, $parent_id = 0) {
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'name' => $name,
            'parent' => $parent_id,
            'hide_empty' => false,
            'number' => 1
        ));

        return !empty($categories) ? $categories[0] : null;
    }

    /**
     * Update category hierarchy
     */
    public function update_category_hierarchy($product_id, $browse_node_info, $force_update = false) {
        if (!$force_update) {
            // Check if categories were recently updated
            $last_category_update = get_post_meta($product_id, '_amazon_categories_last_update', true);
            
            if ($last_category_update && (time() - strtotime($last_category_update)) < 604800) {
                return false; // Skip if updated within last week
            }
        }

        // Import categories
        $category_ids = $this->import_product_categories($product_id, $browse_node_info);

        // Update timestamp
        update_post_meta($product_id, '_amazon_categories_last_update', current_time('mysql'));

        return $category_ids;
    }

    /**
     * Get category mapping
     */
    public function get_category_mapping() {
        $mapping = get_option('amazon_importer_category_mapping', array());
        
        return apply_filters('amazon_importer_category_mapping', $mapping);
    }

    /**
     * Map Amazon category to WooCommerce category
     */
    public function map_category($amazon_category_name) {
        $mapping = $this->get_category_mapping();
        
        if (isset($mapping[$amazon_category_name])) {
            return $mapping[$amazon_category_name];
        }

        return $amazon_category_name;
    }

    /**
     * Cleanup orphaned categories
     */
    public function cleanup_orphaned_categories() {
        // Get all Amazon-imported categories
        $amazon_categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'meta_query' => array(
                array(
                    'key' => '_amazon_browse_node_id',
                    'compare' => 'EXISTS'
                )
            ),
            'hide_empty' => false
        ));

        $deleted_count = 0;

        foreach ($amazon_categories as $category) {
            // Check if category has any products
            $product_count = wp_count_terms(array(
                'taxonomy' => 'product_cat',
                'parent' => $category->term_id,
                'hide_empty' => true
            ));

            if ($product_count == 0) {
                $result = wp_delete_term($category->term_id, 'product_cat');
                if (!is_wp_error($result)) {
                    $deleted_count++;
                }
            }
        }

        $this->logger->log('info', 'Orphaned categories cleanup completed', array(
            'deleted_count' => $deleted_count
        ));

        return $deleted_count;
    }
}