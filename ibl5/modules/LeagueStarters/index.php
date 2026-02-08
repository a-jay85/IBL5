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

$commonRepository = new Services\CommonMysqliRepository($mysqli_db);
$season = new Season($mysqli_db);

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$pagetitle = "- $module_name";

$username = strval($cookie[1] ?? '');
$userTeamName = $commonRepository->getTeamnameFromUsername($username);
$userTeam = Team::initialize($mysqli_db, $userTeamName);
$league = new League($mysqli_db);

// Initialize services
$service = new LeagueStartersService($mysqli_db, $league);
$view = new LeagueStartersView($mysqli_db, $season, $module_name);

// Get starters by position
$startersByPosition = $service->getAllStartersByPosition();
$display = $_REQUEST['display'] ?? 'ratings';

// Render page
Nuke\Header::header();

echo $view->render($startersByPosition, $userTeam, $display);

Nuke\Footer::footer();