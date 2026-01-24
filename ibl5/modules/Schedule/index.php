<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2005 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
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

$pagetitle = "- $module_name";

Nuke\Header::header();
OpenTable();

global $mysqli_db;
$season = new Season($mysqli_db);
$league = new League($mysqli_db);

// Find current week for "jump to" functionality
$lastSimEndDate = $season->lastSimEndDate;
$simLengthDays = $league->getSimLengthInDays();

$min_date_query = "SELECT MIN(Date) as mindate FROM ibl_schedule";
$min_date_result = $db->sql_query($min_date_query);
$row = $db->sql_fetch_assoc($min_date_result);
$min_date = $row['mindate'];

$max_date_query = "SELECT MAX(Date) as maxdate FROM ibl_schedule";
$max_date_result = $db->sql_query($max_date_query);
$row2 = $db->sql_fetch_assoc($max_date_result);
$max_date = $row2['maxdate'];
$max_date = fnc_date_calc($max_date, 0);

// Calculate which week number contains the last sim date
$currentWeek = 1;
$chunk_start = $min_date;
$weekNum = 0;
while ($chunk_start < $max_date) {
    $weekNum++;
    if (strtotime($lastSimEndDate) >= strtotime($chunk_start) &&
        strtotime($lastSimEndDate) <= strtotime(fnc_date_calc($chunk_start, 6))) {
        $currentWeek = $weekNum;
    }
    if ($weekNum == 13) {
        $chunk_start = fnc_date_calc($chunk_start, 11);
    } else {
        $chunk_start = fnc_date_calc($chunk_start, 7);
    }
}

// Output schedule container
echo '<div class="schedule-container">';

// Header with jump button
echo '<div class="schedule-header">';
echo '<h1 class="schedule-title">Schedule</h1>';
echo '<a href="#week-' . $currentWeek . '" class="schedule-jump-btn" onclick="smoothScrollToWeek(event, ' . $currentWeek . ')">';
echo '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12l7 7 7-7"/></svg>';
echo 'Current Week';
echo '</a>';
echo '</div>';

$chunk_start_date = $min_date;
$chunk_end_date = fnc_date_calc($min_date, 6);

$i = 0;
while ($chunk_start_date < $max_date) {
    $i++;
    chunk($chunk_start_date, $chunk_end_date, $i, $currentWeek, $lastSimEndDate, $simLengthDays);
    if ($i == 13) {
        $chunk_start_date = fnc_date_calc($chunk_start_date, 11);
        $chunk_end_date = fnc_date_calc($chunk_start_date, 6);
    } else {
        $chunk_start_date = fnc_date_calc($chunk_start_date, 7);
        $chunk_end_date = fnc_date_calc($chunk_start_date, 6);
    }
}

echo '</div>'; // Close schedule-container

// Smooth scroll script
echo '<script>
function smoothScrollToWeek(e, weekNum) {
    e.preventDefault();
    var el = document.getElementById("week-" + weekNum);
    if (el) {
        el.scrollIntoView({ behavior: "smooth", block: "start" });
    }
}
</script>';

CloseTable();
Nuke\Footer::footer();

function chunk($chunk_start_date, $chunk_end_date, $j, $currentWeek, $lastSimEndDate, $simLengthDays)
{
    global $db, $mysqli_db;
    $commonRepository = new Services\CommonMysqliRepository($mysqli_db);

    $query = "SELECT *
		FROM ibl_schedule
		WHERE Date BETWEEN '$chunk_start_date' AND '$chunk_end_date'
		ORDER BY SchedID ASC";
    $result = $db->sql_query($query);
    $num = $db->sql_numrows($result);

    if ($num == 0) {
        return;
    }

    $teamSeasonRecordsQuery = "SELECT tid, leagueRecord FROM ibl_standings ORDER BY tid ASC;";
    $teamSeasonRecordsResult = $db->sql_query($teamSeasonRecordsQuery);

    $projectedNextSimEnd = date('Y/m/d', strtotime($lastSimEndDate . ' + ' . $simLengthDays . ' days'));

    // Format week dates for header
    $weekStart = date('n/j', strtotime($chunk_start_date));
    $weekEnd = date('n/j', strtotime($chunk_end_date));

    $isCurrentWeek = ($j == $currentWeek);
    $weekClass = 'schedule-week' . ($isCurrentWeek ? ' schedule-week--current' : '');

    echo '<div class="' . $weekClass . '" id="week-' . $j . '">';
    echo '<div class="schedule-week__header">';
    echo '<span class="schedule-week__label">Wk ' . $j . '</span>';
    echo '<span class="schedule-week__dates">' . $weekStart . '–' . $weekEnd . '</span>';
    if ($isCurrentWeek) {
        echo '<span class="schedule-week__current-badge">Current</span>';
    }
    echo '</div>';

    echo '<div class="schedule-week__grid">';

    $i = 0;
    while ($i < $num) {
        $date = $db->sql_result($result, $i, "Date");
        $visitor = $db->sql_result($result, $i, "Visitor");
        $visitorScore = $db->sql_result($result, $i, "VScore");
        $home = $db->sql_result($result, $i, "Home");
        $homeScore = $db->sql_result($result, $i, "HScore");
        $boxid = $db->sql_result($result, $i, "BoxID");

        $visitorTeamname = $commonRepository->getTeamnameFromTeamID($visitor);
        $homeTeamname = $commonRepository->getTeamnameFromTeamID($home);
        $visitorRecord = $db->sql_result($teamSeasonRecordsResult, $visitor - 1, "leagueRecord");
        $homeRecord = $db->sql_result($teamSeasonRecordsResult, $home - 1, "leagueRecord");

        // Determine game state
        $isUnplayed = ($visitorScore == $homeScore && strtotime($date) <= strtotime($projectedNextSimEnd));
        $visitorWon = ($visitorScore > $homeScore);
        $homeWon = ($homeScore > $visitorScore);

        // Game card classes
        $gameClass = 'schedule-game';
        if ($isUnplayed) {
            $gameClass .= ' schedule-game--upcoming';
        }

        echo '<a href="ibl/IBL/box' . \Utilities\HtmlSanitizer::safeHtmlOutput($boxid) . '.htm" class="' . $gameClass . '">';

        // Compact date
        $shortDate = date('n/j', strtotime($date));
        echo '<span class="schedule-game__date">' . $shortDate . '</span>';

        // Visitor
        $vClass = $visitorWon ? ' schedule-game__team--win' : '';
        echo '<span class="schedule-game__team' . $vClass . '">' . \Utilities\HtmlSanitizer::safeHtmlOutput($visitorTeamname) . '</span>';
        echo '<span class="schedule-game__score' . $vClass . '">' . ($isUnplayed ? '-' : \Utilities\HtmlSanitizer::safeHtmlOutput($visitorScore)) . '</span>';

        echo '<span class="schedule-game__vs">@</span>';

        // Home
        $hClass = $homeWon ? ' schedule-game__team--win' : '';
        echo '<span class="schedule-game__score' . $hClass . '">' . ($isUnplayed ? '-' : \Utilities\HtmlSanitizer::safeHtmlOutput($homeScore)) . '</span>';
        echo '<span class="schedule-game__team' . $hClass . '">' . \Utilities\HtmlSanitizer::safeHtmlOutput($homeTeamname) . '</span>';

        echo '</a>';

        $i++;
    }

    echo '</div>'; // Close grid
    echo '</div>'; // Close week
}

function fnc_date_calc($this_date, $num_days)
{
    $my_time = strtotime($this_date);
    $timestamp = $my_time + ($num_days * 86400);
    $return_date = date("Y/m/d", $timestamp);

    return $return_date;
}
