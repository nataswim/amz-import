<?php

/**
 * Amazon API Authentication
 */
class Amazon_Product_Importer_Amazon_Auth {

    private $access_key_id;
    private $secret_access_key;
    private $associate_tag;
    private $region;
    private $endpoint;

    public function __construct() {
        $this->access_key_id = get_option('amazon_importer_api_access_key_id');
        $this->secret_access_key = get_option('amazon_importer_api_secret_access_key');
        $this->associate_tag = get_option('amazon_importer_api_associate_tag');
        $this->region = get_option('amazon_importer_api_region', 'com');
        $this->endpoint = $this->get_endpoint();
    }

    /**
     * Get API endpoint based on region
     */
    private function get_endpoint() {
        $endpoints = array(
            'com' => 'webservices.amazon.com',
            'co.uk' => 'webservices.amazon.co.uk',
            'de' => 'webservices.amazon.de',
            'fr' => 'webservices.amazon.fr',
            'it' => 'webservices.amazon.it',
            'es' => 'webservices.amazon.es',
            'ca' => 'webservices.amazon.ca',
            'co.jp' => 'webservices.amazon.co.jp',
            'in' => 'webservices.amazon.in',
            'com.br' => 'webservices.amazon.com.br',
            'com.mx' => 'webservices.amazon.com.mx',
            'com.au' => 'webservices.amazon.com.au'
        );

        return isset($endpoints[$this->region]) ? $endpoints[$this->region] : $endpoints['com'];
    }

    /**
     * Generate AWS signature
     */
    public function generate_signature($method, $uri, $params, $timestamp) {
        // Sort parameters
        ksort($params);
        
        // Create canonical query string
        $canonical_query_string = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        
        // Create string to sign
        $string_to_sign = implode("\n", array(
            $method,
            $this->endpoint,
            $uri,
            $canonical_query_string
        ));

        // Generate signature
        $signature = base64_encode(
            hash_hmac('sha256', $string_to_sign, $this->secret_access_key, true)
        );

        return $signature;
    }

    /**
     * Get signed request parameters
     */
    public function get_signed_params($operation, $params = array()) {
        if (!$this->is_configured()) {
            throw new Exception(__('API credentials not configured', 'amazon-product-importer'));
        }

        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        
        $default_params = array(
            'Service' => 'ProductAdvertisingAPI',
            'Operation' => $operation,
            'AWSAccessKeyId' => $this->access_key_id,
            'AssociateTag' => $this->associate_tag,
            'Timestamp' => $timestamp,
            'SignatureMethod' => 'HmacSHA256',
            'SignatureVersion' => '2',
            'Version' => '2013-08-01'
        );

        $all_params = array_merge($default_params, $params);
        
        // Generate signature
        $signature = $this->generate_signature('GET', '/onca/xml', $all_params, $timestamp);
        $all_params['Signature'] = $signature;

        return $all_params;
    }

    /**
     * Check if API credentials are configured
     */
    public function is_configured() {
        return !empty($this->access_key_id) && 
               !empty($this->secret_access_key) && 
               !empty($this->associate_tag);
    }

    /**
     * Get API endpoint URL
     */
    public function get_api_url() {
        return 'https://' . $this->endpoint . '/onca/xml';
    }

    /**
     * Get region
     */
    public function get_region() {
        return $this->region;
    }

    /**
     * Get associate tag
     */
    public function get_associate_tag() {
        return $this->associate_tag;
    }
}