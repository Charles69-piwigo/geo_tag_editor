<?php
defined('GEOTAGWRITE_PATH') or die('Hacking attempt!');

// Charger les traductions
load_language('plugin.lang', GEOTAGWRITE_PATH);

include_once(GEOTAGWRITE_PATH . 'lib/rights_manager.php');

// Charger le JS spécifique
$template->func_combine_script(array(
    'id' => 'admin_rights',
    'path' => GEOTAGWRITE_PATH . 'js/admin_rights.js',
    'require' => 'jquery'
));


// Passer les traductions au JavaScript
$template->append('head_elements', '
<script type="text/javascript">
var adminRightsLang = {
    "select_album": "' . l10n('-- Sélectionner un album --') . '",
    "type_to_search": "' . l10n('Tapez pour rechercher...') . '",
    "no_results": "' . l10n('Aucun résultat') . '",
    "filter_albums": "' . l10n('Filtrer les albums...') . '",
    "add": "' . l10n('+ Ajouter') . '",
    "albums": "' . l10n('albums') . '",
    "authorized_albums": "' . l10n('Albums autorisés (sous-albums inclus) :') . '",
    "sub_albums_included": "' . l10n('(+ sous-albums)') . '",
    "select_album_from_list": "' . l10n('Veuillez sélectionner un album dans la liste') . '",
    "max_5_albums": "' . l10n('Maximum 5 albums par utilisateur') . '",
    "config_saved": "' . l10n('Configuration enregistrée avec succès') . '",
    "config_saved_all_mode": "' . l10n('Configuration enregistrée') . '\\n\\n' . l10n('Mode: Tous les albums') . '\\n\\n' . l10n('Les configurations utilisateurs sont conservées et seront réappliquées si vous revenez en mode sélectif.') . '",
    "save_error": "' . l10n('Erreur lors de la sauvegarde') . '"
};
</script>
');


// Récupérer la configuration actuelle
$config = get_geotag_rights_config();

// Traitement des actions AJAX
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
case 'save_rights_config':
    $mode = isset($_POST['mode']) ? $_POST['mode'] : 'all';
    $users_config = isset($_POST['users']) ? json_decode(stripslashes($_POST['users']), true) : array();
    
    // Valider les données
    if (!in_array($mode, array('all', 'selective'))) {
        $mode = 'all';
    }
    
    // Récupérer la config existante
    $existing_config = get_geotag_rights_config();
    
    // Valider chaque configuration utilisateur
    $validated_users = array();
    foreach ($users_config as $user_id => $categories) {
        $user_id = intval($user_id);
        if (!is_array($categories)) {
            continue;
        }
        
        // Limiter à 5 albums
        $categories = array_slice($categories, 0, 5);
        
        // Convertir en entiers
        $categories = array_map('intval', $categories);
        
        $validated_users[$user_id] = $categories;
    }
    
    // IMPORTANT: Conserver les configs users existantes si on passe en mode "all"
    // Cela permet de revenir en mode "selective" sans perdre la configuration
    if ($mode === 'all' && empty($validated_users) && !empty($existing_config['users'])) {
        // On garde les anciennes configs users
        $validated_users = $existing_config['users'];
    }
    
    $new_config = array(
        'mode' => $mode,
        'users' => $validated_users
    );
    
    save_geotag_rights_config($new_config);
    $config = $new_config;
    
    $page['infos'][] = l10n('Configuration enregistrée');
    break;
        
        case 'get_geotag_users':
            // Retourner JSON pour AJAX
            header('Content-Type: application/json');
            $users = get_geotag_group_users();
            echo json_encode($users);
            exit;
        
        case 'get_user_albums':
            // Retourner JSON pour AJAX
            header('Content-Type: application/json');
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            
            if ($user_id > 0) {
                $categories = geotag_get_user_authorized_categories($user_id);
                echo json_encode($categories);
            } else {
                echo json_encode(array());
            }
            exit;
    }
}

// Afficher le tabsheet
geotageditor_admin_tabsheet('manage_rights');

// Récupérer les utilisateurs du groupe GeoTag
$geotag_users = get_geotag_group_users();

// Assigner les variables au template
$template->assign(array(
    'RIGHTS_MODE' => $config['mode'],
    'GEOTAG_USERS' => $geotag_users,
    'USERS_CONFIG' => json_encode($config['users']),
    'GEOTAGWRITE_ADMIN_URL' => GEOTAGWRITE_ADMIN . '&tab=manage_rights',
));

// Charger le template
$template->set_filename('geo_tag_editor_manage_rights', dirname(__FILE__).'/../template/manage_rights.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'geo_tag_editor_manage_rights');
?>
