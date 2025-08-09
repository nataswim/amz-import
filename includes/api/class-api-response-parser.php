<?php

/**
 * Parses Amazon API responses
 */
class Amazon_Product_Importer_API_Response_Parser {

    private $helper;

    public function __construct() {
        $this->helper = new Amazon_Product_Importer_Helper();
    }

    /**
     * Parse search response
     */
    public function parse_search_response($response) {
        $parsed_response = array(
            'success' => true,
            'items' => array(),
            'pagination' => array()
        );

        try {
            if (!isset($response['ItemSearchResponse'])) {
                throw new Exception('Invalid search response format');
            }

            $search_response = $response['ItemSearchResponse'];

            // Check for errors
            if (isset($search_response['Items']['Request']['Errors'])) {
                throw new Exception($search_response['Items']['Request']['Errors']['Error']['Message']);
            }

            // Parse items
            if (isset($search_response['Items']['Item'])) {
                $items = $search_response['Items']['Item'];
                
                // Handle single item response
                if (isset($items['ASIN'])) {
                    $items = array($items);
                }

                foreach ($items as $item) {
                    $parsed_item = $this->parse_search_item($item);
                    if ($parsed_item) {
                        $parsed_response['items'][] = $parsed_item;
                    }
                }
            }

            // Parse pagination
            if (isset($search_response['Items']['TotalResults'])) {
                $parsed_response['pagination'] = $this->parse_pagination($search_response['Items']);
            }

        } catch (Exception $e) {
            $parsed_response['success'] = false;
            $parsed_response['error'] = $e->getMessage();
        }

        return $parsed_response;
    }

    /**
     * Parse single search item
     */
    private function parse_search_item($item) {
        if (!isset($item['ASIN'])) {
            return null;
        }

        $parsed_item = array(
            'asin' => $item['ASIN'],
            'title' => $this->get_item_title($item),
            'image' => $this->get_item_image($item),
            'price' => $this->get_item_price($item),
            'url' => $this->get_item_url($item)
        );

        return $parsed_item;
    }

    /**
     * Parse product response
     */
    public function parse_product_response($response) {
        $parsed_response = array(
            'success' => true,
            'product' => null
        );

        try {
            if (!isset($response['ItemLookupResponse'])) {
                throw new Exception('Invalid product response format');
            }

            $lookup_response = $response['ItemLookupResponse'];

            // Check for errors
            if (isset($lookup_response['Items']['Request']['Errors'])) {
                throw new Exception($lookup_response['Items']['Request']['Errors']['Error']['Message']);
            }

            // Parse product
            if (isset($lookup_response['Items']['Item'])) {
                $item = $lookup_response['Items']['Item'];
                
                // Handle multiple items (take first)
                if (is_array($item) && isset($item[0])) {
                    $item = $item[0];
                }

                $parsed_response['product'] = $this->parse_product_item($item);
            }

        } catch (Exception $e) {
            $parsed_response['success'] = false;
            $parsed_response['error'] = $e->getMessage();
        }

        return $parsed_response;
    }

    /**
     * Parse single product item
     */
    private function parse_product_item($item) {
        if (!isset($item['ASIN'])) {
            return null;
        }

        $parsed_product = array(
            'ASIN' => $item['ASIN'],
            'ItemInfo' => $this->parse_item_info($item),
            'Images' => $this->parse_images($item),
            'Offers' => $this->parse_offers($item),
            'BrowseNodeInfo' => $this->parse_browse_nodes($item),
            'VariationSummary' => $this->parse_variation_summary($item)
        );

        return $parsed_product;
    }

    /**
     * Parse item information
     */
    private function parse_item_info($item) {
        $item_info = array();

        // Title
        if (isset($item['ItemAttributes']['Title'])) {
            $item_info['Title'] = array(
                'DisplayValue' => $item['ItemAttributes']['Title']
            );
        }

        // Features
        if (isset($item['ItemAttributes']['Feature'])) {
            $features = $item['ItemAttributes']['Feature'];
            if (!is_array($features)) {
                $features = array($features);
            }
            $item_info['Features'] = array(
                'DisplayValues' => $features
            );
        }

        // Product Info
        $product_info = array();
        
        if (isset($item['ItemAttributes']['Color'])) {
            $product_info['Color'] = array('DisplayValue' => $item['ItemAttributes']['Color']);
        }
        
        if (isset($item['ItemAttributes']['Size'])) {
            $product_info['Size'] = array('DisplayValue' => $item['ItemAttributes']['Size']);
        }

        if (isset($item['EditorialReviews']['EditorialReview']['Content'])) {
            $product_info['ProductDescription'] = array(
                'DisplayValue' => $item['EditorialReviews']['EditorialReview']['Content']
            );
        }

        if (!empty($product_info)) {
            $item_info['ProductInfo'] = $product_info;
        }

        // Manufacture Info
        $manufacture_info = array();
        
        if (isset($item['ItemAttributes']['Brand'])) {
            $manufacture_info['Brand'] = array('DisplayValue' => $item['ItemAttributes']['Brand']);
        }
        
        if (isset($item['ItemAttributes']['Manufacturer'])) {
            $manufacture_info['Manufacturer'] = array('DisplayValue' => $item['ItemAttributes']['Manufacturer']);
        }
        
        if (isset($item['ItemAttributes']['Model'])) {
            $manufacture_info['Model'] = array('DisplayValue' => $item['ItemAttributes']['Model']);
        }

        if (!empty($manufacture_info)) {
            $item_info['ManufactureInfo'] = $manufacture_info;
        }

        return $item_info;
    }

    /**
     * Parse images
     */
    private function parse_images($item) {
        $images = array();

        // Primary image
        if (isset($item['LargeImage'])) {
            $images['Primary'] = array(
                'Large' => array('URL' => $item['LargeImage']['URL']),
                'Medium' => array('URL' => $item['MediumImage']['URL'] ?? $item['LargeImage']['URL']),
                'Small' => array('URL' => $item['SmallImage']['URL'] ?? $item['LargeImage']['URL'])
            );
        }

        // Image sets (variants)
        if (isset($item['ImageSets']['ImageSet'])) {
            $image_sets = $item['ImageSets']['ImageSet'];
            
            if (!isset($image_sets[0])) {
                $image_sets = array($image_sets);
            }

            $variants = array();
            foreach ($image_sets as $image_set) {
                if (isset($image_set['LargeImage'])) {
                    $variants[] = array(
                        'Large' => array('URL' => $image_set['LargeImage']['URL']),
                        'Medium' => array('URL' => $image_set['MediumImage']['URL'] ?? $image_set['LargeImage']['URL']),
                        'Small' => array('URL' => $image_set['SmallImage']['URL'] ?? $image_set['LargeImage']['URL'])
                    );
                }
            }
            
            if (!empty($variants)) {
                $images['Variants'] = $variants;
            }
        }

        return $images;
    }

    /**
     * Parse offers
     */
    private function parse_offers($item) {
        $offers = array('Listings' => array());

        if (isset($item['OfferSummary'])) {
            $offer_summary = $item['OfferSummary'];
            
            $listing = array();

            // Price
            if (isset($offer_summary['LowestNewPrice']['Amount'])) {
                $listing['Price'] = array(
                    'Amount' => intval($offer_summary['LowestNewPrice']['Amount'])
                );
            }

            // List price
            if (isset($item['ItemAttributes']['ListPrice']['Amount'])) {
                $listing['SavingBasis'] = array(
                    'Amount' => intval($item['ItemAttributes']['ListPrice']['Amount'])
                );
            }

            // Availability
            $listing['Availability'] = array(
                'Type' => 'Now' // Default availability
            );

            if (!empty($listing)) {
                $offers['Listings'][] = $listing;
            }
        }

        return $offers;
    }

    /**
     * Parse browse nodes
     */
    private function parse_browse_nodes($item) {
        $browse_node_info = array('BrowseNodes' => array());

        if (isset($item['BrowseNodes']['BrowseNode'])) {
            $browse_nodes = $item['BrowseNodes']['BrowseNode'];
            
            if (!isset($browse_nodes[0])) {
                $browse_nodes = array($browse_nodes);
            }

            foreach ($browse_nodes as $node) {
                if (isset($node['BrowseNodeId'])) {
                    $browse_node_info['BrowseNodes'][] = array(
                        'Id' => $node['BrowseNodeId'],
                        'DisplayName' => $node['Name'] ?? '',
                        'SalesRank' => $node['SalesRank'] ?? null
                    );
                }
            }
        }

        return $browse_node_info;
    }

    /**
     * Parse variation summary
     */
    private function parse_variation_summary($item) {
        $variation_summary = array();

        if (isset($item['Variations']['Item'])) {
            $variations = $item['Variations']['Item'];
            
            if (!isset($variations[0])) {
                $variations = array($variations);
            }

            // Extract variation dimensions
            $dimensions = array();
            foreach ($variations as $variation) {
                if (isset($variation['ItemAttributes'])) {
                    $attributes = $variation['ItemAttributes'];
                    
                    foreach ($attributes as $key => $value) {
                        if (in_array($key, array('Color', 'Size', 'Style'))) {
                            $dimensions[$key] = $value;
                        }
                    }
                }
            }

            if (!empty($dimensions)) {
                $variation_summary['VariationDimension'] = array();
                
                foreach ($dimensions as $name => $value) {
                    $variation_summary['VariationDimension'][] = array(
                        'Name' => $name,
                        'DisplayValue' => $value
                    );
                }
            }
        }

        return $variation_summary;
    }

    /**
     * Parse variations response
     */
    public function parse_variations_response($response) {
        $parsed_response = array(
            'success' => true,
            'variations' => array()
        );

        try {
            if (!isset($response['GetVariationsResponse'])) {
                throw new Exception('Invalid variations response format');
            }

            $variations_response = $response['GetVariationsResponse'];

            if (isset($variations_response['GetVariationsResult']['Variations']['Item'])) {
                $variations = $variations_response['GetVariationsResult']['Variations']['Item'];
                
                if (!isset($variations[0])) {
                    $variations = array($variations);
                }

                foreach ($variations as $variation) {
                    $parsed_variation = $this->parse_product_item($variation);
                    if ($parsed_variation) {
                        $parsed_response['variations'][] = $parsed_variation;
                    }
                }
            }

        } catch (Exception $e) {
            $parsed_response['success'] = false;
            $parsed_response['error'] = $e->getMessage();
        }

        return $parsed_response;
    }

    /**
     * Parse browse nodes response
     */
    public function parse_browse_nodes_response($response) {
        $parsed_response = array(
            'success' => true,
            'browse_nodes' => array()
        );

        try {
            if (!isset($response['BrowseNodeLookupResponse'])) {
                throw new Exception('Invalid browse nodes response format');
            }

            $lookup_response = $response['BrowseNodeLookupResponse'];

            if (isset($lookup_response['BrowseNodes']['BrowseNode'])) {
                $browse_nodes = $lookup_response['BrowseNodes']['BrowseNode'];
                
                if (!isset($browse_nodes[0])) {
                    $browse_nodes = array($browse_nodes);
                }

                foreach ($browse_nodes as $node) {
                    $parsed_node = $this->parse_browse_node($node);
                    if ($parsed_node) {
                        $parsed_response['browse_nodes'][] = $parsed_node;
                    }
                }
            }

        } catch (Exception $e) {
            $parsed_response['success'] = false;
            $parsed_response['error'] = $e->getMessage();
        }

        return $parsed_response;
    }

    /**
     * Parse single browse node
     */
    private function parse_browse_node($node) {
        if (!isset($node['BrowseNodeId'])) {
            return null;
        }

        $parsed_node = array(
            'Id' => $node['BrowseNodeId'],
            'Name' => $node['Name'] ?? '',
            'Children' => array(),
            'Ancestors' => array()
        );

        // Parse children
        if (isset($node['Children']['BrowseNode'])) {
            $children = $node['Children']['BrowseNode'];
            
            if (!isset($children[0])) {
                $children = array($children);
            }

            foreach ($children as $child) {
                $parsed_node['Children'][] = array(
                    'Id' => $child['BrowseNodeId'],
                    'Name' => $child['Name'] ?? ''
                );
            }
        }

        // Parse ancestors
        if (isset($node['Ancestors']['BrowseNode'])) {
            $ancestors = $node['Ancestors']['BrowseNode'];
            
            if (!isset($ancestors[0])) {
                $ancestors = array($ancestors);
            }

            foreach ($ancestors as $ancestor) {
                $parsed_node['Ancestors'][] = array(
                    'Id' => $ancestor['BrowseNodeId'],
                    'Name' => $ancestor['Name'] ?? ''
                );
            }
        }

        return $parsed_node;
    }

    /**
     * Parse pagination information
     */
    private function parse_pagination($items_data) {
        $pagination = array(
            'current_page' => 1,
            'total_pages' => 1,
            'total_items' => 0,
            'items_per_page' => 10,
            'has_previous' => false,
            'has_next' => false
        );

        if (isset($items_data['TotalResults'])) {
            $pagination['total_items'] = intval($items_data['TotalResults']);
        }

        if (isset($items_data['TotalPages'])) {
            $pagination['total_pages'] = intval($items_data['TotalPages']);
        }

        if (isset($items_data['ItemPage'])) {
            $pagination['current_page'] = intval($items_data['ItemPage']);
        }

        $pagination['has_previous'] = $pagination['current_page'] > 1;
        $pagination['has_next'] = $pagination['current_page'] < $pagination['total_pages'];

        return $pagination;
    }

    /**
     * Get item title
     */
    private function get_item_title($item) {
        if (isset($item['ItemAttributes']['Title'])) {
            return $item['ItemAttributes']['Title'];
        }
        return 'Untitled Product';
    }

    /**
     * Get item image
     */
    private function get_item_image($item) {
        if (isset($item['MediumImage']['URL'])) {
            return $item['MediumImage']['URL'];
        }
        if (isset($item['SmallImage']['URL'])) {
            return $item['SmallImage']['URL'];
        }
        return null;
    }

    /**
     * Get item price
     */
    private function get_item_price($item) {
        if (isset($item['OfferSummary']['LowestNewPrice']['FormattedPrice'])) {
            return $item['OfferSummary']['LowestNewPrice']['FormattedPrice'];
        }
        if (isset($item['ItemAttributes']['ListPrice']['FormattedPrice'])) {
            return $item['ItemAttributes']['ListPrice']['FormattedPrice'];
        }
        return null;
    }

    /**
     * Get item URL
     */
    private function get_item_url($item) {
        if (isset($item['DetailPageURL'])) {
            return $item['DetailPageURL'];
        }
        return null;
    }
}