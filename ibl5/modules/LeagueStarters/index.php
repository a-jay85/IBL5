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

global $authService;

// Route HTMX API requests (no PageLayout, returns HTML fragment only)
$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : '';
$factory = \Module\ModuleServices::current()->factory(\Module\Factories\LeagueStartersFactory::class);
$commonRepository = $factory->teamIdentity();

if ($op === 'api') {
    $handler = $factory->apiHandler();
    $handler->handle();
    return;
}
$season = $factory->season();

$module_name = basename(dirname(__FILE__));
$pagetitle = "- $module_name";

// Initialize services
$service = $factory->service();
$view = $factory->view($module_name);

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
$userTeam = \Team\Team::initialize($factory->db(), $userTeamName);

echo $view->render($factory->db(), $season, $startersByPosition, $userTeam, $display);

PageLayout\PageLayout::footer();