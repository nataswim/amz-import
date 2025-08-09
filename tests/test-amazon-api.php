<?php
/**
 * Tests for Amazon API functionality
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/tests
 * @since      1.0.0
 */

class Test_Amazon_API extends Amazon_Importer_Test_Case {

    /**
     * Amazon API instance
     *
     * @var Amazon_Api
     */
    private $amazon_api;

    /**
     * Amazon Auth instance
     *
     * @var Amazon_Auth
     */
    private $amazon_auth;

    /**
     * API Request instance
     *
     * @var Amazon_Api_Request
     */
    private $api_request;

    /**
     * Response Parser instance
     *
     * @var Amazon_Api_Response_Parser
     */
    private $response_parser;

    /**
     * Test API credentials
     *
     * @var array
     */
    private $test_credentials;

    /**
     * Set up test fixtures
     */
    public function setUp(): void {
        parent::setUp();

        // Initialize API classes
        $this->amazon_api = new Amazon_Api();
        $this->amazon_auth = new Amazon_Auth();
        $this->api_request = new Amazon_Api_Request();
        $this->response_parser = new Amazon_Api_Response_Parser();

        // Set test credentials
        $this->test_credentials = array(
            'access_key' => 'TEST_ACCESS_KEY_ID',
            'secret_key' => 'TEST_SECRET_ACCESS_KEY',
            'associate_tag' => 'test-associate-tag-20',
            'region' => 'com'
        );

        // Configure API with test credentials
        $this->amazon_api->set_credentials( $this->test_credentials );

        // Enable API mocking
        add_filter( 'pre_http_request', array( $this, 'mock_amazon_api_requests' ), 10, 3 );
    }

    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        remove_filter( 'pre_http_request', array( $this, 'mock_amazon_api_requests' ), 10 );
        parent::tearDown();
    }

    /**
     * Test Amazon API credentials validation
     */
    public function test_credentials_validation() {
        // Test valid credentials
        $valid_credentials = array(
            'access_key' => 'AKIAIOSFODNN7EXAMPLE',
            'secret_key' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            'associate_tag' => 'example-20',
            'region' => 'com'
        );

        $this->assertTrue( $this->amazon_auth->validate_credentials( $valid_credentials ) );

        // Test invalid access key
        $invalid_credentials = $valid_credentials;
        $invalid_credentials['access_key'] = 'invalid';
        $this->assertFalse( $this->amazon_auth->validate_credentials( $invalid_credentials ) );

        // Test missing secret key
        unset( $invalid_credentials['secret_key'] );
        $this->assertFalse( $this->amazon_auth->validate_credentials( $invalid_credentials ) );

        // Test invalid region
        $invalid_credentials = $valid_credentials;
        $invalid_credentials['region'] = 'invalid';
        $this->assertFalse( $this->amazon_auth->validate_credentials( $invalid_credentials ) );
    }

    /**
     * Test Amazon signature generation
     */
    public function test_signature_generation() {
        $method = 'GET';
        $uri = '/paapi5/getitems';
        $query_string = 'PartnerTag=test-20&PartnerType=Associates&Marketplace=www.amazon.com';
        $headers = array(
            'content-type' => 'application/json; charset=utf-8',
            'host' => 'webservices.amazon.com',
            'x-amz-date' => '20230101T120000Z',
            'x-amz-target' => 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.GetItems'
        );
        $payload = '{"ItemIds":["B08N5WRWNW"],"Resources":["ItemInfo.Title","Offers.Listings.Price"]}';

        $signature = $this->amazon_auth->generate_signature(
            $method,
            $uri,
            $query_string,
            $headers,
            $payload,
            $this->test_credentials
        );

        // Signature should be a valid hex string
        $this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $signature );

        // Same parameters should generate same signature
        $signature2 = $this->amazon_auth->generate_signature(
            $method,
            $uri,
            $query_string,
            $headers,
            $payload,
            $this->test_credentials
        );

        $this->assertEquals( $signature, $signature2 );
    }

    /**
     * Test GetItems API request
     */
    public function test_get_items_request() {
        $asin = 'B08N5WRWNW';
        $resources = array(
            'ItemInfo.Title',
            'ItemInfo.Features',
            'Offers.Listings.Price',
            'Images.Primary'
        );

        $response = $this->amazon_api->get_items( array( $asin ), $resources );

        $this->assertIsArray( $response );
        $this->assertArrayHasKey( 'ItemsResult', $response );
        $this->assertArrayHasKey( 'Items', $response['ItemsResult'] );
        $this->assertNotEmpty( $response['ItemsResult']['Items'] );

        $item = $response['ItemsResult']['Items'][0];
        $this->assertEquals( $asin, $item['ASIN'] );
        $this->assertArrayHasKey( 'ItemInfo', $item );
        $this->assertArrayHasKey( 'Offers', $item );
    }

    /**
     * Test GetItems with multiple ASINs
     */
    public function test_get_multiple_items() {
        $asins = array( 'B08N5WRWNW', 'B08N5WRWN1', 'B08N5WRWN2' );
        $resources = array( 'ItemInfo.Title', 'Offers.Listings.Price' );

        $response = $this->amazon_api->get_items( $asins, $resources );

        $this->assertIsArray( $response );
        $this->assertArrayHasKey( 'ItemsResult', $response );
        $this->assertCount( 3, $response['ItemsResult']['Items'] );

        foreach ( $response['ItemsResult']['Items'] as $item ) {
            $this->assertContains( $item['ASIN'], $asins );
        }
    }

    /**
     * Test SearchItems API request
     */
    public function test_search_items_request() {
        $keywords = 'echo dot';
        $search_index = 'All';
        $resources = array(
            'ItemInfo.Title',
            'ItemInfo.Features',
            'Offers.Listings.Price'
        );

        $response = $this->amazon_api->search_items( $keywords, $search_index, $resources );

        $this->assertIsArray( $response );
        $this->assertArrayHasKey( 'SearchResult', $response );
        $this->assertArrayHasKey( 'Items', $response['SearchResult'] );
        $this->assertNotEmpty( $response['SearchResult']['Items'] );

        // Check that results contain search terms
        foreach ( $response['SearchResult']['Items'] as $item ) {
            $title = strtolower( $item['ItemInfo']['Title']['DisplayValue'] );
            $this->assertStringContainsStringIgnoringCase( 'echo', $title );
        }
    }

    /**
     * Test SearchItems with pagination
     */
    public function test_search_items_pagination() {
        $keywords = 'kindle';
        $search_index = 'All';
        $resources = array( 'ItemInfo.Title' );
        $item_page = 2;

        $response = $this->amazon_api->search_items( $keywords, $search_index, $resources, array(
            'ItemPage' => $item_page
        ) );

        $this->assertIsArray( $response );
        $this->assertArrayHasKey( 'SearchResult', $response );
        $this->assertArrayHasKey( 'Items', $response['SearchResult'] );
    }

    /**
     * Test GetVariations API request
     */
    public function test_get_variations_request() {
        $parent_asin = 'B08N5WRWNW';
        $resources = array(
            'ItemInfo.Title',
            'Offers.Listings.Price',
            'VariationSummary.VariationDimension'
        );

        $response = $this->amazon_api->get_variations( $parent_asin, $resources );

        $this->assertIsArray( $response );
        $this->assertArrayHasKey( 'VariationsResult', $response );
        
        if ( isset( $response['VariationsResult']['Items'] ) ) {
            $this->assertIsArray( $response['VariationsResult']['Items'] );
            
            foreach ( $response['VariationsResult']['Items'] as $variation ) {
                $this->assertArrayHasKey( 'ASIN', $variation );
                $this->assertArrayHasKey( 'ParentASIN', $variation );
                $this->assertEquals( $parent_asin, $variation['ParentASIN'] );
            }
        }
    }

    /**
     * Test API error handling
     */
    public function test_api_error_handling() {
        // Test invalid ASIN
        $invalid_asin = 'INVALID123';
        
        // Mock error response
        add_filter( 'amazon_api_mock_response', function( $response, $url, $args ) use ( $invalid_asin ) {
            if ( strpos( $args['body'], $invalid_asin ) !== false ) {
                return array(
                    'response' => array( 'code' => 400 ),
                    'body' => wp_json_encode( array(
                        'Errors' => array(
                            array(
                                'Code' => 'InvalidParameterValue',
                                'Message' => 'The ItemId provided in the request is invalid.'
                            )
                        )
                    ) )
                );
            }
            return $response;
        }, 10, 3 );

        $response = $this->amazon_api->get_items( array( $invalid_asin ) );

        $this->assertIsWPError( $response );
        $this->assertEquals( 'amazon_api_error', $response->get_error_code() );
    }

    /**
     * Test rate limiting handling
     */
    public function test_rate_limiting() {
        // Mock rate limit response
        add_filter( 'amazon_api_mock_response', function( $response, $url, $args ) {
            static $request_count = 0;
            $request_count++;

            if ( $request_count <= 2 ) {
                return array(
                    'response' => array( 'code' => 429 ),
                    'body' => wp_json_encode( array(
                        'Errors' => array(
                            array(
                                'Code' => 'TooManyRequests',
                                'Message' => 'The request was denied due to request throttling.'
                            )
                        )
                    ) ),
                    'headers' => array(
                        'x-amzn-RateLimit-Limit' => '1',
                        'x-amzn-RateLimit-Remaining' => '0'
                    )
                );
            }

            return $response;
        }, 10, 3 );

        $start_time = time();
        $response = $this->amazon_api->get_items( array( 'B08N5WRWNW' ) );
        $end_time = time();

        // Should retry after rate limit and eventually succeed
        $this->assertIsArray( $response );
        $this->assertGreaterThan( 1, $end_time - $start_time ); // Should have delayed
    }

    /**
     * Test response parsing
     */
    public function test_response_parsing() {
        $raw_response = array(
            'ItemsResult' => array(
                'Items' => array(
                    array(
                        'ASIN' => 'B08N5WRWNW',
                        'ItemInfo' => array(
                            'Title' => array(
                                'DisplayValue' => 'Echo Dot (4th Gen)',
                                'Label' => 'Title',
                                'Locale' => 'en_US'
                            ),
                            'Features' => array(
                                'DisplayValues' => array(
                                    'Compact design',
                                    'Voice control',
                                    'Smart home hub'
                                ),
                                'Label' => 'Features',
                                'Locale' => 'en_US'
                            )
                        ),
                        'Offers' => array(
                            'Listings' => array(
                                array(
                                    'Id' => 'listing1',
                                    'Price' => array(
                                        'Amount' => 4999,
                                        'Currency' => 'USD',
                                        'DisplayAmount' => '$49.99'
                                    ),
                                    'Availability' => array(
                                        'MaxOrderQuantity' => 10,
                                        'Message' => 'In Stock.',
                                        'Type' => 'Now'
                                    )
                                )
                            )
                        ),
                        'Images' => array(
                            'Primary' => array(
                                'Large' => array(
                                    'URL' => 'https://m.media-amazon.com/images/I/example.jpg',
                                    'Height' => 500,
                                    'Width' => 500
                                )
                            ),
                            'Variants' => array(
                                array(
                                    'Large' => array(
                                        'URL' => 'https://m.media-amazon.com/images/I/variant1.jpg',
                                        'Height' => 500,
                                        'Width' => 500
                                    )
                                )
                            )
                        )
                    )
                )
            )
        );

        $parsed = $this->response_parser->parse_get_items_response( $raw_response );

        $this->assertIsArray( $parsed );
        $this->assertArrayHasKey( 'items', $parsed );
        $this->assertCount( 1, $parsed['items'] );

        $item = $parsed['items'][0];
        $this->assertEquals( 'B08N5WRWNW', $item['asin'] );
        $this->assertEquals( 'Echo Dot (4th Gen)', $item['title'] );
        $this->assertEquals( array( 'Compact design', 'Voice control', 'Smart home hub' ), $item['features'] );
        $this->assertEquals( 49.99, $item['price'] );
        $this->assertEquals( 'USD', $item['currency'] );
        $this->assertEquals( 'In Stock.', $item['availability'] );
        $this->assertNotEmpty( $item['images']['primary'] );
        $this->assertNotEmpty( $item['images']['variants'] );
    }

    /**
     * Test search results parsing
     */
    public function test_search_results_parsing() {
        $raw_response = array(
            'SearchResult' => array(
                'Items' => array(
                    array(
                        'ASIN' => 'B08N5WRWNW',
                        'ItemInfo' => array(
                            'Title' => array(
                                'DisplayValue' => 'Echo Dot (4th Gen)'
                            )
                        ),
                        'Offers' => array(
                            'Listings' => array(
                                array(
                                    'Price' => array(
                                        'DisplayAmount' => '$49.99'
                                    )
                                )
                            )
                        )
                    )
                ),
                'TotalResultCount' => 1000,
                'SearchURL' => 'https://www.amazon.com/s?k=echo+dot'
            )
        );

        $parsed = $this->response_parser->parse_search_items_response( $raw_response );

        $this->assertIsArray( $parsed );
        $this->assertArrayHasKey( 'items', $parsed );
        $this->assertArrayHasKey( 'total_results', $parsed );
        $this->assertArrayHasKey( 'search_url', $parsed );

        $this->assertEquals( 1000, $parsed['total_results'] );
        $this->assertCount( 1, $parsed['items'] );
        $this->assertEquals( 'B08N5WRWNW', $parsed['items'][0]['asin'] );
    }

    /**
     * Test variations parsing
     */
    public function test_variations_parsing() {
        $raw_response = array(
            'VariationsResult' => array(
                'Items' => array(
                    array(
                        'ASIN' => 'B08N5WRWN1',
                        'ParentASIN' => 'B08N5WRWNW',
                        'ItemInfo' => array(
                            'Title' => array(
                                'DisplayValue' => 'Echo Dot (4th Gen) - Charcoal'
                            )
                        ),
                        'VariationAttributes' => array(
                            array(
                                'Name' => 'Color',
                                'Value' => 'Charcoal'
                            )
                        )
                    ),
                    array(
                        'ASIN' => 'B08N5WRWN2',
                        'ParentASIN' => 'B08N5WRWNW',
                        'ItemInfo' => array(
                            'Title' => array(
                                'DisplayValue' => 'Echo Dot (4th Gen) - Glacier White'
                            )
                        ),
                        'VariationAttributes' => array(
                            array(
                                'Name' => 'Color',
                                'Value' => 'Glacier White'
                            )
                        )
                    )
                ),
                'VariationSummary' => array(
                    'PageCount' => 1,
                    'VariationCount' => 2,
                    'VariationDimensions' => array(
                        array(
                            'Name' => 'Color',
                            'DisplayName' => 'Color',
                            'Values' => array(
                                array( 'DisplayValue' => 'Charcoal' ),
                                array( 'DisplayValue' => 'Glacier White' )
                            )
                        )
                    )
                )
            )
        );

        $parsed = $this->response_parser->parse_get_variations_response( $raw_response );

        $this->assertIsArray( $parsed );
        $this->assertArrayHasKey( 'variations', $parsed );
        $this->assertArrayHasKey( 'variation_summary', $parsed );

        $this->assertCount( 2, $parsed['variations'] );
        $this->assertEquals( 2, $parsed['variation_summary']['variation_count'] );
        $this->assertCount( 1, $parsed['variation_summary']['variation_dimensions'] );

        $variation = $parsed['variations'][0];
        $this->assertEquals( 'B08N5WRWN1', $variation['asin'] );
        $this->assertEquals( 'B08N5WRWNW', $variation['parent_asin'] );
        $this->assertEquals( array( 'Color' => 'Charcoal' ), $variation['attributes'] );
    }

    /**
     * Test API caching
     */
    public function test_api_caching() {
        $asin = 'B08N5WRWNW';

        // First request
        $start_time = microtime( true );
        $response1 = $this->amazon_api->get_items( array( $asin ) );
        $first_request_time = microtime( true ) - $start_time;

        // Second request (should be cached)
        $start_time = microtime( true );
        $response2 = $this->amazon_api->get_items( array( $asin ) );
        $second_request_time = microtime( true ) - $start_time;

        $this->assertEquals( $response1, $response2 );
        $this->assertLessThan( $first_request_time, $second_request_time );
    }

    /**
     * Test cache invalidation
     */
    public function test_cache_invalidation() {
        $asin = 'B08N5WRWNW';

        // Make request to cache it
        $this->amazon_api->get_items( array( $asin ) );

        // Clear cache
        $this->amazon_api->clear_cache( $asin );

        // Mock different response for second request
        add_filter( 'amazon_api_mock_response', function( $response, $url, $args ) {
            static $call_count = 0;
            $call_count++;

            if ( $call_count > 1 ) {
                $body = json_decode( $response['body'], true );
                $body['ItemsResult']['Items'][0]['ItemInfo']['Title']['DisplayValue'] = 'Updated Title';
                $response['body'] = wp_json_encode( $body );
            }

            return $response;
        }, 10, 3 );

        $response = $this->amazon_api->get_items( array( $asin ) );
        $this->assertEquals( 'Updated Title', $response['ItemsResult']['Items'][0]['ItemInfo']['Title']['DisplayValue'] );
    }

    /**
     * Test different Amazon regions
     */
    public function test_different_regions() {
        $regions = array( 'com', 'co.uk', 'de', 'fr', 'it', 'es', 'ca', 'com.au', 'co.jp' );

        foreach ( $regions as $region ) {
            $credentials = $this->test_credentials;
            $credentials['region'] = $region;

            $api = new Amazon_Api();
            $api->set_credentials( $credentials );

            $response = $api->get_items( array( 'B08N5WRWNW' ) );
            $this->assertIsArray( $response );
            $this->assertArrayHasKey( 'ItemsResult', $response );
        }
    }

    /**
     * Test API request timeout handling
     */
    public function test_request_timeout() {
        // Mock timeout response
        add_filter( 'amazon_api_mock_response', function( $response, $url, $args ) {
            return new WP_Error( 'http_request_failed', 'Operation timed out' );
        }, 10, 3 );

        $response = $this->amazon_api->get_items( array( 'B08N5WRWNW' ) );
        $this->assertIsWPError( $response );
        $this->assertEquals( 'http_request_failed', $response->get_error_code() );
    }

    /**
     * Test API request retry logic
     */
    public function test_request_retry_logic() {
        $attempt_count = 0;

        add_filter( 'amazon_api_mock_response', function( $response, $url, $args ) use ( &$attempt_count ) {
            $attempt_count++;

            if ( $attempt_count < 3 ) {
                return new WP_Error( 'http_request_failed', 'Temporary network error' );
            }

            return $response;
        }, 10, 3 );

        $response = $this->amazon_api->get_items( array( 'B08N5WRWNW' ) );

        $this->assertIsArray( $response );
        $this->assertEquals( 3, $attempt_count ); // Should have retried twice
    }

    /**
     * Test batch processing of multiple items
     */
    public function test_batch_processing() {
        // Generate 15 ASINs (more than API limit of 10)
        $asins = array();
        for ( $i = 1; $i <= 15; $i++ ) {
            $asins[] = 'B08N5WRWN' . str_pad( $i, 2, '0', STR_PAD_LEFT );
        }

        $response = $this->amazon_api->get_items_batch( $asins );

        $this->assertIsArray( $response );
        $this->assertArrayHasKey( 'items', $response );
        $this->assertCount( 15, $response['items'] );
    }

    /**
     * Test resource filtering
     */
    public function test_resource_filtering() {
        $resources = array(
            'ItemInfo.Title',
            'ItemInfo.Features',
            'Offers.Listings.Price'
        );

        $response = $this->amazon_api->get_items( array( 'B08N5WRWNW' ), $resources );
        $item = $response['ItemsResult']['Items'][0];

        // Should have requested resources
        $this->assertArrayHasKey( 'ItemInfo', $item );
        $this->assertArrayHasKey( 'Title', $item['ItemInfo'] );
        $this->assertArrayHasKey( 'Features', $item['ItemInfo'] );
        $this->assertArrayHasKey( 'Offers', $item );

        // Should not have unrequested resources (like Images)
        $this->assertArrayNotHasKey( 'Images', $item );
    }

    /**
     * Mock Amazon API requests for testing
     */
    public function mock_amazon_api_requests( $preempt, $args, $url ) {
        // Only mock Amazon API requests
        if ( strpos( $url, 'webservices.amazon.' ) === false ) {
            return $preempt;
        }

        // Allow custom mock responses via filter
        $custom_response = apply_filters( 'amazon_api_mock_response', null, $url, $args );
        if ( $custom_response !== null ) {
            return $custom_response;
        }

        // Parse request body
        $request_body = json_decode( $args['body'], true );
        $operation = $this->extract_operation_from_headers( $args['headers'] );

        // Generate appropriate mock response
        switch ( $operation ) {
            case 'GetItems':
                return $this->generate_mock_get_items_response( $request_body );

            case 'SearchItems':
                return $this->generate_mock_search_items_response( $request_body );

            case 'GetVariations':
                return $this->generate_mock_get_variations_response( $request_body );

            default:
                return $this->generate_default_mock_response();
        }
    }

    /**
     * Extract operation from request headers
     */
    private function extract_operation_from_headers( $headers ) {
        if ( isset( $headers['x-amz-target'] ) ) {
            $target = $headers['x-amz-target'];
            if ( strpos( $target, '.GetItems' ) !== false ) {
                return 'GetItems';
            } elseif ( strpos( $target, '.SearchItems' ) !== false ) {
                return 'SearchItems';
            } elseif ( strpos( $target, '.GetVariations' ) !== false ) {
                return 'GetVariations';
            }
        }

        return 'GetItems'; // Default
    }

    /**
     * Generate mock GetItems response
     */
    private function generate_mock_get_items_response( $request_body ) {
        $item_ids = $request_body['ItemIds'] ?? array( 'B08N5WRWNW' );
        $resources = $request_body['Resources'] ?? array();

        $items = array();
        foreach ( $item_ids as $asin ) {
            $item = array(
                'ASIN' => $asin,
                'DetailPageURL' => "https://www.amazon.com/dp/{$asin}",
                'ItemInfo' => array(
                    'Title' => array(
                        'DisplayValue' => "Mock Product {$asin}",
                        'Label' => 'Title',
                        'Locale' => 'en_US'
                    )
                ),
                'Offers' => array(
                    'Listings' => array(
                        array(
                            'Id' => 'listing1',
                            'Price' => array(
                                'Amount' => rand( 1000, 10000 ),
                                'Currency' => 'USD',
                                'DisplayAmount' => '$' . number_format( rand( 10, 100 ), 2 )
                            ),
                            'Availability' => array(
                                'MaxOrderQuantity' => rand( 1, 10 ),
                                'Message' => 'In Stock.',
                                'Type' => 'Now'
                            )
                        )
                    )
                )
            );

            // Add features if requested
            if ( in_array( 'ItemInfo.Features', $resources ) ) {
                $item['ItemInfo']['Features'] = array(
                    'DisplayValues' => array(
                        'Feature 1 for ' . $asin,
                        'Feature 2 for ' . $asin,
                        'Feature 3 for ' . $asin
                    ),
                    'Label' => 'Features',
                    'Locale' => 'en_US'
                );
            }

            // Add images if requested
            if ( in_array( 'Images.Primary', $resources ) ) {
                $item['Images'] = array(
                    'Primary' => array(
                        'Large' => array(
                            'URL' => "https://m.media-amazon.com/images/I/{$asin}.jpg",
                            'Height' => 500,
                            'Width' => 500
                        )
                    )
                );
            }

            $items[] = $item;
        }

        $response_body = array(
            'ItemsResult' => array(
                'Items' => $items
            )
        );

        return array(
            'headers' => array( 'content-type' => 'application/json' ),
            'body' => wp_json_encode( $response_body ),
            'response' => array( 'code' => 200, 'message' => 'OK' ),
            'cookies' => array(),
            'filename' => null
        );
    }

    /**
     * Generate mock SearchItems response
     */
    private function generate_mock_search_items_response( $request_body ) {
        $keywords = $request_body['Keywords'] ?? 'test';
        $item_count = $request_body['ItemCount'] ?? 10;

        $items = array();
        for ( $i = 1; $i <= $item_count; $i++ ) {
            $asin = 'SEARCH' . str_pad( $i, 3, '0', STR_PAD_LEFT );
            $items[] = array(
                'ASIN' => $asin,
                'ItemInfo' => array(
                    'Title' => array(
                        'DisplayValue' => "{$keywords} Product {$i}"
                    )
                ),
                'Offers' => array(
                    'Listings' => array(
                        array(
                            'Price' => array(
                                'DisplayAmount' => '$' . number_format( rand( 10, 100 ), 2 )
                            )
                        )
                    )
                )
            );
        }

        $response_body = array(
            'SearchResult' => array(
                'Items' => $items,
                'TotalResultCount' => 1000,
                'SearchURL' => "https://www.amazon.com/s?k=" . urlencode( $keywords )
            )
        );

        return array(
            'headers' => array( 'content-type' => 'application/json' ),
            'body' => wp_json_encode( $response_body ),
            'response' => array( 'code' => 200, 'message' => 'OK' ),
            'cookies' => array(),
            'filename' => null
        );
    }

    /**
     * Generate mock GetVariations response
     */
    private function generate_mock_get_variations_response( $request_body ) {
        $parent_asin = $request_body['ASIN'] ?? 'B08N5WRWNW';

        $variations = array();
        $colors = array( 'Black', 'White', 'Blue', 'Red' );
        $sizes = array( 'Small', 'Medium', 'Large' );

        foreach ( $colors as $color ) {
            foreach ( $sizes as $size ) {
                $variation_asin = $parent_asin . substr( md5( $color . $size ), 0, 2 );
                $variations[] = array(
                    'ASIN' => $variation_asin,
                    'ParentASIN' => $parent_asin,
                    'ItemInfo' => array(
                        'Title' => array(
                            'DisplayValue' => "Mock Product - {$color} {$size}"
                        )
                    ),
                    'VariationAttributes' => array(
                        array( 'Name' => 'Color', 'Value' => $color ),
                        array( 'Name' => 'Size', 'Value' => $size )
                    )
                );
            }
        }

        $response_body = array(
            'VariationsResult' => array(
                'Items' => array_slice( $variations, 0, 10 ), // Limit to 10
                'VariationSummary' => array(
                    'PageCount' => 1,
                    'VariationCount' => count( $variations ),
                    'VariationDimensions' => array(
                        array(
                            'Name' => 'Color',
                            'DisplayName' => 'Color',
                            'Values' => array_map( function( $color ) {
                                return array( 'DisplayValue' => $color );
                            }, $colors )
                        ),
                        array(
                            'Name' => 'Size',
                            'DisplayName' => 'Size',
                            'Values' => array_map( function( $size ) {
                                return array( 'DisplayValue' => $size );
                            }, $sizes )
                        )
                    )
                )
            )
        );

        return array(
            'headers' => array( 'content-type' => 'application/json' ),
            'body' => wp_json_encode( $response_body ),
            'response' => array( 'code' => 200, 'message' => 'OK' ),
            'cookies' => array(),
            'filename' => null
        );
    }

    /**
     * Generate default mock response
     */
    private function generate_default_mock_response() {
        return array(
            'headers' => array( 'content-type' => 'application/json' ),
            'body' => wp_json_encode( array( 'Error' => 'Unknown operation' ) ),
            'response' => array( 'code' => 400, 'message' => 'Bad Request' ),
            'cookies' => array(),
            'filename' => null
        );
    }
}