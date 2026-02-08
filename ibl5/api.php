<?php

declare(strict_types=1);

/**
 * IBL5 Public REST API Entry Point
 *
 * Lightweight bootstrap that skips PHP-Nuke (mainfile.php) entirely.
 * Only loads the autoloader, config, and database connection.
 *
 * URL: /api/v1/{resource} → rewritten to api.php?route={resource}
 */

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: X-API-Key, Content-Type');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/autoloader.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db/db.php';

$responder = new Api\Response\JsonResponder();
$router = new Api\Router();

/** @var \mysqli $mysqli_db */

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Only GET is supported
if ($method !== 'GET') {
    $responder->error(405, 'method_not_allowed', 'Only GET requests are supported.');
    exit;
}

// Authenticate
$authenticator = new Api\Middleware\ApiKeyAuthenticator(
    new Api\Repository\ApiKeyRepository($mysqli_db)
);
$apiKey = $authenticator->authenticate();
if ($apiKey === null) {
    $responder->error(401, 'unauthorized', 'Missing or invalid API key. Include X-API-Key header.');
    exit;
}

// Rate limit
$rateLimiter = new Api\Middleware\RateLimiter(
    new Api\Repository\RateLimitRepository($mysqli_db)
);
$rateLimitResult = $rateLimiter->check($apiKey);
if ($rateLimitResult !== null) {
    $responder->error(429, 'rate_limit_exceeded', 'Rate limit exceeded. Try again later.', $rateLimitResult);
    exit;
}

// Route
$match = $router->match($route, $method);
if ($match === null) {
    $responder->error(404, 'not_found', 'The requested endpoint does not exist.');
    exit;
}

// Dispatch to controller
/** @var class-string<\Api\Contracts\ControllerInterface> $controllerClass */
$controllerClass = $match['controller'];

/** @var \Api\Contracts\ControllerInterface $controller */
$controller = new $controllerClass($mysqli_db);
$controller->handle($match['params'], $_GET, $responder);
