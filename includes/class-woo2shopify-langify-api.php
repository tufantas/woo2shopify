<?php
/**
 * Langify API Integration for Woo2Shopify
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo2Shopify_Langify_API {
    
    private $shopify_api;
    
    public function __construct() {
        $this->shopify_api = new Woo2Shopify_Shopify_API();
    }
    
    /**
     * Create translations for a product using Langify format
     */
    public function create_product_translations($shopify_product_id, $translations) {
        $results = array();
        
        foreach ($translations as $lang_code => $translation) {
            woo2shopify_log("Creating Langify translation for product $shopify_product_id in language $lang_code", 'info');
            
            // Langify uses specific metafield format
            $metafields = array(
                // Product title translation
                array(
                    'namespace' => 'langify',
                    'key' => "title_{$lang_code}",
                    'value' => $translation['name'],
                    'type' => 'single_line_text_field'
                ),
                // Product description translation
                array(
                    'namespace' => 'langify',
                    'key' => "description_{$lang_code}",
                    'value' => $translation['description'],
                    'type' => 'multi_line_text_field'
                ),
                // Product handle translation (for SEO URLs)
                array(
                    'namespace' => 'langify',
                    'key' => "handle_{$lang_code}",
                    'value' => woo2shopify_sanitize_handle($translation['slug']),
                    'type' => 'single_line_text_field'
                )
            );
            
            // Add short description if available
            if (!empty($translation['short_description'])) {
                $metafields[] = array(
                    'namespace' => 'langify',
                    'key' => "excerpt_{$lang_code}",
                    'value' => $translation['short_description'],
                    'type' => 'multi_line_text_field'
                );
            }
            
            // Add SEO title if available
            if (!empty($translation['seo_title'])) {
                $metafields[] = array(
                    'namespace' => 'langify',
                    'key' => "seo_title_{$lang_code}",
                    'value' => $translation['seo_title'],
                    'type' => 'single_line_text_field'
                );
            }
            
            // Add SEO description if available
            if (!empty($translation['seo_description'])) {
                $metafields[] = array(
                    'namespace' => 'langify',
                    'key' => "seo_description_{$lang_code}",
                    'value' => $translation['seo_description'],
                    'type' => 'multi_line_text_field'
                );
            }
            
            // Create metafields for this language
            $lang_results = array();
            foreach ($metafields as $metafield) {
                $result = $this->shopify_api->create_product_metafield($shopify_product_id, $metafield);
                
                if (is_wp_error($result)) {
                    woo2shopify_log("Failed to create Langify metafield {$metafield['key']}: " . $result->get_error_message(), 'error');
                    $lang_results[] = array('success' => false, 'error' => $result->get_error_message());
                } else {
                    woo2shopify_log("Created Langify metafield {$metafield['key']} for language $lang_code", 'info');
                    $lang_results[] = array('success' => true, 'metafield_id' => $result['id']);
                }
                
                // Small delay to avoid rate limits
                usleep(100000); // 0.1 second
            }
            
            $results[$lang_code] = $lang_results;
        }
        
        // Create language configuration metafield
        $this->create_language_config($shopify_product_id, array_keys($translations));
        
        return $results;
    }
    
    /**
     * Create language configuration for Langify
     */
    private function create_language_config($shopify_product_id, $languages) {
        $config = array(
            'enabled_languages' => $languages,
            'default_language' => $languages[0] ?? 'en', // First language as default
            'auto_translate' => false,
            'created_by' => 'woo2shopify'
        );
        
        $metafield = array(
            'namespace' => 'langify',
            'key' => 'language_config',
            'value' => json_encode($config),
            'type' => 'json'
        );
        
        $result = $this->shopify_api->create_product_metafield($shopify_product_id, $metafield);
        
        if (is_wp_error($result)) {
            woo2shopify_log("Failed to create Langify language config: " . $result->get_error_message(), 'error');
        } else {
            woo2shopify_log("Created Langify language config for languages: " . implode(', ', $languages), 'info');
        }
        
        return $result;
    }
    
    /**
     * Create collection translations for Langify
     */
    public function create_collection_translations($shopify_collection_id, $translations) {
        $results = array();
        
        foreach ($translations as $lang_code => $translation) {
            woo2shopify_log("Creating Langify collection translation for $shopify_collection_id in language $lang_code", 'info');
            
            $metafields = array(
                // Collection title translation
                array(
                    'namespace' => 'langify',
                    'key' => "title_{$lang_code}",
                    'value' => $translation['name'],
                    'type' => 'single_line_text_field'
                ),
                // Collection description translation
                array(
                    'namespace' => 'langify',
                    'key' => "description_{$lang_code}",
                    'value' => $translation['description'],
                    'type' => 'multi_line_text_field'
                ),
                // Collection handle translation
                array(
                    'namespace' => 'langify',
                    'key' => "handle_{$lang_code}",
                    'value' => woo2shopify_sanitize_handle($translation['slug']),
                    'type' => 'single_line_text_field'
                )
            );
            
            // Create metafields for this language
            $lang_results = array();
            foreach ($metafields as $metafield) {
                $result = $this->shopify_api->create_collection_metafield($shopify_collection_id, $metafield);
                
                if (is_wp_error($result)) {
                    woo2shopify_log("Failed to create Langify collection metafield {$metafield['key']}: " . $result->get_error_message(), 'error');
                    $lang_results[] = array('success' => false, 'error' => $result->get_error_message());
                } else {
                    woo2shopify_log("Created Langify collection metafield {$metafield['key']} for language $lang_code", 'info');
                    $lang_results[] = array('success' => true, 'metafield_id' => $result['id']);
                }
                
                usleep(100000); // 0.1 second delay
            }
            
            $results[$lang_code] = $lang_results;
        }
        
        return $results;
    }
}
