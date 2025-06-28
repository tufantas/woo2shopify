<?php
/**
 * Installation and Setup Script for Woo2Shopify
 * This file helps with initial setup and configuration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Woo2Shopify_Installer {
    
    /**
     * Run installation
     */
    public static function install() {
        self::create_database_tables();
        self::set_default_options();
        self::create_directories();
        self::schedule_cleanup_job();
        
        // Set installation flag
        update_option('woo2shopify_installed', true);
        update_option('woo2shopify_version', WOO2SHOPIFY_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private static function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Migration logs table
        $logs_table = $wpdb->prefix . 'woo2shopify_logs';
        $sql_logs = "CREATE TABLE $logs_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            migration_id varchar(50) NOT NULL,
            product_id bigint(20) DEFAULT NULL,
            action varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            message text,
            shopify_id varchar(50) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY migration_id (migration_id),
            KEY product_id (product_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Migration progress table
        $progress_table = $wpdb->prefix . 'woo2shopify_progress';
        $sql_progress = "CREATE TABLE $progress_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            migration_id varchar(50) NOT NULL,
            total_products int(11) NOT NULL DEFAULT 0,
            processed_products int(11) NOT NULL DEFAULT 0,
            successful_products int(11) NOT NULL DEFAULT 0,
            failed_products int(11) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            status_message text,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY migration_id (migration_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_logs);
        dbDelta($sql_progress);
    }
    
    /**
     * Set default options
     */
    private static function set_default_options() {
        $defaults = array(
            'shopify_store_url' => '',
            'shopify_access_token' => '',
            'shopify_api_key' => '',
            'shopify_api_secret' => '',
            'batch_size' => 10,
            'image_quality' => 80,
            'include_variations' => true,
            'include_categories' => true,
            'include_tags' => true,
            'include_meta' => true,
            'include_videos' => true,
            'include_translations' => true,
            'include_currencies' => true,
            'debug_mode' => false,
            'max_image_size' => 20971520, // 20MB
            'api_timeout' => 30,
            'retry_attempts' => 3,
            'cleanup_days' => 30
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option('woo2shopify_' . $key) === false) {
                add_option('woo2shopify_' . $key, $value);
            }
        }
    }
    
    /**
     * Create necessary directories
     */
    private static function create_directories() {
        $upload_dir = wp_upload_dir();
        $woo2shopify_dir = $upload_dir['basedir'] . '/woo2shopify';
        
        if (!file_exists($woo2shopify_dir)) {
            wp_mkdir_p($woo2shopify_dir);
        }
        
        // Create subdirectories
        $subdirs = array('logs', 'exports', 'temp');
        foreach ($subdirs as $subdir) {
            $dir_path = $woo2shopify_dir . '/' . $subdir;
            if (!file_exists($dir_path)) {
                wp_mkdir_p($dir_path);
            }
        }
        
        // Create .htaccess for security
        $htaccess_content = "Order deny,allow\nDeny from all\n";
        file_put_contents($woo2shopify_dir . '/.htaccess', $htaccess_content);
    }
    
    /**
     * Schedule cleanup job
     */
    private static function schedule_cleanup_job() {
        if (!wp_next_scheduled('woo2shopify_cleanup')) {
            wp_schedule_event(time(), 'daily', 'woo2shopify_cleanup');
        }
    }
    
    /**
     * Run uninstallation
     */
    public static function uninstall() {
        global $wpdb;
        
        // Remove database tables
        $logs_table = $wpdb->prefix . 'woo2shopify_logs';
        $progress_table = $wpdb->prefix . 'woo2shopify_progress';
        
        $wpdb->query("DROP TABLE IF EXISTS $logs_table");
        $wpdb->query("DROP TABLE IF EXISTS $progress_table");
        
        // Remove options
        $options = array(
            'woo2shopify_shopify_store_url',
            'woo2shopify_shopify_access_token',
            'woo2shopify_batch_size',
            'woo2shopify_image_quality',
            'woo2shopify_include_variations',
            'woo2shopify_include_categories',
            'woo2shopify_include_tags',
            'woo2shopify_include_meta',
            'woo2shopify_debug_mode',
            'woo2shopify_max_image_size',
            'woo2shopify_api_timeout',
            'woo2shopify_retry_attempts',
            'woo2shopify_cleanup_days',
            'woo2shopify_installed',
            'woo2shopify_version'
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // Clear scheduled events
        wp_clear_scheduled_hook('woo2shopify_cleanup');
        wp_clear_scheduled_hook('woo2shopify_process_batch');
        
        // Remove upload directories
        $upload_dir = wp_upload_dir();
        $woo2shopify_dir = $upload_dir['basedir'] . '/woo2shopify';
        
        if (file_exists($woo2shopify_dir)) {
            self::remove_directory($woo2shopify_dir);
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Update plugin
     */
    public static function update() {
        $current_version = get_option('woo2shopify_version', '0.0.0');
        
        if (version_compare($current_version, WOO2SHOPIFY_VERSION, '<')) {
            // Run update procedures
            self::update_database_schema();
            self::update_options();
            
            // Update version
            update_option('woo2shopify_version', WOO2SHOPIFY_VERSION);
        }
    }
    
    /**
     * Update database schema
     */
    private static function update_database_schema() {
        // Add any new columns or tables for future versions
        global $wpdb;
        
        $progress_table = $wpdb->prefix . 'woo2shopify_progress';
        
        // Check if status_message column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM $progress_table LIKE %s",
            'status_message'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $progress_table ADD COLUMN status_message text AFTER status");
        }
        
        // Check if updated_at column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM $progress_table LIKE %s",
            'updated_at'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $progress_table ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        }
    }
    
    /**
     * Update options for new version
     */
    private static function update_options() {
        // Add any new default options
        $new_options = array(
            'max_image_size' => 20971520,
            'api_timeout' => 30,
            'retry_attempts' => 3,
            'cleanup_days' => 30
        );
        
        foreach ($new_options as $key => $value) {
            if (get_option('woo2shopify_' . $key) === false) {
                add_option('woo2shopify_' . $key, $value);
            }
        }
    }
    
    /**
     * Check system requirements
     */
    public static function check_requirements() {
        $errors = array();
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $errors[] = sprintf(__('PHP version 7.4 or higher is required. You are running version %s.', 'woo2shopify'), PHP_VERSION);
        }
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            $errors[] = sprintf(__('WordPress version 5.0 or higher is required. You are running version %s.', 'woo2shopify'), get_bloginfo('version'));
        }
        
        // Check WooCommerce
        if (!class_exists('WooCommerce')) {
            $errors[] = __('WooCommerce plugin is required but not installed or activated.', 'woo2shopify');
        } elseif (version_compare(WC_VERSION, '5.0', '<')) {
            $errors[] = sprintf(__('WooCommerce version 5.0 or higher is required. You are running version %s.', 'woo2shopify'), WC_VERSION);
        }
        
        // Check required PHP extensions
        $required_extensions = array('curl', 'json', 'mbstring');
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $errors[] = sprintf(__('PHP extension "%s" is required but not installed.', 'woo2shopify'), $extension);
            }
        }
        
        // Check memory limit
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        if ($memory_limit < 134217728) { // 128MB
            $errors[] = __('PHP memory limit should be at least 128MB for optimal performance.', 'woo2shopify');
        }
        
        return $errors;
    }
    
    /**
     * Remove directory recursively
     */
    private static function remove_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::remove_directory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
    
    /**
     * Get installation status
     */
    public static function get_status() {
        return array(
            'installed' => get_option('woo2shopify_installed', false),
            'version' => get_option('woo2shopify_version', '0.0.0'),
            'requirements' => self::check_requirements(),
            'database_tables' => self::check_database_tables(),
            'directories' => self::check_directories()
        );
    }
    
    /**
     * Check database tables
     */
    private static function check_database_tables() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'woo2shopify_logs';
        $progress_table = $wpdb->prefix . 'woo2shopify_progress';
        
        return array(
            'logs_table' => $wpdb->get_var("SHOW TABLES LIKE '$logs_table'") === $logs_table,
            'progress_table' => $wpdb->get_var("SHOW TABLES LIKE '$progress_table'") === $progress_table
        );
    }
    
    /**
     * Check directories
     */
    private static function check_directories() {
        $upload_dir = wp_upload_dir();
        $woo2shopify_dir = $upload_dir['basedir'] . '/woo2shopify';
        
        return array(
            'main_dir' => is_dir($woo2shopify_dir),
            'logs_dir' => is_dir($woo2shopify_dir . '/logs'),
            'exports_dir' => is_dir($woo2shopify_dir . '/exports'),
            'temp_dir' => is_dir($woo2shopify_dir . '/temp')
        );
    }
}
