<?php
/*
Plugin Name: geo_tag_editor
Version: 1.8a
Description: Gestion des coordonnées GPS dans les métadonnées
Plugin URI: https://piwigo.org/ext/extension_view.php?eid=1057
Author: Charles69
Has Settings: webmaster
*/

//============= VERSIONS ============================================
/*
version 1.8a - 23/06/2026
    bug affichage photo dans la fenêtre popup

version 1.8 - 23/06/2026
    conformation au standard get_original_url

version 1.7a - 24/02/2026 (non diffusée)
    tests pour débugage
    version ne nécessitant pas Imagick
    
version 1.7 - 17/02/2026
    corrigé régression png
    si pwg_openstreetmap actif, enregistrement en base de données
    corrigé impossibilité supprimer description existante

version 1.6 - 13/02/2026
    corrigé traduction absente dans main.inc
    corrigé impossible saisir description sans les coordonnées
    
version 1.5 - 28/01/2026
    nouvelle fonctionnalité : champ de description

version 1.4 - 24/01/2026
    mémorisation du niveau de zoom entre deux tags photos
    commentaires sur console.log
    traduction : méthode lazy loading
    
version 1.3c - 24/01/2026  - diffusion pour test
    test mémorisation niveau de zoom
    commentaires sur console.log

version 1.3b - 09/01/2026
    ajouté langues de_DE ru_RU

version 1.3a - 08/01/2026
    ajouté gestion défaut langue UK
    nettoyage code 

version 1.3 - 06/01/2026
    corrigé : détection de geo tag pwg_osm seul (pas dans la photo)
    ajouté la gestion de lieux personnels , import des lieux personnels de pwg_osm
    ajouté dans l'éditeur l'utilisation des lieux personnels
    correction traduction

version 1.2 - 29/12/2025
	  ajouté Plugin URI

version 1.1d - 1ère publication
version 1.1C - 29/12/2025
    leaflet 1.9.4 isolée pour geo_tag_editor

version 1.1B - 28/12/2025 
    conflit leaflet Katryne - sépararation des instances

version 1.1A - 28/12/2025 
    mémorisation position du dernier enregistré
    mise à jour bdd lors de la suppression d'une localisation 
    chargement dynamique de Leaflet pour éviter conflits

version 1.1 
    effet de bord css sur liste des albums

version 1.0 - 26/12/2025
    Création du plugin
    Lecture et écriture des coordonnées GPS EXIF
    Interface avec OpenStreetMap (Leaflet.js)
    Recherche de lieux (Nominatim)
    Copier/coller de positions entre photos
    Utilisation de PHP Imagick ou External ImageMagick (sans exiftool)
    Utilisation de la bibliothèque PHP PEL
*/
//====================================================================



if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

if (basename(dirname(__FILE__)) != 'geo_tag_editor')
{
  add_event_handler('init', 'geo_tag_editor_error');
  function geo_tag_editor_error()
  {
    global $page;
    $page['errors'][] = 'Désactiver le plugin et renommer le répertoire "geo_tag_editor"';
  }
  return;
}

// Plugin constants
define('GEOTAG_ID', basename(dirname(__FILE__)));                                     //  geo_tag_editor
define('GEOTAG_PATH', PHPWG_PLUGINS_PATH . GEOTAG_ID . '/');                          //  ./plugins/geo_tag_editor 
define('GEOTAG_ADMIN', get_root_url() . 'admin.php?page=plugin-' . GEOTAG_ID);        //  admin.php?page=plugin-geo_tag_editor


// Logs -------------------------------------- à activer pour débugage 

//error_reporting(E_ALL);
//ini_set('display_errors', 0);
//ini_set('log_errors', 1);
//ini_set('error_log', './plugins/geo_tag_editor/geo_tag_debug.log');


// Charger les classes
require_once(GEOTAG_PATH . 'lib/geotag_imagick_wrapper.php');
require_once(GEOTAG_PATH . 'lib/gps_metadata_reader.php');
require_once(GEOTAG_PATH . 'lib/gps_metadata_writer.php');
require_once(GEOTAG_PATH . 'lib/geotag_file_resolver.php');
require_once(GEOTAG_PATH . 'lib/rights_manager.php');
require_once(GEOTAG_PATH . 'img/icon_svg.php');




// ==================== VÉRIFICATION DES DROITS ====================
function geo_tag_user_can_edit($image_id)
{
  global $user;
  
  if (is_admin() || is_webmaster()) {
    return true;
  }
  
  $query = '
    SELECT COUNT(*) as count
    FROM '.USER_GROUP_TABLE.' ug
    JOIN '.GROUPS_TABLE.' g ON ug.group_id = g.id
    WHERE ug.user_id = '.$user['id'].'
    AND g.name = \'GeoTag\'';
  
  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);
  
  if ($row['count'] == 0) {
    return false;
  }
  
  $config = get_geotag_rights_config();
  
  if ($config['mode'] === 'all') {
    return true;
  }
  
  if ($config['mode'] === 'selective') {
    if (!isset($config['users'][$user['id']]) || empty($config['users'][$user['id']])) {
      return false;
    }
    
    $authorized_categories = $config['users'][$user['id']];
    
    $query = '
      SELECT category_id
      FROM '.IMAGE_CATEGORY_TABLE.'
      WHERE image_id = '.$image_id;
    
    $result = pwg_query($query);
    $image_categories = array();
    
    while ($row = pwg_db_fetch_assoc($result)) {
      $image_categories[] = $row['category_id'];
    }
    
    foreach ($image_categories as $cat_id) {
      $query = '
        SELECT uppercats
        FROM '.CATEGORIES_TABLE.'
        WHERE id = '.$cat_id;
      
      $result = pwg_query($query);
      $row = pwg_db_fetch_assoc($result);
      
      if ($row) {
        $all_parents = explode(',', $row['uppercats']);
        
        foreach ($authorized_categories as $auth_cat) {
          if (in_array($auth_cat, $all_parents)) {
            return true;
          }
        }
      }
    }
    
    return false;
  }
  
  return false;
}

//===================== CHARGEMENT DES LANGUES , UK PAR DEFAUT ==================
// Charger d'abord l'anglais comme base
load_language('plugin.lang', GEOTAG_PATH, array('language' => 'en_UK', 'no_fallback' => true));
// Puis charger la langue de l'utilisateur (qui écrasera l'anglais si c'est du français)
load_language('plugin.lang', GEOTAG_PATH);
//=================================================================================


// ==================== CHARGER JQUERY ====================
add_event_handler('loc_begin_page_header', 'geo_tag_load_jquery');
function geo_tag_load_jquery()
{
  global $template;
  
  $template->append('head_elements', '
  <script type="text/javascript">
    if (typeof jQuery === "undefined") {
      document.write(\'<script type="text/javascript" src="' . get_root_url() . 'themes/default/js/jquery.min.js"><\/script>\');
    }
  </script>
  ');
}

// ==================== CHARGER LE CSS ====================
add_event_handler('loc_begin_page_header', 'geo_tag_load_css',);
function geo_tag_load_css()
{
  global $template, $page;
  
  // Charger seulement sur les pages photo
  if (!isset($page['image_id'])) {
    return;
  }
  
  $template->append('head_elements', '
  <link rel="stylesheet" href="' . GEOTAG_PATH . 'css/geo_tag_button.css">
  <link rel="stylesheet" href="' . GEOTAG_PATH . 'css/geo_tag_modal.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
        crossorigin="" />
  ');
}

// ==================== CHARGER SCRIPT ====================
add_event_handler('loc_end_page_tail', 'geo_tag_load_scripts');
function geo_tag_load_scripts()
{
  global $template, $page;
  
  if (!isset($page['image_id'])) {
    return;
  }
  
  // Charger Leaflet 1.9.4 et l'isoler immédiatement avec noConflict
  $template->append('footer_elements', '
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
          integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
          crossorigin=""></script>
  <script>
    // Isoler Leaflet 1.9.4 pour éviter les conflits
    (function() {
      if (typeof L !== "undefined" && typeof L.noConflict === "function") {
        var existingL = window.L;
        window.GeoTagLeaflet = L.noConflict();
        //console.log("Geo Tag Editor: Leaflet 1.9.4 isolé");
        if (existingL) {
          //console.log("Geo Tag Editor: Leaflet existant préservé v" + (existingL.version || "?"));
        }
      } else if (typeof L !== "undefined") {
        window.GeoTagLeaflet = L;
        //console.log("Geo Tag Editor: Leaflet 1.9.4 chargé");
      } else {
        //console.error("Geo Tag Editor: Échec du chargement de Leaflet");
      }
    })();
  </script>
  <script src="' . GEOTAG_PATH . 'template/geo_tag.js"></script>
  ');
}

// ==================== AJOUTER LE BOUTON ====================
add_event_handler('loc_end_picture', 'geo_tag_add_button');

function geo_tag_add_button()
{
  global $template, $user, $page;
  
  if (!isset($page['image_id'])) {
    return;
  }
  
  $image_id = $page['image_id'];
  
  if (!geo_tag_user_can_edit($image_id)) {
    return;
  }
  
  $query = '
SELECT path, file, comment
FROM ' . IMAGES_TABLE . '
WHERE id = ' . intval($image_id);

  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);

  if (!$row) {
    return;
  }

  $image_path = geo_tag_resolve_path($row['path']);

  // Récupérer la description existante
  $description = isset($row['comment']) ? $row['comment'] : '';
  
  $ext = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));
  $is_jpeg = in_array($ext, array('jpg', 'jpeg'));

  if (!in_array($ext, array('jpg', 'jpeg', 'png', 'gif'))) {
    return;
  }

  // Pour les non-JPEG : géotagage BDD uniquement, nécessite piwigo_openstreetmap
  if (!$is_jpeg) {
    $osm_plugins = get_db_plugins('active', 'piwigo-openstreetmap');
    if (empty($osm_plugins)) {
      return; // Pas de géotagage possible pour les non-JPEG sans OSM
    }
  }

  $reader = new GPSMetadataReader();
  $gps_data = $is_jpeg ? $reader->readGPS($image_path) : array();
  
  $has_gps = !empty($gps_data['latitude']) && !empty($gps_data['longitude']);
  $gps_source = 'exif'; // Source par défaut
  
  // Si pas de GPS dans EXIF, chercher dans la BDD (données de piwigo_openstreetmap)
  if (!$has_gps) {
    $query_bdd = 'SELECT latitude, longitude FROM '.IMAGES_TABLE.' 
                  WHERE id = '.$image_id.' 
                  AND latitude IS NOT NULL 
                  AND longitude IS NOT NULL';
    $result_bdd = pwg_query($query_bdd);
    
    if ($row_bdd = pwg_db_fetch_assoc($result_bdd)) {
      $gps_data['latitude'] = $row_bdd['latitude'];
      $gps_data['longitude'] = $row_bdd['longitude'];
      $has_gps = true;
      $gps_source = 'database'; // Indicateur que les données viennent de la BDD
    }
  }
  
  // Compatibilité pdp : laisser le plugin de protection réécrire l'URL si actif
  include_once(PHPWG_ROOT_PATH . 'include/derivative.inc.php');
  $src_image = new SrcImage(array(
    'id'   => $image_id,
    'path' => $row['path'],
    'file' => $row['file'],
  ));
  $image_src_url = trigger_change('get_original_url', get_root_url() . $row['path'], $src_image);

  $button_data = array(
    'image_id' => $image_id,
    'image_src' => $image_src_url,
    'image_file' => $row['file'],
    'has_gps' => $has_gps,
    'latitude' => $has_gps ? $gps_data['latitude'] : null,
    'longitude' => $has_gps ? $gps_data['longitude'] : null,
    'altitude' => isset($gps_data['altitude']) ? $gps_data['altitude'] : null,
    'gps_source' => $gps_source,
    'is_jpeg' => $is_jpeg,
    'description' => $description,
    'save_url' => get_root_url() . 'ws.php?format=json&method=geotag.saveGPS'
  );
  
  $button_data_json = htmlspecialchars(json_encode($button_data), ENT_QUOTES, 'UTF-8');
  
  $geo_text = l10n('Geo Taguer');
  $geo_title = l10n('Géolocaliser la photo');
  
  $button_html = '
    <a href="#" 
       id="geotag-open-editor"
       data-geotag="' . $button_data_json . '"
       class="pwg-state-default pwg-button" 
       title="' . $geo_title . '" 
       rel="nofollow">
      <span class="pwg-icon">' . get_geo_tag_icon_svg() . '</span>
      <span class="pwg-button-text">' . $geo_text . '</span>
    </a>';

  if (isset($user['theme']) && ($user['theme'] == 'bootstrapdefault' || $user['theme'] == 'bootstrap_darkroom')) {
    $button_html = '
      <a href="#" 
         id="geotag-open-editor"
         data-geotag="' . $button_data_json . '"
         class="btn btn-primary" 
         title="' . $geo_title . '" 
         rel="nofollow">
        ' . get_geo_tag_icon_svg() . ' ' . $geo_text . '
      </a>';
  }
  
  $template->concat('PLUGIN_PICTURE_ACTIONS', $button_html);
}

// ==================== WEB SERVICES ====================
add_event_handler('ws_add_methods', 'geo_tag_add_ws_methods');

function geo_tag_add_ws_methods($arr)
{
  $service = &$arr[0];
  
  $service->addMethod(
    'geotag.saveGPS',
    'geotag_ws_save_gps',
    array(
      'image_id' => array('default' => null),
      'latitude' => array('default' => null),
      'longitude' => array('default' => null),
      'altitude' => array('default' => null),
      'description' => array('default' => null),
    ),
    'Save GPS coordinates and description to image metadata',
    null,
    array('POST')
  );
  
  $service->addMethod(
    'geotag.getGPS',
    'geotag_ws_get_gps',
    array(
      'image_id' => array('default' => null),
    ),
    'Get GPS coordinates from image metadata'
  );
  
  $service->addMethod(
    'geotag.removeGPS',
    'geotag_ws_remove_gps',
    array(
      'image_id' => array('default' => null),
    ),
    'Remove GPS coordinates from image metadata',
    null,
    array('POST')
  );
$service->addMethod(
    'geotag.removeGPS',
    'geotag_ws_remove_gps',
    array(
      'image_id' => array('default' => null),
    ),
    'Remove GPS coordinates from image metadata',
    null,
    array('POST')
  );
  
  // Lieux personnels
  $service->addMethod(
    'geotag.checkPlacesEnabled',
    'geotag_ws_check_places_enabled',
    array(),
    'Check if personal places are enabled'
  );
  
  $service->addMethod(
    'geotag.getPlaces',
    'geotag_ws_get_places',
    array(),
    'Get all personal places'
  );
  
  $service->addMethod(
    'geotag.addPlace',
    'geotag_ws_add_place',
    array(
      'name' => array('default' => null),
      'latitude' => array('default' => null),
      'longitude' => array('default' => null),
    ),
    'Add a new personal place',
    null,
    array('POST')
  );
}



// ==================== FONCTION WEB SERVICE: SAUVEGARDER GPS ====================
function geotag_ws_save_gps($params, &$service)
{
  if (empty($params['image_id'])) {
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing image_id');
  }

  if (!geo_tag_user_can_edit($params['image_id'])) {
    return new PwgError(403, 'Access denied');
  }

  // Récupérer les coordonnées GPS (peuvent être absentes)
  $has_gps = !empty($params['latitude']) && !empty($params['longitude']);
  $latitude = $has_gps ? floatval($params['latitude']) : null;
  $longitude = $has_gps ? floatval($params['longitude']) : null;
  $altitude = !empty($params['altitude']) ? floatval($params['altitude']) : null;

  // Récupérer la description (peut être vide ou null)
  // Note: Piwigo applique addslashes() à tous les $_REQUEST dans common.inc.php
  // Il faut donc utiliser stripslashes() pour récupérer la valeur originale
  $description = isset($params['description']) ? stripslashes(trim($params['description'])) : null;
  if ($description === '') {
    $description = null;
  }

  // Il faut au moins des coordonnées GPS, une description, ou un effacement explicite de description
  $description_sent = isset($params['description']);
  if (!$has_gps && $description === null && !$description_sent) {
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing GPS coordinates or description');
  }

  // Valider les coordonnées GPS si présentes
  if ($has_gps) {
    if ($latitude < -90 || $latitude > 90) {
      return new PwgError(WS_ERR_INVALID_PARAM, 'Invalid latitude');
    }

    if ($longitude < -180 || $longitude > 180) {
      return new PwgError(WS_ERR_INVALID_PARAM, 'Invalid longitude');
    }
  }

  $query = '
SELECT path
FROM ' . IMAGES_TABLE . '
WHERE id = ' . intval($params['image_id']);

  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);

  if (!$row) {
    return new PwgError(404, 'Image not found');
  }

  $image_path = geo_tag_resolve_path($row['path']);

  if (!file_exists($image_path)) {
    return new PwgError(404, 'Image file not found');
  }

  $ext = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));
  $is_jpeg = in_array($ext, array('jpg', 'jpeg'));

  // ---- Non-JPEG : écriture en BDD uniquement (pas d'EXIF) ----
  if (!$is_jpeg) {
    $updates = array();
    if ($has_gps) {
      $updates[] = 'latitude = ' . $latitude;
      $updates[] = 'longitude = ' . $longitude;
    }
    if ($description !== null) {
      $updates[] = "comment = '" . pwg_db_real_escape_string($description) . "'";
    } elseif (isset($params['description'])) {
      $updates[] = 'comment = NULL';
    }
    if (!empty($updates)) {
      $query = 'UPDATE ' . IMAGES_TABLE . ' SET ' . implode(', ', $updates)
             . ' WHERE id = ' . intval($params['image_id']);
      pwg_query($query);
    }
    if (function_exists('invalidate_user_cache')) {
      invalidate_user_cache();
    }
    return array(
      'stat' => 'ok',
      'message' => 'Data saved in database only (non-JPEG)',
      'latitude' => $latitude,
      'longitude' => $longitude,
      'altitude' => $altitude,
      'description' => $description
    );
  }

  // ---- JPEG : écriture EXIF + IPTC + sync BDD (comportement existant) ----
  $writer = new GPSMetadataWriter();

  // Écrire les coordonnées GPS (EXIF) seulement si présentes
  if ($has_gps) {
    $gps_result = $writer->writeGPS($image_path, $latitude, $longitude, $altitude);

    if (!$gps_result['success']) {
      return new PwgError(500, 'Failed to write GPS: ' . $gps_result['error']);
    }
  }

  // Écrire la description IPTC (si fournie ou pour la supprimer)

  // Écrire la description IPTC (si fournie ou pour la supprimer)
error_log('GTE MAIN - image_path: ' . $image_path);
error_log('GTE MAIN - description reçue: ' . var_export($description, true));
error_log('GTE MAIN - file_exists: ' . var_export(file_exists($image_path), true));
error_log('GTE MAIN - is_writable: ' . var_export(is_writable($image_path), true));

  $desc_result = $writer->writeDescription($image_path, $description);

error_log('GTE MAIN - writeDescription result: ' . var_export($desc_result, true));  

  if (!$desc_result['success']) {
    error_log('geo_tag_editor: Warning - Failed to write description: ' . $desc_result['error']);
  }

  // Synchroniser les métadonnées Piwigo
  // sync_metadata() lit l'IPTC du fichier et met à jour la BDD automatiquement
  // (même approche que face_tag_editor)
  geo_tag_sync_metadata($params['image_id']);

  // sync_metadata ne met pas à NULL les champs absents de l'IPTC
  // Il faut donc forcer la mise à jour en BDD quand la description est effacée
  if ($description === null && $description_sent) {
    $query = 'UPDATE ' . IMAGES_TABLE . " SET comment = NULL WHERE id = " . intval($params['image_id']);
    pwg_query($query);
  }

  return array(
    'stat' => 'ok',
    'message' => 'Data saved successfully',
    'latitude' => $latitude,
    'longitude' => $longitude,
    'altitude' => $altitude,
    'description' => $description
  );
}

// ==================== FONCTION WEB SERVICE: RÉCUPÉRER GPS ====================
function geotag_ws_get_gps($params, &$service)
{
  if (empty($params['image_id'])) {
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing image_id');
  }
  
  $query = '
SELECT path
FROM ' . IMAGES_TABLE . '
WHERE id = ' . intval($params['image_id']);
  
  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);
  
  if (!$row) {
    return new PwgError(404, 'Image not found');
  }
  
  $image_path = geo_tag_resolve_path($row['path']);
  
  if (!file_exists($image_path)) {
    return new PwgError(404, 'Image file not found');
  }
  
  $reader = new GPSMetadataReader();
  $gps_data = $reader->readGPS($image_path);
  
  return array(
    'image_id' => $params['image_id'],
    'has_gps' => !empty($gps_data['latitude']) && !empty($gps_data['longitude']),
    'latitude' => isset($gps_data['latitude']) ? $gps_data['latitude'] : null,
    'longitude' => isset($gps_data['longitude']) ? $gps_data['longitude'] : null,
    'altitude' => isset($gps_data['altitude']) ? $gps_data['altitude'] : null
  );
}

// ==================== FONCTION WEB SERVICE: SUPPRIMER GPS ====================
function geotag_ws_remove_gps($params, &$service)
{
  if (empty($params['image_id'])) {
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing image_id');
  }
  
  if (!geo_tag_user_can_edit($params['image_id'])) {
    return new PwgError(403, 'Access denied');
  }
  
  $query = '
SELECT path
FROM ' . IMAGES_TABLE . '
WHERE id = ' . intval($params['image_id']);
  
  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);
  
  if (!$row) {
    return new PwgError(404, 'Image not found');
  }
  
  $image_path = geo_tag_resolve_path($row['path']);

  if (!file_exists($image_path)) {
    return new PwgError(404, 'Image file not found');
  }

  $ext = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));
  $is_jpeg = in_array($ext, array('jpg', 'jpeg'));

  // Pour les JPEG : supprimer les données EXIF GPS
  if ($is_jpeg) {
    $writer = new GPSMetadataWriter();
    $result = $writer->removeGPS($image_path);

    if (!$result['success']) {
      return new PwgError(500, 'Failed to remove GPS: ' . $result['error']);
    }
  }

  // Mettre à jour la base de données (pour tous les formats)
  $query = '
UPDATE ' . IMAGES_TABLE . '
SET latitude = NULL, longitude = NULL
WHERE id = ' . intval($params['image_id']);

  pwg_query($query);

  geo_tag_sync_metadata($params['image_id']);

  return array(
    'stat' => 'ok',
    'message' => 'GPS coordinates removed successfully'
  );
}

// ==================== FONCTION WEB SERVICE: VÉRIFIER SI LIEUX ACTIVÉS ====================
function geotag_ws_check_places_enabled($params, &$service)
{
  include_once(GEOTAG_PATH . 'lib/geotag_places_manager.php');
  $places_manager = new GeoTagEditorPlaces();
  
  return array('enabled' => $places_manager->is_enabled());
}

// ==================== FONCTION WEB SERVICE: RÉCUPÉRER LES LIEUX ====================
function geotag_ws_get_places($params, &$service)
{
  include_once(GEOTAG_PATH . 'lib/geotag_places_manager.php');
  $places_manager = new GeoTagEditorPlaces();
  
  $places = $places_manager->get_all_places();
  
  return array(
    'success' => true,
    'places' => $places
  );
}

// ==================== FONCTION WEB SERVICE: AJOUTER UN LIEU ====================
function geotag_ws_add_place($params, &$service)
{
  if (empty($params['name'])) {
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing name');
  }
  
  if (empty($params['latitude']) || empty($params['longitude'])) {
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing coordinates');
  }
  
  include_once(GEOTAG_PATH . 'lib/geotag_places_manager.php');
  $places_manager = new GeoTagEditorPlaces();
  
  $name = $params['name'];
  $latitude = floatval($params['latitude']);
  $longitude = floatval($params['longitude']);
  
  $id = $places_manager->add_place($name, $latitude, $longitude);
  
  return array(
    'success' => true,
    'id' => $id
  );
}


// ==================== SYNCHRONISATION DES METADONNEES ====================
function geo_tag_sync_metadata($image_id)
{
  if (!function_exists('sync_metadata')) {
    include_once(PHPWG_ROOT_PATH . 'admin/include/functions_metadata.php');
  }
  
  if (!function_exists('tag_id_from_tag_name')) {
    include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
  }
  
  sync_metadata(array($image_id));
  invalidate_user_cache();
  
  return true;
}

// ==================== RÉSOLUTION DES CHEMINS ====================
function geo_tag_resolve_path($relative_path)
{
  $resolver = new GeoTagFileResolver();
  return $resolver->resolve($relative_path);
}

// ==================== TRADUCTIONS - Méthode Lazy loading ====================
add_event_handler('ws_add_methods', 'geotag_add_ws_methods');

function geotag_add_ws_methods($arr)
{
  $service = &$arr[0];
  
  $service->addMethod(
    'geotag.getTranslations',
    'geotag_ws_get_translations',
    array(),
    'Get translations for geotag editor'
  );
}

function geotag_ws_get_translations($params, &$service)
{


  $translations = array(
    'Éditeur de géolocalisation' => l10n('Éditeur de géolocalisation'),
    'Image' => l10n('Image'),
    'Instructions :' => l10n('Instructions :'),
    'Cliquez sur la carte pour placer le marqueur de position. Vous pouvez aussi déplacer le marqueur ou utiliser la recherche.' => l10n('Cliquez sur la carte pour placer le marqueur de position. Vous pouvez aussi déplacer le marqueur ou utiliser la recherche.'),
    'Position GPS' => l10n('Position GPS'),
    'Aucune position GPS' => l10n('Aucune position GPS'),
    'Latitude' => l10n('Latitude'),
    'Longitude' => l10n('Longitude'),
    'Altitude' => l10n('Altitude'),
    'Rechercher un lieu' => l10n('Rechercher un lieu'),
    'Rechercher...' => l10n('Rechercher...'),
    'Copier la position' => l10n('Copier la position'),
    'Coller la position' => l10n('Coller la position'),
    'Saisir/Coller coordonnées' => l10n('Saisir/Coller coordonnées'),
    'Appliquer' => l10n('Appliquer'),
    'Copier coordonnées' => l10n('Copier coordonnées'),
    'Google Lens' => l10n('Google Lens'),
    'Réinitialiser' => l10n('Réinitialiser'),
    'Supprimer GPS' => l10n('Supprimer GPS'),
    'Supprimer les coordonnées GPS' => l10n('Supprimer les coordonnées GPS'),
    'Annuler' => l10n('Annuler'),
    'Enregistrer ' => l10n('Enregistrer '),
    'Position copiée !' => l10n('Position copiée !'),
    'Aucune position à coller' => l10n('Aucune position à coller'),
    'Position collée !' => l10n('Position collée !'),
    'Coordonnées GPS enregistrées avec succès !' => l10n('Coordonnées GPS enregistrées avec succès !'),
    'Données enregistrées avec succès !' => l10n('Données enregistrées avec succès !'),
    'Coordonnées GPS supprimées !' => l10n('Coordonnées GPS supprimées !'),
    'Coordonnées copiées !' => l10n('Coordonnées copiées !'),
    'Coordonnées appliquées !' => l10n('Coordonnées appliquées !'),
    'Position réinitialisée !' => l10n('Position réinitialisée !'),
    'Voulez-vous vraiment supprimer les coordonnées GPS ?' => l10n('Voulez-vous vraiment supprimer les coordonnées GPS ?'),
    'Geo Taguer' => l10n('Geo Taguer'),
    'Veuillez placer un marqueur sur la carte' => l10n('Veuillez placer un marqueur sur la carte'),
    'Veuillez placer un marqueur sur la carte ou saisir une description' => l10n('Veuillez placer un marqueur sur la carte ou saisir une description'),
    'Recherche...' => l10n('Recherche...'),
    'Aucun résultat trouvé' => l10n('Aucun résultat trouvé'),
    'm' => l10n('m'),
    'Enregistrement...' => l10n('Enregistrement...'),
    'Suppression...' => l10n('Suppression...'),
    'Veuillez entrer un lieu à rechercher' => l10n('Veuillez entrer un lieu à rechercher'),
    'Veuillez entrer des coordonnées' => l10n('Veuillez entrer des coordonnées'),
    'Format invalide. Utilisez: latitude, longitude. Exemple: 45.433214, 12.339914' => l10n('Format invalide. Utilisez: latitude, longitude. Exemple: 45.433214, 12.339914'),
    'Coordonnées invalides. Vérifiez les valeurs.' => l10n('Coordonnées invalides. Vérifiez les valeurs.'),
    'Latitude invalide. Doit être entre -90 et 90.' => l10n('Latitude invalide. Doit être entre -90 et 90.'),
    'Longitude invalide. Doit être entre -180 et 180.' => l10n('Longitude invalide. Doit être entre -180 et 180.'),
    'Aucune position originale à restaurer' => l10n('Aucune position originale à restaurer'),
    'Erreur de copie. Coordonnées: ' => l10n('Erreur de copie. Coordonnées: '),
    'Lieux personnels' => l10n('Lieux personnels'),
    'Rechercher un lieu...' => l10n('Rechercher un lieu...'),
    'Appliquer' => l10n('Appliquer'),
    'Ajouter' => l10n('Ajouter'),
    'Nom du lieu :' => l10n('Nom du lieu :'),
    'Lieu' => l10n('Lieu'),
    'ajouté avec succès' => l10n('ajouté avec succès'),
    'Position appliquée depuis' => l10n('Position appliquée depuis'),
    'Pour utiliser Google Lens :' => l10n('Pour utiliser Google Lens :'),
    '1. Faites un clic droit sur l\'image à gauche' => l10n('1. Faites un clic droit sur l\'image à gauche'),
    '2. Sélectionnez "Rechercher une image avec Google Lens"' => l10n('2. Sélectionnez "Rechercher une image avec Google Lens"'),
    'OU' => l10n('OU'),
    'Cliquez sur OK pour télécharger l\'image et ouvrir Google Lens' => l10n('Cliquez sur OK pour télécharger l\'image et ouvrir Google Lens'),
    'Description' => l10n('Description'),
    'Description de l\'image...' => l10n('Description de l\'image...'),
    'Cliquez sur Enregistrer pour les écrire dans les métadonnées EXIF.' => l10n('Cliquez sur Enregistrer pour les écrire dans les métadonnées EXIF.'),
    'Coordonnées trouvées en base de données mais pas dans la photo.' => l10n('Coordonnées trouvées en base de données mais pas dans la photo.')

  );
  
 return $translations;

}

?>