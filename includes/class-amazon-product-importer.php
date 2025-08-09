<?php

/**
 * The core plugin class.
 */
class Amazon_Product_Importer {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct() {
        if (defined('AMAZON_PRODUCT_IMPORTER_VERSION')) {
            $this->version = AMAZON_PRODUCT_IMPORTER_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'amazon-product-importer';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_cron_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        // The class responsible for orchestrating the actions and filters
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/class-loader.php';

        // The class responsible for defining internationalization functionality
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/class-i18n.php';

        // API classes
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/api/class-amazon-api.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/api/class-amazon-auth.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/api/class-api-request.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/api/class-api-response-parser.php';

        // Import classes
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/import/class-product-importer.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/import/class-product-mapper.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/import/class-image-handler.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/import/class-category-handler.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/import/class-variation-handler.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/import/class-price-updater.php';

        // Cron classes
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/cron/class-cron-manager.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/cron/class-price-sync.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/cron/class-product-updater.php';

        // Database classes
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/database/class-database.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/database/class-product-meta.php';

        // Utility classes
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/utilities/class-logger.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/utilities/class-validator.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/utilities/class-cache.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'includes/utilities/class-helper.php';

        // Admin classes
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'admin/class-admin.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'admin/class-admin-settings.php';
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'admin/class-admin-import.php';

        // Public classes
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'public/class-public.php';

        $this->loader = new Amazon_Product_Importer_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     */
    private function set_locale() {
        $plugin_i18n = new Amazon_Product_Importer_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     */
    private function define_admin_hooks() {
        $plugin_admin = new Amazon_Product_Importer_Admin($this->get_plugin_name(), $this->get_version());
        $plugin_settings = new Amazon_Product_Importer_Admin_Settings($this->get_plugin_name(), $this->get_version());
        $plugin_import = new Amazon_Product_Importer_Admin_Import($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');

        // Settings hooks
        $this->loader->add_action('admin_init', $plugin_settings, 'init_settings');

        // Import hooks
        $this->loader->add_action('wp_ajax_api_search_products', $plugin_import, 'ajax_search_products');
        $this->loader->add_action('wp_ajax_api_import_product', $plugin_import, 'ajax_import_product');
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     */
    private function define_public_hooks() {
        $plugin_public = new Amazon_Product_Importer_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    /**
     * Register all cron hooks
     */
    private function define_cron_hooks() {
        $cron_manager = new Amazon_Product_Importer_Cron_Manager();
        
        $this->loader->add_action('init', $cron_manager, 'init');
        $this->loader->add_action('api_price_sync_hourly', $cron_manager, 'run_price_sync');
        $this->loader->add_action('api_product_update_daily', $cron_manager, 'run_product_update');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to  identify it within the context of
     * WordPress and to define internationalization functionality.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
}