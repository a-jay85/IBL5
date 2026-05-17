<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

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
    $teamIdentityRepo = new Services\TeamIdentityRepository($mysqli_db);
    $playerLookupRepo = new Services\PlayerLookupRepository($mysqli_db);
    $salaryCapRepo = new Services\SalaryCapRepository($mysqli_db);
    $validator = new Waivers\WaiversValidator();
    $newsService = new Services\NewsService($mysqli_db);
    $processor = new Waivers\WaiversProcessor($repo, $teamIdentityRepo, $playerLookupRepo, $validator, $newsService, $mysqli_db);
    $view = new Waivers\WaiversView();
    $teamQueryRepo = new Team\TeamQueryRepository($mysqli_db);
    $service = new Waivers\WaiversService($teamIdentityRepo, $processor, $view, $teamQueryRepo, $mysqli_db);
    $nukeCompat = new Utilities\NukeCompat();
    $controller = new Waivers\WaiversController($service, $processor, $view, $teamIdentityRepo, $salaryCapRepo, $nukeCompat, $mysqli_db);
    $controller->handleWaiverRequest($user, $action ?? 'add');
}

waivers($user);
