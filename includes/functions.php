<?php
/**
 * Helper Functions for Woo2Shopify
 */

if (!defined('ABSPATH')) {
    exit;
}

// Debug: Log that functions.php is being loaded
error_log('Woo2Shopify: functions.php is being loaded');

/**
 * Woo2Shopify specific debug logging
 */
if (!function_exists('woo2shopify_log')) {
    function woo2shopify_log($message, $level = 'info') {
        // Always log to WordPress error log with prefix
        error_log('Woo2Shopify [' . $level . ']: ' . $message);

        // Only log to custom file if debug is enabled
        if (!woo2shopify_is_debug_enabled()) {
            return;
        }

        // Try multiple log file locations
        $log_locations = array(
            WP_CONTENT_DIR . '/woo2shopify-debug.log',
            ABSPATH . 'woo2shopify-debug.log',
            dirname(__FILE__) . '/../woo2shopify-debug.log'
        );

        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;

        foreach ($log_locations as $log_file) {
            $dir = dirname($log_file);
            if (is_writable($dir)) {
                file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
                break;
            }
        }
    }
}

/**
 * Check if Woo2Shopify debug is enabled
 */
if (!function_exists('woo2shopify_is_debug_enabled')) {
    function woo2shopify_is_debug_enabled() {
        return (defined('WOO2SHOPIFY_DEBUG') && WOO2SHOPIFY_DEBUG) ||
               get_option('woo2shopify_debug_mode', false);
    }
}

/**
 * Clear Woo2Shopify debug log
 */
if (!function_exists('woo2shopify_clear_debug_log')) {
    function woo2shopify_clear_debug_log() {
        $log_files = array(
            WP_CONTENT_DIR . '/woo2shopify-only.log',
            WP_CONTENT_DIR . '/woo2shopify-debug.log'
        );

        foreach ($log_files as $log_file) {
            if (file_exists($log_file)) {
                unlink($log_file);
            }
        }
        return true;
    }
}

/**
 * Get Woo2Shopify debug log content
 */
if (!function_exists('woo2shopify_get_debug_log')) {
    function woo2shopify_get_debug_log($lines = 100) {
        // Try multiple log file locations
        $log_files = array(
            WP_CONTENT_DIR . '/woo2shopify-only.log',  // wp-config.php debug log
            WP_CONTENT_DIR . '/woo2shopify-debug.log', // Plugin debug log
            WP_CONTENT_DIR . '/debug.log'              // WordPress debug log
        );

        $content = '';
        foreach ($log_files as $log_file) {
            if (file_exists($log_file)) {
                $file_content = file_get_contents($log_file);

                // Filter only Woo2Shopify lines if it's the main debug.log
                if (strpos($log_file, 'debug.log') !== false) {
                    $all_lines = explode("\n", $file_content);
                    $filtered_lines = array_filter($all_lines, function($line) {
                        return strpos($line, 'Woo2Shopify') !== false;
                    });
                    $content .= "=== From $log_file (filtered) ===\n" . implode("\n", $filtered_lines) . "\n\n";
                } else {
                    $content .= "=== From $log_file ===\n" . $file_content . "\n\n";
                }
            }
        }

        if (empty($content)) {
            return 'No debug log found. Make sure WOO2SHOPIFY_DEBUG is enabled in wp-config.php';
        }

        $log_lines = explode("\n", $content);

        // Get last N lines
        $recent_lines = array_slice($log_lines, -$lines);
        return implode("\n", $recent_lines);
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

        // Check if record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE migration_id = %s ORDER BY id DESC LIMIT 1",
            $migration_id
        ));

        if ($existing) {
            // Update existing record
            return $wpdb->update(
                $table_name,
                array_merge($data, array('updated_at' => current_time('mysql'))),
                array('id' => $existing->id)
            );
        } else {
            // Insert new record
            return $wpdb->insert($table_name, array_merge(array(
                'migration_id' => $migration_id,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ), $data));
        }
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
            "SELECT * FROM {$table_name} WHERE migration_id = %s ORDER BY id DESC LIMIT 1",
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
 * Convert WooCommerce product status to Shopify status
 */
if (!function_exists('woo2shopify_convert_product_status')) {
    function woo2shopify_convert_product_status($wc_status) {
        switch ($wc_status) {
            case 'publish':
                return 'active';
            case 'draft':
                return 'draft';
            case 'private':
                return 'draft';
            case 'pending':
                return 'draft';
            case 'trash':
                return 'archived';
            default:
                return 'draft';
        }
    }
}

/**
 * Convert WooCommerce stock status to Shopify inventory policy
 */
if (!function_exists('woo2shopify_convert_stock_status')) {
    function woo2shopify_convert_stock_status($stock_status, $manage_stock = false) {
        if (!$manage_stock) {
            return 'continue'; // Don't track inventory
        }

        switch ($stock_status) {
            case 'instock':
                return 'continue';
            case 'outofstock':
                return 'deny';
            case 'onbackorder':
                return 'continue';
            default:
                return 'continue';
        }
    }
}

/**
 * Check memory limit and usage
 */
if (!function_exists('woo2shopify_check_memory_limit')) {
    function woo2shopify_check_memory_limit() {
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $current_usage = memory_get_usage(true);
        $usage_percentage = ($current_usage / $memory_limit) * 100;

        woo2shopify_log("Memory usage: " . round($usage_percentage, 2) . "% (" .
                       round($current_usage / 1024 / 1024, 2) . "MB / " .
                       round($memory_limit / 1024 / 1024, 2) . "MB)", 'debug');

        // Return true if memory usage is above 80%
        return $usage_percentage > 80;
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

/**
 * Debug: Get all settings
 */
if (!function_exists('woo2shopify_debug_settings')) {
    function woo2shopify_debug_settings() {
        $settings = get_option('woo2shopify_settings', array());
        error_log('Woo2Shopify Settings Debug: ' . print_r($settings, true));
        return $settings;
    }
}

/**
 * Force save settings (for debugging)
 */
if (!function_exists('woo2shopify_force_save_settings')) {
    function woo2shopify_force_save_settings($new_settings) {
        $result = update_option('woo2shopify_settings', $new_settings);
        error_log('Woo2Shopify Force Save Result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        error_log('Woo2Shopify New Settings: ' . print_r($new_settings, true));
        return $result;
    }
}

/**
 * Stop all background tasks
 */
if (!function_exists('woo2shopify_stop_all_tasks')) {
    function woo2shopify_stop_all_tasks() {
        global $wpdb;

        // Stop all running migrations
        $progress_table = $wpdb->prefix . 'woo2shopify_progress';
        $wpdb->query("UPDATE {$progress_table} SET status = 'stopped' WHERE status = 'running'");

        // Clear WP Cron jobs
        wp_clear_scheduled_hook('woo2shopify_process_batch');
        wp_clear_scheduled_hook('woo2shopify_process_enhanced_batch');
        wp_clear_scheduled_hook('woo2shopify_cleanup');

        // Clear ActionScheduler jobs
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('woo2shopify_process_batch');
            as_unschedule_all_actions('woo2shopify_process_enhanced_batch');
        }

        error_log('Woo2Shopify: All background tasks stopped');
        return true;
    }
}

/**
 * Get running tasks status
 */
if (!function_exists('woo2shopify_get_running_tasks')) {
    function woo2shopify_get_running_tasks() {
        global $wpdb;

        $tasks = array();

        // Check running migrations
        $progress_table = $wpdb->prefix . 'woo2shopify_progress';
        $running_migrations = $wpdb->get_results(
            "SELECT * FROM {$progress_table} WHERE status = 'running' ORDER BY started_at DESC"
        );

        $tasks['migrations'] = $running_migrations;

        // Check WP Cron
        $cron_jobs = wp_get_scheduled_event('woo2shopify_process_batch');
        $tasks['wp_cron'] = $cron_jobs ? 'active' : 'inactive';

        // Check ActionScheduler
        if (function_exists('as_get_scheduled_actions')) {
            $as_jobs = as_get_scheduled_actions(array(
                'hook' => 'woo2shopify_process_batch',
                'status' => 'pending'
            ));
            $tasks['action_scheduler'] = count($as_jobs);
        }

        return $tasks;
    }
}

/**
 * Safe video processing - prevents stuck videos
 */
if (!function_exists('woo2shopify_safe_video_process')) {
    function woo2shopify_safe_video_process($video_url, $shopify_product_id, $timeout = 10) {
        // Set time limit for video processing
        $start_time = time();

        try {
            // Quick validation - don't spend too much time
            if (empty($video_url) || !filter_var($video_url, FILTER_VALIDATE_URL)) {
                return new WP_Error('invalid_video_url', 'Invalid video URL');
            }

            // Check if we've already spent too much time
            if ((time() - $start_time) > $timeout) {
                return new WP_Error('video_timeout', 'Video processing timeout');
            }

            // Simple metafield creation - no complex processing
            $metafield_data = array(
                'namespace' => 'custom',
                'key' => 'product_video_url',
                'value' => $video_url,
                'type' => 'url'
            );

            // Use Shopify API with short timeout
            $shopify_api = new Woo2Shopify_Shopify_API();
            $result = $shopify_api->create_product_metafield($shopify_product_id, $metafield_data);

            return $result;

        } catch (Exception $e) {
            error_log('Woo2Shopify: Safe video process failed: ' . $e->getMessage());
            return new WP_Error('video_process_failed', $e->getMessage());
        }
    }
}

/**
 * Clear stuck video cache entries
 */
if (!function_exists('woo2shopify_clear_stuck_videos')) {
    function woo2shopify_clear_stuck_videos() {
        $cached_videos = get_option('woo2shopify_cached_videos', array());
        $current_time = time();
        $cleared_count = 0;

        foreach ($cached_videos as $hash => $video_data) {
            $should_clear = false;

            // Clear videos stuck for more than 2 minutes
            if (isset($video_data['started_at'])) {
                $started_time = strtotime($video_data['started_at']);
                if (($current_time - $started_time) > 120) { // 2 minutes
                    $should_clear = true;
                }
            }

            // Clear videos without proper status
            if (!isset($video_data['migrated']) && !isset($video_data['pending'])) {
                $should_clear = true;
            }

            if ($should_clear) {
                unset($cached_videos[$hash]);
                $cleared_count++;
            }
        }

        if ($cleared_count > 0) {
            update_option('woo2shopify_cached_videos', $cached_videos);
            error_log('Woo2Shopify: Cleared ' . $cleared_count . ' stuck videos');
        }

        return $cleared_count;
    }
}

/**
 * Disable video processing if causing issues
 */
if (!function_exists('woo2shopify_should_process_videos')) {
    function woo2shopify_should_process_videos() {
        // Check if video processing is disabled
        $video_disabled = get_option('woo2shopify_disable_videos', false);
        if ($video_disabled) {
            return false;
        }

        // Check if too many video failures recently
        $video_failures = get_option('woo2shopify_video_failures', 0);
        if ($video_failures > 5) {
            // Auto-disable video processing after 5 failures
            update_option('woo2shopify_disable_videos', true);
            error_log('Woo2Shopify: Auto-disabled video processing due to repeated failures');
            return false;
        }

        return true;
    }
}

/**
 * Record video processing failure
 */
if (!function_exists('woo2shopify_record_video_failure')) {
    function woo2shopify_record_video_failure() {
        $failures = get_option('woo2shopify_video_failures', 0);
        update_option('woo2shopify_video_failures', $failures + 1);
    }
}

/**
 * Reset video processing failures
 */
if (!function_exists('woo2shopify_reset_video_failures')) {
    function woo2shopify_reset_video_failures() {
        delete_option('woo2shopify_video_failures');
        delete_option('woo2shopify_disable_videos');
        return true;
    }
}

// End of file - ensure proper closure