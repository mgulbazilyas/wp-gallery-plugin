<?php
/**
 * The public-facing functionality of the plugin.
 */
class Gallery_Frontend {

    /**
     * Initialize the class.
     */
    public function __construct() {
        add_action('wp_ajax_filter_gallery', array($this, 'filter_gallery'));
        add_action('wp_ajax_nopriv_filter_gallery', array($this, 'filter_gallery'));
        add_action('wp_ajax_get_all_items', array($this, 'get_all_items'));
        add_action('wp_ajax_nopriv_get_all_items', array($this, 'get_all_items'));
        add_action('wp_ajax_search_exhibitions', array($this, 'search_exhibitions'));
        add_action('wp_ajax_nopriv_search_exhibitions', array($this, 'search_exhibitions'));
        add_action('wp_ajax_search_artists', array($this, 'search_artists'));
        add_action('wp_ajax_nopriv_search_artists', array($this, 'search_artists'));
    }

    /**
     * AJAX handler for searching exhibitions
     */
    public function search_exhibitions() {
        check_ajax_referer('gallery_filter_nonce', 'nonce');
        
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        
        $terms = get_terms(array(
            'taxonomy' => 'exhibition',
            'hide_empty' => true,
            'search' => $search,
        ));

        $results = array();
        foreach ($terms as $term) {
            $results[] = array(
                'id' => $term->slug,
                'text' => $term->name
            );
        }

        wp_send_json(array('results' => $results));
    }

    /**
     * AJAX handler for searching artists
     */
    public function search_artists() {
        check_ajax_referer('gallery_filter_nonce', 'nonce');
        
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        
        $terms = get_terms(array(
            'taxonomy' => 'artist',
            'hide_empty' => false,
            'search' => $search,
        ));

        $results = array();
        foreach ($terms as $term) {
            $results[] = array(
                'id' => $term->slug,
                'text' => $term->name
            );
        }

        wp_send_json(array('results' => $results));
    }

    /**
     * Get all gallery items with their details
     */
    public function get_all_items() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gallery_filter_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $args = array(
            'post_type' => 'gallery_item',
            'posts_per_page' => -1,
        );

        $query = new WP_Query($args);
        $items = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $artist_terms = wp_get_post_terms($post_id, 'artist');
                $exhibition_terms = wp_get_post_terms($post_id, 'exhibition');
                
                $items[$post_id] = array(
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'image' => get_the_post_thumbnail_url($post_id, 'full'),
                    'artist' => !empty($artist_terms) ? $artist_terms[0]->name : '',
                    'artist_slug' => !empty($artist_terms) ? $artist_terms[0]->slug : '',
                    'exhibition' => !empty($exhibition_terms) ? $exhibition_terms[0]->name : '',
                    'exhibition_slug' => !empty($exhibition_terms) ? $exhibition_terms[0]->slug : '',
                    'date' => get_post_meta($post_id, '_gallery_image_date', true),
                    'medium' => get_post_meta($post_id, '_gallery_image_medium', true),
                    'description' => get_the_content(),
                    'is_admin' => current_user_can('edit_posts'),
                    'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit')
                );
            }
        }

        wp_reset_postdata();
        wp_send_json_success(array('items' => $items));
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'gallery-plugin-public',
            GALLERY_PLUGIN_URL . 'public/css/gallery-public.css',
            array(),
            GALLERY_PLUGIN_VERSION,
            'all'
        );
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     */
    public function enqueue_scripts() {
        // Enqueue Select2
        wp_enqueue_style(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css'
        );
        
        wp_enqueue_script(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            array('jquery'),
            '4.1.0',
            true
        );

        wp_enqueue_script(
            'gallery-plugin-public',
            GALLERY_PLUGIN_URL . 'public/js/gallery-public.js',
            array('jquery', 'select2'),
            GALLERY_PLUGIN_VERSION,
            true
        );

        wp_localize_script(
            'gallery-plugin-public',
            'galleryPluginAjax',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gallery_filter_nonce')
            )
        );
    }

    /**
     * Register shortcodes.
     */
    public function register_shortcodes() {
        add_shortcode('gallery_display', array($this, 'gallery_shortcode'));
        // Also register for the gallery shortcode with IDs
        add_shortcode('gallery', array($this, 'gallery_shortcode'));
    }

    /**
     * Shortcode callback function.
     */
    public function gallery_shortcode($atts = array(), $content = null) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'ids' => '',
        ), $atts, 'gallery_display');
        // Enqueue required scripts and styles
        wp_enqueue_style('gallery-plugin-public');
        wp_enqueue_script('gallery-plugin-public');

        // Get all exhibitions and artists for filters
        $exhibitions = get_terms(array(
            'taxonomy' => 'exhibition',
            'hide_empty' => true,
        ));

        // If specific IDs are provided, we'll use them later in get_gallery_items
        $specific_ids = !empty($atts['ids']) ? explode(',', $atts['ids']) : array();

        $artists = get_terms(array(
            'taxonomy' => 'artist',
            'hide_empty' => true,
        ));

        // Start output buffering
        ob_start();
        ?>
        <div class="gallery-container">
            <!-- Filters Section -->
            <div class="gallery-filters">
                <div class="filter-section">
                    <h3><?php _e('Exhibitions', 'gallery-plugin'); ?></h3>
                    <select class="filter-select" name="exhibition[]" multiple="multiple" data-placeholder="<?php _e('Select exhibitions...', 'gallery-plugin'); ?>">
                        <?php foreach ($exhibitions as $exhibition) : ?>
                            <option value="<?php echo esc_attr($exhibition->slug); ?>">
                                <?php echo esc_html($exhibition->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-section">
                    <h3><?php _e('Artists', 'gallery-plugin'); ?></h3>
                    <select class="filter-select" name="artist[]" multiple="multiple" data-placeholder="<?php _e('Select artists...', 'gallery-plugin'); ?>">
                        <?php foreach ($artists as $artist) : ?>
                            <option value="<?php echo esc_attr($artist->slug); ?>">
                                <?php echo esc_html($artist->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Gallery Grid -->
            <div class="gallery-grid">
                <?php echo $this->get_gallery_items(array(), $specific_ids); ?>
            </div>

            <!-- Detail Sidebar -->
            <div class="gallery-detail-sidebar">
                <button class="close-sidebar">&times;</button>
                <div class="detail-content"></div>
                <div class="detail-navigation">
                    <button class="nav-prev"><?php _e('Previous', 'gallery-plugin'); ?></button>
                    <button class="nav-next"><?php _e('Next', 'gallery-plugin'); ?></button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get gallery items HTML.
     */
    private function get_gallery_items($filters = array(), $specific_ids = array()) {
        $args = array(
            'post_type' => 'gallery_item',
            'posts_per_page' => -1,
            'tax_query' => array(),
        );

        // If specific IDs are provided, use them
        if (!empty($specific_ids)) {
            $args['post__in'] = array_map('intval', $specific_ids);
            $args['orderby'] = 'post__in';
        }

        // Add taxonomy filters if present
        if (!empty($filters['exhibition'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'exhibition',
                'field' => 'slug',
                'terms' => $filters['exhibition'],
            );
        }

        if (!empty($filters['artist'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'artist',
                'field' => 'slug',
                'terms' => $filters['artist'],
            );
        }

        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        $query = new WP_Query($args);
        ob_start();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $image = get_the_post_thumbnail_url($post_id, 'large');
                $artist_terms = wp_get_post_terms($post_id, 'artist');
                $exhibition_terms = wp_get_post_terms($post_id, 'exhibition');
                ?>
                <div class="gk-gallery-item" data-id="<?php echo esc_attr($post_id); ?>">
                    <div class="gk-gallery-item-image">
                        <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr(get_the_title()); ?>">
                    </div>
                    <div class="gk-gallery-item-info">
                        <h3><?php the_title(); ?></h3>
                        <?php if (!empty($artist_terms)) : ?>
                            <p class="artist"><?php echo esc_html($artist_terms[0]->name); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($exhibition_terms)) : ?>
                            <p class="exhibition"><?php echo esc_html($exhibition_terms[0]->name); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
        } else {
            echo '<p class="no-items">' . __('No gallery items found.', 'gallery-plugin') . '</p>';
        }

        wp_reset_postdata();
        return ob_get_clean();
    }

    /**
     * AJAX handler for filtering gallery items.
     */
    public function filter_gallery() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gallery_filter_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $filters = array(
            'exhibition' => isset($_POST['exhibition']) ? array_map('sanitize_text_field', $_POST['exhibition']) : array(),
            'artist' => isset($_POST['artist']) ? array_map('sanitize_text_field', $_POST['artist']) : array(),
        );

        $html = $this->get_gallery_items($filters);
        wp_send_json_success(array('html' => $html));
    }

    /**
     * Get item details for the sidebar.
     */
    public function get_item_details() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gallery_filter_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
        }

        $image = get_the_post_thumbnail_url($post_id, 'full');
        $artist_terms = wp_get_post_terms($post_id, 'artist');
        $exhibition_terms = wp_get_post_terms($post_id, 'exhibition');
        $image_date = get_post_meta($post_id, '_gallery_image_date', true);
        $image_medium = get_post_meta($post_id, '_gallery_image_medium', true);

        $data = array(
            'title' => get_the_title($post_id),
            'image' => $image,
            'artist' => !empty($artist_terms) ? $artist_terms[0]->name : '',
            'exhibition' => !empty($exhibition_terms) ? $exhibition_terms[0]->name : '',
            'date' => $image_date,
            'medium' => $image_medium,
            'description' => get_the_content(null, false, $post_id),
            'is_admin' => current_user_can('edit_posts'),
            'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit')
        );

        wp_send_json_success($data);
    }
}
