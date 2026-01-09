<?php

declare(strict_types=1);

/**
 * Draft_Pick_Locator Module - Display draft pick ownership matrix
 *
 * Shows which teams own which draft picks across multiple years.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see DraftPickLocator\DraftPickLocatorRepository For database operations
 * @see DraftPickLocator\DraftPickLocatorService For business logic
 * @see DraftPickLocator\DraftPickLocatorView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use DraftPickLocator\DraftPickLocatorRepository;
use DraftPickLocator\DraftPickLocatorService;
use DraftPickLocator\DraftPickLocatorView;

global $mysqli_db;

$season = new Season($mysqli_db);

// Initialize services
$repository = new DraftPickLocatorRepository($mysqli_db);
$service = new DraftPickLocatorService($repository);
$view = new DraftPickLocatorView();

// Get teams with their draft picks
$teamsWithPicks = $service->getAllTeamsWithPicks();

// Render output
echo $view->render($teamsWithPicks, $season->endingYear);
