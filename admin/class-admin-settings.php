<?php

/**
 * Admin settings functionality
 */
class Amazon_Product_Importer_Admin_Settings {

    private $plugin_name;
    private $version;
    private $validator;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->validator = new Amazon_Product_Importer_Validator();
    }

    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting(
            'amazon_importer_settings',
            'amazon_importer_api_access_key_id',
            array($this, 'sanitize_api_access_key')
        );

        register_setting(
            'amazon_importer_settings',
            'amazon_importer_api_secret_access_key',
            array($this, 'sanitize_api_secret_key')
        );

        register_setting(
            'amazon_importer_settings',
            'amazon_importer_api_associate_tag',
            array($this, 'sanitize_associate_tag')
        );

        register_setting(
            'amazon_importer_settings',
            'amazon_importer_api_region',
            array($this, 'sanitize_region')
        );

        register_setting(
            'amazon_importer_settings',
            'amazon_importer_ams_product_thumbnail_size',
            array($this, 'sanitize_thumbnail_size')
        );

        register_setting(
            'amazon_importer_settings',
            'amazon_importer_product_name_cron',
            array($this, 'sanitize_checkbox')
        );

        register_setting(
            'amazon_importer_settings',
            'amazon_importer_product_category_cron',
            array($this, 'sanitize_checkbox')
        );

        register_setting(
            'amazon_importer_settings',
            'amazon_importer_product_sku_cron',
            array($this, 'sanitize_checkbox')
        );

        register_setting(
            'amazon_importer_settings',
            'amazon_importer_price_sync_enabled',
            array($this, 'sanitize_checkbox')
        );

        register_setting(
            'amazon_importer_settings',
            'amazon_importer_price_sync_frequency',
            array($this, 'sanitize_sync_frequency')
        );

        register_setting(
            'amazon_importer_settings',
            'amazon_importer_category_min_depth',
            array($this, 'sanitize_depth')
        );

        register_setting(
            'amazon_importer_settings',
            'amazon_importer_category_max_depth',
            array($this, 'sanitize_depth')
        );

        register_setting(
            'amazon_importer_settings',
            'amazon_importer_debug_mode',
            array($this, 'sanitize_checkbox')
        );

        // Add AJAX handlers
        add_action('wp_ajax_test_amazon_api_connection', array($this, 'ajax_test_api_connection'));
        add_action('wp_ajax_clear_amazon_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_reset_amazon_settings', array($this, 'ajax_reset_settings'));
    }

    /**
     * Sanitize API access key
     */
    public function sanitize_api_access_key($value) {
        $value = sanitize_text_field($value);
        
        if (!empty($value) && (strlen($value) < 16 || strlen($value) > 32)) {
            add_settings_error(
                'amazon_importer_api_access_key_id',
                'invalid_access_key',
                __('Access Key ID doit contenir entre 16 et 32 caractères', 'amazon-product-importer')
            );
        }

        return $value;
    }

    /**
     * Sanitize API secret key
     */
    public function sanitize_api_secret_key($value) {
        $value = sanitize_text_field($value);
        
        if (!empty($value) && strlen($value) < 32) {
            add_settings_error(
                'amazon_importer_api_secret_access_key',
                'invalid_secret_key',
                __('Secret Access Key doit contenir au moins 32 caractères', 'amazon-product-importer')
            );
        }

        return $value;
    }

    /**
     * Sanitize associate tag
     */
    public function sanitize_associate_tag($value) {
        $value = sanitize_text_field($value);
        
        if (!empty($value) && !preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
            add_settings_error(
                'amazon_importer_api_associate_tag',
                'invalid_associate_tag',
                __('Associate Tag contient des caractères invalides', 'amazon-product-importer')
            );
        }

        return $value;
    }

    /**
     * Sanitize region
     */
    public function sanitize_region($value) {
        $allowed_regions = array('com', 'co.uk', 'de', 'fr', 'it', 'es', 'ca', 'co.jp', 'in', 'com.br', 'com.mx', 'com.au');
        
        if (!in_array($value, $allowed_regions)) {
            return 'com';
        }

        return $value;
    }

    /**
     * Sanitize thumbnail size
     */
    public function sanitize_thumbnail_size($value) {
        $allowed_sizes = array('small', 'medium', 'large');
        
        if (!in_array($value, $allowed_sizes)) {
            return 'large';
        }

        return $value;
    }

    /**
     * Sanitize checkbox
     */
    public function sanitize_checkbox($value) {
        return $value ? 1 : 0;
    }

    /**
     * Sanitize sync frequency
     */
    public function sanitize_sync_frequency($value) {
        $allowed_frequencies = array('hourly', 'daily', 'weekly');
        
        if (!in_array($value, $allowed_frequencies)) {
            return 'hourly';
        }

        return $value;
    }

    /**
     * Sanitize depth
     */
    public function sanitize_depth($value) {
        $depth = intval($value);
        
        if ($depth < 0) {
            return 0;
        }
        
        if ($depth > 10) {
            return 10;
        }

        return $depth;
    }

    /**
     * Test API connection via AJAX
     */
    public function ajax_test_api_connection() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Permissions insuffisantes', 'amazon-product-importer'));
        }

        $access_key = sanitize_text_field($_POST['access_key_id']);
        $secret_key = sanitize_text_field($_POST['secret_access_key']);
        $associate_tag = sanitize_text_field($_POST['associate_tag']);
        $region = sanitize_text_field($_POST['region']);

        // Validate credentials
        $validation = $this->validator->validate_api_credentials($access_key, $secret_key, $associate_tag);
        
        if (is_wp_error($validation)) {
            wp_send_json_error(array(
                'message' => $validation->get_error_message()
            ));
        }

        // Temporarily update options for testing
        $original_values = array(
            'access_key' => get_option('amazon_importer_api_access_key_id'),
            'secret_key' => get_option('amazon_importer_api_secret_access_key'),
            'associate_tag' => get_option('amazon_importer_api_associate_tag'),
            'region' => get_option('amazon_importer_api_region')
        );

        update_option('amazon_importer_api_access_key_id', $access_key);
        update_option('amazon_importer_api_secret_access_key', $secret_key);
        update_option('amazon_importer_api_associate_tag', $associate_tag);
        update_option('amazon_importer_api_region', $region);

        try {
            // Test API connection
            $api = new Amazon_Product_Importer_Amazon_API();
            $test_result = $api->search_products('test', null, 1, 1);

            // Restore original values
            foreach ($original_values as $key => $value) {
                update_option('amazon_importer_api_' . $key, $value);
            }

            if ($test_result['success']) {
                wp_send_json_success(array(
                    'message' => __('Connexion API réussie!', 'amazon-product-importer')
                ));
            } else {
                wp_send_json_error(array(
                    'message' => __('Échec de la connexion API: ', 'amazon-product-importer') . $test_result['error']
                ));
            }

        } catch (Exception $e) {
            // Restore original values
            foreach ($original_values as $key => $value) {
                update_option('amazon_importer_api_' . $key, $value);
            }

            wp_send_json_error(array(
                'message' => __('Erreur de connexion: ', 'amazon-product-importer') . $e->getMessage()
            ));
        }
    }

    /**
     * Clear cache via AJAX
     */
    public function ajax_clear_cache() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Permissions insuffisantes', 'amazon-product-importer'));
        }

        $cache = new Amazon_Product_Importer_Cache();
        $cache->clear_all();

        wp_send_json_success(array(
            'message' => __('Cache vidé avec succès', 'amazon-product-importer')
        ));
    }

    /**
     * Reset settings via AJAX
     */
    public function ajax_reset_settings() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Permissions insuffisantes', 'amazon-product-importer'));
        }

        // Reset all options to defaults
        $default_options = array(
            'amazon_importer_api_access_key_id' => '',
            'amazon_importer_api_secret_access_key' => '',
            'amazon_importer_api_associate_tag' => '',
            'amazon_importer_api_region' => 'com',
            'amazon_importer_ams_product_thumbnail_size' => 'large',
            'amazon_importer_product_name_cron' => false,
            'amazon_importer_product_category_cron' => false,
            'amazon_importer_product_sku_cron' => false,
            'amazon_importer_price_sync_enabled' => true,
            'amazon_importer_price_sync_frequency' => 'hourly',
            'amazon_importer_category_min_depth' => 1,
            'amazon_importer_category_max_depth' => 3,
            'amazon_importer_debug_mode' => false
        );

        foreach ($default_options as $option => $value) {
            update_option($option, $value);
        }

        wp_send_json_success(array(
            'message' => __('Paramètres réinitialisés avec succès', 'amazon-product-importer')
        ));
    }
}