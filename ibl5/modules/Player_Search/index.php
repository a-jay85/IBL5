<?php

declare(strict_types=1);

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2007 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

/**
 * Player Search Module
 * 
 * Provides advanced player search functionality with multiple filter criteria.
 * Security: All inputs are validated and queries use prepared statements.
 * 
 * Refactored November 2025 to use Repository/Service/View pattern.
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- $module_name";

global $mysqli_db;

// Initialize classes
$validator = new \PlayerSearch\PlayerSearchValidator();
$repository = new \PlayerSearch\PlayerSearchRepository($mysqli_db);
$playerRepository = new \Player\PlayerRepository($mysqli_db);
$service = new \PlayerSearch\PlayerSearchService($validator, $repository, $playerRepository);
$view = new \PlayerSearch\PlayerSearchView($service);

// Get and validate search parameters from POST
$searchResult = $service->search($_POST);

// Render page
Nuke\Header::header();
OpenTable();
UI::playerMenu();

// Render search form with current parameters
echo $view->renderSearchForm($searchResult['params']);

// Render results if form was submitted
if (!empty($_POST)) {
    echo $view->renderTableHeader();
    
    if ($searchResult['count'] > 0) {
        $rowIndex = 0;
        foreach ($searchResult['players'] as $player) {
            echo $view->renderPlayerRow($player, $rowIndex);
            $rowIndex++;
        }
    }
    
    echo $view->renderTableFooter();
}

CloseTable();
Nuke\Footer::footer();