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

$min_date_query = "SELECT MIN(Date) as mindate FROM ibl_schedule";
$min_date_result = $db->sql_query($min_date_query);
$row = $db->sql_fetch_assoc($min_date_result);
$min_date = $row['mindate'];

$max_date_query = "SELECT MAX(Date) as maxdate FROM ibl_schedule";
$max_date_result = $db->sql_query($max_date_query);
$row2 = $db->sql_fetch_assoc($max_date_result);
$max_date = $row2['maxdate'];
$max_date = fnc_date_calc($max_date, 0);

$chunk_start_date = $min_date;
$chunk_end_date = fnc_date_calc($min_date, 6);

$i = 0;
while ($chunk_start_date < $max_date) {
    $i++;
    chunk($chunk_start_date, $chunk_end_date, $i);
    if ($i == 13) {
        $chunk_start_date = fnc_date_calc($chunk_start_date, 11);
        $chunk_end_date = fnc_date_calc($chunk_start_date, 6);
    } else {
        $chunk_start_date = fnc_date_calc($chunk_start_date, 7);
        $chunk_end_date = fnc_date_calc($chunk_start_date, 6);
    }
}

CloseTable();
Nuke\Footer::footer();

function chunk($chunk_start_date, $chunk_end_date, $j)
{
    //TODO: unify this code with the Team module's boxscore function

    global $db;
    $sharedFunctions = new Shared($db);
    $season = new Season($db);

    $query = "SELECT *
		FROM ibl_schedule
		WHERE Date BETWEEN '$chunk_start_date' AND '$chunk_end_date'
		ORDER BY SchedID ASC";
    $result = $db->sql_query($query);
    $num = $db->sql_numrows($result);

    $teamSeasonRecordsQuery = "SELECT tid, leagueRecord FROM ibl_standings ORDER BY tid ASC;";
    $teamSeasonRecordsResult = $db->sql_query($teamSeasonRecordsQuery);

    $season->lastSimEndDate = date_create($season->lastSimEndDate);
    $projectedNextSimEndDate = date_add($season->lastSimEndDate, date_interval_create_from_date_string(League::getSimLengthInDays($db) . ' days'));

    // override $projectedNextSimEndDate to account for the blank week at end of HEAT
    if ($projectedNextSimEndDate >= date_create("$season->beginningYear-10-23") and $projectedNextSimEndDate < date_create("$season->beginningYear-11-01")) {
        $projectedNextSimEndDate = date_create("$season->beginningYear-11-08");
    }

    echo "<table width=\"500\" cellpadding=\"6\" cellspacing=\"0\" border=\"1\" align=center>";

    $i = 0;
    $z = 0;
    while ($i < $num) {
        $date = $db->sql_result($result, $i, "Date");
        $visitor = $db->sql_result($result, $i, "Visitor");
        $visitorScore = $db->sql_result($result, $i, "VScore");
        $home = $db->sql_result($result, $i, "Home");
        $homeScore = $db->sql_result($result, $i, "HScore");
        $boxid = $db->sql_result($result, $i, "BoxID");

        $visitorTeamname = $sharedFunctions->getTeamnameFromTeamID($visitor);
        $homeTeamname = $sharedFunctions->getTeamnameFromTeamID($home);
        $visitorRecord = $db->sql_result($teamSeasonRecordsResult, $visitor - 1, "leagueRecord");
        $homeRecord = $db->sql_result($teamSeasonRecordsResult, $home - 1, "leagueRecord");

        if (($i % 2) == 0) {
            $bgcolor = "FFFFFF";
        } else {
            $bgcolor = "DDDDDD";
        }

        if (($z % 2) == 0) {
            $bgcolor2 = "0070C0";
        } else {
            $bgcolor2 = "C00000";
        }

        if ($visitorScore == $homeScore and date_create($date) <= $projectedNextSimEndDate) {
            $bgcolor = "DDDD00";
        }

        if ($visitorScore > $homeScore) {
            $visitorTeamname = '<b>' . $visitorTeamname . '</b>';
            $visitorRecord = '<b>' . $visitorRecord . '</b>';
            $visitorScore = '<b>' . $visitorScore . '</b>';
        } elseif ($homeScore > $visitorScore) {
            $homeTeamname = '<b>' . $homeTeamname . '</b>';
            $homeRecord = '<b>' . $homeRecord . '</b>';
            $homeScore = '<b>' . $homeScore . '</b>';
        }

        if ($date == $datebase) {
            echo "<tr bgcolor=$bgcolor>
				<td>$date</td>
				<td><a href=\"modules.php?name=Team&op=team&tid=$visitor\">$visitorTeamname ($visitorRecord)</a></td>
				<td align=right>$visitorScore</td>
				<td><a href=\"modules.php?name=Team&op=team&tid=$home\">$homeTeamname ($homeRecord)</a></td>
				<td align=right>$homeScore</td>
				<td><a href=\"ibl/IBL/box$boxid.htm\">View</a></td>
			</tr>";
        } else {
            echo "<tr>
				<td></td><td></td><td></td><td></td><td></td><td></td>
			</tr>";
            echo "<tr bgcolor=$bgcolor2>
				<td><font color=\"FFFFFF\"><b>Date</td>
				<td><font color=\"FFFFFF\"><b>Visitor</td>
				<td><font color=\"FFFFFF\"><b>Score</td>
				<td><font color=\"FFFFFF\"><b>Home</td>
				<td><font color=\"FFFFFF\"><b>Score</td>
				<td><font color=\"FFFFFF\"><b>Box Score</td>
			</tr>";
            echo "<tr bgcolor=$bgcolor>
				<td>$date</td>
				<td><a href=\"modules.php?name=Team&op=team&tid=$visitor\">$visitorTeamname ($visitorRecord)</a></td>
				<td align=right>$visitorScore</td>
				<td><a href=\"modules.php?name=Team&op=team&tid=$home\">$homeTeamname ($homeRecord)</a></td>
				<td align=right>$homeScore</td>
				<td><a href=\"ibl/IBL/box$boxid.htm\">View</a></td>
			</tr>";
            $datebase = $date;
            $z++;
        }
        $i++;
    }
    echo "</table>";
    //return array($homewin, $homeloss, $visitorwin, $visitorloss);
}

function fnc_date_calc($this_date, $num_days)
{

    $my_time = strtotime($this_date); //converts date string to UNIX timestamp
    $timestamp = $my_time + ($num_days * 86400); //calculates # of days passed ($num_days) * # seconds in a day (86400)
    $return_date = date("Y/m/d", $timestamp); //puts the UNIX timestamp back into string format

    return $return_date; //exit function and return string
}
