<?php

declare(strict_types=1);

/**
 * Season_Highs Module - Display season high stats
 *
 * Shows players' and teams' highest single-game performances
 * for the current season phase.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see SeasonHighs\SeasonHighsRepository For database operations
 * @see SeasonHighs\SeasonHighsService For business logic
 * @see SeasonHighs\SeasonHighsView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));

// Get current season info
$factory = \Module\ModuleServices::current()->factory(\Module\Factories\SeasonHighsFactory::class);
$season = $factory->season();

// Determine season phase (from request or current phase)
$seasonPhase = isset($_GET['seasonPhase']) && !empty($_GET['seasonPhase'])
    ? $_GET['seasonPhase']
    : $season->phase;

$pagetitle = "- $seasonPhase Stat Leaders";

// Initialize services
$service = $factory->service();
$view = $factory->view();

// Get season highs data
$data = $service->getSeasonHighsData($seasonPhase);
$homeAwayData = $service->getHomeAwayHighs($seasonPhase);
$discrepancies = $service->validateAgainstRcb($homeAwayData, $season->beginningYear);

// Render page
PageLayout\PageLayout::header();

echo $view->render($seasonPhase, $data);
echo $view->renderHomeAwayHighs($seasonPhase, $homeAwayData, $discrepancies);

PageLayout\PageLayout::footer();
