<?php
/**
 * Helper Functions for Woo2Shopify
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Log message
 */
if (!function_exists('woo2shopify_log')) {
    function woo2shopify_log($message, $level = 'info') {
        error_log('Woo2Shopify: ' . $message);
    }
}

/**
 * Check system requirements
 */
if (!function_exists('woo2shopify_check_requirements')) {
    function woo2shopify_check_requirements() {
        $requirements = array();
        
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $requirements[] = __('PHP 7.4 or higher is required', 'woo2shopify');
        }
        
        if (!class_exists('WooCommerce')) {
            $requirements[] = __('WooCommerce plugin is required', 'woo2shopify');
        }
        
        if (!function_exists('curl_init')) {
            $requirements[] = __('cURL extension is required', 'woo2shopify');
        }

        return $requirements;
    }
}

/**
 * Generate unique migration ID
 */
if (!function_exists('woo2shopify_generate_migration_id')) {
    function woo2shopify_generate_migration_id() {
        return 'migration_' . time() . '_' . wp_generate_password(8, false);
    }
}

/**
 * Update migration progress
 */
if (!function_exists('woo2shopify_update_progress')) {
    function woo2shopify_update_progress($migration_id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'woo2shopify_progress';
        
        return $wpdb->insert($table_name, array_merge(array(
            'migration_id' => $migration_id,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ), $data));
    }
}

/**
 * Get migration progress
 */
if (!function_exists('woo2shopify_get_progress')) {
    function woo2shopify_get_progress($migration_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'woo2shopify_progress';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE migration_id = %s",
            $migration_id
        ));
    }
}

/**
 * Delete migration progress
 */
if (!function_exists('woo2shopify_delete_progress')) {
    function woo2shopify_delete_progress($migration_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'woo2shopify_progress';
        
        return $wpdb->delete(
            $table_name,
            array('migration_id' => $migration_id),
            array('%s')
        );
    }
}

/**
 * Sanitize handle for Shopify
 */
if (!function_exists('woo2shopify_sanitize_handle')) {
    function woo2shopify_sanitize_handle($string) {
        $handle = strtolower($string);
        $handle = preg_replace('/[^a-z0-9\-]/', '-', $handle);
        $handle = preg_replace('/-+/', '-', $handle);
        $handle = trim($handle, '-');
        
        if (empty($handle)) {
            $handle = 'product-' . time();
        }
        
        return substr($handle, 0, 255);
    }
}

/**
 * Format price for Shopify
 */
if (!function_exists('woo2shopify_format_price')) {
    function woo2shopify_format_price($price) {
        if (empty($price) || !is_numeric($price)) {
            return '0.00';
        }
        
        return number_format(floatval($price), 2, '.', '');
    }
}

/**
 * Check if migration is running
 */
if (!function_exists('woo2shopify_is_migration_running')) {
    function woo2shopify_is_migration_running() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'woo2shopify_progress';

        $running = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE status = 'running'"
        );

        return intval($running) > 0;
    }
}

/**
 * Get option with default
 */
if (!function_exists('woo2shopify_get_option')) {
    function woo2shopify_get_option($option_name, $default = null) {
        $options = get_option('woo2shopify_settings', array());
        return isset($options[$option_name]) ? $options[$option_name] : $default;
    }
}

/**
 * Update option
 */
if (!function_exists('woo2shopify_update_option')) {
    function woo2shopify_update_option($option_name, $value) {
        $options = get_option('woo2shopify_settings', array());
        $options[$option_name] = $value;
        return update_option('woo2shopify_settings', $options);
    }
}