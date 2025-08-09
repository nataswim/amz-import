<?php

/**
 * Main Amazon API
 */
class Amazon_Product_Importer_Amazon_API {

    private $auth;
    private $request;
    private $parser;
    private $logger;
    private $cache;

    public function __construct() {
        $this->auth = new Amazon_Product_Importer_Amazon_Auth();
        $this->request = new Amazon_Product_Importer_API_Request();
        $this->parser = new Amazon_Product_Importer_API_Response_Parser();
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->cache = new Amazon_Product_Importer_Cache();
    }

    /**
     * Search products by keywords
     */
    public function search_products($keywords, $category = null, $page = 1, $items_per_page = 10) {
        $cache_key = 'search_' . md5($keywords . $category . $page . $items_per_page);
        
        if ($cached_result = $this->cache->get($cache_key)) {
            return $cached_result;
        }

        try {
            $params = array(
                'Keywords' => $keywords,
                'SearchIndex' => $category ?: 'All',
                'ItemPage' => $page,
                'ItemCount' => $items_per_page,
                'Resources' => array(
                    'Images.Primary.Medium',
                    'Images.Primary.Large',
                    'ItemInfo.Title',
                    'ItemInfo.Features',
                    'ItemInfo.ProductInfo',
                    'Offers.Listings.Price',
                    'Offers.Listings.SavingBasis',
                    'BrowseNodeInfo.BrowseNodes'
                )
            );

            $response = $this->request->make_request('SearchItems', $params);
            $parsed_response = $this->parser->parse_search_response($response);

            $this->cache->set($cache_key, $parsed_response, 3600); // Cache for 1 hour
            
            $this->logger->log('info', 'Product search completed', array(
                'keywords' => $keywords,
                'results_count' => count($parsed_response['items'])
            ));

            return $parsed_response;

        } catch (Exception $e) {
            $this->logger->log('error', 'Search products failed', array(
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
     * Get product details by ASIN
     */
    public function get_product($asin) {
        $cache_key = 'product_' . $asin;
        
        if ($cached_result = $this->cache->get($cache_key)) {
            return $cached_result;
        }

        try {
            $params = array(
                'ItemIds' => array($asin),
                'Resources' => array(
                    'Images.Primary.Small',
                    'Images.Primary.Medium',
                    'Images.Primary.Large',
                    'Images.Variants.Small',
                    'Images.Variants.Medium',
                    'Images.Variants.Large',
                    'ItemInfo.Title',
                    'ItemInfo.Features',
                    'ItemInfo.ProductInfo',
                    'ItemInfo.TechnicalInfo',
                    'ItemInfo.ManufactureInfo',
                    'Offers.Listings.Price',
                    'Offers.Listings.SavingBasis',
                    'Offers.Listings.Availability',
                    'BrowseNodeInfo.BrowseNodes',
                    'VariationSummary.VariationDimension',
                    'VariationSummary.PageCount'
                )
            );

            $response = $this->request->make_request('GetItems', $params);
            $parsed_response = $this->parser->parse_product_response($response);

            $this->cache->set($cache_key, $parsed_response, 1800); // Cache for 30 minutes
            
            $this->logger->log('info', 'Product details retrieved', array(
                'asin' => $asin
            ));

            return $parsed_response;

        } catch (Exception $e) {
            $this->logger->log('error', 'Get product failed', array(
                'asin' => $asin,
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'product' => null
            );
        }
    }

    /**
     * Get product variations
     */
    public function get_variations($asin, $page = 1) {
        $cache_key = 'variations_' . $asin . '_' . $page;
        
        if ($cached_result = $this->cache->get($cache_key)) {
            return $cached_result;
        }

        try {
            $params = array(
                'ASIN' => $asin,
                'VariationPage' => $page,
                'Resources' => array(
                    'Images.Primary.Medium',
                    'ItemInfo.Title',
                    'Offers.Listings.Price',
                    'Offers.Listings.SavingBasis',
                    'Offers.Listings.Availability',
                    'VariationSummary.VariationDimension'
                )
            );

            $response = $this->request->make_request('GetVariations', $params);
            $parsed_response = $this->parser->parse_variations_response($response);

            $this->cache->set($cache_key, $parsed_response, 1800); // Cache for 30 minutes
            
            $this->logger->log('info', 'Product variations retrieved', array(
                'asin' => $asin,
                'page' => $page,
                'variations_count' => count($parsed_response['variations'])
            ));

            return $parsed_response;

        } catch (Exception $e) {
            $this->logger->log('error', 'Get variations failed', array(
                'asin' => $asin,
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'variations' => array()
            );
        }
    }

    /**
     * Get browse node information
     */
    public function get_browse_nodes($browse_node_ids) {
        if (!is_array($browse_node_ids)) {
            $browse_node_ids = array($browse_node_ids);
        }

        $cache_key = 'browse_nodes_' . md5(implode(',', $browse_node_ids));
        
        if ($cached_result = $this->cache->get($cache_key)) {
            return $cached_result;
        }

        try {
            $params = array(
                'BrowseNodeIds' => $browse_node_ids,
                'Resources' => array(
                    'BrowseNodes.Ancestor',
                    'BrowseNodes.Children'
                )
            );

            $response = $this->request->make_request('GetBrowseNodes', $params);
            $parsed_response = $this->parser->parse_browse_nodes_response($response);

            $this->cache->set($cache_key, $parsed_response, 7200); // Cache for 2 hours
            
            $this->logger->log('info', 'Browse nodes retrieved', array(
                'node_ids' => $browse_node_ids
            ));

            return $parsed_response;

        } catch (Exception $e) {
            $this->logger->log('error', 'Get browse nodes failed', array(
                'node_ids' => $browse_node_ids,
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'browse_nodes' => array()
            );
        }
    }
}