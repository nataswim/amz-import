<?php

/**
 * Admin import functionality
 */
class Amazon_Product_Importer_Admin_Import {

    private $plugin_name;
    private $version;
    private $importer;
    private $validator;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->importer = new Amazon_Product_Importer_Product_Importer();
        $this->validator = new Amazon_Product_Importer_Validator();
    }

    /**
     * Handle AJAX product search
     */
    public function ajax_search_products() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permissions insuffisantes', 'amazon-product-importer'));
        }

        $search_type = sanitize_text_field($_POST['search_type']);
        $keywords = sanitize_text_field($_POST['keywords']);
        $category = sanitize_text_field($_POST['category']);
        $page = intval($_POST['page']) ?: 1;
        $items_per_page = intval($_POST['items_per_page']) ?: 20;

        if (empty($keywords)) {
            wp_send_json_error(array(
                'message' => __('Mots-clés requis', 'amazon-product-importer')
            ));
        }

        try {
            if ($search_type === 'asin') {
                $result = $this->search_by_asins($keywords);
            } else {
                $result = $this->importer->search_products($keywords, $category, $page, $items_per_page);
            }

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error(array(
                    'message' => $result['error']
                ));
            }

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Search by ASINs
     */
    private function search_by_asins($asins_string) {
        $helper = new Amazon_Product_Importer_Helper();
        $asins = $helper->validate_asins($asins_string);

        if (empty($asins)) {
            return array(
                'success' => false,
                'error' => __('Aucun ASIN valide trouvé', 'amazon-product-importer')
            );
        }

        $api = new Amazon_Product_Importer_Amazon_API();
        $items = array();

        foreach ($asins as $asin) {
            $product_result = $api->get_product($asin);
            
            if ($product_result['success'] && $product_result['product']) {
                $product = $product_result['product'];
                
                // Convert to search result format
                $item = array(
                    'asin' => $product['ASIN'],
                    'title' => $product['ItemInfo']['Title']['DisplayValue'] ?? 'Produit sans titre',
                    'image' => $this->get_product_image($product),
                    'price' => $this->get_product_price($product),
                    'is_imported' => $this->is_product_imported($asin)
                );
                
                $items[] = $item;
            }
        }

        return array(
            'success' => true,
            'items' => $items,
            'pagination' => array(
                'current_page' => 1,
                'total_pages' => 1,
                'total_items' => count($items),
                'has_previous' => false,
                'has_next' => false
            )
        );
    }

    /**
     * Get product image from Amazon data
     */
    private function get_product_image($product) {
        if (isset($product['Images']['Primary']['Medium']['URL'])) {
            return $product['Images']['Primary']['Medium']['URL'];
        }
        
        if (isset($product['Images']['Primary']['Small']['URL'])) {
            return $product['Images']['Primary']['Small']['URL'];
        }

        return null;
    }

    /**
     * Get product price from Amazon data
     */
    private function get_product_price($product) {
        if (isset($product['Offers']['Listings'][0]['Price']['Amount'])) {
            $amount = $product['Offers']['Listings'][0]['Price']['Amount'];
            $helper = new Amazon_Product_Importer_Helper();
            return $helper->format_price($amount);
        }

        return __('Prix non disponible', 'amazon-product-importer');
    }

    /**
     * Check if product is already imported
     */
    private function is_product_imported($asin) {
        global $wpdb;

        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_amazon_asin' AND meta_value = %s 
             LIMIT 1",
            $asin
        ));

        return !empty($product_id);
    }

    /**
     * Handle AJAX product import
     */
    public function ajax_import_product() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permissions insuffisantes', 'amazon-product-importer'));
        }

        $asin = sanitize_text_field($_POST['asin']);

        if (empty($asin)) {
            wp_send_json_error(array(
                'message' => __('ASIN requis', 'amazon-product-importer')
            ));
        }

        // Validate ASIN
        $validation = $this->validator->validate_asin($asin);
        if (is_wp_error($validation)) {
            wp_send_json_error(array(
                'message' => $validation->get_error_message()
            ));
        }

        try {
            $result = $this->importer->import_product($asin);

            if ($result['success']) {
                // Update import statistics
                $this->update_import_stats();

                wp_send_json_success(array(
                    'message' => $result['message'],
                    'product_id' => $result['product_id']
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $result['error']
                ));
            }

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Update import statistics
     */
    private function update_import_stats() {
        $stats = get_option('amazon_importer_stats', array(
            'today' => 0,
            'total' => 0,
            'last_reset' => date('Y-m-d')
        ));

        // Reset daily counter if it's a new day
        if ($stats['last_reset'] !== date('Y-m-d')) {
            $stats['today'] = 0;
            $stats['last_reset'] = date('Y-m-d');
        }

        $stats['today']++;
        $stats['total']++;

        update_option('amazon_importer_stats', $stats);
    }

    /**
     * Handle bulk import
     */
    public function ajax_bulk_import() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permissions insuffisantes', 'amazon-product-importer'));
        }

        $asins = array_map('sanitize_text_field', $_POST['asins']);

        if (empty($asins)) {
            wp_send_json_error(array(
                'message' => __('Aucun ASIN sélectionné', 'amazon-product-importer')
            ));
        }

        // Validate ASINs
        $valid_asins = array();
        foreach ($asins as $asin) {
            $validation = $this->validator->validate_asin($asin);
            if (!is_wp_error($validation)) {
                $valid_asins[] = $asin;
            }
        }

        if (empty($valid_asins)) {
            wp_send_json_error(array(
                'message' => __('Aucun ASIN valide trouvé', 'amazon-product-importer')
            ));
        }

        try {
            $result = $this->importer->import_multiple_products($valid_asins);

            wp_send_json_success(array(
                'message' => sprintf(
                    __('%d produits importés avec succès, %d erreurs', 'amazon-product-importer'),
                    $result['summary']['success'],
                    $result['summary']['errors']
                ),
                'results' => $result['results'],
                'summary' => $result['summary']
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Get import queue status
     */
    public function ajax_get_import_status() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permissions insuffisantes', 'amazon-product-importer'));
        }

        $database = new Amazon_Product_Importer_Database();
        $queue_status = $database->get_sync_queue_status();

        wp_send_json_success($queue_status);
    }

    /**
     * Clear import queue
     */
    public function ajax_clear_import_queue() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permissions insuffisantes', 'amazon-product-importer'));
        }

        $database = new Amazon_Product_Importer_Database();
        $cleared_count = $database->clear_sync_queue();

        wp_send_json_success(array(
            'message' => sprintf(
                __('%d éléments supprimés de la file d\'attente', 'amazon-product-importer'),
                $cleared_count
            )
        ));
    }
}