<?php
/**
 * Tests for Product Import functionality
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/tests
 * @since      1.0.0
 */

class Test_Product_Import extends Amazon_Importer_Test_Case {

    /**
     * Product Importer instance
     *
     * @var Amazon_Product_Importer_Import
     */
    private $product_importer;

    /**
     * Product Mapper instance
     *
     * @var Amazon_Product_Mapper
     */
    private $product_mapper;

    /**
     * Image Handler instance
     *
     * @var Amazon_Image_Handler
     */
    private $image_handler;

    /**
     * Category Handler instance
     *
     * @var Amazon_Category_Handler
     */
    private $category_handler;

    /**
     * Variation Handler instance
     *
     * @var Amazon_Variation_Handler
     */
    private $variation_handler;

    /**
     * Test import data
     *
     * @var array
     */
    private $test_import_data;

    /**
     * Created product IDs for cleanup
     *
     * @var array
     */
    private $created_product_ids = array();

    /**
     * Set up test fixtures
     */
    public function setUp(): void {
        parent::setUp();

        // Initialize import classes
        $this->product_importer = new Amazon_Product_Importer_Import();
        $this->product_mapper = new Amazon_Product_Mapper();
        $this->image_handler = new Amazon_Image_Handler();
        $this->category_handler = new Amazon_Category_Handler();
        $this->variation_handler = new Amazon_Variation_Handler();

        // Setup test data
        $this->setup_test_import_data();

        // Mock external requests
        add_filter( 'pre_http_request', array( $this, 'mock_external_requests' ), 10, 3 );

        // Track created products for cleanup
        add_action( 'woocommerce_new_product', array( $this, 'track_created_product' ) );
    }

    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        // Clean up created products
        $this->cleanup_created_products();

        // Remove filters
        remove_filter( 'pre_http_request', array( $this, 'mock_external_requests' ), 10 );
        remove_action( 'woocommerce_new_product', array( $this, 'track_created_product' ) );

        parent::tearDown();
    }

    /**
     * Test basic product import from ASIN
     */
    public function test_import_product_by_asin() {
        $asin = 'B08N5WRWNW';
        
        // Import product
        $result = $this->product_importer->import_by_asin( $asin );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'success', $result );
        $this->assertTrue( $result['success'] );
        $this->assertArrayHasKey( 'product_id', $result );

        $product_id = $result['product_id'];
        $this->assertGreaterThan( 0, $product_id );

        // Verify product was created correctly
        $product = wc_get_product( $product_id );
        $this->assertInstanceOf( 'WC_Product', $product );
        $this->assertEquals( $asin, get_post_meta( $product_id, '_amazon_asin', true ) );
        
        // Check basic product data
        $this->assertNotEmpty( $product->get_name() );
        $this->assertGreaterThan( 0, $product->get_price() );
        $this->assertNotEmpty( $product->get_description() );
    }

    /**
     * Test product import with full data mapping
     */
    public function test_full_product_data_mapping() {
        $amazon_data = $this->test_import_data['full_product'];
        
        $result = $this->product_importer->import_from_data( $amazon_data );

        $this->assertTrue( $result['success'] );
        $product_id = $result['product_id'];
        $product = wc_get_product( $product_id );

        // Test title mapping
        $this->assertEquals( 'Echo Dot (4th Gen) | Smart speaker with Alexa', $product->get_name() );

        // Test price mapping
        $this->assertEquals( '49.99', $product->get_price() );
        $this->assertEquals( '59.99', $product->get_regular_price() );
        $this->assertEquals( '49.99', $product->get_sale_price() );

        // Test description mapping
        $this->assertStringContainsString( 'Our most popular smart speaker', $product->get_short_description() );
        $this->assertStringContainsString( 'Compact design that fits perfectly', $product->get_description() );

        // Test brand mapping
        $this->assertEquals( 'Amazon', get_post_meta( $product_id, '_amazon_brand', true ) );

        // Test features mapping
        $features = get_post_meta( $product_id, '_amazon_features', true );
        $this->assertIsArray( $features );
        $this->assertContains( 'Compact design', $features );

        // Test availability mapping
        $this->assertEquals( 'In Stock.', get_post_meta( $product_id, '_amazon_availability', true ) );

        // Test region mapping
        $this->assertEquals( 'com', get_post_meta( $product_id, '_amazon_region', true ) );
    }

    /**
     * Test product image import
     */
    public function test_product_image_import() {
        $amazon_data = $this->test_import_data['with_images'];
        
        $result = $this->product_importer->import_from_data( $amazon_data );

        $this->assertTrue( $result['success'] );
        $product_id = $result['product_id'];
        $product = wc_get_product( $product_id );

        // Check main product image
        $image_id = $product->get_image_id();
        $this->assertGreaterThan( 0, $image_id );

        // Verify image metadata
        $image_url = wp_get_attachment_url( $image_id );
        $this->assertNotFalse( $image_url );

        // Check gallery images
        $gallery_ids = $product->get_gallery_image_ids();
        $this->assertNotEmpty( $gallery_ids );
        $this->assertCount( 3, $gallery_ids );

        // Verify Amazon image metadata
        $amazon_image_url = get_post_meta( $image_id, '_amazon_image_url', true );
        $this->assertNotEmpty( $amazon_image_url );
    }

    /**
     * Test category import and mapping
     */
    public function test_category_import() {
        $amazon_data = $this->test_import_data['with_categories'];
        
        $result = $this->product_importer->import_from_data( $amazon_data );

        $this->assertTrue( $result['success'] );
        $product_id = $result['product_id'];

        // Check product categories
        $categories = wp_get_post_terms( $product_id, 'product_cat' );
        $this->assertNotEmpty( $categories );

        // Find Electronics category
        $electronics_category = null;
        foreach ( $categories as $category ) {
            if ( $category->name === 'Electronics' ) {
                $electronics_category = $category;
                break;
            }
        }

        $this->assertNotNull( $electronics_category );

        // Check category hierarchy (Electronics > Smart Home > Smart Speakers)
        $smart_speakers_category = null;
        foreach ( $categories as $category ) {
            if ( $category->name === 'Smart Speakers' ) {
                $smart_speakers_category = $category;
                break;
            }
        }

        $this->assertNotNull( $smart_speakers_category );
        $this->assertEquals( $electronics_category->term_id, $smart_speakers_category->parent );
    }

    /**
     * Test variable product import
     */
    public function test_variable_product_import() {
        $amazon_data = $this->test_import_data['variable_product'];
        
        $result = $this->product_importer->import_from_data( $amazon_data );

        $this->assertTrue( $result['success'] );
        $product_id = $result['product_id'];
        $product = wc_get_product( $product_id );

        // Check product type
        $this->assertEquals( 'variable', $product->get_type() );

        // Check product attributes
        $attributes = $product->get_attributes();
        $this->assertArrayHasKey( 'pa_color', $attributes );
        $this->assertArrayHasKey( 'pa_size', $attributes );

        // Verify attribute values
        $color_attribute = $attributes['pa_color'];
        $this->assertTrue( $color_attribute->get_variation() );
        $this->assertTrue( $color_attribute->get_visible() );

        // Check variations
        $variations = $product->get_children();
        $this->assertNotEmpty( $variations );
        $this->assertGreaterThanOrEqual( 2, count( $variations ) );

        // Test specific variation
        $variation = wc_get_product( $variations[0] );
        $this->assertInstanceOf( 'WC_Product_Variation', $variation );
        $this->assertEquals( $product_id, $variation->get_parent_id() );

        // Check variation attributes
        $variation_attributes = $variation->get_attributes();
        $this->assertArrayHasKey( 'pa_color', $variation_attributes );

        // Check variation-specific Amazon data
        $variation_asin = get_post_meta( $variations[0], '_amazon_asin', true );
        $this->assertNotEmpty( $variation_asin );
    }

    /**
     * Test product update vs new import
     */
    public function test_product_update_vs_new_import() {
        $asin = 'B08N5WRWNW';
        $amazon_data = $this->test_import_data['full_product'];

        // First import
        $result1 = $this->product_importer->import_from_data( $amazon_data );
        $this->assertTrue( $result1['success'] );
        $product_id1 = $result1['product_id'];

        // Modify data for second import
        $updated_data = $amazon_data;
        $updated_data['ItemInfo']['Title']['DisplayValue'] = 'Updated Echo Dot Title';
        $updated_data['Offers']['Listings'][0]['Price']['DisplayAmount'] = '$39.99';

        // Second import (should update existing product)
        $result2 = $this->product_importer->import_from_data( $updated_data );
        $this->assertTrue( $result2['success'] );
        $product_id2 = $result2['product_id'];

        // Should be the same product
        $this->assertEquals( $product_id1, $product_id2 );

        // Check that data was updated
        $product = wc_get_product( $product_id2 );
        $this->assertEquals( 'Updated Echo Dot Title', $product->get_name() );
        $this->assertEquals( '39.99', $product->get_price() );
    }

    /**
     * Test batch import functionality
     */
    public function test_batch_import() {
        $asins = array( 'B08N5WRWNW', 'B08N5WRWN1', 'B08N5WRWN2' );
        
        $result = $this->product_importer->import_batch( $asins );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'imported', $result );
        $this->assertArrayHasKey( 'errors', $result );
        $this->assertArrayHasKey( 'skipped', $result );

        $this->assertEquals( 3, $result['imported'] );
        $this->assertEquals( 0, count( $result['errors'] ) );

        // Verify all products were created
        foreach ( $asins as $asin ) {
            $product_id = $this->product_importer->get_product_id_by_asin( $asin );
            $this->assertGreaterThan( 0, $product_id );
        }
    }

    /**
     * Test import error handling
     */
    public function test_import_error_handling() {
        // Mock API error
        add_filter( 'amazon_api_mock_response', function( $response, $url, $args ) {
            return new WP_Error( 'api_error', 'Product not found' );
        }, 10, 3 );

        $result = $this->product_importer->import_by_asin( 'INVALID_ASIN' );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'success', $result );
        $this->assertFalse( $result['success'] );
        $this->assertArrayHasKey( 'error', $result );
        $this->assertEquals( 'api_error', $result['error']['code'] );
    }

    /**
     * Test duplicate import prevention
     */
    public function test_duplicate_import_prevention() {
        $asin = 'B08N5WRWNW';
        
        // First import
        $result1 = $this->product_importer->import_by_asin( $asin );
        $this->assertTrue( $result1['success'] );
        $product_id1 = $result1['product_id'];

        // Second import with same ASIN
        $result2 = $this->product_importer->import_by_asin( $asin, array( 'force_update' => false ) );
        
        // Should return existing product
        $this->assertTrue( $result2['success'] );
        $this->assertEquals( $product_id1, $result2['product_id'] );
        $this->assertArrayHasKey( 'updated', $result2 );
        $this->assertFalse( $result2['updated'] );
    }

    /**
     * Test product validation during import
     */
    public function test_product_validation() {
        // Test invalid product data
        $invalid_data = array(
            'ASIN' => '', // Empty ASIN
            'ItemInfo' => array(
                'Title' => array( 'DisplayValue' => '' ) // Empty title
            )
        );

        $result = $this->product_importer->import_from_data( $invalid_data );

        $this->assertFalse( $result['success'] );
        $this->assertArrayHasKey( 'error', $result );
        $this->assertStringContainsString( 'validation', strtolower( $result['error']['message'] ) );
    }

    /**
     * Test import with custom settings
     */
    public function test_import_with_custom_settings() {
        $amazon_data = $this->test_import_data['full_product'];
        
        $custom_settings = array(
            'import_images' => false,
            'import_categories' => false,
            'price_markup' => 10, // 10% markup
            'status' => 'draft'
        );

        $result = $this->product_importer->import_from_data( $amazon_data, $custom_settings );

        $this->assertTrue( $result['success'] );
        $product_id = $result['product_id'];
        $product = wc_get_product( $product_id );

        // Check that images were not imported
        $this->assertEquals( 0, $product->get_image_id() );

        // Check that categories were not assigned
        $categories = wp_get_post_terms( $product_id, 'product_cat' );
        $this->assertEmpty( $categories );

        // Check price markup was applied
        $expected_price = round( 49.99 * 1.10, 2 );
        $this->assertEquals( $expected_price, floatval( $product->get_price() ) );

        // Check product status
        $this->assertEquals( 'draft', $product->get_status() );
    }

    /**
     * Test product attribute mapping
     */
    public function test_product_attribute_mapping() {
        $amazon_data = $this->test_import_data['with_attributes'];
        
        $result = $this->product_importer->import_from_data( $amazon_data );

        $this->assertTrue( $result['success'] );
        $product_id = $result['product_id'];
        $product = wc_get_product( $product_id );

        // Check product attributes
        $attributes = $product->get_attributes();
        
        $this->assertArrayHasKey( 'brand', $attributes );
        $this->assertArrayHasKey( 'dimensions', $attributes );
        $this->assertArrayHasKey( 'weight', $attributes );

        // Verify attribute values
        $brand_attribute = $attributes['brand'];
        $this->assertEquals( 'Amazon', $brand_attribute->get_options() );

        $dimensions_attribute = $attributes['dimensions'];
        $this->assertStringContainsString( '3.9 x 3.9 x 3.5 inches', $dimensions_attribute->get_options() );
    }

    /**
     * Test SKU handling during import
     */
    public function test_sku_handling() {
        $amazon_data = $this->test_import_data['full_product'];
        
        // Test with ASIN as SKU (default)
        $result = $this->product_importer->import_from_data( $amazon_data );
        $product = wc_get_product( $result['product_id'] );
        $this->assertEquals( 'B08N5WRWNW', $product->get_sku() );

        // Test with custom SKU setting
        $custom_settings = array( 'use_asin_as_sku' => false );
        $amazon_data['ASIN'] = 'B08N5WRWN1'; // Different ASIN
        
        $result = $this->product_importer->import_from_data( $amazon_data, $custom_settings );
        $product = wc_get_product( $result['product_id'] );
        $this->assertEmpty( $product->get_sku() );
    }

    /**
     * Test import hooks and filters
     */
    public function test_import_hooks_and_filters() {
        $hook_called = array();

        // Add test hooks
        add_action( 'amazon_importer_before_product_import', function( $data ) use ( &$hook_called ) {
            $hook_called['before_import'] = $data['ASIN'];
        } );

        add_action( 'amazon_importer_after_product_import', function( $product_id, $data ) use ( &$hook_called ) {
            $hook_called['after_import'] = array( $product_id, $data['ASIN'] );
        }, 10, 2 );

        add_filter( 'amazon_importer_product_title', function( $title, $data ) {
            return 'Filtered: ' . $title;
        }, 10, 2 );

        $amazon_data = $this->test_import_data['full_product'];
        $result = $this->product_importer->import_from_data( $amazon_data );

        // Check hooks were called
        $this->assertArrayHasKey( 'before_import', $hook_called );
        $this->assertEquals( 'B08N5WRWNW', $hook_called['before_import'] );

        $this->assertArrayHasKey( 'after_import', $hook_called );
        $this->assertEquals( $result['product_id'], $hook_called['after_import'][0] );

        // Check filter was applied
        $product = wc_get_product( $result['product_id'] );
        $this->assertStringStartsWith( 'Filtered:', $product->get_name() );
    }

    /**
     * Test import with existing WooCommerce product
     */
    public function test_import_with_existing_product() {
        // Create a regular WooCommerce product first
        $existing_product = new WC_Product_Simple();
        $existing_product->set_name( 'Existing Product' );
        $existing_product->set_sku( 'B08N5WRWNW' );
        $existing_product->set_price( '25.00' );
        $existing_product_id = $existing_product->save();

        // Try to import Amazon product with same SKU
        $amazon_data = $this->test_import_data['full_product'];
        $result = $this->product_importer->import_from_data( $amazon_data );

        $this->assertTrue( $result['success'] );
        $this->assertEquals( $existing_product_id, $result['product_id'] );

        // Verify product was updated with Amazon data
        $updated_product = wc_get_product( $existing_product_id );
        $this->assertNotEquals( 'Existing Product', $updated_product->get_name() );
        $this->assertEquals( 'B08N5WRWNW', get_post_meta( $existing_product_id, '_amazon_asin', true ) );
    }

    /**
     * Test image optimization during import
     */
    public function test_image_optimization() {
        $amazon_data = $this->test_import_data['with_images'];
        
        // Enable image optimization
        $custom_settings = array(
            'optimize_images' => true,
            'image_max_width' => 800,
            'image_quality' => 85
        );

        $result = $this->product_importer->import_from_data( $amazon_data, $custom_settings );

        $this->assertTrue( $result['success'] );
        $product_id = $result['product_id'];
        $product = wc_get_product( $product_id );

        $image_id = $product->get_image_id();
        $this->assertGreaterThan( 0, $image_id );

        // Check if optimization metadata was stored
        $optimized = get_post_meta( $image_id, '_amazon_image_optimized', true );
        $this->assertEquals( '1', $optimized );
    }

    /**
     * Test import progress tracking
     */
    public function test_import_progress_tracking() {
        $asins = array( 'B08N5WRWNW', 'B08N5WRWN1', 'B08N5WRWN2', 'B08N5WRWN3', 'B08N5WRWN4' );
        
        $progress_updates = array();
        
        add_action( 'amazon_importer_batch_progress', function( $current, $total, $percent ) use ( &$progress_updates ) {
            $progress_updates[] = array(
                'current' => $current,
                'total' => $total,
                'percent' => $percent
            );
        }, 10, 3 );

        $result = $this->product_importer->import_batch( $asins );

        $this->assertTrue( $result['imported'] >= 5 );
        $this->assertNotEmpty( $progress_updates );
        
        // Verify progress was tracked correctly
        $last_progress = end( $progress_updates );
        $this->assertEquals( 5, $last_progress['total'] );
        $this->assertEquals( 100, $last_progress['percent'] );
    }

    /**
     * Test import with rate limiting
     */
    public function test_import_with_rate_limiting() {
        // Mock rate limiting response
        $api_call_count = 0;
        add_filter( 'amazon_api_mock_response', function( $response, $url, $args ) use ( &$api_call_count ) {
            $api_call_count++;
            
            if ( $api_call_count <= 2 ) {
                return array(
                    'response' => array( 'code' => 429 ),
                    'body' => wp_json_encode( array(
                        'Errors' => array(
                            array(
                                'Code' => 'TooManyRequests',
                                'Message' => 'Rate limit exceeded'
                            )
                        )
                    ) )
                );
            }
            
            return null; // Use default mock after rate limit
        }, 10, 3 );

        $start_time = time();
        $result = $this->product_importer->import_by_asin( 'B08N5WRWNW' );
        $end_time = time();

        $this->assertTrue( $result['success'] );
        $this->assertGreaterThan( 1, $end_time - $start_time ); // Should have delayed due to rate limiting
    }

    /**
     * Test import cleanup on failure
     */
    public function test_import_cleanup_on_failure() {
        // Mock failure after partial import
        $import_stage = 0;
        add_filter( 'amazon_importer_import_stage', function( $stage ) use ( &$import_stage ) {
            $import_stage = $stage;
            
            if ( $stage === 'images' ) {
                throw new Exception( 'Simulated failure during image import' );
            }
            
            return $stage;
        } );

        $amazon_data = $this->test_import_data['with_images'];
        $result = $this->product_importer->import_from_data( $amazon_data );

        $this->assertFalse( $result['success'] );
        $this->assertArrayHasKey( 'error', $result );

        // Verify cleanup occurred - no orphaned products should exist
        $orphaned_products = get_posts( array(
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key' => '_amazon_import_incomplete',
                    'value' => '1'
                )
            ),
            'post_status' => 'any'
        ) );

        $this->assertEmpty( $orphaned_products );
    }

    /**
     * Setup test import data
     */
    private function setup_test_import_data() {
        $this->test_import_data = array(
            'full_product' => array(
                'ASIN' => 'B08N5WRWNW',
                'DetailPageURL' => 'https://www.amazon.com/dp/B08N5WRWNW',
                'ItemInfo' => array(
                    'Title' => array(
                        'DisplayValue' => 'Echo Dot (4th Gen) | Smart speaker with Alexa',
                        'Label' => 'Title',
                        'Locale' => 'en_US'
                    ),
                    'Features' => array(
                        'DisplayValues' => array(
                            'Our most popular smart speaker with a fabric design',
                            'Compact design that fits perfectly into small spaces',
                            'Voice control your music, smart home, and more'
                        ),
                        'Label' => 'Features',
                        'Locale' => 'en_US'
                    ),
                    'ProductInfo' => array(
                        'ProductDescription' => 'Compact design that fits perfectly into small spaces. Our most popular smart speaker with a fabric design that complements any home.'
                    ),
                    'ManufactureInfo' => array(
                        'ItemPartNumber' => 'B08N5WRWNW'
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
                            'SavingBasis' => array(
                                'Amount' => 5999,
                                'Currency' => 'USD',
                                'DisplayAmount' => '$59.99'
                            ),
                            'Availability' => array(
                                'MaxOrderQuantity' => 10,
                                'Message' => 'In Stock.',
                                'Type' => 'Now'
                            )
                        )
                    )
                ),
                'BrowseNodeInfo' => array(
                    'BrowseNodes' => array(
                        array(
                            'Id' => '172282',
                            'DisplayName' => 'Electronics',
                            'Ancestor' => array(
                                'Id' => '502394',
                                'DisplayName' => 'Smart Home'
                            )
                        )
                    )
                )
            ),
            'with_images' => array(
                'ASIN' => 'B08N5WRWN1',
                'ItemInfo' => array(
                    'Title' => array( 'DisplayValue' => 'Test Product with Images' )
                ),
                'Offers' => array(
                    'Listings' => array(
                        array(
                            'Price' => array(
                                'Amount' => 2999,
                                'Currency' => 'USD',
                                'DisplayAmount' => '$29.99'
                            )
                        )
                    )
                ),
                'Images' => array(
                    'Primary' => array(
                        'Large' => array(
                            'URL' => 'https://m.media-amazon.com/images/I/primary.jpg',
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
                        ),
                        array(
                            'Large' => array(
                                'URL' => 'https://m.media-amazon.com/images/I/variant2.jpg',
                                'Height' => 500,
                                'Width' => 500
                            )
                        ),
                        array(
                            'Large' => array(
                                'URL' => 'https://m.media-amazon.com/images/I/variant3.jpg',
                                'Height' => 500,
                                'Width' => 500
                            )
                        )
                    )
                )
            ),
            'with_categories' => array(
                'ASIN' => 'B08N5WRWN2',
                'ItemInfo' => array(
                    'Title' => array( 'DisplayValue' => 'Test Product with Categories' )
                ),
                'Offers' => array(
                    'Listings' => array(
                        array(
                            'Price' => array(
                                'Amount' => 3999,
                                'Currency' => 'USD',
                                'DisplayAmount' => '$39.99'
                            )
                        )
                    )
                ),
                'BrowseNodeInfo' => array(
                    'BrowseNodes' => array(
                        array(
                            'Id' => '172282',
                            'DisplayName' => 'Electronics',
                            'Children' => array(
                                array(
                                    'Id' => '6817755011',
                                    'DisplayName' => 'Smart Home',
                                    'Children' => array(
                                        array(
                                            'Id' => '14969551011',
                                            'DisplayName' => 'Smart Speakers'
                                        )
                                    )
                                )
                            )
                        )
                    )
                )
            ),
            'variable_product' => array(
                'ASIN' => 'B08N5WRWN3',
                'ParentASIN' => 'B08N5WRWN3',
                'ItemInfo' => array(
                    'Title' => array( 'DisplayValue' => 'Variable Test Product' )
                ),
                'Offers' => array(
                    'Listings' => array(
                        array(
                            'Price' => array(
                                'Amount' => 4999,
                                'Currency' => 'USD',
                                'DisplayAmount' => '$49.99'
                            )
                        )
                    )
                ),
                'VariationSummary' => array(
                    'VariationDimensions' => array(
                        array(
                            'Name' => 'Color',
                            'DisplayName' => 'Color',
                            'Values' => array(
                                array( 'DisplayValue' => 'Black' ),
                                array( 'DisplayValue' => 'White' ),
                                array( 'DisplayValue' => 'Blue' )
                            )
                        ),
                        array(
                            'Name' => 'Size',
                            'DisplayName' => 'Size',
                            'Values' => array(
                                array( 'DisplayValue' => 'Small' ),
                                array( 'DisplayValue' => 'Large' )
                            )
                        )
                    )
                ),
                'Variations' => array(
                    array(
                        'ASIN' => 'B08N5WRWN4',
                        'ParentASIN' => 'B08N5WRWN3',
                        'VariationAttributes' => array(
                            array( 'Name' => 'Color', 'Value' => 'Black' ),
                            array( 'Name' => 'Size', 'Value' => 'Small' )
                        ),
                        'Offers' => array(
                            'Listings' => array(
                                array(
                                    'Price' => array(
                                        'Amount' => 4999,
                                        'Currency' => 'USD'
                                    )
                                )
                            )
                        )
                    ),
                    array(
                        'ASIN' => 'B08N5WRWN5',
                        'ParentASIN' => 'B08N5WRWN3',
                        'VariationAttributes' => array(
                            array( 'Name' => 'Color', 'Value' => 'White' ),
                            array( 'Name' => 'Size', 'Value' => 'Large' )
                        ),
                        'Offers' => array(
                            'Listings' => array(
                                array(
                                    'Price' => array(
                                        'Amount' => 5999,
                                        'Currency' => 'USD'
                                    )
                                )
                            )
                        )
                    )
                )
            ),
            'with_attributes' => array(
                'ASIN' => 'B08N5WRWN6',
                'ItemInfo' => array(
                    'Title' => array( 'DisplayValue' => 'Product with Attributes' ),
                    'TechnicalInfo' => array(
                        'Formats' => array(
                            'DisplayValues' => array( 'Hardcover', 'Kindle' )
                        )
                    ),
                    'ProductInfo' => array(
                        'ItemDimensions' => array(
                            'Height' => array( 'DisplayValue' => '3.5', 'Unit' => 'Inches' ),
                            'Length' => array( 'DisplayValue' => '3.9', 'Unit' => 'Inches' ),
                            'Width' => array( 'DisplayValue' => '3.9', 'Unit' => 'Inches' ),
                            'Weight' => array( 'DisplayValue' => '0.6', 'Unit' => 'Pounds' )
                        )
                    ),
                    'ByLineInfo' => array(
                        'Brand' => array( 'DisplayValue' => 'Amazon' ),
                        'Manufacturer' => array( 'DisplayValue' => 'Amazon' )
                    )
                ),
                'Offers' => array(
                    'Listings' => array(
                        array(
                            'Price' => array(
                                'Amount' => 2999,
                                'Currency' => 'USD',
                                'DisplayAmount' => '$29.99'
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * Mock external HTTP requests
     */
    public function mock_external_requests( $preempt, $args, $url ) {
        // Mock Amazon API requests
        if ( strpos( $url, 'webservices.amazon.' ) !== false ) {
            return $this->mock_amazon_api_request( $preempt, $args, $url );
        }

        // Mock image downloads
        if ( strpos( $url, 'm.media-amazon.com' ) !== false ) {
            return $this->mock_image_download( $url );
        }

        return $preempt;
    }

    /**
     * Mock Amazon API request
     */
    private function mock_amazon_api_request( $preempt, $args, $url ) {
        $request_body = json_decode( $args['body'], true );
        $item_ids = $request_body['ItemIds'] ?? array();

        $items = array();
        foreach ( $item_ids as $asin ) {
            if ( isset( $this->test_import_data['full_product'] ) && 
                 $this->test_import_data['full_product']['ASIN'] === $asin ) {
                $items[] = $this->test_import_data['full_product'];
            } else {
                // Generate mock data for other ASINs
                $items[] = $this->generate_mock_product_data( $asin );
            }
        }

        return array(
            'headers' => array( 'content-type' => 'application/json' ),
            'body' => wp_json_encode( array( 'ItemsResult' => array( 'Items' => $items ) ) ),
            'response' => array( 'code' => 200, 'message' => 'OK' ),
            'cookies' => array(),
            'filename' => null
        );
    }

    /**
     * Mock image download
     */
    private function mock_image_download( $url ) {
        // Create a simple 1x1 pixel PNG
        $image_data = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' );

        return array(
            'headers' => array( 'content-type' => 'image/png' ),
            'body' => $image_data,
            'response' => array( 'code' => 200, 'message' => 'OK' ),
            'cookies' => array(),
            'filename' => null
        );
    }

    /**
     * Generate mock product data for testing
     */
    private function generate_mock_product_data( $asin ) {
        return array(
            'ASIN' => $asin,
            'DetailPageURL' => "https://www.amazon.com/dp/{$asin}",
            'ItemInfo' => array(
                'Title' => array(
                    'DisplayValue' => "Mock Product {$asin}",
                    'Label' => 'Title',
                    'Locale' => 'en_US'
                ),
                'Features' => array(
                    'DisplayValues' => array(
                        "Feature 1 for {$asin}",
                        "Feature 2 for {$asin}"
                    )
                )
            ),
            'Offers' => array(
                'Listings' => array(
                    array(
                        'Price' => array(
                            'Amount' => rand( 1000, 10000 ),
                            'Currency' => 'USD',
                            'DisplayAmount' => '$' . number_format( rand( 10, 100 ), 2 )
                        ),
                        'Availability' => array(
                            'Message' => 'In Stock.',
                            'Type' => 'Now'
                        )
                    )
                )
            )
        );
    }

    /**
     * Track created products for cleanup
     */
    public function track_created_product( $product_id ) {
        $this->created_product_ids[] = $product_id;
    }

    /**
     * Clean up created products
     */
    private function cleanup_created_products() {
        foreach ( $this->created_product_ids as $product_id ) {
            wp_delete_post( $product_id, true );
        }
        $this->created_product_ids = array();
    }
}