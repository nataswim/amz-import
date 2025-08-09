<?php
/**
 * Définit la classe responsable de l'enregistrement de tous les hooks
 * pour le plugin Amazon Product Importer.
 */

class Amazon_Product_Importer_Loader {

    /**
     * Tableau des actions à enregistrer.
     */
    protected $actions;

    /**
     * Constructeur.
     */
    public function __construct() {
        $this->actions = array();
    }

    /**
     * Ajoute une action à la pile d'exécution.
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions[] = compact('hook', 'component', 'callback', 'priority', 'accepted_args');
    }

    /**
     * Enregistre tous les hooks avec WordPress.
     */
    public function run() {
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}
