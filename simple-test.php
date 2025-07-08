<?php
/**
 * Simple Test - Check if WordPress loads properly
 */

// WordPress environment
if (!defined('ABSPATH')) {
    require_once('../../../wp-config.php');
}

echo "<h1>🔍 Simple WordPress Test</h1>";

// 1. WordPress loaded?
if (defined('ABSPATH')) {
    echo "✅ WordPress loaded<br>";
} else {
    echo "❌ WordPress not loaded<br>";
}

// 2. WooCommerce active?
if (class_exists('WooCommerce')) {
    echo "✅ WooCommerce active<br>";
} else {
    echo "❌ WooCommerce not active<br>";
}

// 3. Plugin files exist?
$plugin_files = array(
    'woo2shopify.php',
    'includes/functions.php',
    'includes/class-woo2shopify-batch-processor.php'
);

foreach ($plugin_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file missing<br>";
    }
}

echo "<p>Test completed at " . date('Y-m-d H:i:s') . "</p>";
?>
