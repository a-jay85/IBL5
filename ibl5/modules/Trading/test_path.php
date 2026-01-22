<?php
// Test file to check paths on production
echo "<pre>";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";
echo "SCRIPT_FILENAME: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "__FILE__: " . __FILE__ . "\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "\n";
echo "Calculated mainfile path: " . $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php' . "\n";
echo "File exists: " . (file_exists($_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php') ? 'YES' : 'NO') . "\n";
echo "\n";
echo "Alternative path: " . dirname(dirname(dirname(__FILE__))) . '/mainfile.php' . "\n";
echo "File exists: " . (file_exists(dirname(dirname(dirname(__FILE__))) . '/mainfile.php') ? 'YES' : 'NO') . "\n";
echo "</pre>";
