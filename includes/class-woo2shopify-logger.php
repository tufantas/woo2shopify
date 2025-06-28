<?php
/**
 * Logger Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Woo2Shopify_Logger {
    
    /**
     * Log levels
     */
    const LEVEL_ERROR = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_INFO = 'info';
    const LEVEL_SUCCESS = 'success';
    const LEVEL_DEBUG = 'debug';
    
    /**
     * Debug mode
     */
    private $debug_mode;
    
    /**
     * Log file path
     */
    private $log_file;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->debug_mode = woo2shopify_get_option('debug_mode', false);
        $this->log_file = WP_CONTENT_DIR . '/uploads/woo2shopify-debug.log';
    }
    
    /**
     * Log message
     */
    public function log($migration_id, $product_id, $action, $level, $message, $shopify_id = '') {
        // Always log to database
        $this->log_to_database($migration_id, $product_id, $action, $level, $message, $shopify_id);
        
        // Log to file if debug mode is enabled
        if ($this->debug_mode) {
            $this->log_to_file($migration_id, $product_id, $action, $level, $message, $shopify_id);
        }
        
        // Log critical errors to WordPress error log
        if ($level === self::LEVEL_ERROR) {
            error_log(sprintf(
                'Woo2Shopify Error [%s]: %s (Product ID: %s, Migration ID: %s)',
                $action,
                $message,
                $product_id ?: 'N/A',
                $migration_id
            ));
        }
    }
    
    /**
     * Log to database
     */
    private function log_to_database($migration_id, $product_id, $action, $level, $message, $shopify_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'woo2shopify_logs';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'migration_id' => $migration_id,
                'product_id' => $product_id,
                'action' => $action,
                'status' => $level,
                'message' => $message,
                'shopify_id' => $shopify_id,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('Woo2Shopify: Failed to log to database - ' . $wpdb->last_error);
        }
    }
    
    /**
     * Log to file
     */
    private function log_to_file($migration_id, $product_id, $action, $level, $message, $shopify_id) {
        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = sprintf(
            "[%s] [%s] [%s] Migration: %s | Product: %s | Shopify: %s | Message: %s\n",
            $timestamp,
            strtoupper($level),
            $action,
            $migration_id,
            $product_id ?: 'N/A',
            $shopify_id ?: 'N/A',
            $message
        );
        
        // Ensure log directory exists
        $log_dir = dirname($this->log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        // Write to log file
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Rotate log file if it gets too large (10MB)
        if (file_exists($this->log_file) && filesize($this->log_file) > 10485760) {
            $this->rotate_log_file();
        }
    }
    
    /**
     * Rotate log file
     */
    private function rotate_log_file() {
        $backup_file = $this->log_file . '.old';
        
        // Remove old backup if exists
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }
        
        // Move current log to backup
        rename($this->log_file, $backup_file);
    }
    
    /**
     * Get logs from database
     */
    public function get_logs($migration_id = null, $limit = 100, $offset = 0, $level = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'woo2shopify_logs';
        $where_conditions = array();
        $where_values = array();
        
        if ($migration_id) {
            $where_conditions[] = 'migration_id = %s';
            $where_values[] = $migration_id;
        }
        
        if ($level) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $level;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $query = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare($query, $where_values));
    }
    
    /**
     * Get logs count
     */
    public function get_logs_count($migration_id = null, $level = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'woo2shopify_logs';
        $where_conditions = array();
        $where_values = array();
        
        if ($migration_id) {
            $where_conditions[] = 'migration_id = %s';
            $where_values[] = $migration_id;
        }
        
        if ($level) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $level;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $query = "SELECT COUNT(*) FROM $table_name $where_clause";
        
        if (!empty($where_values)) {
            return $wpdb->get_var($wpdb->prepare($query, $where_values));
        } else {
            return $wpdb->get_var($query);
        }
    }
    
    /**
     * Get error summary
     */
    public function get_error_summary($migration_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'woo2shopify_logs';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                action,
                COUNT(*) as count,
                GROUP_CONCAT(DISTINCT message SEPARATOR '; ') as messages
            FROM $table_name 
            WHERE migration_id = %s AND status = 'error'
            GROUP BY action
            ORDER BY count DESC
        ", $migration_id));
        
        return $results;
    }
    
    /**
     * Clear logs
     */
    public function clear_logs($migration_id = null, $older_than_days = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'woo2shopify_logs';
        $where_conditions = array();
        $where_values = array();
        
        if ($migration_id) {
            $where_conditions[] = 'migration_id = %s';
            $where_values[] = $migration_id;
        }
        
        if ($older_than_days) {
            $where_conditions[] = 'created_at < %s';
            $where_values[] = date('Y-m-d H:i:s', strtotime("-{$older_than_days} days"));
        }
        
        if (empty($where_conditions)) {
            // Clear all logs
            $result = $wpdb->query("TRUNCATE TABLE $table_name");
        } else {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
            $query = "DELETE FROM $table_name $where_clause";
            $result = $wpdb->query($wpdb->prepare($query, $where_values));
        }
        
        // Also clear log file if debug mode
        if ($this->debug_mode && !$migration_id) {
            if (file_exists($this->log_file)) {
                unlink($this->log_file);
            }
        }
        
        return $result !== false;
    }
    
    /**
     * Export logs
     */
    public function export_logs($migration_id, $format = 'csv') {
        $logs = $this->get_logs($migration_id, 1000); // Get up to 1000 logs
        
        if (empty($logs)) {
            return false;
        }
        
        $filename = "woo2shopify-logs-{$migration_id}." . $format;
        $filepath = wp_upload_dir()['path'] . '/' . $filename;
        
        if ($format === 'csv') {
            $this->export_to_csv($logs, $filepath);
        } elseif ($format === 'json') {
            $this->export_to_json($logs, $filepath);
        }
        
        return array(
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => wp_upload_dir()['url'] . '/' . $filename
        );
    }
    
    /**
     * Export to CSV
     */
    private function export_to_csv($logs, $filepath) {
        $file = fopen($filepath, 'w');
        
        // Write header
        fputcsv($file, array(
            'Date',
            'Migration ID',
            'Product ID',
            'Action',
            'Status',
            'Message',
            'Shopify ID'
        ));
        
        // Write data
        foreach ($logs as $log) {
            fputcsv($file, array(
                $log->created_at,
                $log->migration_id,
                $log->product_id,
                $log->action,
                $log->status,
                $log->message,
                $log->shopify_id
            ));
        }
        
        fclose($file);
    }
    
    /**
     * Export to JSON
     */
    private function export_to_json($logs, $filepath) {
        $json_data = array(
            'export_date' => current_time('Y-m-d H:i:s'),
            'total_logs' => count($logs),
            'logs' => $logs
        );
        
        file_put_contents($filepath, wp_json_encode($json_data, JSON_PRETTY_PRINT));
    }
    
    /**
     * Get system info for debugging
     */
    public function get_system_info() {
        global $wpdb;
        
        return array(
            'wordpress_version' => get_bloginfo('version'),
            'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : 'Not installed',
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'mysql_version' => $wpdb->db_version(),
            'plugin_version' => WOO2SHOPIFY_VERSION,
            'debug_mode' => $this->debug_mode ? 'Enabled' : 'Disabled',
            'log_file_exists' => file_exists($this->log_file) ? 'Yes' : 'No',
            'log_file_size' => file_exists($this->log_file) ? woo2shopify_format_bytes(filesize($this->log_file)) : 'N/A'
        );
    }
    
    /**
     * Log API request/response for debugging
     */
    public function log_api_call($migration_id, $endpoint, $method, $request_data, $response_data, $response_code) {
        if (!$this->debug_mode) {
            return;
        }
        
        $message = sprintf(
            'API Call: %s %s | Response Code: %d | Request: %s | Response: %s',
            $method,
            $endpoint,
            $response_code,
            wp_json_encode($request_data),
            is_wp_error($response_data) ? $response_data->get_error_message() : wp_json_encode($response_data)
        );
        
        $this->log($migration_id, null, 'api_call', self::LEVEL_DEBUG, $message);
    }
    
    /**
     * Log memory usage
     */
    public function log_memory_usage($migration_id, $context = '') {
        if (!$this->debug_mode) {
            return;
        }
        
        $memory_info = woo2shopify_get_memory_info();
        $message = sprintf(
            'Memory Usage %s: Current: %s | Peak: %s | Limit: %s',
            $context,
            woo2shopify_format_bytes($memory_info['current']),
            woo2shopify_format_bytes($memory_info['peak']),
            $memory_info['limit']
        );
        
        $this->log($migration_id, null, 'memory_usage', self::LEVEL_DEBUG, $message);
    }
    
    /**
     * Create debug report
     */
    public function create_debug_report($migration_id) {
        $report = array(
            'migration_id' => $migration_id,
            'generated_at' => current_time('Y-m-d H:i:s'),
            'system_info' => $this->get_system_info(),
            'error_summary' => $this->get_error_summary($migration_id),
            'recent_logs' => $this->get_logs($migration_id, 50),
            'settings' => array(
                'shopify_store_url' => woo2shopify_get_option('shopify_store_url'),
                'batch_size' => woo2shopify_get_option('batch_size'),
                'image_quality' => woo2shopify_get_option('image_quality'),
                'include_variations' => woo2shopify_get_option('include_variations'),
                'include_categories' => woo2shopify_get_option('include_categories'),
                'include_tags' => woo2shopify_get_option('include_tags'),
                'include_meta' => woo2shopify_get_option('include_meta')
            )
        );
        
        return $report;
    }
}
