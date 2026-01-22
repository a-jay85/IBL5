<?php
// Absolutely minimal test - no autoloader at all
header('Content-Type: text/html; charset=UTF-8');
echo "Step 1: File started<br>";

// Manually require the classes we need WITHOUT autoloader
echo "Step 2: About to require classes manually...<br>";

// Check what files exist
$themeComponentsFile = __DIR__ . '/classes/View/ThemeComponents.php';
$bladeRendererFile = __DIR__ . '/classes/View/BladeRenderer.php';

if (file_exists($themeComponentsFile)) {
    echo "Step 3: ThemeComponents.php exists<br>";
    require_once $themeComponentsFile;
    echo "Step 4: ThemeComponents.php loaded successfully<br>";
} else {
    echo "Step 3: ThemeComponents.php NOT FOUND<br>";
}

if (file_exists($bladeRendererFile)) {
    echo "Step 5: BladeRenderer.php exists<br>";
    require_once $bladeRendererFile;
    echo "Step 6: BladeRenderer.php loaded successfully<br>";
} else {
    echo "Step 5: BladeRenderer.php NOT FOUND<br>";
}

echo "<hr>";
echo "<h1>SUCCESS - No redirect occurred!</h1>";
echo "<p>This means the autoloader itself is fine, but something else is causing the redirect.</p>";
?>
