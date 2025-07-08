<?php
/**
 * Image Migration Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Woo2Shopify_Image_Migrator {
    
    /**
     * Shopify API instance
     */
    private $shopify_api;
    
    /**
     * Image quality setting
     */
    private $image_quality;
    
    /**
     * Maximum image size (in bytes)
     */
    private $max_image_size = 20971520; // 20MB
    
    /**
     * Supported image formats
     */
    private $supported_formats = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');

    /**
     * Supported video formats
     */
    private $supported_video_formats = array('video/mp4', 'video/webm', 'video/ogg', 'video/quicktime');
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->shopify_api = new Woo2Shopify_Shopify_API();
        $this->image_quality = woo2shopify_get_option('image_quality', 80);
    }
    
    /**
     * Migrate product images and videos
     */
    public function migrate_product_media($product_id, $media_items, $shopify_product_id) {
        $migrated_media = array();
        $errors = array();

        foreach ($media_items as $index => $media) {
            try {
                // Check if it's a video or image
                if ($this->is_video($media)) {
                    $result = $this->migrate_single_video($media, $shopify_product_id);
                } else {
                    $result = $this->migrate_single_image($media, $shopify_product_id, $index === 0);
                }

                if (is_wp_error($result)) {
                    $errors[] = array(
                        'media_id' => $media['id'],
                        'type' => $this->is_video($media) ? 'video' : 'image',
                        'error' => $result->get_error_message()
                    );
                } else {
                    $migrated_media[] = $result;
                }

            } catch (Exception $e) {
                $errors[] = array(
                    'media_id' => $media['id'],
                    'type' => $this->is_video($media) ? 'video' : 'image',
                    'error' => $e->getMessage()
                );
            }
        }

        return array(
            'success' => count($migrated_media),
            'failed' => count($errors),
            'media' => $migrated_media,
            'errors' => $errors
        );
    }

    /**
     * Migrate product images (backward compatibility)
     */
    public function migrate_product_images($product_id, $images, $shopify_product_id) {
        return $this->migrate_product_media($product_id, $images, $shopify_product_id);
    }
    
    /**
     * Migrate single image
     */
    private function migrate_single_image($image, $shopify_product_id, $is_featured = false) {
        // Validate image
        $validation_result = $this->validate_image($image);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }
        
        // Prepare image data
        $image_data = $this->prepare_image_data($image, $is_featured);

        // Check if image has valid src
        $image_src = isset($image['src']) ? $image['src'] : (isset($image['url']) ? $image['url'] : '');
        if (empty($image_src)) {
            woo2shopify_log("Image has no valid src/url for product $shopify_product_id", 'warning');
            return new WP_Error('invalid_image', 'Image has no valid src/url');
        }

        // Check if this image was already uploaded for this product
        $image_hash = md5($image_src . $shopify_product_id);
        $uploaded_images = get_option('woo2shopify_uploaded_images', array());

        if (isset($uploaded_images[$image_hash])) {
            woo2shopify_log("Image already uploaded for product $shopify_product_id: " . $image_src, 'info');
            return $uploaded_images[$image_hash];
        }

        // Upload to Shopify
        woo2shopify_log("Uploading new image for product $shopify_product_id: " . $image_src, 'info');
        $result = $this->shopify_api->upload_product_image($shopify_product_id, $image_data);
        
        if (is_wp_error($result)) {
            return $result;
        }

        // Cache the uploaded image to prevent duplicates
        $uploaded_images[$image_hash] = $result;
        update_option('woo2shopify_uploaded_images', $uploaded_images);

        woo2shopify_log("Image uploaded successfully for product $shopify_product_id", 'info');

        return array(
            'wc_image_id' => $image['id'],
            'shopify_image_id' => $result['id'],
            'shopify_url' => $result['src'],
            'alt_text' => $result['alt'],
            'position' => $result['position']
        );
    }
    
    /**
     * Validate image
     */
    private function validate_image($image) {
        // Check if image URL is accessible
        if (empty($image['url'])) {
            return new WP_Error('empty_url', __('Image URL is empty', 'woo2shopify'));
        }
        
        // Check if image exists
        $response = wp_remote_head($image['url']);
        if (is_wp_error($response)) {
            return new WP_Error('image_not_accessible', __('Image is not accessible', 'woo2shopify'));
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('image_not_found', sprintf(__('Image not found (HTTP %d)', 'woo2shopify'), $response_code));
        }
        
        // Check content type
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        if (!in_array($content_type, $this->supported_formats) && !in_array($content_type, $this->supported_video_formats)) {
            return new WP_Error('unsupported_format', sprintf(__('Unsupported media format: %s', 'woo2shopify'), $content_type));
        }
        
        // Check file size
        $content_length = wp_remote_retrieve_header($response, 'content-length');
        if ($content_length && intval($content_length) > $this->max_image_size) {
            return new WP_Error('image_too_large', __('Image is too large (max 20MB)', 'woo2shopify'));
        }
        
        return true;
    }
    
    /**
     * Prepare image data for Shopify
     */
    private function prepare_image_data($image, $is_featured = false) {
        $image_data = array(
            'src' => $this->optimize_image_url($image['url']),
            'alt' => $this->prepare_alt_text($image)
        );
        
        // Set position (featured image should be first)
        if ($is_featured) {
            $image_data['position'] = 1;
        }
        
        return $image_data;
    }
    
    /**
     * Optimize image URL
     */
    private function optimize_image_url($url) {
        // If image optimization is enabled, we could add parameters here
        // For now, return the original URL
        return $url;
    }
    
    /**
     * Prepare alt text
     */
    private function prepare_alt_text($image) {
        // Priority: alt text > title > filename
        if (!empty($image['alt'])) {
            return $image['alt'];
        }
        
        if (!empty($image['title'])) {
            return $image['title'];
        }
        
        // Extract filename from URL
        $filename = basename($image['url']);
        $filename = pathinfo($filename, PATHINFO_FILENAME);
        
        // Clean up filename for alt text
        $alt_text = str_replace(array('-', '_'), ' ', $filename);
        $alt_text = ucwords($alt_text);
        
        return $alt_text;
    }
    
    /**
     * Download and optimize image
     */
    public function download_and_optimize_image($image_url) {
        // Download image
        $response = wp_remote_get($image_url, array(
            'timeout' => 30,
            'stream' => true,
            'filename' => wp_tempnam()
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $temp_file = $response['filename'];
        
        // Get image info
        $image_info = getimagesize($temp_file);
        if (!$image_info) {
            unlink($temp_file);
            return new WP_Error('invalid_image', __('Invalid image file', 'woo2shopify'));
        }
        
        // Optimize image if needed
        $optimized_file = $this->optimize_image_file($temp_file, $image_info);
        
        if (is_wp_error($optimized_file)) {
            unlink($temp_file);
            return $optimized_file;
        }
        
        // Read optimized file
        $image_data = file_get_contents($optimized_file);
        
        // Clean up temp files
        unlink($temp_file);
        if ($optimized_file !== $temp_file) {
            unlink($optimized_file);
        }
        
        return array(
            'data' => base64_encode($image_data),
            'mime_type' => $image_info['mime'],
            'width' => $image_info[0],
            'height' => $image_info[1]
        );
    }
    
    /**
     * Optimize image file
     */
    private function optimize_image_file($file_path, $image_info) {
        $mime_type = $image_info['mime'];
        $width = $image_info[0];
        $height = $image_info[1];
        
        // Skip optimization for small images or if quality is 100
        if (($width * $height < 500000) || $this->image_quality >= 100) {
            return $file_path;
        }
        
        // Create image resource
        switch ($mime_type) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($file_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($file_path);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($file_path);
                break;
            default:
                return $file_path; // Skip optimization for unsupported formats
        }
        
        if (!$image) {
            return new WP_Error('image_processing_failed', __('Failed to process image', 'woo2shopify'));
        }
        
        // Create optimized image
        $optimized_file = wp_tempnam();
        
        switch ($mime_type) {
            case 'image/jpeg':
                $success = imagejpeg($image, $optimized_file, $this->image_quality);
                break;
            case 'image/png':
                // PNG quality is 0-9, convert from 0-100
                $png_quality = 9 - round(($this->image_quality / 100) * 9);
                $success = imagepng($image, $optimized_file, $png_quality);
                break;
            case 'image/gif':
                $success = imagegif($image, $optimized_file);
                break;
            default:
                $success = false;
        }
        
        imagedestroy($image);
        
        if (!$success) {
            unlink($optimized_file);
            return new WP_Error('image_optimization_failed', __('Failed to optimize image', 'woo2shopify'));
        }
        
        return $optimized_file;
    }
    
    /**
     * Resize image if too large
     */
    private function resize_image_if_needed($image, $max_width = 2048, $max_height = 2048) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Check if resize is needed
        if ($width <= $max_width && $height <= $max_height) {
            return $image;
        }
        
        // Calculate new dimensions
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = round($width * $ratio);
        $new_height = round($height * $ratio);
        
        // Create resized image
        $resized_image = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency for PNG and GIF
        imagealphablending($resized_image, false);
        imagesavealpha($resized_image, true);
        
        // Resize
        imagecopyresampled(
            $resized_image, $image,
            0, 0, 0, 0,
            $new_width, $new_height,
            $width, $height
        );
        
        imagedestroy($image);
        
        return $resized_image;
    }
    
    /**
     * Get image statistics
     */
    public function get_migration_stats($migration_id) {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'woo2shopify_logs';
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_images,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_images,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_images
            FROM {$logs_table} 
            WHERE migration_id = %s AND action = 'image_upload'
        ", $migration_id));
        
        return array(
            'total' => intval($stats->total_images),
            'successful' => intval($stats->successful_images),
            'failed' => intval($stats->failed_images),
            'success_rate' => $stats->total_images > 0 ? round(($stats->successful_images / $stats->total_images) * 100, 2) : 0
        );
    }
    
    /**
     * Clean up temporary files
     */
    public function cleanup_temp_files() {
        $temp_dir = sys_get_temp_dir();
        $pattern = $temp_dir . '/woo2shopify_*';
        
        foreach (glob($pattern) as $file) {
            if (is_file($file) && (time() - filemtime($file)) > 3600) { // 1 hour old
                unlink($file);
            }
        }
    }
    
    /**
     * Check if media is a video
     */
    private function is_video($media) {
        if (isset($media['mime_type'])) {
            return in_array($media['mime_type'], $this->supported_video_formats);
        }

        // Check by file extension if mime type not available
        $url = $media['url'] ?? '';
        $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));
        $video_extensions = array('mp4', 'webm', 'ogg', 'mov', 'avi');

        return in_array($extension, $video_extensions);
    }

    /**
     * Migrate single video
     */
    private function migrate_single_video($video, $shopify_product_id) {
        // Validate video
        $validation_result = $this->validate_video($video);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // For Shopify, videos are handled differently than images
        // We need to use the GraphQL Admin API for video uploads
        return $this->upload_video_to_shopify($video, $shopify_product_id);
    }

    /**
     * Validate video
     */
    private function validate_video($video) {
        // Check if video URL is accessible
        if (empty($video['url'])) {
            return new WP_Error('empty_url', __('Video URL is empty', 'woo2shopify'));
        }

        // Check if video exists
        $response = wp_remote_head($video['url']);
        if (is_wp_error($response)) {
            return new WP_Error('video_not_accessible', __('Video is not accessible', 'woo2shopify'));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('video_not_found', sprintf(__('Video not found (HTTP %d)', 'woo2shopify'), $response_code));
        }

        // Check content type
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        if (!in_array($content_type, $this->supported_video_formats)) {
            return new WP_Error('unsupported_video_format', sprintf(__('Unsupported video format: %s', 'woo2shopify'), $content_type));
        }

        // Check file size (Shopify limit is 1GB for videos)
        $content_length = wp_remote_retrieve_header($response, 'content-length');
        if ($content_length && intval($content_length) > 1073741824) { // 1GB
            return new WP_Error('video_too_large', __('Video is too large (max 1GB)', 'woo2shopify'));
        }

        return true;
    }

    /**
     * Upload video to Shopify
     */
    private function upload_video_to_shopify($video, $shopify_product_id) {
        // Check if video is already migrated to avoid duplicates
        $video_hash = md5($video['url']);
        $cached_videos = get_option('woo2shopify_cached_videos', array());

        if (isset($cached_videos[$video_hash]) && $cached_videos[$video_hash]['migrated']) {
            error_log('Woo2Shopify: Using cached video for: ' . $video['url']);
            // Return cached result
            return array(
                'wc_video_id' => $video['id'],
                'video_hash' => $video_hash,
                'shopify_metafield_id' => $cached_videos[$video_hash]['metafield_id'],
                'video_url' => $video['url'],
                'alt_text' => $this->prepare_alt_text($video),
                'type' => 'video',
                'source' => $video['source'] ?? 'unknown',
                'cached' => true
            );
        }

        // Mark video as pending to prevent duplicate processing
        $cached_videos[$video_hash] = array(
            'url' => $video['url'],
            'migrated' => false,
            'pending' => true,
            'started_at' => current_time('mysql')
        );
        update_option('woo2shopify_cached_videos', $cached_videos);

        error_log('Woo2Shopify: Processing new video for product: ' . $shopify_product_id);
        error_log('Woo2Shopify: Video URL: ' . $video['url']);

        try {
            // Create a metafield with the video URL
            $metafield_data = array(
                'namespace' => 'custom',
                'key' => 'product_video_url',
                'value' => $video['url'],
                'type' => 'url'
            );

            // Create the metafield in Shopify
            $result = $this->shopify_api->create_product_metafield($shopify_product_id, $metafield_data);

            if (is_wp_error($result)) {
                error_log('Woo2Shopify: Video metafield creation failed: ' . $result->get_error_message());

                // Mark as failed in cache
                $cached_videos[$video_hash] = array(
                    'url' => $video['url'],
                    'migrated' => false,
                    'pending' => false,
                    'failed' => true,
                    'error' => $result->get_error_message(),
                    'failed_at' => current_time('mysql')
                );
                update_option('woo2shopify_cached_videos', $cached_videos);

                return $result;
            }

            error_log('Woo2Shopify: Video metafield created successfully');

            // Cache the successful result
            $cached_videos[$video_hash] = array(
                'url' => $video['url'],
                'migrated' => true,
                'pending' => false,
                'metafield_id' => $result['id'] ?? null,
                'migrated_at' => current_time('mysql')
            );
            update_option('woo2shopify_cached_videos', $cached_videos);

        } catch (Exception $e) {
            error_log('Woo2Shopify: Video processing exception: ' . $e->getMessage());

            // Mark as failed in cache
            $cached_videos[$video_hash] = array(
                'url' => $video['url'],
                'migrated' => false,
                'pending' => false,
                'failed' => true,
                'error' => $e->getMessage(),
                'failed_at' => current_time('mysql')
            );
            update_option('woo2shopify_cached_videos', $cached_videos);

            return new WP_Error('video_processing_failed', $e->getMessage());
        }

        return array(
            'wc_video_id' => $video['id'],
            'video_hash' => $video_hash,
            'shopify_metafield_id' => $result['id'] ?? null,
            'video_url' => $video['url'],
            'alt_text' => $this->prepare_alt_text($video),
            'type' => 'video',
            'source' => $video['source'] ?? 'unknown',
            'cached' => false
        );
    }

    /**
     * Get video thumbnail
     */
    private function get_video_thumbnail($video_url) {
        // This would extract a thumbnail from the video
        // Implementation depends on video processing capabilities
        // For now, return null
        return null;
    }

    /**
     * Validate image dimensions
     */
    private function validate_dimensions($width, $height) {
        // Shopify requirements
        $min_dimension = 1;
        $max_dimension = 5760;

        if ($width < $min_dimension || $height < $min_dimension) {
            return new WP_Error('image_too_small', __('Image dimensions are too small', 'woo2shopify'));
        }

        if ($width > $max_dimension || $height > $max_dimension) {
            return new WP_Error('image_too_large', __('Image dimensions are too large', 'woo2shopify'));
        }

        return true;
    }
}
