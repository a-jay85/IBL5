<?php
// Output IMMEDIATELY before anything else
ob_implicit_flush(true);
echo "FIRST LINE - FILE STARTED\n";
flush();

echo "Second line\n";
flush();

echo "Third line - about to load autoloader\n";
flush();

require_once __DIR__ . '/autoloader.php';

echo "Fourth line - autoloader loaded\n";
flush();

echo "DONE - No redirect\n";
?>
