<?php
/**
 * Plugin Name: Gallery Plugin
 * Description: A WordPress plugin for managing and displaying image galleries with advanced filtering capabilities.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL-2.0+
 * Text Domain: gallery-plugin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('GALLERY_PLUGIN_VERSION', '1.0.0');
define('GALLERY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GALLERY_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include the main plugin class
require_once GALLERY_PLUGIN_DIR . 'includes/class-gallery-plugin.php';

/**
 * Begins execution of the plugin.
 */
function run_gallery_plugin() {
    $plugin = new Gallery_Plugin();
    $plugin->run();
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'activate_gallery_plugin');
register_deactivation_hook(__FILE__, 'deactivate_gallery_plugin');

function activate_gallery_plugin() {
    // Register post type and taxonomies on activation
    $plugin_admin = new Gallery_Admin();
    $plugin_admin->register_post_type();
    $plugin_admin->register_taxonomies();
    
    // Clear the permalinks after the post type has been registered
    flush_rewrite_rules();
    
    // Set default options
    $default_options = array(
        'items_per_page' => 12,
        'enable_lightbox' => true,
        'grid_columns' => 3,
        'show_filters' => true
    );
    
    add_option('gallery_plugin_options', $default_options);
}

function deactivate_gallery_plugin() {
    // Unregister post type and taxonomies
    unregister_post_type('gallery_item');
    unregister_taxonomy('exhibition');
    unregister_taxonomy('artist');
    
    // Clear the permalinks
    flush_rewrite_rules();
}

// Run the plugin
run_gallery_plugin();
