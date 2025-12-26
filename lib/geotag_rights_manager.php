<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class GeoTagRightsManager
{
  public function canEditMetadata()
  {
    global $user, $conf;
    
    // Seuls les administrateurs et webmasters peuvent éditer les métadonnées
    if (is_admin() || is_webmaster()) {
      return true;
    }
    
    // Vérifier si l'utilisateur a les droits d'édition
    if (isset($user['status'])) {
      if ($user['status'] == 'admin' || $user['status'] == 'webmaster') {
        return true;
      }
    }
    
    return false;
  }
  
  public function canViewGPS()
  {
    // Tous les utilisateurs peuvent voir les coordonnées GPS
    return true;
  }
}
?>
