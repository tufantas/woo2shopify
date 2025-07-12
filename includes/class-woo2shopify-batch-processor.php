<?php
/**
 * Batch Processor Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Woo2Shopify_Batch_Processor {
    
    /**
     * WooCommerce Reader
     */
    private $wc_reader;
    
    /**
     * Data Mapper
     */
    private $data_mapper;
    
    /**
     * Shopify API
     */
    private $shopify_api;
    
    /**
     * Image Migrator
     */
    private $image_migrator;
    
    /**
     * Logger
     */
    private $logger;
    
    /**
     * Batch size
     */
    private $batch_size;
    
    /**
     * Current migration ID
     */
    private $migration_id;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->wc_reader = new Woo2Shopify_WooCommerce_Reader();
        $this->data_mapper = new Woo2Shopify_Data_Mapper();
        $this->shopify_api = new Woo2Shopify_Shopify_API();
        $this->image_migrator = new Woo2Shopify_Image_Migrator();
        $this->logger = new Woo2Shopify_Logger();

        // Optimal batch size for Shopify API (considering rate limits and timeouts)
        $this->batch_size = 3; // Conservative batch size to prevent timeouts

        error_log('Woo2Shopify: Batch processor initialized with batch size: ' . $this->batch_size);
    }
    
    /**
     * Start migration
     */
    public function start_migration($options = array()) {
        try {
            // Generate migration ID
            $this->migration_id = woo2shopify_generate_migration_id();
            error_log('Woo2Shopify: Generated migration ID: ' . $this->migration_id);

            // Store migration options temporarily
            foreach ($options as $key => $value) {
                woo2shopify_update_option($key, $value);
            }
            error_log('Woo2Shopify: Migration options: ' . json_encode($options));

            // Test connection first
            error_log('Woo2Shopify: Testing Shopify connection...');
            $connection_test = $this->shopify_api->test_connection();
            if (!$connection_test['success']) {
                error_log('Woo2Shopify: Connection test failed: ' . $connection_test['message']);
                return array(
                    'success' => false,
                    'message' => __('Shopify connection failed: ', 'woo2shopify') . $connection_test['message']
                );
            }
            error_log('Woo2Shopify: Connection test successful');
            
            // Get total product count
            error_log('Woo2Shopify: Getting product count...');
            $total_products = $this->wc_reader->get_product_count();
            error_log('Woo2Shopify: Total products found: ' . $total_products);

            if ($total_products === 0) {
                error_log('Woo2Shopify: No products found, aborting migration');
                return array(
                    'success' => false,
                    'message' => __('No products found to migrate', 'woo2shopify')
                );
            }
            
            // Initialize progress tracking
            error_log('Woo2Shopify: Initializing progress tracking...');
            $progress_result = woo2shopify_update_progress($this->migration_id, array(
                'total_products' => $total_products,
                'processed_products' => 0,
                'successful_products' => 0,
                'failed_products' => 0,
                'status' => 'running',
                'started_at' => current_time('mysql')
            ));
            error_log('Woo2Shopify: Progress tracking initialized: ' . ($progress_result ? 'success' : 'failed'));
            
            // Log migration start
            $this->logger->log($this->migration_id, null, 'migration_start', 'info', 
                sprintf(__('Migration started with %d products', 'woo2shopify'), $total_products));
            
            // Schedule background processing
            $this->schedule_batch_processing();
            
            error_log('Woo2Shopify: Migration started successfully with ID: ' . $this->migration_id);

            return array(
                'success' => true,
                'migration_id' => $this->migration_id,
                'total_products' => $total_products,
                'message' => __('Migration started successfully', 'woo2shopify')
            );

        } catch (Exception $e) {
            woo2shopify_log('Migration start exception: ' . $e->getMessage(), 'error');
            woo2shopify_log('Exception file: ' . $e->getFile(), 'error');
            woo2shopify_log('Exception line: ' . $e->getLine(), 'error');
            woo2shopify_log('Exception trace: ' . $e->getTraceAsString(), 'error');

            $this->logger->log($this->migration_id, null, 'migration_start', 'error', $e->getMessage());

            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'error_details' => array(
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                )
            );
        }
    }
    
    /**
     * Schedule batch processing
     */
    private function schedule_batch_processing() {
        error_log('Woo2Shopify: Scheduling batch processing...');

        // Try WordPress cron first - give more time for initialization
        $scheduled = wp_schedule_single_event(time() + 10, 'woo2shopify_process_batch', array($this->migration_id, 0));
        error_log('Woo2Shopify: Cron scheduled: ' . ($scheduled ? 'success' : 'failed'));

        // Also schedule delayed fallback processing
        error_log('Woo2Shopify: Starting delayed processing as fallback...');
        wp_schedule_single_event(time() + 15, 'woo2shopify_process_batch', array($this->migration_id, 0));
        error_log('Woo2Shopify: Delayed trigger scheduled: success');
    }
    
    /**
     * Process batch
     */
    public function process_batch($migration_id, $offset = 0) {
        error_log('Woo2Shopify: Starting process_batch - Migration ID: ' . $migration_id . ', Offset: ' . $offset);
        $this->migration_id = $migration_id;

        try {
            // Check if migration is still running
            error_log('Woo2Shopify: Checking migration progress...');
            $progress = woo2shopify_get_progress($migration_id);
            if (!$progress || $progress->status !== 'running') {
                error_log('Woo2Shopify: Migration not running or not found. Status: ' . ($progress ? $progress->status : 'not found'));
                return;
            }
            error_log('Woo2Shopify: Migration is running, proceeding...');

            // Get products for this batch
            error_log('Woo2Shopify: Getting products for batch - Limit: ' . $this->batch_size . ', Offset: ' . $offset);
            $products = $this->wc_reader->get_products(array(
                'limit' => $this->batch_size,
                'offset' => $offset
            ));
            error_log('Woo2Shopify: Found ' . count($products) . ' products for this batch');

            if (empty($products)) {
                // No more products, complete migration
                error_log('Woo2Shopify: No products found, completing migration...');
                $this->complete_migration();
                return;
            }
            
            // Process each product in the batch
            $batch_results = array(
                'successful' => 0,
                'failed' => 0
            );
            
            foreach ($products as $index => $product_data) {
                error_log('Woo2Shopify: Processing product ' . ($index + 1) . '/' . count($products) . ' - ID: ' . $product_data['id']);

                $result = $this->process_single_product($product_data);

                if ($result['success']) {
                    $batch_results['successful']++;
                } else {
                    $batch_results['failed']++;
                }

                // Update progress after each product for real-time tracking
                $this->update_single_product_progress($batch_results['successful'], $batch_results['failed']);

                // Add delay between products to prevent API overload
                if ($index < count($products) - 1) { // Don't sleep after last product
                    error_log('Woo2Shopify: Waiting 1 second before next product...');
                    sleep(1);
                }

                // Check memory usage
                if (woo2shopify_check_memory_limit()) {
                    $this->logger->log($migration_id, null, 'memory_warning', 'warning',
                        'Approaching memory limit, scheduling next batch');
                    break;
                }
            }
            
            // Update progress
            $this->update_batch_progress($batch_results['successful'], $batch_results['failed']);
            
            // Schedule next batch
            $next_offset = $offset + $this->batch_size;

            // Try cron first
            wp_schedule_single_event(time() + 5, 'woo2shopify_process_batch', array($migration_id, $next_offset));

            // Also trigger via AJAX as fallback
            $trigger_url = admin_url('admin-ajax.php');
            $trigger_args = array(
                'timeout' => 1,
                'blocking' => false,
                'body' => array(
                    'action' => 'woo2shopify_trigger_batch',
                    'migration_id' => $migration_id,
                    'offset' => $next_offset,
                    'nonce' => wp_create_nonce('woo2shopify_nonce'),
                    'delay' => 5
                )
            );
            wp_remote_post($trigger_url, $trigger_args);
            
        } catch (Exception $e) {
            $this->logger->log($migration_id, null, 'batch_error', 'error', $e->getMessage());
            $this->fail_migration($e->getMessage());
        }
    }
    
    /**
     * Process single product
     */
    private function process_single_product($wc_product_data) {
        $product_id = $wc_product_data['id'];

        // Check if this product was already processed in this migration
        if ($this->is_product_already_processed($product_id)) {
            error_log("Woo2Shopify: SKIPPING duplicate product {$product_id} - already processed in this migration");
            return array('success' => true, 'skipped' => true, 'reason' => 'Already processed');
        }

        try {
            // Map WooCommerce data to Shopify format
            $shopify_product_data = $this->data_mapper->map_product($wc_product_data);
            
            // Create product in Shopify
            $shopify_product = $this->shopify_api->create_product($shopify_product_data);
            
            if (is_wp_error($shopify_product)) {
                throw new Exception($shopify_product->get_error_message());
            }
            
            $shopify_product_id = $shopify_product['id'];

            // DISABLE Translation API (too problematic) - Use metafields instead
            // $this->register_product_translations($shopify_product_id, $wc_product_data);

            // Register translations as metafields (more reliable)
            $this->register_translation_metafields($shopify_product_id, $wc_product_data);

            // Migrate images and videos if enabled
            $media_results = array();

            // Migrate images (backward compatibility)
            if (woo2shopify_get_option('include_images', true) && !empty($wc_product_data['images'])) {
                $image_results = $this->image_migrator->migrate_product_images(
                    $product_id,
                    $wc_product_data['images'],
                    $shopify_product_id
                );
                $media_results['images'] = $image_results;
            }

            // Migrate images first (always safe)
            if (woo2shopify_get_option('include_images', true) && !empty($wc_product_data['images'])) {
                try {
                    $image_results = $this->image_migrator->migrate_product_images(
                        $product_id,
                        $wc_product_data['images'],
                        $shopify_product_id
                    );
                    $media_results['images'] = $image_results;
                } catch (Exception $e) {
                    error_log('Woo2Shopify: Image migration exception for product ' . $product_id . ': ' . $e->getMessage());
                    // Continue even if images fail
                }
            }

            // Video processing - DISABLED for now to prevent stuck migrations
            // Videos will be handled separately in a dedicated video migration tool
            if (false && woo2shopify_get_option('include_videos', true)) {
                error_log('Woo2Shopify: Video processing is temporarily disabled to prevent stuck migrations');
                error_log('Woo2Shopify: Use the dedicated Video Migration tool instead');
            }

            // Create collections and add product to them
            if (woo2shopify_get_option('include_categories', true) && !empty($wc_product_data['categories'])) {
                $this->create_collections_and_add_product($wc_product_data['categories'], $shopify_product_id);
            }
            
            // Log success
            $this->logger->log($this->migration_id, $product_id, 'product_created', 'success',
                sprintf(__('Product migrated successfully (Shopify ID: %s)', 'woo2shopify'), $shopify_product_id),
                $shopify_product_id);

            // Mark as processed
            $this->mark_product_as_processed($product_id, $shopify_product_id);
            
            return array(
                'success' => true,
                'shopify_id' => $shopify_product_id,
                'image_results' => $image_results
            );
            
        } catch (Exception $e) {
            // Log error
            $this->logger->log($this->migration_id, $product_id, 'product_failed', 'error', $e->getMessage());
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Create collections and add product to them
     */
    private function create_collections_and_add_product($categories, $shopify_product_id) {
        static $created_collections = array();

        foreach ($categories as $category) {
            $collection_handle = woo2shopify_sanitize_handle($category['slug']);
            $collection_id = null;

            // Check if collection already exists
            if (isset($created_collections[$collection_handle])) {
                $collection_id = $created_collections[$collection_handle];
                woo2shopify_log("Using existing collection: {$category['name']} (ID: $collection_id)", 'info');
            } else {
                // Try to get existing collection first
                $existing_collection = $this->shopify_api->get_collection_by_handle($collection_handle);

                if ($existing_collection) {
                    $collection_id = $existing_collection['id'];
                    $created_collections[$collection_handle] = $collection_id;
                    woo2shopify_log("Found existing collection: {$category['name']} (ID: $collection_id)", 'info');
                } else {
                    // Create new collection
                    try {
                        $collection_data = $this->data_mapper->map_collection($category);
                        $result = $this->shopify_api->create_collection($collection_data);

                        if (!is_wp_error($result)) {
                            $collection_id = $result['id'];
                            $created_collections[$collection_handle] = $collection_id;
                            $this->logger->log($this->migration_id, null, 'collection_created', 'success',
                                sprintf(__('Collection created: %s (ID: %s)', 'woo2shopify'), $category['name'], $collection_id));
                            woo2shopify_log("Created new collection: {$category['name']} (ID: $collection_id)", 'info');
                        } else {
                            woo2shopify_log("Failed to create collection {$category['name']}: " . $result->get_error_message(), 'error');
                            continue;
                        }

                    } catch (Exception $e) {
                        $this->logger->log($this->migration_id, null, 'collection_failed', 'error',
                            sprintf(__('Failed to create collection %s: %s', 'woo2shopify'), $category['name'], $e->getMessage()));
                        woo2shopify_log("Exception creating collection {$category['name']}: " . $e->getMessage(), 'error');
                        continue;
                    }
                }
            }

            // Add product to collection
            if ($collection_id) {
                try {
                    $result = $this->shopify_api->add_product_to_collection($collection_id, $shopify_product_id);

                    if (!is_wp_error($result)) {
                        woo2shopify_log("Added product $shopify_product_id to collection {$category['name']}", 'info');
                        $this->logger->log($this->migration_id, null, 'product_added_to_collection', 'success',
                            sprintf(__('Product added to collection: %s', 'woo2shopify'), $category['name']));
                    } else {
                        woo2shopify_log("Failed to add product to collection {$category['name']}: " . $result->get_error_message(), 'error');
                    }

                } catch (Exception $e) {
                    woo2shopify_log("Exception adding product to collection {$category['name']}: " . $e->getMessage(), 'error');
                }
            }
        }
    }
    
    /**
     * Update progress after single product (real-time updates)
     */
    private function update_single_product_progress($successful_in_batch, $failed_in_batch) {
        $progress = woo2shopify_get_progress($this->migration_id);

        if ($progress) {
            // Calculate current totals based on batch progress
            $current_batch_processed = $successful_in_batch + $failed_in_batch;
            $new_processed = $progress->processed_products + $current_batch_processed;
            $new_successful = $progress->successful_products + $successful_in_batch;
            $new_failed = $progress->failed_products + $failed_in_batch;

            // Calculate percentage
            $percentage = $progress->total_products > 0 ?
                round(($new_processed / $progress->total_products) * 100, 1) : 0;

            // Update with current progress
            woo2shopify_update_progress($this->migration_id, array(
                'processed_products' => $new_processed,
                'successful_products' => $new_successful,
                'failed_products' => $new_failed,
                'percentage' => $percentage,
                'status_message' => sprintf(
                    __('Processing... %d of %d products (%s%%) - Success: %d, Failed: %d', 'woo2shopify'),
                    $new_processed,
                    $progress->total_products,
                    $percentage,
                    $new_successful,
                    $new_failed
                )
            ));

            // Log progress for debugging
            error_log("Woo2Shopify: Real-time progress - {$new_processed}/{$progress->total_products} ({$percentage}%) - Success: {$new_successful}, Failed: {$new_failed}");
        }
    }

    /**
     * Update batch progress (legacy - kept for compatibility)
     */
    private function update_batch_progress($successful, $failed) {
        // This is now handled by update_single_product_progress for real-time updates
        // But we'll keep this for final batch completion
        $progress = woo2shopify_get_progress($this->migration_id);

        if ($progress) {
            $new_processed = $progress->processed_products + $successful + $failed;
            $new_successful = $progress->successful_products + $successful;
            $new_failed = $progress->failed_products + $failed;

            // Calculate percentage
            $percentage = $progress->total_products > 0 ?
                round(($new_processed / $progress->total_products) * 100, 1) : 0;

            woo2shopify_update_progress($this->migration_id, array(
                'processed_products' => $new_processed,
                'successful_products' => $new_successful,
                'failed_products' => $new_failed,
                'percentage' => $percentage,
                'status_message' => sprintf(
                    __('Batch completed - %d of %d products (%s%%) - Success: %d, Failed: %d', 'woo2shopify'),
                    $new_processed,
                    $progress->total_products,
                    $percentage,
                    $new_successful,
                    $new_failed
                )
            ));

            // Log batch completion
            error_log("Woo2Shopify: Batch completed - {$new_processed}/{$progress->total_products} ({$percentage}%) - Success: {$new_successful}, Failed: {$new_failed}");
        }
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
        
        $this->logger->log($this->migration_id, null, 'migration_completed', 'success', 
            __('Migration completed successfully', 'woo2shopify'));
        
        // Clean up temporary files
        $this->image_migrator->cleanup_temp_files();
        
        // Send completion notification if email is configured
        $this->send_completion_notification();
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
        
        $this->logger->log($this->migration_id, null, 'migration_failed', 'error', $error_message);
    }
    
    /**
     * Get migration progress
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
                'percentage' => $percentage,
                'status_message' => $progress->status_message ?: __('Processing...', 'woo2shopify'),
                'started_at' => $progress->started_at,
                'completed_at' => $progress->completed_at
            )
        );
    }
    
    /**
     * Stop migration
     */
    public function stop_migration($migration_id) {
        woo2shopify_update_progress($migration_id, array(
            'status' => 'stopped',
            'completed_at' => current_time('mysql'),
            'status_message' => __('Migration stopped by user', 'woo2shopify')
        ));
        
        $this->logger->log($migration_id, null, 'migration_stopped', 'info', 
            __('Migration stopped by user', 'woo2shopify'));
        
        // Clear scheduled events
        wp_clear_scheduled_hook('woo2shopify_process_batch', array($migration_id));
        
        return array(
            'success' => true,
            'message' => __('Migration stopped successfully', 'woo2shopify')
        );
    }
    
    /**
     * Send completion notification
     */
    private function send_completion_notification() {
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }
        
        $progress = woo2shopify_get_progress($this->migration_id);
        if (!$progress) {
            return;
        }
        
        $subject = sprintf(__('[%s] Woo2Shopify Migration Completed', 'woo2shopify'), get_bloginfo('name'));
        
        $message = sprintf(
            __("Your WooCommerce to Shopify migration has been completed.\n\nMigration Summary:\n- Total Products: %d\n- Successful: %d\n- Failed: %d\n- Started: %s\n- Completed: %s\n\nYou can view detailed logs in your WordPress admin panel.", 'woo2shopify'),
            $progress->total_products,
            $progress->successful_products,
            $progress->failed_products,
            $progress->started_at,
            $progress->completed_at
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Get migration statistics
     */
    public function get_migration_stats($migration_id) {
        $progress = woo2shopify_get_progress($migration_id);
        $image_stats = $this->image_migrator->get_migration_stats($migration_id);
        
        return array(
            'progress' => $progress,
            'images' => $image_stats,
            'logs_count' => $this->logger->get_logs_count($migration_id)
        );
    }

    /**
     * Check if product was already processed in this migration
     */
    private function is_product_already_processed($product_id) {
        global $wpdb;

        $log_table = $wpdb->prefix . 'woo2shopify_logs';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$log_table}
             WHERE migration_id = %s
             AND product_id = %d
             AND action = 'product_created'
             AND level = 'success'",
            $this->migration_id,
            $product_id
        ));

        return $count > 0;
    }

    /**
     * Mark product as processed (for future reference)
     */
    private function mark_product_as_processed($product_id, $shopify_id) {
        // This is already handled by the logger in process_single_product
        // But we can add additional tracking if needed
        error_log("Woo2Shopify: Marked product {$product_id} as processed (Shopify ID: {$shopify_id})");
    }

    /**
     * Register product translations using Shopify Translation API
     */
    private function register_product_translations($shopify_product_id, $wc_product_data) {
        if (empty($wc_product_data['translations'])) {
            return;
        }

        error_log("Woo2Shopify: Registering translations for product {$shopify_product_id}");

        foreach ($wc_product_data['translations'] as $lang_code => $translation) {
            // Skip if this is the default language
            if ($lang_code === 'en') {
                continue;
            }

            try {
                // Enable locale if not already enabled
                $locale_result = $this->shopify_api->enable_locale($lang_code);
                if (is_wp_error($locale_result)) {
                    error_log("Woo2Shopify: Failed to enable locale {$lang_code}: " . $locale_result->get_error_message());
                    continue;
                }

                // Register title translation (ULTRA SAFE - strip HTML if needed)
                $safe_title = $this->make_json_safe($translation['name'], false); // No HTML in titles
                $title_result = $this->shopify_api->register_translation(
                    $shopify_product_id,
                    'Product',
                    $lang_code,
                    'title',
                    $safe_title
                );

                if (is_wp_error($title_result)) {
                    error_log("Woo2Shopify: Failed to register title translation for {$lang_code}: " . $title_result->get_error_message());
                } else {
                    error_log("Woo2Shopify: Successfully registered title translation for {$lang_code}");
                }

                // Register description translation (ULTRA SAFE - strip HTML if causes issues)
                $safe_description = $this->make_json_safe($translation['description'], true); // Allow HTML but strip if problematic
                $desc_result = $this->shopify_api->register_translation(
                    $shopify_product_id,
                    'Product',
                    $lang_code,
                    'body_html',
                    $safe_description
                );

                if (is_wp_error($desc_result)) {
                    error_log("Woo2Shopify: Failed to register description translation for {$lang_code}: " . $desc_result->get_error_message());
                } else {
                    error_log("Woo2Shopify: Successfully registered description translation for {$lang_code}");
                }

                // Small delay to respect rate limits
                usleep(100000); // 0.1 second

            } catch (Exception $e) {
                error_log("Woo2Shopify: Exception during translation registration for {$lang_code}: " . $e->getMessage());
            }
        }

        error_log("Woo2Shopify: Completed translation registration for product {$shopify_product_id}");
    }

    /**
     * Escape content for GraphQL JSON - AGGRESSIVE CLEANING
     */
    private function escape_for_graphql($content) {
        if (empty($content)) {
            return '';
        }

        // Log original content for debugging
        error_log("Woo2Shopify: Original content: " . substr($content, 0, 100) . "...");

        // Step 1: Basic cleanup
        $content = trim($content);

        // Step 2: Remove problematic characters that break JSON
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);

        // Step 3: Normalize quotes - this is critical for JSON
        $content = str_replace('"', '&quot;', $content);
        $content = str_replace("'", '&#39;', $content);

        // Handle smart quotes using Unicode codes (safer)
        $content = str_replace(chr(226).chr(128).chr(156), '&quot;', $content); // Left double quote
        $content = str_replace(chr(226).chr(128).chr(157), '&quot;', $content); // Right double quote
        $content = str_replace(chr(226).chr(128).chr(152), '&#39;', $content);  // Left single quote
        $content = str_replace(chr(226).chr(128).chr(153), '&#39;', $content);  // Right single quote

        // Step 4: Handle line breaks properly
        $content = str_replace(array("\r\n", "\r", "\n"), ' ', $content);

        // Step 5: Clean up multiple spaces
        $content = preg_replace('/\s+/', ' ', $content);

        // Step 6: Ensure valid UTF-8
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        }

        // Step 7: Final trim
        $content = trim($content);

        // Log cleaned content
        error_log("Woo2Shopify: Cleaned content: " . substr($content, 0, 100) . "...");

        return $content;
    }

    /**
     * Make content absolutely JSON-safe - NUCLEAR OPTION
     */
    private function make_json_safe($content, $allow_html = true) {
        if (empty($content)) {
            return '';
        }

        error_log("Woo2Shopify: Making JSON safe - Original: " . substr($content, 0, 100) . "...");

        // Step 1: Basic cleanup
        $content = trim($content);

        // Step 2: Test if current content is JSON-safe
        $test_array = array('test' => $content);
        $test_json = json_encode($test_array);

        if (json_last_error() === JSON_ERROR_NONE) {
            error_log("Woo2Shopify: Content is already JSON-safe");
            return $content;
        }

        error_log("Woo2Shopify: Content causes JSON error: " . json_last_error_msg());

        // Step 3: If HTML is not allowed or causes issues, strip it
        if (!$allow_html || (strpos($content, '<') !== false && strpos($content, '>') !== false)) {
            error_log("Woo2Shopify: Stripping HTML to make JSON-safe");
            $content = strip_tags($content);
            $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        }

        // Step 4: Remove all problematic characters
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);

        // Step 5: Replace quotes with safe alternatives
        $content = str_replace('"', '&quot;', $content);
        $content = str_replace("'", '&#39;', $content);
        $content = str_replace('\\', '&#92;', $content);

        // Step 6: Normalize whitespace
        $content = str_replace(array("\r\n", "\r", "\n"), ' ', $content);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        // Step 7: Final JSON test
        $final_test = json_encode(array('test' => $content));
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Woo2Shopify: STILL JSON ERROR after cleaning: " . json_last_error_msg());
            // Last resort: keep only alphanumeric and basic punctuation
            $content = preg_replace('/[^\p{L}\p{N}\s\.\,\!\?\-\(\)]/u', '', $content);
            $content = trim($content);
        }

        error_log("Woo2Shopify: Final JSON-safe content: " . substr($content, 0, 100) . "...");
        return $content;
    }

    /**
     * Register translations as metafields (more reliable than Translation API)
     */
    private function register_translation_metafields($shopify_product_id, $wc_product_data) {
        if (empty($wc_product_data['translations'])) {
            return;
        }

        error_log("Woo2Shopify: Registering translation metafields for product {$shopify_product_id}");

        foreach ($wc_product_data['translations'] as $lang_code => $translation) {
            // Skip if this is the default language
            if ($lang_code === 'en') {
                continue;
            }

            try {
                // Create title metafield
                $title_metafield = array(
                    'namespace' => 'custom',
                    'key' => "title_{$lang_code}",
                    'value' => strip_tags($translation['name']), // Safe: no HTML in titles
                    'type' => 'single_line_text_field'
                );

                $title_result = $this->shopify_api->create_product_metafield($shopify_product_id, $title_metafield);

                if (is_wp_error($title_result)) {
                    error_log("Woo2Shopify: Failed to create title metafield for {$lang_code}: " . $title_result->get_error_message());
                } else {
                    error_log("Woo2Shopify: Successfully created title metafield for {$lang_code}");
                }

                // Create long description metafield (preserve HTML but make safe)
                $safe_description = $this->make_json_safe($translation['description'], true);
                $desc_metafield = array(
                    'namespace' => 'custom',
                    'key' => "description_{$lang_code}",
                    'value' => $safe_description,
                    'type' => 'multi_line_text_field'
                );

                $desc_result = $this->shopify_api->create_product_metafield($shopify_product_id, $desc_metafield);

                if (is_wp_error($desc_result)) {
                    error_log("Woo2Shopify: Failed to create description metafield for {$lang_code}: " . $desc_result->get_error_message());
                } else {
                    error_log("Woo2Shopify: Successfully created description metafield for {$lang_code}");
                }

                // Create short description metafield
                $safe_short_description = $this->make_json_safe($translation['short_description'], true);
                $short_desc_metafield = array(
                    'namespace' => 'custom',
                    'key' => "short_description_{$lang_code}",
                    'value' => $safe_short_description,
                    'type' => 'multi_line_text_field'
                );

                $short_desc_result = $this->shopify_api->create_product_metafield($shopify_product_id, $short_desc_metafield);

                if (is_wp_error($short_desc_result)) {
                    error_log("Woo2Shopify: Failed to create short description metafield for {$lang_code}: " . $short_desc_result->get_error_message());
                } else {
                    error_log("Woo2Shopify: Successfully created short description metafield for {$lang_code}");
                }

                // Small delay to respect rate limits
                usleep(100000); // 0.1 second

            } catch (Exception $e) {
                error_log("Woo2Shopify: Exception during metafield creation for {$lang_code}: " . $e->getMessage());
            }
        }

        error_log("Woo2Shopify: Completed translation metafield registration for product {$shopify_product_id}");
    }
}

// Hook for batch processing
add_action('woo2shopify_process_batch', function($migration_id, $offset) {
    $processor = new Woo2Shopify_Batch_Processor();
    $processor->process_batch($migration_id, $offset);
}, 10, 2);
