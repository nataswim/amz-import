<?php

/**
 * Handles image import from Amazon
 */
class Amazon_Product_Importer_Image_Handler {

    private $logger;
    private $helper;

    public function __construct() {
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->helper = new Amazon_Product_Importer_Helper();
    }

    /**
     * Import product images
     */
    public function import_product_images($product_id, $images_data) {
        try {
            $imported_images = array();
            $thumbnail_size = get_option('amazon_importer_ams_product_thumbnail_size', 'large');

            // Import primary image
            if (isset($images_data['Primary'][$this->get_size_key($thumbnail_size)])) {
                $primary_image_url = $images_data['Primary'][$this->get_size_key($thumbnail_size)]['URL'];
                $primary_image_id = $this->import_single_image($primary_image_url, $product_id, true);
                
                if ($primary_image_id) {
                    $imported_images[] = $primary_image_id;
                    set_post_thumbnail($product_id, $primary_image_id);
                }
            }

            // Import variant images (gallery)
            if (isset($images_data['Variants']) && is_array($images_data['Variants'])) {
                $gallery_ids = array();
                
                foreach ($images_data['Variants'] as $variant) {
                    if (isset($variant[$this->get_size_key($thumbnail_size)]['URL'])) {
                        $image_url = $variant[$this->get_size_key($thumbnail_size)]['URL'];
                        $image_id = $this->import_single_image($image_url, $product_id, false);
                        
                        if ($image_id) {
                            $gallery_ids[] = $image_id;
                            $imported_images[] = $image_id;
                        }
                    }
                }

                // Set product gallery
                if (!empty($gallery_ids)) {
                    update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
                }
            }

            $this->logger->log('info', 'Product images imported', array(
                'product_id' => $product_id,
                'images_count' => count($imported_images)
            ));

            return $imported_images;

        } catch (Exception $e) {
            $this->logger->log('error', 'Image import failed', array(
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ));

            return array();
        }
    }

    /**
     * Import a single image
     */
    private function import_single_image($image_url, $product_id, $is_primary = false) {
        try {
            // Check if image already exists
            $existing_image_id = $this->get_existing_image_by_url($image_url);
            if ($existing_image_id) {
                return $existing_image_id;
            }

            // Download image
            $image_data = $this->download_image($image_url);
            if (!$image_data) {
                throw new Exception('Failed to download image');
            }

            // Generate filename
            $filename = $this->generate_image_filename($image_url, $product_id, $is_primary);

            // Upload to WordPress media library
            $upload = wp_upload_bits($filename, null, $image_data);
            
            if ($upload['error']) {
                throw new Exception('Upload failed: ' . $upload['error']);
            }

            // Create attachment
            $attachment_id = $this->create_attachment($upload, $product_id, $image_url);

            return $attachment_id;

        } catch (Exception $e) {
            $this->logger->log('error', 'Single image import failed', array(
                'image_url' => $image_url,
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ));

            return false;
        }
    }

    /**
     * Download image from URL
     */
    private function download_image($image_url) {
        $response = wp_remote_get($image_url, array(
            'timeout' => 30,
            'user-agent' => 'Amazon Product Importer WordPress Plugin'
        ));

        if (is_wp_error($response)) {
            throw new Exception('Download failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            throw new Exception('Download failed: HTTP ' . $response_code);
        }

        $image_data = wp_remote_retrieve_body($response);
        
        if (empty($image_data)) {
            throw new Exception('Empty image data');
        }

        return $image_data;
    }

    /**
     * Generate image filename
     */
    private function generate_image_filename($image_url, $product_id, $is_primary = false) {
        $path_info = pathinfo(parse_url($image_url, PHP_URL_PATH));
        $extension = isset($path_info['extension']) ? $path_info['extension'] : 'jpg';
        
        $prefix = $is_primary ? 'primary' : 'gallery';
        $timestamp = time();
        
        return "amazon-product-{$product_id}-{$prefix}-{$timestamp}.{$extension}";
    }

    /**
     * Create WordPress attachment
     */
    private function create_attachment($upload, $product_id, $source_url) {
        $file_path = $upload['file'];
        $file_name = basename($file_path);
        $file_type = wp_check_filetype($file_name, null);
        
        $attachment = array(
            'post_mime_type' => $file_type['type'],
            'post_title' => sanitize_file_name($file_name),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attachment_id = wp_insert_attachment($attachment, $file_path, $product_id);

        if (is_wp_error($attachment_id)) {
            throw new Exception('Attachment creation failed');
        }

        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        // Store source URL for future reference
        update_post_meta($attachment_id, '_amazon_source_url', $source_url);
        update_post_meta($attachment_id, '_amazon_import_date', current_time('mysql'));

        return $attachment_id;
    }

    /**
     * Get existing image by URL
     */
    private function get_existing_image_by_url($image_url) {
        global $wpdb;

        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_amazon_source_url' AND meta_value = %s 
             LIMIT 1",
            $image_url
        ));

        return $attachment_id;
    }

    /**
     * Get size key based on setting
     */
    private function get_size_key($size) {
        switch ($size) {
            case 'small':
                return 'Small';
            case 'medium':
                return 'Medium';
            case 'large':
            default:
                return 'Large';
        }
    }

    /**
     * Clean up orphaned images
     */
    public function cleanup_orphaned_images() {
        global $wpdb;

        // Find Amazon images not attached to any product
        $orphaned_images = $wpdb->get_results(
            "SELECT p.ID, p.post_parent 
             FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'attachment' 
             AND pm.meta_key = '_amazon_source_url' 
             AND (p.post_parent = 0 OR p.post_parent NOT IN (
                 SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status != 'trash'
             ))"
        );

        $deleted_count = 0;

        foreach ($orphaned_images as $image) {
            if (wp_delete_attachment($image->ID, true)) {
                $deleted_count++;
            }
        }

        $this->logger->log('info', 'Orphaned images cleanup completed', array(
            'deleted_count' => $deleted_count
        ));

        return $deleted_count;
    }

    /**
     * Update product images
     */
    public function update_product_images($product_id, $images_data, $force_update = false) {
        if (!$force_update) {
            // Check if images were recently updated
            $last_image_update = get_post_meta($product_id, '_amazon_images_last_update', true);
            
            if ($last_image_update && (time() - strtotime($last_image_update)) < 86400) {
                return false; // Skip if updated within last 24 hours
            }
        }

        // Import new images
        $imported_images = $this->import_product_images($product_id, $images_data);

        // Update timestamp
        update_post_meta($product_id, '_amazon_images_last_update', current_time('mysql'));

        return $imported_images;
    }
}