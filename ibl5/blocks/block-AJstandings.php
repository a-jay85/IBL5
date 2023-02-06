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

if (!defined('BLOCK_FILE')) {
    Header("Location: ../index.php");
    die();
}

global $db;
$sharedFunctions = new Shared($db);

$arrayLastSimDates = $sharedFunctions->getLastSimDatesArray();
$lastSimStartDate = $arrayLastSimDates["Start Date"];
$lastSimEndDate = $arrayLastSimDates["End Date"];

$content = $content . '<table width=150>';
$content = $content . "<center><u>Recent Sim Dates:</u></center>";
$content = $content . "<center><strong>$lastSimStartDate</strong></center>";
$content = $content . "<center>-to-</center>";
$content = $content . "<center><strong>$lastSimEndDate</strong></center>";
$content = $content . '<tr><td colspan=2><hr></td></tr>';

$queryEasternConference = "SELECT tid, team_name, leagueRecord, confGB, clinchedConference, clinchedDivision, clinchedPlayoffs
    FROM ibl_standings
    WHERE conference = 'Eastern'
    ORDER BY confGB ASC";
$resultEasternConference = $db->sql_query($queryEasternConference);
$limitEasternConference = $db->sql_numrows($resultEasternConference);

$content = $content . '
<tr><td colspan=2><center><font color=#fd004d><b>Eastern Conference</b></font></center></td></tr>
<tr bgcolor=#006cb3><td><center><font color=#ffffff><b>Team (W-L)</b></font></center></td><td><center><font color=#ffffff><b>GB</b></font></center></td></tr>';

$i = 0;
while ($i < $limitEasternConference) {
    $tid = $db->sql_result($resultEasternConference, $i, 0);
    $team_name = trim($db->sql_result($resultEasternConference, $i, 1));
    $leagueRecord = $db->sql_result($resultEasternConference, $i, 2);
    $confGB = $db->sql_result($resultEasternConference, $i, 3);
    $clinchedConference = $db->sql_result($resultEasternConference, $i, 4);
    $clinchedDivision = $db->sql_result($resultEasternConference, $i, 5);
    $clinchedPlayoffs = $db->sql_result($resultEasternConference, $i, 6);
    if ($clinchedConference == 1) {
        $team_name = "<b>Z</b>-" . $team_name;
    } elseif ($clinchedDivision == 1) {
        $team_name = "<b>Y</b>-" . $team_name;
    } elseif ($clinchedPlayoffs == 1) {
        $team_name = "<b>X</b>-" . $team_name;
    }

    $content = $content . '<tr><td nowrap><a href="modules.php?name=Team&op=team&tid=' . $tid . '">' . $team_name . '</a> (' . $leagueRecord . ')</td><td>' . $confGB . '</td></tr>';
    $i++;
}

$queryWesternConference = "SELECT tid, team_name, leagueRecord, confGB, clinchedConference, clinchedDivision, clinchedPlayoffs
    FROM ibl_standings
    WHERE conference = 'Western'
    ORDER BY confGB ASC";
$resultWesternConference = $db->sql_query($queryWesternConference);
$limitWesternConference = $db->sql_numrows($resultWesternConference);

$content = $content . '
<tr><td colspan=2><hr></td></tr>
<tr><td colspan=2><center><font color=#fd004d><b>Western Conference</b></font></center></td></tr>
<tr bgcolor=#006cb3><td><center><font color=#ffffff><b>Team (W-L)</b></font></center></td><td><center><font color=#ffffff><b>GB</b></font></center></td></tr>';

$i = 0;
while ($i < $limitWesternConference) {
    $tid = $db->sql_result($resultWesternConference, $i, 0);
    $team_name = trim($db->sql_result($resultWesternConference, $i, 1));
    $leagueRecord = $db->sql_result($resultWesternConference, $i, 2);
    $confGB = $db->sql_result($resultWesternConference, $i, 3);
    $clinchedConference = $db->sql_result($resultWesternConference, $i, 4);
    $clinchedDivision = $db->sql_result($resultWesternConference, $i, 5);
    $clinchedPlayoffs = $db->sql_result($resultWesternConference, $i, 6);
    if ($clinchedConference == 1) {
        $team_name = "<b>Z</b>-" . $team_name;
    } elseif ($clinchedDivision == 1) {
        $team_name = "<b>Y</b>-" . $team_name;
    } elseif ($clinchedPlayoffs == 1) {
        $team_name = "<b>X</b>-" . $team_name;
    }

    $content = $content . '<tr><td nowrap><a href="modules.php?name=Team&op=team&tid=' . $tid . '">' . $team_name . '</a> (' . $leagueRecord . ')</td><td>' . $confGB . '</td></tr>';
    $i++;
}

$content = $content . '
<tr><td colspan=2><center><a href="modules.php?name=Content&pa=showpage&pid=4"><font color=#aaaaaa><i>-- Full Standings --</i></font></a></center></td></tr>
</table>';
