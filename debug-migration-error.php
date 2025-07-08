<?php
/**
 * Debug Migration Error
 * Temporary debug file to find the exact error causing 500 status
 */

// WordPress environment
if (!defined('ABSPATH')) {
    require_once('../../../wp-config.php');
}

echo "<h1>🔍 Migration Error Debug</h1>";
echo "<p>Debugging the 500 error during migration start...</p>";

// 1. Check if classes exist
echo "<h2>📋 Class Availability Check</h2>";
$required_classes = array(
    'Woo2Shopify_Batch_Processor',
    'Woo2Shopify_Logger',
    'Woo2Shopify_Image_Migrator'
);

foreach ($required_classes as $class) {
    if (class_exists($class)) {
        echo "✅ $class - Available<br>";
    } else {
        echo "❌ $class - Missing<br>";
    }
}

// 2. Check if functions exist
echo "<h2>📋 Function Availability Check</h2>";
$required_functions = array(
    'woo2shopify_generate_migration_id',
    'woo2shopify_update_progress',
    'woo2shopify_get_progress',
    'woo2shopify_get_option',
    'woo2shopify_update_option'
);

foreach ($required_functions as $func) {
    if (function_exists($func)) {
        echo "✅ $func - Available<br>";
    } else {
        echo "❌ $func - Missing<br>";
    }
}

// 3. Test database tables
echo "<h2>📋 Database Tables Check</h2>";
global $wpdb;

$tables = array(
    $wpdb->prefix . 'woo2shopify_progress',
    $wpdb->prefix . 'woo2shopify_products',
    $wpdb->prefix . 'woo2shopify_logs'
);

foreach ($tables as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($exists) {
        echo "✅ $table - Exists<br>";
    } else {
        echo "❌ $table - Missing<br>";
    }
}

// 4. Test basic migration start
echo "<h2>🧪 Test Migration Start</h2>";
try {
    // Simulate the exact same call as AJAX
    if (class_exists('Woo2Shopify_Batch_Processor')) {
        $batch_processor = new Woo2Shopify_Batch_Processor();
        
        $options = array(
            'include_images' => true,
            'include_videos' => false,
            'include_variations' => true,
            'include_categories' => true,
            'include_translations' => true,
            'batch_size' => 3
        );
        
        echo "🔄 Starting test migration...<br>";
        $result = $batch_processor->start_migration($options);
        
        if ($result['success']) {
            echo "✅ Migration started successfully!<br>";
            echo "Migration ID: " . $result['migration_id'] . "<br>";
            echo "Total products: " . $result['total_products'] . "<br>";
        } else {
            echo "❌ Migration failed:<br>";
            echo "Error: " . $result['message'] . "<br>";
            if (isset($result['error_details'])) {
                echo "File: " . $result['error_details']['file'] . "<br>";
                echo "Line: " . $result['error_details']['line'] . "<br>";
            }
        }
    } else {
        echo "❌ Woo2Shopify_Batch_Processor class not found<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Exception during test:<br>";
    echo "Message: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
    echo "Trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}

// 5. Check WooCommerce
echo "<h2>📋 WooCommerce Check</h2>";
if (class_exists('WooCommerce')) {
    echo "✅ WooCommerce is active<br>";
    
    // Test product count
    $products = wc_get_products(array(
        'status' => 'publish',
        'limit' => 1,
        'return' => 'ids'
    ));
    
    if (!empty($products)) {
        echo "✅ WooCommerce products found<br>";
    } else {
        echo "⚠️ No WooCommerce products found<br>";
    }
} else {
    echo "❌ WooCommerce is not active<br>";
}

// 6. Check memory and limits
echo "<h2>📋 System Limits</h2>";
echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "Max Execution Time: " . ini_get('max_execution_time') . "<br>";
echo "Current Memory Usage: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB<br>";

echo "<hr>";
echo "<p><strong>🔧 Debug completed at " . date('Y-m-d H:i:s') . "</strong></p>";
?>
