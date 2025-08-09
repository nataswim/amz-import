<?php
/**
 * Provide the search results view for Amazon products
 *
 * This file displays the search results from Amazon API
 * with import options and product details.
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

// Default values if not set
$products = isset($products) ? $products : array();
$total_results = isset($total_results) ? $total_results : 0;
$current_page = isset($current_page) ? $current_page : 1;
$total_pages = isset($total_pages) ? $total_pages : 1;
$search_term = isset($search_term) ? $search_term : '';
$view_mode = isset($view_mode) ? $view_mode : 'grid';
?>

<?php if (empty($products)): ?>
    <!-- No Results -->
    <div class="amazon-no-search-results">
        <div class="amazon-no-results-icon">
            <span class="dashicons dashicons-search"></span>
        </div>
        <h3><?php echo esc_html__('No products found', 'amazon-product-importer'); ?></h3>
        <p>
            <?php 
            printf(
                esc_html__('No products found for "%s". Try different keywords or check your spelling.', 'amazon-product-importer'),
                esc_html($search_term)
            ); 
            ?>
        </p>
        <div class="amazon-search-suggestions">
            <h4><?php echo esc_html__('Search Tips:', 'amazon-product-importer'); ?></h4>
            <ul>
                <li><?php echo esc_html__('Use more specific keywords', 'amazon-product-importer'); ?></li>
                <li><?php echo esc_html__('Check for typos in your search terms', 'amazon-product-importer'); ?></li>
                <li><?php echo esc_html__('Try broader terms or synonyms', 'amazon-product-importer'); ?></li>
                <li><?php echo esc_html__('Use brand names when searching', 'amazon-product-importer'); ?></li>
            </ul>
        </div>
    </div>

<?php else: ?>
    <!-- Results Header -->
    <div class="amazon-search-results-header">
        <div class="amazon-results-info">
            <span class="amazon-results-count">
                <?php 
                printf(
                    _n(
                        '%s product found',
                        '%s products found',
                        $total_results,
                        'amazon-product-importer'
                    ),
                    number_format_i18n($total_results)
                );
                ?>
            </span>
            <?php if (!empty($search_term)): ?>
            <span class="amazon-search-term">
                <?php echo esc_html__('for', 'amazon-product-importer'); ?> 
                "<strong><?php echo esc_html($search_term); ?></strong>"
            </span>
            <?php endif; ?>
        </div>
        
        <div class="amazon-results-meta">
            <span class="amazon-page-info">
                <?php 
                printf(
                    esc_html__('Page %d of %d', 'amazon-product-importer'),
                    $current_page,
                    $total_pages
                );
                ?>
            </span>
        </div>
    </div>

    <!-- Products Grid/List -->
    <div class="amazon-products-container amazon-view-<?php echo esc_attr($view_mode); ?>">
        <?php foreach ($products as $index => $product): ?>
        <div class="amazon-product-item" 
             data-asin="<?php echo esc_attr($product['asin']); ?>"
             data-index="<?php echo esc_attr($index); ?>">
             
            <!-- Selection Checkbox -->
            <div class="amazon-product-checkbox">
                <input type="checkbox" 
                       class="amazon-product-select" 
                       name="selected_products[]" 
                       value="<?php echo esc_attr($product['asin']); ?>"
                       id="product_<?php echo esc_attr($product['asin']); ?>">
                <label for="product_<?php echo esc_attr($product['asin']); ?>" class="screen-reader-text">
                    <?php echo esc_html__('Select this product', 'amazon-product-importer'); ?>
                </label>
            </div>

            <!-- Product Image -->
            <div class="amazon-product-image">
                <?php if (!empty($product['image'])): ?>
                <img src="<?php echo esc_url($product['image']); ?>" 
                     alt="<?php echo esc_attr($product['title']); ?>"
                     loading="lazy"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <?php endif; ?>
                
                <div class="amazon-image-placeholder" <?php echo !empty($product['image']) ? 'style="display:none;"' : ''; ?>>
                    <span class="dashicons dashicons-format-image"></span>
                    <span><?php echo esc_html__('No Image', 'amazon-product-importer'); ?></span>
                </div>
                
                <!-- Product Status Indicators -->
                <div class="amazon-product-indicators">
                    <?php if ($product['exists_locally']): ?>
                    <span class="amazon-indicator amazon-exists-indicator" 
                          title="<?php echo esc_attr__('Product already exists in your store', 'amazon-product-importer'); ?>">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php echo esc_html__('Exists', 'amazon-product-importer'); ?>
                    </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($product['prime'])): ?>
                    <span class="amazon-indicator amazon-prime-indicator" 
                          title="<?php echo esc_attr__('Amazon Prime eligible', 'amazon-product-importer'); ?>">
                        <span class="dashicons dashicons-star-filled"></span>
                        Prime
                    </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($product['bestseller'])): ?>
                    <span class="amazon-indicator amazon-bestseller-indicator" 
                          title="<?php echo esc_attr__('Amazon Best Seller', 'amazon-product-importer'); ?>">
                        <span class="dashicons dashicons-awards"></span>
                        <?php echo esc_html__('Best Seller', 'amazon-product-importer'); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Product Details -->
            <div class="amazon-product-details">
                
                <!-- Product Title -->
                <h3 class="amazon-product-title">
                    <a href="<?php echo esc_url($product['url']); ?>" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       title="<?php echo esc_attr__('View on Amazon', 'amazon-product-importer'); ?>">
                        <?php echo esc_html($product['title']); ?>
                        <span class="dashicons dashicons-external"></span>
                    </a>
                </h3>

                <!-- ASIN -->
                <div class="amazon-product-asin">
                    <span class="amazon-label"><?php echo esc_html__('ASIN:', 'amazon-product-importer'); ?></span>
                    <span class="amazon-value amazon-asin-value"><?php echo esc_html($product['asin']); ?></span>
                    <button type="button" 
                            class="amazon-copy-asin" 
                            data-asin="<?php echo esc_attr($product['asin']); ?>"
                            title="<?php echo esc_attr__('Copy ASIN', 'amazon-product-importer'); ?>">
                        <span class="dashicons dashicons-admin-page"></span>
                    </button>
                </div>

                <!-- Brand -->
                <?php if (!empty($product['brand'])): ?>
                <div class="amazon-product-brand">
                    <span class="amazon-label"><?php echo esc_html__('Brand:', 'amazon-product-importer'); ?></span>
                    <span class="amazon-value"><?php echo esc_html($product['brand']); ?></span>
                </div>
                <?php endif; ?>

                <!-- Price -->
                <?php if (!empty($product['price'])): ?>
                <div class="amazon-product-price">
                    <?php if (!empty($product['original_price']) && $product['original_price'] !== $product['price']): ?>
                    <span class="amazon-price-original"><?php echo esc_html($product['original_price']); ?></span>
                    <?php endif; ?>
                    <span class="amazon-price-current"><?php echo esc_html($product['price']); ?></span>
                    <?php if (!empty($product['savings'])): ?>
                    <span class="amazon-price-savings">
                        <?php echo esc_html($product['savings']); ?> 
                        <?php echo esc_html__('off', 'amazon-product-importer'); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Rating -->
                <?php if (!empty($product['rating'])): ?>
                <div class="amazon-product-rating">
                    <div class="amazon-rating-stars">
                        <?php
                        $rating = floatval($product['rating']);
                        $full_stars = floor($rating);
                        $half_star = ($rating - $full_stars) >= 0.5;
                        $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
                        
                        // Full stars
                        for ($i = 0; $i < $full_stars; $i++) {
                            echo '<span class="dashicons dashicons-star-filled"></span>';
                        }
                        
                        // Half star
                        if ($half_star) {
                            echo '<span class="dashicons dashicons-star-half"></span>';
                        }
                        
                        // Empty stars
                        for ($i = 0; $i < $empty_stars; $i++) {
                            echo '<span class="dashicons dashicons-star-empty"></span>';
                        }
                        ?>
                    </div>
                    <span class="amazon-rating-value"><?php echo esc_html($product['rating']); ?></span>
                    <?php if (!empty($product['review_count'])): ?>
                    <span class="amazon-review-count">
                        (<?php echo number_format_i18n($product['review_count']); ?>)
                    </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Features (List View) -->
                <?php if ($view_mode === 'list' && !empty($product['features'])): ?>
                <div class="amazon-product-features">
                    <ul class="amazon-features-list">
                        <?php foreach (array_slice($product['features'], 0, 3) as $feature): ?>
                        <li><?php echo esc_html($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Availability -->
                <?php if (!empty($product['availability'])): ?>
                <div class="amazon-product-availability">
                    <span class="amazon-availability-status amazon-availability-<?php echo esc_attr(strtolower($product['availability_status'])); ?>">
                        <?php echo esc_html($product['availability']); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Product Actions -->
            <div class="amazon-product-actions">
                
                <!-- Primary Actions -->
                <div class="amazon-primary-actions">
                    <?php if ($product['exists_locally']): ?>
                    <button type="button" 
                            class="button button-secondary amazon-update-product-btn" 
                            data-asin="<?php echo esc_attr($product['asin']); ?>"
                            title="<?php echo esc_attr__('Update existing product', 'amazon-product-importer'); ?>">
                        <span class="dashicons dashicons-update"></span>
                        <?php echo esc_html__('Update', 'amazon-product-importer'); ?>
                    </button>
                    <?php else: ?>
                    <button type="button" 
                            class="button button-primary amazon-import-product-btn" 
                            data-asin="<?php echo esc_attr($product['asin']); ?>"
                            title="<?php echo esc_attr__('Import this product', 'amazon-product-importer'); ?>">
                        <span class="dashicons dashicons-download"></span>
                        <?php echo esc_html__('Import', 'amazon-product-importer'); ?>
                    </button>
                    <?php endif; ?>
                    
                    <button type="button" 
                            class="button button-secondary amazon-preview-product-btn" 
                            data-asin="<?php echo esc_attr($product['asin']); ?>"
                            title="<?php echo esc_attr__('Preview product details', 'amazon-product-importer'); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php echo esc_html__('Preview', 'amazon-product-importer'); ?>
                    </button>
                </div>

                <!-- Secondary Actions -->
                <div class="amazon-secondary-actions">
                    <div class="amazon-action-menu">
                        <button type="button" 
                                class="button button-small amazon-action-menu-toggle"
                                title="<?php echo esc_attr__('More actions', 'amazon-product-importer'); ?>">
                            <span class="dashicons dashicons-ellipsis"></span>
                        </button>
                        
                        <div class="amazon-action-menu-content" style="display: none;">
                            <a href="<?php echo esc_url($product['url']); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="amazon-action-item">
                                <span class="dashicons dashicons-external"></span>
                                <?php echo esc_html__('View on Amazon', 'amazon-product-importer'); ?>
                            </a>
                            
                            <?php if ($product['exists_locally']): ?>
                            <a href="<?php echo get_edit_post_link($product['local_product_id']); ?>" 
                               class="amazon-action-item">
                                <span class="dashicons dashicons-edit"></span>
                                <?php echo esc_html__('Edit Local Product', 'amazon-product-importer'); ?>
                            </a>
                            <?php endif; ?>
                            
                            <button type="button" 
                                    class="amazon-action-item amazon-add-to-wishlist-btn"
                                    data-asin="<?php echo esc_attr($product['asin']); ?>">
                                <span class="dashicons dashicons-heart"></span>
                                <?php echo esc_html__('Add to Wishlist', 'amazon-product-importer'); ?>
                            </button>
                            
                            <button type="button" 
                                    class="amazon-action-item amazon-compare-btn"
                                    data-asin="<?php echo esc_attr($product['asin']); ?>">
                                <span class="dashicons dashicons-image-flip-horizontal"></span>
                                <?php echo esc_html__('Add to Compare', 'amazon-product-importer'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Import Status (Hidden by default) -->
            <div class="amazon-import-status" style="display: none;">
                <div class="amazon-import-progress">
                    <div class="amazon-import-spinner">
                        <span class="spinner is-active"></span>
                    </div>
                    <div class="amazon-import-message">
                        <?php echo esc_html__('Importing...', 'amazon-product-importer'); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="amazon-search-pagination">
        <div class="amazon-pagination-info">
            <?php 
            $start = (($current_page - 1) * 10) + 1;
            $end = min($current_page * 10, $total_results);
            printf(
                esc_html__('Showing %d-%d of %d results', 'amazon-product-importer'),
                $start,
                $end,
                $total_results
            );
            ?>
        </div>
        
        <div class="amazon-pagination-controls">
            <?php if ($current_page > 1): ?>
            <button type="button" 
                    class="button amazon-page-btn amazon-prev-page" 
                    data-page="<?php echo esc_attr($current_page - 1); ?>"
                    title="<?php echo esc_attr__('Previous page', 'amazon-product-importer'); ?>">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php echo esc_html__('Previous', 'amazon-product-importer'); ?>
            </button>
            <?php endif; ?>
            
            <div class="amazon-page-numbers">
                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                if ($start_page > 1): ?>
                <button type="button" 
                        class="button amazon-page-btn" 
                        data-page="1">1</button>
                <?php if ($start_page > 2): ?>
                <span class="amazon-pagination-dots">...</span>
                <?php endif; ?>
                <?php endif;
                
                for ($i = $start_page; $i <= $end_page; $i++): ?>
                <button type="button" 
                        class="button amazon-page-btn <?php echo $i === $current_page ? 'amazon-current-page' : ''; ?>" 
                        data-page="<?php echo esc_attr($i); ?>"
                        <?php echo $i === $current_page ? 'disabled' : ''; ?>>
                    <?php echo esc_html($i); ?>
                </button>
                <?php endfor;
                
                if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                <span class="amazon-pagination-dots">...</span>
                <?php endif; ?>
                <button type="button" 
                        class="button amazon-page-btn" 
                        data-page="<?php echo esc_attr($total_pages); ?>">
                    <?php echo esc_html($total_pages); ?>
                </button>
                <?php endif; ?>
            </div>
            
            <?php if ($current_page < $total_pages): ?>
            <button type="button" 
                    class="button amazon-page-btn amazon-next-page" 
                    data-page="<?php echo esc_attr($current_page + 1); ?>"
                    title="<?php echo esc_attr__('Next page', 'amazon-product-importer'); ?>">
                <?php echo esc_html__('Next', 'amazon-product-importer'); ?>
                <span class="dashicons dashicons-arrow-right-alt2"></span>
            </button>
            <?php endif; ?>
        </div>
        
        <div class="amazon-pagination-jump">
            <label for="amazon-jump-to-page"><?php echo esc_html__('Go to page:', 'amazon-product-importer'); ?></label>
            <input type="number" 
                   id="amazon-jump-to-page" 
                   class="small-text" 
                   min="1" 
                   max="<?php echo esc_attr($total_pages); ?>" 
                   value="<?php echo esc_attr($current_page); ?>">
            <button type="button" 
                    class="button button-small amazon-jump-btn">
                <?php echo esc_html__('Go', 'amazon-product-importer'); ?>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bulk Actions Footer -->
    <div class="amazon-bulk-actions-footer">
        <div class="amazon-selected-info">
            <span id="amazon-selected-count">0</span>
            <?php echo esc_html__('products selected', 'amazon-product-importer'); ?>
        </div>
        
        <div class="amazon-bulk-actions-controls">
            <button type="button" 
                    id="amazon-bulk-import-btn" 
                    class="button button-primary" 
                    disabled>
                <span class="dashicons dashicons-download"></span>
                <?php echo esc_html__('Import Selected', 'amazon-product-importer'); ?>
            </button>
            
            <button type="button" 
                    id="amazon-bulk-preview-btn" 
                    class="button button-secondary" 
                    disabled>
                <span class="dashicons dashicons-visibility"></span>
                <?php echo esc_html__('Preview Selected', 'amazon-product-importer'); ?>
            </button>
            
            <button type="button" 
                    id="amazon-bulk-compare-btn" 
                    class="button button-secondary" 
                    disabled>
                <span class="dashicons dashicons-image-flip-horizontal"></span>
                <?php echo esc_html__('Compare Selected', 'amazon-product-importer'); ?>
            </button>
        </div>
    </div>
<?php endif; ?>

<script type="text/javascript">
    // Product search results data
    var amazon_search_results_data = {
        total_results: <?php echo intval($total_results); ?>,
        current_page: <?php echo intval($current_page); ?>,
        total_pages: <?php echo intval($total_pages); ?>,
        products_count: <?php echo count($products); ?>,
        view_mode: '<?php echo esc_js($view_mode); ?>'
    };
</script>