<?php

/**
 * Define the internationalization functionality.
 */
class Amazon_Product_Importer_i18n {

    /**
     * Load the plugin text domain for translation.
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'amazon-product-importer',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}