<?php
/**
 * Plugin Name:       Amazon Product Importer for WooCommerce
 * Plugin URI:        https://github.com/yourname/amazon-product-importer
 * Description:       Import Amazon products directly into your WooCommerce store with advanced search, automatic synchronization, and variation handling.
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://yourwebsite.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       amazon-product-importer
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Tested up to:      6.3
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * WC tested up to:   8.0
 * Network:           false
 *
 * @package AmazonProductImporter
 * @author  Your Name
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Amazon Product Importer requires WooCommerce to be installed and active.', 'amazon-product-importer');
        echo '</p></div>';
    });
    return;
}

/**
 * Define Plugin Constants
 */
define('AMAZON_PRODUCT_IMPORTER_VERSION', '1.0.0');
define('AMAZON_PRODUCT_IMPORTER_PLUGIN_FILE', __FILE__);
define('AMAZON_PRODUCT_IMPORTER_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AMAZON_PRODUCT_IMPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR', dirname(__FILE__));
define('AMAZON_PRODUCT_IMPORTER_PLUGIN_SLUG', 'amazon-product-importer');
define('AMAZON_PRODUCT_IMPORTER_TEXT_DOMAIN', 'amazon-product-importer');

// Database table names
define('AMAZON_PRODUCT_IMPORTER_TABLE_IMPORTS', 'amazon_product_imports');
define('AMAZON_PRODUCT_IMPORTER_TABLE_SYNC_LOG', 'amazon_sync_log');

/**
 * Plugin activation hook
 */
function activate_amazon_product_importer() {
    require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/class-activator.php';
    Amazon_Product_Importer_Activator::activate();
}
register_activation_hook(__FILE__, 'activate_amazon_product_importer');

/**
 * Plugin deactivation hook
 */
function deactivate_amazon_product_importer() {
    require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/class-deactivator.php';
    Amazon_Product_Importer_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'deactivate_amazon_product_importer');

/**
 * Load plugin textdomain for translations
 */
function amazon_product_importer_load_textdomain() {
    load_plugin_textdomain(
        'amazon-product-importer',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('plugins_loaded', 'amazon_product_importer_load_textdomain');

/**
 * Check PHP version compatibility
 */
function amazon_product_importer_php_version_check() {
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            printf(
                esc_html__('Amazon Product Importer requires PHP version 7.4 or higher. You are running version %s.', 'amazon-product-importer'),
                PHP_VERSION
            );
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * Initialize the plugin
 */
function run_amazon_product_importer() {
    
    // Check PHP version
    if (!amazon_product_importer_php_version_check()) {
        return;
    }
    
    // Load core files
    require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/class-amazon-product-importer.php';
    require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/class-loader.php';
    require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/class-i18n.php';
    
    // Initialize the main plugin class
    $plugin = new Amazon_Product_Importer();
    $plugin->run();
}

/**
 * Add settings link to plugin page
 */
function amazon_product_importer_add_settings_link($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url('admin.php?page=amazon-product-importer-settings'),
        esc_html__('Settings', 'amazon-product-importer')
    );
    array_unshift($links, $settings_link);
    
    $import_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url('admin.php?page=amazon-product-importer'),
        esc_html__('Import Products', 'amazon-product-importer')
    );
    array_unshift($links, $import_link);
    
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'amazon_product_importer_add_settings_link');

/**
 * Add plugin meta links
 */
function amazon_product_importer_plugin_meta_links($links, $file) {
    if ($file === plugin_basename(__FILE__)) {
        $meta_links = array(
            'docs' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                'https://github.com/yourname/amazon-product-importer/wiki',
                esc_html__('Documentation', 'amazon-product-importer')
            ),
            'support' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                'https://github.com/yourname/amazon-product-importer/issues',
                esc_html__('Support', 'amazon-product-importer')
            )
        );
        $links = array_merge($links, $meta_links);
    }
    return $links;
}
add_filter('plugin_row_meta', 'amazon_product_importer_plugin_meta_links', 10, 2);

/**
 * Display admin notice for missing API credentials
 */
function amazon_product_importer_admin_notices() {
    
    // Only show on plugin pages
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'amazon-product-importer') === false) {
        return;
    }
    
    $access_key = get_option('amazon_product_importer_access_key_id', '');
    $secret_key = get_option('amazon_product_importer_secret_access_key', '');
    $associate_tag = get_option('amazon_product_importer_associate_tag', '');
    
    if (empty($access_key) || empty($secret_key) || empty($associate_tag)) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p>';
        printf(
            esc_html__('Please configure your Amazon API credentials in the %s to start importing products.', 'amazon-product-importer'),
            sprintf('<a href="%s">%s</a>', 
                admin_url('admin.php?page=amazon-product-importer-settings'),
                esc_html__('settings page', 'amazon-product-importer')
            )
        );
        echo '</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'amazon_product_importer_admin_notices');

/**
 * Handle plugin updates
 */
function amazon_product_importer_check_version() {
    $installed_version = get_option('amazon_product_importer_version', '0.0.0');
    
    if (version_compare($installed_version, AMAZON_PRODUCT_IMPORTER_VERSION, '<')) {
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'includes/class-activator.php';
        Amazon_Product_Importer_Activator::update($installed_version);
        update_option('amazon_product_importer_version', AMAZON_PRODUCT_IMPORTER_VERSION);
    }
}
add_action('plugins_loaded', 'amazon_product_importer_check_version');

/**
 * Add custom cron schedules
 */
function amazon_product_importer_cron_schedules($schedules) {
    $schedules['every_15_minutes'] = array(
        'interval' => 900, // 15 minutes
        'display'  => esc_html__('Every 15 Minutes', 'amazon-product-importer')
    );
    
    $schedules['every_30_minutes'] = array(
        'interval' => 1800, // 30 minutes
        'display'  => esc_html__('Every 30 Minutes', 'amazon-product-importer')
    );
    
    $schedules['every_6_hours'] = array(
        'interval' => 21600, // 6 hours
        'display'  => esc_html__('Every 6 Hours', 'amazon-product-importer')
    );
    
    return $schedules;
}
add_filter('cron_schedules', 'amazon_product_importer_cron_schedules');

/**
 * Handle AJAX requests for non-logged users (if needed)
 */
function amazon_product_importer_ajax_handler() {
    // Add any public AJAX handlers here if needed
}
add_action('wp_ajax_nopriv_amazon_product_importer', 'amazon_product_importer_ajax_handler');
add_action('wp_ajax_amazon_product_importer', 'amazon_product_importer_ajax_handler');

/**
 * Debug function - remove in production
 */
if (!function_exists('amazon_product_importer_debug_log')) {
    function amazon_product_importer_debug_log($message, $data = null) {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            $log_message = '[Amazon Product Importer] ' . $message;
            if ($data !== null) {
                $log_message .= ' | Data: ' . print_r($data, true);
            }
            error_log($log_message);
        }
    }
}

/**
 * Initialize plugin after WordPress and WooCommerce are loaded
 */
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        run_amazon_product_importer();
    }
});

/**
 * Add custom capabilities for plugin access
 */
function amazon_product_importer_add_capabilities() {
    $role = get_role('administrator');
    if ($role) {
        $role->add_cap('manage_amazon_product_importer');
        $role->add_cap('import_amazon_products');
    }
    
    $role = get_role('shop_manager');
    if ($role) {
        $role->add_cap('manage_amazon_product_importer');
        $role->add_cap('import_amazon_products');
    }
}
register_activation_hook(__FILE__, 'amazon_product_importer_add_capabilities');

/**
 * Remove custom capabilities on deactivation
 */
function amazon_product_importer_remove_capabilities() {
    $roles = ['administrator', 'shop_manager'];
    
    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->remove_cap('manage_amazon_product_importer');
            $role->remove_cap('import_amazon_products');
        }
    }
}
register_deactivation_hook(__FILE__, 'amazon_product_importer_remove_capabilities');

// Prevent any accidental output
if (!defined('AMAZON_PRODUCT_IMPORTER_PLUGIN_FILE')) {
    exit;
}