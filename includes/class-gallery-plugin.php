<?php
/**
 * The core plugin class.
 */

class Gallery_Plugin {

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     */
    protected $loader;

    /**
     * Initialize the plugin and set up hooks.
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        require_once GALLERY_PLUGIN_DIR . 'includes/class-gallery-loader.php';
        require_once GALLERY_PLUGIN_DIR . 'includes/class-gallery-admin.php';
        require_once GALLERY_PLUGIN_DIR . 'includes/class-gallery-frontend.php';
        require_once GALLERY_PLUGIN_DIR . 'includes/class-gallery-scripts.php';

        $this->loader = new Gallery_Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     */
    private function define_admin_hooks() {
        $plugin_admin = new Gallery_Admin();
        $plugin_scripts = new Gallery_Scripts();

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
    }

    /**
     * Register all of the hooks related to the public-facing functionality.
     */
    private function define_public_hooks() {
        $plugin_public = new Gallery_Frontend();

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        
        // Register shortcodes immediately
        $plugin_public->register_shortcodes();
        
        // Also register on init in case it's needed
        $this->loader->add_action('init', $plugin_public, 'register_shortcodes');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run() {
        $this->loader->run();
    }
}
