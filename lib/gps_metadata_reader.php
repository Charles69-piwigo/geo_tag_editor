<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class GPSMetadataReader
{
  public function readGPS($image_path)
  {
    $gps_data = array(
      'latitude' => null,
      'longitude' => null,
      'altitude' => null
    );

    error_log('GTE READER - image_path: ' . $image_path);
    error_log('GTE READER - file_exists: ' . var_export(file_exists($image_path), true));

    // Essayer d'abord avec exif_read_data (plus rapide et natif)
    if (function_exists('exif_read_data')) {
      error_log('GTE READER - exif_read_data disponible');
      $exif = @exif_read_data($image_path, 'GPS');

      if ($exif && isset($exif['GPSLatitude']) && isset($exif['GPSLongitude'])) {
        error_log('GTE READER - GPS trouvé via exif_read_data');

        // Convertir la latitude
        if (isset($exif['GPSLatitude'])) {
          $lat = $this->gps2Num($exif['GPSLatitude']);
          $lat_ref = isset($exif['GPSLatitudeRef']) ? $exif['GPSLatitudeRef'] : 'N';
          if ($lat_ref == 'S') {
            $lat = -$lat;
          }
          $gps_data['latitude'] = $lat;
        }

        // Convertir la longitude
        if (isset($exif['GPSLongitude'])) {
          $lon = $this->gps2Num($exif['GPSLongitude']);
          $lon_ref = isset($exif['GPSLongitudeRef']) ? $exif['GPSLongitudeRef'] : 'E';
          if ($lon_ref == 'W') {
            $lon = -$lon;
          }
          $gps_data['longitude'] = $lon;
        }

        // Convertir l'altitude
        if (isset($exif['GPSAltitude'])) {
          $alt_parts = explode('/', $exif['GPSAltitude']);
          if (count($alt_parts) == 2 && $alt_parts[1] != 0) {
            $alt = $alt_parts[0] / $alt_parts[1];
            $alt_ref = isset($exif['GPSAltitudeRef']) ? $exif['GPSAltitudeRef'] : 0;
            if ($alt_ref == 1) {
              $alt = -$alt;
            }
            $gps_data['altitude'] = $alt;
          }
        }

        error_log('GTE READER - résultat final: ' . json_encode($gps_data));
        return $gps_data;

      } else {
        error_log('GTE READER - exif_read_data: pas de GPS trouvé');
      }

    } else {
      error_log('GTE READER - exif_read_data non disponible');
    }

    // Fallback avec Imagick si exif_read_data n'a rien trouvé
    if (extension_loaded('imagick')) {
      error_log('GTE READER - fallback Imagick disponible');
      try {
        $imagick = new Imagick($image_path);
        $exif_data = $imagick->getImageProperties();

        if (isset($exif_data['exif:GPSLatitude']) && isset($exif_data['exif:GPSLongitude'])) {
          error_log('GTE READER - GPS trouvé via Imagick');

          $gps_data['latitude'] = $this->parseImagickGPS(
            $exif_data['exif:GPSLatitude'],
            isset($exif_data['exif:GPSLatitudeRef']) ? $exif_data['exif:GPSLatitudeRef'] : 'N'
          );

          $gps_data['longitude'] = $this->parseImagickGPS(
            $exif_data['exif:GPSLongitude'],
            isset($exif_data['exif:GPSLongitudeRef']) ? $exif_data['exif:GPSLongitudeRef'] : 'E'
          );

          if (isset($exif_data['exif:GPSAltitude'])) {
            $alt_parts = explode('/', $exif_data['exif:GPSAltitude']);
            if (count($alt_parts) == 2 && $alt_parts[1] != 0) {
              $alt = $alt_parts[0] / $alt_parts[1];
              $alt_ref = isset($exif_data['exif:GPSAltitudeRef']) ? intval($exif_data['exif:GPSAltitudeRef']) : 0;
              if ($alt_ref == 1) {
                $alt = -$alt;
              }
              $gps_data['altitude'] = $alt;
            }
          }

        } else {
          error_log('GTE READER - Imagick: pas de GPS trouvé');
        }

        $imagick->clear();
        $imagick->destroy();

      } catch (Exception $e) {
        error_log('GTE READER - Imagick exception: ' . $e->getMessage());
      }

    } else {
      error_log('GTE READER - Imagick non disponible');
    }

    error_log('GTE READER - résultat final: ' . json_encode($gps_data));
    return $gps_data;
  }

  /**
   * Convertit les coordonnées GPS EXIF en degrés décimaux
   */
  private function gps2Num($coordPart)
  {
    if (!is_array($coordPart) || count($coordPart) != 3) {
      return 0;
    }

    $degrees = 0;
    $minutes = 0;
    $seconds = 0;

    // Degrés
    if (is_string($coordPart[0])) {
      $parts = explode('/', $coordPart[0]);
      if (count($parts) == 2 && $parts[1] != 0) {
        $degrees = $parts[0] / $parts[1];
      }
    } else {
      $degrees = $coordPart[0];
    }

    // Minutes
    if (is_string($coordPart[1])) {
      $parts = explode('/', $coordPart[1]);
      if (count($parts) == 2 && $parts[1] != 0) {
        $minutes = $parts[0] / $parts[1];
      }
    } else {
      $minutes = $coordPart[1];
    }

    // Secondes
    if (is_string($coordPart[2])) {
      $parts = explode('/', $coordPart[2]);
      if (count($parts) == 2 && $parts[1] != 0) {
        $seconds = $parts[0] / $parts[1];
      }
    } else {
      $seconds = $coordPart[2];
    }

    return $degrees + ($minutes / 60) + ($seconds / 3600);
  }

  /**
   * Parse les coordonnées GPS depuis Imagick
   */
  private function parseImagickGPS($coord_str, $ref)
  {
    // Format: "48/1, 51/1, 2123/100" ou similaire
    $parts = explode(', ', $coord_str);
    if (count($parts) != 3) {
      return null;
    }

    $degrees = 0;
    $minutes = 0;
    $seconds = 0;

    foreach ($parts as $i => $part) {
      $fraction = explode('/', $part);
      if (count($fraction) == 2 && $fraction[1] != 0) {
        $value = $fraction[0] / $fraction[1];
        if ($i == 0) $degrees = $value;
        elseif ($i == 1) $minutes = $value;
        elseif ($i == 2) $seconds = $value;
      }
    }

    $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

    if ($ref == 'S' || $ref == 'W') {
      $decimal = -$decimal;
    }

    return $decimal;
  }
}
?>