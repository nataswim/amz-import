<?php

/**
 * The image handling functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/import
 */

/**
 * The image handling functionality of the plugin.
 *
 * Handles downloading, processing, and managing product images from Amazon.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/import
 * @author     Your Name <email@example.com>
 */
class Amazon_Product_Importer_Image_Handler {

    /**
     * The logger instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Logger    $logger    The logger instance.
     */
    private $logger;

    /**
     * Image processing settings.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $settings    Image processing settings.
     */
    private $settings;

    /**
     * Allowed image file types.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $allowed_types    Allowed image file types.
     */
    private $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'webp');

    /**
     * Image download cache.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $download_cache    Cache for downloaded images.
     */
    private $download_cache = array();

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->load_settings();
        
        // Add WordPress hooks
        add_filter('wp_handle_sideload_prefilter', array($this, 'handle_sideload_prefilter'));
    }

    /**
     * Load image processing settings.
     *
     * @since    1.0.0
     */
    private function load_settings() {
        $this->settings = array(
            'thumbnail_size' => get_option('ams_product_thumbnail_size', 'large'),
            'max_images' => get_option('ams_max_product_images', 10),
            'max_file_size' => get_option('ams_max_image_file_size', 5) * 1024 * 1024, // MB to bytes
            'timeout' => get_option('ams_image_download_timeout', 30),
            'replace_existing' => get_option('ams_replace_existing_images', false),
            'preserve_original_names' => get_option('ams_preserve_original_names', false),
            'add_watermark' => get_option('ams_add_watermark', false),
            'watermark_text' => get_option('ams_watermark_text', 'Imported from Amazon'),
            'compress_images' => get_option('ams_compress_images', true),
            'quality' => get_option('ams_image_quality', 85),
            'create_thumbnails' => get_option('ams_create_thumbnails', true),
            'alt_text_template' => get_option('ams_alt_text_template', '%product_title% - Image %image_number%')
        );
    }

    /**
     * Process product images from Amazon API data.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $item_data     Amazon API item data.
     * @return   bool     True on success, false on failure.
     */
    public function process_product_images($product_id, $item_data) {
        if (!isset($item_data['Images'])) {
            $this->logger->log("No image data found for product {$product_id}", 'warning');
            return false;
        }

        try {
            $images_data = $item_data['Images'];
            $processed_images = array();

            // Process primary image
            if (isset($images_data['Primary'])) {
                $primary_image = $this->process_image_set($images_data['Primary'], $product_id, 'primary');
                if ($primary_image) {
                    $processed_images['primary'] = $primary_image;
                }
            }

            // Process variant images (gallery)
            $gallery_images = array();
            if (isset($images_data['Variants']) && is_array($images_data['Variants'])) {
                foreach ($images_data['Variants'] as $index => $variant) {
                    $variant_image = $this->process_image_set($variant, $product_id, 'variant_' . $index);
                    if ($variant_image) {
                        $gallery_images[] = $variant_image;
                    }

                    // Limit number of images
                    if (count($gallery_images) >= $this->settings['max_images'] - 1) {
                        break;
                    }
                }
            }

            if (!empty($gallery_images)) {
                $processed_images['gallery'] = $gallery_images;
            }

            // Assign images to product
            if (!empty($processed_images)) {
                $this->assign_images_to_product($product_id, $processed_images);
                
                // Store original image data for future reference
                update_post_meta($product_id, '_amazon_original_images', $images_data);
                update_post_meta($product_id, '_amazon_images_imported', current_time('mysql'));

                $this->logger->log(sprintf(
                    'Images processed for product %d. Primary: %s, Gallery: %d images',
                    $product_id,
                    isset($processed_images['primary']) ? 'Yes' : 'No',
                    isset($processed_images['gallery']) ? count($processed_images['gallery']) : 0
                ), 'info');

                return true;
            }

            return false;

        } catch (Exception $e) {
            $this->logger->log("Error processing images for product {$product_id}: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Update product images.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $item_data     Amazon API item data.
     * @return   bool     True if images were updated.
     */
    public function update_product_images($product_id, $item_data) {
        if (!$this->settings['replace_existing']) {
            return false;
        }

        // Get current image URLs for comparison
        $current_images = $this->get_current_product_images($product_id);
        $new_images = $this->extract_image_urls($item_data);

        // Check if images have changed
        if ($this->have_images_changed($current_images, $new_images)) {
            // Remove old Amazon images
            $this->remove_product_images($product_id);
            
            // Import new images
            return $this->process_product_images($product_id, $item_data);
        }

        return false;
    }

    /**
     * Process a single image set (with different sizes).
     *
     * @since    1.0.0
     * @param    array     $image_set    Image set data from Amazon.
     * @param    int       $product_id   Product ID.
     * @param    string    $image_type   Type of image (primary, variant_x).
     * @return   int|null  Attachment ID or null on failure.
     */
    private function process_image_set($image_set, $product_id, $image_type) {
        // Get the best image URL based on settings
        $image_url = $this->get_best_image_url($image_set);
        
        if (empty($image_url)) {
            $this->logger->log("No suitable image URL found for {$image_type} image", 'warning');
            return null;
        }

        // Check if we've already downloaded this image
        $cache_key = md5($image_url);
        if (isset($this->download_cache[$cache_key])) {
            return $this->download_cache[$cache_key];
        }

        // Download and process the image
        $attachment_id = $this->download_and_import_image($image_url, $product_id, $image_type);

        if ($attachment_id) {
            // Cache the result
            $this->download_cache[$cache_key] = $attachment_id;
            
            // Store metadata
            update_post_meta($attachment_id, '_amazon_image_type', $image_type);
            update_post_meta($attachment_id, '_amazon_original_url', $image_url);
            update_post_meta($attachment_id, '_amazon_imported_date', current_time('mysql'));
        }

        return $attachment_id;
    }

    /**
     * Get the best image URL from an image set.
     *
     * @since    1.0.0
     * @param    array    $image_set    Image set data.
     * @return   string   Best image URL.
     */
    private function get_best_image_url($image_set) {
        $size_preference = $this->get_size_preference();
        
        // Try to get the preferred size first
        foreach ($size_preference as $size) {
            if (isset($image_set[$size]['URL'])) {
                return $image_set[$size]['URL'];
            }
        }

        // Fallback to any available size
        $available_sizes = array('Large', 'Medium', 'Small');
        foreach ($available_sizes as $size) {
            if (isset($image_set[$size]['URL'])) {
                return $image_set[$size]['URL'];
            }
        }

        return '';
    }

    /**
     * Get size preference based on settings.
     *
     * @since    1.0.0
     * @return   array    Array of size preferences in order.
     */
    private function get_size_preference() {
        switch ($this->settings['thumbnail_size']) {
            case 'small':
                return array('Small', 'Medium', 'Large');
            case 'medium':
                return array('Medium', 'Large', 'Small');
            case 'large':
            default:
                return array('Large', 'Medium', 'Small');
        }
    }

    /**
     * Download and import an image.
     *
     * @since    1.0.0
     * @param    string    $image_url     Image URL.
     * @param    int       $product_id    Product ID.
     * @param    string    $image_type    Image type.
     * @return   int|null  Attachment ID or null on failure.
     */
    private function download_and_import_image($image_url, $product_id, $image_type) {
        // Validate URL
        if (!$this->is_valid_image_url($image_url)) {
            $this->logger->log("Invalid image URL: {$image_url}", 'error');
            return null;
        }

        // Check if image already exists
        if (!$this->settings['replace_existing']) {
            $existing_id = $this->find_existing_image($image_url);
            if ($existing_id) {
                return $existing_id;
            }
        }

        try {
            // Download the image
            $temp_file = $this->download_image($image_url);
            
            if (!$temp_file) {
                return null;
            }

            // Validate downloaded file
            if (!$this->validate_image_file($temp_file)) {
                unlink($temp_file);
                return null;
            }

            // Process the image
            $processed_file = $this->process_image_file($temp_file, $product_id, $image_type);
            
            if (!$processed_file) {
                unlink($temp_file);
                return null;
            }

            // Import to WordPress media library
            $attachment_id = $this->import_to_media_library($processed_file, $product_id, $image_type);

            // Clean up temporary files
            if ($temp_file !== $processed_file) {
                unlink($temp_file);
            }
            unlink($processed_file);

            return $attachment_id;

        } catch (Exception $e) {
            $this->logger->log("Failed to download image {$image_url}: " . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Download image from URL.
     *
     * @since    1.0.0
     * @param    string    $url    Image URL.
     * @return   string|null    Temporary file path or null on failure.
     */
    private function download_image($url) {
        $args = array(
            'timeout' => $this->settings['timeout'],
            'user-agent' => 'Mozilla/5.0 (compatible; Amazon Product Importer)',
            'headers' => array(
                'Accept' => 'image/*'
            )
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            $this->logger->log("Failed to download image from {$url}: " . $response->get_error_message(), 'error');
            return null;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $this->logger->log("Failed to download image from {$url}: HTTP {$response_code}", 'error');
            return null;
        }

        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            $this->logger->log("Empty image data received from {$url}", 'error');
            return null;
        }

        // Check file size
        if (strlen($image_data) > $this->settings['max_file_size']) {
            $this->logger->log("Image too large: " . strlen($image_data) . " bytes (max: {$this->settings['max_file_size']})", 'error');
            return null;
        }

        // Save to temporary file
        $temp_file = wp_tempnam();
        if (file_put_contents($temp_file, $image_data) === false) {
            $this->logger->log("Failed to save image to temporary file", 'error');
            return null;
        }

        return $temp_file;
    }

    /**
     * Validate image file.
     *
     * @since    1.0.0
     * @param    string    $file_path    File path.
     * @return   bool      True if valid.
     */
    private function validate_image_file($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }

        // Check file size
        if (filesize($file_path) > $this->settings['max_file_size']) {
            $this->logger->log("Image file too large: " . filesize($file_path) . " bytes", 'error');
            return false;
        }

        // Check if it's a valid image
        $image_info = getimagesize($file_path);
        if ($image_info === false) {
            $this->logger->log("Invalid image file", 'error');
            return false;
        }

        // Check file type
        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $mime_type = $image_info['mime'];
        
        $allowed_mimes = array(
            'image/jpeg' => array('jpg', 'jpeg'),
            'image/png' => array('png'),
            'image/gif' => array('gif'),
            'image/webp' => array('webp')
        );

        $is_allowed = false;
        foreach ($allowed_mimes as $mime => $extensions) {
            if ($mime_type === $mime && in_array($file_extension, $extensions)) {
                $is_allowed = true;
                break;
            }
        }

        if (!$is_allowed) {
            $this->logger->log("Unsupported image type: {$mime_type}", 'error');
            return false;
        }

        return true;
    }

    /**
     * Process image file (resize, compress, watermark).
     *
     * @since    1.0.0
     * @param    string    $file_path     Original file path.
     * @param    int       $product_id    Product ID.
     * @param    string    $image_type    Image type.
     * @return   string|null    Processed file path or null on failure.
     */
    private function process_image_file($file_path, $product_id, $image_type) {
        if (!$this->settings['compress_images'] && !$this->settings['add_watermark']) {
            return $file_path; // No processing needed
        }

        try {
            $image_editor = wp_get_image_editor($file_path);
            
            if (is_wp_error($image_editor)) {
                $this->logger->log("Failed to get image editor: " . $image_editor->get_error_message(), 'error');
                return $file_path;
            }

            $modified = false;

            // Compress image
            if ($this->settings['compress_images']) {
                $image_editor->set_quality($this->settings['quality']);
                $modified = true;
            }

            // Add watermark
            if ($this->settings['add_watermark'] && !empty($this->settings['watermark_text'])) {
                $this->add_watermark($image_editor, $this->settings['watermark_text']);
                $modified = true;
            }

            if ($modified) {
                $file_info = pathinfo($file_path);
                $processed_file = $file_info['dirname'] . '/processed_' . $file_info['basename'];
                
                $save_result = $image_editor->save($processed_file);
                if (is_wp_error($save_result)) {
                    $this->logger->log("Failed to save processed image: " . $save_result->get_error_message(), 'error');
                    return $file_path;
                }

                return $save_result['path'];
            }

            return $file_path;

        } catch (Exception $e) {
            $this->logger->log("Error processing image: " . $e->getMessage(), 'error');
            return $file_path;
        }
    }

    /**
     * Add watermark to image.
     *
     * @since    1.0.0
     * @param    WP_Image_Editor    $image_editor    Image editor instance.
     * @param    string             $text            Watermark text.
     */
    private function add_watermark($image_editor, $text) {
        // This is a basic implementation
        // For more advanced watermarking, you might want to use external libraries
        
        $size = $image_editor->get_size();
        $font_size = max(12, min(20, $size['width'] / 40));
        
        // Create text overlay (simplified - would need more complex implementation for actual text rendering)
        // This is a placeholder for watermark functionality
        $this->logger->log("Watermark functionality needs GD or ImageMagick text rendering implementation", 'info');
    }

    /**
     * Import image to WordPress media library.
     *
     * @since    1.0.0
     * @param    string    $file_path     File path.
     * @param    int       $product_id    Product ID.
     * @param    string    $image_type    Image type.
     * @return   int|null  Attachment ID or null on failure.
     */
    private function import_to_media_library($file_path, $product_id, $image_type) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $file_info = pathinfo($file_path);
        $filename = $this->generate_filename($product_id, $image_type, $file_info['extension']);

        // Prepare file for sideload
        $file_array = array(
            'name' => $filename,
            'tmp_name' => $file_path
        );

        // Sideload the file
        $attachment_id = media_handle_sideload($file_array, $product_id);

        if (is_wp_error($attachment_id)) {
            $this->logger->log("Failed to import image to media library: " . $attachment_id->get_error_message(), 'error');
            return null;
        }

        // Set alt text
        $alt_text = $this->generate_alt_text($product_id, $image_type);
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);

        return $attachment_id;
    }

    /**
     * Generate filename for imported image.
     *
     * @since    1.0.0
     * @param    int       $product_id    Product ID.
     * @param    string    $image_type    Image type.
     * @param    string    $extension     File extension.
     * @return   string    Generated filename.
     */
    private function generate_filename($product_id, $image_type, $extension) {
        if ($this->settings['preserve_original_names']) {
            return "amazon-product-{$product_id}-{$image_type}.{$extension}";
        }

        $product = wc_get_product($product_id);
        $product_name = $product ? sanitize_file_name($product->get_name()) : "product-{$product_id}";
        
        return "{$product_name}-{$image_type}.{$extension}";
    }

    /**
     * Generate alt text for image.
     *
     * @since    1.0.0
     * @param    int       $product_id    Product ID.
     * @param    string    $image_type    Image type.
     * @return   string    Generated alt text.
     */
    private function generate_alt_text($product_id, $image_type) {
        $product = wc_get_product($product_id);
        $product_title = $product ? $product->get_name() : "Product {$product_id}";
        
        $image_number = str_replace(array('primary', 'variant_'), array('1', ''), $image_type);
        if (!is_numeric($image_number)) {
            $image_number = '1';
        } else {
            $image_number = intval($image_number) + 1;
        }

        $alt_text = $this->settings['alt_text_template'];
        $alt_text = str_replace('%product_title%', $product_title, $alt_text);
        $alt_text = str_replace('%image_number%', $image_number, $alt_text);

        return $alt_text;
    }

    /**
     * Assign images to product.
     *
     * @since    1.0.0
     * @param    int      $product_id        Product ID.
     * @param    array    $processed_images  Processed image data.
     */
    private function assign_images_to_product($product_id, $processed_images) {
        // Set featured image
        if (isset($processed_images['primary'])) {
            set_post_thumbnail($product_id, $processed_images['primary']);
        }

        // Set gallery images
        if (isset($processed_images['gallery']) && !empty($processed_images['gallery'])) {
            $gallery_ids = $processed_images['gallery'];
            
            // Include featured image in gallery if it exists
            if (isset($processed_images['primary'])) {
                array_unshift($gallery_ids, $processed_images['primary']);
            }

            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
        }

        // Store image mapping for future reference
        $image_mapping = array(
            'featured_image' => isset($processed_images['primary']) ? $processed_images['primary'] : null,
            'gallery_images' => isset($processed_images['gallery']) ? $processed_images['gallery'] : array(),
            'imported_date' => current_time('mysql')
        );

        update_post_meta($product_id, '_amazon_image_mapping', $image_mapping);
    }

    /**
     * Check if image URL is valid.
     *
     * @since    1.0.0
     * @param    string    $url    Image URL.
     * @return   bool      True if valid.
     */
    private function is_valid_image_url($url) {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Check if URL is from Amazon (basic security check)
        $allowed_domains = array('images-amazon.com', 'ssl-images-amazon.com', 'm.media-amazon.com');
        $domain = parse_url($url, PHP_URL_HOST);
        
        foreach ($allowed_domains as $allowed_domain) {
            if (strpos($domain, $allowed_domain) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find existing image by URL.
     *
     * @since    1.0.0
     * @param    string    $url    Image URL.
     * @return   int|null  Attachment ID or null if not found.
     */
    private function find_existing_image($url) {
        global $wpdb;

        $attachment_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_amazon_original_url' AND meta_value = %s",
                $url
            )
        );

        return $attachment_id ? intval($attachment_id) : null;
    }

    /**
     * Get current product images.
     *
     * @since    1.0.0
     * @param    int    $product_id    Product ID.
     * @return   array  Current image URLs.
     */
    private function get_current_product_images($product_id) {
        $original_images = get_post_meta($product_id, '_amazon_original_images', true);
        
        if (empty($original_images) || !is_array($original_images)) {
            return array();
        }

        return $this->extract_image_urls_from_data($original_images);
    }

    /**
     * Extract image URLs from Amazon API data.
     *
     * @since    1.0.0
     * @param    array    $item_data    Amazon API item data.
     * @return   array    Array of image URLs.
     */
    private function extract_image_urls($item_data) {
        if (!isset($item_data['Images'])) {
            return array();
        }

        return $this->extract_image_urls_from_data($item_data['Images']);
    }

    /**
     * Extract image URLs from image data.
     *
     * @since    1.0.0
     * @param    array    $images_data    Image data.
     * @return   array    Array of image URLs.
     */
    private function extract_image_urls_from_data($images_data) {
        $urls = array();

        // Primary image
        if (isset($images_data['Primary'])) {
            $primary_url = $this->get_best_image_url($images_data['Primary']);
            if ($primary_url) {
                $urls[] = $primary_url;
            }
        }

        // Variant images
        if (isset($images_data['Variants']) && is_array($images_data['Variants'])) {
            foreach ($images_data['Variants'] as $variant) {
                $variant_url = $this->get_best_image_url($variant);
                if ($variant_url) {
                    $urls[] = $variant_url;
                }
            }
        }

        return $urls;
    }

    /**
     * Check if images have changed.
     *
     * @since    1.0.0
     * @param    array    $current_urls    Current image URLs.
     * @param    array    $new_urls        New image URLs.
     * @return   bool     True if images have changed.
     */
    private function have_images_changed($current_urls, $new_urls) {
        // Simple comparison - could be made more sophisticated
        return count(array_diff($current_urls, $new_urls)) > 0 || 
               count(array_diff($new_urls, $current_urls)) > 0;
    }

    /**
     * Remove product images imported from Amazon.
     *
     * @since    1.0.0
     * @param    int    $product_id    Product ID.
     * @return   bool   True on success.
     */
    public function remove_product_images($product_id) {
        $image_mapping = get_post_meta($product_id, '_amazon_image_mapping', true);
        
        if (empty($image_mapping) || !is_array($image_mapping)) {
            return false;
        }

        $deleted_count = 0;

        // Remove featured image
        if (!empty($image_mapping['featured_image'])) {
            if (wp_delete_attachment($image_mapping['featured_image'], true)) {
                $deleted_count++;
            }
            delete_post_thumbnail($product_id);
        }

        // Remove gallery images
        if (!empty($image_mapping['gallery_images']) && is_array($image_mapping['gallery_images'])) {
            foreach ($image_mapping['gallery_images'] as $image_id) {
                if (wp_delete_attachment($image_id, true)) {
                    $deleted_count++;
                }
            }
            delete_post_meta($product_id, '_product_image_gallery');
        }

        // Clean up meta data
        delete_post_meta($product_id, '_amazon_image_mapping');
        delete_post_meta($product_id, '_amazon_original_images');
        delete_post_meta($product_id, '_amazon_images_imported');

        $this->logger->log("Removed {$deleted_count} Amazon images from product {$product_id}", 'info');

        return $deleted_count > 0;
    }

    /**
     * Handle sideload prefilter.
     *
     * @since    1.0.0
     * @param    array    $file    File array.
     * @return   array    Modified file array.
     */
    public function handle_sideload_prefilter($file) {
        // Additional validation can be added here
        return $file;
    }

    /**
     * Get image statistics.
     *
     * @since    1.0.0
     * @return   array    Image statistics.
     */
    public function get_image_statistics() {
        global $wpdb;

        $stats = array();

        // Total Amazon images
        $total_amazon_images = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_amazon_image_type'"
        );
        $stats['total_amazon_images'] = intval($total_amazon_images);

        // Images by type
        $images_by_type = $wpdb->get_results(
            "SELECT meta_value as image_type, COUNT(*) as count 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_amazon_image_type' 
             GROUP BY meta_value"
        );

        $stats['images_by_type'] = array();
        foreach ($images_by_type as $row) {
            $stats['images_by_type'][$row->image_type] = intval($row->count);
        }

        // Products with Amazon images
        $products_with_images = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_amazon_images_imported'"
        );
        $stats['products_with_images'] = intval($products_with_images);

        return $stats;
    }

    /**
     * Clean up orphaned Amazon images.
     *
     * @since    1.0.0
     * @param    bool    $dry_run    Whether to perform a dry run.
     * @return   array   Cleanup results.
     */
    public function cleanup_orphaned_images($dry_run = true) {
        global $wpdb;

        $results = array('deleted' => 0, 'images' => array());

        // Find Amazon images attached to non-existent products
        $orphaned_images = $wpdb->get_results(
            "SELECT p.ID, p.post_title 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             LEFT JOIN {$wpdb->posts} parent ON p.post_parent = parent.ID
             WHERE p.post_type = 'attachment'
             AND pm.meta_key = '_amazon_image_type'
             AND (p.post_parent = 0 OR parent.ID IS NULL OR parent.post_type != 'product')"
        );

        foreach ($orphaned_images as $image) {
            $results['images'][] = array(
                'id' => $image->ID,
                'title' => $image->post_title
            );

            if (!$dry_run) {
                if (wp_delete_attachment($image->ID, true)) {
                    $results['deleted']++;
                }
            }
        }

        return $results;
    }
}