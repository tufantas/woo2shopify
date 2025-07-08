<?php
/**
 * Debug Fatal Error - Woo2Shopify
 * Bu dosyayı browser'da açarak gerçek hatayı görebilirsiniz
 */

// WordPress'i yükle
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

// Error reporting'i aç
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>🔍 Woo2Shopify Fatal Error Debug</h1>";
echo "<hr>";

// 1. PHP ve WordPress bilgileri
echo "<h2>📋 System Information</h2>";
echo "<strong>PHP Version:</strong> " . PHP_VERSION . "<br>";
echo "<strong>WordPress Version:</strong> " . get_bloginfo('version') . "<br>";
echo "<strong>WooCommerce:</strong> " . (class_exists('WooCommerce') ? 'Active (' . WC_VERSION . ')' : 'Not Active') . "<br>";
echo "<strong>Memory Limit:</strong> " . ini_get('memory_limit') . "<br>";
echo "<strong>Max Execution Time:</strong> " . ini_get('max_execution_time') . "<br>";
echo "<hr>";

// 2. Plugin dosyalarını kontrol et
echo "<h2>📁 Plugin Files Check</h2>";
$plugin_dir = WP_PLUGIN_DIR . '/woo2shopify/';
$required_files = array(
    'woo2shopify.php',
    'includes/functions.php',
    'includes/class-woo2shopify-admin.php',
    'includes/class-woo2shopify-shopify-api.php',
    'includes/class-woo2shopify-woocommerce-reader.php',
    'includes/class-woo2shopify-data-mapper.php',
    'includes/class-woo2shopify-image-migrator.php',
    'includes/class-woo2shopify-video-processor.php',
    'includes/class-woo2shopify-batch-processor.php',
    'includes/class-woo2shopify-enhanced-batch-processor.php',
    'includes/class-woo2shopify-selective-migrator.php',
    'includes/class-woo2shopify-logger.php',
    'includes/class-woo2shopify-database.php',
    'assets/js/admin.js',
    'assets/css/admin.css'
);

foreach ($required_files as $file) {
    $file_path = $plugin_dir . $file;
    $exists = file_exists($file_path);
    $readable = $exists ? is_readable($file_path) : false;
    
    echo "<strong>$file:</strong> ";
    if ($exists && $readable) {
        echo "✅ OK (" . number_format(filesize($file_path)) . " bytes)<br>";
    } elseif ($exists && !$readable) {
        echo "❌ EXISTS but NOT READABLE<br>";
    } else {
        echo "❌ NOT FOUND<br>";
    }
}
echo "<hr>";

// 3. Plugin aktivasyon durumu
echo "<h2>🔌 Plugin Status</h2>";
$active_plugins = get_option('active_plugins', array());
$woo2shopify_active = in_array('woo2shopify/woo2shopify.php', $active_plugins);
echo "<strong>Woo2Shopify Active:</strong> " . ($woo2shopify_active ? '✅ YES' : '❌ NO') . "<br>";

if ($woo2shopify_active) {
    echo "<strong>Plugin Data:</strong><br>";
    $plugin_data = get_plugin_data($plugin_dir . 'woo2shopify.php');
    foreach ($plugin_data as $key => $value) {
        if (!empty($value)) {
            echo "&nbsp;&nbsp;<strong>$key:</strong> $value<br>";
        }
    }
}
echo "<hr>";

// 4. Manuel plugin yükleme testi
echo "<h2>🧪 Manual Plugin Load Test</h2>";
echo "<strong>Testing manual plugin load...</strong><br>";

try {
    // Plugin ana dosyasını yükle
    if (file_exists($plugin_dir . 'woo2shopify.php')) {
        echo "Loading main plugin file...<br>";
        ob_start();
        include_once($plugin_dir . 'woo2shopify.php');
        $output = ob_get_clean();
        
        if (!empty($output)) {
            echo "<div style='background: #ffeeee; padding: 10px; border: 1px solid #ff0000;'>";
            echo "<strong>⚠️ Output during plugin load:</strong><br>";
            echo "<pre>" . htmlspecialchars($output) . "</pre>";
            echo "</div>";
        } else {
            echo "✅ Plugin loaded without output<br>";
        }
        
        // Sınıfların yüklenip yüklenmediğini kontrol et
        echo "<br><strong>Class Loading Check:</strong><br>";
        $classes = array(
            'Woo2Shopify',
            'Woo2Shopify_Admin',
            'Woo2Shopify_Shopify_API',
            'Woo2Shopify_WooCommerce_Reader'
        );
        
        foreach ($classes as $class) {
            echo "&nbsp;&nbsp;<strong>$class:</strong> " . (class_exists($class) ? '✅ Loaded' : '❌ Not Found') . "<br>";
        }
        
    } else {
        echo "❌ Main plugin file not found!<br>";
    }
    
} catch (ParseError $e) {
    echo "<div style='background: #ffeeee; padding: 10px; border: 1px solid #ff0000;'>";
    echo "<strong>🚨 PARSE ERROR FOUND!</strong><br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "<strong>Message:</strong> " . $e->getMessage() . "<br>";
    echo "</div>";
} catch (Error $e) {
    echo "<div style='background: #ffeeee; padding: 10px; border: 1px solid #ff0000;'>";
    echo "<strong>🚨 FATAL ERROR FOUND!</strong><br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "<strong>Message:</strong> " . $e->getMessage() . "<br>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div style='background: #ffeeee; padding: 10px; border: 1px solid #ff0000;'>";
    echo "<strong>🚨 EXCEPTION FOUND!</strong><br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "<strong>Message:</strong> " . $e->getMessage() . "<br>";
    echo "</div>";
}

echo "<hr>";

// 5. Error log kontrolü
echo "<h2>📋 Recent Error Logs</h2>";
$error_log_paths = array(
    WP_CONTENT_DIR . '/debug.log',
    ini_get('error_log'),
    '/var/log/apache2/error.log',
    '/var/log/nginx/error.log'
);

foreach ($error_log_paths as $log_path) {
    if (file_exists($log_path) && is_readable($log_path)) {
        echo "<strong>Log file:</strong> $log_path<br>";
        $log_content = file_get_contents($log_path);
        $lines = explode("\n", $log_content);
        $recent_lines = array_slice($lines, -20); // Son 20 satır
        
        $woo2shopify_errors = array();
        foreach ($recent_lines as $line) {
            if (stripos($line, 'woo2shopify') !== false || stripos($line, 'fatal') !== false) {
                $woo2shopify_errors[] = $line;
            }
        }
        
        if (!empty($woo2shopify_errors)) {
            echo "<div style='background: #fff3cd; padding: 10px; border: 1px solid #ffc107; margin: 10px 0;'>";
            echo "<strong>🔍 Related Errors Found:</strong><br>";
            foreach ($woo2shopify_errors as $error) {
                echo "<code>" . htmlspecialchars($error) . "</code><br>";
            }
            echo "</div>";
        }
        break;
    }
}

echo "<hr>";

// 6. Database kontrol
echo "<h2>🗄️ Database Check</h2>";
global $wpdb;

try {
    $tables = array(
        $wpdb->prefix . 'woo2shopify_logs',
        $wpdb->prefix . 'woo2shopify_progress'
    );
    
    foreach ($tables as $table) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        echo "<strong>$table:</strong> " . ($exists ? '✅ EXISTS' : '❌ NOT FOUND') . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p><strong>🔧 Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Check the error messages above</li>";
echo "<li>If parse errors found, fix the syntax issues</li>";
echo "<li>If fatal errors found, check missing dependencies</li>";
echo "<li>Check file permissions (should be 644 for files, 755 for directories)</li>";
echo "<li>Deactivate and reactivate the plugin</li>";
echo "</ol>";

echo "<p><em>Debug completed at " . date('Y-m-d H:i:s') . "</em></p>";
?>
