<?php

/**
 * The product mapping functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/import
 */

/**
 * The product mapping functionality of the plugin.
 *
 * Maps Amazon API data to WooCommerce product format.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/import
 * @author     Your Name <email@example.com>
 */
class Amazon_Product_Importer_Product_Mapper {

    /**
     * The logger instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Logger    $logger    The logger instance.
     */
    private $logger;

    /**
     * Mapping configuration settings.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $settings    Mapping configuration settings.
     */
    private $settings;

    /**
     * Custom field mappings.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $custom_mappings    Custom field mappings.
     */
    private $custom_mappings;

    /**
     * Attribute mappings for variations.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $attribute_mappings    Attribute mappings.
     */
    private $attribute_mappings;

    /**
     * Text processing rules.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $text_processing_rules    Text processing rules.
     */
    private $text_processing_rules;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->load_settings();
        $this->load_custom_mappings();
        $this->load_attribute_mappings();
        $this->setup_text_processing_rules();
    }

    /**
     * Load mapping settings.
     *
     * @since    1.0.0
     */
    private function load_settings() {
        $this->settings = array(
            'title_max_length' => get_option('ams_title_max_length', 100),
            'description_max_length' => get_option('ams_description_max_length', 5000),
            'short_description_max_length' => get_option('ams_short_description_max_length', 300),
            'remove_html_tags' => get_option('ams_remove_html_tags', true),
            'clean_description' => get_option('ams_clean_description', true),
            'use_features_as_short_desc' => get_option('ams_use_features_as_short_desc', true),
            'feature_list_format' => get_option('ams_feature_list_format', 'bullets'), // bullets, numbered, plain
            'include_brand_in_title' => get_option('ams_include_brand_in_title', false),
            'title_case_conversion' => get_option('ams_title_case_conversion', 'title'), // title, sentence, upper, lower, none
            'sku_format' => get_option('ams_sku_format', 'asin'), // asin, custom, original
            'sku_prefix' => get_option('ams_sku_prefix', 'AMZ-'),
            'weight_unit' => get_option('ams_weight_unit', 'kg'),
            'dimension_unit' => get_option('ams_dimension_unit', 'cm'),
            'auto_create_attributes' => get_option('ams_auto_create_attributes', true),
            'exclude_common_words' => get_option('ams_exclude_common_words', true),
            'default_weight' => get_option('ams_default_weight', 0.1),
            'map_technical_details' => get_option('ams_map_technical_details', true)
        );
    }

    /**
     * Load custom field mappings.
     *
     * @since    1.0.0
     */
    private function load_custom_mappings() {
        $this->custom_mappings = get_option('ams_custom_field_mappings', array(
            'title' => array(
                'source' => 'ItemInfo.Title.DisplayValue',
                'transform' => 'clean_title',
                'fallback' => ''
            ),
            'description' => array(
                'source' => 'ItemInfo.ProductInfo.ProductDescription',
                'transform' => 'clean_description',
                'fallback' => 'ItemInfo.Features.DisplayValues'
            ),
            'short_description' => array(
                'source' => 'ItemInfo.Features.DisplayValues',
                'transform' => 'features_to_list',
                'fallback' => ''
            ),
            'brand' => array(
                'source' => 'ItemInfo.ByLineInfo.Brand.DisplayValue',
                'transform' => 'clean_text',
                'fallback' => 'ItemInfo.ByLineInfo.Manufacturer.DisplayValue'
            ),
            'manufacturer' => array(
                'source' => 'ItemInfo.ByLineInfo.Manufacturer.DisplayValue',
                'transform' => 'clean_text',
                'fallback' => 'ItemInfo.ByLineInfo.Brand.DisplayValue'
            )
        ));
    }

    /**
     * Load attribute mappings for variations.
     *
     * @since    1.0.0
     */
    private function load_attribute_mappings() {
        $this->attribute_mappings = get_option('ams_attribute_mappings', array(
            'Color' => 'pa_color',
            'Size' => 'pa_size',
            'Style' => 'pa_style',
            'Material' => 'pa_material',
            'Pattern' => 'pa_pattern',
            'Flavor' => 'pa_flavor',
            'Scent' => 'pa_scent',
            'Edition' => 'pa_edition',
            'Format' => 'pa_format',
            'PackageQuantity' => 'pa_package_quantity'
        ));
    }

    /**
     * Setup text processing rules.
     *
     * @since    1.0.0
     */
    private function setup_text_processing_rules() {
        $this->text_processing_rules = array(
            'remove_patterns' => array(
                '/\[.*?\]/', // Remove content in square brackets
                '/\(.*?\)/', // Remove content in parentheses (optional)
                '/Amazon\.com/', // Remove Amazon.com references
                '/Visit the .* Store/', // Remove store visit references
                '/Brand: .*/', // Remove brand lines
            ),
            'replace_patterns' => array(
                '/\s+/' => ' ', // Multiple spaces to single space
                '/\n+/' => "\n", // Multiple newlines to single
                '/\t+/' => ' ', // Tabs to space
            ),
            'common_words_to_remove' => array(
                'amazon', 'bestseller', 'best seller', 'top rated', 'highly rated',
                'customer favorite', 'amazon choice', "amazon's choice", 'prime eligible'
            )
        );
    }

    /**
     * Map Amazon API data to WooCommerce product data.
     *
     * @since    1.0.0
     * @param    array    $api_data    Amazon API product data.
     * @return   array    Mapped WooCommerce product data.
     */
    public function map_amazon_to_woocommerce($api_data) {
        try {
            $mapped_data = array();

            // Basic product information
            $mapped_data['asin'] = $api_data['ASIN'];
            $mapped_data['name'] = $this->map_product_title($api_data);
            $mapped_data['description'] = $this->map_product_description($api_data);
            $mapped_data['short_description'] = $this->map_short_description($api_data);
            $mapped_data['sku'] = $this->generate_sku($api_data);

            // Pricing information
            $mapped_data = array_merge($mapped_data, $this->map_pricing_data($api_data));

            // Product attributes
            $mapped_data['attributes'] = $this->map_product_attributes($api_data);

            // Product dimensions and weight
            $mapped_data = array_merge($mapped_data, $this->map_dimensions_and_weight($api_data));

            // Stock and availability
            $mapped_data['stock_status'] = $this->map_stock_status($api_data);
            $mapped_data['manage_stock'] = false; // Amazon products don't provide exact stock numbers

            // Product type determination
            $mapped_data['product_type'] = $this->determine_product_type($api_data);

            // Variation data if applicable
            if (isset($api_data['VariationSummary'])) {
                $mapped_data['variation_data'] = $this->map_variation_summary($api_data['VariationSummary']);
            }

            // Technical details
            if ($this->settings['map_technical_details'] && isset($api_data['ItemInfo']['TechnicalInfo'])) {
                $mapped_data['technical_details'] = $this->map_technical_details($api_data['ItemInfo']['TechnicalInfo']);
            }

            // Additional metadata
            $mapped_data['metadata'] = $this->map_additional_metadata($api_data);

            // Apply custom field mappings
            $mapped_data = $this->apply_custom_mappings($api_data, $mapped_data);

            $this->logger->log("Successfully mapped Amazon data for ASIN: {$api_data['ASIN']}", 'info');

            return $mapped_data;

        } catch (Exception $e) {
            $this->logger->log("Error mapping Amazon data: " . $e->getMessage(), 'error');
            return array();
        }
    }

    /**
     * Map product title from Amazon data.
     *
     * @since    1.0.0
     * @param    array    $api_data    Amazon API data.
     * @return   string   Mapped product title.
     */
    private function map_product_title($api_data) {
        $title = '';

        // Get title from API data
        if (isset($api_data['ItemInfo']['Title']['DisplayValue'])) {
            $title = $api_data['ItemInfo']['Title']['DisplayValue'];
        }

        if (empty($title)) {
            return 'Amazon Product ' . $api_data['ASIN'];
        }

        // Clean the title
        $title = $this->clean_title($title);

        // Add brand to title if enabled
        if ($this->settings['include_brand_in_title']) {
            $brand = $this->get_brand_from_api_data($api_data);
            if (!empty($brand) && strpos(strtolower($title), strtolower($brand)) === false) {
                $title = $brand . ' ' . $title;
            }
        }

        // Apply case conversion
        $title = $this->apply_case_conversion($title, $this->settings['title_case_conversion']);

        // Truncate if necessary
        if (strlen($title) > $this->settings['title_max_length']) {
            $title = substr($title, 0, $this->settings['title_max_length'] - 3) . '...';
        }

        return trim($title);
    }

    /**
     * Map product description from Amazon data.
     *
     * @since    1.0.0
     * @param    array    $api_data    Amazon API data.
     * @return   string   Mapped product description.
     */
    private function map_product_description($api_data) {
        $description = '';

        // Try to get description from various sources
        $description_sources = array(
            'ItemInfo.ProductInfo.ProductDescription',
            'ItemInfo.ContentInfo.PublicationDate', // For books
            'ItemInfo.ContentInfo.Edition', // For books
        );

        foreach ($description_sources as $source) {
            $value = $this->get_nested_value($api_data, $source);
            if (!empty($value)) {
                $description = $value;
                break;
            }
        }

        // If no description found, use features as fallback
        if (empty($description) && isset($api_data['ItemInfo']['Features']['DisplayValues'])) {
            $features = $api_data['ItemInfo']['Features']['DisplayValues'];
            $description = $this->format_features_as_description($features);
        }

        // Clean the description
        if (!empty($description)) {
            $description = $this->clean_description($description);
        }

        return $description;
    }

    /**
     * Map short description from Amazon data.
     *
     * @since    1.0.0
     * @param    array    $api_data    Amazon API data.
     * @return   string   Mapped short description.
     */
    private function map_short_description($api_data) {
        if (!$this->settings['use_features_as_short_desc']) {
            return '';
        }

        if (!isset($api_data['ItemInfo']['Features']['DisplayValues'])) {
            return '';
        }

        $features = $api_data['ItemInfo']['Features']['DisplayValues'];
        
        // Limit features for short description
        $max_features = 5;
        $features = array_slice($features, 0, $max_features);

        $short_description = $this->format_feature_list($features);

        // Truncate if necessary
        if (strlen($short_description) > $this->settings['short_description_max_length']) {
            $short_description = substr($short_description, 0, $this->settings['short_description_max_length'] - 3) . '...';
        }

        return $short_description;
    }

    /**
     * Generate SKU for the product.
     *
     * @since    1.0.0
     * @param    array    $api_data    Amazon API data.
     * @return   string   Generated SKU.
     */
    private function generate_sku($api_data) {
        switch ($this->settings['sku_format']) {
            case 'asin':
                return $api_data['ASIN'];
            
            case 'custom':
                return $this->settings['sku_prefix'] . $api_data['ASIN'];
            
            case 'original':
                // Try to get original manufacturer SKU
                $original_sku = $this->get_nested_value($api_data, 'ItemInfo.ProductInfo.PartNumber');
                return !empty($original_sku) ? $original_sku : $api_data['ASIN'];
            
            default:
                return $api_data['ASIN'];
        }
    }

    /**
     * Map pricing data from Amazon API.
     *
     * @since    1.0.0
     * @param    array    $api_data    Amazon API data.
     * @return   array    Mapped pricing data.
     */
    private function map_pricing_data($api_data) {
        $pricing = array(
            'regular_price' => '',
            'sale_price' => '',
            'price' => ''
        );

        if (!isset($api_data['Offers']['Listings'][0])) {
            return $pricing;
        }

        $listing = $api_data['Offers']['Listings'][0];

        // Get current price
        if (isset($listing['Price']['Amount'])) {
            $current_price = floatval($listing['Price']['Amount']);
            $pricing['price'] = $current_price;
        }

        // Get list price (regular price)
        if (isset($listing['SavingBasis']['Amount'])) {
            $list_price = floatval($listing['SavingBasis']['Amount']);
            $pricing['regular_price'] = $list_price;
            
            // If current price is lower than list price, it's a sale
            if (isset($current_price) && $current_price < $list_price) {
                $pricing['sale_price'] = $current_price;
            }
        } else {
            // No list price, current price becomes regular price
            $pricing['regular_price'] = isset($current_price) ? $current_price : '';
        }

        // Get currency information
        if (isset($listing['Price']['Currency'])) {
            $pricing['currency'] = $listing['Price']['Currency'];
        }

        return $pricing;
    }

    /**
     * Map product attributes from Amazon data.
     *
     * @since    1.0.0
     * @param    array    $api_data    Amazon API data.
     * @return   array    Mapped product attributes.
     */
    private function map_product_attributes($api_data) {
        $attributes = array();

        // Map brand
        $brand = $this->get_brand_from_api_data($api_data);
        if (!empty($brand)) {
            $attributes['Brand'] = $brand;
        }

        // Map manufacturer
        if (isset($api_data['ItemInfo']['ByLineInfo']['Manufacturer']['DisplayValue'])) {
            $manufacturer = $api_data['ItemInfo']['ByLineInfo']['Manufacturer']['DisplayValue'];
            if (!empty($manufacturer) && $manufacturer !== $brand) {
                $attributes['Manufacturer'] = $manufacturer;
            }
        }

        // Map technical details if available
        if (isset($api_data['ItemInfo']['TechnicalInfo'])) {
            $technical_details = $this->extract_technical_attributes($api_data['ItemInfo']['TechnicalInfo']);
            $attributes = array_merge($attributes, $technical_details);
        }

        // Map classification info
        if (isset($api_data['ItemInfo']['Classifications'])) {
            $classifications = $this->extract_classification_attributes($api_data['ItemInfo']['Classifications']);
            $attributes = array_merge($attributes, $classifications);
        }

        // Map external IDs as attributes
        if (isset($api_data['ItemInfo']['ExternalIds'])) {
            $external_ids = $this->extract_external_id_attributes($api_data['ItemInfo']['ExternalIds']);
            $attributes = array_merge($attributes, $external_ids);
        }

        // Clean and validate attributes
        $attributes = $this->clean_attributes($attributes);

        return $attributes;
    }

    /**
     * Map dimensions and weight from Amazon data.
     *
     * @since    1.0.0
     * @param    array    $api_data    Amazon API data.
     * @return   array    Mapped dimensions and weight data.
     */
    private function map_dimensions_and_weight($api_data) {
        $data = array(
            'weight' => '',
            'length' => '',
            'width' => '',
            'height' => ''
        );

        // Map item dimensions
        if (isset($api_data['ItemInfo']['ProductInfo']['ItemDimensions'])) {
            $dimensions = $api_data['ItemInfo']['ProductInfo']['ItemDimensions'];
            
            if (isset($dimensions['Weight']['DisplayValue'])) {
                $data['weight'] = $this->convert_weight($dimensions['Weight']['DisplayValue'], $dimensions['Weight']['Unit']);
            }
            
            if (isset($dimensions['Length']['DisplayValue'])) {
                $data['length'] = $this->convert_dimension($dimensions['Length']['DisplayValue'], $dimensions['Length']['Unit']);
            }
            
            if (isset($dimensions['Width']['DisplayValue'])) {
                $data['width'] = $this->convert_dimension($dimensions['Width']['DisplayValue'], $dimensions['Width']['Unit']);
            }
            
            if (isset($dimensions['Height']['DisplayValue'])) {
                $data['height'] = $this->convert_dimension($dimensions['Height']['DisplayValue'], $dimensions['Height']['Unit']);
            }
        }

        // Use default weight if no weight is provided
        if (empty($data['weight']) && $this->settings['default_weight'] > 0) {
            $data['weight'] = $this->settings['default_weight'];
        }

        return $data;
    }

    /**
     * Map stock status from Amazon availability data.
     *
     * @since    1.0.0
     * @param    array    $api_data    Amazon API data.
     * @return   string   WooCommerce stock status.
     */
    private function map_stock_status($api_data) {
        if (!isset($api_data['Offers']['Listings'][0]['Availability']['Type'])) {
            return 'instock'; // Default to in stock
        }

        $availability = strtolower($api_data['Offers']['Listings'][0]['Availability']['Type']);

        $in_stock_indicators = array('now', 'in stock', 'available');
        $out_of_stock_indicators = array('unavailable', 'out of stock', 'temporarily unavailable');

        foreach ($in_stock_indicators as $indicator) {
            if (strpos($availability, $indicator) !== false) {
                return 'instock';
            }
        }

        foreach ($out_of_stock_indicators as $indicator) {
            if (strpos($availability, $indicator) !== false) {
                return 'outofstock';
            }
        }

        return 'instock'; // Default fallback
    }

    /**
     * Determine product type from Amazon data.
     *
     * @since    1.0.0
     * @param    array    $api_data    Amazon API data.
     * @return   string   WooCommerce product type.
     */
    private function determine_product_type($api_data) {
        // Check if product has variations
        if (isset($api_data['VariationSummary']['VariationCount']) && 
            $api_data['VariationSummary']['VariationCount'] > 1) {
            return 'variable';
        }

        // Check if this is a variation of a parent product
        if (isset($api_data['ParentASIN'])) {
            return 'variation';
        }

        // Default to simple product
        return 'simple';
    }

    /**
     * Map variation summary data.
     *
     * @since    1.0.0
     * @param    array    $variation_summary    Amazon variation summary data.
     * @return   array    Mapped variation data.
     */
    private function map_variation_summary($variation_summary) {
        $variation_data = array(
            'variation_count' => isset($variation_summary['VariationCount']) ? $variation_summary['VariationCount'] : 0,
            'variation_dimensions' => array()
        );

        if (isset($variation_summary['VariationDimensions'])) {
            foreach ($variation_summary['VariationDimensions'] as $dimension) {
                if (isset($dimension['Name'])) {
                    $dimension_name = $dimension['Name'];
                    $woo_attribute = $this->map_variation_dimension_to_attribute($dimension_name);
                    
                    $variation_data['variation_dimensions'][$dimension_name] = array(
                        'woo_attribute' => $woo_attribute,
                        'display_name' => isset($dimension['DisplayName']) ? $dimension['DisplayName'] : $dimension_name,
                        'values' => isset($dimension['Values']) ? $dimension['Values'] : array()
                    );
                }
            }
        }

        return $variation_data;
    }

    /**
     * Map variation data for individual variations.
     *
     * @since    1.0.0
     * @param    array    $variation_data    Amazon variation data.
     * @return   array    Mapped variation data.
     */
    public function map_variation_data($variation_data) {
        $mapped = array(
            'attributes' => array(),
            'regular_price' => '',
            'sale_price' => '',
            'stock_status' => 'instock'
        );

        // Map variation attributes
        if (isset($variation_data['VariationAttributes'])) {
            foreach ($variation_data['VariationAttributes'] as $attribute) {
                if (isset($attribute['Name']) && isset($attribute['Value'])) {
                    $attribute_name = $this->map_variation_dimension_to_attribute($attribute['Name']);
                    $mapped['attributes'][$attribute_name] = $attribute['Value'];
                }
            }
        }

        // Map pricing
        $pricing = $this->map_pricing_data($variation_data);
        $mapped['regular_price'] = $pricing['regular_price'];
        $mapped['sale_price'] = $pricing['sale_price'];

        // Map stock status
        $mapped['stock_status'] = $this->map_stock_status($variation_data);

        return $mapped;
    }

    /**
     * Map additional metadata from Amazon data.
     *
     * @since    1.0.0
     * @param    array    $api_data    Amazon API data.
     * @return   array    Additional metadata.
     */
    private function map_additional_metadata($api_data) {
        $metadata = array();

        // Detail page URL
        if (isset($api_data['DetailPageURL'])) {
            $metadata['detail_page_url'] = $api_data['DetailPageURL'];
        }

        // Customer reviews URL
        if (isset($api_data['CustomerReviews']['IFrameURL'])) {
            $metadata['customer_reviews_url'] = $api_data['CustomerReviews']['IFrameURL'];
        }

        // Browse node information
        if (isset($api_data['BrowseNodeInfo']['BrowseNodes'][0])) {
            $browse_node = $api_data['BrowseNodeInfo']['BrowseNodes'][0];
            $metadata['browse_node_id'] = $browse_node['Id'];
            $metadata['browse_node_name'] = $browse_node['DisplayName'];
        }

        // Prime eligibility
        if (isset($api_data['Offers']['Listings'][0]['ProgramEligibility']['IsPrimeEligible'])) {
            $metadata['prime_eligible'] = $api_data['Offers']['Listings'][0]['ProgramEligibility']['IsPrimeEligible'];
        }

        return $metadata;
    }

    /**
     * Clean product title.
     *
     * @since    1.0.0
     * @param    string    $title    Raw title.
     * @return   string    Cleaned title.
     */
    private function clean_title($title) {
        // Remove HTML tags
        if ($this->settings['remove_html_tags']) {
            $title = wp_strip_all_tags($title);
        }

        // Apply text processing rules
        $title = $this->apply_text_processing_rules($title);

        // Remove common words if enabled
        if ($this->settings['exclude_common_words']) {
            $title = $this->remove_common_words($title);
        }

        return trim($title);
    }

    /**
     * Clean product description.
     *
     * @since    1.0.0
     * @param    string    $description    Raw description.
     * @return   string    Cleaned description.
     */
    private function clean_description($description) {
        if (!$this->settings['clean_description']) {
            return $description;
        }

        // Remove HTML tags if enabled
        if ($this->settings['remove_html_tags']) {
            $description = wp_strip_all_tags($description);
        }

        // Apply text processing rules
        $description = $this->apply_text_processing_rules($description);

        // Limit length
        if (strlen($description) > $this->settings['description_max_length']) {
            $description = substr($description, 0, $this->settings['description_max_length'] - 3) . '...';
        }

        return trim($description);
    }

    /**
     * Format features as a list.
     *
     * @since    1.0.0
     * @param    array    $features    Features array.
     * @return   string   Formatted feature list.
     */
    private function format_feature_list($features) {
        if (empty($features) || !is_array($features)) {
            return '';
        }

        switch ($this->settings['feature_list_format']) {
            case 'bullets':
                return '• ' . implode("\n• ", $features);
            
            case 'numbered':
                $numbered = array();
                foreach ($features as $index => $feature) {
                    $numbered[] = ($index + 1) . '. ' . $feature;
                }
                return implode("\n", $numbered);
            
            case 'plain':
            default:
                return implode("\n", $features);
        }
    }

    /**
     * Format features as description.
     *
     * @since    1.0.0
     * @param    array    $features    Features array.
     * @return   string   Formatted description.
     */
    private function format_features_as_description($features) {
        if (empty($features) || !is_array($features)) {
            return '';
        }

        $description = "Product Features:\n\n";
        $description .= $this->format_feature_list($features);

        return $description;
    }

    /**
     * Apply text processing rules.
     *
     * @since    1.0.0
     * @param    string    $text    Text to process.
     * @return   string    Processed text.
     */
    private function apply_text_processing_rules($text) {
        // Apply removal patterns
        foreach ($this->text_processing_rules['remove_patterns'] as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }

        // Apply replacement patterns
        foreach ($this->text_processing_rules['replace_patterns'] as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return trim($text);
    }

    /**
     * Remove common words from text.
     *
     * @since    1.0.0
     * @param    string    $text    Text to process.
     * @return   string    Text with common words removed.
     */
    private function remove_common_words($text) {
        $text_lower = strtolower($text);
        
        foreach ($this->text_processing_rules['common_words_to_remove'] as $word) {
            $text_lower = str_replace(strtolower($word), '', $text_lower);
        }

        return trim($text);
    }

    /**
     * Apply case conversion to text.
     *
     * @since    1.0.0
     * @param    string    $text    Text to convert.
     * @param    string    $type    Conversion type.
     * @return   string    Converted text.
     */
    private function apply_case_conversion($text, $type) {
        switch ($type) {
            case 'title':
                return ucwords(strtolower($text));
            case 'sentence':
                return ucfirst(strtolower($text));
            case 'upper':
                return strtoupper($text);
            case 'lower':
                return strtolower($text);
            case 'none':
            default:
                return $text;
        }
    }

    /**
     * Get brand from API data.
     *
     * @since    1.0.0
     * @param    array    $api_data    Amazon API data.
     * @return   string   Brand name.
     */
    private function get_brand_from_api_data($api_data) {
        if (isset($api_data['ItemInfo']['ByLineInfo']['Brand']['DisplayValue'])) {
            return $api_data['ItemInfo']['ByLineInfo']['Brand']['DisplayValue'];
        }

        return '';
    }

    /**
     * Get nested value from array using dot notation.
     *
     * @since    1.0.0
     * @param    array     $array    Array to search.
     * @param    string    $path     Dot notation path.
     * @return   mixed     Value or null if not found.
     */
    private function get_nested_value($array, $path) {
        $keys = explode('.', $path);
        $current = $array;

        foreach ($keys as $key) {
            if (is_array($current) && isset($current[$key])) {
                $current = $current[$key];
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Extract technical attributes from technical info.
     *
     * @since    1.0.0
     * @param    array    $technical_info    Technical information.
     * @return   array    Technical attributes.
     */
    private function extract_technical_attributes($technical_info) {
        $attributes = array();

        // This would extract technical details based on the structure
        // The exact implementation depends on Amazon's technical info format
        
        if (is_array($technical_info)) {
            foreach ($technical_info as $key => $value) {
                if (is_string($value) && !empty($value)) {
                    $clean_key = ucwords(str_replace('_', ' ', $key));
                    $attributes[$clean_key] = $value;
                }
            }
        }

        return $attributes;
    }

    /**
     * Extract classification attributes.
     *
     * @since    1.0.0
     * @param    array    $classifications    Classification data.
     * @return   array    Classification attributes.
     */
    private function extract_classification_attributes($classifications) {
        $attributes = array();

        if (isset($classifications['ProductGroup']['DisplayValue'])) {
            $attributes['Product Group'] = $classifications['ProductGroup']['DisplayValue'];
        }

        if (isset($classifications['Binding']['DisplayValue'])) {
            $attributes['Binding'] = $classifications['Binding']['DisplayValue'];
        }

        return $attributes;
    }

    /**
     * Extract external ID attributes.
     *
     * @since    1.0.0
     * @param    array    $external_ids    External IDs data.
     * @return   array    External ID attributes.
     */
    private function extract_external_id_attributes($external_ids) {
        $attributes = array();

        $id_types = array('EAN', 'ISBN', 'UPC', 'GTIN');

        foreach ($id_types as $type) {
            if (isset($external_ids[$type]['DisplayValues'][0])) {
                $attributes[$type] = $external_ids[$type]['DisplayValues'][0];
            }
        }

        return $attributes;
    }

    /**
     * Map variation dimension to WooCommerce attribute.
     *
     * @since    1.0.0
     * @param    string    $dimension_name    Amazon dimension name.
     * @return   string    WooCommerce attribute name.
     */
    private function map_variation_dimension_to_attribute($dimension_name) {
        if (isset($this->attribute_mappings[$dimension_name])) {
            return $this->attribute_mappings[$dimension_name];
        }

        // Create a WooCommerce attribute name from the dimension name
        $attribute_name = 'pa_' . sanitize_title($dimension_name);
        
        return $attribute_name;
    }

    /**
     * Convert weight to site's default unit.
     *
     * @since    1.0.0
     * @param    string    $weight    Weight value.
     * @param    string    $unit      Original unit.
     * @return   float     Converted weight.
     */
    private function convert_weight($weight, $unit) {
        $weight_value = floatval($weight);
        $target_unit = $this->settings['weight_unit'];

        // Simple conversion logic - expand as needed
        $conversions = array(
            'pounds_to_kg' => 0.453592,
            'ounces_to_kg' => 0.0283495,
            'grams_to_kg' => 0.001,
            'kg_to_pounds' => 2.20462,
            'kg_to_ounces' => 35.274,
            'kg_to_grams' => 1000
        );

        $unit_lower = strtolower($unit);
        $conversion_key = $unit_lower . '_to_' . $target_unit;

        if (isset($conversions[$conversion_key])) {
            return $weight_value * $conversions[$conversion_key];
        }

        return $weight_value; // Return as-is if no conversion available
    }

    /**
     * Convert dimension to site's default unit.
     *
     * @since    1.0.0
     * @param    string    $dimension    Dimension value.
     * @param    string    $unit         Original unit.
     * @return   float     Converted dimension.
     */
    private function convert_dimension($dimension, $unit) {
        $dimension_value = floatval($dimension);
        $target_unit = $this->settings['dimension_unit'];

        // Simple conversion logic
        $conversions = array(
            'inches_to_cm' => 2.54,
            'feet_to_cm' => 30.48,
            'cm_to_inches' => 0.393701,
            'cm_to_feet' => 0.0328084,
            'mm_to_cm' => 0.1,
            'cm_to_mm' => 10
        );

        $unit_lower = strtolower($unit);
        $conversion_key = $unit_lower . '_to_' . $target_unit;

        if (isset($conversions[$conversion_key])) {
            return $dimension_value * $conversions[$conversion_key];
        }

        return $dimension_value;
    }

    /**
     * Clean and validate attributes.
     *
     * @since    1.0.0
     * @param    array    $attributes    Raw attributes.
     * @return   array    Cleaned attributes.
     */
    private function clean_attributes($attributes) {
        $clean_attributes = array();

        foreach ($attributes as $name => $value) {
            // Skip empty values
            if (empty($value)) {
                continue;
            }

            // Clean attribute name
            $clean_name = trim($name);
            $clean_name = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $clean_name);

            // Clean attribute value
            $clean_value = is_array($value) ? array_map('trim', $value) : trim($value);

            if (!empty($clean_name) && !empty($clean_value)) {
                $clean_attributes[$clean_name] = $clean_value;
            }
        }

        return $clean_attributes;
    }

    /**
     * Apply custom field mappings.
     *
     * @since    1.0.0
     * @param    array    $api_data       Amazon API data.
     * @param    array    $mapped_data    Current mapped data.
     * @return   array    Updated mapped data.
     */
    private function apply_custom_mappings($api_data, $mapped_data) {
        foreach ($this->custom_mappings as $field => $mapping) {
            if (isset($mapping['source'])) {
                $value = $this->get_nested_value($api_data, $mapping['source']);
                
                // Try fallback if primary source is empty
                if (empty($value) && isset($mapping['fallback'])) {
                    $value = $this->get_nested_value($api_data, $mapping['fallback']);
                }

                // Apply transformation if specified
                if (!empty($value) && isset($mapping['transform'])) {
                    $value = $this->apply_transformation($value, $mapping['transform']);
                }

                if (!empty($value)) {
                    $mapped_data[$field] = $value;
                }
            }
        }

        return $mapped_data;
    }

    /**
     * Apply transformation to a value.
     *
     * @since    1.0.0
     * @param    mixed     $value           Value to transform.
     * @param    string    $transformation  Transformation type.
     * @return   mixed     Transformed value.
     */
    private function apply_transformation($value, $transformation) {
        switch ($transformation) {
            case 'clean_title':
                return $this->clean_title($value);
            
            case 'clean_description':
                return $this->clean_description($value);
            
            case 'clean_text':
                return $this->apply_text_processing_rules($value);
            
            case 'features_to_list':
                return is_array($value) ? $this->format_feature_list($value) : $value;
            
            case 'uppercase':
                return strtoupper($value);
            
            case 'lowercase':
                return strtolower($value);
            
            case 'title_case':
                return ucwords(strtolower($value));
            
            default:
                return $value;
        }
    }

    /**
     * Map technical details to structured data.
     *
     * @since    1.0.0
     * @param    array    $technical_info    Technical information.
     * @return   array    Structured technical details.
     */
    private function map_technical_details($technical_info) {
        $details = array();

        // This would map technical specifications based on product type
        // Implementation depends on the structure of Amazon's technical info
        
        return $details;
    }

    /**
     * Get mapping statistics.
     *
     * @since    1.0.0
     * @return   array    Mapping statistics.
     */
    public function get_mapping_statistics() {
        return array(
            'custom_mappings_count' => count($this->custom_mappings),
            'attribute_mappings_count' => count($this->attribute_mappings),
            'processing_rules_count' => count($this->text_processing_rules['remove_patterns']) + count($this->text_processing_rules['replace_patterns']),
            'settings' => $this->settings
        );
    }

    /**
     * Update custom mapping configuration.
     *
     * @since    1.0.0
     * @param    array    $mappings    New mapping configuration.
     * @return   bool     True on success.
     */
    public function update_custom_mappings($mappings) {
        update_option('ams_custom_field_mappings', $mappings);
        $this->custom_mappings = $mappings;
        return true;
    }

    /**
     * Update attribute mapping configuration.
     *
     * @since    1.0.0
     * @param    array    $mappings    New attribute mappings.
     * @return   bool     True on success.
     */
    public function update_attribute_mappings($mappings) {
        update_option('ams_attribute_mappings', $mappings);
        $this->attribute_mappings = $mappings;
        return true;
    }
}