<?php

declare(strict_types=1);

/**
 * CapSpace Module - Salary cap information display
 *
 * Displays salary cap availability and roster slots for all teams.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see CapSpace\CapSpaceService For business logic
 * @see CapSpace\CapSpaceRepository For database operations
 * @see CapSpace\CapSpaceView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use CapSpace\CapSpaceRepository;
use CapSpace\CapSpaceService;
use CapSpace\CapSpaceView;

global $mysqli_db;

$season = new Season($mysqli_db);

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

Nuke\Header::header();

// Initialize services
$repository = new CapSpaceRepository($mysqli_db);
$service = new CapSpaceService($repository, $mysqli_db);
$view = new CapSpaceView();

// Get data
$teamsData = $service->getTeamsCapData($season);
$displayYears = $service->getDisplayYears($season);

// Render output
echo $view->render(
    $teamsData,
    $displayYears['beginningYear'],
    $displayYears['endingYear']
);

Nuke\Footer::footer();
