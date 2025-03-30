<?php
/**
 * Class for managing custom scripts and WP-CLI commands
 */
class Gallery_Scripts {
    /**
     * Initialize the class
     */
    public function __construct() {
        // Add settings page
        // add_action('admin_menu', array($this, 'add_scripts_settings_page'));
        
        // Register WP-CLI commands if WP-CLI is available
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('gallery-plugin test', array($this, 'test_script_cli'));
            WP_CLI::add_command('gallery-plugin process-products', array($this, 'process_products_cli'));
        }
    }

    /**
     * Add scripts settings page to admin menu
     */
    public function add_scripts_settings_page() {
        add_submenu_page(
            'edit.php?post_type=gallery_item',
            __('Scripts Management', 'gallery-plugin'),
            __('Scripts', 'gallery-plugin'),
            'manage_options',
            'gallery-scripts',
            array($this, 'render_scripts_page')
        );
    }

    /**
     * Render the scripts settings page
     */
    public function render_scripts_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Process form submission
        if (isset($_POST['run_test_script']) && check_admin_referer('gallery_scripts_action')) {
            $result = $this->run_test_script();
            $message = $result['success'] ? 
                      '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>' : 
                      '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
        }

        if (isset($_POST['process_products']) && check_admin_referer('gallery_scripts_action')) {
            $result = $this->process_products();
            $message = $result['success'] ? 
                      '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>' : 
                      '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
        }
        if(isset($_POST['process_trashed_products']) && check_admin_referer('gallery_scripts_action')){
            $result = $this->process_trashed_products();
            $message = $result['success'] ? 
                      '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>' : 
                      '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (isset($message)) echo $message; ?>

            <div class="card">
                <h2><?php _e('Available Scripts', 'gallery-plugin'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('gallery_scripts_action'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Test Script', 'gallery-plugin'); ?></th>
                            <td>
                                <p class="description">
                                    <?php _e('Runs a test script that counts gallery items and performs basic system checks.', 'gallery-plugin'); ?>
                                </p>
                                <input type="submit" name="run_test_script" class="button button-primary" 
                                       value="<?php _e('Run Test Script', 'gallery-plugin'); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Process Products', 'gallery-plugin'); ?></th>
                            <td>
                                <p class="description">
                                    <?php _e('Process products and create artist categories.', 'gallery-plugin'); ?>
                                </p>
                                <input type="submit" name="process_products" class="button button-primary" 
                                        value="<?php _e('Process Products', 'gallery-plugin'); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Sync Collections', 'gallery-plugin'); ?></th>
                            <td>
                                <p class="description">
                                    <?php _e('Sync collections and update artist information.', 'gallery-plugin'); ?>
                                </p>
                                <input type="submit" name="process_trashed_products" class="button button-primary" 
                                    value="<?php _e('Sync Collections', 'gallery-plugin'); ?>">
                            </td>
                        </tr>

                    </table>
                </form>
            </div>

            <div class="card">
                <h2><?php _e('WP-CLI Commands', 'gallery-plugin'); ?></h2>
                <p><?php _e('Available WP-CLI commands:', 'gallery-plugin'); ?></p>
                <code>wp gallery-plugin test</code> - <?php _e('Run the test script via CLI', 'gallery-plugin'); ?>
                <code>wp gallery-plugin process-products</code> - <?php _e('Process products and create artist categories via CLI', 'gallery-plugin'); ?>
                <code>wp gallery-plugin process-trashed-products</code> - <?php _e('Process Trashed products and create artist categories via CLI', 'gallery-plugin'); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Run test script
     * 
     * @return array Array containing success status and message
     */
    public function run_test_script() {
        try {
            // Count gallery items
            $gallery_items = wp_count_posts('gallery_item');
            $total_items = $gallery_items->publish + $gallery_items->draft;

            // Get taxonomy terms count
            $exhibitions_count = wp_count_terms('exhibition');
            $artists_count = wp_count_terms('artist');

            // System information
            $wp_version = get_bloginfo('version');
            $php_version = PHP_VERSION;

            $message = sprintf(
                __('Test completed successfully! Found %d gallery items, %d exhibitions, and %d artists. WordPress v%s, PHP v%s', 'gallery-plugin'),
                $total_items,
                $exhibitions_count,
                $artists_count,
                $wp_version,
                $php_version
            );

            return array(
                'success' => true,
                'message' => $message
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Process products and create artist categories
     * 
     * @return array Array containing success status and message
     */
    public function process_products() {
        try {
            // Get all products
            $products = get_posts(array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            ));
            // Create parent category "Artist" if it doesn't exist
            $artist_category = term_exists('Artist', 'product_cat');
            if (!$artist_category) {
                $artist_category = wp_insert_term('Artist', 'product_cat');
            }

            // Process each product
            foreach ($products as $product) {
                // Get product title and explode it with "-"
                $title_parts = explode('-', $product->post_title);
                if (count($title_parts) > 0) {
                    $artist_name = trim($title_parts[0]);

                    // Create category under "Artist" if it doesn't exist
                    $artist_term = term_exists($artist_name, 'product_cat');
                    if (!$artist_term) {
                        $artist_term = wp_insert_term($artist_name, 'product_cat', array(
                            'parent' => $artist_category['term_id']
                        ));
                    }

                    // Attach category to product
                    if (!wp_get_post_terms($product->ID, 'product_cat')) {
                        wp_set_post_terms($product->ID, array($artist_term['term_id']), 'product_cat', true);
                    } else {
                        $existing_terms = wp_get_post_terms($product->ID, 'product_cat');
                        $existing_term_ids = wp_list_pluck($existing_terms, 'term_id');
                        $existing_term_ids[] = $artist_term['term_id'];
                        wp_set_post_terms($product->ID, $existing_term_ids, 'product_cat', false);
                    }
                }
            }

            $message = __('Products processed successfully and artist categories created.', 'gallery-plugin');

            return array(
                'success' => true,
                'message' => $message
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    public function process_trashed_products(){
        // Get trashed products
$trashed_products = get_posts(array(
    'post_type' => 'product',
    'post_status' => 'trash',
    'posts_per_page' => -1
));

// Process each trashed product
foreach ($trashed_products as $product) {
    // Update post type to 'gallery_item'
    $result = wp_update_post(array(
        'ID' => $product->ID,
        'post_type' => 'gallery_item'
    ));

    if ($result) {
        // Get product title and explode it with "-"
        $title_parts = explode('-', $product->post_title);
        if (count($title_parts) > 0) {
            $artist_name = trim($title_parts[0]);

            // Attach product to 'exhibition' taxonomy
            $exhibition_term_id = 241; // Assuming the ID of the first found exhibition is always 241
            wp_set_post_terms($product->ID, array($exhibition_term_id), 'exhibition', true);

            // Attach product to 'artist' taxonomy
            $artist_term = term_exists($artist_name, 'artist');
            if ($artist_term && is_array($artist_term)) {
                $term_id = $artist_term['term_id'];
            } else {
                $result = wp_insert_term($artist_name, 'artist');
                if (is_wp_error($result)) {
                    // Handle the error
                    continue;
                }
                $term_id = $result['term_id'];
            }

            wp_set_post_terms($product->ID, array($term_id), 'artist', true);
        }
    }
}
    }

    

    /**
     * WP-CLI command callback for test script
     */
    public function test_script_cli() {
        $result = $this->run_test_script();
        if ($result['success']) {
            WP_CLI::success($result['message']);
        } else {
            WP_CLI::error($result['message']);
        }
    }

    /**
     * WP-CLI command callback for process products
     */
    public function process_products_cli() {
        $result = $this->process_products();
        if ($result['success']) {
            WP_CLI::success($result['message']);
        } else {
            WP_CLI::error($result['message']);
        }
    }
}