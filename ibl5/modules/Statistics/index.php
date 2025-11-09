<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2007 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* Based on NukeStats Module Version 1.0                                */
/* Copyright ©2002 by Harry Mangindaan (sens@indosat.net) and           */
/*                    Sudirman (sudirman@akademika.net)                 */
/* http://www.nuketest.com                                              */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$pagetitle = "- " . _STATS;
$ThemeSel = get_theme();

// Initialize controller
$controller = new Statistics\StatisticsController($db, $module_name, $ThemeSel);

// Handle routing based on operation
$op = $_GET['op'] ?? '';
$year = isset($_GET['year']) ? intval($_GET['year']) : 0;
$month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$date = isset($_GET['date']) ? intval($_GET['date']) : 0;

switch ($op) {
    case "Stats":
        $controller->showDetailedStats();
        break;

    case "YearlyStats":
        $controller->showYearlyStats($year);
        break;

    case "MonthlyStats":
        $controller->showMonthlyStats($year, $month);
        break;

    case "DailyStats":
        $controller->showDailyStats($year, $month, $date);
        break;

    default:
        $controller->showMainStats();
        break;
}
