<?php
/**
 * PHPUnit bootstrap file for Amazon Product Importer Plugin
 *
 * This file sets up the WordPress testing environment and loads the plugin
 * for unit testing.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/tests
 * @since      1.0.0
 */

// Composer autoloader
if ( file_exists( dirname( __DIR__ ) . '/vendor/autoload.php' ) ) {
    require_once dirname( __DIR__ ) . '/vendor/autoload.php';
}

// Define test environment constants
define( 'AMAZON_IMPORTER_TESTS', true );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WP_TESTS_FORCE_KNOWN_BUGS', true );

// Disable error reporting for cleaner test output
error_reporting( E_ALL & ~E_DEPRECATED & ~E_STRICT );

// Set timezone to avoid warnings
if ( ! ini_get( 'date.timezone' ) ) {
    date_default_timezone_set( 'UTC' );
}

// Define WordPress test database settings
if ( ! defined( 'DB_NAME' ) ) {
    define( 'DB_NAME', getenv( 'WP_TEST_DB_NAME' ) ?: 'wordpress_test' );
}
if ( ! defined( 'DB_USER' ) ) {
    define( 'DB_USER', getenv( 'WP_TEST_DB_USER' ) ?: 'root' );
}
if ( ! defined( 'DB_PASSWORD' ) ) {
    define( 'DB_PASSWORD', getenv( 'WP_TEST_DB_PASS' ) ?: '' );
}
if ( ! defined( 'DB_HOST' ) ) {
    define( 'DB_HOST', getenv( 'WP_TEST_DB_HOST' ) ?: 'localhost' );
}
if ( ! defined( 'DB_CHARSET' ) ) {
    define( 'DB_CHARSET', 'utf8' );
}
if ( ! defined( 'DB_COLLATE' ) ) {
    define( 'DB_COLLATE', '' );
}

// WordPress table prefix for tests
$table_prefix = 'wptests_';

// WordPress debug settings for tests
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );

// Force WordPress to use our test configuration
define( 'WP_USE_THEMES', false );
define( 'WP_INSTALLING', true );

// Find WordPress tests library path
$_tests_dir = getenv( 'WP_TESTS_DIR' );

// Try to find the tests directory automatically
if ( ! $_tests_dir ) {
    $possible_paths = array(
        '/tmp/wordpress-tests-lib',
        '/tmp/wordpress-develop/tests/phpunit',
        dirname( __DIR__ ) . '/vendor/wordpress/wordpress/tests/phpunit',
        dirname( __DIR__ ) . '/../../../tests/phpunit',
        '/usr/local/src/wordpress-tests-lib'
    );
    
    foreach ( $possible_paths as $path ) {
        if ( file_exists( $path . '/includes/functions.php' ) ) {
            $_tests_dir = $path;
            break;
        }
    }
}

if ( ! $_tests_dir ) {
    throw new Exception( 
        'WordPress tests directory not found. ' .
        'Please set WP_TESTS_DIR environment variable or install wordpress/tests package via Composer.'
    );
}

// Check if the WordPress test suite is available
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    throw new Exception( 
        "Could not find $_tests_dir/includes/functions.php. " .
        'Please check your WordPress test installation.'
    );
}

/**
 * WordPress Test Configuration Class
 */
class Amazon_Importer_Test_Config {
    
    /**
     * Plugin file path
     */
    private static $plugin_file;
    
    /**
     * Test data directory
     */
    private static $test_data_dir;
    
    /**
     * Initialize test configuration
     */
    public static function init() {
        self::$plugin_file = dirname( __DIR__ ) . '/amazon-product-importer.php';
        self::$test_data_dir = __DIR__ . '/data';
        
        // Create test data directory if it doesn't exist
        if ( ! is_dir( self::$test_data_dir ) ) {
            wp_mkdir_p( self::$test_data_dir );
        }
        
        self::setup_constants();
        self::setup_hooks();
    }
    
    /**
     * Setup test-specific constants
     */
    private static function setup_constants() {
        // Plugin constants
        define( 'AMAZON_PRODUCT_IMPORTER_VERSION', '1.0.0-test' );
        define( 'AMAZON_PRODUCT_IMPORTER_PLUGIN_FILE', self::$plugin_file );
        define( 'AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR', dirname( self::$plugin_file ) );
        define( 'AMAZON_PRODUCT_IMPORTER_PLUGIN_URL', 'http://example.org/wp-content/plugins/amazon-product-importer/' );
        
        // Test-specific constants
        define( 'AMAZON_IMPORTER_TEST_DATA_DIR', self::$test_data_dir );
        define( 'AMAZON_IMPORTER_MOCK_API', true );
        define( 'AMAZON_IMPORTER_SKIP_EXTERNAL_REQUESTS', true );
        
        // WooCommerce test constants
        define( 'WC_TAX_ROUNDING_MODE', 'auto' );
        define( 'WC_USE_TRANSACTIONS', false );
    }
    
    /**
     * Setup WordPress hooks for testing
     */
    private static function setup_hooks() {
        // Load our plugin after WordPress is loaded
        add_action( 'plugins_loaded', array( __CLASS__, 'load_plugin' ), 1 );
        
        // Setup WooCommerce for testing
        add_action( 'plugins_loaded', array( __CLASS__, 'setup_woocommerce' ), 5 );
        
        // Setup test fixtures
        add_action( 'init', array( __CLASS__, 'setup_test_fixtures' ), 20 );
        
        // Mock external HTTP requests
        add_filter( 'pre_http_request', array( __CLASS__, 'mock_http_requests' ), 10, 3 );
    }
    
    /**
     * Load the Amazon Product Importer plugin
     */
    public static function load_plugin() {
        if ( file_exists( self::$plugin_file ) ) {
            require_once self::$plugin_file;
            
            // Activate the plugin programmatically
            if ( function_exists( 'activate_amazon_product_importer' ) ) {
                activate_amazon_product_importer();
            }
            
            // Initialize plugin for testing
            if ( class_exists( 'Amazon_Product_Importer' ) ) {
                Amazon_Product_Importer::get_instance();
            }
        }
    }
    
    /**
     * Setup WooCommerce for testing
     */
    public static function setup_woocommerce() {
        // Check if WooCommerce is available
        if ( ! class_exists( 'WooCommerce' ) ) {
            // Try to load WooCommerce if available
            $wc_plugin_file = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
            if ( file_exists( $wc_plugin_file ) ) {
                require_once $wc_plugin_file;
            } else {
                throw new Exception( 'WooCommerce is required for Amazon Product Importer tests.' );
            }
        }
        
        // Install WooCommerce tables
        if ( class_exists( 'WC_Install' ) ) {
            WC_Install::install();
            
            // Set up WooCommerce defaults
            update_option( 'woocommerce_store_address', '123 Test Street' );
            update_option( 'woocommerce_store_city', 'Test City' );
            update_option( 'woocommerce_default_country', 'US:CA' );
            update_option( 'woocommerce_currency', 'USD' );
            update_option( 'woocommerce_manage_stock', 'yes' );
        }
    }
    
    /**
     * Setup test fixtures and sample data
     */
    public static function setup_test_fixtures() {
        // Create test Amazon products
        self::create_test_products();
        
        // Setup test Amazon API responses
        self::setup_mock_api_responses();
        
        // Create test users
        self::create_test_users();
    }
    
    /**
     * Create test products for Amazon import testing
     */
    private static function create_test_products() {
        // Sample Amazon product data
        $test_products = array(
            array(
                'asin' => 'B08N5WRWNW',
                'title' => 'Echo Dot (4th Gen) | Smart speaker with Alexa',
                'price' => '49.99',
                'brand' => 'Amazon',
                'description' => 'Our most popular smart speaker with a fabric design.',
                'region' => 'com'
            ),
            array(
                'asin' => 'B08N5WRWN1',
                'title' => 'Fire TV Stick 4K Max streaming device',
                'price' => '54.99',
                'brand' => 'Amazon',
                'description' => 'Our most powerful streaming stick.',
                'region' => 'com'
            ),
            array(
                'asin' => 'B08N5WRWN2',
                'title' => 'Kindle Paperwhite (11th generation)',
                'price' => '139.99',
                'brand' => 'Amazon',
                'description' => 'Now with a 6.8" display and adjustable warm light.',
                'region' => 'com'
            )
        );
        
        foreach ( $test_products as $product_data ) {
            // Create WooCommerce product
            $product = new WC_Product_Simple();
            $product->set_name( $product_data['title'] );
            $product->set_regular_price( $product_data['price'] );
            $product->set_description( $product_data['description'] );
            $product->set_short_description( $product_data['description'] );
            $product->set_sku( $product_data['asin'] );
            $product->set_status( 'publish' );
            $product->set_manage_stock( true );
            $product->set_stock_quantity( 10 );
            $product->set_stock_status( 'instock' );
            
            $product_id = $product->save();
            
            if ( $product_id ) {
                // Add Amazon-specific metadata
                update_post_meta( $product_id, '_amazon_asin', $product_data['asin'] );
                update_post_meta( $product_id, '_amazon_region', $product_data['region'] );
                update_post_meta( $product_id, '_amazon_brand', $product_data['brand'] );
                update_post_meta( $product_id, '_amazon_last_updated', current_time( 'mysql' ) );
                update_post_meta( $product_id, '_amazon_sync_status', 'synced' );
            }
        }
    }
    
    /**
     * Setup mock API responses for testing
     */
    private static function setup_mock_api_responses() {
        // Create sample API response files
        $api_responses = array(
            'get_items_response.json' => array(
                'ItemsResult' => array(
                    'Items' => array(
                        array(
                            'ASIN' => 'B08N5WRWNW',
                            'ItemInfo' => array(
                                'Title' => array(
                                    'DisplayValue' => 'Echo Dot (4th Gen) | Smart speaker with Alexa'
                                ),
                                'Features' => array(
                                    'DisplayValues' => array(
                                        'Our most popular smart speaker',
                                        'Compact design with fabric finish',
                                        'Voice control your smart home'
                                    )
                                )
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
                            'Images' => array(
                                'Primary' => array(
                                    'Large' => array(
                                        'URL' => 'https://example.com/image.jpg',
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
        
        foreach ( $api_responses as $filename => $data ) {
            $file_path = self::$test_data_dir . '/' . $filename;
            file_put_contents( $file_path, wp_json_encode( $data, JSON_PRETTY_PRINT ) );
        }
    }
    
    /**
     * Create test users
     */
    private static function create_test_users() {
        // Create admin user for tests
        if ( ! get_user_by( 'login', 'testadmin' ) ) {
            wp_create_user( 'testadmin', 'password', 'admin@example.com' );
            $user = get_user_by( 'login', 'testadmin' );
            $user->set_role( 'administrator' );
        }
        
        // Create shop manager user
        if ( ! get_user_by( 'login', 'shopmanager' ) ) {
            wp_create_user( 'shopmanager', 'password', 'manager@example.com' );
            $user = get_user_by( 'login', 'shopmanager' );
            $user->set_role( 'shop_manager' );
        }
    }
    
    /**
     * Mock HTTP requests for testing
     */
    public static function mock_http_requests( $preempt, $args, $url ) {
        // Only mock Amazon API requests
        if ( strpos( $url, 'webservices.amazon.' ) !== false ) {
            return self::get_mock_amazon_response( $url, $args );
        }
        
        // Let other requests pass through
        return $preempt;
    }
    
    /**
     * Get mock Amazon API response
     */
    private static function get_mock_amazon_response( $url, $args ) {
        // Parse request to determine response type
        $body = wp_remote_retrieve_body( $args );
        
        // Default successful response
        $response_data = array(
            'ItemsResult' => array(
                'Items' => array(
                    array(
                        'ASIN' => 'B08N5WRWNW',
                        'ItemInfo' => array(
                            'Title' => array(
                                'DisplayValue' => 'Mock Product Title'
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
                )
            )
        );
        
        return array(
            'headers' => array(
                'content-type' => 'application/json'
            ),
            'body' => wp_json_encode( $response_data ),
            'response' => array(
                'code' => 200,
                'message' => 'OK'
            ),
            'cookies' => array(),
            'filename' => null
        );
    }
    
    /**
     * Clean up test data
     */
    public static function cleanup() {
        // Remove test data files
        if ( is_dir( self::$test_data_dir ) ) {
            $files = glob( self::$test_data_dir . '/*' );
            foreach ( $files as $file ) {
                if ( is_file( $file ) ) {
                    unlink( $file );
                }
            }
        }
        
        // Clean up test products
        $products = get_posts( array(
            'post_type' => 'product',
            'meta_key' => '_amazon_asin',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ) );
        
        foreach ( $products as $product_id ) {
            wp_delete_post( $product_id, true );
        }
        
        // Clean up test users
        $test_users = array( 'testadmin', 'shopmanager' );
        foreach ( $test_users as $username ) {
            $user = get_user_by( 'login', $username );
            if ( $user ) {
                wp_delete_user( $user->ID );
            }
        }
    }
}

/**
 * Test Helper Functions
 */

/**
 * Get test data file path
 */
function amazon_importer_get_test_data_file( $filename ) {
    return AMAZON_IMPORTER_TEST_DATA_DIR . '/' . $filename;
}

/**
 * Load test data from JSON file
 */
function amazon_importer_load_test_data( $filename ) {
    $file_path = amazon_importer_get_test_data_file( $filename );
    if ( file_exists( $file_path ) ) {
        $content = file_get_contents( $file_path );
        return json_decode( $content, true );
    }
    return false;
}

/**
 * Create a test Amazon product
 */
function amazon_importer_create_test_product( $asin = 'TEST123', $data = array() ) {
    $defaults = array(
        'title' => 'Test Amazon Product',
        'price' => '29.99',
        'description' => 'This is a test product.',
        'brand' => 'Test Brand',
        'region' => 'com'
    );
    
    $data = wp_parse_args( $data, $defaults );
    
    $product = new WC_Product_Simple();
    $product->set_name( $data['title'] );
    $product->set_regular_price( $data['price'] );
    $product->set_description( $data['description'] );
    $product->set_sku( $asin );
    $product->set_status( 'publish' );
    
    $product_id = $product->save();
    
    if ( $product_id ) {
        update_post_meta( $product_id, '_amazon_asin', $asin );
        update_post_meta( $product_id, '_amazon_region', $data['region'] );
        update_post_meta( $product_id, '_amazon_brand', $data['brand'] );
    }
    
    return $product_id;
}

/**
 * Get mock Amazon API response
 */
function amazon_importer_get_mock_api_response( $type = 'get_items' ) {
    $responses = array(
        'get_items' => 'get_items_response.json',
        'search_items' => 'search_items_response.json',
        'get_variations' => 'get_variations_response.json'
    );
    
    $filename = isset( $responses[ $type ] ) ? $responses[ $type ] : 'get_items_response.json';
    return amazon_importer_load_test_data( $filename );
}

// Initialize test configuration
Amazon_Importer_Test_Config::init();

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

// Load WordPress test functions
function _manually_load_plugin() {
    // Load WooCommerce first
    if ( file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
        require WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
    }
    
    // Load our plugin
    Amazon_Importer_Test_Config::load_plugin();
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Include our custom test case classes
require_once __DIR__ . '/class-amazon-importer-test-case.php';

// Register shutdown function to clean up
register_shutdown_function( array( 'Amazon_Importer_Test_Config', 'cleanup' ) );

// Output test environment information
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    echo "Amazon Product Importer Test Environment Loaded\n";
    echo "WordPress Version: " . get_bloginfo( 'version' ) . "\n";
    echo "WooCommerce Version: " . ( defined( 'WC_VERSION' ) ? WC_VERSION : 'Not Available' ) . "\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Test Database: " . DB_NAME . "\n";
}