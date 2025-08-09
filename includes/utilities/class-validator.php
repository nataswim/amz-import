<?php

/**
 * Data validation utility
 */
class Amazon_Product_Importer_Validator {

    /**
     * Validate ASIN format
     */
    public function validate_asin($asin) {
        // ASIN should be 10 characters, alphanumeric
        if (!preg_match('/^[A-Z0-9]{10}$/', $asin)) {
            return new WP_Error('invalid_asin', __('ASIN invalide. Doit contenir 10 caractères alphanumériques.', 'amazon-product-importer'));
        }

        return true;
    }

    /**
     * Validate API credentials
     */
    public function validate_api_credentials($access_key, $secret_key, $associate_tag) {
        $errors = array();

        if (empty($access_key)) {
            $errors[] = __('Access Key ID requis', 'amazon-product-importer');
        } elseif (strlen($access_key) < 16 || strlen($access_key) > 32) {
            $errors[] = __('Access Key ID doit contenir entre 16 et 32 caractères', 'amazon-product-importer');
        }

        if (empty($secret_key)) {
            $errors[] = __('Secret Access Key requis', 'amazon-product-importer');
        } elseif (strlen($secret_key) < 32) {
            $errors[] = __('Secret Access Key doit contenir au moins 32 caractères', 'amazon-product-importer');
        }

        if (empty($associate_tag)) {
            $errors[] = __('Associate Tag requis', 'amazon-product-importer');
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $associate_tag)) {
            $errors[] = __('Associate Tag contient des caractères invalides', 'amazon-product-importer');
        }

        if (!empty($errors)) {
            return new WP_Error('invalid_credentials', implode('. ', $errors));
        }

        return true;
    }

    /**
     * Validate import settings
     */
    public function validate_import_settings($settings) {
        $errors = array();

        if (isset($settings['category_min_depth'])) {
            $min_depth = intval($settings['category_min_depth']);
            if ($min_depth < 0 || $min_depth > 10) {
                $errors[] = __('Profondeur minimale des catégories doit être entre 0 et 10', 'amazon-product-importer');
            }
        }

        if (isset($settings['category_max_depth'])) {
            $max_depth = intval($settings['category_max_depth']);
            if ($max_depth < 1 || $max_depth > 10) {
                $errors[] = __('Profondeur maximale des catégories doit être entre 1 et 10', 'amazon-product-importer');
            }
        }

        if (isset($settings['category_min_depth']) && isset($settings['category_max_depth'])) {
            if ($settings['category_min_depth'] >= $settings['category_max_depth']) {
                $errors[] = __('Profondeur minimale doit être inférieure à la profondeur maximale', 'amazon-product-importer');
            }
        }

        if (!empty($errors)) {
            return new WP_Error('invalid_settings', implode('. ', $errors));
        }

        return true;
    }

    /**
     * Validate product data
     */
    public function validate_product_data($product_data) {
        $errors = array();

        if (empty($product_data['title'])) {
            $errors[] = __('Titre du produit requis', 'amazon-product-importer');
        }

        if (empty($product_data['asin'])) {
            $errors[] = __('ASIN requis', 'amazon-product-importer');
        } else {
            $asin_validation = $this->validate_asin($product_data['asin']);
            if (is_wp_error($asin_validation)) {
                $errors[] = $asin_validation->get_error_message();
            }
        }

        if (isset($product_data['regular_price'])) {
            $price = floatval($product_data['regular_price']);
            if ($price < 0) {
                $errors[] = __('Prix ne peut pas être négatif', 'amazon-product-importer');
            }
        }

        if (!empty($errors)) {
            return new WP_Error('invalid_product_data', implode('. ', $errors));
        }

        return true;
    }

    /**
     * Sanitize import data
     */
    public function sanitize_import_data($data) {
        $sanitized = array();

        foreach ($data as $key => $value) {
            switch ($key) {
                case 'title':
                case 'description':
                case 'short_description':
                    $sanitized[$key] = wp_kses_post($value);
                    break;
                
                case 'asin':
                case 'sku':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                
                case 'regular_price':
                case 'sale_price':
                    $sanitized[$key] = floatval($value);
                    break;
                
                case 'weight':
                    $sanitized[$key] = floatval($value);
                    break;
                
                case 'attributes':
                    if (is_array($value)) {
                        $sanitized[$key] = array_map('sanitize_text_field', $value);
                    }
                    break;
                
                default:
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * Validate URL
     */
    public function validate_url($url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', __('URL invalide', 'amazon-product-importer'));
        }

        return true;
    }

    /**
     * Validate email
     */
    public function validate_email($email) {
        if (!is_email($email)) {
            return new WP_Error('invalid_email', __('Adresse email invalide', 'amazon-product-importer'));
        }

        return true;
    }
}