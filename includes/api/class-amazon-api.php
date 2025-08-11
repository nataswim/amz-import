<?php
/**
 * Amazon Product Advertising API 5.0 Implementation
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/api
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Amazon Product Advertising API 5.0 Class
 *
 * Handles all API communications with Amazon PA-API 5.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/api
 * @author     Your Name <email@example.com>
 */
class Amazon_Product_Importer_Amazon_API {

    /**
     * Authentication handler
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Amazon_Auth    $auth    Authentication handler
     */
    private $auth;

    /**
     * Logger instance
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Logger    $logger    Logger instance
     */
    private $logger;

    /**
     * Cache handler
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Cache    $cache    Cache handler
     */
    private $cache;

    /**
     * API request timeout
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $timeout    Request timeout in seconds
     */
    private $timeout = 30;

    /**
     * API retry attempts
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $retry_attempts    Number of retry attempts
     */
    private $retry_attempts = 3;

    /**
     * Default resources to fetch
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $default_resources    Default API resources
     */
    private $default_resources = array(
        'ItemInfo.Title',
        'ItemInfo.Features',
        'ItemInfo.ProductInfo',
        'ItemInfo.TechnicalInfo',
        'ItemInfo.ManufactureInfo',
        'Images.Primary.Small',
        'Images.Primary.Medium',
        'Images.Primary.Large',
        'Images.Variants.Small',
        'Images.Variants.Medium',
        'Images.Variants.Large',
        'Offers.Listings.Price',
        'Offers.Listings.SavingBasis',
        'Offers.Listings.Availability',
        'Offers.Summaries.HighestPrice',
        'Offers.Summaries.LowestPrice',
        'BrowseNodeInfo.BrowseNodes',
        'BrowseNodeInfo.BrowseNodes.Ancestor',
        'BrowseNodeInfo.WebsiteSalesRank',
        'VariationSummary.VariationDimension'
    );

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->auth = new Amazon_Product_Importer_Amazon_Auth();
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->cache = new Amazon_Product_Importer_Cache();
        
        // Load settings
        $this->timeout = get_option('amazon_product_importer_api_timeout', 30);
        $this->retry_attempts = get_option('amazon_product_importer_api_retries', 3);
    }

    /**
     * Search for products by keywords
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $keywords        Search keywords
     * @param    array     $options         Search options
     * @return   array                      Search results
     */
    public function search_products($keywords, $options = array()) {
        if (!$this->auth->is_configured()) {
            return array(
                'success' => false,
                'error' => __('API credentials not configured', 'amazon-product-importer')
            );
        }

        try {
            // Prepare search parameters
            $search_params = array(
                'PartnerTag' => $this->auth->get_associate_tag(),
                'PartnerType' => 'Associates',
                'Marketplace' => $this->auth->get_marketplace(),
                'Keywords' => sanitize_text_field($keywords),
                'Resources' => $this->default_resources
            );

            // Add optional parameters
            if (isset($options['search_index'])) {
                $search_params['SearchIndex'] = sanitize_text_field($options['search_index']);
            }

            if (isset($options['sort_by'])) {
                $search_params['SortBy'] = sanitize_text_field($options['sort_by']);
            }

            if (isset($options['item_count'])) {
                $search_params['ItemCount'] = intval($options['item_count']);
            }

            if (isset($options['item_page'])) {
                $search_params['ItemPage'] = intval($options['item_page']);
            }

            // Make API request
            $response = $this->make_api_request('searchitems', $search_params);

            if (!$response['success']) {
                return $response;
            }

            // Parse search results
            $parsed_results = $this->parse_search_results($response['data']);

            $this->logger->log('info', 'Product search completed', array(
                'keywords' => $keywords,
                'results_count' => count($parsed_results)
            ));

            return array(
                'success' => true,
                'products' => $parsed_results,
                'total_results' => count($parsed_results)
            );

        } catch (Exception $e) {
            $this->logger->log('error', 'Product search failed', array(
                'keywords' => $keywords,
                'error' => $e->getMessage()
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Get product details by ASIN
     *
     * @since    1.0.0
     * @access   public
     * @param    string|array    $asins        Product ASIN(s)
     * @param    array          $options      Options for the request
     * @return   array                        Product details
     */
    public function get_product($asins, $options = array()) {
        if (!$this->auth->is_configured()) {
            return array(
                'success' => false,
                'error' => __('API credentials not configured', 'amazon-product-importer')
            );
        }

        // Convert single ASIN to array
        if (is_string($asins)) {
            $asins = array($asins);
            $single_product = true;
        } else {
            $single_product = false;
        }

        // Validate ASINs
        $validated_asins = array();
        foreach ($asins as $asin) {
            if ($this->validate_asin($asin)) {
                $validated_asins[] = $asin;
            }
        }

        if (empty($validated_asins)) {
            return array(
                'success' => false,
                'error' => __('Invalid ASIN provided', 'amazon-product-importer')
            );
        }

        try {
            // Check cache first
            if ($single_product && count($validated_asins) === 1) {
                $cached_product = $this->cache->get_product_data($validated_asins[0]);
                if ($cached_product !== false) {
                    return array(
                        'success' => true,
                        'product' => $cached_product
                    );
                }
            }

            // Prepare API parameters
            $api_params = array(
                'PartnerTag' => $this->auth->get_associate_tag(),
                'PartnerType' => 'Associates',
                'Marketplace' => $this->auth->get_marketplace(),
                'ItemIds' => $validated_asins,
                'Resources' => $this->default_resources
            );

            // Add optional parameters
            if (isset($options['offer_count'])) {
                $api_params['OfferCount'] = intval($options['offer_count']);
            }

            // Make API request
            $response = $this->make_api_request('getitems', $api_params);

            if (!$response['success']) {
                return $response;
            }

            // Parse product data
            $parsed_products = $this->parse_product_results($response['data']);

            // Cache single product
            if ($single_product && !empty($parsed_products)) {
                $this->cache->set_product_data($validated_asins[0], $parsed_products[0]);
            }

            $this->logger->log('info', 'Product details retrieved', array(
                'asins' => $validated_asins,
                'products_found' => count($parsed_products)
            ));

            if ($single_product) {
                return array(
                    'success' => true,
                    'product' => !empty($parsed_products) ? $parsed_products[0] : null
                );
            } else {
                return array(
                    'success' => true,
                    'products' => $parsed_products
                );
            }

        } catch (Exception $e) {
            $this->logger->log('error', 'Get product failed', array(
                'asins' => $validated_asins,
                'error' => $e->getMessage()
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Get product variations
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $parent_asin     Parent product ASIN
     * @param    array     $options         Options for the request
     * @return   array                      Variation details
     */
    public function get_variations($parent_asin, $options = array()) {
        if (!$this->auth->is_configured()) {
            return array(
                'success' => false,
                'error' => __('API credentials not configured', 'amazon-product-importer')
            );
        }

        if (!$this->validate_asin($parent_asin)) {
            return array(
                'success' => false,
                'error' => __('Invalid parent ASIN provided', 'amazon-product-importer')
            );
        }

        try {
            // Prepare API parameters
            $api_params = array(
                'PartnerTag' => $this->auth->get_associate_tag(),
                'PartnerType' => 'Associates',
                'Marketplace' => $this->auth->get_marketplace(),
                'ASIN' => $parent_asin,
                'Resources' => array(
                    'VariationSummary.Price.HighestPrice',
                    'VariationSummary.Price.LowestPrice',
                    'VariationSummary.VariationCount',
                    'VariationSummary.VariationDimensions'
                )
            );

            // Add optional parameters
            if (isset($options['variation_count'])) {
                $api_params['VariationCount'] = intval($options['variation_count']);
            }

            if (isset($options['variation_page'])) {
                $api_params['VariationPage'] = intval($options['variation_page']);
            }

            // Make API request
            $response = $this->make_api_request('getvariations', $api_params);

            if (!$response['success']) {
                return $response;
            }

            // Parse variation data
            $parsed_variations = $this->parse_variation_results($response['data']);

            $this->logger->log('info', 'Product variations retrieved', array(
                'parent_asin' => $parent_asin,
                'variations_found' => count($parsed_variations)
            ));

            return array(
                'success' => true,
                'variations' => $parsed_variations
            );

        } catch (Exception $e) {
            $this->logger->log('error', 'Get variations failed', array(
                'parent_asin' => $parent_asin,
                'error' => $e->getMessage()
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Make API request to PA-API 5.0
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $operation    API operation
     * @param    array     $params       Request parameters
     * @return   array                   API response
     */
    private function make_api_request($operation, $params) {
        $payload = json_encode($params);
        $endpoint = $this->auth->get_paapi_endpoint($operation);

        $headers = array(
            'content-type' => 'application/json; charset=utf-8',
            'x-amz-target' => "com.amazon.paapi5.v1.ProductAdvertisingAPIv1." . ucfirst($operation)
        );

        $auth_header = $this->auth->get_authorization_header('POST', "/paapi5/{$operation}", $headers, $payload);

        $request_headers = array_merge($headers, array(
            'Authorization' => $auth_header,
            'Host' => $this->auth->get_api_host(),
            'X-Amz-Date' => gmdate('Ymd\THis\Z')
        ));

        $attempt = 0;
        $last_error = null;

        while ($attempt < $this->retry_attempts) {
            $attempt++;

            $response = wp_remote_post($endpoint, array(
                'timeout' => $this->timeout,
                'headers' => $request_headers,
                'body' => $payload
            ));

            if (is_wp_error($response)) {
                $last_error = $response->get_error_message();
                
                if ($attempt < $this->retry_attempts) {
                    sleep(pow(2, $attempt)); // Exponential backoff
                    continue;
                }
                
                throw new Exception('HTTP request failed: ' . $last_error);
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code === 200) {
                $decoded_response = json_decode($response_body, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Invalid JSON response from API');
                }

                return array(
                    'success' => true,
                    'data' => $decoded_response
                );
            } elseif ($response_code === 429) {
                // Rate limit exceeded, wait and retry
                if ($attempt < $this->retry_attempts) {
                    sleep(pow(2, $attempt) * 2);
                    continue;
                }
            }

            // Parse error response
            $error_data = json_decode($response_body, true);
            $error_message = $error_data['message'] ?? 'Unknown API error';
            $error_type = $error_data['__type'] ?? 'UnknownError';

            $last_error = "{$error_type}: {$error_message}";

            if ($attempt < $this->retry_attempts && in_array($response_code, array(500, 502, 503, 504))) {
                sleep(pow(2, $attempt));
                continue;
            }

            break;
        }

        return array(
            'success' => false,
            'error' => $last_error,
            'error_code' => $response_code ?? null
        );
    }

    /**
     * Parse search results from API response
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $response_data    API response data
     * @return   array                      Parsed search results
     */
    private function parse_search_results($response_data) {
        $products = array();

        if (!isset($response_data['SearchResult']['Items'])) {
            return $products;
        }

        foreach ($response_data['SearchResult']['Items'] as $item) {
            $products[] = $this->parse_single_product($item);
        }

        return $products;
    }

    /**
     * Parse product results from API response
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $response_data    API response data
     * @return   array                      Parsed product results
     */
    private function parse_product_results($response_data) {
        $products = array();

        if (!isset($response_data['ItemsResult']['Items'])) {
            return $products;
        }

        foreach ($response_data['ItemsResult']['Items'] as $item) {
            $products[] = $this->parse_single_product($item);
        }

        return $products;
    }

    /**
     * Parse variation results from API response
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $response_data    API response data
     * @return   array                      Parsed variation results
     */
    private function parse_variation_results($response_data) {
        $variations = array();

        if (!isset($response_data['VariationsResult']['Items'])) {
            return $variations;
        }

        foreach ($response_data['VariationsResult']['Items'] as $item) {
            $variations[] = $this->parse_single_product($item);
        }

        return $variations;
    }

    /**
     * Parse single product from API response
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $item    Product item from API
     * @return   array             Parsed product data
     */
    private function parse_single_product($item) {
        return array(
            'ASIN' => $item['ASIN'] ?? '',
            'DetailPageURL' => $item['DetailPageURL'] ?? '',
            'ItemInfo' => $item['ItemInfo'] ?? array(),
            'Images' => $item['Images'] ?? array(),
            'Offers' => $item['Offers'] ?? array(),
            'BrowseNodeInfo' => $item['BrowseNodeInfo'] ?? array(),
            'VariationSummary' => $item['VariationSummary'] ?? array(),
            'ParentASIN' => $item['ParentASIN'] ?? null
        );
    }

    /**
     * Validate ASIN format
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $asin    ASIN to validate
     * @return   bool               True if valid, false otherwise
     */
    private function validate_asin($asin) {
        return preg_match('/^[A-Z0-9]{10}$/', $asin);
    }

    /**
     * Test API connection
     *
     * @since    1.0.0
     * @access   public
     * @return   array    Test result
     */
    public function test_connection() {
        return $this->auth->test_connection();
    }

    /**
     * Get API usage statistics (if available)
     *
     * @since    1.0.0
     * @access   public
     * @return   array    Usage statistics
     */
    public function get_api_usage() {
        // Note: PA-API 5.0 doesn't provide usage statistics directly
        // This method can be extended to track usage locally
        
        return array(
            'requests_today' => get_option('amazon_api_requests_today', 0),
            'last_reset' => get_option('amazon_api_last_reset', date('Y-m-d')),
            'rate_limit_remaining' => get_option('amazon_api_rate_limit_remaining', 1)
        );
    }

    /**
     * Update API usage statistics
     *
     * @since    1.0.0
     * @access   public
     * @param    array    $usage_data    Usage data to update
     */
    public function update_api_usage($usage_data) {
        if (isset($usage_data['requests_today'])) {
            update_option('amazon_api_requests_today', intval($usage_data['requests_today']));
        }
        
        if (isset($usage_data['rate_limit_remaining'])) {
            update_option('amazon_api_rate_limit_remaining', intval($usage_data['rate_limit_remaining']));
        }
        
        update_option('amazon_api_last_reset', date('Y-m-d'));
    }
}