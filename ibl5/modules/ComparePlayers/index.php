<?php

declare(strict_types=1);

/**
 * Compare Players Module
 *
 * Side-by-side comparison of two players' ratings, season stats, and career stats.
 *
 * @see ComparePlayers\ComparePlayersService For comparison logic
 * @see ComparePlayers\ComparePlayersView For HTML rendering
 */

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are read here via the Http\HttpRequest value object.
$httpRequest = \Module\ModuleServices::current()->request();
$op = is_string($httpRequest->request('op')) ? $httpRequest->request('op') : '';

global $user;

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\ComparePlayersFactory::class);
$controller = $factory->controller();

switch ($op) {
    default:
        $controller->main($user);
        break;
}
