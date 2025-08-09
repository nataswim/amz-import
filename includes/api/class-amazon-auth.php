<?php
/**
 * Amazon AWS Authentication Class
 *
 * This class handles AWS Signature Version 4 authentication for
 * Amazon Product Advertising API requests.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/api
 */

/**
 * Amazon authentication class for AWS Signature Version 4
 *
 * Implements the AWS Signature Version 4 signing process required
 * for Amazon Product Advertising API authentication.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/api
 * @author     Your Name <your.email@example.com>
 */
class Amazon_Product_Importer_Amazon_Auth {

    /**
     * AWS access key ID
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $access_key_id    AWS access key ID
     */
    private $access_key_id;

    /**
     * AWS secret access key
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $secret_access_key    AWS secret access key
     */
    private $secret_access_key;

    /**
     * AWS region
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $region    AWS region
     */
    private $region;

    /**
     * AWS service name
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $service    AWS service name
     */
    private $service;

    /**
     * Request timestamp
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $timestamp    ISO 8601 timestamp
     */
    private $timestamp;

    /**
     * Date string for signing
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $date_string    Date string in YYYYMMDD format
     */
    private $date_string;

    /**
     * Logger instance
     *
     * @since    1.0.0
     * @access   private
     * @var      object    $logger    Logger instance
     */
    private $logger;

    /**
     * Debug mode flag
     *
     * @since    1.0.0
     * @access   private
     * @var      bool    $debug_mode    Debug mode flag
     */
    private $debug_mode;

    /**
     * Constants for AWS Signature Version 4
     */
    const ALGORITHM = 'AWS4-HMAC-SHA256';
    const TERMINATION_STRING = 'aws4_request';
    const SERVICE_NAME = 'ProductAdvertisingAPI';

    /**
     * Initialize the authentication class
     *
     * @since    1.0.0
     * @param    array    $config    Authentication configuration
     */
    public function __construct($config = array()) {
        $this->access_key_id = isset($config['access_key_id']) ? $config['access_key_id'] : '';
        $this->secret_access_key = isset($config['secret_access_key']) ? $config['secret_access_key'] : '';
        $this->region = isset($config['region']) ? $config['region'] : 'us-east-1';
        $this->service = isset($config['service']) ? $config['service'] : self::SERVICE_NAME;
        $this->debug_mode = isset($config['debug_mode']) ? $config['debug_mode'] : false;

        // Initialize timestamp
        $this->init_timestamp();

        // Initialize logger if available
        if (class_exists('Amazon_Product_Importer_Logger')) {
            $this->logger = new Amazon_Product_Importer_Logger();
        }

        // Validate credentials
        $this->validate_credentials();
    }

    /**
     * Sign an HTTP request using AWS Signature Version 4
     *
     * @since    1.0.0
     * @param    string    $method      HTTP method (GET, POST, etc.)
     * @param    string    $url         Request URL
     * @param    array     $headers     HTTP headers
     * @param    string    $payload     Request payload
     * @return   array                  Signed headers and authorization
     */
    public function sign_request($method, $url, $headers = array(), $payload = '') {
        try {
            // Parse URL components
            $url_parts = parse_url($url);
            if (!$url_parts) {
                throw new Exception('Invalid URL format');
            }

            $host = $url_parts['host'];
            $path = isset($url_parts['path']) ? $url_parts['path'] : '/';
            $query = isset($url_parts['query']) ? $url_parts['query'] : '';

            // Prepare headers
            $headers = $this->prepare_headers($headers, $host, $payload);

            // Create canonical request
            $canonical_request = $this->create_canonical_request($method, $path, $query, $headers, $payload);

            if ($this->debug_mode && $this->logger) {
                $this->logger->debug('Canonical request created', array(
                    'canonical_request' => $canonical_request
                ));
            }

            // Create string to sign
            $string_to_sign = $this->create_string_to_sign($canonical_request);

            if ($this->debug_mode && $this->logger) {
                $this->logger->debug('String to sign created', array(
                    'string_to_sign' => $string_to_sign
                ));
            }

            // Calculate signature
            $signature = $this->calculate_signature($string_to_sign);

            // Create authorization header
            $authorization_header = $this->create_authorization_header($headers, $signature);

            // Add authorization to headers
            $headers['Authorization'] = $authorization_header;

            return array(
                'headers' => $headers,
                'signature' => $signature,
                'canonical_request' => $canonical_request,
                'string_to_sign' => $string_to_sign
            );

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Request signing failed', array(
                    'error' => $e->getMessage(),
                    'method' => $method,
                    'url' => $url
                ));
            }
            throw $e;
        }
    }

    /**
     * Initialize timestamp for request
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_timestamp() {
        $now = time();
        $this->timestamp = gmdate('Ymd\THis\Z', $now);
        $this->date_string = gmdate('Ymd', $now);
    }

    /**
     * Validate AWS credentials
     *
     * @since    1.0.0
     * @access   private
     * @throws   Exception    If credentials are invalid
     */
    private function validate_credentials() {
        if (empty($this->access_key_id)) {
            throw new Exception('AWS Access Key ID is required');
        }

        if (empty($this->secret_access_key)) {
            throw new Exception('AWS Secret Access Key is required');
        }

        if (empty($this->region)) {
            throw new Exception('AWS Region is required');
        }

        // Validate access key format
        if (!preg_match('/^[A-Z0-9]{16,}$/', $this->access_key_id)) {
            throw new Exception('Invalid AWS Access Key ID format');
        }

        // Validate secret key format (basic length check)
        if (strlen($this->secret_access_key) < 20) {
            throw new Exception('Invalid AWS Secret Access Key format');
        }
    }

    /**
     * Prepare headers for signing
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $headers    Original headers
     * @param    string    $host       Host header value
     * @param    string    $payload    Request payload
     * @return   array                 Prepared headers
     */
    private function prepare_headers($headers, $host, $payload) {
        // Ensure required headers are present
        $headers['Host'] = $host;
        $headers['X-Amz-Date'] = $this->timestamp;
        $headers['X-Amz-Target'] = 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.' . $this->get_operation_from_headers($headers);
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        
        // Add content encoding if payload is present
        if (!empty($payload)) {
            $headers['Content-Length'] = strlen($payload);
            $headers['X-Amz-Content-Sha256'] = hash('sha256', $payload);
        } else {
            $headers['X-Amz-Content-Sha256'] = hash('sha256', '');
        }

        // Add security token if available (for temporary credentials)
        $security_token = $this->get_security_token();
        if ($security_token) {
            $headers['X-Amz-Security-Token'] = $security_token;
        }

        // Normalize header names (lowercase)
        $normalized_headers = array();
        foreach ($headers as $name => $value) {
            $normalized_headers[strtolower($name)] = trim($value);
        }

        return $normalized_headers;
    }

    /**
     * Create canonical request
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $method     HTTP method
     * @param    string    $path       Request path
     * @param    string    $query      Query string
     * @param    array     $headers    Headers
     * @param    string    $payload    Request payload
     * @return   string                Canonical request
     */
    private function create_canonical_request($method, $path, $query, $headers, $payload) {
        // Step 1: HTTP method
        $canonical_method = strtoupper($method);

        // Step 2: Canonical URI
        $canonical_uri = $this->create_canonical_uri($path);

        // Step 3: Canonical query string
        $canonical_query_string = $this->create_canonical_query_string($query);

        // Step 4: Canonical headers
        $canonical_headers = $this->create_canonical_headers($headers);

        // Step 5: Signed headers
        $signed_headers = $this->create_signed_headers($headers);

        // Step 6: Payload hash
        $payload_hash = hash('sha256', $payload);

        // Combine all parts
        $canonical_request = implode("\n", array(
            $canonical_method,
            $canonical_uri,
            $canonical_query_string,
            $canonical_headers,
            $signed_headers,
            $payload_hash
        ));

        return $canonical_request;
    }

    /**
     * Create canonical URI
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $path    Request path
     * @return   string             Canonical URI
     */
    private function create_canonical_uri($path) {
        // Normalize path
        if (empty($path)) {
            return '/';
        }

        // URL encode each path segment
        $segments = explode('/', $path);
        $encoded_segments = array_map(array($this, 'url_encode_path'), $segments);
        
        return implode('/', $encoded_segments);
    }

    /**
     * Create canonical query string
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $query    Query string
     * @return   string              Canonical query string
     */
    private function create_canonical_query_string($query) {
        if (empty($query)) {
            return '';
        }

        // Parse query parameters
        parse_str($query, $params);

        // Sort parameters by key
        ksort($params);

        // URL encode keys and values
        $encoded_params = array();
        foreach ($params as $key => $value) {
            $encoded_key = $this->url_encode_query($key);
            $encoded_value = $this->url_encode_query($value);
            $encoded_params[] = $encoded_key . '=' . $encoded_value;
        }

        return implode('&', $encoded_params);
    }

    /**
     * Create canonical headers
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $headers    Headers array
     * @return   string               Canonical headers
     */
    private function create_canonical_headers($headers) {
        // Sort headers by name (already lowercase)
        ksort($headers);

        $canonical_headers = array();
        foreach ($headers as $name => $value) {
            // Normalize whitespace in header values
            $normalized_value = preg_replace('/\s+/', ' ', $value);
            $canonical_headers[] = $name . ':' . $normalized_value;
        }

        return implode("\n", $canonical_headers) . "\n";
    }

    /**
     * Create signed headers list
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $headers    Headers array
     * @return   string               Signed headers list
     */
    private function create_signed_headers($headers) {
        $header_names = array_keys($headers);
        sort($header_names);
        return implode(';', $header_names);
    }

    /**
     * Create string to sign
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $canonical_request    Canonical request
     * @return   string                          String to sign
     */
    private function create_string_to_sign($canonical_request) {
        $credential_scope = $this->create_credential_scope();
        $canonical_request_hash = hash('sha256', $canonical_request);

        $string_to_sign = implode("\n", array(
            self::ALGORITHM,
            $this->timestamp,
            $credential_scope,
            $canonical_request_hash
        ));

        return $string_to_sign;
    }

    /**
     * Create credential scope
     *
     * @since    1.0.0
     * @access   private
     * @return   string    Credential scope
     */
    private function create_credential_scope() {
        return implode('/', array(
            $this->date_string,
            $this->region,
            $this->service,
            self::TERMINATION_STRING
        ));
    }

    /**
     * Calculate signature
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $string_to_sign    String to sign
     * @return   string                       Calculated signature
     */
    private function calculate_signature($string_to_sign) {
        // Create signing key
        $signing_key = $this->create_signing_key();

        // Calculate signature
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

        return $signature;
    }

    /**
     * Create signing key
     *
     * @since    1.0.0
     * @access   private
     * @return   string    Signing key
     */
    private function create_signing_key() {
        $k_date = hash_hmac('sha256', $this->date_string, 'AWS4' . $this->secret_access_key, true);
        $k_region = hash_hmac('sha256', $this->region, $k_date, true);
        $k_service = hash_hmac('sha256', $this->service, $k_region, true);
        $k_signing = hash_hmac('sha256', self::TERMINATION_STRING, $k_service, true);

        return $k_signing;
    }

    /**
     * Create authorization header
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $headers      Headers array
     * @param    string    $signature    Calculated signature
     * @return   string                  Authorization header value
     */
    private function create_authorization_header($headers, $signature) {
        $credential = $this->access_key_id . '/' . $this->create_credential_scope();
        $signed_headers = $this->create_signed_headers($headers);

        $authorization = sprintf(
            '%s Credential=%s, SignedHeaders=%s, Signature=%s',
            self::ALGORITHM,
            $credential,
            $signed_headers,
            $signature
        );

        return $authorization;
    }

    /**
     * URL encode for path segments
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $string    String to encode
     * @return   string               Encoded string
     */
    private function url_encode_path($string) {
        return str_replace('%2F', '/', rawurlencode($string));
    }

    /**
     * URL encode for query parameters
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $string    String to encode
     * @return   string               Encoded string
     */
    private function url_encode_query($string) {
        return str_replace(
            array('+', '%7E'),
            array('%20', '~'),
            rawurlencode($string)
        );
    }

    /**
     * Get operation name from headers
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $headers    Headers array
     * @return   string               Operation name
     */
    private function get_operation_from_headers($headers) {
        // Extract operation from X-Amz-Target header if present
        if (isset($headers['X-Amz-Target'])) {
            $parts = explode('.', $headers['X-Amz-Target']);
            return end($parts);
        }

        // Default operation
        return 'SearchItems';
    }

    /**
     * Get security token for temporary credentials
     *
     * @since    1.0.0
     * @access   private
     * @return   string|null    Security token or null
     */
    private function get_security_token() {
        // Check for temporary security token
        $token = get_option('amazon_product_importer_security_token', '');
        return !empty($token) ? $token : null;
    }

    /**
     * Validate signature
     *
     * @since    1.0.0
     * @param    string    $method              HTTP method
     * @param    string    $url                 Request URL
     * @param    array     $headers             Headers
     * @param    string    $payload             Request payload
     * @param    string    $expected_signature  Expected signature
     * @return   bool                           True if valid, false otherwise
     */
    public function validate_signature($method, $url, $headers, $payload, $expected_signature) {
        try {
            $signed_request = $this->sign_request($method, $url, $headers, $payload);
            return hash_equals($expected_signature, $signed_request['signature']);
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Signature validation failed', array(
                    'error' => $e->getMessage()
                ));
            }
            return false;
        }
    }

    /**
     * Generate pre-signed URL (for GET requests)
     *
     * @since    1.0.0
     * @param    string    $url         Request URL
     * @param    int       $expires     Expiration time in seconds
     * @param    array     $headers     Additional headers
     * @return   string                 Pre-signed URL
     */
    public function generate_presigned_url($url, $expires = 3600, $headers = array()) {
        try {
            // Parse URL
            $url_parts = parse_url($url);
            $query_params = array();
            
            if (isset($url_parts['query'])) {
                parse_str($url_parts['query'], $query_params);
            }

            // Add AWS query parameters
            $query_params['X-Amz-Algorithm'] = self::ALGORITHM;
            $query_params['X-Amz-Credential'] = $this->access_key_id . '/' . $this->create_credential_scope();
            $query_params['X-Amz-Date'] = $this->timestamp;
            $query_params['X-Amz-Expires'] = $expires;
            $query_params['X-Amz-SignedHeaders'] = 'host';

            // Add security token if available
            $security_token = $this->get_security_token();
            if ($security_token) {
                $query_params['X-Amz-Security-Token'] = $security_token;
            }

            // Build query string
            ksort($query_params);
            $query_string = http_build_query($query_params);

            // Create canonical request for signing
            $canonical_request = $this->create_canonical_request(
                'GET',
                $url_parts['path'],
                $query_string,
                array('host' => $url_parts['host']),
                ''
            );

            // Create string to sign
            $string_to_sign = $this->create_string_to_sign($canonical_request);

            // Calculate signature
            $signature = $this->calculate_signature($string_to_sign);

            // Add signature to query parameters
            $query_params['X-Amz-Signature'] = $signature;

            // Build final URL
            $signed_url = $url_parts['scheme'] . '://' . $url_parts['host'];
            if (isset($url_parts['port'])) {
                $signed_url .= ':' . $url_parts['port'];
            }
            $signed_url .= $url_parts['path'] . '?' . http_build_query($query_params);

            return $signed_url;

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Pre-signed URL generation failed', array(
                    'error' => $e->getMessage(),
                    'url' => $url
                ));
            }
            throw $e;
        }
    }

    /**
     * Get current timestamp
     *
     * @since    1.0.0
     * @return   string    ISO 8601 timestamp
     */
    public function get_timestamp() {
        return $this->timestamp;
    }

    /**
     * Get date string
     *
     * @since    1.0.0
     * @return   string    Date string in YYYYMMDD format
     */
    public function get_date_string() {
        return $this->date_string;
    }

    /**
     * Get credential scope
     *
     * @since    1.0.0
     * @return   string    Credential scope
     */
    public function get_credential_scope() {
        return $this->create_credential_scope();
    }

    /**
     * Set custom timestamp (for testing)
     *
     * @since    1.0.0
     * @param    int    $timestamp    Unix timestamp
     */
    public function set_timestamp($timestamp) {
        $this->timestamp = gmdate('Ymd\THis\Z', $timestamp);
        $this->date_string = gmdate('Ymd', $timestamp);
    }

    /**
     * Get access key ID (masked for security)
     *
     * @since    1.0.0
     * @return   string    Masked access key ID
     */
    public function get_masked_access_key() {
        return substr($this->access_key_id, 0, 4) . str_repeat('*', strlen($this->access_key_id) - 8) . substr($this->access_key_id, -4);
    }

    /**
     * Test authentication credentials
     *
     * @since    1.0.0
     * @return   bool|WP_Error    True if valid, WP_Error otherwise
     */
    public function test_credentials() {
        try {
            // Create a test request
            $test_url = 'https://webservices.amazon.com/paapi5/searchitems';
            $test_headers = array();
            $test_payload = json_encode(array(
                'Keywords' => 'test',
                'PartnerTag' => 'test-tag',
                'PartnerType' => 'Associates',
                'Marketplace' => 'www.amazon.com',
                'Resources' => array('ItemInfo.Title')
            ));

            // Sign the test request
            $signed_request = $this->sign_request('POST', $test_url, $test_headers, $test_payload);

            // If we get here without exceptions, credentials format is valid
            return true;

        } catch (Exception $e) {
            return new WP_Error('auth_test_failed', $e->getMessage());
        }
    }

    /**
     * Clear sensitive data from memory
     *
     * @since    1.0.0
     */
    public function clear_credentials() {
        $this->access_key_id = '';
        $this->secret_access_key = '';
        
        // Clear any cached signing keys
        if (function_exists('sodium_memzero')) {
            // Use libsodium if available for secure memory clearing
            sodium_memzero($this->secret_access_key);
        }
    }

    /**
     * Get authentication statistics
     *
     * @since    1.0.0
     * @return   array    Authentication statistics
     */
    public function get_auth_stats() {
        return array(
            'access_key_id' => $this->get_masked_access_key(),
            'region' => $this->region,
            'service' => $this->service,
            'algorithm' => self::ALGORITHM,
            'timestamp' => $this->timestamp,
            'credential_scope' => $this->get_credential_scope(),
            'has_security_token' => !empty($this->get_security_token())
        );
    }

    /**
     * Destructor - clear sensitive data
     *
     * @since    1.0.0
     */
    public function __destruct() {
        $this->clear_credentials();
    }
}