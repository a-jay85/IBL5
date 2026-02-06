<?php

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

$controller = new \SeriesRecords\SeriesRecordsController($mysqli_db);
$controller->main($user);
