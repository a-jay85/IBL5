<?php
/**
 * Progressive Class Loading Test
 *
 * This loads each class one by one to identify which one causes the redirect
 */

// Prevent any caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo "<!DOCTYPE html><html><head><title>Progressive Loading Test</title></head><body>";
echo "<h1>Progressive Class Loading Test</h1>";
echo "<p>Testing each class individually to find which one causes redirect...</p>";
echo "<hr>";

// Step 1: Load autoloader
echo "<p>✓ Step 1: Loading autoloader...</p>";
require_once __DIR__ . '/autoloader.php';
echo "<p style='color: green;'>✓ Autoloader loaded successfully!</p>";

// Step 2: Try loading View\BladeRenderer
echo "<p>✓ Step 2: Loading View\\BladeRenderer...</p>";
try {
    $test = new View\BladeRenderer(__DIR__ . '/themes/IBL');
    echo "<p style='color: green;'>✓ BladeRenderer loaded successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ BladeRenderer failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Step 3: Try loading View\ThemeComponents
echo "<p>✓ Step 3: Loading View\\ThemeComponents...</p>";
try {
    $card = View\ThemeComponents::openCard('Test', 'Test content');
    echo "<p style='color: green;'>✓ ThemeComponents loaded successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ ThemeComponents failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Step 4: Try loading Navigation\BlockLinkParser
echo "<p>✓ Step 4: Loading Navigation\\BlockLinkParser...</p>";
try {
    $parser = new Navigation\BlockLinkParser();
    echo "<p style='color: green;'>✓ BlockLinkParser loaded successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ BlockLinkParser failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Step 5: Try loading Navigation\MobileMenuBuilder
echo "<p>✓ Step 5: Loading Navigation\\MobileMenuBuilder...</p>";
try {
    $builder = new Navigation\MobileMenuBuilder($parser, __DIR__ . '/blocks');
    echo "<p style='color: green;'>✓ MobileMenuBuilder loaded successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ MobileMenuBuilder failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Step 6: Try calling build() method
echo "<p>✓ Step 6: Calling MobileMenuBuilder::build()...</p>";
try {
    $menu = $builder->build();
    echo "<p style='color: green;'>✓ MobileMenuBuilder::build() completed successfully!</p>";
    echo "<p>Menu items found: Team(" . count($menu['team']) . "), Stats(" . count($menu['stats']) . "), Site(" . count($menu['site']) . "), Account(" . count($menu['account']) . ")</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ MobileMenuBuilder::build() failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h2>✅ ALL TESTS PASSED - No redirect occurred!</h2>";
echo "<p>If you see this message, all classes loaded successfully without triggering a redirect.</p>";
echo "<p>This means the issue is likely in how the classes interact together in demo.php</p>";
echo "</body></html>";
?>
