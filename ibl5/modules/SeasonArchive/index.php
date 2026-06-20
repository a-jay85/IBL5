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
 * @see SeasonArchive\SeasonArchiveIndexView For index HTML rendering
 * @see SeasonArchive\SeasonDetailView For season-detail HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use SeasonArchive\SeasonArchiveIndexView;
use SeasonArchive\SeasonArchiveRepository;
use SeasonArchive\SeasonArchiveService;
use SeasonArchive\SeasonDetailView;

global $mysqli_db, $leagueContext;

PageLayout\PageLayout::header();

$repository = new SeasonArchiveRepository($mysqli_db, $leagueContext);
$service = new SeasonArchiveService($repository);
$indexView = new SeasonArchiveIndexView();
$detailView = new SeasonDetailView();

$year = isset($_GET['year']) ? (int) $_GET['year'] : 0;

if ($year > 0) {
    $seasonData = $service->getSeasonDetail($year);
    if ($seasonData !== null) {
        echo $detailView->renderSeasonDetail($seasonData);
    } else {
        $seasons = $service->getAllSeasons();
        $teamColors = $repository->getTeamColors();
        $teamIds = [];
        foreach ($teamColors as $name => $data) {
            $teamIds[$name] = $data['teamid'];
        }
        $mvpNames = array_values(array_filter(array_column($seasons, 'mvp'), static fn(string $n): bool => $n !== ''));
        $playerIds = $repository->getPlayerIdsByNames($mvpNames);
        echo $indexView->renderIndex($seasons, $teamColors, $playerIds, $teamIds);
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
    echo $indexView->renderIndex($seasons, $teamColors, $playerIds, $teamIds);
}

PageLayout\PageLayout::footer();
