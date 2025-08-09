<?php
/**
 * Default Settings Configuration
 *
 * This file contains all default values for plugin settings.
 * These values are used when the plugin is first activated
 * or when settings are reset to defaults.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/config
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Default Settings Configuration
 */
return array(

    /**
     * Amazon API Settings
     * Default configuration for Amazon Product Advertising API
     */
    'api_settings' => array(
        'amazon_product_importer_access_key_id' => '',
        'amazon_product_importer_secret_access_key' => '',
        'amazon_product_importer_associate_tag' => '',
        'amazon_product_importer_marketplace' => 'www.amazon.com',
        'amazon_product_importer_region' => 'us-east-1',
        'amazon_product_importer_api_version' => '2020-10-01',
        'amazon_product_importer_service_name' => 'ProductAdvertisingAPI',
        'amazon_product_importer_partner_type' => 'Associates',
        'amazon_product_importer_api_timeout' => 30,
        'amazon_product_importer_api_retries' => 3,
        'amazon_product_importer_api_retry_delay' => 1000,
        'amazon_product_importer_ssl_verify' => true,
        'amazon_product_importer_user_agent' => 'AmazonProductImporter/1.0.0 (WordPress)'
    ),

    /**
     * Import Settings
     * Default configuration for product import behavior
     */
    'import_settings' => array(
        'amazon_product_importer_default_status' => 'draft',
        'amazon_product_importer_default_visibility' => 'visible',
        'amazon_product_importer_default_stock_status' => 'instock',
        'amazon_product_importer_default_manage_stock' => false,
        'amazon_product_importer_default_backorders' => 'no',
        'amazon_product_importer_default_sold_individually' => false,
        'amazon_product_importer_default_virtual' => false,
        'amazon_product_importer_default_downloadable' => false,
        'amazon_product_importer_default_tax_status' => 'taxable',
        'amazon_product_importer_default_tax_class' => '',
        'amazon_product_importer_default_weight_unit' => 'kg',
        'amazon_product_importer_default_dimension_unit' => 'cm',
        'amazon_product_importer_price_markup_type' => 'none',
        'amazon_product_importer_price_markup_value' => 0,
        'amazon_product_importer_price_rounding' => true,
        'amazon_product_importer_price_rounding_precision' => 2,
        'amazon_product_importer_currency_conversion' => false,
        'amazon_product_importer_target_currency' => 'USD'
    ),

    /**
     * Image Import Settings
     * Default configuration for image handling
     */
    'image_settings' => array(
        'amazon_product_importer_import_images' => true,
        'amazon_product_importer_thumbnail_size' => 'SL200',
        'amazon_product_importer_max_images' => 10,
        'amazon_product_importer_image_quality' => 90,
        'amazon_product_importer_image_format' => 'original',
        'amazon_product_importer_image_alt_text' => 'auto',
        'amazon_product_importer_image_title' => 'auto',
        'amazon_product_importer_image_caption' => '',
        'amazon_product_importer_image_description' => '',
        'amazon_product_importer_watermark_images' => false,
        'amazon_product_importer_resize_images' => false,
        'amazon_product_importer_image_width' => 800,
        'amazon_product_importer_image_height' => 600,
        'amazon_product_importer_maintain_aspect_ratio' => true,
        'amazon_product_importer_image_compression' => true,
        'amazon_product_importer_convert_to_webp' => false,
        'amazon_product_importer_lazy_load_images' => true,
        'amazon_product_importer_cdn_images' => false,
        'amazon_product_importer_image_backup' => false
    ),

    /**
     * Synchronization Settings
     * Default configuration for automatic synchronization
     */
    'sync_settings' => array(
        'amazon_product_importer_auto_sync_enabled' => true,
        'amazon_product_importer_sync_interval' => 'every_6_hours',
        'amazon_product_importer_sync_price' => true,
        'amazon_product_importer_sync_stock' => true,
        'amazon_product_importer_sync_title' => false,
        'amazon_product_importer_sync_description' => false,
        'amazon_product_importer_sync_images' => false,
        'amazon_product_importer_sync_categories' => false,
        'amazon_product_importer_sync_attributes' => false,
        'amazon_product_importer_sync_variations' => true,
        'amazon_product_importer_sync_reviews' => false,
        'amazon_product_importer_sync_on_view' => false,
        'amazon_product_importer_sync_batch_size' => 50,
        'amazon_product_importer_sync_max_execution_time' => 300,
        'amazon_product_importer_sync_memory_limit' => '256M',
        'amazon_product_importer_sync_error_threshold' => 5,
        'amazon_product_importer_sync_retry_failed' => true,
        'amazon_product_importer_sync_notification' => false,
        'amazon_product_importer_sync_log_level' => 'info',
        'amazon_product_importer_sync_preserve_local_changes' => true,
        'amazon_product_importer_sync_conflict_resolution' => 'amazon_wins'
    ),

    /**
     * Category Settings
     * Default configuration for category handling
     */
    'category_settings' => array(
        'amazon_product_importer_auto_categories' => true,
        'amazon_product_importer_category_min_depth' => 1,
        'amazon_product_importer_category_max_depth' => 5,
        'amazon_product_importer_default_category' => '',
        'amazon_product_importer_category_hierarchy' => true,
        'amazon_product_importer_category_slug_format' => 'name',
        'amazon_product_importer_category_description' => 'auto',
        'amazon_product_importer_category_image' => false,
        'amazon_product_importer_merge_similar_categories' => true,
        'amazon_product_importer_category_mapping' => array(),
        'amazon_product_importer_excluded_categories' => array(),
        'amazon_product_importer_category_prefix' => '',
        'amazon_product_importer_category_suffix' => '',
        'amazon_product_importer_primary_category_only' => false,
        'amazon_product_importer_update_existing_categories' => false
    ),

    /**
     * Attribute and Variation Settings
     * Default configuration for product attributes and variations
     */
    'attribute_settings' => array(
        'amazon_product_importer_import_attributes' => true,
        'amazon_product_importer_attribute_prefix' => 'pa_',
        'amazon_product_importer_global_attributes' => true,
        'amazon_product_importer_variation_threshold' => 100,
        'amazon_product_importer_max_variations' => 50,
        'amazon_product_importer_variation_image_sync' => true,
        'amazon_product_importer_variation_price_sync' => true,
        'amazon_product_importer_variation_stock_sync' => true,
        'amazon_product_importer_variation_description' => false,
        'amazon_product_importer_create_all_variations' => true,
        'amazon_product_importer_variation_default_values' => array(),
        'amazon_product_importer_attribute_visibility' => true,
        'amazon_product_importer_attribute_archive' => false,
        'amazon_product_importer_custom_attribute_mapping' => array()
    ),

    /**
     * Performance and Rate Limiting
     * Default configuration for performance optimization
     */
    'performance_settings' => array(
        'amazon_product_importer_api_rate_limit' => 1000,
        'amazon_product_importer_requests_per_second' => 1,
        'amazon_product_importer_burst_requests' => 10,
        'amazon_product_importer_throttle_delay' => 1000,
        'amazon_product_importer_batch_processing' => true,
        'amazon_product_importer_batch_size' => 25,
        'amazon_product_importer_concurrent_imports' => 3,
        'amazon_product_importer_memory_optimization' => true,
        'amazon_product_importer_database_optimization' => true,
        'amazon_product_importer_query_caching' => true,
        'amazon_product_importer_background_processing' => true,
        'amazon_product_importer_queue_processing' => true,
        'amazon_product_importer_max_execution_time' => 0,
        'amazon_product_importer_memory_limit' => '512M',
        'amazon_product_importer_chunk_size' => 100
    ),

    /**
     * Cache Settings
     * Default configuration for caching
     */
    'cache_settings' => array(
        'amazon_product_importer_cache_duration' => 60,
        'amazon_product_importer_enable_cache' => true,
        'amazon_product_importer_cache_type' => 'transient',
        'amazon_product_importer_cache_search_results' => true,
        'amazon_product_importer_cache_product_details' => true,
        'amazon_product_importer_cache_browse_nodes' => true,
        'amazon_product_importer_cache_variations' => true,
        'amazon_product_importer_cache_images' => false,
        'amazon_product_importer_cache_compression' => true,
        'amazon_product_importer_cache_cleanup' => true,
        'amazon_product_importer_cache_cleanup_interval' => 'daily',
        'amazon_product_importer_cache_max_size' => '100MB',
        'amazon_product_importer_cache_preload' => false,
        'amazon_product_importer_cache_warming' => false,
        'amazon_product_importer_cdn_cache' => false
    ),

    /**
     * Security Settings
     * Default configuration for security
     */
    'security_settings' => array(
        'amazon_product_importer_ssl_verify' => true,
        'amazon_product_importer_api_key_encryption' => true,
        'amazon_product_importer_secure_headers' => true,
        'amazon_product_importer_request_validation' => true,
        'amazon_product_importer_sanitize_data' => true,
        'amazon_product_importer_escape_output' => true,
        'amazon_product_importer_csrf_protection' => true,
        'amazon_product_importer_rate_limit_protection' => true,
        'amazon_product_importer_ip_whitelist' => array(),
        'amazon_product_importer_ip_blacklist' => array(),
        'amazon_product_importer_user_agent_filtering' => false,
        'amazon_product_importer_audit_logging' => true,
        'amazon_product_importer_security_headers' => true,
        'amazon_product_importer_content_filtering' => true,
        'amazon_product_importer_file_validation' => true
    ),

    /**
     * Logging and Debugging
     * Default configuration for logging
     */
    'logging_settings' => array(
        'amazon_product_importer_debug_mode' => false,
        'amazon_product_importer_log_level' => 'warning',
        'amazon_product_importer_enable_logging' => true,
        'amazon_product_importer_log_file_size' => '10MB',
        'amazon_product_importer_log_retention_days' => 30,
        'amazon_product_importer_log_rotation' => true,
        'amazon_product_importer_log_api_requests' => false,
        'amazon_product_importer_log_api_responses' => false,
        'amazon_product_importer_log_imports' => true,
        'amazon_product_importer_log_sync_operations' => true,
        'amazon_product_importer_log_errors_only' => false,
        'amazon_product_importer_log_performance' => false,
        'amazon_product_importer_log_memory_usage' => false,
        'amazon_product_importer_log_database_queries' => false,
        'amazon_product_importer_verbose_logging' => false,
        'amazon_product_importer_log_format' => 'standard',
        'amazon_product_importer_log_timezone' => 'UTC'
    ),

    /**
     * Notification Settings
     * Default configuration for notifications
     */
    'notification_settings' => array(
        'amazon_product_importer_email_notifications' => false,
        'amazon_product_importer_admin_notifications' => true,
        'amazon_product_importer_notification_email' => '',
        'amazon_product_importer_notify_on_import' => false,
        'amazon_product_importer_notify_on_sync' => false,
        'amazon_product_importer_notify_on_error' => true,
        'amazon_product_importer_notify_on_api_limit' => true,
        'amazon_product_importer_notify_on_quota_exceeded' => true,
        'amazon_product_importer_notification_frequency' => 'immediate',
        'amazon_product_importer_digest_notifications' => false,
        'amazon_product_importer_webhook_notifications' => false,
        'amazon_product_importer_webhook_url' => '',
        'amazon_product_importer_slack_notifications' => false,
        'amazon_product_importer_slack_webhook_url' => '',
        'amazon_product_importer_dashboard_notifications' => true,
        'amazon_product_importer_browser_notifications' => false
    ),

    /**
     * SEO Settings
     * Default configuration for SEO optimization
     */
    'seo_settings' => array(
        'amazon_product_importer_seo_title_format' => '{product_title}',
        'amazon_product_importer_seo_description_format' => '{short_description}',
        'amazon_product_importer_seo_keywords_auto' => true,
        'amazon_product_importer_seo_slug_format' => '{product_title}',
        'amazon_product_importer_canonical_url' => 'local',
        'amazon_product_importer_noindex_drafts' => true,
        'amazon_product_importer_schema_markup' => true,
        'amazon_product_importer_open_graph' => true,
        'amazon_product_importer_twitter_cards' => true,
        'amazon_product_importer_meta_robots' => 'index,follow',
        'amazon_product_importer_breadcrumbs' => true,
        'amazon_product_importer_alt_text_auto' => true,
        'amazon_product_importer_image_seo' => true,
        'amazon_product_importer_sitemap_inclusion' => true,
        'amazon_product_importer_rich_snippets' => true
    ),

    /**
     * Content Processing
     * Default configuration for content processing
     */
    'content_settings' => array(
        'amazon_product_importer_content_filtering' => true,
        'amazon_product_importer_allowed_html_tags' => array('p', 'br', 'strong', 'em', 'ul', 'ol', 'li'),
        'amazon_product_importer_strip_scripts' => true,
        'amazon_product_importer_strip_styles' => true,
        'amazon_product_importer_convert_encoding' => true,
        'amazon_product_importer_target_encoding' => 'UTF-8',
        'amazon_product_importer_content_length_limit' => 10000,
        'amazon_product_importer_word_wrap' => false,
        'amazon_product_importer_auto_paragraphs' => true,
        'amazon_product_importer_shortcode_processing' => false,
        'amazon_product_importer_emoji_support' => true,
        'amazon_product_importer_rtl_support' => false,
        'amazon_product_importer_language_detection' => false,
        'amazon_product_importer_translation_service' => 'none',
        'amazon_product_importer_content_templates' => array()
    ),

    /**
     * Advanced Settings
     * Default configuration for advanced features
     */
    'advanced_settings' => array(
        'amazon_product_importer_keep_data_on_uninstall' => false,
        'amazon_product_importer_database_cleanup' => true,
        'amazon_product_importer_optimize_database' => true,
        'amazon_product_importer_custom_fields' => array(),
        'amazon_product_importer_hooks_enabled' => true,
        'amazon_product_importer_filters_enabled' => true,
        'amazon_product_importer_developer_mode' => false,
        'amazon_product_importer_experimental_features' => false,
        'amazon_product_importer_beta_features' => false,
        'amazon_product_importer_feature_flags' => array(),
        'amazon_product_importer_custom_css' => '',
        'amazon_product_importer_custom_js' => '',
        'amazon_product_importer_custom_templates' => array(),
        'amazon_product_importer_integration_settings' => array(),
        'amazon_product_importer_third_party_plugins' => array()
    ),

    /**
     * Backup and Recovery
     * Default configuration for backup
     */
    'backup_settings' => array(
        'amazon_product_importer_auto_backup' => false,
        'amazon_product_importer_backup_frequency' => 'weekly',
        'amazon_product_importer_backup_retention' => 4,
        'amazon_product_importer_backup_location' => 'local',
        'amazon_product_importer_backup_compression' => true,
        'amazon_product_importer_backup_encryption' => false,
        'amazon_product_importer_backup_verification' => true,
        'amazon_product_importer_incremental_backup' => true,
        'amazon_product_importer_backup_exclude' => array('logs', 'cache'),
        'amazon_product_importer_restore_point' => 'auto',
        'amazon_product_importer_backup_notification' => false,
        'amazon_product_importer_cloud_backup' => false,
        'amazon_product_importer_backup_schedule' => '02:00'
    ),

    /**
     * Cron and Scheduling
     * Default configuration for cron jobs
     */
    'cron_settings' => array(
        'amazon_product_importer_product_name_cron' => false,
        'amazon_product_importer_product_category_cron' => true,
        'amazon_product_importer_product_sku_cron' => false,
        'amazon_product_importer_cron_enabled' => true,
        'amazon_product_importer_cron_frequency' => 'every_6_hours',
        'amazon_product_importer_cron_timeout' => 300,
        'amazon_product_importer_cron_memory_limit' => '256M',
        'amazon_product_importer_cron_overlap_prevention' => true,
        'amazon_product_importer_cron_error_handling' => true,
        'amazon_product_importer_cron_notification' => false,
        'amazon_product_importer_cron_logging' => true,
        'amazon_product_importer_maintenance_mode' => false,
        'amazon_product_importer_cron_priority' => 'normal',
        'amazon_product_importer_parallel_processing' => false
    ),

    /**
     * User Interface
     * Default configuration for UI
     */
    'ui_settings' => array(
        'amazon_product_importer_ui_theme' => 'default',
        'amazon_product_importer_items_per_page' => 20,
        'amazon_product_importer_default_view' => 'grid',
        'amazon_product_importer_show_thumbnails' => true,
        'amazon_product_importer_show_prices' => true,
        'amazon_product_importer_show_ratings' => true,
        'amazon_product_importer_show_availability' => true,
        'amazon_product_importer_compact_view' => false,
        'amazon_product_importer_auto_refresh' => false,
        'amazon_product_importer_refresh_interval' => 300,
        'amazon_product_importer_keyboard_shortcuts' => true,
        'amazon_product_importer_tooltips' => true,
        'amazon_product_importer_progress_indicators' => true,
        'amazon_product_importer_animations' => true,
        'amazon_product_importer_responsive_design' => true
    ),

    /**
     * Development and Testing
     * Default configuration for development
     */
    'development_settings' => array(
        'amazon_product_importer_test_mode' => false,
        'amazon_product_importer_sandbox_mode' => false,
        'amazon_product_importer_mock_api' => false,
        'amazon_product_importer_test_data' => false,
        'amazon_product_importer_profiling' => false,
        'amazon_product_importer_benchmarking' => false,
        'amazon_product_importer_code_coverage' => false,
        'amazon_product_importer_unit_tests' => false,
        'amazon_product_importer_integration_tests' => false,
        'amazon_product_importer_load_tests' => false,
        'amazon_product_importer_debug_toolbar' => false,
        'amazon_product_importer_query_monitor' => false,
        'amazon_product_importer_development_tools' => false,
        'amazon_product_importer_staging_environment' => false,
        'amazon_product_importer_version_control' => false
    ),

    /**
     * License and Updates
     * Default configuration for licensing
     */
    'license_settings' => array(
        'amazon_product_importer_license_key' => '',
        'amazon_product_importer_license_status' => 'inactive',
        'amazon_product_importer_auto_updates' => true,
        'amazon_product_importer_update_channel' => 'stable',
        'amazon_product_importer_beta_updates' => false,
        'amazon_product_importer_update_notifications' => true,
        'amazon_product_importer_license_check_interval' => 'weekly',
        'amazon_product_importer_telemetry' => false,
        'amazon_product_importer_anonymous_usage' => false,
        'amazon_product_importer_feature_requests' => true,
        'amazon_product_importer_bug_reporting' => true,
        'amazon_product_importer_support_access' => false,
        'amazon_product_importer_premium_features' => false,
        'amazon_product_importer_license_domain' => '',
        'amazon_product_importer_multi_site_license' => false
    ),

    /**
     * Integration Settings
     * Default configuration for third-party integrations
     */
    'integration_settings' => array(
        'amazon_product_importer_woocommerce_integration' => true,
        'amazon_product_importer_yoast_seo_integration' => false,
        'amazon_product_importer_rankmath_integration' => false,
        'amazon_product_importer_elementor_integration' => false,
        'amazon_product_importer_gutenberg_integration' => false,
        'amazon_product_importer_wpml_integration' => false,
        'amazon_product_importer_polylang_integration' => false,
        'amazon_product_importer_mailchimp_integration' => false,
        'amazon_product_importer_google_analytics_integration' => false,
        'amazon_product_importer_facebook_pixel_integration' => false,
        'amazon_product_importer_google_merchant_integration' => false,
        'amazon_product_importer_affiliate_integration' => false,
        'amazon_product_importer_comparison_plugins' => false,
        'amazon_product_importer_review_plugins' => false,
        'amazon_product_importer_inventory_plugins' => false
    ),

    /**
     * Compliance Settings
     * Default configuration for compliance
     */
    'compliance_settings' => array(
        'amazon_product_importer_gdpr_compliance' => true,
        'amazon_product_importer_ccpa_compliance' => false,
        'amazon_product_importer_data_retention_policy' => 365,
        'amazon_product_importer_cookie_consent' => false,
        'amazon_product_importer_privacy_policy_link' => '',
        'amazon_product_importer_terms_of_service_link' => '',
        'amazon_product_importer_affiliate_disclosure' => true,
        'amazon_product_importer_fcc_compliance' => false,
        'amazon_product_importer_accessibility_compliance' => true,
        'amazon_product_importer_age_verification' => false,
        'amazon_product_importer_content_warnings' => false,
        'amazon_product_importer_regional_restrictions' => array(),
        'amazon_product_importer_legal_disclaimers' => true,
        'amazon_product_importer_data_export' => true,
        'amazon_product_importer_data_deletion' => true
    )
);