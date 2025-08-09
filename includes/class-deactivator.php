<?php
/**
 * Actions à exécuter lors de la désactivation du plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Amazon_Product_Importer_Deactivator {

    public static function deactivate() {
        if ( class_exists( 'Amazon_Product_Importer_Cron_Manager' ) ) {
            Amazon_Product_Importer_Cron_Manager::clear_cron_jobs();
        }
    }
}
