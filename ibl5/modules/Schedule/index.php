<?php

declare(strict_types=1);

/**
 * Schedule Module - Display league or team game schedule
 *
 * Shows the full league schedule, or a specific team's schedule if teamid is provided.
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

use Repositories\TeamIdentityRepository;
use Schedule\ScheduleController;

global $mysqli_db, $leagueContext;

$teamid = isset($_GET['teamid']) ? (int) $_GET['teamid'] : 0;
$controller = new ScheduleController($mysqli_db, $leagueContext, new TeamIdentityRepository($mysqli_db));

PageLayout\PageLayout::header();
echo $controller->render($teamid);
PageLayout\PageLayout::footer();
