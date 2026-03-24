<?php

declare(strict_types=1);

/**
 * League_Starters Module - Display starting lineups for all teams
 *
 * Shows all team starters organized by position.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see LeagueStarters\LeagueStartersService For business logic
 * @see LeagueStarters\LeagueStartersView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use LeagueStarters\LeagueStartersService;
use LeagueStarters\LeagueStartersView;

global $cookie, $mysqli_db;

// Route HTMX API requests (no PageLayout, returns HTML fragment only)
$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : '';
if ($op === 'api') {
    $handler = new LeagueStarters\LeagueStartersApiHandler($mysqli_db);
    $handler->handle();
    return;
}

$commonRepository = new Services\CommonMysqliRepository($mysqli_db);
$season = new \Season\Season($mysqli_db);

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$pagetitle = "- $module_name";

$league = new \League\League($mysqli_db);

// Initialize services
$service = new LeagueStartersService($mysqli_db, $league);
$view = new LeagueStartersView($mysqli_db, $season, $module_name);

// Get starters by position
$startersByPosition = $service->getAllStartersByPosition();
$display = 'ratings';
if (isset($_REQUEST['display']) && is_string($_REQUEST['display'])
    && in_array($_REQUEST['display'], ['ratings', 'total_s', 'avg_s', 'per36mins'], true)) {
    $display = $_REQUEST['display'];
}

// Render header first (populates $cookie via cookiedecode())
PageLayout\PageLayout::header();

$username = strval($cookie[1] ?? '');
$userTeamName = $commonRepository->getTeamnameFromUsername($username);
$userTeam = \Team\Team::initialize($mysqli_db, $userTeamName);

echo $view->render($startersByPosition, $userTeam, $display);

PageLayout\PageLayout::footer();