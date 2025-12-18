<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Player Archives";

global $mysqli_db;

// Initialize classes
$repository = new \Leaderboards\LeaderboardsRepository($mysqli_db);
$service = new Leaderboards\LeaderboardsService();
$view = new \Leaderboards\LeaderboardsView($service);

// Get filter parameters from POST
$filters = [
    'boards_type' => $_POST['boards_type'] ?? '',
    'sort_cat' => $_POST['sort_cat'] ?? '',
    'active' => $_POST['active'] ?? '0',
    'display' => $_POST['display'] ?? '',
    'submitted' => $_POST['submitted'] ?? null
];

// Render page
Nuke\Header::header();
OpenTable();
UI::playerMenu();

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
        
        // Render table header
        echo $view->renderTableHeader();
        
        // Render player rows
        $rank = 1;
        foreach ($leadersData['result'] as $row) {
            $stats = $service->processPlayerRow($row, $tableType);
            echo $view->renderPlayerRow($stats, $rank);
            $rank++;
        }
        
        // Render table footer
        echo $view->renderTableFooter();
    }
}

CloseTable();
Nuke\Footer::footer();