<?php
/**
 * Cron Schedules Configuration
 *
 * This file contains all cron job schedules, task definitions,
 * and synchronization settings for the Amazon Product Importer plugin.
 *
 * @link       https://mycreanet.fr
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
 * Cron Schedules Configuration
 */
return array(

    /**
     * Custom Cron Schedules
     * These schedules will be added to WordPress cron_schedules filter
     */
    'custom_schedules' => array(
        'every_5_minutes' => array(
            'interval' => 300, // 5 minutes
            'display' => __('Every 5 Minutes', 'amazon-product-importer'),
            'description' => __('Runs every 5 minutes for critical tasks', 'amazon-product-importer')
        ),
        'every_10_minutes' => array(
            'interval' => 600, // 10 minutes
            'display' => __('Every 10 Minutes', 'amazon-product-importer'),
            'description' => __('Runs every 10 minutes for frequent updates', 'amazon-product-importer')
        ),
        'every_15_minutes' => array(
            'interval' => 900, // 15 minutes
            'display' => __('Every 15 Minutes', 'amazon-product-importer'),
            'description' => __('Runs every 15 minutes for regular sync tasks', 'amazon-product-importer')
        ),
        'every_30_minutes' => array(
            'interval' => 1800, // 30 minutes
            'display' => __('Every 30 Minutes', 'amazon-product-importer'),
            'description' => __('Runs every 30 minutes for moderate frequency tasks', 'amazon-product-importer')
        ),
        'every_2_hours' => array(
            'interval' => 7200, // 2 hours
            'display' => __('Every 2 Hours', 'amazon-product-importer'),
            'description' => __('Runs every 2 hours for periodic updates', 'amazon-product-importer')
        ),
        'every_6_hours' => array(
            'interval' => 21600, // 6 hours
            'display' => __('Every 6 Hours', 'amazon-product-importer'),
            'description' => __('Runs every 6 hours for comprehensive sync', 'amazon-product-importer')
        ),
        'every_12_hours' => array(
            'interval' => 43200, // 12 hours
            'display' => __('Every 12 Hours', 'amazon-product-importer'),
            'description' => __('Runs every 12 hours for full synchronization', 'amazon-product-importer')
        ),
        'weekly' => array(
            'interval' => 604800, // 7 days
            'display' => __('Weekly', 'amazon-product-importer'),
            'description' => __('Runs weekly for maintenance tasks', 'amazon-product-importer')
        ),
        'monthly' => array(
            'interval' => 2635200, // 30.5 days
            'display' => __('Monthly', 'amazon-product-importer'),
            'description' => __('Runs monthly for cleanup and optimization', 'amazon-product-importer')
        )
    ),

    /**
     * Cron Jobs Configuration
     * Definition of all cron jobs used by the plugin
     */
    'cron_jobs' => array(
        
        // Price Synchronization
        'amazon_product_importer_sync_prices' => array(
            'hook' => 'amazon_product_importer_sync_prices',
            'callback' => 'Amazon_Product_Importer_Cron_Manager::sync_product_prices',
            'schedule' => 'every_6_hours',
            'description' => 'Synchronize product prices from Amazon',
            'priority' => 10,
            'enabled' => true,
            'batch_size' => 50,
            'timeout' => 300, // 5 minutes
            'dependencies' => array('amazon_product_importer_auto_sync_enabled'),
            'conditions' => array(
                'setting' => 'amazon_product_importer_sync_price',
                'value' => '1'
            )
        ),

        // Stock Status Synchronization
        'amazon_product_importer_sync_stock' => array(
            'hook' => 'amazon_product_importer_sync_stock',
            'callback' => 'Amazon_Product_Importer_Cron_Manager::sync_stock_status',
            'schedule' => 'every_6_hours',
            'description' => 'Synchronize stock status from Amazon',
            'priority' => 15,
            'enabled' => true,
            'batch_size' => 50,
            'timeout' => 300,
            'dependencies' => array('amazon_product_importer_auto_sync_enabled'),
            'conditions' => array(
                'setting' => 'amazon_product_importer_sync_stock',
                'value' => '1'
            )
        ),

        // Product Information Updates
        'amazon_product_importer_update_products' => array(
            'hook' => 'amazon_product_importer_update_products',
            'callback' => 'Amazon_Product_Importer_Cron_Manager::update_product_info',
            'schedule' => 'every_12_hours',
            'description' => 'Update product titles, descriptions, and features',
            'priority' => 20,
            'enabled' => true,
            'batch_size' => 25,
            'timeout' => 600, // 10 minutes
            'dependencies' => array('amazon_product_importer_auto_sync_enabled'),
            'conditions' => array(
                array(
                    'setting' => 'amazon_product_importer_sync_title',
                    'value' => '1'
                ),
                array(
                    'setting' => 'amazon_product_importer_sync_description',
                    'value' => '1',
                    'operator' => 'OR'
                )
            )
        ),

        // Image Synchronization
        'amazon_product_importer_sync_images' => array(
            'hook' => 'amazon_product_importer_sync_images',
            'callback' => 'Amazon_Product_Importer_Cron_Manager::sync_product_images',
            'schedule' => 'daily',
            'description' => 'Synchronize product images from Amazon',
            'priority' => 25,
            'enabled' => true,
            'batch_size' => 20,
            'timeout' => 900, // 15 minutes
            'dependencies' => array('amazon_product_importer_auto_sync_enabled'),
            'conditions' => array(
                'setting' => 'amazon_product_importer_sync_images',
                'value' => '1'
            )
        ),

        // Variation Updates
        'amazon_product_importer_sync_variations' => array(
            'hook' => 'amazon_product_importer_sync_variations',
            'callback' => 'Amazon_Product_Importer_Cron_Manager::sync_product_variations',
            'schedule' => 'every_12_hours',
            'description' => 'Synchronize product variations and attributes',
            'priority' => 30,
            'enabled' => true,
            'batch_size' => 15,
            'timeout' => 600,
            'dependencies' => array('amazon_product_importer_auto_sync_enabled')
        ),

        // Category Updates
        'amazon_product_importer_update_categories' => array(
            'hook' => 'amazon_product_importer_update_categories',
            'callback' => 'Amazon_Product_Importer_Cron_Manager::update_product_categories',
            'schedule' => 'weekly',
            'description' => 'Update product categories based on Amazon browse nodes',
            'priority' => 35,
            'enabled' => true,
            'batch_size' => 30,
            'timeout' => 300,
            'dependencies' => array('amazon_product_importer_auto_categories'),
            'conditions' => array(
                'setting' => 'amazon_product_importer_product_category_cron',
                'value' => '1'
            )
        ),

        // Health Check and Monitoring
        'amazon_product_importer_health_check' => array(
            'hook' => 'amazon_product_importer_health_check',
            'callback' => 'Amazon_Product_Importer_Cron_Manager::perform_health_check',
            'schedule' => 'every_30_minutes',
            'description' => 'Perform system health checks and API status monitoring',
            'priority' => 5,
            'enabled' => true,
            'batch_size' => 1,
            'timeout' => 60,
            'dependencies' => array(),
            'always_run' => true
        ),

        // Error Retry Processing
        'amazon_product_importer_retry_failed' => array(
            'hook' => 'amazon_product_importer_retry_failed',
            'callback' => 'Amazon_Product_Importer_Cron_Manager::retry_failed_imports',
            'schedule' => 'every_2_hours',
            'description' => 'Retry failed product imports and sync operations',
            'priority' => 40,
            'enabled' => true,
            'batch_size' => 10,
            'timeout' => 300,
            'dependencies' => array(),
            'max_retries' => 3
        ),

        // Cache Cleanup
        'amazon_product_importer_cache_cleanup' => array(
            'hook' => 'amazon_product_importer_cache_cleanup',
            'callback' => 'Amazon_Product_Importer_Cron_Manager::cleanup_cache',
            'schedule' => 'daily',
            'description' => 'Clean up expired cache entries and temporary data',
            'priority' => 50,
            'enabled' => true,
            'batch_size' => 1,
            'timeout' => 120,
            'dependencies' => array()
        ),

        // Log Cleanup
        'amazon_product_importer_log_cleanup' => array(
            'hook' => 'amazon_product_importer_log_cleanup',
            'callback' => 'Amazon_Product_Importer_Cron_Manager::cleanup_logs',
            'schedule' => 'weekly',
            'description' => 'Clean up old log entries and maintain log size',
            'priority' => 55,
            'enabled' => true,
            'batch_size' => 1,
            'timeout' => 60,
            'dependencies' => array(),
            'retention_days' => 30
        ),

        // Rate Limit Reset
        'amazon_product_importer_reset_rate_limits' => array(
            'hook' => 'amazon_product_importer_reset_rate_limits',
            'callback' => 'Amazon_Product_Importer_Cron_Manager::reset_rate_limits',
            'schedule' => 'hourly',
            'description' => 'Reset API rate limit counters',
            'priority' => 60,
            'enabled' => true,
            'batch_size' => 1,
            'timeout' => 30,
            'dependencies' => array()
        ),

        // Orphaned Product Cleanup
        'amazon_product_importer_cleanup_orphaned' => array(
            'hook' => 'amazon_product_importer_cleanup_orphaned',
            'callback' => 'Amazon_Product_Importer_Cron_Manager::cleanup_orphaned_products',
            'schedule' => 'weekly',
            'description' => 'Clean up orphaned Amazon product data',
            'priority' => 65,
            'enabled' => true,
            'batch_size' => 50,
            'timeout' => 300,
            'dependencies' => array()
        ),

        // Statistics Update
        'amazon_product_importer_update_stats' => array(
            'hook' => 'amazon_product_importer_update_stats',
            'callback' => 'Amazon_Product_Importer_Cron_Manager::update_statistics',
            'schedule' => 'daily',
            'description' => 'Update plugin statistics and metrics',
            'priority' => 70,
            'enabled' => true,
            'batch_size' => 1,
            'timeout' => 60,
            'dependencies' => array()
        ),

        // Single Product Sync (Dynamic)
        'amazon_product_importer_sync_single_product' => array(
            'hook' => 'amazon_product_importer_sync_single_product',
            'callback' => 'Amazon_Product_Importer_Cron_Manager::sync_single_product',
            'schedule' => 'single_event',
            'description' => 'Synchronize a single product (triggered dynamically)',
            'priority' => 75,
            'enabled' => true,
            'batch_size' => 1,
            'timeout' => 60,
            'dependencies' => array()
        )
    ),

    /**
     * Synchronization Configuration
     */
    'sync_config' => array(
        'max_concurrent_jobs' => 3,
        'job_overlap_prevention' => true,
        'memory_limit' => '256M',
        'execution_time_limit' => 0, // No limit for cron jobs
        'error_threshold' => 5, // Stop batch after 5 consecutive errors
        'api_request_delay' => 1000, // 1 second between API requests
        'batch_processing_delay' => 5000, // 5 seconds between batches
        'max_execution_time' => 1800, // 30 minutes max per job
        'lock_timeout' => 3600, // 1 hour lock timeout
        'heartbeat_interval' => 60, // 1 minute heartbeat
        'progress_update_interval' => 30 // 30 seconds progress updates
    ),

    /**
     * Performance Optimization
     */
    'performance' => array(
        'enable_background_processing' => true,
        'use_action_scheduler' => true, // If Action Scheduler is available
        'chunk_size' => 10, // Items per chunk
        'memory_cleanup_interval' => 50, // Clean memory every 50 items
        'database_optimization' => true,
        'query_caching' => true,
        'transient_caching' => true,
        'object_caching' => true,
        'cdn_image_processing' => false
    ),

    /**
     * Error Handling and Retry Logic
     */
    'error_handling' => array(
        'max_retries' => 3,
        'retry_delay' => 300, // 5 minutes
        'exponential_backoff' => true,
        'backoff_multiplier' => 2,
        'max_retry_delay' => 3600, // 1 hour
        'error_notification' => true,
        'admin_email_errors' => false,
        'log_all_errors' => true,
        'critical_error_notification' => true,
        'auto_disable_on_critical_error' => true,
        'error_rate_threshold' => 0.1 // 10% error rate threshold
    ),

    /**
     * API Rate Limiting for Cron Jobs
     */
    'rate_limiting' => array(
        'requests_per_minute' => 30,
        'requests_per_hour' => 1000,
        'requests_per_day' => 8000,
        'burst_allowance' => 10,
        'throttle_enabled' => true,
        'adaptive_throttling' => true,
        'priority_queuing' => true,
        'fair_usage_enforcement' => true
    ),

    /**
     * Monitoring and Logging
     */
    'monitoring' => array(
        'enable_logging' => true,
        'log_level' => 'info', // debug, info, warning, error
        'log_rotation' => true,
        'max_log_size' => '10MB',
        'log_retention_days' => 30,
        'performance_monitoring' => true,
        'memory_usage_tracking' => true,
        'execution_time_tracking' => true,
        'api_response_tracking' => true,
        'success_rate_tracking' => true,
        'alert_on_failure_rate' => 0.2 // 20% failure rate triggers alert
    ),

    /**
     * Queue Management
     */
    'queue_management' => array(
        'queue_type' => 'database', // database, redis, file
        'max_queue_size' => 1000,
        'queue_cleanup_interval' => 3600, // 1 hour
        'priority_levels' => array(
            'critical' => 1,
            'high' => 5,
            'normal' => 10,
            'low' => 20
        ),
        'queue_processing_order' => 'priority_fifo', // priority_fifo, fifo, lifo
        'dead_letter_queue' => true,
        'max_processing_attempts' => 3
    ),

    /**
     * Maintenance Windows
     */
    'maintenance_windows' => array(
        'enabled' => false,
        'timezone' => 'UTC',
        'windows' => array(
            array(
                'day' => 'sunday',
                'start_time' => '02:00',
                'end_time' => '04:00',
                'description' => 'Weekly maintenance window'
            )
        ),
        'maintenance_jobs' => array(
            'amazon_product_importer_cache_cleanup',
            'amazon_product_importer_log_cleanup',
            'amazon_product_importer_cleanup_orphaned'
        )
    ),

    /**
     * Conditional Execution Rules
     */
    'execution_conditions' => array(
        'min_free_memory' => '64MB',
        'max_server_load' => 0.8,
        'check_db_connection' => true,
        'check_api_availability' => true,
        'skip_during_peak_hours' => false,
        'peak_hours' => array(
            'start' => '09:00',
            'end' => '17:00',
            'timezone' => 'America/New_York'
        ),
        'weekend_execution' => true,
        'holiday_execution' => false
    ),

    /**
     * Notification Settings
     */
    'notifications' => array(
        'enabled' => true,
        'email_notifications' => false,
        'admin_bar_notifications' => true,
        'dashboard_widget' => true,
        'webhook_notifications' => false,
        'slack_notifications' => false,
        'notification_triggers' => array(
            'job_completion' => false,
            'job_failure' => true,
            'high_error_rate' => true,
            'api_quota_exceeded' => true,
            'maintenance_required' => true
        ),
        'notification_recipients' => array(
            'admin_email' => true,
            'custom_emails' => array()
        )
    ),

    /**
     * Backup and Recovery
     */
    'backup_recovery' => array(
        'backup_before_sync' => false,
        'backup_frequency' => 'weekly',
        'backup_retention' => 4, // Keep 4 backups
        'incremental_backup' => true,
        'recovery_point_objective' => '1 hour',
        'auto_recovery' => false,
        'backup_verification' => true
    ),

    /**
     * Development and Testing
     */
    'development' => array(
        'debug_mode' => false,
        'test_mode' => false,
        'dry_run_mode' => false,
        'verbose_logging' => false,
        'profiling_enabled' => false,
        'mock_api_responses' => false,
        'test_data_injection' => false,
        'development_overrides' => array()
    ),

    /**
     * Feature Flags
     */
    'feature_flags' => array(
        'enable_advanced_sync' => true,
        'enable_bulk_operations' => true,
        'enable_smart_scheduling' => true,
        'enable_predictive_caching' => false,
        'enable_machine_learning' => false,
        'enable_a_b_testing' => false,
        'enable_experimental_features' => false
    ),

    /**
     * Timezone Configuration
     */
    'timezone' => array(
        'default_timezone' => 'UTC',
        'respect_wp_timezone' => true,
        'sync_with_amazon_timezone' => false,
        'daylight_saving_adjustment' => true
    ),

    /**
     * Resource Limits
     */
    'resource_limits' => array(
        'max_memory_usage' => '512MB',
        'max_execution_time' => 1800, // 30 minutes
        'max_database_queries' => 1000,
        'max_api_calls_per_job' => 100,
        'max_file_uploads' => 50,
        'max_concurrent_processes' => 5
    )
);