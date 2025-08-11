<?php

/**
 * The price synchronization functionality of the plugin.
 *
 * @link       https://mycreanet.fr
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/cron
 */

/**
 * The price synchronization functionality of the plugin.
 *
 * Handles automatic synchronization of product prices from Amazon API.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/cron
 * @author     Your Name <https://mycreanet.fr>
 */
class Amazon_Product_Importer_Price_Sync {

    /**
     * The Amazon API instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Amazon_Api    $amazon_api    The Amazon API instance.
     */
    private $amazon_api;

    /**
     * The logger instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Logger    $logger    The logger instance.
     */
    private $logger;

    /**
     * Maximum number of products to sync per batch.
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $batch_size    Maximum products per batch.
     */
    private $batch_size = 10;

    /**
     * Maximum number of API requests per minute.
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $api_rate_limit    API rate limit.
     */
    private $api_rate_limit = 8;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->amazon_api = new Amazon_Product_Importer_Amazon_Api();
        $this->logger = new Amazon_Product_Importer_Logger();
        
        // Get batch size from settings
        $this->batch_size = get_option('ams_price_sync_batch_size', 10);
    }

    /**
     * Execute the price synchronization process.
     *
     * @since    1.0.0
     * @return   array    Results of the sync operation.
     */
    public function sync_prices() {
        $start_time = time();
        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => array()
        );

        try {
            // Check if API credentials are configured
            if (!$this->amazon_api->is_configured()) {
                throw new Exception('Amazon API credentials not configured.');
            }

            // Get products that need price updates
            $products = $this->get_products_for_sync();
            
            if (empty($products)) {
                $this->logger->log('No products found for price synchronization.', 'info');
                return $results;
            }

            $this->logger->log(sprintf('Starting price sync for %d products.', count($products)), 'info');

            // Process products in batches
            $batches = array_chunk($products, $this->batch_size);
            
            foreach ($batches as $batch_index => $batch) {
                $batch_results = $this->process_batch($batch);
                
                $results['success'] += $batch_results['success'];
                $results['failed'] += $batch_results['failed'];
                $results['skipped'] += $batch_results['skipped'];
                $results['errors'] = array_merge($results['errors'], $batch_results['errors']);

                // Rate limiting - wait between batches
                if ($batch_index < count($batches) - 1) {
                    $this->apply_rate_limit();
                }
            }

            $execution_time = time() - $start_time;
            $this->logger->log(sprintf(
                'Price sync completed. Success: %d, Failed: %d, Skipped: %d, Time: %ds',
                $results['success'],
                $results['failed'], 
                $results['skipped'],
                $execution_time
            ), 'info');

        } catch (Exception $e) {
            $this->logger->log('Price sync failed: ' . $e->getMessage(), 'error');
            $results['errors'][] = $e->getMessage();
        }

        // Update last sync timestamp
        update_option('ams_last_price_sync', current_time('mysql'));

        return $results;
    }

    /**
     * Get products that need price synchronization.
     *
     * @since    1.0.0
     * @return   array    Array of product IDs with their ASINs.
     */
    private function get_products_for_sync() {
        global $wpdb;

        // Get sync frequency from settings (hours)
        $sync_frequency = get_option('ams_price_sync_frequency', 24);
        $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$sync_frequency} hours"));

        // Query products with Amazon ASINs that need updating
        $query = "
            SELECT p.ID, pm_asin.meta_value as asin, pm_region.meta_value as region
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_asin ON p.ID = pm_asin.post_id AND pm_asin.meta_key = '_amazon_asin'
            LEFT JOIN {$wpdb->postmeta} pm_region ON p.ID = pm_region.post_id AND pm_region.meta_key = '_amazon_region'
            LEFT JOIN {$wpdb->postmeta} pm_last_sync ON p.ID = pm_last_sync.post_id AND pm_last_sync.meta_key = '_amazon_last_price_sync'
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND (pm_last_sync.meta_value IS NULL OR pm_last_sync.meta_value < %s)
            ORDER BY pm_last_sync.meta_value ASC
            LIMIT %d
        ";

        $results = $wpdb->get_results(
            $wpdb->prepare($query, $cutoff_time, $this->batch_size * 5)
        );

        $products = array();
        foreach ($results as $row) {
            $products[] = array(
                'product_id' => $row->ID,
                'asin' => $row->asin,
                'region' => $row->region ?: 'US'
            );
        }

        return $products;
    }

    /**
     * Process a batch of products for price updates.
     *
     * @since    1.0.0
     * @param    array    $batch    Array of products to process.
     * @return   array    Results of the batch processing.
     */
    private function process_batch($batch) {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => array()
        );

        // Group ASINs by region for efficient API calls
        $asin_groups = array();
        foreach ($batch as $product_data) {
            $region = $product_data['region'];
            if (!isset($asin_groups[$region])) {
                $asin_groups[$region] = array();
            }
            $asin_groups[$region][] = $product_data;
        }

        // Process each region group
        foreach ($asin_groups as $region => $products) {
            $asins = array_column($products, 'asin');
            
            try {
                // Get price data from Amazon API
                $price_data = $this->amazon_api->get_items_batch($asins, array(
                    'resources' => array('Offers.Listings.Price', 'Offers.Listings.SavingBasis'),
                    'region' => $region
                ));

                if ($price_data && isset($price_data['ItemsResult']['Items'])) {
                    foreach ($price_data['ItemsResult']['Items'] as $item) {
                        $asin = $item['ASIN'];
                        $product_data = $this->find_product_by_asin($products, $asin);
                        
                        if ($product_data) {
                            $update_result = $this->update_product_prices($product_data['product_id'], $item);
                            
                            if ($update_result) {
                                $results['success']++;
                            } else {
                                $results['failed']++;
                                $results['errors'][] = "Failed to update prices for product ID {$product_data['product_id']}";
                            }
                        }
                    }
                } else {
                    $results['failed'] += count($products);
                    $results['errors'][] = "API request failed for region {$region}";
                }

            } catch (Exception $e) {
                $results['failed'] += count($products);
                $results['errors'][] = "Error processing region {$region}: " . $e->getMessage();
                $this->logger->log("Price sync error for region {$region}: " . $e->getMessage(), 'error');
            }
        }

        return $results;
    }

    /**
     * Update product prices from Amazon API data.
     *
     * @since    1.0.0
     * @param    int      $product_id    WooCommerce product ID.
     * @param    array    $item_data     Amazon API item data.
     * @return   bool     Success status.
     */
    private function update_product_prices($product_id, $item_data) {
        try {
            $product = wc_get_product($product_id);
            
            if (!$product) {
                return false;
            }

            $updated = false;
            $price_updated = false;

            // Check if price updates are enabled
            if (!get_option('ams_auto_update_prices', true)) {
                // Just update the sync timestamp
                update_post_meta($product_id, '_amazon_last_price_sync', current_time('mysql'));
                return true;
            }

            // Extract price information
            if (isset($item_data['Offers']['Listings'][0]['Price']['Amount'])) {
                $current_price = floatval($item_data['Offers']['Listings'][0]['Price']['Amount']);
                $current_price_formatted = number_format($current_price, 2, '.', '');
                
                // Get regular price (if on sale)
                $regular_price = $current_price;
                if (isset($item_data['Offers']['Listings'][0]['SavingBasis']['Amount'])) {
                    $regular_price = floatval($item_data['Offers']['Listings'][0]['SavingBasis']['Amount']);
                }
                $regular_price_formatted = number_format($regular_price, 2, '.', '');

                // Check if prices have changed
                $old_price = $product->get_price();
                $old_regular_price = $product->get_regular_price();

                if ($old_regular_price != $regular_price_formatted || $old_price != $current_price_formatted) {
                    // Update product prices
                    $product->set_regular_price($regular_price_formatted);
                    
                    if ($current_price < $regular_price) {
                        $product->set_sale_price($current_price_formatted);
                        $product->set_price($current_price_formatted);
                    } else {
                        $product->set_sale_price('');
                        $product->set_price($regular_price_formatted);
                    }

                    $product->save();
                    $price_updated = true;
                    $updated = true;

                    // Log price change
                    $this->logger->log(sprintf(
                        'Price updated for product %d (ASIN: %s): Regular: %s -> %s, Sale: %s -> %s',
                        $product_id,
                        $item_data['ASIN'],
                        $old_regular_price,
                        $regular_price_formatted,
                        $old_price,
                        $current_price_formatted
                    ), 'info');
                }
            }

            // Store price history if enabled
            if ($price_updated && get_option('ams_store_price_history', false)) {
                $this->store_price_history($product_id, $item_data);
            }

            // Update sync timestamp
            update_post_meta($product_id, '_amazon_last_price_sync', current_time('mysql'));

            // Update availability status if provided
            if (isset($item_data['Offers']['Listings'][0]['Availability']['Type'])) {
                $availability = $item_data['Offers']['Listings'][0]['Availability']['Type'];
                $stock_status = ($availability === 'Now') ? 'instock' : 'outofstock';
                $product->set_stock_status($stock_status);
                $product->save();
            }

            return true;

        } catch (Exception $e) {
            $this->logger->log("Error updating prices for product {$product_id}: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Store price history for a product.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $item_data     Amazon API item data.
     */
    private function store_price_history($product_id, $item_data) {
        $history = get_post_meta($product_id, '_amazon_price_history', true);
        if (!is_array($history)) {
            $history = array();
        }

        $price_entry = array(
            'date' => current_time('mysql'),
            'price' => isset($item_data['Offers']['Listings'][0]['Price']['Amount']) 
                      ? $item_data['Offers']['Listings'][0]['Price']['Amount'] : null,
            'regular_price' => isset($item_data['Offers']['Listings'][0]['SavingBasis']['Amount']) 
                              ? $item_data['Offers']['Listings'][0]['SavingBasis']['Amount'] : null,
            'availability' => isset($item_data['Offers']['Listings'][0]['Availability']['Type']) 
                             ? $item_data['Offers']['Listings'][0]['Availability']['Type'] : null
        );

        $history[] = $price_entry;

        // Keep only last 30 entries
        if (count($history) > 30) {
            $history = array_slice($history, -30);
        }

        update_post_meta($product_id, '_amazon_price_history', $history);
    }

    /**
     * Find product data by ASIN in the batch.
     *
     * @since    1.0.0
     * @param    array    $products    Array of product data.
     * @param    string   $asin        ASIN to find.
     * @return   array|null            Product data or null if not found.
     */
    private function find_product_by_asin($products, $asin) {
        foreach ($products as $product_data) {
            if ($product_data['asin'] === $asin) {
                return $product_data;
            }
        }
        return null;
    }

    /**
     * Apply rate limiting between API calls.
     *
     * @since    1.0.0
     */
    private function apply_rate_limit() {
        $delay = 60 / $this->api_rate_limit; // seconds between requests
        sleep($delay);
    }

    /**
     * Get the next scheduled sync time.
     *
     * @since    1.0.0
     * @return   string    Next sync time in MySQL format.
     */
    public function get_next_sync_time() {
        $frequency = get_option('ams_price_sync_frequency', 24);
        $last_sync = get_option('ams_last_price_sync');
        
        if ($last_sync) {
            return date('Y-m-d H:i:s', strtotime($last_sync . " +{$frequency} hours"));
        } else {
            return current_time('mysql');
        }
    }

    /**
     * Check if price sync is due.
     *
     * @since    1.0.0
     * @return   bool    True if sync is due.
     */
    public function is_sync_due() {
        $next_sync = $this->get_next_sync_time();
        return (strtotime($next_sync) <= time());
    }

    /**
     * Manual price sync for specific products.
     *
     * @since    1.0.0
     * @param    array    $product_ids    Array of product IDs to sync.
     * @return   array    Sync results.
     */
    public function manual_sync($product_ids) {
        $products = array();
        
        foreach ($product_ids as $product_id) {
            $asin = get_post_meta($product_id, '_amazon_asin', true);
            $region = get_post_meta($product_id, '_amazon_region', true) ?: 'US';
            
            if ($asin) {
                $products[] = array(
                    'product_id' => $product_id,
                    'asin' => $asin,
                    'region' => $region
                );
            }
        }

        if (empty($products)) {
            return array('success' => 0, 'failed' => 0, 'errors' => array('No valid Amazon products found.'));
        }

        return $this->process_batch($products);
    }
}