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
            'includes/class-woo2shopify-logger.php',
            'includes/class-woo2shopify-database.php'
        );

        foreach ($required_files as $file) {
            $file_path = WOO2SHOPIFY_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                error_log('Woo2Shopify: Loaded ' . $file);

                // Special check for functions.php
                if ($file === 'includes/functions.php') {
                    if (function_exists('woo2shopify_convert_product_status')) {
                        error_log('Woo2Shopify: Functions loaded successfully');
                    } else {
                        error_log('Woo2Shopify: ERROR - Functions not loaded properly!');
                    }
                }
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
        add_action('wp_ajax_woo2shopify_debug_migration', array($this, 'ajax_debug_migration'));
        add_action('wp_ajax_woo2shopify_stop_all_tasks', array($this, 'ajax_stop_all_tasks'));

        // New selective migration AJAX handlers
        add_action('wp_ajax_woo2shopify_get_products_for_selection', array($this, 'ajax_get_products_for_selection'));
        add_action('wp_ajax_woo2shopify_get_pages_for_selection', array($this, 'ajax_get_pages_for_selection'));
        add_action('wp_ajax_woo2shopify_migrate_selected_products', array($this, 'ajax_migrate_selected_products'));
        add_action('wp_ajax_woo2shopify_migrate_selected_pages', array($this, 'ajax_migrate_selected_pages'));

        // Batch processing trigger (for cron fallback)
        add_action('wp_ajax_woo2shopify_trigger_batch', array($this, 'ajax_trigger_batch'));
        add_action('wp_ajax_nopriv_woo2shopify_trigger_batch', array($this, 'ajax_trigger_batch'));

        // Force continue migration
        add_action('wp_ajax_woo2shopify_force_continue', array($this, 'ajax_force_continue'));

        // Debug migration status
        add_action('wp_ajax_woo2shopify_debug_status', array($this, 'ajax_debug_status'));

        // Debug migration
        add_action('wp_ajax_woo2shopify_debug_migration', array($this, 'ajax_debug_migration'));
        add_action('wp_ajax_woo2shopify_start_enhanced_migration', array($this, 'ajax_start_enhanced_migration'));
        add_action('wp_ajax_woo2shopify_get_enhanced_progress', array($this, 'ajax_get_enhanced_progress'));

        // Test batch processing
        add_action('wp_ajax_woo2shopify_test_batch', array($this, 'ajax_test_batch'));

        // Test batch processing
        add_action('wp_ajax_woo2shopify_test_batch', array($this, 'ajax_test_batch'));

        // Video migration handlers
        add_action('wp_ajax_woo2shopify_test_video', array($this, 'ajax_test_video'));
        add_action('wp_ajax_woo2shopify_migrate_single_video', array($this, 'ajax_migrate_single_video'));
        add_action('wp_ajax_woo2shopify_clear_video_cache', array($this, 'ajax_clear_video_cache'));
        add_action('wp_ajax_woo2shopify_reset_video_failures', array($this, 'ajax_reset_video_failures'));

        // Debug handlers
        add_action('wp_ajax_woo2shopify_get_debug_log', array($this, 'ajax_get_debug_log'));
        add_action('wp_ajax_woo2shopify_clear_shopify_products', array($this, 'ajax_clear_shopify_products'));
        add_action('wp_ajax_woo2shopify_debug_languages', array($this, 'ajax_debug_languages'));

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

                    // Only set query cache if it's available (check first)
                    $cache_result = $wpdb->get_var("SHOW VARIABLES LIKE 'query_cache_type'");
                    if ($cache_result && $cache_result !== 'OFF') {
                        $wpdb->query("SET SESSION query_cache_type = ON");
                    }
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
        // Enhanced error logging
        woo2shopify_log('AJAX start migration called', 'info');
        woo2shopify_log('POST data: ' . json_encode($_POST), 'debug');

        try {
            check_ajax_referer('woo2shopify_nonce', 'nonce');
            woo2shopify_log('Nonce verified successfully', 'info');

            if (!current_user_can('manage_options')) {
                woo2shopify_log('Insufficient permissions for user: ' . get_current_user_id(), 'error');
                wp_send_json_error(__('Insufficient permissions', 'woo2shopify'));
                return;
            }
            woo2shopify_log('Permissions verified for user: ' . get_current_user_id(), 'info');

            // Check if required classes exist
            if (!class_exists('Woo2Shopify_Batch_Processor')) {
                error_log('Woo2Shopify: Batch processor class not found');
                wp_send_json_error(array('message' => 'Batch processor class not found'));
                return;
            }
            error_log('Woo2Shopify: Batch processor class found');

            // Get migration options from POST data
            $options = array(
                'include_images' => isset($_POST['include_images']) ? (bool)$_POST['include_images'] : true,
                'include_videos' => false, // Video processing disabled for main migration
                'include_variations' => isset($_POST['include_variations']) ? (bool)$_POST['include_variations'] : true,
                'include_categories' => isset($_POST['include_categories']) ? (bool)$_POST['include_categories'] : true,
                'include_translations' => isset($_POST['include_translations']) ? (bool)$_POST['include_translations'] : true,
                'batch_size' => isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 3
            );
            error_log('Woo2Shopify: Options prepared: ' . json_encode($options));

            $batch_processor = new Woo2Shopify_Batch_Processor();
            error_log('Woo2Shopify: Batch processor instantiated');

            $result = $batch_processor->start_migration($options);
            error_log('Woo2Shopify: Migration start result: ' . json_encode($result));

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (Exception $e) {
            error_log('Woo2Shopify: Exception in ajax_start_migration: ' . $e->getMessage());
            error_log('Woo2Shopify: Exception file: ' . $e->getFile());
            error_log('Woo2Shopify: Exception line: ' . $e->getLine());
            error_log('Woo2Shopify: Exception trace: ' . $e->getTraceAsString());

            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'error_details' => array(
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                )
            ));
        } catch (Error $e) {
            error_log('Woo2Shopify: Fatal error in ajax_start_migration: ' . $e->getMessage());
            error_log('Woo2Shopify: Fatal error file: ' . $e->getFile());
            error_log('Woo2Shopify: Fatal error line: ' . $e->getLine());

            wp_send_json_error(array(
                'message' => 'Fatal error: ' . $e->getMessage(),
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
            wp_send_json_error(__('Insufficient permissions', 'woo2shopify'));
            return;
        }

        $migration_id = sanitize_text_field($_POST['migration_id'] ?? '');

        if (empty($migration_id)) {
            wp_send_json_error(__('Migration ID required', 'woo2shopify'));
            return;
        }

        try {
            $batch_processor = new Woo2Shopify_Batch_Processor();
            $progress_result = $batch_processor->get_progress($migration_id);

            // Log for debugging
            error_log('Woo2Shopify: Progress request for ID: ' . $migration_id);
            error_log('Woo2Shopify: Progress result: ' . print_r($progress_result, true));

            if ($progress_result['success']) {
                wp_send_json_success($progress_result['data']);
            } else {
                wp_send_json_error($progress_result['message']);
            }

        } catch (Exception $e) {
            error_log('Woo2Shopify: Progress error: ' . $e->getMessage());
            wp_send_json_error(__('Failed to get progress: ', 'woo2shopify') . $e->getMessage());
        }
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
            'status' => sanitize_text_field($_POST['status'] ?? 'any'),
            'product_type' => sanitize_text_field($_POST['product_type'] ?? ''),
            'migration_status' => sanitize_text_field($_POST['migration_status'] ?? ''),
            'price_min' => floatval($_POST['price_min'] ?? 0),
            'price_max' => floatval($_POST['price_max'] ?? 0)
        );

        // Clean up empty values
        if (empty($args['category'])) {
            $args['category'] = '';
        }
        if (empty($args['price_min'])) {
            $args['price_min'] = '';
        }
        if (empty($args['price_max'])) {
            $args['price_max'] = '';
        }

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
            'include_categories' => $_POST['include_categories'] === 'true',
            'include_tags' => $_POST['include_tags'] === 'true',
            'include_translations' => $_POST['include_translations'] === 'true',
            'include_currencies' => $_POST['include_currencies'] === 'true',
            'include_seo' => $_POST['include_seo'] === 'true',
            'include_custom_fields' => $_POST['include_custom_fields'] === 'true',
            'skip_duplicates' => $_POST['skip_duplicates'] === 'true',
            'update_existing' => $_POST['update_existing'] === 'true',
            'selected_languages' => $_POST['selected_languages'] ?? array(),
            'selected_currencies' => $_POST['selected_currencies'] ?? array()
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

    /**
     * AJAX: Force continue migration
     */
    public function ajax_force_continue() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'woo2shopify'));
            return;
        }

        $migration_id = sanitize_text_field($_POST['migration_id'] ?? '');
        if (empty($migration_id)) {
            wp_send_json_error(__('Missing migration ID', 'woo2shopify'));
            return;
        }

        error_log('Woo2Shopify: Auto-recovery force continue migration - ID: ' . $migration_id);

        try {
            // Get migration progress
            $progress = woo2shopify_get_progress($migration_id);
            if (!$progress) {
                wp_send_json_error(__('Migration not found', 'woo2shopify'));
                return;
            }

            // Force set status to running if it's stuck
            if ($progress->status !== 'running') {
                woo2shopify_update_progress($migration_id, array(
                    'status' => 'running',
                    'status_message' => __('Auto-recovered and resumed', 'woo2shopify')
                ));
                error_log('Woo2Shopify: Migration status auto-recovered to running');
            } else {
                // Update status message even if already running
                woo2shopify_update_progress($migration_id, array(
                    'status_message' => __('Auto-recovery triggered - resuming processing', 'woo2shopify')
                ));
                error_log('Woo2Shopify: Migration auto-recovery triggered while running');
            }

            // Clear any stuck video cache that might be causing issues
            global $wpdb;
            $video_table = $wpdb->prefix . 'woo2shopify_video_cache';
            $stuck_videos = $wpdb->query($wpdb->prepare(
                "UPDATE {$video_table} SET status = 'failed', error_message = 'Auto-recovery: Reset stuck video'
                 WHERE migration_id = %s AND status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 30 SECOND)",
                $migration_id
            ));

            if ($stuck_videos > 0) {
                error_log("Woo2Shopify: Auto-recovery reset {$stuck_videos} stuck videos");
            }

            // Trigger batch processing with a small delay to prevent immediate re-trigger
            $batch_processor = new Woo2Shopify_Batch_Processor();
            $batch_processor->process_batch($migration_id, $progress->processed_products);

            wp_send_json_success(array(
                'message' => __('Migration auto-recovered and resumed successfully', 'woo2shopify')
            ));

        } catch (Exception $e) {
            error_log('Woo2Shopify: Force continue failed: ' . $e->getMessage());
            wp_send_json_error(__('Force continue failed: ', 'woo2shopify') . $e->getMessage());
        }
    }

    /**
     * AJAX: Debug migration status
     */
    public function ajax_debug_status() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'woo2shopify'));
            return;
        }

        global $wpdb;
        $progress_table = $wpdb->prefix . 'woo2shopify_progress';

        // Get all migrations
        $migrations = $wpdb->get_results("SELECT * FROM {$progress_table} ORDER BY started_at DESC LIMIT 5");

        $debug_info = array();
        foreach ($migrations as $migration) {
            $debug_info[] = array(
                'id' => $migration->migration_id,
                'status' => $migration->status,
                'processed' => $migration->processed_products,
                'total' => $migration->total_products,
                'started' => $migration->started_at,
                'completed' => $migration->completed_at,
                'message' => $migration->status_message
            );
        }

        wp_send_json_success($debug_info);
    }

    /**
     * AJAX: Trigger batch processing (fallback for cron)
     */
    public function ajax_trigger_batch() {
        // Verify nonce - use the main nonce for simplicity
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'woo2shopify_nonce')) {
            wp_die('Security check failed');
        }

        $migration_id = sanitize_text_field($_POST['migration_id'] ?? '');
        $offset = intval($_POST['offset'] ?? 0);
        $delay = intval($_POST['delay'] ?? 0);

        if (empty($migration_id)) {
            wp_die('Missing migration ID');
        }

        // Add delay if specified
        if ($delay > 0) {
            sleep($delay);
        }

        error_log('Woo2Shopify: AJAX trigger batch - Migration ID: ' . $migration_id . ', Offset: ' . $offset);

        try {
            // Check if migration is still running
            $progress = woo2shopify_get_progress($migration_id);
            if (!$progress || $progress->status !== 'running') {
                error_log('Woo2Shopify: Migration not running, skipping batch');
                wp_die('Migration not running');
            }

            $batch_processor = new Woo2Shopify_Batch_Processor();
            $batch_processor->process_batch($migration_id, $offset);

            wp_die('Batch processed');
        } catch (Exception $e) {
            error_log('Woo2Shopify: AJAX batch processing failed: ' . $e->getMessage());
            wp_die('Batch processing failed: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Debug migration status
     */
    public function ajax_debug_migration() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'woo2shopify'));
        }

        global $wpdb;

        $debug_info = array();

        // Get recent migrations
        $progress_table = $wpdb->prefix . 'woo2shopify_progress';
        $recent_migrations = $wpdb->get_results(
            "SELECT * FROM {$progress_table} ORDER BY created_at DESC LIMIT 5"
        );

        $debug_info['recent_migrations'] = $recent_migrations;

        // Get scheduled cron jobs
        $cron_jobs = wp_get_scheduled_event('woo2shopify_process_batch');
        $debug_info['scheduled_cron'] = $cron_jobs;

        // Check if WP Cron is working
        $debug_info['wp_cron_disabled'] = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;

        // Get recent logs
        $logs_table = $wpdb->prefix . 'woo2shopify_logs';
        $recent_logs = $wpdb->get_results(
            "SELECT * FROM {$logs_table} ORDER BY created_at DESC LIMIT 10"
        );

        $debug_info['recent_logs'] = $recent_logs;

        // Check Shopify connection
        try {
            $shopify_api = new Woo2Shopify_Shopify_API();
            $connection_test = $shopify_api->test_connection();
            $debug_info['shopify_connection'] = $connection_test;
        } catch (Exception $e) {
            $debug_info['shopify_connection'] = array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }

        // Get product count
        $wc_reader = new Woo2Shopify_WooCommerce_Reader();
        $debug_info['product_count'] = $wc_reader->get_product_count();

        wp_send_json_success($debug_info);
    }

    /**
     * AJAX: Stop all background tasks
     */
    public function ajax_stop_all_tasks() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'woo2shopify'));
            return;
        }

        try {
            // Get current running tasks before stopping
            $running_tasks = woo2shopify_get_running_tasks();

            // Stop all tasks
            $result = woo2shopify_stop_all_tasks();

            // Also clear any stuck migrations
            global $wpdb;
            $progress_table = $wpdb->prefix . 'woo2shopify_progress';
            $stuck_migrations = $wpdb->query("UPDATE {$progress_table} SET status = 'stopped' WHERE status = 'running'");

            error_log('Woo2Shopify: All tasks stopped and ' . $stuck_migrations . ' stuck migrations cleared');

            wp_send_json_success(array(
                'message' => 'All background tasks stopped successfully',
                'stopped_migrations' => count($running_tasks['migrations']),
                'stuck_migrations_cleared' => $stuck_migrations,
                'wp_cron_status' => $running_tasks['wp_cron'],
                'action_scheduler_jobs' => $running_tasks['action_scheduler'] ?? 0
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to stop tasks: ' . $e->getMessage()
            ));
        }
    }

    /**
     * AJAX: Clear Shopify products (for testing)
     */
    public function ajax_clear_shopify_products() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'woo2shopify'));
            return;
        }

        try {
            $shopify_api = new Woo2Shopify_Shopify_API();

            // Get all products
            $products = $shopify_api->get_products();
            $deleted_count = 0;

            if ($products && isset($products['products'])) {
                foreach ($products['products'] as $product) {
                    $result = $shopify_api->delete_product($product['id']);
                    if ($result) {
                        $deleted_count++;
                    }
                    // Small delay to avoid rate limiting
                    usleep(500000); // 0.5 seconds
                }
            }

            wp_send_json_success(array(
                'message' => "Deleted {$deleted_count} products from Shopify",
                'deleted_count' => $deleted_count
            ));

        } catch (Exception $e) {
            error_log('Woo2Shopify: Clear Shopify products error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to clear products: ' . $e->getMessage()
            ));
        }
    }

    /**
     * AJAX: Debug language settings
     */
    public function ajax_debug_languages() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'woo2shopify'));
            return;
        }

        $debug_info = array();

        // WPML Debug
        if (function_exists('icl_get_default_language')) {
            global $sitepress;
            $debug_info['wpml'] = array(
                'active' => true,
                'default_language' => icl_get_default_language(),
                'current_language' => $sitepress ? $sitepress->get_current_language() : 'N/A',
                'active_languages' => $sitepress ? $sitepress->get_active_languages() : array()
            );
        } else {
            $debug_info['wpml'] = array('active' => false);
        }

        // Polylang Debug
        if (function_exists('pll_default_language')) {
            $debug_info['polylang'] = array(
                'active' => true,
                'default_language' => pll_default_language(),
                'current_language' => pll_current_language(),
                'languages' => pll_languages_list()
            );
        } else {
            $debug_info['polylang'] = array('active' => false);
        }

        // WPML Database Debug
        if (function_exists('icl_get_default_language')) {
            global $wpdb;
            $default_lang = icl_get_default_language();

            // Check WPML translations table
            $wpml_products = $wpdb->get_results($wpdb->prepare(
                "SELECT element_id, language_code, source_language_code
                 FROM {$wpdb->prefix}icl_translations
                 WHERE element_type = 'post_product'
                 AND element_id IN (27136, 48210, 48250)
                 ORDER BY element_id, language_code"
            ));

            $debug_info['wpml_database'] = $wpml_products;
        }

        // Sample products debug
        $wc_reader = new Woo2Shopify_WooCommerce_Reader();
        $sample_products = $wc_reader->get_products(array('limit' => 5));

        $debug_info['sample_products'] = array();
        foreach ($sample_products as $product) {
            $debug_info['sample_products'][] = array(
                'id' => $product['id'],
                'name' => $product['name'],
                'translations' => array_keys($product['translations'] ?? array())
            );
        }

        wp_send_json_success($debug_info);
    }

    /**
     * AJAX: Test batch processing
     */
    public function ajax_test_batch() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'woo2shopify'));
            return;
        }

        try {
            $debug_info = array();

            // Test 1: Check if functions exist
            $debug_info['functions_exist'] = array(
                'woo2shopify_get_progress' => function_exists('woo2shopify_get_progress'),
                'woo2shopify_update_progress' => function_exists('woo2shopify_update_progress'),
                'woo2shopify_generate_migration_id' => function_exists('woo2shopify_generate_migration_id')
            );

            // Test 2: Check database tables
            global $wpdb;
            $progress_table = $wpdb->prefix . 'woo2shopify_progress';
            $debug_info['table_exists'] = $wpdb->get_var("SHOW TABLES LIKE '$progress_table'") == $progress_table;

            // Test 3: Check WP Cron
            $debug_info['wp_cron_enabled'] = !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON;

            // Test 4: Try to create a test migration record
            if ($debug_info['table_exists'] && $debug_info['functions_exist']['woo2shopify_generate_migration_id']) {
                $test_migration_id = woo2shopify_generate_migration_id();
                $insert_result = woo2shopify_update_progress($test_migration_id, array(
                    'total_products' => 1,
                    'processed_products' => 0,
                    'status' => 'test'
                ));
                $debug_info['test_insert'] = $insert_result !== false;

                // Clean up test record
                if ($insert_result) {
                    $wpdb->delete($progress_table, array('migration_id' => $test_migration_id));
                }
            }

            // Test 5: Check class loading
            $debug_info['classes_exist'] = array(
                'Woo2Shopify_Batch_Processor' => class_exists('Woo2Shopify_Batch_Processor'),
                'Woo2Shopify_Shopify_API' => class_exists('Woo2Shopify_Shopify_API'),
                'Woo2Shopify_WooCommerce_Reader' => class_exists('Woo2Shopify_WooCommerce_Reader')
            );

            wp_send_json_success($debug_info);

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
        }
    }

    /**
     * Test video AJAX handler
     */
    public function ajax_test_video() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        $video_url = sanitize_url($_POST['video_url']);

        if (empty($video_url)) {
            wp_send_json_error(array('message' => 'Video URL is required'));
        }

        try {
            // Test if video URL is accessible
            $response = wp_remote_head($video_url, array(
                'timeout' => 10,
                'user-agent' => 'Woo2Shopify Video Tester'
            ));

            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => 'Cannot access video: ' . $response->get_error_message()));
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                wp_send_json_error(array('message' => 'Video not accessible (HTTP ' . $response_code . ')'));
            }

            wp_send_json_success(array('message' => 'Video is accessible'));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error testing video: ' . $e->getMessage()));
        }
    }

    /**
     * Migrate single video AJAX handler
     */
    public function ajax_migrate_single_video() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        $video_url = sanitize_url($_POST['video_url']);
        $product_id = intval($_POST['product_id']);

        if (empty($video_url) || empty($product_id)) {
            wp_send_json_error(array('message' => 'Video URL and Product ID are required'));
        }

        try {
            // Get Shopify product ID for this WooCommerce product
            global $wpdb;
            $table_name = $wpdb->prefix . 'woo2shopify_products';

            $shopify_product_id = $wpdb->get_var($wpdb->prepare(
                "SELECT shopify_product_id FROM $table_name WHERE wc_product_id = %d AND status = 'completed'",
                $product_id
            ));

            if (!$shopify_product_id) {
                wp_send_json_error(array('message' => 'Product not found in Shopify or not migrated yet'));
            }

            // Process video with timeout protection
            $video_result = woo2shopify_safe_video_process($video_url, $shopify_product_id, 20);

            if (is_wp_error($video_result)) {
                wp_send_json_error(array('message' => $video_result->get_error_message()));
            }

            wp_send_json_success(array(
                'message' => 'Video migrated successfully',
                'video_id' => $video_result
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error migrating video: ' . $e->getMessage()));
        }
    }

    /**
     * Clear video cache AJAX handler
     */
    public function ajax_clear_video_cache() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'woo2shopify_video_cache';

            $deleted = $wpdb->query("DELETE FROM $table_name");

            wp_send_json_success(array(
                'message' => sprintf('Cleared %d video cache entries', $deleted)
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error clearing video cache: ' . $e->getMessage()));
        }
    }

    /**
     * Reset video failures AJAX handler
     */
    public function ajax_reset_video_failures() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        try {
            // Reset video failure counters
            delete_option('woo2shopify_video_failures');
            delete_option('woo2shopify_disable_videos');

            // Clear stuck videos
            woo2shopify_clear_stuck_videos();

            wp_send_json_success(array(
                'message' => 'Video failures reset successfully. Video processing is now enabled.'
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error resetting video failures: ' . $e->getMessage()));
        }
    }

    /**
     * Get debug log AJAX handler
     */
    public function ajax_get_debug_log() {
        check_ajax_referer('woo2shopify_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'woo2shopify'));
            return;
        }

        try {
            $log_content = woo2shopify_get_debug_log(50);
            wp_send_json_success(array('log' => $log_content));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error getting debug log: ' . $e->getMessage()));
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
