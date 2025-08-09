<?php
/**
 * Composer Autoloader for Amazon Product Importer
 *
 * This file provides autoloading functionality for the Amazon Product Importer plugin.
 * It handles both Composer-managed dependencies and custom plugin classes.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/vendor
 * @since      1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Amazon Product Importer Autoloader Class
 */
class Amazon_Product_Importer_Autoloader {

    /**
     * Plugin directory path
     *
     * @var string
     */
    private static $plugin_dir;

    /**
     * Registered namespaces
     *
     * @var array
     */
    private static $namespaces = array();

    /**
     * Class aliases
     *
     * @var array
     */
    private static $aliases = array();

    /**
     * Composer autoloader instance
     *
     * @var object
     */
    private static $composer_autoloader;

    /**
     * Whether the autoloader has been initialized
     *
     * @var bool
     */
    private static $initialized = false;

    /**
     * Initialize the autoloader
     *
     * @param string $plugin_dir Plugin directory path
     */
    public static function init( $plugin_dir = null ) {
        if ( self::$initialized ) {
            return;
        }

        self::$plugin_dir = $plugin_dir ?: dirname( __DIR__ );
        
        // Register the autoloader
        spl_autoload_register( array( __CLASS__, 'autoload' ), true, true );

        // Setup default namespaces
        self::setup_namespaces();

        // Setup class aliases
        self::setup_aliases();

        // Load Composer autoloader if available
        self::load_composer_autoloader();

        // Load helper functions
        self::load_helper_functions();

        self::$initialized = true;
    }

    /**
     * Autoload classes
     *
     * @param string $class_name Class name to load
     * @return bool Whether the class was loaded
     */
    public static function autoload( $class_name ) {
        // Handle class aliases first
        if ( isset( self::$aliases[ $class_name ] ) ) {
            $class_name = self::$aliases[ $class_name ];
        }

        // Try namespace-based loading first
        if ( self::load_by_namespace( $class_name ) ) {
            return true;
        }

        // Fall back to plugin-specific loading
        if ( self::load_plugin_class( $class_name ) ) {
            return true;
        }

        // Try Composer autoloader if available
        if ( self::$composer_autoloader && is_callable( array( self::$composer_autoloader, 'loadClass' ) ) ) {
            return self::$composer_autoloader->loadClass( $class_name );
        }

        return false;
    }

    /**
     * Register a namespace for autoloading
     *
     * @param string $namespace Namespace
     * @param string $directory Directory path
     */
    public static function register_namespace( $namespace, $directory ) {
        $namespace = rtrim( $namespace, '\\' ) . '\\';
        $directory = rtrim( $directory, '/' ) . '/';
        
        self::$namespaces[ $namespace ] = $directory;
    }

    /**
     * Register a class alias
     *
     * @param string $alias Alias name
     * @param string $class_name Real class name
     */
    public static function register_alias( $alias, $class_name ) {
        self::$aliases[ $alias ] = $class_name;
    }

    /**
     * Get the plugin directory
     *
     * @return string Plugin directory path
     */
    public static function get_plugin_dir() {
        return self::$plugin_dir;
    }

    /**
     * Setup default namespaces
     */
    private static function setup_namespaces() {
        $includes_dir = self::$plugin_dir . '/includes/';
        
        // Core namespaces
        self::register_namespace( 'Amazon_Product_Importer\\', $includes_dir );
        self::register_namespace( 'Amazon_Product_Importer\\API\\', $includes_dir . 'api/' );
        self::register_namespace( 'Amazon_Product_Importer\\Import\\', $includes_dir . 'import/' );
        self::register_namespace( 'Amazon_Product_Importer\\Cron\\', $includes_dir . 'cron/' );
        self::register_namespace( 'Amazon_Product_Importer\\Database\\', $includes_dir . 'database/' );
        self::register_namespace( 'Amazon_Product_Importer\\Utilities\\', $includes_dir . 'utilities/' );

        // Admin namespaces
        $admin_dir = self::$plugin_dir . '/admin/';
        self::register_namespace( 'Amazon_Product_Importer\\Admin\\', $admin_dir );

        // Public namespaces
        $public_dir = self::$plugin_dir . '/public/';
        self::register_namespace( 'Amazon_Product_Importer\\Public\\', $public_dir );

        // Test namespaces
        $tests_dir = self::$plugin_dir . '/tests/';
        self::register_namespace( 'Amazon_Product_Importer\\Tests\\', $tests_dir );
    }

    /**
     * Setup class aliases for backward compatibility
     */
    private static function setup_aliases() {
        // Main plugin classes
        self::register_alias( 'Amazon_Product_Importer', 'Amazon_Product_Importer\\Amazon_Product_Importer' );
        self::register_alias( 'Amazon_Activator', 'Amazon_Product_Importer\\Amazon_Activator' );
        self::register_alias( 'Amazon_Deactivator', 'Amazon_Product_Importer\\Amazon_Deactivator' );

        // API classes
        self::register_alias( 'Amazon_Api', 'Amazon_Product_Importer\\API\\Amazon_Api' );
        self::register_alias( 'Amazon_Auth', 'Amazon_Product_Importer\\API\\Amazon_Auth' );
        self::register_alias( 'Amazon_Api_Request', 'Amazon_Product_Importer\\API\\Amazon_Api_Request' );
        self::register_alias( 'Amazon_Api_Response_Parser', 'Amazon_Product_Importer\\API\\Amazon_Api_Response_Parser' );

        // Import classes
        self::register_alias( 'Amazon_Product_Importer_Import', 'Amazon_Product_Importer\\Import\\Amazon_Product_Importer_Import' );
        self::register_alias( 'Amazon_Product_Mapper', 'Amazon_Product_Importer\\Import\\Amazon_Product_Mapper' );
        self::register_alias( 'Amazon_Image_Handler', 'Amazon_Product_Importer\\Import\\Amazon_Image_Handler' );
        self::register_alias( 'Amazon_Category_Handler', 'Amazon_Product_Importer\\Import\\Amazon_Category_Handler' );
        self::register_alias( 'Amazon_Variation_Handler', 'Amazon_Product_Importer\\Import\\Amazon_Variation_Handler' );
        self::register_alias( 'Amazon_Price_Updater', 'Amazon_Product_Importer\\Import\\Amazon_Price_Updater' );

        // Cron classes
        self::register_alias( 'Amazon_Cron_Manager', 'Amazon_Product_Importer\\Cron\\Amazon_Cron_Manager' );
        self::register_alias( 'Amazon_Price_Sync', 'Amazon_Product_Importer\\Cron\\Amazon_Price_Sync' );
        self::register_alias( 'Amazon_Product_Updater', 'Amazon_Product_Importer\\Cron\\Amazon_Product_Updater' );

        // Database classes
        self::register_alias( 'Amazon_Database', 'Amazon_Product_Importer\\Database\\Amazon_Database' );
        self::register_alias( 'Amazon_Product_Meta', 'Amazon_Product_Importer\\Database\\Amazon_Product_Meta' );

        // Utility classes
        self::register_alias( 'Amazon_Logger', 'Amazon_Product_Importer\\Utilities\\Amazon_Logger' );
        self::register_alias( 'Amazon_Validator', 'Amazon_Product_Importer\\Utilities\\Amazon_Validator' );
        self::register_alias( 'Amazon_Cache', 'Amazon_Product_Importer\\Utilities\\Amazon_Cache' );
        self::register_alias( 'Amazon_Helper', 'Amazon_Product_Importer\\Utilities\\Amazon_Helper' );

        // Admin classes
        self::register_alias( 'Amazon_Product_Importer_Admin', 'Amazon_Product_Importer\\Admin\\Amazon_Product_Importer_Admin' );
        self::register_alias( 'Amazon_Admin_Settings', 'Amazon_Product_Importer\\Admin\\Amazon_Admin_Settings' );
        self::register_alias( 'Amazon_Admin_Import', 'Amazon_Product_Importer\\Admin\\Amazon_Admin_Import' );

        // Public classes
        self::register_alias( 'Amazon_Product_Importer_Public', 'Amazon_Product_Importer\\Public\\Amazon_Product_Importer_Public' );

        // Test classes
        self::register_alias( 'Amazon_Importer_Test_Case', 'Amazon_Product_Importer\\Tests\\Amazon_Importer_Test_Case' );
    }

    /**
     * Load class by namespace
     *
     * @param string $class_name Class name
     * @return bool Whether the class was loaded
     */
    private static function load_by_namespace( $class_name ) {
        foreach ( self::$namespaces as $namespace => $directory ) {
            if ( strpos( $class_name, $namespace ) === 0 ) {
                $relative_class = substr( $class_name, strlen( $namespace ) );
                $file_path = $directory . str_replace( '\\', '/', $relative_class ) . '.php';
                
                if ( file_exists( $file_path ) ) {
                    require_once $file_path;
                    return class_exists( $class_name, false ) || interface_exists( $class_name, false ) || trait_exists( $class_name, false );
                }
            }
        }

        return false;
    }

    /**
     * Load plugin-specific classes (legacy support)
     *
     * @param string $class_name Class name
     * @return bool Whether the class was loaded
     */
    private static function load_plugin_class( $class_name ) {
        // Skip if not an Amazon plugin class
        if ( strpos( $class_name, 'Amazon_' ) !== 0 && strpos( $class_name, 'WC_' ) !== 0 ) {
            return false;
        }

        $file_name = self::class_name_to_file_name( $class_name );
        $search_paths = self::get_search_paths();

        foreach ( $search_paths as $path ) {
            $file_path = $path . $file_name;
            
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
                return class_exists( $class_name, false );
            }
        }

        return false;
    }

    /**
     * Convert class name to file name
     *
     * @param string $class_name Class name
     * @return string File name
     */
    private static function class_name_to_file_name( $class_name ) {
        // Convert class name to file name following WordPress conventions
        $file_name = strtolower( str_replace( '_', '-', $class_name ) );
        
        // Add class prefix if not present
        if ( strpos( $file_name, 'class-' ) !== 0 && strpos( $file_name, 'interface-' ) !== 0 && strpos( $file_name, 'trait-' ) !== 0 ) {
            $file_name = 'class-' . $file_name;
        }
        
        return $file_name . '.php';
    }

    /**
     * Get search paths for plugin classes
     *
     * @return array Search paths
     */
    private static function get_search_paths() {
        $base_path = self::$plugin_dir . '/';
        
        return array(
            $base_path . 'includes/',
            $base_path . 'includes/api/',
            $base_path . 'includes/import/',
            $base_path . 'includes/cron/',
            $base_path . 'includes/database/',
            $base_path . 'includes/utilities/',
            $base_path . 'admin/',
            $base_path . 'public/',
            $base_path . 'tests/',
        );
    }

    /**
     * Load Composer autoloader if available
     */
    private static function load_composer_autoloader() {
        $composer_autoload = self::$plugin_dir . '/vendor/composer/autoload_real.php';
        
        if ( file_exists( $composer_autoload ) ) {
            require_once $composer_autoload;
            
            $composer_class_name = 'ComposerAutoloaderInit' . md5( __DIR__ );
            if ( class_exists( $composer_class_name ) ) {
                self::$composer_autoloader = $composer_class_name::getLoader();
            }
        }
    }

    /**
     * Load helper functions
     */
    private static function load_helper_functions() {
        $functions_files = array(
            self::$plugin_dir . '/includes/functions.php',
            self::$plugin_dir . '/includes/template-functions.php',
            self::$plugin_dir . '/includes/deprecated-functions.php',
        );

        foreach ( $functions_files as $file ) {
            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    }

    /**
     * Get all registered namespaces
     *
     * @return array Registered namespaces
     */
    public static function get_namespaces() {
        return self::$namespaces;
    }

    /**
     * Get all registered aliases
     *
     * @return array Registered aliases
     */
    public static function get_aliases() {
        return self::$aliases;
    }

    /**
     * Check if a class is autoloadable
     *
     * @param string $class_name Class name
     * @return bool Whether the class is autoloadable
     */
    public static function is_autoloadable( $class_name ) {
        // Check aliases
        if ( isset( self::$aliases[ $class_name ] ) ) {
            return true;
        }

        // Check namespaces
        foreach ( self::$namespaces as $namespace => $directory ) {
            if ( strpos( $class_name, $namespace ) === 0 ) {
                $relative_class = substr( $class_name, strlen( $namespace ) );
                $file_path = $directory . str_replace( '\\', '/', $relative_class ) . '.php';
                
                if ( file_exists( $file_path ) ) {
                    return true;
                }
            }
        }

        // Check plugin-specific paths
        if ( strpos( $class_name, 'Amazon_' ) === 0 || strpos( $class_name, 'WC_' ) === 0 ) {
            $file_name = self::class_name_to_file_name( $class_name );
            $search_paths = self::get_search_paths();

            foreach ( $search_paths as $path ) {
                if ( file_exists( $path . $file_name ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Unregister the autoloader
     */
    public static function unregister() {
        spl_autoload_unregister( array( __CLASS__, 'autoload' ) );
        self::$initialized = false;
    }

    /**
     * Debug information about the autoloader
     *
     * @return array Debug information
     */
    public static function debug_info() {
        return array(
            'initialized' => self::$initialized,
            'plugin_dir' => self::$plugin_dir,
            'namespaces' => self::$namespaces,
            'aliases' => self::$aliases,
            'composer_autoloader' => self::$composer_autoloader ? get_class( self::$composer_autoloader ) : null,
            'search_paths' => self::get_search_paths(),
        );
    }
}

/**
 * Initialize the autoloader
 */
Amazon_Product_Importer_Autoloader::init( dirname( __DIR__ ) );

/**
 * Global helper functions for the autoloader
 */

if ( ! function_exists( 'amazon_importer_autoload_class' ) ) {
    /**
     * Manually load a class
     *
     * @param string $class_name Class name to load
     * @return bool Whether the class was loaded
     */
    function amazon_importer_autoload_class( $class_name ) {
        return Amazon_Product_Importer_Autoloader::autoload( $class_name );
    }
}

if ( ! function_exists( 'amazon_importer_register_namespace' ) ) {
    /**
     * Register a namespace for autoloading
     *
     * @param string $namespace Namespace
     * @param string $directory Directory path
     */
    function amazon_importer_register_namespace( $namespace, $directory ) {
        Amazon_Product_Importer_Autoloader::register_namespace( $namespace, $directory );
    }
}

if ( ! function_exists( 'amazon_importer_register_alias' ) ) {
    /**
     * Register a class alias
     *
     * @param string $alias Alias name
     * @param string $class_name Real class name
     */
    function amazon_importer_register_alias( $alias, $class_name ) {
        Amazon_Product_Importer_Autoloader::register_alias( $alias, $class_name );
    }
}

if ( ! function_exists( 'amazon_importer_is_class_autoloadable' ) ) {
    /**
     * Check if a class is autoloadable
     *
     * @param string $class_name Class name
     * @return bool Whether the class is autoloadable
     */
    function amazon_importer_is_class_autoloadable( $class_name ) {
        return Amazon_Product_Importer_Autoloader::is_autoloadable( $class_name );
    }
}

// Load essential classes that might be needed immediately
$essential_classes = array(
    'Amazon_Product_Importer',
    'Amazon_Logger',
    'Amazon_Helper',
    'Amazon_Validator'
);

foreach ( $essential_classes as $class ) {
    if ( ! class_exists( $class ) ) {
        Amazon_Product_Importer_Autoloader::autoload( $class );
    }
}

// Hook into WordPress to provide additional debugging if needed
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
    add_action( 'init', function() {
        if ( isset( $_GET['amazon_debug_autoloader'] ) && current_user_can( 'manage_options' ) ) {
            error_log( 'Amazon Product Importer Autoloader Debug Info: ' . print_r( Amazon_Product_Importer_Autoloader::debug_info(), true ) );
        }
    } );
}

// Return the autoloader instance for advanced usage
return Amazon_Product_Importer_Autoloader::class;