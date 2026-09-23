<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

global $mysqli_db, $user, $authService;

$pagetitle = "- Team Pages";

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
$request = \Http\HttpRequest::fromGlobals();
$controller = new Waivers\WaiversController($service, $processor, $view, $teamIdentityRepo, $salaryCapRepo, $nukeCompat, $mysqli_db, $authService, $request);
$controller->handleWaiverRequest($user);
