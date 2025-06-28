<?php
/**
 * Plugin Name: Woo2Shopify - WooCommerce to Shopify Migration Tool
 * Plugin URI: https://github.com/tufantas/woo2shopify
 * Description: Comprehensive tool to migrate products, images, videos, and data from WooCommerce to Shopify
 * Version: 1.0.0
 * Author: Tufan Taş
 * Author URI: mailto:tufantas@gmail.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo2shopify
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * Contact: tufantas@gmail.com
 * Support: https://github.com/tufantas/woo2shopify/issues
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check minimum requirements
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo __('Woo2Shopify requires PHP 7.4 or higher. You are running PHP ' . PHP_VERSION, 'woo2shopify');
        echo '</p></div>';
    });
    return;
}

// WooCommerce check will be done in plugins_loaded hook

// Define plugin constants
define('WOO2SHOPIFY_VERSION', '1.0.0');
define('WOO2SHOPIFY_PLUGIN_FILE', __FILE__);
define('WOO2SHOPIFY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOO2SHOPIFY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOO2SHOPIFY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Woo2Shopify Class
 */
class Woo2Shopify {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        try {
            $this->init_hooks();
            $this->includes();
            // init() will be called in plugins_loaded hook
        } catch (Exception $e) {
            error_log('Woo2Shopify: Constructor error - ' . $e->getMessage());
            add_action('admin_notices', array($this, 'show_constructor_error'));
        }
    }

    /**
     * Show constructor error notice
     */
    public function show_constructor_error() {
        echo '<div class="notice notice-error"><p>';
        echo __('Woo2Shopify: Plugin initialization failed. Please check error logs.', 'woo2shopify');
        echo '</p></div>';
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('woo2shopify', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }


    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    /**
     * Include required files
     */
    private function includes() {
        $required_files = array(
            'includes/functions.php', // Load functions first
            'includes/class-woo2shopify-admin.php',
            'includes/class-woo2shopify-shopify-api.php',
            'includes/class-woo2shopify-woocommerce-reader.php',
            'includes/class-woo2shopify-data-mapper.php',
            'includes/class-woo2shopify-image-migrator.php',
            'includes/class-woo2shopify-video-processor.php',
            'includes/class-woo2shopify-batch-processor.php',
            'includes/class-woo2shopify-enhanced-batch-processor.php',
            'includes/class-woo2shopify-selective-migrator.php',
            'includes/class-woo2shopify-logger.php',
            'includes/class-woo2shopify-database.php'
        );

        foreach ($required_files as $file) {
            $file_path = WOO2SHOPIFY_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log('Woo2Shopify: Required file not found - ' . $file_path);
            }
        }


    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            return;
        }

        // Database and ActionScheduler optimizations
        $this->optimize_database_connections();

        // Initialize admin interface
        if (is_admin()) {
            new Woo2Shopify_Admin();
        }
        
        // Initialize AJAX handlers
        add_action('wp_ajax_woo2shopify_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_woo2shopify_start_migration', array($this, 'ajax_start_migration'));
        add_action('wp_ajax_woo2shopify_get_progress', array($this, 'ajax_get_progress'));
        add_action('wp_ajax_woo2shopify_stop_migration', array($this, 'ajax_stop_migration'));
        add_action('wp_ajax_woo2shopify_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_woo2shopify_test_product_count', array($this, 'ajax_test_product_count'));
        add_action('wp_ajax_woo2shopify_test_database', array($this, 'ajax_test_database'));
        add_action('wp_ajax_woo2shopify_create_tables', array($this, 'ajax_create_tables'));

        // New selective migration AJAX handlers
        add_action('wp_ajax_woo2shopify_get_products_for_selection', array($this, 'ajax_get_products_for_selection'));
        add_action('wp_ajax_woo2shopify_get_pages_for_selection', array($this, 'ajax_get_pages_for_selection'));
        add_action('wp_ajax_woo2shopify_migrate_selected_products', array($this, 'ajax_migrate_selected_products'));
        add_action('wp_ajax_woo2shopify_migrate_selected_pages', array($this, 'ajax_migrate_selected_pages'));
        add_action('wp_ajax_woo2shopify_start_enhanced_migration', array($this, 'ajax_start_enhanced_migration'));
        add_action('wp_ajax_woo2shopify_get_enhanced_progress', array($this, 'ajax_get_enhanced_progress'));

        // Initialize cron hooks
        add_action('woo2shopify_process_batch', array($this, 'handle_batch_processing'), 10, 2);
        add_action('woo2shopify_process_enhanced_batch', array($this, 'handle_enhanced_batch_processing'), 10, 2);
    }

    /**
     * Optimize database connections and ActionScheduler
     */
    private function optimize_database_connections() {
        // Reduce ActionScheduler batch size (only if WooCommerce is active)
        if (class_exists('WooCommerce')) {
            add_filter('action_scheduler_queue_runner_batch_size', function($batch_size) {
                return min($batch_size, 5);
            });

            add_filter('action_scheduler_queue_runner_time_limit', function($time_limit) {
                return 20; // 20 seconds timeout
            });

            // ActionScheduler memory limit kontrolü
            add_filter('action_scheduler_queue_runner_concurrent_batches', function($concurrent_batches) {
                return 1; // Only 1 batch at a time
            });
        }

        // MySQL connection optimization
        add_action('init', function() {
            global $wpdb;

            if (isset($wpdb->dbh) && is_object($wpdb->dbh)) {
                try {
                    // Soften MySQL strict mode
                    $wpdb->query("SET SESSION sql_mode = ''");

                    // Optimize query cache
                    $wpdb->query("SET SESSION query_cache_type = ON");
                } catch (Exception $e) {
                    error_log('Woo2Shopify: MySQL optimization failed - ' . $e->getMessage());
                }
            }
        }, 1);

        // Optimize database flush operation
        add_action('shutdown', function() {
            global $wpdb;
            if (isset($wpdb->dbh)) {
                $wpdb->flush();
            }
        }, 999);
    }

    /**
     * Plugin activation
     */
    public function activate() {
        try {
            error_log('Woo2Shopify: Starting plugin activation...');

            // Check if we're in a valid WordPress environment
            if (!function_exists('get_option')) {
                throw new Exception('WordPress functions not available');
            }

            // Ensure database class is loaded
            if (!class_exists('Woo2Shopify_Database')) {
                $db_file = WOO2SHOPIFY_PLUGIN_DIR . 'includes/class-woo2shopify-database.php';
                if (!file_exists($db_file)) {
                    throw new Exception('Database class file not found: ' . $db_file);
                }
                require_once $db_file;
            }

            // Create database tables
            error_log('Woo2Shopify: Creating database tables...');
            $result = Woo2Shopify_Database::create_tables();

            if (!$result) {
                error_log('Woo2Shopify: Failed to create database tables during activation');
                // Don't throw exception, continue with activation
            } else {
                error_log('Woo2Shopify: Database tables created successfully');
            }

            // Set default options
            error_log('Woo2Shopify: Setting default options...');
            $this->set_default_options();

            // Schedule cleanup cron job
            if (!wp_next_scheduled('woo2shopify_cleanup')) {
                wp_schedule_event(time(), 'daily', 'woo2shopify_cleanup');
                error_log('Woo2Shopify: Cleanup cron scheduled');
            }

            // Flush rewrite rules
            flush_rewrite_rules();

            // Log successful activation
            error_log('Woo2Shopify: Plugin activated successfully');

        } catch (Exception $e) {
            error_log('Woo2Shopify: Activation error - ' . $e->getMessage());
            error_log('Woo2Shopify: Stack trace - ' . $e->getTraceAsString());

            // Show user-friendly error message
            wp_die(
                '<h1>Plugin Activation Error</h1>' .
                '<p><strong>Woo2Shopify</strong> could not be activated due to an error:</p>' .
                '<p><code>' . esc_html($e->getMessage()) . '</code></p>' .
                '<p>Please check the error logs and try again.</p>' .
                '<p><a href="' . admin_url('plugins.php') . '" class="button">← Back to Plugins</a></p>',
                'Plugin Activation Error',
                array('back_link' => true)
            );
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        try {
            error_log('Woo2Shopify: Starting plugin deactivation...');

            // Clear scheduled events
            wp_clear_scheduled_hook('woo2shopify_cleanup');
            wp_clear_scheduled_hook('woo2shopify_process_batch');
            wp_clear_scheduled_hook('woo2shopify_process_enhanced_batch');

            // Flush rewrite rules
            flush_rewrite_rules();

            error_log('Woo2Shopify: Plugin deactivated successfully');

        } catch (Exception $e) {
            error_log('Woo2Shopify: Deactivation error - ' . $e->getMessage());
        }
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        if (!$this->is_woocommerce_active()) {
            echo '<div class="notice notice-error"><p>';
            echo __('Woo2Shopify requires WooCommerce to be installed and activated.', 'woo2shopify');
            echo '</p></div>';
        }
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        // Check multiple ways to ensure WooCommerce is really active
        if (class_exists('WooCommerce')) {
            return true;
        }

        // Check if WooCommerce plugin is in active plugins list
        if (function_exists('is_plugin_active')) {
            return is_plugin_active('woocommerce/woocommerce.php');
        }

        // Fallback check
        $active_plugins = get_option('active_plugins', array());
        return in_array('woocommerce/woocommerce.php', $active_plugins);
    }
    

    
    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = array(
            'shopify_store_url' => '',
            'shopify_access_token' => '',
            'batch_size' => 10,
            'image_quality' => 80,
            'include_variations' => true,
            'include_categories' => true,
            'include_tags' => true,
            'include_meta' => true,
            'debug_mode' => false
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option('woo2shopify_' . $key) === false) {
                add_option('woo2shopify_' . $key, $value);
            }
        }
    }
    
    /**
     * AJAX: Test Shopify connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'woo2shopify'));
            return;
        }

        try {
            $shopify_api = new Woo2Shopify_Shopify_API();
            $result = $shopify_api->test_connection();

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'error_details' => array(
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                )
            ));
        }
    }
    
    /**
     * AJAX: Start migration
     */
    public function ajax_start_migration() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'woo2shopify'));
            return;
        }

        try {
            // Get migration options from POST data
            $options = array(
                'include_images' => isset($_POST['include_images']) ? (bool)$_POST['include_images'] : true,
                'include_videos' => isset($_POST['include_videos']) ? (bool)$_POST['include_videos'] : true,
                'include_variations' => isset($_POST['include_variations']) ? (bool)$_POST['include_variations'] : true,
                'include_categories' => isset($_POST['include_categories']) ? (bool)$_POST['include_categories'] : true,
                'include_translations' => isset($_POST['include_translations']) ? (bool)$_POST['include_translations'] : true,
                'batch_size' => isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 3
            );

            $batch_processor = new Woo2Shopify_Batch_Processor();
            $result = $batch_processor->start_migration($options);

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'error_details' => array(
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                )
            ));
        }
    }
    
    /**
     * AJAX: Get migration progress
     */
    public function ajax_get_progress() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'woo2shopify'));
        }

        $migration_id = sanitize_text_field($_POST['migration_id'] ?? '');
        $batch_processor = new Woo2Shopify_Batch_Processor();
        $progress = $batch_processor->get_progress($migration_id);

        wp_send_json($progress);
    }

    /**
     * AJAX: Stop migration
     */
    public function ajax_stop_migration() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'woo2shopify'));
        }

        $migration_id = sanitize_text_field($_POST['migration_id'] ?? '');
        $batch_processor = new Woo2Shopify_Batch_Processor();
        $result = $batch_processor->stop_migration($migration_id);

        wp_send_json($result);
    }

    /**
     * AJAX: Clear logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'woo2shopify'));
        }

        $logger = new Woo2Shopify_Logger();
        $result = $logger->clear_logs();

        if ($result) {
            wp_send_json_success(__('Logs cleared successfully', 'woo2shopify'));
        } else {
            wp_send_json_error(__('Failed to clear logs', 'woo2shopify'));
        }
    }

    /**
     * AJAX: Test product count (debug)
     */
    public function ajax_test_product_count() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'woo2shopify'));
            return;
        }

        try {
            $wc_reader = new Woo2Shopify_WooCommerce_Reader();

            // Test different methods
            $count_method1 = $wc_reader->get_product_count();

            // Direct WC method
            $wc_products = wc_get_products(array(
                'status' => array('publish', 'draft', 'private'),
                'return' => 'ids',
                'limit' => -1
            ));
            $count_method2 = count($wc_products);

            // WordPress post count
            $post_counts = wp_count_posts('product');
            $count_method3 = $post_counts->publish + $post_counts->draft + $post_counts->private;

            wp_send_json_success(array(
                'method1_custom' => $count_method1,
                'method2_wc_get_products' => $count_method2,
                'method3_wp_count_posts' => $count_method3,
                'post_counts_detail' => $post_counts,
                'message' => 'Product count comparison completed'
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'error_details' => array(
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                )
            ));
        }
    }

    /**
     * AJAX: Test database tables (debug)
     */
    public function ajax_test_database() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'woo2shopify'));
            return;
        }

        try {
            global $wpdb;

            $results = array();

            // Check progress table
            $progress_table = $wpdb->prefix . 'woo2shopify_progress';
            $progress_exists = $wpdb->get_var("SHOW TABLES LIKE '$progress_table'") == $progress_table;
            $results['progress_table_exists'] = $progress_exists;

            if ($progress_exists) {
                $progress_count = $wpdb->get_var("SELECT COUNT(*) FROM $progress_table");
                $results['progress_records'] = intval($progress_count);

                // Get latest progress record
                $latest_progress = $wpdb->get_row("SELECT * FROM $progress_table ORDER BY id DESC LIMIT 1");
                $results['latest_progress'] = $latest_progress;
            }

            // Check logs table
            $logs_table = $wpdb->prefix . 'woo2shopify_logs';
            $logs_exists = $wpdb->get_var("SHOW TABLES LIKE '$logs_table'") == $logs_table;
            $results['logs_table_exists'] = $logs_exists;

            if ($logs_exists) {
                $logs_count = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table");
                $results['logs_records'] = intval($logs_count);
            }

            wp_send_json_success($results);

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'error_details' => array(
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                )
            ));
        }
    }

    /**
     * AJAX: Create database tables manually
     */
    public function ajax_create_tables() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'woo2shopify'));
            return;
        }

        try {
            error_log('Woo2Shopify: Manual table creation started');
            $result = Woo2Shopify_Database::create_tables();

            // Verify tables were created
            $tables_exist = Woo2Shopify_Database::tables_exist();

            wp_send_json_success(array(
                'message' => 'Database tables created successfully',
                'tables_created' => $result,
                'tables_exist' => $tables_exist,
                'db_version' => Woo2Shopify_Database::get_db_version()
            ));

        } catch (Exception $e) {
            error_log('Woo2Shopify: Table creation failed: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'error_details' => array(
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                )
            ));
        }
    }

    /**
     * Handle batch processing via cron
     */
    public function handle_batch_processing($migration_id, $offset) {
        error_log('Woo2Shopify: Cron batch processing triggered - Migration ID: ' . $migration_id . ', Offset: ' . $offset);

        try {
            $batch_processor = new Woo2Shopify_Batch_Processor();
            $batch_processor->process_batch($migration_id, $offset);
        } catch (Exception $e) {
            error_log('Woo2Shopify: Cron batch processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle enhanced batch processing via cron
     */
    public function handle_enhanced_batch_processing($migration_id, $batch_number) {
        try {
            $enhanced_processor = new Woo2Shopify_Enhanced_Batch_Processor();
            $enhanced_processor->process_enhanced_batch($migration_id, $batch_number);
        } catch (Exception $e) {
            error_log('Woo2Shopify: Enhanced batch processing failed: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Get products for selection
     */
    public function ajax_get_products_for_selection() {
        check_ajax_referer('woo2shopify_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'woo2shopify'));
        }

        $args = array(
            'limit' => intval($_POST['limit'] ?? 50),
            'offset' => intval($_POST['offset'] ?? 0),
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'category' => intval($_POST['category'] ?? 0),
            'status' => sanitize_text_field($_POST['status'] ?? 'any')
        );

        try {
            $selective_migrator = new Woo2Shopify_Selective_Migrator();
            $result = $selective_migrator->get_products_for_selection($args);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX: Get pages for selection
     */
    public function ajax_get_pages_for_selection() {
        check_ajax_referer('woo2shopify_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'woo2shopify'));
        }

        $args = array(
            'limit' => intval($_POST['limit'] ?? 50),
            'offset' => intval($_POST['offset'] ?? 0),
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'publish')
        );

        try {
            $selective_migrator = new Woo2Shopify_Selective_Migrator();
            $result = $selective_migrator->get_pages_for_selection($args);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX: Migrate selected products
     */
    public function ajax_migrate_selected_products() {
        check_ajax_referer('woo2shopify_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'woo2shopify'));
        }

        $product_ids = array_map('intval', $_POST['product_ids'] ?? array());
        $options = array(
            'include_images' => $_POST['include_images'] === 'true',
            'include_videos' => $_POST['include_videos'] === 'true',
            'include_variations' => $_POST['include_variations'] === 'true',
            'include_categories' => $_POST['include_categories'] === 'true'
        );

        if (empty($product_ids)) {
            wp_send_json_error(array('message' => __('No products selected', 'woo2shopify')));
        }

        try {
            $selective_migrator = new Woo2Shopify_Selective_Migrator();
            $result = $selective_migrator->migrate_selected_products($product_ids, $options);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX: Migrate selected pages
     */
    public function ajax_migrate_selected_pages() {
        check_ajax_referer('woo2shopify_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'woo2shopify'));
        }

        $page_ids = array_map('intval', $_POST['page_ids'] ?? array());

        if (empty($page_ids)) {
            wp_send_json_error(array('message' => __('No pages selected', 'woo2shopify')));
        }

        try {
            $selective_migrator = new Woo2Shopify_Selective_Migrator();
            $result = $selective_migrator->migrate_selected_pages($page_ids);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX: Start enhanced migration
     */
    public function ajax_start_enhanced_migration() {
        check_ajax_referer('woo2shopify_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'woo2shopify'));
        }

        $options = array(
            'product_ids' => array_map('intval', $_POST['product_ids'] ?? array()),
            'include_images' => $_POST['include_images'] === 'true',
            'include_videos' => $_POST['include_videos'] === 'true',
            'include_variations' => $_POST['include_variations'] === 'true',
            'include_categories' => $_POST['include_categories'] === 'true',
            'include_translations' => $_POST['include_translations'] === 'true',
            'batch_size' => intval($_POST['batch_size'] ?? 0)
        );

        try {
            $enhanced_processor = new Woo2Shopify_Enhanced_Batch_Processor();
            $result = $enhanced_processor->start_migration($options);
            wp_send_json($result);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX: Get enhanced progress
     */
    public function ajax_get_enhanced_progress() {
        check_ajax_referer('woo2shopify_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'woo2shopify'));
        }

        $migration_id = sanitize_text_field($_POST['migration_id'] ?? '');

        if (empty($migration_id)) {
            wp_send_json_error(array('message' => __('Migration ID required', 'woo2shopify')));
        }

        try {
            $enhanced_processor = new Woo2Shopify_Enhanced_Batch_Processor();
            $result = $enhanced_processor->get_progress($migration_id);
            wp_send_json($result);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}

// Initialize the plugin
function woo2shopify_init() {
    $plugin = Woo2Shopify::get_instance();
    $plugin->init(); // Now init is called at the right time
    return $plugin;
}

// Start the plugin
add_action('plugins_loaded', 'woo2shopify_init');
