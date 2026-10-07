<?php
/**
 * Page d'administration : Gestion des lieux personnels
 * Fichier : include/manage_places.php
 */

defined('GEOTAG_PATH') or die('Hacking attempt!');

// Charger les traductions
//load_language('plugin.lang', GEOTAG_PATH);

// Charger la classe de gestion
include_once(GEOTAG_PATH . 'lib/geotag_places_manager.php');
$places_manager = new GeoTagEditorPlaces();

// Charger la configuration dans $conf
global $conf;
$query = "SELECT value FROM " . CONFIG_TABLE . " WHERE param = 'geotag_places_enabled'";
$result = pwg_query($query);
if ($row = pwg_db_fetch_assoc($result)) {
    $conf['geotag_places_enabled'] = $row['value'];
}

// Afficher le tabsheet
geotageditor_admin_tabsheet('places');

// Charger Leaflet pour la carte (isolé comme dans l'éditeur)
global $template;

// CSS Leaflet
$template->append('head_elements', '
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
      crossorigin="" />
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css"
      integrity="sha256-V2sIX92Uh6ZaGSFTKMHghsB85b9toJtmazgG09AI2uk="
        crossorigin="" />
');

// JS Leaflet + isolation
$template->append('footer_elements', '
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
        crossorigin=""></script>
<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"
        integrity="sha256-vpYzxNhw4m+zfxz+XFp3GBZnEUAD6hYgeseFDY2ordE="
        crossorigin=""></script>
<script src="https://unpkg.com/@maplibre/maplibre-gl-leaflet@0.0.22/leaflet-maplibre-gl.js"
        integrity="sha256-WezY0rMnedJPG9Jrh8iJDyfkpueFjZk70X5Nh45tJnc="
        crossorigin=""></script>
<script>
  // Isoler Leaflet 1.9.4 pour éviter les conflits avec OSM
  (function() {
    if (typeof L !== "undefined" && typeof L.noConflict === "function") {
      var existingL = window.L;
      window.GeoTagLeaflet = L.noConflict();
      //console.log("Geo Tag Places: Leaflet 1.9.4 isolé");
      if (existingL) {
       // console.log("Geo Tag Places: Leaflet existant préservé v" + (existingL.version || "?"));
      }
    } else if (typeof L !== "undefined") {
      window.GeoTagLeaflet = L;
      //console.log("Geo Tag Places: Leaflet 1.9.4 chargé");
    }
  })();
</script>
<script src="' . GEOTAG_PATH . 'js/geotag_basemap.js"></script>
');




// Traitement des actions AJAX via $_POST
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'toggle_enabled':
            $enabled = $_POST['enabled'] === 'true';
            $places_manager->set_enabled($enabled);
            
            // Message de confirmation comme dans manage_rights
            $page['infos'][] = l10n('Configuration enregistrée');
            break;
            
        case 'get_places':
            header('Content-Type: application/json');
            $places = $places_manager->get_all_places();
            echo json_encode(array('success' => true, 'places' => $places));
            exit;
            
        case 'add_place':
            header('Content-Type: application/json');
            $name = $_POST['name'];
            $lat = floatval($_POST['latitude']);
            $lng = floatval($_POST['longitude']);
            
            if (empty($name)) {
                echo json_encode(array('success' => false, 'error' => 'Le nom est requis'));
                exit;
            }
            
            $id = $places_manager->add_place($name, $lat, $lng);
            echo json_encode(array('success' => true, 'id' => $id));
            exit;
            
        case 'update_place':
            header('Content-Type: application/json');
            $id = intval($_POST['id']);
            $name = $_POST['name'];
            $lat = floatval($_POST['latitude']);
            $lng = floatval($_POST['longitude']);
            
            if (empty($name)) {
                echo json_encode(array('success' => false, 'error' => 'Le nom est requis'));
                exit;
            }
            
            $places_manager->update_place($id, $name, $lat, $lng);
            echo json_encode(array('success' => true));
            exit;
            
        case 'delete_place':
            header('Content-Type: application/json');
            $id = intval($_POST['id']);
            $places_manager->delete_place($id);
            echo json_encode(array('success' => true));
            exit;
            
        case 'search_places':
            header('Content-Type: application/json');
            $search = $_POST['search'];
            $places = $places_manager->search_places($search);
            echo json_encode(array('success' => true, 'places' => $places));
            exit;
            
        case 'check_osm':
            header('Content-Type: application/json');
            $has_osm = $places_manager->osm_plugin_exists();
            echo json_encode(array('success' => true, 'has_osm' => $has_osm));
            exit;
            
        case 'import_from_osm':
            header('Content-Type: application/json');
            $mode = $_POST['conflict_mode'];
            $stats = $places_manager->import_from_osm($mode);
            echo json_encode(array('success' => true, 'stats' => $stats));
            exit;
    }
}

// Préparer les données pour le template
$template->assign(array(
    'GEOTAG_PATH' => GEOTAG_PATH,
    'PLACES_ENABLED' => $places_manager->is_enabled(),
    'HAS_OSM_PLUGIN' => $places_manager->osm_plugin_exists(),
));

// Charger le template
$template->set_filename('geo_tag_editor_manage_places', dirname(__FILE__).'/../template/manage_places.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'geo_tag_editor_manage_places');
?>