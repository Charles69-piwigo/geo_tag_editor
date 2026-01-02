<div id="manage_rights_tab">
    <h2>{'Gestion des droits de géolocalisation'|@translate}</h2>
    
    <!-- Mode de fonctionnement -->
    <fieldset>
        <legend>{'Mode de fonctionnement du groupe GeoTag'|@translate}</legend>
        
        <p style="margin-bottom:15px; color:#666; ">
            <strong>{'Note'|@translate} :</strong> {'Les webmasters et administrateurs ont toujours un accès total, quel que soit le mode sélectionné.'|@translate}
        </p>
        
        <form method="post" action="{$GEOTAG_ADMIN}" id="rights_mode_form">
            <input type="hidden" name="action" value="save_rights_config">
            
            <label style="display:block; margin-bottom:10px;">
                <input type="radio" name="mode" value="all" {if $RIGHTS_MODE == 'all'}checked{/if}>
                <strong>{'Tous les albums'|@translate}</strong> - {'Les utilisateurs du groupe GeoTag peuvent géolocaliser dans tous les albums qu\'ils peuvent voir'|@translate}
            </label>
            
            <label style="display:block; margin-bottom:15px;">
                <input type="radio" name="mode" value="selective" {if $RIGHTS_MODE == 'selective'}checked{/if}>
                <strong>{'Sélectif par utilisateur'|@translate}</strong> - {'Configuration individuelle des albums autorisés pour chaque utilisateur'|@translate}
            </label>
            
            {if $RIGHTS_MODE == 'all' && count($GEOTAG_USERS) > 0}
            <p style="margin-left:25px; padding:10px; background-color:#e8f4f8; border-left:3px solid #4CAF50; font-size:13px;">
                ℹ️ <strong>{'Note'|@translate} :</strong> {'Les configurations utilisateurs sont conservées même en mode "Tous les albums". Elles seront automatiquement réappliquées si vous revenez en mode "Sélectif".'|@translate}
            </p>
            {/if}
            
            <p class="formButtons">
                <button type="button" id="btn_save_mode" class="buttonLike">{'Enregistrer le mode'|@translate}</button>
            </p>
        </form>
    </fieldset>
    
    <!-- Configuration par utilisateur -->
    <fieldset id="selective_config" style="{if $RIGHTS_MODE != 'selective'}display:none;{/if}">
        <legend>{'Configuration des utilisateurs du groupe GeoTag'|@translate}</legend>
        
        {if count($GEOTAG_USERS) == 0}
            <p style="font-style:italic; color:#999;">
                {'Aucun utilisateur dans le groupe GeoTag.'|@translate}
                <a href="admin.php?page=group_list">{'Gérer les groupes'|@translate}</a>
            </p>
        {else}
            <p style="margin-bottom:20px; color:#666;">
                {count($GEOTAG_USERS)} {'utilisateur(s) dans le groupe GeoTag. Sélectionnez jusqu\'à 5 albums par utilisateur (les sous-albums sont automatiquement inclus).'|@translate}
            </p>
            
            <div id="users_config_container">
                <!-- Les configurations utilisateurs seront ajoutées ici par JavaScript -->
            </div>
            
            <p class="formButtons" style="margin-top:20px;">
                <button type="button" id="btn_save_users_config" class="buttonLike">{'Enregistrer les permissions'|@translate}</button>
            </p>
        {/if}
    </fieldset>
</div>

<style>

#manage_rights_tab fieldset,
#manage_rights_tab form,
#manage_rights_tab label,
#manage_rights_tab p {
    text-align: left;
}

#manage_rights_tab .formButtons {
    text-align: left;
}    

#manage_rights_tab .formButtons {
    text-align: left;
}

#manage_rights_tab fieldset > p.formButtons,
#manage_rights_tab fieldset > form > p.formButtons {
    text-align: left;
}

#manage_rights_tab fieldset {
    margin-bottom: 20px;
    padding: 15px;
}

#manage_rights_tab legend {
    font-weight: bold;
    padding: 0 10px;
}

.user_config_block {
    border: 1px solid #ddd;
    padding: 15px;
    margin-bottom: 15px;
    background-color: #f9f9f9;
    border-radius: 4px;
}

.user_config_block h4 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #333;
    text-align: left !important;
}

.album_selector {
    margin-bottom: 10px;
}

.album_search {
    min-width: 300px;
    padding: 6px 10px;
    border: 1px solid #ccc;
    border-radius: 3px;
    font-size: 14px;
    margin-bottom: 5px;
    display: block;
}

.album_select {
    min-width: 300px;
    margin-right: 10px;
}

.selected_albums {
    margin-top: 15px;
}

.selected_albums ul {
    list-style: none;
    padding: 0;
    margin: 10px 0;
}

.selected_albums li {
    padding: 8px 12px;
    background-color: #e8f4f8;
    border-left: 3px solid #4CAF50;
    margin-bottom: 5px;
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    align-items: center;
}

.selected_albums .remove_album {
    color: #d9534f;
    cursor: pointer;
    font-weight: bold;
    padding: 2px 8px;
    border: 1px solid #d9534f;
    border-radius: 3px;
    background-color: white;
    margin-right: 15px;
    flex-shrink: 0;
}

.selected_albums .remove_album:hover {
    background-color: #d9534f;
    color: white;
}

.album_count {
    font-size: 12px;
    color: #666;
    margin-left: 10px;
}

.album_count.limit_reached {
    color: #d9534f;
    font-weight: bold;
}
</style>

<script type="text/javascript">
// Passer les données PHP au JavaScript
var geotagUsers = {json_encode($GEOTAG_USERS)};
var usersConfig = {$USERS_CONFIG};
</script>
