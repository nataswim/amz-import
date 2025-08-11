<?php
/**
 * Bulk Import Handler
 *
 * @link       https://yourwebsite.com
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
 * Bulk Import Handler Class
 *
 * Handles bulk import operations from various sources
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/import
 * @author     Your Name <email@example.com>
 */
class Amazon_Product_Importer_Bulk_Importer {

    /**
     * Product importer instance
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Product_Importer    $product_importer    Product importer instance
     */
    private $product_importer;

    /**
     * Logger instance
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Logger    $logger    Logger instance
     */
    private $logger;

    /**
     * Database instance
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Database    $database    Database instance
     */
    private $database;

    /**
     * Maximum batch size for processing
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $batch_size    Maximum items per batch
     */
    private $batch_size = 10;

    /**
     * Maximum file size for uploads (in bytes)
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $max_file_size    Maximum file size
     */
    private $max_file_size = 5242880; // 5MB

    /**
     * Allowed file extensions
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $allowed_extensions    Allowed file extensions
     */
    private $allowed_extensions = array('csv', 'txt');

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->product_importer = new Amazon_Product_Importer_Product_Importer();
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->database = new Amazon_Product_Importer_Database();
        
        // Load settings
        $this->batch_size = get_option('amazon_product_importer_bulk_batch_size', 10);
        $this->max_file_size = get_option('amazon_product_importer_max_file_size', 5242880);
    }

    /**
     * Import products from CSV file
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $file_path    Path to CSV file
     * @param    array     $options      Import options
     * @return   array                   Import results
     */
    public function import_from_csv($file_path, $options = array()) {
        try {
            $this->logger->log('info', 'Starting CSV import', array(
                'file_path' => $file_path,
                'options' => $options
            ));

            // Validate file
            $validation_result = $this->validate_file($file_path);
            if (!$validation_result['valid']) {
                return array(
                    'success' => false,
                    'error' => $validation_result['error']
                );
            }

            // Parse CSV file
            $csv_data = $this->parse_csv_file($file_path);
            if (empty($csv_data)) {
                return array(
                    'success' => false,
                    'error' => __('Aucune donnée valide trouvée dans le fichier CSV', 'amazon-product-importer')
                );
            }

            // Extract ASINs from CSV data
            $asins = $this->extract_asins_from_csv($csv_data);
            if (empty($asins)) {
                return array(
                    'success' => false,
                    'error' => __('Aucun ASIN valide trouvé dans le fichier', 'amazon-product-importer')
                );
            }

            // Create bulk import job
            $job_id = $this->create_bulk_job('csv_import', array(
                'file_path' => $file_path,
                'total_items' => count($asins),
                'options' => $options
            ));

            // Process import in batches
            $results = $this->process_bulk_import($asins, $job_id, $options);

            // Update job status
            $this->update_bulk_job($job_id, 'completed', $results);

            $this->logger->log('info', 'CSV import completed', array(
                'job_id' => $job_id,
                'results' => $results
            ));

            return array(
                'success' => true,
                'job_id' => $job_id,
                'results' => $results
            );

        } catch (Exception $e) {
            $this->logger->log('error', 'CSV import failed', array(
                'file_path' => $file_path,
                'error' => $e->getMessage()
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Import products from ASIN list
     *
     * @since    1.0.0
     * @access   public
     * @param    array    $asins      Array of ASINs
     * @param    array    $options    Import options
     * @return   array                Import results
     */
    public function import_from_asin_list($asins, $options = array()) {
        try {
            $this->logger->log('info', 'Starting ASIN list import', array(
                'asin_count' => count($asins),
                'options' => $options
            ));

            // Validate ASINs
            $validated_asins = $this->validate_asin_list($asins);
            if (empty($validated_asins)) {
                return array(
                    'success' => false,
                    'error' => __('Aucun ASIN valide fourni', 'amazon-product-importer')
                );
            }

            // Create bulk import job
            $job_id = $this->create_bulk_job('asin_list_import', array(
                'total_items' => count($validated_asins),
                'options' => $options
            ));

            // Process import in batches
            $results = $this->process_bulk_import($validated_asins, $job_id, $options);

            // Update job status
            $this->update_bulk_job($job_id, 'completed', $results);

            $this->logger->log('info', 'ASIN list import completed', array(
                'job_id' => $job_id,
                'results' => $results
            ));

            return array(
                'success' => true,
                'job_id' => $job_id,
                'results' => $results
            );

        } catch (Exception $e) {
            $this->logger->log('error', 'ASIN list import failed', array(
                'error' => $e->getMessage()
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Import products from Amazon wishlist
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $wishlist_url    Wishlist URL
     * @param    array     $options         Import options
     * @return   array                      Import results
     */
    public function import_from_wishlist($wishlist_url, $options = array()) {
        try {
            $this->logger->log('info', 'Starting wishlist import', array(
                'wishlist_url' => $wishlist_url,
                'options' => $options
            ));

            // Validate wishlist URL
            if (!$this->validate_wishlist_url($wishlist_url)) {
                return array(
                    'success' => false,
                    'error' => __('URL de liste de souhaits invalide', 'amazon-product-importer')
                );
            }

            // Extract ASINs from wishlist
            $asins = $this->extract_asins_from_wishlist($wishlist_url);
            if (empty($asins)) {
                return array(
                    'success' => false,
                    'error' => __('Aucun produit trouvé dans la liste de souhaits', 'amazon-product-importer')
                );
            }

            // Create bulk import job
            $job_id = $this->create_bulk_job('wishlist_import', array(
                'wishlist_url' => $wishlist_url,
                'total_items' => count($asins),
                'options' => $options
            ));

            // Process import in batches
            $results = $this->process_bulk_import($asins, $job_id, $options);

            // Update job status
            $this->update_bulk_job($job_id, 'completed', $results);

            $this->logger->log('info', 'Wishlist import completed', array(
                'job_id' => $job_id,
                'wishlist_url' => $wishlist_url,
                'results' => $results
            ));

            return array(
                'success' => true,
                'job_id' => $job_id,
                'results' => $results,
                'asins' => $asins
            );

        } catch (Exception $e) {
            $this->logger->log('error', 'Wishlist import failed', array(
                'wishlist_url' => $wishlist_url,
                'error' => $e->getMessage()
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Process bulk import in batches
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $asins      Array of ASINs to import
     * @param    string   $job_id     Bulk import job ID
     * @param    array    $options    Import options
     * @return   array                Import results
     */
    private function process_bulk_import($asins, $job_id, $options = array()) {
        $results = array(
            'total' => count($asins),
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => array(),
            'imported_products' => array()
        );

        // Split ASINs into batches
        $batches = array_chunk($asins, $this->batch_size);
        $current_batch = 0;

        foreach ($batches as $batch) {
            $current_batch++;
            
            $this->logger->log('info', 'Processing batch', array(
                'job_id' => $job_id,
                'batch' => $current_batch,
                'total_batches' => count($batches),
                'batch_size' => count($batch)
            ));

            // Update job progress
            $progress = ($current_batch - 1) / count($batches) * 100;
            $this->update_bulk_job_progress($job_id, $progress, 
                "Traitement du lot {$current_batch}/{" . count($batches) . "}");

            // Process each ASIN in the batch
            foreach ($batch as $asin) {
                $import_result = $this->import_single_asin($asin, $options, $job_id);
                
                if ($import_result['success']) {
                    $results['success']++;
                    $results['imported_products'][] = array(
                        'asin' => $asin,
                        'product_id' => $import_result['product_id']
                    );
                } elseif ($import_result['skipped']) {
                    $results['skipped']++;
                } else {
                    $results['failed']++;
                    $results['errors'][$asin] = $import_result['error'];
                }

                // Log individual import result
                $this->log_bulk_import_item($job_id, $asin, $import_result);
            }

            // Add delay between batches to avoid API rate limits
            if ($current_batch < count($batches)) {
                sleep(2);
            }
        }

        // Update final progress
        $this->update_bulk_job_progress($job_id, 100, 'Import terminé');

        return $results;
    }

    /**
     * Import a single ASIN
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $asin       ASIN to import
     * @param    array     $options    Import options
     * @param    string    $job_id     Bulk import job ID
     * @return   array                 Import result
     */
    private function import_single_asin($asin, $options, $job_id) {
        try {
            // Check if product already exists (unless force update is enabled)
            if (!isset($options['force_update']) || !$options['force_update']) {
                $existing_product = $this->product_importer->get_existing_product_by_asin($asin);
                if ($existing_product) {
                    return array(
                        'success' => false,
                        'skipped' => true,
                        'error' => __('Produit déjà importé', 'amazon-product-importer')
                    );
                }
            }

            // Import the product
            $import_result = $this->product_importer->import_product($asin, 
                isset($options['force_update']) ? $options['force_update'] : false);

            return $import_result;

        } catch (Exception $e) {
            return array(
                'success' => false,
                'skipped' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Validate uploaded file
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $file_path    Path to file
     * @return   array                   Validation result
     */
    private function validate_file($file_path) {
        // Check if file exists
        if (!file_exists($file_path)) {
            return array(
                'valid' => false,
                'error' => __('Fichier non trouvé', 'amazon-product-importer')
            );
        }

        // Check file size
        $file_size = filesize($file_path);
        if ($file_size > $this->max_file_size) {
            return array(
                'valid' => false,
                'error' => sprintf(
                    __('Fichier trop volumineux. Taille maximale: %s', 'amazon-product-importer'),
                    size_format($this->max_file_size)
                )
            );
        }

        // Check file extension
        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if (!in_array($file_extension, $this->allowed_extensions)) {
            return array(
                'valid' => false,
                'error' => sprintf(
                    __('Extension de fichier non supportée. Extensions autorisées: %s', 'amazon-product-importer'),
                    implode(', ', $this->allowed_extensions)
                )
            );
        }

        // Check if file is readable
        if (!is_readable($file_path)) {
            return array(
                'valid' => false,
                'error' => __('Impossible de lire le fichier', 'amazon-product-importer')
            );
        }

        return array('valid' => true);
    }

    /**
     * Parse CSV file
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $file_path    Path to CSV file
     * @return   array                   Parsed CSV data
     */
    private function parse_csv_file($file_path) {
        $csv_data = array();
        
        if (($handle = fopen($file_path, "r")) !== FALSE) {
            $header = null;
            $row_count = 0;
            
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row_count++;
                
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }
                
                // First row might be header
                if ($header === null) {
                    // Check if first row looks like header (contains non-ASIN data)
                    $has_asin = false;
                    foreach ($row as $cell) {
                        if ($this->validate_asin(trim($cell))) {
                            $has_asin = true;
                            break;
                        }
                    }
                    
                    if (!$has_asin && $row_count === 1) {
                        // Treat as header row
                        $header = array_map('trim', $row);
                        continue;
                    } else {
                        // No header, use generic column names
                        $header = array_map(function($i) {
                            return 'column_' . ($i + 1);
                        }, array_keys($row));
                    }
                }
                
                // Create associative array
                $csv_data[] = array_combine($header, array_map('trim', $row));
            }
            
            fclose($handle);
        }
        
        return $csv_data;
    }

    /**
     * Extract ASINs from CSV data
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $csv_data    Parsed CSV data
     * @return   array                 Array of unique ASINs
     */
    private function extract_asins_from_csv($csv_data) {
        $asins = array();
        
        foreach ($csv_data as $row) {
            foreach ($row as $cell) {
                $cell = trim($cell);
                if ($this->validate_asin($cell)) {
                    $asins[] = $cell;
                    break; // Only take first ASIN per row
                }
            }
        }
        
        return array_unique($asins);
    }

    /**
     * Validate ASIN list
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $asins    Array of ASINs
     * @return   array              Array of valid ASINs
     */
    private function validate_asin_list($asins) {
        $valid_asins = array();
        
        foreach ($asins as $asin) {
            $asin = trim(strtoupper($asin));
            if ($this->validate_asin($asin)) {
                $valid_asins[] = $asin;
            }
        }
        
        return array_unique($valid_asins);
    }

    /**
     * Validate ASIN format
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $asin    ASIN to validate
     * @return   bool               True if valid, false otherwise
     */
    private function validate_asin($asin) {
        return preg_match('/^[A-Z0-9]{10}$/', $asin);
    }

    /**
     * Validate Amazon wishlist URL
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $url    Wishlist URL
     * @return   bool              True if valid, false otherwise
     */
    private function validate_wishlist_url($url) {
        $pattern = '/^https?:\/\/(www\.)?amazon\.[a-z.]+\/.*\/wishlist\/ls\/[A-Z0-9]+/i';
        return preg_match($pattern, $url);
    }

    /**
     * Extract ASINs from Amazon wishlist
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $wishlist_url    Wishlist URL
     * @return   array                      Array of ASINs
     */
    private function extract_asins_from_wishlist($wishlist_url) {
        // Note: This is a simplified implementation
        // In a real-world scenario, you would need to handle Amazon's anti-scraping measures
        // and potentially use their API or a third-party service
        
        $asins = array();
        
        try {
            // Add headers to mimic a real browser
            $context = stream_context_create(array(
                'http' => array(
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n",
                    'timeout' => 30
                )
            ));
            
            $html = file_get_contents($wishlist_url, false, $context);
            
            if ($html === false) {
                throw new Exception(__('Impossible de récupérer la liste de souhaits', 'amazon-product-importer'));
            }
            
            // Extract ASINs using regex (this is a basic implementation)
            preg_match_all('/data-asin="([A-Z0-9]{10})"/i', $html, $matches);
            
            if (!empty($matches[1])) {
                $asins = array_unique($matches[1]);
            }
            
            // Alternative method: look for product URLs
            if (empty($asins)) {
                preg_match_all('/\/dp\/([A-Z0-9]{10})/i', $html, $matches);
                if (!empty($matches[1])) {
                    $asins = array_unique($matches[1]);
                }
            }
            
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to extract ASINs from wishlist', array(
                'wishlist_url' => $wishlist_url,
                'error' => $e->getMessage()
            ));
        }
        
        return $asins;
    }

    /**
     * Create bulk import job
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $type    Import type
     * @param    array     $data    Job data
     * @return   string             Job ID
     */
    private function create_bulk_job($type, $data) {
        global $wpdb;
        
        $job_id = uniqid('bulk_import_');
        
        $wpdb->insert(
            $wpdb->prefix . 'amazon_bulk_jobs',
            array(
                'job_id' => $job_id,
                'type' => $type,
                'status' => 'running',
                'data' => json_encode($data),
                'progress' => 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
        
        return $job_id;
    }

    /**
     * Update bulk import job
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $job_id    Job ID
     * @param    string    $status    Job status
     * @param    array     $results   Job results
     */
    private function update_bulk_job($job_id, $status, $results = null) {
        global $wpdb;
        
        $update_data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );
        
        if ($results !== null) {
            $update_data['results'] = json_encode($results);
        }
        
        $wpdb->update(
            $wpdb->prefix . 'amazon_bulk_jobs',
            $update_data,
            array('job_id' => $job_id),
            array('%s', '%s', '%s'),
            array('%s')
        );
    }

    /**
     * Update bulk import job progress
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $job_id      Job ID
     * @param    float     $progress    Progress percentage
     * @param    string    $message     Progress message
     */
    private function update_bulk_job_progress($job_id, $progress, $message = '') {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'amazon_bulk_jobs',
            array(
                'progress' => $progress,
                'progress_message' => $message,
                'updated_at' => current_time('mysql')
            ),
            array('job_id' => $job_id),
            array('%f', '%s', '%s'),
            array('%s')
        );
    }

    /**
     * Log bulk import item result
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $job_id    Job ID
     * @param    string    $asin      ASIN
     * @param    array     $result    Import result
     */
    private function log_bulk_import_item($job_id, $asin, $result) {
        global $wpdb;
        
        $status = $result['success'] ? 'success' : ($result['skipped'] ?? false ? 'skipped' : 'failed');
        $message = $result['success'] ? 'Import réussi' : ($result['error'] ?? 'Erreur inconnue');
        $product_id = $result['product_id'] ?? null;
        
        $wpdb->insert(
            $wpdb->prefix . 'amazon_bulk_items',
            array(
                'job_id' => $job_id,
                'asin' => $asin,
                'status' => $status,
                'message' => $message,
                'product_id' => $product_id,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s')
        );
    }

    /**
     * Get bulk import job status
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $job_id    Job ID
     * @return   array|false          Job data or false if not found
     */
    public function get_bulk_job_status($job_id) {
        global $wpdb;
        
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}amazon_bulk_jobs WHERE job_id = %s",
            $job_id
        ), ARRAY_A);
        
        if (!$job) {
            return false;
        }
        
        // Decode JSON fields
        $job['data'] = json_decode($job['data'], true);
        if (!empty($job['results'])) {
            $job['results'] = json_decode($job['results'], true);
        }
        
        return $job;
    }

    /**
     * Get bulk import job items
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $job_id    Job ID
     * @return   array                Array of job items
     */
    public function get_bulk_job_items($job_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}amazon_bulk_items WHERE job_id = %s ORDER BY created_at DESC",
            $job_id
        ), ARRAY_A);
    }

    /**
     * Clean up old bulk import jobs
     *
     * @since    1.0.0
     * @access   public
     * @param    int    $days_old    Remove jobs older than this many days
     * @return   int                 Number of jobs cleaned up
     */
    public function cleanup_old_jobs($days_old = 30) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        // Get job IDs to delete
        $job_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT job_id FROM {$wpdb->prefix}amazon_bulk_jobs WHERE created_at < %s",
            $cutoff_date
        ));
        
        if (empty($job_ids)) {
            return 0;
        }
        
        // Delete job items first (foreign key constraint)
        $placeholders = implode(',', array_fill(0, count($job_ids), '%s'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}amazon_bulk_items WHERE job_id IN ($placeholders)",
            ...$job_ids
        ));
        
        // Delete jobs
        $deleted_count = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}amazon_bulk_jobs WHERE created_at < %s",
            $cutoff_date
        ));
        
        $this->logger->log('info', 'Old bulk import jobs cleaned up', array(
            'deleted_count' => $deleted_count,
            'days_old' => $days_old
        ));
        
        return $deleted_count;
    }
}