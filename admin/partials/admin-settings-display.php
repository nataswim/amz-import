<?php
/**
 * Settings page template
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get current settings
$api_access_key_id = get_option('amazon_importer_api_access_key_id', '');
$api_secret_access_key = get_option('amazon_importer_api_secret_access_key', '');
$api_associate_tag = get_option('amazon_importer_api_associate_tag', '');
$api_region = get_option('amazon_importer_api_region', 'com');
$thumbnail_size = get_option('amazon_importer_ams_product_thumbnail_size', 'large');
$product_name_cron = get_option('amazon_importer_product_name_cron', false);
$product_category_cron = get_option('amazon_importer_product_category_cron', false);
$product_sku_cron = get_option('amazon_importer_product_sku_cron', false);
$price_sync_enabled = get_option('amazon_importer_price_sync_enabled', true);
$price_sync_frequency = get_option('amazon_importer_price_sync_frequency', 'hourly');
$category_min_depth = get_option('amazon_importer_category_min_depth', 1);
$category_max_depth = get_option('amazon_importer_category_max_depth', 3);
$debug_mode = get_option('amazon_importer_debug_mode', false);
?>

<div class="wrap amazon-importer-wrap">
    <div class="amazon-importer-header">
        <img src="<?php echo AMAZON_PRODUCT_IMPORTER_PLUGIN_URL; ?>assets/images/amazon-logo.png" 
             alt="Amazon" class="amazon-logo">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    </div>

    <form method="post" action="options.php" id="amazon-importer-settings-form">
        <?php
        settings_fields('amazon_importer_settings');
        do_settings_sections('amazon_importer_settings');
        ?>

        <div class="amazon-importer-tabs">
            <nav class="nav-tab-wrapper">
                <a href="#api-settings" class="nav-tab nav-tab-active"><?php _e('Configuration API', 'amazon-product-importer'); ?></a>
                <a href="#import-settings" class="nav-tab"><?php _e('Paramètres d\'importation', 'amazon-product-importer'); ?></a>
                <a href="#sync-settings" class="nav-tab"><?php _e('Synchronisation', 'amazon-product-importer'); ?></a>
                <a href="#advanced-settings" class="nav-tab"><?php _e('Avancé', 'amazon-product-importer'); ?></a>
            </nav>
        </div>

        <!-- API Settings -->
        <div id="api-settings" class="amazon-tab-content amazon-settings-section">
            <h3><?php _e('Configuration de l\'API Amazon', 'amazon-product-importer'); ?></h3>
            <p><?php _e('Configurez vos clés d\'accès Amazon Product Advertising API.', 'amazon-product-importer'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="api_access_key_id"><?php _e('Access Key ID', 'amazon-product-importer'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="api_access_key_id" 
                               name="amazon_importer_api_access_key_id" 
                               value="<?php echo esc_attr($api_access_key_id); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php _e('Votre clé d\'accès AWS fournie par Amazon', 'amazon-product-importer'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="api_secret_access_key"><?php _e('Secret Access Key', 'amazon-product-importer'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               id="api_secret_access_key" 
                               name="amazon_importer_api_secret_access_key" 
                               value="<?php echo esc_attr($api_secret_access_key); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php _e('Votre clé secrète AWS (gardée confidentielle)', 'amazon-product-importer'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="api_associate_tag"><?php _e('Associate Tag', 'amazon-product-importer'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="api_associate_tag" 
                               name="amazon_importer_api_associate_tag" 
                               value="<?php echo esc_attr($api_associate_tag); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php _e('Votre tag d\'associé Amazon pour l\'affiliation', 'amazon-product-importer'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="api_region"><?php _e('Région', 'amazon-product-importer'); ?></label>
                    </th>
                    <td>
                        <select id="api_region" name="amazon_importer_api_region">
                            <option value="com" <?php selected($api_region, 'com'); ?>>États-Unis (.com)</option>
                            <option value="co.uk" <?php selected($api_region, 'co.uk'); ?>>Royaume-Uni (.co.uk)</option>
                            <option value="de" <?php selected($api_region, 'de'); ?>>Allemagne (.de)</option>
                            <option value="fr" <?php selected($api_region, 'fr'); ?>>France (.fr)</option>
                            <option value="it" <?php selected($api_region, 'it'); ?>>Italie (.it)</option>
                            <option value="es" <?php selected($api_region, 'es'); ?>>Espagne (.es)</option>
                            <option value="ca" <?php selected($api_region, 'ca'); ?>>Canada (.ca)</option>
                            <option value="co.jp" <?php selected($api_region, 'co.jp'); ?>>Japon (.co.jp)</option>
                            <option value="in" <?php selected($api_region, 'in'); ?>>Inde (.in)</option>
                            <option value="com.br" <?php selected($api_region, 'com.br'); ?>>Brésil (.com.br)</option>
                            <option value="com.mx" <?php selected($api_region, 'com.mx'); ?>>Mexique (.com.mx)</option>
                            <option value="com.au" <?php selected($api_region, 'com.au'); ?>>Australie (.com.au)</option>
                        </select>
                        <p class="description">
                            <?php _e('Sélectionnez la région Amazon à utiliser', 'amazon-product-importer'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <div class="api-test-section" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
                <h4><?php _e('Test de connexion API', 'amazon-product-importer'); ?></h4>
                <p><?php _e('Testez votre configuration API avant de sauvegarder.', 'amazon-product-importer'); ?></p>
                <button type="button" id="test-api-connection" class="button">
                    <?php _e('Tester la connexion', 'amazon-product-importer'); ?>
                </button>
                <div id="api-connection-status" style="margin-top: 10px;"></div>
            </div>
        </div>

        <!-- Import Settings -->
        <div id="import-settings" class="amazon-tab-content amazon-settings-section" style="display: none;">
            <h3><?php _e('Paramètres d\'importation', 'amazon-product-importer'); ?></h3>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ams_product_thumbnail_size"><?php _e('Taille des images', 'amazon-product-importer'); ?></label>
                    </th>
                    <td>
                        <select id="ams_product_thumbnail_size" name="amazon_importer_ams_product_thumbnail_size">
                            <option value="small" <?php selected($thumbnail_size, 'small'); ?>><?php _e('Petite (75x75)', 'amazon-product-importer'); ?></option>
                            <option value="medium" <?php selected($thumbnail_size, 'medium'); ?>><?php _e('Moyenne (160x160)', 'amazon-product-importer'); ?></option>
                            <option value="large" <?php selected($thumbnail_size, 'large'); ?>><?php _e('Grande (500x500)', 'amazon-product-importer'); ?></option>
                        </select>
                        <p class="description">
                            <?php _e('Taille par défaut des images à importer depuis Amazon', 'amazon-product-importer'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="category_min_depth"><?php _e('Profondeur minimale des catégories', 'amazon-product-importer'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="category_min_depth" 
                               name="amazon_importer_category_min_depth" 
                               value="<?php echo esc_attr($category_min_depth); ?>" 
                               min="0" max="10" />
                        <p class="description">
                            <?php _e('Profondeur minimale pour l\'importation des catégories Amazon', 'amazon-product-importer'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="category_max_depth"><?php _e('Profondeur maximale des catégories', 'amazon-product-importer'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="category_max_depth" 
                               name="amazon_importer_category_max_depth" 
                               value="<?php echo esc_attr($category_max_depth); ?>" 
                               min="1" max="10" />
                        <p class="description">
                            <?php _e('Profondeur maximale pour l\'importation des catégories Amazon', 'amazon-product-importer'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Sync Settings -->
        <div id="sync-settings" class="amazon-tab-content amazon-settings-section" style="display: none;">
            <h3><?php _e('Paramètres de synchronisation', 'amazon-product-importer'); ?></h3>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="price_sync_enabled"><?php _e('Synchronisation des prix', 'amazon-product-importer'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="price_sync_enabled" 
                                   name="amazon_importer_price_sync_enabled" 
                                   value="1" 
                                   <?php checked($price_sync_enabled, 1); ?> />
                            <?php _e('Activer la synchronisation automatique des prix', 'amazon-product-importer'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Met à jour automatiquement les prix des produits importés', 'amazon-product-importer'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="price_sync_frequency"><?php _e('Fréquence de synchronisation', 'amazon-product-importer'); ?></label>
                    </th>
                    <td>
                        <select id="price_sync_frequency" name="amazon_importer_price_sync_frequency">
                            <option value="hourly" <?php selected($price_sync_frequency, 'hourly'); ?>><?php _e('Toutes les heures', 'amazon-product-importer'); ?></option>
                            <option value="daily" <?php selected($price_sync_frequency, 'daily'); ?>><?php _e('Quotidienne', 'amazon-product-importer'); ?></option>
                            <option value="weekly" <?php selected($price_sync_frequency, 'weekly'); ?>><?php _e('Hebdomadaire', 'amazon-product-importer'); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Éléments à synchroniser', 'amazon-product-importer'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="amazon_importer_product_name_cron" 
                                   value="1" 
                                   <?php checked($product_name_cron, 1); ?> />
                            <?php _e('Noms des produits', 'amazon-product-importer'); ?>
                        </label><br>

                        <label>
                            <input type="checkbox" 
                                   name="amazon_importer_product_category_cron" 
                                   value="1" 
                                   <?php checked($product_category_cron, 1); ?> />
                            <?php _e('Catégories des produits', 'amazon-product-importer'); ?>
                        </label><br>

                        <label>
                            <input type="checkbox" 
                                   name="amazon_importer_product_sku_cron" 
                                   value="1" 
                                   <?php checked($product_sku_cron, 1); ?> />
                            <?php _e('SKU des produits', 'amazon-product-importer'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Advanced Settings -->
        <div id="advanced-settings" class="amazon-tab-content amazon-settings-section" style="display: none;">
            <h3><?php _e('Paramètres avancés', 'amazon-product-importer'); ?></h3>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="debug_mode"><?php _e('Mode debug', 'amazon-product-importer'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="debug_mode" 
                                   name="amazon_importer_debug_mode" 
                                   value="1" 
                                   <?php checked($debug_mode, 1); ?> />
                            <?php _e('Activer le mode debug (logs détaillés)', 'amazon-product-importer'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Active les logs détaillés pour le débogage. À désactiver en production.', 'amazon-product-importer'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <div class="advanced-actions" style="margin-top: 30px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7;">
                <h4><?php _e('Actions avancées', 'amazon-product-importer'); ?></h4>
                
                <p>
                    <button type="button" id="clear-cache" class="button">
                        <?php _e('Vider le cache', 'amazon-product-importer'); ?>
                    </button>
                    <span class="description">
                        <?php _e('Supprime tous les caches API stockés', 'amazon-product-importer'); ?>
                    </span>
                </p>

                <p>
                    <button type="button" id="reset-sync-queue" class="button">
                        <?php _e('Réinitialiser la file de synchronisation', 'amazon-product-importer'); ?>
                    </button>
                    <span class="description">
                        <?php _e('Remet à zéro la file d\'attente de synchronisation', 'amazon-product-importer'); ?>
                    </span>
                </p>

                <p style="color: #d63384;">
                    <button type="button" id="reset-settings" class="button button-secondary">
                        <?php _e('Réinitialiser tous les paramètres', 'amazon-product-importer'); ?>
                    </button>
                    <span class="description">
                        <?php _e('⚠️ Remet tous les paramètres à leurs valeurs par défaut', 'amazon-product-importer'); ?>
                    </span>
                </p>
            </div>
        </div>

        <?php submit_button(__('Sauvegarder les paramètres', 'amazon-product-importer')); ?>
    </form>
</div>