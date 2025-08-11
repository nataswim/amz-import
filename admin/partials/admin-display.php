<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
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

// Get import statistics
$import_stats = $this->get_import_statistics();
$recent_imports = $this->get_recent_imports(5);
?>

<div class="wrap amazon-product-importer">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Amazon Product Importer', 'amazon-product-importer'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=amazon-product-importer-settings'); ?>" class="page-title-action">
        <?php echo esc_html__('Settings', 'amazon-product-importer'); ?>
    </a>
    
    <hr class="wp-header-end">

    <?php settings_errors('amazon_product_importer'); ?>

    <!-- Quick Stats Dashboard -->
    <div class="amazon-stats-dashboard">
        <div class="amazon-stats-grid">
            <div class="amazon-stat-card">
                <div class="amazon-stat-number"><?php echo number_format_i18n($import_stats['total_imports']); ?></div>
                <div class="amazon-stat-label"><?php echo esc_html__('Total Imports', 'amazon-product-importer'); ?></div>
            </div>
            <div class="amazon-stat-card">
                <div class="amazon-stat-number"><?php echo number_format_i18n($import_stats['successful_imports']); ?></div>
                <div class="amazon-stat-label"><?php echo esc_html__('Successful', 'amazon-product-importer'); ?></div>
            </div>
            <div class="amazon-stat-card">
                <div class="amazon-stat-number"><?php echo number_format_i18n($import_stats['failed_imports']); ?></div>
                <div class="amazon-stat-label"><?php echo esc_html__('Failed', 'amazon-product-importer'); ?></div>
            </div>
            <div class="amazon-stat-card">
                <div class="amazon-stat-number"><?php echo number_format_i18n($import_stats['recent_imports']); ?></div>
                <div class="amazon-stat-label"><?php echo esc_html__('Last 24h', 'amazon-product-importer'); ?></div>
            </div>
        </div>
    </div>

    <div class="amazon-import-container">
        <div class="amazon-import-main">
            
            <!-- Search Section -->
            <div class="amazon-search-section">
                <div class="amazon-card">
                    <div class="amazon-card-header">
                        <h2><?php echo esc_html__('Search Amazon Products', 'amazon-product-importer'); ?></h2>
                        <p class="description">
                            <?php echo esc_html__('Search for products on Amazon by keywords or enter a specific ASIN to import directly.', 'amazon-product-importer'); ?>
                        </p>
                    </div>
                    
                    <div class="amazon-card-body">
                        <form id="amazon-search-form" class="amazon-search-form">
                            <div class="amazon-search-controls">
                                <div class="amazon-search-input-group">
                                    <select id="amazon-search-type" name="search_type" class="amazon-search-type">
                                        <option value="keywords"><?php echo esc_html__('Keywords', 'amazon-product-importer'); ?></option>
                                        <option value="asin"><?php echo esc_html__('ASIN', 'amazon-product-importer'); ?></option>
                                    </select>
                                    
                                    <input type="text" 
                                           id="amazon-search-term" 
                                           name="search_term" 
                                           class="amazon-search-input" 
                                           placeholder="<?php echo esc_attr__('Enter keywords or ASIN...', 'amazon-product-importer'); ?>"
                                           required>
                                    
                                    <button type="submit" class="button button-primary amazon-search-btn">
                                        <span class="amazon-search-text"><?php echo esc_html__('Search', 'amazon-product-importer'); ?></span>
                                        <span class="amazon-search-spinner" style="display: none;">
                                            <span class="spinner is-active"></span>
                                            <?php echo esc_html__('Searching...', 'amazon-product-importer'); ?>
                                        </span>
                                    </button>
                                </div>
                                
                                <div class="amazon-search-options">
                                    <label>
                                        <input type="checkbox" id="amazon-search-advanced" class="amazon-search-advanced-toggle">
                                        <?php echo esc_html__('Advanced options', 'amazon-product-importer'); ?>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Advanced Search Options -->
                            <div id="amazon-advanced-options" class="amazon-advanced-options" style="display: none;">
                                <div class="amazon-advanced-grid">
                                    <div class="amazon-advanced-field">
                                        <label for="amazon-search-category">
                                            <?php echo esc_html__('Category', 'amazon-product-importer'); ?>
                                        </label>
                                        <select id="amazon-search-category" name="search_category">
                                            <option value=""><?php echo esc_html__('All Categories', 'amazon-product-importer'); ?></option>
                                            <option value="Electronics"><?php echo esc_html__('Electronics', 'amazon-product-importer'); ?></option>
                                            <option value="Books"><?php echo esc_html__('Books', 'amazon-product-importer'); ?></option>
                                            <option value="Clothing"><?php echo esc_html__('Clothing', 'amazon-product-importer'); ?></option>
                                            <option value="Home"><?php echo esc_html__('Home & Kitchen', 'amazon-product-importer'); ?></option>
                                            <option value="Sports"><?php echo esc_html__('Sports & Outdoors', 'amazon-product-importer'); ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="amazon-advanced-field">
                                        <label for="amazon-min-price">
                                            <?php echo esc_html__('Min Price', 'amazon-product-importer'); ?>
                                        </label>
                                        <input type="number" id="amazon-min-price" name="min_price" min="0" step="0.01">
                                    </div>
                                    
                                    <div class="amazon-advanced-field">
                                        <label for="amazon-max-price">
                                            <?php echo esc_html__('Max Price', 'amazon-product-importer'); ?>
                                        </label>
                                        <input type="number" id="amazon-max-price" name="max_price" min="0" step="0.01">
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Search Results Section -->
            <div id="amazon-search-results-section" class="amazon-search-results-section" style="display: none;">
                <div class="amazon-card">
                    <div class="amazon-card-header">
                        <h2><?php echo esc_html__('Search Results', 'amazon-product-importer'); ?></h2>
                        <div class="amazon-results-actions">
                            <button type="button" class="button amazon-select-all-btn">
                                <?php echo esc_html__('Select All', 'amazon-product-importer'); ?>
                            </button>
                            <button type="button" class="button amazon-deselect-all-btn">
                                <?php echo esc_html__('Deselect All', 'amazon-product-importer'); ?>
                            </button>
                            <button type="button" class="button button-primary amazon-import-selected-btn" disabled>
                                <?php echo esc_html__('Import Selected', 'amazon-product-importer'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="amazon-card-body">
                        <div id="amazon-search-results" class="amazon-search-results">
                            <!-- Results will be loaded here via AJAX -->
                        </div>
                        
                        <!-- Pagination -->
                        <div id="amazon-search-pagination" class="amazon-pagination" style="display: none;">
                            <!-- Pagination will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Import Progress Section -->
            <div id="amazon-import-progress-section" class="amazon-import-progress-section" style="display: none;">
                <div class="amazon-card">
                    <div class="amazon-card-header">
                        <h2><?php echo esc_html__('Import Progress', 'amazon-product-importer'); ?></h2>
                        <button type="button" class="button amazon-cancel-import-btn">
                            <?php echo esc_html__('Cancel Import', 'amazon-product-importer'); ?>
                        </button>
                    </div>
                    
                    <div class="amazon-card-body">
                        <div id="amazon-import-progress" class="amazon-import-progress">
                            <div class="amazon-progress-bar">
                                <div class="amazon-progress-fill"></div>
                            </div>
                            <div class="amazon-progress-text">
                                <span class="amazon-progress-current">0</span> / 
                                <span class="amazon-progress-total">0</span> 
                                <?php echo esc_html__('products processed', 'amazon-product-importer'); ?>
                            </div>
                            <div class="amazon-progress-details">
                                <span class="amazon-progress-success">0 <?php echo esc_html__('successful', 'amazon-product-importer'); ?></span>
                                <span class="amazon-progress-failed">0 <?php echo esc_html__('failed', 'amazon-product-importer'); ?></span>
                            </div>
                        </div>
                        
                        <div id="amazon-import-log" class="amazon-import-log">
                            <!-- Import log will appear here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="amazon-import-sidebar">
            
            <!-- Quick Import -->
            <div class="amazon-card amazon-quick-import">
                <div class="amazon-card-header">
                    <h3><?php echo esc_html__('Quick ASIN Import', 'amazon-product-importer'); ?></h3>
                </div>
                <div class="amazon-card-body">
                    <p class="description">
                        <?php echo esc_html__('Enter an ASIN to import immediately:', 'amazon-product-importer'); ?>
                    </p>
                    <form id="amazon-quick-import-form" class="amazon-quick-import-form">
                        <input type="text" 
                               id="amazon-quick-asin" 
                               name="quick_asin" 
                               placeholder="<?php echo esc_attr__('B01234567X', 'amazon-product-importer'); ?>"
                               pattern="^[A-Z0-9]{10}$"
                               maxlength="10"
                               required>
                        <button type="submit" class="button button-primary button-small">
                            <?php echo esc_html__('Import', 'amazon-product-importer'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Recent Imports -->
            <?php if (!empty($recent_imports)): ?>
            <div class="amazon-card amazon-recent-imports">
                <div class="amazon-card-header">
                    <h3><?php echo esc_html__('Recent Imports', 'amazon-product-importer'); ?></h3>
                    <a href="<?php echo admin_url('admin.php?page=amazon-product-importer-history'); ?>" class="amazon-view-all">
                        <?php echo esc_html__('View All', 'amazon-product-importer'); ?>
                    </a>
                </div>
                <div class="amazon-card-body">
                    <div class="amazon-recent-list">
                        <?php foreach ($recent_imports as $import): ?>
                        <div class="amazon-recent-item">
                            <div class="amazon-recent-asin">
                                <a href="https://amazon.com/dp/<?php echo esc_attr($import->asin); ?>" target="_blank">
                                    <?php echo esc_html($import->asin); ?>
                                </a>
                            </div>
                            <div class="amazon-recent-details">
                                <span class="amazon-recent-status amazon-status-<?php echo esc_attr($import->status); ?>">
                                    <?php echo esc_html(ucfirst($import->status)); ?>
                                </span>
                                <span class="amazon-recent-date">
                                    <?php echo human_time_diff(strtotime($import->import_date), current_time('timestamp')); ?>
                                    <?php echo esc_html__('ago', 'amazon-product-importer'); ?>
                                </span>
                            </div>
                            <?php if ($import->product_id): ?>
                            <div class="amazon-recent-product">
                                <a href="<?php echo get_edit_post_link($import->product_id); ?>">
                                    <?php echo esc_html(get_the_title($import->product_id)); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Help & Tips -->
            <div class="amazon-card amazon-help">
                <div class="amazon-card-header">
                    <h3><?php echo esc_html__('Tips & Help', 'amazon-product-importer'); ?></h3>
                </div>
                <div class="amazon-card-body">
                    <ul class="amazon-tips-list">
                        <li>
                            <strong><?php echo esc_html__('ASIN Format:', 'amazon-product-importer'); ?></strong>
                            <?php echo esc_html__('10 characters (letters and numbers)', 'amazon-product-importer'); ?>
                        </li>
                        <li>
                            <strong><?php echo esc_html__('Keyword Search:', 'amazon-product-importer'); ?></strong>
                            <?php echo esc_html__('Use specific terms for better results', 'amazon-product-importer'); ?>
                        </li>
                        <li>
                            <strong><?php echo esc_html__('Existing Products:', 'amazon-product-importer'); ?></strong>
                            <?php echo esc_html__('Will be updated if imported again', 'amazon-product-importer'); ?>
                        </li>
                        <li>
                            <strong><?php echo esc_html__('API Limits:', 'amazon-product-importer'); ?></strong>
                            <?php echo esc_html__('Be mindful of Amazon API rate limits', 'amazon-product-importer'); ?>
                        </li>
                    </ul>
                    
                    <div class="amazon-help-links">
                        <a href="<?php echo admin_url('admin.php?page=amazon-product-importer-settings'); ?>" class="button button-small">
                            <?php echo esc_html__('Settings', 'amazon-product-importer'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=amazon-product-importer-history'); ?>" class="button button-small">
                            <?php echo esc_html__('Import History', 'amazon-product-importer'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- System Status -->
            <div class="amazon-card amazon-system-status">
                <div class="amazon-card-header">
                    <h3><?php echo esc_html__('System Status', 'amazon-product-importer'); ?></h3>
                </div>
                <div class="amazon-card-body">
                    <div class="amazon-status-list">
                        <div class="amazon-status-item">
                            <span class="amazon-status-label"><?php echo esc_html__('API Connection:', 'amazon-product-importer'); ?></span>
                            <span class="amazon-status-value amazon-status-ok">
                                <?php echo esc_html__('OK', 'amazon-product-importer'); ?>
                            </span>
                        </div>
                        <div class="amazon-status-item">
                            <span class="amazon-status-label"><?php echo esc_html__('WooCommerce:', 'amazon-product-importer'); ?></span>
                            <span class="amazon-status-value amazon-status-ok">
                                <?php echo esc_html__('Active', 'amazon-product-importer'); ?>
                            </span>
                        </div>
                        <div class="amazon-status-item">
                            <span class="amazon-status-label"><?php echo esc_html__('Auto Sync:', 'amazon-product-importer'); ?></span>
                            <span class="amazon-status-value <?php echo get_option('amazon_product_importer_auto_sync_enabled', '1') === '1' ? 'amazon-status-ok' : 'amazon-status-warning'; ?>">
                                <?php echo get_option('amazon_product_importer_auto_sync_enabled', '1') === '1' ? esc_html__('Enabled', 'amazon-product-importer') : esc_html__('Disabled', 'amazon-product-importer'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Product Detail Modal -->
<div id="amazon-product-modal" class="amazon-modal" style="display: none;">
    <div class="amazon-modal-content">
        <div class="amazon-modal-header">
            <h2><?php echo esc_html__('Product Details', 'amazon-product-importer'); ?></h2>
            <button type="button" class="amazon-modal-close">&times;</button>
        </div>
        <div class="amazon-modal-body">
            <!-- Product details will be loaded here -->
        </div>
        <div class="amazon-modal-footer">
            <button type="button" class="button amazon-modal-cancel">
                <?php echo esc_html__('Cancel', 'amazon-product-importer'); ?>
            </button>
            <button type="button" class="button button-primary amazon-modal-import">
                <?php echo esc_html__('Import Product', 'amazon-product-importer'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Import Confirmation Modal -->
<div id="amazon-import-confirm-modal" class="amazon-modal" style="display: none;">
    <div class="amazon-modal-content amazon-modal-small">
        <div class="amazon-modal-header">
            <h2><?php echo esc_html__('Confirm Import', 'amazon-product-importer'); ?></h2>
            <button type="button" class="amazon-modal-close">&times;</button>
        </div>
        <div class="amazon-modal-body">
            <p id="amazon-import-confirm-message"></p>
        </div>
        <div class="amazon-modal-footer">
            <button type="button" class="button amazon-modal-cancel">
                <?php echo esc_html__('Cancel', 'amazon-product-importer'); ?>
            </button>
            <button type="button" class="button button-primary amazon-modal-confirm">
                <?php echo esc_html__('Confirm', 'amazon-product-importer'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Hidden fields for JavaScript -->
<input type="hidden" id="amazon-ajax-url" value="<?php echo admin_url('admin-ajax.php'); ?>">
<input type="hidden" id="amazon-nonce" value="<?php echo wp_create_nonce('amazon_product_importer_nonce'); ?>">