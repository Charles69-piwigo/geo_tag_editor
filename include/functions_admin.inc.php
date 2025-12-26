<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

/**
 * Crée et affiche le tabsheet (onglets) pour les pages d'administration
 * @param string $selected L'onglet actuellement sélectionné 
 */
function geotageditor_admin_tabsheet($selected = 'help')
{
  include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');
  $tabsheet = new tabsheet();
  $tabsheet->set_id('geo_tag_editor');
  $tabsheet->add('help', l10n('Aide'), GEOTAGWRITE_ADMIN . '&tab=help');
  $tabsheet->add('manage_rights', l10n('Gestion des droits'), GEOTAGWRITE_ADMIN . '&tab=manage_rights');
  
  $tabsheet->select($selected);
  $tabsheet->assign();
}
?>
