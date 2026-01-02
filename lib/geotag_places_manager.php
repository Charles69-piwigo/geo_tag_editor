<?php
/**
 * Gestion des lieux personnels pour geo_tag_editor
 * Fichier : lib/geotag_places_manager.php
 */

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class GeoTagEditorPlaces {
    private $table;
    private $config;
    
    public function __construct() {
        $this->table = 'piwigo_geotag_places';
        $this->config = 'geotag_places_enabled';
    }
    
    /**
     * Installation : créer la table
     */
    public function install() {
        global $conf;
        
        $query = "CREATE TABLE IF NOT EXISTS `" . $this->table . "` (
            `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
            `latitude` double(8,6) NOT NULL,
            `longitude` double(9,6) NOT NULL,
            `name` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `name` (`name`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8";
        
        pwg_query($query);
        
// Initialiser la configuration uniquement si elle n'existe pas
// Cela évite d'écraser une config existante (cas des mises à jour)
$query = "SELECT COUNT(*) FROM " . CONFIG_TABLE . " WHERE param = '" . $this->config . "'";
$result = pwg_query($query);
$row = pwg_db_fetch_row($result);

if ($row[0] == 0) {
    // Config n'existe pas → créer avec enabled = false
    $config = array('enabled' => false);
    conf_update_param($this->config, $config);
}
// Sinon, on garde la config existante (ne rien faire)
    }
    
    /**
     * Désinstallation : supprimer la table
     */
    public function uninstall() {
        $query = "DROP TABLE IF EXISTS `" . $this->table . "`";
        pwg_query($query);
        
        // Supprimer la configuration
        $query = "DELETE FROM " . CONFIG_TABLE . " WHERE param = '" . $this->config . "'";
        pwg_query($query);
    }
    
    /**
     * Vérifier si la fonctionnalité est activée
     */
    public function is_enabled() {
        global $conf;
        
        // Charger la config si elle existe
        if (isset($conf[$this->config])) {
            $config = safe_unserialize($conf[$this->config]);
            return isset($config['enabled']) && $config['enabled'] === true;
        }
        
        return false;
    }
    
    /**
     * Activer/désactiver la fonctionnalité
     */
    public function set_enabled($enabled) {
        global $conf;
        
        // Charger la config existante ou créer une nouvelle
        if (isset($conf[$this->config])) {
            $config = safe_unserialize($conf[$this->config]);
        } else {
            $config = array();
        }
        
        // Mettre à jour le statut
        $config['enabled'] = (bool)$enabled;
        
        // Sauvegarder
        conf_update_param($this->config, $config);
        
        // Recharger dans $conf
        $conf[$this->config] = serialize($config);
    }
    
    /**
     * Récupérer tous les lieux
     */
    public function get_all_places() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY name ASC";
        return query2array($query);
    }
    
    /**
     * Récupérer un lieu par ID
     */
    public function get_place($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = " . intval($id);
        $result = pwg_query($query);
        return pwg_db_fetch_assoc($result);
    }
    
    /**
     * Rechercher des lieux par nom (pour autocomplétion)
     */
    public function search_places($search) {
        $search = pwg_db_real_escape_string($search);
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE name LIKE '%" . $search . "%' 
                  ORDER BY name ASC 
                  LIMIT 20";
        return query2array($query);
    }
    
    /**
 * Ajouter un lieu
 */
public function add_place($name, $latitude, $longitude) {
    // Supprimer les éventuels échappements automatiques
    $name = stripslashes($name);
    $name = pwg_db_real_escape_string($name);
    $latitude = floatval($latitude);
    $longitude = floatval($longitude);
    
    $query = "INSERT INTO " . $this->table . " (name, latitude, longitude) 
              VALUES ('" . $name . "', " . $latitude . ", " . $longitude . ")";
    pwg_query($query);
    
    return pwg_db_insert_id();
}
    
    /**
 * Mettre à jour un lieu
 */
public function update_place($id, $name, $latitude, $longitude) {
    $id = intval($id);
    // Supprimer les éventuels échappements automatiques
    $name = stripslashes($name);
    $name = pwg_db_real_escape_string($name);
    $latitude = floatval($latitude);
    $longitude = floatval($longitude);
    
    $query = "UPDATE " . $this->table . " 
              SET name = '" . $name . "', 
                  latitude = " . $latitude . ", 
                  longitude = " . $longitude . " 
              WHERE id = " . $id;
    pwg_query($query);
}
    
    /**
     * Supprimer un lieu
     */
    public function delete_place($id) {
        $id = intval($id);
        $query = "DELETE FROM " . $this->table . " WHERE id = " . $id;
        pwg_query($query);
    }
    
    /**
     * Vérifier si un lieu existe déjà (par nom)
     */
    public function place_exists($name) {
        $name = pwg_db_real_escape_string($name);
        $query = "SELECT id FROM " . $this->table . " WHERE name = '" . $name . "'";
        $result = pwg_query($query);
        return pwg_db_num_rows($result) > 0;
    }
    
    /**
     * Vérifier si piwigo_openstreetmap est installé
     */
    public function osm_plugin_exists() {
        $osm_table = 'piwigo_osm_places';
        
        $query = "SHOW TABLES LIKE '" . $osm_table . "'";
        $result = pwg_query($query);
        return pwg_db_num_rows($result) > 0;
    }
    
    /**
     * Récupérer les lieux du plugin OSM
     */
    public function get_osm_places() {
        if (!$this->osm_plugin_exists()) {
            return array();
        }
        
        $osm_table = 'piwigo_osm_places';
        
        $query = "SELECT * FROM " . $osm_table . " ORDER BY name ASC";
        return query2array($query);
    }
    
    /**
     * Importer les lieux depuis OSM
     * @param string $conflict_mode : 'skip', 'overwrite', 'duplicate'
     */
    public function import_from_osm($conflict_mode = 'skip') {
        $osm_places = $this->get_osm_places();
        $stats = array(
            'total' => count($osm_places),
            'imported' => 0,
            'skipped' => 0,
            'overwritten' => 0,
            'duplicated' => 0
        );
        
        foreach ($osm_places as $place) {
            $name = $place['name'];
            $latitude = $place['latitude'];
            $longitude = $place['longitude'];
            
            // Vérifier si le lieu existe déjà
            $query = "SELECT id FROM " . $this->table . " WHERE name = '" . pwg_db_real_escape_string($name) . "'";
            $result = pwg_query($query);
            $exists = pwg_db_fetch_assoc($result);
            
            if ($exists) {
                // Le lieu existe déjà
                switch ($conflict_mode) {
                    case 'skip':
                        $stats['skipped']++;
                        break;
                        
                    case 'overwrite':
                        $this->update_place($exists['id'], $name, $latitude, $longitude);
                        $stats['overwritten']++;
                        break;
                        
                    case 'duplicate':
                        // Trouver un nom unique
                        $unique_name = $this->find_unique_name($name);
                        $this->add_place($unique_name, $latitude, $longitude);
                        $stats['duplicated']++;
                        break;
                }
            } else {
                // Nouveau lieu
                $this->add_place($name, $latitude, $longitude);
                $stats['imported']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Trouver un nom unique en ajoutant un suffixe
     */
    private function find_unique_name($base_name) {
        $counter = 2;
        $name = $base_name . ' (2)';
        
        while ($this->place_exists($name)) {
            $counter++;
            $name = $base_name . ' (' . $counter . ')';
        }
        
        return $name;
    }
}