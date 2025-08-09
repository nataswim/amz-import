<?php
/**
 * Amazon API Request Builder
 *
 * This class handles the construction and execution of HTTP requests
 * to the Amazon Product Advertising API, including payload formatting,
 * header management, and request validation.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/api
 */

/**
 * API request builder class for Amazon Product Advertising API
 *
 * Constructs and executes HTTP requests to Amazon PA-API with proper
 * authentication, headers, and payload formatting.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/api
 * @author     Your Name <your.email@example.com>
 */
class Amazon_Product_Importer_API_Request {

    /**
     * API configuration
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $config    API configuration settings
     */
    private $config;

    /**
     * Endpoints configuration
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $endpoints    API endpoints configuration
     */
    private $endpoints;

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
     * @var      object    $logger    Logger instance
     */
    private $logger;

    /**
     * Request statistics
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $stats    Request statistics
     */
    private $stats;

    /**
     * Debug mode flag
     *
     * @since    1.0.0
     * @access   private
     * @var      bool    $debug_mode    Debug mode flag
     */
    private $debug_mode;

    /**
     * Request timeout
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $timeout    Request timeout in seconds
     */
    private $timeout;

    /**
     * User agent string
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $user_agent    User agent string
     */
    private $user_agent;

    /**
     * Initialize the request builder
     *
     * @since    1.0.0
     * @param    array    $config      API configuration
     * @param    array    $endpoints   Endpoints configuration
     */
    public function __construct($config, $endpoints) {
        $this->config = $config;
        $this->endpoints = $endpoints;
        $this->debug_mode = isset($config['debug_mode']) ? $config['debug_mode'] : false;
        $this->timeout = isset($config['timeout']) ? $config['timeout'] : 30;

        $this->init_authentication();
        $this->init_logger();
        $this->init_user_agent();
        $this->init_stats();
    }

    /**
     * Build API request
     *
     * @since    1.0.0
     * @param    string    $operation    API operation name
     * @param    array     $payload      Request payload
     * @return   array|WP_Error         Built request or error
     */
    public function build_request($operation, $payload) {
        try {
            // Validate operation
            if (!$this->is_valid_operation($operation)) {
                return new WP_Error('invalid_operation', "Unsupported operation: {$operation}");
            }

            // Get operation configuration
            $operation_config = $this->get_operation_config($operation);

            // Validate payload
            $validated_payload = $this->validate_payload($operation, $payload);
            if (is_wp_error($validated_payload)) {
                return $validated_payload;
            }

            // Get endpoint URL
            $endpoint_url = $this->get_endpoint_url($operation);
            if (!$endpoint_url) {
                return new WP_Error('invalid_endpoint', 'Could not determine endpoint URL');
            }

            // Build request components
            $method = $operation_config['method'];
            $headers = $this->build_headers($operation, $validated_payload);
            $body = $this->build_request_body($operation, $validated_payload);

            // Sign the request
            $signed_request = $this->sign_request($method, $endpoint_url, $headers, $body);
            if (is_wp_error($signed_request)) {
                return $signed_request;
            }

            // Compile final request
            $request = array(
                'method' => $method,
                'url' => $endpoint_url,
                'headers' => $signed_request['headers'],
                'body' => $body,
                'timeout' => $this->timeout,
                'user_agent' => $this->user_agent,
                'operation' => $operation,
                'payload_size' => strlen($body)
            );

            // Log request if debug mode is enabled
            if ($this->debug_mode) {
                $this->log_request($request);
            }

            // Update statistics
            $this->update_request_stats($operation, strlen($body));

            return $request;

        } catch (Exception $e) {
            $this->logger->error('Request building failed', array(
                'operation' => $operation,
                'error' => $e->getMessage(),
                'payload_keys' => array_keys($payload)
            ));

            return new WP_Error('request_build_error', $e->getMessage());
        }
    }

    /**
     * Execute API request
     *
     * @since    1.0.0
     * @param    array    $request    Built request array
     * @return   array|WP_Error      Response or error
     */
    public function execute_request($request) {
        try {
            $start_time = microtime(true);

            // Validate request structure
            if (!$this->is_valid_request($request)) {
                return new WP_Error('invalid_request_structure', 'Invalid request structure');
            }

            // Prepare WordPress HTTP API arguments
            $http_args = array(
                'method' => $request['method'],
                'headers' => $request['headers'],
                'body' => $request['body'],
                'timeout' => $request['timeout'],
                'user-agent' => $request['user_agent'],
                'sslverify' => true,
                'compress' => true,
                'decompress' => true,
                'stream' => false,
                'filename' => null,
                'limit_response_size' => 1048576 // 1MB limit
            );

            // Add custom HTTP version if specified
            if (isset($this->config['http_version'])) {
                $http_args['httpversion'] = $this->config['http_version'];
            }

            // Log request execution start
            $this->logger->info('Executing API request', array(
                'operation' => $request['operation'],
                'url' => $request['url'],
                'method' => $request['method'],
                'payload_size' => $request['payload_size']
            ));

            // Execute HTTP request
            $response = wp_remote_request($request['url'], $http_args);

            $execution_time = microtime(true) - $start_time;

            // Check for WordPress HTTP errors
            if (is_wp_error($response)) {
                $this->logger->error('HTTP request failed', array(
                    'operation' => $request['operation'],
                    'error' => $response->get_error_message(),
                    'execution_time' => $execution_time
                ));

                return new WP_Error('http_request_failed', $response->get_error_message());
            }

            // Process response
            $processed_response = $this->process_response($response, $request, $execution_time);

            return $processed_response;

        } catch (Exception $e) {
            $this->logger->error('Request execution failed', array(
                'operation' => isset($request['operation']) ? $request['operation'] : 'unknown',
                'error' => $e->getMessage()
            ));

            return new WP_Error('request_execution_error', $e->getMessage());
        }
    }

    /**
     * Build complete request and execute it
     *
     * @since    1.0.0
     * @param    string    $operation    API operation name
     * @param    array     $payload      Request payload
     * @return   array|WP_Error         Response or error
     */
    public function make_request($operation, $payload) {
        // Build request
        $request = $this->build_request($operation, $payload);
        if (is_wp_error($request)) {
            return $request;
        }

        // Execute request
        $response = $this->execute_request($request);

        return $response;
    }

    /**
     * Initialize authentication handler
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_authentication() {
        $auth_config = array(
            'access_key_id' => $this->config['access_key_id'],
            'secret_access_key' => $this->config['secret_access_key'],
            'region' => $this->config['region'],
            'service' => isset($this->endpoints['api_version']) ? 'ProductAdvertisingAPI' : 'ProductAdvertisingAPI',
            'debug_mode' => $this->debug_mode
        );

        $this->auth = new Amazon_Product_Importer_Amazon_Auth($auth_config);
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
     * Initialize user agent string
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_user_agent() {
        $this->user_agent = sprintf(
            'AmazonProductImporter/%s (WordPress/%s; PHP/%s; %s)',
            AMAZON_PRODUCT_IMPORTER_VERSION,
            get_bloginfo('version'),
            PHP_VERSION,
            home_url()
        );
    }

    /**
     * Initialize request statistics
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_stats() {
        $this->stats = array(
            'requests_made' => 0,
            'total_payload_size' => 0,
            'total_response_size' => 0,
            'total_execution_time' => 0,
            'operations' => array(),
            'errors' => array()
        );
    }

    /**
     * Validate operation
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $operation    Operation name
     * @return   bool                    True if valid, false otherwise
     */
    private function is_valid_operation($operation) {
        return isset($this->endpoints['operations'][$operation]);
    }

    /**
     * Get operation configuration
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $operation    Operation name
     * @return   array                   Operation configuration
     */
    private function get_operation_config($operation) {
        return $this->endpoints['operations'][$operation];
    }

    /**
     * Validate request payload
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $operation    Operation name
     * @param    array     $payload      Request payload
     * @return   array|WP_Error         Validated payload or error
     */
    private function validate_payload($operation, $payload) {
        $operation_config = $this->get_operation_config($operation);
        $validated_payload = array();

        // Check required parameters
        if (isset($operation_config['required_params'])) {
            foreach ($operation_config['required_params'] as $required_param) {
                if (!isset($payload[$required_param])) {
                    return new WP_Error(
                        'missing_required_parameter',
                        "Missing required parameter: {$required_param}"
                    );
                }
                $validated_payload[$required_param] = $payload[$required_param];
            }
        }

        // Add optional parameters
        if (isset($operation_config['optional_params'])) {
            foreach ($operation_config['optional_params'] as $optional_param) {
                if (isset($payload[$optional_param])) {
                    $validated_payload[$optional_param] = $payload[$optional_param];
                }
            }
        }

        // Validate operation-specific constraints
        $constraint_validation = $this->validate_operation_constraints($operation, $validated_payload);
        if (is_wp_error($constraint_validation)) {
            return $constraint_validation;
        }

        // Ensure required defaults are set
        $validated_payload = $this->apply_operation_defaults($operation, $validated_payload);

        return $validated_payload;
    }

    /**
     * Validate operation-specific constraints
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $operation    Operation name
     * @param    array     $payload      Payload to validate
     * @return   bool|WP_Error          True if valid, error otherwise
     */
    private function validate_operation_constraints($operation, $payload) {
        $operation_config = $this->get_operation_config($operation);

        switch ($operation) {
            case 'SearchItems':
                // Validate ItemCount
                if (isset($payload['ItemCount'])) {
                    $max_items = isset($operation_config['max_item_count']) ? $operation_config['max_item_count'] : 10;
                    if ($payload['ItemCount'] > $max_items) {
                        return new WP_Error('invalid_item_count', "ItemCount cannot exceed {$max_items}");
                    }
                }

                // Validate ItemPage
                if (isset($payload['ItemPage'])) {
                    $max_pages = isset($operation_config['max_item_page']) ? $operation_config['max_item_page'] : 10;
                    if ($payload['ItemPage'] > $max_pages) {
                        return new WP_Error('invalid_item_page', "ItemPage cannot exceed {$max_pages}");
                    }
                }

                // Validate Keywords or ItemIds presence
                if (!isset($payload['Keywords']) && !isset($payload['ItemIds'])) {
                    return new WP_Error('missing_search_criteria', 'Either Keywords or ItemIds must be provided');
                }
                break;

            case 'GetItems':
                // Validate ItemIds count
                if (isset($payload['ItemIds'])) {
                    $max_items = isset($operation_config['max_items']) ? $operation_config['max_items'] : 10;
                    $item_count = is_array($payload['ItemIds']) ? count($payload['ItemIds']) : 1;
                    if ($item_count > $max_items) {
                        return new WP_Error('too_many_items', "Cannot request more than {$max_items} items");
                    }
                }
                break;

            case 'GetVariations':
                // Validate ASIN
                if (isset($payload['ASIN']) && !preg_match('/^[A-Z0-9]{10}$/', $payload['ASIN'])) {
                    return new WP_Error('invalid_asin', 'Invalid ASIN format');
                }

                // Validate VariationCount
                if (isset($payload['VariationCount'])) {
                    $max_variations = isset($operation_config['max_variation_count']) ? $operation_config['max_variation_count'] : 10;
                    if ($payload['VariationCount'] > $max_variations) {
                        return new WP_Error('invalid_variation_count', "VariationCount cannot exceed {$max_variations}");
                    }
                }
                break;

            case 'GetBrowseNodes':
                // Validate BrowseNodeIds count
                if (isset($payload['BrowseNodeIds'])) {
                    $max_nodes = isset($operation_config['max_browse_nodes']) ? $operation_config['max_browse_nodes'] : 10;
                    $node_count = is_array($payload['BrowseNodeIds']) ? count($payload['BrowseNodeIds']) : 1;
                    if ($node_count > $max_nodes) {
                        return new WP_Error('too_many_browse_nodes', "Cannot request more than {$max_nodes} browse nodes");
                    }
                }
                break;
        }

        return true;
    }

    /**
     * Apply operation defaults
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $operation    Operation name
     * @param    array     $payload      Current payload
     * @return   array                   Payload with defaults applied
     */
    private function apply_operation_defaults($operation, $payload) {
        // Get default parameters from endpoints configuration
        $defaults = isset($this->endpoints['default_params']) ? $this->endpoints['default_params'] : array();

        // Apply operation-specific defaults
        switch ($operation) {
            case 'SearchItems':
                $defaults = array_merge($defaults, array(
                    'ItemCount' => 10,
                    'ItemPage' => 1,
                    'SearchIndex' => 'All'
                ));
                break;

            case 'GetVariations':
                $defaults = array_merge($defaults, array(
                    'VariationCount' => 10,
                    'VariationPage' => 1
                ));
                break;
        }

        // Merge defaults with payload (payload takes precedence)
        return array_merge($defaults, $payload);
    }

    /**
     * Get endpoint URL for operation
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $operation    Operation name
     * @return   string|false           Endpoint URL or false
     */
    private function get_endpoint_url($operation) {
        $region = $this->config['region'];
        
        // Get region configuration
        if (!isset($this->endpoints['regions'][$region])) {
            return false;
        }

        $region_config = $this->endpoints['regions'][$region];
        $base_url = $region_config['base_url'];
        
        // Get operation path
        $operation_config = $this->get_operation_config($operation);
        $path = $operation_config['path'];

        return $base_url . $path;
    }

    /**
     * Build request headers
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $operation    Operation name
     * @param    array     $payload      Request payload
     * @return   array                   Request headers
     */
    private function build_headers($operation, $payload) {
        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Amz-Target' => $this->build_amz_target($operation),
            'User-Agent' => $this->user_agent
        );

        // Add marketplace-specific headers if needed
        $marketplace = isset($payload['Marketplace']) ? $payload['Marketplace'] : $this->config['marketplace'];
        if ($marketplace && isset($this->endpoints['marketplaces'][$marketplace])) {
            $marketplace_config = $this->endpoints['marketplaces'][$marketplace];
            
            // Add language preference
            if (isset($marketplace_config['language'])) {
                $headers['Accept-Language'] = $marketplace_config['language'];
            }
        }

        // Add custom headers from configuration
        if (isset($this->config['custom_headers']) && is_array($this->config['custom_headers'])) {
            $headers = array_merge($headers, $this->config['custom_headers']);
        }

        return $headers;
    }

    /**
     * Build X-Amz-Target header value
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $operation    Operation name
     * @return   string                  X-Amz-Target header value
     */
    private function build_amz_target($operation) {
        $api_version = isset($this->endpoints['api_version']) ? $this->endpoints['api_version'] : '2020-10-01';
        $service_name = isset($this->endpoints['service_name']) ? $this->endpoints['service_name'] : 'ProductAdvertisingAPI';
        
        return "com.amazon.paapi5.v1.{$service_name}v1.{$operation}";
    }

    /**
     * Build request body
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $operation    Operation name
     * @param    array     $payload      Request payload
     * @return   string                  JSON-encoded request body
     */
    private function build_request_body($operation, $payload) {
        // Ensure consistent encoding
        $json_body = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($json_body === false) {
            throw new Exception('Failed to encode request payload as JSON: ' . json_last_error_msg());
        }

        return $json_body;
    }

    /**
     * Sign the request
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $method     HTTP method
     * @param    string    $url        Request URL
     * @param    array     $headers    Request headers
     * @param    string    $body       Request body
     * @return   array|WP_Error       Signed request data or error
     */
    private function sign_request($method, $url, $headers, $body) {
        try {
            $signed_request = $this->auth->sign_request($method, $url, $headers, $body);
            
            if ($this->debug_mode) {
                $this->logger->debug('Request signed successfully', array(
                    'method' => $method,
                    'url' => $url,
                    'signature_preview' => substr($signed_request['signature'], 0, 8) . '...'
                ));
            }

            return $signed_request;

        } catch (Exception $e) {
            $this->logger->error('Request signing failed', array(
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage()
            ));

            return new WP_Error('signing_failed', $e->getMessage());
        }
    }

    /**
     * Validate request structure
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $request    Request array
     * @return   bool                True if valid, false otherwise
     */
    private function is_valid_request($request) {
        $required_fields = array('method', 'url', 'headers', 'body', 'timeout');
        
        foreach ($required_fields as $field) {
            if (!isset($request[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Process HTTP response
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $response         WordPress HTTP response
     * @param    array    $request          Original request
     * @param    float    $execution_time   Request execution time
     * @return   array|WP_Error            Processed response or error
     */
    private function process_response($response, $request, $execution_time) {
        try {
            // Get response components
            $response_code = wp_remote_retrieve_response_code($response);
            $response_headers = wp_remote_retrieve_headers($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_size = strlen($response_body);

            // Log response details
            $this->logger->info('API response received', array(
                'operation' => $request['operation'],
                'response_code' => $response_code,
                'response_size' => $response_size,
                'execution_time' => round($execution_time, 3),
                'content_type' => wp_remote_retrieve_header($response, 'content-type')
            ));

            // Update statistics
            $this->update_response_stats($request['operation'], $response_size, $execution_time, $response_code);

            // Check for HTTP errors
            if ($response_code < 200 || $response_code >= 300) {
                return $this->handle_http_error($response_code, $response_body, $request);
            }

            // Validate content type
            $content_type = wp_remote_retrieve_header($response, 'content-type');
            if (!$this->is_valid_content_type($content_type)) {
                return new WP_Error('invalid_content_type', "Unexpected content type: {$content_type}");
            }

            // Parse JSON response
            $parsed_response = json_decode($response_body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('json_decode_error', 'Invalid JSON response: ' . json_last_error_msg());
            }

            // Check for API errors in response
            if (isset($parsed_response['Errors']) && !empty($parsed_response['Errors'])) {
                return $this->handle_api_errors($parsed_response['Errors'], $request);
            }

            // Add metadata to response
            $parsed_response['_metadata'] = array(
                'operation' => $request['operation'],
                'response_code' => $response_code,
                'execution_time' => $execution_time,
                'response_size' => $response_size,
                'timestamp' => current_time('mysql')
            );

            return $parsed_response;

        } catch (Exception $e) {
            $this->logger->error('Response processing failed', array(
                'operation' => $request['operation'],
                'error' => $e->getMessage(),
                'execution_time' => $execution_time
            ));

            return new WP_Error('response_processing_error', $e->getMessage());
        }
    }

    /**
     * Handle HTTP errors
     *
     * @since    1.0.0
     * @access   private
     * @param    int       $response_code    HTTP response code
     * @param    string    $response_body    Response body
     * @param    array     $request          Original request
     * @return   WP_Error                    Error object
     */
    private function handle_http_error($response_code, $response_body, $request) {
        // Try to parse error response
        $error_data = json_decode($response_body, true);
        $error_message = "HTTP {$response_code} error";

        if ($error_data && isset($error_data['Errors'][0]['Message'])) {
            $error_message = $error_data['Errors'][0]['Message'];
        } elseif (!empty($response_body)) {
            // Truncate long error messages
            $error_message = substr($response_body, 0, 200);
        }

        // Map common HTTP errors
        $error_code = $this->map_http_error_code($response_code);

        $this->logger->error('HTTP error received', array(
            'operation' => $request['operation'],
            'response_code' => $response_code,
            'error_message' => $error_message
        ));

        return new WP_Error($error_code, $error_message, array('http_code' => $response_code));
    }

    /**
     * Handle API errors from response
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $errors     API errors array
     * @param    array    $request    Original request
     * @return   WP_Error             Error object
     */
    private function handle_api_errors($errors, $request) {
        $primary_error = $errors[0];
        $error_code = isset($primary_error['Code']) ? $primary_error['Code'] : 'api_error';
        $error_message = isset($primary_error['Message']) ? $primary_error['Message'] : 'Unknown API error';

        $this->logger->error('API error received', array(
            'operation' => $request['operation'],
            'error_code' => $error_code,
            'error_message' => $error_message,
            'all_errors' => $errors
        ));

        // Update error statistics
        $this->update_error_stats($error_code, $request['operation']);

        return new WP_Error($error_code, $error_message, array('api_errors' => $errors));
    }

    /**
     * Validate content type
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $content_type    Content-Type header value
     * @return   bool                       True if valid, false otherwise
     */
    private function is_valid_content_type($content_type) {
        $valid_types = array(
            'application/json',
            'application/json; charset=utf-8',
            'text/json'
        );

        foreach ($valid_types as $valid_type) {
            if (strpos($content_type, $valid_type) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map HTTP error codes to WordPress error codes
     *
     * @since    1.0.0
     * @access   private
     * @param    int    $http_code    HTTP response code
     * @return   string              WordPress error code
     */
    private function map_http_error_code($http_code) {
        $error_map = array(
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            429 => 'too_many_requests',
            500 => 'internal_server_error',
            502 => 'bad_gateway',
            503 => 'service_unavailable',
            504 => 'gateway_timeout'
        );

        return isset($error_map[$http_code]) ? $error_map[$http_code] : 'http_error';
    }

    /**
     * Log request details
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $request    Request array
     */
    private function log_request($request) {
        $this->logger->debug('Request details', array(
            'operation' => $request['operation'],
            'method' => $request['method'],
            'url' => $request['url'],
            'headers_count' => count($request['headers']),
            'payload_size' => $request['payload_size'],
            'timeout' => $request['timeout']
        ));
    }

    /**
     * Update request statistics
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $operation      Operation name
     * @param    int       $payload_size   Payload size in bytes
     */
    private function update_request_stats($operation, $payload_size) {
        $this->stats['requests_made']++;
        $this->stats['total_payload_size'] += $payload_size;

        if (!isset($this->stats['operations'][$operation])) {
            $this->stats['operations'][$operation] = 0;
        }
        $this->stats['operations'][$operation]++;
    }

    /**
     * Update response statistics
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $operation        Operation name
     * @param    int       $response_size    Response size in bytes
     * @param    float     $execution_time   Execution time in seconds
     * @param    int       $response_code    HTTP response code
     */
    private function update_response_stats($operation, $response_size, $execution_time, $response_code) {
        $this->stats['total_response_size'] += $response_size;
        $this->stats['total_execution_time'] += $execution_time;

        // Track success/failure rates
        if (!isset($this->stats['response_codes'])) {
            $this->stats['response_codes'] = array();
        }
        if (!isset($this->stats['response_codes'][$response_code])) {
            $this->stats['response_codes'][$response_code] = 0;
        }
        $this->stats['response_codes'][$response_code]++;
    }

    /**
     * Update error statistics
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $error_code    Error code
     * @param    string    $operation     Operation name
     */
    private function update_error_stats($error_code, $operation) {
        if (!isset($this->stats['errors'][$error_code])) {
            $this->stats['errors'][$error_code] = 0;
        }
        $this->stats['errors'][$error_code]++;
    }

    /**
     * Get request statistics
     *
     * @since    1.0.0
     * @return   array    Request statistics
     */
    public function get_stats() {
        return $this->stats;
    }

    /**
     * Reset statistics
     *
     * @since    1.0.0
     */
    public function reset_stats() {
        $this->init_stats();
    }

    /**
     * Get authentication handler
     *
     * @since    1.0.0
     * @return   Amazon_Product_Importer_Amazon_Auth    Authentication handler
     */
    public function get_auth() {
        return $this->auth;
    }

    /**
     * Set debug mode
     *
     * @since    1.0.0
     * @param    bool    $debug_mode    Debug mode flag
     */
    public function set_debug_mode($debug_mode) {
        $this->debug_mode = (bool) $debug_mode;
    }

    /**
     * Get supported operations
     *
     * @since    1.0.0
     * @return   array    Array of supported operation names
     */
    public function get_supported_operations() {
        return array_keys($this->endpoints['operations']);
    }

    /**
     * Validate configuration
     *
     * @since    1.0.0
     * @return   bool|WP_Error    True if valid, error otherwise
     */
    public function validate_config() {
        // Check required configuration
        $required_config = array('access_key_id', 'secret_access_key', 'region', 'marketplace');
        
        foreach ($required_config as $key) {
            if (empty($this->config[$key])) {
                return new WP_Error('missing_config', "Missing required configuration: {$key}");
            }
        }

        // Validate endpoints configuration
        if (empty($this->endpoints) || !isset($this->endpoints['operations'])) {
            return new WP_Error('invalid_endpoints', 'Invalid endpoints configuration');
        }

        return true;
    }

    /**
     * Get configuration summary
     *
     * @since    1.0.0
     * @return   array    Configuration summary
     */
    public function get_config_summary() {
        return array(
            'region' => $this->config['region'],
            'marketplace' => $this->config['marketplace'],
            'timeout' => $this->timeout,
            'debug_mode' => $this->debug_mode,
            'user_agent' => $this->user_agent,
            'supported_operations' => $this->get_supported_operations(),
            'auth_status' => $this->auth ? 'configured' : 'not_configured'
        );
    }
}