<?php
/**
 * Provide the settings page view for the plugin
 *
 * This file is used to markup the settings page of the plugin.
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

// Get current settings values
$sections = $this->get_sections();
$fields = $this->get_fields();
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'api';
?>

<div class="wrap amazon-product-importer-settings">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Amazon Product Importer Settings', 'amazon-product-importer'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=amazon-product-importer'); ?>" class="page-title-action">
        <?php echo esc_html__('Import Products', 'amazon-product-importer'); ?>
    </a>
    
    <hr class="wp-header-end">

    <?php settings_errors('amazon_product_importer_settings'); ?>

    <!-- Settings Navigation Tabs -->
    <nav class="nav-tab-wrapper amazon-settings-tabs">
        <?php foreach ($sections as $section_key => $section): ?>
        <a href="<?php echo admin_url('admin.php?page=amazon-product-importer-settings&tab=' . $section_key); ?>" 
           class="nav-tab <?php echo $current_tab === $section_key ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html($section['title']); ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="" id="amazon-settings-form" class="amazon-settings-form">
        <?php wp_nonce_field('amazon_product_importer_settings_save', 'amazon_product_importer_settings_nonce'); ?>
        
        <div class="amazon-settings-container">
            
            <!-- API Settings Tab -->
            <?php if ($current_tab === 'api'): ?>
            <div class="amazon-settings-section amazon-api-settings">
                <div class="amazon-settings-header">
                    <h2><?php echo esc_html($sections['api']['title']); ?></h2>
                    <p class="description"><?php echo esc_html($sections['api']['desc']); ?></p>
                </div>
                
                <div class="amazon-settings-content">
                    <table class="form-table amazon-form-table">
                        <tbody>
                            <?php 
                            $api_fields = $this->get_section_fields('api');
                            foreach ($api_fields as $field_key => $field): 
                                $field_value = $this->get_field_value($field['id'], $field['default'] ?? '');
                            ?>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr($field['id']); ?>">
                                        <?php echo esc_html($field['title']); ?>
                                        <?php if (!empty($field['required'])): ?>
                                        <span class="amazon-required">*</span>
                                        <?php endif; ?>
                                    </label>
                                </th>
                                <td>
                                    <?php $this->render_field($field, $field_value); ?>
                                    <?php if (!empty($field['desc'])): ?>
                                    <p class="description"><?php echo esc_html($field['desc']); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- API Test Section -->
                    <div class="amazon-api-test-section">
                        <h3><?php echo esc_html__('Test API Connection', 'amazon-product-importer'); ?></h3>
                        <p class="description">
                            <?php echo esc_html__('Test your Amazon API credentials to ensure they are working correctly.', 'amazon-product-importer'); ?>
                        </p>
                        
                        <div class="amazon-api-test-controls">
                            <button type="button" 
                                    id="amazon-test-api-btn" 
                                    class="button button-secondary amazon-test-api-btn">
                                <span class="amazon-test-text"><?php echo esc_html__('Test Connection', 'amazon-product-importer'); ?></span>
                                <span class="amazon-test-loading" style="display: none;">
                                    <span class="spinner is-active"></span>
                                    <?php echo esc_html__('Testing...', 'amazon-product-importer'); ?>
                                </span>
                            </button>
                            
                            <div id="amazon-api-test-result" class="amazon-api-test-result" style="display: none;">
                                <!-- Test result will appear here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Import Settings Tab -->
            <?php if ($current_tab === 'import'): ?>
            <div class="amazon-settings-section amazon-import-settings">
                <div class="amazon-settings-header">
                    <h2><?php echo esc_html($sections['import']['title']); ?></h2>
                    <p class="description"><?php echo esc_html($sections['import']['desc']); ?></p>
                </div>
                
                <div class="amazon-settings-content">
                    <table class="form-table amazon-form-table">
                        <tbody>
                            <?php 
                            $import_fields = $this->get_section_fields('import');
                            foreach ($import_fields as $field_key => $field): 
                                $field_value = $this->get_field_value($field['id'], $field['default'] ?? '');
                            ?>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr($field['id']); ?>">
                                        <?php echo esc_html($field['title']); ?>
                                    </label>
                                </th>
                                <td>
                                    <?php $this->render_field($field, $field_value); ?>
                                    <?php if (!empty($field['desc'])): ?>
                                    <p class="description"><?php echo esc_html($field['desc']); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Import Preview -->
                    <div class="amazon-import-preview-section">
                        <h3><?php echo esc_html__('Import Preview', 'amazon-product-importer'); ?></h3>
                        <p class="description">
                            <?php echo esc_html__('Preview how products will be imported with current settings.', 'amazon-product-importer'); ?>
                        </p>
                        
                        <div class="amazon-preview-example">
                            <div class="amazon-preview-product">
                                <div class="amazon-preview-image">
                                    <div class="amazon-preview-placeholder">
                                        <?php echo esc_html__('Product Image', 'amazon-product-importer'); ?>
                                    </div>
                                </div>
                                <div class="amazon-preview-details">
                                    <h4><?php echo esc_html__('Sample Product Title', 'amazon-product-importer'); ?></h4>
                                    <p class="amazon-preview-status">
                                        <?php echo esc_html__('Status:', 'amazon-product-importer'); ?>
                                        <span class="amazon-preview-status-value">
                                            <?php echo esc_html($this->get_field_value('amazon_product_importer_default_status', 'draft')); ?>
                                        </span>
                                    </p>
                                    <p class="amazon-preview-visibility">
                                        <?php echo esc_html__('Visibility:', 'amazon-product-importer'); ?>
                                        <span class="amazon-preview-visibility-value">
                                            <?php echo esc_html($this->get_field_value('amazon_product_importer_default_visibility', 'visible')); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Sync Settings Tab -->
            <?php if ($current_tab === 'sync'): ?>
            <div class="amazon-settings-section amazon-sync-settings">
                <div class="amazon-settings-header">
                    <h2><?php echo esc_html($sections['sync']['title']); ?></h2>
                    <p class="description"><?php echo esc_html($sections['sync']['desc']); ?></p>
                </div>
                
                <div class="amazon-settings-content">
                    <table class="form-table amazon-form-table">
                        <tbody>
                            <?php 
                            $sync_fields = $this->get_section_fields('sync');
                            foreach ($sync_fields as $field_key => $field): 
                                $field_value = $this->get_field_value($field['id'], $field['default'] ?? '');
                            ?>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr($field['id']); ?>">
                                        <?php echo esc_html($field['title']); ?>
                                    </label>
                                </th>
                                <td>
                                    <?php $this->render_field($field, $field_value); ?>
                                    <?php if (!empty($field['desc'])): ?>
                                    <p class="description"><?php echo esc_html($field['desc']); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Sync Status -->
                    <div class="amazon-sync-status-section">
                        <h3><?php echo esc_html__('Sync Status', 'amazon-product-importer'); ?></h3>
                        
                        <div class="amazon-sync-status-grid">
                            <div class="amazon-sync-status-item">
                                <div class="amazon-sync-status-label">
                                    <?php echo esc_html__('Auto Sync Status', 'amazon-product-importer'); ?>
                                </div>
                                <div class="amazon-sync-status-value">
                                    <?php if ($this->get_field_value('amazon_product_importer_auto_sync_enabled', '1') === '1'): ?>
                                        <span class="amazon-status-active"><?php echo esc_html__('Active', 'amazon-product-importer'); ?></span>
                                    <?php else: ?>
                                        <span class="amazon-status-inactive"><?php echo esc_html__('Inactive', 'amazon-product-importer'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="amazon-sync-status-item">
                                <div class="amazon-sync-status-label">
                                    <?php echo esc_html__('Next Sync', 'amazon-product-importer'); ?>
                                </div>
                                <div class="amazon-sync-status-value">
                                    <?php 
                                    $next_sync = wp_next_scheduled('amazon_product_importer_sync_prices');
                                    if ($next_sync): 
                                    ?>
                                        <?php echo human_time_diff($next_sync, current_time('timestamp')); ?>
                                    <?php else: ?>
                                        <?php echo esc_html__('Not scheduled', 'amazon-product-importer'); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="amazon-sync-status-item">
                                <div class="amazon-sync-status-label">
                                    <?php echo esc_html__('Products with Sync', 'amazon-product-importer'); ?>
                                </div>
                                <div class="amazon-sync-status-value">
                                    <?php 
                                    global $wpdb;
                                    $sync_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_amazon_sync_enabled' AND meta_value = 'yes'");
                                    echo number_format_i18n($sync_count);
                                    ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="amazon-sync-actions">
                            <button type="button" 
                                    id="amazon-force-sync-btn" 
                                    class="button button-secondary amazon-force-sync-btn">
                                <?php echo esc_html__('Force Sync Now', 'amazon-product-importer'); ?>
                            </button>
                            
                            <a href="<?php echo admin_url('admin.php?page=amazon-product-importer-sync'); ?>" 
                               class="button button-secondary">
                                <?php echo esc_html__('View Sync Status', 'amazon-product-importer'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Category Settings Tab -->
            <?php if ($current_tab === 'categories'): ?>
            <div class="amazon-settings-section amazon-category-settings">
                <div class="amazon-settings-header">
                    <h2><?php echo esc_html($sections['categories']['title']); ?></h2>
                    <p class="description"><?php echo esc_html($sections['categories']['desc']); ?></p>
                </div>
                
                <div class="amazon-settings-content">
                    <table class="form-table amazon-form-table">
                        <tbody>
                            <?php 
                            $category_fields = $this->get_section_fields('categories');
                            foreach ($category_fields as $field_key => $field): 
                                $field_value = $this->get_field_value($field['id'], $field['default'] ?? '');
                            ?>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr($field['id']); ?>">
                                        <?php echo esc_html($field['title']); ?>
                                    </label>
                                </th>
                                <td>
                                    <?php $this->render_field($field, $field_value); ?>
                                    <?php if (!empty($field['desc'])): ?>
                                    <p class="description"><?php echo esc_html($field['desc']); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Category Mapping Preview -->
                    <div class="amazon-category-mapping-section">
                        <h3><?php echo esc_html__('Category Mapping Example', 'amazon-product-importer'); ?></h3>
                        <p class="description">
                            <?php echo esc_html__('Example of how Amazon categories will be mapped to WooCommerce categories.', 'amazon-product-importer'); ?>
                        </p>
                        
                        <div class="amazon-category-example">
                            <div class="amazon-category-source">
                                <h4><?php echo esc_html__('Amazon Categories', 'amazon-product-importer'); ?></h4>
                                <ul class="amazon-category-tree">
                                    <li>Electronics
                                        <ul>
                                            <li>Computers & Accessories
                                                <ul>
                                                    <li>Laptops</li>
                                                </ul>
                                            </li>
                                        </ul>
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="amazon-category-arrow">→</div>
                            
                            <div class="amazon-category-target">
                                <h4><?php echo esc_html__('WooCommerce Categories', 'amazon-product-importer'); ?></h4>
                                <ul class="amazon-category-tree">
                                    <li>Electronics
                                        <ul>
                                            <li>Computers
                                                <ul>
                                                    <li>Laptops</li>
                                                </ul>
                                            </li>
                                        </ul>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Advanced Settings Tab -->
            <?php if ($current_tab === 'advanced'): ?>
            <div class="amazon-settings-section amazon-advanced-settings">
                <div class="amazon-settings-header">
                    <h2><?php echo esc_html($sections['advanced']['title']); ?></h2>
                    <p class="description"><?php echo esc_html($sections['advanced']['desc']); ?></p>
                </div>
                
                <div class="amazon-settings-content">
                    <table class="form-table amazon-form-table">
                        <tbody>
                            <?php 
                            $advanced_fields = $this->get_section_fields('advanced');
                            foreach ($advanced_fields as $field_key => $field): 
                                $field_value = $this->get_field_value($field['id'], $field['default'] ?? '');
                            ?>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr($field['id']); ?>">
                                        <?php echo esc_html($field['title']); ?>
                                    </label>
                                </th>
                                <td>
                                    <?php $this->render_field($field, $field_value); ?>
                                    <?php if (!empty($field['desc'])): ?>
                                    <p class="description"><?php echo esc_html($field['desc']); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- System Information -->
                    <div class="amazon-system-info-section">
                        <h3><?php echo esc_html__('System Information', 'amazon-product-importer'); ?></h3>
                        
                        <table class="amazon-system-info-table">
                            <tbody>
                                <tr>
                                    <td><?php echo esc_html__('Plugin Version', 'amazon-product-importer'); ?></td>
                                    <td><?php echo esc_html(AMAZON_PRODUCT_IMPORTER_VERSION); ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo esc_html__('WordPress Version', 'amazon-product-importer'); ?></td>
                                    <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo esc_html__('WooCommerce Version', 'amazon-product-importer'); ?></td>
                                    <td><?php echo esc_html(WC()->version); ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo esc_html__('PHP Version', 'amazon-product-importer'); ?></td>
                                    <td><?php echo esc_html(PHP_VERSION); ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo esc_html__('Memory Limit', 'amazon-product-importer'); ?></td>
                                    <td><?php echo esc_html(ini_get('memory_limit')); ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo esc_html__('Max Execution Time', 'amazon-product-importer'); ?></td>
                                    <td><?php echo esc_html(ini_get('max_execution_time')); ?> seconds</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Data Management -->
                    <div class="amazon-data-management-section">
                        <h3><?php echo esc_html__('Data Management', 'amazon-product-importer'); ?></h3>
                        
                        <div class="amazon-data-actions">
                            <div class="amazon-data-action">
                                <h4><?php echo esc_html__('Export Settings', 'amazon-product-importer'); ?></h4>
                                <p class="description">
                                    <?php echo esc_html__('Export your current plugin settings as a JSON file.', 'amazon-product-importer'); ?>
                                </p>
                                <button type="button" 
                                        id="amazon-export-settings-btn" 
                                        class="button button-secondary">
                                    <?php echo esc_html__('Export Settings', 'amazon-product-importer'); ?>
                                </button>
                            </div>
                            
                            <div class="amazon-data-action">
                                <h4><?php echo esc_html__('Import Settings', 'amazon-product-importer'); ?></h4>
                                <p class="description">
                                    <?php echo esc_html__('Import plugin settings from a JSON file.', 'amazon-product-importer'); ?>
                                </p>
                                <input type="file" 
                                       id="amazon-import-settings-file" 
                                       accept=".json"
                                       style="display: none;">
                                <button type="button" 
                                        id="amazon-import-settings-btn" 
                                        class="button button-secondary">
                                    <?php echo esc_html__('Import Settings', 'amazon-product-importer'); ?>
                                </button>
                            </div>
                            
                            <div class="amazon-data-action amazon-data-danger">
                                <h4><?php echo esc_html__('Reset Settings', 'amazon-product-importer'); ?></h4>
                                <p class="description">
                                    <?php echo esc_html__('Reset all plugin settings to their default values. This action cannot be undone.', 'amazon-product-importer'); ?>
                                </p>
                                <button type="button" 
                                        id="amazon-reset-settings-btn" 
                                        class="button button-secondary amazon-danger-btn">
                                    <?php echo esc_html__('Reset Settings', 'amazon-product-importer'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Form Actions -->
        <div class="amazon-settings-actions">
            <?php submit_button(esc_html__('Save Settings', 'amazon-product-importer'), 'primary', 'submit', false); ?>
            
            <button type="button" 
                    id="amazon-reset-form-btn" 
                    class="button button-secondary amazon-reset-form-btn">
                <?php echo esc_html__('Reset Form', 'amazon-product-importer'); ?>
            </button>
            
            <a href="<?php echo admin_url('admin.php?page=amazon-product-importer'); ?>" 
               class="button button-secondary">
                <?php echo esc_html__('Back to Import', 'amazon-product-importer'); ?>
            </a>
        </div>
    </form>
</div>

<!-- Confirmation Modal -->
<div id="amazon-confirm-modal" class="amazon-modal" style="display: none;">
    <div class="amazon-modal-content amazon-modal-small">
        <div class="amazon-modal-header">
            <h2 id="amazon-confirm-title"><?php echo esc_html__('Confirm Action', 'amazon-product-importer'); ?></h2>
            <button type="button" class="amazon-modal-close">&times;</button>
        </div>
        <div class="amazon-modal-body">
            <p id="amazon-confirm-message"></p>
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

<script type="text/javascript">
    var amazon_settings_data = {
        ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('amazon_product_importer_nonce'); ?>',
        current_tab: '<?php echo esc_js($current_tab); ?>',
        strings: {
            test_connection: '<?php echo esc_js(__('Test Connection', 'amazon-product-importer')); ?>',
            testing: '<?php echo esc_js(__('Testing...', 'amazon-product-importer')); ?>',
            connection_success: '<?php echo esc_js(__('Connection successful!', 'amazon-product-importer')); ?>',
            connection_failed: '<?php echo esc_js(__('Connection failed. Please check your credentials.', 'amazon-product-importer')); ?>',
            confirm_reset: '<?php echo esc_js(__('Are you sure you want to reset all settings to default values? This cannot be undone.', 'amazon-product-importer')); ?>',
            confirm_force_sync: '<?php echo esc_js(__('This will immediately sync all products with Amazon. Continue?', 'amazon-product-importer')); ?>',
            settings_saved: '<?php echo esc_js(__('Settings saved successfully!', 'amazon-product-importer')); ?>',
            settings_error: '<?php echo esc_js(__('Error saving settings. Please try again.', 'amazon-product-importer')); ?>'
        }
    };
</script>

<?php
// Helper method to render fields
if (!method_exists($this, 'render_field')) {
    /**
     * Render a settings field
     */
    function render_field($field, $value) {
        $field_id = esc_attr($field['id']);
        $field_name = esc_attr($field['id']);
        
        switch ($field['type']) {
            case 'text':
            case 'email':
            case 'url':
                printf(
                    '<input type="%s" id="%s" name="%s" value="%s" class="regular-text" %s>',
                    esc_attr($field['type']),
                    $field_id,
                    $field_name,
                    esc_attr($value),
                    !empty($field['required']) ? 'required' : ''
                );
                break;
                
            case 'password':
                printf(
                    '<input type="password" id="%s" name="%s" value="%s" class="regular-text" %s>',
                    $field_id,
                    $field_name,
                    esc_attr($value),
                    !empty($field['required']) ? 'required' : ''
                );
                break;
                
            case 'number':
                printf(
                    '<input type="number" id="%s" name="%s" value="%s" class="small-text" min="%s" max="%s" step="%s">',
                    $field_id,
                    $field_name,
                    esc_attr($value),
                    isset($field['min']) ? esc_attr($field['min']) : '',
                    isset($field['max']) ? esc_attr($field['max']) : '',
                    isset($field['step']) ? esc_attr($field['step']) : '1'
                );
                break;
                
            case 'textarea':
                printf(
                    '<textarea id="%s" name="%s" rows="5" cols="50" class="large-text">%s</textarea>',
                    $field_id,
                    $field_name,
                    esc_textarea($value)
                );
                break;
                
            case 'select':
                printf('<select id="%s" name="%s" class="regular-text">', $field_id, $field_name);
                if (!empty($field['options'])) {
                    foreach ($field['options'] as $option_value => $option_label) {
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr($option_value),
                            selected($value, $option_value, false),
                            esc_html($option_label)
                        );
                    }
                }
                echo '</select>';
                break;
                
            case 'checkbox':
                printf(
                    '<input type="checkbox" id="%s" name="%s" value="1" %s>',
                    $field_id,
                    $field_name,
                    checked($value, '1', false)
                );
                break;
                
            default:
                printf(
                    '<input type="text" id="%s" name="%s" value="%s" class="regular-text">',
                    $field_id,
                    $field_name,
                    esc_attr($value)
                );
                break;
        }
    }
}
?>