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

use SeasonHighs\SeasonHighsRepository;
use SeasonHighs\SeasonHighsService;
use SeasonHighs\SeasonHighsView;

$module_name = basename(dirname(__FILE__));

global $mysqli_db, $leagueContext;

// Get current season info
$season = new \Season\Season($mysqli_db, $leagueContext);

// Determine season phase (from request or current phase)
$seasonPhase = isset($_GET['seasonPhase']) && !empty($_GET['seasonPhase'])
    ? $_GET['seasonPhase']
    : $season->phase;

$pagetitle = "- $seasonPhase Stat Leaders";

// Initialize services
$repository = new \SeasonHighs\CachedSeasonHighsRepository(
    new SeasonHighsRepository($mysqli_db, $leagueContext),
    new \Cache\DatabaseCache($mysqli_db),
    $leagueContext instanceof \League\LeagueContext
        ? $leagueContext->getCurrentLeague()
        : \League\LeagueContext::LEAGUE_IBL
);
$service = new SeasonHighsService($repository, $season);
$view = new SeasonHighsView();

// Get season highs data
$data = $service->getSeasonHighsData($seasonPhase);
$homeAwayData = $service->getHomeAwayHighs($seasonPhase);
$discrepancies = $service->validateAgainstRcb($homeAwayData, $season->beginningYear);

// Render page
PageLayout\PageLayout::header();

echo $view->render($seasonPhase, $data);
echo $view->renderHomeAwayHighs($seasonPhase, $homeAwayData, $discrepancies);

PageLayout\PageLayout::footer();
