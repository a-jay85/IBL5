<?php

declare(strict_types=1);

/**
 * Injuries Module - Display all injured players
 *
 * Shows a table of all currently injured players with their position,
 * team, and days remaining for injury.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see Injuries\InjuriesService For business logic
 * @see Injuries\InjuriesView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use Injuries\InjuriesService;
use Injuries\InjuriesView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Injured Players";

$teamID = isset($teamID) ? (int) $teamID : 0;

global $mysqli_db;

// Initialize services
$service = new InjuriesService($mysqli_db);
$view = new InjuriesView();

// Get injured players data
$injuredPlayers = $service->getInjuredPlayersWithTeams();

// Render page
Nuke\Header::header();
OpenTable();

UI::displaytopmenu($mysqli_db, $teamID);

echo $view->render($injuredPlayers);

CloseTable();
Nuke\Footer::footer();
