// geo_tag_editor - Interface modale de géolocalisation avec OpenStreetMap
// Leaflet 1.9.4 isolé via noConflict() au chargement de la page

(function($) {
  'use strict';
  
  $(document).ready(function() {
    
    console.log('Geo Tag Editor: Script chargé');


//--------------------------------------------------  

    
    var map = null;
    var marker = null;
    var imageId = null;
    var imageSrc = null;
    var imageFile = null; 
    var saveUrl = null;
    var currentLatitude = null;
    var currentLongitude = null;
    var currentAltitude = null;
    var currentZoom = null;  // cvn
    var hasGPS = false;
    var copiedPosition = null;
    var originalLatitude = null;
    var originalLongitude = null;
    var originalAltitude = null;
    var gpsSource = 'exif';
    var isJpeg = true;            // Format JPEG (EXIF supporté) ou non
    var currentDescription = '';  // Description de l'image (IPTC Caption-Abstract)
    var initialDescription = '';  // Description initiale pour détecter l'effacement

    // Variables pour les lieux personnels
    var allPlaces = [];           // Liste complète des lieux
    var selectedPlace = null;     // Lieu actuellement sélectionné
    var placesEnabled = false;    // Fonctionnalité activée ?
    
    // Notre référence à Leaflet isolé (définie dans main.inc.php via noConflict)
    var GeoTagLeaflet = window.GeoTagLeaflet;
    
    // Vérifier que Leaflet est bien chargé et isolé
    if (!GeoTagLeaflet) {
      console.error('Geo Tag Editor: GeoTagLeaflet non disponible !');
      
      // Cacher le bouton si Leaflet n'est pas chargé
      $('#geotag-open-editor').hide();
      
      return;
    }
    
    //console.log('Geo Tag Editor: Leaflet ' + GeoTagLeaflet.version + ' prêt (isolé)');
    
    // Vérifier si window.L existe toujours (piwigo_openstreetmap)
    if (typeof window.L !== 'undefined') {
      //console.log('Geo Tag Editor: Leaflet existant détecté (v' + (window.L.version || 'inconnue') + ') - préservé');
    }
    
    try {
       var stored = localStorage.getItem('geotag_copied_position');
       if (stored) {
           copiedPosition = JSON.parse(stored);
       }
    } catch(e) {
      // Ignore localStorage errors
    }

// ================= fonction de Traduction ============================================    

    function _(trad) {
      return (typeof geotagLang !== 'undefined' && geotagLang[trad]) ? geotagLang[trad] : trad;
    }

// ==================== CHARGEMENT LAZY DES TRADUCTIONS ====================
let geotagLang = null;
let translationsPromise = null;

async function loadGeotagTranslations() {
  if (geotagLang) {
    return geotagLang; // Déjà chargées
  }
  
  if (!translationsPromise) {
    translationsPromise = fetch('ws.php?format=json&method=geotag.getTranslations')
      .then(response => response.json())
      .then(data => {
        if (data.stat === 'ok' && data.result) {
          geotagLang = data.result;
          return geotagLang;
        } else {
          throw new Error('Erreur chargement traductions');
        }
      })
      .catch(error => {
        console.error('Erreur chargement traductions:', error);
        // geotagLang reste null, la fonction _() retournera le texte par défaut
        return null;
      });
  }
  
  return translationsPromise;
}


    // ==================== GESTIONNAIRE DU BOUTON ====================
$(document).on('click', '#geotag-open-editor', async function(e) {
  e.preventDefault();
  
  // Désactiver le bouton pendant le chargement
  var $btn = $(this);
  var originalText = $btn.text();
  $btn.prop('disabled', true).text('Chargement...');
  
  var dataAttr = $btn.data('geotag');
  if (!dataAttr) {
    alert('Erreur: Données manquantes');
    $btn.prop('disabled', false).text(originalText);
    return;
  }
  
  var data;
  try {
    data = typeof dataAttr === 'string' ? JSON.parse(dataAttr) : dataAttr;
  } catch (err) {
    console.error('Erreur parsing JSON:', err);
    alert('Erreur: Données invalides');
    $btn.prop('disabled', false).text(originalText);
    return;
  }
  
  imageId = data.image_id;
  imageSrc = data.image_src;
  imageFile = data.image_file || 'Image #' + data.image_id;
  saveUrl = data.save_url;
  hasGPS = data.has_gps;
  gpsSource = data.gps_source || 'exif';
  isJpeg = data.is_jpeg !== undefined ? data.is_jpeg : true;

  currentLatitude = data.latitude ? parseFloat(data.latitude) : null;
  currentLongitude = data.longitude ? parseFloat(data.longitude) : null;
  currentAltitude = data.altitude ? parseFloat(data.altitude) : null;
  originalLatitude = data.latitude ? parseFloat(data.latitude) : null;
  originalLongitude = data.longitude ? parseFloat(data.longitude) : null;
  originalAltitude = data.altitude ? parseFloat(data.altitude) : null;
  currentDescription = data.description || '';  // Charger la description existante
  initialDescription = currentDescription;  // Mémoriser pour détecter l'effacement
  
  // ✅ CHARGER LES TRADUCTIONS AVANT D'OUVRIR LA MODALE
  try {
    await loadGeotagTranslations();
    // Restaurer le bouton
    $btn.prop('disabled', false).text(originalText);
    // Ouvrir la modale avec les traductions disponibles
    openModal();
  } catch (error) {
    console.error('Erreur lors du chargement:', error);
    $btn.prop('disabled', false).text(originalText);
    alert('Erreur lors du chargement de l\'éditeur');
  }
});
    
    // ==================== CRÉER LA MODALE ====================
    function openModal() {
      // Supprimer les modales existantes
      $('#geotag-modal, #geotag-modal-overlay').remove();
      
     var modalHtml = `
  <div id="geotag-modal-overlay"></div>
  
  <div id="geotag-modal">
    
    <!-- Header -->
    <div class="modal-header">
      <h3>📍 ${_('Éditeur de géolocalisation')} (#${imageId}) - ${imageFile}</h3>
      <button id="geotag-close-modal">&times;</button>
    </div>
    
    <!-- Instructions -->
    <div class="modal-instructions">
      <p><strong>${_('Instructions :')}</strong> ${_('Cliquez sur la carte pour placer le marqueur de position. Vous pouvez aussi déplacer le marqueur ou utiliser la recherche.')}</p>
    </div>
    
    <!-- Contenu principal -->
    <div class="modal-content">
      
      <!-- Zone image (gauche) -->
      <div class="modal-image-area">
        <img src="${imageSrc}" alt="Image" />

        <!-- Description de l'image (sous la photo) -->
        <div class="description-section">
          <label for="geotag-description">${_('Description')} :</label>
          <textarea id="geotag-description" rows="3" placeholder="${_('Description de l\'image...')}"></textarea>
        </div>
      </div>
      
      <!-- Zone carte (droite) -->
      <div class="modal-map-area">
        
        <!-- Barre de recherche -->
        <div class="map-search-bar">
          <div class="search-container">
            <input type="text" id="geotag-search-input" placeholder="${_('Rechercher...')}" />
            <button id="geotag-search-btn">🔍 ${_('Rechercher un lieu')}</button>
          </div>
          <div id="geotag-search-results"></div>
        </div>
        
        <!-- Carte OpenStreetMap -->
        <div id="geotag-map"></div>
        
      </div>
      
      <!-- Sidebar : info GPS -->
<div class="modal-sidebar">
  <h4>📍 ${_('Position GPS')}</h4>
  
  <!-- Affichage coordonnées -->
  <div id="geotag-gps-info">
    <div class="gps-no-data">${_('Aucune position GPS')}</div>
  </div>

  <!-- Saisir/Coller coordonnées -->
  <div class="coords-input-section">
    <label for="geotag-coords-input">${_('Saisir/Coller coordonnées')} :</label>
    <input type="text" id="geotag-coords-input" placeholder="45.433214, 12.339914" />
    <button id="geotag-apply-coords">✓ ${_('Appliquer')}</button>
  </div>
  
  <!-- Actions sur positions -->
  <div class="sidebar-actions">
    <button id="geotag-copy-position" disabled>📋 ${_('Copier la position')}</button>
    <button id="geotag-paste-position" disabled>📌 ${_('Coller la position')}</button>
  </div>


  <!-- Lieux personnels -->
  <div class="sidebar-places" id="geotag-places-block" style="display:none;">
    <h3>${_('Lieux personnels')}</h3>
    <div class="places-search">
      <input type="text" 
             id="geotag-place-search" 
             placeholder="${_('Rechercher un lieu...')}"
             autocomplete="off" />
      <div id="geotag-places-dropdown" class="places-dropdown" style="display:none;"></div>
    </div>
    <div class="places-actions">
      <button id="geotag-apply-place" class="btn-apply-place" disabled>✓ ${_('Appliquer')}</button>
      <button id="geotag-add-place" class="btn-add-place" disabled>➕ ${_('Ajouter')}</button>
    </div>
  </div>
</div>
<!-- FIN SIDEBAR -->

</div>
<!-- FIN modal-content -->

<!-- Footer : boutons -->
<div class="modal-footer">
  
  <div class="modal-footer-left">
    <button id="geotag-copy-coords" disabled>📋 ${_('Copier coordonnées')}</button>
    <button id="geotag-google-lens">🔍 ${_('Google Lens')}</button>
    <button id="geotag-reset-position" ${!hasGPS ? 'disabled' : ''}>↺ ${_('Réinitialiser')}</button>
    <button id="geotag-remove-gps" ${!hasGPS ? 'disabled' : ''}>🗑️ ${_('Supprimer GPS')}</button>
  </div>
  
  <div class="modal-footer-right">
    <button id="geotag-cancel">${_('Annuler')}</button>
    <button id="geotag-save-gps">💾 ${_('Enregistrer ')}</button>
  </div>

</div>
    
  </div>
`;
      
$('body').append(modalHtml);

// Afficher un message d'erreur si l'image originale est inaccessible (ex: 403)
$('.modal-image-area img').on('error', function() {
  $(this).replaceWith(
    '<div style="padding:16px;color:#c00;background:#fee;border:1px solid #f99;border-radius:4px;font-size:13px;text-align:center;">' +
    '⚠️ Image inaccessible<br><small>' + imageSrc + '</small></div>'
  );
});

// Charger la description existante dans le textarea
$('#geotag-description').val(currentDescription);

// Mettre à jour le bouton Enregistrer quand la description change
$('#geotag-description').on('input', function() {
  updateSaveButton();
});

// Initialiser la carte
initMap();

// Si des coordonnées existent (EXIF ou BDD), placer le marqueur
if (currentLatitude && currentLongitude) {
  setTimeout(function() {
    placeMarker(currentLatitude, currentLongitude);
  }, 300); // Petit délai pour que la carte soit bien chargée
}

// Mettre à jour l'affichage des coordonnées
updateGPSInfo();

// Événements de fermeture
$('#geotag-close-modal, #geotag-cancel').click(closeModal);

// Événement de sauvegarde
$('#geotag-save-gps').click(saveGPS);

// Événement de suppression
$('#geotag-remove-gps').click(removeGPS);

// Événement de recherche
$('#geotag-search-btn').click(searchLocation);
$('#geotag-search-input').keypress(function(e) {
  if (e.which === 13) {
    searchLocation();
  }
});

// Événements copier/coller
$('#geotag-copy-position').click(copyPosition);
$('#geotag-paste-position').click(pastePosition);

// Événement Google Lens
$('#geotag-google-lens').click(function() {
    openGoogleLens(imageId);
});

// Événement réinitialiser
$('#geotag-reset-position').click(resetPosition);

// Événement appliquer coordonnées
$('#geotag-apply-coords').click(applyCoordinatesFromInput);

// Permettre d'appuyer sur Entrée dans le champ
$('#geotag-coords-input').keypress(function(e) {
  if (e.which === 13) {
    applyCoordinatesFromInput();
  }
});

// Vérifier s'il y a une position copiée
if (copiedPosition) {
  $('#geotag-paste-position').prop('disabled', false);
}

// Activer le bouton réinitialiser si position GPS originale
if (originalLatitude !== null && originalLongitude !== null) {
  $('#geotag-reset-position').prop('disabled', false);
}

// Événement copier coordonnées (bouton dans footer)
$('#geotag-copy-coords').click(copyCoordinatesToClipboard);

// Charger les lieux personnels
      loadPersonalPlaces();

        // Avertissements selon le format et la source des coordonnées
      if (!isJpeg) {
        // Format non-JPEG : pas d'écriture EXIF possible
        setTimeout(function() {
          showStatusMessage(
            '⚠️ ' + _('Format non-JPEG : les coordonnées seront enregistrées uniquement en base de données.'),
            'warning'
          );
        }, 1500);
      } else if (gpsSource === 'database') {
        // JPEG avec coordonnées en BDD mais pas dans EXIF
        setTimeout(function() {
          showStatusMessage(
            '⚠️ ' + _('Coordonnées trouvées en base de données mais pas dans la photo.') + ' ' +
            _('Cliquez sur Enregistrer pour les écrire dans les métadonnées EXIF.'),
            'warning'
          );
        }, 1500);
      }    
      
      //console.log('Geo Tag Editor: Modale ouverte');
    }

// ==================== INITIALISER LA CARTE OPENSTREETMAP ====================
function initMap() {
  // IMPORTANT : Utiliser GeoTagLeaflet (notre version isolée)
  var L = GeoTagLeaflet;
  
  // Position par défaut Lyon
  var defaultLat = 45.7578;
  var defaultLon = 4.8320;
  var defaultZoom = 13;
  
  var centerLat, centerLon, centerZoom;
  
  // Si l'image a déjà des coordonnées GPS, les utiliser en priorité
  if (hasGPS && currentLatitude && currentLongitude) {
    centerLat = currentLatitude;
    centerLon = currentLongitude;
    centerZoom = currentZoom ?? defaultZoom; // cvn
    
    //console.log('Geo Tag Editor: Photo avec GPS existant');
  } else {
    // Photo sans GPS : chercher la dernière position utilisée
    //console.log('Geo Tag Editor: Photo sans coordonnées GPS');
    try {
      var lastPositionStr = localStorage.getItem('geotag_last_position');
      
      if (lastPositionStr) {
        var lastPosition = JSON.parse(lastPositionStr);
        var ageInHours = (Date.now() - lastPosition.timestamp) / (1000 * 60 * 60);
        
        // Expirer après 2h
        if (ageInHours < 2) {
          centerLat = lastPosition.lat;
          centerLon = lastPosition.lon;
          //centerZoom = 13;
          centerZoom = lastPosition.zoomMem ?? defaultZoom; // cvn
          //console.log('Geo Tag Editor: Utilisation dernière position (âge: ' + ageInHours.toFixed(1) + 'h)');
          console.log('Geo Tag Editor: dernier zoom ' + centerZoom);  // cvn
          console.log('Geo Tag Editor: zoomMem ' + lastPosition.zoomMem);  // cvn
        } else {
          // Position trop ancienne, supprimer
          localStorage.removeItem('geotag_last_position');
          centerLat = defaultLat;
          centerLon = defaultLon;
          centerZoom = defaultZoom;
          //console.log('Geo Tag Editor: Position expirée (âge: ' + ageInHours.toFixed(1) + 'h), retour au défaut');
        }
      } else {
        // Aucune position précédente : ville par défaut
        centerLat = defaultLat;
        centerLon = defaultLon;
        centerZoom = defaultZoom;
        //console.log('Geo Tag Editor: Aucune position précédente, utilisation position par défaut');
      }
    } catch(e) {
      // Erreur localStorage : utiliser valeur par défaut
      //console.error('Geo Tag Editor: Erreur lecture localStorage:', e);
      centerLat = defaultLat;
      centerLon = defaultLon;
      centerZoom = defaultZoom;
    }
  }
  
  // Créer la carte
  map = L.map('geotag-map').setView([centerLat, centerLon], centerZoom);
  
  // Ajouter les tuiles OpenStreetMap
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
  }).addTo(map);
  
  // Si l'image a déjà des coordonnées, placer le marqueur
  if (hasGPS && currentLatitude && currentLongitude) {
    placeMarker(currentLatitude, currentLongitude);
  }
  
  // Événement de clic sur la carte
  map.on('click', function(e) {
    placeMarker(e.latlng.lat, e.latlng.lng);
  });
}
    
// ==================== PLACER LE MARQUEUR ====================
function placeMarker(lat, lon) {
  // IMPORTANT : Utiliser GeoTagLeaflet
  var L = GeoTagLeaflet;
  
  // Supprimer le marqueur existant
  if (marker) {
    map.removeLayer(marker);
  }
  
  // Créer un nouveau marqueur
  marker = L.marker([lat, lon], {
    draggable: true
  }).addTo(map);
  
  // Mettre à jour les coordonnées
  currentLatitude = lat;
  currentLongitude = lon;
  currentZoom = map.getZoom() ; // cvn
  //console.log('Current Zoom :', currentZoom);

  
  // Événement de déplacement du marqueur
  marker.on('dragend', function(e) {
    var pos = marker.getLatLng();
    currentLatitude = pos.lat;
    currentLongitude = pos.lng;
    //currentZoom = map.getZoom() ; // cvn
    //console.log('Current Zoom :', currentZoom);
    
    updateGPSInfo();
  });

  // Evénement de zoom
  map.on('zoomend', function() {
  currentZoom = map.getZoom();
  //console.log('currentZoom = ',currentZoom);

});

  
  // Mettre à jour l'affichage
  updateGPSInfo();
  
  // Activer le bouton de copie
  $('#geotag-copy-position').prop('disabled', false);

  // Activer le bouton réinitialiser si position originale existe
  if (originalLatitude !== null && originalLongitude !== null) {
    $('#geotag-reset-position').prop('disabled', false);
  }
}
    
    // ==================== RECHERCHER UN LIEU ====================
    function searchLocation() {
      var query = $('#geotag-search-input').val().trim();
      
      if (!query) {
        alert(_('Veuillez entrer un lieu à rechercher'));
        return;
      }
      
      //console.log('Recherche:', query);
      
      // Afficher un message de chargement
      $('#geotag-search-results').html('<div class="geotag-search-result-item">' + _('Recherche...') + '</div>').addClass('show');
      
      // Utiliser Nominatim (service de géocodage d'OpenStreetMap)
      var url = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query);
      
      $.ajax({
        url: url,
        type: 'GET',
        dataType: 'json',
        success: function(results) {
          //console.log('Résultats recherche:', results);
          
          if (results && results.length > 0) {
            displaySearchResults(results);
          } else {
            $('#geotag-search-results').html('<div class="geotag-search-result-item">' + _('Aucun résultat trouvé') + '</div>');
          }
        },
        error: function(xhr, status, error) {
          console.error('Erreur recherche:', error);
          $('#geotag-search-results').html('<div class="geotag-search-result-item">Erreur de recherche</div>');
        }
      });
    }
    
    // ==================== AFFICHER LES RÉSULTATS DE RECHERCHE ====================
    function displaySearchResults(results) {
      var html = '';
      
      results.slice(0, 10).forEach(function(result) {
        html += '<div class="geotag-search-result-item" data-lat="' + result.lat + '" data-lon="' + result.lon + '">';
        html += '<div class="geotag-search-result-name">' + (result.name || result.display_name.split(',')[0]) + '</div>';
        html += '<div class="geotag-search-result-display">' + result.display_name + '</div>';
        html += '</div>';
      });
      
      $('#geotag-search-results').html(html).addClass('show');
      
      // Événement de clic sur un résultat
      $('.geotag-search-result-item').click(function() {
        var lat = parseFloat($(this).data('lat'));
        var lon = parseFloat($(this).data('lon'));
        
        // Placer le marqueur
        placeMarker(lat, lon);
        
        // Centrer la carte
        map.setView([lat, lon], 15);
        
        // Masquer les résultats
        $('#geotag-search-results').removeClass('show');
        $('#geotag-search-input').val('');
      });
    }
    
    // ==================== METTRE À JOUR L'AFFICHAGE GPS ====================
    // Activer/désactiver le bouton Enregistrer selon GPS ou description
    function updateSaveButton() {
      var hasGPS = (currentLatitude !== null && currentLongitude !== null);
      var hasDescription = ($('#geotag-description').val() || '').trim().length > 0;
      // Permettre l'enregistrement si la description a été effacée (non-vide → vide)
      var descriptionCleared = (initialDescription.trim().length > 0 && !hasDescription);
      $('#geotag-save-gps').prop('disabled', !hasGPS && !hasDescription && !descriptionCleared);
    }

    function updateGPSInfo() {
      if (currentLatitude !== null && currentLongitude !== null) {
        var html = '<div class="gps-info">';
        html += '<div class="gps-info-row">';
        html += '<span class="gps-info-label">' + _('Latitude') + ':</span>';
        html += '<span class="gps-info-value">' + currentLatitude.toFixed(6) + '°</span>';
        html += '</div>';
        html += '<div class="gps-info-row">';
        html += '<span class="gps-info-label">' + _('Longitude') + ':</span>';
        html += '<span class="gps-info-value">' + currentLongitude.toFixed(6) + '°</span>';
        html += '</div>';
        if (currentAltitude !== null) {
          html += '<div class="gps-info-row">';
          html += '<span class="gps-info-label">' + _('Altitude') + ':</span>';
          html += '<span class="gps-info-value">' + currentAltitude.toFixed(1) + ' ' + _('m') + '</span>';
          html += '</div>';
        }
        html += '</div>';

        $('#geotag-gps-info').html(html);

        // Activer le bouton "Copier coordonnées" dans le footer
        $('#geotag-copy-coords').prop('disabled', false);
      } else {
        $('#geotag-gps-info').html('<div class="gps-no-data">' + _('Aucune position GPS') + '</div>');

        // Désactiver le bouton "Copier coordonnées"
        $('#geotag-copy-coords').prop('disabled', true);
      }

        // Mettre à jour le bouton de sauvegarde et le bouton Ajouter des lieux
        updateSaveButton();
        updateAddPlaceButton();

    }
    
    // ==================== COPIER LA POSITION ====================
    function copyPosition() {
      if (currentLatitude !== null && currentLongitude !== null) {
        copiedPosition = {
          latitude: currentLatitude,
          longitude: currentLongitude,
          altitude: currentAltitude
        };
        
        // Sauvegarder dans localStorage
        try {
          localStorage.setItem('geotag_copied_position', JSON.stringify(copiedPosition));
        } catch(e) {
          console.error('Cannot save to localStorage:', e);
        }
        
        // Activer le bouton coller
        $('#geotag-paste-position').prop('disabled', false);
        
        // Afficher un message
        showStatusMessage(_('Position copiée !'), 'success');
        
        //console.log('Position copiée:', copiedPosition);
      }
    }
    
    // ==================== COLLER LA POSITION ====================
    function pastePosition() {
      //console.log('=== PASTE POSITION ===');
      //console.log('copiedPosition:', copiedPosition);

      if (copiedPosition) {
        placeMarker(copiedPosition.latitude, copiedPosition.longitude);
        
        if (copiedPosition.altitude !== null) {
          currentAltitude = copiedPosition.altitude;
        }
        
        // Centrer la carte
        map.setView([copiedPosition.latitude, copiedPosition.longitude], 15);
        
        // Afficher un message
        showStatusMessage(_('Position collée !'), 'success');
        
        //console.log('Position collée:', copiedPosition);
      } else {
        alert(_('Aucune position à coller'));
      }
    }

    // ==================== RÉINITIALISER LA POSITION ====================
    function resetPosition() {
      if (originalLatitude !== null && originalLongitude !== null) {
        // Restaurer la position originale
        placeMarker(originalLatitude, originalLongitude);
        
        if (originalAltitude !== null) {
          currentAltitude = originalAltitude;
        }
        
        // Centrer la carte
        map.setView([originalLatitude, originalLongitude], 15);
        
        // Afficher un message
        showStatusMessage(_('Position réinitialisée !'), 'info');
        /*
        console.log('Position réinitialisée:', {
          lat: originalLatitude,
          lon: originalLongitude,
          alt: originalAltitude
        });
        */
      } else {
        alert(_('Aucune position originale à restaurer'));
      }
    }

    // ==================== OUVRIR GOOGLE LENS ====================

function openGoogleLens(imgId) {
    showStatusMessage(
        '🔍 ' + _('Clic droit sur l\'image → "Rechercher une image avec Google Lens"'),
        'info',
        6000
    );
}

    // ==================== APPLIQUER COORDONNÉES DEPUIS LE CHAMP ====================
    function applyCoordinatesFromInput() {
      //console.log('=== APPLY COORDS CLICKED ===');
      var input = $('#geotag-coords-input').val().trim();
      //console.log('Input value:', input);
      
      if (!input) {
        alert(_('Veuillez entrer des coordonnées'));
        return;
      }
      
      // Nettoyer l'input (enlever espaces multiples, etc.)
      input = input.replace(/\s+/g, ' ');
      
      var coords;
      if (input.includes(',')) {
        coords = input.split(',');
      } else if (input.includes(' ')) {
        coords = input.split(' ');
      } else {
        alert(_('Format invalide. Utilisez: latitude, longitude. Exemple: 45.433214, 12.339914'));
        return;
      }
      
      if (coords.length !== 2) {
        alert(_('Format invalide. Utilisez: latitude, longitude. Exemple: 45.433214, 12.339914'));
        return;
      }
      
      var lat = parseFloat(coords[0].trim());
      var lon = parseFloat(coords[1].trim());
      
      // Valider les coordonnées
      if (isNaN(lat) || isNaN(lon)) {
        alert(_('Coordonnées invalides. Vérifiez les valeurs.'));
        return;
      }
      
      if (lat < -90 || lat > 90) {
        alert(_('Latitude invalide. Doit être entre -90 et 90.'));
        return;
      }
      
      if (lon < -180 || lon > 180) {
        alert(_('Longitude invalide. Doit être entre -180 et 180.'));
        return;
      }
      
      // Appliquer les coordonnées
      placeMarker(lat, lon);
      map.setView([lat, lon], 15);
      
      // Message de confirmation
      showStatusMessage(_('Coordonnées appliquées !'), 'success');
      
      //console.log('Coordonnées appliquées:', {lat: lat, lon: lon});
    }
    
    // ==================== COPIER COORDONNÉES DANS LE PRESSE-PAPIER ====================
    function copyCoordinatesToClipboard() {
      if (currentLatitude !== null && currentLongitude !== null) {
        // Format pour Google Maps: latitude, longitude
        var coords = currentLatitude.toFixed(6) + ', ' + currentLongitude.toFixed(6);
        
        // Copier dans le presse-papier
        if (navigator.clipboard && navigator.clipboard.writeText) {
          // Méthode moderne
          navigator.clipboard.writeText(coords).then(function() {
            showStatusMessage(_('Coordonnées copiées !') + ' (' + coords + ')', 'success');
            //console.log('Coordonnées copiées:', coords);
          }).catch(function(err) {
            console.error('Erreur copie presse-papier:', err);
            fallbackCopyToClipboard(coords);
          });
        } else {
          // Fallback pour navigateurs anciens
          fallbackCopyToClipboard(coords);
        }
      }
    }

    // Fallback pour copier dans le presse-papier (navigateurs anciens)
    function fallbackCopyToClipboard(text) {
      var textArea = document.createElement('textarea');
      textArea.value = text;
      textArea.style.position = 'fixed';
      textArea.style.top = '0';
      textArea.style.left = '0';
      textArea.style.opacity = '0';
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();
      
      try {
        var successful = document.execCommand('copy');
        if (successful) {
          showStatusMessage(_('Coordonnées copiées !') + ' (' + text + ')', 'success');
        } else {
          showStatusMessage(_('Erreur de copie. Coordonnées: ') + text, 'error');
        }
      } catch (err) {
        showStatusMessage(_('Erreur de copie. Coordonnées: ') + text, 'error');
      }
      
      document.body.removeChild(textArea);
    }

    // ==================== SAUVEGARDER LES COORDONNÉES GPS ====================

    function saveGPS() {
      var description = $('#geotag-description').val() || '';
      var hasGPS = (currentLatitude !== null && currentLongitude !== null);
      var hasDescription = (description.trim().length > 0);

      // Il faut au moins des coordonnées GPS, une description, ou un effacement de description
      var descriptionCleared = (initialDescription.trim().length > 0 && !hasDescription);
      if (!hasGPS && !hasDescription && !descriptionCleared) {
        alert(_('Veuillez placer un marqueur sur la carte ou saisir une description'));
        return;
      }

      $('#geotag-save-gps').prop('disabled', true).text(_('Enregistrement...'));

      // Créer un FormData pour envoyer en POST
      var formData = new FormData();
      formData.append('image_id', imageId);
      if (hasGPS) {
        formData.append('latitude', currentLatitude);
        formData.append('longitude', currentLongitude);
        if (currentAltitude !== null) {
          formData.append('altitude', currentAltitude);
        }
      }

      // Envoyer la description
      formData.append('description', description);
      
      $.ajax({
        url: saveUrl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(data) {
          //console.log('Réponse:', data);
          
          var result = data.result || data;
          
          if (data.stat === 'ok' || result.stat === 'ok') {
            showStatusMessage(_('Données enregistrées avec succès !'), 'success');

            // Copier automatiquement la position si des coordonnées GPS ont été sauvegardées
            if (hasGPS && !copiedPosition) {
              copiedPosition = {
                latitude: currentLatitude,
                longitude: currentLongitude,
                altitude: currentAltitude
              };

              // Sauvegarder dans localStorage
              try {
                localStorage.setItem('geotag_copied_position', JSON.stringify(copiedPosition));
              } catch(e) {
                console.error('Cannot save to localStorage:', e);
              }
            }


            // Sauvegarder comme dernière position utilisée avec timestamp - mémorisation de la dernière position v1.4
            if (hasGPS) {
              try {
                var positionData = {
                  lat: currentLatitude,
                  lon: currentLongitude,
                  zoomMem: currentZoom , // cvn
                  timestamp: Date.now()
                };
                localStorage.setItem('geotag_last_position', JSON.stringify(positionData));
                console.log('Geo Tag Editor: Dernière position sauvegardée:', currentLatitude, currentLongitude, currentZoom);
              } catch(e) {
                //console.log('Cannot save last position to localStorage:', e);
              }
            }
            
            setTimeout(function() {
              closeModal();
              // Recharger la page pour voir les changements
              window.location.reload();
            }, 200);
          } else {
            console.error('Erreur:', data.message || result.message || 'Erreur inconnue');
            showStatusMessage('Erreur: ' + (data.message || result.message || 'Erreur inconnue'), 'error');
            $('#geotag-save-gps').prop('disabled', false).text(_('Enregistrer '));
          }
        },
        error: function(xhr, status, error) {
          console.error('Erreur AJAX:', error);
          console.error('Response:', xhr.responseText);
          showStatusMessage('Erreur de communication: ' + error, 'error');
          $('#geotag-save-gps').prop('disabled', false).text(_('Enregistrer '));
        }
      });
    }
    
    // ==================== SUPPRIMER LES COORDONNÉES GPS ====================
    function removeGPS() {
      if (!confirm(_('Voulez-vous vraiment supprimer les coordonnées GPS ?'))) {
        return;
      }
      
      //console.log('Suppression GPS pour image:', imageId);
      
      $('#geotag-remove-gps').prop('disabled', true).text(_('Suppression...'));
      
      var removeUrl = saveUrl.replace('saveGPS', 'removeGPS');
      
      var formData = new FormData();
      formData.append('image_id', imageId);
      
      $.ajax({
        url: removeUrl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(data) {
          //console.log('Réponse:', data);
          
          var result = data.result || data;
          
          if (data.stat === 'ok' || result.stat === 'ok') {
            showStatusMessage(_('Coordonnées GPS supprimées !'), 'success');
            
            // Supprimer le marqueur
            if (marker) {
              map.removeLayer(marker);
              marker = null;
            }
            
            currentLatitude = null;
            currentLongitude = null;
            currentAltitude = null;
            hasGPS = false;
            
            updateGPSInfo();
            
            setTimeout(function() {
              closeModal();
              window.location.reload();
            }, 500);
          } else {
            console.error('Erreur:', data.message || result.message || 'Erreur inconnue');
            showStatusMessage('Erreur: ' + (data.message || result.message || 'Erreur inconnue'), 'error');
            $('#geotag-remove-gps').prop('disabled', false).text(_('Supprimer les coordonnées GPS'));
          }
        },
        error: function(xhr, status, error) {
          console.error('Erreur AJAX:', error);
          showStatusMessage('Erreur de communication: ' + error, 'error');
          $('#geotag-remove-gps').prop('disabled', false).text(_('Supprimer les coordonnées GPS'));
        }
      });
    }
    
    // ==================== AFFICHER UN MESSAGE DE STATUT ====================
    function showStatusMessage(message, type, duration) {
      var className = 'status-message status-' + type;
      var html = '<div class="' + className + '">' + message + '</div>';
      
      $('.modal-footer').before(html);

      if (!duration) {
        duration = type === 'warning' ? 10000 : 3000;
      }
      
      setTimeout(function() {
        $('.status-message').fadeOut(function() {
          $(this).remove();
        });
      }, duration);
    }
    
// ==================== LIEUX PERSONNELS ===========================================================
    
    /**-----------------------------------------------------------------------------------
     * Charger les lieux personnels depuis le serveur
     */
    function loadPersonalPlaces() {
      // Vérifier si la fonctionnalité est activée
      $.ajax({
        url: 'ws.php?format=json&method=geotag.checkPlacesEnabled',
        method: 'GET',
        success: function(response) {
          try {
            var data = typeof response === 'string' ? JSON.parse(response) : response;
            
            if (data.stat === 'ok' && data.result) {
              placesEnabled = data.result.enabled;
              
              if (placesEnabled) {
                // Charger la liste des lieux
                $.ajax({
                  url: 'ws.php?format=json&method=geotag.getPlaces',
                  method: 'GET',
                  success: function(response) {
                    try {
                      var data = typeof response === 'string' ? JSON.parse(response) : response;
                      
                      if (data.stat === 'ok' && data.result) {
                        allPlaces = data.result.places || [];
                        //console.log('Lieux chargés:', allPlaces.length, allPlaces);
                        $('#geotag-places-block').show();
                        initPlacesAutocomplete();
                      }
                    } catch(e) {
                      console.error('Erreur parsing places:', e);
                    }
                  },
                  error: function(xhr, status, error) {
                    console.error('Erreur chargement lieux:', error);
                  }
                });
              }
            }
          } catch(e) {
            console.error('Erreur parsing enabled:', e);
          }
        },
        error: function(xhr, status, error) {
          console.error('Erreur vérification lieux:', error);
        }
      });
    }
    
    /**--------------------------------------------------------------------------------
     * Initialiser l'autocomplétion des lieux
     */
    function initPlacesAutocomplete() {
      var $input = $('#geotag-place-search');
      var $dropdown = $('#geotag-places-dropdown');

        // Afficher la liste au focus
        $input.on('focus', function() {
        //console.log('Focus dans le champ, allPlaces.length:', allPlaces.length);
          if (allPlaces.length > 0) {
            showAllPlaces();
          }
        });


      
      // Filtrage en temps réel
      $input.on('input', function() {
        var search = $(this).val().toLowerCase().trim();
        
        if (search.length < 2) {
          $dropdown.hide().empty();
          selectedPlace = null;
          $('#geotag-apply-place').prop('disabled', true);
          return;
        }
        
        // Filtrer les lieux
        var filtered = allPlaces.filter(function(place) {
          return place.name.toLowerCase().indexOf(search) !== -1;
        });
        
        if (filtered.length === 0) {
          $dropdown.hide().empty();
          selectedPlace = null;
          $('#geotag-apply-place').prop('disabled', true);
          return;
        }
        
        // Afficher les résultats
        var html = '';
        filtered.forEach(function(place) {
          html += '<div class="place-item" data-id="' + place.id + '">';
          html += '<div class="place-name">' + escapeHtml(place.name) + '</div>';
          html += '<div class="place-coords">' + place.latitude + ', ' + place.longitude + '</div>';
          html += '</div>';
        });
        
        $dropdown.html(html).show();
      });
      
$(document).on('click', '#geotag-places-dropdown .place-item', function() {
  //console.log('Clic sur lieu détecté');
  var placeId = parseInt($(this).data('id'));
  //console.log('Place ID:', placeId);
  //console.log('allPlaces:', allPlaces); // DEBUG - voir la structure
  selectedPlace = allPlaces.find(function(p) { 
    //console.log('Comparaison:', p.id, '===', placeId, '?', p.id == placeId); // DEBUG
    return p.id == placeId; // Utiliser == au lieu de === pour éviter les problèmes de type
  });
  //console.log('Selected place:', selectedPlace);
        
        if (selectedPlace) {
          $input.val(selectedPlace.name);
          $dropdown.hide();
          $('#geotag-apply-place').prop('disabled', false);
        }
      });
      
      // Cacher le dropdown si clic ailleurs
      $(document).on('click', function(e) {
        if (!$(e.target).closest('.places-search').length) {
          $dropdown.hide();
        }
      });
      
      // Bouton Appliquer
      $('#geotag-apply-place').on('click', function() {
        if (selectedPlace) {
          applyPlaceCoordinates(selectedPlace);
        }
      });
      
      // Bouton Ajouter
      $('#geotag-add-place').on('click', function() {
        if (currentLatitude && currentLongitude) {
          addCurrentPositionAsPlace();
        }
      });
      
      // Activer/désactiver le bouton Ajouter selon la présence de GPS
      updateAddPlaceButton();
    }
    
/**--------------------------------------------------------------------------------
 * Afficher tous les lieux dans le dropdown
 */
function showAllPlaces() {
  //console.log('showAllPlaces appelée, nombre de lieux:', allPlaces.length);
  var $dropdown = $('#geotag-places-dropdown');
  
  if (allPlaces.length === 0) {
    $dropdown.hide().empty();
    return;
  }
  
  var html = '';
  allPlaces.forEach(function(place) {
    html += '<div class="place-item" data-id="' + place.id + '">';
    html += '<div class="place-name">' + escapeHtml(place.name) + '</div>';
    html += '<div class="place-coords">' + place.latitude + ', ' + place.longitude + '</div>';
    html += '</div>';
  });
  
  $dropdown.html(html).show();
}




    /**-----------------------------------------------------------------
     * Appliquer les coordonnées d'un lieu
     */
    function applyPlaceCoordinates(place) {
      currentLatitude = parseFloat(place.latitude);
      currentLongitude = parseFloat(place.longitude);
      currentAltitude = null; // Les lieux n'ont pas d'altitude
      
      // Mettre à jour l'affichage
      updateGPSInfo();
      
      // Placer le marqueur sur la carte
      placeMarker(currentLatitude, currentLongitude);
      
      // Activer les boutons
      // Activer les boutons de copie
$('#geotag-copy-position').prop('disabled', false);
$('#geotag-copy-coords').prop('disabled', false);
$('#geotag-reset-position').prop('disabled', false);
$('#geotag-remove-gps').prop('disabled', false);
updateAddPlaceButton();
      
      // Message de confirmation
      showStatusMessage(_('Position appliquée depuis') + ' "' + place.name + '"', 'success');
      
      // Vider le champ de recherche
      $('#geotag-place-search').val('');
      $('#geotag-places-dropdown').hide();
      selectedPlace = null;
      $('#geotag-apply-place').prop('disabled', true);
    }
    
    /**
     * Ajouter la position actuelle comme lieu personnel
     */
    function addCurrentPositionAsPlace() {
      var name = prompt(_('Nom du lieu :'));
      
      if (!name || name.trim() === '') {
        return;
      }
      
      name = name.trim();
      
      // Envoyer au serveur
      $.ajax({
        url: 'ws.php?format=json',
        method: 'POST',
        data: {
          method: 'geotag.addPlace',
          name: name,
          latitude: currentLatitude,
          longitude: currentLongitude
        },
        success: function(response) {
          try {
            var data = typeof response === 'string' ? JSON.parse(response) : response;
            
            if (data.stat === 'ok' && data.result && data.result.success) {
              showStatusMessage(_('Lieu') + ' "' + name + '" ' + _('ajouté avec succès'), 'success');
              // Recharger les lieux
              loadPersonalPlaces();
            } else {
              var error = data.message || _('Erreur inconnue');
              showStatusMessage(_('Erreur') + ': ' + error, 'error');
            }
          } catch(e) {
            console.error('Erreur parsing response:', e);
            showStatusMessage(_('Erreur lors de l\'ajout du lieu'), 'error');
          }
        },
        error: function(xhr, status, error) {
          console.error('Erreur AJAX:', error);
          showStatusMessage(_('Erreur de communication'), 'error');
        }
      });
    }
    
    /**
     * Mettre à jour l'état du bouton Ajouter
     */
    function updateAddPlaceButton() {
      var hasCoords = currentLatitude !== null && currentLongitude !== null;
      $('#geotag-add-place').prop('disabled', !hasCoords);
    }
    
    /**
     * Échapper le HTML pour éviter XSS
     */
    function escapeHtml(text) {
      var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // ==================== FIN LIEUX PERSONNELS ====================


    // ==================== FERMER LA MODALE ====================
    function closeModal() {
      //console.log('Geo Tag Editor: Fermeture de la modale...');
      
      // Détruire complètement la carte AVANT de supprimer les éléments DOM
      if (map) {
        map.off();  // Retirer TOUS les event listeners
        map.remove();
        map = null;
      }
      
      marker = null;
      
      // Retirer les éléments DOM
      $('#geotag-modal, #geotag-modal-overlay').remove();
      
      //console.log('Geo Tag Editor: Modale fermée et carte détruite');
    }
    
  });
  
})(jQuery);