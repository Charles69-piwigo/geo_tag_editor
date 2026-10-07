// Fond de carte partagé (éditeur de photo + admin "Lieux personnels").
// Fond vectoriel OpenFreeMap (style Liberty) : étiquettes en caractères latins + locaux
// (ex. "Beijing / 北京"). Repli automatique sur les tuiles raster OpenStreetMap si MapLibre
// n'est pas chargé, si WebGL est indisponible ou si le style ne peut pas être récupéré.
(function() {
  var STYLE_URL = 'https://tiles.openfreemap.org/styles/liberty';

  function hasWebGL() {
    try {
      var canvas = document.createElement('canvas');
      return !!(canvas.getContext('webgl2') || canvas.getContext('webgl'));
    } catch (e) {
      return false;
    }
  }

  function addOsmRaster(L, map) {
    return L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      maxZoom: 19
    }).addTo(map);
  }

  window.GeoTagBasemap = {
    add: function(L, map) {
      if (typeof L.maplibreGL !== 'function' || !window.maplibregl || !hasWebGL()) {
        return addOsmRaster(L, map);
      }

      var layer;
      try {
        layer = L.maplibreGL({
          style: STYLE_URL,
          maxZoom: 19,
          attribution: '<a href="https://openfreemap.org" target="_blank" rel="noopener">OpenFreeMap</a> ' +
            '&copy; <a href="https://openmaptiles.org/" target="_blank" rel="noopener">OpenMapTiles</a> ' +
            'data from <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
        }).addTo(map);
      } catch (e) {
        return addOsmRaster(L, map);
      }

      // Si le style n'a pas pu être chargé, basculer sur les tuiles raster
      var glMap = layer.getMaplibreMap && layer.getMaplibreMap();
      if (glMap) {
        var loaded = false;
        glMap.once('load', function() { loaded = true; });
        glMap.on('error', function() {
          if (!loaded && map.hasLayer(layer)) {
            map.removeLayer(layer);
            addOsmRaster(L, map);
          }
        });
      }
      return layer;
    }
  };
})();
