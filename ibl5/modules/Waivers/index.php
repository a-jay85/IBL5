<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here. Leave $action unset (null)
// so the downstream `$action ?? 'add'` fallback inside waivers() still applies.
$action = (is_string($_REQUEST['action'] ?? null) && $_REQUEST['action'] !== '') ? $_REQUEST['action'] : null;

$pagetitle = "- Team Pages";

/**
 * Main entry point for waivers module
 * 
 * @param mixed $user Current user
 */
function waivers($user)
{
    global $mysqli_db, $action;

    cookiedecode($user);

    $repo = new Waivers\WaiversRepository($mysqli_db);
    $teamIdentityRepo = new Repositories\TeamIdentityRepository($mysqli_db);
    $playerLookupRepo = new Repositories\PlayerLookupRepository($mysqli_db);
    $salaryCapRepo = new Repositories\SalaryCapRepository($mysqli_db);
    $validator = new Waivers\WaiversValidator();
    $newsService = new Topics\News\NewsRepository($mysqli_db);
    $processor = new Waivers\WaiversProcessor($repo, $teamIdentityRepo, $playerLookupRepo, $validator, $newsService, $mysqli_db);
    $view = new Waivers\WaiversView();
    $teamQueryRepo = new Team\TeamQueryRepository($mysqli_db);
    $service = new Waivers\WaiversService($teamIdentityRepo, $processor, $view, $teamQueryRepo, $mysqli_db);
    $nukeCompat = new Utilities\NukeCompat();
    $controller = new Waivers\WaiversController($service, $processor, $view, $teamIdentityRepo, $salaryCapRepo, $nukeCompat, $mysqli_db);
    $controller->handleWaiverRequest($user, $action ?? 'add');
}

waivers($user);
