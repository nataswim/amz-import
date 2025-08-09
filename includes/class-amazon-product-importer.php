<?php
/**
 * Classe principale du plugin Amazon Product Importer
 */

class Amazon_Product_Importer {

    /**
     * Le chargeur qui coordonne les hooks du plugin.
     *
     * @var Amazon_Product_Importer_Loader
     */
    protected $loader;

    /**
     * L'identifiant unique du plugin.
     *
     * @var string
     */
    protected $plugin_name;

    /**
     * La version actuelle du plugin.
     *
     * @var string
     */
    protected $version;

    /**
     * Constructeur
     */
    public function __construct() {
        $this->plugin_name = 'amazon-product-importer';
        $this->version = '1.0.0';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
    }

    /**
     * Charger les dépendances nécessaires.
     */
    private function load_dependencies() {
        require_once plugin_dir_path(__FILE__) . 'class-loader.php';
        require_once plugin_dir_path(__FILE__) . 'class-i18n.php';
        require_once plugin_dir_path(__FILE__) . '../admin/class-admin.php';

        $this->loader = new Amazon_Product_Importer_Loader();
    }

    /**
     * Définir les hooks liés à la traduction.
     */
    private function set_locale() {
        $plugin_i18n = new Amazon_Product_Importer_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Définir les hooks pour le panneau admin.
     */
    private function define_admin_hooks() {
        $plugin_admin = new Amazon_Product_Importer_Admin();
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
    }

    /**
     * Lancer le plugin.
     */
    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_loader() {
        return $this->loader;
    }

    public function get_version() {
        return $this->version;
    }
}
