jQuery(document).ready(function($) {
    
    // Fonction de traduction
    function _(key) {
        return (typeof adminRightsLang !== 'undefined' && adminRightsLang[key]) ? adminRightsLang[key] : key;
    }
    
    // Variable globale pour stocker tous les albums par utilisateur
    var allAlbumsByUser = {};
    
    // Gestion du changement de mode
    $('input[name="mode"]').on('change', function() {
        if ($(this).val() === 'selective') {
            $('#selective_config').slideDown();
        } else {
            $('#selective_config').slideUp();
        }
    });
    
    // Sauvegarder le mode
    $('#btn_save_mode').on('click', function() {
        var mode = $('input[name="mode"]:checked').val();
        var usersConfigData = {};
        
        // Si mode sélectif, récupérer aussi les configs users
        if (mode === 'selective') {
            usersConfigData = collectUsersConfig();
        }
        
        saveConfiguration(mode, usersConfigData);
    });
    
    // Sauvegarder les permissions utilisateurs
    $('#btn_save_users_config').on('click', function() {
        var mode = 'selective';
        var usersConfigData = collectUsersConfig();
        saveConfiguration(mode, usersConfigData);
    });
    
    /**
     * Initialise l'interface pour les utilisateurs GeoTag
     */
    function initializeUsersConfig() {
        var $container = $('#users_config_container');
        $container.empty();
        
        if (Object.keys(geotagUsers).length === 0) {
            return;
        }
        
        // Limiter à 5 utilisateurs
        var userIds = Object.keys(geotagUsers).slice(0, 5);
        
        userIds.forEach(function(userId) {
            var username = geotagUsers[userId];
            createUserConfigBlock(userId, username);
        });
    }
    
    /**
     * Crée un bloc de configuration pour un utilisateur
     */
    function createUserConfigBlock(userId, username) {
        var existingCategories = usersConfig[userId] || [];
        
        var blockHtml = `
            <div class="user_config_block" data-user-id="${userId}">
                <h4>👤 ${username}</h4>
                
                <div class="album_selector">
                    <input type="text" 
                           class="album_search" 
                           data-user-id="${userId}" 
                           placeholder="${_('filter_albums')}">
                    <select class="album_select" 
                            data-user-id="${userId}">
                        <option value="">${_('select_album')}</option>
                    </select>
                    <button type="button" class="buttonLike btn_add_album" data-user-id="${userId}">
                        ${_('add')}
                    </button>
                    <span class="album_count" data-user-id="${userId}">
                        <span class="count">0</span>/5 ${_('albums')}
                    </span>
                </div>
                
                <div class="selected_albums" data-user-id="${userId}">
                    <strong>${_('authorized_albums')}</strong>
                    <ul class="albums_list"></ul>
                </div>
            </div>
        `;
        
        $('#users_config_container').append(blockHtml);
        
        // Charger les albums disponibles pour cet utilisateur
        loadUserAlbums(userId, existingCategories);
    }
    
    /**
     * Charge les albums accessibles par un utilisateur
     */
    function loadUserAlbums(userId, selectedCategories) {
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'get_user_albums',
                user_id: userId
            },
            dataType: 'json',
            success: function(albums) {
                // Stocker tous les albums pour ce user
                allAlbumsByUser[userId] = albums;
                
                populateAlbumSelect(userId, albums);
                
                // Afficher les albums déjà sélectionnés
                selectedCategories.forEach(function(catId) {
                    if (albums[catId]) {
                        addAlbumToList(userId, catId, albums[catId].name);
                    }
                });
                
                updateAlbumCount(userId);
            },
            error: function(xhr, status, error) {
                console.error('Erreur chargement albums:', error);
            }
        });
    }
    
    /**
     * Remplit le select avec les albums disponibles (avec indentation)
     */
    function populateAlbumSelect(userId, albums) {
        var $select = $('.album_select[data-user-id="' + userId + '"]');
        
        // Garder la première option "Sélectionner"
        $select.find('option:not(:first)').remove();
        
        // Ajouter les albums avec indentation
        $.each(albums, function(catId, catData) {
            var indent = '';
            var level = catData.level || 0;
            
            // Créer l'indentation (4 espaces par niveau)
            for (var i = 0; i < level; i++) {
                indent += '\u00A0\u00A0\u00A0\u00A0'; // 4 espaces insécables par niveau
            }
            
            // Ajouter un préfixe visuel pour les sous-albums
            var prefix = level > 0 ? '└─ ' : '';
            
            $select.append(
                $('<option>')
                    .val(catId)
                    .text(indent + prefix + catData.name)
                    .attr('data-name', catData.name.toLowerCase())
                    .attr('data-level', level)
            );
        });
    }
    
    /**
     * Filtre les options du select selon la recherche
     */
    function filterAlbums(userId, searchText) {
        var $select = $('.album_select[data-user-id="' + userId + '"]');
        searchText = searchText.toLowerCase().trim();
        
        var visibleCount = 0;
        
        $select.find('option').each(function() {
            var $option = $(this);
            
            // Ne jamais cacher la première option "Sélectionner"
            if ($option.val() === '') {
                return;
            }
            
            var albumName = $option.attr('data-name') || '';
            
            if (searchText === '' || albumName.indexOf(searchText) !== -1) {
                $option.show();
                visibleCount++;
            } else {
                $option.hide();
            }
        });
        
        // Afficher un message si aucun résultat
        if (visibleCount === 0 && searchText !== '') {
            $select.find('option:first').text(_('no_results'));
        } else {
            $select.find('option:first').text(_('select_album'));
        }
    }
    
    /**
     * Event: Recherche dans les albums
     */
    $(document).on('input', '.album_search', function() {
        var userId = $(this).data('user-id');
        var searchText = $(this).val();
        filterAlbums(userId, searchText);
    });
    
    /**
     * Ajoute un album à la liste d'un utilisateur
     */
    function addAlbumToList(userId, catId, catName) {
        var $list = $('.selected_albums[data-user-id="' + userId + '"] .albums_list');
        
        // Vérifier si déjà dans la liste
        if ($list.find('li[data-cat-id="' + catId + '"]').length > 0) {
            return;
        }
        
        // Vérifier la limite de 5
        if ($list.find('li').length >= 5) {
            alert(_('max_5_albums'));
            return;
        }
        
        var $item = $('<li>')
            .attr('data-cat-id', catId)
            .html(`
                <span>${catName} ${_('sub_albums_included')}</span>
                <span class="remove_album" data-user-id="${userId}" data-cat-id="${catId}">[×]</span>
            `);
        
        $list.append($item);
        updateAlbumCount(userId);
    }
    
    /**
     * Met à jour le compteur d'albums
     */
    function updateAlbumCount(userId) {
        var count = $('.selected_albums[data-user-id="' + userId + '"] .albums_list li').length;
        var $counter = $('.album_count[data-user-id="' + userId + '"]');
        
        $counter.find('.count').text(count);
        
        if (count >= 5) {
            $counter.addClass('limit_reached');
            $('.btn_add_album[data-user-id="' + userId + '"]').prop('disabled', true);
            $('.album_search[data-user-id="' + userId + '"]').prop('disabled', true);
        } else {
            $counter.removeClass('limit_reached');
            $('.btn_add_album[data-user-id="' + userId + '"]').prop('disabled', false);
            $('.album_search[data-user-id="' + userId + '"]').prop('disabled', false);
        }
    }
    
    /**
     * Event: Ajouter un album
     */
    $(document).on('click', '.btn_add_album', function() {
        var userId = $(this).data('user-id');
        var $select = $('.album_select[data-user-id="' + userId + '"]');
        var catId = $select.val();
        var catName = $select.find('option:selected').text().replace(/^[\s\u00A0└─]*/, ''); // Nettoyer l'indentation
        
        if (!catId) {
            alert(_('select_album_from_list'));
            return;
        }
        
        addAlbumToList(userId, catId, catName);
        
        // Réinitialiser
        $('.album_search[data-user-id="' + userId + '"]').val('');
        filterAlbums(userId, '');
    });
    
    /**
     * Event: Supprimer un album
     */
    $(document).on('click', '.remove_album', function() {
        var userId = $(this).data('user-id');
        var catId = $(this).data('cat-id');
        
        $(this).closest('li').remove();
        updateAlbumCount(userId);
    });
    
    /**
     * Collecte la configuration de tous les utilisateurs
     */
    function collectUsersConfig() {
        var config = {};
        
        $('.user_config_block').each(function() {
            var userId = $(this).data('user-id');
            var categories = [];
            
            $(this).find('.albums_list li').each(function() {
                categories.push(parseInt($(this).data('cat-id')));
            });
            
            if (categories.length > 0) {
                config[userId] = categories;
            }
        });
        
        return config;
    }
    
    /**
     * Sauvegarde la configuration complète
     */
    function saveConfiguration(mode, usersConfigData) {
        // Si on sauvegarde en mode "all" sans données users,
        // on envoie quand même les données collectées pour les préserver
        if (mode === 'all' && Object.keys(usersConfigData).length === 0) {
            usersConfigData = collectUsersConfig();
        }
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'save_rights_config',
                mode: mode,
                users: JSON.stringify(usersConfigData)
            },
            dataType: 'json',
            success: function(response) {
                if (mode === 'all') {
                    alert(_('config_saved_all_mode'));
                } else {
                    alert(_('config_saved'));
                }
                
                // Recharger la page pour voir les changements
                setTimeout(function() {
                    window.location.reload();
                }, 500);
            },
            error: function(xhr, status, error) {
                console.error('Erreur sauvegarde:', error);
                
                // Vérifier si c'est une fausse erreur (succès en réalité)
                try {
                    var response = xhr.responseText;
                    if (response.indexOf('Configuration enregistrée') !== -1 || response.indexOf('Configuration saved') !== -1) {
                        alert(_('config_saved'));
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                        return;
                    }
                } catch(e) {}
                
                alert(_('save_error'));
            }
        });
    }
    
    // Initialiser au chargement
    initializeUsersConfig();
    
});
