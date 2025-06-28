<?php
/**
 * WooCommerce Data Reader Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Woo2Shopify_WooCommerce_Reader {
    
    /**
     * Get products for migration
     */
    public function get_products($args = array()) {
        $defaults = array(
            'status' => array('publish', 'draft', 'private'),
            'limit' => -1,
            'offset' => 0,
            'orderby' => 'ID',
            'order' => 'ASC',
            'include_variations' => true,
            'meta_query' => array(),
            'date_query' => array()
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $products = wc_get_products($args);
        $product_data = array();
        
        foreach ($products as $product) {
            $product_info = $this->get_product_data($product);
            if ($product_info) {
                $product_data[] = $product_info;
            }
        }
        
        return $product_data;
    }
    
    /**
     * Get single product data
     */
    public function get_product_data($product) {
        if (is_numeric($product)) {
            $product = wc_get_product($product);
        }
        
        if (!$product || !is_a($product, 'WC_Product')) {
            return false;
        }
        
        $product_data = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'slug' => $product->get_slug(),
            'status' => $product->get_status(),
            'type' => $product->get_type(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'sku' => $product->get_sku(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'weight' => $product->get_weight(),
            'length' => $product->get_length(),
            'width' => $product->get_width(),
            'height' => $product->get_height(),
            'manage_stock' => $product->get_manage_stock(),
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
            'backorders' => $product->get_backorders(),
            'featured' => $product->get_featured(),
            'virtual' => $product->get_virtual(),
            'downloadable' => $product->get_downloadable(),
            'tax_status' => $product->get_tax_status(),
            'tax_class' => $product->get_tax_class(),
            'date_created' => $product->get_date_created(),
            'date_modified' => $product->get_date_modified(),
            'categories' => $this->get_product_categories($product->get_id()),
            'tags' => $this->get_product_tags($product->get_id()),
            'images' => $this->get_product_images($product),
            'media' => $this->get_product_media($product), // Images + Videos
            'attributes' => $this->get_product_attributes($product),
            'meta_data' => $this->get_product_meta($product),
            'variations' => array(),
            'translations' => $this->get_product_translations($product->get_id()),
            'currencies' => $this->get_product_currencies($product->get_id())
        );
        
        // Get variations for variable products
        if ($product->is_type('variable')) {
            $product_data['variations'] = $this->get_product_variations($product);
        }
        
        return $product_data;
    }
    
    /**
     * Get product categories
     */
    public function get_product_categories($product_id) {
        $categories = wp_get_post_terms($product_id, 'product_cat');
        $category_data = array();
        
        if (!is_wp_error($categories) && !empty($categories)) {
            foreach ($categories as $category) {
                $category_data[] = array(
                    'id' => $category->term_id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'parent' => $category->parent,
                    'count' => $category->count
                );
            }
        }
        
        return $category_data;
    }
    
    /**
     * Get product tags
     */
    public function get_product_tags($product_id) {
        $tags = wp_get_post_terms($product_id, 'product_tag');
        $tag_data = array();
        
        if (!is_wp_error($tags) && !empty($tags)) {
            foreach ($tags as $tag) {
                $tag_data[] = array(
                    'id' => $tag->term_id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'description' => $tag->description
                );
            }
        }
        
        return $tag_data;
    }
    
    /**
     * Get product images and videos
     */
    public function get_product_media($product) {
        $media = array();

        // Featured image
        $featured_image_id = $product->get_image_id();
        if ($featured_image_id) {
            $media_data = $this->get_media_data($featured_image_id, true);
            if ($media_data) {
                $media[] = $media_data;
            }
        }

        // Gallery images
        $gallery_image_ids = $product->get_gallery_image_ids();
        foreach ($gallery_image_ids as $image_id) {
            $media_data = $this->get_media_data($image_id, false);
            if ($media_data) {
                $media[] = $media_data;
            }
        }

        // Product videos (from custom fields or plugins)
        $videos = $this->get_product_videos($product);
        foreach ($videos as $video) {
            $media[] = $video;
        }

        return $media;
    }

    /**
     * Get product images (backward compatibility)
     */
    public function get_product_images($product) {
        return $this->get_product_media($product);
    }
    
    /**
     * Get media data (images and videos)
     */
    private function get_media_data($media_id, $is_featured = false) {
        $media_url = wp_get_attachment_url($media_id);
        if (!$media_url) {
            return false;
        }

        $media_meta = wp_get_attachment_metadata($media_id);
        $attachment = get_post($media_id);
        $mime_type = get_post_mime_type($media_id);

        $media_data = array(
            'id' => $media_id,
            'url' => $media_url,
            'alt' => get_post_meta($media_id, '_wp_attachment_image_alt', true),
            'title' => $attachment ? $attachment->post_title : '',
            'caption' => $attachment ? $attachment->post_excerpt : '',
            'description' => $attachment ? $attachment->post_content : '',
            'featured' => $is_featured,
            'mime_type' => $mime_type,
            'type' => $this->get_media_type($mime_type)
        );

        // Add specific data based on media type
        if (strpos($mime_type, 'image/') === 0) {
            $media_data['width'] = isset($media_meta['width']) ? $media_meta['width'] : 0;
            $media_data['height'] = isset($media_meta['height']) ? $media_meta['height'] : 0;
        } elseif (strpos($mime_type, 'video/') === 0) {
            $media_data['duration'] = isset($media_meta['length']) ? $media_meta['length'] : 0;
            $media_data['width'] = isset($media_meta['width']) ? $media_meta['width'] : 0;
            $media_data['height'] = isset($media_meta['height']) ? $media_meta['height'] : 0;
        }

        $media_data['filesize'] = isset($media_meta['filesize']) ? $media_meta['filesize'] : 0;

        return $media_data;
    }

    /**
     * Get image data (backward compatibility)
     */
    private function get_image_data($image_id, $is_featured = false) {
        return $this->get_media_data($image_id, $is_featured);
    }

    /**
     * Get media type from mime type
     */
    private function get_media_type($mime_type) {
        if (strpos($mime_type, 'image/') === 0) {
            return 'image';
        } elseif (strpos($mime_type, 'video/') === 0) {
            return 'video';
        } elseif (strpos($mime_type, 'audio/') === 0) {
            return 'audio';
        }

        return 'file';
    }

    /**
     * Get product videos from various sources
     */
    private function get_product_videos($product) {
        $videos = array();
        $product_id = $product->get_id();

        // Check common video meta fields
        $video_meta_keys = array(
            '_product_video_url',
            '_wc_product_video',
            'product_video',
            '_featured_video',
            '_product_video_embed'
        );

        foreach ($video_meta_keys as $meta_key) {
            $video_url = get_post_meta($product_id, $meta_key, true);
            if (!empty($video_url)) {
                $videos[] = array(
                    'id' => 'meta_' . $meta_key,
                    'url' => $video_url,
                    'title' => $product->get_name() . ' Video',
                    'type' => 'video',
                    'source' => 'meta_field',
                    'meta_key' => $meta_key
                );
            }
        }

        // Check for video attachments in product gallery
        $attachment_ids = $product->get_gallery_image_ids();
        foreach ($attachment_ids as $attachment_id) {
            $mime_type = get_post_mime_type($attachment_id);
            if (strpos($mime_type, 'video/') === 0) {
                $video_data = $this->get_media_data($attachment_id, false);
                if ($video_data) {
                    $video_data['source'] = 'attachment';
                    $videos[] = $video_data;
                }
            }
        }

        // Check for YouTube/Vimeo embeds in product description
        $description = $product->get_description();
        $video_embeds = $this->extract_video_embeds($description);
        foreach ($video_embeds as $embed) {
            $videos[] = $embed;
        }

        return $videos;
    }

    /**
     * Extract video embeds from content
     */
    private function extract_video_embeds($content) {
        $videos = array();

        // YouTube URLs
        preg_match_all('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $content, $youtube_matches);
        foreach ($youtube_matches[1] as $video_id) {
            $videos[] = array(
                'id' => 'youtube_' . $video_id,
                'url' => 'https://www.youtube.com/watch?v=' . $video_id,
                'embed_url' => 'https://www.youtube.com/embed/' . $video_id,
                'thumbnail' => 'https://img.youtube.com/vi/' . $video_id . '/maxresdefault.jpg',
                'type' => 'video',
                'source' => 'youtube',
                'video_id' => $video_id
            );
        }

        // Vimeo URLs
        preg_match_all('/vimeo\.com\/(\d+)/', $content, $vimeo_matches);
        foreach ($vimeo_matches[1] as $video_id) {
            $videos[] = array(
                'id' => 'vimeo_' . $video_id,
                'url' => 'https://vimeo.com/' . $video_id,
                'embed_url' => 'https://player.vimeo.com/video/' . $video_id,
                'type' => 'video',
                'source' => 'vimeo',
                'video_id' => $video_id
            );
        }

        return $videos;
    }
    
    /**
     * Get product attributes
     */
    public function get_product_attributes($product) {
        $attributes = array();
        $product_attributes = $product->get_attributes();
        
        foreach ($product_attributes as $attribute_name => $attribute) {
            $attribute_data = array(
                'name' => $attribute_name,
                'label' => wc_attribute_label($attribute_name),
                'options' => array(),
                'visible' => $attribute->get_visible(),
                'variation' => $attribute->get_variation(),
                'position' => $attribute->get_position()
            );
            
            if ($attribute->is_taxonomy()) {
                $terms = wp_get_post_terms($product->get_id(), $attribute_name);
                if (!is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $attribute_data['options'][] = $term->name;
                    }
                }
            } else {
                $attribute_data['options'] = $attribute->get_options();
            }
            
            $attributes[] = $attribute_data;
        }
        
        return $attributes;
    }
    
    /**
     * Get product meta data
     */
    public function get_product_meta($product) {
        $meta_data = array();
        $all_meta = $product->get_meta_data();
        
        // Define which meta keys to include
        $included_meta_keys = array(
            '_product_url',
            '_button_text',
            '_wc_average_rating',
            '_wc_review_count',
            '_product_image_gallery',
            '_crosssell_ids',
            '_upsell_ids',
            '_purchase_note'
        );
        
        foreach ($all_meta as $meta) {
            $key = $meta->key;
            $value = $meta->value;
            
            // Skip private meta keys unless specifically included
            if (substr($key, 0, 1) === '_' && !in_array($key, $included_meta_keys)) {
                continue;
            }
            
            $meta_data[$key] = $value;
        }
        
        return $meta_data;
    }
    
    /**
     * Get product variations
     */
    public function get_product_variations($product) {
        if (!$product->is_type('variable')) {
            return array();
        }
        
        $variations = array();
        $variation_ids = $product->get_children();
        
        foreach ($variation_ids as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) {
                continue;
            }
            
            $variation_data = array(
                'id' => $variation->get_id(),
                'sku' => $variation->get_sku(),
                'price' => $variation->get_price(),
                'regular_price' => $variation->get_regular_price(),
                'sale_price' => $variation->get_sale_price(),
                'weight' => $variation->get_weight(),
                'length' => $variation->get_length(),
                'width' => $variation->get_width(),
                'height' => $variation->get_height(),
                'manage_stock' => $variation->get_manage_stock(),
                'stock_quantity' => $variation->get_stock_quantity(),
                'stock_status' => $variation->get_stock_status(),
                'image' => $this->get_variation_image($variation),
                'attributes' => $variation->get_variation_attributes(),
                'meta_data' => $this->get_product_meta($variation)
            );
            
            $variations[] = $variation_data;
        }
        
        return $variations;
    }
    
    /**
     * Get variation image
     */
    private function get_variation_image($variation) {
        $image_id = $variation->get_image_id();
        if ($image_id) {
            return $this->get_image_data($image_id, false);
        }
        return null;
    }
    
    /**
     * Get product count
     */
    public function get_product_count($args = array()) {
        $defaults = array(
            'status' => array('publish', 'draft', 'private'),
            'return' => 'ids',
            'limit' => -1  // Get all products for counting
        );

        $args = wp_parse_args($args, $defaults);

        // Use direct database query for better performance with large product counts
        global $wpdb;

        $statuses = "'" . implode("','", array_map('esc_sql', $args['status'])) . "'";

        $query = "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'product'
            AND p.post_status IN ({$statuses})
        ";

        $count = $wpdb->get_var($query);

        error_log('Woo2Shopify: Product count query result: ' . $count);

        // Fallback: Use WooCommerce function if direct query fails
        if ($count === null || $count === false) {
            error_log('Woo2Shopify: Direct query failed, using WC fallback');
            $wc_products = wc_get_products(array(
                'status' => $args['status'],
                'return' => 'ids',
                'limit' => -1
            ));
            $count = count($wc_products);
            error_log('Woo2Shopify: WC fallback count: ' . $count);
        }

        return intval($count);
    }
    
    /**
     * Get categories
     */
    public function get_categories() {
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false
        ));
        
        $category_data = array();
        
        if (!is_wp_error($categories)) {
            foreach ($categories as $category) {
                $category_data[] = array(
                    'id' => $category->term_id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'parent' => $category->parent,
                    'count' => $category->count,
                    'image' => $this->get_category_image($category->term_id)
                );
            }
        }
        
        return $category_data;
    }
    
    /**
     * Get category image
     */
    private function get_category_image($category_id) {
        $image_id = get_term_meta($category_id, 'thumbnail_id', true);
        if ($image_id) {
            return $this->get_image_data($image_id, false);
        }
        return null;
    }

    /**
     * Get product translations (WPML, Polylang, etc.)
     */
    private function get_product_translations($product_id) {
        $translations = array();

        // WPML Support
        if (function_exists('icl_get_languages')) {
            $languages = icl_get_languages('skip_missing=0');

            foreach ($languages as $lang_code => $language) {
                $translated_id = apply_filters('wpml_object_id', $product_id, 'product', false, $lang_code);

                if ($translated_id && $translated_id != $product_id) {
                    $translated_product = wc_get_product($translated_id);
                    if ($translated_product) {
                        $translations[$lang_code] = array(
                            'id' => $translated_id,
                            'name' => $translated_product->get_name(),
                            'description' => $translated_product->get_description(),
                            'short_description' => $translated_product->get_short_description(),
                            'slug' => $translated_product->get_slug(),
                            'language_code' => $lang_code,
                            'language_name' => $language['native_name'],
                            'is_default' => $language['default_locale'] == get_locale()
                        );
                    }
                }
            }
        }

        // Polylang Support
        if (function_exists('pll_get_post_translations')) {
            $translations_pll = pll_get_post_translations($product_id);

            foreach ($translations_pll as $lang_code => $translated_id) {
                if ($translated_id != $product_id) {
                    $translated_product = wc_get_product($translated_id);
                    if ($translated_product) {
                        $translations[$lang_code] = array(
                            'id' => $translated_id,
                            'name' => $translated_product->get_name(),
                            'description' => $translated_product->get_description(),
                            'short_description' => $translated_product->get_short_description(),
                            'slug' => $translated_product->get_slug(),
                            'language_code' => $lang_code,
                            'language_name' => pll_get_language_name($lang_code),
                            'is_default' => pll_default_language() == $lang_code
                        );
                    }
                }
            }
        }

        return $translations;
    }

    /**
     * Get product currencies (WooCommerce Multi-Currency, WPML Currency, etc.)
     */
    private function get_product_currencies($product_id) {
        $currencies = array();
        $product = wc_get_product($product_id);

        if (!$product) {
            return $currencies;
        }

        // Default currency
        $default_currency = get_woocommerce_currency();
        $currencies[$default_currency] = array(
            'code' => $default_currency,
            'symbol' => get_woocommerce_currency_symbol($default_currency),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'price' => $product->get_price(),
            'is_default' => true
        );

        // WooCommerce Multi-Currency Support
        if (class_exists('WOOMC\App')) {
            $multi_currency = WOOMC\App::instance();
            $enabled_currencies = $multi_currency->get_enabled_currencies();

            foreach ($enabled_currencies as $currency_code) {
                if ($currency_code != $default_currency) {
                    $price_regular = get_post_meta($product_id, '_regular_price_' . $currency_code, true);
                    $price_sale = get_post_meta($product_id, '_sale_price_' . $currency_code, true);
                    $price = get_post_meta($product_id, '_price_' . $currency_code, true);

                    if ($price_regular || $price) {
                        $currencies[$currency_code] = array(
                            'code' => $currency_code,
                            'symbol' => get_woocommerce_currency_symbol($currency_code),
                            'regular_price' => $price_regular ?: $price,
                            'sale_price' => $price_sale,
                            'price' => $price ?: $price_regular,
                            'is_default' => false
                        );
                    }
                }
            }
        }

        // WPML WooCommerce Multi-Currency Support
        if (class_exists('woocommerce_wpml') && function_exists('wcml_get_woocommerce_currency_option')) {
            global $woocommerce_wpml;

            if (isset($woocommerce_wpml->multi_currency)) {
                $currencies_wpml = $woocommerce_wpml->multi_currency->get_currencies();

                foreach ($currencies_wpml as $currency_code => $currency_data) {
                    if ($currency_code != $default_currency) {
                        $price_regular = get_post_meta($product_id, '_regular_price_' . $currency_code, true);
                        $price_sale = get_post_meta($product_id, '_sale_price_' . $currency_code, true);
                        $price = get_post_meta($product_id, '_price_' . $currency_code, true);

                        if ($price_regular || $price) {
                            $currencies[$currency_code] = array(
                                'code' => $currency_code,
                                'symbol' => get_woocommerce_currency_symbol($currency_code),
                                'regular_price' => $price_regular ?: $price,
                                'sale_price' => $price_sale,
                                'price' => $price ?: $price_regular,
                                'is_default' => false,
                                'rate' => isset($currency_data['rate']) ? $currency_data['rate'] : 1
                            );
                        }
                    }
                }
            }
        }

        return $currencies;
    }

    /**
     * Get available languages
     */
    public function get_available_languages() {
        $languages = array();

        // WPML
        if (function_exists('icl_get_languages')) {
            $wpml_languages = icl_get_languages('skip_missing=0');
            foreach ($wpml_languages as $lang_code => $language) {
                $languages[$lang_code] = array(
                    'code' => $lang_code,
                    'name' => $language['native_name'],
                    'english_name' => $language['english_name'],
                    'is_default' => $language['default_locale'] == get_locale(),
                    'plugin' => 'WPML'
                );
            }
        }

        // Polylang
        if (function_exists('pll_languages_list')) {
            $pll_languages = pll_languages_list(array('fields' => 'all'));
            foreach ($pll_languages as $language) {
                $languages[$language->slug] = array(
                    'code' => $language->slug,
                    'name' => $language->name,
                    'english_name' => $language->name,
                    'is_default' => pll_default_language() == $language->slug,
                    'plugin' => 'Polylang'
                );
            }
        }

        return $languages;
    }

    /**
     * Get available currencies
     */
    public function get_available_currencies() {
        $currencies = array();

        // Default currency
        $default_currency = get_woocommerce_currency();
        $currencies[$default_currency] = array(
            'code' => $default_currency,
            'symbol' => get_woocommerce_currency_symbol($default_currency),
            'name' => $default_currency,
            'is_default' => true,
            'plugin' => 'WooCommerce'
        );

        // Multi-currency plugins
        if (class_exists('WOOMC\App')) {
            $multi_currency = WOOMC\App::instance();
            $enabled_currencies = $multi_currency->get_enabled_currencies();

            foreach ($enabled_currencies as $currency_code) {
                if ($currency_code != $default_currency) {
                    $currencies[$currency_code] = array(
                        'code' => $currency_code,
                        'symbol' => get_woocommerce_currency_symbol($currency_code),
                        'name' => $currency_code,
                        'is_default' => false,
                        'plugin' => 'WooCommerce Multi-Currency'
                    );
                }
            }
        }

        return $currencies;
    }
}
