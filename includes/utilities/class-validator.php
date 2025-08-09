<?php

/**
 * The validation functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/utilities
 */

/**
 * The validation functionality of the plugin.
 *
 * Provides comprehensive validation capabilities for all plugin data.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/includes/utilities
 * @author     Your Name <email@example.com>
 */
class Amazon_Product_Importer_Validator {

    /**
     * Validation errors.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $errors    Array of validation errors.
     */
    private $errors = array();

    /**
     * Validation warnings.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $warnings    Array of validation warnings.
     */
    private $warnings = array();

    /**
     * Validation rules.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $rules    Validation rules configuration.
     */
    private $rules = array();

    /**
     * Custom validation messages.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $messages    Custom validation messages.
     */
    private $messages = array();

    /**
     * Amazon marketplace configurations.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $amazon_config    Amazon marketplace configurations.
     */
    private $amazon_config = array(
        'regions' => array('US', 'CA', 'MX', 'UK', 'DE', 'FR', 'IT', 'ES', 'NL', 'AU', 'JP', 'IN', 'BR', 'SG', 'AE', 'SA', 'PL', 'TR', 'SE'),
        'currencies' => array('USD', 'CAD', 'MXN', 'GBP', 'EUR', 'AUD', 'JPY', 'INR', 'BRL', 'SGD', 'AED', 'SAR', 'PLN', 'TRY', 'SEK'),
        'max_variations' => 100,
        'max_images' => 20,
        'max_categories' => 10
    );

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->setup_default_rules();
        $this->setup_default_messages();
    }

    /**
     * Setup default validation rules.
     *
     * @since    1.0.0
     */
    private function setup_default_rules() {
        $this->rules = array(
            'asin' => array(
                'required' => true,
                'pattern' => '/^[A-Z0-9]{10}$/',
                'length' => 10
            ),
            'email' => array(
                'pattern' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'
            ),
            'url' => array(
                'pattern' => '/^https?:\/\/.+/'
            ),
            'amazon_url' => array(
                'pattern' => '/amazon\.(com|ca|co\.uk|de|fr|it|es|com\.au|co\.jp|in|com\.br|sg|ae|sa|pl|com\.tr|se)/'
            ),
            'price' => array(
                'type' => 'numeric',
                'min' => 0,
                'max' => 999999.99
            ),
            'sku' => array(
                'max_length' => 100,
                'pattern' => '/^[a-zA-Z0-9\-_]+$/'
            ),
            'product_title' => array(
                'required' => true,
                'min_length' => 3,
                'max_length' => 200
            ),
            'description' => array(
                'max_length' => 5000
            ),
            'short_description' => array(
                'max_length' => 300
            ),
            'weight' => array(
                'type' => 'numeric',
                'min' => 0,
                'max' => 10000
            ),
            'dimension' => array(
                'type' => 'numeric',
                'min' => 0,
                'max' => 10000
            )
        );
    }

    /**
     * Setup default validation messages.
     *
     * @since    1.0.0
     */
    private function setup_default_messages() {
        $this->messages = array(
            'required' => 'The {field} field is required.',
            'pattern' => 'The {field} field format is invalid.',
            'length' => 'The {field} field must be exactly {length} characters.',
            'min_length' => 'The {field} field must be at least {min_length} characters.',
            'max_length' => 'The {field} field must not exceed {max_length} characters.',
            'type' => 'The {field} field must be of type {type}.',
            'min' => 'The {field} field must be at least {min}.',
            'max' => 'The {field} field must not exceed {max}.',
            'in' => 'The {field} field must be one of: {values}.',
            'email' => 'The {field} field must be a valid email address.',
            'url' => 'The {field} field must be a valid URL.',
            'unique' => 'The {field} field must be unique.',
            'numeric' => 'The {field} field must be numeric.',
            'integer' => 'The {field} field must be an integer.',
            'boolean' => 'The {field} field must be true or false.',
            'array' => 'The {field} field must be an array.',
            'image' => 'The {field} field must be a valid image.',
            'file_size' => 'The {field} file size must not exceed {max_size}.',
            'file_type' => 'The {field} file type is not allowed.',
            'date' => 'The {field} field must be a valid date.',
            'asin' => 'The {field} field must be a valid Amazon ASIN.',
            'amazon_region' => 'The {field} field must be a valid Amazon region.',
            'currency' => 'The {field} field must be a valid currency code.'
        );
    }

    /**
     * Validate data against rules.
     *
     * @since    1.0.0
     * @param    array    $data     Data to validate.
     * @param    array    $rules    Validation rules.
     * @return   bool     True if validation passes.
     */
    public function validate($data, $rules = array()) {
        $this->clear_errors();
        $this->clear_warnings();

        foreach ($rules as $field => $field_rules) {
            $value = isset($data[$field]) ? $data[$field] : null;
            $this->validate_field($field, $value, $field_rules);
        }

        return empty($this->errors);
    }

    /**
     * Validate single field.
     *
     * @since    1.0.0
     * @param    string    $field       Field name.
     * @param    mixed     $value       Field value.
     * @param    array     $rules       Field validation rules.
     * @return   bool      True if field is valid.
     */
    public function validate_field($field, $value, $rules) {
        $is_valid = true;

        // Handle string rules (e.g., "required|email|max:255")
        if (is_string($rules)) {
            $rules = $this->parse_string_rules($rules);
        }

        foreach ($rules as $rule => $parameters) {
            if (is_numeric($rule)) {
                $rule = $parameters;
                $parameters = array();
            }

            $method = 'validate_' . $rule;
            
            if (method_exists($this, $method)) {
                if (!$this->$method($field, $value, $parameters)) {
                    $is_valid = false;
                }
            } else {
                $this->add_error($field, "Unknown validation rule: {$rule}");
                $is_valid = false;
            }
        }

        return $is_valid;
    }

    /**
     * Validate ASIN format.
     *
     * @since    1.0.0
     * @param    string    $asin    ASIN to validate.
     * @return   bool      True if valid ASIN.
     */
    public function validate_asin($asin) {
        if (empty($asin)) {
            $this->add_error('asin', 'ASIN is required');
            return false;
        }

        if (!preg_match('/^[A-Z0-9]{10}$/', $asin)) {
            $this->add_error('asin', 'Invalid ASIN format. Must be 10 alphanumeric characters.');
            return false;
        }

        return true;
    }

    /**
     * Validate Amazon URL.
     *
     * @since    1.0.0
     * @param    string    $url    Amazon URL to validate.
     * @return   bool      True if valid Amazon URL.
     */
    public function validate_amazon_url($url) {
        if (empty($url)) {
            return true; // Optional field
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->add_error('amazon_url', 'Invalid URL format');
            return false;
        }

        $amazon_domains = array(
            'amazon.com', 'amazon.ca', 'amazon.com.mx', 'amazon.co.uk',
            'amazon.de', 'amazon.fr', 'amazon.it', 'amazon.es',
            'amazon.nl', 'amazon.com.au', 'amazon.co.jp', 'amazon.in',
            'amazon.com.br', 'amazon.sg', 'amazon.ae', 'amazon.sa',
            'amazon.pl', 'amazon.com.tr', 'amazon.se'
        );

        $domain = parse_url($url, PHP_URL_HOST);
        $domain = str_replace('www.', '', $domain);

        if (!in_array($domain, $amazon_domains)) {
            $this->add_error('amazon_url', 'URL must be from a valid Amazon domain');
            return false;
        }

        return true;
    }

    /**
     * Validate Amazon region.
     *
     * @since    1.0.0
     * @param    string    $region    Region code to validate.
     * @return   bool      True if valid region.
     */
    public function validate_amazon_region($region) {
        if (empty($region)) {
            $this->add_error('region', 'Amazon region is required');
            return false;
        }

        if (!in_array($region, $this->amazon_config['regions'])) {
            $this->add_error('region', 'Invalid Amazon region: ' . $region);
            return false;
        }

        return true;
    }

    /**
     * Validate currency code.
     *
     * @since    1.0.0
     * @param    string    $currency    Currency code to validate.
     * @return   bool      True if valid currency.
     */
    public function validate_currency($currency) {
        if (empty($currency)) {
            return true; // Optional field
        }

        if (!in_array($currency, $this->amazon_config['currencies'])) {
            $this->add_error('currency', 'Invalid currency code: ' . $currency);
            return false;
        }

        return true;
    }

    /**
     * Validate product data structure.
     *
     * @since    1.0.0
     * @param    array    $product_data    Product data to validate.
     * @return   bool     True if valid product data.
     */
    public function validate_product_data($product_data) {
        $rules = array(
            'asin' => 'required|asin',
            'title' => 'required|min_length:3|max_length:200',
            'description' => 'max_length:5000',
            'short_description' => 'max_length:300',
            'price' => 'numeric|min:0',
            'regular_price' => 'numeric|min:0',
            'sale_price' => 'numeric|min:0',
            'sku' => 'max_length:100',
            'weight' => 'numeric|min:0',
            'length' => 'numeric|min:0',
            'width' => 'numeric|min:0',
            'height' => 'numeric|min:0',
            'stock_status' => 'in:instock,outofstock,onbackorder'
        );

        return $this->validate($product_data, $rules);
    }

    /**
     * Validate import settings.
     *
     * @since    1.0.0
     * @param    array    $settings    Import settings to validate.
     * @return   bool     True if valid settings.
     */
    public function validate_import_settings($settings) {
        $rules = array(
            'import_mode' => 'required|in:create_only,update_only,create_and_update',
            'duplicate_handling' => 'required|in:skip,update,create_new',
            'batch_size' => 'integer|min:1|max:100',
            'delay_between_imports' => 'integer|min:0|max:60',
            'max_retries' => 'integer|min:0|max:10',
            'import_images' => 'boolean',
            'import_categories' => 'boolean',
            'import_variations' => 'boolean',
            'auto_publish' => 'boolean'
        );

        return $this->validate($settings, $rules);
    }

    /**
     * Validate API credentials.
     *
     * @since    1.0.0
     * @param    array    $credentials    API credentials to validate.
     * @return   bool     True if valid credentials.
     */
    public function validate_api_credentials($credentials) {
        $rules = array(
            'access_key_id' => 'required|min_length:16|max_length:128',
            'secret_access_key' => 'required|min_length:32|max_length:128',
            'affiliate_tag' => 'max_length:50|pattern:/^[a-zA-Z0-9\-]+$/',
            'region' => 'required|amazon_region'
        );

        return $this->validate($credentials, $rules);
    }

    /**
     * Validate image data.
     *
     * @since    1.0.0
     * @param    array    $image_data    Image data to validate.
     * @return   bool     True if valid image data.
     */
    public function validate_image_data($image_data) {
        if (empty($image_data)) {
            return true; // Images are optional
        }

        foreach ($image_data as $index => $image) {
            if (!$this->validate_single_image($image, "image_{$index}")) {
                return false;
            }
        }

        if (count($image_data) > $this->amazon_config['max_images']) {
            $this->add_error('images', 'Too many images. Maximum allowed: ' . $this->amazon_config['max_images']);
            return false;
        }

        return true;
    }

    /**
     * Validate single image.
     *
     * @since    1.0.0
     * @param    array     $image    Image data.
     * @param    string    $field    Field name for error messages.
     * @return   bool      True if valid image.
     */
    private function validate_single_image($image, $field) {
        if (!isset($image['url']) || empty($image['url'])) {
            $this->add_error($field, 'Image URL is required');
            return false;
        }

        if (!filter_var($image['url'], FILTER_VALIDATE_URL)) {
            $this->add_error($field, 'Invalid image URL format');
            return false;
        }

        // Validate image file extension
        $extension = strtolower(pathinfo(parse_url($image['url'], PHP_URL_PATH), PATHINFO_EXTENSION));
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        
        if (!in_array($extension, $allowed_extensions)) {
            $this->add_warning($field, 'Image file extension may not be supported: ' . $extension);
        }

        return true;
    }

    /**
     * Validate variation data.
     *
     * @since    1.0.0
     * @param    array    $variations    Variation data to validate.
     * @return   bool     True if valid variations.
     */
    public function validate_variations($variations) {
        if (empty($variations)) {
            return true; // Variations are optional
        }

        if (count($variations) > $this->amazon_config['max_variations']) {
            $this->add_error('variations', 'Too many variations. Maximum allowed: ' . $this->amazon_config['max_variations']);
            return false;
        }

        foreach ($variations as $index => $variation) {
            if (!$this->validate_single_variation($variation, "variation_{$index}")) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate single variation.
     *
     * @since    1.0.0
     * @param    array     $variation    Variation data.
     * @param    string    $field        Field name for error messages.
     * @return   bool      True if valid variation.
     */
    private function validate_single_variation($variation, $field) {
        $rules = array(
            'asin' => 'required|asin',
            'attributes' => 'required|array',
            'price' => 'numeric|min:0',
            'regular_price' => 'numeric|min:0',
            'sale_price' => 'numeric|min:0'
        );

        $is_valid = true;

        foreach ($rules as $rule_field => $rule) {
            $value = isset($variation[$rule_field]) ? $variation[$rule_field] : null;
            if (!$this->validate_field("{$field}_{$rule_field}", $value, $rule)) {
                $is_valid = false;
            }
        }

        // Validate attributes
        if (isset($variation['attributes']) && is_array($variation['attributes'])) {
            if (empty($variation['attributes'])) {
                $this->add_error("{$field}_attributes", 'Variation must have at least one attribute');
                $is_valid = false;
            }

            foreach ($variation['attributes'] as $attr_name => $attr_value) {
                if (empty($attr_name) || empty($attr_value)) {
                    $this->add_error("{$field}_attributes", 'Attribute name and value cannot be empty');
                    $is_valid = false;
                }
            }
        }

        return $is_valid;
    }

    /**
     * Validate file upload.
     *
     * @since    1.0.0
     * @param    array    $file          File data from $_FILES.
     * @param    array    $constraints   File constraints.
     * @return   bool     True if valid file.
     */
    public function validate_file_upload($file, $constraints = array()) {
        $defaults = array(
            'max_size' => 5 * 1024 * 1024, // 5MB
            'allowed_types' => array('csv', 'txt', 'json'),
            'required' => true
        );

        $constraints = array_merge($defaults, $constraints);

        if ($constraints['required'] && (!isset($file['tmp_name']) || empty($file['tmp_name']))) {
            $this->add_error('file', 'File is required');
            return false;
        }

        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            $this->add_error('file', 'File upload error: ' . $this->get_upload_error_message($file['error']));
            return false;
        }

        if (isset($file['size']) && $file['size'] > $constraints['max_size']) {
            $this->add_error('file', 'File size exceeds maximum allowed size');
            return false;
        }

        if (isset($file['name'])) {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $constraints['allowed_types'])) {
                $this->add_error('file', 'File type not allowed. Allowed types: ' . implode(', ', $constraints['allowed_types']));
                return false;
            }
        }

        return true;
    }

    /**
     * Validate search parameters.
     *
     * @since    1.0.0
     * @param    array    $params    Search parameters.
     * @return   bool     True if valid parameters.
     */
    public function validate_search_params($params) {
        $rules = array(
            'keywords' => 'required|min_length:2|max_length:200',
            'search_index' => 'in:All,Books,Electronics,Fashion,Home,Music,Software,Toys',
            'sort_by' => 'in:Relevance,Price:LowToHigh,Price:HighToLow,AvgCustomerReviews,NewestArrivals',
            'min_price' => 'numeric|min:0',
            'max_price' => 'numeric|min:0',
            'max_results' => 'integer|min:1|max:50',
            'region' => 'amazon_region'
        );

        $is_valid = $this->validate($params, $rules);

        // Additional validation: max_price should be greater than min_price
        if (isset($params['min_price']) && isset($params['max_price'])) {
            if ($params['max_price'] <= $params['min_price']) {
                $this->add_error('max_price', 'Maximum price must be greater than minimum price');
                $is_valid = false;
            }
        }

        return $is_valid;
    }

    /**
     * Validate email configuration.
     *
     * @since    1.0.0
     * @param    array    $config    Email configuration.
     * @return   bool     True if valid configuration.
     */
    public function validate_email_config($config) {
        $rules = array(
            'smtp_host' => 'required',
            'smtp_port' => 'required|integer|min:1|max:65535',
            'smtp_username' => 'required|email',
            'smtp_password' => 'required',
            'from_email' => 'required|email',
            'from_name' => 'required|min_length:2|max_length:100',
            'encryption' => 'in:none,ssl,tls'
        );

        return $this->validate($config, $rules);
    }

    // ===============================
    // INDIVIDUAL VALIDATION METHODS
    // ===============================

    /**
     * Validate required field.
     *
     * @since    1.0.0
     * @param    string    $field        Field name.
     * @param    mixed     $value        Field value.
     * @param    array     $parameters   Rule parameters.
     * @return   bool      True if valid.
     */
    protected function validate_required($field, $value, $parameters = array()) {
        if (is_null($value) || $value === '' || (is_array($value) && empty($value))) {
            $this->add_error($field, $this->get_message('required', $field, $parameters));
            return false;
        }
        return true;
    }

    /**
     * Validate pattern matching.
     *
     * @since    1.0.0
     * @param    string    $field        Field name.
     * @param    mixed     $value        Field value.
     * @param    array     $parameters   Rule parameters.
     * @return   bool      True if valid.
     */
    protected function validate_pattern($field, $value, $parameters = array()) {
        if (is_null($value) || $value === '') {
            return true; // Skip validation for empty values
        }

        $pattern = is_array($parameters) ? $parameters[0] : $parameters;
        
        if (!preg_match($pattern, $value)) {
            $this->add_error($field, $this->get_message('pattern', $field, $parameters));
            return false;
        }

        return true;
    }

    /**
     * Validate minimum length.
     *
     * @since    1.0.0
     * @param    string    $field        Field name.
     * @param    mixed     $value        Field value.
     * @param    array     $parameters   Rule parameters.
     * @return   bool      True if valid.
     */
    protected function validate_min_length($field, $value, $parameters = array()) {
        if (is_null($value) || $value === '') {
            return true;
        }

        $min_length = is_array($parameters) ? $parameters[0] : $parameters;
        
        if (strlen($value) < $min_length) {
            $this->add_error($field, $this->get_message('min_length', $field, array('min_length' => $min_length)));
            return false;
        }

        return true;
    }

    /**
     * Validate maximum length.
     *
     * @since    1.0.0
     * @param    string    $field        Field name.
     * @param    mixed     $value        Field value.
     * @param    array     $parameters   Rule parameters.
     * @return   bool      True if valid.
     */
    protected function validate_max_length($field, $value, $parameters = array()) {
        if (is_null($value) || $value === '') {
            return true;
        }

        $max_length = is_array($parameters) ? $parameters[0] : $parameters;
        
        if (strlen($value) > $max_length) {
            $this->add_error($field, $this->get_message('max_length', $field, array('max_length' => $max_length)));
            return false;
        }

        return true;
    }

    /**
     * Validate exact length.
     *
     * @since    1.0.0
     * @param    string    $field        Field name.
     * @param    mixed     $value        Field value.
     * @param    array     $parameters   Rule parameters.
     * @return   bool      True if valid.
     */
    protected function validate_length($field, $value, $parameters = array()) {
        if (is_null($value) || $value === '') {
            return true;
        }

        $length = is_array($parameters) ? $parameters[0] : $parameters;
        
        if (strlen($value) !== (int)$length) {
            $this->add_error($field, $this->get_message('length', $field, array('length' => $length)));
            return false;
        }

        return true;
    }

    /**
     * Validate numeric value.
     *
     * @since    1.0.0
     * @param    string    $field        Field name.
     * @param    mixed     $value        Field value.
     * @param    array     $parameters   Rule parameters.
     * @return   bool      True if valid.
     */
    protected function validate_numeric($field, $value, $parameters = array()) {
        if (is_null($value) || $value === '') {
            return true;
        }

        if (!is_numeric($value)) {
            $this->add_error($field, $this->get_message('numeric', $field, $parameters));
            return false;
        }

        return true;
    }

    /**
     * Validate integer value.
     *
     * @since    1.0.0
     * @param    string    $field        Field name.
     * @param    mixed     $value        Field value.
     * @param    array     $parameters   Rule parameters.
     * @return   bool      True if valid.
     */
    protected function validate_integer($field, $value, $parameters = array()) {
        if (is_null($value) || $value === '') {
            return true;
        }

        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            $this->add_error($field, $this->get_message('integer', $field, $parameters));
            return false;
        }

        return true;
    }

    /**
     * Validate boolean value.
     *
     * @since    1.0.0
     * @param    string    $field        Field name.
     * @param    mixed     $value        Field value.
     * @param    array     $parameters   Rule parameters.
     * @return   bool      True if valid.
     */
    protected function validate_boolean($field, $value, $parameters = array()) {
        if (is_null($value) || $value === '') {
            return true;
        }

        if (!is_bool($value) && !in_array($value, array('0', '1', 'true', 'false', 0, 1, true, false), true)) {
            $this->add_error($field, $this->get_message('boolean', $field, $parameters));
            return false;
        }

        return true;
    }

    /**
     * Validate array value.
     *
     * @since    1.0.0
     * @param    string    $field        Field name.
     * @param    mixed     $value        Field value.
     * @param    array     $parameters   Rule parameters.
     * @return   bool      True if valid.
     */
    protected function validate_array($field, $value, $parameters = array()) {
        if (is_null($value)) {
            return true;
        }

        if (!is_array($value)) {
            $this->add_error($field, $this->get_message('array', $field, $parameters));
            return false;
        }

        return true;
    }

    /**
     * Validate minimum value.
     *
     * @since    1.0.0
     * @param    string    $field        Field name.
     * @param    mixed     $value        Field value.
     * @param    array     $parameters   Rule parameters.
     * @return   bool      True if valid.
     */
    protected function validate_min($field, $value, $parameters = array()) {
        if (is_null($value) || $value === '') {
            return true;
        }

        $min = is_array($parameters) ? $parameters[0] : $parameters;
        
        if (is_numeric($value) && $value < $min) {
            $this->add_error($field, $this->get_message('min', $field, array('min' => $min)));
            return false;
        }

        return true;
    }

    /**
     * Validate maximum value.
     *
     * @since    1.0.0
     * @param    string    $field        Field name.
     * @param    mixed     $value        Field value.
     * @param    array     $parameters   Rule parameters.
     * @return   bool      True if valid.
     */
    protected function validate_max($field, $value, $parameters = array()) {
        if (is_null($value) || $value === '') {
            return true;
        }

        $max = is_array($parameters) ? $parameters[0] : $parameters;
        
        if (is_numeric($value) && $value > $max) {
            $this->add_error($field, $this->get_message('max', $field, array('max' => $max)));
            return false;
        }

        return true;
    }

    /**
     * Validate value is in list.
     *
     * @since    1.0.0
     * @param    string    $field        Field name.
     * @param    mixed     $value        Field value.
     * @param    array     $parameters   Rule parameters.
     * @return   bool      True if valid.
     */
    protected function validate_in($field, $value, $parameters = array()) {
        if (is_null($value) || $value === '') {
            return true;
        }

        $allowed_values = is_array($parameters) ? $parameters : explode(',', $parameters);
        
        if (!in_array($value, $allowed_values)) {
            $this->add_error($field, $this->get_message('in', $field, array('values' => implode(', ', $allowed_values))));
            return false;
        }

        return true;
    }

    /**
     * Validate email format.
     *
     * @since    1.0.0
     * @param    string    $field        Field name.
     * @param    mixed     $value        Field value.
     * @param    array     $parameters   Rule parameters.
     * @return   bool      True if valid.
     */
    protected function validate_email($field, $value, $parameters = array()) {
        if (is_null($value) || $value === '') {
            return true;
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->add_error($field, $this->get_message('email', $field, $parameters));
            return false;
        }

        return true;
    }

    /**
     * Validate URL format.
     *
     * @since    1.0.0
     * @param    string    $field        Field name.
     * @param    mixed     $value        Field value.
     * @param    array     $parameters   Rule parameters.
     * @return   bool      True if valid.
     */
    protected function validate_url($field, $value, $parameters = array()) {
        if (is_null($value) || $value === '') {
            return true;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->add_error($field, $this->get_message('url', $field, $parameters));
            return false;
        }

        return true;
    }

    /**
     * Parse string rules into array format.
     *
     * @since    1.0.0
     * @param    string    $rules    String rules.
     * @return   array     Parsed rules.
     */
    private function parse_string_rules($rules) {
        $parsed = array();
        $rules_array = explode('|', $rules);

        foreach ($rules_array as $rule) {
            if (strpos($rule, ':') !== false) {
                list($rule_name, $parameters) = explode(':', $rule, 2);
                $parsed[$rule_name] = explode(',', $parameters);
            } else {
                $parsed[$rule] = array();
            }
        }

        return $parsed;
    }

    /**
     * Get validation message.
     *
     * @since    1.0.0
     * @param    string    $rule         Validation rule.
     * @param    string    $field        Field name.
     * @param    array     $parameters   Rule parameters.
     * @return   string    Validation message.
     */
    private function get_message($rule, $field, $parameters = array()) {
        $message = isset($this->messages[$rule]) ? $this->messages[$rule] : "The {field} field is invalid.";
        
        $replacements = array_merge(array('field' => $field), $parameters);
        
        foreach ($replacements as $key => $value) {
            $message = str_replace('{' . $key . '}', $value, $message);
        }

        return $message;
    }

    /**
     * Get file upload error message.
     *
     * @since    1.0.0
     * @param    int    $error_code    PHP upload error code.
     * @return   string Upload error message.
     */
    private function get_upload_error_message($error_code) {
        $errors = array(
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        );

        return isset($errors[$error_code]) ? $errors[$error_code] : 'Unknown upload error';
    }

    /**
     * Add validation error.
     *
     * @since    1.0.0
     * @param    string    $field      Field name.
     * @param    string    $message    Error message.
     */
    public function add_error($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = array();
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Add validation warning.
     *
     * @since    1.0.0
     * @param    string    $field      Field name.
     * @param    string    $message    Warning message.
     */
    public function add_warning($field, $message) {
        if (!isset($this->warnings[$field])) {
            $this->warnings[$field] = array();
        }
        $this->warnings[$field][] = $message;
    }

    /**
     * Get validation errors.
     *
     * @since    1.0.0
     * @return   array    Validation errors.
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Get validation warnings.
     *
     * @since    1.0.0
     * @return   array    Validation warnings.
     */
    public function get_warnings() {
        return $this->warnings;
    }

    /**
     * Check if there are validation errors.
     *
     * @since    1.0.0
     * @return   bool    True if there are errors.
     */
    public function has_errors() {
        return !empty($this->errors);
    }

    /**
     * Check if there are validation warnings.
     *
     * @since    1.0.0
     * @return   bool    True if there are warnings.
     */
    public function has_warnings() {
        return !empty($this->warnings);
    }

    /**
     * Clear validation errors.
     *
     * @since    1.0.0
     */
    public function clear_errors() {
        $this->errors = array();
    }

    /**
     * Clear validation warnings.
     *
     * @since    1.0.0
     */
    public function clear_warnings() {
        $this->warnings = array();
    }

    /**
     * Get first error for a field.
     *
     * @since    1.0.0
     * @param    string    $field    Field name.
     * @return   string|null        First error message or null.
     */
    public function get_first_error($field) {
        return isset($this->errors[$field]) ? $this->errors[$field][0] : null;
    }

    /**
     * Get all error messages as flat array.
     *
     * @since    1.0.0
     * @return   array    Flat array of error messages.
     */
    public function get_error_messages() {
        $messages = array();
        foreach ($this->errors as $field_errors) {
            $messages = array_merge($messages, $field_errors);
        }
        return $messages;
    }

    /**
     * Get all warning messages as flat array.
     *
     * @since    1.0.0
     * @return   array    Flat array of warning messages.
     */
    public function get_warning_messages() {
        $messages = array();
        foreach ($this->warnings as $field_warnings) {
            $messages = array_merge($messages, $field_warnings);
        }
        return $messages;
    }

    /**
     * Set custom validation message.
     *
     * @since    1.0.0
     * @param    string    $rule       Validation rule.
     * @param    string    $message    Custom message.
     */
    public function set_message($rule, $message) {
        $this->messages[$rule] = $message;
    }

    /**
     * Set multiple custom validation messages.
     *
     * @since    1.0.0
     * @param    array    $messages    Array of rule => message pairs.
     */
    public function set_messages($messages) {
        $this->messages = array_merge($this->messages, $messages);
    }

    /**
     * Add custom validation rule.
     *
     * @since    1.0.0
     * @param    string    $rule       Rule name.
     * @param    callable  $callback   Validation callback.
     * @param    string    $message    Error message template.
     */
    public function add_rule($rule, $callback, $message = '') {
        if (!method_exists($this, "validate_{$rule}")) {
            $method_name = "validate_{$rule}";
            $this->$method_name = $callback;
        }

        if (!empty($message)) {
            $this->messages[$rule] = $message;
        }
    }

    /**
     * Check if validation rule exists.
     *
     * @since    1.0.0
     * @param    string    $rule    Rule name.
     * @return   bool      True if rule exists.
     */
    public function rule_exists($rule) {
        return method_exists($this, "validate_{$rule}");
    }

    /**
     * Get validation summary.
     *
     * @since    1.0.0
     * @return   array    Validation summary.
     */
    public function get_summary() {
        return array(
            'valid' => !$this->has_errors(),
            'error_count' => count($this->get_error_messages()),
            'warning_count' => count($this->get_warning_messages()),
            'errors' => $this->errors,
            'warnings' => $this->warnings
        );
    }
}