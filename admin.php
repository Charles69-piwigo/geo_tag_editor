<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

// Charger les fonctions
include_once(GEOTAGWRITE_PATH . 'include/functions_admin.inc.php');

// Déterminer l'onglet actif
$page['tab'] = isset($_GET['tab']) ? $_GET['tab'] : 'help';

// Charger le contenu de l'onglet
switch ($page['tab']) {
    case 'help':
        include(GEOTAGWRITE_PATH . 'include/help.php');
        break;

    case 'manage_rights':
        include(GEOTAGWRITE_PATH . 'include/manage_rights.php');
        break;

    default:
        include(GEOTAGWRITE_PATH . 'include/help.php');
        break;
}
?>
