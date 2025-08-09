<?php
/**
 * Provide the import interface view for the plugin
 *
 * This file is used to markup the import-facing aspects of the plugin.
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

// Get import statistics and recent imports
$import_stats = $this->get_import_statistics();
$recent_imports = $this->get_recent_imports(3);
?>

<div class="wrap amazon-product-importer-import">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Import Amazon Products', 'amazon-product-importer'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=amazon-product-importer-settings'); ?>" class="page-title-action">
        <?php echo esc_html__('Settings', 'amazon-product-importer'); ?>
    </a>
    
    <hr class="wp-header-end">

    <?php settings_errors('amazon_product_importer'); ?>

    <div class="amazon-import-layout">
        
        <!-- Main Import Interface -->
        <div class="amazon-import-main">
            
            <!-- Search Interface -->
            <div class="amazon-search-container">
                <div class="amazon-card">
                    <div class="amazon-card-header">
                        <h2><?php echo esc_html__('Search Amazon Products', 'amazon-product-importer'); ?></h2>
                        <p class="description">
                            <?php echo esc_html__('Search for products using keywords or enter a specific ASIN to find exact products.', 'amazon-product-importer'); ?>
                        </p>
                    </div>
                    
                    <div class="amazon-card-body">
                        <form id="amazon-search-form" class="amazon-search-form">
                            <?php wp_nonce_field('amazon_product_importer_nonce', 'amazon_search_nonce'); ?>
                            
                            <div class="amazon-search-row">
                                <div class="amazon-search-type-wrapper">
                                    <label for="amazon-search-type" class="screen-reader-text">
                                        <?php echo esc_html__('Search Type', 'amazon-product-importer'); ?>
                                    </label>
                                    <select id="amazon-search-type" name="search_type" class="amazon-search-type">
                                        <option value="keywords"><?php echo esc_html__('Keywords', 'amazon-product-importer'); ?></option>
                                        <option value="asin"><?php echo esc_html__('ASIN', 'amazon-product-importer'); ?></option>
                                    </select>
                                </div>
                                
                                <div class="amazon-search-input-wrapper">
                                    <label for="amazon-search-term" class="screen-reader-text">
                                        <?php echo esc_html__('Search Term', 'amazon-product-importer'); ?>
                                    </label>
                                    <input type="text" 
                                           id="amazon-search-term" 
                                           name="search_term" 
                                           class="amazon-search-input large-text" 
                                           placeholder="<?php echo esc_attr__('Enter keywords or ASIN...', 'amazon-product-importer'); ?>"
                                           autocomplete="off"
                                           required>
                                    <div id="amazon-search-suggestions" class="amazon-search-suggestions" style="display: none;"></div>
                                </div>
                                
                                <div class="amazon-search-button-wrapper">
                                    <button type="submit" 
                                            id="amazon-search-submit" 
                                            class="button button-primary amazon-search-btn">
                                        <span class="amazon-search-icon dashicons dashicons-search"></span>
                                        <span class="amazon-search-text"><?php echo esc_html__('Search', 'amazon-product-importer'); ?></span>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Advanced Search Options -->
                            <div class="amazon-advanced-toggle">
                                <button type="button" 
                                        id="amazon-advanced-toggle-btn" 
                                        class="button button-link amazon-advanced-toggle-btn">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    <?php echo esc_html__('Advanced Search Options', 'amazon-product-importer'); ?>
                                </button>
                            </div>
                            
                            <div id="amazon-advanced-options" class="amazon-advanced-options" style="display: none;">
                                <div class="amazon-advanced-grid">
                                    <div class="amazon-field-group">
                                        <label for="amazon-search-category">
                                            <?php echo esc_html__('Search Category', 'amazon-product-importer'); ?>
                                        </label>
                                        <select id="amazon-search-category" name="search_category" class="regular-text">
                                            <option value=""><?php echo esc_html__('All Categories', 'amazon-product-importer'); ?></option>
                                            <option value="All"><?php echo esc_html__('All Departments', 'amazon-product-importer'); ?></option>
                                            <option value="Electronics"><?php echo esc_html__('Electronics', 'amazon-product-importer'); ?></option>
                                            <option value="Computers"><?php echo esc_html__('Computers', 'amazon-product-importer'); ?></option>
                                            <option value="Books"><?php echo esc_html__('Books', 'amazon-product-importer'); ?></option>
                                            <option value="Clothing"><?php echo esc_html__('Clothing, Shoes & Jewelry', 'amazon-product-importer'); ?></option>
                                            <option value="HomeGarden"><?php echo esc_html__('Home & Garden', 'amazon-product-importer'); ?></option>
                                            <option value="SportingGoods"><?php echo esc_html__('Sports & Outdoors', 'amazon-product-importer'); ?></option>
                                            <option value="HealthPersonalCare"><?php echo esc_html__('Health & Personal Care', 'amazon-product-importer'); ?></option>
                                            <option value="Tools"><?php echo esc_html__('Tools & Home Improvement', 'amazon-product-importer'); ?></option>
                                            <option value="Automotive"><?php echo esc_html__('Automotive', 'amazon-product-importer'); ?></option>
                                            <option value="Baby"><?php echo esc_html__('Baby', 'amazon-product-importer'); ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="amazon-field-group">
                                        <label for="amazon-min-price">
                                            <?php echo esc_html__('Minimum Price', 'amazon-product-importer'); ?>
                                        </label>
                                        <input type="number" 
                                               id="amazon-min-price" 
                                               name="min_price" 
                                               class="small-text" 
                                               min="0" 
                                               step="0.01"
                                               placeholder="0.00">
                                    </div>
                                    
                                    <div class="amazon-field-group">
                                        <label for="amazon-max-price">
                                            <?php echo esc_html__('Maximum Price', 'amazon-product-importer'); ?>
                                        </label>
                                        <input type="number" 
                                               id="amazon-max-price" 
                                               name="max_price" 
                                               class="small-text" 
                                               min="0" 
                                               step="0.01"
                                               placeholder="999.99">
                                    </div>
                                    
                                    <div class="amazon-field-group">
                                        <label for="amazon-min-reviews">
                                            <?php echo esc_html__('Minimum Reviews', 'amazon-product-importer'); ?>
                                        </label>
                                        <input type="number" 
                                               id="amazon-min-reviews" 
                                               name="min_reviews" 
                                               class="small-text" 
                                               min="0"
                                               placeholder="10">
                                    </div>
                                    
                                    <div class="amazon-field-group">
                                        <label for="amazon-sort-by">
                                            <?php echo esc_html__('Sort Results By', 'amazon-product-importer'); ?>
                                        </label>
                                        <select id="amazon-sort-by" name="sort_by" class="regular-text">
                                            <option value="Relevance"><?php echo esc_html__('Relevance', 'amazon-product-importer'); ?></option>
                                            <option value="Price:LowToHigh"><?php echo esc_html__('Price: Low to High', 'amazon-product-importer'); ?></option>
                                            <option value="Price:HighToLow"><?php echo esc_html__('Price: High to Low', 'amazon-product-importer'); ?></option>
                                            <option value="NewestArrivals"><?php echo esc_html__('Newest Arrivals', 'amazon-product-importer'); ?></option>
                                            <option value="AvgCustomerReviews"><?php echo esc_html__('Customer Reviews', 'amazon-product-importer'); ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="amazon-field-group">
                                        <label for="amazon-results-per-page">
                                            <?php echo esc_html__('Results Per Page', 'amazon-product-importer'); ?>
                                        </label>
                                        <select id="amazon-results-per-page" name="results_per_page" class="regular-text">
                                            <option value="10">10</option>
                                            <option value="20" selected>20</option>
                                            <option value="30">30</option>
                                            <option value="50">50</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Search Results -->
            <div id="amazon-search-results-container" class="amazon-search-results-container" style="display: none;">
                <div class="amazon-card">
                    <div class="amazon-card-header">
                        <div class="amazon-results-header">
                            <h2 id="amazon-results-title"><?php echo esc_html__('Search Results', 'amazon-product-importer'); ?></h2>
                            <div class="amazon-results-count">
                                <span id="amazon-results-info"></span>
                            </div>
                        </div>
                        
                        <div class="amazon-results-actions">
                            <div class="amazon-bulk-actions">
                                <button type="button" 
                                        class="button amazon-select-all-btn"
                                        title="<?php echo esc_attr__('Select all visible products', 'amazon-product-importer'); ?>">
                                    <?php echo esc_html__('Select All', 'amazon-product-importer'); ?>
                                </button>
                                
                                <button type="button" 
                                        class="button amazon-select-none-btn"
                                        title="<?php echo esc_attr__('Deselect all products', 'amazon-product-importer'); ?>">
                                    <?php echo esc_html__('Select None', 'amazon-product-importer'); ?>
                                </button>
                                
                                <button type="button" 
                                        id="amazon-import-selected-btn"
                                        class="button button-primary amazon-import-selected-btn" 
                                        disabled
                                        title="<?php echo esc_attr__('Import selected products', 'amazon-product-importer'); ?>">
                                    <?php echo esc_html__('Import Selected', 'amazon-product-importer'); ?>
                                    <span class="amazon-selected-count">(0)</span>
                                </button>
                            </div>
                            
                            <div class="amazon-view-options">
                                <button type="button" 
                                        class="button amazon-view-toggle active" 
                                        data-view="grid"
                                        title="<?php echo esc_attr__('Grid view', 'amazon-product-importer'); ?>">
                                    <span class="dashicons dashicons-grid-view"></span>
                                </button>
                                <button type="button" 
                                        class="button amazon-view-toggle" 
                                        data-view="list"
                                        title="<?php echo esc_attr__('List view', 'amazon-product-importer'); ?>">
                                    <span class="dashicons dashicons-list-view"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="amazon-card-body">
                        <!-- Loading state -->
                        <div id="amazon-search-loading" class="amazon-loading" style="display: none;">
                            <div class="amazon-loading-spinner">
                                <div class="spinner is-active"></div>
                            </div>
                            <p><?php echo esc_html__('Searching Amazon products...', 'amazon-product-importer'); ?></p>
                        </div>
                        
                        <!-- Results grid/list -->
                        <div id="amazon-search-results" class="amazon-search-results amazon-view-grid">
                            <!-- Results will be populated here via AJAX -->
                        </div>
                        
                        <!-- No results message -->
                        <div id="amazon-no-results" class="amazon-no-results" style="display: none;">
                            <div class="amazon-no-results-icon">
                                <span class="dashicons dashicons-search"></span>
                            </div>
                            <h3><?php echo esc_html__('No products found', 'amazon-product-importer'); ?></h3>
                            <p><?php echo esc_html__('Try adjusting your search terms or using different keywords.', 'amazon-product-importer'); ?></p>
                        </div>
                        
                        <!-- Pagination -->
                        <div id="amazon-pagination" class="amazon-pagination" style="display: none;">
                            <!-- Pagination will be populated here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Import Progress -->
            <div id="amazon-import-progress-container" class="amazon-import-progress-container" style="display: none;">
                <div class="amazon-card">
                    <div class="amazon-card-header">
                        <h2><?php echo esc_html__('Import Progress', 'amazon-product-importer'); ?></h2>
                        <div class="amazon-progress-actions">
                            <button type="button" 
                                    id="amazon-cancel-import-btn" 
                                    class="button amazon-cancel-import-btn">
                                <?php echo esc_html__('Cancel Import', 'amazon-product-importer'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="amazon-card-body">
                        <div id="amazon-import-progress" class="amazon-import-progress">
                            <div class="amazon-progress-bar-container">
                                <div class="amazon-progress-bar">
                                    <div class="amazon-progress-fill"></div>
                                </div>
                                <div class="amazon-progress-percentage">0%</div>
                            </div>
                            
                            <div class="amazon-progress-stats">
                                <div class="amazon-progress-stat">
                                    <span class="amazon-progress-label"><?php echo esc_html__('Progress:', 'amazon-product-importer'); ?></span>
                                    <span class="amazon-progress-current">0</span> / 
                                    <span class="amazon-progress-total">0</span>
                                </div>
                                <div class="amazon-progress-stat amazon-progress-success">
                                    <span class="amazon-progress-label"><?php echo esc_html__('Successful:', 'amazon-product-importer'); ?></span>
                                    <span class="amazon-progress-success-count">0</span>
                                </div>
                                <div class="amazon-progress-stat amazon-progress-failed">
                                    <span class="amazon-progress-label"><?php echo esc_html__('Failed:', 'amazon-product-importer'); ?></span>
                                    <span class="amazon-progress-failed-count">0</span>
                                </div>
                            </div>
                            
                            <div class="amazon-progress-time">
                                <span class="amazon-progress-label"><?php echo esc_html__('Elapsed:', 'amazon-product-importer'); ?></span>
                                <span class="amazon-progress-elapsed">00:00</span>
                                <span class="amazon-progress-label"><?php echo esc_html__('ETA:', 'amazon-product-importer'); ?></span>
                                <span class="amazon-progress-eta">--:--</span>
                            </div>
                        </div>
                        
                        <!-- Import Log -->
                        <div class="amazon-import-log-container">
                            <h3><?php echo esc_html__('Import Log', 'amazon-product-importer'); ?></h3>
                            <div id="amazon-import-log" class="amazon-import-log">
                                <!-- Log entries will be added here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="amazon-import-sidebar">
            
            <!-- Quick ASIN Import -->
            <div class="amazon-card amazon-quick-import-card">
                <div class="amazon-card-header">
                    <h3><?php echo esc_html__('Quick ASIN Import', 'amazon-product-importer'); ?></h3>
                </div>
                <div class="amazon-card-body">
                    <p class="description">
                        <?php echo esc_html__('Enter an Amazon ASIN to import directly without searching.', 'amazon-product-importer'); ?>
                    </p>
                    
                    <form id="amazon-quick-import-form" class="amazon-quick-import-form">
                        <div class="amazon-quick-import-field">
                            <label for="amazon-quick-asin" class="screen-reader-text">
                                <?php echo esc_html__('Amazon ASIN', 'amazon-product-importer'); ?>
                            </label>
                            <input type="text" 
                                   id="amazon-quick-asin" 
                                   name="quick_asin" 
                                   class="regular-text amazon-asin-input"
                                   placeholder="<?php echo esc_attr__('B01234567X', 'amazon-product-importer'); ?>"
                                   pattern="^[A-Z0-9]{10}$"
                                   maxlength="10"
                                   title="<?php echo esc_attr__('ASIN must be 10 characters (letters and numbers)', 'amazon-product-importer'); ?>"
                                   required>
                        </div>
                        
                        <div class="amazon-quick-import-options">
                            <label>
                                <input type="checkbox" 
                                       id="amazon-quick-force-update" 
                                       name="force_update" 
                                       value="1">
                                <?php echo esc_html__('Force update if exists', 'amazon-product-importer'); ?>
                            </label>
                        </div>
                        
                        <button type="submit" class="button button-primary amazon-quick-import-btn">
                            <span class="amazon-quick-import-text"><?php echo esc_html__('Import Now', 'amazon-product-importer'); ?></span>
                            <span class="amazon-quick-import-loading" style="display: none;">
                                <span class="spinner is-active"></span>
                                <?php echo esc_html__('Importing...', 'amazon-product-importer'); ?>
                            </span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Import Statistics -->
            <div class="amazon-card amazon-stats-card">
                <div class="amazon-card-header">
                    <h3><?php echo esc_html__('Import Statistics', 'amazon-product-importer'); ?></h3>
                </div>
                <div class="amazon-card-body">
                    <div class="amazon-stats-grid">
                        <div class="amazon-stat-item">
                            <div class="amazon-stat-number"><?php echo number_format_i18n($import_stats['total_imports']); ?></div>
                            <div class="amazon-stat-label"><?php echo esc_html__('Total Imports', 'amazon-product-importer'); ?></div>
                        </div>
                        <div class="amazon-stat-item">
                            <div class="amazon-stat-number amazon-stat-success"><?php echo number_format_i18n($import_stats['successful_imports']); ?></div>
                            <div class="amazon-stat-label"><?php echo esc_html__('Successful', 'amazon-product-importer'); ?></div>
                        </div>
                        <div class="amazon-stat-item">
                            <div class="amazon-stat-number amazon-stat-failed"><?php echo number_format_i18n($import_stats['failed_imports']); ?></div>
                            <div class="amazon-stat-label"><?php echo esc_html__('Failed', 'amazon-product-importer'); ?></div>
                        </div>
                        <div class="amazon-stat-item">
                            <div class="amazon-stat-number amazon-stat-recent"><?php echo number_format_i18n($import_stats['recent_imports']); ?></div>
                            <div class="amazon-stat-label"><?php echo esc_html__('Last 24h', 'amazon-product-importer'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Imports -->
            <?php if (!empty($recent_imports)): ?>
            <div class="amazon-card amazon-recent-imports-card">
                <div class="amazon-card-header">
                    <h3><?php echo esc_html__('Recent Imports', 'amazon-product-importer'); ?></h3>
                    <a href="<?php echo admin_url('admin.php?page=amazon-product-importer-history'); ?>" 
                       class="amazon-view-all-link">
                        <?php echo esc_html__('View All', 'amazon-product-importer'); ?>
                    </a>
                </div>
                <div class="amazon-card-body">
                    <div class="amazon-recent-imports-list">
                        <?php foreach ($recent_imports as $import): ?>
                        <div class="amazon-recent-import-item">
                            <div class="amazon-recent-import-asin">
                                <a href="https://amazon.com/dp/<?php echo esc_attr($import->asin); ?>" 
                                   target="_blank" 
                                   title="<?php echo esc_attr__('View on Amazon', 'amazon-product-importer'); ?>">
                                    <?php echo esc_html($import->asin); ?>
                                    <span class="dashicons dashicons-external"></span>
                                </a>
                            </div>
                            <div class="amazon-recent-import-meta">
                                <span class="amazon-recent-import-status amazon-status-<?php echo esc_attr($import->status); ?>">
                                    <?php echo esc_html(ucfirst($import->status)); ?>
                                </span>
                                <span class="amazon-recent-import-time">
                                    <?php echo human_time_diff(strtotime($import->import_date), current_time('timestamp')); ?>
                                    <?php echo esc_html__('ago', 'amazon-product-importer'); ?>
                                </span>
                            </div>
                            <?php if ($import->product_id): ?>
                            <div class="amazon-recent-import-product">
                                <a href="<?php echo get_edit_post_link($import->product_id); ?>"
                                   title="<?php echo esc_attr__('Edit Product', 'amazon-product-importer'); ?>">
                                    <?php echo esc_html(wp_trim_words(get_the_title($import->product_id), 6)); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Help & Resources -->
            <div class="amazon-card amazon-help-card">
                <div class="amazon-card-header">
                    <h3><?php echo esc_html__('Help & Resources', 'amazon-product-importer'); ?></h3>
                </div>
                <div class="amazon-card-body">
                    <div class="amazon-help-content">
                        <div class="amazon-help-section">
                            <h4><?php echo esc_html__('ASIN Format', 'amazon-product-importer'); ?></h4>
                            <p><?php echo esc_html__('Amazon Standard Identification Numbers (ASINs) are 10-character alphanumeric codes.', 'amazon-product-importer'); ?></p>
                            <code>Example: B08N5WRWNW</code>
                        </div>
                        
                        <div class="amazon-help-section">
                            <h4><?php echo esc_html__('Search Tips', 'amazon-product-importer'); ?></h4>
                            <ul>
                                <li><?php echo esc_html__('Use specific product names for better results', 'amazon-product-importer'); ?></li>
                                <li><?php echo esc_html__('Include brand names when searching', 'amazon-product-importer'); ?></li>
                                <li><?php echo esc_html__('Try different keyword combinations', 'amazon-product-importer'); ?></li>
                            </ul>
                        </div>
                        
                        <div class="amazon-help-links">
                            <a href="<?php echo admin_url('admin.php?page=amazon-product-importer-settings'); ?>" 
                               class="button button-small">
                                <?php echo esc_html__('Settings', 'amazon-product-importer'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=amazon-product-importer-history'); ?>" 
                               class="button button-small">
                                <?php echo esc_html__('Import History', 'amazon-product-importer'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden fields for JavaScript -->
<script type="text/javascript">
    var amazon_importer_data = {
        ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('amazon_product_importer_nonce'); ?>',
        strings: {
            searching: '<?php echo esc_js(__('Searching Amazon products...', 'amazon-product-importer')); ?>',
            importing: '<?php echo esc_js(__('Importing product...', 'amazon-product-importer')); ?>',
            import_success: '<?php echo esc_js(__('Product imported successfully!', 'amazon-product-importer')); ?>',
            import_error: '<?php echo esc_js(__('Import failed. Please try again.', 'amazon-product-importer')); ?>',
            confirm_import: '<?php echo esc_js(__('Are you sure you want to import this product?', 'amazon-product-importer')); ?>',
            confirm_batch_import: '<?php echo esc_js(__('Import %d selected products?', 'amazon-product-importer')); ?>',
            no_selection: '<?php echo esc_js(__('Please select at least one product to import.', 'amazon-product-importer')); ?>',
            cancel_import: '<?php echo esc_js(__('Are you sure you want to cancel the import?', 'amazon-product-importer')); ?>',
            product_exists: '<?php echo esc_js(__('This product already exists. Force update?', 'amazon-product-importer')); ?>'
        }
    };
</script>