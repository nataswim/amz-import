<?php

/**
 * The variation handling functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/import
 */

/**
 * The variation handling functionality of the plugin.
 *
 * Handles creation and management of variable products and their variations.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/import
 * @author     Your Name <email@example.com>
 */
class Amazon_Product_Importer_Variation_Handler {

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
     * The price updater instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Price_Updater    $price_updater    The price updater instance.
     */
    private $price_updater;

    /**
     * The image handler instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Image_Handler    $image_handler    The image handler instance.
     */
    private $image_handler;

    /**
     * The product meta handler instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Product_Meta    $product_meta    The product meta handler instance.
     */
    private $product_meta;

    /**
     * Variation handling settings.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $settings    Variation handling settings.
     */
    private $settings;

    /**
     * Attribute taxonomy cache.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $attribute_cache    Attribute taxonomy cache.
     */
    private $attribute_cache = array();

    /**
     * Amazon to WooCommerce attribute mapping.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $attribute_mapping    Attribute mapping configuration.
     */
    private $attribute_mapping;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->product_mapper = new Amazon_Product_Importer_Product_Mapper();
        $this->price_updater = new Amazon_Product_Importer_Price_Updater();
        $this->image_handler = new Amazon_Product_Importer_Image_Handler();
        $this->product_meta = new Amazon_Product_Importer_Product_Meta();
        
        $this->load_settings();
        $this->load_attribute_mapping();
    }

    /**
     * Load variation handling settings.
     *
     * @since    1.0.0
     */
    private function load_settings() {
        $this->settings = array(
            'max_variations' => get_option('ams_max_variations_per_product', 50),
            'auto_create_attributes' => get_option('ams_auto_create_variation_attributes', true),
            'variation_image_handling' => get_option('ams_variation_image_handling', 'individual'), // individual, inherit, none
            'variation_price_inheritance' => get_option('ams_variation_price_inheritance', false),
            'variation_stock_management' => get_option('ams_variation_stock_management', 'individual'),
            'attribute_visibility' => get_option('ams_variation_attribute_visibility', true),
            'attribute_for_variations' => get_option('ams_attributes_for_variations', true),
            'variation_description_source' => get_option('ams_variation_description_source', 'parent'), // parent, individual, none
            'sync_variation_status' => get_option('ams_sync_variation_status', true),
            'remove_unused_variations' => get_option('ams_remove_unused_variations', false),
            'variation_title_format' => get_option('ams_variation_title_format', '%parent_title% - %attributes%'),
            'attribute_term_limit' => get_option('ams_attribute_term_limit', 100)
        );
    }

    /**
     * Load attribute mapping configuration.
     *
     * @since    1.0.0
     */
    private function load_attribute_mapping() {
        $this->attribute_mapping = get_option('ams_variation_attribute_mapping', array(
            'Color' => array(
                'woo_attribute' => 'pa_color',
                'type' => 'select',
                'has_archives' => true,
                'orderby' => 'menu_order'
            ),
            'Size' => array(
                'woo_attribute' => 'pa_size',
                'type' => 'select',
                'has_archives' => true,
                'orderby' => 'menu_order'
            ),
            'Style' => array(
                'woo_attribute' => 'pa_style',
                'type' => 'select',
                'has_archives' => false,
                'orderby' => 'name'
            ),
            'Material' => array(
                'woo_attribute' => 'pa_material',
                'type' => 'select',
                'has_archives' => false,
                'orderby' => 'name'
            ),
            'Pattern' => array(
                'woo_attribute' => 'pa_pattern',
                'type' => 'select',
                'has_archives' => false,
                'orderby' => 'name'
            ),
            'Flavor' => array(
                'woo_attribute' => 'pa_flavor',
                'type' => 'select',
                'has_archives' => false,
                'orderby' => 'name'
            )
        ));
    }

    /**
     * Create variable product from Amazon variation data.
     *
     * @since    1.0.0
     * @param    array    $parent_data    Parent product data from Amazon.
     * @param    array    $variations     Variation items from Amazon.
     * @return   int|null Parent product ID or null on failure.
     */
    public function create_variable_product($parent_data, $variations) {
        try {
            // Create the parent variable product
            $parent_id = $this->create_variable_parent($parent_data);
            
            if (!$parent_id) {
                throw new Exception('Failed to create variable parent product');
            }

            // Extract and create variation attributes
            $attributes = $this->extract_variation_attributes($variations);
            
            if (empty($attributes)) {
                throw new Exception('No variation attributes found');
            }

            // Set up product attributes
            $this->setup_product_attributes($parent_id, $attributes);

            // Create individual variations
            $variation_results = $this->create_product_variations($parent_id, $variations, $attributes);

            // Update parent product with variation data
            $this->update_parent_product_data($parent_id, $variation_results);

            // Sync variation data
            $this->sync_variable_product($parent_id);

            $this->logger->log(sprintf(
                'Created variable product %d with %d variations for parent ASIN %s',
                $parent_id,
                count($variation_results['created']),
                $parent_data['ASIN']
            ), 'info');

            return $parent_id;

        } catch (Exception $e) {
            $this->logger->log("Error creating variable product: " . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Update existing variable product with new variation data.
     *
     * @since    1.0.0
     * @param    int      $parent_id     Parent product ID.
     * @param    array    $parent_data   Parent product data from Amazon.
     * @param    array    $variations    Variation items from Amazon.
     * @return   bool     True on success.
     */
    public function update_variable_product($parent_id, $parent_data, $variations) {
        try {
            $parent_product = wc_get_product($parent_id);
            
            if (!$parent_product || !$parent_product->is_type('variable')) {
                throw new Exception("Product {$parent_id} is not a variable product");
            }

            // Get existing variations
            $existing_variations = $parent_product->get_children();
            $existing_asins = $this->get_variation_asins($existing_variations);

            // Extract new variation attributes
            $new_attributes = $this->extract_variation_attributes($variations);

            // Update product attributes if needed
            $this->update_product_attributes($parent_id, $new_attributes);

            // Process each variation
            $processed_asins = array();
            $results = array('created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array());

            foreach ($variations as $variation_data) {
                if (!isset($variation_data['ASIN'])) {
                    continue;
                }

                $variation_asin = $variation_data['ASIN'];
                $processed_asins[] = $variation_asin;

                if (in_array($variation_asin, $existing_asins)) {
                    // Update existing variation
                    $variation_id = $this->product_meta->get_product_by_asin($variation_asin);
                    if ($variation_id) {
                        $updated = $this->update_single_variation($variation_id, $variation_data, $new_attributes);
                        if ($updated) {
                            $results['updated']++;
                        } else {
                            $results['skipped']++;
                        }
                    }
                } else {
                    // Create new variation
                    $variation_id = $this->create_single_variation($parent_id, $variation_data, $new_attributes);
                    if ($variation_id) {
                        $results['created']++;
                    } else {
                        $results['errors'][] = "Failed to create variation for ASIN {$variation_asin}";
                    }
                }
            }

            // Remove unused variations if enabled
            if ($this->settings['remove_unused_variations']) {
                $removed = $this->remove_unused_variations($parent_id, $processed_asins);
                $results['removed'] = $removed;
            }

            // Sync variable product
            $this->sync_variable_product($parent_id);

            $this->logger->log(sprintf(
                'Updated variable product %d: Created %d, Updated %d, Removed %d variations',
                $parent_id,
                $results['created'],
                $results['updated'],
                isset($results['removed']) ? $results['removed'] : 0
            ), 'info');

            return true;

        } catch (Exception $e) {
            $this->logger->log("Error updating variable product {$parent_id}: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Create variable parent product.
     *
     * @since    1.0.0
     * @param    array    $parent_data    Parent product data.
     * @return   int|null Parent product ID.
     */
    private function create_variable_parent($parent_data) {
        $product_data = $this->product_mapper->map_amazon_to_woocommerce($parent_data);
        
        $parent_product = new WC_Product_Variable();
        $parent_product->set_name($product_data['name']);
        $parent_product->set_description($product_data['description']);
        $parent_product->set_short_description($product_data['short_description']);
        $parent_product->set_sku($product_data['sku']);
        $parent_product->set_status('publish');

        // Set stock management
        $parent_product->set_manage_stock(false);
        $parent_product->set_stock_status('instock');

        $parent_id = $parent_product->save();

        if ($parent_id) {
            // Store Amazon metadata
            $this->store_parent_metadata($parent_id, $parent_data);
        }

        return $parent_id;
    }

    /**
     * Extract variation attributes from variation data.
     *
     * @since    1.0.0
     * @param    array    $variations    Variation data array.
     * @return   array    Extracted attributes.
     */
    private function extract_variation_attributes($variations) {
        $attributes = array();

        foreach ($variations as $variation_data) {
            if (!isset($variation_data['VariationAttributes'])) {
                continue;
            }

            foreach ($variation_data['VariationAttributes'] as $attr) {
                if (!isset($attr['Name']) || !isset($attr['Value'])) {
                    continue;
                }

                $attr_name = $attr['Name'];
                $attr_value = $attr['Value'];

                // Map to WooCommerce attribute name
                $woo_attr_name = $this->map_amazon_attribute_to_woo($attr_name);

                if (!isset($attributes[$woo_attr_name])) {
                    $attributes[$woo_attr_name] = array(
                        'name' => $woo_attr_name,
                        'label' => $this->get_attribute_label($attr_name),
                        'values' => array(),
                        'visible' => $this->settings['attribute_visibility'],
                        'variation' => true,
                        'amazon_name' => $attr_name
                    );
                }

                if (!in_array($attr_value, $attributes[$woo_attr_name]['values'])) {
                    $attributes[$woo_attr_name]['values'][] = $attr_value;
                }
            }
        }

        // Limit attribute terms if configured
        foreach ($attributes as $attr_name => $attr_data) {
            if (count($attr_data['values']) > $this->settings['attribute_term_limit']) {
                $attributes[$attr_name]['values'] = array_slice(
                    $attr_data['values'], 
                    0, 
                    $this->settings['attribute_term_limit']
                );
                
                $this->logger->log(
                    "Attribute {$attr_name} has too many terms, limited to {$this->settings['attribute_term_limit']}", 
                    'warning'
                );
            }
        }

        return $attributes;
    }

    /**
     * Setup product attributes for variable product.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $attributes    Attributes data.
     */
    private function setup_product_attributes($product_id, $attributes) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $product_attributes = array();

        foreach ($attributes as $attr_name => $attr_data) {
            // Create/get global attribute
            $taxonomy = $this->create_or_get_attribute_taxonomy($attr_name, $attr_data);
            
            if (!$taxonomy) {
                continue;
            }

            // Create/get attribute terms
            $term_ids = $this->create_attribute_terms($taxonomy, $attr_data['values']);

            // Create product attribute
            $attribute = new WC_Product_Attribute();
            $attribute->set_id(wc_attribute_taxonomy_id_by_name($taxonomy));
            $attribute->set_name($taxonomy);
            $attribute->set_options($term_ids);
            $attribute->set_visible($attr_data['visible']);
            $attribute->set_variation($attr_data['variation']);

            $product_attributes[] = $attribute;
        }

        $product->set_attributes($product_attributes);
        $product->save();
    }

    /**
     * Update product attributes for existing variable product.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $new_attributes New attributes data.
     * @return   bool     True if updated.
     */
    private function update_product_attributes($product_id, $new_attributes) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }

        $existing_attributes = $product->get_attributes();
        $updated = false;

        foreach ($new_attributes as $attr_name => $attr_data) {
            $taxonomy = $this->get_attribute_taxonomy_name($attr_name);
            
            if (isset($existing_attributes[$taxonomy])) {
                // Update existing attribute
                $existing_terms = $existing_attributes[$taxonomy]->get_options();
                $new_term_ids = $this->create_attribute_terms($taxonomy, $attr_data['values']);
                
                $merged_terms = array_unique(array_merge($existing_terms, $new_term_ids));
                
                if (count($merged_terms) !== count($existing_terms)) {
                    $existing_attributes[$taxonomy]->set_options($merged_terms);
                    $updated = true;
                }
            } else {
                // Add new attribute
                $this->setup_product_attributes($product_id, array($attr_name => $attr_data));
                $updated = true;
            }
        }

        if ($updated) {
            $product->set_attributes($existing_attributes);
            $product->save();
        }

        return $updated;
    }

    /**
     * Create individual product variations.
     *
     * @since    1.0.0
     * @param    int      $parent_id     Parent product ID.
     * @param    array    $variations    Variation data.
     * @param    array    $attributes    Attribute data.
     * @return   array    Creation results.
     */
    private function create_product_variations($parent_id, $variations, $attributes) {
        $results = array('created' => array(), 'failed' => array());

        $variation_count = 0;
        foreach ($variations as $variation_data) {
            if ($variation_count >= $this->settings['max_variations']) {
                $this->logger->log("Maximum variations limit reached for product {$parent_id}", 'warning');
                break;
            }

            $variation_id = $this->create_single_variation($parent_id, $variation_data, $attributes);
            
            if ($variation_id) {
                $results['created'][] = $variation_id;
                $variation_count++;
            } else {
                $results['failed'][] = $variation_data['ASIN'];
            }
        }

        return $results;
    }

    /**
     * Create a single product variation.
     *
     * @since    1.0.0
     * @param    int      $parent_id        Parent product ID.
     * @param    array    $variation_data   Variation data from Amazon.
     * @param    array    $attributes       Available attributes.
     * @return   int|null Variation ID or null on failure.
     */
    private function create_single_variation($parent_id, $variation_data, $attributes) {
        try {
            if (!isset($variation_data['ASIN'])) {
                throw new Exception('Variation ASIN not provided');
            }

            $variation = new WC_Product_Variation();
            $variation->set_parent_id($parent_id);

            // Map variation attributes
            $variation_attributes = $this->map_variation_attributes($variation_data, $attributes);
            $variation->set_attributes($variation_attributes);

            // Set basic data
            $mapped_data = $this->product_mapper->map_variation_data($variation_data);
            
            if (!empty($mapped_data['regular_price'])) {
                $variation->set_regular_price($mapped_data['regular_price']);
            }
            
            if (!empty($mapped_data['sale_price'])) {
                $variation->set_sale_price($mapped_data['sale_price']);
            }

            // Set stock status
            $variation->set_stock_status($mapped_data['stock_status']);
            $variation->set_manage_stock(false);

            // Set variation description if configured
            if ($this->settings['variation_description_source'] === 'individual') {
                $description = $this->extract_variation_description($variation_data);
                if (!empty($description)) {
                    $variation->set_description($description);
                }
            }

            // Generate variation title
            $variation_title = $this->generate_variation_title($parent_id, $variation_attributes);
            if (!empty($variation_title)) {
                $variation->set_name($variation_title);
            }

            // Save the variation
            $variation_id = $variation->save();

            if ($variation_id) {
                // Store Amazon metadata
                $this->store_variation_metadata($variation_id, $variation_data, $parent_id);

                // Handle variation images
                if ($this->settings['variation_image_handling'] === 'individual') {
                    $this->handle_variation_images($variation_id, $variation_data);
                }

                $this->logger->log("Created variation {$variation_id} for ASIN {$variation_data['ASIN']}", 'info');
            }

            return $variation_id;

        } catch (Exception $e) {
            $this->logger->log("Error creating variation: " . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Update a single product variation.
     *
     * @since    1.0.0
     * @param    int      $variation_id     Variation ID.
     * @param    array    $variation_data   New variation data.
     * @param    array    $attributes       Available attributes.
     * @return   bool     True if updated.
     */
    private function update_single_variation($variation_id, $variation_data, $attributes) {
        try {
            $variation = wc_get_product($variation_id);
            
            if (!$variation || !$variation->is_type('variation')) {
                return false;
            }

            $updated = false;

            // Update prices
            $price_updated = $this->price_updater->update_product_prices($variation_id, $variation_data, true);
            if ($price_updated) {
                $updated = true;
            }

            // Update attributes if they've changed
            $new_attributes = $this->map_variation_attributes($variation_data, $attributes);
            $current_attributes = $variation->get_attributes();
            
            if ($this->have_attributes_changed($current_attributes, $new_attributes)) {
                $variation->set_attributes($new_attributes);
                $updated = true;
            }

            // Update stock status
            $mapped_data = $this->product_mapper->map_variation_data($variation_data);
            if ($variation->get_stock_status() !== $mapped_data['stock_status']) {
                $variation->set_stock_status($mapped_data['stock_status']);
                $updated = true;
            }

            // Update images if configured
            if ($this->settings['variation_image_handling'] === 'individual') {
                $images_updated = $this->handle_variation_images($variation_id, $variation_data);
                if ($images_updated) {
                    $updated = true;
                }
            }

            if ($updated) {
                $variation->save();
                
                // Update metadata
                $this->update_variation_metadata($variation_id, $variation_data);
            }

            return $updated;

        } catch (Exception $e) {
            $this->logger->log("Error updating variation {$variation_id}: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Map variation attributes from Amazon data.
     *
     * @since    1.0.0
     * @param    array    $variation_data    Variation data.
     * @param    array    $attributes        Available attributes.
     * @return   array    Mapped attributes.
     */
    private function map_variation_attributes($variation_data, $attributes) {
        $mapped_attributes = array();

        if (!isset($variation_data['VariationAttributes'])) {
            return $mapped_attributes;
        }

        foreach ($variation_data['VariationAttributes'] as $attr) {
            if (!isset($attr['Name']) || !isset($attr['Value'])) {
                continue;
            }

            $amazon_attr_name = $attr['Name'];
            $attr_value = $attr['Value'];

            // Find the corresponding WooCommerce attribute
            $woo_attr_name = $this->map_amazon_attribute_to_woo($amazon_attr_name);
            $taxonomy = $this->get_attribute_taxonomy_name($woo_attr_name);

            if (isset($attributes[$woo_attr_name])) {
                // Get term slug for the value
                $term_slug = $this->get_attribute_term_slug($taxonomy, $attr_value);
                if ($term_slug) {
                    $mapped_attributes[$taxonomy] = $term_slug;
                }
            }
        }

        return $mapped_attributes;
    }

    /**
     * Create or get attribute taxonomy.
     *
     * @since    1.0.0
     * @param    string    $attr_name    Attribute name.
     * @param    array     $attr_data    Attribute data.
     * @return   string|null Taxonomy name or null on failure.
     */
    private function create_or_get_attribute_taxonomy($attr_name, $attr_data) {
        if (isset($this->attribute_cache[$attr_name])) {
            return $this->attribute_cache[$attr_name];
        }

        $taxonomy = $this->get_attribute_taxonomy_name($attr_name);

        // Check if taxonomy already exists
        if (taxonomy_exists($taxonomy)) {
            $this->attribute_cache[$attr_name] = $taxonomy;
            return $taxonomy;
        }

        if (!$this->settings['auto_create_attributes']) {
            return null;
        }

        // Create new attribute taxonomy
        $attribute_data = array(
            'name' => $attr_data['label'],
            'slug' => $attr_name,
            'type' => 'select',
            'order_by' => 'menu_order',
            'has_archives' => false
        );

        // Apply custom configuration if available
        if (isset($this->attribute_mapping[$attr_data['amazon_name']])) {
            $custom_config = $this->attribute_mapping[$attr_data['amazon_name']];
            $attribute_data = array_merge($attribute_data, $custom_config);
        }

        $result = wc_create_attribute($attribute_data);

        if (is_wp_error($result)) {
            $this->logger->log("Failed to create attribute {$attr_name}: " . $result->get_error_message(), 'error');
            return null;
        }

        // Register the taxonomy
        register_taxonomy($taxonomy, array('product', 'product_variation'));

        $this->attribute_cache[$attr_name] = $taxonomy;
        
        $this->logger->log("Created attribute taxonomy: {$taxonomy}", 'info');
        
        return $taxonomy;
    }

    /**
     * Create attribute terms.
     *
     * @since    1.0.0
     * @param    string    $taxonomy    Taxonomy name.
     * @param    array     $values      Term values.
     * @return   array     Term IDs.
     */
    private function create_attribute_terms($taxonomy, $values) {
        $term_ids = array();

        foreach ($values as $value) {
            $term = get_term_by('name', $value, $taxonomy);
            
            if (!$term) {
                $result = wp_insert_term($value, $taxonomy);
                
                if (is_wp_error($result)) {
                    $this->logger->log("Failed to create term '{$value}' in {$taxonomy}: " . $result->get_error_message(), 'error');
                    continue;
                }
                
                $term_ids[] = $result['term_id'];
            } else {
                $term_ids[] = $term->term_id;
            }
        }

        return $term_ids;
    }

    /**
     * Get attribute taxonomy name from attribute name.
     *
     * @since    1.0.0
     * @param    string    $attr_name    Attribute name.
     * @return   string    Taxonomy name.
     */
    private function get_attribute_taxonomy_name($attr_name) {
        // Remove 'pa_' prefix if present and re-add it
        $clean_name = str_replace('pa_', '', $attr_name);
        return 'pa_' . sanitize_title($clean_name);
    }

    /**
     * Map Amazon attribute name to WooCommerce attribute.
     *
     * @since    1.0.0
     * @param    string    $amazon_attr_name    Amazon attribute name.
     * @return   string    WooCommerce attribute name.
     */
    private function map_amazon_attribute_to_woo($amazon_attr_name) {
        // Check custom mappings first
        foreach ($this->attribute_mapping as $amazon_name => $config) {
            if (strcasecmp($amazon_name, $amazon_attr_name) === 0) {
                return $config['woo_attribute'];
            }
        }

        // Default mapping
        return 'pa_' . sanitize_title(strtolower($amazon_attr_name));
    }

    /**
     * Get attribute label for display.
     *
     * @since    1.0.0
     * @param    string    $amazon_attr_name    Amazon attribute name.
     * @return   string    Attribute label.
     */
    private function get_attribute_label($amazon_attr_name) {
        // Check if we have a custom label
        if (isset($this->attribute_mapping[$amazon_attr_name]['label'])) {
            return $this->attribute_mapping[$amazon_attr_name]['label'];
        }

        // Generate label from name
        return ucwords(str_replace(array('_', '-'), ' ', $amazon_attr_name));
    }

    /**
     * Get attribute term slug.
     *
     * @since    1.0.0
     * @param    string    $taxonomy    Taxonomy name.
     * @param    string    $value       Term value.
     * @return   string|null Term slug or null if not found.
     */
    private function get_attribute_term_slug($taxonomy, $value) {
        $term = get_term_by('name', $value, $taxonomy);
        
        if (!$term) {
            // Try to find by slug
            $term = get_term_by('slug', sanitize_title($value), $taxonomy);
        }

        return $term ? $term->slug : null;
    }

    /**
     * Store parent product metadata.
     *
     * @since    1.0.0
     * @param    int      $parent_id      Parent product ID.
     * @param    array    $parent_data    Parent product data.
     */
    private function store_parent_metadata($parent_id, $parent_data) {
        $metadata = array(
            'asin' => $parent_data['ASIN'],
            'product_type' => 'variable',
            'import_date' => current_time('mysql'),
            'is_variation_parent' => true
        );

        // Store variation summary data
        if (isset($parent_data['VariationSummary'])) {
            $metadata['variation_count'] = $parent_data['VariationSummary']['VariationCount'];
            $metadata['variation_dimensions'] = $parent_data['VariationSummary']['VariationDimensions'];
        }

        $this->product_meta->set_multiple_meta($parent_id, $metadata);
    }

    /**
     * Store variation metadata.
     *
     * @since    1.0.0
     * @param    int      $variation_id     Variation ID.
     * @param    array    $variation_data   Variation data.
     * @param    int      $parent_id        Parent product ID.
     */
    private function store_variation_metadata($variation_id, $variation_data, $parent_id) {
        $metadata = array(
            'asin' => $variation_data['ASIN'],
            'parent_asin' => isset($variation_data['ParentASIN']) ? $variation_data['ParentASIN'] : '',
            'parent_product_id' => $parent_id,
            'product_type' => 'variation',
            'import_date' => current_time('mysql')
        );

        $this->product_meta->set_multiple_meta($variation_id, $metadata);
    }

    /**
     * Update variation metadata.
     *
     * @since    1.0.0
     * @param    int      $variation_id     Variation ID.
     * @param    array    $variation_data   Variation data.
     */
    private function update_variation_metadata($variation_id, $variation_data) {
        $metadata = array(
            'last_sync' => current_time('mysql'),
            'last_product_update' => current_time('mysql')
        );

        $this->product_meta->set_multiple_meta($variation_id, $metadata);
    }

    /**
     * Handle variation images.
     *
     * @since    1.0.0
     * @param    int      $variation_id     Variation ID.
     * @param    array    $variation_data   Variation data.
     * @return   bool     True if images were processed.
     */
    private function handle_variation_images($variation_id, $variation_data) {
        if (!isset($variation_data['Images'])) {
            return false;
        }

        return $this->image_handler->process_product_images($variation_id, $variation_data);
    }

    /**
     * Generate variation title.
     *
     * @since    1.0.0
     * @param    int      $parent_id           Parent product ID.
     * @param    array    $variation_attributes Variation attributes.
     * @return   string   Generated title.
     */
    private function generate_variation_title($parent_id, $variation_attributes) {
        $parent_product = wc_get_product($parent_id);
        if (!$parent_product) {
            return '';
        }

        $parent_title = $parent_product->get_name();
        $attribute_string = implode(' - ', $variation_attributes);

        $title = str_replace(
            array('%parent_title%', '%attributes%'),
            array($parent_title, $attribute_string),
            $this->settings['variation_title_format']
        );

        return $title;
    }

    /**
     * Extract variation description from data.
     *
     * @since    1.0.0
     * @param    array    $variation_data    Variation data.
     * @return   string   Variation description.
     */
    private function extract_variation_description($variation_data) {
        // Try to get specific variation description
        if (isset($variation_data['ItemInfo']['Features']['DisplayValues'])) {
            $features = $variation_data['ItemInfo']['Features']['DisplayValues'];
            return implode("\n", $features);
        }

        return '';
    }

    /**
     * Update parent product data after variation processing.
     *
     * @since    1.0.0
     * @param    int      $parent_id    Parent product ID.
     * @param    array    $results      Variation processing results.
     */
    private function update_parent_product_data($parent_id, $results) {
        $metadata = array(
            'variation_count' => count($results['created']),
            'last_variation_sync' => current_time('mysql')
        );

        $this->product_meta->set_multiple_meta($parent_id, $metadata);
    }

    /**
     * Sync variable product data.
     *
     * @since    1.0.0
     * @param    int    $parent_id    Parent product ID.
     */
    private function sync_variable_product($parent_id) {
        $parent_product = wc_get_product($parent_id);
        
        if (!$parent_product || !$parent_product->is_type('variable')) {
            return;
        }

        // Sync variations to update parent price range and stock status
        $parent_product->sync_variations();
        $parent_product->save();
    }

    /**
     * Get ASINs of existing variations.
     *
     * @since    1.0.0
     * @param    array    $variation_ids    Variation IDs.
     * @return   array    ASINs.
     */
    private function get_variation_asins($variation_ids) {
        $asins = array();

        foreach ($variation_ids as $variation_id) {
            $asin = $this->product_meta->get_meta($variation_id, 'asin');
            if (!empty($asin)) {
                $asins[] = $asin;
            }
        }

        return $asins;
    }

    /**
     * Check if attributes have changed.
     *
     * @since    1.0.0
     * @param    array    $current_attributes    Current attributes.
     * @param    array    $new_attributes        New attributes.
     * @return   bool     True if changed.
     */
    private function have_attributes_changed($current_attributes, $new_attributes) {
        if (count($current_attributes) !== count($new_attributes)) {
            return true;
        }

        foreach ($new_attributes as $key => $value) {
            if (!isset($current_attributes[$key]) || $current_attributes[$key] !== $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove unused variations.
     *
     * @since    1.0.0
     * @param    int      $parent_id         Parent product ID.
     * @param    array    $processed_asins   ASINs that were processed.
     * @return   int      Number of variations removed.
     */
    private function remove_unused_variations($parent_id, $processed_asins) {
        $parent_product = wc_get_product($parent_id);
        
        if (!$parent_product || !$parent_product->is_type('variable')) {
            return 0;
        }

        $existing_variations = $parent_product->get_children();
        $removed_count = 0;

        foreach ($existing_variations as $variation_id) {
            $variation_asin = $this->product_meta->get_meta($variation_id, 'asin');
            
            if (!empty($variation_asin) && !in_array($variation_asin, $processed_asins)) {
                wp_delete_post($variation_id, true);
                $removed_count++;
                
                $this->logger->log("Removed unused variation {$variation_id} (ASIN: {$variation_asin})", 'info');
            }
        }

        return $removed_count;
    }

    /**
     * Get variation statistics.
     *
     * @since    1.0.0
     * @return   array    Variation statistics.
     */
    public function get_variation_statistics() {
        global $wpdb;

        $stats = array();

        // Total variable products
        $variable_products = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'product'
             AND pm.meta_key = '_amazon_product_type'
             AND pm.meta_value = 'variable'"
        );
        $stats['variable_products'] = intval($variable_products);

        // Total variations
        $variations = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'product_variation'
             AND pm.meta_key = '_amazon_asin'"
        );
        $stats['total_variations'] = intval($variations);

        // Average variations per product
        if ($stats['variable_products'] > 0) {
            $stats['avg_variations_per_product'] = round($stats['total_variations'] / $stats['variable_products'], 2);
        } else {
            $stats['avg_variations_per_product'] = 0;
        }

        // Attribute usage
        $attributes = $wpdb->get_results(
            "SELECT attribute_name, COUNT(*) as usage_count
             FROM {$wpdb->prefix}woocommerce_attribute_taxonomies
             WHERE attribute_name LIKE 'pa_%'
             GROUP BY attribute_name
             ORDER BY usage_count DESC
             LIMIT 10"
        );

        $stats['top_attributes'] = array();
        foreach ($attributes as $attr) {
            $stats['top_attributes'][$attr->attribute_name] = intval($attr->usage_count);
        }

        return $stats;
    }

    /**
     * Clean up orphaned variations.
     *
     * @since    1.0.0
     * @param    bool    $dry_run    Whether to perform a dry run.
     * @return   array   Cleanup results.
     */
    public function cleanup_orphaned_variations($dry_run = true) {
        global $wpdb;

        $results = array('found' => 0, 'cleaned' => 0, 'variations' => array());

        // Find variations with non-existent or non-variable parents
        $orphaned_variations = $wpdb->get_results(
            "SELECT v.ID, v.post_parent, pm.meta_value as asin
             FROM {$wpdb->posts} v
             LEFT JOIN {$wpdb->posts} p ON v.post_parent = p.ID
             LEFT JOIN {$wpdb->postmeta} pm ON v.ID = pm.post_id AND pm.meta_key = '_amazon_asin'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_product_type'
             WHERE v.post_type = 'product_variation'
             AND (p.ID IS NULL OR pm2.meta_value != 'variable')"
        );

        foreach ($orphaned_variations as $variation) {
            $results['variations'][] = array(
                'id' => $variation->ID,
                'parent_id' => $variation->post_parent,
                'asin' => $variation->asin
            );
            $results['found']++;

            if (!$dry_run) {
                wp_delete_post($variation->ID, true);
                $results['cleaned']++;
            }
        }

        return $results;
    }

    /**
     * Export variation configuration.
     *
     * @since    1.0.0
     * @return   array    Variation configuration.
     */
    public function export_variation_config() {
        return array(
            'settings' => $this->settings,
            'attribute_mapping' => $this->attribute_mapping,
            'export_date' => current_time('mysql'),
            'version' => '1.0.0'
        );
    }

    /**
     * Import variation configuration.
     *
     * @since    1.0.0
     * @param    array    $config    Configuration to import.
     * @return   bool     True on success.
     */
    public function import_variation_config($config) {
        if (!isset($config['settings']) || !isset($config['attribute_mapping'])) {
            return false;
        }

        // Update settings
        foreach ($config['settings'] as $key => $value) {
            update_option("ams_{$key}", $value);
        }

        // Update attribute mappings
        update_option('ams_variation_attribute_mapping', $config['attribute_mapping']);

        // Reload configuration
        $this->load_settings();
        $this->load_attribute_mapping();

        return true;
    }
}