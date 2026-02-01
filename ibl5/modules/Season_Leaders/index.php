<?php

use SeasonLeaders\SeasonLeadersRepository;
use SeasonLeaders\SeasonLeadersService;
use SeasonLeaders\SeasonLeadersView;

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "Season Stats";

// Initialize classes
$repository = new SeasonLeadersRepository($mysqli_db);
$service = new SeasonLeadersService();
$view = new SeasonLeadersView($service);

// Get filter parameters from POST
$filters = [
    'year' => $_POST['year'] ?? '',
    'team' => (int)($_POST['team'] ?? 0),
    'sortby' => $_POST['sortby'] ?? '1',
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

// Render page
Nuke\Header::header();

echo '<h2 class="ibl-title">Season Leaders</h2>';

// Get data for dropdowns
$teams = $repository->getTeams();
$years = $repository->getYears();

// Render filter form
echo $view->renderFilterForm($teams, $years, $filters);

// Get and render season leaders
$leadersData = $repository->getSeasonLeaders($filters, $limit);
$rows = $leadersData['result'];
$numRows = $leadersData['count'];

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

Nuke\Footer::footer();
