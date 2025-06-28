<?php
/**
 * Enhanced Batch Processor for Woo2Shopify
 * 
 * Improved batch processing with better progress tracking and error handling
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo2Shopify_Enhanced_Batch_Processor {
    
    private $wc_reader;
    private $data_mapper;
    private $shopify_api;
    private $image_migrator;
    private $logger;
    private $batch_size;
    private $migration_id;
    
    public function __construct() {
        $this->wc_reader = new Woo2Shopify_WooCommerce_Reader();
        $this->data_mapper = new Woo2Shopify_Data_Mapper();
        $this->shopify_api = new Woo2Shopify_Shopify_API();
        $this->image_migrator = new Woo2Shopify_Image_Migrator();
        $this->logger = new Woo2Shopify_Logger();
        
        // Dynamic batch size based on server capabilities
        $this->batch_size = $this->calculate_optimal_batch_size();
    }
    
    /**
     * Calculate optimal batch size
     */
    private function calculate_optimal_batch_size() {
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $max_execution_time = ini_get('max_execution_time');
        
        // Conservative batch size calculation
        if ($memory_limit < 134217728) { // Less than 128MB
            return 1;
        } elseif ($memory_limit < 268435456) { // Less than 256MB
            return 2;
        } elseif ($max_execution_time < 60) { // Less than 60 seconds
            return 3;
        } else {
            return 5; // Maximum batch size
        }
    }
    
    /**
     * Start enhanced migration
     */
    public function start_migration($options = array()) {
        $defaults = array(
            'product_ids' => array(), // Specific products to migrate
            'include_images' => true,
            'include_videos' => true,
            'include_variations' => true,
            'include_categories' => true,
            'include_translations' => true,
            'batch_size' => null // Override automatic batch size
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Override batch size if specified
        if ($options['batch_size'] && $options['batch_size'] > 0) {
            $this->batch_size = min($options['batch_size'], 10); // Max 10 for safety
        }
        
        try {
            // Generate migration ID
            $this->migration_id = woo2shopify_generate_migration_id();
            
            // Get products to migrate
            if (!empty($options['product_ids'])) {
                $total_products = count($options['product_ids']);
                $product_query = array('include' => $options['product_ids']);
            } else {
                $total_products = $this->wc_reader->get_product_count();
                $product_query = array();
            }
            
            if ($total_products === 0) {
                return array(
                    'success' => false,
                    'message' => __('No products found to migrate', 'woo2shopify')
                );
            }
            
            // Initialize enhanced progress tracking
            $this->initialize_progress($total_products, $options);
            
            // Start processing
            $this->schedule_enhanced_batch_processing($product_query, $options);
            
            return array(
                'success' => true,
                'migration_id' => $this->migration_id,
                'total_products' => $total_products,
                'batch_size' => $this->batch_size,
                'estimated_time' => $this->estimate_migration_time($total_products)
            );
            
        } catch (Exception $e) {
            $this->logger->log($this->migration_id, null, 'migration_start_error', 'error', $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Initialize enhanced progress tracking
     */
    private function initialize_progress($total_products, $options) {
        $progress_data = array(
            'total_products' => $total_products,
            'processed_products' => 0,
            'successful_products' => 0,
            'failed_products' => 0,
            'skipped_products' => 0,
            'status' => 'running',
            'started_at' => current_time('mysql'),
            'batch_size' => $this->batch_size,
            'options' => $options,
            'current_batch' => 0,
            'total_batches' => ceil($total_products / $this->batch_size),
            'estimated_completion' => $this->calculate_estimated_completion($total_products),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        );
        
        woo2shopify_update_progress($this->migration_id, $progress_data);
        
        $this->logger->log($this->migration_id, null, 'migration_start', 'info',
            sprintf(__('Enhanced migration started: %d products, %d batches, batch size: %d', 'woo2shopify'),
                $total_products, $progress_data['total_batches'], $this->batch_size));
    }
    
    /**
     * Schedule enhanced batch processing
     */
    private function schedule_enhanced_batch_processing($product_query, $options) {
        // Store migration data for batch processing
        update_option('woo2shopify_migration_' . $this->migration_id, array(
            'product_query' => $product_query,
            'options' => $options,
            'batch_size' => $this->batch_size
        ));
        
        // Schedule first batch immediately
        wp_schedule_single_event(time() + 1, 'woo2shopify_process_enhanced_batch', 
            array($this->migration_id, 0));
    }
    
    /**
     * Process enhanced batch
     */
    public function process_enhanced_batch($migration_id, $batch_number) {
        $this->migration_id = $migration_id;
        
        try {
            // Check if migration is still running
            $progress = woo2shopify_get_progress($migration_id);
            if (!$progress || $progress->status !== 'running') {
                return;
            }
            
            // Get migration data
            $migration_data = get_option('woo2shopify_migration_' . $migration_id);
            if (!$migration_data) {
                throw new Exception(__('Migration data not found', 'woo2shopify'));
            }
            
            $this->batch_size = $migration_data['batch_size'];
            $offset = $batch_number * $this->batch_size;
            
            // Update current batch info
            woo2shopify_update_progress($migration_id, array(
                'current_batch' => $batch_number + 1,
                'status_message' => sprintf(__('Processing batch %d of %d', 'woo2shopify'), 
                    $batch_number + 1, $progress->total_batches)
            ));
            
            // Get products for this batch
            $query_args = array_merge($migration_data['product_query'], array(
                'limit' => $this->batch_size,
                'offset' => $offset
            ));
            
            $products = $this->wc_reader->get_products($query_args);
            
            if (empty($products)) {
                $this->complete_migration();
                return;
            }
            
            // Process products in this batch
            $batch_results = $this->process_products_batch($products, $migration_data['options']);
            
            // Update progress
            $this->update_enhanced_progress($batch_results);
            
            // Check if migration is complete
            $updated_progress = woo2shopify_get_progress($migration_id);
            if ($updated_progress->processed_products >= $updated_progress->total_products) {
                $this->complete_migration();
                return;
            }
            
            // Schedule next batch with delay to prevent overwhelming
            $delay = $this->calculate_batch_delay($batch_results);
            wp_schedule_single_event(time() + $delay, 'woo2shopify_process_enhanced_batch',
                array($migration_id, $batch_number + 1));
                
        } catch (Exception $e) {
            $this->fail_migration($e->getMessage());
        }
    }
    
    /**
     * Process products batch
     */
    private function process_products_batch($products, $options) {
        $results = array(
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => array(),
            'processing_time' => 0,
            'memory_used' => 0
        );
        
        $start_time = microtime(true);
        $start_memory = memory_get_usage(true);
        
        foreach ($products as $product_data) {
            try {
                $product_id = $product_data['id'];
                
                // Check if already migrated
                if (get_post_meta($product_id, '_woo2shopify_migrated', true) === '1') {
                    $results['skipped']++;
                    continue;
                }
                
                // Map product data
                $shopify_product = $this->data_mapper->map_product($product_data);
                
                // Create product in Shopify
                $shopify_result = $this->shopify_api->create_product($shopify_product);
                if (is_wp_error($shopify_result)) {
                    throw new Exception($shopify_result->get_error_message());
                }
                
                $shopify_product_id = $shopify_result['id'];
                
                // Migrate media if enabled
                if ($options['include_images'] || $options['include_videos']) {
                    $this->migrate_product_media($product_id, $product_data, $shopify_product_id, $options);
                }
                
                // Process description videos
                if ($options['include_videos']) {
                    $this->data_mapper->process_description_videos($product_id, $shopify_product_id);
                }
                
                // Mark as migrated
                update_post_meta($product_id, '_woo2shopify_migrated', '1');
                update_post_meta($product_id, '_woo2shopify_shopify_id', $shopify_product_id);
                update_post_meta($product_id, '_woo2shopify_migration_id', $this->migration_id);
                
                $results['successful']++;
                
                $this->logger->log($this->migration_id, $product_id, 'product_migrated', 'success',
                    sprintf(__('Product migrated successfully (Shopify ID: %s)', 'woo2shopify'), $shopify_product_id));
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = array(
                    'product_id' => $product_data['id'],
                    'error' => $e->getMessage()
                );
                
                $this->logger->log($this->migration_id, $product_data['id'], 'product_failed', 'error', $e->getMessage());
            }
            
            // Memory check
            if (memory_get_usage(true) > (wp_convert_hr_to_bytes(ini_get('memory_limit')) * 0.8)) {
                $this->logger->log($this->migration_id, null, 'memory_warning', 'warning',
                    'High memory usage detected, stopping batch early');
                break;
            }
        }
        
        $results['processing_time'] = microtime(true) - $start_time;
        $results['memory_used'] = memory_get_usage(true) - $start_memory;
        
        return $results;
    }
    
    /**
     * Migrate product media
     */
    private function migrate_product_media($product_id, $product_data, $shopify_product_id, $options) {
        if (!empty($product_data['media'])) {
            $media_results = $this->image_migrator->migrate_product_media(
                $product_id,
                $product_data['media'],
                $shopify_product_id
            );
            
            // Log media migration results
            if (!empty($media_results['errors'])) {
                foreach ($media_results['errors'] as $error) {
                    $this->logger->log($this->migration_id, $product_id, 'media_failed', 'warning',
                        'Media migration failed: ' . $error['error']);
                }
            }
        }
    }
    
    /**
     * Update enhanced progress
     */
    private function update_enhanced_progress($batch_results) {
        $progress = woo2shopify_get_progress($this->migration_id);
        
        if ($progress) {
            $new_processed = $progress->processed_products + $batch_results['successful'] + $batch_results['failed'] + $batch_results['skipped'];
            $new_successful = $progress->successful_products + $batch_results['successful'];
            $new_failed = $progress->failed_products + $batch_results['failed'];
            $new_skipped = ($progress->skipped_products ?? 0) + $batch_results['skipped'];
            
            $percentage = $progress->total_products > 0 ? 
                round(($new_processed / $progress->total_products) * 100, 2) : 0;
            
            $update_data = array(
                'processed_products' => $new_processed,
                'successful_products' => $new_successful,
                'failed_products' => $new_failed,
                'skipped_products' => $new_skipped,
                'percentage' => $percentage,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'status_message' => sprintf(
                    __('Processed %d of %d products (%s%%) - Success: %d, Failed: %d, Skipped: %d', 'woo2shopify'),
                    $new_processed,
                    $progress->total_products,
                    $percentage,
                    $new_successful,
                    $new_failed,
                    $new_skipped
                )
            );
            
            // Update estimated completion time
            if ($new_processed > 0 && $batch_results['processing_time'] > 0) {
                $avg_time_per_product = $batch_results['processing_time'] / ($batch_results['successful'] + $batch_results['failed']);
                $remaining_products = $progress->total_products - $new_processed;
                $estimated_remaining_time = $remaining_products * $avg_time_per_product;
                $update_data['estimated_completion'] = date('Y-m-d H:i:s', time() + $estimated_remaining_time);
            }
            
            woo2shopify_update_progress($this->migration_id, $update_data);
        }
    }
    
    /**
     * Calculate batch delay
     */
    private function calculate_batch_delay($batch_results) {
        // Base delay of 2 seconds
        $delay = 2;
        
        // Add delay based on processing time
        if ($batch_results['processing_time'] > 30) {
            $delay += 3; // Longer delay for slow batches
        }
        
        // Add delay if there were errors
        if ($batch_results['failed'] > 0) {
            $delay += 2;
        }
        
        // Add delay based on memory usage
        $memory_usage_percent = (memory_get_usage(true) / wp_convert_hr_to_bytes(ini_get('memory_limit'))) * 100;
        if ($memory_usage_percent > 70) {
            $delay += 5;
        }
        
        return $delay;
    }
    
    /**
     * Estimate migration time
     */
    private function estimate_migration_time($total_products) {
        // Rough estimate: 10-30 seconds per product depending on complexity
        $avg_time_per_product = 20;
        $total_time = $total_products * $avg_time_per_product;
        
        // Add batch overhead
        $total_batches = ceil($total_products / $this->batch_size);
        $batch_overhead = $total_batches * 3; // 3 seconds per batch
        
        return $total_time + $batch_overhead;
    }
    
    /**
     * Calculate estimated completion
     */
    private function calculate_estimated_completion($total_products) {
        $estimated_time = $this->estimate_migration_time($total_products);
        return date('Y-m-d H:i:s', time() + $estimated_time);
    }
    
    /**
     * Complete migration
     */
    private function complete_migration() {
        woo2shopify_update_progress($this->migration_id, array(
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
            'status_message' => __('Migration completed successfully', 'woo2shopify')
        ));
        
        // Clean up migration data
        delete_option('woo2shopify_migration_' . $this->migration_id);
        
        $this->logger->log($this->migration_id, null, 'migration_completed', 'success',
            __('Enhanced migration completed successfully', 'woo2shopify'));
    }
    
    /**
     * Fail migration
     */
    private function fail_migration($error_message) {
        woo2shopify_update_progress($this->migration_id, array(
            'status' => 'failed',
            'completed_at' => current_time('mysql'),
            'status_message' => $error_message
        ));
        
        // Clean up migration data
        delete_option('woo2shopify_migration_' . $this->migration_id);
        
        $this->logger->log($this->migration_id, null, 'migration_failed', 'error', $error_message);
    }
    
    /**
     * Get enhanced progress
     */
    public function get_progress($migration_id) {
        $progress = woo2shopify_get_progress($migration_id);
        
        if (!$progress) {
            return array(
                'success' => false,
                'message' => __('Migration not found', 'woo2shopify')
            );
        }
        
        $percentage = $progress->total_products > 0 ? 
            round(($progress->processed_products / $progress->total_products) * 100, 2) : 0;
        
        return array(
            'success' => true,
            'data' => array(
                'migration_id' => $migration_id,
                'status' => $progress->status,
                'total_products' => intval($progress->total_products),
                'processed_products' => intval($progress->processed_products),
                'successful_products' => intval($progress->successful_products),
                'failed_products' => intval($progress->failed_products),
                'skipped_products' => intval($progress->skipped_products ?? 0),
                'percentage' => $percentage,
                'current_batch' => intval($progress->current_batch ?? 0),
                'total_batches' => intval($progress->total_batches ?? 0),
                'batch_size' => intval($progress->batch_size ?? 0),
                'status_message' => $progress->status_message ?: __('Processing...', 'woo2shopify'),
                'started_at' => $progress->started_at,
                'completed_at' => $progress->completed_at,
                'estimated_completion' => $progress->estimated_completion ?? null,
                'memory_usage' => $this->format_bytes($progress->memory_usage ?? 0),
                'peak_memory' => $this->format_bytes($progress->peak_memory ?? 0)
            )
        );
    }
    
    /**
     * Format bytes
     */
    private function format_bytes($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}
