<?php
/**
 * Emergency Fix for Woo2Shopify Fatal Error
 * Bu dosyayı browser'da açarak plugin'i güvenli şekilde deaktive edebilirsiniz
 */

// WordPress'i yükle
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

// Admin yetkisi kontrolü
if (!current_user_can('activate_plugins')) {
    die('Bu işlem için yeterli yetkiniz yok.');
}

echo "<h1>🚨 Woo2Shopify Emergency Fix</h1>";
echo "<hr>";

// GET parametresi ile işlem kontrolü
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'deactivate') {
    echo "<h2>🔌 Plugin Deactivation</h2>";
    
    // Plugin'i deaktive et
    $plugin_file = 'woo2shopify/woo2shopify.php';
    $active_plugins = get_option('active_plugins', array());
    
    if (in_array($plugin_file, $active_plugins)) {
        $key = array_search($plugin_file, $active_plugins);
        if ($key !== false) {
            unset($active_plugins[$key]);
            update_option('active_plugins', array_values($active_plugins));
            echo "✅ <strong>Plugin successfully deactivated!</strong><br>";
            echo "WordPress sitesi artık normal çalışmalı.<br>";
        }
    } else {
        echo "ℹ️ Plugin zaten deaktif durumda.<br>";
    }
    
    echo "<br><a href='?action=status' class='button'>Check Status</a> ";
    echo "<a href='/wp-admin/plugins.php' class='button'>Go to Plugins Page</a>";
    
} elseif ($action === 'activate') {
    echo "<h2>🔌 Plugin Activation</h2>";
    
    // Önce dosyaları kontrol et
    $plugin_dir = WP_PLUGIN_DIR . '/woo2shopify/';
    $main_file = $plugin_dir . 'woo2shopify.php';
    
    if (!file_exists($main_file)) {
        echo "❌ Plugin ana dosyası bulunamadı: $main_file<br>";
    } else {
        // Syntax kontrolü
        $syntax_check = shell_exec("php -l '$main_file' 2>&1");
        if (strpos($syntax_check, 'No syntax errors') !== false) {
            echo "✅ Syntax kontrolü başarılı<br>";
            
            // Plugin'i aktive et
            $plugin_file = 'woo2shopify/woo2shopify.php';
            $active_plugins = get_option('active_plugins', array());
            
            if (!in_array($plugin_file, $active_plugins)) {
                $active_plugins[] = $plugin_file;
                update_option('active_plugins', $active_plugins);
                echo "✅ <strong>Plugin successfully activated!</strong><br>";
            } else {
                echo "ℹ️ Plugin zaten aktif durumda.<br>";
            }
        } else {
            echo "❌ <strong>Syntax Error Found:</strong><br>";
            echo "<pre>" . htmlspecialchars($syntax_check) . "</pre>";
        }
    }
    
    echo "<br><a href='?action=status' class='button'>Check Status</a> ";
    echo "<a href='/wp-admin/plugins.php' class='button'>Go to Plugins Page</a>";
    
} elseif ($action === 'reset') {
    echo "<h2>🔄 Plugin Reset</h2>";
    
    // Plugin ayarlarını sıfırla
    delete_option('woo2shopify_settings');
    delete_option('woo2shopify_installed');
    delete_option('woo2shopify_version');
    
    // Database tablolarını temizle
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}woo2shopify_logs");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}woo2shopify_progress");
    
    echo "✅ Plugin ayarları ve database tabloları sıfırlandı.<br>";
    echo "<br><a href='?action=status' class='button'>Check Status</a>";
    
} else {
    // Status sayfası
    echo "<h2>📊 Current Status</h2>";
    
    // Plugin durumu
    $plugin_file = 'woo2shopify/woo2shopify.php';
    $active_plugins = get_option('active_plugins', array());
    $is_active = in_array($plugin_file, $active_plugins);
    
    echo "<strong>Plugin Status:</strong> " . ($is_active ? '🟢 ACTIVE' : '🔴 INACTIVE') . "<br>";
    
    // WordPress durumu
    $wp_error = get_option('wp_fatal_error_handler_last_error');
    if ($wp_error) {
        echo "<strong>WordPress Fatal Error:</strong> 🚨 YES<br>";
        echo "<div style='background: #ffeeee; padding: 10px; border: 1px solid #ff0000; margin: 10px 0;'>";
        echo "<strong>Last Error:</strong><br>";
        echo "<pre>" . print_r($wp_error, true) . "</pre>";
        echo "</div>";
    } else {
        echo "<strong>WordPress Fatal Error:</strong> ✅ NO<br>";
    }
    
    // Site erişilebilirlik
    $site_url = home_url();
    echo "<strong>Site URL:</strong> <a href='$site_url' target='_blank'>$site_url</a><br>";
    
    // Admin panel erişilebilirlik
    $admin_url = admin_url();
    echo "<strong>Admin URL:</strong> <a href='$admin_url' target='_blank'>$admin_url</a><br>";
    
    echo "<hr>";
    
    // İşlem butonları
    echo "<h2>🛠️ Emergency Actions</h2>";
    
    if ($is_active) {
        echo "<a href='?action=deactivate' class='button button-danger' onclick='return confirm(\"Plugin deaktive edilsin mi?\")'>🔴 Deactivate Plugin</a> ";
    } else {
        echo "<a href='?action=activate' class='button button-primary'>🟢 Activate Plugin</a> ";
    }
    
    echo "<a href='?action=reset' class='button button-warning' onclick='return confirm(\"Tüm plugin ayarları silinsin mi?\")'>🔄 Reset Plugin</a> ";
    echo "<a href='/wp-admin/' class='button'>📊 WordPress Admin</a> ";
    echo "<a href='/wp-admin/plugins.php' class='button'>🔌 Plugins Page</a>";
    
    echo "<hr>";
    
    // Sistem bilgileri
    echo "<h2>💻 System Information</h2>";
    echo "<strong>PHP Version:</strong> " . PHP_VERSION . "<br>";
    echo "<strong>WordPress Version:</strong> " . get_bloginfo('version') . "<br>";
    echo "<strong>WooCommerce:</strong> " . (class_exists('WooCommerce') ? 'Active (' . WC_VERSION . ')' : 'Not Active') . "<br>";
    echo "<strong>Memory Limit:</strong> " . ini_get('memory_limit') . "<br>";
    echo "<strong>Max Execution Time:</strong> " . ini_get('max_execution_time') . "<br>";
    
    // Plugin dosyaları
    echo "<br><strong>Plugin Files:</strong><br>";
    $plugin_dir = WP_PLUGIN_DIR . '/woo2shopify/';
    $main_file = $plugin_dir . 'woo2shopify.php';
    echo "&nbsp;&nbsp;Main File: " . (file_exists($main_file) ? '✅ EXISTS' : '❌ MISSING') . "<br>";
    echo "&nbsp;&nbsp;Includes Dir: " . (is_dir($plugin_dir . 'includes/') ? '✅ EXISTS' : '❌ MISSING') . "<br>";
    echo "&nbsp;&nbsp;Assets Dir: " . (is_dir($plugin_dir . 'assets/') ? '✅ EXISTS' : '❌ MISSING') . "<br>";
}

echo "<hr>";
echo "<p><em>Emergency fix completed at " . date('Y-m-d H:i:s') . "</em></p>";

// CSS for buttons
echo "<style>
.button {
    display: inline-block;
    padding: 8px 16px;
    margin: 4px;
    text-decoration: none;
    border-radius: 4px;
    border: 1px solid #ccc;
    background: #f7f7f7;
    color: #333;
}
.button:hover {
    background: #e7e7e7;
}
.button-primary {
    background: #0073aa;
    color: white;
    border-color: #0073aa;
}
.button-danger {
    background: #dc3232;
    color: white;
    border-color: #dc3232;
}
.button-warning {
    background: #ffb900;
    color: white;
    border-color: #ffb900;
}
</style>";
?>
