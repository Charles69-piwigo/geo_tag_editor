<?php
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

// Définir le chemin du plugin
define('GEOTAG_MAINTAIN_PATH', PHPWG_PLUGINS_PATH . 'geo_tag_editor/');

// Installation du plugin avec les valeurs par défaut
function plugin_install()
{
    // Rien ici pour l'instant (ou autres initialisations futures)
}

// Activation du plugin
function plugin_activate()
{
    // Créer la table des lieux personnels si elle n'existe pas
    // Cela permet aux utilisateurs existants d'avoir la nouvelle fonctionnalité
    include_once(GEOTAG_MAINTAIN_PATH . 'lib/geotag_places_manager.php');
    $places_manager = new GeoTagEditorPlaces();
    $places_manager->install(); // install() vérifie déjà avec "IF NOT EXISTS"
}

// Désinstallation du plugin
function plugin_uninstall()
{
    // Désinstallation de la table des lieux personnels
    include_once(GEOTAG_MAINTAIN_PATH . 'lib/geotag_places_manager.php');
    $places_manager = new GeoTagEditorPlaces();
    $places_manager->uninstall();
}

// Désactivation du plugin
function plugin_deactivate()
{
    // Rien de spécifique à faire lors de la désactivation
}
?>