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

global $leagueContext;
/** @var \League\LeagueContext $leagueContext */

// IBL serves Season Highs from the Records page; Olympics keeps this standalone page.
if (!($leagueContext instanceof \League\LeagueContext && $leagueContext->isOlympics())) {
    $target = \Records\RecordsController::legacyRedirectUrl('SeasonHighs', $_GET + $_POST)
        ?? 'modules.php?name=Records&tab=' . \Records\RecordsController::TAB_THISSEASON;
    (new \Api\Response\RedirectResponder())->redirect($target);
    return;
}

// Get current season info
$factory = \Module\ModuleServices::current()->factory(\Module\Factories\SeasonHighsFactory::class);
$season = $factory->season();

// Determine season phase (from request or current phase)
$seasonPhaseParam = $_GET['seasonPhase'] ?? null;
$seasonPhase = is_string($seasonPhaseParam) && $seasonPhaseParam !== '' && $seasonPhaseParam !== '0'
    ? $seasonPhaseParam
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
