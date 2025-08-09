<?php
/**
 * Provide a admin area view for the plugin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$api_configured = get_option('amazon_importer_api_access_key_id') && 
                  get_option('amazon_importer_api_secret_access_key') && 
                  get_option('amazon_importer_api_associate_tag');
?>

<div class="wrap amazon-importer-wrap">
    <div class="amazon-importer-header">
        <img src="<?php echo AMAZON_PRODUCT_IMPORTER_PLUGIN_URL; ?>assets/images/amazon-logo.png" 
             alt="Amazon" class="amazon-logo">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    </div>

    <?php if (!$api_configured): ?>
        <div class="notice notice-warning">
            <p>
                <?php _e('Veuillez configurer vos clés API Amazon dans les ', 'amazon-product-importer'); ?>
                <a href="<?php echo admin_url('admin.php?page=amazon-importer-settings'); ?>">paramètres</a>
                <?php _e(' avant d\'importer des produits.', 'amazon-product-importer'); ?>
            </p>
        </div>
    <?php endif; ?>

    <div id="amazon-messages"></div>

    <div class="amazon-importer-content">
        
        <!-- Search Form -->
        <div class="amazon-search-form">
            <h3><?php _e('Rechercher des produits Amazon', 'amazon-product-importer'); ?></h3>
            
            <form id="amazon-search-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php _e('Type de recherche', 'amazon-product-importer'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="radio" name="search_type" value="keywords" checked>
                                <?php _e('Par mots-clés', 'amazon-product-importer'); ?>
                            </label>
                            <label style="margin-left: 20px;">
                                <input type="radio" name="search_type" value="asin">
                                <?php _e('Par ASIN', 'amazon-product-importer'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="search-keywords"><?php _e('Mots-clés / ASIN', 'amazon-product-importer'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="search-keywords" 
                                   name="keywords" 
                                   placeholder="iPhone, Samsung Galaxy, ..."
                                   class="regular-text"
                                   <?php echo !$api_configured ? 'disabled' : ''; ?>>
                            <p class="description">
                                <?php _e('Entrez des mots-clés pour rechercher ou des ASIN séparés par des virgules', 'amazon-product-importer'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="search-category"><?php _e('Catégorie', 'amazon-product-importer'); ?></label>
                        </th>
                        <td>
                            <select id="search-category" name="category" <?php echo !$api_configured ? 'disabled' : ''; ?>>
                                <option value=""><?php _e('Toutes les catégories', 'amazon-product-importer'); ?></option>
                                <option value="Electronics"><?php _e('Électronique', 'amazon-product-importer'); ?></option>
                                <option value="Clothing"><?php _e('Vêtements', 'amazon-product-importer'); ?></option>
                                <option value="Books"><?php _e('Livres', 'amazon-product-importer'); ?></option>
                                <option value="Home"><?php _e('Maison & Jardin', 'amazon-product-importer'); ?></option>
                                <option value="Sports"><?php _e('Sports', 'amazon-product-importer'); ?></option>
                                <option value="Toys"><?php _e('Jouets', 'amazon-product-importer'); ?></option>
                                <option value="Beauty"><?php _e('Beauté', 'amazon-product-importer'); ?></option>
                                <option value="Automotive"><?php _e('Auto & Moto', 'amazon-product-importer'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th></th>
                        <td>
                            <button type="submit" 
                                    id="search-button" 
                                    class="button search-button"
                                    <?php echo !$api_configured ? 'disabled' : ''; ?>>
                                <span class="dashicons dashicons-search"></span>
                                <?php _e('Rechercher', 'amazon-product-importer'); ?>
                            </button>
                            
                            <button type="button" 
                                    id="clear-results" 
                                    class="button"
                                    style="margin-left: 10px;">
                                <?php _e('Effacer', 'amazon-product-importer'); ?>
                            </button>
                        </td>
                    </tr>
                </table>
            </form>
        </div>

        <!-- Loading Animation -->
        <div id="amazon-loading" class="amazon-loading">
            <span class="spinner is-active"></span>
            <p><?php _e('Recherche en cours...', 'amazon-product-importer'); ?></p>
        </div>

        <!-- Search Statistics -->
        <div id="search-stats" style="display: none; margin: 15px 0; font-weight: bold;"></div>

        <!-- Bulk Import Controls -->
        <div id="bulk-import-controls" style="display: none; margin: 15px 0;">
            <label>
                <input type="checkbox" id="select-all-products">
                <?php _e('Tout sélectionner', 'amazon-product-importer'); ?>
            </label>
            
            <button type="button" 
                    id="bulk-import-selected" 
                    class="button button-primary" 
                    disabled
                    style="margin-left: 15px;">
                <?php _e('Importer la sélection', 'amazon-product-importer'); ?>
            </button>
        </div>

        <!-- Bulk Import Progress -->
        <div id="bulk-progress-wrap" class="amazon-progress-wrap" style="display: none;">
            <div id="bulk-progress-bar" class="amazon-progress-bar">
                <div class="amazon-progress-fill"></div>
            </div>
            <div id="bulk-progress-text" class="amazon-progress-text"></div>
        </div>

        <!-- Search Results -->
        <div id="amazon-results" class="amazon-results">
            <div class="amazon-results-header">
                <h3><?php _e('Résultats de la recherche', 'amazon-product-importer'); ?></h3>
            </div>
            
            <div id="amazon-products-grid" class="amazon-products-grid">
                <!-- Products will be loaded here via AJAX -->
            </div>
        </div>

    </div>

    <!-- Import Statistics (if available) -->
    <?php
    $import_stats = get_option('amazon_importer_stats', array());
    if (!empty($import_stats)):
    ?>
        <div class="amazon-import-stats" style="margin-top: 30px; background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
            <h3><?php _e('Statistiques d\'importation', 'amazon-product-importer'); ?></h3>
            <p>
                <?php _e('Produits importés aujourd\'hui:', 'amazon-product-importer'); ?> 
                <strong id="import-counter"><?php echo isset($import_stats['today']) ? $import_stats['today'] : 0; ?></strong>
            </p>
            <p>
                <?php _e('Total des produits importés:', 'amazon-product-importer'); ?> 
                <strong><?php echo isset($import_stats['total']) ? $import_stats['total'] : 0; ?></strong>
            </p>
        </div>
    <?php endif; ?>

    <!-- Help Section -->
    <div class="amazon-help-section" style="margin-top: 30px; background: #f9f9f9; padding: 20px; border-left: 4px solid #0073aa;">
        <h3><?php _e('Guide d\'utilisation', 'amazon-product-importer'); ?></h3>
        <ul>
            <li><?php _e('Recherchez des produits par mots-clés ou entrez directement des codes ASIN', 'amazon-product-importer'); ?></li>
            <li><?php _e('Utilisez les catégories pour affiner vos résultats de recherche', 'amazon-product-importer'); ?></li>
            <li><?php _e('Cliquez sur "Importer" pour ajouter un produit à votre boutique', 'amazon-product-importer'); ?></li>
            <li><?php _e('Utilisez l\'importation en lot pour importer plusieurs produits à la fois', 'amazon-product-importer'); ?></li>
            <li><?php _e('Les produits importés seront automatiquement créés en tant que produits WooCommerce', 'amazon-product-importer'); ?></li>
        </ul>
    </div>
</div>