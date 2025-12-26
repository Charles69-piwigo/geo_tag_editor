<?php
/**
 * ImageMagick Wrapper - Fallback support for PHP Imagick extension
 *
 * This wrapper provides a unified interface for:
 * 1. PHP Imagick extension (if available)
 * 2. External ImageMagick command-line tools (convert, identify) as fallback
 */

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class GeoTagImagickWrapper
{
  private $image_path;
  private $use_external = false;
  private $error = null;
  private $imagick = null;

  public static function load($image_path)
  {
    return new self($image_path);
  }

 //---------------------------------------------------------------------------------------- 
  private function __construct($image_path)
  {
    $this->image_path = $image_path;
    //*error_log('Wrapper Image Path : '.$image_path) ;

    // Try PHP Imagick extension first
    if (extension_loaded('imagick')) {
//    if (false && extension_loaded('imagick')) {  // ← DÉSACTIVÉ POUR TEST
      try {
        $this->imagick = new Imagick($image_path);
        //*error_log('INFO: Using PHP Imagick extension');
        return;
      } catch (Exception $e) {
        error_log('WARNING: PHP Imagick extension failed: ' . $e->getMessage());
      }
    }

    // Try external ImageMagick
    if ($this->checkExternalImageMagick()) {
      //*error_log('INFO: Using external ImageMagick command-line tools');
      $this->use_external = true;
    } else {
      $this->error = 'Neither PHP Imagick extension nor external ImageMagick is available';
      error_log('ERROR: ' . $this->error);
    }
  }

  //----------------------------------------------------------------------------
  private function checkExternalImageMagick()
  {
    $commands = array('convert', 'identify', 'magick');

    foreach ($commands as $cmd) {
      $output = array();
      $return_var = 0;

      if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $test_cmd = 'where ' . escapeshellarg($cmd);
      } else {
        $test_cmd = 'which ' . escapeshellarg($cmd);
      }

      @exec($test_cmd, $output, $return_var);

      if ($return_var === 0 && !empty($output[0])) {
        //*error_log('Found external ImageMagick command: ' . $cmd);
        return true;
      }
    }

    return false;
  }

  public function hasError()
  {
    return $this->error !== null;
  }

  public function getError()
  {
    return $this->error;
  }

  //---------------------------------------------------------------------------------
  public function getImageProfile($profile_name)
  {
    if ($this->hasError()) {
      return false;
    }

    if (!$this->use_external) {
      try {
        return $this->imagick->getImageProfile($profile_name);
      } catch (Exception $e) {
        //*error_log('Error getting profile via PHP Imagick: ' . $e->getMessage());
        return false;
      }
    } else {
      return $this->getProfileExternal($profile_name);
    }
  }

//-----------------------------------------------------------------------------
  public function removeImageProfile($profile_name)
  {
    if ($this->hasError()) {
      return false;
    }

    if (!$this->use_external) {
      try {
        return $this->imagick->removeImageProfile($profile_name);
      } catch (Exception $e) {
        //*error_log('Error removing profile via PHP Imagick: ' . $e->getMessage());
        return false;
      }
    } else {
      // External ImageMagick - utiliser convert avec +profile
      return $this->removeProfileExternal($profile_name);
    }
  }

  private function removeProfileExternal($profile_name)
  {
    $temp_output = tempnam(sys_get_temp_dir(), 'imgout_');

    try {
      //*error_log('External ImageMagick: Removing ' . $profile_name . ' profile');
      
      // Créer un backup avant modification
      $backup_image = $this->image_path . '.bak_remove';
      if (!@copy($this->image_path, $backup_image)) {
        //*error_log('Failed to create backup of image');
        @unlink($temp_output);
        return false;
      }

      // Commande pour supprimer le profil
      // +profile supprime, -profile ajoute
      if ($profile_name === '8BIM') {
        // Supprimer le profil Photoshop APP13
        $cmd = 'convert ' . escapeshellarg($this->image_path) .
               ' +profile "8BIM" ' .
               escapeshellarg($temp_output) .
               ' 2>&1';
      } elseif ($profile_name === 'iptc') {
        $cmd = 'convert ' . escapeshellarg($this->image_path) .
               ' +profile "iptc" ' .
               escapeshellarg($temp_output) .
               ' 2>&1';
      } elseif ($profile_name === 'xmp') {
        $cmd = 'convert ' . escapeshellarg($this->image_path) .
               ' +profile "xmp" ' .
               escapeshellarg($temp_output) .
               ' 2>&1';
      } else {
        //*error_log('Unsupported profile type for removal: ' . $profile_name);
        @unlink($temp_output);
        @unlink($backup_image);
        return false;
      }
      
      //*error_log('External ImageMagick command: convert IMAGE +profile "' . $profile_name . '" OUTPUT');
      
      $return_var = 0;
      $output = array();
      @exec($cmd, $output, $return_var);
      
      // Nettoyer
      if ($return_var !== 0) {
        //*error_log('External convert command failed (return code: ' . $return_var . ')');
        if (!empty($output)) {
          //*error_log('Convert stderr: ' . implode("\n", $output));
        }
        @unlink($temp_output);
        @unlink($backup_image);
        return false;
      }

      // Vérifier que le fichier de sortie existe
      if (!file_exists($temp_output) || filesize($temp_output) == 0) {
        //*error_log('ERROR: Output file invalid after convert +profile');
        @unlink($temp_output);
        @unlink($backup_image);
        return false;
      }

      $output_size = filesize($temp_output);
      //*error_log('Profile removed, output file size: ' . $output_size . ' bytes');

      // Remplacer l'original par le fichier sans profil
      if (!@copy($temp_output, $this->image_path)) {
        //*error_log('Failed to copy output to original path');
        @unlink($temp_output);
        @copy($backup_image, $this->image_path);
        @unlink($backup_image);
        return false;
      }

      //*error_log('✓ File successfully updated at: ' . $this->image_path);
      
      // Vérifier que le fichier a bien été écrit
      clearstatcache(true, $this->image_path);
      $final_size = filesize($this->image_path);
      //*error_log('✓ Final file size: ' . $final_size . ' bytes');

      // Nettoyer
      @unlink($temp_output);
      @unlink($backup_image);
      
      //*error_log('✓ External ImageMagick: Profile removed successfully');
      return true;

    } catch (Exception $e) {
      //*error_log('Error removing profile via external ImageMagick: ' . $e->getMessage());
      @unlink($temp_output);
      if (isset($backup_image) && file_exists($backup_image)) {
        @copy($backup_image, $this->image_path);
        @unlink($backup_image);
      }
      return false;
    }
  }



  //-----------------------------------------------------------------------
  private function getProfileExternal($profile_name)
  {
    $temp_profile = tempnam(sys_get_temp_dir(), 'imgprof_');

    try {
      if ($profile_name === 'xmp') {
        // Syntaxe qui fonctionne sur Synology : convert IMAGE xmp:- 
        // On redirige la sortie vers un fichier
        $cmd = 'convert ' . escapeshellarg($this->image_path) . ' xmp:- > ' . escapeshellarg($temp_profile) . ' 2>&1';
      } elseif ($profile_name === 'iptc') {
        // Pour IPTC, essayer d'abord avec la syntaxe standard
        $cmd = 'convert ' . escapeshellarg($this->image_path) . ' iptc:- > ' . escapeshellarg($temp_profile) . ' 2>&1';
      } else {
        @unlink($temp_profile);
        return false;
      }

      //*error_log('External ImageMagick GET profile command: ' . $cmd);

      $return_var = 0;
      $output = array();
      @exec($cmd, $output, $return_var);

      if ($return_var === 0 && file_exists($temp_profile) && filesize($temp_profile) > 0) {
        $data = file_get_contents($temp_profile);
        //*error_log('External ImageMagick: Successfully read ' . $profile_name . ' profile (' . strlen($data) . ' bytes)');
        @unlink($temp_profile);
        return $data;
      }

      if ($return_var !== 0) {
        //*error_log('External ImageMagick: Failed to read ' . $profile_name . ' profile (return code: ' . $return_var . ')');
        if (!empty($output)) {
          //*error_log('Output: ' . implode("\n", $output));
        }
      } else {
        //*error_log('External ImageMagick: Profile file is empty or does not exist');
      }

      @unlink($temp_profile);
      return false;

    } catch (Exception $e) {
      //*error_log('Error getting profile via external ImageMagick: ' . $e->getMessage());
      @unlink($temp_profile);
      return false;
    }
  }

  public function setImageProfile($profile_name, $profile_data)
  {
    if ($this->hasError()) {
      return false;
    }

    if (!$this->use_external) {
      try {
        return $this->imagick->setImageProfile($profile_name, $profile_data);
      } catch (Exception $e) {
        //*error_log('Error setting profile via PHP Imagick: ' . $e->getMessage());
        return false;
      }
    } else {
      return $this->setProfileExternal($profile_name, $profile_data);
    }
  }

  private function setProfileExternal($profile_name, $profile_data)
  {
    $temp_profile = tempnam(sys_get_temp_dir(), 'imgprof_');
    $temp_output = tempnam(sys_get_temp_dir(), 'imgout_');

    try {
      // Écrire le profil dans un fichier temporaire
      if (file_put_contents($temp_profile, $profile_data) === false) {
        //*error_log('Failed to write temporary profile file');
        @unlink($temp_profile);
        @unlink($temp_output);
        return false;
      }

      //*error_log('External ImageMagick: Setting ' . $profile_name . ' profile');
      //*error_log('Profile data size: ' . strlen($profile_data) . ' bytes');
      //*error_log('Image path: ' . $this->image_path);

      // Créer un backup avant modification
      $backup_image = $this->image_path . '.bak';
      if (!@copy($this->image_path, $backup_image)) {
        //*error_log('Failed to create backup of image');
        @unlink($temp_profile);
        @unlink($temp_output);
        return false;
      }

      // Construire la commande selon le type de profil
      // IMPORTANT: Pour XMP et IPTC, utiliser stdin avec le pipe
      if ($profile_name === 'xmp') {
        // Syntaxe qui fonctionne : cat XMP_FILE | convert IMAGE -profile xmp:- OUTPUT
        $cmd = 'cat ' . escapeshellarg($temp_profile) . 
               ' | convert ' . escapeshellarg($this->image_path) .
               ' -profile xmp:- ' .
               escapeshellarg($temp_output) .
               ' 2>&1';
      } elseif ($profile_name === 'iptc') {
        // Même syntaxe pour IPTC
        $cmd = 'cat ' . escapeshellarg($temp_profile) . 
               ' | convert ' . escapeshellarg($this->image_path) .
               ' -profile iptc:- ' .
               escapeshellarg($temp_output) .
               ' 2>&1';
      } else {
        // Pour les autres profils (ICC, etc.), syntaxe classique
        $cmd = 'convert ' . escapeshellarg($this->image_path) .
               ' -profile ' . escapeshellarg($temp_profile) .
               ' ' . escapeshellarg($temp_output) .
               ' 2>&1';
      }
      
      //*error_log('External ImageMagick command: ' . ($profile_name === 'xmp' || $profile_name === 'iptc' ? 'cat PROFILE | convert IMAGE -profile ' . $profile_name . ':- OUTPUT' : 'convert IMAGE -profile PROFILE OUTPUT'));
      
      $return_var = 0;
      $output = array();
      @exec($cmd, $output, $return_var);
      
      // Nettoyer le fichier de profil
      @unlink($temp_profile);
      
      // Vérifier le code de retour
      if ($return_var !== 0) {
        //*error_log('External convert command failed (return code: ' . $return_var . ')');
        if (!empty($output)) {
          //*error_log('Convert stderr: ' . implode("\n", $output));
        }
        @unlink($temp_output);
        @copy($backup_image, $this->image_path);
        @unlink($backup_image);
        return false;
      }

      // Vérifier que le fichier de sortie existe et n'est pas vide
      if (!file_exists($temp_output)) {
        //*error_log('ERROR: Output file does not exist after convert');
        @unlink($temp_output);
        @copy($backup_image, $this->image_path);
        @unlink($backup_image);
        return false;
      }
      
      $output_size = filesize($temp_output);
      if ($output_size == 0) {
        //*error_log('ERROR: Output file is empty (0 bytes)');
        @unlink($temp_output);
        @copy($backup_image, $this->image_path);
        @unlink($backup_image);
        return false;
      }

      $original_size = filesize($this->image_path);
      //*error_log('File sizes - Original: ' . $original_size . ' bytes, Output: ' . $output_size . ' bytes');
      
      // Vérifier que la taille est raisonnable
      $size_ratio = $output_size / $original_size;
      if ($size_ratio < 0.5 || $size_ratio > 1.5) {
        //*error_log('WARNING: Output file size unusual (ratio: ' . number_format($size_ratio, 2) . ')');
      }

      // Remplacer l'original par le fichier temporaire
      if (!@copy($temp_output, $this->image_path)) {
        //*error_log('Failed to copy temporary output to original path');
        @unlink($temp_output);
        @copy($backup_image, $this->image_path);
        @unlink($backup_image);
        return false;
      }

      //*error_log('✓ File successfully replaced at: ' . $this->image_path);
      
      // Vérifier que le fichier a bien été écrit
      clearstatcache(true, $this->image_path);
      $final_size = filesize($this->image_path);
      //*error_log('✓ Final file size: ' . $final_size . ' bytes');
      
      if ($final_size != $output_size) {
        //*error_log('WARNING: Final file size differs from temp output!');
      }

      // Nettoyer
      @unlink($temp_output);
      @unlink($backup_image);
      
      //*error_log('✓ External ImageMagick: Profile set successfully');
      return true;

    } catch (Exception $e) {
      //*error_log('Error setting profile via external ImageMagick: ' . $e->getMessage());
      @unlink($temp_profile);
      @unlink($temp_output);
      if (isset($backup_image) && file_exists($backup_image)) {
        @copy($backup_image, $this->image_path);
        @unlink($backup_image);
      }
      return false;
    }
  }

  //-----------------------------------------------------------------------------
  public function writeImage($output_path = null)
  {
    if ($this->hasError()) {
      return false;
    }

    if (!$this->use_external) {
      try {
        if ($output_path === null) {
          $output_path = $this->image_path;
        }
        return $this->imagick->writeImage($output_path);
      } catch (Exception $e) {
        //*error_log('Error writing image via PHP Imagick: ' . $e->getMessage());
        return false;
      }
    }
    return true;
  }

  //---------------------------------------------------------------------------
  public function getImageWidth()
  {
    if ($this->hasError()) {
      return 0;
    }

    if (!$this->use_external) {
      try {
        return $this->imagick->getImageWidth();
      } catch (Exception $e) {
        //*error_log('Error getting image width: ' . $e->getMessage());
        return 0;
      }
    } else {
      return $this->getImageDimensionExternal('width');
    }
  }

  //-------------------------------------------------------------------------------
  public function getImageHeight()
  {
    if ($this->hasError()) {
      return 0;
    }

    if (!$this->use_external) {
      try {
        return $this->imagick->getImageHeight();
      } catch (Exception $e) {
        //*error_log('Error getting image height: ' . $e->getMessage());
        return 0;
      }
    } else {
      return $this->getImageDimensionExternal('height');
    }
  }

  //---------------------------------------------------------------------------------------
  private function getImageDimensionExternal($dimension)
  {
    $cmd = 'identify -format "%' . ($dimension === 'width' ? 'w' : 'h') . '" ' . escapeshellarg($this->image_path);

    $output = array();
    $return_var = 0;
    @exec($cmd, $output, $return_var);

    if ($return_var === 0 && !empty($output[0])) {
      $value = intval($output[0]);
      return $value > 0 ? $value : 0;
    }

    return 0;
  }

  //------------------------------------------------------------------------------------------
  public function getImageProperties()
  {
    if ($this->hasError()) {
      return array();
    }

    if (!$this->use_external) {
      try {
        return $this->imagick->getImageProperties();
      } catch (Exception $e) {
        //*error_log('Error getting image properties: ' . $e->getMessage());
        return array();
      }
    }
    return array();
  }

  //-----------------------------------------------------------------------------------------
  public function clear()
  {
    if ($this->imagick) {
      try {
        $this->imagick->clear();
      } catch (Exception $e) {
        // Ignore cleanup errors
      }
    }
  }

  //-------------------------------------------------------------------------------------------
  public function destroy()
  {
    if ($this->imagick) {
      try {
        $this->imagick->destroy();
      } catch (Exception $e) {
        // Ignore cleanup errors
      }
    }
    $this->imagick = null;
  }

  //---------------------------------------------------------------------------------------------
  public function isUsingExternal()
  {
    return $this->use_external;
  }

  //---------------------------------------------------------------------------------------------
  public function getMode()
  {
    if ($this->hasError()) {
      return 'error';
    }
    return $this->use_external ? 'external' : 'php-imagick';

  }

//---------------------------------------------------------------------------------------------
  public function setImageProperty($name, $value)
  {
    if ($this->hasError()) {
      return false;
    }

    if (!$this->use_external) {
      try {
        return $this->imagick->setImageProperty($name, $value);
      } catch (Exception $e) {
        error_log('Error setting image property: ' . $e->getMessage());
        return false;
      }
    }
    
    // Pour external, on ne peut pas définir les propriétés individuellement
    // On retournera false pour forcer l'utilisation de la méthode External
    return false;
  }

  //---------------------------------------------------------------------------------------------
  public function deleteImageProperty($name)
  {
    if ($this->hasError()) {
      return false;
    }

    if (!$this->use_external) {
      try {
        return $this->imagick->deleteImageProperty($name);
      } catch (Exception $e) {
        error_log('Error deleting image property: ' . $e->getMessage());
        return false;
      }
    }
    
    return false;
  }

}
?>