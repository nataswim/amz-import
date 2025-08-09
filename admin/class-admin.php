<?php

/**
 * The admin-specific functionality of the plugin.
 */
class Amazon_Product_Importer_Admin {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name, 
            AMAZON_PRODUCT_IMPORTER_PLUGIN_URL . 'admin/css/admin.css', 
            array(), 
            $this->version, 
            'all'
        );
        
        wp_enqueue_style(
            $this->plugin_name . '-import', 
            AMAZON_PRODUCT_IMPORTER_PLUGIN_URL . 'admin/css/import-interface.css', 
            array(), 
            $this->version, 
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name, 
            AMAZON_PRODUCT_IMPORTER_PLUGIN_URL . 'admin/js/admin.js', 
            array('jquery'), 
            $this->version, 
            false
        );
        
        wp_enqueue_script(
            $this->plugin_name . '-import', 
            AMAZON_PRODUCT_IMPORTER_PLUGIN_URL . 'admin/js/import-interface.js', 
            array('jquery'), 
            $this->version, 
            false
        );
        
        wp_enqueue_script(
            $this->plugin_name . '-settings', 
            AMAZON_PRODUCT_IMPORTER_PLUGIN_URL . 'admin/js/settings.js', 
            array('jquery'), 
            $this->version, 
            false
        );

        // Localize script with AJAX data
        wp_localize_script($this->plugin_name . '-import', 'amazon_importer_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('amazon_importer_nonce'),
            'strings' => array(
                'searching' => __('Recherche en cours...', 'amazon-product-importer'),
                'importing' => __('Importation en cours...', 'amazon-product-importer'),
                'success' => __('Produit importé avec succès', 'amazon-product-importer'),
                'error' => __('Erreur lors de l\'importation', 'amazon-product-importer'),
                'no_results' => __('Aucun résultat trouvé', 'amazon-product-importer')
            )
        ));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Amazon Importer', 'amazon-product-importer'),
            __('Amazon Importer', 'amazon-product-importer'),
            'manage_woocommerce',
            'amazon-product-importer',
            array($this, 'display_admin_page'),
            'dashicons-amazon',
            55
        );

        // Import submenu
        add_submenu_page(
            'amazon-product-importer',
            __('Importer des produits', 'amazon-product-importer'),
            __('Importer', 'amazon-product-importer'),
            'manage_woocommerce',
            'amazon-product-importer',
            array($this, 'display_admin_page')
        );

        // Settings submenu
        add_submenu_page(
            'amazon-product-importer',
            __('Paramètres', 'amazon-product-importer'),
            __('Paramètres', 'amazon-product-importer'),
            'manage_options',
            'amazon-importer-settings',
            array($this, 'display_settings_page')
        );

        // Logs submenu
        add_submenu_page(
            'amazon-product-importer',
            __('Logs', 'amazon-product-importer'),
            __('Logs', 'amazon-product-importer'),
            'manage_woocommerce',
            'amazon-importer-logs',
            array($this, 'display_logs_page')
        );
    }

    /**
     * Display main admin page
     */
    public function display_admin_page() {
        include_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'admin/partials/admin-import-display.php';
    }

    /**
     * Display settings page
     */
    public function display_settings_page() {
        include_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'admin/partials/admin-settings-display.php';
    }

    /**
     * Display logs page
     */
    public function display_logs_page() {
        $logger = new Amazon_Product_Importer_Logger();
        $logs = $logger->get_recent_logs(100);
        include_once AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'admin/partials/admin-logs-display.php';
    }

    /**
     * Add product meta box to edit product page
     */
    public function add_product_meta_boxes() {
        add_meta_box(
            'amazon-product-info',
            __('Informations Amazon', 'amazon-product-importer'),
            array($this, 'display_product_meta_box'),
            'product',
            'side',
            'high'
        );
    }

    /**
     * Display product meta box
     */
    public function display_product_meta_box($post) {
        $asin = get_post_meta($post->ID, '_amazon_asin', true);
        $sync_enabled = get_post_meta($post->ID, '_amazon_sync_enabled', true);
        $last_sync = get_post_meta($post->ID, '_amazon_last_sync', true);
        
        include AMAZON_PRODUCT_IMPORTER_PLUGIN_DIR . 'templates/admin/metaboxes/amazon-product-info.php';
    }

    /**
     * Save product meta box data
     */
    public function save_product_meta_box($post_id) {
        if (!wp_verify_nonce($_POST['amazon_product_nonce'], 'amazon_product_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['amazon_sync_enabled'])) {
            update_post_meta($post_id, '_amazon_sync_enabled', sanitize_text_field($_POST['amazon_sync_enabled']));
        }
    }
}