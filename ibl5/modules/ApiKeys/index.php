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

global $user;

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\ApiKeysFactory::class);

$httpRequest = $factory->request();
$op = is_string($httpRequest->request('op')) ? $httpRequest->request('op') : 'main';

$controller = $factory->controller();

$controller->handle($op, $user);
