<?php

declare(strict_types=1);

/**
 * SeasonArchive Module - Display historical season data
 *
 * Shows an index of all IBL seasons (1989-2006) with links to detailed
 * season pages containing awards, playoff brackets, standings, and rosters.
 *
 * @see SeasonArchive\SeasonArchiveRepository For database operations
 * @see SeasonArchive\SeasonArchiveService For business logic
 * @see SeasonArchive\SeasonArchiveView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use SeasonArchive\SeasonArchiveRepository;
use SeasonArchive\SeasonArchiveService;
use SeasonArchive\SeasonArchiveView;

global $mysqli_db;

Nuke\Header::header();

$repository = new SeasonArchiveRepository($mysqli_db);
$service = new SeasonArchiveService($repository);
$view = new SeasonArchiveView();

$year = isset($_GET['year']) ? (int) $_GET['year'] : 0;

if ($year > 0) {
    $seasonData = $service->getSeasonDetail($year);
    if ($seasonData !== null) {
        echo $view->renderSeasonDetail($seasonData);
    } else {
        $seasons = $service->getAllSeasons();
        $teamColors = $repository->getTeamColors();
        $teamIds = [];
        foreach ($teamColors as $name => $data) {
            $teamIds[$name] = $data['teamid'];
        }
        $mvpNames = array_values(array_filter(array_column($seasons, 'mvp'), static fn(string $n): bool => $n !== ''));
        $playerIds = $repository->getPlayerIdsByNames($mvpNames);
        echo $view->renderIndex($seasons, $teamColors, $playerIds, $teamIds);
    }
} else {
    $seasons = $service->getAllSeasons();
    $teamColors = $repository->getTeamColors();
    $teamIds = [];
    foreach ($teamColors as $name => $data) {
        $teamIds[$name] = $data['teamid'];
    }
    $mvpNames = array_values(array_filter(array_column($seasons, 'mvp'), static fn(string $n): bool => $n !== ''));
    $playerIds = $repository->getPlayerIdsByNames($mvpNames);
    echo $view->renderIndex($seasons, $teamColors, $playerIds, $teamIds);
}

Nuke\Footer::footer();
