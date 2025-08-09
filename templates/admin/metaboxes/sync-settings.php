<?php
/**
 * Amazon Product Sync Settings Metabox Template
 *
 * This template displays detailed synchronization settings for Amazon products
 * in the WooCommerce product edit screen.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/templates/admin/metaboxes
 * @since      1.0.0
 * 
 * @var object $product         WooCommerce product object
 * @var array  $sync_settings   Current synchronization settings
 * @var array  $global_settings Global plugin settings
 * @var array  $sync_history    Synchronization history
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get current settings
$product_id = $product->get_id();
$asin = get_post_meta( $product_id, '_amazon_asin', true );

// Sync settings
$auto_sync_enabled = get_post_meta( $product_id, '_amazon_auto_sync_enabled', true ) !== '0';
$sync_frequency = get_post_meta( $product_id, '_amazon_sync_frequency', true ) ?: 'daily';
$last_sync = get_post_meta( $product_id, '_amazon_last_sync', true );
$sync_errors = get_post_meta( $product_id, '_amazon_sync_errors', true ) ?: array();

// Sync options
$sync_price = get_post_meta( $product_id, '_amazon_sync_price', true ) !== '0';
$sync_stock = get_post_meta( $product_id, '_amazon_sync_stock', true ) !== '0';
$sync_images = get_post_meta( $product_id, '_amazon_sync_images', true ) !== '0';
$sync_description = get_post_meta( $product_id, '_amazon_sync_description', true ) !== '0';
$sync_title = get_post_meta( $product_id, '_amazon_sync_title', true ) !== '0';
$sync_attributes = get_post_meta( $product_id, '_amazon_sync_attributes', true ) !== '0';
$sync_categories = get_post_meta( $product_id, '_amazon_sync_categories', true ) !== '0';

// Price settings
$price_markup_type = get_post_meta( $product_id, '_amazon_price_markup_type', true ) ?: 'percentage';
$price_markup_value = get_post_meta( $product_id, '_amazon_price_markup_value', true ) ?: '0';
$price_rounding = get_post_meta( $product_id, '_amazon_price_rounding', true ) ?: 'none';

// Stock settings
$stock_buffer = get_post_meta( $product_id, '_amazon_stock_buffer', true ) ?: '0';
$stock_threshold = get_post_meta( $product_id, '_amazon_stock_threshold', true ) ?: '5';

// Notification settings
$notify_price_change = get_post_meta( $product_id, '_amazon_notify_price_change', true ) !== '0';
$notify_stock_out = get_post_meta( $product_id, '_amazon_notify_stock_out', true ) !== '0';
$notify_sync_errors = get_post_meta( $product_id, '_amazon_notify_sync_errors', true ) !== '0';

// Advanced settings
$skip_if_manual_edit = get_post_meta( $product_id, '_amazon_skip_if_manual_edit', true ) !== '0';
$preserve_local_changes = get_post_meta( $product_id, '_amazon_preserve_local_changes', true ) !== '0';

// Nonce for security
wp_nonce_field( 'amazon_sync_settings_metabox', 'amazon_sync_settings_nonce' );
?>

<div class="amazon-sync-settings-metabox">

    <!-- General Sync Settings -->
    <div class="amazon-section amazon-general-sync">
        <h4 class="amazon-section-title">
            <span class="dashicons dashicons-update"></span>
            <?php _e( 'Paramètres généraux de synchronisation', 'amazon-product-importer' ); ?>
        </h4>

        <div class="amazon-sync-general-options">
            <label class="amazon-toggle-label">
                <input type="checkbox" 
                       name="amazon_auto_sync_enabled" 
                       value="1" 
                       <?php checked( $auto_sync_enabled ); ?>
                       class="amazon-master-toggle" />
                <span class="amazon-toggle-slider"></span>
                <strong><?php _e( 'Activer la synchronisation automatique', 'amazon-product-importer' ); ?></strong>
            </label>
            <p class="description">
                <?php _e( 'Active ou désactive la synchronisation automatique pour ce produit', 'amazon-product-importer' ); ?>
            </p>

            <div class="amazon-sync-frequency-wrapper" <?php echo $auto_sync_enabled ? '' : 'style="display:none;"'; ?>>
                <label for="amazon_sync_frequency">
                    <strong><?php _e( 'Fréquence de synchronisation', 'amazon-product-importer' ); ?></strong>
                </label>
                <select id="amazon_sync_frequency" name="amazon_sync_frequency" class="regular-text">
                    <?php
                    $frequencies = array(
                        'every_15min' => __( 'Toutes les 15 minutes', 'amazon-product-importer' ),
                        'every_30min' => __( 'Toutes les 30 minutes', 'amazon-product-importer' ),
                        'hourly' => __( 'Toutes les heures', 'amazon-product-importer' ),
                        'twicedaily' => __( 'Deux fois par jour', 'amazon-product-importer' ),
                        'daily' => __( 'Quotidienne', 'amazon-product-importer' ),
                        'weekly' => __( 'Hebdomadaire', 'amazon-product-importer' ),
                        'monthly' => __( 'Mensuelle', 'amazon-product-importer' )
                    );
                    
                    foreach ( $frequencies as $freq_key => $freq_label ):
                    ?>
                        <option value="<?php echo esc_attr( $freq_key ); ?>" <?php selected( $sync_frequency, $freq_key ); ?>>
                            <?php echo esc_html( $freq_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php _e( 'Définit la fréquence de vérification et de mise à jour automatique', 'amazon-product-importer' ); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Sync Elements -->
    <div class="amazon-section amazon-sync-elements" <?php echo $auto_sync_enabled ? '' : 'style="display:none;"'; ?>>
        <h4 class="amazon-section-title">
            <span class="dashicons dashicons-admin-generic"></span>
            <?php _e( 'Éléments à synchroniser', 'amazon-product-importer' ); ?>
        </h4>

        <div class="amazon-sync-elements-grid">
            <div class="amazon-sync-element">
                <label class="amazon-checkbox-label">
                    <input type="checkbox" 
                           name="amazon_sync_price" 
                           value="1" 
                           <?php checked( $sync_price ); ?> />
                    <span class="checkmark"></span>
                    <strong><?php _e( 'Prix', 'amazon-product-importer' ); ?></strong>
                </label>
                <p class="description"><?php _e( 'Synchronise les prix réguliers et de vente', 'amazon-product-importer' ); ?></p>
            </div>

            <div class="amazon-sync-element">
                <label class="amazon-checkbox-label">
                    <input type="checkbox" 
                           name="amazon_sync_stock" 
                           value="1" 
                           <?php checked( $sync_stock ); ?> />
                    <span class="checkmark"></span>
                    <strong><?php _e( 'Stock / Disponibilité', 'amazon-product-importer' ); ?></strong>
                </label>
                <p class="description"><?php _e( 'Synchronise le statut de disponibilité et le stock', 'amazon-product-importer' ); ?></p>
            </div>

            <div class="amazon-sync-element">
                <label class="amazon-checkbox-label">
                    <input type="checkbox" 
                           name="amazon_sync_images" 
                           value="1" 
                           <?php checked( $sync_images ); ?> />
                    <span class="checkmark"></span>
                    <strong><?php _e( 'Images', 'amazon-product-importer' ); ?></strong>
                </label>
                <p class="description"><?php _e( 'Synchronise les images du produit et de la galerie', 'amazon-product-importer' ); ?></p>
            </div>

            <div class="amazon-sync-element">
                <label class="amazon-checkbox-label">
                    <input type="checkbox" 
                           name="amazon_sync_description" 
                           value="1" 
                           <?php checked( $sync_description ); ?> />
                    <span class="checkmark"></span>
                    <strong><?php _e( 'Description', 'amazon-product-importer' ); ?></strong>
                </label>
                <p class="description"><?php _e( 'Synchronise la description et les caractéristiques', 'amazon-product-importer' ); ?></p>
            </div>

            <div class="amazon-sync-element">
                <label class="amazon-checkbox-label">
                    <input type="checkbox" 
                           name="amazon_sync_title" 
                           value="1" 
                           <?php checked( $sync_title ); ?> />
                    <span class="checkmark"></span>
                    <strong><?php _e( 'Titre', 'amazon-product-importer' ); ?></strong>
                </label>
                <p class="description"><?php _e( 'Synchronise le titre du produit', 'amazon-product-importer' ); ?></p>
            </div>

            <div class="amazon-sync-element">
                <label class="amazon-checkbox-label">
                    <input type="checkbox" 
                           name="amazon_sync_attributes" 
                           value="1" 
                           <?php checked( $sync_attributes ); ?> />
                    <span class="checkmark"></span>
                    <strong><?php _e( 'Attributs', 'amazon-product-importer' ); ?></strong>
                </label>
                <p class="description"><?php _e( 'Synchronise les attributs et variations', 'amazon-product-importer' ); ?></p>
            </div>

            <div class="amazon-sync-element">
                <label class="amazon-checkbox-label">
                    <input type="checkbox" 
                           name="amazon_sync_categories" 
                           value="1" 
                           <?php checked( $sync_categories ); ?> />
                    <span class="checkmark"></span>
                    <strong><?php _e( 'Catégories', 'amazon-product-importer' ); ?></strong>
                </label>
                <p class="description"><?php _e( 'Synchronise les catégories du produit', 'amazon-product-importer' ); ?></p>
            </div>
        </div>
    </div>

    <!-- Price Settings -->
    <div class="amazon-section amazon-price-settings" <?php echo $auto_sync_enabled && $sync_price ? '' : 'style="display:none;"'; ?>>
        <h4 class="amazon-section-title">
            <span class="dashicons dashicons-money-alt"></span>
            <?php _e( 'Paramètres de prix', 'amazon-product-importer' ); ?>
        </h4>

        <div class="amazon-price-options">
            <div class="amazon-markup-settings">
                <label for="amazon_price_markup_type">
                    <strong><?php _e( 'Type de marge', 'amazon-product-importer' ); ?></strong>
                </label>
                <div class="amazon-markup-input-group">
                    <select id="amazon_price_markup_type" name="amazon_price_markup_type" class="amazon-markup-type">
                        <option value="none" <?php selected( $price_markup_type, 'none' ); ?>><?php _e( 'Aucune marge', 'amazon-product-importer' ); ?></option>
                        <option value="percentage" <?php selected( $price_markup_type, 'percentage' ); ?>><?php _e( 'Pourcentage', 'amazon-product-importer' ); ?></option>
                        <option value="fixed" <?php selected( $price_markup_type, 'fixed' ); ?>><?php _e( 'Montant fixe', 'amazon-product-importer' ); ?></option>
                    </select>
                    
                    <input type="number" 
                           name="amazon_price_markup_value" 
                           value="<?php echo esc_attr( $price_markup_value ); ?>"
                           class="small-text amazon-markup-value"
                           step="0.01"
                           min="0"
                           <?php echo $price_markup_type === 'none' ? 'disabled' : ''; ?> />
                    
                    <span class="amazon-markup-unit">
                        <?php echo $price_markup_type === 'percentage' ? '%' : get_woocommerce_currency_symbol(); ?>
                    </span>
                </div>
                <p class="description">
                    <?php _e( 'Ajoute une marge automatique aux prix Amazon synchronisés', 'amazon-product-importer' ); ?>
                </p>
            </div>

            <div class="amazon-price-rounding">
                <label for="amazon_price_rounding">
                    <strong><?php _e( 'Arrondi des prix', 'amazon-product-importer' ); ?></strong>
                </label>
                <select id="amazon_price_rounding" name="amazon_price_rounding" class="regular-text">
                    <option value="none" <?php selected( $price_rounding, 'none' ); ?>><?php _e( 'Aucun arrondi', 'amazon-product-importer' ); ?></option>
                    <option value="round" <?php selected( $price_rounding, 'round' ); ?>><?php _e( 'Arrondi normal', 'amazon-product-importer' ); ?></option>
                    <option value="ceil" <?php selected( $price_rounding, 'ceil' ); ?>><?php _e( 'Arrondi supérieur', 'amazon-product-importer' ); ?></option>
                    <option value="floor" <?php selected( $price_rounding, 'floor' ); ?>><?php _e( 'Arrondi inférieur', 'amazon-product-importer' ); ?></option>
                    <option value="psychological" <?php selected( $price_rounding, 'psychological' ); ?>><?php _e( 'Prix psychologique (.99)', 'amazon-product-importer' ); ?></option>
                </select>
                <p class="description">
                    <?php _e( 'Méthode d\'arrondi appliquée aux prix calculés', 'amazon-product-importer' ); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Stock Settings -->
    <div class="amazon-section amazon-stock-settings" <?php echo $auto_sync_enabled && $sync_stock ? '' : 'style="display:none;"'; ?>>
        <h4 class="amazon-section-title">
            <span class="dashicons dashicons-store"></span>
            <?php _e( 'Paramètres de stock', 'amazon-product-importer' ); ?>
        </h4>

        <div class="amazon-stock-options">
            <div class="amazon-stock-buffer">
                <label for="amazon_stock_buffer">
                    <strong><?php _e( 'Buffer de stock', 'amazon-product-importer' ); ?></strong>
                </label>
                <input type="number" 
                       id="amazon_stock_buffer" 
                       name="amazon_stock_buffer" 
                       value="<?php echo esc_attr( $stock_buffer ); ?>"
                       class="small-text"
                       min="0" />
                <p class="description">
                    <?php _e( 'Quantité à soustraire du stock Amazon (sécurité)', 'amazon-product-importer' ); ?>
                </p>
            </div>

            <div class="amazon-stock-threshold">
                <label for="amazon_stock_threshold">
                    <strong><?php _e( 'Seuil de stock bas', 'amazon-product-importer' ); ?></strong>
                </label>
                <input type="number" 
                       id="amazon_stock_threshold" 
                       name="amazon_stock_threshold" 
                       value="<?php echo esc_attr( $stock_threshold ); ?>"
                       class="small-text"
                       min="0" />
                <p class="description">
                    <?php _e( 'Marquer comme "Stock bas" en dessous de cette quantité', 'amazon-product-importer' ); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Notification Settings -->
    <div class="amazon-section amazon-notifications" <?php echo $auto_sync_enabled ? '' : 'style="display:none;"'; ?>>
        <h4 class="amazon-section-title">
            <span class="dashicons dashicons-bell"></span>
            <?php _e( 'Notifications', 'amazon-product-importer' ); ?>
        </h4>

        <div class="amazon-notification-options">
            <label class="amazon-checkbox-label">
                <input type="checkbox" 
                       name="amazon_notify_price_change" 
                       value="1" 
                       <?php checked( $notify_price_change ); ?> />
                <span class="checkmark"></span>
                <?php _e( 'Notifier les changements de prix significatifs', 'amazon-product-importer' ); ?>
            </label>

            <label class="amazon-checkbox-label">
                <input type="checkbox" 
                       name="amazon_notify_stock_out" 
                       value="1" 
                       <?php checked( $notify_stock_out ); ?> />
                <span class="checkmark"></span>
                <?php _e( 'Notifier les ruptures de stock', 'amazon-product-importer' ); ?>
            </label>

            <label class="amazon-checkbox-label">
                <input type="checkbox" 
                       name="amazon_notify_sync_errors" 
                       value="1" 
                       <?php checked( $notify_sync_errors ); ?> />
                <span class="checkmark"></span>
                <?php _e( 'Notifier les erreurs de synchronisation', 'amazon-product-importer' ); ?>
            </label>
        </div>
    </div>

    <!-- Advanced Settings -->
    <div class="amazon-section amazon-advanced-settings" <?php echo $auto_sync_enabled ? '' : 'style="display:none;"'; ?>>
        <h4 class="amazon-section-title">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php _e( 'Paramètres avancés', 'amazon-product-importer' ); ?>
        </h4>

        <div class="amazon-advanced-options">
            <label class="amazon-checkbox-label">
                <input type="checkbox" 
                       name="amazon_skip_if_manual_edit" 
                       value="1" 
                       <?php checked( $skip_if_manual_edit ); ?> />
                <span class="checkmark"></span>
                <?php _e( 'Ignorer la sync si édition manuelle récente', 'amazon-product-importer' ); ?>
                <span class="description"><?php _e( 'Ne pas synchroniser si le produit a été modifié manuellement dans les dernières 24h', 'amazon-product-importer' ); ?></span>
            </label>

            <label class="amazon-checkbox-label">
                <input type="checkbox" 
                       name="amazon_preserve_local_changes" 
                       value="1" 
                       <?php checked( $preserve_local_changes ); ?> />
                <span class="checkmark"></span>
                <?php _e( 'Préserver les modifications locales', 'amazon-product-importer' ); ?>
                <span class="description"><?php _e( 'Garde les champs modifiés localement même lors de la synchronisation', 'amazon-product-importer' ); ?></span>
            </label>
        </div>
    </div>

    <!-- Sync History -->
    <div class="amazon-section amazon-sync-history">
        <h4 class="amazon-section-title">
            <span class="dashicons dashicons-backup"></span>
            <?php _e( 'Historique de synchronisation', 'amazon-product-importer' ); ?>
        </h4>

        <div class="amazon-sync-history-content">
            <?php if ( $last_sync ): ?>
                <div class="amazon-last-sync">
                    <strong><?php _e( 'Dernière synchronisation:', 'amazon-product-importer' ); ?></strong>
                    <span class="amazon-date"><?php echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_sync ) ); ?></span>
                    <span class="amazon-time-ago">(<?php echo human_time_diff( strtotime( $last_sync ), current_time( 'timestamp' ) ); ?> ago)</span>
                </div>
            <?php else: ?>
                <p class="amazon-no-sync"><?php _e( 'Aucune synchronisation effectuée', 'amazon-product-importer' ); ?></p>
            <?php endif; ?>

            <?php if ( ! empty( $sync_errors ) ): ?>
                <div class="amazon-sync-errors">
                    <h5><?php _e( 'Erreurs récentes:', 'amazon-product-importer' ); ?></h5>
                    <ul class="amazon-error-list">
                        <?php foreach ( array_slice( $sync_errors, -5 ) as $error ): ?>
                            <li class="amazon-error-item">
                                <span class="amazon-error-date"><?php echo date_i18n( 'j M Y H:i', strtotime( $error['date'] ) ); ?></span>
                                <span class="amazon-error-message"><?php echo esc_html( $error['message'] ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="amazon-sync-actions">
                <button type="button" 
                        class="button button-secondary amazon-view-full-history" 
                        data-product-id="<?php echo esc_attr( $product_id ); ?>">
                    <?php _e( 'Voir l\'historique complet', 'amazon-product-importer' ); ?>
                </button>

                <button type="button" 
                        class="button button-secondary amazon-clear-history" 
                        data-product-id="<?php echo esc_attr( $product_id ); ?>">
                    <?php _e( 'Effacer l\'historique', 'amazon-product-importer' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Manual Sync Actions -->
    <div class="amazon-section amazon-manual-actions">
        <h4 class="amazon-section-title">
            <span class="dashicons dashicons-controls-play"></span>
            <?php _e( 'Actions manuelles', 'amazon-product-importer' ); ?>
        </h4>

        <div class="amazon-manual-actions-grid">
            <button type="button" 
                    class="button button-primary amazon-force-sync" 
                    data-product-id="<?php echo esc_attr( $product_id ); ?>"
                    data-asin="<?php echo esc_attr( $asin ); ?>">
                <span class="dashicons dashicons-update"></span>
                <?php _e( 'Synchronisation forcée', 'amazon-product-importer' ); ?>
            </button>

            <button type="button" 
                    class="button button-secondary amazon-test-connection" 
                    data-asin="<?php echo esc_attr( $asin ); ?>">
                <span class="dashicons dashicons-admin-links"></span>
                <?php _e( 'Tester la connexion Amazon', 'amazon-product-importer' ); ?>
            </button>

            <button type="button" 
                    class="button button-secondary amazon-preview-changes" 
                    data-product-id="<?php echo esc_attr( $product_id ); ?>">
                <span class="dashicons dashicons-visibility"></span>
                <?php _e( 'Prévisualiser les changements', 'amazon-product-importer' ); ?>
            </button>

            <button type="button" 
                    class="button button-secondary amazon-reset-settings" 
                    data-product-id="<?php echo esc_attr( $product_id ); ?>">
                <span class="dashicons dashicons-undo"></span>
                <?php _e( 'Réinitialiser les paramètres', 'amazon-product-importer' ); ?>
            </button>
        </div>
    </div>

</div>

<style>
/* Sync Settings Metabox Styles */
.amazon-sync-settings-metabox {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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

/* Toggle Switch */
.amazon-toggle-label {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    cursor: pointer;
}

.amazon-toggle-slider {
    position: relative;
    width: 50px;
    height: 24px;
    background: #ccc;
    border-radius: 12px;
    transition: background 0.3s;
}

.amazon-toggle-slider:before {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 20px;
    height: 20px;
    background: white;
    border-radius: 50%;
    transition: transform 0.3s;
}

input[type="checkbox"]:checked + .amazon-toggle-slider {
    background: #2271b1;
}

input[type="checkbox"]:checked + .amazon-toggle-slider:before {
    transform: translateX(26px);
}

/* Sync Elements Grid */
.amazon-sync-elements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
}

.amazon-sync-element {
    padding: 12px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.amazon-checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 5px;
    cursor: pointer;
}

.amazon-checkbox-label .description {
    font-size: 12px;
    color: #666;
    font-style: italic;
    margin-left: 0;
}

/* Price Markup */
.amazon-markup-input-group {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 5px;
}

.amazon-markup-type {
    min-width: 120px;
}

.amazon-markup-value {
    width: 80px;
}

.amazon-markup-unit {
    font-weight: 600;
    color: #666;
}

/* Manual Actions Grid */
.amazon-manual-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
}

.amazon-manual-actions-grid .button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px 12px;
}

/* Sync History */
.amazon-last-sync {
    background: white;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 10px;
}

.amazon-error-list {
    background: #ffeaea;
    border: 1px solid #ffcdd2;
    border-radius: 4px;
    padding: 10px;
    margin: 10px 0;
    max-height: 150px;
    overflow-y: auto;
}

.amazon-error-item {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px solid #ffcdd2;
}

.amazon-error-item:last-child {
    border-bottom: none;
}

.amazon-error-date {
    font-size: 11px;
    color: #666;
    white-space: nowrap;
}

.amazon-error-message {
    color: #d32f2f;
    font-size: 12px;
}

/* Responsive */
@media (max-width: 782px) {
    .amazon-sync-elements-grid {
        grid-template-columns: 1fr;
    }
    
    .amazon-manual-actions-grid {
        grid-template-columns: 1fr;
    }
    
    .amazon-markup-input-group {
        flex-direction: column;
        align-items: flex-start;
    }
}

/* States */
.amazon-section[style*="display:none"] {
    display: none !important;
}

input:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.amazon-loading .button {
    opacity: 0.7;
    pointer-events: none;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Master toggle functionality
    $('.amazon-master-toggle').on('change', function() {
        const isEnabled = $(this).is(':checked');
        const $dependentSections = $('.amazon-sync-elements, .amazon-price-settings, .amazon-stock-settings, .amazon-notifications, .amazon-advanced-settings');
        
        if (isEnabled) {
            $dependentSections.slideDown();
            $('.amazon-sync-frequency-wrapper').slideDown();
        } else {
            $dependentSections.slideUp();
            $('.amazon-sync-frequency-wrapper').slideUp();
        }
    });

    // Sync element dependencies
    $('input[name="amazon_sync_price"]').on('change', function() {
        const $priceSettings = $('.amazon-price-settings');
        if ($(this).is(':checked')) {
            $priceSettings.slideDown();
        } else {
            $priceSettings.slideUp();
        }
    });

    $('input[name="amazon_sync_stock"]').on('change', function() {
        const $stockSettings = $('.amazon-stock-settings');
        if ($(this).is(':checked')) {
            $stockSettings.slideDown();
        } else {
            $stockSettings.slideUp();
        }
    });

    // Price markup type change
    $('select[name="amazon_price_markup_type"]').on('change', function() {
        const $valueInput = $('.amazon-markup-value');
        const $unit = $('.amazon-markup-unit');
        const type = $(this).val();
        
        if (type === 'none') {
            $valueInput.prop('disabled', true).val('0');
            $unit.text('');
        } else {
            $valueInput.prop('disabled', false);
            $unit.text(type === 'percentage' ? '%' : '<?php echo get_woocommerce_currency_symbol(); ?>');
        }
    });

    // Force sync
    $('.amazon-force-sync').on('click', function() {
        const $button = $(this);
        const productId = $button.data('product-id');
        const asin = $button.data('asin');
        
        $button.prop('disabled', true).text('<?php _e( 'Synchronisation...', 'amazon-product-importer' ); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'amazon_force_sync_product',
                product_id: productId,
                asin: asin,
                nonce: '<?php echo wp_create_nonce( 'amazon_force_sync' ); ?>'
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
                $button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> <?php _e( 'Synchronisation forcée', 'amazon-product-importer' ); ?>');
            }
        });
    });

    // Test connection
    $('.amazon-test-connection').on('click', function() {
        const $button = $(this);
        const asin = $button.data('asin');
        
        $button.prop('disabled', true).text('<?php _e( 'Test en cours...', 'amazon-product-importer' ); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'amazon_test_connection',
                asin: asin,
                nonce: '<?php echo wp_create_nonce( 'amazon_test_connection' ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php _e( 'Connexion réussie! Produit trouvé sur Amazon.', 'amazon-product-importer' ); ?>');
                } else {
                    alert('<?php _e( 'Erreur de connexion', 'amazon-product-importer' ); ?>: ' + response.data);
                }
            },
            error: function() {
                alert('<?php _e( 'Erreur de connexion', 'amazon-product-importer' ); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-admin-links"></span> <?php _e( 'Tester la connexion Amazon', 'amazon-product-importer' ); ?>');
            }
        });
    });

    // Preview changes
    $('.amazon-preview-changes').on('click', function() {
        const productId = $(this).data('product-id');
        
        // Open modal with preview
        const modal = $('<div id="amazon-preview-modal" style="display:none;"><div class="amazon-modal-content"><div class="amazon-modal-header"><h3><?php _e( 'Prévisualisation des changements', 'amazon-product-importer' ); ?></h3><span class="amazon-modal-close">&times;</span></div><div class="amazon-modal-body"><?php _e( 'Chargement...', 'amazon-product-importer' ); ?></div></div></div>');
        
        $('body').append(modal);
        modal.fadeIn();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'amazon_preview_changes',
                product_id: productId,
                nonce: '<?php echo wp_create_nonce( 'amazon_preview_changes' ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    modal.find('.amazon-modal-body').html(response.data);
                } else {
                    modal.find('.amazon-modal-body').html('<p style="color:red;"><?php _e( 'Erreur:', 'amazon-product-importer' ); ?> ' + response.data + '</p>');
                }
            }
        });
        
        // Close modal
        modal.on('click', '.amazon-modal-close, #amazon-preview-modal', function(e) {
            if (e.target === this) {
                modal.fadeOut(function() { modal.remove(); });
            }
        });
    });

    // Reset settings
    $('.amazon-reset-settings').on('click', function() {
        if (confirm('<?php _e( 'Êtes-vous sûr de vouloir réinitialiser tous les paramètres de synchronisation?', 'amazon-product-importer' ); ?>')) {
            const productId = $(this).data('product-id');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'amazon_reset_sync_settings',
                    product_id: productId,
                    nonce: '<?php echo wp_create_nonce( 'amazon_reset_settings' ); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('<?php _e( 'Erreur lors de la réinitialisation', 'amazon-product-importer' ); ?>: ' + response.data);
                    }
                }
            });
        }
    });
});
</script>