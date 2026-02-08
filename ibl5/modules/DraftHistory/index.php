<?php

declare(strict_types=1);

/**
 * Draft_History Module - Display draft history by year or by team
 *
 * Shows draft picks for a selected year with player info and draft order.
 * Supports team-specific view via ?teamID=N parameter.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see DraftHistory\DraftHistoryRepository For database operations
 * @see DraftHistory\DraftHistoryView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use DraftHistory\DraftHistoryRepository;
use DraftHistory\DraftHistoryView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

global $mysqli_db;

// Initialize services
$repository = new DraftHistoryRepository($mysqli_db);
$view = new DraftHistoryView();

// Check for team ID parameter
$teamID = isset($_GET['teamID']) ? (int) $_GET['teamID'] : 0;

$isValidTeam = false;
if ($teamID > 0) {
    $team = \Team::initialize($mysqli_db, $teamID);
    $isValidTeam = ($team->teamID > 0);
}

// Set page title before header
if ($isValidTeam) {
    $pagetitle = "- {$team->name} Draft History";
} else {
    // Get year range
    $startYear = $repository->getFirstDraftYear();
    $endYear = $repository->getLastDraftYear();

    // Get selected year from request, default to most recent draft
    $year = isset($_REQUEST['year']) ? (int) $_REQUEST['year'] : $endYear;
    $pagetitle = "- $year Draft";
}

// Render page
Nuke\Header::header();

if ($isValidTeam) {
    echo $view->renderTeamHistory($team, $repository->getDraftPicksByTeam($team->name));
} else {
    $draftPicks = $repository->getDraftPicksByYear($year);
    echo $view->render($year, $startYear, $endYear, $draftPicks);
}

Nuke\Footer::footer();
