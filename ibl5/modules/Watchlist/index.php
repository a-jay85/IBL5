<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

// Module inputs are read from $_REQUEST explicitly (PR2 narrowed the legacy
// ConfigBootstrap::extractRequestToGlobals() allowlist).
$op = (is_string($_REQUEST['op'] ?? null) && $_REQUEST['op'] !== '') ? $_REQUEST['op'] : '';

$pagetitle = "- Team Pages";

/**
 * Main entry point for the watchlist module.
 *
 * @param mixed $user Current user
 */
function watchlist($user)
{
    global $mysqli_db, $op;

    cookiedecode($user);

    $teamIdentityRepo = new Repositories\TeamIdentityRepository($mysqli_db);
    $repo = new Watchlist\WatchlistRepository($mysqli_db);
    $service = new Watchlist\WatchlistService($teamIdentityRepo, $repo);
    $view = new Watchlist\WatchlistView();
    $nukeCompat = new Utilities\NukeCompat();
    $controller = new Watchlist\WatchlistController($service, $view, $nukeCompat);
    $controller->handleRequest($user, $op ?? '');
}

watchlist($user);
