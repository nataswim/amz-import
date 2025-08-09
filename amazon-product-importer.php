<?php
/**
 * Plugin Name: Amazon Product Importer
 * Plugin URI: https://example.com/amazon-product-importer
 * Description: Importez facilement des produits Amazon dans votre boutique WooCommerce
 * Version: 1.0.0
 * Author: Votre Nom
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: amazon-product-importer
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.0
 * Tested up to: 6.3
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 */
define('AMAZON_PRODUCT_IMPORTER_VERSION', '1.0.0');
define('AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AMAZON_PRODUCT_IMPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AMAZON_PRODUCT_IMPORTER_BASENAME', plugin_basename(__FILE__));

/**
 * Check if WooCommerce is active
 */
function amazon_product_importer_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e('Amazon Product Importer nécessite WooCommerce pour fonctionner.', 'amazon-product-importer'); ?></p>
            </div>
            <?php
        });
        return false;
    }
    return true;
}

/**
 * The code that runs during plugin activation.
 */
function activate_amazon_product_importer() {
    if (!amazon_product_importer_check_woocommerce()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('Ce plugin nécessite WooCommerce pour fonctionner.', 'amazon-product-importer'));
    }
    require_once plugin_dir_path(__FILE__) . 'includes/class-activator.php';
    Amazon_Product_Importer_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_amazon_product_importer() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-deactivator.php';
    Amazon_Product_Importer_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_amazon_product_importer');
register_deactivation_hook(__FILE__, 'deactivate_amazon_product_importer');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-amazon-product-importer.php';

/**
 * Begins execution of the plugin.
 */
function run_amazon_product_importer() {
    $plugin = new Amazon_Product_Importer();
    $plugin->run();
}

// Check WooCommerce and run plugin
add_action('plugins_loaded', function() {
    if (amazon_product_importer_check_woocommerce()) {
        run_amazon_product_importer();
    }
});