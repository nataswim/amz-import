<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/admin
 * @author     Your Name <your.email@example.com>
 */
class Amazon_Product_Importer_Admin {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * The settings instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Admin_Settings    $settings
     */
    private $settings;

    /**
     * The import interface instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Admin_Import    $import
     */
    private $import;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        $this->load_dependencies();
        $this->init_admin_classes();
    }

    /**
     * Load the required dependencies for the Admin area.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        
        /**
         * The class responsible for defining admin settings functionality.
         */
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'admin/class-admin-settings.php';
        
        /**
         * The class responsible for defining import interface functionality.
         */
        require_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'admin/class-admin-import.php';
    }

    /**
     * Initialize admin classes.
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_admin_classes() {
        $this->settings = new Amazon_Product_Importer_Admin_Settings($this->plugin_name, $this->version);
        $this->import = new Amazon_Product_Importer_Admin_Import($this->plugin_name, $this->version);
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        
        // Get current screen
        $screen = get_current_screen();
        
        // Only load on our admin pages
        if (!$this->is_plugin_admin_page($screen)) {
            return;
        }

        // Main admin styles
        wp_enqueue_style(
            $this->plugin_name . '-admin',
            AMAZON_PRODUCT_IMPORTER_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            $this->version,
            'all'
        );

        // Import interface specific styles
        if ($this->is_import_page($screen)) {
            wp_enqueue_style(
                $this->plugin_name . '-import',
                AMAZON_PRODUCT_IMPORTER_PLUGIN_URL . 'admin/css/import-interface.css',
                array($this->plugin_name . '-admin'),
                $this->version,
                'all'
            );
        }

        // WordPress core styles that we need
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_style('thickbox');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        
        // Get current screen
        $screen = get_current_screen();
        
        // Only load on our admin pages
        if (!$this->is_plugin_admin_page($screen)) {
            return;
        }

        // Main admin script
        wp_enqueue_script(
            $this->plugin_name . '-admin',
            AMAZON_PRODUCT_IMPORTER_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery', 'wp-color-picker', 'thickbox'),
            $this->version,
            false
        );

        // Import interface specific script
        if ($this->is_import_page($screen)) {
            wp_enqueue_script(
                $this->plugin_name . '-import',
                AMAZON_PRODUCT_IMPORTER_PLUGIN_URL . 'admin/js/import-interface.js',
                array('jquery', $this->plugin_name . '-admin'),
                $this->version,
                false
            );

            // Localize script for AJAX calls
            wp_localize_script(
                $this->plugin_name . '-import',
                'amazon_product_importer_ajax',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('amazon_product_importer_nonce'),
                    'strings' => array(
                        'searching' => esc_html__('Searching Amazon products...', 'amazon-product-importer'),
                        'importing' => esc_html__('Importing product...', 'amazon-product-importer'),
                        'success' => esc_html__('Product imported successfully!', 'amazon-product-importer'),
                        'error' => esc_html__('Import failed. Please try again.', 'amazon-product-importer'),
                        'confirm_import' => esc_html__('Are you sure you want to import this product?', 'amazon-product-importer'),
                        'batch_import_confirm' => esc_html__('Import %d selected products?', 'amazon-product-importer'),
                    )
                )
            );
        }

        // Settings page specific script
        if ($this->is_settings_page($screen)) {
            wp_enqueue_script(
                $this->plugin_name . '-settings',
                AMAZON_PRODUCT_IMPORTER_PLUGIN_URL . 'admin/js/settings.js',
                array('jquery', $this->plugin_name . '-admin'),
                $this->version,
                false
            );
        }
    }

    /**
     * Add the plugin admin menu.
     *
     * @since    1.0.0
     */
    public function add_admin_menu() {
        
        // Main menu page
        add_menu_page(
            esc_html__('Amazon Product Importer', 'amazon-product-importer'),
            esc_html__('Amazon Importer', 'amazon-product-importer'),
            'import_amazon_products',
            'amazon-product-importer',
            array($this, 'display_plugin_admin_page'),
            'dashicons-cart',
            56
        );

        // Import submenu (same as main page but different title in submenu)
        add_submenu_page(
            'amazon-product-importer',
            esc_html__('Import Products', 'amazon-product-importer'),
            esc_html__('Import Products', 'amazon-product-importer'),
            'import_amazon_products',
            'amazon-product-importer',
            array($this, 'display_plugin_admin_page')
        );

        // Settings submenu
        add_submenu_page(
            'amazon-product-importer',
            esc_html__('Amazon Importer Settings', 'amazon-product-importer'),
            esc_html__('Settings', 'amazon-product-importer'),
            'manage_amazon_product_importer',
            'amazon-product-importer-settings',
            array($this->settings, 'display_settings_page')
        );

        // Import History/Logs submenu
        add_submenu_page(
            'amazon-product-importer',
            esc_html__('Import History', 'amazon-product-importer'),
            esc_html__('Import History', 'amazon-product-importer'),
            'view_amazon_import_logs',
            'amazon-product-importer-history',
            array($this, 'display_import_history_page')
        );

        // Sync Status submenu
        add_submenu_page(
            'amazon-product-importer',
            esc_html__('Sync Status', 'amazon-product-importer'),
            esc_html__('Sync Status', 'amazon-product-importer'),
            'manage_amazon_product_importer',
            'amazon-product-importer-sync',
            array($this, 'display_sync_status_page')
        );
    }

    /**
     * Display the main plugin admin page.
     *
     * @since    1.0.0
     */
    public function display_plugin_admin_page() {
        
        // Check user capabilities
        if (!current_user_can('import_amazon_products')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'amazon-product-importer'));
        }

        // Display the import interface
        $this->import->display_import_page();
    }

    /**
     * Display the import history page.
     *
     * @since    1.0.0
     */
    public function display_import_history_page() {
        
        // Check user capabilities
        if (!current_user_can('view_amazon_import_logs')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'amazon-product-importer'));
        }

        include_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'admin/partials/import-history-display.php';
    }

    /**
     * Display the sync status page.
     *
     * @since    1.0.0
     */
    public function display_sync_status_page() {
        
        // Check user capabilities
        if (!current_user_can('manage_amazon_product_importer')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'amazon-product-importer'));
        }

        include_once AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'admin/partials/sync-status-display.php';
    }

    /**
     * Add meta boxes to product edit pages.
     *
     * @since    1.0.0
     */
    public function add_product_meta_boxes() {
        
        // Amazon product information meta box
        add_meta_box(
            'amazon-product-info',
            esc_html__('Amazon Product Information', 'amazon-product-importer'),
            array($this, 'display_amazon_product_meta_box'),
            'product',
            'side',
            'high'
        );

        // Amazon sync settings meta box
        add_meta_box(
            'amazon-sync-settings',
            esc_html__('Amazon Sync Settings', 'amazon-product-importer'),
            array($this, 'display_amazon_sync_meta_box'),
            'product',
            'side',
            'default'
        );
    }

    /**
     * Display Amazon product information meta box.
     *
     * @since    1.0.0
     * @param    WP_Post    $post    The current post object.
     */
    public function display_amazon_product_meta_box($post) {
        include AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'admin/partials/amazon-product-info-metabox.php';
    }

    /**
     * Display Amazon sync settings meta box.
     *
     * @since    1.0.0
     * @param    WP_Post    $post    The current post object.
     */
    public function display_amazon_sync_meta_box($post) {
        include AMAZON_PRODUCT_IMPORTER_PLUGIN_PATH . 'admin/partials/amazon-sync-settings-metabox.php';
    }

    /**
     * Save meta box data.
     *
     * @since    1.0.0
     * @param    int    $post_id    The ID of the post being saved.
     */
    public function save_product_meta_boxes($post_id) {
        
        // Security checks
        if (!isset($_POST['amazon_product_importer_meta_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['amazon_product_importer_meta_nonce'], 'amazon_product_importer_meta_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save Amazon sync settings
        if (isset($_POST['amazon_sync_enabled'])) {
            update_post_meta($post_id, '_amazon_sync_enabled', sanitize_text_field($_POST['amazon_sync_enabled']));
        }

        if (isset($_POST['amazon_sync_price'])) {
            update_post_meta($post_id, '_amazon_sync_price', sanitize_text_field($_POST['amazon_sync_price']));
        }

        if (isset($_POST['amazon_sync_stock'])) {
            update_post_meta($post_id, '_amazon_sync_stock', sanitize_text_field($_POST['amazon_sync_stock']));
        }

        if (isset($_POST['amazon_sync_images'])) {
            update_post_meta($post_id, '_amazon_sync_images', sanitize_text_field($_POST['amazon_sync_images']));
        }
    }

    /**
     * Add custom columns to the products list table.
     *
     * @since    1.0.0
     * @param    array    $columns    Existing columns.
     * @return   array    Modified columns.
     */
    public function add_product_list_columns($columns) {
        
        // Add Amazon ASIN column after the product title
        $new_columns = array();
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ($key === 'name') {
                $new_columns['amazon_asin'] = esc_html__('Amazon ASIN', 'amazon-product-importer');
                $new_columns['amazon_sync_status'] = esc_html__('Sync Status', 'amazon-product-importer');
            }
        }

        return $new_columns;
    }

    /**
     * Display content for custom product list columns.
     *
     * @since    1.0.0
     * @param    string    $column    The name of the column to display.
     * @param    int       $post_id   The current post ID.
     */
    public function display_product_list_columns($column, $post_id) {
        
        switch ($column) {
            case 'amazon_asin':
                $asin = get_post_meta($post_id, '_amazon_asin', true);
                if ($asin) {
                    printf(
                        '<a href="%s" target="_blank" title="%s">%s</a>',
                        esc_url('https://amazon.com/dp/' . $asin),
                        esc_attr__('View on Amazon', 'amazon-product-importer'),
                        esc_html($asin)
                    );
                } else {
                    echo '<span class="na">—</span>';
                }
                break;

            case 'amazon_sync_status':
                $sync_enabled = get_post_meta($post_id, '_amazon_sync_enabled', true);
                $last_sync = get_post_meta($post_id, '_amazon_last_sync', true);
                
                if ($sync_enabled === 'yes') {
                    $status_class = 'enabled';
                    $status_text = esc_html__('Enabled', 'amazon-product-importer');
                    if ($last_sync) {
                        $last_sync_formatted = human_time_diff(strtotime($last_sync), current_time('timestamp'));
                        $status_text .= sprintf(' (%s ago)', $last_sync_formatted);
                    }
                } else {
                    $status_class = 'disabled';
                    $status_text = esc_html__('Disabled', 'amazon-product-importer');
                }
                
                printf(
                    '<span class="amazon-sync-status %s">%s</span>',
                    esc_attr($status_class),
                    $status_text
                );
                break;
        }
    }

    /**
     * Make custom columns sortable.
     *
     * @since    1.0.0
     * @param    array    $columns    Existing sortable columns.
     * @return   array    Modified sortable columns.
     */
    public function add_sortable_product_columns($columns) {
        $columns['amazon_asin'] = 'amazon_asin';
        $columns['amazon_sync_status'] = 'amazon_sync_status';
        return $columns;
    }

    /**
     * Handle sorting for custom columns.
     *
     * @since    1.0.0
     * @param    WP_Query    $query    The WP_Query instance.
     */
    public function handle_product_column_sorting($query) {
        
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $orderby = $query->get('orderby');

        switch ($orderby) {
            case 'amazon_asin':
                $query->set('meta_key', '_amazon_asin');
                $query->set('orderby', 'meta_value');
                break;

            case 'amazon_sync_status':
                $query->set('meta_key', '_amazon_sync_enabled');
                $query->set('orderby', 'meta_value');
                break;
        }
    }

    /**
     * Add bulk actions to product list table.
     *
     * @since    1.0.0
     * @param    array    $bulk_actions    Existing bulk actions.
     * @return   array    Modified bulk actions.
     */
    public function add_product_bulk_actions($bulk_actions) {
        $bulk_actions['amazon_enable_sync'] = esc_html__('Enable Amazon Sync', 'amazon-product-importer');
        $bulk_actions['amazon_disable_sync'] = esc_html__('Disable Amazon Sync', 'amazon-product-importer');
        $bulk_actions['amazon_force_sync'] = esc_html__('Force Amazon Sync', 'amazon-product-importer');
        
        return $bulk_actions;
    }

    /**
     * Handle bulk actions for products.
     *
     * @since    1.0.0
     * @param    string    $redirect_to    The redirect URL.
     * @param    string    $doaction       The action being taken.
     * @param    array     $post_ids       The items to take the action on.
     * @return   string    The redirect URL.
     */
    public function handle_product_bulk_actions($redirect_to, $doaction, $post_ids) {
        
        if (!in_array($doaction, array('amazon_enable_sync', 'amazon_disable_sync', 'amazon_force_sync'))) {
            return $redirect_to;
        }

        $processed = 0;

        foreach ($post_ids as $post_id) {
            
            // Verify this is a product and has Amazon data
            if (get_post_type($post_id) !== 'product') {
                continue;
            }

            $asin = get_post_meta($post_id, '_amazon_asin', true);
            if (empty($asin)) {
                continue;
            }

            switch ($doaction) {
                case 'amazon_enable_sync':
                    update_post_meta($post_id, '_amazon_sync_enabled', 'yes');
                    $processed++;
                    break;

                case 'amazon_disable_sync':
                    update_post_meta($post_id, '_amazon_sync_enabled', 'no');
                    $processed++;
                    break;

                case 'amazon_force_sync':
                    // Queue for immediate sync
                    update_post_meta($post_id, '_amazon_force_sync', 'yes');
                    wp_schedule_single_event(time(), 'amazon_product_importer_sync_single_product', array($post_id));
                    $processed++;
                    break;
            }
        }

        $redirect_to = add_query_arg('amazon_bulk_action_processed', $processed, $redirect_to);
        return $redirect_to;
    }

    /**
     * Display bulk action admin notices.
     *
     * @since    1.0.0
     */
    public function display_bulk_action_notices() {
        
        if (!isset($_REQUEST['amazon_bulk_action_processed'])) {
            return;
        }

        $processed = intval($_REQUEST['amazon_bulk_action_processed']);
        
        if ($processed > 0) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                sprintf(
                    _n(
                        'Amazon sync updated for %d product.',
                        'Amazon sync updated for %d products.',
                        $processed,
                        'amazon-product-importer'
                    ),
                    $processed
                )
            );
        }
    }

    /**
     * Check if current screen is a plugin admin page.
     *
     * @since    1.0.0
     * @access   private
     * @param    WP_Screen    $screen    Current screen object.
     * @return   bool         True if plugin admin page, false otherwise.
     */
    private function is_plugin_admin_page($screen) {
        if (!$screen) {
            return false;
        }

        $plugin_pages = array(
            'toplevel_page_amazon-product-importer',
            'amazon-importer_page_amazon-product-importer-settings',
            'amazon-importer_page_amazon-product-importer-history',
            'amazon-importer_page_amazon-product-importer-sync',
        );

        return in_array($screen->id, $plugin_pages);
    }

    /**
     * Check if current screen is the import page.
     *
     * @since    1.0.0
     * @access   private
     * @param    WP_Screen    $screen    Current screen object.
     * @return   bool         True if import page, false otherwise.
     */
    private function is_import_page($screen) {
        return $screen && $screen->id === 'toplevel_page_amazon-product-importer';
    }

    /**
     * Check if current screen is the settings page.
     *
     * @since    1.0.0
     * @access   private
     * @param    WP_Screen    $screen    Current screen object.
     * @return   bool         True if settings page, false otherwise.
     */
    private function is_settings_page($screen) {
        return $screen && $screen->id === 'amazon-importer_page_amazon-product-importer-settings';
    }

    /**
     * Get the settings instance.
     *
     * @since    1.0.0
     * @return   Amazon_Product_Importer_Admin_Settings
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Get the import instance.
     *
     * @since    1.0.0
     * @return   Amazon_Product_Importer_Admin_Import
     */
    public function get_import() {
        return $this->import;
    }
}