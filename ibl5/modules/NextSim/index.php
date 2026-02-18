<?php

declare(strict_types=1);

/**
 * Next_Sim Module - Display upcoming simulation games
 *
 * Shows the user's upcoming games with matchup information.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see NextSim\NextSimService For business logic
 * @see NextSim\NextSimView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use NextSim\NextSimService;
use NextSim\NextSimView;
use Standings\StandingsRepository;
use TeamSchedule\TeamScheduleRepository;

global $db, $cookie, $mysqli_db;

$commonRepository = new Services\CommonMysqliRepository($mysqli_db);
$season = new Season($mysqli_db);

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$pagetitle = "- $module_name";

// Load power rankings for SOS tier indicators
$standingsRepo = new StandingsRepository($mysqli_db);
$allStreakData = $standingsRepo->getAllStreakData();
/** @var array<int, float> $teamPowerRankings */
$teamPowerRankings = [];
foreach ($allStreakData as $tid => $data) {
    $teamPowerRankings[$tid] = (float)$data['ranking'];
}

// Render header first (populates $cookie via online() → cookiedecode())
Nuke\Header::header();

$username = strval($cookie[1] ?? '');
$userTeamName = $commonRepository->getTeamnameFromUsername($username);
$userTeam = Team::initialize($mysqli_db, $userTeamName);
$league = new League($mysqli_db);

// Initialize services
$teamScheduleRepository = new TeamScheduleRepository($mysqli_db);
$service = new NextSimService($mysqli_db, $teamScheduleRepository, $teamPowerRankings);
$view = new NextSimView($mysqli_db, $season, $module_name);

// Get next sim games
$games = $service->getNextSimGames($userTeam->teamID, $season);

// Add user starters to each game for comparison
$userStarters = $service->getUserStartingLineup($userTeam);
foreach ($games as $index => $game) {
    $games[$index]['userStartingPG'] = $userStarters['PG'];
    $games[$index]['userStartingSG'] = $userStarters['SG'];
    $games[$index]['userStartingSF'] = $userStarters['SF'];
    $games[$index]['userStartingPF'] = $userStarters['PF'];
    $games[$index]['userStartingC'] = $userStarters['C'];
    $games[$index]['opposingStartingPG'] = $game['opposingStarters']['PG'];
    $games[$index]['opposingStartingSG'] = $game['opposingStarters']['SG'];
    $games[$index]['opposingStartingSF'] = $game['opposingStarters']['SF'];
    $games[$index]['opposingStartingPF'] = $game['opposingStarters']['PF'];
    $games[$index]['opposingStartingC'] = $game['opposingStarters']['C'];
}

echo $view->render($games, $league->getSimLengthInDays());

Nuke\Footer::footer();
