<?php
/**
 * Provide the import progress view for the plugin
 *
 * This file displays the real-time import progress interface
 * with statistics, logs, and controls.
 *
 * @link       https://yourwebsite.com
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
                            <?php echo $this->format_duration($elapsed_time); ?>
                        </span>
                    </div>
                    
                    <div class="amazon-timing-item">
                        <span class="amazon-timing-label"><?php echo esc_html__('Estimated Total:', 'amazon-product-importer'); ?></span>
                        <span class="amazon-timing-value amazon-estimated-time">
                            <?php 
                            if ($batch_data['processed'] > 0 && $batch_data['status'] === 'running') {
                                $rate = $batch_data['processed'] / max($elapsed_time, 1);
                                $estimated_total = $batch_data['total'] / max($rate, 0.01);
                                echo $this->format_duration($estimated_total);
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
                                echo $this->format_duration($remaining_time);
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
            <div class="amazon-current-product-header">
                <h3><?php echo esc_html__('Currently Processing', 'amazon-product-importer'); ?></h3>
            </div>
            <div class="amazon-current-product-content">
                <div class="amazon-current-asin">
                    <span class="amazon-current-label"><?php echo esc_html__('ASIN:', 'amazon-product-importer'); ?></span>
                    <span class="amazon-current-value"><?php echo esc_html($batch_data['current_asin']); ?></span>
                </div>
                <?php if (!empty($batch_data['current_product'])): ?>
                <div class="amazon-current-title">
                    <span class="amazon-current-label"><?php echo esc_html__('Product:', 'amazon-product-importer'); ?></span>
                    <span class="amazon-current-value"><?php echo esc_html($batch_data['current_product']); ?></span>
                </div>
                <?php endif; ?>
                <div class="amazon-current-spinner">
                    <span class="spinner is-active"></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Import Results -->
        <div class="amazon-import-results">
            <div class="amazon-results-tabs">
                <button type="button" 
                        class="amazon-tab-btn active" 
                        data-tab="all">
                    <?php echo esc_html__('All Products', 'amazon-product-importer'); ?>
                    <span class="amazon-tab-count"><?php echo number_format_i18n($batch_data['processed']); ?></span>
                </button>
                
                <button type="button" 
                        class="amazon-tab-btn" 
                        data-tab="success">
                    <?php echo esc_html__('Successful', 'amazon-product-importer'); ?>
                    <span class="amazon-tab-count"><?php echo number_format_i18n($batch_data['success']); ?></span>
                </button>
                
                <button type="button" 
                        class="amazon-tab-btn" 
                        data-tab="failed">
                    <?php echo esc_html__('Failed', 'amazon-product-importer'); ?>
                    <span class="amazon-tab-count"><?php echo number_format_i18n($batch_data['failed']); ?></span>
                </button>
                
                <button type="button" 
                        class="amazon-tab-btn" 
                        data-tab="skipped">
                    <?php echo esc_html__('Skipped', 'amazon-product-importer'); ?>
                    <span class="amazon-tab-count"><?php echo number_format_i18n($batch_data['skipped']); ?></span>
                </button>
            </div>

            <div class="amazon-results-content">
                <div class="amazon-results-header">
                    <div class="amazon-results-search">
                        <input type="text" 
                               id="amazon-results-search" 
                               class="amazon-search-input" 
                               placeholder="<?php echo esc_attr__('Search products...', 'amazon-product-importer'); ?>">
                        <button type="button" class="button amazon-search-clear" style="display: none;">
                            <?php echo esc_html__('Clear', 'amazon-product-importer'); ?>
                        </button>
                    </div>
                    
                    <div class="amazon-results-actions">
                        <button type="button" 
                                class="button button-small amazon-refresh-results">
                            <span class="dashicons dashicons-update"></span>
                            <?php echo esc_html__('Refresh', 'amazon-product-importer'); ?>
                        </button>
                        
                        <select id="amazon-results-per-page" class="amazon-results-per-page">
                            <option value="10">10 <?php echo esc_html__('per page', 'amazon-product-importer'); ?></option>
                            <option value="25" selected>25 <?php echo esc_html__('per page', 'amazon-product-importer'); ?></option>
                            <option value="50">50 <?php echo esc_html__('per page', 'amazon-product-importer'); ?></option>
                            <option value="100">100 <?php echo esc_html__('per page', 'amazon-product-importer'); ?></option>
                        </select>
                    </div>
                </div>

                <div id="amazon-results-table-container" class="amazon-results-table-container">
                    <table class="wp-list-table widefat fixed striped amazon-results-table">
                        <thead>
                            <tr>
                                <th class="amazon-col-asin">
                                    <?php echo esc_html__('ASIN', 'amazon-product-importer'); ?>
                                </th>
                                <th class="amazon-col-title">
                                    <?php echo esc_html__('Product Title', 'amazon-product-importer'); ?>
                                </th>
                                <th class="amazon-col-status">
                                    <?php echo esc_html__('Status', 'amazon-product-importer'); ?>
                                </th>
                                <th class="amazon-col-time">
                                    <?php echo esc_html__('Time', 'amazon-product-importer'); ?>
                                </th>
                                <th class="amazon-col-actions">
                                    <?php echo esc_html__('Actions', 'amazon-product-importer'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="amazon-results-tbody">
                            <?php if (!empty($batch_data['products'])): ?>
                                <?php foreach ($batch_data['products'] as $product_result): ?>
                                <tr class="amazon-result-row amazon-status-<?php echo esc_attr($product_result['status']); ?>" 
                                    data-asin="<?php echo esc_attr($product_result['asin']); ?>"
                                    data-status="<?php echo esc_attr($product_result['status']); ?>">
                                    
                                    <td class="amazon-col-asin">
                                        <a href="https://amazon.com/dp/<?php echo esc_attr($product_result['asin']); ?>" 
                                           target="_blank" 
                                           title="<?php echo esc_attr__('View on Amazon', 'amazon-product-importer'); ?>">
                                            <?php echo esc_html($product_result['asin']); ?>
                                            <span class="dashicons dashicons-external"></span>
                                        </a>
                                    </td>
                                    
                                    <td class="amazon-col-title">
                                        <?php if (!empty($product_result['product_id'])): ?>
                                        <a href="<?php echo get_edit_post_link($product_result['product_id']); ?>" 
                                           title="<?php echo esc_attr__('Edit Product', 'amazon-product-importer'); ?>">
                                            <?php echo esc_html($product_result['title']); ?>
                                        </a>
                                        <?php else: ?>
                                        <?php echo esc_html($product_result['title']); ?>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="amazon-col-status">
                                        <span class="amazon-status-badge amazon-status-<?php echo esc_attr($product_result['status']); ?>">
                                            <?php 
                                            switch ($product_result['status']) {
                                                case 'success':
                                                    echo esc_html__('Success', 'amazon-product-importer');
                                                    break;
                                                case 'failed':
                                                    echo esc_html__('Failed', 'amazon-product-importer');
                                                    break;
                                                case 'skipped':
                                                    echo esc_html__('Skipped', 'amazon-product-importer');
                                                    break;
                                                default:
                                                    echo esc_html(ucfirst($product_result['status']));
                                            }
                                            ?>
                                        </span>
                                        
                                        <?php if (!empty($product_result['message'])): ?>
                                        <div class="amazon-status-message">
                                            <?php echo esc_html($product_result['message']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="amazon-col-time">
                                        <?php if (!empty($product_result['processed_time'])): ?>
                                        <span title="<?php echo esc_attr(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($product_result['processed_time']))); ?>">
                                            <?php echo human_time_diff(strtotime($product_result['processed_time']), current_time('timestamp')); ?>
                                            <?php echo esc_html__('ago', 'amazon-product-importer'); ?>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="amazon-col-actions">
                                        <div class="amazon-row-actions">
                                            <?php if ($product_result['status'] === 'failed'): ?>
                                            <button type="button" 
                                                    class="button button-small amazon-retry-btn" 
                                                    data-asin="<?php echo esc_attr($product_result['asin']); ?>"
                                                    title="<?php echo esc_attr__('Retry Import', 'amazon-product-importer'); ?>">
                                                <span class="dashicons dashicons-update"></span>
                                                <?php echo esc_html__('Retry', 'amazon-product-importer'); ?>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <button type="button" 
                                                    class="button button-small amazon-details-btn" 
                                                    data-asin="<?php echo esc_attr($product_result['asin']); ?>"
                                                    title="<?php echo esc_attr__('View Details', 'amazon-product-importer'); ?>">
                                                <span class="dashicons dashicons-info"></span>
                                                <?php echo esc_html__('Details', 'amazon-product-importer'); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <tr class="amazon-no-results">
                                <td colspan="5" class="amazon-no-results-cell">
                                    <div class="amazon-no-results-content">
                                        <span class="dashicons dashicons-info"></span>
                                        <?php echo esc_html__('No products processed yet.', 'amazon-product-importer'); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div id="amazon-results-pagination" class="amazon-pagination" style="display: none;">
                    <!-- Pagination will be populated via JavaScript -->
                </div>
            </div>
        </div>

        <!-- Error Log -->
        <?php if (!empty($batch_data['errors'])): ?>
        <div class="amazon-error-log">
            <div class="amazon-error-log-header">
                <h3><?php echo esc_html__('Error Log', 'amazon-product-importer'); ?></h3>
                <button type="button" class="button button-small amazon-clear-errors">
                    <?php echo esc_html__('Clear Errors', 'amazon-product-importer'); ?>
                </button>
            </div>
            
            <div class="amazon-error-log-content">
                <?php foreach ($batch_data['errors'] as $error): ?>
                <div class="amazon-error-item">
                    <div class="amazon-error-time">
                        <?php echo esc_html(date_i18n(get_option('time_format'), strtotime($error['time']))); ?>
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
        <?php endif; ?>
    </div>
</div>

<!-- Product Details Modal -->
<div id="amazon-product-details-modal" class="amazon-modal" style="display: none;">
    <div class="amazon-modal-content amazon-modal-large">
        <div class="amazon-modal-header">
            <h2><?php echo esc_html__('Product Import Details', 'amazon-product-importer'); ?></h2>
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

<?php
// Helper method to format duration
if (!method_exists($this, 'format_duration')) {
    /**
     * Format duration in seconds to human readable format
     */
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
}
?>