<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

function geotageditor_admin_tabsheet($selected = 'help')
{
  include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');
  $tabsheet = new tabsheet();
  $tabsheet->set_id('geo_tag_editor');
  $tabsheet->add('help', l10n('Aide'), GEOTAG_ADMIN . '&tab=help');
  $tabsheet->add('manage_rights', l10n('Gestion des droits'), GEOTAG_ADMIN . '&tab=manage_rights');
  $tabsheet->add('places', l10n('Lieux personnels'), GEOTAG_ADMIN . '&tab=places');
  
  $tabsheet->select($selected);
  $tabsheet->assign();
}
?>