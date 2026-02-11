<?php

declare(strict_types=1);

/**
 * Schedule Module - Display league or team game schedule
 *
 * Shows the full league schedule, or a specific team's schedule if teamID is provided.
 * Team schedules include team colors, logo banner, and win/loss tracking.
 *
 * @see TeamSchedule\TeamScheduleService For team-specific business logic
 * @see TeamSchedule\TeamScheduleView For team-specific HTML rendering
 * @see LeagueSchedule\LeagueScheduleService For league-wide business logic
 * @see LeagueSchedule\LeagueScheduleView For league-wide HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use LeagueSchedule\LeagueScheduleRepository;
use LeagueSchedule\LeagueScheduleService;
use LeagueSchedule\LeagueScheduleView;
use TeamSchedule\TeamScheduleRepository;
use TeamSchedule\TeamScheduleService;
use TeamSchedule\TeamScheduleView;

global $cookie, $mysqli_db;

$commonRepository = new Services\CommonMysqliRepository($mysqli_db);
$season = new Season($mysqli_db);
$league = new League($mysqli_db);

// Check for team ID parameter
$teamID = isset($_GET['teamID']) ? (int)$_GET['teamID'] : 0;

// Validate team ID exists (if provided)
$isValidTeam = false;
if ($teamID > 0) {
    $team = Team::initialize($mysqli_db, $teamID);
    $isValidTeam = ($team->teamID > 0);
}

Nuke\Header::header();

if ($isValidTeam) {
    // Team-specific schedule with colors, logo, and win/loss tracking
    $teamScheduleRepository = new TeamScheduleRepository($mysqli_db);
    $service = new TeamScheduleService($mysqli_db, $teamScheduleRepository);
    $view = new TeamScheduleView();
    $games = $service->getProcessedSchedule($teamID, $season);
    echo $view->render($team, $games, $league->getSimLengthInDays(), $season->phase);
} else {
    // League-wide schedule
    $repository = new LeagueScheduleRepository($mysqli_db);
    $service = new LeagueScheduleService($repository);
    $view = new LeagueScheduleView();
    $pageData = $service->getSchedulePageData($season, $league, $commonRepository);
    echo $view->render($pageData);
}

Nuke\Footer::footer();
