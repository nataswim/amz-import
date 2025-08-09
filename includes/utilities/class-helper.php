<?php

/**
 * Helper utility functions
 */
class Amazon_Product_Importer_Helper {

    /**
     * Get nested array value using dot notation
     */
    public function get_nested_value($array, $path, $default = null) {
        $keys = explode('.', $path);
        $value = $array;

        foreach ($keys as $key) {
            if (!is_array($value) || !isset($value[$key])) {
                return $default;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Sanitize ASIN
     */
    public function sanitize_asin($asin) {
        // Remove any non-alphanumeric characters and ensure length
        $asin = preg_replace('/[^A-Za-z0-9]/', '', $asin);
        
        // ASIN should be 10 characters
        if (strlen($asin) !== 10) {
            return false;
        }

        return strtoupper($asin);
    }

    /**
     * Validate multiple ASINs
     */
    public function validate_asins($asins_string) {
        $asins = array_map('trim', explode(',', $asins_string));
        $valid_asins = array();

        foreach ($asins as $asin) {
            $sanitized_asin = $this->sanitize_asin($asin);
            if ($sanitized_asin) {
                $valid_asins[] = $sanitized_asin;
            }
        }

        return array_unique($valid_asins);
    }

    /**
     * Format price for display
     */
    public function format_price($amount, $currency = 'USD') {
        $formatted_amount = number_format($amount / 100, 2);
        
        $currency_symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'C$',
            'JPY' => '¥'
        );

        $symbol = isset($currency_symbols[$currency]) ? $currency_symbols[$currency] : $currency . ' ';

        return $symbol . $formatted_amount;
    }

    /**
     * Clean HTML content
     */
    public function clean_html($content) {
        // Remove script and style tags
        $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi', '', $content);
        $content = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi', '', $content);
        
        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        // Allow only safe HTML tags
        $allowed_tags = '<p><br><strong><b><em><i><ul><ol><li><a><h1><h2><h3><h4><h5><h6>';
        $content = strip_tags($content, $allowed_tags);

        return $content;
    }

    /**
     * Truncate text with word boundary
     */
    public function truncate_text($text, $length = 100, $suffix = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }

        $truncated = substr($text, 0, $length);
        $last_space = strrpos($truncated, ' ');

        if ($last_space !== false) {
            $truncated = substr($truncated, 0, $last_space);
        }

        return $truncated . $suffix;
    }

    /**
     * Generate unique filename
     */
    public function generate_unique_filename($filename, $directory = '') {
        $pathinfo = pathinfo($filename);
        $name = $pathinfo['filename'];
        $extension = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';
        
        $counter = 1;
        $new_filename = $filename;

        while (file_exists($directory . $new_filename)) {
            $new_filename = $name . '-' . $counter . $extension;
            $counter++;
        }

        return $new_filename;
    }

    /**
     * Convert bytes to human readable format
     */
    public function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Check if URL is valid image
     */
    public function is_valid_image_url($url) {
        $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        $path = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, $image_extensions);
    }

    /**
     * Get domain from URL
     */
    public function get_domain_from_url($url) {
        $parsed_url = parse_url($url);
        return isset($parsed_url['host']) ? $parsed_url['host'] : '';
    }

    /**
     * Check if string contains keywords
     */
    public function contains_keywords($text, $keywords) {
        $text = strtolower($text);
        
        if (is_string($keywords)) {
            $keywords = array($keywords);
        }

        foreach ($keywords as $keyword) {
            if (strpos($text, strtolower($keyword)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate similarity between strings
     */
    public function string_similarity($str1, $str2) {
        $len1 = strlen($str1);
        $len2 = strlen($str2);

        if ($len1 === 0) return $len2;
        if ($len2 === 0) return $len1;

        $matrix = array();

        for ($i = 0; $i <= $len1; $i++) {
            $matrix[$i][0] = $i;
        }

        for ($j = 0; $j <= $len2; $j++) {
            $matrix[0][$j] = $j;
        }

        for ($i = 1; $i <= $len1; $i++) {
            for ($j = 1; $j <= $len2; $j++) {
                $cost = ($str1[$i - 1] === $str2[$j - 1]) ? 0 : 1;
                
                $matrix[$i][$j] = min(
                    $matrix[$i - 1][$j] + 1,
                    $matrix[$i][$j - 1] + 1,
                    $matrix[$i - 1][$j - 1] + $cost
                );
            }
        }

        $max_len = max($len1, $len2);
        return ($max_len - $matrix[$len1][$len2]) / $max_len;
    }

    /**
     * Generate random string
     */
    public function generate_random_string($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $random_string = '';

        for ($i = 0; $i < $length; $i++) {
            $random_string .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $random_string;
    }

    /**
     * Check if running in WordPress admin
     */
    public function is_admin_context() {
        return is_admin() && !wp_doing_ajax() && !wp_doing_cron();
    }

    /**
     * Check if WooCommerce is active
     */
    public function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Get WooCommerce version
     */
    public function get_woocommerce_version() {
        if ($this->is_woocommerce_active()) {
            return WC()->version;
        }
        return null;
    }

    /**
     * Log memory usage
     */
    public function log_memory_usage($label = '') {
        $memory_usage = memory_get_usage(true);
        $peak_usage = memory_get_peak_usage(true);
        
        error_log(sprintf(
            '[Amazon Importer] Memory Usage %s: Current=%s, Peak=%s',
            $label,
            $this->format_bytes($memory_usage),
            $this->format_bytes($peak_usage)
        ));
    }

    /**
     * Check if function is available
     */
    public function is_function_available($function_name) {
        return function_exists($function_name) && !in_array($function_name, $this->get_disabled_functions());
    }

    /**
     * Get disabled PHP functions
     */
    private function get_disabled_functions() {
        $disabled = ini_get('disable_functions');
        return $disabled ? array_map('trim', explode(',', $disabled)) : array();
    }

    /**
     * Convert array to CSV string
     */
    public function array_to_csv($array, $delimiter = ',', $enclosure = '"') {
        $output = fopen('php://temp', 'r+');
        
        foreach ($array as $row) {
            fputcsv($output, $row, $delimiter, $enclosure);
        }
        
        rewind($output);
        $csv_string = stream_get_contents($output);
        fclose($output);
        
        return $csv_string;
    }

    /**
     * Parse CSV string to array
     */
    public function csv_to_array($csv_string, $delimiter = ',', $enclosure = '"') {
        $lines = explode("\n", $csv_string);
        $array = array();
        
        foreach ($lines as $line) {
            if (trim($line)) {
                $array[] = str_getcsv($line, $delimiter, $enclosure);
            }
        }
        
        return $array;
    }
}