<?php
/**
 * Classe de gestion des traductions pour Amazon Product Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Amazon_Product_Importer_I18n {

    /**
     * Nom du domaine de traduction
     *
     * @var string
     */
    private $domain;

    /**
     * Définit le domaine de traduction
     */
    public function set_domain( $domain ) {
        $this->domain = $domain;
    }

    /**
     * Charge le fichier de traduction
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            $this->domain,
            false,
            dirname( plugin_basename( __FILE__ ), 2 ) . '/languages/'
        );
    }
}
