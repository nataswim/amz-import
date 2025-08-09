<?php

/**
 * The price updating functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/import
 */

/**
 * The price updating functionality of the plugin.
 *
 * Handles price updates during import and manual operations.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/import
 * @author     Your Name <email@example.com>
 */
class Amazon_Product_Importer_Price_Updater {

    /**
     * The logger instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Logger    $logger    The logger instance.
     */
    private $logger;

    /**
     * The product meta handler instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Amazon_Product_Importer_Product_Meta    $product_meta    The product meta handler.
     */
    private $product_meta;

    /**
     * Price update settings.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $settings    Price update settings.
     */
    private $settings;

    /**
     * Currency conversion rates cache.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $currency_rates    Currency conversion rates.
     */
    private $currency_rates = array();

    /**
     * Supported Amazon currencies.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $amazon_currencies    Supported Amazon currencies by region.
     */
    private $amazon_currencies = array(
        'US' => 'USD',
        'CA' => 'CAD',
        'MX' => 'MXN',
        'UK' => 'GBP',
        'DE' => 'EUR',
        'FR' => 'EUR',
        'IT' => 'EUR',
        'ES' => 'EUR',
        'JP' => 'JPY',
        'AU' => 'AUD',
        'IN' => 'INR',
        'BR' => 'BRL'
    );

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->logger = new Amazon_Product_Importer_Logger();
        $this->product_meta = new Amazon_Product_Importer_Product_Meta();
        $this->load_settings();
    }

    /**
     * Load price update settings.
     *
     * @since    1.0.0
     */
    private function load_settings() {
        $this->settings = array(
            'base_currency' => get_woocommerce_currency(),
            'price_markup' => get_option('ams_price_markup', 0),
            'price_markup_type' => get_option('ams_price_markup_type', 'percentage'), // percentage or fixed
            'round_prices' => get_option('ams_round_prices', true),
            'round_precision' => get_option('ams_price_round_precision', 2),
            'min_price' => get_option('ams_min_price', 0),
            'max_price' => get_option('ams_max_price', 0),
            'auto_currency_conversion' => get_option('ams_auto_currency_conversion', true),
            'currency_api_key' => get_option('ams_currency_api_key', ''),
            'price_comparison_enabled' => get_option('ams_price_comparison_enabled', true),
            'price_alert_threshold' => get_option('ams_price_alert_threshold', 20), // percentage
            'exclude_shipping' => get_option('ams_exclude_shipping_price', true),
            'tax_inclusive' => get_option('ams_tax_inclusive_pricing', false),
            'price_history_enabled' => get_option('ams_price_history_enabled', true)
        );
    }

    /**
     * Update product prices from Amazon API data.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $item_data     Amazon API item data.
     * @param    bool     $force_update  Whether to force update even if disabled.
     * @return   bool     True on success, false on failure.
     */
    public function update_product_prices($product_id, $item_data, $force_update = false) {
        if (!$force_update && !get_option('ams_auto_update_prices', true)) {
            return false;
        }

        try {
            $product = wc_get_product($product_id);
            if (!$product) {
                $this->logger->log("Product {$product_id} not found for price update", 'error');
                return false;
            }

            // Extract pricing information from API data
            $price_data = $this->extract_price_data($item_data);
            
            if (empty($price_data)) {
                $this->logger->log("No price data found for product {$product_id}", 'warning');
                return false;
            }

            // Store original price before update
            $old_prices = $this->get_current_prices($product);

            // Update prices based on product type
            $updated = false;
            if ($product->is_type('variable')) {
                $updated = $this->update_variable_product_prices($product, $price_data, $item_data);
            } else {
                $updated = $this->update_simple_product_prices($product, $price_data);
            }

            if ($updated) {
                // Update price metadata
                $this->update_price_metadata($product_id, $price_data, $old_prices);
                
                // Store price history
                if ($this->settings['price_history_enabled']) {
                    $this->store_price_history($product_id, $price_data, $old_prices);
                }

                // Check for significant price changes
                $this->check_price_alerts($product_id, $old_prices, $price_data);

                $this->logger->log(sprintf(
                    'Prices updated for product %d. Regular: %s, Sale: %s',
                    $product_id,
                    isset($price_data['regular_price']) ? $price_data['regular_price'] : 'N/A',
                    isset($price_data['sale_price']) ? $price_data['sale_price'] : 'N/A'
                ), 'info');

                return true;
            }

            return false;

        } catch (Exception $e) {
            $this->logger->log("Error updating prices for product {$product_id}: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Extract price data from Amazon API response.
     *
     * @since    1.0.0
     * @param    array    $item_data    Amazon API item data.
     * @return   array    Extracted price data.
     */
    private function extract_price_data($item_data) {
        $price_data = array();

        if (!isset($item_data['Offers']['Listings'][0])) {
            return $price_data;
        }

        $listing = $item_data['Offers']['Listings'][0];

        // Get current price
        if (isset($listing['Price']['Amount'])) {
            $current_price = floatval($listing['Price']['Amount']);
            $currency = isset($listing['Price']['Currency']) ? $listing['Price']['Currency'] : 'USD';
            
            $price_data['current_price'] = $current_price;
            $price_data['currency'] = $currency;
        }

        // Get regular price (list price or saving basis)
        if (isset($listing['SavingBasis']['Amount'])) {
            $price_data['list_price'] = floatval($listing['SavingBasis']['Amount']);
        }

        // Determine regular and sale prices
        if (isset($price_data['list_price']) && isset($price_data['current_price'])) {
            if ($price_data['current_price'] < $price_data['list_price']) {
                $price_data['regular_price'] = $price_data['list_price'];
                $price_data['sale_price'] = $price_data['current_price'];
            } else {
                $price_data['regular_price'] = $price_data['current_price'];
            }
        } else {
            $price_data['regular_price'] = isset($price_data['current_price']) ? $price_data['current_price'] : 0;
        }

        // Get shipping information if not excluded
        if (!$this->settings['exclude_shipping'] && isset($listing['DeliveryInfo']['ShippingCharges'])) {
            $price_data['shipping_charge'] = floatval($listing['DeliveryInfo']['ShippingCharges']['Amount']);
        }

        // Get availability information
        if (isset($listing['Availability'])) {
            $price_data['availability'] = $listing['Availability']['Type'];
            $price_data['min_order_quantity'] = isset($listing['Availability']['MinOrderQuantity']) 
                                              ? intval($listing['Availability']['MinOrderQuantity']) : 1;
            $price_data['max_order_quantity'] = isset($listing['Availability']['MaxOrderQuantity']) 
                                              ? intval($listing['Availability']['MaxOrderQuantity']) : null;
        }

        // Get Prime eligibility
        if (isset($listing['ProgramEligibility']['IsPrimeEligible'])) {
            $price_data['prime_eligible'] = $listing['ProgramEligibility']['IsPrimeEligible'];
        }

        return $price_data;
    }

    /**
     * Update prices for simple product.
     *
     * @since    1.0.0
     * @param    WC_Product    $product      Product object.
     * @param    array         $price_data   Price data.
     * @return   bool          True if updated.
     */
    private function update_simple_product_prices($product, $price_data) {
        $updated = false;

        // Convert currency if needed
        $converted_prices = $this->convert_currency($price_data);

        // Apply markup
        $final_prices = $this->apply_markup($converted_prices);

        // Validate price limits
        $final_prices = $this->validate_price_limits($final_prices);

        // Round prices
        if ($this->settings['round_prices']) {
            $final_prices = $this->round_prices($final_prices);
        }

        // Update regular price
        if (isset($final_prices['regular_price']) && $final_prices['regular_price'] > 0) {
            $current_regular = $product->get_regular_price();
            if ($current_regular != $final_prices['regular_price']) {
                $product->set_regular_price($final_prices['regular_price']);
                $updated = true;
            }
        }

        // Update sale price
        if (isset($final_prices['sale_price']) && $final_prices['sale_price'] > 0) {
            $current_sale = $product->get_sale_price();
            if ($current_sale != $final_prices['sale_price']) {
                $product->set_sale_price($final_prices['sale_price']);
                $updated = true;
            }
        } else {
            // Remove sale price if not applicable
            if ($product->get_sale_price()) {
                $product->set_sale_price('');
                $updated = true;
            }
        }

        // Update stock status based on availability
        if (isset($price_data['availability'])) {
            $stock_status = $this->get_stock_status_from_availability($price_data['availability']);
            if ($product->get_stock_status() !== $stock_status) {
                $product->set_stock_status($stock_status);
                $updated = true;
            }
        }

        if ($updated) {
            $product->save();
        }

        return $updated;
    }

    /**
     * Update prices for variable product.
     *
     * @since    1.0.0
     * @param    WC_Product    $product      Product object.
     * @param    array         $price_data   Price data.
     * @param    array         $item_data    Full item data.
     * @return   bool          True if updated.
     */
    private function update_variable_product_prices($product, $price_data, $item_data) {
        $updated = false;

        // For variable products, we need to handle variations
        if (isset($item_data['Variations']['Items'])) {
            foreach ($item_data['Variations']['Items'] as $variation_data) {
                $variation_asin = $variation_data['ASIN'];
                $variation_id = $this->product_meta->get_product_by_asin($variation_asin);
                
                if ($variation_id) {
                    $variation_price_data = $this->extract_price_data($variation_data);
                    if (!empty($variation_price_data)) {
                        $variation = wc_get_product($variation_id);
                        if ($variation && $variation->is_type('variation')) {
                            $variation_updated = $this->update_simple_product_prices($variation, $variation_price_data);
                            if ($variation_updated) {
                                $updated = true;
                            }
                        }
                    }
                }
            }
        } else {
            // If no variation data, update parent with main price data
            $updated = $this->update_simple_product_prices($product, $price_data);
        }

        // Update variable product price range
        if ($updated) {
            $product->sync_variations();
            $product->save();
        }

        return $updated;
    }

    /**
     * Convert currency if needed.
     *
     * @since    1.0.0
     * @param    array    $price_data    Price data.
     * @return   array    Converted price data.
     */
    private function convert_currency($price_data) {
        if (!$this->settings['auto_currency_conversion']) {
            return $price_data;
        }

        $from_currency = isset($price_data['currency']) ? $price_data['currency'] : 'USD';
        $to_currency = $this->settings['base_currency'];

        if ($from_currency === $to_currency) {
            return $price_data;
        }

        $rate = $this->get_currency_rate($from_currency, $to_currency);
        if (!$rate) {
            $this->logger->log("Currency conversion rate not available: {$from_currency} to {$to_currency}", 'warning');
            return $price_data;
        }

        $converted_data = $price_data;

        // Convert prices
        $price_fields = array('current_price', 'regular_price', 'sale_price', 'list_price', 'shipping_charge');
        foreach ($price_fields as $field) {
            if (isset($converted_data[$field]) && $converted_data[$field] > 0) {
                $converted_data[$field] = $converted_data[$field] * $rate;
            }
        }

        $converted_data['original_currency'] = $from_currency;
        $converted_data['conversion_rate'] = $rate;
        $converted_data['currency'] = $to_currency;

        return $converted_data;
    }

    /**
     * Apply markup to prices.
     *
     * @since    1.0.0
     * @param    array    $price_data    Price data.
     * @return   array    Price data with markup applied.
     */
    private function apply_markup($price_data) {
        if ($this->settings['price_markup'] <= 0) {
            return $price_data;
        }

        $markup_amount = $this->settings['price_markup'];
        $markup_type = $this->settings['price_markup_type'];

        $marked_up_data = $price_data;

        $price_fields = array('regular_price', 'sale_price');
        foreach ($price_fields as $field) {
            if (isset($marked_up_data[$field]) && $marked_up_data[$field] > 0) {
                if ($markup_type === 'percentage') {
                    $marked_up_data[$field] = $marked_up_data[$field] * (1 + ($markup_amount / 100));
                } else {
                    $marked_up_data[$field] = $marked_up_data[$field] + $markup_amount;
                }
            }
        }

        return $marked_up_data;
    }

    /**
     * Validate price limits.
     *
     * @since    1.0.0
     * @param    array    $price_data    Price data.
     * @return   array    Validated price data.
     */
    private function validate_price_limits($price_data) {
        $validated_data = $price_data;

        $price_fields = array('regular_price', 'sale_price');
        foreach ($price_fields as $field) {
            if (isset($validated_data[$field]) && $validated_data[$field] > 0) {
                // Check minimum price
                if ($this->settings['min_price'] > 0 && $validated_data[$field] < $this->settings['min_price']) {
                    $validated_data[$field] = $this->settings['min_price'];
                }

                // Check maximum price
                if ($this->settings['max_price'] > 0 && $validated_data[$field] > $this->settings['max_price']) {
                    $validated_data[$field] = $this->settings['max_price'];
                }
            }
        }

        return $validated_data;
    }

    /**
     * Round prices according to settings.
     *
     * @since    1.0.0
     * @param    array    $price_data    Price data.
     * @return   array    Rounded price data.
     */
    private function round_prices($price_data) {
        $rounded_data = $price_data;
        $precision = $this->settings['round_precision'];

        $price_fields = array('regular_price', 'sale_price', 'current_price');
        foreach ($price_fields as $field) {
            if (isset($rounded_data[$field]) && $rounded_data[$field] > 0) {
                $rounded_data[$field] = round($rounded_data[$field], $precision);
            }
        }

        return $rounded_data;
    }

    /**
     * Get currency conversion rate.
     *
     * @since    1.0.0
     * @param    string    $from_currency    From currency code.
     * @param    string    $to_currency      To currency code.
     * @return   float|null    Conversion rate or null if not available.
     */
    private function get_currency_rate($from_currency, $to_currency) {
        $cache_key = "{$from_currency}_{$to_currency}";
        
        // Check cache first
        if (isset($this->currency_rates[$cache_key])) {
            return $this->currency_rates[$cache_key];
        }

        // Try to get rate from WordPress transient (24-hour cache)
        $transient_key = "ams_currency_rate_{$cache_key}";
        $cached_rate = get_transient($transient_key);
        
        if ($cached_rate !== false) {
            $this->currency_rates[$cache_key] = $cached_rate;
            return $cached_rate;
        }

        // Fetch rate from external API
        $rate = $this->fetch_currency_rate($from_currency, $to_currency);
        
        if ($rate) {
            $this->currency_rates[$cache_key] = $rate;
            set_transient($transient_key, $rate, DAY_IN_SECONDS);
        }

        return $rate;
    }

    /**
     * Fetch currency rate from external API.
     *
     * @since    1.0.0
     * @param    string    $from_currency    From currency code.
     * @param    string    $to_currency      To currency code.
     * @return   float|null    Conversion rate or null if not available.
     */
    private function fetch_currency_rate($from_currency, $to_currency) {
        if (empty($this->settings['currency_api_key'])) {
            // Use a free API service (example: exchangerate-api.com)
            $api_url = "https://api.exchangerate-api.com/v4/latest/{$from_currency}";
        } else {
            // Use paid API service with key
            $api_url = "https://api.currencyapi.com/v3/latest?apikey={$this->settings['currency_api_key']}&base_currency={$from_currency}";
        }

        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'Amazon Product Importer Plugin'
            )
        ));

        if (is_wp_error($response)) {
            $this->logger->log("Currency API error: " . $response->get_error_message(), 'error');
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || !isset($data['rates'][$to_currency])) {
            $this->logger->log("Invalid currency API response", 'error');
            return null;
        }

        return floatval($data['rates'][$to_currency]);
    }

    /**
     * Get stock status from Amazon availability.
     *
     * @since    1.0.0
     * @param    string    $availability    Amazon availability status.
     * @return   string    WooCommerce stock status.
     */
    private function get_stock_status_from_availability($availability) {
        $availability_lower = strtolower($availability);

        $in_stock_statuses = array('now', 'in stock', 'available');
        $out_of_stock_statuses = array('unavailable', 'out of stock', 'temporarily unavailable');

        foreach ($in_stock_statuses as $status) {
            if (strpos($availability_lower, $status) !== false) {
                return 'instock';
            }
        }

        foreach ($out_of_stock_statuses as $status) {
            if (strpos($availability_lower, $status) !== false) {
                return 'outofstock';
            }
        }

        return 'instock'; // Default to in stock
    }

    /**
     * Get current product prices.
     *
     * @since    1.0.0
     * @param    WC_Product    $product    Product object.
     * @return   array         Current prices.
     */
    private function get_current_prices($product) {
        return array(
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'price' => $product->get_price()
        );
    }

    /**
     * Update price metadata.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $price_data    New price data.
     * @param    array    $old_prices    Old price data.
     */
    private function update_price_metadata($product_id, $price_data, $old_prices) {
        // Update Amazon-specific price metadata
        $this->product_meta->set_meta($product_id, 'original_price', isset($price_data['current_price']) ? $price_data['current_price'] : '');
        $this->product_meta->set_meta($product_id, 'original_currency', isset($price_data['currency']) ? $price_data['currency'] : '');
        $this->product_meta->set_meta($product_id, 'last_price_update', current_time('mysql'));

        if (isset($price_data['prime_eligible'])) {
            $this->product_meta->set_meta($product_id, 'prime_eligible', $price_data['prime_eligible']);
        }

        if (isset($price_data['availability'])) {
            $this->product_meta->set_meta($product_id, 'availability', $price_data['availability']);
        }

        // Store conversion information if applicable
        if (isset($price_data['conversion_rate'])) {
            $this->product_meta->set_meta($product_id, 'conversion_rate', $price_data['conversion_rate']);
            $this->product_meta->set_meta($product_id, 'original_currency', $price_data['original_currency']);
        }
    }

    /**
     * Store price history.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $price_data    New price data.
     * @param    array    $old_prices    Old price data.
     */
    private function store_price_history($product_id, $price_data, $old_prices) {
        $current_price = isset($price_data['regular_price']) ? $price_data['regular_price'] : 0;
        $currency = isset($price_data['currency']) ? $price_data['currency'] : $this->settings['base_currency'];

        $this->product_meta->add_price_to_history($product_id, $current_price, $currency);
    }

    /**
     * Check for price alerts.
     *
     * @since    1.0.0
     * @param    int      $product_id    Product ID.
     * @param    array    $old_prices    Old price data.
     * @param    array    $new_prices    New price data.
     */
    private function check_price_alerts($product_id, $old_prices, $new_prices) {
        if (!$this->settings['price_comparison_enabled']) {
            return;
        }

        $old_price = floatval($old_prices['regular_price']);
        $new_price = isset($new_prices['regular_price']) ? floatval($new_prices['regular_price']) : 0;

        if ($old_price <= 0 || $new_price <= 0) {
            return;
        }

        $change_percent = abs(($new_price - $old_price) / $old_price) * 100;

        if ($change_percent >= $this->settings['price_alert_threshold']) {
            $direction = $new_price > $old_price ? 'increased' : 'decreased';
            
            $this->logger->log(sprintf(
                'Significant price change for product %d: %s from %s to %s (%.1f%%)',
                $product_id,
                $direction,
                wc_price($old_price),
                wc_price($new_price),
                $change_percent
            ), 'warning');

            // Trigger action hook for price alerts
            do_action('ams_significant_price_change', $product_id, $old_price, $new_price, $change_percent, $direction);
        }
    }

    /**
     * Bulk update prices for multiple products.
     *
     * @since    1.0.0
     * @param    array    $products_data    Array of product ID => item data pairs.
     * @return   array    Update results.
     */
    public function bulk_update_prices($products_data) {
        $results = array('success' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => array());

        foreach ($products_data as $product_id => $item_data) {
            try {
                $updated = $this->update_product_prices($product_id, $item_data);
                
                if ($updated) {
                    $results['success']++;
                } else {
                    $results['skipped']++;
                }

            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Product {$product_id}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Get price update statistics.
     *
     * @since    1.0.0
     * @return   array    Price update statistics.
     */
    public function get_price_statistics() {
        global $wpdb;

        $stats = array();

        // Products with Amazon prices
        $amazon_products = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_amazon_asin'"
        );
        $stats['total_amazon_products'] = intval($amazon_products);

        // Products updated in last 24 hours
        $recently_updated = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_amazon_last_price_update' 
                 AND meta_value > %s",
                date('Y-m-d H:i:s', strtotime('-24 hours'))
            )
        );
        $stats['recently_updated'] = intval($recently_updated);

        // Currency breakdown
        $currency_stats = $wpdb->get_results(
            "SELECT meta_value as currency, COUNT(*) as count 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_amazon_original_currency' 
             GROUP BY meta_value"
        );

        $stats['by_currency'] = array();
        foreach ($currency_stats as $row) {
            $stats['by_currency'][$row->currency] = intval($row->count);
        }

        return $stats;
    }

    /**
     * Clean up old price history.
     *
     * @since    1.0.0
     * @param    int    $days    Days to keep.
     * @return   int    Number of entries cleaned.
     */
    public function cleanup_price_history($days = 90) {
        global $wpdb;

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // This would clean up price history from custom table if implemented
        // For now, we'll clean up the meta-based history
        $products_with_history = $wpdb->get_col(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_amazon_price_history'"
        );

        $cleaned = 0;
        foreach ($products_with_history as $product_id) {
            $history = get_post_meta($product_id, '_amazon_price_history', true);
            if (is_array($history)) {
                $filtered_history = array_filter($history, function($entry) use ($cutoff_date) {
                    return isset($entry['date']) && $entry['date'] >= $cutoff_date;
                });

                if (count($filtered_history) !== count($history)) {
                    update_post_meta($product_id, '_amazon_price_history', $filtered_history);
                    $cleaned += count($history) - count($filtered_history);
                }
            }
        }

        return $cleaned;
    }

    /**
     * Export price data for analysis.
     *
     * @since    1.0.0
     * @param    array    $product_ids    Product IDs to export.
     * @return   array    Exported price data.
     */
    public function export_price_data($product_ids = array()) {
        if (empty($product_ids)) {
            // Get all Amazon products if none specified
            global $wpdb;
            $product_ids = $wpdb->get_col(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_amazon_asin'"
            );
        }

        $export_data = array();

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }

            $price_history = $this->product_meta->get_meta($product_id, 'price_history');
            $asin = $this->product_meta->get_meta($product_id, 'asin');

            $export_data[] = array(
                'product_id' => $product_id,
                'asin' => $asin,
                'product_name' => $product->get_name(),
                'current_regular_price' => $product->get_regular_price(),
                'current_sale_price' => $product->get_sale_price(),
                'original_price' => $this->product_meta->get_meta($product_id, 'original_price'),
                'original_currency' => $this->product_meta->get_meta($product_id, 'original_currency'),
                'last_update' => $this->product_meta->get_meta($product_id, 'last_price_update'),
                'price_history' => $price_history
            );
        }

        return $export_data;
    }
}