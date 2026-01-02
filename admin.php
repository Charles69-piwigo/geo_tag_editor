<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

// Charger les fonctions
include_once(GEOTAG_PATH . 'include/functions_admin.inc.php');

// Déterminer l'onglet actif
$page['tab'] = isset($_GET['tab']) ? $_GET['tab'] : 'help';

// Charger le contenu de l'onglet
switch ($page['tab']) {
    case 'help':
        include(GEOTAG_PATH . 'include/help.php');
        break;

    case 'manage_rights':
        include(GEOTAG_PATH . 'include/manage_rights.php');
        break;

    case 'places':
        include(GEOTAG_PATH . 'include/manage_places.php');
        break;

    default:
        include(GEOTAG_PATH . 'include/help.php');
        break;
}
?>