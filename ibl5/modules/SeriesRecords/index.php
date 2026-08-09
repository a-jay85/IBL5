<?php

declare(strict_types=1);

/**
 * Series Records Module
 * 
 * Displays head-to-head series records between all teams in a grid format.
 * Each cell shows wins-losses for the row team vs the column team.
 * 
 * @see \SeriesRecords\SeriesRecordsController
 */

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

// Use global mysqli database connection
global $mysqli_db, $user;

$commonRepo = new \Repositories\TeamIdentityRepository($mysqli_db);
$repository = new \SeriesRecords\SeriesRecordsRepository($mysqli_db);
$service = new \SeriesRecords\SeriesRecordsService();
$view = new \SeriesRecords\SeriesRecordsView($service);
$nukeCompat = new \Utilities\NukeCompat();
$controller = new \SeriesRecords\SeriesRecordsController($commonRepo, $repository, $service, $view, $nukeCompat);
$controller->main($user);
