<?php

declare(strict_types=1);

/**
 * Cap_Info Module - Salary cap information display
 *
 * Displays salary cap availability and roster slots for all teams.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see CapInfo\CapInfoService For business logic
 * @see CapInfo\CapInfoRepository For database operations
 * @see CapInfo\CapInfoView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use CapInfo\CapInfoRepository;
use CapInfo\CapInfoService;
use CapInfo\CapInfoView;

global $mysqli_db;

$season = new Season($mysqli_db);

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

Nuke\Header::header();

// Initialize services
$repository = new CapInfoRepository($mysqli_db);
$service = new CapInfoService($repository, $mysqli_db);
$view = new CapInfoView();

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
