<?php
// Trace what's causing the redirect
header('Content-Type: text/html; charset=UTF-8');
echo "<!DOCTYPE html><html><body><pre>";
echo "=== REDIRECT TRACE ===\n\n";

// Check if any autoloaders are already registered
echo "Checking existing autoloaders BEFORE loading anything:\n";
$autoloaders = spl_autoload_functions();
echo "Number of autoloaders: " . count($autoloaders) . "\n";
foreach ($autoloaders as $idx => $loader) {
    echo "  Autoloader $idx: " . print_r($loader, true) . "\n";
}
echo "\n";

// Check included files
echo "Files already included:\n";
$included = get_included_files();
foreach ($included as $file) {
    echo "  - $file\n";
}
echo "\n";

// Check if mainfile.php is somehow being loaded
echo "Checking if mainfile.php is loaded: ";
$mainfileLoaded = false;
foreach ($included as $file) {
    if (strpos($file, 'mainfile.php') !== false) {
        $mainfileLoaded = true;
        echo "YES - FOUND: $file\n";
        break;
    }
}
if (!$mainfileLoaded) {
    echo "NO\n";
}
echo "\n";

// Check php.ini settings for auto_prepend_file
echo "PHP auto_prepend_file setting: " . ini_get('auto_prepend_file') . "\n";
echo "PHP auto_append_file setting: " . ini_get('auto_append_file') . "\n";
echo "\n";

// Now try to load autoloader
echo "About to load autoloader.php...\n";
ob_flush();
flush();

require_once __DIR__ . '/autoloader.php';

echo "✓ Autoloader loaded successfully!\n";
echo "\n";

echo "=== SUCCESS - NO REDIRECT ===\n";
echo "</pre></body></html>";
?>
