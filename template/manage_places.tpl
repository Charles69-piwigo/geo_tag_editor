<div id="geotag-places-admin" class="geotag-places-admin-container">
    <h2>{'Gestion des lieux personnels'|translate}</h2>
    
    <!-- Activation / Désactivation -->
    <fieldset class="places-activation">
        <legend>{'Activation'|translate}</legend>
        <label>
            <input type="checkbox" id="places-enabled" {if $PLACES_ENABLED}checked{/if}>
            {'Activer la liste des lieux personnels'|translate}
        </label>
        <p class="description">
            {'Permet de créer une liste de lieux fréquemment utilisés pour géolocaliser rapidement vos photos.'|translate}
        
        <button type="button" id="btn-save-places-enabled" class="buttonLike">{'Enregistrer'|translate}</button>
</p>
    </fieldset>
    
    <div id="places-config" {if !$PLACES_ENABLED}style="display:none;"{/if}>
        
        <!-- Gestion des lieux -->
        <div class="places-management">
            <div class="places-list-panel">
                <h3>{'Liste des lieux'|translate}</h3>
                
                <!-- Formulaire d'ajout -->
                <div class="place-add-form">
                    <input type="text" 
                           id="place-name-input" 
                           placeholder="{'Nom du lieu'|translate}"
                           maxlength="255">
                    <button type="button" id="add-place-btn" class="button" title="{'Ajouter'|translate}">
                        +
                    </button>
                </div>
                
                <!-- Liste des lieux -->
                <div class="places-list" id="places-list">
                    <p class="loading">{'Chargement...'|translate}</p>
                </div>
            </div>
            
            <div class="places-map-panel">
                <h3>{'Carte de localisation'|translate}</h3>
                <div id="places-map" class="places-map"></div>
                <p class="map-help">
                    {'Cliquez sur un lieu de la liste pour le visualiser, ou cliquez sur la carte pour définir les coordonnées.'|translate}
                </p>
            </div>
        </div>
        
        <!-- Import depuis OSM -->
        {if $HAS_OSM_PLUGIN}
        <fieldset class="places-import">
            <legend>{'Import depuis piwigo_openstreetmap'|translate}</legend>
            <p>{'Le plugin piwigo_openstreetmap est installé. Vous pouvez importer ses lieux.'|translate}</p>
            
            <div class="import-options">
                <label>
                    {'En cas de conflit (nom existant) :'|translate}
                    <select id="import-conflict-mode">
                        <option value="skip">{'Ignorer (ne pas importer)'|translate}</option>
                        <option value="overwrite">{'Écraser (remplacer coordonnées)'|translate}</option>
                        <option value="duplicate">{'Créer un doublon (ajouter suffixe)'|translate}</option>
                    </select>
                </label>
                <button type="button" id="import-osm-btn" class="button">
                    {'Importer les lieux OSM'|translate}
                </button>
            </div>
            
            <div id="import-result" style="display:none;"></div>
        </fieldset>
        {/if}
    </div>
</div>

<style>


/* Styles spécifiques à la page de gestion des lieux */



#geotag-places-admin .geotag-places-admin-container {
    padding: 20px;
}

#geotag-places-admin fieldset {
    margin-bottom: 20px;
    padding: 15px;
    border: 1px solid #ccc;
    border-radius: 4px;
    text-align: left;
}

#geotag-places-admin fieldset legend {
    font-weight: bold;
    padding: 0 10px;
}

#geotag-places-admin .description {
    color: #666;
    font-style: italic;
    margin-top: 5px;
    margin-bottom: 10px;
}

#geotag-places-admin .import-options {
    margin-top: 10px;
}

#geotag-places-admin .import-options label {
    display: block;
    margin-bottom: 10px;
}

#geotag-places-admin .import-options select {
    margin-left: 10px;
    padding: 5px;
}

#geotag-places-admin #import-result {
    margin-top: 15px;
    padding: 10px;
    background: #f0f0f0;
    border-radius: 4px;
}

#geotag-places-admin #import-result.success {
    background: #d4edda;
    color: #155724;
}

#geotag-places-admin #import-result.error {
    background: #f8d7da;
    color: #721c24;
}

#geotag-places-admin .places-management {
    display: grid;
    grid-template-columns: 500px 1fr;
    gap: 20px;
    margin-top: 20px;
}

#geotag-places-admin .places-list-panel,
#geotag-places-admin .places-map-panel {
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 15px;
}

#geotag-places-admin .places-list-panel h3,
#geotag-places-admin .places-map-panel h3 {
    margin-top: 0;
    margin-bottom: 15px;
    border-bottom: 2px solid #007bff;
    padding-bottom: 5px;
}

#geotag-places-admin .place-add-form {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

#geotag-places-admin .place-add-form input {
    flex: 1;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

#geotag-places-admin .place-add-form button {
    width: 40px;
    height: 40px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

#geotag-places-admin .place-add-form button:hover {
    background: #0056b3;
}

#geotag-places-admin .places-list {
    max-height: 660px; /* hauteur de la liste */
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fafafa;
}

#geotag-places-admin .places-list .loading,
#geotag-places-admin .places-list .empty {
    padding: 20px;
    text-align: center;
    color: #666;
    font-style: italic;
}

#geotag-places-admin .place-item {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    cursor: pointer;
    transition: background-color 0.2s;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

#geotag-places-admin .place-item:hover {
    background-color: #e9ecef;
}

#geotag-places-admin .place-item.selected {
    background-color: #cfe2ff;
    border-left: 3px solid #007bff;
}

#geotag-places-admin .place-item.editing {
    background-color: #fff3cd;
}

#geotag-places-admin .place-info {
    flex: 1;
}

#geotag-places-admin .place-name {
    font-weight: bold;
    margin-bottom: 3px;
}

#geotag-places-admin .place-coords {
    font-size: 11px;
    color: #666;
}

#geotag-places-admin .place-actions {
    display: flex;
    gap: 5px;
}

#geotag-places-admin .place-actions button {
    padding: 5px 10px;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
}

#geotag-places-admin .place-actions .edit-btn {
    background: #ffc107;
    color: #000;
}

#geotag-places-admin .place-actions .edit-btn:hover {
    background: #e0a800;
}

#geotag-places-admin .place-actions .delete-btn {
    background: #dc3545;
    color: white;
}

#geotag-places-admin .place-actions .delete-btn:hover {
    background: #c82333;
}

#geotag-places-admin .place-actions .save-btn {
    background: #28a745;
    color: white;
}

#geotag-places-admin .place-actions .save-btn:hover {
    background: #218838;
}

#geotag-places-admin .place-actions .cancel-btn {
    background: #6c757d;
    color: white;
}

#geotag-places-admin .place-actions .cancel-btn:hover {
    background: #5a6268;
}

#geotag-places-admin .place-item.editing input {
    padding: 5px;
    border: 1px solid #ccc;
    border-radius: 3px;
    width: 100%;
}

#geotag-places-admin .places-map {
    width: 100%;
    height: 680px;    /* hauteur de la carte */
    border-radius: 4px;
    border: 1px solid #ddd;
}

#geotag-places-admin .map-help {
    margin-top: 10px;
    font-size: 12px;
    color: #666;
    font-style: italic;
}

/* Responsive */
@media (max-width: 1200px) {
    #geotag-places-admin .places-management {
        grid-template-columns: 1fr;
    }
}
</style>

<script src="{$GEOTAG_PATH}js/manage_places.js"></script>
