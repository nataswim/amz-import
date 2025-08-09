<?php
/**
 * Tests for Cron Jobs functionality
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/tests
 * @since      1.0.0
 */

class Test_Cron_Jobs extends Amazon_Importer_Test_Case {

    /**
     * Cron Manager instance
     *
     * @var Amazon_Cron_Manager
     */
    private $cron_manager;

    /**
     * Price Sync instance
     *
     * @var Amazon_Price_Sync
     */
    private $price_sync;

    /**
     * Product Updater instance
     *
     * @var Amazon_Product_Updater
     */
    private $product_updater;

    /**
     * Test products
     *
     * @var array
     */
    private $test_products = array();

    /**
     * Set up test fixtures
     */
    public function setUp(): void {
        parent::setUp();

        // Initialize cron classes
        $this->cron_manager = new Amazon_Cron_Manager();
        $this->price_sync = new Amazon_Price_Sync();
        $this->product_updater = new Amazon_Product_Updater();

        // Create test products
        $this->create_test_products();

        // Mock time functions for consistent testing
        $this->mock_time_functions();

        // Enable cron mocking
        add_filter( 'pre_http_request', array( $this, 'mock_amazon_api_requests' ), 10, 3 );
    }

    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        // Clear all scheduled events
        $this->clear_all_cron_events();

        // Clean up test products
        $this->cleanup_test_products();

        // Remove filters
        remove_filter( 'pre_http_request', array( $this, 'mock_amazon_api_requests' ), 10 );

        parent::tearDown();
    }

    /**
     * Test cron schedule registration
     */
    public function test_cron_schedule_registration() {
        // Register cron schedules
        $this->cron_manager->init();

        $schedules = wp_get_schedules();

        // Check custom schedules are registered
        $this->assertArrayHasKey( 'amazon_every_15min', $schedules );
        $this->assertArrayHasKey( 'amazon_every_30min', $schedules );
        $this->assertArrayHasKey( 'amazon_twice_daily', $schedules );

        // Verify schedule intervals
        $this->assertEquals( 15 * MINUTE_IN_SECONDS, $schedules['amazon_every_15min']['interval'] );
        $this->assertEquals( 30 * MINUTE_IN_SECONDS, $schedules['amazon_every_30min']['interval'] );
        $this->assertEquals( 12 * HOUR_IN_SECONDS, $schedules['amazon_twice_daily']['interval'] );
    }

    /**
     * Test cron event scheduling
     */
    public function test_cron_event_scheduling() {
        // Schedule price sync event
        $this->cron_manager->schedule_price_sync( 'hourly' );

        // Check if event is scheduled
        $next_scheduled = wp_next_scheduled( 'amazon_price_sync' );
        $this->assertNotFalse( $next_scheduled );
        $this->assertGreaterThan( time(), $next_scheduled );

        // Schedule product update event
        $this->cron_manager->schedule_product_update( 'daily' );

        $next_scheduled = wp_next_scheduled( 'amazon_product_update' );
        $this->assertNotFalse( $next_scheduled );

        // Test custom arguments
        $this->cron_manager->schedule_single_product_sync( 123, time() + 3600 );
        $next_scheduled = wp_next_scheduled( 'amazon_single_product_sync', array( 123 ) );
        $this->assertNotFalse( $next_scheduled );
    }

    /**
     * Test cron event unscheduling
     */
    public function test_cron_event_unscheduling() {
        // Schedule an event first
        $this->cron_manager->schedule_price_sync( 'hourly' );
        $this->assertTrue( wp_next_scheduled( 'amazon_price_sync' ) !== false );

        // Unschedule it
        $this->cron_manager->unschedule_price_sync();
        $this->assertFalse( wp_next_scheduled( 'amazon_price_sync' ) );

        // Test unscheduling all events
        $this->cron_manager->schedule_price_sync( 'hourly' );
        $this->cron_manager->schedule_product_update( 'daily' );

        $this->cron_manager->unschedule_all_events();
        $this->assertFalse( wp_next_scheduled( 'amazon_price_sync' ) );
        $this->assertFalse( wp_next_scheduled( 'amazon_product_update' ) );
    }

    /**
     * Test price synchronization cron job
     */
    public function test_price_sync_cron_job() {
        // Add test products with old price data
        $product_ids = array_keys( $this->test_products );
        
        foreach ( $product_ids as $product_id ) {
            // Set old price update timestamp
            update_post_meta( $product_id, '_amazon_price_updated', date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
            update_post_meta( $product_id, '_amazon_auto_sync_price', '1' );
        }

        // Mock API responses
        add_filter( 'amazon_api_mock_response_get_items', array( $this, 'mock_price_sync_response' ) );

        // Execute price sync
        $result = $this->price_sync->sync_all_prices();

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'updated', $result );
        $this->assertArrayHasKey( 'errors', $result );
        $this->assertGreaterThan( 0, $result['updated'] );

        // Verify prices were updated
        foreach ( $product_ids as $product_id ) {
            $last_updated = get_post_meta( $product_id, '_amazon_price_updated', true );
            $this->assertGreaterThan( time() - 60, strtotime( $last_updated ) );
        }
    }

    /**
     * Test selective price synchronization
     */
    public function test_selective_price_sync() {
        $product_ids = array_keys( $this->test_products );
        
        // Enable auto sync for only some products
        update_post_meta( $product_ids[0], '_amazon_auto_sync_price', '1' );
        update_post_meta( $product_ids[1], '_amazon_auto_sync_price', '0' );
        update_post_meta( $product_ids[2], '_amazon_auto_sync_price', '1' );

        // Set different update frequencies
        update_post_meta( $product_ids[0], '_amazon_sync_frequency', 'hourly' );
        update_post_meta( $product_ids[2], '_amazon_sync_frequency', 'daily' );

        // Execute sync with frequency filter
        $result = $this->price_sync->sync_prices_by_frequency( 'hourly' );

        $this->assertIsArray( $result );
        $this->assertGreaterThan( 0, $result['processed'] );

        // Only products with hourly frequency should be processed
        $hourly_products = $this->price_sync->get_products_by_frequency( 'hourly' );
        $this->assertContains( $product_ids[0], $hourly_products );
        $this->assertNotContains( $product_ids[1], $hourly_products );
        $this->assertNotContains( $product_ids[2], $hourly_products );
    }

    /**
     * Test product update cron job
     */
    public function test_product_update_cron_job() {
        $product_ids = array_keys( $this->test_products );

        // Set products for full update
        foreach ( $product_ids as $product_id ) {
            update_post_meta( $product_id, '_amazon_auto_sync_enabled', '1' );
            update_post_meta( $product_id, '_amazon_last_sync', date( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS ) );
        }

        // Mock comprehensive API responses
        add_filter( 'amazon_api_mock_response_get_items', array( $this, 'mock_full_product_response' ) );

        // Execute product update
        $result = $this->product_updater->update_all_products();

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'updated', $result );
        $this->assertArrayHasKey( 'errors', $result );
        $this->assertGreaterThan( 0, $result['updated'] );

        // Verify products were updated
        foreach ( $product_ids as $product_id ) {
            $last_sync = get_post_meta( $product_id, '_amazon_last_sync', true );
            $this->assertGreaterThan( time() - 60, strtotime( $last_sync ) );
        }
    }

    /**
     * Test batch processing in cron jobs
     */
    public function test_batch_processing() {
        // Create more test products to test batching
        for ( $i = 0; $i < 25; $i++ ) {
            $product_id = amazon_importer_create_test_product( 'BATCH' . str_pad( $i, 3, '0', STR_PAD_LEFT ) );
            update_post_meta( $product_id, '_amazon_auto_sync_price', '1' );
            $this->test_products[ $product_id ] = 'BATCH' . str_pad( $i, 3, '0', STR_PAD_LEFT );
        }

        // Set batch size
        add_filter( 'amazon_importer_price_sync_batch_size', function() { return 10; } );

        // Execute price sync
        $result = $this->price_sync->sync_all_prices();

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'batches_processed', $result );
        $this->assertGreaterThan( 1, $result['batches_processed'] );
    }

    /**
     * Test cron job error handling
     */
    public function test_cron_error_handling() {
        $product_ids = array_keys( $this->test_products );

        // Mock API error responses
        add_filter( 'amazon_api_mock_response_get_items', function( $response, $url, $args ) {
            return new WP_Error( 'api_error', 'Simulated API error' );
        }, 10, 3 );

        // Execute price sync
        $result = $this->price_sync->sync_all_prices();

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'errors', $result );
        $this->assertGreaterThan( 0, count( $result['errors'] ) );

        // Check error logging
        foreach ( $product_ids as $product_id ) {
            $sync_errors = get_post_meta( $product_id, '_amazon_sync_errors', true );
            $this->assertIsArray( $sync_errors );
            $this->assertNotEmpty( $sync_errors );
        }
    }

    /**
     * Test cron job timeout handling
     */
    public function test_cron_timeout_handling() {
        // Mock a long-running operation
        add_filter( 'amazon_importer_price_sync_timeout', function() { return 5; } ); // 5 seconds

        // Track execution time
        $start_time = time();
        
        // Execute with simulated delay
        add_filter( 'amazon_api_mock_response_get_items', function( $response, $url, $args ) {
            sleep( 2 ); // Simulate slow API response
            return $response;
        }, 10, 3 );

        $result = $this->price_sync->sync_all_prices();

        $execution_time = time() - $start_time;

        // Should complete within timeout
        $this->assertLessThan( 10, $execution_time );
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'timeout_reached', $result );
    }

    /**
     * Test queue management in cron jobs
     */
    public function test_queue_management() {
        // Add items to sync queue
        $product_ids = array_keys( $this->test_products );
        
        foreach ( $product_ids as $product_id ) {
            $this->cron_manager->add_to_sync_queue( $product_id, 'price_sync' );
        }

        // Check queue size
        $queue_size = $this->cron_manager->get_queue_size();
        $this->assertEquals( count( $product_ids ), $queue_size );

        // Process queue
        $result = $this->cron_manager->process_sync_queue( 2 ); // Process 2 items

        $this->assertIsArray( $result );
        $this->assertEquals( 2, $result['processed'] );

        // Check remaining queue size
        $remaining_queue_size = $this->cron_manager->get_queue_size();
        $this->assertEquals( count( $product_ids ) - 2, $remaining_queue_size );
    }

    /**
     * Test cron job priority system
     */
    public function test_cron_priority_system() {
        $product_ids = array_keys( $this->test_products );

        // Set different priorities
        update_post_meta( $product_ids[0], '_amazon_sync_priority', 'high' );
        update_post_meta( $product_ids[1], '_amazon_sync_priority', 'normal' );
        update_post_meta( $product_ids[2], '_amazon_sync_priority', 'low' );

        // Add to queue
        foreach ( $product_ids as $product_id ) {
            $this->cron_manager->add_to_sync_queue( $product_id, 'price_sync' );
        }

        // Get queue items by priority
        $high_priority_items = $this->cron_manager->get_queue_items_by_priority( 'high' );
        $this->assertContains( $product_ids[0], $high_priority_items );
        $this->assertNotContains( $product_ids[1], $high_priority_items );

        // Process high priority items first
        $result = $this->cron_manager->process_sync_queue( 1, 'high' );
        $this->assertEquals( 1, $result['processed'] );
    }

    /**
     * Test cron job memory management
     */
    public function test_memory_management() {
        // Create many test products
        for ( $i = 0; $i < 50; $i++ ) {
            $product_id = amazon_importer_create_test_product( 'MEM' . str_pad( $i, 3, '0', STR_PAD_LEFT ) );
            update_post_meta( $product_id, '_amazon_auto_sync_price', '1' );
            $this->test_products[ $product_id ] = 'MEM' . str_pad( $i, 3, '0', STR_PAD_LEFT );
        }

        // Set memory limit for testing
        $original_memory_limit = ini_get( 'memory_limit' );
        ini_set( 'memory_limit', '64M' );

        // Monitor memory usage
        $initial_memory = memory_get_usage();

        $result = $this->price_sync->sync_all_prices();

        $final_memory = memory_get_usage();
        $memory_increase = $final_memory - $initial_memory;

        // Memory increase should be reasonable
        $this->assertLessThan( 32 * 1024 * 1024, $memory_increase ); // Less than 32MB increase

        // Restore original memory limit
        ini_set( 'memory_limit', $original_memory_limit );

        $this->assertIsArray( $result );
    }

    /**
     * Test cron job notifications
     */
    public function test_cron_notifications() {
        // Enable notifications
        update_option( 'amazon_importer_notify_cron_errors', '1' );
        update_option( 'amazon_importer_notify_email', 'admin@example.com' );

        $product_ids = array_keys( $this->test_products );

        // Mock email sending
        $emails_sent = array();
        add_filter( 'wp_mail', function( $args ) use ( &$emails_sent ) {
            $emails_sent[] = $args;
            return true;
        } );

        // Mock API error to trigger notification
        add_filter( 'amazon_api_mock_response_get_items', function( $response, $url, $args ) {
            return new WP_Error( 'api_error', 'API quota exceeded' );
        }, 10, 3 );

        // Execute sync
        $this->price_sync->sync_all_prices();

        // Check if notification was sent
        $this->assertNotEmpty( $emails_sent );
        $this->assertStringContainsString( 'Amazon Import Error', $emails_sent[0]['subject'] );
        $this->assertEquals( 'admin@example.com', $emails_sent[0]['to'] );
    }

    /**
     * Test cron job statistics tracking
     */
    public function test_statistics_tracking() {
        $product_ids = array_keys( $this->test_products );

        // Clear existing stats
        delete_option( 'amazon_importer_cron_stats' );

        // Execute multiple sync operations
        for ( $i = 0; $i < 3; $i++ ) {
            $this->price_sync->sync_all_prices();
        }

        // Check statistics
        $stats = get_option( 'amazon_importer_cron_stats', array() );
        
        $this->assertIsArray( $stats );
        $this->assertArrayHasKey( 'price_sync', $stats );
        $this->assertArrayHasKey( 'total_runs', $stats['price_sync'] );
        $this->assertEquals( 3, $stats['price_sync']['total_runs'] );
        $this->assertArrayHasKey( 'last_run', $stats['price_sync'] );
        $this->assertArrayHasKey( 'average_duration', $stats['price_sync'] );
    }

    /**
     * Test cron job cleanup operations
     */
    public function test_cleanup_operations() {
        // Create old log entries
        $old_logs = array();
        for ( $i = 0; $i < 100; $i++ ) {
            $old_logs[] = array(
                'timestamp' => date( 'Y-m-d H:i:s', time() - ( WEEK_IN_SECONDS * 2 ) ),
                'level' => 'info',
                'message' => 'Old log entry ' . $i
            );
        }
        update_option( 'amazon_importer_sync_logs', $old_logs );

        // Create old queue items
        for ( $i = 0; $i < 50; $i++ ) {
            $this->cron_manager->add_to_sync_queue( 999 + $i, 'old_sync', time() - ( DAY_IN_SECONDS * 3 ) );
        }

        // Execute cleanup
        $result = $this->cron_manager->cleanup_old_data();

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'logs_cleaned', $result );
        $this->assertArrayHasKey( 'queue_cleaned', $result );
        $this->assertGreaterThan( 0, $result['logs_cleaned'] );
        $this->assertGreaterThan( 0, $result['queue_cleaned'] );

        // Verify cleanup
        $remaining_logs = get_option( 'amazon_importer_sync_logs', array() );
        $this->assertLessThan( 100, count( $remaining_logs ) );
    }

    /**
     * Test concurrent cron job execution prevention
     */
    public function test_concurrent_execution_prevention() {
        // Set a lock
        $this->price_sync->set_sync_lock();

        // Try to execute another sync
        $result = $this->price_sync->sync_all_prices();

        $this->assertIsWPError( $result );
        $this->assertEquals( 'sync_in_progress', $result->get_error_code() );

        // Clear lock
        $this->price_sync->clear_sync_lock();

        // Should work now
        $result = $this->price_sync->sync_all_prices();
        $this->assertIsArray( $result );
    }

    /**
     * Test cron job performance monitoring
     */
    public function test_performance_monitoring() {
        // Enable performance monitoring
        add_filter( 'amazon_importer_enable_performance_monitoring', '__return_true' );

        $product_ids = array_keys( $this->test_products );

        // Execute sync with monitoring
        $result = $this->price_sync->sync_all_prices();

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'performance', $result );
        $this->assertArrayHasKey( 'execution_time', $result['performance'] );
        $this->assertArrayHasKey( 'memory_usage', $result['performance'] );
        $this->assertArrayHasKey( 'api_calls', $result['performance'] );

        // Check performance logs
        $performance_logs = get_option( 'amazon_importer_performance_logs', array() );
        $this->assertNotEmpty( $performance_logs );
    }

    /**
     * Test cron job graceful shutdown
     */
    public function test_graceful_shutdown() {
        // Simulate shutdown signal
        add_action( 'shutdown', function() {
            // Simulate SIGTERM
            if ( function_exists( 'pcntl_signal' ) ) {
                pcntl_signal( SIGTERM, SIG_DFL );
            }
        } );

        $product_ids = array_keys( $this->test_products );

        // Mock long-running operation
        add_filter( 'amazon_api_mock_response_get_items', function( $response, $url, $args ) {
            // Simulate interrupt during processing
            if ( defined( 'AMAZON_IMPORTER_SHUTDOWN' ) ) {
                return new WP_Error( 'interrupted', 'Process interrupted' );
            }
            return $response;
        }, 10, 3 );

        // Start sync and trigger shutdown
        define( 'AMAZON_IMPORTER_SHUTDOWN', true );
        $result = $this->price_sync->sync_all_prices();

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'interrupted', $result );
        $this->assertTrue( $result['interrupted'] );
    }

    /**
     * Create test products for cron testing
     */
    private function create_test_products() {
        $test_asins = array( 'CRON001', 'CRON002', 'CRON003' );

        foreach ( $test_asins as $asin ) {
            $product_id = amazon_importer_create_test_product( $asin, array(
                'title' => 'Cron Test Product ' . $asin,
                'price' => '29.99'
            ) );

            if ( $product_id ) {
                $this->test_products[ $product_id ] = $asin;
            }
        }
    }

    /**
     * Clean up test products
     */
    private function cleanup_test_products() {
        foreach ( array_keys( $this->test_products ) as $product_id ) {
            wp_delete_post( $product_id, true );
        }
        $this->test_products = array();
    }

    /**
     * Clear all cron events
     */
    private function clear_all_cron_events() {
        wp_clear_scheduled_hook( 'amazon_price_sync' );
        wp_clear_scheduled_hook( 'amazon_product_update' );
        wp_clear_scheduled_hook( 'amazon_single_product_sync' );
        wp_clear_scheduled_hook( 'amazon_cleanup_old_data' );
    }

    /**
     * Mock time functions for consistent testing
     */
    private function mock_time_functions() {
        // Mock current_time() for predictable testing
        if ( ! function_exists( 'amazon_test_current_time' ) ) {
            function amazon_test_current_time( $type, $gmt = 0 ) {
                static $mock_time = null;
                if ( $mock_time === null ) {
                    $mock_time = time();
                }
                return $mock_time;
            }
        }
    }

    /**
     * Mock Amazon API requests for cron testing
     */
    public function mock_amazon_api_requests( $preempt, $args, $url ) {
        if ( strpos( $url, 'webservices.amazon.' ) === false ) {
            return $preempt;
        }

        // Check for custom mock filters
        $custom_response = apply_filters( 'amazon_api_mock_response_get_items', null, $url, $args );
        if ( $custom_response !== null ) {
            return $custom_response;
        }

        // Default successful response
        return $this->generate_mock_successful_response( $args );
    }

    /**
     * Mock price sync response
     */
    public function mock_price_sync_response( $response, $url, $args ) {
        $request_body = json_decode( $args['body'], true );
        $item_ids = $request_body['ItemIds'] ?? array();

        $items = array();
        foreach ( $item_ids as $asin ) {
            $items[] = array(
                'ASIN' => $asin,
                'Offers' => array(
                    'Listings' => array(
                        array(
                            'Price' => array(
                                'Amount' => rand( 2000, 5000 ),
                                'Currency' => 'USD',
                                'DisplayAmount' => '$' . number_format( rand( 20, 50 ), 2 )
                            )
                        )
                    )
                )
            );
        }

        return array(
            'headers' => array( 'content-type' => 'application/json' ),
            'body' => wp_json_encode( array( 'ItemsResult' => array( 'Items' => $items ) ) ),
            'response' => array( 'code' => 200, 'message' => 'OK' )
        );
    }

    /**
     * Mock full product response
     */
    public function mock_full_product_response( $response, $url, $args ) {
        $request_body = json_decode( $args['body'], true );
        $item_ids = $request_body['ItemIds'] ?? array();

        $items = array();
        foreach ( $item_ids as $asin ) {
            $items[] = array(
                'ASIN' => $asin,
                'ItemInfo' => array(
                    'Title' => array(
                        'DisplayValue' => 'Updated Product Title ' . $asin
                    ),
                    'Features' => array(
                        'DisplayValues' => array(
                            'Updated feature 1',
                            'Updated feature 2'
                        )
                    )
                ),
                'Offers' => array(
                    'Listings' => array(
                        array(
                            'Price' => array(
                                'Amount' => rand( 3000, 6000 ),
                                'Currency' => 'USD'
                            ),
                            'Availability' => array(
                                'Message' => 'In Stock.'
                            )
                        )
                    )
                ),
                'Images' => array(
                    'Primary' => array(
                        'Large' => array(
                            'URL' => "https://example.com/updated-{$asin}.jpg"
                        )
                    )
                )
            );
        }

        return array(
            'headers' => array( 'content-type' => 'application/json' ),
            'body' => wp_json_encode( array( 'ItemsResult' => array( 'Items' => $items ) ) ),
            'response' => array( 'code' => 200, 'message' => 'OK' )
        );
    }

    /**
     * Generate mock successful response
     */
    private function generate_mock_successful_response( $args ) {
        $request_body = json_decode( $args['body'], true );
        $item_ids = $request_body['ItemIds'] ?? array( 'DEFAULT001' );

        $items = array();
        foreach ( $item_ids as $asin ) {
            $items[] = array(
                'ASIN' => $asin,
                'ItemInfo' => array(
                    'Title' => array(
                        'DisplayValue' => 'Mock Product ' . $asin
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
            );
        }

        return array(
            'headers' => array( 'content-type' => 'application/json' ),
            'body' => wp_json_encode( array( 'ItemsResult' => array( 'Items' => $items ) ) ),
            'response' => array( 'code' => 200, 'message' => 'OK' ),
            'cookies' => array(),
            'filename' => null
        );
    }
}