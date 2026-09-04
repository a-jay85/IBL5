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

global $mysqli_db, $authService;

// Route HTMX API requests (no PageLayout, returns HTML fragment only)
$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : '';
$commonRepository = new Repositories\TeamIdentityRepository($mysqli_db);

if ($op === 'api') {
    $handler = new LeagueStarters\LeagueStartersApiHandler($mysqli_db, $commonRepository, $authService);
    $handler->handle();
    return;
}
$season = new \Season\Season($mysqli_db);

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$pagetitle = "- $module_name";

$league = new \League\League($mysqli_db);

// Initialize services
$service = new LeagueStartersService($mysqli_db, $league);
$view = new LeagueStartersView($module_name);

// Get starters by position
$startersByPosition = $service->getAllStartersByPosition();
$display = 'ratings';
if (isset($_REQUEST['display']) && is_string($_REQUEST['display'])
    && in_array($_REQUEST['display'], ['ratings', 'total_s', 'avg_s', 'per36mins'], true)) {
    $display = $_REQUEST['display'];
}

PageLayout\PageLayout::header();

$username = $authService->getUsername() ?? '';
// getTeamnameFromUsername() returns null for a logged-in user with no `ibl_team_info`
// row (a registered non-GM). Team::initialize() takes int|string|array, so null is a
// TypeError under strict_types. Fall back to the same value a logged-out visitor
// already gets, keeping this read-only page rendering for everyone.
$userTeamName = $commonRepository->getTeamnameFromUsername($username) ?? \League\League::FREE_AGENTS_TEAM_NAME;
$userTeam = \Team\Team::initialize($mysqli_db, $userTeamName);

echo $view->render($mysqli_db, $season, $startersByPosition, $userTeam, $display);

PageLayout\PageLayout::footer();