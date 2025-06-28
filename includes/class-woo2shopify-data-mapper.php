<?php
/**
 * Data Mapper Class - Converts WooCommerce data to Shopify format
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Woo2Shopify_Data_Mapper {

    /**
     * Video processor instance
     */
    private $video_processor;

    /**
     * Constructor
     */
    public function __construct() {
        $this->video_processor = new Woo2Shopify_Video_Processor();
    }

    /**
     * Convert WooCommerce product to Shopify format
     */
    public function map_product($wc_product_data) {
        $shopify_product = array(
            'title' => $this->sanitize_title($wc_product_data['name']),
            'body_html' => $this->format_description($wc_product_data),
            'vendor' => $this->get_vendor($wc_product_data),
            'product_type' => $this->get_product_type($wc_product_data),
            'handle' => woo2shopify_sanitize_handle($wc_product_data['slug']),
            'status' => woo2shopify_convert_product_status($wc_product_data['status']),
            'published_at' => $this->format_date($wc_product_data['date_created']),
            'tags' => $this->format_tags($wc_product_data['tags']),
            'options' => $this->map_product_options($wc_product_data),
            'variants' => $this->map_product_variants($wc_product_data),
            'images' => $this->map_product_images($wc_product_data['images']),
            'metafields' => $this->map_metafields($wc_product_data)
        );
        
        // Add SEO fields if available
        $seo_data = $this->get_seo_data($wc_product_data);
        if (!empty($seo_data)) {
            $shopify_product = array_merge($shopify_product, $seo_data);
        }
        
        return $shopify_product;
    }
    
    /**
     * Sanitize product title
     */
    private function sanitize_title($title) {
        // Remove HTML tags and decode entities
        $title = wp_strip_all_tags($title);
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        
        // Trim and limit length (Shopify limit is 255 characters)
        $title = trim($title);
        if (strlen($title) > 255) {
            $title = substr($title, 0, 252) . '...';
        }
        
        return $title;
    }
    
    /**
     * Format product description
     */
    private function format_description($wc_product_data) {
        $description = '';

        // Main description
        if (!empty($wc_product_data['description'])) {
            $description .= $wc_product_data['description'];
        }

        // Short description
        if (!empty($wc_product_data['short_description'])) {
            if (!empty($description)) {
                $description .= '<br><br>';
            }
            $description .= '<strong>' . __('Summary:', 'woo2shopify') . '</strong><br>';
            $description .= $wc_product_data['short_description'];
        }

        // Add product specifications
        $specs = $this->get_product_specifications($wc_product_data);
        if (!empty($specs)) {
            $description .= '<br><br><strong>' . __('Specifications:', 'woo2shopify') . '</strong><br>';
            $description .= $specs;
        }

        // Clean and process HTML content
        $description = $this->clean_html_content($description);

        return $description;
    }
    
    /**
     * Get product specifications
     */
    private function get_product_specifications($wc_product_data) {
        $specs = array();
        
        // Dimensions
        if (!empty($wc_product_data['weight'])) {
            $specs[] = __('Weight:', 'woo2shopify') . ' ' . $wc_product_data['weight'] . ' ' . get_option('woocommerce_weight_unit');
        }
        
        if (!empty($wc_product_data['length']) || !empty($wc_product_data['width']) || !empty($wc_product_data['height'])) {
            $dimensions = array();
            if (!empty($wc_product_data['length'])) $dimensions[] = $wc_product_data['length'];
            if (!empty($wc_product_data['width'])) $dimensions[] = $wc_product_data['width'];
            if (!empty($wc_product_data['height'])) $dimensions[] = $wc_product_data['height'];
            
            if (!empty($dimensions)) {
                $specs[] = __('Dimensions:', 'woo2shopify') . ' ' . implode(' × ', $dimensions) . ' ' . get_option('woocommerce_dimension_unit');
            }
        }
        
        // SKU
        if (!empty($wc_product_data['sku'])) {
            $specs[] = __('SKU:', 'woo2shopify') . ' ' . $wc_product_data['sku'];
        }
        
        return !empty($specs) ? implode('<br>', $specs) : '';
    }

    /**
     * Clean HTML content for Shopify
     */
    private function clean_html_content($html) {
        if (empty($html)) {
            return '';
        }

        // Remove problematic SVG icons that display as large emojis
        $html = $this->remove_svg_icons($html);

        // Process video links and convert to proper video embeds
        $html = $this->process_video_links($html);

        // Clean up empty paragraphs and redundant content
        $html = $this->clean_redundant_content($html);

        // Fix broken HTML structure
        $html = $this->fix_html_structure($html);

        return $html;
    }

    /**
     * Remove SVG icons that cause display issues
     */
    private function remove_svg_icons($html) {
        // Remove SVG elements completely
        $html = preg_replace('/<svg[^>]*>.*?<\/svg>/is', '', $html);

        // Remove empty anchor tags that contained only SVG
        $html = preg_replace('/<a[^>]*aria-label="[^"]*"[^>]*>\s*<\/a>/i', '', $html);

        // Clean up empty paragraphs left after SVG removal
        $html = preg_replace('/<p>\s*<\/p>/i', '', $html);

        return $html;
    }

    /**
     * Process video links and convert to proper embeds
     */
    private function process_video_links($html) {
        // Use video processor to extract videos
        $videos = $this->video_processor->extract_videos_from_html($html);

        foreach ($videos as $video) {
            // Store video URL for later processing
            $this->store_video_for_migration($video['url']);

            // Replace video URL with theme compatible video embed
            $video_html = $this->video_processor->create_theme_compatible_video($video['url'], array(
                'aspect_ratio' => 'sixteen-nine',
                'theme_class' => 'product-video-container',
                'container_class' => '',
                'controls' => true,
                'autoplay' => false
            ));

            // Replace original match with video embed
            $html = str_replace($video['original_match'], $video_html, $html);
        }

        return $html;
    }

    /**
     * Store video URL for migration
     */
    private function store_video_for_migration($video_url) {
        // Check if video already exists in our migration cache
        $video_hash = md5($video_url);
        $cached_videos = get_option('woo2shopify_cached_videos', array());

        if (!isset($cached_videos[$video_hash])) {
            $cached_videos[$video_hash] = array(
                'url' => $video_url,
                'migrated' => false,
                'shopify_url' => null,
                'first_seen' => current_time('mysql')
            );
            update_option('woo2shopify_cached_videos', $cached_videos);
        }

        return $video_hash;
    }



    /**
     * Clean redundant content (duplicate sections)
     */
    private function clean_redundant_content($html) {
        // Remove duplicate sections that appear multiple times
        $sections = array();

        // Split by common section markers
        $parts = preg_split('/(<h[1-6][^>]*>.*?<\/h[1-6]>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        $cleaned_html = '';
        $seen_sections = array();

        foreach ($parts as $part) {
            if (empty(trim($part))) {
                continue;
            }

            // Check if this is a heading
            if (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $part, $heading_match)) {
                $heading_text = strip_tags($heading_match[1]);

                // Skip duplicate headings
                if (in_array($heading_text, $seen_sections)) {
                    continue;
                }
                $seen_sections[] = $heading_text;
            }

            $cleaned_html .= $part;
        }

        return $cleaned_html;
    }

    /**
     * Fix HTML structure issues
     */
    private function fix_html_structure($html) {
        // Remove empty paragraphs
        $html = preg_replace('/<p>\s*<\/p>/i', '', $html);

        // Remove paragraphs that only contain <br> tags
        $html = preg_replace('/<p>\s*(<br\s*\/?>)+\s*<\/p>/i', '', $html);

        // Fix multiple consecutive <br> tags
        $html = preg_replace('/(<br\s*\/?>){3,}/i', '<br><br>', $html);

        // Remove empty anchor tags
        $html = preg_replace('/<a[^>]*>\s*<\/a>/i', '', $html);

        // Clean up whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        $html = trim($html);

        return $html;
    }

    /**
     * Process videos found in product descriptions
     */
    public function process_description_videos($product_id, $shopify_product_id) {
        $cached_videos = get_option('woo2shopify_cached_videos', array());
        $processed_videos = array();

        foreach ($cached_videos as $video_hash => $video_data) {
            if (!$video_data['migrated']) {
                // Create video metafield for this product
                $metafield_data = array(
                    'namespace' => 'custom',
                    'key' => 'product_video_url',
                    'value' => $video_data['url'],
                    'type' => 'url'
                );

                // Use the Shopify API to create metafield
                if (class_exists('Woo2Shopify_Shopify_API')) {
                    $shopify_api = new Woo2Shopify_Shopify_API();
                    $result = $shopify_api->create_product_metafield($shopify_product_id, $metafield_data);

                    if (!is_wp_error($result)) {
                        // Update cache
                        $cached_videos[$video_hash]['migrated'] = true;
                        $cached_videos[$video_hash]['metafield_id'] = $result['id'] ?? null;
                        $processed_videos[] = array(
                            'video_hash' => $video_hash,
                            'url' => $video_data['url'],
                            'metafield_id' => $result['id'] ?? null
                        );
                    }
                }
            }
        }

        // Update cached videos
        update_option('woo2shopify_cached_videos', $cached_videos);

        return $processed_videos;
    }
    
    /**
     * Get vendor from product data
     */
    private function get_vendor($wc_product_data) {
        // Try to get vendor from meta data or attributes
        if (isset($wc_product_data['meta_data']['_vendor'])) {
            return $wc_product_data['meta_data']['_vendor'];
        }
        
        // Check attributes for brand/manufacturer
        foreach ($wc_product_data['attributes'] as $attribute) {
            if (in_array(strtolower($attribute['name']), array('brand', 'manufacturer', 'vendor'))) {
                return !empty($attribute['options']) ? $attribute['options'][0] : '';
            }
        }
        
        return get_bloginfo('name'); // Default to site name
    }
    
    /**
     * Get product type
     */
    private function get_product_type($wc_product_data) {
        // Use the first category as product type
        if (!empty($wc_product_data['categories'])) {
            return $wc_product_data['categories'][0]['name'];
        }
        
        return '';
    }
    
    /**
     * Format tags
     */
    private function format_tags($tags) {
        $tag_names = array();
        
        foreach ($tags as $tag) {
            $tag_names[] = $tag['name'];
        }
        
        return implode(', ', $tag_names);
    }
    
    /**
     * Map product options (attributes)
     */
    private function map_product_options($wc_product_data) {
        $options = array();
        
        // For variable products, get variation attributes
        if ($wc_product_data['type'] === 'variable' && !empty($wc_product_data['variations'])) {
            $variation_attributes = array();
            
            foreach ($wc_product_data['variations'] as $variation) {
                foreach ($variation['attributes'] as $attr_name => $attr_value) {
                    $clean_name = str_replace('attribute_', '', $attr_name);
                    $clean_name = str_replace('pa_', '', $clean_name);
                    $clean_name = ucwords(str_replace('-', ' ', $clean_name));
                    
                    if (!isset($variation_attributes[$clean_name])) {
                        $variation_attributes[$clean_name] = array();
                    }
                    
                    if (!empty($attr_value) && !in_array($attr_value, $variation_attributes[$clean_name])) {
                        $variation_attributes[$clean_name][] = $attr_value;
                    }
                }
            }
            
            $option_index = 1;
            foreach ($variation_attributes as $name => $values) {
                if ($option_index <= 3) { // Shopify supports max 3 options
                    $options[] = array(
                        'name' => $name,
                        'values' => array_values($values)
                    );
                    $option_index++;
                }
            }
        } else {
            // For simple products, create default option
            $options[] = array(
                'name' => 'Title',
                'values' => array('Default Title')
            );
        }
        
        return $options;
    }
    
    /**
     * Map product variants
     */
    private function map_product_variants($wc_product_data) {
        $variants = array();
        
        if ($wc_product_data['type'] === 'variable' && !empty($wc_product_data['variations'])) {
            foreach ($wc_product_data['variations'] as $variation) {
                $variant = $this->map_single_variant($variation, $wc_product_data);
                if ($variant) {
                    $variants[] = $variant;
                }
            }
        } else {
            // Simple product - create single variant
            $variant = $this->map_simple_product_variant($wc_product_data);
            if ($variant) {
                $variants[] = $variant;
            }
        }
        
        return $variants;
    }
    
    /**
     * Map single variant
     */
    private function map_single_variant($variation, $parent_product) {
        $variant = array(
            'sku' => $variation['sku'] ?: $parent_product['sku'],
            'price' => $this->format_price($variation['price']),
            'compare_at_price' => $this->get_compare_price($variation),
            'inventory_quantity' => $this->get_inventory_quantity($variation),
            'inventory_policy' => woo2shopify_convert_stock_status($variation['stock_status'], $variation['manage_stock']),
            'fulfillment_service' => 'manual',
            'inventory_management' => $variation['manage_stock'] ? 'shopify' : null,
            'requires_shipping' => !$parent_product['virtual'],
            'taxable' => $parent_product['tax_status'] === 'taxable',
            'weight' => $this->format_weight($variation['weight']),
            'weight_unit' => $this->get_weight_unit()
        );
        
        // Add option values
        $option_values = $this->get_variant_option_values($variation['attributes']);
        $variant = array_merge($variant, $option_values);
        
        return $variant;
    }
    
    /**
     * Map simple product as variant
     */
    private function map_simple_product_variant($wc_product_data) {
        return array(
            'sku' => $wc_product_data['sku'],
            'price' => $this->format_price($wc_product_data['price']),
            'compare_at_price' => $this->get_compare_price($wc_product_data),
            'inventory_quantity' => $this->get_inventory_quantity($wc_product_data),
            'inventory_policy' => woo2shopify_convert_stock_status($wc_product_data['stock_status'], $wc_product_data['manage_stock']),
            'fulfillment_service' => 'manual',
            'inventory_management' => $wc_product_data['manage_stock'] ? 'shopify' : null,
            'requires_shipping' => !$wc_product_data['virtual'],
            'taxable' => $wc_product_data['tax_status'] === 'taxable',
            'weight' => $this->format_weight($wc_product_data['weight']),
            'weight_unit' => $this->get_weight_unit(),
            'option1' => 'Default Title'
        );
    }
    
    /**
     * Format price
     */
    private function format_price($price) {
        return !empty($price) ? number_format((float)$price, 2, '.', '') : '0.00';
    }
    
    /**
     * Get compare at price (regular price when on sale)
     */
    private function get_compare_price($product_data) {
        if (!empty($product_data['sale_price']) && !empty($product_data['regular_price'])) {
            return $this->format_price($product_data['regular_price']);
        }
        return null;
    }
    
    /**
     * Get inventory quantity
     */
    private function get_inventory_quantity($product_data) {
        if ($product_data['manage_stock'] && isset($product_data['stock_quantity'])) {
            return max(0, intval($product_data['stock_quantity']));
        }
        return 0;
    }
    
    /**
     * Format weight
     */
    private function format_weight($weight) {
        return !empty($weight) ? floatval($weight) : 0;
    }
    
    /**
     * Get weight unit
     */
    private function get_weight_unit() {
        $wc_unit = get_option('woocommerce_weight_unit', 'kg');
        
        $unit_map = array(
            'kg' => 'kg',
            'g' => 'g',
            'lbs' => 'lb',
            'oz' => 'oz'
        );
        
        return isset($unit_map[$wc_unit]) ? $unit_map[$wc_unit] : 'kg';
    }
    
    /**
     * Get variant option values
     */
    private function get_variant_option_values($attributes) {
        $option_values = array();
        $option_index = 1;
        
        foreach ($attributes as $attr_name => $attr_value) {
            if ($option_index <= 3 && !empty($attr_value)) {
                $option_values["option{$option_index}"] = $attr_value;
                $option_index++;
            }
        }
        
        // Ensure at least option1 exists
        if (empty($option_values)) {
            $option_values['option1'] = 'Default Title';
        }
        
        return $option_values;
    }
    
    /**
     * Map product images
     */
    private function map_product_images($images) {
        $shopify_images = array();
        
        foreach ($images as $image) {
            $shopify_image = array(
                'src' => $image['url'],
                'alt' => $image['alt'] ?: $image['title']
            );
            
            $shopify_images[] = $shopify_image;
        }
        
        return $shopify_images;
    }
    
    /**
     * Map metafields
     */
    private function map_metafields($wc_product_data) {
        $metafields = array();
        
        // Add WooCommerce ID as metafield
        $metafields[] = array(
            'namespace' => 'woocommerce',
            'key' => 'product_id',
            'value' => (string)$wc_product_data['id'],
            'type' => 'single_line_text_field'
        );

        // Add translations as metafields (compatible with Shopify Translate & Adapt)
        if (!empty($wc_product_data['translations'])) {
            // Legacy format for backward compatibility
            foreach ($wc_product_data['translations'] as $lang_code => $translation) {
                $metafields[] = array(
                    'namespace' => 'translations',
                    'key' => $lang_code . '_title',
                    'value' => $translation['name'],
                    'type' => 'single_line_text_field'
                );

                $metafields[] = array(
                    'namespace' => 'translations',
                    'key' => $lang_code . '_description',
                    'value' => $translation['description'],
                    'type' => 'multi_line_text_field'
                );

                if (!empty($translation['short_description'])) {
                    $metafields[] = array(
                        'namespace' => 'translations',
                        'key' => $lang_code . '_short_description',
                        'value' => $translation['short_description'],
                        'type' => 'multi_line_text_field'
                    );
                }
            }

            // Shopify Translate & Adapt compatible format
            $translation_data = array();
            foreach ($wc_product_data['translations'] as $lang_code => $translation) {
                $translation_data[$lang_code] = array(
                    'title' => $translation['name'],
                    'description' => $translation['description'],
                    'short_description' => $translation['short_description'] ?? ''
                );
            }

            // Store as JSON for Shopify Translate & Adapt
            $metafields[] = array(
                'namespace' => 'shopify_translate',
                'key' => 'product_translations',
                'value' => json_encode($translation_data),
                'type' => 'json'
            );

            // Store available languages
            $metafields[] = array(
                'namespace' => 'shopify_translate',
                'key' => 'available_languages',
                'value' => implode(',', array_keys($wc_product_data['translations'])),
                'type' => 'single_line_text_field'
            );
        }

        // Add multi-currency prices as metafields
        if (!empty($wc_product_data['currencies'])) {
            foreach ($wc_product_data['currencies'] as $currency_code => $currency_data) {
                if (!$currency_data['is_default']) {
                    $metafields[] = array(
                        'namespace' => 'currencies',
                        'key' => $currency_code . '_price',
                        'value' => $currency_data['price'],
                        'type' => 'money'
                    );

                    if (!empty($currency_data['regular_price'])) {
                        $metafields[] = array(
                            'namespace' => 'currencies',
                            'key' => $currency_code . '_regular_price',
                            'value' => $currency_data['regular_price'],
                            'type' => 'money'
                        );
                    }

                    if (!empty($currency_data['sale_price'])) {
                        $metafields[] = array(
                            'namespace' => 'currencies',
                            'key' => $currency_code . '_sale_price',
                            'value' => $currency_data['sale_price'],
                            'type' => 'money'
                        );
                    }
                }
            }
        }
        
        // Add custom meta data
        foreach ($wc_product_data['meta_data'] as $key => $value) {
            if (!empty($value) && is_scalar($value)) {
                $metafields[] = array(
                    'namespace' => 'custom',
                    'key' => ltrim($key, '_'),
                    'value' => (string)$value,
                    'type' => 'single_line_text_field'
                );
            }
        }
        
        return $metafields;
    }
    
    /**
     * Get SEO data
     */
    private function get_seo_data($wc_product_data) {
        $seo_data = array();
        
        // Check for Yoast SEO data
        if (isset($wc_product_data['meta_data']['_yoast_wpseo_title'])) {
            $seo_data['seo_title'] = $wc_product_data['meta_data']['_yoast_wpseo_title'];
        }
        
        if (isset($wc_product_data['meta_data']['_yoast_wpseo_metadesc'])) {
            $seo_data['seo_description'] = $wc_product_data['meta_data']['_yoast_wpseo_metadesc'];
        }
        
        return $seo_data;
    }
    
    /**
     * Format date for Shopify
     */
    private function format_date($date) {
        if (is_a($date, 'WC_DateTime')) {
            return $date->format('c'); // ISO 8601 format
        }
        
        if (is_string($date)) {
            $datetime = new DateTime($date);
            return $datetime->format('c');
        }
        
        return null;
    }
    
    /**
     * Map collection (category)
     */
    public function map_collection($wc_category) {
        return array(
            'title' => $wc_category['name'],
            'handle' => woo2shopify_sanitize_handle($wc_category['slug']),
            'body_html' => $wc_category['description'],
            'sort_order' => 'best-selling',
            'published' => true
        );
    }
}
