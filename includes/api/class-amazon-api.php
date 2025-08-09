<?php
/**
 * Amazon Product Advertising API Interface
 *
 * This class handles all communication with the Amazon Product Advertising API,
 * including authentication, request signing, rate limiting, and response processing.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/api
 */

/**
 * Amazon API class for handling Product Advertising API requests
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/api
 * @author     Your Name <your.email@example.com>
 */
class Amazon_Product_Importer_Amazon_API {

    /**
     * API configuration
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $config    API configuration settings
     */
    private $config;

    /**
     * Request cache
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $cache    Cached API responses
     */
    private $cache;

    /**
     * Rate limiter
     *
     * @since    1.0.0
     * @access   private
     * @var      object    $rate_limiter    Rate limiting handler
     */
    private $rate_limiter;

    /**
     * Logger instance
     *
     * @since    1.0.0
     * @access   private
     * @var      object    $logger    Logger instance
     */
    private $logger;

    /**
     * Last API response
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $last_response    Last API response data
     */
    private $last_response;

    /**
     * API endpoints configuration
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $endpoints    API endpoints configuration
     */
    private $endpoints;

    /**
     * Initialize the class and set its properties
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->load_dependencies();
        $this->init_config();
        $this->init_rate_limiter();
        $this->init_logger();
        $this->load_endpoints_config();
    }

    /**
     * Load the required dependencies
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/api/class-amazon-auth.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/api/class-api-request.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/api/class-api-response-parser.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/utilities/class-logger.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/utilities/class-cache.php';
    }

    /**
     * Initialize API configuration
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_config() {
        $this->config = array(
            'access_key_id' => get_option('amazon_product_importer_access_key_id', ''),
            'secret_access_key' => get_option('amazon_product_importer_secret_access_key', ''),
            'associate_tag' => get_option('amazon_product_importer_associate_tag', ''),
            'marketplace' => get_option('amazon_product_importer_marketplace', 'www.amazon.com'),
            'region' => get_option('amazon_product_importer_region', 'us-east-1'),
            'timeout' => get_option('amazon_product_importer_api_timeout', 30),
            'retries' => get_option('amazon_product_importer_api_retries', 3),
            'retry_delay' => get_option('amazon_product_importer_api_retry_delay', 1000),
            'rate_limit' => get_option('amazon_product_importer_api_rate_limit', 1000),
            'cache_duration' => get_option('amazon_product_importer_cache_duration', 60),
            'debug_mode' => get_option('amazon_product_importer_debug_mode', false)
        );

        // Validate required configuration
        if (!$this->validate_config()) {
            throw new Exception('Invalid Amazon API configuration. Please check your settings.');
        }
    }

    /**
     * Load endpoints configuration
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_endpoints_config() {
        $endpoints_file = AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'config/api-endpoints.php';
        if (file_exists($endpoints_file)) {
            $this->endpoints = include $endpoints_file;
        } else {
            throw new Exception('API endpoints configuration file not found.');
        }
    }

    /**
     * Initialize rate limiter
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_rate_limiter() {
        $this->rate_limiter = new stdClass();
        $this->rate_limiter->requests_made = 0;
        $this->rate_limiter->last_request_time = 0;
        $this->rate_limiter->window_start = time();
    }

    /**
     * Initialize logger
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_logger() {
        $this->logger = new Amazon_Product_Importer_Logger();
    }

    /**
     * Search for products using keywords or ASIN
     *
     * @since    1.0.0
     * @param    array    $params    Search parameters
     * @return   array|WP_Error     Search results or error
     */
    public function search_products($params = array()) {
        try {
            // Validate search parameters
            $validated_params = $this->validate_search_params($params);
            if (is_wp_error($validated_params)) {
                return $validated_params;
            }

            // Check cache first
            $cache_key = 'amazon_search_' . md5(serialize($validated_params));
            $cached_result = $this->get_cached_response($cache_key);
            if ($cached_result !== false) {
                $this->logger->info('Returning cached search results', array('cache_key' => $cache_key));
                return $cached_result;
            }

            // Check rate limits
            if (!$this->check_rate_limit()) {
                return new WP_Error('rate_limit_exceeded', 'API rate limit exceeded. Please try again later.');
            }

            // Determine operation type based on parameters
            $operation = isset($params['ItemIds']) ? 'GetItems' : 'SearchItems';
            
            // Build request payload
            $payload = $this->build_request_payload($operation, $validated_params);

            // Make API request
            $response = $this->make_api_request($operation, $payload);

            if (is_wp_error($response)) {
                return $response;
            }

            // Parse and process response
            $processed_response = $this->process_search_response($response, $operation);

            // Cache the response
            $this->cache_response($cache_key, $processed_response);

            return $processed_response;

        } catch (Exception $e) {
            $this->logger->error('Search products failed', array(
                'error' => $e->getMessage(),
                'params' => $params
            ));
            return new WP_Error('api_error', $e->getMessage());
        }
    }

    /**
     * Get detailed product information by ASIN
     *
     * @since    1.0.0
     * @param    string|array    $asins    Single ASIN or array of ASINs
     * @return   array|WP_Error           Product details or error
     */
    public function get_product_details($asins) {
        try {
            // Normalize ASINs to array
            if (is_string($asins)) {
                $asins = array($asins);
            }

            // Validate ASINs
            $validated_asins = $this->validate_asins($asins);
            if (is_wp_error($validated_asins)) {
                return $validated_asins;
            }

            // Check cache for each ASIN
            $cache_results = array();
            $uncached_asins = array();

            foreach ($validated_asins as $asin) {
                $cache_key = 'amazon_product_' . $asin;
                $cached_result = $this->get_cached_response($cache_key);
                
                if ($cached_result !== false) {
                    $cache_results[$asin] = $cached_result;
                } else {
                    $uncached_asins[] = $asin;
                }
            }

            // If all results are cached, return them
            if (empty($uncached_asins)) {
                return count($cache_results) === 1 ? reset($cache_results) : $cache_results;
            }

            // Check rate limits
            if (!$this->check_rate_limit()) {
                return new WP_Error('rate_limit_exceeded', 'API rate limit exceeded. Please try again later.');
            }

            // Build request for uncached items
            $params = array(
                'ItemIds' => $uncached_asins,
                'Resources' => $this->get_default_resources()
            );

            $payload = $this->build_request_payload('GetItems', $params);

            // Make API request
            $response = $this->make_api_request('GetItems', $payload);

            if (is_wp_error($response)) {
                return $response;
            }

            // Process response
            $processed_response = $this->process_product_details_response($response);

            // Cache individual products
            if (isset($processed_response['items'])) {
                foreach ($processed_response['items'] as $item) {
                    if (isset($item['ASIN'])) {
                        $cache_key = 'amazon_product_' . $item['ASIN'];
                        $this->cache_response($cache_key, $item);
                        $cache_results[$item['ASIN']] = $item;
                    }
                }
            }

            // Return appropriate format
            if (count($validated_asins) === 1) {
                return isset($cache_results[$validated_asins[0]]) ? $cache_results[$validated_asins[0]] : null;
            }

            return $cache_results;

        } catch (Exception $e) {
            $this->logger->error('Get product details failed', array(
                'error' => $e->getMessage(),
                'asins' => $asins
            ));
            return new WP_Error('api_error', $e->getMessage());
        }
    }

    /**
     * Get product variations for a parent ASIN
     *
     * @since    1.0.0
     * @param    string    $parent_asin    Parent product ASIN
     * @param    array     $params         Additional parameters
     * @return   array|WP_Error           Variations data or error
     */
    public function get_product_variations($parent_asin, $params = array()) {
        try {
            // Validate parent ASIN
            if (!$this->validate_asin($parent_asin)) {
                return new WP_Error('invalid_asin', 'Invalid parent ASIN format.');
            }

            // Check cache
            $cache_key = 'amazon_variations_' . $parent_asin . '_' . md5(serialize($params));
            $cached_result = $this->get_cached_response($cache_key);
            if ($cached_result !== false) {
                return $cached_result;
            }

            // Check rate limits
            if (!$this->check_rate_limit()) {
                return new WP_Error('rate_limit_exceeded', 'API rate limit exceeded. Please try again later.');
            }

            // Build request parameters
            $request_params = array_merge(array(
                'ASIN' => $parent_asin,
                'Resources' => $this->get_default_resources(),
                'VariationCount' => 10,
                'VariationPage' => 1
            ), $params);

            $payload = $this->build_request_payload('GetVariations', $request_params);

            // Make API request
            $response = $this->make_api_request('GetVariations', $payload);

            if (is_wp_error($response)) {
                return $response;
            }

            // Process response
            $processed_response = $this->process_variations_response($response);

            // Cache the response
            $this->cache_response($cache_key, $processed_response);

            return $processed_response;

        } catch (Exception $e) {
            $this->logger->error('Get product variations failed', array(
                'error' => $e->getMessage(),
                'parent_asin' => $parent_asin,
                'params' => $params
            ));
            return new WP_Error('api_error', $e->getMessage());
        }
    }

    /**
     * Get browse node information
     *
     * @since    1.0.0
     * @param    array    $browse_node_ids    Browse node IDs
     * @return   array|WP_Error               Browse node data or error
     */
    public function get_browse_nodes($browse_node_ids) {
        try {
            // Validate browse node IDs
            if (empty($browse_node_ids) || !is_array($browse_node_ids)) {
                return new WP_Error('invalid_browse_nodes', 'Invalid browse node IDs.');
            }

            // Check cache
            $cache_key = 'amazon_browse_nodes_' . md5(serialize($browse_node_ids));
            $cached_result = $this->get_cached_response($cache_key);
            if ($cached_result !== false) {
                return $cached_result;
            }

            // Check rate limits
            if (!$this->check_rate_limit()) {
                return new WP_Error('rate_limit_exceeded', 'API rate limit exceeded. Please try again later.');
            }

            // Build request parameters
            $params = array(
                'BrowseNodeIds' => $browse_node_ids,
                'Resources' => array(
                    'BrowseNodeInfo.BrowseNodes',
                    'BrowseNodeInfo.BrowseNodes.Ancestor',
                    'BrowseNodeInfo.BrowseNodes.Children'
                )
            );

            $payload = $this->build_request_payload('GetBrowseNodes', $params);

            // Make API request
            $response = $this->make_api_request('GetBrowseNodes', $payload);

            if (is_wp_error($response)) {
                return $response;
            }

            // Process response
            $processed_response = $this->process_browse_nodes_response($response);

            // Cache the response
            $this->cache_response($cache_key, $processed_response, DAY_IN_SECONDS);

            return $processed_response;

        } catch (Exception $e) {
            $this->logger->error('Get browse nodes failed', array(
                'error' => $e->getMessage(),
                'browse_node_ids' => $browse_node_ids
            ));
            return new WP_Error('api_error', $e->getMessage());
        }
    }

    /**
     * Test API connection and credentials
     *
     * @since    1.0.0
     * @return   bool|WP_Error    True if successful, error otherwise
     */
    public function test_connection() {
        try {
            $this->logger->info('Testing API connection');

            // Use a simple search with a common keyword to test
            $test_params = array(
                'Keywords' => 'book',
                'SearchIndex' => 'Books',
                'ItemCount' => 1
            );

            $result = $this->search_products($test_params);

            if (is_wp_error($result)) {
                $this->logger->error('API connection test failed', array('error' => $result->get_error_message()));
                return $result;
            }

            $this->logger->info('API connection test successful');
            return true;

        } catch (Exception $e) {
            $this->logger->error('API connection test exception', array('error' => $e->getMessage()));
            return new WP_Error('connection_test_failed', $e->getMessage());
        }
    }

    /**
     * Make API request to Amazon
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $operation    API operation
     * @param    array     $payload      Request payload
     * @return   array|WP_Error         Response or error
     */
    private function make_api_request($operation, $payload) {
        try {
            // Get endpoint configuration
            $endpoint_config = $this->get_endpoint_config($operation);
            if (!$endpoint_config) {
                throw new Exception("Unsupported operation: {$operation}");
            }

            // Create request instance
            $request = new Amazon_Product_Importer_API_Request($this->config, $this->endpoints);

            // Build and sign request
            $signed_request = $request->build_request($operation, $payload);

            if (is_wp_error($signed_request)) {
                return $signed_request;
            }

            // Record rate limit
            $this->record_api_request();

            // Log request if debug mode is enabled
            if ($this->config['debug_mode']) {
                $this->logger->debug('Making API request', array(
                    'operation' => $operation,
                    'url' => $signed_request['url'],
                    'payload_size' => strlen(json_encode($payload))
                ));
            }

            // Make HTTP request
            $response = wp_remote_post($signed_request['url'], array(
                'headers' => $signed_request['headers'],
                'body' => $signed_request['body'],
                'timeout' => $this->config['timeout'],
                'sslverify' => true,
                'user-agent' => $this->get_user_agent()
            ));

            // Check for HTTP errors
            if (is_wp_error($response)) {
                throw new Exception('HTTP request failed: ' . $response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            // Log response if debug mode is enabled
            if ($this->config['debug_mode']) {
                $this->logger->debug('API response received', array(
                    'response_code' => $response_code,
                    'response_size' => strlen($response_body)
                ));
            }

            // Check response code
            if ($response_code !== 200) {
                $error_data = json_decode($response_body, true);
                $error_message = isset($error_data['Errors'][0]['Message']) 
                    ? $error_data['Errors'][0]['Message'] 
                    : "HTTP {$response_code} error";
                    
                throw new Exception($error_message);
            }

            // Parse JSON response
            $parsed_response = json_decode($response_body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response: ' . json_last_error_msg());
            }

            // Store last response
            $this->last_response = $parsed_response;

            // Check for API errors
            if (isset($parsed_response['Errors'])) {
                $error = $parsed_response['Errors'][0];
                throw new Exception($error['Message'], $error['Code']);
            }

            return $parsed_response;

        } catch (Exception $e) {
            $this->logger->error('API request failed', array(
                'operation' => $operation,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ));

            // Handle specific error codes
            $error_code = $this->map_error_code($e->getCode());
            return new WP_Error($error_code, $e->getMessage());
        }
    }

    /**
     * Build request payload for API operation
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $operation    API operation
     * @param    array     $params       Request parameters
     * @return   array                   Request payload
     */
    private function build_request_payload($operation, $params) {
        $default_params = array(
            'PartnerTag' => $this->config['associate_tag'],
            'PartnerType' => 'Associates',
            'Marketplace' => $this->config['marketplace']
        );

        // Merge with operation-specific defaults
        $operation_defaults = $this->get_operation_defaults($operation);
        $payload = array_merge($default_params, $operation_defaults, $params);

        // Remove empty values
        $payload = array_filter($payload, function($value) {
            return $value !== '' && $value !== null && $value !== array();
        });

        return $payload;
    }

    /**
     * Process search response
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $response     Raw API response
     * @param    string   $operation    API operation
     * @return   array                  Processed response
     */
    private function process_search_response($response, $operation) {
        $parser = new Amazon_Product_Importer_API_Response_Parser();
        return $parser->parse_search_response($response, $operation);
    }

    /**
     * Process product details response
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $response    Raw API response
     * @return   array                 Processed response
     */
    private function process_product_details_response($response) {
        $parser = new Amazon_Product_Importer_API_Response_Parser();
        return $parser->parse_product_details_response($response);
    }

    /**
     * Process variations response
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $response    Raw API response
     * @return   array                 Processed response
     */
    private function process_variations_response($response) {
        $parser = new Amazon_Product_Importer_API_Response_Parser();
        return $parser->parse_variations_response($response);
    }

    /**
     * Process browse nodes response
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $response    Raw API response
     * @return   array                 Processed response
     */
    private function process_browse_nodes_response($response) {
        $parser = new Amazon_Product_Importer_API_Response_Parser();
        return $parser->parse_browse_nodes_response($response);
    }

    /**
     * Validate API configuration
     *
     * @since    1.0.0
     * @access   private
     * @return   bool    True if valid, false otherwise
     */
    private function validate_config() {
        $required_fields = array('access_key_id', 'secret_access_key', 'associate_tag');
        
        foreach ($required_fields as $field) {
            if (empty($this->config[$field])) {
                return false;
            }
        }

        // Validate ASIN format for associate tag
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $this->config['associate_tag'])) {
            return false;
        }

        return true;
    }

    /**
     * Validate search parameters
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $params    Search parameters
     * @return   array|WP_Error     Validated parameters or error
     */
    private function validate_search_params($params) {
        $validated = array();

        // Validate keywords
        if (isset($params['Keywords'])) {
            $keywords = trim($params['Keywords']);
            if (strlen($keywords) < 2 || strlen($keywords) > 255) {
                return new WP_Error('invalid_keywords', 'Keywords must be between 2 and 255 characters.');
            }
            $validated['Keywords'] = $keywords;
        }

        // Validate ItemIds (ASINs)
        if (isset($params['ItemIds'])) {
            $asins = is_array($params['ItemIds']) ? $params['ItemIds'] : array($params['ItemIds']);
            $validated_asins = $this->validate_asins($asins);
            if (is_wp_error($validated_asins)) {
                return $validated_asins;
            }
            $validated['ItemIds'] = $validated_asins;
        }

        // Validate SearchIndex
        if (isset($params['SearchIndex'])) {
            $search_index = $params['SearchIndex'];
            $valid_indices = array_keys($this->endpoints['search_indices']['global']);
            if (!in_array($search_index, $valid_indices)) {
                return new WP_Error('invalid_search_index', 'Invalid search index.');
            }
            $validated['SearchIndex'] = $search_index;
        }

        // Validate ItemCount
        if (isset($params['ItemCount'])) {
            $item_count = intval($params['ItemCount']);
            if ($item_count < 1 || $item_count > 10) {
                return new WP_Error('invalid_item_count', 'Item count must be between 1 and 10.');
            }
            $validated['ItemCount'] = $item_count;
        }

        // Validate ItemPage
        if (isset($params['ItemPage'])) {
            $item_page = intval($params['ItemPage']);
            if ($item_page < 1 || $item_page > 10) {
                return new WP_Error('invalid_item_page', 'Item page must be between 1 and 10.');
            }
            $validated['ItemPage'] = $item_page;
        }

        // Validate price range
        if (isset($params['MinPrice']) || isset($params['MaxPrice'])) {
            $min_price = isset($params['MinPrice']) ? intval($params['MinPrice']) : 0;
            $max_price = isset($params['MaxPrice']) ? intval($params['MaxPrice']) : PHP_INT_MAX;
            
            if ($min_price < 0 || $max_price < 0 || $min_price > $max_price) {
                return new WP_Error('invalid_price_range', 'Invalid price range.');
            }
            
            if ($min_price > 0) $validated['MinPrice'] = $min_price;
            if ($max_price < PHP_INT_MAX) $validated['MaxPrice'] = $max_price;
        }

        // Add other optional parameters
        $optional_params = array('SortBy', 'Condition', 'Merchant', 'Brand', 'Resources');
        foreach ($optional_params as $param) {
            if (isset($params[$param])) {
                $validated[$param] = $params[$param];
            }
        }

        return $validated;
    }

    /**
     * Validate ASINs
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $asins    Array of ASINs
     * @return   array|WP_Error     Validated ASINs or error
     */
    private function validate_asins($asins) {
        if (empty($asins) || !is_array($asins)) {
            return new WP_Error('invalid_asins', 'ASINs must be a non-empty array.');
        }

        if (count($asins) > 10) {
            return new WP_Error('too_many_asins', 'Maximum 10 ASINs allowed per request.');
        }

        $validated_asins = array();
        foreach ($asins as $asin) {
            if (!$this->validate_asin($asin)) {
                return new WP_Error('invalid_asin', "Invalid ASIN format: {$asin}");
            }
            $validated_asins[] = strtoupper(trim($asin));
        }

        return array_unique($validated_asins);
    }

    /**
     * Validate single ASIN
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $asin    ASIN to validate
     * @return   bool               True if valid, false otherwise
     */
    private function validate_asin($asin) {
        return preg_match('/^[A-Z0-9]{10}$/', strtoupper(trim($asin)));
    }

    /**
     * Check rate limits
     *
     * @since    1.0.0
     * @access   private
     * @return   bool    True if request is allowed, false otherwise
     */
    private function check_rate_limit() {
        $current_time = time();
        $window_duration = 3600; // 1 hour

        // Reset window if needed
        if ($current_time - $this->rate_limiter->window_start >= $window_duration) {
            $this->rate_limiter->requests_made = 0;
            $this->rate_limiter->window_start = $current_time;
        }

        // Check if we've exceeded the rate limit
        if ($this->rate_limiter->requests_made >= $this->config['rate_limit']) {
            return false;
        }

        // Check minimum delay between requests
        $min_delay = 1; // 1 second minimum
        if ($current_time - $this->rate_limiter->last_request_time < $min_delay) {
            sleep($min_delay);
        }

        return true;
    }

    /**
     * Record API request for rate limiting
     *
     * @since    1.0.0
     * @access   private
     */
    private function record_api_request() {
        $this->rate_limiter->requests_made++;
        $this->rate_limiter->last_request_time = time();

        // Store in database for persistence
        update_option('amazon_api_rate_limiter', $this->rate_limiter);
    }

    /**
     * Get cached response
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $cache_key    Cache key
     * @return   mixed                   Cached data or false
     */
    private function get_cached_response($cache_key) {
        if (!get_option('amazon_product_importer_enable_cache', true)) {
            return false;
        }

        return get_transient($cache_key);
    }

    /**
     * Cache response
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $cache_key    Cache key
     * @param    mixed     $data         Data to cache
     * @param    int       $expiration   Cache expiration in seconds
     */
    private function cache_response($cache_key, $data, $expiration = null) {
        if (!get_option('amazon_product_importer_enable_cache', true)) {
            return;
        }

        if ($expiration === null) {
            $expiration = $this->config['cache_duration'] * MINUTE_IN_SECONDS;
        }

        set_transient($cache_key, $data, $expiration);
    }

    /**
     * Get endpoint configuration for operation
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $operation    API operation
     * @return   array|false             Endpoint config or false
     */
    private function get_endpoint_config($operation) {
        return isset($this->endpoints['operations'][$operation]) 
            ? $this->endpoints['operations'][$operation] 
            : false;
    }

    /**
     * Get operation defaults
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $operation    API operation
     * @return   array                   Default parameters
     */
    private function get_operation_defaults($operation) {
        $defaults = isset($this->endpoints['default_params']) 
            ? $this->endpoints['default_params'] 
            : array();

        // Add operation-specific defaults
        switch ($operation) {
            case 'SearchItems':
                $defaults['ItemCount'] = 10;
                $defaults['ItemPage'] = 1;
                break;
            case 'GetItems':
                break;
            case 'GetVariations':
                $defaults['VariationCount'] = 10;
                $defaults['VariationPage'] = 1;
                break;
        }

        return $defaults;
    }

    /**
     * Get default resources to request
     *
     * @since    1.0.0
     * @access   private
     * @return   array    Default resources
     */
    private function get_default_resources() {
        return isset($this->endpoints['default_params']['Resources']) 
            ? $this->endpoints['default_params']['Resources'] 
            : array(
                'ItemInfo.Title',
                'ItemInfo.Features',
                'Images.Primary.Large',
                'Offers.Listings.Price'
            );
    }

    /**
     * Get user agent string
     *
     * @since    1.0.0
     * @access   private
     * @return   string    User agent string
     */
    private function get_user_agent() {
        return sprintf(
            'AmazonProductImporter/%s (WordPress/%s; %s)',
            AMAZON_PRODUCT_IMPORTER_VERSION,
            get_bloginfo('version'),
            home_url()
        );
    }

    /**
     * Map API error code to WordPress error code
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $api_error_code    API error code
     * @return   string                       WordPress error code
     */
    private function map_error_code($api_error_code) {
        $error_map = array(
            'InvalidParameterValue' => 'invalid_parameter',
            'MissingParameter' => 'missing_parameter',
            'RequestThrottled' => 'rate_limit_exceeded',
            'InvalidAssociate' => 'invalid_credentials',
            'AccessDenied' => 'access_denied',
            'ItemsNotFound' => 'items_not_found',
            'TooManyRequests' => 'rate_limit_exceeded'
        );

        return isset($error_map[$api_error_code]) ? $error_map[$api_error_code] : 'api_error';
    }

    /**
     * Get total pages from last search response
     *
     * @since    1.0.0
     * @return   int    Total pages
     */
    public function get_total_pages() {
        if (isset($this->last_response['SearchResult']['TotalResultPages'])) {
            return intval($this->last_response['SearchResult']['TotalResultPages']);
        }
        return 1;
    }

    /**
     * Get total results count from last search response
     *
     * @since    1.0.0
     * @return   int    Total results count
     */
    public function get_total_results() {
        if (isset($this->last_response['SearchResult']['TotalResultCount'])) {
            return intval($this->last_response['SearchResult']['TotalResultCount']);
        }
        return 0;
    }

    /**
     * Get API configuration
     *
     * @since    1.0.0
     * @return   array    API configuration
     */
    public function get_config() {
        return $this->config;
    }

    /**
     * Get rate limiter status
     *
     * @since    1.0.0
     * @return   array    Rate limiter status
     */
    public function get_rate_limiter_status() {
        return array(
            'requests_made' => $this->rate_limiter->requests_made,
            'rate_limit' => $this->config['rate_limit'],
            'remaining' => max(0, $this->config['rate_limit'] - $this->rate_limiter->requests_made),
            'window_start' => $this->rate_limiter->window_start,
            'next_reset' => $this->rate_limiter->window_start + 3600
        );
    }

    /**
     * Clear API cache
     *
     * @since    1.0.0
     * @param    string    $pattern    Cache key pattern (optional)
     * @return   bool                  True on success
     */
    public function clear_cache($pattern = null) {
        global $wpdb;

        if ($pattern) {
            $like_pattern = $wpdb->esc_like($pattern) . '%';
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . $like_pattern
            ));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_' . $like_pattern
            ));
        } else {
            // Clear all Amazon-related transients
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_amazon_%' OR option_name LIKE '_transient_timeout_amazon_%'"
            );
        }

        return true;
    }

    /**
     * Get cache statistics
     *
     * @since    1.0.0
     * @return   array    Cache statistics
     */
    public function get_cache_stats() {
        global $wpdb;

        $cache_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_amazon_%'"
        );

        $cache_size = $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE '_transient_amazon_%'"
        );

        return array(
            'cached_items' => intval($cache_count),
            'cache_size_bytes' => intval($cache_size),
            'cache_size_mb' => round($cache_size / 1024 / 1024, 2)
        );
    }
}