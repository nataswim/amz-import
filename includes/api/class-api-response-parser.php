<?php
/**
 * Amazon API Response Parser
 *
 * This class handles the parsing and processing of responses from the
 * Amazon Product Advertising API, transforming raw API data into
 * structured data suitable for WordPress/WooCommerce integration.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/api
 */

/**
 * API response parser class for Amazon Product Advertising API
 *
 * Parses and normalizes responses from Amazon PA-API into structured
 * data formats compatible with WordPress and WooCommerce.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/api
 * @author     Your Name <your.email@example.com>
 */
class Amazon_Product_Importer_API_Response_Parser {

    /**
     * Logger instance
     *
     * @since    1.0.0
     * @access   private
     * @var      object    $logger    Logger instance
     */
    private $logger;

    /**
     * Parser configuration
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $config    Parser configuration
     */
    private $config;

    /**
     * Currency symbols mapping
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $currency_symbols    Currency symbols
     */
    private $currency_symbols;

    /**
     * Image size mappings
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $image_sizes    Image size mappings
     */
    private $image_sizes;

    /**
     * Data sanitization rules
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $sanitization_rules    Sanitization rules
     */
    private $sanitization_rules;

    /**
     * Initialize the parser
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->init_logger();
        $this->init_config();
        $this->load_mappings();
        $this->init_sanitization_rules();
    }

    /**
     * Parse search response from SearchItems operation
     *
     * @since    1.0.0
     * @param    array     $response     Raw API response
     * @param    string    $operation    API operation name
     * @return   array                   Parsed response data
     */
    public function parse_search_response($response, $operation = 'SearchItems') {
        try {
            $parsed_data = array(
                'items' => array(),
                'pagination' => array(),
                'metadata' => array(),
                'errors' => array()
            );

            // Handle SearchItems response
            if ($operation === 'SearchItems' && isset($response['SearchResult'])) {
                $search_result = $response['SearchResult'];

                // Parse items
                if (isset($search_result['Items']) && is_array($search_result['Items'])) {
                    foreach ($search_result['Items'] as $item_data) {
                        $parsed_item = $this->parse_item_data($item_data);
                        if ($parsed_item) {
                            $parsed_data['items'][] = $parsed_item;
                        }
                    }
                }

                // Parse pagination info
                $parsed_data['pagination'] = $this->parse_pagination_info($search_result);

                // Parse search refinements if available
                if (isset($search_result['SearchRefinements'])) {
                    $parsed_data['refinements'] = $this->parse_search_refinements($search_result['SearchRefinements']);
                }
            }

            // Handle GetItems response (multiple items)
            elseif ($operation === 'GetItems' && isset($response['ItemsResult'])) {
                $items_result = $response['ItemsResult'];

                if (isset($items_result['Items']) && is_array($items_result['Items'])) {
                    foreach ($items_result['Items'] as $item_data) {
                        $parsed_item = $this->parse_item_data($item_data);
                        if ($parsed_item) {
                            $parsed_data['items'][] = $parsed_item;
                        }
                    }
                }
            }

            // Parse metadata
            $parsed_data['metadata'] = $this->parse_response_metadata($response);

            // Parse any errors
            if (isset($response['Errors'])) {
                $parsed_data['errors'] = $this->parse_errors($response['Errors']);
            }

            $this->logger->info('Search response parsed successfully', array(
                'operation' => $operation,
                'items_count' => count($parsed_data['items']),
                'has_pagination' => !empty($parsed_data['pagination'])
            ));

            return $parsed_data;

        } catch (Exception $e) {
            $this->logger->error('Search response parsing failed', array(
                'operation' => $operation,
                'error' => $e->getMessage()
            ));

            return array(
                'items' => array(),
                'pagination' => array(),
                'metadata' => array(),
                'errors' => array(array('message' => $e->getMessage()))
            );
        }
    }

    /**
     * Parse product details response from GetItems operation
     *
     * @since    1.0.0
     * @param    array    $response    Raw API response
     * @return   array                 Parsed product details
     */
    public function parse_product_details_response($response) {
        try {
            $parsed_data = array(
                'items' => array(),
                'metadata' => array(),
                'errors' => array()
            );

            if (isset($response['ItemsResult']['Items']) && is_array($response['ItemsResult']['Items'])) {
                foreach ($response['ItemsResult']['Items'] as $item_data) {
                    $parsed_item = $this->parse_detailed_item_data($item_data);
                    if ($parsed_item) {
                        $parsed_data['items'][] = $parsed_item;
                    }
                }
            }

            // Parse metadata
            $parsed_data['metadata'] = $this->parse_response_metadata($response);

            // Parse errors
            if (isset($response['Errors'])) {
                $parsed_data['errors'] = $this->parse_errors($response['Errors']);
            }

            $this->logger->info('Product details response parsed successfully', array(
                'items_count' => count($parsed_data['items'])
            ));

            return $parsed_data;

        } catch (Exception $e) {
            $this->logger->error('Product details response parsing failed', array(
                'error' => $e->getMessage()
            ));

            return array(
                'items' => array(),
                'metadata' => array(),
                'errors' => array(array('message' => $e->getMessage()))
            );
        }
    }

    /**
     * Parse variations response from GetVariations operation
     *
     * @since    1.0.0
     * @param    array    $response    Raw API response
     * @return   array                 Parsed variations data
     */
    public function parse_variations_response($response) {
        try {
            $parsed_data = array(
                'parent_asin' => '',
                'variations' => array(),
                'variation_summary' => array(),
                'metadata' => array(),
                'errors' => array()
            );

            if (isset($response['VariationsResult'])) {
                $variations_result = $response['VariationsResult'];

                // Get parent ASIN
                if (isset($variations_result['ASIN'])) {
                    $parsed_data['parent_asin'] = $variations_result['ASIN'];
                }

                // Parse variation summary
                if (isset($variations_result['VariationSummary'])) {
                    $parsed_data['variation_summary'] = $this->parse_variation_summary($variations_result['VariationSummary']);
                }

                // Parse individual variations
                if (isset($variations_result['Items']) && is_array($variations_result['Items'])) {
                    foreach ($variations_result['Items'] as $variation_data) {
                        $parsed_variation = $this->parse_variation_data($variation_data);
                        if ($parsed_variation) {
                            $parsed_data['variations'][] = $parsed_variation;
                        }
                    }
                }
            }

            // Parse metadata
            $parsed_data['metadata'] = $this->parse_response_metadata($response);

            // Parse errors
            if (isset($response['Errors'])) {
                $parsed_data['errors'] = $this->parse_errors($response['Errors']);
            }

            $this->logger->info('Variations response parsed successfully', array(
                'parent_asin' => $parsed_data['parent_asin'],
                'variations_count' => count($parsed_data['variations'])
            ));

            return $parsed_data;

        } catch (Exception $e) {
            $this->logger->error('Variations response parsing failed', array(
                'error' => $e->getMessage()
            ));

            return array(
                'parent_asin' => '',
                'variations' => array(),
                'variation_summary' => array(),
                'metadata' => array(),
                'errors' => array(array('message' => $e->getMessage()))
            );
        }
    }

    /**
     * Parse browse nodes response from GetBrowseNodes operation
     *
     * @since    1.0.0
     * @param    array    $response    Raw API response
     * @return   array                 Parsed browse nodes data
     */
    public function parse_browse_nodes_response($response) {
        try {
            $parsed_data = array(
                'browse_nodes' => array(),
                'metadata' => array(),
                'errors' => array()
            );

            if (isset($response['BrowseNodesResult']['BrowseNodes']) && is_array($response['BrowseNodesResult']['BrowseNodes'])) {
                foreach ($response['BrowseNodesResult']['BrowseNodes'] as $browse_node_data) {
                    $parsed_node = $this->parse_browse_node_data($browse_node_data);
                    if ($parsed_node) {
                        $parsed_data['browse_nodes'][] = $parsed_node;
                    }
                }
            }

            // Parse metadata
            $parsed_data['metadata'] = $this->parse_response_metadata($response);

            // Parse errors
            if (isset($response['Errors'])) {
                $parsed_data['errors'] = $this->parse_errors($response['Errors']);
            }

            $this->logger->info('Browse nodes response parsed successfully', array(
                'nodes_count' => count($parsed_data['browse_nodes'])
            ));

            return $parsed_data;

        } catch (Exception $e) {
            $this->logger->error('Browse nodes response parsing failed', array(
                'error' => $e->getMessage()
            ));

            return array(
                'browse_nodes' => array(),
                'metadata' => array(),
                'errors' => array(array('message' => $e->getMessage()))
            );
        }
    }

    /**
     * Parse individual item data
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $item_data    Raw item data
     * @return   array|null             Parsed item data or null
     */
    private function parse_item_data($item_data) {
        if (empty($item_data) || !isset($item_data['ASIN'])) {
            return null;
        }

        $parsed_item = array(
            'asin' => $item_data['ASIN'],
            'title' => '',
            'description' => '',
            'short_description' => '',
            'brand' => '',
            'manufacturer' => '',
            'model' => '',
            'url' => '',
            'images' => array(),
            'price' => array(),
            'offers' => array(),
            'features' => array(),
            'attributes' => array(),
            'dimensions' => array(),
            'weight' => '',
            'availability' => array(),
            'reviews' => array(),
            'browse_nodes' => array(),
            'parent_asin' => '',
            'variation_attributes' => array(),
            'metadata' => array()
        );

        // Parse basic item info
        if (isset($item_data['ItemInfo'])) {
            $parsed_item = array_merge($parsed_item, $this->parse_item_info($item_data['ItemInfo']));
        }

        // Parse images
        if (isset($item_data['Images'])) {
            $parsed_item['images'] = $this->parse_images($item_data['Images']);
        }

        // Parse offers and pricing
        if (isset($item_data['Offers'])) {
            $price_data = $this->parse_offers($item_data['Offers']);
            $parsed_item['price'] = $price_data['price'];
            $parsed_item['offers'] = $price_data['offers'];
            $parsed_item['availability'] = $price_data['availability'];
        }

        // Parse customer reviews
        if (isset($item_data['CustomerReviews'])) {
            $parsed_item['reviews'] = $this->parse_customer_reviews($item_data['CustomerReviews']);
        }

        // Parse browse node info
        if (isset($item_data['BrowseNodeInfo'])) {
            $parsed_item['browse_nodes'] = $this->parse_browse_node_info($item_data['BrowseNodeInfo']);
        }

        // Parse variation info if present
        if (isset($item_data['VariationSummary'])) {
            $variation_data = $this->parse_variation_summary($item_data['VariationSummary']);
            $parsed_item['parent_asin'] = isset($variation_data['parent_asin']) ? $variation_data['parent_asin'] : '';
            $parsed_item['variation_attributes'] = isset($variation_data['dimensions']) ? $variation_data['dimensions'] : array();
        }

        // Parse detail page URL
        if (isset($item_data['DetailPageURL'])) {
            $parsed_item['url'] = $item_data['DetailPageURL'];
        }

        // Add metadata
        $parsed_item['metadata'] = array(
            'parsed_at' => current_time('mysql'),
            'source' => 'amazon_api',
            'api_version' => '2020-10-01'
        );

        return $parsed_item;
    }

    /**
     * Parse detailed item data with extended information
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $item_data    Raw item data
     * @return   array|null             Parsed detailed item data or null
     */
    private function parse_detailed_item_data($item_data) {
        // Start with basic item parsing
        $parsed_item = $this->parse_item_data($item_data);
        
        if (!$parsed_item) {
            return null;
        }

        // Add detailed information specific to GetItems responses
        if (isset($item_data['ItemInfo'])) {
            $item_info = $item_data['ItemInfo'];

            // Parse technical info
            if (isset($item_info['TechnicalInfo'])) {
                $parsed_item['technical_info'] = $this->parse_technical_info($item_info['TechnicalInfo']);
            }

            // Parse content info
            if (isset($item_info['ContentInfo'])) {
                $parsed_item['content_info'] = $this->parse_content_info($item_info['ContentInfo']);
            }

            // Parse trade-in info
            if (isset($item_info['TradeInInfo'])) {
                $parsed_item['trade_in_info'] = $this->parse_trade_in_info($item_info['TradeInInfo']);
            }

            // Parse external IDs
            if (isset($item_info['ExternalIds'])) {
                $parsed_item['external_ids'] = $this->parse_external_ids($item_info['ExternalIds']);
            }

            // Parse classifications
            if (isset($item_info['Classifications'])) {
                $parsed_item['classifications'] = $this->parse_classifications($item_info['Classifications']);
            }
        }

        return $parsed_item;
    }

    /**
     * Parse item info section
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $item_info    Item info data
     * @return   array                  Parsed item info
     */
    private function parse_item_info($item_info) {
        $parsed_info = array();

        // Parse title
        if (isset($item_info['Title']['DisplayValue'])) {
            $parsed_info['title'] = $this->sanitize_text($item_info['Title']['DisplayValue']);
        }

        // Parse features
        if (isset($item_info['Features']['DisplayValues']) && is_array($item_info['Features']['DisplayValues'])) {
            $parsed_info['features'] = array_map(array($this, 'sanitize_text'), $item_info['Features']['DisplayValues']);
            $parsed_info['short_description'] = implode("\n", array_slice($parsed_info['features'], 0, 5));
        }

        // Parse brand and manufacturer info
        if (isset($item_info['ByLineInfo'])) {
            $by_line = $item_info['ByLineInfo'];
            
            if (isset($by_line['Brand']['DisplayValue'])) {
                $parsed_info['brand'] = $this->sanitize_text($by_line['Brand']['DisplayValue']);
            }
            
            if (isset($by_line['Manufacturer']['DisplayValue'])) {
                $parsed_info['manufacturer'] = $this->sanitize_text($by_line['Manufacturer']['DisplayValue']);
            }
        }

        // Parse manufacture info
        if (isset($item_info['ManufactureInfo'])) {
            $manufacture_info = $item_info['ManufactureInfo'];
            
            if (isset($manufacture_info['ItemPartNumber']['DisplayValue'])) {
                $parsed_info['model'] = $this->sanitize_text($manufacture_info['ItemPartNumber']['DisplayValue']);
            }
        }

        // Parse product info
        if (isset($item_info['ProductInfo'])) {
            $product_info = $item_info['ProductInfo'];
            
            // Product description
            if (isset($product_info['ProductDescription'])) {
                $parsed_info['description'] = $this->sanitize_html($product_info['ProductDescription']);
            }
            
            // Dimensions and weight
            if (isset($product_info['ItemDimensions'])) {
                $parsed_info['dimensions'] = $this->parse_dimensions($product_info['ItemDimensions']);
            }
            
            if (isset($product_info['ItemWeight'])) {
                $parsed_info['weight'] = $this->parse_weight($product_info['ItemWeight']);
            }
        }

        return $parsed_info;
    }

    /**
     * Parse images data
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $images_data    Images data
     * @return   array                    Parsed images
     */
    private function parse_images($images_data) {
        $parsed_images = array(
            'primary' => array(),
            'variants' => array()
        );

        // Parse primary image
        if (isset($images_data['Primary'])) {
            $parsed_images['primary'] = $this->parse_image_set($images_data['Primary']);
        }

        // Parse variant images
        if (isset($images_data['Variants']) && is_array($images_data['Variants'])) {
            foreach ($images_data['Variants'] as $variant) {
                $parsed_variant = $this->parse_image_set($variant);
                if (!empty($parsed_variant)) {
                    $parsed_images['variants'][] = $parsed_variant;
                }
            }
        }

        return $parsed_images;
    }

    /**
     * Parse image set (different sizes)
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $image_set    Image set data
     * @return   array                  Parsed image set
     */
    private function parse_image_set($image_set) {
        $parsed_set = array();

        $size_priorities = array('Large', 'Medium', 'Small');
        
        foreach ($size_priorities as $size) {
            if (isset($image_set[$size])) {
                $image_data = $image_set[$size];
                
                $parsed_set[$size] = array(
                    'url' => isset($image_data['URL']) ? $this->sanitize_url($image_data['URL']) : '',
                    'width' => isset($image_data['Width']) ? intval($image_data['Width']) : 0,
                    'height' => isset($image_data['Height']) ? intval($image_data['Height']) : 0
                );
            }
        }

        return $parsed_set;
    }

    /**
     * Parse offers and pricing data
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $offers_data    Offers data
     * @return   array                    Parsed pricing data
     */
    private function parse_offers($offers_data) {
        $parsed_data = array(
            'price' => array(),
            'offers' => array(),
            'availability' => array()
        );

        // Parse listings
        if (isset($offers_data['Listings']) && is_array($offers_data['Listings'])) {
            foreach ($offers_data['Listings'] as $listing) {
                $parsed_listing = $this->parse_listing($listing);
                $parsed_data['offers'][] = $parsed_listing;
                
                // Use first listing for primary price
                if (empty($parsed_data['price']) && !empty($parsed_listing['price'])) {
                    $parsed_data['price'] = $parsed_listing['price'];
                    $parsed_data['availability'] = $parsed_listing['availability'];
                }
            }
        }

        // Parse summaries
        if (isset($offers_data['Summaries']) && is_array($offers_data['Summaries'])) {
            foreach ($offers_data['Summaries'] as $summary) {
                if (isset($summary['Condition']['Value'])) {
                    $condition = $summary['Condition']['Value'];
                    
                    if ($condition === 'New' && empty($parsed_data['price'])) {
                        // Use summary pricing if no listing price available
                        if (isset($summary['LowestPrice'])) {
                            $parsed_data['price'] = array_merge(
                                $parsed_data['price'],
                                $this->parse_price($summary['LowestPrice'])
                            );
                        }
                    }
                }
            }
        }

        return $parsed_data;
    }

    /**
     * Parse individual listing
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $listing    Listing data
     * @return   array                Parsed listing
     */
    private function parse_listing($listing) {
        $parsed_listing = array(
            'price' => array(),
            'availability' => array(),
            'condition' => array(),
            'merchant' => array(),
            'delivery' => array(),
            'is_buy_box_winner' => false
        );

        // Parse price
        if (isset($listing['Price'])) {
            $parsed_listing['price'] = $this->parse_price($listing['Price']);
            
            // Add savings basis if available
            if (isset($listing['SavingBasis'])) {
                $savings_data = $this->parse_price($listing['SavingBasis']);
                $parsed_listing['price']['original_price'] = $savings_data['amount'];
                $parsed_listing['price']['savings'] = $savings_data['amount'] - $parsed_listing['price']['amount'];
            }
        }

        // Parse availability
        if (isset($listing['Availability'])) {
            $parsed_listing['availability'] = $this->parse_availability($listing['Availability']);
        }

        // Parse condition
        if (isset($listing['Condition'])) {
            $parsed_listing['condition'] = $this->parse_condition($listing['Condition']);
        }

        // Parse merchant info
        if (isset($listing['MerchantInfo'])) {
            $parsed_listing['merchant'] = $this->parse_merchant_info($listing['MerchantInfo']);
        }

        // Parse delivery info
        if (isset($listing['DeliveryInfo'])) {
            $parsed_listing['delivery'] = $this->parse_delivery_info($listing['DeliveryInfo']);
        }

        // Check if buy box winner
        if (isset($listing['IsBuyBoxWinner'])) {
            $parsed_listing['is_buy_box_winner'] = (bool) $listing['IsBuyBoxWinner'];
        }

        return $parsed_listing;
    }

    /**
     * Parse price information
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $price_data    Price data
     * @return   array                   Parsed price
     */
    private function parse_price($price_data) {
        $parsed_price = array(
            'amount' => 0,
            'currency' => '',
            'display_amount' => '',
            'per_unit' => ''
        );

        if (isset($price_data['Amount'])) {
            $parsed_price['amount'] = floatval($price_data['Amount']);
        }

        if (isset($price_data['Currency'])) {
            $parsed_price['currency'] = $price_data['Currency'];
        }

        if (isset($price_data['DisplayAmount'])) {
            $parsed_price['display_amount'] = $this->sanitize_text($price_data['DisplayAmount']);
        }

        if (isset($price_data['PricePerUnit'])) {
            $parsed_price['per_unit'] = $this->sanitize_text($price_data['PricePerUnit']);
        }

        return $parsed_price;
    }

    /**
     * Parse availability information
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $availability_data    Availability data
     * @return   array                          Parsed availability
     */
    private function parse_availability($availability_data) {
        $parsed_availability = array(
            'type' => '',
            'message' => '',
            'min_order_quantity' => 1,
            'max_order_quantity' => 999999
        );

        if (isset($availability_data['Type'])) {
            $parsed_availability['type'] = $availability_data['Type'];
        }

        if (isset($availability_data['Message'])) {
            $parsed_availability['message'] = $this->sanitize_text($availability_data['Message']);
        }

        if (isset($availability_data['MinOrderQuantity'])) {
            $parsed_availability['min_order_quantity'] = intval($availability_data['MinOrderQuantity']);
        }

        if (isset($availability_data['MaxOrderQuantity'])) {
            $parsed_availability['max_order_quantity'] = intval($availability_data['MaxOrderQuantity']);
        }

        return $parsed_availability;
    }

    /**
     * Parse customer reviews
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $reviews_data    Reviews data
     * @return   array                     Parsed reviews
     */
    private function parse_customer_reviews($reviews_data) {
        $parsed_reviews = array(
            'count' => 0,
            'star_rating' => 0,
            'star_rating_text' => ''
        );

        if (isset($reviews_data['Count'])) {
            $parsed_reviews['count'] = intval($reviews_data['Count']);
        }

        if (isset($reviews_data['StarRating'])) {
            $star_data = $reviews_data['StarRating'];
            
            if (isset($star_data['Value'])) {
                $parsed_reviews['star_rating'] = floatval($star_data['Value']);
            }
            
            if (isset($star_data['DisplayValue'])) {
                $parsed_reviews['star_rating_text'] = $this->sanitize_text($star_data['DisplayValue']);
            }
        }

        return $parsed_reviews;
    }

    /**
     * Parse variation summary
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $variation_summary    Variation summary data
     * @return   array                          Parsed variation summary
     */
    private function parse_variation_summary($variation_summary) {
        $parsed_summary = array(
            'page_count' => 1,
            'variation_count' => 0,
            'dimensions' => array()
        );

        if (isset($variation_summary['PageCount'])) {
            $parsed_summary['page_count'] = intval($variation_summary['PageCount']);
        }

        if (isset($variation_summary['VariationCount'])) {
            $parsed_summary['variation_count'] = intval($variation_summary['VariationCount']);
        }

        if (isset($variation_summary['VariationDimensions']) && is_array($variation_summary['VariationDimensions'])) {
            foreach ($variation_summary['VariationDimensions'] as $dimension) {
                $parsed_dimension = $this->parse_variation_dimension($dimension);
                if ($parsed_dimension) {
                    $parsed_summary['dimensions'][] = $parsed_dimension;
                }
            }
        }

        return $parsed_summary;
    }

    /**
     * Parse variation dimension
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $dimension    Dimension data
     * @return   array|null             Parsed dimension or null
     */
    private function parse_variation_dimension($dimension) {
        if (!isset($dimension['Name']) || !isset($dimension['Values'])) {
            return null;
        }

        $parsed_dimension = array(
            'name' => $this->sanitize_text($dimension['Name']),
            'display_name' => $this->sanitize_text($dimension['DisplayName'] ?? $dimension['Name']),
            'values' => array()
        );

        if (is_array($dimension['Values'])) {
            foreach ($dimension['Values'] as $value) {
                if (isset($value['DisplayValue'])) {
                    $parsed_dimension['values'][] = $this->sanitize_text($value['DisplayValue']);
                }
            }
        }

        return $parsed_dimension;
    }

    /**
     * Parse variation data
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $variation_data    Variation data
     * @return   array|null                  Parsed variation or null
     */
    private function parse_variation_data($variation_data) {
        // Use the standard item parsing as base
        $parsed_variation = $this->parse_item_data($variation_data);
        
        if (!$parsed_variation) {
            return null;
        }

        // Add variation-specific attributes
        if (isset($variation_data['VariationAttributes']) && is_array($variation_data['VariationAttributes'])) {
            $parsed_variation['variation_attributes'] = array();
            
            foreach ($variation_data['VariationAttributes'] as $attribute) {
                if (isset($attribute['Name']) && isset($attribute['Value'])) {
                    $parsed_variation['variation_attributes'][$attribute['Name']] = $this->sanitize_text($attribute['Value']);
                }
            }
        }

        return $parsed_variation;
    }

    /**
     * Parse browse node data
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $browse_node_data    Browse node data
     * @return   array|null                    Parsed browse node or null
     */
    private function parse_browse_node_data($browse_node_data) {
        if (!isset($browse_node_data['Id'])) {
            return null;
        }

        $parsed_node = array(
            'id' => $browse_node_data['Id'],
            'name' => '',
            'display_name' => '',
            'context_free_name' => '',
            'is_root' => false,
            'children' => array(),
            'ancestors' => array()
        );

        if (isset($browse_node_data['DisplayName'])) {
            $parsed_node['display_name'] = $this->sanitize_text($browse_node_data['DisplayName']);
            $parsed_node['name'] = $parsed_node['display_name'];
        }

        if (isset($browse_node_data['ContextFreeName'])) {
            $parsed_node['context_free_name'] = $this->sanitize_text($browse_node_data['ContextFreeName']);
        }

        if (isset($browse_node_data['IsRoot'])) {
            $parsed_node['is_root'] = (bool) $browse_node_data['IsRoot'];
        }

        // Parse children
        if (isset($browse_node_data['Children']) && is_array($browse_node_data['Children'])) {
            foreach ($browse_node_data['Children'] as $child) {
                $parsed_child = $this->parse_browse_node_data($child);
                if ($parsed_child) {
                    $parsed_node['children'][] = $parsed_child;
                }
            }
        }

        // Parse ancestors
        if (isset($browse_node_data['Ancestor']) && is_array($browse_node_data['Ancestor'])) {
            $parsed_node['ancestors'] = $this->parse_browse_node_ancestors($browse_node_data['Ancestor']);
        }

        return $parsed_node;
    }

    /**
     * Parse browse node ancestors
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $ancestors_data    Ancestors data
     * @return   array                       Parsed ancestors
     */
    private function parse_browse_node_ancestors($ancestors_data) {
        $parsed_ancestors = array();

        foreach ($ancestors_data as $ancestor) {
            if (isset($ancestor['Id']) && isset($ancestor['DisplayName'])) {
                $parsed_ancestors[] = array(
                    'id' => $ancestor['Id'],
                    'name' => $this->sanitize_text($ancestor['DisplayName']),
                    'context_free_name' => isset($ancestor['ContextFreeName']) 
                        ? $this->sanitize_text($ancestor['ContextFreeName']) 
                        : $this->sanitize_text($ancestor['DisplayName'])
                );
            }
        }

        return $parsed_ancestors;
    }

    /**
     * Parse browse node info from item
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $browse_node_info    Browse node info data
     * @return   array                         Parsed browse node info
     */
    private function parse_browse_node_info($browse_node_info) {
        $parsed_info = array(
            'browse_nodes' => array(),
            'website_sales_rank' => array()
        );

        if (isset($browse_node_info['BrowseNodes']) && is_array($browse_node_info['BrowseNodes'])) {
            foreach ($browse_node_info['BrowseNodes'] as $node) {
                $parsed_node = $this->parse_browse_node_data($node);
                if ($parsed_node) {
                    $parsed_info['browse_nodes'][] = $parsed_node;
                }
            }
        }

        if (isset($browse_node_info['WebsiteSalesRank'])) {
            $parsed_info['website_sales_rank'] = $this->parse_sales_rank($browse_node_info['WebsiteSalesRank']);
        }

        return $parsed_info;
    }

    /**
     * Parse sales rank information
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $sales_rank_data    Sales rank data
     * @return   array                        Parsed sales rank
     */
    private function parse_sales_rank($sales_rank_data) {
        $parsed_rank = array();

        if (isset($sales_rank_data['SalesRanks']) && is_array($sales_rank_data['SalesRanks'])) {
            foreach ($sales_rank_data['SalesRanks'] as $rank) {
                if (isset($rank['Rank']) && isset($rank['DisplayName'])) {
                    $parsed_rank[] = array(
                        'rank' => intval($rank['Rank']),
                        'category' => $this->sanitize_text($rank['DisplayName']),
                        'context_free_name' => isset($rank['ContextFreeName']) 
                            ? $this->sanitize_text($rank['ContextFreeName'])
                            : $this->sanitize_text($rank['DisplayName'])
                    );
                }
            }
        }

        return $parsed_rank;
    }

    /**
     * Parse pagination information
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $search_result    Search result data
     * @return   array                      Parsed pagination
     */
    private function parse_pagination_info($search_result) {
        $pagination = array(
            'total_result_count' => 0,
            'total_result_pages' => 1,
            'current_page' => 1,
            'items_per_page' => 10
        );

        if (isset($search_result['TotalResultCount'])) {
            $pagination['total_result_count'] = intval($search_result['TotalResultCount']);
        }

        if (isset($search_result['TotalResultPages'])) {
            $pagination['total_result_pages'] = intval($search_result['TotalResultPages']);
        }

        if (isset($search_result['SearchURL'])) {
            $pagination['search_url'] = $this->sanitize_url($search_result['SearchURL']);
        }

        return $pagination;
    }

    /**
     * Parse response metadata
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $response    Full API response
     * @return   array                 Parsed metadata
     */
    private function parse_response_metadata($response) {
        $metadata = array(
            'request_id' => '',
            'timestamp' => current_time('mysql'),
            'api_version' => '2020-10-01'
        );

        // Extract request ID if available
        if (isset($response['_metadata']['RequestId'])) {
            $metadata['request_id'] = $response['_metadata']['RequestId'];
        }

        return $metadata;
    }

    /**
     * Parse API errors
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $errors    Errors array
     * @return   array               Parsed errors
     */
    private function parse_errors($errors) {
        $parsed_errors = array();

        foreach ($errors as $error) {
            $parsed_error = array(
                'code' => isset($error['Code']) ? $error['Code'] : 'unknown',
                'message' => isset($error['Message']) ? $this->sanitize_text($error['Message']) : 'Unknown error'
            );

            $parsed_errors[] = $parsed_error;
        }

        return $parsed_errors;
    }

    /**
     * Parse dimensions data
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $dimensions_data    Dimensions data
     * @return   array                        Parsed dimensions
     */
    private function parse_dimensions($dimensions_data) {
        $parsed_dimensions = array(
            'height' => '',
            'length' => '',
            'width' => '',
            'weight' => ''
        );

        $dimension_fields = array('Height', 'Length', 'Width', 'Weight');

        foreach ($dimension_fields as $field) {
            if (isset($dimensions_data[$field])) {
                $dimension = $dimensions_data[$field];
                $parsed_dimensions[strtolower($field)] = array(
                    'value' => isset($dimension['DisplayValue']) ? $this->sanitize_text($dimension['DisplayValue']) : '',
                    'unit' => isset($dimension['Unit']) ? $dimension['Unit'] : ''
                );
            }
        }

        return $parsed_dimensions;
    }

    /**
     * Parse weight data
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $weight_data    Weight data
     * @return   array                    Parsed weight
     */
    private function parse_weight($weight_data) {
        return array(
            'value' => isset($weight_data['DisplayValue']) ? $this->sanitize_text($weight_data['DisplayValue']) : '',
            'unit' => isset($weight_data['Unit']) ? $weight_data['Unit'] : ''
        );
    }

    /**
     * Initialize logger
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_logger() {
        if (class_exists('Amazon_Product_Importer_Logger')) {
            $this->logger = new Amazon_Product_Importer_Logger();
        } else {
            // Fallback logger
            $this->logger = new stdClass();
            $this->logger->info = function() {};
            $this->logger->error = function() {};
            $this->logger->debug = function() {};
        }
    }

    /**
     * Initialize parser configuration
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_config() {
        $this->config = array(
            'sanitize_html' => true,
            'strip_tags' => false,
            'allowed_html_tags' => array('p', 'br', 'strong', 'em', 'ul', 'ol', 'li'),
            'max_text_length' => 10000,
            'max_features_count' => 20,
            'default_currency' => 'USD',
            'include_metadata' => true
        );
    }

    /**
     * Load currency and image mappings
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_mappings() {
        // Load from endpoints config if available
        $endpoints_file = AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'config/api-endpoints.php';
        if (file_exists($endpoints_file)) {
            $endpoints_config = include $endpoints_file;
            $this->currency_symbols = isset($endpoints_config['currency_symbols']) ? $endpoints_config['currency_symbols'] : array();
            $this->image_sizes = isset($endpoints_config['image_sizes']) ? $endpoints_config['image_sizes'] : array();
        } else {
            $this->currency_symbols = array();
            $this->image_sizes = array();
        }
    }

    /**
     * Initialize sanitization rules
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_sanitization_rules() {
        $this->sanitization_rules = array(
            'strip_scripts' => true,
            'strip_styles' => true,
            'normalize_whitespace' => true,
            'decode_entities' => true,
            'remove_nulls' => true
        );
    }

    /**
     * Sanitize text content
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $text    Text to sanitize
     * @return   string             Sanitized text
     */
    private function sanitize_text($text) {
        if (empty($text)) {
            return '';
        }

        // Remove null bytes
        $text = str_replace("\0", '', $text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));

        // Limit length
        if (strlen($text) > $this->config['max_text_length']) {
            $text = substr($text, 0, $this->config['max_text_length']);
        }

        return sanitize_text_field($text);
    }

    /**
     * Sanitize HTML content
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $html    HTML to sanitize
     * @return   string             Sanitized HTML
     */
    private function sanitize_html($html) {
        if (empty($html)) {
            return '';
        }

        // Remove null bytes
        $html = str_replace("\0", '', $html);

        // Strip or escape scripts and styles
        if ($this->sanitization_rules['strip_scripts']) {
            $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        }

        if ($this->sanitization_rules['strip_styles']) {
            $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        }

        // Use WordPress sanitization
        $allowed_html = $this->config['strip_tags'] ? array() : $this->config['allowed_html_tags'];
        
        return wp_kses($html, $allowed_html);
    }

    /**
     * Sanitize URL
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $url    URL to sanitize
     * @return   string            Sanitized URL
     */
    private function sanitize_url($url) {
        return esc_url_raw($url);
    }

    /**
     * Get parser statistics
     *
     * @since    1.0.0
     * @return   array    Parser statistics
     */
    public function get_stats() {
        return array(
            'config' => $this->config,
            'currency_symbols_count' => count($this->currency_symbols),
            'image_sizes_count' => count($this->image_sizes),
            'sanitization_rules' => $this->sanitization_rules
        );
    }

    /**
     * Set parser configuration
     *
     * @since    1.0.0
     * @param    array    $config    Configuration array
     */
    public function set_config($config) {
        $this->config = array_merge($this->config, $config);
    }

    // Additional helper methods for extended parsing...
    
    private function parse_technical_info($technical_info) { /* Implementation */ }
    private function parse_content_info($content_info) { /* Implementation */ }
    private function parse_trade_in_info($trade_in_info) { /* Implementation */ }
    private function parse_external_ids($external_ids) { /* Implementation */ }
    private function parse_classifications($classifications) { /* Implementation */ }
    private function parse_condition($condition) { /* Implementation */ }
    private function parse_merchant_info($merchant_info) { /* Implementation */ }
    private function parse_delivery_info($delivery_info) { /* Implementation */ }
    private function parse_search_refinements($refinements) { /* Implementation */ }
}