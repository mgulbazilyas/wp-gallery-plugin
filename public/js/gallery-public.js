jQuery(document).ready(function($) {
    'use strict';

    // Cache DOM elements
    var $container = $('.gallery-container');
    var $grid = $('.gallery-grid');
    var $sidebar = $('.gallery-detail-sidebar');
    var $filterInputs = $('.filter-options input[type="checkbox"]');
    var $searchInputs = $('.filter-search');
    var currentItemId = null;

    // Cache for all gallery items
    var itemsCache = {};
    var displayedItems = [];

    // Load all items on page load
    function loadAllItems() {
        $.ajax({
            url: galleryPluginAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_all_items',
                nonce: galleryPluginAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    itemsCache = response.data.items;
                    displayedItems = Object.values(itemsCache);
                    renderGalleryItems(displayedItems);
                    initializeItems();
                }
            }
        });
    }

    // Initialize filtering
    function updateGallery() {
        var filters = {
            exhibition: [],
            artist: []
        };

        // Collect selected filters
        $filterInputs.each(function() {
            if ($(this).is(':checked')) {
                var filterType = $(this).attr('name').replace('[]', '');
                filters[filterType].push($(this).val());
            }
        });

        // Filter items client-side
        var filteredItems = Object.values(itemsCache).filter(function(item) {
            var matchesExhibition = filters.exhibition.length === 0 || 
                                  filters.exhibition.includes(item.exhibition_slug);
            var matchesArtist = filters.artist.length === 0 || 
                               filters.artist.includes(item.artist_slug);
            return matchesExhibition && matchesArtist;
        });

        displayedItems = filteredItems;
        renderGalleryItems(filteredItems);
        initializeItems();
    }

    // Render gallery items
    function renderGalleryItems(items) {
        if (items.length === 0) {
            $grid.html('<p class="no-items">No gallery items found.</p>');
            return;
        }

        var html = items.map(function(item) {
            return `
                <div class="gallery-item" data-id="${item.id}">
                    <div class="gallery-item-image">
                        <img src="${item.image}" alt="${item.title}">
                    </div>
                    <div class="gallery-item-info">
                        <h3>${item.title}</h3>
                        ${item.artist ? `<p class="artist">${item.artist}</p>` : ''}
                        ${item.exhibition ? `<p class="exhibition">${item.exhibition}</p>` : ''}
                    </div>
                </div>
            `;
        }).join('');

        $grid.html(html);
    }

    // Handle filter changes
    $filterInputs.on('change', updateGallery);

    // Handle filter search
    $searchInputs.on('input', function() {
        var searchTerm = $(this).val().toLowerCase();
        var filterType = $(this).data('filter');
        
        $('.filter-options input[name="' + filterType + '[]"]').each(function() {
            var $label = $(this).parent();
            var labelText = $label.text().toLowerCase();
            
            if (labelText.includes(searchTerm)) {
                $label.show();
            } else {
                $label.hide();
            }
        });
    });

    // Initialize gallery items
    function initializeItems() {
        $('.gallery-item').on('click', function() {
            var itemId = $(this).data('id');
            showItemDetails(itemId);
        });
    }

    // Show item details in sidebar
    function showItemDetails(itemId) {
        currentItemId = itemId;
        var item = itemsCache[itemId];

        if (item) {
            var content = `
                <img src="${item.image}" alt="${item.title}">
                <h2>${item.title}</h2>
                ${item.is_admin ? `<a href="${item.edit_url}" class="edit-button" target="_blank">Edit</a>` : ''}
                <p class="artist"><strong>Artist:</strong> ${item.artist}</p>
                <p class="exhibition"><strong>Exhibition:</strong> ${item.exhibition}</p>
                ${item.date ? `<p class="date"><strong>Date:</strong> ${item.date}</p>` : ''}
                ${item.medium ? `<p class="medium"><strong>Medium:</strong> ${item.medium}</p>` : ''}
                <div class="description">${item.description}</div>
            `;
            
            $('.detail-content').html(content);
            $sidebar.addClass('active');
            updateNavigation();
        }
    }

    // Handle navigation between items
    function updateNavigation() {
        var currentIndex = displayedItems.findIndex(item => item.id == currentItemId);
        
        $('.nav-prev').toggleClass('disabled', currentIndex <= 0);
        $('.nav-next').toggleClass('disabled', currentIndex >= displayedItems.length - 1);
    }

    $('.nav-prev').on('click', function() {
        if ($(this).hasClass('disabled')) return;
        
        var currentIndex = displayedItems.findIndex(item => item.id == currentItemId);
        if (currentIndex > 0) {
            showItemDetails(displayedItems[currentIndex - 1].id);
        }
    });

    $('.nav-next').on('click', function() {
        if ($(this).hasClass('disabled')) return;
        
        var currentIndex = displayedItems.findIndex(item => item.id == currentItemId);
        if (currentIndex < displayedItems.length - 1) {
            showItemDetails(displayedItems[currentIndex + 1].id);
        }
    });

    // Close sidebar
    $('.close-sidebar').on('click', function() {
        $sidebar.removeClass('active');
        currentItemId = null;
    });

    // Handle keyboard navigation
    $(document).on('keydown', function(e) {
        if (!$sidebar.hasClass('active')) return;

        switch(e.keyCode) {
            case 37: // Left arrow
                $('.nav-prev').trigger('click');
                break;
            case 39: // Right arrow
                $('.nav-next').trigger('click');
                break;
            case 27: // Escape
                $('.close-sidebar').trigger('click');
                break;
        }
    });

    // Handle responsive sidebar
    $(window).on('resize', function() {
        if (window.innerWidth <= 768) {
            $sidebar.css('top', window.scrollY + 'px');
        } else {
            $sidebar.css('top', '0');
        }
    });

    // Load all items on page load
    loadAllItems();
});
