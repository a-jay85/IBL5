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
 * Player Awards Module
 * 
 * Provides search functionality for player award history.
 * Security: All inputs are validated and queries use prepared statements.
 * 
 * Refactored January 2026 to use Repository/Service/View pattern.
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- $module_name";

global $mysqli_db;

// Initialize classes
$validator = new \PlayerAwards\PlayerAwardsValidator();
$repository = new \PlayerAwards\PlayerAwardsRepository($mysqli_db);
$service = new \PlayerAwards\PlayerAwardsService($validator, $repository);
$view = new \PlayerAwards\PlayerAwardsView($service);

// Get and validate search parameters from POST
$searchResult = $service->search($_POST);

// Render page
Nuke\Header::header();
OpenTable();
UI::playerMenu();

// Render search form with current parameters
echo $view->renderSearchForm($searchResult['params']);

// Render results table
echo $view->renderTableHeader();

if ($searchResult['count'] > 0) {
    $rowIndex = 0;
    foreach ($searchResult['awards'] as $award) {
        echo $view->renderAwardRow($award, $rowIndex);
        $rowIndex++;
    }
}

echo $view->renderTableFooter();

CloseTable();
Nuke\Footer::footer();
