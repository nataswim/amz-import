<?php

/**
 * The helper utilities functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/utilities
 */

/**
 * The helper utilities functionality of the plugin.
 *
 * Provides common utility functions used throughout the plugin.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/utilities
 * @author     Your Name <email@example.com>
 */
class Amazon_Product_Importer_Helper {

    /**
     * Amazon marketplace domains.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $amazon_domains    Amazon marketplace domains by region.
     */
    private static $amazon_domains = array(
        'US' => 'amazon.com',
        'CA' => 'amazon.ca',
        'MX' => 'amazon.com.mx',
        'UK' => 'amazon.co.uk',
        'DE' => 'amazon.de',
        'FR' => 'amazon.fr',
        'IT' => 'amazon.it',
        'ES' => 'amazon.es',
        'NL' => 'amazon.nl',
        'AU' => 'amazon.com.au',
        'JP' => 'amazon.co.jp',
        'IN' => 'amazon.in',
        'BR' => 'amazon.com.br',
        'SG' => 'amazon.sg',
        'AE' => 'amazon.ae',
        'SA' => 'amazon.sa',
        'PL' => 'amazon.pl',
        'TR' => 'amazon.com.tr',
        'SE' => 'amazon.se'
    );

    /**
     * Common currency symbols.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $currency_symbols    Currency symbols by code.
     */
    private static $currency_symbols = array(
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'CAD' => 'C$',
        'AUD' => 'A$',
        'CHF' => 'CHF',
        'CNY' => '¥',
        'INR' => '₹',
        'BRL' => 'R$',
        'MXN' => '$',
        'SGD' => 'S$',
        'AED' => 'د.إ',
        'SAR' => '﷼',
        'PLN' => 'zł',
        'TRY' => '₺',
        'SEK' => 'kr'
    );

    /**
     * Unit conversion factors.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $unit_conversions    Unit conversion factors.
     */
    private static $unit_conversions = array(
        'weight' => array(
            'pounds_to_kg' => 0.453592,
            'ounces_to_kg' => 0.0283495,
            'grams_to_kg' => 0.001,
            'kg_to_pounds' => 2.20462,
            'kg_to_ounces' => 35.274,
            'kg_to_grams' => 1000
        ),
        'length' => array(
            'inches_to_cm' => 2.54,
            'feet_to_cm' => 30.48,
            'mm_to_cm' => 0.1,
            'meters_to_cm' => 100,
            'cm_to_inches' => 0.393701,
            'cm_to_feet' => 0.0328084,
            'cm_to_mm' => 10,
            'cm_to_meters' => 0.01
        )
    );

    /**
     * Validate ASIN format.
     *
     * @since    1.0.0
     * @param    string    $asin    ASIN to validate.
     * @return   bool      True if valid ASIN format.
     */
    public static function is_valid_asin($asin) {
        return preg_match('/^[A-Z0-9]{10}$/', $asin);
    }

    /**
     * Extract ASIN from Amazon URL.
     *
     * @since    1.0.0
     * @param    string    $url    Amazon product URL.
     * @return   string|null      ASIN or null if not found.
     */
    public static function extract_asin_from_url($url) {
        // Common Amazon URL patterns
        $patterns = array(
            '/\/dp\/([A-Z0-9]{10})/',           // /dp/ASIN
            '/\/gp\/product\/([A-Z0-9]{10})/', // /gp/product/ASIN
            '/\/exec\/obidos\/ASIN\/([A-Z0-9]{10})/', // /exec/obidos/ASIN/ASIN
            '/asin=([A-Z0-9]{10})/',           // asin=ASIN
            '/product\/([A-Z0-9]{10})/',       // product/ASIN
            '/B([A-Z0-9]{9})/',                // BASIN format
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                $asin = isset($matches[1]) ? $matches[1] : 'B' . $matches[1];
                if (self::is_valid_asin($asin)) {
                    return $asin;
                }
            }
        }

        return null;
    }

    /**
     * Generate Amazon product URL.
     *
     * @since    1.0.0
     * @param    string    $asin         Product ASIN.
     * @param    string    $region       Amazon region.
     * @param    string    $affiliate_tag Affiliate tag (optional).
     * @return   string    Amazon product URL.
     */
    public static function generate_amazon_url($asin, $region = 'US', $affiliate_tag = '') {
        if (!self::is_valid_asin($asin) || !isset(self::$amazon_domains[$region])) {
            return '';
        }

        $domain = self::$amazon_domains[$region];
        $url = "https://www.{$domain}/dp/{$asin}";

        if (!empty($affiliate_tag)) {
            $url .= "?tag={$affiliate_tag}";
        }

        return $url;
    }

    /**
     * Detect Amazon region from URL.
     *
     * @since    1.0.0
     * @param    string    $url    Amazon URL.
     * @return   string    Region code or 'US' as default.
     */
    public static function detect_region_from_url($url) {
        $domain = parse_url($url, PHP_URL_HOST);
        $domain = str_replace('www.', '', $domain);

        foreach (self::$amazon_domains as $region => $amazon_domain) {
            if ($domain === $amazon_domain) {
                return $region;
            }
        }

        return 'US'; // Default fallback
    }

    /**
     * Format price with currency symbol.
     *
     * @since    1.0.0
     * @param    float     $price        Price value.
     * @param    string    $currency     Currency code.
     * @param    int       $decimals     Number of decimal places.
     * @return   string    Formatted price.
     */
    public static function format_price($price, $currency = 'USD', $decimals = 2) {
        if (!is_numeric($price)) {
            return '';
        }

        $symbol = isset(self::$currency_symbols[$currency]) ? 
                 self::$currency_symbols[$currency] : $currency;

        $formatted_price = number_format($price, $decimals, '.', ',');

        // Handle currency symbol positioning
        $symbol_position = self::get_currency_symbol_position($currency);
        
        switch ($symbol_position) {
            case 'before':
                return $symbol . $formatted_price;
            case 'after':
                return $formatted_price . ' ' . $symbol;
            case 'before_with_space':
                return $symbol . ' ' . $formatted_price;
            default:
                return $symbol . $formatted_price;
        }
    }

    /**
     * Get currency symbol position.
     *
     * @since    1.0.0
     * @param    string    $currency    Currency code.
     * @return   string    Position (before, after, before_with_space).
     */
    private static function get_currency_symbol_position($currency) {
        $after_currencies = array('EUR', 'SEK', 'PLN');
        $before_with_space = array('CHF');

        if (in_array($currency, $after_currencies)) {
            return 'after';
        } elseif (in_array($currency, $before_with_space)) {
            return 'before_with_space';
        }

        return 'before';
    }

    /**
     * Convert weight units.
     *
     * @since    1.0.0
     * @param    float     $value       Weight value.
     * @param    string    $from_unit   Source unit.
     * @param    string    $to_unit     Target unit.
     * @return   float     Converted value.
     */
    public static function convert_weight($value, $from_unit, $to_unit) {
        $from_unit = strtolower($from_unit);
        $to_unit = strtolower($to_unit);

        if ($from_unit === $to_unit) {
            return $value;
        }

        $conversion_key = $from_unit . '_to_' . $to_unit;
        
        if (isset(self::$unit_conversions['weight'][$conversion_key])) {
            return $value * self::$unit_conversions['weight'][$conversion_key];
        }

        // Try conversion through kg as intermediate
        $to_kg_key = $from_unit . '_to_kg';
        $from_kg_key = 'kg_to_' . $to_unit;

        if (isset(self::$unit_conversions['weight'][$to_kg_key]) && 
            isset(self::$unit_conversions['weight'][$from_kg_key])) {
            $kg_value = $value * self::$unit_conversions['weight'][$to_kg_key];
            return $kg_value * self::$unit_conversions['weight'][$from_kg_key];
        }

        return $value; // Return original if no conversion available
    }

    /**
     * Convert length units.
     *
     * @since    1.0.0
     * @param    float     $value       Length value.
     * @param    string    $from_unit   Source unit.
     * @param    string    $to_unit     Target unit.
     * @return   float     Converted value.
     */
    public static function convert_length($value, $from_unit, $to_unit) {
        $from_unit = strtolower($from_unit);
        $to_unit = strtolower($to_unit);

        if ($from_unit === $to_unit) {
            return $value;
        }

        $conversion_key = $from_unit . '_to_' . $to_unit;
        
        if (isset(self::$unit_conversions['length'][$conversion_key])) {
            return $value * self::$unit_conversions['length'][$conversion_key];
        }

        // Try conversion through cm as intermediate
        $to_cm_key = $from_unit . '_to_cm';
        $from_cm_key = 'cm_to_' . $to_unit;

        if (isset(self::$unit_conversions['length'][$to_cm_key]) && 
            isset(self::$unit_conversions['length'][$from_cm_key])) {
            $cm_value = $value * self::$unit_conversions['length'][$to_cm_key];
            return $cm_value * self::$unit_conversions['length'][$from_cm_key];
        }

        return $value; // Return original if no conversion available
    }

    /**
     * Sanitize and validate text input.
     *
     * @since    1.0.0
     * @param    string    $text           Text to sanitize.
     * @param    array     $options        Sanitization options.
     * @return   string    Sanitized text.
     */
    public static function sanitize_text($text, $options = array()) {
        $defaults = array(
            'strip_tags' => true,
            'trim' => true,
            'max_length' => null,
            'allow_html' => false,
            'decode_entities' => true,
            'normalize_whitespace' => true
        );

        $options = array_merge($defaults, $options);

        // Decode HTML entities
        if ($options['decode_entities']) {
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        }

        // Strip tags unless HTML is allowed
        if ($options['strip_tags'] && !$options['allow_html']) {
            $text = wp_strip_all_tags($text);
        } elseif ($options['allow_html']) {
            $text = wp_kses_post($text);
        }

        // Normalize whitespace
        if ($options['normalize_whitespace']) {
            $text = preg_replace('/\s+/', ' ', $text);
        }

        // Trim whitespace
        if ($options['trim']) {
            $text = trim($text);
        }

        // Limit length
        if ($options['max_length'] && strlen($text) > $options['max_length']) {
            $text = substr($text, 0, $options['max_length'] - 3) . '...';
        }

        return $text;
    }

    /**
     * Generate unique SKU.
     *
     * @since    1.0.0
     * @param    string    $base_sku    Base SKU.
     * @param    string    $prefix      SKU prefix (optional).
     * @return   string    Unique SKU.
     */
    public static function generate_unique_sku($base_sku, $prefix = '') {
        $sku = $prefix . $base_sku;
        $original_sku = $sku;
        $counter = 1;

        // Check if SKU already exists
        while (self::sku_exists($sku)) {
            $sku = $original_sku . '-' . $counter;
            $counter++;
        }

        return $sku;
    }

    /**
     * Check if SKU exists.
     *
     * @since    1.0.0
     * @param    string    $sku    SKU to check.
     * @return   bool      True if SKU exists.
     */
    public static function sku_exists($sku) {
        global $wpdb;

        $product_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND meta_value=%s LIMIT 1",
                $sku
            )
        );

        return !empty($product_id);
    }

    /**
     * Generate slug from text.
     *
     * @since    1.0.0
     * @param    string    $text         Text to convert.
     * @param    int       $max_length   Maximum slug length.
     * @return   string    Generated slug.
     */
    public static function generate_slug($text, $max_length = 50) {
        $slug = sanitize_title($text);
        
        if (strlen($slug) > $max_length) {
            $slug = substr($slug, 0, $max_length);
            // Ensure we don't cut in the middle of a word
            $last_hyphen = strrpos($slug, '-');
            if ($last_hyphen !== false && $last_hyphen > $max_length * 0.8) {
                $slug = substr($slug, 0, $last_hyphen);
            }
        }

        return $slug;
    }

    /**
     * Extract image dimensions from URL or file.
     *
     * @since    1.0.0
     * @param    string    $image_url    Image URL or file path.
     * @return   array|null              Array with width and height or null.
     */
    public static function get_image_dimensions($image_url) {
        try {
            // Check if it's a local file or URL
            if (filter_var($image_url, FILTER_VALIDATE_URL)) {
                // For URLs, try to get remote image size
                $image_info = @getimagesize($image_url);
            } else {
                // For local files
                $image_info = @getimagesize($image_url);
            }

            if ($image_info && isset($image_info[0]) && isset($image_info[1])) {
                return array(
                    'width' => $image_info[0],
                    'height' => $image_info[1],
                    'type' => $image_info[2],
                    'mime' => isset($image_info['mime']) ? $image_info['mime'] : ''
                );
            }

        } catch (Exception $e) {
            // Silently fail and return null
        }

        return null;
    }

    /**
     * Format file size in human readable format.
     *
     * @since    1.0.0
     * @param    int       $bytes      File size in bytes.
     * @param    int       $precision  Decimal precision.
     * @return   string    Formatted file size.
     */
    public static function format_file_size($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Parse Amazon image URL to get different sizes.
     *
     * @since    1.0.0
     * @param    string    $image_url    Original Amazon image URL.
     * @param    string    $size         Desired size (small, medium, large).
     * @return   string    Modified image URL.
     */
    public static function get_amazon_image_size($image_url, $size = 'large') {
        if (empty($image_url)) {
            return $image_url;
        }

        $size_mappings = array(
            'small' => array('_SL160_', '_SS160_'),
            'medium' => array('_SL300_', '_SS300_'),
            'large' => array('_SL500_', '_SS500_'),
            'xlarge' => array('_SL1000_', '_SS1000_')
        );

        if (!isset($size_mappings[$size])) {
            return $image_url;
        }

        // Try to replace existing size parameters
        $patterns = array(
            '/_S[LS]\d+_/',
            '/_SS\d+_/',
            '/_SX\d+_/',
            '/_SY\d+_/'
        );

        $modified_url = $image_url;
        foreach ($patterns as $pattern) {
            $modified_url = preg_replace($pattern, $size_mappings[$size][0], $modified_url);
            if ($modified_url !== $image_url) {
                return $modified_url;
            }
        }

        // If no existing size parameters, try to add them
        if (strpos($image_url, '._') !== false) {
            $modified_url = preg_replace('/\._([^.]+)\./', '.' . $size_mappings[$size][0] . '$1.', $image_url);
            if ($modified_url !== $image_url) {
                return $modified_url;
            }
        }

        return $image_url;
    }

    /**
     * Calculate percentage change.
     *
     * @since    1.0.0
     * @param    float    $old_value    Old value.
     * @param    float    $new_value    New value.
     * @return   float    Percentage change.
     */
    public static function calculate_percentage_change($old_value, $new_value) {
        if ($old_value == 0) {
            return $new_value > 0 ? 100 : 0;
        }

        return (($new_value - $old_value) / $old_value) * 100;
    }

    /**
     * Check if string contains any of the specified words.
     *
     * @since    1.0.0
     * @param    string    $haystack    String to search in.
     * @param    array     $needles     Words to search for.
     * @param    bool      $case_sensitive Case sensitive search.
     * @return   bool      True if any word is found.
     */
    public static function contains_words($haystack, $needles, $case_sensitive = false) {
        if (!$case_sensitive) {
            $haystack = strtolower($haystack);
            $needles = array_map('strtolower', $needles);
        }

        foreach ($needles as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clean HTML from text while preserving line breaks.
     *
     * @since    1.0.0
     * @param    string    $html    HTML content.
     * @return   string    Cleaned text.
     */
    public static function html_to_text($html) {
        // Convert line breaks
        $text = str_replace(array('<br>', '<br/>', '<br />'), "\n", $html);
        $text = str_replace(array('<p>', '</p>'), array('', "\n\n"), $text);
        
        // Remove all other HTML tags
        $text = wp_strip_all_tags($text);
        
        // Clean up multiple line breaks
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);
        
        return trim($text);
    }

    /**
     * Generate excerpt from text.
     *
     * @since    1.0.0
     * @param    string    $text      Source text.
     * @param    int       $length    Excerpt length in words.
     * @param    string    $more      More text suffix.
     * @return   string    Generated excerpt.
     */
    public static function generate_excerpt($text, $length = 55, $more = '...') {
        $text = wp_strip_all_tags($text);
        $words = explode(' ', $text);

        if (count($words) <= $length) {
            return $text;
        }

        $excerpt = implode(' ', array_slice($words, 0, $length));
        return $excerpt . $more;
    }

    /**
     * Validate email address.
     *
     * @since    1.0.0
     * @param    string    $email    Email to validate.
     * @return   bool      True if valid email.
     */
    public static function is_valid_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate URL.
     *
     * @since    1.0.0
     * @param    string    $url    URL to validate.
     * @return   bool      True if valid URL.
     */
    public static function is_valid_url($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Get file extension from filename or URL.
     *
     * @since    1.0.0
     * @param    string    $filename    Filename or URL.
     * @return   string    File extension (lowercase).
     */
    public static function get_file_extension($filename) {
        return strtolower(pathinfo(parse_url($filename, PHP_URL_PATH), PATHINFO_EXTENSION));
    }

    /**
     * Check if file extension is allowed image type.
     *
     * @since    1.0.0
     * @param    string    $extension    File extension.
     * @return   bool      True if allowed image type.
     */
    public static function is_allowed_image_type($extension) {
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp');
        return in_array(strtolower($extension), $allowed_types);
    }

    /**
     * Generate random string.
     *
     * @since    1.0.0
     * @param    int       $length    String length.
     * @param    string    $chars     Character set to use.
     * @return   string    Random string.
     */
    public static function generate_random_string($length = 10, $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789') {
        $result = '';
        $chars_length = strlen($chars);
        
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[rand(0, $chars_length - 1)];
        }
        
        return $result;
    }

    /**
     * Convert array to CSV string.
     *
     * @since    1.0.0
     * @param    array     $data        Array data.
     * @param    string    $delimiter   CSV delimiter.
     * @param    string    $enclosure   CSV enclosure.
     * @return   string    CSV string.
     */
    public static function array_to_csv($data, $delimiter = ',', $enclosure = '"') {
        $output = fopen('php://temp', 'w');
        
        foreach ($data as $row) {
            fputcsv($output, $row, $delimiter, $enclosure);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    /**
     * Deep merge arrays recursively.
     *
     * @since    1.0.0
     * @param    array    $array1    First array.
     * @param    array    $array2    Second array.
     * @return   array    Merged array.
     */
    public static function array_merge_recursive_distinct($array1, $array2) {
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = self::array_merge_recursive_distinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Get nested array value using dot notation.
     *
     * @since    1.0.0
     * @param    array     $array       Array to search.
     * @param    string    $path        Dot notation path.
     * @param    mixed     $default     Default value.
     * @return   mixed     Found value or default.
     */
    public static function array_get($array, $path, $default = null) {
        $keys = explode('.', $path);
        $value = $array;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Set nested array value using dot notation.
     *
     * @since    1.0.0
     * @param    array     $array    Array to modify.
     * @param    string    $path     Dot notation path.
     * @param    mixed     $value    Value to set.
     * @return   array     Modified array.
     */
    public static function array_set(&$array, $path, $value) {
        $keys = explode('.', $path);
        $current = &$array;

        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = array();
            }
            $current = &$current[$key];
        }

        $current = $value;
        return $array;
    }

    /**
     * Remove empty values from array recursively.
     *
     * @since    1.0.0
     * @param    array    $array    Array to clean.
     * @return   array    Cleaned array.
     */
    public static function array_filter_recursive($array) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::array_filter_recursive($value);
                if (empty($array[$key])) {
                    unset($array[$key]);
                }
            } elseif (empty($value) && $value !== 0 && $value !== '0') {
                unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * Check if current user can manage Amazon imports.
     *
     * @since    1.0.0
     * @return   bool    True if user can manage imports.
     */
    public static function current_user_can_import() {
        return current_user_can('manage_woocommerce') || current_user_can('manage_options');
    }

    /**
     * Get WordPress timezone.
     *
     * @since    1.0.0
     * @return   DateTimeZone    WordPress timezone object.
     */
    public static function get_wp_timezone() {
        $timezone_string = get_option('timezone_string');
        
        if (!empty($timezone_string)) {
            return new DateTimeZone($timezone_string);
        }

        $offset = get_option('gmt_offset');
        $hours = (int) $offset;
        $minutes = abs(($offset - $hours) * 60);
        $offset_string = sprintf('%+03d:%02d', $hours, $minutes);
        
        return new DateTimeZone($offset_string);
    }

    /**
     * Format date according to WordPress settings.
     *
     * @since    1.0.0
     * @param    string    $date      Date string or timestamp.
     * @param    string    $format    Date format (optional).
     * @return   string    Formatted date.
     */
    public static function format_wp_date($date, $format = null) {
        if ($format === null) {
            $format = get_option('date_format') . ' ' . get_option('time_format');
        }

        $timestamp = is_numeric($date) ? $date : strtotime($date);
        return wp_date($format, $timestamp);
    }

    /**
     * Get memory usage in human readable format.
     *
     * @since    1.0.0
     * @param    bool    $peak    Get peak memory usage instead of current.
     * @return   string  Formatted memory usage.
     */
    public static function get_memory_usage($peak = false) {
        $memory = $peak ? memory_get_peak_usage(true) : memory_get_usage(true);
        return self::format_file_size($memory);
    }

    /**
     * Get execution time since start.
     *
     * @since    1.0.0
     * @param    float    $start_time    Start time from microtime(true).
     * @return   string   Formatted execution time.
     */
    public static function get_execution_time($start_time) {
        $execution_time = microtime(true) - $start_time;
        return number_format($execution_time, 3) . 's';
    }

    /**
     * Check if function is available and enabled.
     *
     * @since    1.0.0
     * @param    string    $function_name    Function name to check.
     * @return   bool      True if function is available.
     */
    public static function is_function_available($function_name) {
        return function_exists($function_name) && 
               is_callable($function_name) && 
               !in_array($function_name, array_map('trim', explode(',', ini_get('disable_functions'))));
    }

    /**
     * Get system information for debugging.
     *
     * @since    1.0.0
     * @return   array    System information array.
     */
    public static function get_system_info() {
        global $wp_version;

        return array(
            'wordpress_version' => $wp_version,
            'php_version' => PHP_VERSION,
            'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : 'Not installed',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'allow_url_fopen' => ini_get('allow_url_fopen') ? 'Yes' : 'No',
            'curl_available' => self::is_function_available('curl_init') ? 'Yes' : 'No',
            'gd_available' => extension_loaded('gd') ? 'Yes' : 'No',
            'zip_available' => class_exists('ZipArchive') ? 'Yes' : 'No',
            'mbstring_available' => extension_loaded('mbstring') ? 'Yes' : 'No',
            'current_theme' => get_option('current_theme'),
            'active_plugins' => get_option('active_plugins'),
            'multisite' => is_multisite() ? 'Yes' : 'No',
            'timezone' => get_option('timezone_string'),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
        );
    }

    /**
     * Debug log helper.
     *
     * @since    1.0.0
     * @param    mixed     $data         Data to log.
     * @param    string    $context      Log context.
     * @param    string    $level        Log level.
     */
    public static function debug_log($data, $context = 'general', $level = 'debug') {
        if (!get_option('ams_debug_mode', false)) {
            return;
        }

        $message = is_array($data) || is_object($data) ? print_r($data, true) : $data;
        $log_entry = sprintf('[%s] [%s] [%s] %s', 
            current_time('mysql'), 
            strtoupper($level), 
            $context, 
            $message
        );

        error_log($log_entry);
    }

    /**
     * Get all available Amazon regions.
     *
     * @since    1.0.0
     * @return   array    Array of region codes and names.
     */
    public static function get_amazon_regions() {
        return array(
            'US' => 'United States',
            'CA' => 'Canada',
            'MX' => 'Mexico',
            'UK' => 'United Kingdom',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'NL' => 'Netherlands',
            'AU' => 'Australia',
            'JP' => 'Japan',
            'IN' => 'India',
            'BR' => 'Brazil',
            'SG' => 'Singapore',
            'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia',
            'PL' => 'Poland',
            'TR' => 'Turkey',
            'SE' => 'Sweden'
        );
    }

    /**
     * Check if WooCommerce is active and available.
     *
     * @since    1.0.0
     * @return   bool    True if WooCommerce is available.
     */
    public static function is_woocommerce_available() {
        return class_exists('WooCommerce') && function_exists('WC');
    }

    /**
     * Get WooCommerce product types.
     *
     * @since    1.0.0
     * @return   array    Array of product types.
     */
    public static function get_wc_product_types() {
        if (!self::is_woocommerce_available()) {
            return array();
        }

        return wc_get_product_types();
    }

    /**
     * Truncate text to specified length preserving word boundaries.
     *
     * @since    1.0.0
     * @param    string    $text      Text to truncate.
     * @param    int       $length    Maximum length.
     * @param    string    $suffix    Suffix to append.
     * @return   string    Truncated text.
     */
    public static function truncate_text($text, $length = 100, $suffix = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }

        $text = substr($text, 0, $length);
        $last_space = strrpos($text, ' ');
        
        if ($last_space !== false && $last_space > $length * 0.75) {
            $text = substr($text, 0, $last_space);
        }

        return $text . $suffix;
    }
}