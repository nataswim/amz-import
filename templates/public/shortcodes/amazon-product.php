<?php
/**
 * Amazon Product Shortcode Template
 *
 * This template displays an Amazon product using the [amazon_product] shortcode.
 * It can be overridden by copying it to yourtheme/amazon-product-importer/shortcodes/amazon-product.php
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/templates/public/shortcodes
 * @since      1.0.0
 * 
 * @var WC_Product $product  WooCommerce product object
 * @var array      $atts     Shortcode attributes
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Bail if no product
if ( ! $product || ! $product->exists() ) {
    return;
}

// Get Amazon-specific data
$asin = get_post_meta( $product->get_id(), '_amazon_asin', true );
$region = get_post_meta( $product->get_id(), '_amazon_region', true ) ?: 'com';
$brand = get_post_meta( $product->get_id(), '_amazon_brand', true );
$availability = get_post_meta( $product->get_id(), '_amazon_availability', true );
$last_updated = get_post_meta( $product->get_id(), '_amazon_price_updated', true );
$amazon_rating = get_post_meta( $product->get_id(), '_amazon_rating', true );
$amazon_reviews_count = get_post_meta( $product->get_id(), '_amazon_reviews_count', true );

// Shortcode attributes with defaults
$template = $atts['template'] ?? 'default';
$show_price = filter_var( $atts['show_price'] ?? true, FILTER_VALIDATE_BOOLEAN );
$show_button = filter_var( $atts['show_button'] ?? true, FILTER_VALIDATE_BOOLEAN );
$show_image = filter_var( $atts['show_image'] ?? true, FILTER_VALIDATE_BOOLEAN );
$show_description = filter_var( $atts['show_description'] ?? true, FILTER_VALIDATE_BOOLEAN );
$image_size = $atts['image_size'] ?? 'medium';
$target = $atts['target'] ?? '_blank';
$custom_class = $atts['class'] ?? '';

// Get product data
$product_id = $product->get_id();
$product_title = $product->get_name();
$product_permalink = get_permalink( $product_id );
$product_description = $product->get_short_description();
$product_image_id = $product->get_image_id();
$product_gallery_ids = $product->get_gallery_image_ids();

// Build Amazon affiliate URL
$amazon_url = '';
if ( $asin ) {
    $associate_tag = get_option( 'amazon_product_importer_options' )['associate_tag'] ?? '';
    $amazon_url = "https://www.amazon.{$region}/dp/{$asin}";
    if ( $associate_tag ) {
        $amazon_url .= "?tag=" . urlencode( $associate_tag );
    }
}

// CSS classes
$wrapper_classes = array(
    'amazon-product-shortcode',
    'amazon-product-' . $template,
    'product-' . $product_id,
    $custom_class
);

// Add variation class if applicable
if ( $product->is_type( 'variable' ) ) {
    $wrapper_classes[] = 'amazon-product-variable';
}

// Add availability class
if ( $availability ) {
    $wrapper_classes[] = 'availability-' . sanitize_html_class( strtolower( str_replace( ' ', '-', $availability ) ) );
}

$wrapper_classes = implode( ' ', array_filter( $wrapper_classes ) );

// Schema.org structured data
$schema_data = array(
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product_title,
    'description' => wp_strip_all_tags( $product_description ),
    'sku' => $product->get_sku(),
    'gtin' => $asin,
    'brand' => array(
        '@type' => 'Brand',
        'name' => $brand ?: 'Amazon'
    ),
    'offers' => array(
        '@type' => 'Offer',
        'price' => $product->get_price(),
        'priceCurrency' => get_woocommerce_currency(),
        'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url' => $amazon_url ?: $product_permalink,
        'seller' => array(
            '@type' => 'Organization',
            'name' => 'Amazon'
        )
    )
);

if ( $product_image_id ) {
    $image_url = wp_get_attachment_image_url( $product_image_id, 'large' );
    if ( $image_url ) {
        $schema_data['image'] = $image_url;
    }
}

if ( $amazon_rating && $amazon_reviews_count ) {
    $schema_data['aggregateRating'] = array(
        '@type' => 'AggregateRating',
        'ratingValue' => $amazon_rating,
        'reviewCount' => $amazon_reviews_count,
        'bestRating' => '5',
        'worstRating' => '1'
    );
}
?>

<div class="<?php echo esc_attr( $wrapper_classes ); ?>" 
     data-product-id="<?php echo esc_attr( $product_id ); ?>"
     data-amazon-asin="<?php echo esc_attr( $asin ); ?>"
     data-amazon-region="<?php echo esc_attr( $region ); ?>"
     itemscope 
     itemtype="https://schema.org/Product">
    
    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
        <?php echo wp_json_encode( $schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>
    </script>

    <!-- Amazon Badge -->
    <div class="amazon-product-badge-wrapper">
        <span class="amazon-product-badge" title="<?php esc_attr_e( 'Produit importé d\'Amazon', 'amazon-product-importer' ); ?>">
            <?php _e( 'Amazon', 'amazon-product-importer' ); ?>
        </span>
        <?php if ( $availability ): ?>
            <span class="amazon-availability-badge availability-<?php echo esc_attr( sanitize_html_class( strtolower( str_replace( ' ', '-', $availability ) ) ) ); ?>">
                <?php echo esc_html( $availability ); ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="amazon-product-content">
        
        <!-- Product Image -->
        <?php if ( $show_image && $product_image_id ): ?>
        <div class="amazon-product-image-wrapper">
            <?php if ( $amazon_url ): ?>
                <a href="<?php echo esc_url( $amazon_url ); ?>" 
                   target="<?php echo esc_attr( $target ); ?>" 
                   rel="nofollow noopener" 
                   class="amazon-product-image-link"
                   aria-label="<?php echo esc_attr( sprintf( __( 'Voir %s sur Amazon', 'amazon-product-importer' ), $product_title ) ); ?>">
            <?php endif; ?>
            
            <?php
            echo wp_get_attachment_image( 
                $product_image_id, 
                $image_size, 
                false, 
                array(
                    'class' => 'amazon-product-image',
                    'alt' => $product_title,
                    'itemprop' => 'image',
                    'loading' => 'lazy'
                )
            );
            ?>
            
            <?php if ( $amazon_url ): ?>
                </a>
            <?php endif; ?>

            <!-- Image Gallery (for hover effect or lightbox) -->
            <?php if ( ! empty( $product_gallery_ids ) && count( $product_gallery_ids ) > 0 ): ?>
            <div class="amazon-product-gallery-thumbs" style="display: none;">
                <?php foreach ( array_slice( $product_gallery_ids, 0, 3 ) as $gallery_image_id ): ?>
                    <img src="<?php echo esc_url( wp_get_attachment_image_url( $gallery_image_id, 'thumbnail' ) ); ?>" 
                         alt="<?php echo esc_attr( $product_title ); ?>" 
                         class="amazon-gallery-thumb" 
                         loading="lazy" />
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Product Information -->
        <div class="amazon-product-info">
            
            <!-- Product Title -->
            <h3 class="amazon-product-title" itemprop="name">
                <?php if ( $amazon_url ): ?>
                    <a href="<?php echo esc_url( $amazon_url ); ?>" 
                       target="<?php echo esc_attr( $target ); ?>" 
                       rel="nofollow noopener" 
                       class="amazon-product-title-link">
                        <?php echo esc_html( $product_title ); ?>
                    </a>
                <?php else: ?>
                    <?php echo esc_html( $product_title ); ?>
                <?php endif; ?>
            </h3>

            <!-- Brand -->
            <?php if ( $brand ): ?>
            <div class="amazon-product-brand">
                <span class="amazon-brand-label"><?php _e( 'Marque:', 'amazon-product-importer' ); ?></span>
                <span class="amazon-brand-name" itemprop="brand"><?php echo esc_html( $brand ); ?></span>
            </div>
            <?php endif; ?>

            <!-- Rating and Reviews -->
            <?php if ( $amazon_rating && $amazon_reviews_count ): ?>
            <div class="amazon-product-rating" itemprop="aggregateRating" itemscope itemtype="https://schema.org/AggregateRating">
                <div class="amazon-stars" aria-label="<?php echo esc_attr( sprintf( __( '%s étoiles sur 5', 'amazon-product-importer' ), $amazon_rating ) ); ?>">
                    <?php
                    $full_stars = floor( $amazon_rating );
                    $half_star = ( $amazon_rating - $full_stars ) >= 0.5;
                    
                    for ( $i = 1; $i <= 5; $i++ ) {
                        if ( $i <= $full_stars ) {
                            echo '<span class="amazon-star amazon-star-full">★</span>';
                        } elseif ( $i == $full_stars + 1 && $half_star ) {
                            echo '<span class="amazon-star amazon-star-half">★</span>';
                        } else {
                            echo '<span class="amazon-star amazon-star-empty">☆</span>';
                        }
                    }
                    ?>
                </div>
                <div class="amazon-rating-details">
                    <span class="amazon-rating-value" itemprop="ratingValue"><?php echo esc_html( $amazon_rating ); ?></span>
                    <span class="amazon-rating-separator">/</span>
                    <span class="amazon-rating-scale" itemprop="bestRating">5</span>
                    <span class="amazon-reviews-count">
                        (<?php echo number_format_i18n( $amazon_reviews_count ); ?> <?php _e( 'avis', 'amazon-product-importer' ); ?>)
                    </span>
                    <meta itemprop="reviewCount" content="<?php echo esc_attr( $amazon_reviews_count ); ?>" />
                </div>
            </div>
            <?php endif; ?>

            <!-- Product Description -->
            <?php if ( $show_description && $product_description ): ?>
            <div class="amazon-product-description" itemprop="description">
                <?php echo wp_kses_post( wpautop( $product_description ) ); ?>
            </div>
            <?php endif; ?>

            <!-- Product Features -->
            <?php
            $features = get_post_meta( $product_id, '_amazon_features', true );
            if ( $features && is_array( $features ) && ! empty( $features ) ):
            ?>
            <div class="amazon-product-features">
                <h4 class="amazon-features-title"><?php _e( 'Caractéristiques principales:', 'amazon-product-importer' ); ?></h4>
                <ul class="amazon-features-list">
                    <?php foreach ( array_slice( $features, 0, 5 ) as $feature ): ?>
                        <li class="amazon-feature-item"><?php echo esc_html( $feature ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Product Variations -->
            <?php if ( $product->is_type( 'variable' ) ): ?>
            <div class="amazon-product-variations">
                <?php
                $variations = $product->get_available_variations();
                if ( ! empty( $variations ) ):
                ?>
                <div class="amazon-variations-selector">
                    <label for="amazon-variation-select-<?php echo esc_attr( $product_id ); ?>">
                        <?php _e( 'Choisir une option:', 'amazon-product-importer' ); ?>
                    </label>
                    <select id="amazon-variation-select-<?php echo esc_attr( $product_id ); ?>" 
                            class="amazon-variation-select" 
                            data-product-id="<?php echo esc_attr( $product_id ); ?>">
                        <option value=""><?php _e( 'Sélectionner...', 'amazon-product-importer' ); ?></option>
                        <?php foreach ( $variations as $variation ): ?>
                            <?php
                            $variation_obj = wc_get_product( $variation['variation_id'] );
                            if ( ! $variation_obj ) continue;
                            
                            $variation_attributes = array();
                            foreach ( $variation['attributes'] as $attr_name => $attr_value ) {
                                if ( $attr_value ) {
                                    $variation_attributes[] = $attr_value;
                                }
                            }
                            $variation_label = implode( ', ', $variation_attributes );
                            ?>
                            <option value="<?php echo esc_attr( $variation['variation_id'] ); ?>"
                                    data-price="<?php echo esc_attr( $variation_obj->get_price() ); ?>">
                                <?php echo esc_html( $variation_label ); ?>
                                <?php if ( $variation['price_html'] ): ?>
                                    - <?php echo wp_kses_post( $variation['price_html'] ); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Price Display -->
            <?php if ( $show_price ): ?>
            <div class="amazon-product-price-wrapper" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
                <div class="amazon-product-price">
                    <?php
                    $regular_price = $product->get_regular_price();
                    $sale_price = $product->get_sale_price();
                    $current_price = $product->get_price();
                    ?>
                    
                    <?php if ( $sale_price && $sale_price < $regular_price ): ?>
                        <span class="amazon-price-sale">
                            <span class="amazon-price-current" itemprop="price"><?php echo wc_price( $current_price ); ?></span>
                            <span class="amazon-price-regular"><?php echo wc_price( $regular_price ); ?></span>
                        </span>
                        <span class="amazon-price-savings">
                            <?php 
                            $savings = $regular_price - $sale_price;
                            $savings_percent = round( ( $savings / $regular_price ) * 100 );
                            printf( 
                                __( 'Économisez %s (%s%%)', 'amazon-product-importer' ), 
                                wc_price( $savings ), 
                                $savings_percent 
                            ); 
                            ?>
                        </span>
                    <?php else: ?>
                        <span class="amazon-price-current" itemprop="price"><?php echo wc_price( $current_price ); ?></span>
                    <?php endif; ?>
                    
                    <meta itemprop="priceCurrency" content="<?php echo esc_attr( get_woocommerce_currency() ); ?>" />
                    <meta itemprop="availability" content="<?php echo $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'; ?>" />
                </div>

                <!-- Price Update Info -->
                <?php if ( $last_updated ): ?>
                <div class="amazon-price-updated">
                    <small>
                        <?php 
                        printf( 
                            __( 'Prix mis à jour le %s', 'amazon-product-importer' ), 
                            date_i18n( get_option( 'date_format' ), strtotime( $last_updated ) )
                        ); 
                        ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Stock Status -->
            <div class="amazon-stock-status">
                <?php if ( $product->is_in_stock() ): ?>
                    <span class="amazon-in-stock">
                        <span class="amazon-stock-icon">✓</span>
                        <?php _e( 'En stock', 'amazon-product-importer' ); ?>
                    </span>
                <?php else: ?>
                    <span class="amazon-out-of-stock">
                        <span class="amazon-stock-icon">⚠</span>
                        <?php _e( 'Temporairement en rupture de stock', 'amazon-product-importer' ); ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Call to Action -->
            <?php if ( $show_button && $amazon_url ): ?>
            <div class="amazon-product-cta">
                <a href="<?php echo esc_url( $amazon_url ); ?>" 
                   target="<?php echo esc_attr( $target ); ?>" 
                   rel="nofollow noopener" 
                   class="amazon-buy-button"
                   data-asin="<?php echo esc_attr( $asin ); ?>"
                   data-product-id="<?php echo esc_attr( $product_id ); ?>">
                    <span class="amazon-button-text"><?php _e( 'Voir sur Amazon', 'amazon-product-importer' ); ?></span>
                    <span class="amazon-button-icon">→</span>
                </a>
                
                <!-- Alternative buttons -->
                <div class="amazon-secondary-actions">
                    <button type="button" 
                            class="amazon-action-button amazon-add-to-wishlist" 
                            data-product-id="<?php echo esc_attr( $product_id ); ?>"
                            title="<?php esc_attr_e( 'Ajouter à la liste de souhaits', 'amazon-product-importer' ); ?>">
                        <span class="amazon-action-icon">♡</span>
                        <span class="amazon-action-text"><?php _e( 'Favoris', 'amazon-product-importer' ); ?></span>
                    </button>

                    <button type="button" 
                            class="amazon-action-button amazon-share-product" 
                            data-product-id="<?php echo esc_attr( $product_id ); ?>"
                            data-product-title="<?php echo esc_attr( $product_title ); ?>"
                            data-product-url="<?php echo esc_attr( $amazon_url ); ?>"
                            title="<?php esc_attr_e( 'Partager ce produit', 'amazon-product-importer' ); ?>">
                        <span class="amazon-action-icon">↗</span>
                        <span class="amazon-action-text"><?php _e( 'Partager', 'amazon-product-importer' ); ?></span>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Product Meta Information -->
            <div class="amazon-product-meta">
                <div class="amazon-meta-item">
                    <span class="amazon-meta-label"><?php _e( 'ASIN:', 'amazon-product-importer' ); ?></span>
                    <span class="amazon-meta-value amazon-asin"><?php echo esc_html( $asin ); ?></span>
                </div>
                
                <?php if ( $product->get_sku() ): ?>
                <div class="amazon-meta-item">
                    <span class="amazon-meta-label"><?php _e( 'SKU:', 'amazon-product-importer' ); ?></span>
                    <span class="amazon-meta-value"><?php echo esc_html( $product->get_sku() ); ?></span>
                </div>
                <?php endif; ?>

                <div class="amazon-meta-item">
                    <span class="amazon-meta-label"><?php _e( 'Région:', 'amazon-product-importer' ); ?></span>
                    <span class="amazon-meta-value">Amazon.<?php echo esc_html( $region ); ?></span>
                </div>
            </div>

        </div>
    </div>

    <!-- Additional Product Information (Expandable) -->
    <div class="amazon-product-additional-info" style="display: none;">
        
        <!-- Product Attributes -->
        <?php
        $attributes = $product->get_attributes();
        if ( ! empty( $attributes ) ):
        ?>
        <div class="amazon-product-attributes">
            <h4 class="amazon-attributes-title"><?php _e( 'Spécifications techniques', 'amazon-product-importer' ); ?></h4>
            <dl class="amazon-attributes-list">
                <?php foreach ( $attributes as $attribute ): ?>
                    <?php if ( $attribute->get_visible() ): ?>
                        <dt class="amazon-attribute-name"><?php echo esc_html( wc_attribute_label( $attribute->get_name() ) ); ?></dt>
                        <dd class="amazon-attribute-value">
                            <?php
                            if ( $attribute->is_taxonomy() ) {
                                $values = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
                                echo esc_html( implode( ', ', $values ) );
                            } else {
                                echo wp_kses_post( $attribute->get_options() );
                            }
                            ?>
                        </dd>
                    <?php endif; ?>
                <?php endforeach; ?>
            </dl>
        </div>
        <?php endif; ?>

        <!-- Gallery Images -->
        <?php if ( ! empty( $product_gallery_ids ) ): ?>
        <div class="amazon-product-gallery">
            <h4 class="amazon-gallery-title"><?php _e( 'Images du produit', 'amazon-product-importer' ); ?></h4>
            <div class="amazon-gallery-grid">
                <?php foreach ( $product_gallery_ids as $gallery_image_id ): ?>
                    <div class="amazon-gallery-item">
                        <?php
                        echo wp_get_attachment_image( 
                            $gallery_image_id, 
                            'medium', 
                            false, 
                            array(
                                'class' => 'amazon-gallery-image',
                                'loading' => 'lazy'
                            )
                        );
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Toggle for Additional Info -->
    <div class="amazon-product-toggle">
        <button type="button" 
                class="amazon-toggle-button" 
                data-target=".amazon-product-additional-info"
                aria-expanded="false">
            <span class="amazon-toggle-text-show"><?php _e( 'Voir plus d\'informations', 'amazon-product-importer' ); ?></span>
            <span class="amazon-toggle-text-hide" style="display: none;"><?php _e( 'Masquer les informations', 'amazon-product-importer' ); ?></span>
            <span class="amazon-toggle-icon">▼</span>
        </button>
    </div>

</div>

<style>
/* Amazon Product Shortcode Styles */
.amazon-product-shortcode {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.6;
    position: relative;
    transition: box-shadow 0.3s ease;
}

.amazon-product-shortcode:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

/* Badge */
.amazon-product-badge-wrapper {
    position: absolute;
    top: 15px;
    right: 15px;
    display: flex;
    gap: 5px;
    z-index: 2;
}

.amazon-product-badge {
    background: #ff9900;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.amazon-availability-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.availability-in-stock { background: #d4edda; color: #155724; }
.availability-out-of-stock { background: #f8d7da; color: #721c24; }
.availability-limited { background: #fff3cd; color: #856404; }

/* Layout */
.amazon-product-content {
    display: flex;
    gap: 20px;
    margin-top: 30px;
}

.amazon-product-image-wrapper {
    flex: 0 0 200px;
    text-align: center;
}

.amazon-product-image {
    max-width: 100%;
    height: auto;
    border-radius: 6px;
    transition: transform 0.3s ease;
}

.amazon-product-image:hover {
    transform: scale(1.05);
}

.amazon-product-info {
    flex: 1;
    min-width: 0;
}

/* Typography */
.amazon-product-title {
    margin: 0 0 10px 0;
    font-size: 20px;
    font-weight: 600;
    line-height: 1.4;
}

.amazon-product-title-link {
    color: #2271b1;
    text-decoration: none;
}

.amazon-product-title-link:hover {
    text-decoration: underline;
}

.amazon-product-brand {
    margin-bottom: 10px;
    font-size: 14px;
    color: #666;
}

.amazon-brand-name {
    font-weight: 600;
    color: #333;
}

/* Rating */
.amazon-product-rating {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.amazon-stars {
    display: flex;
    gap: 2px;
}

.amazon-star {
    font-size: 16px;
    line-height: 1;
}

.amazon-star-full { color: #ffa500; }
.amazon-star-half { color: #ffa500; opacity: 0.5; }
.amazon-star-empty { color: #ddd; }

.amazon-rating-details {
    font-size: 13px;
    color: #666;
}

.amazon-reviews-count {
    color: #2271b1;
}

/* Features */
.amazon-product-features {
    margin: 15px 0;
}

.amazon-features-title {
    margin: 0 0 8px 0;
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.amazon-features-list {
    margin: 0;
    padding-left: 0;
    list-style: none;
}

.amazon-feature-item {
    position: relative;
    padding-left: 16px;
    margin-bottom: 5px;
    font-size: 14px;
    line-height: 1.4;
}

.amazon-feature-item::before {
    content: '✓';
    position: absolute;
    left: 0;
    color: #28a745;
    font-weight: 600;
}

/* Variations */
.amazon-product-variations {
    margin: 15px 0;
}

.amazon-variation-select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

/* Price */
.amazon-product-price-wrapper {
    margin: 15px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
}

.amazon-product-price {
    display: flex;
    align-items: baseline;
    gap: 10px;
    margin-bottom: 5px;
}

.amazon-price-current {
    font-size: 24px;
    font-weight: 700;
    color: #ff9900;
}

.amazon-price-regular {
    font-size: 18px;
    text-decoration: line-through;
    color: #666;
}

.amazon-price-savings {
    background: #d4edda;
    color: #155724;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.amazon-price-updated {
    font-size: 11px;
    color: #666;
}

/* Stock Status */
.amazon-stock-status {
    margin: 10px 0;
}

.amazon-in-stock {
    color: #28a745;
    font-weight: 500;
}

.amazon-out-of-stock {
    color: #dc3545;
    font-weight: 500;
}

.amazon-stock-icon {
    margin-right: 5px;
}

/* CTA */
.amazon-product-cta {
    margin: 20px 0;
}

.amazon-buy-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #ff9900;
    color: white;
    padding: 12px 24px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    font-size: 16px;
    transition: background 0.3s ease;
    border: none;
    cursor: pointer;
}

.amazon-buy-button:hover {
    background: #e68a00;
    color: white;
    text-decoration: none;
}

.amazon-secondary-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.amazon-action-button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: none;
    border: 1px solid #ddd;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    color: #666;
    cursor: pointer;
    transition: all 0.3s ease;
}

.amazon-action-button:hover {
    border-color: #2271b1;
    color: #2271b1;
}

/* Meta */
.amazon-product-meta {
    margin: 15px 0;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
    font-size: 12px;
}

.amazon-meta-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 3px;
}

.amazon-meta-label {
    font-weight: 500;
    color: #666;
}

.amazon-meta-value {
    color: #333;
}

.amazon-asin {
    font-family: 'Courier New', monospace;
    background: #e9ecef;
    padding: 2px 4px;
    border-radius: 3px;
}

/* Toggle */
.amazon-product-toggle {
    text-align: center;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e0e0e0;
}

.amazon-toggle-button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: none;
    border: 1px solid #ddd;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 13px;
    color: #2271b1;
    cursor: pointer;
    transition: all 0.3s ease;
}

.amazon-toggle-button:hover {
    border-color: #2271b1;
    background: #f0f6fc;
}

.amazon-toggle-icon {
    transition: transform 0.3s ease;
}

.amazon-toggle-button[aria-expanded="true"] .amazon-toggle-icon {
    transform: rotate(180deg);
}

/* Additional Info */
.amazon-product-additional-info {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e0e0e0;
}

.amazon-attributes-list {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 8px 15px;
    margin: 10px 0;
}

.amazon-attribute-name {
    font-weight: 500;
    color: #666;
    font-size: 13px;
}

.amazon-attribute-value {
    color: #333;
    font-size: 13px;
}

.amazon-gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
    margin: 10px 0;
}

.amazon-gallery-image {
    width: 100%;
    height: auto;
    border-radius: 4px;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.amazon-gallery-image:hover {
    transform: scale(1.05);
}

/* Responsive */
@media (max-width: 768px) {
    .amazon-product-content {
        flex-direction: column;
        gap: 15px;
    }
    
    .amazon-product-image-wrapper {
        flex: none;
    }
    
    .amazon-product-price {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .amazon-secondary-actions {
        flex-direction: column;
    }
    
    .amazon-attributes-list {
        grid-template-columns: 1fr;
    }
    
    .amazon-product-badge-wrapper {
        position: static;
        margin-bottom: 10px;
        justify-content: flex-start;
    }
}

@media (max-width: 480px) {
    .amazon-product-shortcode {
        padding: 15px;
        margin: 15px 0;
    }
    
    .amazon-product-title {
        font-size: 18px;
    }
    
    .amazon-price-current {
        font-size: 20px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle additional information
    const toggleButtons = document.querySelectorAll('.amazon-toggle-button');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetSelector = this.getAttribute('data-target');
            const target = this.closest('.amazon-product-shortcode').querySelector(targetSelector);
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            
            if (target) {
                if (isExpanded) {
                    target.style.display = 'none';
                    this.setAttribute('aria-expanded', 'false');
                    this.querySelector('.amazon-toggle-text-show').style.display = 'inline';
                    this.querySelector('.amazon-toggle-text-hide').style.display = 'none';
                } else {
                    target.style.display = 'block';
                    this.setAttribute('aria-expanded', 'true');
                    this.querySelector('.amazon-toggle-text-show').style.display = 'none';
                    this.querySelector('.amazon-toggle-text-hide').style.display = 'inline';
                }
            }
        });
    });

    // Handle variation selection
    const variationSelects = document.querySelectorAll('.amazon-variation-select');
    
    variationSelects.forEach(select => {
        select.addEventListener('change', function() {
            const productId = this.getAttribute('data-product-id');
            const variationId = this.value;
            
            if (variationId && typeof AmazonProductImporter !== 'undefined') {
                // Use the public JavaScript API if available
                AmazonProductImporter.Public.loadVariationData(
                    this.closest('.amazon-product-shortcode'), 
                    variationId
                );
            }
        });
    });

    // Handle wishlist and share actions
    document.addEventListener('click', function(e) {
        if (e.target.matches('.amazon-add-to-wishlist, .amazon-add-to-wishlist *')) {
            e.preventDefault();
            const button = e.target.closest('.amazon-add-to-wishlist');
            const productId = button.getAttribute('data-product-id');
            
            // Add to wishlist functionality
            console.log('Add to wishlist:', productId);
        }
        
        if (e.target.matches('.amazon-share-product, .amazon-share-product *')) {
            e.preventDefault();
            const button = e.target.closest('.amazon-share-product');
            const title = button.getAttribute('data-product-title');
            const url = button.getAttribute('data-product-url');
            
            // Web Share API or fallback
            if (navigator.share) {
                navigator.share({
                    title: title,
                    url: url
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(url).then(() => {
                    alert('Lien copié dans le presse-papiers!');
                });
            }
        }
    });
});
</script>