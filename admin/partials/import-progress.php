<?php
/**
 * Provide the import progress view for the plugin
 *
 * This file displays the real-time import progress interface
 * with statistics, logs, and controls.
 *
 * @link       https://mycreanet.fr
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/admin/partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get batch data from parameters
$batch_id = isset($_GET['batch_id']) ? sanitize_text_field($_GET['batch_id']) : '';
$batch_data = $batch_id ? get_transient('amazon_import_batch_' . $batch_id) : null;

// Default batch data structure
if (!$batch_data) {
    $batch_data = array(
        'batch_id' => $batch_id,
        'total' => 0,
        'processed' => 0,
        'success' => 0,
        'failed' => 0,
        'skipped' => 0,
        'start_time' => current_time('timestamp'),
        'status' => 'idle',
        'current_asin' => '',
        'current_product' => '',
        'estimated_time' => 0,
        'products' => array(),
        'errors' => array()
    );
}

$progress_percentage = $batch_data['total'] > 0 ? round(($batch_data['processed'] / $batch_data['total']) * 100, 1) : 0;
$elapsed_time = current_time('timestamp') - $batch_data['start_time'];

// Helper function for duration formatting
function format_duration($seconds) {
    if ($seconds < 60) {
        return sprintf('%02d:%02d', 0, $seconds);
    } elseif ($seconds < 3600) {
        return sprintf('%02d:%02d', floor($seconds / 60), $seconds % 60);
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds % 60);
    }
}
?>

<div class="wrap amazon-import-progress-page">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Import Progress', 'amazon-product-importer'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=amazon-product-importer'); ?>" class="page-title-action">
        <?php echo esc_html__('New Import', 'amazon-product-importer'); ?>
    </a>
    
    <hr class="wp-header-end">

    <div class="amazon-import-progress-container" data-batch-id="<?php echo esc_attr($batch_id); ?>">
        
        <!-- Progress Header -->
        <div class="amazon-progress-header">
            <div class="amazon-progress-status">
                <h2 class="amazon-progress-title">
                    <?php echo esc_html__('Batch Import Progress', 'amazon-product-importer'); ?>
                    <?php if ($batch_id): ?>
                    <span class="amazon-batch-id">#<?php echo esc_html($batch_id); ?></span>
                    <?php endif; ?>
                </h2>
                
                <div class="amazon-progress-status-indicator">
                    <span class="amazon-status-badge amazon-status-<?php echo esc_attr($batch_data['status']); ?>">
                        <?php echo esc_html(ucfirst($batch_data['status'])); ?>
                    </span>
                </div>
            </div>
            
            <div class="amazon-progress-controls">
                <?php if ($batch_data['status'] === 'running'): ?>
                <button type="button" 
                        id="amazon-pause-import-btn" 
                        class="button button-secondary amazon-pause-btn">
                    <span class="dashicons dashicons-controls-pause"></span>
                    <?php echo esc_html__('Pause', 'amazon-product-importer'); ?>
                </button>
                <?php elseif ($batch_data['status'] === 'paused'): ?>
                <button type="button" 
                        id="amazon-resume-import-btn" 
                        class="button button-secondary amazon-resume-btn">
                    <span class="dashicons dashicons-controls-play"></span>
                    <?php echo esc_html__('Resume', 'amazon-product-importer'); ?>
                </button>
                <?php endif; ?>
                
                <?php if (in_array($batch_data['status'], array('running', 'paused'))): ?>
                <button type="button" 
                        id="amazon-cancel-import-btn" 
                        class="button amazon-cancel-btn">
                    <span class="dashicons dashicons-no"></span>
                    <?php echo esc_html__('Cancel', 'amazon-product-importer'); ?>
                </button>
                <?php endif; ?>
                
                <?php if (in_array($batch_data['status'], array('completed', 'cancelled', 'failed'))): ?>
                <button type="button" 
                        id="amazon-download-report-btn" 
                        class="button button-secondary amazon-download-btn">
                    <span class="dashicons dashicons-download"></span>
                    <?php echo esc_html__('Download Report', 'amazon-product-importer'); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Progress Overview -->
        <div class="amazon-progress-overview">
            <div class="amazon-progress-card">
                
                <!-- Main Progress Bar -->
                <div class="amazon-main-progress">
                    <div class="amazon-progress-info">
                        <div class="amazon-progress-label">
                            <?php echo esc_html__('Overall Progress', 'amazon-product-importer'); ?>
                        </div>
                        <div class="amazon-progress-percentage">
                            <?php echo esc_html($progress_percentage); ?>%
                        </div>
                    </div>
                    
                    <div class="amazon-progress-bar-container">
                        <div class="amazon-progress-bar">
                            <div class="amazon-progress-fill" 
                                 style="width: <?php echo esc_attr($progress_percentage); ?>%">
                            </div>
                        </div>
                    </div>
                    
                    <div class="amazon-progress-counts">
                        <span class="amazon-progress-current"><?php echo number_format_i18n($batch_data['processed']); ?></span>
                        /
                        <span class="amazon-progress-total"><?php echo number_format_i18n($batch_data['total']); ?></span>
                        <?php echo esc_html__('products processed', 'amazon-product-importer'); ?>
                    </div>
                </div>

                <!-- Statistics Grid -->
                <div class="amazon-progress-stats-grid">
                    <div class="amazon-progress-stat amazon-stat-success">
                        <div class="amazon-stat-icon">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                        <div class="amazon-stat-content">
                            <div class="amazon-stat-number"><?php echo number_format_i18n($batch_data['success']); ?></div>
                            <div class="amazon-stat-label"><?php echo esc_html__('Successful', 'amazon-product-importer'); ?></div>
                        </div>
                    </div>
                    
                    <div class="amazon-progress-stat amazon-stat-failed">
                        <div class="amazon-stat-icon">
                            <span class="dashicons dashicons-dismiss"></span>
                        </div>
                        <div class="amazon-stat-content">
                            <div class="amazon-stat-number"><?php echo number_format_i18n($batch_data['failed']); ?></div>
                            <div class="amazon-stat-label"><?php echo esc_html__('Failed', 'amazon-product-importer'); ?></div>
                        </div>
                    </div>
                    
                    <div class="amazon-progress-stat amazon-stat-skipped">
                        <div class="amazon-stat-icon">
                            <span class="dashicons dashicons-minus"></span>
                        </div>
                        <div class="amazon-stat-content">
                            <div class="amazon-stat-number"><?php echo number_format_i18n($batch_data['skipped']); ?></div>
                            <div class="amazon-stat-label"><?php echo esc_html__('Skipped', 'amazon-product-importer'); ?></div>
                        </div>
                    </div>
                    
                    <div class="amazon-progress-stat amazon-stat-remaining">
                        <div class="amazon-stat-icon">
                            <span class="dashicons dashicons-clock"></span>
                        </div>
                        <div class="amazon-stat-content">
                            <div class="amazon-stat-number"><?php echo number_format_i18n($batch_data['total'] - $batch_data['processed']); ?></div>
                            <div class="amazon-stat-label"><?php echo esc_html__('Remaining', 'amazon-product-importer'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Time Information -->
                <div class="amazon-progress-timing">
                    <div class="amazon-timing-item">
                        <span class="amazon-timing-label"><?php echo esc_html__('Elapsed:', 'amazon-product-importer'); ?></span>
                        <span class="amazon-timing-value amazon-elapsed-time">
                            <?php echo format_duration($elapsed_time); ?>
                        </span>
                    </div>
                    
                    <div class="amazon-timing-item">
                        <span class="amazon-timing-label"><?php echo esc_html__('Estimated Total:', 'amazon-product-importer'); ?></span>
                        <span class="amazon-timing-value amazon-estimated-time">
                            <?php 
                            if ($batch_data['processed'] > 0 && $batch_data['status'] === 'running') {
                                $rate = $batch_data['processed'] / max($elapsed_time, 1);
                                $estimated_total = $batch_data['total'] / max($rate, 0.01);
                                echo format_duration($estimated_total);
                            } else {
                                echo '--:--';
                            }
                            ?>
                        </span>
                    </div>
                    
                    <div class="amazon-timing-item">
                        <span class="amazon-timing-label"><?php echo esc_html__('ETA:', 'amazon-product-importer'); ?></span>
                        <span class="amazon-timing-value amazon-eta-time">
                            <?php 
                            if ($batch_data['processed'] > 0 && $batch_data['status'] === 'running') {
                                $rate = $batch_data['processed'] / max($elapsed_time, 1);
                                $remaining_time = ($batch_data['total'] - $batch_data['processed']) / max($rate, 0.01);
                                echo format_duration($remaining_time);
                            } else {
                                echo '--:--';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Product -->
        <?php if ($batch_data['status'] === 'running' && !empty($batch_data['current_asin'])): ?>
        <div class="amazon-current-product">
            <div class="amazon-progress-card">
                <h3 class="amazon-current-title">
                    <?php echo esc_html__('Currently Processing', 'amazon-product-importer'); ?>
                </h3>
                
                <div class="amazon-current-product-info">
                    <div class="amazon-current-asin">
                        <strong><?php echo esc_html__('ASIN:', 'amazon-product-importer'); ?></strong>
                        <span class="amazon-asin-value"><?php echo esc_html($batch_data['current_asin']); ?></span>
                    </div>
                    
                    <?php if (!empty($batch_data['current_product'])): ?>
                    <div class="amazon-current-name">
                        <strong><?php echo esc_html__('Product:', 'amazon-product-importer'); ?></strong>
                        <span class="amazon-product-name"><?php echo esc_html($batch_data['current_product']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="amazon-current-progress">
                        <div class="amazon-processing-spinner"></div>
                        <span class="amazon-processing-text">
                            <?php echo esc_html__('Importing...', 'amazon-product-importer'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Product List -->
        <div class="amazon-product-list">
            <div class="amazon-progress-card">
                <div class="amazon-product-list-header">
                    <h3><?php echo esc_html__('Product Import Log', 'amazon-product-importer'); ?></h3>
                    
                    <div class="amazon-list-controls">
                        <select id="amazon-status-filter" class="amazon-filter-select">
                            <option value=""><?php echo esc_html__('All Status', 'amazon-product-importer'); ?></option>
                            <option value="success"><?php echo esc_html__('Success', 'amazon-product-importer'); ?></option>
                            <option value="failed"><?php echo esc_html__('Failed', 'amazon-product-importer'); ?></option>
                            <option value="skipped"><?php echo esc_html__('Skipped', 'amazon-product-importer'); ?></option>
                            <option value="pending"><?php echo esc_html__('Pending', 'amazon-product-importer'); ?></option>
                        </select>
                        
                        <button type="button" 
                                id="amazon-refresh-list-btn" 
                                class="button button-secondary">
                            <span class="dashicons dashicons-update"></span>
                            <?php echo esc_html__('Refresh', 'amazon-product-importer'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="amazon-product-list-container" id="amazon-product-list">
                    <?php if (!empty($batch_data['products'])): ?>
                    <div class="amazon-product-items">
                        <?php foreach ($batch_data['products'] as $product): ?>
                        <div class="amazon-product-item amazon-status-<?php echo esc_attr($product['status']); ?>" 
                             data-asin="<?php echo esc_attr($product['asin']); ?>">
                            
                            <div class="amazon-product-status">
                                <span class="amazon-status-icon"></span>
                            </div>
                            
                            <div class="amazon-product-info">
                                <div class="amazon-product-asin">
                                    <strong><?php echo esc_html($product['asin']); ?></strong>
                                </div>
                                
                                <?php if (!empty($product['title'])): ?>
                                <div class="amazon-product-title">
                                    <?php echo esc_html($product['title']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="amazon-product-timestamp">
                                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $product['timestamp'])); ?>
                                </div>
                            </div>
                            
                            <div class="amazon-product-actions">
                                <?php if ($product['status'] === 'success' && !empty($product['product_id'])): ?>
                                <a href="<?php echo admin_url('post.php?post=' . $product['product_id'] . '&action=edit'); ?>" 
                                   class="button button-small amazon-view-product">
                                    <?php echo esc_html__('View Product', 'amazon-product-importer'); ?>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($product['status'] === 'failed'): ?>
                                <button type="button" 
                                        class="button button-small amazon-retry-product" 
                                        data-asin="<?php echo esc_attr($product['asin']); ?>">
                                    <?php echo esc_html__('Retry', 'amazon-product-importer'); ?>
                                </button>
                                <?php endif; ?>
                                
                                <button type="button" 
                                        class="button button-small amazon-view-details" 
                                        data-asin="<?php echo esc_attr($product['asin']); ?>">
                                    <?php echo esc_html__('Details', 'amazon-product-importer'); ?>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="amazon-empty-list">
                        <p><?php echo esc_html__('No products in the import queue yet.', 'amazon-product-importer'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Error Log -->
        <?php if (!empty($batch_data['errors'])): ?>
        <div class="amazon-error-log">
            <div class="amazon-progress-card">
                <h3 class="amazon-error-title">
                    <?php echo esc_html__('Import Errors', 'amazon-product-importer'); ?>
                    <span class="amazon-error-count">(<?php echo count($batch_data['errors']); ?>)</span>
                </h3>
                
                <div class="amazon-error-list">
                    <?php foreach ($batch_data['errors'] as $error): ?>
                    <div class="amazon-error-item">
                        <div class="amazon-error-time">
                            <?php echo esc_html(date_i18n(get_option('time_format'), $error['timestamp'])); ?>
                        </div>
                        <div class="amazon-error-message">
                            <?php echo esc_html($error['message']); ?>
                        </div>
                        <?php if (!empty($error['asin'])): ?>
                        <div class="amazon-error-asin">
                            ASIN: <?php echo esc_html($error['asin']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Product Details Modal -->
<div id="amazon-product-details-modal" class="amazon-modal" style="display: none;">
    <div class="amazon-modal-overlay"></div>
    <div class="amazon-modal-content">
        <div class="amazon-modal-header">
            <h3><?php echo esc_html__('Product Details', 'amazon-product-importer'); ?></h3>
            <button type="button" class="amazon-modal-close">&times;</button>
        </div>
        <div class="amazon-modal-body">
            <!-- Product details will be loaded here -->
        </div>
        <div class="amazon-modal-footer">
            <button type="button" class="button amazon-modal-close">
                <?php echo esc_html__('Close', 'amazon-product-importer'); ?>
            </button>
        </div>
    </div>
</div>

<script type="text/javascript">
    var amazon_progress_data = {
        ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('amazon_product_importer_nonce'); ?>',
        batch_id: '<?php echo esc_js($batch_id); ?>',
        refresh_interval: 5000, // 5 seconds
        strings: {
            pausing: '<?php echo esc_js(__('Pausing...', 'amazon-product-importer')); ?>',
            resuming: '<?php echo esc_js(__('Resuming...', 'amazon-product-importer')); ?>',
            cancelling: '<?php echo esc_js(__('Cancelling...', 'amazon-product-importer')); ?>',
            confirm_cancel: '<?php echo esc_js(__('Are you sure you want to cancel this import? Progress will be lost.', 'amazon-product-importer')); ?>',
            retry_failed: '<?php echo esc_js(__('Retry this product import?', 'amazon-product-importer')); ?>',
            download_starting: '<?php echo esc_js(__('Download starting...', 'amazon-product-importer')); ?>'
        }
    };
</script>