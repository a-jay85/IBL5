<?php

declare(strict_types=1);

/**
 * Leaderboards Module - Season and career stat leaders on one page
 *
 * tab=season (the default) shows the single-season leaderboard and
 * tab=career shows the career leaderboard. The retired SeasonLeaderboards
 * and CareerLeaderboards module names 302 here via Module\ModuleRedirect::TARGETS.
 *
 * @see Leaderboards\LeaderboardsTabs For tab resolution and the tab bar
 * @see SeasonLeaderboards\SeasonLeaderboardsView For the season board
 * @see CareerLeaderboards\CareerLeaderboardsView For the career board
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use Leaderboards\LeaderboardsTabs;
use SeasonLeaderboards\CachedSeasonLeaderboardsRepository;
use SeasonLeaderboards\SeasonLeaderboardsRepository;
use SeasonLeaderboards\SeasonLeaderboardsService;
use SeasonLeaderboards\SeasonLeaderboardsView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$tab = LeaderboardsTabs::resolve($_GET['tab'] ?? null);

$pagetitle = $tab === LeaderboardsTabs::CAREER ? '- Player Archives' : 'Season Stats';

global $mysqli_db, $leagueContext;

PageLayout\PageLayout::header();

echo LeaderboardsTabs::render($tab);

if ($tab === LeaderboardsTabs::CAREER) {
    // Initialize classes
    $dbCache = new \Cache\DatabaseCache($mysqli_db);
    $innerRepository = new \CareerLeaderboards\CareerLeaderboardsRepository($mysqli_db);
    $repository = new \CareerLeaderboards\CachedCareerLeaderboardsRepository($innerRepository, $dbCache);
    $service = new CareerLeaderboards\CareerLeaderboardsService();
    $view = new \CareerLeaderboards\CareerLeaderboardsView($service);

    // Get filter parameters from POST
    $filters = [
        'boards_type' => $_POST['boards_type'] ?? '',
        'sort_cat' => $_POST['sort_cat'] ?? '',
        'active' => $_POST['active'] ?? '0',
        'display' => $_POST['display'] ?? '',
        'submitted' => $_POST['submitted'] ?? null
    ];

    echo '<h1 class="ibl-title">Career Leaderboards</h1>';

    // Render filter form
    echo $view->renderFilterForm($filters);

    // Run query if form has been submitted
    if ($filters['submitted'] != null) {
        // Map display name to table key
        $boardTypes = $service->getBoardTypes();
        $tableKey = array_search($filters['boards_type'], $boardTypes);

        // Map display name to sort column
        $sortCategories = $service->getSortCategories();
        $sortColumn = array_search($filters['sort_cat'], $sortCategories);

        if ($tableKey !== false && $sortColumn !== false) {
            // Get table type (totals or averages)
            $tableType = $repository->getTableType($tableKey);

            // Get leaderboard data
            $activeOnly = (int)$filters['active'];
            $limit = is_numeric($filters['display']) && $filters['display'] > 0 ? (int)$filters['display'] : 0;
            $leadersData = $repository->getLeaderboards($tableKey, $sortColumn, $activeOnly, $limit);

            // Set active sort column for highlighting
            $view->setSortColumn($sortColumn);

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
    }
} else {
    // Initialize classes
    $dbCache = new \Cache\DatabaseCache($mysqli_db);
    $innerRepository = new SeasonLeaderboardsRepository($mysqli_db, $leagueContext);
    $repository = new CachedSeasonLeaderboardsRepository($innerRepository, $dbCache);
    $service = new SeasonLeaderboardsService($repository);
    $view = new SeasonLeaderboardsView($service);

    // Get filter parameters from POST
    $filters = [
        'year' => $_POST['year'] ?? '',
        'team' => (int)($_POST['team'] ?? 0),
        'sortby' => $_POST['sortby'] ?? 'PPG',
        'limit' => $_POST['limit'] ?? ''
    ];

    // Determine limit: use POST value if provided, otherwise default to 50 on first load
    $isFirstLoad = empty($_POST);
    $limit = 0;
    if ($isFirstLoad) {
        $limit = 50; // Default limit on first load
    } elseif (is_numeric($filters['limit']) && (int)$filters['limit'] > 0) {
        $limit = (int)$filters['limit'];
    }

    echo '<h1 class="ibl-title">Season Leaders</h1>';

    // Get data for dropdowns
    $teams = $repository->getTeams();
    $years = $repository->getYears();

    // Render filter form
    echo $view->renderFilterForm($teams, $years, $filters);

    // Get and render season leaders
    $leadersData = $service->getFilteredLeaderboard($filters, $limit);
    $rows = $leadersData['results'];
    $numRows = $leadersData['count'];

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

PageLayout\PageLayout::footer();
