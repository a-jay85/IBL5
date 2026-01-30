<?php

declare(strict_types=1);

/**
 * Free_Agency_Preview Module - Display upcoming free agents
 *
 * Shows a table of players who will become free agents at the end
 * of the current season with their ratings.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see FreeAgencyPreview\FreeAgencyPreviewRepository For database operations
 * @see FreeAgencyPreview\FreeAgencyPreviewService For business logic
 * @see FreeAgencyPreview\FreeAgencyPreviewView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use FreeAgencyPreview\FreeAgencyPreviewRepository;
use FreeAgencyPreview\FreeAgencyPreviewService;
use FreeAgencyPreview\FreeAgencyPreviewView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

global $mysqli_db;

// Get current season info
$season = new Season($mysqli_db);

// Check if season is available
if ($season->endingYear === null || $season->endingYear === 0) {
    Nuke\Header::header();
    echo '<p style="text-align: center; padding: 2rem;">Season information is not available.</p>';
    Nuke\Footer::footer();
    return;
}

$pagetitle = "- Upcoming Free Agents ($season->endingYear)";

// Initialize services
$repository = new FreeAgencyPreviewRepository($mysqli_db);
$service = new FreeAgencyPreviewService($repository);
$view = new FreeAgencyPreviewView();

// Get upcoming free agents
$freeAgents = $service->getUpcomingFreeAgents($season->endingYear);

// Render page
Nuke\Header::header();
echo $view->render($season->endingYear, $freeAgents);
Nuke\Footer::footer();
