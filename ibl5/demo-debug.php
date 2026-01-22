<?php
// Debug page - outputs immediately before any processing
header('Content-Type: text/html; charset=UTF-8');

// Prevent any caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo "<!DOCTYPE html><html><head><title>Debug Page</title></head><body>";
echo "<h1>✅ DEBUG PAGE LOADED - NO REDIRECT</h1>";
echo "<hr>";
echo "<h2>Server Information</h2>";
echo "<pre>";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'N/A') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'N/A') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'N/A') . "\n";
echo "PWD: " . getcwd() . "\n";
echo "</pre>";
echo "<hr>";
echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>If you see this page, Apache is NOT redirecting</li>";
echo "<li>The correct URL to access demo.php is: <strong>http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/ibl5/demo.php</strong></li>";
echo "<li>Try accessing: <a href='/ibl5/demo.php'>http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/ibl5/demo.php</a></li>";
echo "</ol>";
echo "</body></html>";
?>
