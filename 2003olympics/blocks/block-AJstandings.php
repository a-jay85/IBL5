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
$season = new Season($db);

$content .= '<table width=150>';
$content .= "<center><u>Recent Sim Dates:</u></center>";
$content .= "<center><strong>$season->lastSimStartDate</strong></center>";
$content .= "<center>-to-</center>";
$content .= "<center><strong>$season->lastSimEndDate</strong></center>";
$content .= '<tr><td colspan=2><hr></td></tr>';

$queryOlympicTeams = "SELECT tid, team_name, leagueRecord, confGB, clinchedConference, clinchedDivision, clinchedPlayoffs
    FROM ibl_standings
    WHERE tid BETWEEN 1 AND 8
    ORDER BY confGB ASC";
$resultOlympicTeams = $db->sql_query($queryOlympicTeams);
$limitOlympicTeams = $db->sql_numrows($resultOlympicTeams);

$content .= '
    <tr>
        <td colspan=2>
            <center><font color=#fd004d><b>' . $season->endingYear . ' Olympic Games</b></font></center>
        </td>
    </tr>
    <tr bgcolor=#006cb3>
        <td>
            <center><font color=#ffffff><b>Team (W-L)</b></font></center>
        </td>
        <td>
            <center><font color=#ffffff><b>GB</b></font></center>
        </td>
    </tr>';

$i = 0;
while ($i < $limitOlympicTeams) {
    $tid = $db->sql_result($resultOlympicTeams, $i, 0);
    $team_name = trim($db->sql_result($resultOlympicTeams, $i, 1));
    $leagueRecord = $db->sql_result($resultOlympicTeams, $i, 2);
    $confGB = $db->sql_result($resultOlympicTeams, $i, 3);
    $clinchedConference = $db->sql_result($resultOlympicTeams, $i, 4);
    $clinchedDivision = $db->sql_result($resultOlympicTeams, $i, 5);
    $clinchedPlayoffs = $db->sql_result($resultOlympicTeams, $i, 6);
    if ($clinchedConference == 1) {
        $team_name = "<b>Z</b>-" . $team_name;
    } elseif ($clinchedDivision == 1) {
        $team_name = "<b>Y</b>-" . $team_name;
    } elseif ($clinchedPlayoffs == 1) {
        $team_name = "<b>X</b>-" . $team_name;
    }

    $content .= '
        <tr>
            <td nowrap>
                <a href="modules.php?name=Team&op=team&tid=' . $tid . '">' . $team_name . '</a> (' . $leagueRecord . ')
            </td>
            <td>
                ' . $confGB . '
            </td>
        </tr>';
    $i++;
}

$content .= '
    <tr>
        <td colspan=2>
            <center><a href="modules.php?name=Content&pa=showpage&pid=4"><font color=#aaaaaa><i>-- Full Standings --</i></font></a></center>
        </td>
    </tr>
</table>';
