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

$module_name = basename(dirname(__FILE__));

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\DraftPickLocatorFactory::class);
$season = $factory->season();

$pagetitle = "- Draft Pick Locator";

// Initialize services
$service = $factory->service();
$view = $factory->view();

// Get teams with their draft picks
$teamsWithPicks = $service->getAllTeamsWithPicks();

// Render page
PageLayout\PageLayout::header();

echo $view->render($teamsWithPicks, $season->endingYear);

PageLayout\PageLayout::footer();
