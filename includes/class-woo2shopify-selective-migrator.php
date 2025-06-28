<?php
/**
 * Selective Migration Class for Woo2Shopify
 * 
 * Handles selective product migration and page migration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo2Shopify_Selective_Migrator {
    
    private $wc_reader;
    private $data_mapper;
    private $shopify_api;
    private $image_migrator;
    private $logger;
    
    public function __construct() {
        $this->wc_reader = new Woo2Shopify_WooCommerce_Reader();
        $this->data_mapper = new Woo2Shopify_Data_Mapper();
        $this->shopify_api = new Woo2Shopify_Shopify_API();
        $this->image_migrator = new Woo2Shopify_Image_Migrator();
        $this->logger = new Woo2Shopify_Logger();
    }
    
    /**
     * Get products for selection
     */
    public function get_products_for_selection($args = array()) {
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'search' => '',
            'category' => '',
            'status' => 'any',
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $query_args = array(
            'post_type' => 'product',
            'post_status' => $args['status'] === 'any' ? array('publish', 'draft', 'private') : $args['status'],
            'posts_per_page' => $args['limit'],
            'offset' => $args['offset'],
            'orderby' => $args['orderby'],
            'order' => $args['order'],
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_woo2shopify_migrated',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_woo2shopify_migrated',
                    'value' => '1',
                    'compare' => '!='
                )
            )
        );
        
        // Search filter
        if (!empty($args['search'])) {
            $query_args['s'] = $args['search'];
        }
        
        // Category filter
        if (!empty($args['category'])) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $args['category']
                )
            );
        }
        
        $query = new WP_Query($query_args);
        $products = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);
                
                if ($product) {
                    $products[] = array(
                        'id' => $product_id,
                        'title' => $product->get_name(),
                        'sku' => $product->get_sku(),
                        'price' => $product->get_price(),
                        'status' => $product->get_status(),
                        'type' => $product->get_type(),
                        'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                        'categories' => wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names')),
                        'date_created' => $product->get_date_created()->format('Y-m-d H:i:s'),
                        'migrated' => get_post_meta($product_id, '_woo2shopify_migrated', true) === '1',
                        'shopify_id' => get_post_meta($product_id, '_woo2shopify_shopify_id', true)
                    );
                }
            }
            wp_reset_postdata();
        }
        
        return array(
            'products' => $products,
            'total' => $query->found_posts,
            'has_more' => ($args['offset'] + $args['limit']) < $query->found_posts
        );
    }
    
    /**
     * Get pages for selection
     */
    public function get_pages_for_selection($args = array()) {
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'search' => '',
            'status' => 'publish'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $query_args = array(
            'post_type' => 'page',
            'post_status' => $args['status'],
            'posts_per_page' => $args['limit'],
            'offset' => $args['offset'],
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_woo2shopify_page_migrated',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_woo2shopify_page_migrated',
                    'value' => '1',
                    'compare' => '!='
                )
            )
        );
        
        if (!empty($args['search'])) {
            $query_args['s'] = $args['search'];
        }
        
        $query = new WP_Query($query_args);
        $pages = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $page_id = get_the_ID();
                
                $pages[] = array(
                    'id' => $page_id,
                    'title' => get_the_title(),
                    'slug' => get_post_field('post_name', $page_id),
                    'status' => get_post_status($page_id),
                    'content_length' => strlen(get_post_field('post_content', $page_id)),
                    'date_modified' => get_the_modified_date('Y-m-d H:i:s'),
                    'migrated' => get_post_meta($page_id, '_woo2shopify_page_migrated', true) === '1',
                    'shopify_id' => get_post_meta($page_id, '_woo2shopify_shopify_page_id', true)
                );
            }
            wp_reset_postdata();
        }
        
        return array(
            'pages' => $pages,
            'total' => $query->found_posts,
            'has_more' => ($args['offset'] + $args['limit']) < $query->found_posts
        );
    }
    
    /**
     * Migrate selected products
     */
    public function migrate_selected_products($product_ids, $options = array()) {
        $migration_id = woo2shopify_generate_migration_id();
        
        // Initialize progress
        woo2shopify_update_progress($migration_id, array(
            'total_products' => count($product_ids),
            'processed_products' => 0,
            'successful_products' => 0,
            'failed_products' => 0,
            'status' => 'running',
            'started_at' => current_time('mysql'),
            'type' => 'selective'
        ));
        
        $results = array(
            'successful' => 0,
            'failed' => 0,
            'errors' => array()
        );
        
        foreach ($product_ids as $index => $product_id) {
            try {
                // Update progress
                woo2shopify_update_progress($migration_id, array(
                    'processed_products' => $index + 1,
                    'status_message' => sprintf(__('Processing product %d of %d', 'woo2shopify'), $index + 1, count($product_ids))
                ));
                
                // Get product data
                $product_data = $this->wc_reader->get_product_data($product_id);
                if (!$product_data) {
                    throw new Exception(__('Product data not found', 'woo2shopify'));
                }
                
                // Map to Shopify format
                $shopify_product = $this->data_mapper->map_product($product_data);
                
                // Create product in Shopify
                $shopify_result = $this->shopify_api->create_product($shopify_product);
                if (is_wp_error($shopify_result)) {
                    throw new Exception($shopify_result->get_error_message());
                }
                
                $shopify_product_id = $shopify_result['id'];
                
                // Migrate images and videos
                if (!empty($product_data['media'])) {
                    $this->image_migrator->migrate_product_media(
                        $product_id,
                        $product_data['media'],
                        $shopify_product_id
                    );
                }
                
                // Process description videos
                $this->data_mapper->process_description_videos($product_id, $shopify_product_id);
                
                // Mark as migrated
                update_post_meta($product_id, '_woo2shopify_migrated', '1');
                update_post_meta($product_id, '_woo2shopify_shopify_id', $shopify_product_id);
                update_post_meta($product_id, '_woo2shopify_migration_id', $migration_id);
                
                $results['successful']++;
                
                $this->logger->log($migration_id, $product_id, 'product_migrated', 'success',
                    sprintf(__('Product migrated successfully (Shopify ID: %s)', 'woo2shopify'), $shopify_product_id));
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = array(
                    'product_id' => $product_id,
                    'error' => $e->getMessage()
                );
                
                $this->logger->log($migration_id, $product_id, 'product_failed', 'error', $e->getMessage());
            }
        }
        
        // Complete migration
        woo2shopify_update_progress($migration_id, array(
            'successful_products' => $results['successful'],
            'failed_products' => $results['failed'],
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
            'status_message' => sprintf(__('Migration completed: %d successful, %d failed', 'woo2shopify'), 
                $results['successful'], $results['failed'])
        ));
        
        return array(
            'success' => true,
            'migration_id' => $migration_id,
            'results' => $results
        );
    }
    
    /**
     * Migrate selected pages
     */
    public function migrate_selected_pages($page_ids, $options = array()) {
        $results = array(
            'successful' => 0,
            'failed' => 0,
            'errors' => array()
        );
        
        foreach ($page_ids as $page_id) {
            try {
                $page_data = $this->prepare_page_data($page_id);
                $shopify_page = $this->map_page_to_shopify($page_data);
                
                $shopify_result = $this->shopify_api->create_page($shopify_page);
                if (is_wp_error($shopify_result)) {
                    throw new Exception($shopify_result->get_error_message());
                }
                
                // Mark as migrated
                update_post_meta($page_id, '_woo2shopify_page_migrated', '1');
                update_post_meta($page_id, '_woo2shopify_shopify_page_id', $shopify_result['id']);
                
                $results['successful']++;
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = array(
                    'page_id' => $page_id,
                    'error' => $e->getMessage()
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Prepare page data
     */
    private function prepare_page_data($page_id) {
        $page = get_post($page_id);
        if (!$page) {
            throw new Exception(__('Page not found', 'woo2shopify'));
        }
        
        return array(
            'id' => $page_id,
            'title' => $page->post_title,
            'content' => $page->post_content,
            'slug' => $page->post_name,
            'status' => $page->post_status,
            'date_created' => $page->post_date,
            'date_modified' => $page->post_modified,
            'excerpt' => $page->post_excerpt,
            'meta_title' => get_post_meta($page_id, '_yoast_wpseo_title', true),
            'meta_description' => get_post_meta($page_id, '_yoast_wpseo_metadesc', true)
        );
    }
    
    /**
     * Map page to Shopify format
     */
    private function map_page_to_shopify($page_data) {
        // Clean HTML content
        $content = $this->data_mapper->clean_html_content($page_data['content']);
        
        return array(
            'title' => $page_data['title'],
            'body_html' => $content,
            'handle' => woo2shopify_sanitize_handle($page_data['slug']),
            'published' => $page_data['status'] === 'publish',
            'created_at' => $page_data['date_created'],
            'updated_at' => $page_data['date_modified'],
            'summary_html' => $page_data['excerpt'],
            'template_suffix' => '',
            'metafields' => array(
                array(
                    'namespace' => 'seo',
                    'key' => 'title',
                    'value' => $page_data['meta_title'] ?: $page_data['title'],
                    'type' => 'single_line_text_field'
                ),
                array(
                    'namespace' => 'seo',
                    'key' => 'description',
                    'value' => $page_data['meta_description'] ?: '',
                    'type' => 'multi_line_text_field'
                )
            )
        );
    }
}
