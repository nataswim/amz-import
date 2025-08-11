<?php
/**
 * Admin Import Display Template
 *
 * @link       https://mycreanet.fr
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Check user capabilities
if (!current_user_can('manage_woocommerce')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Get current settings for search filters
$api_configured = get_option('amazon_product_importer_access_key_id') && 
                  get_option('amazon_product_importer_secret_access_key') && 
                  get_option('amazon_product_importer_associate_tag');

$marketplace = get_option('amazon_product_importer_marketplace', 'www.amazon.com');
$region = get_option('amazon_product_importer_region', 'us-east-1');

// Get import history
global $wpdb;
$recent_imports = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}amazon_import_log 
     WHERE action_type = 'import' 
     ORDER BY created_at DESC 
     LIMIT 10"
);
?>

<div class="wrap amazon-importer-wrap">
    <!-- Header Section -->
    <div class="amazon-importer-header">
        <div class="header-content">
            <div class="header-title">
                <img src="<?php echo AMAZON_PRODUCT_IMPORTER_PLUGIN_URL; ?>assets/images/amazon-logo.png" 
                     alt="Amazon" class="amazon-logo">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            </div>
            <div class="header-actions">
                <button type="button" id="bulk-import-btn" class="button button-secondary">
                    <span class="dashicons dashicons-upload"></span>
                    <?php _e('Import en masse', 'amazon-product-importer'); ?>
                </button>
                <button type="button" id="import-history-btn" class="button button-secondary">
                    <span class="dashicons dashicons-backup"></span>
                    <?php _e('Historique', 'amazon-product-importer'); ?>
                </button>
            </div>
        </div>
    </div>

    <?php if (!$api_configured): ?>
    <!-- Configuration Warning -->
    <div class="notice notice-warning">
        <p>
            <strong><?php _e('Configuration requise', 'amazon-product-importer'); ?></strong>
            <?php _e('Veuillez configurer vos clés API Amazon dans les ', 'amazon-product-importer'); ?>
            <a href="<?php echo admin_url('admin.php?page=amazon-product-importer-settings'); ?>">
                <?php _e('paramètres', 'amazon-product-importer'); ?>
            </a>
            <?php _e(' avant d\'importer des produits.', 'amazon-product-importer'); ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Main Import Interface -->
    <div class="amazon-import-interface <?php echo !$api_configured ? 'disabled' : ''; ?>">
        
        <!-- Search Section -->
        <div class="import-search-section">
            <div class="section-header">
                <h2><?php _e('Rechercher des Produits Amazon', 'amazon-product-importer'); ?></h2>
                <p class="section-description">
                    <?php _e('Recherchez des produits Amazon par mots-clés ou ASIN pour les importer dans votre boutique.', 'amazon-product-importer'); ?>
                </p>
            </div>
            
            <div class="search-controls-container">
                <!-- Search Type Selector -->
                <div class="search-type-selector">
                    <div class="radio-group">
                        <label class="radio-label active">
                            <input type="radio" name="search_type" value="keywords" checked>
                            <span class="radio-custom"></span>
                            <span class="radio-text"><?php _e('Recherche par mots-clés', 'amazon-product-importer'); ?></span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="search_type" value="asin">
                            <span class="radio-custom"></span>
                            <span class="radio-text"><?php _e('Recherche par ASIN', 'amazon-product-importer'); ?></span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="search_type" value="bulk_asin">
                            <span class="radio-custom"></span>
                            <span class="radio-text"><?php _e('Plusieurs ASIN', 'amazon-product-importer'); ?></span>
                        </label>
                    </div>
                </div>
                
                <!-- Search Input Group -->
                <div class="search-input-group">
                    <div class="input-container">
                        <input type="text" 
                               id="amazon-search-query" 
                               class="search-input"
                               placeholder="<?php _e('Entrez vos mots-clés...', 'amazon-product-importer'); ?>"
                               <?php echo !$api_configured ? 'disabled' : ''; ?>>
                        <textarea id="amazon-bulk-asin" 
                                  class="search-textarea" 
                                  placeholder="<?php _e('Entrez les ASIN séparés par des virgules ou des retours à la ligne...', 'amazon-product-importer'); ?>"
                                  style="display: none;"
                                  <?php echo !$api_configured ? 'disabled' : ''; ?>></textarea>
                    </div>
                    <button type="button" 
                            id="amazon-search-btn" 
                            class="button button-primary search-button"
                            <?php echo !$api_configured ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-search"></span>
                        <span class="button-text"><?php _e('Rechercher', 'amazon-product-importer'); ?></span>
                        <span class="loading-spinner"></span>
                    </button>
                </div>
                
                <!-- Advanced Search Filters -->
                <div class="search-filters" id="search-filters">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="amazon-category-filter"><?php _e('Catégorie', 'amazon-product-importer'); ?></label>
                            <select id="amazon-category-filter" class="filter-select" <?php echo !$api_configured ? 'disabled' : ''; ?>>
                                <option value=""><?php _e('Toutes catégories', 'amazon-product-importer'); ?></option>
                                <option value="All"><?php _e('Tous les départements', 'amazon-product-importer'); ?></option>
                                <option value="Electronics"><?php _e('Électronique', 'amazon-product-importer'); ?></option>
                                <option value="Clothing"><?php _e('Vêtements', 'amazon-product-importer'); ?></option>
                                <option value="Books"><?php _e('Livres', 'amazon-product-importer'); ?></option>
                                <option value="Sports"><?php _e('Sports', 'amazon-product-importer'); ?></option>
                                <option value="Home"><?php _e('Maison', 'amazon-product-importer'); ?></option>
                                <option value="Beauty"><?php _e('Beauté', 'amazon-product-importer'); ?></option>
                                <option value="Automotive"><?php _e('Automobile', 'amazon-product-importer'); ?></option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="amazon-sort-by"><?php _e('Trier par', 'amazon-product-importer'); ?></label>
                            <select id="amazon-sort-by" class="filter-select" <?php echo !$api_configured ? 'disabled' : ''; ?>>
                                <option value="Relevance"><?php _e('Pertinence', 'amazon-product-importer'); ?></option>
                                <option value="Price:LowToHigh"><?php _e('Prix croissant', 'amazon-product-importer'); ?></option>
                                <option value="Price:HighToLow"><?php _e('Prix décroissant', 'amazon-product-importer'); ?></option>
                                <option value="NewestArrivals"><?php _e('Nouveautés', 'amazon-product-importer'); ?></option>
                                <option value="AvgCustomerReviews"><?php _e('Meilleures évaluations', 'amazon-product-importer'); ?></option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="amazon-item-count"><?php _e('Résultats par page', 'amazon-product-importer'); ?></label>
                            <select id="amazon-item-count" class="filter-select" <?php echo !$api_configured ? 'disabled' : ''; ?>>
                                <option value="10">10</option>
                                <option value="20" selected>20</option>
                                <option value="30">30</option>
                                <option value="50">50</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <button type="button" id="advanced-filters-toggle" class="button button-link">
                                <span class="dashicons dashicons-admin-generic"></span>
                                <?php _e('Filtres avancés', 'amazon-product-importer'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Advanced Filters (Hidden by default) -->
                    <div class="advanced-filters" id="advanced-filters" style="display: none;">
                        <div class="filters-row">
                            <div class="filter-group">
                                <label for="min-price"><?php _e('Prix minimum', 'amazon-product-importer'); ?></label>
                                <input type="number" id="min-price" class="filter-input" min="0" step="0.01">
                            </div>
                            <div class="filter-group">
                                <label for="max-price"><?php _e('Prix maximum', 'amazon-product-importer'); ?></label>
                                <input type="number" id="max-price" class="filter-input" min="0" step="0.01">
                            </div>
                            <div class="filter-group">
                                <label for="brand-filter"><?php _e('Marque', 'amazon-product-importer'); ?></label>
                                <input type="text" id="brand-filter" class="filter-input" placeholder="<?php _e('Nom de la marque...', 'amazon-product-importer'); ?>">
                            </div>
                            <div class="filter-group">
                                <label for="condition-filter"><?php _e('État', 'amazon-product-importer'); ?></label>
                                <select id="condition-filter" class="filter-select">
                                    <option value=""><?php _e('Tous les états', 'amazon-product-importer'); ?></option>
                                    <option value="New"><?php _e('Neuf', 'amazon-product-importer'); ?></option>
                                    <option value="Used"><?php _e('Occasion', 'amazon-product-importer'); ?></option>
                                    <option value="Refurbished"><?php _e('Reconditionné', 'amazon-product-importer'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Search Status -->
        <div class="search-status" id="search-status" style="display: none;">
            <div class="status-content">
                <span class="status-text"></span>
                <span class="status-count"></span>
            </div>
        </div>
        
        <!-- Search Results -->
        <div class="search-results-section">
            <div class="results-header" id="results-header" style="display: none;">
                <div class="results-info">
                    <h3><?php _e('Résultats de recherche', 'amazon-product-importer'); ?></h3>
                    <span class="results-count" id="results-count"></span>
                </div>
                <div class="results-actions">
                    <div class="view-toggle">
                        <button type="button" class="view-btn active" data-view="grid">
                            <span class="dashicons dashicons-grid-view"></span>
                        </button>
                        <button type="button" class="view-btn" data-view="list">
                            <span class="dashicons dashicons-list-view"></span>
                        </button>
                    </div>
                    <div class="bulk-actions">
                        <button type="button" id="select-all-btn" class="button button-secondary">
                            <?php _e('Tout sélectionner', 'amazon-product-importer'); ?>
                        </button>
                        <button type="button" id="import-selected-btn" class="button button-primary" disabled>
                            <?php _e('Importer sélectionnés', 'amazon-product-importer'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Results Container -->
            <div id="amazon-search-results" class="search-results-grid">
                <!-- Results will be populated via AJAX -->
            </div>
            
            <!-- Pagination -->
            <div class="results-pagination" id="results-pagination" style="display: none;">
                <button type="button" id="load-more-btn" class="button button-large">
                    <?php _e('Charger plus de résultats', 'amazon-product-importer'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Import Progress Modal -->
    <div id="import-progress-modal" class="amazon-modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php _e('Import en cours...', 'amazon-product-importer'); ?></h3>
                <button type="button" class="modal-close" aria-label="<?php _e('Fermer', 'amazon-product-importer'); ?>">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="modal-body">
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill"></div>
                    </div>
                    <div class="progress-info">
                        <span class="progress-text" id="progress-text">0%</span>
                        <span class="progress-details" id="progress-details"></span>
                    </div>
                </div>
                <div class="import-log" id="import-log">
                    <!-- Import progress messages will appear here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="cancel-import-btn" class="button button-secondary">
                    <?php _e('Annuler', 'amazon-product-importer'); ?>
                </button>
                <button type="button" id="close-modal-btn" class="button button-primary" style="display: none;">
                    <?php _e('Fermer', 'amazon-product-importer'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Bulk Import Modal -->
    <div id="bulk-import-modal" class="amazon-modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php _e('Import en masse', 'amazon-product-importer'); ?></h3>
                <button type="button" class="modal-close">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="modal-body">
                <div class="bulk-import-options">
                    <div class="import-option">
                        <h4><?php _e('Import depuis un fichier CSV', 'amazon-product-importer'); ?></h4>
                        <p><?php _e('Importez plusieurs produits depuis un fichier CSV contenant les ASIN.', 'amazon-product-importer'); ?></p>
                        <div class="file-upload-area">
                            <input type="file" id="csv-file-input" accept=".csv" style="display: none;">
                            <button type="button" id="csv-upload-btn" class="button button-secondary">
                                <span class="dashicons dashicons-upload"></span>
                                <?php _e('Choisir un fichier CSV', 'amazon-product-importer'); ?>
                            </button>
                            <span class="file-name" id="csv-file-name"></span>
                        </div>
                        <a href="<?php echo AMAZON_PRODUCT_IMPORTER_PLUGIN_URL; ?>assets/sample-import.csv" class="download-sample">
                            <?php _e('Télécharger un exemple de fichier CSV', 'amazon-product-importer'); ?>
                        </a>
                    </div>
                    
                    <div class="import-option">
                        <h4><?php _e('Import depuis une liste de souhaits Amazon', 'amazon-product-importer'); ?></h4>
                        <p><?php _e('Importez tous les produits depuis une liste de souhaits Amazon publique.', 'amazon-product-importer'); ?></p>
                        <div class="wishlist-input-area">
                            <input type="url" 
                                   id="wishlist-url-input" 
                                   class="regular-text"
                                   placeholder="https://www.amazon.com/hz/wishlist/ls/XXXXXXXXXX">
                            <button type="button" id="wishlist-import-btn" class="button button-secondary">
                                <?php _e('Importer la liste', 'amazon-product-importer'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Import History Modal -->
    <div id="import-history-modal" class="amazon-modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-content large">
            <div class="modal-header">
                <h3><?php _e('Historique des imports', 'amazon-product-importer'); ?></h3>
                <button type="button" class="modal-close">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="modal-body">
                <div class="history-filters">
                    <select id="history-filter-status">
                        <option value=""><?php _e('Tous les statuts', 'amazon-product-importer'); ?></option>
                        <option value="success"><?php _e('Réussis', 'amazon-product-importer'); ?></option>
                        <option value="error"><?php _e('Échoués', 'amazon-product-importer'); ?></option>
                    </select>
                    <input type="date" id="history-filter-date">
                    <button type="button" id="refresh-history-btn" class="button button-secondary">
                        <?php _e('Actualiser', 'amazon-product-importer'); ?>
                    </button>
                </div>
                <div class="history-table-container">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('ASIN', 'amazon-product-importer'); ?></th>
                                <th><?php _e('Produit', 'amazon-product-importer'); ?></th>
                                <th><?php _e('Statut', 'amazon-product-importer'); ?></th>
                                <th><?php _e('Date', 'amazon-product-importer'); ?></th>
                                <th><?php _e('Actions', 'amazon-product-importer'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="import-history-table">
                            <!-- History entries will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Data -->
<script type="text/javascript">
var amazon_importer_ajax = {
    url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('amazon_importer_nonce'); ?>',
    marketplace: '<?php echo esc_js($marketplace); ?>',
    region: '<?php echo esc_js($region); ?>',
    api_configured: <?php echo $api_configured ? 'true' : 'false'; ?>,
    strings: {
        searching: '<?php _e('Recherche en cours...', 'amazon-product-importer'); ?>',
        importing: '<?php _e('Import en cours...', 'amazon-product-importer'); ?>',
        import_success: '<?php _e('Produit importé avec succès', 'amazon-product-importer'); ?>',
        import_error: '<?php _e('Erreur lors de l\'import', 'amazon-product-importer'); ?>',
        no_results: '<?php _e('Aucun résultat trouvé', 'amazon-product-importer'); ?>',
        select_products: '<?php _e('Veuillez sélectionner des produits à importer', 'amazon-product-importer'); ?>',
        confirm_cancel: '<?php _e('Êtes-vous sûr de vouloir annuler l\'import ?', 'amazon-product-importer'); ?>'
    }
};
</script>