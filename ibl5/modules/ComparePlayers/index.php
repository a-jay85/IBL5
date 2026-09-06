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
get_lang($module_name);

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are read here via the Http\HttpRequest value object.
$httpRequest = \Http\HttpRequest::fromGlobals();
$op = is_string($httpRequest->request('op')) ? $httpRequest->request('op') : '';

global $mysqli_db, $user;

$repository = new \ComparePlayers\ComparePlayersRepository($mysqli_db);
$service    = new \ComparePlayers\ComparePlayersService($repository);
$view       = new \ComparePlayers\ComparePlayersView();
$nukeCompat = new \Utilities\NukeCompat();
$controller = new \ComparePlayers\ComparePlayersController($repository, $service, $view, $nukeCompat);

switch ($op) {
    default:
        $controller->main($user);
        break;
}
