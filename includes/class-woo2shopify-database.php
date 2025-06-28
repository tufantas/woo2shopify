<?php
/**
 * Database Management Class for Woo2Shopify
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo2Shopify_Database {
    
    /**
     * Create required tables
     */
    public static function create_tables() {
        global $wpdb;

        try {
            // Ensure we have a clean database connection
            $wpdb->flush();

            $charset_collate = $wpdb->get_charset_collate();

            // Progress tracking table
            $progress_table = $wpdb->prefix . 'woo2shopify_progress';
            $progress_sql = "CREATE TABLE $progress_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            migration_id varchar(100) NOT NULL,
            total_products int(11) DEFAULT 0,
            processed_products int(11) DEFAULT 0,
            successful_products int(11) DEFAULT 0,
            failed_products int(11) DEFAULT 0,
            skipped_products int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'running',
            batch_size int(11) DEFAULT 5,
            current_batch int(11) DEFAULT 0,
            total_batches int(11) DEFAULT 0,
            percentage decimal(5,2) DEFAULT 0.00,
            status_message text,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            estimated_completion datetime DEFAULT NULL,
            memory_usage bigint(20) DEFAULT 0,
            peak_memory bigint(20) DEFAULT 0,
            options longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY migration_id (migration_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Migration logs table
        $logs_table = $wpdb->prefix . 'woo2shopify_logs';
        $logs_sql = "CREATE TABLE $logs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            migration_id varchar(100) DEFAULT NULL,
            product_id bigint(20) DEFAULT NULL,
            action varchar(50) NOT NULL,
            level varchar(20) DEFAULT 'info',
            message text NOT NULL,
            data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY migration_id (migration_id),
            KEY product_id (product_id),
            KEY level (level),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Migration mappings table
        $mappings_table = $wpdb->prefix . 'woo2shopify_mappings';
        $mappings_sql = "CREATE TABLE $mappings_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            wc_id bigint(20) NOT NULL,
            wc_type varchar(50) NOT NULL,
            shopify_id varchar(100) NOT NULL,
            shopify_type varchar(50) NOT NULL,
            migration_id varchar(100) DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY wc_mapping (wc_id, wc_type),
            KEY shopify_id (shopify_id),
            KEY migration_id (migration_id),
            KEY status (status)
        ) $charset_collate;";
        
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            $progress_result = dbDelta($progress_sql);
            $logs_result = dbDelta($logs_sql);
            $mappings_result = dbDelta($mappings_sql);

            // Log results
            error_log('Woo2Shopify: Database table creation results:');
            error_log('Progress table: ' . print_r($progress_result, true));
            error_log('Logs table: ' . print_r($logs_result, true));
            error_log('Mappings table: ' . print_r($mappings_result, true));

            // Update database version
            update_option('woo2shopify_db_version', '1.0.0');

            return true;

        } catch (Exception $e) {
            error_log('Woo2Shopify: Database table creation failed - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Drop tables
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'woo2shopify_progress',
            $wpdb->prefix . 'woo2shopify_logs',
            $wpdb->prefix . 'woo2shopify_mappings'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('woo2shopify_db_version');
        
        return true;
    }
    
    /**
     * Check if tables exist
     */
    public static function tables_exist() {
        global $wpdb;
        
        $progress_table = $wpdb->prefix . 'woo2shopify_progress';
        $logs_table = $wpdb->prefix . 'woo2shopify_logs';
        $mappings_table = $wpdb->prefix . 'woo2shopify_mappings';
        
        $progress_exists = $wpdb->get_var("SHOW TABLES LIKE '$progress_table'") === $progress_table;
        $logs_exists = $wpdb->get_var("SHOW TABLES LIKE '$logs_table'") === $logs_table;
        $mappings_exists = $wpdb->get_var("SHOW TABLES LIKE '$mappings_table'") === $mappings_table;
        
        return $progress_exists && $logs_exists && $mappings_exists;
    }
    
    /**
     * Get database version
     */
    public static function get_db_version() {
        return get_option('woo2shopify_db_version', '0.0.0');
    }
    
    /**
     * Check if database needs update
     */
    public static function needs_update() {
        $current_version = self::get_db_version();
        $required_version = '1.0.0';
        
        return version_compare($current_version, $required_version, '<');
    }
    
    /**
     * Update database
     */
    public static function update_database() {
        if (self::needs_update()) {
            return self::create_tables();
        }
        
        return true;
    }
    
    /**
     * Clean old data
     */
    public static function clean_old_data($days = 30) {
        global $wpdb;
        
        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Clean old progress records
        $progress_table = $wpdb->prefix . 'woo2shopify_progress';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $progress_table WHERE status IN ('completed', 'failed', 'stopped') AND completed_at < %s",
            $date_threshold
        ));
        
        // Clean old logs
        $logs_table = $wpdb->prefix . 'woo2shopify_logs';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $logs_table WHERE created_at < %s",
            $date_threshold
        ));
        
        return true;
    }
    
    /**
     * Get database statistics
     */
    public static function get_statistics() {
        global $wpdb;
        
        $progress_table = $wpdb->prefix . 'woo2shopify_progress';
        $logs_table = $wpdb->prefix . 'woo2shopify_logs';
        $mappings_table = $wpdb->prefix . 'woo2shopify_mappings';
        
        $stats = array();
        
        // Progress statistics
        $stats['total_migrations'] = $wpdb->get_var("SELECT COUNT(*) FROM $progress_table");
        $stats['completed_migrations'] = $wpdb->get_var("SELECT COUNT(*) FROM $progress_table WHERE status = 'completed'");
        $stats['failed_migrations'] = $wpdb->get_var("SELECT COUNT(*) FROM $progress_table WHERE status = 'failed'");
        $stats['running_migrations'] = $wpdb->get_var("SELECT COUNT(*) FROM $progress_table WHERE status = 'running'");
        
        // Product statistics
        $stats['total_products_migrated'] = $wpdb->get_var("SELECT SUM(successful_products) FROM $progress_table WHERE status = 'completed'");
        $stats['total_products_failed'] = $wpdb->get_var("SELECT SUM(failed_products) FROM $progress_table");
        
        // Log statistics
        $stats['total_logs'] = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table");
        $stats['error_logs'] = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE level = 'error'");
        $stats['warning_logs'] = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE level = 'warning'");
        
        // Mapping statistics
        $stats['total_mappings'] = $wpdb->get_var("SELECT COUNT(*) FROM $mappings_table");
        $stats['product_mappings'] = $wpdb->get_var("SELECT COUNT(*) FROM $mappings_table WHERE wc_type = 'product'");
        $stats['category_mappings'] = $wpdb->get_var("SELECT COUNT(*) FROM $mappings_table WHERE wc_type = 'category'");
        
        // Database size
        $stats['database_size'] = $wpdb->get_var($wpdb->prepare(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB' 
             FROM information_schema.tables 
             WHERE table_schema = %s 
             AND table_name IN (%s, %s, %s)",
            DB_NAME,
            $progress_table,
            $logs_table,
            $mappings_table
        ));
        
        return $stats;
    }
    
    /**
     * Optimize tables
     */
    public static function optimize_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'woo2shopify_progress',
            $wpdb->prefix . 'woo2shopify_logs',
            $wpdb->prefix . 'woo2shopify_mappings'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE $table");
        }
        
        return true;
    }
    
    /**
     * Backup tables
     */
    public static function backup_tables() {
        global $wpdb;
        
        $backup_dir = WP_CONTENT_DIR . '/uploads/woo2shopify-backups/';
        
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        $backup_file = $backup_dir . 'woo2shopify-backup-' . date('Y-m-d-H-i-s') . '.sql';
        
        $tables = array(
            $wpdb->prefix . 'woo2shopify_progress',
            $wpdb->prefix . 'woo2shopify_logs',
            $wpdb->prefix . 'woo2shopify_mappings'
        );
        
        $sql_content = '';
        
        foreach ($tables as $table) {
            // Get table structure
            $create_table = $wpdb->get_row("SHOW CREATE TABLE $table", ARRAY_N);
            if ($create_table) {
                $sql_content .= "\n\n-- Table structure for $table\n";
                $sql_content .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql_content .= $create_table[1] . ";\n\n";
                
                // Get table data
                $rows = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
                if ($rows) {
                    $sql_content .= "-- Data for table $table\n";
                    foreach ($rows as $row) {
                        $values = array();
                        foreach ($row as $value) {
                            $values[] = $value === null ? 'NULL' : "'" . esc_sql($value) . "'";
                        }
                        $sql_content .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $sql_content .= "\n";
                }
            }
        }
        
        if (file_put_contents($backup_file, $sql_content)) {
            return $backup_file;
        }
        
        return false;
    }
}
