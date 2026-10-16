<?php

declare(strict_types=1);

/**
 * Franchise_History Module - Display franchise history and records
 *
 * Shows all-time and recent (last 5 seasons) win/loss records and titles.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see FranchiseHistory\FranchiseHistoryRepository For database operations
 * @see FranchiseHistory\FranchiseHistoryView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\FranchiseHistoryFactory::class);
$season = $factory->season();

PageLayout\PageLayout::header();

// Initialize services
$service = $factory->service();
$view = $factory->view();

// Get franchise history data
$franchiseData = $service->getAllFranchiseHistory($season->endingYear);

// Render output
echo $view->render($franchiseData);

PageLayout\PageLayout::footer();
