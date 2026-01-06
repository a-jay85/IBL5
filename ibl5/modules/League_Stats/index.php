<?php

/**
 * League Stats Module - Controller
 *
 * Displays league-wide team statistics including offense/defense totals,
 * averages, and differentials for all teams.
 *
 * Refactored to use:
 * - LeagueStats\LeagueStatsRepository for bulk data fetching (1 query vs 30)
 * - LeagueStats\LeagueStatsService for statistics processing
 * - LeagueStats\LeagueStatsView for HTML rendering
 * - Statistics\StatsFormatter for consistent number formatting
 * - Utilities\HtmlSanitizer for XSS protection
 */

declare(strict_types=1);

global $cookie, $mysqli_db;

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$pagetitle = "- $module_name";

// Get current user's team for row highlighting
$username = $cookie[1] ?? '';
$commonRepository = new Services\CommonMysqliRepository($mysqli_db);
$userTeamName = $commonRepository->getTeamnameFromUsername($username);
$userTeam = Team::initialize($mysqli_db, $userTeamName);
$userTeamId = (int) ($userTeam->teamID ?? 0);

// Initialize components
$repository = new LeagueStats\LeagueStatsRepository($mysqli_db);
$service = new LeagueStats\LeagueStatsService();
$view = new LeagueStats\LeagueStatsView();

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
$leagueStatsHtml = $view->render($viewData, $userTeamId);

require "view.php";