<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

/**
 * GPS Metadata Writer avec PEL (PHP Exif Library)
 * Écrit les coordonnées GPS sans dépendre d'exiftool
 * + Support IPTC Caption-Abstract (Description) en PHP pur (sans Imagick)
 */

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
  // Version PHP pur - sans dépendance Imagick ou ImageMagick CLI
  //========================================================================

  /**
   * Écrit la description IPTC (Caption-Abstract, tag 2#120) dans l'image
   * Manipulation directe du segment APP13 du JPEG en PHP pur
   *
   * @param string $image_path Chemin vers l'image
   * @param string|null $description Description à écrire (null ou vide pour supprimer)
   * @return array ['success' => bool, 'error' => string|null]
   */
public function writeDescription($image_path, $description = null)
{
    $backup_path = $image_path . '.iptc_backup';
    if (!@copy($image_path, $backup_path)) {
      return array('success' => false, 'error' => 'Cannot create backup');
    }

    try {
      $jpeg_data = file_get_contents($image_path);

      if ($jpeg_data === false) {
        @unlink($backup_path);
        return array('success' => false, 'error' => 'Cannot read image file');
      }

      if (substr($jpeg_data, 0, 2) !== "\xFF\xD8") {
        @unlink($backup_path);
        return array('success' => false, 'error' => 'Not a valid JPEG file');
      }

      $existing_iptc = $this->extractIptcFromJpeg($jpeg_data);

      $iptc_data = ($existing_iptc !== false && strlen($existing_iptc) > 0)
                  ? $this->parseIptcProfile($existing_iptc)
                  : array();

      if ($description !== null && strlen(trim($description)) > 0) {
        $iptc_data['2#120'] = trim($description);
      } else {
        unset($iptc_data['2#120']);
      }

      $new_iptc = $this->buildIptcProfile($iptc_data);

      $new_jpeg = $this->injectIptcIntoJpeg($jpeg_data, $new_iptc);

      if ($new_jpeg === false) {
        @unlink($backup_path);
        return array('success' => false, 'error' => 'Failed to inject IPTC profile into JPEG');
      }

      $written = file_put_contents($image_path, $new_jpeg);

      if ($written === false) {
        @copy($backup_path, $image_path);
        @unlink($backup_path);
        return array('success' => false, 'error' => 'Cannot write image file');
      }

      @unlink($backup_path);
      clearstatcache(true, $image_path);
      return array('success' => true);

    } catch (Exception $e) {
      error_log('geo_tag_editor: writeDescription exception - ' . $e->getMessage());
      if (file_exists($backup_path)) {
        @copy($backup_path, $image_path);
        @unlink($backup_path);
      }
      return array('success' => false, 'error' => 'IPTC error: ' . $e->getMessage());
    }
}

  /**
   * Extrait le profil IPTC brut depuis le segment APP13 d'un JPEG
   * Le segment APP13 contient un en-tête "Photoshop 3.0\0" suivi de blocs 8BIM
   * Le bloc 8BIM de type 0x0404 contient les données IPTC
   *
   * @param string $jpeg_data Contenu binaire du JPEG
   * @return string|false Données IPTC brutes, ou false si absent
   */
  private function extractIptcFromJpeg($jpeg_data)
  {
    $pos = 2; // Passer le marqueur SOI (FF D8)
    $len = strlen($jpeg_data);

    while ($pos + 4 <= $len) {
      if (ord($jpeg_data[$pos]) !== 0xFF) {
        break;
      }

      $marker = ord($jpeg_data[$pos + 1]);

      // SOS = fin des segments d'en-tête
      if ($marker === 0xDA) break;

      // Segments sans longueur
      if ($marker === 0xD8 || $marker === 0xD9) {
        $pos += 2;
        continue;
      }

      if ($pos + 4 > $len) break;
      $seg_len = (ord($jpeg_data[$pos + 2]) << 8) | ord($jpeg_data[$pos + 3]);

      // APP13 = marqueur 0xED
      if ($marker === 0xED) {
        $seg_data = substr($jpeg_data, $pos + 4, $seg_len - 2);

        $photoshop_header = "Photoshop 3.0\x00";
        if (strncmp($seg_data, $photoshop_header, strlen($photoshop_header)) === 0) {
          // Parcourir les blocs 8BIM
          $bim_pos = strlen($photoshop_header);
          $seg_data_len = strlen($seg_data);

          while ($bim_pos + 12 <= $seg_data_len) {
            if (substr($seg_data, $bim_pos, 4) !== '8BIM') break;

            $resource_type = (ord($seg_data[$bim_pos + 4]) << 8) | ord($seg_data[$bim_pos + 5]);

            $name_len = ord($seg_data[$bim_pos + 6]);
            $name_padded = ($name_len % 2 === 0) ? $name_len + 2 : $name_len + 1;

            $data_offset = $bim_pos + 6 + $name_padded;
            if ($data_offset + 4 > $seg_data_len) break;

            $data_len = (ord($seg_data[$data_offset]) << 24)
                      | (ord($seg_data[$data_offset + 1]) << 16)
                      | (ord($seg_data[$data_offset + 2]) << 8)
                      |  ord($seg_data[$data_offset + 3]);

            $data_start = $data_offset + 4;
            if ($data_start + $data_len > $seg_data_len) break;

            // 0x0404 = IPTC-NAA Resource
            if ($resource_type === 0x0404) {
              return substr($seg_data, $data_start, $data_len);
            }

            $data_padded = ($data_len % 2 !== 0) ? $data_len + 1 : $data_len;
            $bim_pos = $data_start + $data_padded;
          }
        }
      }

      $pos += 2 + $seg_len;
    }

    return false;
  }

  /**
   * Injecte un profil IPTC dans un JPEG
   * Supprime l'APP13 existant et insère le nouveau juste après SOI
   *
   * @param string $jpeg_data Contenu binaire du JPEG original
   * @param string $iptc_data Données IPTC brutes à injecter
   * @return string|false Nouveau contenu JPEG, ou false en cas d'erreur
   */
  private function injectIptcIntoJpeg($jpeg_data, $iptc_data)
  {
    // Construire le bloc 8BIM contenant les données IPTC (type 0x0404)
    $bim_block  = '8BIM';
    $bim_block .= "\x04\x04";                      // Type 0x0404 = IPTC
    $bim_block .= "\x00\x00";                      // Nom Pascal vide
    $bim_block .= pack('N', strlen($iptc_data));   // Taille sur 4 octets
    $bim_block .= $iptc_data;
    if (strlen($iptc_data) % 2 !== 0) {
      $bim_block .= "\x00";                        // Alignement sur 2 octets
    }

    // Construire le segment APP13 complet
    $photoshop_header = "Photoshop 3.0\x00";
    $app13_content  = $photoshop_header . $bim_block;
    $app13_seg_len  = strlen($app13_content) + 2;  // +2 pour les octets de longueur

    $app13_segment = "\xFF\xED" . pack('n', $app13_seg_len) . $app13_content;

    // Supprimer les segments APP13 existants
    $jpeg_without_app13 = $this->removeApp13Segments($jpeg_data);

    // Insérer le nouveau APP13 juste après le SOI (FF D8)
    $new_jpeg = substr($jpeg_without_app13, 0, 2)  // SOI
              . $app13_segment
              . substr($jpeg_without_app13, 2);    // reste du JPEG

    return $new_jpeg;
  }

  /**
   * Supprime tous les segments APP13 (0xFFED) d'un JPEG
   *
   * @param string $jpeg_data Contenu binaire du JPEG
   * @return string JPEG sans segments APP13
   */
  private function removeApp13Segments($jpeg_data)
  {
    $result = substr($jpeg_data, 0, 2); // Conserver le SOI (FF D8)
    $pos = 2;
    $len = strlen($jpeg_data);

    while ($pos + 4 <= $len) {
      if (ord($jpeg_data[$pos]) !== 0xFF) {
        $result .= substr($jpeg_data, $pos);
        break;
      }

      $marker = ord($jpeg_data[$pos + 1]);

      // SOS = copier tout le reste tel quel
      if ($marker === 0xDA) {
        $result .= substr($jpeg_data, $pos);
        break;
      }

      // Segments sans longueur
      if ($marker === 0xD8 || $marker === 0xD9) {
        $result .= substr($jpeg_data, $pos, 2);
        $pos += 2;
        continue;
      }

      $seg_len = (ord($jpeg_data[$pos + 2]) << 8) | ord($jpeg_data[$pos + 3]);

      if ($marker === 0xED) {
        // APP13 : ignorer
      } else {
        $result .= substr($jpeg_data, $pos, 2 + $seg_len);
      }

      $pos += 2 + $seg_len;
    }

    return $result;
  }

  //========================================================================
  // SECTION IPTC - Parse / Build (inchangé)
  //========================================================================

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
      if (ord($binary[$pos]) != 0x1C) {
        $pos++;
        continue;
      }

      if ($pos + 4 >= $len) break;

      $record = ord($binary[$pos + 1]);
      $tag    = ord($binary[$pos + 2]);
      $size   = (ord($binary[$pos + 3]) << 8) | ord($binary[$pos + 4]);

      if ($pos + 5 + $size > $len) break;

      $value = substr($binary, $pos + 5, $size);
      $key   = sprintf('%d#%03d', $record, $tag);

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
      $data['1#090'] = "\x1B%G";
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
      if ($key === '1#000' || $key === '1#090' || $key === '2#000') {
        continue;
      }

      list($record, $tag) = explode('#', $key);
      $record = intval($record);
      $tag    = intval($tag);

      $values = is_array($value) ? $value : array($value);

      foreach ($values as $val) {
        $size    = strlen($val);
        $binary .= chr(0x1C);
        $binary .= chr($record);
        $binary .= chr($tag);
        $binary .= pack('n', $size);
        $binary .= $val;
      }
    }

    return $binary;
  }
}
?>