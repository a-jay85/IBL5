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
    'sortby' => $_POST['sortby'] ?? '1'
];

// Render page
Nuke\Header::header();
OpenTable();

// Get data for dropdowns
$teams = $repository->getTeams();
$years = $repository->getYears();

// Render filter form
echo $view->renderFilterForm($teams, $years, $filters);

// Get and render season leaders
$leadersData = $repository->getSeasonLeaders($filters);
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

CloseTable();
Nuke\Footer::footer();
