<?php

/**
 * Maps Amazon product data to WooCommerce format
 */
class Amazon_Product_Importer_Product_Mapper {

    private $helper;

    public function __construct() {
        $this->helper = new Amazon_Product_Importer_Helper();
    }

    /**
     * Map Amazon product data to WooCommerce format
     */
    public function map_product_data($amazon_product) {
        $mapped_data = array();

        // Basic information
        $mapped_data['asin'] = $this->get_asin($amazon_product);
        $mapped_data['title'] = $this->get_title($amazon_product);
        $mapped_data['description'] = $this->get_description($amazon_product);
        $mapped_data['short_description'] = $this->get_short_description($amazon_product);
        $mapped_data['sku'] = $this->get_sku($amazon_product);

        // Price information
        $price_data = $this->get_price_data($amazon_product);
        if ($price_data) {
            $mapped_data = array_merge($mapped_data, $price_data);
        }

        // Attributes
        $mapped_data['attributes'] = $this->get_attributes($amazon_product);

        // Brand
        $mapped_data['brand'] = $this->get_brand($amazon_product);

        // Weight and dimensions
        $mapped_data['weight'] = $this->get_weight($amazon_product);
        $mapped_data['dimensions'] = $this->get_dimensions($amazon_product);

        return $mapped_data;
    }

    /**
     * Get ASIN from product data
     */
    private function get_asin($amazon_product) {
        return isset($amazon_product['ASIN']) ? $amazon_product['ASIN'] : '';
    }

    /**
     * Get product title
     */
    private function get_title($amazon_product) {
        if (isset($amazon_product['ItemInfo']['Title']['DisplayValue'])) {
            return sanitize_text_field($amazon_product['ItemInfo']['Title']['DisplayValue']);
        }

        return __('Produit Amazon sans titre', 'amazon-product-importer');
    }

    /**
     * Get product description
     */
    private function get_description($amazon_product) {
        $description = '';

        // Try to get product description
        if (isset($amazon_product['ItemInfo']['ProductInfo']['ProductDescription']['DisplayValue'])) {
            $description = $amazon_product['ItemInfo']['ProductInfo']['ProductDescription']['DisplayValue'];
        }

        // If no description, use features
        if (empty($description) && isset($amazon_product['ItemInfo']['Features']['DisplayValues'])) {
            $features = $amazon_product['ItemInfo']['Features']['DisplayValues'];
            $description = '<ul><li>' . implode('</li><li>', $features) . '</li></ul>';
        }

        return wp_kses_post($description);
    }

    /**
     * Get short description (features)
     */
    private function get_short_description($amazon_product) {
        if (isset($amazon_product['ItemInfo']['Features']['DisplayValues'])) {
            $features = array_slice($amazon_product['ItemInfo']['Features']['DisplayValues'], 0, 5);
            return '<ul><li>' . implode('</li><li>', array_map('sanitize_text_field', $features)) . '</li></ul>';
        }

        return '';
    }

    /**
     * Get SKU (use ASIN as SKU)
     */
    private function get_sku($amazon_product) {
        return $this->get_asin($amazon_product);
    }

    /**
     * Get price data
     */
    private function get_price_data($amazon_product) {
        $price_data = array();

        if (!isset($amazon_product['Offers']['Listings'][0])) {
            return $price_data;
        }

        $listing = $amazon_product['Offers']['Listings'][0];

        // Get current price
        if (isset($listing['Price']['Amount'])) {
            $current_price = $this->format_price($listing['Price']['Amount']);
            $price_data['regular_price'] = $current_price;
        }

        // Get list price (original price)
        if (isset($listing['SavingBasis']['Amount'])) {
            $list_price = $this->format_price($listing['SavingBasis']['Amount']);
            
            // If there's a saving, set list price as regular and current as sale
            if (isset($price_data['regular_price']) && $list_price > $price_data['regular_price']) {
                $price_data['sale_price'] = $price_data['regular_price'];
                $price_data['regular_price'] = $list_price;
            }
        }

        return $price_data;
    }

    /**
     * Format price (convert from cents to dollars)
     */
    private function format_price($amount) {
        return number_format($amount / 100, 2, '.', '');
    }

    /**
     * Get product attributes
     */
    private function get_attributes($amazon_product) {
        $attributes = array();

        // Brand
        $brand = $this->get_brand($amazon_product);
        if ($brand) {
            $attributes['Brand'] = $brand;
        }

        // Manufacturer
        if (isset($amazon_product['ItemInfo']['ManufactureInfo']['Manufacturer']['DisplayValue'])) {
            $attributes['Manufacturer'] = $amazon_product['ItemInfo']['ManufactureInfo']['Manufacturer']['DisplayValue'];
        }

        // Model
        if (isset($amazon_product['ItemInfo']['ManufactureInfo']['Model']['DisplayValue'])) {
            $attributes['Model'] = $amazon_product['ItemInfo']['ManufactureInfo']['Model']['DisplayValue'];
        }

        // Color
        if (isset($amazon_product['ItemInfo']['ProductInfo']['Color']['DisplayValue'])) {
            $attributes['Color'] = $amazon_product['ItemInfo']['ProductInfo']['Color']['DisplayValue'];
        }

        // Size
        if (isset($amazon_product['ItemInfo']['ProductInfo']['Size']['DisplayValue'])) {
            $attributes['Size'] = $amazon_product['ItemInfo']['ProductInfo']['Size']['DisplayValue'];
        }

        // Material
        if (isset($amazon_product['ItemInfo']['TechnicalInfo']['Material']['DisplayValue'])) {
            $attributes['Material'] = $amazon_product['ItemInfo']['TechnicalInfo']['Material']['DisplayValue'];
        }

        return $attributes;
    }

    /**
     * Get brand
     */
    private function get_brand($amazon_product) {
        // Try different possible brand locations
        $brand_paths = array(
            'ItemInfo.ByLineInfo.Brand.DisplayValue',
            'ItemInfo.ManufactureInfo.Brand.DisplayValue',
            'ItemInfo.ProductInfo.Brand.DisplayValue'
        );

        foreach ($brand_paths as $path) {
            $brand = $this->helper->get_nested_value($amazon_product, $path);
            if ($brand) {
                return sanitize_text_field($brand);
            }
        }

        return '';
    }

    /**
     * Get product weight
     */
    private function get_weight($amazon_product) {
        if (isset($amazon_product['ItemInfo']['ProductInfo']['Weight']['DisplayValue'])) {
            $weight_string = $amazon_product['ItemInfo']['ProductInfo']['Weight']['DisplayValue'];
            
            // Extract numeric weight (assuming pounds or kg)
            preg_match('/(\d+\.?\d*)\s*(lb|pound|kg|kilogram)/i', $weight_string, $matches);
            
            if (!empty($matches)) {
                $weight = floatval($matches[1]);
                $unit = strtolower($matches[2]);
                
                // Convert to kg if necessary
                if (in_array($unit, array('lb', 'pound'))) {
                    $weight = $weight * 0.453592; // Convert pounds to kg
                }
                
                return $weight;
            }
        }

        return null;
    }

    /**
     * Get product dimensions
     */
    private function get_dimensions($amazon_product) {
        $dimensions = array();

        if (isset($amazon_product['ItemInfo']['ProductInfo']['Dimensions'])) {
            $dim_data = $amazon_product['ItemInfo']['ProductInfo']['Dimensions'];

            if (isset($dim_data['Height']['DisplayValue'])) {
                $dimensions['height'] = $this->extract_dimension($dim_data['Height']['DisplayValue']);
            }

            if (isset($dim_data['Length']['DisplayValue'])) {
                $dimensions['length'] = $this->extract_dimension($dim_data['Length']['DisplayValue']);
            }

            if (isset($dim_data['Width']['DisplayValue'])) {
                $dimensions['width'] = $this->extract_dimension($dim_data['Width']['DisplayValue']);
            }
        }

        return $dimensions;
    }

    /**
     * Extract numeric dimension from string
     */
    private function extract_dimension($dimension_string) {
        // Extract numeric dimension (assuming inches or cm)
        preg_match('/(\d+\.?\d*)\s*(in|inch|cm|centimeter)/i', $dimension_string, $matches);
        
        if (!empty($matches)) {
            $dimension = floatval($matches[1]);
            $unit = strtolower($matches[2]);
            
            // Convert to cm if necessary
            if (in_array($unit, array('in', 'inch'))) {
                $dimension = $dimension * 2.54; // Convert inches to cm
            }
            
            return $dimension;
        }

        return null;
    }

    /**
     * Map variation data
     */
    public function map_variation_data($amazon_variation, $parent_product_id) {
        $mapped_data = array();

        $mapped_data['asin'] = $this->get_asin($amazon_variation);
        $mapped_data['parent_id'] = $parent_product_id;
        $mapped_data['sku'] = $this->get_sku($amazon_variation);

        // Price information
        $price_data = $this->get_price_data($amazon_variation);
        if ($price_data) {
            $mapped_data = array_merge($mapped_data, $price_data);
        }

        // Variation attributes
        $mapped_data['variation_attributes'] = $this->get_variation_attributes($amazon_variation);

        // Stock status
        $mapped_data['stock_status'] = $this->get_stock_status($amazon_variation);

        return $mapped_data;
    }

    /**
     * Get variation attributes
     */
    private function get_variation_attributes($amazon_variation) {
        $attributes = array();

        if (isset($amazon_variation['VariationSummary']['VariationDimension'])) {
            foreach ($amazon_variation['VariationSummary']['VariationDimension'] as $dimension) {
                if (isset($dimension['Name']) && isset($dimension['DisplayValue'])) {
                    $attribute_name = 'pa_' . sanitize_title($dimension['Name']);
                    $attributes[$attribute_name] = sanitize_text_field($dimension['DisplayValue']);
                }
            }
        }

        return $attributes;
    }

    /**
     * Get stock status
     */
    private function get_stock_status($amazon_product) {
        if (isset($amazon_product['Offers']['Listings'][0]['Availability']['Type'])) {
            $availability = $amazon_product['Offers']['Listings'][0]['Availability']['Type'];
            
            switch (strtolower($availability)) {
                case 'now':
                    return 'instock';
                case 'unknown':
                case 'delayed':
                    return 'onbackorder';
                default:
                    return 'outofstock';
            }
        }

        return 'instock'; // Default to in stock
    }
}