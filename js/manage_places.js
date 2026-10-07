/**
 * Gestion des lieux personnels - Page d'administration
 * Fichier : js/manage_places.js
 */

(function() {
    'use strict';
    
    let map = null;
    let marker = null;
    let places = [];
    let selectedPlaceId = null;
    let editingPlaceId = null;
    let L = null; // Référence globale à Leaflet
    
    // Initialisation au chargement du DOM
    document.addEventListener('DOMContentLoaded', function() {
        initEventListeners();
        initMap();
        
        // Charger les lieux si activé
        const enabledCheckbox = document.getElementById('places-enabled');
        if (enabledCheckbox && enabledCheckbox.checked) {
            loadPlaces();
        }
    });
    
    /**
     * Initialiser les écouteurs d'événements
     */
    function initEventListeners() {
        // Bouton d'enregistrement de l'activation
        const saveBtn = document.getElementById('btn-save-places-enabled');
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                const enabled = document.getElementById('places-enabled').checked;
                savePlacesEnabled(enabled);
            });
        }
        
        // Bouton d'ajout
        const addBtn = document.getElementById('add-place-btn');
        if (addBtn) {
            addBtn.addEventListener('click', addPlace);
        }
        
        // Champ de saisie du nom
        const nameInput = document.getElementById('place-name-input');
        if (nameInput) {
            // Enter pour ajouter
            nameInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    addPlace();
                }
            });
            
            // Filtrage en temps réel pendant la saisie
            nameInput.addEventListener('input', function(e) {
                filterPlacesList(e.target.value);
            });
        }
        
        // Import OSM
        const importBtn = document.getElementById('import-osm-btn');
        if (importBtn) {
            importBtn.addEventListener('click', importFromOSM);
        }
    }
    
    /**
     * Sauvegarder l'activation/désactivation
     */
    function savePlacesEnabled(enabled) {
        const btn = document.getElementById('btn-save-places-enabled');
        
        btn.disabled = true;
        btn.textContent = 'Enregistrement...';
        
        // Envoyer la requête
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=toggle_enabled&enabled=' + enabled
        })
        .then(response => response.text())
        .then(html => {
            // Recharger la page pour afficher le message de confirmation
            window.location.reload();
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors de la sauvegarde');
            btn.disabled = false;
            btn.textContent = 'Enregistrer';
        });
    }
    
    /**
     * Initialiser la carte Leaflet
     */
    function initMap() {
        const mapEl = document.getElementById('places-map');
        if (!mapEl) {
            //console.log('Element places-map non trouvé');
            return;
        }
        
        // Attendre que GeoTagLeaflet soit disponible
        setTimeout(function() {
            // Utiliser GeoTagLeaflet isolé (comme dans l'éditeur)
            L = window.GeoTagLeaflet || window.L;
            
            if (!L) {
                console.error('Leaflet n\'est pas chargé');
                mapEl.innerHTML = '<p style="padding:20px;text-align:center;color:red;">Erreur: Leaflet n\'est pas disponible</p>';
                return;
            }
            
            //console.log('Initialisation de la carte avec Leaflet', L.version);
            
            // Créer la carte centrée sur la France
            map = L.map('places-map').setView([46.603354, 1.888334], 6);
            
            // Fond de carte (noms en caractères latins + locaux, repli OSM raster)
            window.GeoTagBasemap.add(L, map);
            
            // Clic sur la carte pour définir les coordonnées
            map.on('click', function(e) {
                setMapPosition(e.latlng.lat, e.latlng.lng);
            });
            
            //console.log('Carte initialisée avec succès');
        }, 100);
    }
    
    /**
     * Charger la liste des lieux
     */
    function loadPlaces() {
        const listDiv = document.getElementById('places-list');
        if (!listDiv) return;
        
        listDiv.innerHTML = '<p class="loading">Chargement...</p>';
        
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_places'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                places = data.places;
                renderPlacesList();
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            listDiv.innerHTML = '<p class="error">Erreur lors du chargement</p>';
        });
    }
    
    /**
     * Filtrer la liste des lieux selon la recherche
     */
    function filterPlacesList(search) {
        const listDiv = document.getElementById('places-list');
        if (!listDiv) return;
        
        const searchLower = search.toLowerCase().trim();
        
        // Si la recherche est vide, afficher tous les lieux
        if (!searchLower) {
            renderPlacesList();
            return;
        }
        
        // Filtrer les lieux
        const filtered = places.filter(place => 
            place.name.toLowerCase().includes(searchLower)
        );
        
        // Afficher les résultats filtrés
        if (filtered.length === 0) {
            listDiv.innerHTML = '<p class="empty">Aucun lieu trouvé pour "' + escapeHtml(search) + '"</p>';
            return;
        }
        
        let html = '';
        filtered.forEach(place => {
            const isSelected = place.id == selectedPlaceId;
            const isEditing = place.id == editingPlaceId;
            
            html += '<div class="place-item' + 
                    (isSelected ? ' selected' : '') + 
                    (isEditing ? ' editing' : '') + 
                    '" data-id="' + place.id + '">';
            
            if (isEditing) {
                // Mode édition
                html += '<div class="place-info">';
                html += '<input type="text" class="edit-name-input" value="' + escapeHtmlAttribute(place.name) + '">';
                html += '<div class="place-coords">Lat: ' + place.latitude + ', Lng: ' + place.longitude + '</div>';
                html += '</div>';
                html += '<div class="place-actions">';
                html += '<button class="save-btn" data-place-id="' + place.id + '">💾</button>';
                html += '<button class="cancel-btn">✕</button>';
                html += '</div>';
            } else {
                // Mode normal
                html += '<div class="place-info" data-place-id="' + place.id + '">';
                html += '<div class="place-name">' + escapeHtml(place.name) + '</div>';
                html += '<div class="place-coords">Lat: ' + place.latitude + ', Lng: ' + place.longitude + '</div>';
                html += '</div>';
                html += '<div class="place-actions">';
                html += '<button class="edit-btn" data-place-id="' + place.id + '">✏️</button>';
                html += '<button class="delete-btn" data-place-id="' + place.id + '">🗑️</button>';
                html += '</div>';
            }
            
            html += '</div>';
        });
        
        listDiv.innerHTML = html;
        
        // Attacher les event listeners
        attachPlaceEventListeners();
    }
    
    /**
     * Afficher la liste des lieux
     */
    function renderPlacesList() {
        const listDiv = document.getElementById('places-list');
        if (!listDiv) return;
        
        if (places.length === 0) {
            listDiv.innerHTML = '<p class="empty">Aucun lieu enregistré</p>';
            return;
        }
        
        let html = '';
        places.forEach(place => {
            const isSelected = place.id == selectedPlaceId;
            const isEditing = place.id == editingPlaceId;
            
            html += '<div class="place-item' + 
                    (isSelected ? ' selected' : '') + 
                    (isEditing ? ' editing' : '') + 
                    '" data-id="' + place.id + '">';
            
            if (isEditing) {
                // Mode édition
html += '<div class="place-info">';
html += '<input type="text" class="edit-name-input" value="' + escapeHtmlAttribute(place.name) + '">';
html += '<div class="place-coords">Lat: ' + place.latitude + ', Lng: ' + place.longitude + '</div>';
html += '</div>';
                html += '<div class="place-actions">';
                html += '<button class="save-btn" data-place-id="' + place.id + '">💾</button>';
                html += '<button class="cancel-btn">✕</button>';
                html += '</div>';
            } else {
                // Mode normal
                html += '<div class="place-info" data-place-id="' + place.id + '">';
                html += '<div class="place-name">' + escapeHtml(place.name) + '</div>';
                html += '<div class="place-coords">Lat: ' + place.latitude + ', Lng: ' + place.longitude + '</div>';
                html += '</div>';
                html += '<div class="place-actions">';
                html += '<button class="edit-btn" data-place-id="' + place.id + '">✏️</button>';
                html += '<button class="delete-btn" data-place-id="' + place.id + '">🗑️</button>';
                html += '</div>';
            }
            
            html += '</div>';
        });
        
        listDiv.innerHTML = html;
        
        // Attacher les event listeners
        attachPlaceEventListeners();
    }
    
    /**
     * Attacher les event listeners aux éléments de la liste
     */
    function attachPlaceEventListeners() {
        // Clic sur un lieu pour le sélectionner
        document.querySelectorAll('.place-info[data-place-id]').forEach(el => {
            el.addEventListener('click', function() {
                selectPlace(parseInt(this.dataset.placeId));
            });
        });
        
        // Boutons d'édition
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                editPlace(parseInt(this.dataset.placeId));
            });
        });
        
        // Boutons de suppression
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                deletePlace(parseInt(this.dataset.placeId));
            });
        });
        
        // Boutons de sauvegarde
        document.querySelectorAll('.save-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                savePlaceEdit(parseInt(this.dataset.placeId));
            });
        });
        
        // Boutons d'annulation
        document.querySelectorAll('.cancel-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                cancelPlaceEdit();
            });
        });
    }
    
    /**
     * Sélectionner un lieu et l'afficher sur la carte
     */
    function selectPlace(id) {
        if (editingPlaceId) return;
        
        selectedPlaceId = id;
        const place = places.find(p => p.id == id);
        
        if (place) {
            setMapPosition(place.latitude, place.longitude);
            renderPlacesList();
        }
    }
    
    /**
     * Définir la position sur la carte
     */
    function setMapPosition(lat, lng) {
        if (!map || !L) return;
        
        // Supprimer le marqueur existant
        if (marker) {
            map.removeLayer(marker);
        }
        
        // Créer un nouveau marqueur
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        
        // Centrer la carte
        map.setView([lat, lng], 13);
        
        // Permettre de déplacer le marqueur
        marker.on('dragend', function(e) {
            const pos = e.target.getLatLng();
            if (editingPlaceId) {
                const place = places.find(p => p.id == editingPlaceId);
                if (place) {
                    place.latitude = pos.lat.toFixed(6);
                    place.longitude = pos.lng.toFixed(6);
                    renderPlacesList();
                }
            }
        });
    }
    
    /**
     * Ajouter un nouveau lieu
     */
    function addPlace() {
        const nameInput = document.getElementById('place-name-input');
        if (!nameInput) return;
        
        const name = nameInput.value.trim();
        
        if (!name) {
            alert('Veuillez saisir un nom');
            return;
        }
        
        if (!marker) {
            alert('Veuillez cliquer sur la carte pour définir la position');
            return;
        }
        
        const pos = marker.getLatLng();
        
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=add_place&name=' + encodeURIComponent(name) + 
                  '&latitude=' + pos.lat + '&longitude=' + pos.lng
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                nameInput.value = '';
                loadPlaces();
            } else {
                alert('Erreur : ' + data.error);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors de l\'ajout du lieu');
        });
    }
    
    /**
     * Éditer un lieu
     */
    function editPlace(id) {
        editingPlaceId = id;
        const place = places.find(p => p.id == id);
        
        if (place) {
            setMapPosition(place.latitude, place.longitude);
            renderPlacesList();
            
            setTimeout(() => {
                const input = document.querySelector('.edit-name-input');
                if (input) input.focus();
            }, 100);
        }
    }
    
    /**
     * Sauvegarder l'édition d'un lieu
     */
    function savePlaceEdit(id) {
        const place = places.find(p => p.id == id);
        const nameInput = document.querySelector('.edit-name-input');
        if (!nameInput) return;
        
        const name = nameInput.value.trim();
        
        if (!name) {
            alert('Veuillez saisir un nom');
            return;
        }
        
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=update_place&id=' + id + 
                  '&name=' + encodeURIComponent(name) + 
                  '&latitude=' + place.latitude + 
                  '&longitude=' + place.longitude
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                editingPlaceId = null;
                loadPlaces();
            } else {
                alert('Erreur : ' + data.error);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors de la mise à jour');
        });
    }
    
    /**
     * Annuler l'édition
     */
    function cancelPlaceEdit() {
        editingPlaceId = null;
        loadPlaces();
    }
    
    /**
     * Supprimer un lieu
     */
    function deletePlace(id) {
        const place = places.find(p => p.id == id);
        if (!place) return;
        
        if (!confirm('Supprimer le lieu "' + place.name + '" ?')) {
            return;
        }
        
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete_place&id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (selectedPlaceId == id) {
                    selectedPlaceId = null;
                    if (marker) {
                        map.removeLayer(marker);
                        marker = null;
                    }
                }
                loadPlaces();
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors de la suppression');
        });
    }
    
    /**
     * Importer depuis OSM
     */
    function importFromOSM() {
        const modeSelect = document.getElementById('import-conflict-mode');
        if (!modeSelect) return;
        
        const mode = modeSelect.value;
        const btn = document.getElementById('import-osm-btn');
        const resultDiv = document.getElementById('import-result');
        
        if (!btn || !resultDiv) return;
        
        btn.disabled = true;
        btn.textContent = 'Import en cours...';
        resultDiv.style.display = 'none';
        
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=import_from_osm&conflict_mode=' + mode
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const stats = data.stats;
                let html = '<strong>Import terminé :</strong><br>';
                html += 'Total: ' + stats.total + ' lieu(x)<br>';
                html += 'Importés: ' + stats.imported + '<br>';
                html += 'Ignorés: ' + stats.skipped + '<br>';
                html += 'Écrasés: ' + stats.overwritten + '<br>';
                html += 'Doublons: ' + stats.duplicated;
                
                resultDiv.innerHTML = html;
                resultDiv.className = 'success';
                resultDiv.style.display = 'block';
                
                loadPlaces();
            } else {
                resultDiv.innerHTML = '<strong>Erreur lors de l\'import</strong>';
                resultDiv.className = 'error';
                resultDiv.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            resultDiv.innerHTML = '<strong>Erreur lors de l\'import</strong>';
            resultDiv.className = 'error';
            resultDiv.style.display = 'block';
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Importer les lieux OSM';
        });
    }
    
   

/**
 * Échapper le HTML pour les attributs (garde les caractères lisibles dans les inputs)
 */
function escapeHtmlAttribute(text) {
    const map = {
        '"': '&quot;',
        '<': '&lt;',
        '>': '&gt;'
    };
    return text.replace(/["<>]/g, m => map[m]);
}

/**
 * Échapper le HTML pour l'affichage
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}





})();