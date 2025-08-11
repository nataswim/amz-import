<?php
/**
 * Amazon API Authentication for PA-API 5.0
 *
 * @link       https://mycreanet.fr
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
 * Amazon API Authentication Class for PA-API 5.0
 *
 * Handles AWS4 signature generation and authentication for Amazon Product Advertising API 5.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/api
 * @author     Your Name <https://mycreanet.fr>
 */
class Amazon_Product_Importer_Amazon_Auth {

    /**
     * AWS Access Key ID
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $access_key_id    AWS Access Key ID
     */
    private $access_key_id;

    /**
     * AWS Secret Access Key
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $secret_access_key    AWS Secret Access Key
     */
    private $secret_access_key;

    /**
     * Amazon Associate Tag
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $associate_tag    Amazon Associate Tag
     */
    private $associate_tag;

    /**
     * Amazon Marketplace
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $marketplace    Amazon Marketplace
     */
    private $marketplace;

    /**
     * AWS Region
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $region    AWS Region
     */
    private $region;

    /**
     * API Service Name
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $service    AWS Service Name
     */
    private $service = 'ProductAdvertisingAPI';

    /**
     * Regional Endpoints Configuration
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $endpoints    Regional endpoints mapping
     */
    private $endpoints = array(
        'us-east-1' => array(
            'host' => 'webservices.amazon.com',
            'marketplaces' => array('www.amazon.com', 'www.amazon.ca', 'www.amazon.com.mx')
        ),
        'eu-west-1' => array(
            'host' => 'webservices.amazon.co.uk',
            'marketplaces' => array(
                'www.amazon.co.uk', 'www.amazon.de', 'www.amazon.fr',
                'www.amazon.it', 'www.amazon.es', 'www.amazon.nl',
                'www.amazon.se', 'www.amazon.pl', 'www.amazon.com.tr'
            )
        ),
        'ap-northeast-1' => array(
            'host' => 'webservices.amazon.co.jp',
            'marketplaces' => array('www.amazon.co.jp')
        ),
        'ap-southeast-1' => array(
            'host' => 'webservices.amazon.in',
            'marketplaces' => array('www.amazon.in', 'www.amazon.sg')
        ),
        'ap-southeast-2' => array(
            'host' => 'webservices.amazon.com.au',
            'marketplaces' => array('www.amazon.com.au')
        )
    );

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->load_credentials();
        $this->validate_region_marketplace();
    }

    /**
     * Load API credentials from WordPress options
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_credentials() {
        $this->access_key_id = get_option('amazon_product_importer_access_key_id', '');
        $this->secret_access_key = get_option('amazon_product_importer_secret_access_key', '');
        $this->associate_tag = get_option('amazon_product_importer_associate_tag', '');
        $this->marketplace = get_option('amazon_product_importer_marketplace', 'www.amazon.com');
        $this->region = get_option('amazon_product_importer_region', 'us-east-1');
    }

    /**
     * Validate that the region supports the selected marketplace
     *
     * @since    1.0.0
     * @access   private
     */
    private function validate_region_marketplace() {
        if (!isset($this->endpoints[$this->region])) {
            $this->region = 'us-east-1';
        }

        $supported_marketplaces = $this->endpoints[$this->region]['marketplaces'];
        if (!in_array($this->marketplace, $supported_marketplaces)) {
            $this->marketplace = $supported_marketplaces[0];
        }
    }

    /**
     * Generate AWS4 signature for PA-API 5.0
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $method        HTTP method (GET, POST, etc.)
     * @param    string    $uri           Request URI
     * @param    string    $query_string  Query string
     * @param    array     $headers       Request headers
     * @param    string    $payload       Request payload
     * @return   string                   Generated signature
     */
    public function generate_aws4_signature($method, $uri, $query_string, $headers, $payload) {
        $algorithm = 'AWS4-HMAC-SHA256';
        $date = gmdate('Ymd');
        $datetime = gmdate('Ymd\THis\Z');

        // Create canonical request
        $canonical_request = $this->create_canonical_request($method, $uri, $query_string, $headers, $payload);

        // Create string to sign
        $credential_scope = "{$date}/{$this->region}/{$this->service}/aws4_request";
        $string_to_sign = implode("\n", array(
            $algorithm,
            $datetime,
            $credential_scope,
            hash('sha256', $canonical_request)
        ));

        // Calculate signature
        $signing_key = $this->get_signing_key($date);
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

        return $signature;
    }

    /**
     * Create canonical request
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $method        HTTP method
     * @param    string    $uri           Request URI
     * @param    string    $query_string  Query string
     * @param    array     $headers       Request headers
     * @param    string    $payload       Request payload
     * @return   string                   Canonical request
     */
    private function create_canonical_request($method, $uri, $query_string, $headers, $payload) {
        // Canonical URI
        $canonical_uri = $uri;

        // Canonical query string
        $canonical_query_string = $query_string;

        // Canonical headers
        $canonical_headers = '';
        $signed_headers = '';
        
        ksort($headers);
        foreach ($headers as $key => $value) {
            $canonical_headers .= strtolower($key) . ':' . trim($value) . "\n";
            $signed_headers .= strtolower($key) . ';';
        }
        $signed_headers = rtrim($signed_headers, ';');

        // Payload hash
        $payload_hash = hash('sha256', $payload);

        return implode("\n", array(
            $method,
            $canonical_uri,
            $canonical_query_string,
            $canonical_headers,
            $signed_headers,
            $payload_hash
        ));
    }

    /**
     * Get AWS4 signing key
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $date    Date in YYYYMMDD format
     * @return   string             Signing key
     */
    private function get_signing_key($date) {
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $this->secret_access_key, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $this->service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        return $kSigning;
    }

    /**
     * Get authorization header for AWS4 signature
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $method     HTTP method
     * @param    string    $uri        Request URI
     * @param    array     $headers    Request headers
     * @param    string    $payload    Request payload
     * @return   string                Authorization header value
     */
    public function get_authorization_header($method, $uri, $headers, $payload) {
        $datetime = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        // Add required headers
        $headers['host'] = $this->get_api_host();
        $headers['x-amz-date'] = $datetime;

        // Generate signature
        $signature = $this->generate_aws4_signature($method, $uri, '', $headers, $payload);

        // Create authorization header
        $credential = $this->access_key_id . '/' . $date . '/' . $this->region . '/' . $this->service . '/aws4_request';
        
        $signed_headers = array();
        foreach ($headers as $key => $value) {
            $signed_headers[] = strtolower($key);
        }
        sort($signed_headers);
        $signed_headers_string = implode(';', $signed_headers);

        return "AWS4-HMAC-SHA256 Credential={$credential}, SignedHeaders={$signed_headers_string}, Signature={$signature}";
    }

    /**
     * Get PA-API 5.0 endpoint URL
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $operation    API operation name
     * @return   string                  Complete endpoint URL
     */
    public function get_paapi_endpoint($operation) {
        $host = $this->get_api_host();
        return "https://{$host}/paapi5/{$operation}";
    }

    /**
     * Get API host for current region
     *
     * @since    1.0.0
     * @access   public
     * @return   string    API host
     */
    public function get_api_host() {
        return $this->endpoints[$this->region]['host'];
    }

    /**
     * Check if API credentials are configured
     *
     * @since    1.0.0
     * @access   public
     * @return   bool    True if configured, false otherwise
     */
    public function is_configured() {
        return !empty($this->access_key_id) && 
               !empty($this->secret_access_key) && 
               !empty($this->associate_tag);
    }

    /**
     * Validate API credentials
     *
     * @since    1.0.0
     * @access   public
     * @param    array    $credentials    Credentials array
     * @return   bool                     True if valid, false otherwise
     */
    public function validate_credentials($credentials = null) {
        if ($credentials) {
            $access_key = $credentials['access_key'] ?? '';
            $secret_key = $credentials['secret_key'] ?? '';
            $associate_tag = $credentials['associate_tag'] ?? '';
            $region = $credentials['region'] ?? '';
        } else {
            $access_key = $this->access_key_id;
            $secret_key = $this->secret_access_key;
            $associate_tag = $this->associate_tag;
            $region = $this->region;
        }

        // Basic validation
        if (empty($access_key) || empty($secret_key) || empty($associate_tag)) {
            return false;
        }

        // Validate access key format
        if (!preg_match('/^AKIA[0-9A-Z]{16}$/', $access_key)) {
            return false;
        }

        // Validate secret key length
        if (strlen($secret_key) != 40) {
            return false;
        }

        // Validate region
        if (!isset($this->endpoints[$region])) {
            return false;
        }

        // Validate associate tag format
        if (!preg_match('/^[a-zA-Z0-9\-]{1,20}$/', $associate_tag)) {
            return false;
        }

        return true;
    }

    /**
     * Test API connection
     *
     * @since    1.0.0
     * @access   public
     * @return   array    Test result
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'error' => __('API credentials not configured', 'amazon-product-importer')
            );
        }

        try {
            // Create a simple test request
            $test_payload = json_encode(array(
                'PartnerTag' => $this->associate_tag,
                'PartnerType' => 'Associates',
                'Marketplace' => $this->marketplace,
                'Keywords' => 'test',
                'Resources' => array('ItemInfo.Title')
            ));

            $headers = array(
                'content-type' => 'application/json; charset=utf-8',
                'x-amz-target' => 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.SearchItems'
            );

            $auth_header = $this->get_authorization_header('POST', '/paapi5/searchitems', $headers, $test_payload);

            $response = wp_remote_post($this->get_paapi_endpoint('searchitems'), array(
                'timeout' => 10,
                'headers' => array_merge($headers, array(
                    'Authorization' => $auth_header,
                    'Host' => $this->get_api_host(),
                    'X-Amz-Date' => gmdate('Ymd\THis\Z')
                )),
                'body' => $test_payload
            ));

            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'error' => $response->get_error_message()
                );
            }

            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code === 200) {
                return array(
                    'success' => true,
                    'message' => __('API connection successful', 'amazon-product-importer')
                );
            } else {
                $response_body = wp_remote_retrieve_body($response);
                $error_data = json_decode($response_body, true);
                
                return array(
                    'success' => false,
                    'error' => $error_data['__type'] ?? 'Unknown API error',
                    'error_code' => $response_code
                );
            }

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Get current region
     *
     * @since    1.0.0
     * @access   public
     * @return   string    Current region
     */
    public function get_region() {
        return $this->region;
    }

    /**
     * Get current marketplace
     *
     * @since    1.0.0
     * @access   public
     * @return   string    Current marketplace
     */
    public function get_marketplace() {
        return $this->marketplace;
    }

    /**
     * Get associate tag
     *
     * @since    1.0.0
     * @access   public
     * @return   string    Associate tag
     */
    public function get_associate_tag() {
        return $this->associate_tag;
    }

    /**
     * Get supported marketplaces for current region
     *
     * @since    1.0.0
     * @access   public
     * @return   array    Supported marketplaces
     */
    public function get_supported_marketplaces() {
        return $this->endpoints[$this->region]['marketplaces'];
    }

    /**
     * Get all available regions
     *
     * @since    1.0.0
     * @access   public
     * @return   array    Available regions
     */
    public function get_available_regions() {
        return array_keys($this->endpoints);
    }
}