<?php

/**
 * League Stats Module - Controller
 *
 * Displays league-wide team statistics including offense/defense totals,
 * averages, and differentials for all teams.
 *
 * Refactored to use:
 * - TeamOffDefStats\TeamOffDefStatsRepository for bulk data fetching (1 query vs 30)
 * - TeamOffDefStats\TeamOffDefStatsService for statistics processing
 * - TeamOffDefStats\TeamOffDefStatsView for HTML rendering
 * - BasketballStats\StatsFormatter for consistent number formatting
 * - Utilities\HtmlSanitizer for XSS protection
 */

declare(strict_types=1);

global $mysqli_db;

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$pagetitle = "- $module_name";

// Initialize components
$repository = new TeamOffDefStats\TeamOffDefStatsRepository($mysqli_db);
$service = new TeamOffDefStats\TeamOffDefStatsService();
$view = new TeamOffDefStats\TeamOffDefStatsView();

// Fetch and process data
$rawStats = $repository->getAllTeamStats();
$processedStats = $service->processTeamStats($rawStats);
$leagueTotals = $service->calculateLeagueTotals($processedStats);
$differentials = $service->calculateDifferentials($processedStats);

// Prepare data for view
$viewData = [
    'teams' => $processedStats,
    'league' => $leagueTotals,
    'differentials' => $differentials,
];

// Render output
$leagueStatsHtml = $view->render($viewData);

require "view.php";