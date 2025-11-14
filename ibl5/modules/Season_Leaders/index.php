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
$repository = new SeasonLeadersRepository($db);
$service = new SeasonLeadersService();
$view = new SeasonLeadersView($service);

// Get filter parameters from POST
$filters = [
    'year' => $_POST['year'] ?? '',
    'team' => $_POST['team'] ?? 0,
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
$result = $leadersData['result'];
$numRows = $leadersData['count'];

// Render table header
echo $view->renderTableHeader();

// Render player rows
$rank = 0;
for ($i = 0; $i < $numRows; $i++) {
    $row = [
        'pid' => $db->sql_result($result, $i, "pid"),
        'name' => $db->sql_result($result, $i, "name"),
        'year' => $db->sql_result($result, $i, "year"),
        'team' => $db->sql_result($result, $i, "team"),
        'teamid' => $db->sql_result($result, $i, "teamid"),
        'games' => $db->sql_result($result, $i, "games"),
        'minutes' => $db->sql_result($result, $i, "min"),
        'fgm' => $db->sql_result($result, $i, "fgm"),
        'fga' => $db->sql_result($result, $i, "fga"),
        'ftm' => $db->sql_result($result, $i, "ftm"),
        'fta' => $db->sql_result($result, $i, "fta"),
        'tgm' => $db->sql_result($result, $i, "tgm"),
        'tga' => $db->sql_result($result, $i, "tga"),
        'orb' => $db->sql_result($result, $i, "orb"),
        'reb' => $db->sql_result($result, $i, "reb"),
        'ast' => $db->sql_result($result, $i, "ast"),
        'stl' => $db->sql_result($result, $i, "stl"),
        'tvr' => $db->sql_result($result, $i, "tvr"),
        'blk' => $db->sql_result($result, $i, "blk"),
        'pf' => $db->sql_result($result, $i, "pf")
    ];
    
    $stats = $service->processPlayerRow($row);
    $rank++;
    echo $view->renderPlayerRow($stats, $rank);
}

// Render table footer
echo $view->renderTableFooter();

CloseTable();
Nuke\Footer::footer();
