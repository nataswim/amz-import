<?php

/**
 * The category handling functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/import
 */

/**
 * The category handling functionality of the plugin.
 *
 * Handles creation and management of product categories from Amazon BrowseNode data.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/import
 * @author     Your Name <email@example.com>
 */
class Amazon_Product_Importer_Category_Handler {

    /**
     * The logger instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Logger    $logger    The logger instance.
     */
    private $logger;

    /**
     * Category mapping cache.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $category_cache    Category mapping cache.
     */
    private $category_cache = array();

    /**
     * Default category settings.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $settings    Default category settings.
     */
    private $settings;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->load_settings();
    }

    /**
     * Load category settings.
     *
     * @since    1.0.0
     */
    private function load_settings() {
        $this->settings = array(
            'auto_create_categories' => get_option('ams_auto_create_categories', true),
            'min_depth' => get_option('ams_category_min_depth', 1),
            'max_depth' => get_option('ams_category_max_depth', 4),
            'category_prefix' => get_option('ams_category_prefix', 'Amazon'),
            'merge_similar_categories' => get_option('ams_merge_similar_categories', true),
            'category_description_template' => get_option('ams_category_description_template', 'Products from Amazon category: %s'),
            'excluded_browse_nodes' => get_option('ams_excluded_browse_nodes', array()),
            'category_mapping' => get_option('ams_category_mapping', array())
        );
    }

    /**
     * Process categories from Amazon BrowseNode data.
     *
     * @since    1.0.0
     * @param    int      $product_id     Product ID.
     * @param    array    $browse_nodes   Amazon BrowseNode data.
     * @return   bool     True on success, false on failure.
     */
    public function process_product_categories($product_id, $browse_nodes) {
        if (!$this->settings['auto_create_categories'] || empty($browse_nodes)) {
            return false;
        }

        try {
            // Find the most relevant browse node path
            $primary_path = $this->find_primary_category_path($browse_nodes);
            
            if (empty($primary_path)) {
                $this->logger->log("No suitable category path found for product {$product_id}", 'warning');
                return false;
            }

            // Create/get category hierarchy
            $category_ids = $this->create_category_hierarchy($primary_path);
            
            if (empty($category_ids)) {
                $this->logger->log("Failed to create category hierarchy for product {$product_id}", 'error');
                return false;
            }

            // Assign categories to product
            $result = $this->assign_categories_to_product($product_id, $category_ids);

            // Store browse node information in product meta
            $this->store_browse_node_meta($product_id, $primary_path);

            $this->logger->log(sprintf(
                'Categories processed for product %d. Assigned %d categories.',
                $product_id,
                count($category_ids)
            ), 'info');

            return $result;

        } catch (Exception $e) {
            $this->logger->log("Error processing categories for product {$product_id}: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Update product categories.
     *
     * @since    1.0.0
     * @param    int      $product_id     Product ID.
     * @param    array    $browse_nodes   Amazon BrowseNode data.
     * @return   bool     True if categories were updated.
     */
    public function update_product_categories($product_id, $browse_nodes) {
        if (!$this->settings['auto_create_categories']) {
            return false;
        }

        // Get current categories
        $current_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
        $current_amazon_categories = $this->get_amazon_categories_for_product($product_id);

        // Process new categories
        $updated = $this->process_product_categories($product_id, $browse_nodes);

        if ($updated) {
            // Get new categories
            $new_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
            
            // Check if categories actually changed
            $changed = (count(array_diff($current_amazon_categories, $new_categories)) > 0) ||
                      (count(array_diff($new_categories, $current_amazon_categories)) > 0);

            return $changed;
        }

        return false;
    }

    /**
     * Find the primary category path from browse nodes.
     *
     * @since    1.0.0
     * @param    array    $browse_nodes    Amazon BrowseNode data.
     * @return   array    Primary category path.
     */
    private function find_primary_category_path($browse_nodes) {
        $paths = array();

        foreach ($browse_nodes as $node) {
            if ($this->is_browse_node_excluded($node)) {
                continue;
            }

            $path = $this->extract_category_path($node);
            if (!empty($path)) {
                $paths[] = $path;
            }
        }

        if (empty($paths)) {
            return array();
        }

        // Select the most specific path (usually the longest within depth limits)
        return $this->select_best_category_path($paths);
    }

    /**
     * Extract category path from a browse node.
     *
     * @since    1.0.0
     * @param    array    $node    Browse node data.
     * @return   array    Category path.
     */
    private function extract_category_path($node) {
        $path = array();

        // Start from the current node and traverse up
        $current_node = $node;
        $depth = 0;

        while ($current_node && $depth < 10) { // Prevent infinite loops
            if (isset($current_node['DisplayName'])) {
                array_unshift($path, array(
                    'id' => isset($current_node['Id']) ? $current_node['Id'] : '',
                    'name' => sanitize_text_field($current_node['DisplayName']),
                    'sales_rank' => isset($current_node['SalesRank']) ? $current_node['SalesRank'] : null
                ));
            }

            // Move to parent node
            $current_node = isset($current_node['Ancestor']) ? $current_node['Ancestor'] : null;
            $depth++;
        }

        // Apply depth filters
        if (count($path) < $this->settings['min_depth'] || count($path) > $this->settings['max_depth']) {
            return array();
        }

        return $path;
    }

    /**
     * Select the best category path from multiple options.
     *
     * @since    1.0.0
     * @param    array    $paths    Array of category paths.
     * @return   array    Best category path.
     */
    private function select_best_category_path($paths) {
        if (count($paths) === 1) {
            return $paths[0];
        }

        // Score paths based on various criteria
        $scored_paths = array();

        foreach ($paths as $path) {
            $score = 0;
            
            // Prefer paths with optimal depth
            $optimal_depth = min($this->settings['max_depth'], 3);
            if (count($path) === $optimal_depth) {
                $score += 10;
            } else {
                $score += max(0, 10 - abs(count($path) - $optimal_depth));
            }

            // Prefer paths with sales rank information
            foreach ($path as $category) {
                if (!empty($category['sales_rank'])) {
                    $score += 2;
                }
            }

            // Prefer shorter category names (often more general)
            $avg_name_length = array_sum(array_map('strlen', array_column($path, 'name'))) / count($path);
            $score += max(0, 5 - ($avg_name_length / 10));

            $scored_paths[] = array('path' => $path, 'score' => $score);
        }

        // Sort by score and return the best path
        usort($scored_paths, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $scored_paths[0]['path'];
    }

    /**
     * Create category hierarchy from path.
     *
     * @since    1.0.0
     * @param    array    $path    Category path.
     * @return   array    Array of category IDs.
     */
    private function create_category_hierarchy($path) {
        $category_ids = array();
        $parent_id = 0;

        foreach ($path as $index => $category_data) {
            $category_name = $category_data['name'];
            $browse_node_id = $category_data['id'];

            // Check cache first
            $cache_key = $parent_id . '_' . sanitize_title($category_name);
            if (isset($this->category_cache[$cache_key])) {
                $category_id = $this->category_cache[$cache_key];
            } else {
                // Check for existing category
                $category_id = $this->find_existing_category($category_name, $parent_id);
                
                if (!$category_id) {
                    // Create new category
                    $category_id = $this->create_category($category_name, $parent_id, $browse_node_id);
                }

                // Cache the result
                $this->category_cache[$cache_key] = $category_id;
            }

            if ($category_id) {
                $category_ids[] = $category_id;
                $parent_id = $category_id;
            } else {
                $this->logger->log("Failed to create/find category: {$category_name}", 'error');
                break;
            }
        }

        return $category_ids;
    }

    /**
     * Find existing category by name and parent.
     *
     * @since    1.0.0
     * @param    string    $name        Category name.
     * @param    int       $parent_id   Parent category ID.
     * @return   int|null  Category ID or null if not found.
     */
    private function find_existing_category($name, $parent_id = 0) {
        // Check for exact match first
        $term = get_term_by('name', $name, 'product_cat');
        if ($term && $term->parent == $parent_id) {
            return $term->term_id;
        }

        // Check for similar names if merge option is enabled
        if ($this->settings['merge_similar_categories']) {
            $similar_term = $this->find_similar_category($name, $parent_id);
            if ($similar_term) {
                return $similar_term->term_id;
            }
        }

        // Check custom category mapping
        if (!empty($this->settings['category_mapping'])) {
            foreach ($this->settings['category_mapping'] as $amazon_name => $woo_name) {
                if (strcasecmp($amazon_name, $name) === 0) {
                    $mapped_term = get_term_by('name', $woo_name, 'product_cat');
                    if ($mapped_term && $mapped_term->parent == $parent_id) {
                        return $mapped_term->term_id;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Find similar category names.
     *
     * @since    1.0.0
     * @param    string    $name        Category name.
     * @param    int       $parent_id   Parent category ID.
     * @return   object|null    Similar term or null.
     */
    private function find_similar_category($name, $parent_id = 0) {
        $args = array(
            'taxonomy' => 'product_cat',
            'parent' => $parent_id,
            'hide_empty' => false,
            'search' => $name
        );

        $terms = get_terms($args);

        foreach ($terms as $term) {
            // Check for high similarity
            $similarity = 0;
            similar_text(strtolower($name), strtolower($term->name), $similarity);
            
            if ($similarity > 85) { // 85% similarity threshold
                return $term;
            }

            // Check for common patterns
            if ($this->are_categories_similar($name, $term->name)) {
                return $term;
            }
        }

        return null;
    }

    /**
     * Check if two category names are similar enough to merge.
     *
     * @since    1.0.0
     * @param    string    $name1    First category name.
     * @param    string    $name2    Second category name.
     * @return   bool      True if similar.
     */
    private function are_categories_similar($name1, $name2) {
        $name1_clean = strtolower(trim($name1));
        $name2_clean = strtolower(trim($name2));

        // Remove common words and check
        $common_words = array('and', 'or', 'the', 'for', 'with', 'in', 'on', 'at', 'by', '&');
        
        foreach ($common_words as $word) {
            $name1_clean = str_replace(" {$word} ", ' ', $name1_clean);
            $name2_clean = str_replace(" {$word} ", ' ', $name2_clean);
        }

        // Check if one name contains the other
        if (strpos($name1_clean, $name2_clean) !== false || strpos($name2_clean, $name1_clean) !== false) {
            return true;
        }

        // Check for plural/singular variations
        if (rtrim($name1_clean, 's') === rtrim($name2_clean, 's')) {
            return true;
        }

        return false;
    }

    /**
     * Create a new category.
     *
     * @since    1.0.0
     * @param    string    $name            Category name.
     * @param    int       $parent_id       Parent category ID.
     * @param    string    $browse_node_id  Amazon browse node ID.
     * @return   int|null  Category ID or null on failure.
     */
    private function create_category($name, $parent_id = 0, $browse_node_id = '') {
        // Apply category prefix if configured
        if (!empty($this->settings['category_prefix']) && $parent_id === 0) {
            $display_name = $this->settings['category_prefix'] . ' - ' . $name;
        } else {
            $display_name = $name;
        }

        $args = array(
            'description' => sprintf($this->settings['category_description_template'], $name),
            'parent' => $parent_id,
            'slug' => ''
        );

        $result = wp_insert_term($display_name, 'product_cat', $args);

        if (is_wp_error($result)) {
            $this->logger->log("Failed to create category '{$name}': " . $result->get_error_message(), 'error');
            return null;
        }

        $category_id = $result['term_id'];

        // Store Amazon-specific metadata
        if (!empty($browse_node_id)) {
            update_term_meta($category_id, '_amazon_browse_node_id', $browse_node_id);
        }
        
        update_term_meta($category_id, '_amazon_category', true);
        update_term_meta($category_id, '_amazon_created_date', current_time('mysql'));

        $this->logger->log("Created category '{$display_name}' with ID {$category_id}", 'info');

        return $category_id;
    }

    /**
     * Assign categories to product.
     *
     * @since    1.0.0
     * @param    int      $product_id     Product ID.
     * @param    array    $category_ids   Array of category IDs.
     * @return   bool     True on success.
     */
    private function assign_categories_to_product($product_id, $category_ids) {
        if (empty($category_ids)) {
            return false;
        }

        // Get existing categories
        $existing_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
        $existing_amazon_categories = $this->get_amazon_categories_for_product($product_id);

        // Determine which categories to keep (non-Amazon categories)
        $categories_to_keep = array_diff($existing_categories, $existing_amazon_categories);

        // Merge with new Amazon categories
        $final_categories = array_merge($categories_to_keep, $category_ids);
        $final_categories = array_unique($final_categories);

        // Set categories
        $result = wp_set_post_terms($product_id, $final_categories, 'product_cat');

        if (is_wp_error($result)) {
            $this->logger->log("Failed to assign categories to product {$product_id}: " . $result->get_error_message(), 'error');
            return false;
        }

        // Store Amazon category IDs in product meta for future reference
        update_post_meta($product_id, '_amazon_category_ids', $category_ids);

        return true;
    }

    /**
     * Get Amazon categories for a product.
     *
     * @since    1.0.0
     * @param    int    $product_id    Product ID.
     * @return   array  Array of Amazon category IDs.
     */
    private function get_amazon_categories_for_product($product_id) {
        $amazon_category_ids = get_post_meta($product_id, '_amazon_category_ids', true);
        return is_array($amazon_category_ids) ? $amazon_category_ids : array();
    }

    /**
     * Store browse node metadata in product.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $path          Category path.
     */
    private function store_browse_node_meta($product_id, $path) {
        if (empty($path)) {
            return;
        }

        // Store the most specific browse node (last in path)
        $leaf_node = end($path);
        if (!empty($leaf_node['id'])) {
            update_post_meta($product_id, '_amazon_browse_node_id', $leaf_node['id']);
        }
        update_post_meta($product_id, '_amazon_browse_node_name', $leaf_node['name']);

        // Store the full path
        update_post_meta($product_id, '_amazon_category_path', $path);
    }

    /**
     * Check if browse node is excluded.
     *
     * @since    1.0.0
     * @param    array    $node    Browse node data.
     * @return   bool     True if excluded.
     */
    private function is_browse_node_excluded($node) {
        if (empty($this->settings['excluded_browse_nodes'])) {
            return false;
        }

        $node_id = isset($node['Id']) ? $node['Id'] : '';
        $node_name = isset($node['DisplayName']) ? $node['DisplayName'] : '';

        return in_array($node_id, $this->settings['excluded_browse_nodes']) ||
               in_array($node_name, $this->settings['excluded_browse_nodes']);
    }

    /**
     * Get category statistics.
     *
     * @since    1.0.0
     * @return   array    Category statistics.
     */
    public function get_category_statistics() {
        $stats = array();

        // Get total Amazon categories
        $amazon_categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'meta_query' => array(
                array(
                    'key' => '_amazon_category',
                    'value' => true,
                    'compare' => '='
                )
            )
        ));

        $stats['total_amazon_categories'] = count($amazon_categories);

        // Get categories by depth
        $depth_counts = array();
        foreach ($amazon_categories as $category) {
            $depth = $this->get_category_depth($category->term_id);
            $depth_counts[$depth] = isset($depth_counts[$depth]) ? $depth_counts[$depth] + 1 : 1;
        }

        $stats['categories_by_depth'] = $depth_counts;

        // Get categories with products
        $categories_with_products = 0;
        foreach ($amazon_categories as $category) {
            if ($category->count > 0) {
                $categories_with_products++;
            }
        }

        $stats['categories_with_products'] = $categories_with_products;
        $stats['empty_categories'] = $stats['total_amazon_categories'] - $categories_with_products;

        return $stats;
    }

    /**
     * Get category depth.
     *
     * @since    1.0.0
     * @param    int    $category_id    Category ID.
     * @return   int    Category depth.
     */
    private function get_category_depth($category_id) {
        $depth = 0;
        $current_id = $category_id;

        while ($current_id > 0) {
            $category = get_term($current_id, 'product_cat');
            if (!$category || is_wp_error($category)) {
                break;
            }
            $current_id = $category->parent;
            $depth++;
        }

        return $depth;
    }

    /**
     * Clean up empty Amazon categories.
     *
     * @since    1.0.0
     * @param    bool    $dry_run    Whether to perform a dry run.
     * @return   array   Cleanup results.
     */
    public function cleanup_empty_categories($dry_run = true) {
        $results = array('deleted' => 0, 'categories' => array());

        $empty_categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'count' => 0,
            'meta_query' => array(
                array(
                    'key' => '_amazon_category',
                    'value' => true,
                    'compare' => '='
                )
            )
        ));

        foreach ($empty_categories as $category) {
            $results['categories'][] = array(
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug
            );

            if (!$dry_run) {
                $deleted = wp_delete_term($category->term_id, 'product_cat');
                if (!is_wp_error($deleted)) {
                    $results['deleted']++;
                }
            }
        }

        return $results;
    }

    /**
     * Export category mapping.
     *
     * @since    1.0.0
     * @return   array    Category mapping data.
     */
    public function export_category_mapping() {
        $amazon_categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'meta_query' => array(
                array(
                    'key' => '_amazon_category',
                    'value' => true,
                    'compare' => '='
                )
            )
        ));

        $mapping = array();

        foreach ($amazon_categories as $category) {
            $browse_node_id = get_term_meta($category->term_id, '_amazon_browse_node_id', true);
            $mapping[] = array(
                'woo_category_id' => $category->term_id,
                'woo_category_name' => $category->name,
                'woo_category_slug' => $category->slug,
                'amazon_browse_node_id' => $browse_node_id,
                'parent_id' => $category->parent,
                'product_count' => $category->count
            );
        }

        return $mapping;
    }

    /**
     * Rebuild category hierarchy.
     *
     * @since    1.0.0
     * @param    bool    $dry_run    Whether to perform a dry run.
     * @return   array   Rebuild results.
     */
    public function rebuild_category_hierarchy($dry_run = true) {
        $results = array('processed' => 0, 'updated' => 0, 'errors' => array());

        // Get all products with Amazon data
        $products = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_amazon_asin',
                    'value' => '',
                    'compare' => '!='
                )
            ),
            'fields' => 'ids'
        ));

        foreach ($products as $product_id) {
            $results['processed']++;

            $category_path = get_post_meta($product_id, '_amazon_category_path', true);
            if (empty($category_path) || !is_array($category_path)) {
                continue;
            }

            if (!$dry_run) {
                $updated = $this->process_product_categories($product_id, array($category_path));
                if ($updated) {
                    $results['updated']++;
                }
            } else {
                $results['updated']++; // For dry run, assume it would be updated
            }
        }

        return $results;
    }
}