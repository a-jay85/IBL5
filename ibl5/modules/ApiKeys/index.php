<?php

declare(strict_types=1);

/**
 * ApiKeys Module - Self-service API key management
 *
 * Lets logged-in users generate, view, and revoke their own API keys
 * for use with the Player Export CSV endpoint and Google Sheets IMPORTDATA.
 *
 * @see ApiKeys\ApiKeysService For key generation logic
 * @see ApiKeys\ApiKeysView For HTML rendering
 */

if (stripos($_SERVER['PHP_SELF'], 'modules.php') === false) {
    die("You can't access this file directly...");
}

global $mysqli_db, $user, $authService;

$op = is_string($_REQUEST['op'] ?? null) ? $_REQUEST['op'] : 'main';

$repository = new \ApiKeys\ApiKeysRepository($mysqli_db);
$service    = new \ApiKeys\ApiKeysService($repository);
$view       = new \ApiKeys\ApiKeysView();
$nukeCompat = new \Utilities\NukeCompat();
$controller = new \ApiKeys\ApiKeysController($service, $view, $nukeCompat, $authService);

$controller->handle($op, $user);
