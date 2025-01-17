<?php
/**
 * The admin-specific functionality of the plugin.
 */
class Gallery_Admin {

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_box_data'));
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'gallery-plugin-admin',
            GALLERY_PLUGIN_URL . 'admin/css/gallery-admin.css',
            array(),
            GALLERY_PLUGIN_VERSION,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'gallery-plugin-admin',
            GALLERY_PLUGIN_URL . 'admin/js/gallery-admin.js',
            array('jquery'),
            GALLERY_PLUGIN_VERSION,
            false
        );
    }

    /**
     * Register the custom post type for gallery items.
     */
    public function register_post_type() {
        $labels = array(
            'name'               => _x('Gallery Items', 'post type general name', 'gallery-plugin'),
            'singular_name'      => _x('Gallery Item', 'post type singular name', 'gallery-plugin'),
            'menu_name'          => _x('Gallery', 'admin menu', 'gallery-plugin'),
            'add_new'            => _x('Add New', 'gallery item', 'gallery-plugin'),
            'add_new_item'       => __('Add New Gallery Item', 'gallery-plugin'),
            'edit_item'          => __('Edit Gallery Item', 'gallery-plugin'),
            'new_item'           => __('New Gallery Item', 'gallery-plugin'),
            'view_item'          => __('View Gallery Item', 'gallery-plugin'),
            'search_items'       => __('Search Gallery Items', 'gallery-plugin'),
            'not_found'          => __('No gallery items found', 'gallery-plugin'),
            'not_found_in_trash' => __('No gallery items found in Trash', 'gallery-plugin'),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'gallery-item'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title', 'editor', 'thumbnail', 'excerpt'),
            'menu_icon'          => 'dashicons-format-gallery'
        );

        register_post_type('gallery_item', $args);
    }

    /**
     * Register custom taxonomies for Exhibition and Artist.
     */
    public function register_taxonomies() {
        // Register Exhibition taxonomy
        $exhibition_labels = array(
            'name'              => _x('Exhibitions', 'taxonomy general name', 'gallery-plugin'),
            'singular_name'     => _x('Exhibition', 'taxonomy singular name', 'gallery-plugin'),
            'search_items'      => __('Search Exhibitions', 'gallery-plugin'),
            'all_items'         => __('All Exhibitions', 'gallery-plugin'),
            'edit_item'         => __('Edit Exhibition', 'gallery-plugin'),
            'update_item'       => __('Update Exhibition', 'gallery-plugin'),
            'add_new_item'      => __('Add New Exhibition', 'gallery-plugin'),
            'new_item_name'     => __('New Exhibition Name', 'gallery-plugin'),
            'menu_name'         => __('Exhibitions', 'gallery-plugin'),
        );

        register_taxonomy('exhibition', array('gallery_item'), array(
            'hierarchical'      => true,
            'labels'            => $exhibition_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'exhibition'),
        ));

        // Register Artist taxonomy
        $artist_labels = array(
            'name'              => _x('Artists', 'taxonomy general name', 'gallery-plugin'),
            'singular_name'     => _x('Artist', 'taxonomy singular name', 'gallery-plugin'),
            'search_items'      => __('Search Artists', 'gallery-plugin'),
            'all_items'         => __('All Artists', 'gallery-plugin'),
            'edit_item'         => __('Edit Artist', 'gallery-plugin'),
            'update_item'       => __('Update Artist', 'gallery-plugin'),
            'add_new_item'      => __('Add New Artist', 'gallery-plugin'),
            'new_item_name'     => __('New Artist Name', 'gallery-plugin'),
            'menu_name'         => __('Artists', 'gallery-plugin'),
        );

        register_taxonomy('artist', array('gallery_item'), array(
            'hierarchical'      => true,
            'labels'            => $artist_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'artist'),
        ));
    }

    /**
     * Add meta boxes for additional image details.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'gallery_item_details',
            __('Gallery Item Details', 'gallery-plugin'),
            array($this, 'render_meta_box'),
            'gallery_item',
            'normal',
            'high'
        );
    }

    /**
     * Render the meta box content.
     */
    public function render_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('gallery_item_meta_box', 'gallery_item_meta_box_nonce');

        // Get existing meta values
        $image_date = get_post_meta($post->ID, '_gallery_image_date', true);
        $image_medium = get_post_meta($post->ID, '_gallery_image_medium', true);

        ?>
        <p>
            <label for="gallery_image_date"><?php _e('Image Date:', 'gallery-plugin'); ?></label>
            <input type="date" id="gallery_image_date" name="gallery_image_date" 
                   value="<?php echo esc_attr($image_date); ?>" class="widefat">
        </p>
        <p>
            <label for="gallery_image_medium"><?php _e('Medium:', 'gallery-plugin'); ?></label>
            <input type="text" id="gallery_image_medium" name="gallery_image_medium" 
                   value="<?php echo esc_attr($image_medium); ?>" class="widefat">
        </p>
        <?php
    }

    /**
     * Save the meta box data.
     */
    public function save_meta_box_data($post_id) {
        // Check if our nonce is set and verify it
        if (!isset($_POST['gallery_item_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['gallery_item_meta_box_nonce'], 'gallery_item_meta_box')) {
            return;
        }

        // If this is an autosave, don't do anything
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save the meta box data
        if (isset($_POST['gallery_image_date'])) {
            update_post_meta($post_id, '_gallery_image_date', 
                sanitize_text_field($_POST['gallery_image_date']));
        }

        if (isset($_POST['gallery_image_medium'])) {
            update_post_meta($post_id, '_gallery_image_medium', 
                sanitize_text_field($_POST['gallery_image_medium']));
        }
    }
}
