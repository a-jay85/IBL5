<?php

declare(strict_types=1);

/**
 * Power_Rankings Module - Display team power rankings
 *
 * Shows current power rankings based on team performance.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see PowerRankings\PowerRankingsRepository For database operations
 * @see PowerRankings\PowerRankingsView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use PowerRankings\PowerRankingsRepository;
use PowerRankings\PowerRankingsView;

global $mysqli_db;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- $module_name";

$season = new Season($mysqli_db);

Nuke\Header::header();

// Initialize services
$repository = new PowerRankingsRepository($mysqli_db);
$view = new PowerRankingsView();

// Get power rankings data
$rankings = $repository->getPowerRankings();

// Render output
echo $view->render($rankings, $season->endingYear);

Nuke\Footer::footer();
