<?php

declare(strict_types=1);

/**
 * Team_Schedule Module - Display team game schedule
 *
 * Shows a team's complete schedule with game results, records, and streaks.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see TeamSchedule\TeamScheduleService For business logic
 * @see TeamSchedule\TeamScheduleView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use TeamSchedule\TeamScheduleService;
use TeamSchedule\TeamScheduleView;

global $db, $cookie, $mysqli_db;

$commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
$season = new Season($mysqli_db);

// Get team ID from request or user's team
$userTeamID = isset($_GET['teamID']) ? (int)$_GET['teamID'] : 0;
if (!$userTeamID) {
    if (!empty($cookie[1])) {
        $userTeamName = $commonRepository->getTeamnameFromUsername(strval($cookie[1] ?? ''));
        $userTeamID = $commonRepository->getTidFromTeamname($userTeamName);
    } else {
        $userTeamID = 0;
    }
}

$userTeam = Team::initialize($mysqli_db, $userTeamID);
$league = new League($mysqli_db);

// Initialize services
$service = new TeamScheduleService($mysqli_db);
$view = new TeamScheduleView();

// Get processed schedule data
$games = $service->getProcessedSchedule($userTeamID, $season);

// Render page
Nuke\Header::header();
OpenTable();
UI::displaytopmenu($mysqli_db, $userTeamID);

echo $view->render($userTeam, $games, $league->getSimLengthInDays());

CloseTable();
Nuke\Footer::footer();