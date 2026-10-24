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

use FreeAgencyPreview\FreeAgencyPreviewService;

$module_name = basename(dirname(__FILE__));

// Get current season info
$factory = \Module\ModuleServices::current()->factory(\Module\Factories\FreeAgencyPreviewFactory::class);
$season = $factory->season();

// Check if season is available
if ($season->endingYear === null || $season->endingYear === 0) {
    PageLayout\PageLayout::header();
    echo '<p style="text-align: center; padding: 2rem;">Season information is not available.</p>';
    PageLayout\PageLayout::footer();
    return;
}

// Resolve the requested target ending year from the URL (clamped to [C, C+5]).
$rawYear = isset($_GET['year']) && is_string($_GET['year']) ? $_GET['year'] : null;
$requestedYear = FreeAgencyPreviewService::resolveRequestedYear($rawYear, $season->endingYear);

$pagetitle = "- Upcoming Free Agents ($requestedYear)";

// Initialize services
$service = $factory->service();
$view = $factory->view();

// Get upcoming free agents
$freeAgents = $service->getUpcomingFreeAgents($requestedYear, $season->endingYear);

// Render page
PageLayout\PageLayout::header();
echo $view->render($requestedYear, $freeAgents);
PageLayout\PageLayout::footer();
