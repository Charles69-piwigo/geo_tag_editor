

<div class="titrePage">
  <h2>Geo Tag Editor</h2>
</div>

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background: #f9f9f9;
        }
        h1 {
            text-align: center;
            color: #667eea;
            margin-bottom: 30px;
        }
        .columns {
            display: flex;
            gap: 40px;
            max-width: 80%;
            margin: 0 auto;
        }
        .col {
            flex: 1;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 { 
            margin-bottom: 20px;
            color: #667eea;
            font-size: 2.0em;
        }
        h3 {
            margin-top: 25px;
            margin-bottom: 12px;
            color: #764ba2;
            font-size: 1.2em;
        }
        p { 
            margin-bottom: 12px;
            line-height: 1.6;
            text-align: justify;
            font-size: 1.4em;
        }
        ul {
            margin: 15px 0;
            padding-left: 25px;
            font-size: 1.4em;
        }
        li {
            margin-bottom: 8px;
            line-height: 1.6;
        }
        .code {
            font-family: 'Courier New', monospace;
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.9em;
        }
        .highlight {
            background: #fff3cd;
            padding: 15px;
            border-left: 4px solid #ffc107;
            margin: 15px 0;
            border-radius: 4px;
        }
        a {
            color: #667eea;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        @media (max-width: 968px) {
            .columns {
                flex-direction: column;
            }
        }
    </style>




<div class="columns">
    <div class="col">
        <h2>🇫🇷 Français</h2>
        
        <p>Le plugin <strong>geo_tag_editor</strong> permet l'édition des tags de géolocalisation GPS.</p>
        
        <h3>Fonctionnalités</h3>
        <p>Avec ce plugin vous pouvez :</p>
        <ul>
            <li>Visualiser la photo avec sa position GPS</li>
            <li>Placer/déplacer le marqueur GPS sur une carte interactive</li>
            <li>Rechercher une localisation par nom (via Nominatim)</li>
            <li>Saisir des coordonnées GPS</li>
            <li>Coller les coordonnées copiées depuis Google Maps</li>
            <li>Copier les coordonnées pour les coller dans Google Maps</li>
            <li>Copier Coller une position d'une photo à une autre</li>
            <li>Modifier/Supprimer/Enregistrer les coordonnées GPS</li>
            <li>Identifier une localisation avec Google Lens (la photo est téléchargéé automatiqement , déplacer là dans Google Lens )</li>
        </ul>
        
        <h3>Droits d'accès</h3>
        <p>Pour pouvoir utiliser le plugin il faut être webmaster, administrateur ou un user appartenant au groupe GeoTag.</p>
        <p>Les droits des utilisateurs sont soit globaux, soit sur des albums à spécifier.</p>
        
        <h3>Visualisation des résultats</h3>
        <p>Pour visualiser les résultats il faut installer/activer un plugin de géolocalisation compatible avec Piwigo, comme :</p>
        <ul>
            <li><strong>piwigo-openstreetmap</strong> : <a href="https://fr.piwigo.org/ext/index.php?eid=701" target="_blank">https://fr.piwigo.org/ext/index.php?eid=701</a></li>
        </ul>
        <p>Ces plugins permettent de visualiser instantanément le résultat de geo_tag_editor sur une carte.</p>
        
        <h3>Formats de métadonnées GPS</h3>
        <p>Les tags GPS sont enregistrés dans les métadonnées des photos aux formats standards :</p>
        <ul>
            <li><strong>GPSLatitude / GPSLongitude</strong> (coordonnées GPS principales)</li>
            <li><strong>GPSLatitudeRef / GPSLongitudeRef</strong> (Nord/Sud, Est/Ouest)</li>
        </ul>
        <p>Ces formats sont compatibles avec la plupart des logiciels de gestion de photos.</p>
        
        <div class="highlight">
            <strong>✅ Compatibilité :</strong> Les tags GPS existants créés par d'autres logiciels (Lightroom, digiKam, etc.) sont pris en compte par geo_tag_editor.
        </div>
        
        <h3>Permissions requises</h3>
        <p>Il faut que les fichiers jpg aient des droits en écriture.</p>
        <p>Pour les NAS Synology :</strong> il faut donner les droits lecture et écriture au groupe <span class="code">http</span> sur les répertoires concernés :</p>
        <ul>
            <li><span class="code">./data</span></li>
            <li><span class="code">./upload</span></li>
            <li><span class="code">./galleries</span></li>
        </ul>
        
        <h3>Compatibilité des chemins</h3>
        <p>L'édition des tags GPS fonctionne sur les photos dans :</p>
        <ul>
            <li><span class="code">./upload</span></li>
            <li><span class="code">./galleries</span> directement</li>
            <li><span class="code">./galleries</span> + liens symboliques</li>
        </ul>
        
        
        <h3>Intégration Piwigo</h3>
        <p>Les coordonnées GPS sont enregistrées dans les métadonnées et peuvent être utilisées par les plugins de carte compatibles.</p>
        
        <h3>Carte interactive</h3>
        <p>La carte utilise :</p>
        <ul>
            <li><strong>Leaflet.js</strong> pour l'interface de carte interactive</li>
            <li><strong>OpenStreetMap</strong> comme fond de carte</li>
            <li><strong>Nominatim</strong> pour la recherche de lieux</li>
        </ul>
    </div>

    <div class="col">
        <h2>🇬🇧 English</h2>
        
        <p>The <strong>geo_tag_editor</strong> plugin allows you to edit GPS geolocation tags.</p>
        
        <h3>Features</h3>
        <p>With this plugin, you can:</p>
        <ul>
            <li>View the photo with its GPS location</li>
            <li>Place/move the GPS marker on an interactive map</li>
            <li>Search for a location by name (via Nominatim)</li>
            <li>Enter GPS coordinates</li>
            <li>Paste coordinates copied from Google Maps</li>
            <li>Copy coordinates to paste into Google Maps</li>
            <li>Copy and paste a location from one photo to another</li>
            <li>Edit/Delete/Save GPS coordinates</li>
            <li>Identify a location with Google Lens (the photo is automatically downloaded, move it into Google Lens)</li>
        </ul>
        
        <h3>Access Rights</h3>
        <p>To use the plugin, you must be a webmaster, administrator, or a user belonging to the GeoTag group.</p>
        <p>User rights are either global or specific to particular albums.</p>
        
        <h3>Viewing Results</h3>
        <p>To view the results, you must install/activate a geolocation plugin compatible with Piwigo, such as:</p>
        <ul>
            <li><strong>piwigo-openstreetmap</strong>: <a href="https://piwigo.org/ext/index.php?eid=701" target="_blank">https://piwigo.org/ext/index.php?eid=701</a></li>
        </ul>
        <p>These plugins allow you to instantly view the results of geo_tag_editor on a map.</p>
        
        <h3>GPS Metadata Formats</h3>
        <p>GPS tags are saved in the photo's metadata in standard formats:</p>
        <ul>
            <li><strong>GPSLatitude / GPSLongitude</strong> (main GPS coordinates)</li>
            <li><strong>GPSLatitudeRef / GPSLongitudeRef</strong> (North/South, East/West)</li>
        </ul>
        <p>These formats are compatible with most photo management software.</p>
        
        <div class="highlight">
            <strong>✅ Compatibility:</strong> Existing GPS tags created by other software (Lightroom, digiKam, etc.) are recognized by geo_tag_editor.
        </div>
        
        <h3>Required Permissions</h3>
        <p>JPG files must have write permissions.</p>
        <p>For Synology NAS devices:</strong> you must grant read and write permissions to the <span class="code">http</span> group on the relevant directories:</p>
        <ul>
            <li><span class="code">./data</span></li>
            <li><span class="code">./upload</span></li>
            <li><span class="code">./galleries</span></li>
        </ul>
        
        <h3>Path Compatibility</h3>
        <p>GPS tag editing works on photos in:</p>
        <ul>
            <li><span class="code">./upload</span></li>
            <li>directly in <span class="code">./galleries</span></li>
            <li><span class="code">./galleries</span> with symbolic links</li>
        </ul>
        
        <h3>Piwigo Integration</h3>
        <p>GPS coordinates are saved in metadata and can be used by compatible map plugins.</p>
        
        <h3>Interactive Map</h3>
        <p>The map uses:</p>
        <ul>
            <li><strong>Leaflet.js</strong> for the interactive map interface</li>
            <li><strong>OpenStreetMap</strong> as base map</li>
            <li><strong>Nominatim</strong> for location search</li>
        </ul>
    </div>
</div>

</body>
</html>
