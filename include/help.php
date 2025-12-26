<?php
defined('GEOTAGWRITE_PATH') or die('Hacking attempt!');

// Afficher le tabsheet
geotageditor_admin_tabsheet('help');

// Charger le template
$template->set_filename('geo_tag_editor_help', dirname(__FILE__).'/../template/help.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'geo_tag_editor_help');
?>
