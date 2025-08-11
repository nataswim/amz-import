<?php
/**
 * Image Handler - Optimized Version
 *
 * @link       https://mycreanet.fr
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/import
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Image Handler Class
 *
 * Handles the import and management of product images from Amazon
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/import
 * @author     Your Name <https://mycreanet.fr>
 */
class Amazon_Product_Importer_Image_Handler {

    /**
     * Logger instance
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Logger    $logger    Logger instance
     */
    private $logger;

    /**
     * Cache instance
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Cache    $cache    Cache instance
     */
    private $cache;

    /**
     * Maximum number of images to import per product
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $max_images    Maximum images per product
     */
    private $max_images = 10;

    /**
     * Default image size to import
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $default_size    Default image size
     */
    private $default_size = 'Large';

    /**
     * Allowed image sizes
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $allowed_sizes    Allowed image sizes
     */
    private $allowed_sizes = array('Small', 'Medium', 'Large');

    /**
     * Image quality for processing
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $image_quality    Image quality (1-100)
     */
    private $image_quality = 85;

    /**
     * Maximum file size in bytes
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $max_file_size    Maximum file size
     */
    private $max_file_size = 5242880; // 5MB

    /**
     * Request timeout for image downloads
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $request_timeout    Request timeout in seconds
     */
    private $request_timeout = 30;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->cache = new Amazon_Product_Importer_Cache();
        
        // Load settings
        $this->max_images = get_option('amazon_product_importer_max_images', 10);
        $this->default_size = get_option('amazon_product_importer_image_size', 'Large');
        $this->image_quality = get_option('amazon_product_importer_image_quality', 85);
        $this->max_file_size = get_option('amazon_product_importer_max_file_size', 5242880);
        $this->request_timeout = get_option('amazon_product_importer_image_timeout', 30);
    }

    /**
     * Import product images from Amazon data
     *
     * @since    1.0.0
     * @access   public
     * @param    int      $product_id    WooCommerce product ID
     * @param    array    $images_data   Amazon images data
     * @return   array                   Import results
     */
    public function import_product_images($product_id, $images_data) {
        try {
            $this->logger->log('info', 'Starting image import', array(
                'product_id' => $product_id
            ));

            $import_results = array(
                'featured_image' => null,
                'gallery_images' => array(),
                'failed_images' => array(),
                'total_imported' => 0
            );

            // Import primary image as featured image
            if (isset($images_data['Primary'])) {
                $featured_result = $this->import_primary_image($product_id, $images_data['Primary']);
                $import_results['featured_image'] = $featured_result;
                
                if ($featured_result['success']) {
                    $import_results['total_imported']++;
                } else {
                    $import_results['failed_images'][] = $featured_result;
                }
            }

            // Import variant images as gallery
            if (isset($images_data['Variants']) && is_array($images_data['Variants'])) {
                $gallery_results = $this->import_gallery_images($product_id, $images_data['Variants']);
                $import_results['gallery_images'] = $gallery_results['successful'];
                $import_results['failed_images'] = array_merge(
                    $import_results['failed_images'], 
                    $gallery_results['failed']
                );
                $import_results['total_imported'] += count($gallery_results['successful']);
            }

            $this->logger->log('info', 'Image import completed', array(
                'product_id' => $product_id,
                'total_imported' => $import_results['total_imported'],
                'failed_count' => count($import_results['failed_images'])
            ));

            return array(
                'success' => $import_results['total_imported'] > 0,
                'results' => $import_results
            );

        } catch (Exception $e) {
            $this->logger->log('error', 'Image import failed', array(
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Import primary image as featured image
     *
     * @since    1.0.0
     * @access   private
     * @param    int      $product_id     Product ID
     * @param    array    $primary_image  Primary image data
     * @return   array                    Import result
     */
    private function import_primary_image($product_id, $primary_image) {
        try {
            $image_url = $this->get_best_image_url($primary_image);
            
            if (!$image_url) {
                return array(
                    'success' => false,
                    'error' => __('URL d\'image primaire non trouvée', 'amazon-product-importer')
                );
            }

            // Check if image already exists
            $existing_attachment = $this->get_existing_attachment($image_url);
            if ($existing_attachment) {
                set_post_thumbnail($product_id, $existing_attachment);
                return array(
                    'success' => true,
                    'attachment_id' => $existing_attachment,
                    'cached' => true
                );
            }

            // Download and import image
            $attachment_id = $this->download_and_import_image($image_url, $product_id, true);
            
            if (!$attachment_id) {
                return array(
                    'success' => false,
                    'error' => __('Échec du téléchargement de l\'image primaire', 'amazon-product-importer')
                );
            }

            // Set as featured image
            set_post_thumbnail($product_id, $attachment_id);

            return array(
                'success' => true,
                'attachment_id' => $attachment_id,
                'cached' => false
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Import gallery images
     *
     * @since    1.0.0
     * @access   private
     * @param    int      $product_id      Product ID
     * @param    array    $variant_images  Variant images data
     * @return   array                     Import results
     */
    private function import_gallery_images($product_id, $variant_images) {
        $successful_imports = array();
        $failed_imports = array();
        $imported_count = 0;

        foreach ($variant_images as $index => $variant_image) {
            // Respect max images limit
            if ($imported_count >= $this->max_images - 1) { // -1 for featured image
                break;
            }

            try {
                $image_url = $this->get_best_image_url($variant_image);
                
                if (!$image_url) {
                    $failed_imports[] = array(
                        'index' => $index,
                        'error' => __('URL d\'image non trouvée', 'amazon-product-importer')
                    );
                    continue;
                }

                // Check if image already exists
                $existing_attachment = $this->get_existing_attachment($image_url);
                if ($existing_attachment) {
                    $successful_imports[] = array(
                        'attachment_id' => $existing_attachment,
                        'cached' => true,
                        'index' => $index
                    );
                    $imported_count++;
                    continue;
                }

                // Download and import image
                $attachment_id = $this->download_and_import_image($image_url, $product_id, false);
                
                if ($attachment_id) {
                    $successful_imports[] = array(
                        'attachment_id' => $attachment_id,
                        'cached' => false,
                        'index' => $index
                    );
                    $imported_count++;
                } else {
                    $failed_imports[] = array(
                        'index' => $index,
                        'error' => __('Échec du téléchargement', 'amazon-product-importer')
                    );
                }

            } catch (Exception $e) {
                $failed_imports[] = array(
                    'index' => $index,
                    'error' => $e->getMessage()
                );
            }
        }

        // Update product gallery
        if (!empty($successful_imports)) {
            $this->update_product_gallery($product_id, $successful_imports);
        }

        return array(
            'successful' => $successful_imports,
            'failed' => $failed_imports
        );
    }

    /**
     * Import images for product variation
     *
     * @since    1.0.0
     * @access   public
     * @param    int      $variation_id    Variation ID
     * @param    array    $images_data     Amazon images data
     * @return   array                     Import results
     */
    public function import_variation_images($variation_id, $images_data) {
        try {
            $import_results = array(
                'variation_image' => null,
                'failed_images' => array()
            );

            // Import primary image for variation
            if (isset($images_data['Primary'])) {
                $image_result = $this->import_variation_primary_image($variation_id, $images_data['Primary']);
                $import_results['variation_image'] = $image_result;
            }

            return array(
                'success' => isset($import_results['variation_image']['success']) && $import_results['variation_image']['success'],
                'results' => $import_results
            );

        } catch (Exception $e) {
            $this->logger->log('error', 'Variation image import failed', array(
                'variation_id' => $variation_id,
                'error' => $e->getMessage()
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Import primary image for variation
     *
     * @since    1.0.0
     * @access   private
     * @param    int      $variation_id    Variation ID
     * @param    array    $primary_image   Primary image data
     * @return   array                     Import result
     */
    private function import_variation_primary_image($variation_id, $primary_image) {
        try {
            $image_url = $this->get_best_image_url($primary_image);
            
            if (!$image_url) {
                return array(
                    'success' => false,
                    'error' => __('URL d\'image de variation non trouvée', 'amazon-product-importer')
                );
            }

            // Check if image already exists
            $existing_attachment = $this->get_existing_attachment($image_url);
            if ($existing_attachment) {
                update_post_meta($variation_id, '_thumbnail_id', $existing_attachment);
                return array(
                    'success' => true,
                    'attachment_id' => $existing_attachment,
                    'cached' => true
                );
            }

            // Download and import image
            $parent_id = wp_get_post_parent_id($variation_id);
            $attachment_id = $this->download_and_import_image($image_url, $parent_id, false);
            
            if (!$attachment_id) {
                return array(
                    'success' => false,
                    'error' => __('Échec du téléchargement de l\'image de variation', 'amazon-product-importer')
                );
            }

            // Set as variation image
            update_post_meta($variation_id, '_thumbnail_id', $attachment_id);

            return array(
                'success' => true,
                'attachment_id' => $attachment_id,
                'cached' => false
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Get best image URL based on size preference
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $image_data    Image data from Amazon
     * @return   string|false            Image URL or false if not found
     */
    private function get_best_image_url($image_data) {
        // Try to get preferred size first
        if (isset($image_data[$this->default_size]['URL'])) {
            return $image_data[$this->default_size]['URL'];
        }

        // Fallback to other sizes
        foreach ($this->allowed_sizes as $size) {
            if (isset($image_data[$size]['URL'])) {
                return $image_data[$size]['URL'];
            }
        }

        return false;
    }

    /**
     * Check if attachment already exists for given URL
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $image_url    Image URL
     * @return   int|false               Attachment ID or false if not found
     */
    private function get_existing_attachment($image_url) {
        global $wpdb;

        // Create a hash of the URL for lookup
        $url_hash = md5($image_url);

        // Check if we have this image cached
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_amazon_image_url_hash' 
             AND meta_value = %s 
             LIMIT 1",
            $url_hash
        ));

        if ($attachment_id) {
            // Verify attachment still exists
            if (get_post($attachment_id)) {
                return intval($attachment_id);
            } else {
                // Clean up orphaned meta
                delete_post_meta($attachment_id, '_amazon_image_url_hash');
            }
        }

        return false;
    }

    /**
     * Download and import image
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $image_url       Image URL
     * @param    int       $product_id      Product ID
     * @param    bool      $is_featured     Whether this is a featured image
     * @return   int|false                  Attachment ID or false on failure
     */
    private function download_and_import_image($image_url, $product_id, $is_featured = false) {
        try {
            // Validate image URL
            if (!$this->validate_image_url($image_url)) {
                throw new Exception(__('URL d\'image invalide', 'amazon-product-importer'));
            }

            // Download image
            $downloaded_file = $this->download_image($image_url);
            
            if (!$downloaded_file) {
                throw new Exception(__('Échec du téléchargement de l\'image', 'amazon-product-importer'));
            }

            // Process and optimize image
            $processed_file = $this->process_image($downloaded_file);

            // Generate filename
            $filename = $this->generate_filename($image_url, $product_id, $is_featured);

            // Create attachment
            $attachment_id = $this->create_attachment($processed_file, $filename, $product_id);

            if (!$attachment_id) {
                // Clean up temporary files
                @unlink($downloaded_file);
                if ($processed_file !== $downloaded_file) {
                    @unlink($processed_file);
                }
                throw new Exception(__('Échec de la création de l\'attachement', 'amazon-product-importer'));
            }

            // Store URL hash for future reference
            update_post_meta($attachment_id, '_amazon_image_url_hash', md5($image_url));
            update_post_meta($attachment_id, '_amazon_image_original_url', $image_url);

            // Clean up temporary files
            @unlink($downloaded_file);
            if ($processed_file !== $downloaded_file) {
                @unlink($processed_file);
            }

            $this->logger->log('info', 'Image imported successfully', array(
                'product_id' => $product_id,
                'attachment_id' => $attachment_id,
                'image_url' => $image_url,
                'is_featured' => $is_featured
            ));

            return $attachment_id;

        } catch (Exception $e) {
            $this->logger->log('error', 'Image import failed', array(
                'product_id' => $product_id,
                'image_url' => $image_url,
                'error' => $e->getMessage()
            ));

            return false;
        }
    }

    /**
     * Validate image URL
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $image_url    Image URL
     * @return   bool                    True if valid, false otherwise
     */
    private function validate_image_url($image_url) {
        // Basic URL validation
        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Check if it's an Amazon image
        if (strpos($image_url, 'images-amazon.com') === false && 
            strpos($image_url, 'm.media-amazon.com') === false) {
            return false;
        }

        // Check file extension
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        $url_parts = parse_url($image_url);
        $path_info = pathinfo($url_parts['path']);
        
        if (!isset($path_info['extension']) || 
            !in_array(strtolower($path_info['extension']), $allowed_extensions)) {
            // Amazon images might not have extensions in URL, so this is not a hard requirement
        }

        return true;
    }

    /**
     * Download image from URL
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $image_url    Image URL
     * @return   string|false            Downloaded file path or false on failure
     */
    private function download_image($image_url) {
        // Create temporary file
        $temp_file = wp_tempnam();
        
        if (!$temp_file) {
            return false;
        }

        // Download image
        $response = wp_remote_get($image_url, array(
            'timeout' => $this->request_timeout,
            'stream' => true,
            'filename' => $temp_file,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (compatible; Amazon Product Importer)'
            )
        ));

        if (is_wp_error($response)) {
            @unlink($temp_file);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            @unlink($temp_file);
            return false;
        }

        // Check file size
        $file_size = filesize($temp_file);
        if ($file_size === false || $file_size > $this->max_file_size) {
            @unlink($temp_file);
            return false;
        }

        // Verify it's an image
        $image_info = getimagesize($temp_file);
        if ($image_info === false) {
            @unlink($temp_file);
            return false;
        }

        return $temp_file;
    }

    /**
     * Process and optimize image
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $file_path    File path
     * @return   string                  Processed file path
     */
    private function process_image($file_path) {
        // Get image info
        $image_info = getimagesize($file_path);
        if (!$image_info) {
            return $file_path;
        }

        $image_type = $image_info[2];
        $width = $image_info[0];
        $height = $image_info[1];

        // Check if processing is needed
        $max_width = get_option('amazon_product_importer_max_image_width', 1200);
        $max_height = get_option('amazon_product_importer_max_image_height', 1200);

        if ($width <= $max_width && $height <= $max_height && $this->image_quality >= 90) {
            return $file_path; // No processing needed
        }

        try {
            // Create image resource
            switch ($image_type) {
                case IMAGETYPE_JPEG:
                    $image = imagecreatefromjpeg($file_path);
                    break;
                case IMAGETYPE_PNG:
                    $image = imagecreatefrompng($file_path);
                    break;
                case IMAGETYPE_GIF:
                    $image = imagecreatefromgif($file_path);
                    break;
                default:
                    return $file_path;
            }

            if (!$image) {
                return $file_path;
            }

            // Calculate new dimensions
            $new_dimensions = $this->calculate_resize_dimensions($width, $height, $max_width, $max_height);
            
            if ($new_dimensions['width'] === $width && $new_dimensions['height'] === $height) {
                imagedestroy($image);
                return $file_path; // No resize needed
            }

            // Create resized image
            $resized_image = imagecreatetruecolor($new_dimensions['width'], $new_dimensions['height']);
            
            // Preserve transparency for PNG and GIF
            if ($image_type === IMAGETYPE_PNG || $image_type === IMAGETYPE_GIF) {
                imagealphablending($resized_image, false);
                imagesavealpha($resized_image, true);
                $transparent = imagecolorallocatealpha($resized_image, 255, 255, 255, 127);
                imagefill($resized_image, 0, 0, $transparent);
            }

            // Resize image
            imagecopyresampled(
                $resized_image, $image, 
                0, 0, 0, 0,
                $new_dimensions['width'], $new_dimensions['height'],
                $width, $height
            );

            // Create new temporary file
            $processed_file = wp_tempnam();
            
            // Save processed image
            switch ($image_type) {
                case IMAGETYPE_JPEG:
                    imagejpeg($resized_image, $processed_file, $this->image_quality);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($resized_image, $processed_file, (100 - $this->image_quality) / 10);
                    break;
                case IMAGETYPE_GIF:
                    imagegif($resized_image, $processed_file);
                    break;
            }

            // Clean up
            imagedestroy($image);
            imagedestroy($resized_image);

            return $processed_file;

        } catch (Exception $e) {
            $this->logger->log('warning', 'Image processing failed', array(
                'file_path' => $file_path,
                'error' => $e->getMessage()
            ));
            
            return $file_path;
        }
    }

    /**
     * Calculate resize dimensions while maintaining aspect ratio
     *
     * @since    1.0.0
     * @access   private
     * @param    int    $width       Original width
     * @param    int    $height      Original height
     * @param    int    $max_width   Maximum width
     * @param    int    $max_height  Maximum height
     * @return   array               New dimensions
     */
    private function calculate_resize_dimensions($width, $height, $max_width, $max_height) {
        $ratio_w = $max_width / $width;
        $ratio_h = $max_height / $height;
        $ratio = min($ratio_w, $ratio_h);

        if ($ratio >= 1) {
            return array('width' => $width, 'height' => $height);
        }

        return array(
            'width' => intval($width * $ratio),
            'height' => intval($height * $ratio)
        );
    }

    /**
     * Generate filename for attachment
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $image_url      Image URL
     * @param    int       $product_id     Product ID
     * @param    bool      $is_featured    Whether this is a featured image
     * @return   string                    Generated filename
     */
    private function generate_filename($image_url, $product_id, $is_featured = false) {
        $url_parts = parse_url($image_url);
        $path_info = pathinfo($url_parts['path']);
        
        $extension = isset($path_info['extension']) ? $path_info['extension'] : 'jpg';
        $product_slug = get_post_field('post_name', $product_id);
        
        if ($is_featured) {
            $filename = "amazon-product-{$product_id}-{$product_slug}-featured.{$extension}";
        } else {
            $hash = substr(md5($image_url), 0, 8);
            $filename = "amazon-product-{$product_id}-{$product_slug}-{$hash}.{$extension}";
        }

        return sanitize_file_name($filename);
    }

    /**
     * Create WordPress attachment
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $file_path    File path
     * @param    string    $filename     Filename
     * @param    int       $product_id   Product ID
     * @return   int|false               Attachment ID or false on failure
     */
    private function create_attachment($file_path, $filename, $product_id) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Move file to uploads directory
        $upload_dir = wp_upload_dir();
        $target_path = $upload_dir['path'] . '/' . $filename;
        
        if (!copy($file_path, $target_path)) {
            return false;
        }

        // Prepare attachment data
        $attachment_data = array(
            'post_mime_type' => wp_check_filetype($filename)['type'],
            'post_title' => sanitize_title_with_dashes(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => $product_id
        );

        // Insert attachment
        $attachment_id = wp_insert_attachment($attachment_data, $target_path, $product_id);
        
        if (is_wp_error($attachment_id)) {
            @unlink($target_path);
            return false;
        }

        // Generate attachment metadata
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $target_path);
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);

        return $attachment_id;
    }

    /**
     * Update product gallery with imported images
     *
     * @since    1.0.0
     * @access   private
     * @param    int      $product_id          Product ID
     * @param    array    $imported_images     Imported image data
     */
    private function update_product_gallery($product_id, $imported_images) {
        $gallery_ids = array();

        foreach ($imported_images as $image_data) {
            $gallery_ids[] = $image_data['attachment_id'];
        }

        if (!empty($gallery_ids)) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
        }
    }

    /**
     * Clean up orphaned images
     *
     * @since    1.0.0
     * @access   public
     * @param    int    $days_old    Remove images older than this many days
     * @return   int                 Number of images cleaned up
     */
    public function cleanup_orphaned_images($days_old = 30) {
        global $wpdb;

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        // Find orphaned Amazon images
        $orphaned_images = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'attachment'
             AND pm.meta_key = '_amazon_image_url_hash'
             AND p.post_parent = 0
             AND p.post_date < %s",
            $cutoff_date
        ));

        $cleaned_count = 0;

        foreach ($orphaned_images as $image) {
            if (wp_delete_attachment($image->ID, true)) {
                $cleaned_count++;
            }
        }

        $this->logger->log('info', 'Orphaned images cleaned up', array(
            'cleaned_count' => $cleaned_count,
            'days_old' => $days_old
        ));

        return $cleaned_count;
    }
}