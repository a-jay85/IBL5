<?php

declare(strict_types=1);

/**
 * Leaderboards Module - Season and career stat leaders on one page
 *
 * tab=season (the default) shows the single-season leaderboard and
 * tab=career shows the career leaderboard. The retired SeasonLeaderboards
 * and CareerLeaderboards module names 302 here via sendWithPassthrough.
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

    // Read filter parameters from POST (allowlisted by the service)
    $postString = static fn (string $key, string $default): string => is_string($_POST[$key] ?? null) ? $_POST[$key] : $default;
    $submitted = isset($_POST['submitted']);

    $phase = $service->resolvePhase($postString('phase', 'regular'));
    $mode = $service->resolveMode($phase, $postString('mode', 'totals'));
    $sortKey = $service->resolveSortKey($postString('sortby', 'PPG'), $mode);
    // An unchecked switch posts nothing, so `submitted` tells "switch off" from first load (ON).
    $retirees = $submitted ? isset($_POST['retirees']) : true;
    $display = $postString('display', '');
    $limit = is_numeric($display) && (int) $display > 0 ? (int) $display : 50;

    // Render filter form
    echo $view->renderFilterForm([
        'phase' => $phase,
        'mode' => $mode,
        'sortby' => $sortKey,
        'retirees' => $retirees,
        'display' => $display,
    ]);

    // Run the default search on first load, like the Season tab
    $tableKey = $service->resolveTableKey($phase, $mode);
    $sortColumn = $service->resolveSortColumn($sortKey, $mode);
    $tableType = $repository->getTableType($tableKey);
    $leadersData = $repository->getLeaderboards($tableKey, $sortColumn, $retirees ? 0 : 1, $limit);

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

$rawBody = ob_get_clean();
$tabBody = $rawBody === false ? '' : $rawBody;

echo '<h1 class="ibl-title">Leaderboards</h1>'
    . $tabs->renderTabBar($tab, 'modules.php?name=Leaderboards') . $tabs->wrapPanel($tabBody, $tab);

PageLayout\PageLayout::footer();
