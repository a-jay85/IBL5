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
$commonRepository = new Services\CommonMysqliRepository($mysqli_db);

$lastSimEndDate = $season->lastSimEndDate;
$simLengthDays = $league->getSimLengthInDays();
$projectedNextSimEnd = date('Y-m-d', strtotime($lastSimEndDate . ' + ' . $simLengthDays . ' days'));

// Get all games
$query = "SELECT * FROM ibl_schedule ORDER BY Date ASC, SchedID ASC";
$result = $db->sql_query($query);
$num = $db->sql_numrows($result);

// Get team records
$teamRecordsQuery = "SELECT tid, leagueRecord FROM ibl_standings ORDER BY tid ASC";
$teamRecordsResult = $db->sql_query($teamRecordsQuery);
$teamRecords = [];
while ($row = $db->sql_fetch_assoc($teamRecordsResult)) {
    $teamRecords[$row['tid']] = $row['leagueRecord'];
}

// Organize games by month and date
$gamesByMonth = [];
$months = [];
$firstUnplayedId = null;

$i = 0;
while ($i < $num) {
    $date = $db->sql_result($result, $i, "Date");
    $visitor = $db->sql_result($result, $i, "Visitor");
    $visitorScore = $db->sql_result($result, $i, "VScore");
    $home = $db->sql_result($result, $i, "Home");
    $homeScore = $db->sql_result($result, $i, "HScore");
    $boxid = $db->sql_result($result, $i, "BoxID");

    $monthKey = date('Y-m', strtotime($date));
    $monthLabel = date('F', strtotime($date));

    if (!isset($gamesByMonth[$monthKey])) {
        $gamesByMonth[$monthKey] = [];
        $months[$monthKey] = $monthLabel;
    }

    if (!isset($gamesByMonth[$monthKey][$date])) {
        $gamesByMonth[$monthKey][$date] = [];
    }

    $isUnplayed = ($visitorScore == $homeScore && strtotime($date) <= strtotime($projectedNextSimEnd));

    // Track first unplayed game
    if ($isUnplayed && $firstUnplayedId === null) {
        $firstUnplayedId = 'game-' . $boxid;
    }

    $gamesByMonth[$monthKey][$date][] = [
        'date' => $date,
        'visitor' => $visitor,
        'visitorScore' => $visitorScore,
        'visitorTeam' => $commonRepository->getTeamnameFromTeamID($visitor),
        'visitorRecord' => $teamRecords[$visitor] ?? '',
        'home' => $home,
        'homeScore' => $homeScore,
        'homeTeam' => $commonRepository->getTeamnameFromTeamID($home),
        'homeRecord' => $teamRecords[$home] ?? '',
        'boxid' => $boxid,
        'isUnplayed' => $isUnplayed,
        'visitorWon' => ($visitorScore > $homeScore),
        'homeWon' => ($homeScore > $visitorScore),
    ];

    $i++;
}

// Output schedule container
echo '<div class="schedule-container">';

// Header with month nav and jump button
echo '<div class="schedule-header">';
echo '<h1 class="schedule-title">Schedule</h1>';
if ($firstUnplayedId) {
    echo '<a href="#' . $firstUnplayedId . '" class="schedule-jump-btn" onclick="scrollToNextGames(event)">';
    echo '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12l7 7 7-7"/></svg>';
    echo 'Next Games';
    echo '</a>';
}
echo '</div>';

// Month navigation
echo '<nav class="schedule-months">';
foreach ($months as $key => $label) {
    $abbrev = date('M', strtotime($key . '-01')); // 3-letter abbreviation
    echo '<a href="#month-' . $key . '" class="schedule-months__link" onclick="scrollToMonth(event, \'' . $key . '\')">';
    echo '<span class="schedule-months__full">' . $label . '</span>';
    echo '<span class="schedule-months__abbr">' . $abbrev . '</span>';
    echo '</a>';
}
echo '</nav>';

// Output games by month
foreach ($gamesByMonth as $monthKey => $dates) {
    $monthLabel = $months[$monthKey];

    echo '<div class="schedule-month" id="month-' . $monthKey . '">';
    echo '<div class="schedule-month__header">' . $monthLabel . '</div>';

    foreach ($dates as $date => $games) {
        $dayName = date('D', strtotime($date));
        $dayNum = date('j', strtotime($date));

        echo '<div class="schedule-day">';
        echo '<div class="schedule-day__header">';
        echo '<span class="schedule-day__name">' . $dayName . '</span>';
        echo '<span class="schedule-day__num">' . $dayNum . '</span>';
        echo '</div>';

        echo '<div class="schedule-day__games">';
        foreach ($games as $game) {
            $gameClass = 'schedule-game';
            if ($game['isUnplayed']) {
                $gameClass .= ' schedule-game--upcoming';
            }

            $gameId = 'game-' . $game['boxid'];

            echo '<a href="ibl/IBL/box' . \Utilities\HtmlSanitizer::safeHtmlOutput($game['boxid']) . '.htm" class="' . $gameClass . '" id="' . $gameId . '">';

            // Visitor
            $vClass = $game['visitorWon'] ? ' schedule-game__team--win' : '';
            echo '<span class="schedule-game__team' . $vClass . '">' . \Utilities\HtmlSanitizer::safeHtmlOutput($game['visitorTeam']) . '</span>';
            echo '<span class="schedule-game__score' . $vClass . '">' . ($game['isUnplayed'] ? '–' : \Utilities\HtmlSanitizer::safeHtmlOutput($game['visitorScore'])) . '</span>';

            echo '<span class="schedule-game__vs">@</span>';

            // Home
            $hClass = $game['homeWon'] ? ' schedule-game__team--win' : '';
            echo '<span class="schedule-game__score' . $hClass . '">' . ($game['isUnplayed'] ? '–' : \Utilities\HtmlSanitizer::safeHtmlOutput($game['homeScore'])) . '</span>';
            echo '<span class="schedule-game__team' . $hClass . '">' . \Utilities\HtmlSanitizer::safeHtmlOutput($game['homeTeam']) . '</span>';

            echo '</a>';
        }
        echo '</div>'; // day__games
        echo '</div>'; // day
    }

    echo '</div>'; // month
}

echo '</div>'; // schedule-container

// Scroll scripts
echo '<script>
var headerOffset = 70; // Offset for sticky header

function scrollToMonth(e, monthKey) {
    e.preventDefault();
    var el = document.getElementById("month-" + monthKey);
    if (el) {
        var rect = el.getBoundingClientRect();
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var targetY = scrollTop + rect.top - headerOffset;
        window.scrollTo({ top: targetY, behavior: "smooth" });
    }
}

function scrollToNextGames(e) {
    e.preventDefault();
    var el = document.getElementById("' . ($firstUnplayedId ?? '') . '");
    if (el) {
        var rect = el.getBoundingClientRect();
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var targetY = scrollTop + rect.top - (window.innerHeight / 2) + (rect.height / 2);
        window.scrollTo({ top: targetY, behavior: "smooth" });
    }
}
</script>';

CloseTable();
Nuke\Footer::footer();
