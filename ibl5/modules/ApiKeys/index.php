<?php

declare(strict_types=1);

/**
 * ApiKeys Module - Player Export page
 *
 * Shows logged-in users the Google Sheets Sync card and the Player Export
 * column guide. The API key card is hidden; its generate/revoke ops remain.
 *
 * @see ApiKeys\ApiKeysService For key generation logic
 * @see ApiKeys\ApiKeysView For HTML rendering
 */

$phpSelf = $_SERVER['PHP_SELF'] ?? '';
if (stripos(is_string($phpSelf) ? $phpSelf : '', 'modules.php') === false) {
    die("You can't access this file directly...");
}

global $user;

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\ApiKeysFactory::class);

$httpRequest = $factory->request();
$op = is_string($httpRequest->request('op')) ? $httpRequest->request('op') : 'main';

$controller = $factory->controller();

$controller->handle($op, $user);
