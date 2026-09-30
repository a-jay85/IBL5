<?php

declare(strict_types=1);

/**
 * Leaderboards Module - Season and career stat leaders on one page
 *
 * tab=season (the default) shows the single-season leaderboard and
 * tab=career shows the career leaderboard. The retired SeasonLeaderboards
 * and CareerLeaderboards module names 302 here via ModuleRedirect::send().
 *
 * @see SeasonLeaderboards\SeasonLeaderboardsView For the season board
 * @see CareerLeaderboards\CareerLeaderboardsView For the career board
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use SeasonLeaderboards\CachedSeasonLeaderboardsRepository;
use SeasonLeaderboards\SeasonLeaderboardsRepository;
use SeasonLeaderboards\SeasonLeaderboardsService;
use SeasonLeaderboards\SeasonLeaderboardsView;

$tabs = new \UI\Components\PageTabs(['season' => 'Season', 'career' => 'Career'], 'season');
$tab = $tabs->resolve($_GET['tab'] ?? null);

$pagetitle = '- Leaderboards: ' . ($tab === 'career' ? 'Career' : 'Season');

global $mysqli_db, $leagueContext;

PageLayout\PageLayout::header();

ob_start();

if ($tab === 'career') {
    // Initialize classes
    $dbCache = new \Cache\DatabaseCache($mysqli_db);
    $innerRepository = new \CareerLeaderboards\CareerLeaderboardsRepository($mysqli_db);
    $repository = new \CareerLeaderboards\CachedCareerLeaderboardsRepository($innerRepository, $dbCache);
    $service = new CareerLeaderboards\CareerLeaderboardsService();
    $view = new \CareerLeaderboards\CareerLeaderboardsView($service);

    // Read filter parameters from the query string (allowlisted by the service)
    $getString = static fn (string $key, string $default): string => is_string($_GET[$key] ?? null) ? $_GET[$key] : $default;
    $submitted = isset($_GET['submitted']);

    $phase = $service->resolvePhase($getString('phase', 'regular'));
    $mode = $service->resolveMode($phase, $getString('mode', 'totals'));
    $sortKey = $service->resolveSortKey($getString('sortby', 'PPG'), $mode);
    $showsGames = $service->phaseShowsGames($phase);
    // One-game phases hide the games column, so a games sort falls back to points
    if ($sortKey === 'GAMES' && !$showsGames) {
        $sortKey = 'PPG';
    }
    // An unchecked switch sends nothing, so `submitted` tells "switch off" from first load (ON).
    $retirees = $submitted ? isset($_GET['retirees']) : true;
    $display = $getString('display', '');
    $limit = is_numeric($display) && (int) $display > 0 ? (int) $display : 50;

    // Render filter form
    echo $view->renderFilterForm([
        'phase' => $phase,
        'mode' => $mode,
        'sortby' => $sortKey,
        'retirees' => $retirees,
        'display' => $display,
    ]);

    // First visit shows only the form; a search runs once the form is submitted
    if ($submitted) {
        $tableKey = $service->resolveTableKey($phase, $mode);
        $sortColumn = $service->resolveSortColumn($sortKey, $mode);
        $tableType = $repository->getTableType($tableKey);
        $leadersData = $repository->getLeaderboards($tableKey, $sortColumn, $retirees ? 0 : 1, $limit);

        // Set active sort column for highlighting
        $view->setSortColumn($sortColumn);
        $view->setShowGames($showsGames);

        // Render table header
        echo $view->renderTableHeader();

        // Render player rows
        $rank = 1;
        foreach ($leadersData['results'] as $row) {
            $stats = $service->processPlayerRow($row, $tableType);
            echo $view->renderPlayerRow($stats, $rank);
            $rank++;
        }

        // Render table footer
        echo $view->renderTableFooter();
    }
} else {
    // Initialize classes
    $dbCache = new \Cache\DatabaseCache($mysqli_db);
    $innerRepository = new SeasonLeaderboardsRepository($mysqli_db, $leagueContext);
    $repository = new CachedSeasonLeaderboardsRepository($innerRepository, $dbCache);
    $service = new SeasonLeaderboardsService($repository);
    $view = new SeasonLeaderboardsView($service);

    // Read filter parameters from the query string
    $getString = static fn (string $key): string => is_string($_GET[$key] ?? null) ? $_GET[$key] : '';
    $filters = [
        'year' => $getString('year'),
        'team' => (int) $getString('team'),
        'sortby' => $getString('sortby') !== '' ? $getString('sortby') : 'PPG',
        'limit' => $getString('limit'),
    ];
    $limit = is_numeric($filters['limit']) && (int) $filters['limit'] > 0 ? (int) $filters['limit'] : 50;

    // Get data for dropdowns
    $teams = $repository->getTeams();
    $years = $repository->getYears();

    // Render filter form
    echo $view->renderFilterForm($teams, $years, $filters);

    // First visit shows only the form; a search runs once the form is submitted
    if (isset($_GET['submitted'])) {
        // Get and render season leaders
        $leadersData = $service->getFilteredLeaderboard($filters, $limit);
        $rows = $leadersData['results'];

        // Set active sort column for highlighting
        $view->setSortBy($filters['sortby']);

        // Render table header
        echo $view->renderTableHeader();

        // Render player rows
        $rank = 0;
        foreach ($rows as $row) {
            $stats = $service->processPlayerRow($row);
            $rank++;
            echo $view->renderPlayerRow($stats, $rank);
        }

        // Render table footer
        echo $view->renderTableFooter();
    }
}

$rawBody = ob_get_clean();
$tabBody = $rawBody === false ? '' : $rawBody;

echo '<div class="leaderboards-page"><h1 class="ibl-title">Leaderboards</h1>'
    . $tabs->renderTabBar($tab, 'modules.php?name=Leaderboards') . $tabs->wrapPanel($tabBody, $tab) . '</div>';

PageLayout\PageLayout::footer();
