<?php
/**
 * Amazon Import Status Widget Template
 *
 * This template displays the current import status and statistics
 * in the WordPress admin dashboard.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/templates/admin/widgets
 * @since      1.0.0
 * 
 * @var array  $import_stats     Import statistics and metrics
 * @var array  $current_imports  Currently running imports
 * @var array  $recent_activity  Recent import activity
 * @var array  $cron_status      Cron job status information
 * @var array  $system_health    System health indicators
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get import statistics
$total_products = isset( $import_stats['total_products'] ) ? $import_stats['total_products'] : 0;
$successful_imports = isset( $import_stats['successful_imports'] ) ? $import_stats['successful_imports'] : 0;
$failed_imports = isset( $import_stats['failed_imports'] ) ? $import_stats['failed_imports'] : 0;
$pending_imports = isset( $import_stats['pending_imports'] ) ? $import_stats['pending_imports'] : 0;
$last_sync_count = isset( $import_stats['last_sync_count'] ) ? $import_stats['last_sync_count'] : 0;

// Calculate percentages
$success_rate = $total_products > 0 ? round( ( $successful_imports / $total_products ) * 100, 1 ) : 0;
$error_rate = $total_products > 0 ? round( ( $failed_imports / $total_products ) * 100, 1 ) : 0;

// Get system status
$api_status = isset( $system_health['api_status'] ) ? $system_health['api_status'] : 'unknown';
$cron_enabled = isset( $system_health['cron_enabled'] ) ? $system_health['cron_enabled'] : false;
$queue_size = isset( $system_health['queue_size'] ) ? $system_health['queue_size'] : 0;
$memory_usage = isset( $system_health['memory_usage'] ) ? $system_health['memory_usage'] : 0;

// Current time for updates
$current_time = current_time( 'timestamp' );
?>

<div class="amazon-import-status-widget">
    
    <!-- Header with Refresh Button -->
    <div class="amazon-widget-header">
        <h3 class="amazon-widget-title">
            <span class="dashicons dashicons-amazon"></span>
            <?php _e( 'Statut d\'importation Amazon', 'amazon-product-importer' ); ?>
        </h3>
        <div class="amazon-widget-actions">
            <button type="button" 
                    class="button button-small amazon-refresh-status" 
                    title="<?php _e( 'Actualiser le statut', 'amazon-product-importer' ); ?>">
                <span class="dashicons dashicons-update"></span>
            </button>
            <button type="button" 
                    class="button button-small amazon-toggle-details" 
                    title="<?php _e( 'Afficher/Masquer les détails', 'amazon-product-importer' ); ?>">
                <span class="dashicons dashicons-visibility"></span>
            </button>
        </div>
    </div>

    <!-- Current Import Status -->
    <?php if ( ! empty( $current_imports ) ): ?>
    <div class="amazon-current-imports">
        <h4 class="amazon-section-title">
            <span class="amazon-status-indicator status-running"></span>
            <?php _e( 'Importations en cours', 'amazon-product-importer' ); ?>
        </h4>
        
        <?php foreach ( $current_imports as $import ): ?>
        <div class="amazon-import-progress" data-import-id="<?php echo esc_attr( $import['id'] ); ?>">
            <div class="amazon-import-info">
                <span class="amazon-import-type"><?php echo esc_html( $import['type'] ); ?></span>
                <span class="amazon-import-status"><?php echo esc_html( $import['status'] ); ?></span>
            </div>
            
            <div class="amazon-progress-bar">
                <div class="amazon-progress-fill" 
                     style="width: <?php echo esc_attr( $import['progress'] ); ?>%"
                     data-progress="<?php echo esc_attr( $import['progress'] ); ?>">
                    <span class="amazon-progress-text"><?php echo esc_html( $import['progress'] ); ?>%</span>
                </div>
            </div>
            
            <div class="amazon-import-details">
                <span class="amazon-processed"><?php echo esc_html( $import['processed'] ); ?>/<?php echo esc_html( $import['total'] ); ?> produits</span>
                <span class="amazon-eta">ETA: <?php echo esc_html( $import['eta'] ); ?></span>
            </div>

            <div class="amazon-import-actions">
                <?php if ( $import['can_pause'] ): ?>
                <button type="button" 
                        class="button button-small amazon-pause-import" 
                        data-import-id="<?php echo esc_attr( $import['id'] ); ?>">
                    <span class="dashicons dashicons-controls-pause"></span>
                    <?php _e( 'Pause', 'amazon-product-importer' ); ?>
                </button>
                <?php endif; ?>

                <?php if ( $import['can_cancel'] ): ?>
                <button type="button" 
                        class="button button-small amazon-cancel-import" 
                        data-import-id="<?php echo esc_attr( $import['id'] ); ?>">
                    <span class="dashicons dashicons-no"></span>
                    <?php _e( 'Annuler', 'amazon-product-importer' ); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Statistics Overview -->
    <div class="amazon-stats-overview">
        <div class="amazon-stats-grid">
            <div class="amazon-stat-item amazon-stat-total">
                <div class="amazon-stat-number"><?php echo number_format_i18n( $total_products ); ?></div>
                <div class="amazon-stat-label"><?php _e( 'Produits Amazon', 'amazon-product-importer' ); ?></div>
            </div>

            <div class="amazon-stat-item amazon-stat-success">
                <div class="amazon-stat-number"><?php echo number_format_i18n( $successful_imports ); ?></div>
                <div class="amazon-stat-label"><?php _e( 'Importés avec succès', 'amazon-product-importer' ); ?></div>
                <div class="amazon-stat-percentage"><?php echo $success_rate; ?>%</div>
            </div>

            <div class="amazon-stat-item amazon-stat-error">
                <div class="amazon-stat-number"><?php echo number_format_i18n( $failed_imports ); ?></div>
                <div class="amazon-stat-label"><?php _e( 'Erreurs d\'importation', 'amazon-product-importer' ); ?></div>
                <div class="amazon-stat-percentage"><?php echo $error_rate; ?>%</div>
            </div>

            <div class="amazon-stat-item amazon-stat-pending">
                <div class="amazon-stat-number"><?php echo number_format_i18n( $pending_imports ); ?></div>
                <div class="amazon-stat-label"><?php _e( 'En attente', 'amazon-product-importer' ); ?></div>
            </div>
        </div>
    </div>

    <!-- System Health Indicators -->
    <div class="amazon-system-health">
        <h4 class="amazon-section-title"><?php _e( 'État du système', 'amazon-product-importer' ); ?></h4>
        
        <div class="amazon-health-indicators">
            <div class="amazon-health-item">
                <span class="amazon-health-label"><?php _e( 'API Amazon', 'amazon-product-importer' ); ?></span>
                <span class="amazon-health-status status-<?php echo esc_attr( $api_status ); ?>">
                    <?php echo $this->get_api_status_label( $api_status ); ?>
                </span>
            </div>

            <div class="amazon-health-item">
                <span class="amazon-health-label"><?php _e( 'Tâches Cron', 'amazon-product-importer' ); ?></span>
                <span class="amazon-health-status status-<?php echo $cron_enabled ? 'healthy' : 'error'; ?>">
                    <?php echo $cron_enabled ? __( 'Actives', 'amazon-product-importer' ) : __( 'Inactives', 'amazon-product-importer' ); ?>
                </span>
            </div>

            <div class="amazon-health-item">
                <span class="amazon-health-label"><?php _e( 'File d\'attente', 'amazon-product-importer' ); ?></span>
                <span class="amazon-health-value"><?php echo number_format_i18n( $queue_size ); ?> éléments</span>
            </div>

            <div class="amazon-health-item">
                <span class="amazon-health-label"><?php _e( 'Mémoire utilisée', 'amazon-product-importer' ); ?></span>
                <span class="amazon-health-value"><?php echo size_format( $memory_usage ); ?></span>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="amazon-recent-activity amazon-details-section" style="display: none;">
        <h4 class="amazon-section-title"><?php _e( 'Activité récente', 'amazon-product-importer' ); ?></h4>
        
        <?php if ( ! empty( $recent_activity ) ): ?>
        <div class="amazon-activity-list">
            <?php foreach ( array_slice( $recent_activity, 0, 5 ) as $activity ): ?>
            <div class="amazon-activity-item activity-<?php echo esc_attr( $activity['type'] ); ?>">
                <div class="amazon-activity-icon">
                    <?php echo $this->get_activity_icon( $activity['type'] ); ?>
                </div>
                <div class="amazon-activity-content">
                    <div class="amazon-activity-message"><?php echo esc_html( $activity['message'] ); ?></div>
                    <div class="amazon-activity-meta">
                        <span class="amazon-activity-time">
                            <?php echo human_time_diff( strtotime( $activity['timestamp'] ), $current_time ); ?> ago
                        </span>
                        <?php if ( isset( $activity['details'] ) ): ?>
                        <span class="amazon-activity-details"><?php echo esc_html( $activity['details'] ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="amazon-activity-actions">
            <a href="<?php echo admin_url( 'admin.php?page=amazon-product-importer-logs' ); ?>" 
               class="button button-secondary">
                <?php _e( 'Voir tous les logs', 'amazon-product-importer' ); ?>
            </a>
        </div>
        <?php else: ?>
        <p class="amazon-no-activity"><?php _e( 'Aucune activité récente', 'amazon-product-importer' ); ?></p>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="amazon-quick-actions">
        <div class="amazon-action-buttons">
            <a href="<?php echo admin_url( 'admin.php?page=amazon-product-importer-import' ); ?>" 
               class="button button-primary">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e( 'Nouvelle importation', 'amazon-product-importer' ); ?>
            </a>

            <button type="button" 
                    class="button button-secondary amazon-sync-all-products">
                <span class="dashicons dashicons-update"></span>
                <?php _e( 'Synchroniser tout', 'amazon-product-importer' ); ?>
            </button>

            <button type="button" 
                    class="button button-secondary amazon-clear-queue">
                <span class="dashicons dashicons-trash"></span>
                <?php _e( 'Vider la file', 'amazon-product-importer' ); ?>
            </button>
        </div>
    </div>

    <!-- Performance Metrics (Details Section) -->
    <div class="amazon-performance-metrics amazon-details-section" style="display: none;">
        <h4 class="amazon-section-title"><?php _e( 'Métriques de performance', 'amazon-product-importer' ); ?></h4>
        
        <div class="amazon-metrics-grid">
            <div class="amazon-metric-item">
                <div class="amazon-metric-label"><?php _e( 'Dernière sync complète', 'amazon-product-importer' ); ?></div>
                <div class="amazon-metric-value">
                    <?php
                    $last_full_sync = get_option( 'amazon_importer_last_full_sync' );
                    if ( $last_full_sync ) {
                        echo human_time_diff( strtotime( $last_full_sync ), $current_time ) . ' ago';
                    } else {
                        _e( 'Jamais', 'amazon-product-importer' );
                    }
                    ?>
                </div>
            </div>

            <div class="amazon-metric-item">
                <div class="amazon-metric-label"><?php _e( 'Produits synchronisés (24h)', 'amazon-product-importer' ); ?></div>
                <div class="amazon-metric-value"><?php echo number_format_i18n( $last_sync_count ); ?></div>
            </div>

            <div class="amazon-metric-item">
                <div class="amazon-metric-label"><?php _e( 'Requêtes API aujourd\'hui', 'amazon-product-importer' ); ?></div>
                <div class="amazon-metric-value">
                    <?php
                    $api_requests_today = get_transient( 'amazon_importer_api_requests_today' ) ?: 0;
                    echo number_format_i18n( $api_requests_today );
                    ?>
                </div>
            </div>

            <div class="amazon-metric-item">
                <div class="amazon-metric-label"><?php _e( 'Taux de succès (7 jours)', 'amazon-product-importer' ); ?></div>
                <div class="amazon-metric-value">
                    <?php
                    $weekly_success_rate = get_transient( 'amazon_importer_weekly_success_rate' ) ?: 0;
                    echo round( $weekly_success_rate, 1 ) . '%';
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scheduled Jobs Status (Details Section) -->
    <div class="amazon-cron-status amazon-details-section" style="display: none;">
        <h4 class="amazon-section-title"><?php _e( 'Tâches programmées', 'amazon-product-importer' ); ?></h4>
        
        <?php if ( ! empty( $cron_status ) ): ?>
        <div class="amazon-cron-jobs">
            <?php foreach ( $cron_status as $job ): ?>
            <div class="amazon-cron-job">
                <div class="amazon-cron-info">
                    <span class="amazon-cron-name"><?php echo esc_html( $job['name'] ); ?></span>
                    <span class="amazon-cron-schedule"><?php echo esc_html( $job['schedule'] ); ?></span>
                </div>
                <div class="amazon-cron-meta">
                    <span class="amazon-cron-next">
                        <?php _e( 'Prochaine exécution:', 'amazon-product-importer' ); ?>
                        <?php echo human_time_diff( $current_time, $job['next_run'] ); ?>
                    </span>
                    <span class="amazon-cron-status status-<?php echo esc_attr( $job['status'] ); ?>">
                        <?php echo esc_html( $job['status_label'] ); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="amazon-no-cron"><?php _e( 'Aucune tâche programmée active', 'amazon-product-importer' ); ?></p>
        <?php endif; ?>
    </div>

    <!-- Last Update Time -->
    <div class="amazon-widget-footer">
        <span class="amazon-last-update">
            <?php _e( 'Dernière mise à jour:', 'amazon-product-importer' ); ?>
            <span id="amazon-last-update-time"><?php echo date_i18n( 'H:i:s' ); ?></span>
        </span>
        <span class="amazon-auto-refresh">
            <input type="checkbox" id="amazon-auto-refresh" checked />
            <label for="amazon-auto-refresh"><?php _e( 'Actualisation auto', 'amazon-product-importer' ); ?></label>
        </span>
    </div>

</div>

<style>
/* Amazon Import Status Widget Styles */
.amazon-import-status-widget {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    overflow: hidden;
}

.amazon-widget-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
}

.amazon-widget-title {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.amazon-widget-actions {
    display: flex;
    gap: 5px;
}

/* Current Imports */
.amazon-current-imports {
    padding: 15px 20px;
    background: #fff8dc;
    border-bottom: 1px solid #ddd;
}

.amazon-import-progress {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 12px;
    margin-bottom: 10px;
}

.amazon-import-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.amazon-import-type {
    font-weight: 600;
    color: #2271b1;
}

.amazon-import-status {
    color: #666;
    font-size: 12px;
    text-transform: uppercase;
}

.amazon-progress-bar {
    width: 100%;
    height: 20px;
    background: #f1f1f1;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 8px;
    position: relative;
}

.amazon-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #2271b1, #135e96);
    transition: width 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.amazon-progress-text {
    color: white;
    font-size: 11px;
    font-weight: 600;
}

.amazon-import-details {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #666;
    margin-bottom: 8px;
}

.amazon-import-actions {
    display: flex;
    gap: 5px;
}

/* Statistics */
.amazon-stats-overview {
    padding: 15px 20px;
}

.amazon-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
}

.amazon-stat-item {
    text-align: center;
    padding: 15px 10px;
    border-radius: 5px;
    position: relative;
}

.amazon-stat-total { background: #e7f3ff; color: #0073aa; }
.amazon-stat-success { background: #d4edda; color: #155724; }
.amazon-stat-error { background: #f8d7da; color: #721c24; }
.amazon-stat-pending { background: #fff3cd; color: #856404; }

.amazon-stat-number {
    font-size: 24px;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 5px;
}

.amazon-stat-label {
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 500;
}

.amazon-stat-percentage {
    position: absolute;
    top: 5px;
    right: 5px;
    font-size: 10px;
    font-weight: 600;
    opacity: 0.7;
}

/* System Health */
.amazon-system-health {
    padding: 15px 20px;
    background: #f9f9f9;
}

.amazon-section-title {
    margin: 0 0 10px 0;
    font-size: 13px;
    font-weight: 600;
    color: #23282d;
}

.amazon-health-indicators {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
}

.amazon-health-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: white;
    border-radius: 4px;
    border: 1px solid #ddd;
}

.amazon-health-label {
    font-size: 12px;
    color: #666;
}

.amazon-health-status {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 3px;
    text-transform: uppercase;
}

.amazon-health-value {
    font-size: 12px;
    font-weight: 600;
    color: #2271b1;
}

.status-healthy { background: #d4edda; color: #155724; }
.status-warning { background: #fff3cd; color: #856404; }
.status-error { background: #f8d7da; color: #721c24; }
.status-unknown { background: #e2e3e5; color: #6c757d; }
.status-running { background: #cce5ff; color: #004085; }

/* Recent Activity */
.amazon-recent-activity {
    padding: 15px 20px;
}

.amazon-activity-list {
    max-height: 200px;
    overflow-y: auto;
}

.amazon-activity-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #f1f1f1;
}

.amazon-activity-item:last-child {
    border-bottom: none;
}

.amazon-activity-icon {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    flex-shrink: 0;
}

.activity-success .amazon-activity-icon { background: #d4edda; color: #155724; }
.activity-error .amazon-activity-icon { background: #f8d7da; color: #721c24; }
.activity-info .amazon-activity-icon { background: #e7f3ff; color: #0073aa; }

.amazon-activity-content {
    flex: 1;
}

.amazon-activity-message {
    font-size: 12px;
    line-height: 1.4;
    margin-bottom: 2px;
}

.amazon-activity-meta {
    font-size: 11px;
    color: #666;
    display: flex;
    gap: 10px;
}

/* Quick Actions */
.amazon-quick-actions {
    padding: 15px 20px;
    background: #f8f9fa;
    border-top: 1px solid #ddd;
}

.amazon-action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.amazon-action-buttons .button {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    padding: 6px 12px;
    height: auto;
}

/* Performance Metrics */
.amazon-performance-metrics {
    padding: 15px 20px;
}

.amazon-metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 10px;
}

.amazon-metric-item {
    padding: 10px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.amazon-metric-label {
    font-size: 11px;
    color: #666;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.amazon-metric-value {
    font-size: 14px;
    font-weight: 600;
    color: #2271b1;
}

/* Cron Status */
.amazon-cron-status {
    padding: 15px 20px;
}

.amazon-cron-job {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 5px;
}

.amazon-cron-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.amazon-cron-name {
    font-weight: 600;
    font-size: 12px;
}

.amazon-cron-schedule {
    font-size: 11px;
    color: #666;
}

.amazon-cron-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
}

.amazon-cron-next {
    font-size: 11px;
    color: #666;
}

/* Widget Footer */
.amazon-widget-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 20px;
    background: #f1f1f1;
    border-top: 1px solid #ddd;
    font-size: 11px;
    color: #666;
}

.amazon-auto-refresh {
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Status Indicators */
.amazon-status-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 5px;
}

.status-running { background: #28a745; animation: pulse 1.5s infinite; }

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

/* Responsive */
@media (max-width: 782px) {
    .amazon-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .amazon-health-indicators {
        grid-template-columns: 1fr;
    }
    
    .amazon-action-buttons {
        flex-direction: column;
    }
    
    .amazon-widget-footer {
        flex-direction: column;
        gap: 5px;
        text-align: center;
    }
}

/* Loading states */
.amazon-loading {
    opacity: 0.7;
    pointer-events: none;
}

.amazon-loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #2271b1;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
jQuery(document).ready(function($) {
    let autoRefreshInterval;
    let autoRefreshEnabled = $('#amazon-auto-refresh').is(':checked');

    // Toggle details sections
    $('.amazon-toggle-details').on('click', function() {
        $('.amazon-details-section').slideToggle();
        const $icon = $(this).find('.dashicons');
        $icon.toggleClass('dashicons-visibility dashicons-hidden');
    });

    // Auto-refresh functionality
    function startAutoRefresh() {
        if (autoRefreshEnabled) {
            autoRefreshInterval = setInterval(refreshStatus, 30000); // 30 seconds
        }
    }

    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
    }

    $('#amazon-auto-refresh').on('change', function() {
        autoRefreshEnabled = $(this).is(':checked');
        if (autoRefreshEnabled) {
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
    });

    // Manual refresh
    $('.amazon-refresh-status').on('click', function() {
        refreshStatus();
    });

    // Refresh status function
    function refreshStatus() {
        const $widget = $('.amazon-import-status-widget');
        $widget.addClass('amazon-loading');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'amazon_refresh_import_status',
                nonce: '<?php echo wp_create_nonce( 'amazon_refresh_status' ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Update specific elements instead of full reload
                    updateStatusElements(response.data);
                    $('#amazon-last-update-time').text(new Date().toLocaleTimeString());
                }
            },
            error: function() {
                console.error('Erreur lors de l\'actualisation du statut');
            },
            complete: function() {
                $widget.removeClass('amazon-loading');
            }
        });
    }

    // Update specific status elements
    function updateStatusElements(data) {
        // Update progress bars
        if (data.current_imports) {
            data.current_imports.forEach(function(importData) {
                const $progress = $('.amazon-import-progress[data-import-id="' + importData.id + '"]');
                if ($progress.length) {
                    $progress.find('.amazon-progress-fill').css('width', importData.progress + '%');
                    $progress.find('.amazon-progress-text').text(importData.progress + '%');
                    $progress.find('.amazon-processed').text(importData.processed + '/' + importData.total + ' produits');
                    $progress.find('.amazon-eta').text('ETA: ' + importData.eta);
                }
            });
        }

        // Update statistics
        if (data.stats) {
            $('.amazon-stat-total .amazon-stat-number').text(data.stats.total_products.toLocaleString());
            $('.amazon-stat-success .amazon-stat-number').text(data.stats.successful_imports.toLocaleString());
            $('.amazon-stat-error .amazon-stat-number').text(data.stats.failed_imports.toLocaleString());
            $('.amazon-stat-pending .amazon-stat-number').text(data.stats.pending_imports.toLocaleString());
        }

        // Update health indicators
        if (data.health) {
            $('.amazon-health-item').each(function() {
                const $item = $(this);
                const label = $item.find('.amazon-health-label').text().trim();
                
                if (data.health[label]) {
                    $item.find('.amazon-health-status, .amazon-health-value').text(data.health[label]);
                }
            });
        }
    }

    // Import control actions
    $('.amazon-pause-import').on('click', function() {
        const importId = $(this).data('import-id');
        controlImport(importId, 'pause');
    });

    $('.amazon-cancel-import').on('click', function() {
        const importId = $(this).data('import-id');
        if (confirm('<?php _e( 'Êtes-vous sûr de vouloir annuler cette importation?', 'amazon-product-importer' ); ?>')) {
            controlImport(importId, 'cancel');
        }
    });

    function controlImport(importId, action) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'amazon_control_import',
                import_id: importId,
                control_action: action,
                nonce: '<?php echo wp_create_nonce( 'amazon_control_import' ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    refreshStatus();
                } else {
                    alert('<?php _e( 'Erreur:', 'amazon-product-importer' ); ?> ' + response.data);
                }
            }
        });
    }

    // Quick actions
    $('.amazon-sync-all-products').on('click', function() {
        if (confirm('<?php _e( 'Lancer la synchronisation de tous les produits Amazon?', 'amazon-product-importer' ); ?>')) {
            const $button = $(this);
            $button.prop('disabled', true).text('<?php _e( 'Synchronisation...', 'amazon-product-importer' ); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'amazon_sync_all_products',
                    nonce: '<?php echo wp_create_nonce( 'amazon_sync_all' ); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e( 'Synchronisation lancée en arrière-plan', 'amazon-product-importer' ); ?>');
                        refreshStatus();
                    } else {
                        alert('<?php _e( 'Erreur:', 'amazon-product-importer' ); ?> ' + response.data);
                    }
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> <?php _e( 'Synchroniser tout', 'amazon-product-importer' ); ?>');
                }
            });
        }
    });

    $('.amazon-clear-queue').on('click', function() {
        if (confirm('<?php _e( 'Vider la file d\'attente? Cette action ne peut pas être annulée.', 'amazon-product-importer' ); ?>')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'amazon_clear_import_queue',
                    nonce: '<?php echo wp_create_nonce( 'amazon_clear_queue' ); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        refreshStatus();
                        alert('<?php _e( 'File d\'attente vidée', 'amazon-product-importer' ); ?>');
                    } else {
                        alert('<?php _e( 'Erreur:', 'amazon-product-importer' ); ?> ' + response.data);
                    }
                }
            });
        }
    });

    // Start auto-refresh if enabled
    if (autoRefreshEnabled) {
        startAutoRefresh();
    }

    // Stop auto-refresh when page is hidden
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoRefresh();
        } else if (autoRefreshEnabled) {
            startAutoRefresh();
        }
    });
});
</script>