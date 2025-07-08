<?php
/**
 * Video Processing Class for Woo2Shopify
 * 
 * Handles video extraction from HTML content, video validation,
 * and video optimization for Shopify migration.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo2Shopify_Video_Processor {
    
    /**
     * Supported video formats
     */
    private $supported_formats = array(
        'video/mp4',
        'video/webm',
        'video/ogg',
        'video/quicktime',
        'video/x-msvideo'
    );
    
    /**
     * Video file extensions
     */
    private $video_extensions = array('mp4', 'webm', 'ogg', 'mov', 'avi');
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize video processor
    }
    
    /**
     * Extract video URLs from HTML content
     */
    public function extract_videos_from_html($html) {
        $videos = array();
        
        if (empty($html)) {
            return $videos;
        }
        
        // Pattern to match video URLs
        $patterns = array(
            // Direct video file URLs
            '/https?:\/\/[^\s<>"\']+\.(?:mp4|webm|ogg|mov|avi)(?:\?[^\s<>"\']*)?(?:#t=[\d.]+)?/i',
            // Video tags
            '/<video[^>]*src=["\']([^"\']+)["\'][^>]*>/i',
            '/<source[^>]*src=["\']([^"\']+)["\'][^>]*>/i'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[0] as $index => $match) {
                    $video_url = isset($matches[1]) ? $matches[1][$index] : $match;
                    
                    // Clean up URL
                    $video_url = $this->clean_video_url($video_url);
                    
                    if ($this->is_valid_video_url($video_url)) {
                        $videos[] = array(
                            'url' => $video_url,
                            'original_match' => $match,
                            'type' => $this->get_video_type($video_url),
                            'hash' => md5($video_url)
                        );
                    }
                }
            }
        }
        
        // Remove duplicates
        $videos = $this->remove_duplicate_videos($videos);
        
        return $videos;
    }
    
    /**
     * Clean video URL
     */
    private function clean_video_url($url) {
        // Remove HTML entities
        $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
        
        // Trim whitespace
        $url = trim($url);
        
        // Remove quotes if present
        $url = trim($url, '"\'');
        
        return $url;
    }
    
    /**
     * Check if URL is a valid video URL
     */
    private function is_valid_video_url($url) {
        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Check file extension
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        return in_array($extension, $this->video_extensions);
    }
    
    /**
     * Get video type from URL
     */
    private function get_video_type($url) {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        $type_map = array(
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo'
        );
        
        return isset($type_map[$extension]) ? $type_map[$extension] : 'video/mp4';
    }
    
    /**
     * Remove duplicate videos
     */
    private function remove_duplicate_videos($videos) {
        $unique_videos = array();
        $seen_hashes = array();
        
        foreach ($videos as $video) {
            if (!in_array($video['hash'], $seen_hashes)) {
                $unique_videos[] = $video;
                $seen_hashes[] = $video['hash'];
            }
        }
        
        return $unique_videos;
    }
    
    /**
     * Validate video file
     */
    public function validate_video($video_url) {
        // Check if URL is accessible
        $response = wp_remote_head($video_url, array(
            'timeout' => 30,
            'user-agent' => 'Woo2Shopify Video Validator'
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('video_not_accessible', 
                sprintf(__('Video not accessible: %s', 'woo2shopify'), $response->get_error_message()));
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('video_not_found', 
                sprintf(__('Video not found (HTTP %d)', 'woo2shopify'), $response_code));
        }
        
        // Check content type
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        if (!empty($content_type) && !in_array($content_type, $this->supported_formats)) {
            return new WP_Error('unsupported_video_format', 
                sprintf(__('Unsupported video format: %s', 'woo2shopify'), $content_type));
        }
        
        // Check file size (Shopify limit is 1GB)
        $content_length = wp_remote_retrieve_header($response, 'content-length');
        if ($content_length && intval($content_length) > 1073741824) {
            return new WP_Error('video_too_large', 
                __('Video is too large (max 1GB)', 'woo2shopify'));
        }
        
        return true;
    }
    
    /**
     * Create video HTML embed
     */
    public function create_video_embed($video_url, $options = array()) {
        $defaults = array(
            'controls' => true,
            'preload' => 'metadata',
            'width' => '100%',
            'height' => 'auto',
            'poster' => '',
            'class' => 'woo2shopify-video',
            'style' => 'max-width: 100%; height: auto;'
        );
        
        $options = wp_parse_args($options, $defaults);
        
        $attributes = array();
        
        if ($options['controls']) {
            $attributes[] = 'controls';
        }
        
        if ($options['preload']) {
            $attributes[] = 'preload="' . esc_attr($options['preload']) . '"';
        }
        
        if ($options['width']) {
            $attributes[] = 'width="' . esc_attr($options['width']) . '"';
        }
        
        if ($options['height']) {
            $attributes[] = 'height="' . esc_attr($options['height']) . '"';
        }
        
        if ($options['poster']) {
            $attributes[] = 'poster="' . esc_url($options['poster']) . '"';
        }
        
        if ($options['class']) {
            $attributes[] = 'class="' . esc_attr($options['class']) . '"';
        }
        
        if ($options['style']) {
            $attributes[] = 'style="' . esc_attr($options['style']) . '"';
        }
        
        $video_type = $this->get_video_type($video_url);
        
        $html = '<video ' . implode(' ', $attributes) . '>';
        $html .= '<source src="' . esc_url($video_url) . '" type="' . esc_attr($video_type) . '">';
        $html .= '<p>' . __('Your browser does not support the video tag.', 'woo2shopify') . '</p>';
        $html .= '</video>';
        
        return $html;
    }

    /**
     * Create theme compatible video embed
     */
    public function create_theme_compatible_video($video_url, $options = array()) {
        $defaults = array(
            'aspect_ratio' => 'sixteen-nine',
            'theme_class' => 'vela-section video-section product-video-woo2shopify',
            'container_class' => 'container',
            'autoplay' => false,
            'controls' => true,
            'loop' => false,
            'muted' => false
        );

        $options = wp_parse_args($options, $defaults);
        $platform = $this->detect_video_platform($video_url);

        $html = '<div class="' . esc_attr($options['theme_class']) . '">';
        $html .= '<div class="' . esc_attr($options['container_class']) . '">';
        $html .= '<div class="video-section__content rounded-3">';
        $html .= '<div class="video-section__media">';

        if ($platform === 'youtube' || $platform === 'vimeo') {
            $html .= $this->create_platform_embed($video_url, $platform, $options);
        } else {
            $html .= $this->create_direct_video_embed($video_url, $options);
        }

        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Detect video platform
     */
    private function detect_video_platform($video_url) {
        if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
            return 'youtube';
        }

        if (strpos($video_url, 'vimeo.com') !== false) {
            return 'vimeo';
        }

        return 'direct';
    }

    /**
     * Create platform specific embed
     */
    private function create_platform_embed($video_url, $platform, $options) {
        $html = '';

        if ($platform === 'youtube') {
            $video_id = $this->extract_youtube_id($video_url);
            if ($video_id) {
                $params = array();
                if ($options['autoplay']) $params[] = 'autoplay=1';
                if ($options['loop']) $params[] = 'loop=1&playlist=' . $video_id;
                if ($options['muted']) $params[] = 'mute=1';
                $params[] = 'rel=0&showinfo=0&modestbranding=1';

                $html .= '<div class="video-embed youtube-embed">';
                $html .= '<iframe src="https://www.youtube.com/embed/' . esc_attr($video_id) . '?' . implode('&', $params) . '" ';
                $html .= 'frameborder="0" allowfullscreen loading="lazy"></iframe>';
                $html .= '</div>';
            }
        } elseif ($platform === 'vimeo') {
            $video_id = $this->extract_vimeo_id($video_url);
            if ($video_id) {
                $params = array('title=0', 'byline=0', 'portrait=0');
                if ($options['autoplay']) $params[] = 'autoplay=1';
                if ($options['loop']) $params[] = 'loop=1';
                if ($options['muted']) $params[] = 'muted=1';

                $html .= '<div class="video-embed vimeo-embed">';
                $html .= '<iframe src="https://player.vimeo.com/video/' . esc_attr($video_id) . '?' . implode('&', $params) . '" ';
                $html .= 'frameborder="0" allowfullscreen loading="lazy"></iframe>';
                $html .= '</div>';
            }
        }

        return $html;
    }

    /**
     * Create direct video embed
     */
    private function create_direct_video_embed($video_url, $options) {
        $video_type = $this->get_video_type($video_url);
        $attributes = array('class="video-element"');

        if ($options['controls']) $attributes[] = 'controls';
        if ($options['autoplay']) $attributes[] = 'autoplay';
        if ($options['loop']) $attributes[] = 'loop';
        if ($options['muted']) $attributes[] = 'muted';
        $attributes[] = 'preload="metadata"';

        $html = '<div class="media-video aspect-ratio-' . esc_attr($options['aspect_ratio']) . '">';
        $html .= '<div class="media-video__wrapper">';
        $html .= '<div class="video-direct">';
        $html .= '<video ' . implode(' ', $attributes) . '>';
        $html .= '<source src="' . esc_url($video_url) . '" type="' . esc_attr($video_type) . '">';
        $html .= '<p>' . __('Your browser does not support the video tag.', 'woo2shopify') . '</p>';
        $html .= '</video>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Extract YouTube video ID
     */
    private function extract_youtube_id($url) {
        preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $url, $matches);
        return isset($matches[1]) ? $matches[1] : false;
    }

    /**
     * Extract Vimeo video ID
     */
    private function extract_vimeo_id($url) {
        preg_match('/vimeo\.com\/(\d+)/', $url, $matches);
        return isset($matches[1]) ? $matches[1] : false;
    }

    /**
     * Get video cache statistics
     */
    public function get_video_cache_stats() {
        $cached_videos = get_option('woo2shopify_cached_videos', array());

        $stats = array(
            'total_videos' => count($cached_videos),
            'migrated_videos' => 0,
            'pending_videos' => 0,
            'failed_videos' => 0,
            'stuck_videos' => 0,
            'total_size' => 0
        );

        $current_time = current_time('timestamp');

        foreach ($cached_videos as $video_data) {
            if (isset($video_data['migrated']) && $video_data['migrated']) {
                $stats['migrated_videos']++;
            } elseif (isset($video_data['failed']) && $video_data['failed']) {
                $stats['failed_videos']++;
            } elseif (isset($video_data['pending']) && $video_data['pending']) {
                // Check if video is stuck (pending for more than 5 minutes)
                if (isset($video_data['started_at'])) {
                    $started_time = strtotime($video_data['started_at']);
                    if (($current_time - $started_time) > 300) { // 5 minutes
                        $stats['stuck_videos']++;
                    } else {
                        $stats['pending_videos']++;
                    }
                } else {
                    $stats['pending_videos']++;
                }
            } else {
                $stats['pending_videos']++;
            }
        }

        return $stats;
    }
    
    /**
     * Clear video cache
     */
    public function clear_video_cache() {
        return delete_option('woo2shopify_cached_videos');
    }

    /**
     * Clear stuck videos from cache
     */
    public function clear_stuck_videos() {
        $cached_videos = get_option('woo2shopify_cached_videos', array());
        $current_time = current_time('timestamp');
        $cleared_count = 0;

        foreach ($cached_videos as $video_hash => $video_data) {
            // Clear videos that are pending for more than 5 minutes
            if (isset($video_data['pending']) && $video_data['pending'] && isset($video_data['started_at'])) {
                $started_time = strtotime($video_data['started_at']);
                if (($current_time - $started_time) > 300) { // 5 minutes
                    unset($cached_videos[$video_hash]);
                    $cleared_count++;
                    error_log('Woo2Shopify: Cleared stuck video: ' . $video_data['url']);
                }
            }

            // Clear failed videos older than 1 hour
            if (isset($video_data['failed']) && $video_data['failed'] && isset($video_data['failed_at'])) {
                $failed_time = strtotime($video_data['failed_at']);
                if (($current_time - $failed_time) > 3600) { // 1 hour
                    unset($cached_videos[$video_hash]);
                    $cleared_count++;
                    error_log('Woo2Shopify: Cleared old failed video: ' . $video_data['url']);
                }
            }
        }

        if ($cleared_count > 0) {
            update_option('woo2shopify_cached_videos', $cached_videos);
            error_log('Woo2Shopify: Cleared ' . $cleared_count . ' stuck/failed videos from cache');
        }

        return $cleared_count;
    }
}
