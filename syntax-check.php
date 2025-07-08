<?php
/**
 * Syntax Check for functions.php
 */

echo "<h1>🔍 PHP Syntax Check</h1>";

// Check syntax of functions.php
$file = 'includes/functions.php';
$output = array();
$return_var = 0;

exec("php -l $file 2>&1", $output, $return_var);

echo "<h2>📋 Syntax Check Results for $file:</h2>";

if ($return_var === 0) {
    echo "<div style='color: green; font-weight: bold;'>✅ No syntax errors detected</div>";
} else {
    echo "<div style='color: red; font-weight: bold;'>❌ Syntax errors found:</div>";
    echo "<pre style='background: #ffeeee; padding: 10px; border: 1px solid #ff0000;'>";
    foreach ($output as $line) {
        echo htmlspecialchars($line) . "\n";
    }
    echo "</pre>";
}

// Also check other important files
$files_to_check = array(
    'woo2shopify.php',
    'includes/class-woo2shopify-batch-processor.php',
    'includes/class-woo2shopify-admin.php'
);

foreach ($files_to_check as $check_file) {
    if (file_exists($check_file)) {
        $output = array();
        $return_var = 0;
        exec("php -l $check_file 2>&1", $output, $return_var);
        
        echo "<h3>📋 $check_file:</h3>";
        if ($return_var === 0) {
            echo "<span style='color: green;'>✅ OK</span><br>";
        } else {
            echo "<span style='color: red;'>❌ Errors:</span><br>";
            echo "<pre style='background: #ffeeee; padding: 5px;'>";
            foreach ($output as $line) {
                echo htmlspecialchars($line) . "\n";
            }
            echo "</pre>";
        }
    }
}

echo "<p>Check completed at " . date('Y-m-d H:i:s') . "</p>";
?>
