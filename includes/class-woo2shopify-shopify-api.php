<?php
/**
 * Shopify API Client Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Woo2Shopify_Shopify_API {
    
    /**
     * Shopify store URL
     */
    private $store_url;
    
    /**
     * Shopify access token (for Custom Apps)
     */
    private $access_token;

    /**
     * Shopify API Key (for Private Apps)
     */
    private $api_key;

    /**
     * Shopify API Secret (for Private Apps)
     */
    private $api_secret;
    
    /**
     * API version
     */
    private $api_version = '2023-10';
    
    /**
     * Request timeout (increased for large product uploads)
     */
    private $timeout = 60;
    
    /**
     * Rate limit tracking
     */
    private $rate_limit_remaining = 40;
    private $rate_limit_reset_time = 0;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->store_url = get_option('woo2shopify_shopify_store_url');
        $this->access_token = get_option('woo2shopify_shopify_access_token');
        $this->api_key = get_option('woo2shopify_shopify_api_key');
        $this->api_secret = get_option('woo2shopify_shopify_api_secret');
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        // Debug: Log connection attempt
        error_log('Woo2Shopify: Testing connection...');
        error_log('Store URL: ' . $this->store_url);
        error_log('Has Access Token: ' . (!empty($this->access_token) ? 'Yes' : 'No'));
        error_log('Has API Key: ' . (!empty($this->api_key) ? 'Yes' : 'No'));

        // Check if we have either Access Token (Custom App) or API Key/Secret (Private App)
        $has_access_token = !empty($this->store_url) && !empty($this->access_token);
        $has_api_credentials = !empty($this->store_url) && !empty($this->api_key) && !empty($this->api_secret);

        if (!$has_access_token && !$has_api_credentials) {
            error_log('Woo2Shopify: Missing credentials');
            return array(
                'success' => false,
                'message' => __('Store URL and either Access Token (Custom App) or API Key + Secret (Private App) are required', 'woo2shopify')
            );
        }

        // Validate store URL format
        if (!filter_var($this->store_url, FILTER_VALIDATE_URL)) {
            error_log('Woo2Shopify: Invalid store URL format');
            return array(
                'success' => false,
                'message' => __('Invalid store URL format. Use: https://your-store.myshopify.com', 'woo2shopify')
            );
        }

        error_log('Woo2Shopify: Making API request to shop.json');
        $response = $this->make_request('GET', 'shop.json');

        if (is_wp_error($response)) {
            error_log('Woo2Shopify: API request failed - ' . $response->get_error_message());
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'debug_info' => array(
                    'store_url' => $this->store_url,
                    'auth_method' => !empty($this->access_token) ? 'Access Token' : 'API Key/Secret',
                    'error_code' => $response->get_error_code()
                )
            );
        }

        error_log('Woo2Shopify: API response received');

        if (isset($response['shop'])) {
            error_log('Woo2Shopify: Connection successful - ' . $response['shop']['name']);
            return array(
                'success' => true,
                'message' => sprintf(
                    __('Connected to %s successfully! (Domain: %s)', 'woo2shopify'),
                    $response['shop']['name'],
                    $response['shop']['domain']
                ),
                'shop_info' => $response['shop'],
                'auth_method' => !empty($this->access_token) ? 'Custom App (Access Token)' : 'Private App (API Key/Secret)'
            );
        }

        error_log('Woo2Shopify: Invalid API response structure');
        return array(
            'success' => false,
            'message' => __('Invalid response from Shopify API', 'woo2shopify'),
            'debug_info' => array(
                'response_keys' => array_keys($response),
                'response_sample' => array_slice($response, 0, 3, true)
            )
        );
    }
    
    /**
     * Create a product
     */
    public function create_product($product_data) {
        $this->check_rate_limit();
        
        $response = $this->make_request('POST', 'products.json', array(
            'product' => $product_data
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['product']) ? $response['product'] : false;
    }
    
    /**
     * Update a product
     */
    public function update_product($product_id, $product_data) {
        $this->check_rate_limit();
        
        $response = $this->make_request('PUT', "products/{$product_id}.json", array(
            'product' => $product_data
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['product']) ? $response['product'] : false;
    }
    
    /**
     * Get a product
     */
    public function get_product($product_id) {
        $this->check_rate_limit();
        
        $response = $this->make_request('GET', "products/{$product_id}.json");
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['product']) ? $response['product'] : false;
    }
    
    /**
     * Delete a product
     */
    public function delete_product($product_id) {
        $this->check_rate_limit();
        
        $response = $this->make_request('DELETE', "products/{$product_id}.json");
        
        return !is_wp_error($response);
    }
    
    /**
     * Create a collection
     */
    public function create_collection($collection_data) {
        $this->check_rate_limit();

        $response = $this->make_request('POST', 'custom_collections.json', array(
            'custom_collection' => $collection_data
        ));

        if (is_wp_error($response)) {
            // Check if it's a duplicate handle error
            if (strpos($response->get_error_message(), 'handle') !== false &&
                strpos($response->get_error_message(), 'already been taken') !== false) {

                // Try with a modified handle
                $original_handle = $collection_data['handle'];
                $collection_data['handle'] = $original_handle . '-' . time();

                woo2shopify_log("Collection handle '$original_handle' already exists, trying with '{$collection_data['handle']}'", 'warning');

                $response = $this->make_request('POST', 'custom_collections.json', array(
                    'custom_collection' => $collection_data
                ));

                if (is_wp_error($response)) {
                    return $response;
                }
            } else {
                return $response;
            }
        }
        
        return isset($response['custom_collection']) ? $response['custom_collection'] : false;
    }
    
    /**
     * Get collections
     */
    public function get_collections($limit = 250) {
        $this->check_rate_limit();
        
        $response = $this->make_request('GET', 'custom_collections.json', array(
            'limit' => $limit
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['custom_collections']) ? $response['custom_collections'] : array();
    }

    /**
     * Add product to collection
     */
    public function add_product_to_collection($collection_id, $product_id) {
        $this->check_rate_limit();

        woo2shopify_log("Adding product $product_id to collection $collection_id", 'info');

        $response = $this->make_request('POST', "custom_collections/{$collection_id}/collects.json", array(
            'collect' => array(
                'product_id' => $product_id,
                'collection_id' => $collection_id
            )
        ));

        if (is_wp_error($response)) {
            woo2shopify_log("Failed to add product to collection: " . $response->get_error_message(), 'error');
            return $response;
        }

        woo2shopify_log("Successfully added product $product_id to collection $collection_id", 'info');
        return $response['collect'];
    }

    /**
     * Get collection by handle
     */
    public function get_collection_by_handle($handle) {
        $this->check_rate_limit();

        $response = $this->make_request('GET', 'custom_collections.json', array(
            'handle' => $handle,
            'limit' => 1
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        if (!empty($response['custom_collections'])) {
            return $response['custom_collections'][0];
        }

        return null;
    }

    /**
     * Upload an image
     */
    public function upload_product_image($product_id, $image_data) {
        $this->check_rate_limit();

        $response = $this->make_request('POST', "products/{$product_id}/images.json", array(
            'image' => $image_data
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        return isset($response['image']) ? $response['image'] : false;
    }

    /**
     * Create product metafield (for video URLs)
     */
    public function create_product_metafield($product_id, $metafield_data) {
        $this->check_rate_limit();

        $response = $this->make_request('POST', "products/{$product_id}/metafields.json", array(
            'metafield' => $metafield_data
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        return isset($response['metafield']) ? $response['metafield'] : false;
    }

    /**
     * Create collection metafield
     */
    public function create_collection_metafield($collection_id, $metafield_data) {
        $this->check_rate_limit();

        $response = $this->make_request('POST', "custom_collections/{$collection_id}/metafields.json", array(
            'metafield' => $metafield_data
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        return isset($response['metafield']) ? $response['metafield'] : false;
    }

    /**
     * Upload video (using GraphQL for staged uploads)
     */
    public function upload_product_video($product_id, $video_data) {
        // Note: This is a simplified implementation
        // Full video upload requires GraphQL API and staged uploads

        // For now, store video URL as metafield
        $metafield_data = array(
            'namespace' => 'custom',
            'key' => 'product_video_url',
            'value' => $video_data['url'],
            'type' => 'url'
        );

        return $this->create_product_metafield($product_id, $metafield_data);
    }
    
    /**
     * Make API request
     */
    private function make_request($method, $endpoint, $data = null) {
        $url = trailingslashit($this->store_url) . "admin/api/{$this->api_version}/{$endpoint}";

        $headers = array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'Woo2Shopify/' . WOO2SHOPIFY_VERSION
        );

        // Use appropriate authentication method
        if (!empty($this->access_token)) {
            // Custom App authentication
            $headers['X-Shopify-Access-Token'] = $this->access_token;
        } elseif (!empty($this->api_key) && !empty($this->api_secret)) {
            // Private App authentication (Basic Auth)
            $headers['Authorization'] = 'Basic ' . base64_encode($this->api_key . ':' . $this->api_secret);
        }
        
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => $this->timeout,
            'sslverify' => true
        );

        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = wp_json_encode($data);
        } elseif ($data && $method === 'GET') {
            $url = add_query_arg($data, $url);
        }

        // Debug: Log request details
        error_log('Woo2Shopify: Making request to ' . $url);
        error_log('Woo2Shopify: Request method: ' . $method);
        error_log('Woo2Shopify: Request headers: ' . print_r(array_keys($headers), true));

        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            error_log('Woo2Shopify: WP_Error - ' . $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        // Debug: Log response details
        error_log('Woo2Shopify: Response code: ' . $response_code);
        error_log('Woo2Shopify: Response body length: ' . strlen($response_body));
        if ($response_code >= 400) {
            error_log('Woo2Shopify: Error response body: ' . $response_body);
        }
        
        // Update rate limit info
        $this->update_rate_limit_info($response_headers);
        
        // Handle different response codes
        if ($response_code >= 200 && $response_code < 300) {
            return json_decode($response_body, true);
        } elseif ($response_code === 429) {
            // Rate limited
            return new WP_Error('rate_limited', __('Rate limit exceeded. Please try again later.', 'woo2shopify'));
        } elseif ($response_code >= 400) {
            $error_data = json_decode($response_body, true);
            $error_message = sprintf(__('HTTP Error %d', 'woo2shopify'), $response_code);

            if (isset($error_data['errors'])) {
                if (is_array($error_data['errors'])) {
                    $error_parts = array();
                    foreach ($error_data['errors'] as $field => $messages) {
                        if (is_array($messages)) {
                            $error_parts[] = $field . ': ' . implode(', ', $messages);
                        } else {
                            $error_parts[] = $field . ': ' . $messages;
                        }
                    }
                    $error_message = implode('; ', $error_parts);
                } else {
                    $error_message = $error_data['errors'];
                }
            }
            
            return new WP_Error('api_error', $error_message, array('response_code' => $response_code));
        }
        
        return new WP_Error('unknown_error', __('Unknown API error', 'woo2shopify'));
    }
    
    /**
     * Update rate limit information
     */
    private function update_rate_limit_info($headers) {
        if (isset($headers['x-shopify-shop-api-call-limit'])) {
            $limit_info = explode('/', $headers['x-shopify-shop-api-call-limit']);
            if (count($limit_info) === 2) {
                $this->rate_limit_remaining = intval($limit_info[1]) - intval($limit_info[0]);
            }
        }
        
        if (isset($headers['retry-after'])) {
            $this->rate_limit_reset_time = time() + intval($headers['retry-after']);
        }
    }
    
    /**
     * Check and handle rate limiting
     */
    private function check_rate_limit() {
        // More conservative rate limiting to prevent timeouts
        if ($this->rate_limit_remaining <= 5) {
            error_log('Woo2Shopify: Rate limit low (' . $this->rate_limit_remaining . '), waiting...');
            $wait_time = max(2, $this->rate_limit_reset_time - time());
            if ($wait_time > 0 && $wait_time <= 15) {
                sleep($wait_time);
            } else {
                // Default wait if no reset time
                sleep(2);
            }
        }

        // Always add a small delay between requests
        usleep(500000); // 0.5 seconds between requests
    }
    
    /**
     * Get rate limit status
     */
    public function get_rate_limit_status() {
        return array(
            'remaining' => $this->rate_limit_remaining,
            'reset_time' => $this->rate_limit_reset_time
        );
    }
    
    /**
     * Validate webhook
     */
    public function validate_webhook($data, $hmac_header) {
        $calculated_hmac = base64_encode(hash_hmac('sha256', $data, $this->access_token, true));
        return hash_equals($calculated_hmac, $hmac_header);
    }
    
    /**
     * Get shop information
     */
    public function get_shop_info() {
        $response = $this->make_request('GET', 'shop.json');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['shop']) ? $response['shop'] : false;
    }
    
    /**
     * Get product count
     */
    public function get_product_count() {
        $response = $this->make_request('GET', 'products/count.json');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['count']) ? intval($response['count']) : 0;
    }
    
    /**
     * Batch create products (using GraphQL for better performance)
     */
    public function batch_create_products($products_data) {
        // This would require GraphQL implementation for bulk operations
        // For now, we'll process them individually with rate limiting
        $results = array();
        
        foreach ($products_data as $product_data) {
            $result = $this->create_product($product_data);
            $results[] = $result;
            
            // Small delay to respect rate limits
            usleep(250000); // 0.25 seconds
        }
        
        return $results;
    }

    /**
     * Create page
     */
    public function create_page($page_data) {
        $endpoint = 'pages.json';

        $data = array(
            'page' => $page_data
        );

        $response = $this->make_request('POST', $endpoint, $data);

        if (is_wp_error($response)) {
            return $response;
        }

        return isset($response['page']) ? $response['page'] : false;
    }

    /**
     * Update page
     */
    public function update_page($page_id, $page_data) {
        $endpoint = 'pages/' . $page_id . '.json';

        $data = array(
            'page' => $page_data
        );

        $response = $this->make_request('PUT', $endpoint, $data);

        if (is_wp_error($response)) {
            return $response;
        }

        return isset($response['page']) ? $response['page'] : false;
    }

    /**
     * Get page
     */
    public function get_page($page_id) {
        $endpoint = 'pages/' . $page_id . '.json';

        $response = $this->make_request('GET', $endpoint);

        if (is_wp_error($response)) {
            return $response;
        }

        return isset($response['page']) ? $response['page'] : false;
    }

    /**
     * Get pages
     */
    public function get_pages($params = array()) {
        $endpoint = 'pages.json';

        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }

        $response = $this->make_request('GET', $endpoint);

        if (is_wp_error($response)) {
            return $response;
        }

        return isset($response['pages']) ? $response['pages'] : array();
    }

    /**
     * Delete page
     */
    public function delete_page($page_id) {
        $endpoint = 'pages/' . $page_id . '.json';

        $response = $this->make_request('DELETE', $endpoint);

        if (is_wp_error($response)) {
            return $response;
        }

        return true;
    }

    /**
     * Get page count
     */
    public function get_page_count() {
        $response = $this->make_request('GET', 'pages/count.json');

        if (is_wp_error($response)) {
            return $response;
        }

        return isset($response['count']) ? intval($response['count']) : 0;
    }
}
