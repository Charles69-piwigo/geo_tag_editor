// geo_tag_editor - Interface modale de géolocalisation avec OpenStreetMap
// Leaflet 1.9.4 isolé via noConflict() au chargement de la page

(function($) {
  'use strict';
  
  $(document).ready(function() {
    
    console.log('Geo Tag Editor: Script chargé');
    
    var map = null;
    var marker = null;
    var imageId = null;
    var imageSrc = null;
    var saveUrl = null;
    var currentLatitude = null;
    var currentLongitude = null;
    var currentAltitude = null;
    var hasGPS = false;
    var copiedPosition = null;
    var originalLatitude = null;
    var originalLongitude = null;
    var originalAltitude = null;
    
    // Notre référence à Leaflet isolé (définie dans main.inc.php via noConflict)
    var GeoTagLeaflet = window.GeoTagLeaflet;
    
    // Vérifier que Leaflet est bien chargé et isolé
    if (!GeoTagLeaflet) {
      console.error('Geo Tag Editor: GeoTagLeaflet non disponible !');
      
      // Cacher le bouton si Leaflet n'est pas chargé
      $('#geotag-open-editor').hide();
      
      return;
    }
    
    console.log('Geo Tag Editor: Leaflet ' + GeoTagLeaflet.version + ' prêt (isolé)');
    
    // Vérifier si window.L existe toujours (piwigo_openstreetmap)
    if (typeof window.L !== 'undefined') {
      console.log('Geo Tag Editor: Leaflet existant détecté (v' + (window.L.version || 'inconnue') + ') - préservé');
    }
    
    try {
       var stored = localStorage.getItem('geotag_copied_position');
       if (stored) {
           copiedPosition = JSON.parse(stored);
       }
    } catch(e) {
      // Ignore localStorage errors
    }

    // Fonction de traduction
    function _(text) {
      return (typeof geotagLang !== 'undefined' && geotagLang[text]) ? geotagLang[text] : text;
    }

    // ==================== GESTIONNAIRE DU BOUTON ====================
    $(document).on('click', '#geotag-open-editor', function(e) {
      e.preventDefault();
      
      var dataAttr = $(this).data('geotag');
      if (!dataAttr) {
        alert('Erreur: Données manquantes');
        return;
      }
      
      var data;
      try {
        data = typeof dataAttr === 'string' ? JSON.parse(dataAttr) : dataAttr;
      } catch (err) {
        console.error('Erreur parsing JSON:', err);
        alert('Erreur: Données invalides');
        return;
      }
      
      imageId = data.image_id;
      imageSrc = data.image_src;
      console.log('Image SRC reçu:', imageSrc);  
      saveUrl = data.save_url;
      hasGPS = data.has_gps;
      currentLatitude = data.latitude;
      currentLongitude = data.longitude;
      currentAltitude = data.altitude;

      originalLatitude = data.latitude;
      originalLongitude = data.longitude;
      originalAltitude = data.altitude;
      
      console.log('Ouverture éditeur GPS:', {
        imageId: imageId,
        hasGPS: hasGPS,
        lat: currentLatitude,
        lon: currentLongitude
      });
      
      // Ouvrir directement la modale (Leaflet déjà chargé et isolé)
      openModal();
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
      <h3>📍 ${_('Éditeur de géolocalisation')} - ${_('Image')} #${imageId}</h3>
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
</div>

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
    <button id="geotag-save-gps">💾 ${_('Enregistrer')}</button>
  </div>

</div>
    
  </div>
`;
      
$('body').append(modalHtml);
      
// Initialiser la carte
initMap();

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

    }

    // ==================== INITIALISER LA CARTE OPENSTREETMAP ====================
    function initMap() {
      // IMPORTANT : Utiliser GeoTagLeaflet (notre version isolée)
      var L = GeoTagLeaflet;
      
      // Position par défaut Lyon    (Paris)
      var defaultLat = 45.7578;  // 48.8566;
      var defaultLon = 4.8320; //2.3522;
      var defaultZoom = 10; // Zoom réduit pour charger moins de tuiles
      
      // Si l'image a déjà des coordonnées GPS, les utiliser
      if (hasGPS && currentLatitude && currentLongitude) {
        defaultLat = currentLatitude;
        defaultLon = currentLongitude;
        defaultZoom = 13; // Zoom réduit de 15 à 13
      }
      
      // Créer la carte
      map = L.map('geotag-map').setView([defaultLat, defaultLon], defaultZoom);
      
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
      
      // Événement de déplacement du marqueur
      marker.on('dragend', function(e) {
        var pos = marker.getLatLng();
        currentLatitude = pos.lat;
        currentLongitude = pos.lng;
        updateGPSInfo();
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
      
      console.log('Recherche:', query);
      
      // Afficher un message de chargement
      $('#geotag-search-results').html('<div class="search-result-item">' + _('Recherche...') + '</div>').addClass('show');
      
      // Utiliser Nominatim (service de géocodage d'OpenStreetMap)
      var url = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query);
      
      $.ajax({
        url: url,
        type: 'GET',
        dataType: 'json',
        success: function(results) {
          console.log('Résultats recherche:', results);
          
          if (results && results.length > 0) {
            displaySearchResults(results);
          } else {
            $('#geotag-search-results').html('<div class="search-result-item">' + _('Aucun résultat trouvé') + '</div>');
          }
        },
        error: function(xhr, status, error) {
          console.error('Erreur recherche:', error);
          $('#geotag-search-results').html('<div class="search-result-item">Erreur de recherche</div>');
        }
      });
    }
    
    // ==================== AFFICHER LES RÉSULTATS DE RECHERCHE ====================
    function displaySearchResults(results) {
      var html = '';
      
      results.slice(0, 10).forEach(function(result) {
        html += '<div class="search-result-item" data-lat="' + result.lat + '" data-lon="' + result.lon + '">';
        html += '<div class="search-result-name">' + (result.name || result.display_name.split(',')[0]) + '</div>';
        html += '<div class="search-result-display">' + result.display_name + '</div>';
        html += '</div>';
      });
      
      $('#geotag-search-results').html(html).addClass('show');
      
      // Événement de clic sur un résultat
      $('.search-result-item').click(function() {
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
        
        // Activer le bouton de sauvegarde
        $('#geotag-save-gps').prop('disabled', false);
      } else {
        $('#geotag-gps-info').html('<div class="gps-no-data">' + _('Aucune position GPS') + '</div>');
        
        // Désactiver le bouton "Copier coordonnées"
        $('#geotag-copy-coords').prop('disabled', true);
        
        $('#geotag-save-gps').prop('disabled', true);
      }
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
        
        console.log('Position copiée:', copiedPosition);
      }
    }
    
    // ==================== COLLER LA POSITION ====================
    function pastePosition() {
      console.log('=== PASTE POSITION ===');
      console.log('copiedPosition:', copiedPosition);

      if (copiedPosition) {
        placeMarker(copiedPosition.latitude, copiedPosition.longitude);
        
        if (copiedPosition.altitude !== null) {
          currentAltitude = copiedPosition.altitude;
        }
        
        // Centrer la carte
        map.setView([copiedPosition.latitude, copiedPosition.longitude], 15);
        
        // Afficher un message
        showStatusMessage(_('Position collée !'), 'success');
        
        console.log('Position collée:', copiedPosition);
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
        
        console.log('Position réinitialisée:', {
          lat: originalLatitude,
          lon: originalLongitude,
          alt: originalAltitude
        });
      } else {
        alert(_('Aucune position originale à restaurer'));
      }
    }

    // ==================== OUVRIR GOOGLE LENS ====================
    function openGoogleLens(imgId) {
        var message = _('Pour utiliser Google Lens :\n\n') +
                     _('1. Faites un clic droit sur l\'image à gauche\n') +
                     _('2. Sélectionnez "Rechercher une image avec Google Lens"\n\n') +
                     _('OU\n\n') +
                     _('Cliquez sur OK pour télécharger l\'image et ouvrir Google Lens');
        
        if (confirm(message)) {
            // Récupérer l'URL de l'image affichée dans la modale
            var imgSrc = $('.modal-image-area img').attr('src');
            
            // Créer un lien de téléchargement
            var a = document.createElement('a');
            a.href = imgSrc;
            a.download = 'image_google_lens_' + imgId + '.jpg';
            a.target = '_blank';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            
            // Ouvrir Google Lens après un court délai
            setTimeout(function() {
                window.open('https://lens.google.com/', '_blank');
            }, 500);
        }
    }

    // ==================== APPLIQUER COORDONNÉES DEPUIS LE CHAMP ====================
    function applyCoordinatesFromInput() {
      console.log('=== APPLY COORDS CLICKED ===');
      var input = $('#geotag-coords-input').val().trim();
      console.log('Input value:', input);
      
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
        alert(_('Format invalide. Utilisez: latitude, longitude\nExemple: 45.433214, 12.339914'));
        return;
      }
      
      if (coords.length !== 2) {
        alert(_('Format invalide. Utilisez: latitude, longitude\nExemple: 45.433214, 12.339914'));
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
      
      console.log('Coordonnées appliquées:', {lat: lat, lon: lon});
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
            console.log('Coordonnées copiées:', coords);
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
      if (currentLatitude === null || currentLongitude === null) {
        alert(_('Veuillez placer un marqueur sur la carte'));
        return;
      }
      
      console.log('Sauvegarde GPS:', {
        imageId: imageId,
        latitude: currentLatitude,
        longitude: currentLongitude,
        altitude: currentAltitude
      });
      
      $('#geotag-save-gps').prop('disabled', true).text(_('Enregistrement...'));
      
      // Créer un FormData pour envoyer en POST
      var formData = new FormData();
      formData.append('image_id', imageId);
      formData.append('latitude', currentLatitude);
      formData.append('longitude', currentLongitude);
      if (currentAltitude !== null) {
        formData.append('altitude', currentAltitude);
      }
      
      $.ajax({
        url: saveUrl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(data) {
          console.log('Réponse:', data);
          
          var result = data.result || data;
          
          if (data.stat === 'ok' || result.stat === 'ok') {
            showStatusMessage(_('Coordonnées GPS enregistrées avec succès !'), 'success');

            // Copier automatiquement la position si aucune position n'est déjà copiée
            if (!copiedPosition) {
              copiedPosition = {
                latitude: currentLatitude,
                longitude: currentLongitude,
                altitude: currentAltitude
              };
              
              // Sauvegarder dans localStorage
              try {
                localStorage.setItem('geotag_copied_position', JSON.stringify(copiedPosition));
                console.log('Position automatiquement copiée après sauvegarde:', copiedPosition);
              } catch(e) {
                console.error('Cannot save to localStorage:', e);
              }
            }
            
            setTimeout(function() {
              closeModal();
              // Recharger la page pour voir les changements
              window.location.reload();
            }, 1500);
          } else {
            console.error('Erreur:', data.message || result.message || 'Erreur inconnue');
            showStatusMessage('Erreur: ' + (data.message || result.message || 'Erreur inconnue'), 'error');
            $('#geotag-save-gps').prop('disabled', false).text(_('Enregistrer'));
          }
        },
        error: function(xhr, status, error) {
          console.error('Erreur AJAX:', error);
          console.error('Response:', xhr.responseText);
          showStatusMessage('Erreur de communication: ' + error, 'error');
          $('#geotag-save-gps').prop('disabled', false).text(_('Enregistrer'));
        }
      });
    }
    
    // ==================== SUPPRIMER LES COORDONNÉES GPS ====================
    function removeGPS() {
      if (!confirm(_('Voulez-vous vraiment supprimer les coordonnées GPS ?'))) {
        return;
      }
      
      console.log('Suppression GPS pour image:', imageId);
      
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
          console.log('Réponse:', data);
          
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
            }, 1500);
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
    function showStatusMessage(message, type) {
      var className = 'status-message status-' + type;
      var html = '<div class="' + className + '">' + message + '</div>';
      
      $('.modal-footer').prepend(html);
      
      setTimeout(function() {
        $('.status-message').fadeOut(function() {
          $(this).remove();
        });
      }, 3000);
    }
    
    // ==================== FERMER LA MODALE ====================
    function closeModal() {
      console.log('Geo Tag Editor: Fermeture de la modale...');
      
      // Détruire complètement la carte AVANT de supprimer les éléments DOM
      if (map) {
        map.off();  // Retirer TOUS les event listeners
        map.remove();
        map = null;
      }
      
      marker = null;
      
      // Retirer les éléments DOM
      $('#geotag-modal, #geotag-modal-overlay').remove();
      
      console.log('Geo Tag Editor: Modale fermée et carte détruite');
    }
    
  });
  
})(jQuery);