<div class="titrePage">
  <h2>Geo Tag Editor</h2>
</div>

<style>
  .gte-help { margin:0 0 2em 20px; max-width:700px; line-height:1.6; text-align:left; }
  .gte-help h4 { margin:1.2em 0 0.3em 0; color:#333; border-bottom:1px solid #eee; padding-bottom:2px; }
  .gte-help p { margin:0.3em 0 0.5em 0; }
  .gte-help ul { margin:0.3em 0 0.5em 1.2em; padding:0; }
  .gte-help li { margin:0.2em 0; }
  .gte-help code { background:#f4f4f4; padding:1px 5px; border-radius:3px; font-size:0.9em; }
</style>

<div class="gte-help">

  <h4>{'Présentation'|@translate}</h4>
  <p>{'Le plugin geo_tag_editor permet d\'éditer les métadonnées de géolocalisation GPS des photos directement depuis Piwigo.'|@translate}</p>

  <h4>{'Fonctionnalités'|@translate}</h4>
  <p>{'Avec ce plugin vous pouvez :'|@translate}</p>
  <ul>
    <li>{'Visualiser la photo avec sa position GPS actuelle'|@translate}</li>
    <li>{'Placer ou déplacer le marqueur GPS sur une carte interactive'|@translate}</li>
    <li>{'Rechercher une localisation par nom (via Nominatim)'|@translate}</li>
    <li>{'Saisir des coordonnées GPS, ou coller des coordonnées copiées depuis Google Maps'|@translate}</li>
    <li>{'Copier les coordonnées d\'une photo pour les coller dans Google Maps'|@translate}</li>
    <li>{'Copier une position d\'une photo et la coller sur une autre'|@translate}</li>
    <li>{'Modifier, supprimer ou enregistrer les coordonnées GPS'|@translate}</li>
    <li>{'Rédiger une description enrichie de la photo'|@translate}</li>
    <li>{'Identifier une localisation à l\'aide de Google Lens'|@translate}</li>
  </ul>

  <h4>{'Google Lens'|@translate}</h4>
  <p>{'Deux méthodes sont proposées pour identifier un lieu à partir de la photo :'|@translate}</p>
  <ul>
    <li>{'Clic droit sur l\'image → "Rechercher une image avec Google Lens" (fonctionne directement si l\'image est accessible depuis le navigateur)'|@translate}</li>
    <li>{'Ou téléchargement de l\'image, puis dépôt manuel de celle-ci dans Google Lens'|@translate}</li>
  </ul>

  <h4>{'Droits d\'accès'|@translate}</h4>
  <p>{'Seuls les webmasters, les administrateurs et les utilisateurs appartenant au groupe GeoTag peuvent utiliser le plugin. Les webmasters et administrateurs ont toujours un accès total.'|@translate}</p>
  <p>{'Pour les membres du groupe GeoTag, l\'onglet "Gestion des droits" propose deux modes :'|@translate}</p>
  <ul>
    <li><strong>{'Tous les albums'|@translate}</strong> {': les utilisateurs du groupe peuvent géolocaliser toutes les photos qu\'ils peuvent voir'|@translate}</li>
    <li><strong>{'Sélectif par utilisateur'|@translate}</strong> {': les albums autorisés sont configurés individuellement pour chaque utilisateur (jusqu\'à 5 albums, sous-albums inclus)'|@translate}</li>
  </ul>

  <h4>{'Lieux personnels'|@translate}</h4>
  <p>{'L\'onglet "Lieux personnels" permet, une fois l\'option activée, d\'établir une liste de lieux géolocalisés prêts à l\'emploi, disponible ensuite dans l\'éditeur de géo tag pour géolocaliser rapidement une photo.'|@translate}</p>
  <p>{'Si le plugin piwigo_openstreetmap est installé, ses lieux personnels peuvent être importés dans cette liste.'|@translate}</p>

  <h4>{'Description'|@translate}</h4>
  <p>{'Un éditeur de texte enrichi (Trumbowyg) permet de rédiger ou modifier la description de la photo directement depuis la fenêtre de géolocalisation : mise en forme (gras, italique, souligné, couleurs, polices), listes, liens, mode plein écran.'|@translate}</p>
  <p>{'L\'enregistrement de la position GPS et celui de la description sont totalement indépendants : enregistrer l\'un ne modifie ni n\'efface l\'autre.'|@translate}</p>
  <p>{'Si une description existante contient une mise en forme HTML complexe, elle s\'affiche en lecture seule pour éviter de l\'altérer involontairement.'|@translate}</p>
  <p>{'L\'enregistrement de la description dans les métadonnées de la photo est automatique. Pour gérer cet enregistrement manuellement, ajoutez'|@translate} <code>$conf['geo_tag_editor_write_comment'] = 'onoff';</code> {'dans Local File Editor.'|@translate}</p>

  <h4>{'Formats de métadonnées GPS'|@translate}</h4>
  <p>{'Les tags GPS sont enregistrés dans les métadonnées des photos aux formats standards :'|@translate}</p>
  <ul>
    <li><strong>{'GPSLatitude / GPSLongitude'|@translate}</strong> {'(coordonnées GPS principales)'|@translate}</li>
    <li><strong>{'GPSLatitudeRef / GPSLongitudeRef'|@translate}</strong> {'(Nord/Sud, Est/Ouest)'|@translate}</li>
  </ul>
  <p>{'Ces formats sont compatibles avec la plupart des logiciels de gestion de photos. Les tags GPS existants créés par d\'autres logiciels (Lightroom, digiKam, etc.) sont reconnus par geo_tag_editor.'|@translate}</p>
  <p>{'Pour les formats non-JPEG, l\'écriture dans les métadonnées du fichier n\'est pas possible : les coordonnées sont alors enregistrées uniquement en base de données.'|@translate}</p>

  <h4>{'Visualisation des résultats'|@translate}</h4>
  <p>{'Pour visualiser les résultats sur une carte, il faut installer et activer un plugin de géolocalisation compatible avec Piwigo, comme :'|@translate}</p>
  <ul>
    <li><strong>piwigo-openstreetmap</strong> : <a href="https://fr.piwigo.org/ext/index.php?eid=701" target="_blank">https://fr.piwigo.org/ext/index.php?eid=701</a></li>
  </ul>

  <h4>{'Permissions requises'|@translate}</h4>
  <p>{'Les fichiers photo doivent avoir des droits en écriture.'|@translate}</p>
  <p>{'Pour les NAS Synology, il faut donner les droits lecture et écriture au groupe'|@translate} <code>http</code> {'sur les répertoires concernés :'|@translate}</p>
  <ul>
    <li><code>./data</code></li>
    <li><code>./upload</code></li>
    <li><code>./galleries</code></li>
  </ul>

  <h4>{'Compatibilité des chemins'|@translate}</h4>
  <p>{'L\'édition des tags GPS fonctionne sur les photos situées dans :'|@translate}</p>
  <ul>
    <li><code>./upload</code></li>
    <li><code>./galleries</code> {'directement'|@translate}</li>
    <li><code>./galleries</code> {'via des liens symboliques'|@translate}</li>
  </ul>

  <h4>{'Carte interactive'|@translate}</h4>
  <p>{'La carte utilise :'|@translate}</p>
  <ul>
    <li><strong>Leaflet.js</strong> {'pour l\'interface de carte interactive'|@translate}</li>
    <li><strong>OpenStreetMap</strong> {'comme fond de carte'|@translate}</li>
    <li><strong>Nominatim</strong> {'pour la recherche de lieux'|@translate}</li>
  </ul>

</div>
