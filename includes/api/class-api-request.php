<?php

/**
 * Handles Amazon API requests
 */
class Amazon_Product_Importer_API_Request {

    private $auth;
    private $logger;
    private $cache;

    public function __construct() {
        $this->auth = new Amazon_Product_Importer_Amazon_Auth();
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->cache = new Amazon_Product_Importer_Cache();
    }

    /**
     * Make API request
     */
    public function make_request($operation, $params = array()) {
        try {
            if (!$this->auth->is_configured()) {
                throw new Exception(__('API credentials not configured', 'amazon-product-importer'));
            }

            // Get signed parameters
            $signed_params = $this->auth->get_signed_params($operation, $params);

            // Build request URL
            $request_url = $this->auth->get_api_url() . '?' . http_build_query($signed_params);

            // Make HTTP request
            $response = $this->execute_request($request_url);

            // Parse and validate response
            $parsed_response = $this->parse_response($response);

            $this->logger->log_api($operation, 'success', 'API request completed successfully');

            return $parsed_response;

        } catch (Exception $e) {
            $this->logger->log_api($operation, 'error', 'API request failed: ' . $e->getMessage(), array(
                'params' => $params
            ));

            throw $e;
        }
    }

    /**
     * Execute HTTP request
     */
    private function execute_request($url) {
        $args = array(
            'timeout' => 30,
            'user-agent' => 'Amazon Product Importer WordPress Plugin/1.0',
            'headers' => array(
                'Accept' => 'application/xml',
                'Accept-Encoding' => 'gzip'
            )
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            throw new Exception('HTTP request failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            throw new Exception('API returned HTTP ' . $response_code . ': ' . $response_body);
        }

        if (empty($response_body)) {
            throw new Exception('Empty response from API');
        }

        return $response_body;
    }

    /**
     * Parse XML response
     */
    private function parse_response($xml_response) {
        // Suppress XML errors for better error handling
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xml_response);

        if ($xml === false) {
            $xml_errors = libxml_get_errors();
            $error_message = 'XML parsing failed';
            
            if (!empty($xml_errors)) {
                $error_message .= ': ' . $xml_errors[0]->message;
            }
            
            throw new Exception($error_message);
        }

        // Check for API errors
        if (isset($xml->Error)) {
            throw new Exception('API Error: ' . (string)$xml->Error->Message);
        }

        // Convert SimpleXML to array for easier handling
        $array_response = $this->xml_to_array($xml);

        return $array_response;
    }

    /**
     * Convert SimpleXML to array
     */
    private function xml_to_array($xml_object) {
        $array = array();

        foreach ($xml_object as $key => $value) {
            if ($value instanceof SimpleXMLElement) {
                if ($value->count() > 0) {
                    $array[$key] = $this->xml_to_array($value);
                } else {
                    $array[$key] = (string)$value;
                }
            } else {
                $array[$key] = (string)$value;
            }
        }

        return $array;
    }

    /**
     * Make batch request for multiple ASINs
     */
    public function make_batch_request($operation, $asins, $additional_params = array()) {
        $batch_size = 10; // Amazon API limit
        $results = array();

        $asin_batches = array_chunk($asins, $batch_size);

        foreach ($asin_batches as $batch) {
            $params = array_merge($additional_params, array(
                'ItemIds' => $batch
            ));

            try {
                $response = $this->make_request($operation, $params);
                $results = array_merge($results, $this->extract_items_from_response($response));
                
                // Rate limiting delay
                usleep(1000000); // 1 second delay between batches
                
            } catch (Exception $e) {
                $this->logger->log('error', 'Batch request failed for ASINs: ' . implode(', ', $batch), array(
                    'error' => $e->getMessage()
                ));
            }
        }

        return $results;
    }

    /**
     * Extract items from API response
     */
    private function extract_items_from_response($response) {
        $items = array();

        if (isset($response['ItemSearchResponse']['Items']['Item'])) {
            $response_items = $response['ItemSearchResponse']['Items']['Item'];
            
            // Handle single item vs multiple items
            if (isset($response_items['ASIN'])) {
                // Single item
                $items[] = $response_items;
            } else {
                // Multiple items
                $items = $response_items;
            }
        }

        if (isset($response['ItemLookupResponse']['Items']['Item'])) {
            $response_items = $response['ItemLookupResponse']['Items']['Item'];
            
            if (isset($response_items['ASIN'])) {
                $items[] = $response_items;
            } else {
                $items = $response_items;
            }
        }

        return $items;
    }

    /**
     * Get request rate limit info
     */
    public function get_rate_limit_info() {
        $rate_limit_key = 'amazon_api_rate_limit_' . date('Y-m-d-H');
        $current_requests = get_transient($rate_limit_key) ?: 0;
        
        return array(
            'requests_this_hour' => $current_requests,
            'hourly_limit' => $this->get_hourly_limit(),
            'remaining' => max(0, $this->get_hourly_limit() - $current_requests)
        );
    }

    /**
     * Update rate limit counter
     */
    private function update_rate_limit_counter() {
        $rate_limit_key = 'amazon_api_rate_limit_' . date('Y-m-d-H');
        $current_requests = get_transient($rate_limit_key) ?: 0;
        
        set_transient($rate_limit_key, $current_requests + 1, HOUR_IN_SECONDS);
    }

    /**
     * Get hourly request limit
     */
    private function get_hourly_limit() {
        // Default limit for new developers, can be configured
        return apply_filters('amazon_importer_hourly_limit', 360);
    }

    /**
     * Check if rate limit exceeded
     */
    private function is_rate_limit_exceeded() {
        $rate_info = $this->get_rate_limit_info();
        return $rate_info['remaining'] <= 0;
    }
}