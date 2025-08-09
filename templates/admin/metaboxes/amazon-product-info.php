<?php
/**
 * Amazon Product Information Metabox Template
 *
 * This template displays Amazon-specific information for products
 * in the WooCommerce product edit screen.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/templates/admin/metaboxes
 * @since      1.0.0
 * 
 * @var object $product         WooCommerce product object
 * @var array  $amazon_data     Amazon-specific product data
 * @var array  $sync_settings   Synchronization settings
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get Amazon data
$asin = isset( $amazon_data['asin'] ) ? $amazon_data['asin'] : '';
$region = isset( $amazon_data['region'] ) ? $amazon_data['region'] : 'com';
$last_updated = isset( $amazon_data['last_updated'] ) ? $amazon_data['last_updated'] : '';
$price_updated = isset( $amazon_data['price_updated'] ) ? $amazon_data['price_updated'] : '';
$sync_status = isset( $amazon_data['sync_status'] ) ? $amazon_data['sync_status'] : 'none';
$availability = isset( $amazon_data['availability'] ) ? $amazon_data['availability'] : '';
$brand = isset( $amazon_data['brand'] ) ? $amazon_data['brand'] : '';
$parent_asin = isset( $amazon_data['parent_asin'] ) ? $amazon_data['parent_asin'] : '';
$variation_attributes = isset( $amazon_data['variation_attributes'] ) ? $amazon_data['variation_attributes'] : array();

// Nonce for security
wp_nonce_field( 'amazon_product_info_metabox', 'amazon_product_info_nonce' );
?>

<div class="amazon-product-info-metabox">
    
    <!-- Amazon Product Identification -->
    <div class="amazon-section amazon-identification">
        <h4 class="amazon-section-title">
            <span class="dashicons dashicons-amazon"></span>
            <?php _e( 'Identification Amazon', 'amazon-product-importer' ); ?>
        </h4>
        
        <div class="amazon-fields-grid">
            <div class="amazon-field">
                <label for="amazon_asin">
                    <strong><?php _e( 'ASIN', 'amazon-product-importer' ); ?></strong>
                    <span class="amazon-required">*</span>
                </label>
                <div class="amazon-field-input">
                    <input type="text" 
                           id="amazon_asin" 
                           name="amazon_asin" 
                           value="<?php echo esc_attr( $asin ); ?>"
                           class="regular-text amazon-asin-input"
                           <?php echo $asin ? 'readonly' : ''; ?>
                           placeholder="B0XXXXXXXXXX" />
                    <?php if ( $asin ): ?>
                        <button type="button" class="button amazon-edit-asin" data-editing="false">
                            <?php _e( 'Modifier', 'amazon-product-importer' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
                <?php if ( $asin ): ?>
                    <p class="description">
                        <?php printf( 
                            __( 'ASIN du produit Amazon. <a href="%s" target="_blank">Voir sur Amazon ↗</a>', 'amazon-product-importer' ),
                            esc_url( $this->get_amazon_product_url( $asin, $region ) )
                        ); ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="amazon-field">
                <label for="amazon_region">
                    <strong><?php _e( 'Région Amazon', 'amazon-product-importer' ); ?></strong>
                </label>
                <select id="amazon_region" name="amazon_region" class="regular-text">
                    <option value="com" <?php selected( $region, 'com' ); ?>>Amazon.com (États-Unis)</option>
                    <option value="co.uk" <?php selected( $region, 'co.uk' ); ?>>Amazon.co.uk (Royaume-Uni)</option>
                    <option value="de" <?php selected( $region, 'de' ); ?>>Amazon.de (Allemagne)</option>
                    <option value="fr" <?php selected( $region, 'fr' ); ?>>Amazon.fr (France)</option>
                    <option value="it" <?php selected( $region, 'it' ); ?>>Amazon.it (Italie)</option>
                    <option value="es" <?php selected( $region, 'es' ); ?>>Amazon.es (Espagne)</option>
                    <option value="ca" <?php selected( $region, 'ca' ); ?>>Amazon.ca (Canada)</option>
                    <option value="com.au" <?php selected( $region, 'com.au' ); ?>>Amazon.com.au (Australie)</option>
                    <option value="co.jp" <?php selected( $region, 'co.jp' ); ?>>Amazon.co.jp (Japon)</option>
                </select>
            </div>

            <?php if ( $parent_asin ): ?>
            <div class="amazon-field">
                <label for="amazon_parent_asin">
                    <strong><?php _e( 'ASIN Parent', 'amazon-product-importer' ); ?></strong>
                </label>
                <div class="amazon-field-input">
                    <input type="text" 
                           id="amazon_parent_asin" 
                           name="amazon_parent_asin" 
                           value="<?php echo esc_attr( $parent_asin ); ?>"
                           class="regular-text"
                           readonly />
                    <a href="<?php echo esc_url( $this->get_amazon_product_url( $parent_asin, $region ) ); ?>" 
                       target="_blank" 
                       class="button button-secondary">
                        <?php _e( 'Voir parent', 'amazon-product-importer' ); ?>
                    </a>
                </div>
                <p class="description">
                    <?php _e( 'ASIN du produit parent pour les variations', 'amazon-product-importer' ); ?>
                </p>
            </div>
            <?php endif; ?>

            <?php if ( $brand ): ?>
            <div class="amazon-field">
                <label for="amazon_brand">
                    <strong><?php _e( 'Marque', 'amazon-product-importer' ); ?></strong>
                </label>
                <input type="text" 
                       id="amazon_brand" 
                       name="amazon_brand" 
                       value="<?php echo esc_attr( $brand ); ?>"
                       class="regular-text"
                       readonly />
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Synchronization Status -->
    <div class="amazon-section amazon-sync-status">
        <h4 class="amazon-section-title">
            <span class="dashicons dashicons-update"></span>
            <?php _e( 'Statut de synchronisation', 'amazon-product-importer' ); ?>
        </h4>

        <div class="amazon-sync-info">
            <div class="amazon-status-indicator">
                <span class="amazon-status-badge status-<?php echo esc_attr( $sync_status ); ?>">
                    <?php echo $this->get_sync_status_label( $sync_status ); ?>
                </span>
                <?php if ( $sync_status === 'error' ): ?>
                    <span class="amazon-status-message">
                        <?php echo esc_html( get_post_meta( $product->get_id(), '_amazon_sync_error', true ) ); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="amazon-last-updates">
                <?php if ( $last_updated ): ?>
                <div class="amazon-update-info">
                    <strong><?php _e( 'Dernière mise à jour complète:', 'amazon-product-importer' ); ?></strong>
                    <span class="amazon-date"><?php echo esc_html( $this->format_date( $last_updated ) ); ?></span>
                    <span class="amazon-time-ago">(<?php echo human_time_diff( strtotime( $last_updated ), current_time( 'timestamp' ) ); ?> ago)</span>
                </div>
                <?php endif; ?>

                <?php if ( $price_updated && $price_updated !== $last_updated ): ?>
                <div class="amazon-update-info">
                    <strong><?php _e( 'Dernière mise à jour des prix:', 'amazon-product-importer' ); ?></strong>
                    <span class="amazon-date"><?php echo esc_html( $this->format_date( $price_updated ) ); ?></span>
                    <span class="amazon-time-ago">(<?php echo human_time_diff( strtotime( $price_updated ), current_time( 'timestamp' ) ); ?> ago)</span>
                </div>
                <?php endif; ?>
            </div>

            <div class="amazon-sync-actions">
                <button type="button" 
                        class="button button-secondary amazon-sync-now" 
                        data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
                        data-asin="<?php echo esc_attr( $asin ); ?>">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e( 'Synchroniser maintenant', 'amazon-product-importer' ); ?>
                </button>

                <button type="button" 
                        class="button button-secondary amazon-refresh-price" 
                        data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
                        data-asin="<?php echo esc_attr( $asin ); ?>">
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php _e( 'Actualiser les prix', 'amazon-product-importer' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Product Availability -->
    <?php if ( $availability ): ?>
    <div class="amazon-section amazon-availability">
        <h4 class="amazon-section-title">
            <span class="dashicons dashicons-store"></span>
            <?php _e( 'Disponibilité', 'amazon-product-importer' ); ?>
        </h4>
        
        <div class="amazon-availability-info">
            <span class="amazon-availability-status availability-<?php echo esc_attr( strtolower( str_replace( ' ', '-', $availability ) ) ); ?>">
                <?php echo esc_html( $availability ); ?>
            </span>
            <p class="description">
                <?php _e( 'Statut de disponibilité du produit sur Amazon', 'amazon-product-importer' ); ?>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Variation Attributes (for variable products) -->
    <?php if ( ! empty( $variation_attributes ) ): ?>
    <div class="amazon-section amazon-variations">
        <h4 class="amazon-section-title">
            <span class="dashicons dashicons-admin-generic"></span>
            <?php _e( 'Attributs de variation Amazon', 'amazon-product-importer' ); ?>
        </h4>
        
        <div class="amazon-variation-attributes">
            <?php foreach ( $variation_attributes as $attribute_name => $attribute_values ): ?>
            <div class="amazon-attribute">
                <strong><?php echo esc_html( ucfirst( str_replace( '_', ' ', $attribute_name ) ) ); ?>:</strong>
                <div class="amazon-attribute-values">
                    <?php foreach ( $attribute_values as $value ): ?>
                        <span class="amazon-attribute-value"><?php echo esc_html( $value ); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sync Settings -->
    <div class="amazon-section amazon-sync-settings">
        <h4 class="amazon-section-title">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php _e( 'Paramètres de synchronisation', 'amazon-product-importer' ); ?>
        </h4>

        <div class="amazon-sync-options">
            <label class="amazon-checkbox-label">
                <input type="checkbox" 
                       name="amazon_auto_sync_price" 
                       value="1" 
                       <?php checked( get_post_meta( $product->get_id(), '_amazon_auto_sync_price', true ), '1' ); ?> />
                <?php _e( 'Synchronisation automatique des prix', 'amazon-product-importer' ); ?>
                <span class="description"><?php _e( 'Met à jour automatiquement les prix via les tâches cron', 'amazon-product-importer' ); ?></span>
            </label>

            <label class="amazon-checkbox-label">
                <input type="checkbox" 
                       name="amazon_auto_sync_availability" 
                       value="1" 
                       <?php checked( get_post_meta( $product->get_id(), '_amazon_auto_sync_availability', true ), '1' ); ?> />
                <?php _e( 'Synchronisation automatique de la disponibilité', 'amazon-product-importer' ); ?>
                <span class="description"><?php _e( 'Met à jour automatiquement le statut de stock', 'amazon-product-importer' ); ?></span>
            </label>

            <label class="amazon-checkbox-label">
                <input type="checkbox" 
                       name="amazon_auto_sync_images" 
                       value="1" 
                       <?php checked( get_post_meta( $product->get_id(), '_amazon_auto_sync_images', true ), '1' ); ?> />
                <?php _e( 'Synchronisation automatique des images', 'amazon-product-importer' ); ?>
                <span class="description"><?php _e( 'Met à jour automatiquement les images du produit', 'amazon-product-importer' ); ?></span>
            </label>

            <div class="amazon-sync-frequency">
                <label for="amazon_sync_frequency">
                    <strong><?php _e( 'Fréquence de synchronisation', 'amazon-product-importer' ); ?></strong>
                </label>
                <select id="amazon_sync_frequency" name="amazon_sync_frequency" class="regular-text">
                    <?php
                    $current_frequency = get_post_meta( $product->get_id(), '_amazon_sync_frequency', true ) ?: 'daily';
                    $frequencies = array(
                        'hourly' => __( 'Toutes les heures', 'amazon-product-importer' ),
                        'twicedaily' => __( 'Deux fois par jour', 'amazon-product-importer' ),
                        'daily' => __( 'Quotidienne', 'amazon-product-importer' ),
                        'weekly' => __( 'Hebdomadaire', 'amazon-product-importer' ),
                        'monthly' => __( 'Mensuelle', 'amazon-product-importer' )
                    );
                    
                    foreach ( $frequencies as $freq_key => $freq_label ):
                    ?>
                        <option value="<?php echo esc_attr( $freq_key ); ?>" <?php selected( $current_frequency, $freq_key ); ?>>
                            <?php echo esc_html( $freq_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Amazon Links -->
    <?php if ( $asin ): ?>
    <div class="amazon-section amazon-links">
        <h4 class="amazon-section-title">
            <span class="dashicons dashicons-admin-links"></span>
            <?php _e( 'Liens Amazon', 'amazon-product-importer' ); ?>
        </h4>

        <div class="amazon-links-grid">
            <a href="<?php echo esc_url( $this->get_amazon_product_url( $asin, $region ) ); ?>" 
               target="_blank" 
               class="button button-secondary amazon-link">
                <span class="dashicons dashicons-external"></span>
                <?php _e( 'Voir sur Amazon', 'amazon-product-importer' ); ?>
            </a>

            <a href="<?php echo esc_url( $this->get_amazon_affiliate_url( $asin, $region ) ); ?>" 
               target="_blank" 
               class="button button-secondary amazon-link">
                <span class="dashicons dashicons-money-alt"></span>
                <?php _e( 'Lien d\'affiliation', 'amazon-product-importer' ); ?>
            </a>

            <?php if ( $parent_asin ): ?>
            <a href="<?php echo esc_url( $this->get_amazon_product_url( $parent_asin, $region ) ); ?>" 
               target="_blank" 
               class="button button-secondary amazon-link">
                <span class="dashicons dashicons-networking"></span>
                <?php _e( 'Produit parent', 'amazon-product-importer' ); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Debug Information (only shown in debug mode) -->
    <?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ): ?>
    <div class="amazon-section amazon-debug">
        <h4 class="amazon-section-title">
            <span class="dashicons dashicons-admin-tools"></span>
            <?php _e( 'Informations de débogage', 'amazon-product-importer' ); ?>
        </h4>

        <div class="amazon-debug-info">
            <details>
                <summary><?php _e( 'Données Amazon complètes', 'amazon-product-importer' ); ?></summary>
                <pre class="amazon-debug-data"><?php echo esc_html( print_r( $amazon_data, true ) ); ?></pre>
            </details>

            <div class="amazon-debug-actions">
                <button type="button" 
                        class="button button-secondary amazon-refresh-data" 
                        data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
                    <?php _e( 'Actualiser les données', 'amazon-product-importer' ); ?>
                </button>

                <button type="button" 
                        class="button button-secondary amazon-clear-cache" 
                        data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
                    <?php _e( 'Vider le cache', 'amazon-product-importer' ); ?>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Loading Overlay -->
    <div class="amazon-loading-overlay" style="display: none;">
        <div class="amazon-spinner">
            <div class="amazon-loading-text">
                <?php _e( 'Synchronisation en cours...', 'amazon-product-importer' ); ?>
            </div>
        </div>
    </div>

</div>

<style>
/* Metabox Styles */
.amazon-product-info-metabox {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    position: relative;
}

.amazon-section {
    margin-bottom: 20px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 5px;
}

.amazon-section-title {
    margin: 0 0 15px 0;
    font-size: 14px;
    font-weight: 600;
    color: #23282d;
    display: flex;
    align-items: center;
    gap: 8px;
}

.amazon-fields-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

@media (max-width: 782px) {
    .amazon-fields-grid {
        grid-template-columns: 1fr;
    }
}

.amazon-field {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.amazon-field label {
    font-weight: 500;
    color: #23282d;
}

.amazon-required {
    color: #d63638;
}

.amazon-field-input {
    display: flex;
    gap: 5px;
    align-items: center;
}

.amazon-asin-input[readonly] {
    background: #f1f1f1;
}

.amazon-status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-synced { background: #d4edda; color: #155724; }
.status-pending { background: #fff3cd; color: #856404; }
.status-error { background: #f8d7da; color: #721c24; }
.status-none { background: #e2e3e5; color: #6c757d; }

.amazon-sync-actions {
    margin-top: 15px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.amazon-checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 10px;
}

.amazon-checkbox-label .description {
    font-style: italic;
    color: #666;
    margin-left: 0;
}

.amazon-links-grid {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.amazon-variation-attributes {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.amazon-attribute-values {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    margin-top: 5px;
}

.amazon-attribute-value {
    background: #e7e7e7;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
}

.amazon-loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.amazon-spinner {
    text-align: center;
}

.amazon-debug-data {
    background: #f1f1f1;
    padding: 10px;
    border-radius: 3px;
    overflow-x: auto;
    font-size: 12px;
    max-height: 300px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Edit ASIN functionality
    $('.amazon-edit-asin').on('click', function() {
        const $button = $(this);
        const $input = $('#amazon_asin');
        const isEditing = $button.data('editing') === 'true';
        
        if (isEditing) {
            $input.prop('readonly', true);
            $button.text('<?php _e( 'Modifier', 'amazon-product-importer' ); ?>');
            $button.data('editing', 'false');
        } else {
            $input.prop('readonly', false).focus();
            $button.text('<?php _e( 'Valider', 'amazon-product-importer' ); ?>');
            $button.data('editing', 'true');
        }
    });

    // Sync now functionality
    $('.amazon-sync-now').on('click', function() {
        const $button = $(this);
        const productId = $button.data('product-id');
        const asin = $button.data('asin');
        const $overlay = $('.amazon-loading-overlay');
        
        $overlay.show();
        $button.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'amazon_sync_product',
                product_id: productId,
                asin: asin,
                nonce: '<?php echo wp_create_nonce( 'amazon_sync_product' ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('<?php _e( 'Erreur de synchronisation', 'amazon-product-importer' ); ?>: ' + response.data);
                }
            },
            error: function() {
                alert('<?php _e( 'Erreur de connexion', 'amazon-product-importer' ); ?>');
            },
            complete: function() {
                $overlay.hide();
                $button.prop('disabled', false);
            }
        });
    });

    // Refresh price functionality
    $('.amazon-refresh-price').on('click', function() {
        const $button = $(this);
        const productId = $button.data('product-id');
        const asin = $button.data('asin');
        
        $button.prop('disabled', true).text('<?php _e( 'Actualisation...', 'amazon-product-importer' ); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'amazon_refresh_price',
                product_id: productId,
                asin: asin,
                nonce: '<?php echo wp_create_nonce( 'amazon_refresh_price' ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Update price display if needed
                    location.reload();
                } else {
                    alert('<?php _e( 'Erreur de mise à jour du prix', 'amazon-product-importer' ); ?>: ' + response.data);
                }
            },
            error: function() {
                alert('<?php _e( 'Erreur de connexion', 'amazon-product-importer' ); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php _e( 'Actualiser les prix', 'amazon-product-importer' ); ?>');
            }
        });
    });
});
</script>