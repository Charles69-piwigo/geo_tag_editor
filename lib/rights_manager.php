<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

/**
 * Récupère la configuration des droits
 */
function get_geotag_rights_config() {
    $query = 'SELECT value FROM '.CONFIG_TABLE.' 
              WHERE param = \'geotag_rights_config\'';
    $result = pwg_query($query);
    
    if ($row = pwg_db_fetch_assoc($result)) {
        return json_decode($row['value'], true);
    }
    
    return array('mode' => 'all', 'users' => array());
}

/**
 * Sauvegarde la configuration des droits
 */
function save_geotag_rights_config($config) {
    $query = 'REPLACE INTO '.CONFIG_TABLE.' (param, value) 
              VALUES (\'geotag_rights_config\', \''.pwg_db_real_escape_string(json_encode($config)).'\')';
    pwg_query($query);
}

/**
 * Récupère les utilisateurs du groupe GeoTag
 */
function get_geotag_group_users() {
    // Trouver l'ID du groupe GeoTag
    $query = 'SELECT id FROM '.GROUPS_TABLE.' WHERE name = \'GeoTag\'';
    $result = pwg_query($query);
    $row = pwg_db_fetch_assoc($result);
    
    if (!$row) {
        return array();
    }
    
    $group_id = $row['id'];
    
    // Récupérer les utilisateurs de ce groupe
    $query = '
        SELECT u.id, u.username
        FROM '.USER_GROUP_TABLE.' ug
        JOIN '.USERS_TABLE.' u ON ug.user_id = u.id
        WHERE ug.group_id = '.$group_id.'
        ORDER BY u.username';
    
    $result = pwg_query($query);
    $users = array();
    
    while ($row = pwg_db_fetch_assoc($result)) {
        $users[$row['id']] = $row['username'];
    }
    
    return $users;
}

/**
 * Récupère les albums visibles par un utilisateur
 */
function geotag_get_user_authorized_categories($user_id) {
    global $user;
    
    // Sauvegarder l'utilisateur actuel
    $saved_user = $user;
    
    // Charger les données de l'utilisateur cible
    $user = build_user($user_id, false);
    
    // Récupérer toutes les catégories visibles par l'utilisateur
    $query = '
        SELECT id, name, uppercats, global_rank
        FROM '.CATEGORIES_TABLE.'
        '.get_sql_condition_FandF(
            array(
                'forbidden_categories' => 'id',
            ),
            'WHERE'
        ).'
        ORDER BY global_rank';
    
    $result = pwg_query($query);
    $categories = array();
    
    while ($row = pwg_db_fetch_assoc($result)) {
        $level = substr_count($row['uppercats'], ',');
        $categories[$row['id']] = array(
            'name' => $row['name'],
            'level' => $level
        );
    }
    
    // Restaurer l'utilisateur actuel
    $user = $saved_user;
    
    return $categories;
}

/**
 * Vérifie si un utilisateur a le droit de géolocaliser une photo
 */
function geotag_user_can_edit_image($image_id, $user_id = null) {
    global $user;
    
    if ($user_id === null) {
        $user_id = $user['id'];
    }
    
    // Les webmasters et admins ont toujours accès
    if (is_admin() || is_webmaster()) {
        return true;
    }
    
    // Vérifier si l'utilisateur est dans le groupe GeoTag
    $query = '
        SELECT COUNT(*) as count
        FROM '.USER_GROUP_TABLE.' ug
        JOIN '.GROUPS_TABLE.' g ON ug.group_id = g.id
        WHERE ug.user_id = '.$user_id.'
        AND g.name = \'GeoTag\'';
    
    $result = pwg_query($query);
    $row = pwg_db_fetch_assoc($result);
    
    if ($row['count'] == 0) {
        return false;
    }
    
    // Récupérer la configuration des droits
    $config = get_geotag_rights_config();
    
    // Mode "all" : accès à tous les albums visibles
    if ($config['mode'] === 'all') {
        // Vérifier que l'utilisateur peut voir l'image
        $query = '
            SELECT COUNT(*) as count
            FROM '.IMAGES_TABLE.' i
            JOIN '.IMAGE_CATEGORY_TABLE.' ic ON i.id = ic.image_id
            WHERE i.id = '.$image_id.'
            '.get_sql_condition_FandF(
                array(
                    'forbidden_categories' => 'ic.category_id',
                ),
                'AND'
            );
        
        $result = pwg_query($query);
        $row = pwg_db_fetch_assoc($result);
        
        return $row['count'] > 0;
    }
    
    // Mode "selective" : vérifier les albums autorisés
    if (!isset($config['users'][$user_id]) || empty($config['users'][$user_id])) {
        return false;
    }
    
    $authorized_categories = $config['users'][$user_id];
    
    // Récupérer toutes les catégories (y compris sous-catégories) autorisées
    $all_authorized = array();
    foreach ($authorized_categories as $cat_id) {
        // Ajouter la catégorie elle-même
        $all_authorized[] = $cat_id;
        
        // Ajouter toutes les sous-catégories
        $query = '
            SELECT id
            FROM '.CATEGORIES_TABLE.'
            WHERE uppercats REGEXP \'(^|,)'.$cat_id.'(,|$)\'';
        
        $result = pwg_query($query);
        while ($row = pwg_db_fetch_assoc($result)) {
            $all_authorized[] = $row['id'];
        }
    }
    
    $all_authorized = array_unique($all_authorized);
    
    // Vérifier si l'image est dans une catégorie autorisée
    $query = '
        SELECT COUNT(*) as count
        FROM '.IMAGE_CATEGORY_TABLE.'
        WHERE image_id = '.$image_id.'
        AND category_id IN ('.implode(',', $all_authorized).')';
    
    $result = pwg_query($query);
    $row = pwg_db_fetch_assoc($result);
    
    return $row['count'] > 0;
}
?>
