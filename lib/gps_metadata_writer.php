<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

/**
 * GPS Metadata Writer avec PEL (PHP Exif Library)
 * Écrit les coordonnées GPS sans dépendre d'exiftool
 * + Support IPTC Caption-Abstract (Description) via Imagick
 */

// Charger le wrapper Imagick pour l'écriture IPTC
require_once(GEOTAG_PATH . 'lib/geotag_imagick_wrapper.php');

// Charger TOUS les fichiers PEL
$pel_files = array(
  'Pel.php',
  'PelException.php',
  'PelConvert.php',
  'PelDataWindow.php',
  'PelDataWindowOffsetException.php',
  'PelDataWindowWindowException.php',
  'PelOverflowException.php',
  'PelJpegMarker.php',
  'PelJpegContent.php',
  'PelJpegComment.php',
  'PelJpegInvalidMarkerException.php',
  'PelFormat.php',
  'PelEntry.php',
  'PelEntryException.php',
  'PelEntryNumber.php',
  'PelEntryByte.php',
  'PelEntrySByte.php',
  'PelEntryShort.php',
  'PelEntrySShort.php',
  'PelEntryLong.php',
  'PelEntrySLong.php',
  'PelEntryRational.php',
  'PelEntrySRational.php',
  'PelEntryAscii.php',
  'PelEntryUndefined.php',
  'PelEntryUserComment.php',
  'PelEntryTime.php',
  'PelEntryVersion.php',
  'PelEntryCopyright.php',
  'PelEntryWindowsString.php',
  'PelTag.php',
  'PelIfd.php',
  'PelIfdException.php',
  'PelTiff.php',
  'PelExif.php',
  'PelJpeg.php',
  'PelMakerNotes.php',
  'PelCanonMakerNotes.php',
  'PelInvalidArgumentException.php',
  'PelInvalidDataException.php',
  'PelIllegalFormatException.php',
  'PelUnexpectedFormatException.php',
  'PelWrongComponentCountException.php',
  'PelMakerNotesMalformedException.php'
);

foreach ($pel_files as $file) {
  $filepath = GEOTAG_PATH . 'lib/pel/' . $file;
  if (file_exists($filepath)) {
    require_once($filepath);
  }
}

// Utiliser les namespaces PEL
use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelExif;
use lsolesen\pel\PelTiff;
use lsolesen\pel\PelIfd;
use lsolesen\pel\PelTag;
use lsolesen\pel\PelEntryRational;
use lsolesen\pel\PelEntryByte;
use lsolesen\pel\PelEntryAscii;

class GPSMetadataWriter
{
  /**
   * Écrit les coordonnées GPS dans les métadonnées EXIF
   */
  public function writeGPS($image_path, $latitude, $longitude, $altitude = null)
  {
    try {
      // Créer une sauvegarde
      $backup_path = $image_path . '.gps_backup';
      if (!@copy($image_path, $backup_path)) {
        return array('success' => false, 'error' => 'Cannot create backup');
      }
      
      // Charger le JPEG avec PEL
      $jpeg = new PelJpeg($image_path);
      
      // Récupérer ou créer la section EXIF
      $exif = $jpeg->getExif();
      if ($exif == null) {
        $exif = new PelExif();
        $jpeg->setExif($exif);
      }
      
      // Récupérer ou créer la structure TIFF
      $tiff = $exif->getTiff();
      if ($tiff == null) {
        $tiff = new PelTiff();
        $exif->setTiff($tiff);
      }
      
      // Récupérer ou créer l'IFD principal (IFD0)
      $ifd0 = $tiff->getIfd();
      if ($ifd0 == null) {
        $ifd0 = new PelIfd(PelIfd::IFD0);
        $tiff->setIfd($ifd0);
      }
      
      // Récupérer ou créer l'IFD GPS
      $gps_ifd = $ifd0->getSubIfd(PelIfd::GPS);
      if ($gps_ifd == null) {
        $gps_ifd = new PelIfd(PelIfd::GPS);
        $ifd0->addSubIfd($gps_ifd);
      }
      
      // Ajouter la version GPS
      $gps_ifd->addEntry(new PelEntryByte(
        PelTag::GPS_VERSION_ID,
        2, 2, 0, 0
      ));
      
      // Convertir et ajouter la latitude
      $lat_ref = $latitude >= 0 ? 'N' : 'S';
      $lat_dms = $this->decimalToDMS(abs($latitude));
      
      $gps_ifd->addEntry(new PelEntryAscii(
        PelTag::GPS_LATITUDE_REF,
        $lat_ref
      ));
      
      $gps_ifd->addEntry(new PelEntryRational(
        PelTag::GPS_LATITUDE,
        array($lat_dms[0], 1),  // degrés
        array($lat_dms[1], 1),  // minutes
        array(round($lat_dms[2] * 1000), 1000)  // secondes
      ));
      
      // Convertir et ajouter la longitude
      $lon_ref = $longitude >= 0 ? 'E' : 'W';
      $lon_dms = $this->decimalToDMS(abs($longitude));
      
      $gps_ifd->addEntry(new PelEntryAscii(
        PelTag::GPS_LONGITUDE_REF,
        $lon_ref
      ));
      
      $gps_ifd->addEntry(new PelEntryRational(
        PelTag::GPS_LONGITUDE,
        array($lon_dms[0], 1),  // degrés
        array($lon_dms[1], 1),  // minutes
        array(round($lon_dms[2] * 1000), 1000)  // secondes
      ));
      
      // Ajouter l'altitude si fournie
      if ($altitude !== null) {
        $alt_ref = $altitude >= 0 ? 0 : 1;
        
        $gps_ifd->addEntry(new PelEntryByte(
          PelTag::GPS_ALTITUDE_REF,
          $alt_ref
        ));
        
        $gps_ifd->addEntry(new PelEntryRational(
          PelTag::GPS_ALTITUDE,
          array(round(abs($altitude) * 100), 100)
        ));
      }
      
      // Sauvegarder le fichier
      $jpeg->saveFile($image_path);
      
      // Supprimer le backup
      @unlink($backup_path);
      clearstatcache(true, $image_path);
      
      return array('success' => true);
      
    } catch (Exception $e) {
      // Restaurer le backup en cas d'erreur
      if (isset($backup_path) && file_exists($backup_path)) {
        @copy($backup_path, $image_path);
        @unlink($backup_path);
      }
      
      return array(
        'success' => false,
        'error' => 'PEL error: ' . $e->getMessage()
      );
    }
  }
  
/**
 * Supprime les coordonnées GPS
 */
public function removeGPS($image_path)
{
  try {
    // Créer une sauvegarde
    $backup_path = $image_path . '.gps_backup';
    if (!@copy($image_path, $backup_path)) {
      return array('success' => false, 'error' => 'Cannot create backup');
    }
    
    // Charger le JPEG avec PEL
    $jpeg = new PelJpeg($image_path);
    
    // Récupérer la section EXIF
    $exif = $jpeg->getExif();
    if ($exif == null) {
      // Pas d'EXIF, donc pas de GPS à supprimer
      @unlink($backup_path);
      return array('success' => true);
    }
    
    // Récupérer la structure TIFF
    $tiff = $exif->getTiff();
    if ($tiff == null) {
      @unlink($backup_path);
      return array('success' => true);
    }
    
    // Récupérer l'IFD principal
    $ifd0 = $tiff->getIfd();
    if ($ifd0 == null) {
      @unlink($backup_path);
      return array('success' => true);
    }
    
    // Créer un nouvel IFD0 SANS l'IFD GPS
    $new_ifd0 = new PelIfd(PelIfd::IFD0);
    
    // Copier toutes les entrées de l'IFD0 original
    $entries = $ifd0->getEntries();
    foreach ($entries as $entry) {
      $new_ifd0->addEntry($entry);
    }
    
    // Copier tous les sub-IFDs SAUF le GPS
    $sub_ifds = array(
      PelIfd::EXIF,
      PelIfd::INTEROPERABILITY
      // On ne copie PAS PelIfd::GPS
    );
    
    foreach ($sub_ifds as $sub_type) {
      $sub_ifd = $ifd0->getSubIfd($sub_type);
      if ($sub_ifd != null) {
        $new_ifd0->addSubIfd($sub_ifd);
      }
    }
    
    // Si il y a un IFD1 (thumbnail), le copier aussi
    $ifd1 = $ifd0->getNextIfd();
    if ($ifd1 != null) {
      $new_ifd0->setNextIfd($ifd1);
    }
    
    // Remplacer l'ancien IFD0 par le nouveau (sans GPS)
    $tiff->setIfd($new_ifd0);
    
    // Sauvegarder le fichier
    $jpeg->saveFile($image_path);
    
    // Supprimer le backup
    @unlink($backup_path);
    clearstatcache(true, $image_path);
    
    return array('success' => true);
    
  } catch (Exception $e) {
    // Restaurer le backup en cas d'erreur
    if (isset($backup_path) && file_exists($backup_path)) {
      @copy($backup_path, $image_path);
      @unlink($backup_path);
    }
    
    return array(
      'success' => false,
      'error' => 'PEL error: ' . $e->getMessage()
    );
  }
}
  
  //========================================================================
  /**
   * Convertit degrés décimaux en DMS (Degrees, Minutes, Seconds)
   */
  private function decimalToDMS($decimal)
  {
    $degrees = floor($decimal);
    $minutes_decimal = ($decimal - $degrees) * 60;
    $minutes = floor($minutes_decimal);
    $seconds = ($minutes_decimal - $minutes) * 60;

    return array($degrees, $minutes, $seconds);
  }

  //========================================================================
  // SECTION IPTC - Gestion de la description (Caption-Abstract)
  //========================================================================

  /**
   * Écrit la description IPTC (Caption-Abstract, tag 2#120) dans l'image
   * Utilise le wrapper Imagick pour compatibilité PHP Imagick / ImageMagick CLI
   *
   * @param string $image_path Chemin vers l'image
   * @param string|null $description Description à écrire (null ou vide pour supprimer)
   * @return array ['success' => bool, 'error' => string|null]
   */
  public function writeDescription($image_path, $description = null)
  {
    try {
      // Charger l'image avec le wrapper Imagick
      $imagick = GeoTagImagickWrapper::load($image_path);

      if ($imagick->hasError()) {
        return array('success' => false, 'error' => $imagick->getError());
      }

      // Écrire la description IPTC
      $this->writeIptcDescription($imagick, $description);

      // Sauvegarder l'image (pour PHP Imagick)
      $imagick->writeImage($image_path);

      // Nettoyer
      $imagick->clear();
      $imagick->destroy();

      clearstatcache(true, $image_path);

      return array('success' => true);

    } catch (Exception $e) {
      return array('success' => false, 'error' => 'IPTC error: ' . $e->getMessage());
    }
  }

  /**
   * Écrit la description dans le profil IPTC
   * Préserve les autres champs IPTC existants (keywords, etc.)
   *
   * @param GeoTagImagickWrapper $imagick Instance du wrapper
   * @param string|null $description Description à écrire
   */
  private function writeIptcDescription($imagick, $description = null)
  {
    // Récupérer profil IPTC existant pour préserver les autres champs
    try {
      $iptc_profile = $imagick->getImageProfile('iptc');
    } catch (Exception $e) {
      $iptc_profile = false;
    }

    if ($iptc_profile) {
      $iptc_data = $this->parseIptcProfile($iptc_profile);
    } else {
      $iptc_data = array();
    }

    // Gérer la description (ajouter, modifier ou supprimer)
    if ($description !== null && strlen(trim($description)) > 0) {
      // Tag IPTC 2#120 = Caption-Abstract (Description)
      $iptc_data['2#120'] = trim($description);
    } else {
      // Supprimer la description si elle existe
      if (isset($iptc_data['2#120'])) {
        unset($iptc_data['2#120']);
      }
    }

    // Reconstruire le profil IPTC complet
    $new_profile = $this->buildIptcProfile($iptc_data);

    // Écrire le profil
    $imagick->setImageProfile('iptc', $new_profile);
  }

  /**
   * Parse un profil IPTC binaire en tableau associatif
   * Format: 0x1C + record + tag + size (2 bytes big-endian) + value
   *
   * @param string $binary Données binaires du profil IPTC
   * @return array Tableau associatif [record#tag => value]
   */
  private function parseIptcProfile($binary)
  {
    $data = array();
    $pos = 0;
    $len = strlen($binary);

    while ($pos < $len) {
      // Chercher le marqueur IPTC (0x1C)
      if (ord($binary[$pos]) != 0x1C) {
        $pos++;
        continue;
      }

      // Vérifier qu'il reste assez de données
      if ($pos + 4 >= $len) break;

      // Lire record, tag et taille
      $record = ord($binary[$pos + 1]);
      $tag = ord($binary[$pos + 2]);
      $size = (ord($binary[$pos + 3]) << 8) | ord($binary[$pos + 4]);

      // Vérifier la taille
      if ($pos + 5 + $size > $len) break;

      // Extraire la valeur
      $value = substr($binary, $pos + 5, $size);
      $key = sprintf('%d#%03d', $record, $tag);

      // Keywords (2#025) peuvent être multiples
      if ($key == '2#025') {
        if (!isset($data[$key])) {
          $data[$key] = array();
        }
        $data[$key][] = $value;
      } else {
        $data[$key] = $value;
      }

      $pos += 5 + $size;
    }

    return $data;
  }

  /**
   * Construit un profil IPTC binaire à partir d'un tableau associatif
   *
   * @param array $data Tableau associatif [record#tag => value]
   * @return string Données binaires du profil IPTC
   */
  private function buildIptcProfile($data)
  {
    $binary = '';

    // Envelope Record (1#000) - Version du format
    if (!isset($data['1#000'])) {
      $data['1#000'] = pack('n', 4);
    }
    $val = $data['1#000'];
    $binary .= chr(0x1C) . chr(1) . chr(0) . pack('n', strlen($val)) . $val;

    // Envelope Record (1#090) - Marqueur UTF-8
    if (!isset($data['1#090'])) {
      $data['1#090'] = "\x1B%G"; // Escape sequence UTF-8
    }
    $val = $data['1#090'];
    $binary .= chr(0x1C) . chr(1) . chr(90) . pack('n', strlen($val)) . $val;

    // Application Record (2#000) - Version
    if (!isset($data['2#000'])) {
      $data['2#000'] = pack('n', 4);
    }
    $val = $data['2#000'];
    $binary .= chr(0x1C) . chr(2) . chr(0) . pack('n', strlen($val)) . $val;

    // Autres tags (description, keywords, etc.)
    foreach ($data as $key => $value) {
      // Ignorer les tags déjà écrits
      if ($key === '1#000' || $key === '1#090' || $key === '2#000') {
        continue;
      }

      // Parser la clé (format: record#tag)
      list($record, $tag) = explode('#', $key);
      $record = intval($record);
      $tag = intval($tag);

      // Gérer les valeurs multiples (comme keywords)
      $values = is_array($value) ? $value : array($value);

      foreach ($values as $val) {
        $size = strlen($val);
        $binary .= chr(0x1C);       // Marqueur IPTC
        $binary .= chr($record);    // Numéro de record
        $binary .= chr($tag);       // Numéro de tag
        $binary .= pack('n', $size); // Taille (big-endian)
        $binary .= $val;            // Valeur
      }
    }

    return $binary;
  }
}
?>