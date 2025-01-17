jQuery(document).ready(function($) {
    'use strict';

    // Initialize media uploader for featured images
    var mediaUploader;
    
    $('.upload-image-button').on('click', function(e) {
        e.preventDefault();
        
        // If the media uploader already exists, open it
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        // Create the media uploader
        mediaUploader = wp.media({
            title: 'Choose Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });
        
        // When an image is selected, run a callback
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#gallery-image').val(attachment.url);
            $('#image-preview').attr('src', attachment.url).show();
        });
        
        // Open the uploader dialog
        mediaUploader.open();
    });

    // Handle date picker initialization
    if ($.fn.datepicker) {
        $('.gallery-date-picker').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true
        });
    }

    // Handle taxonomy quick edit
    var $inline_editor = inlineEditTax || {};

    // Store the original quickEdit function
    var $original_quick_edit = $inline_editor.edit;

    // Override the quick edit function
    $inline_editor.edit = function(id) {
        // Call the original quickEdit function
        $original_quick_edit.apply(this, arguments);

        // Get the ID
        var tag_id = 0;
        if (typeof(id) === 'object') {
            tag_id = parseInt(this.getId(id));
        }

        if (tag_id > 0) {
            // Get the row data
            var $row = $('#tag-' + tag_id);
            
            // Get the custom field values
            var customField = $row.find('.custom-field').text();
            
            // Set the values in the quick edit fields
            $('input[name="custom_field"]', '.inline-edit-row').val(customField);
        }
    };

    // Handle bulk edit functionality
    $('#bulk_edit').on('click', function() {
        // Get the selected items
        var selected = [];
        $('input[name="post[]"]:checked').each(function() {
            selected.push($(this).val());
        });

        // If items are selected
        if (selected.length > 0) {
            // Get the bulk edit values
            var bulkExhibition = $('#bulk-exhibition').val();
            var bulkArtist = $('#bulk-artist').val();

            // Prepare the data
            var data = {
                action: 'bulk_edit_gallery_items',
                post_ids: selected,
                exhibition: bulkExhibition,
                artist: bulkArtist,
                nonce: $('#gallery_bulk_edit_nonce').val()
            };

            // Send the request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        // Reload the page to show updated items
                        location.reload();
                    }
                }
            });
        }
    });

    // Handle meta box validation
    $('#post').on('submit', function(e) {
        var $required = $('.gallery-meta-box .required');
        var valid = true;

        $required.each(function() {
            if (!$(this).val()) {
                e.preventDefault();
                $(this).addClass('error');
                valid = false;
            } else {
                $(this).removeClass('error');
            }
        });

        if (!valid) {
            alert('Please fill in all required fields.');
        }

        return valid;
    });

    // Clear error class on input
    $('.gallery-meta-box .required').on('input', function() {
        $(this).removeClass('error');
    });
});
