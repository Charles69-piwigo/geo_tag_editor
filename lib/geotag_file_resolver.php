<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class GeoTagFileResolver
{
  public function resolve($relative_path)
  {
    // Chemin par défaut
    $default_path = PHPWG_ROOT_PATH . $relative_path;
    
    // Si le fichier existe directement, le retourner
    if (file_exists($default_path) && !is_link($default_path)) {
      return $default_path;
    }
    
    // Si c'est un lien symbolique, résoudre le lien
    if (is_link($default_path)) {
      $real_path = readlink($default_path);
      if ($real_path && file_exists($real_path)) {
        return $real_path;
      }
    }
    
    // Essayer realpath
    $real = realpath($default_path);
    if ($real && file_exists($real)) {
      return $real;
    }
    
    // Retourner le chemin par défaut
    return $default_path;
  }
}
?>
