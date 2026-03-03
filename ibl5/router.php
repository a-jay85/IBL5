<?php

declare(strict_types=1);

// Router script for PHP's built-in server (CI only).
// Simulates: RewriteRule ^api/v1/(.*)$ api.php?route=$1 [QSA,L]

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';

if (preg_match('#^/ibl5/api/v1/(.*)$#', $path, $m) === 1) {
    $_GET['route'] = $m[1];
    require __DIR__ . '/api.php';
    return true;
}

// Let PHP's built-in server handle static files and PHP scripts natively.
return false;
