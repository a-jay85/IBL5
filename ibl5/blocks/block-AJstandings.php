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

$content .= "
    <center>
        <u>
            Recent Sim Dates:
        </u>
        <br>
        <strong>
            $season->lastSimStartDate
        </strong>
        <br>
        -to-
        <br>
        <strong>
            $season->lastSimEndDate
        </strong>
        <table style=\"width:150px;\">
            <tr>
                <td colspan=3>
                    <hr>
                </td>
            </tr>";

$queryEasternConference = "SELECT tid, team_name, leagueRecord, confGB, clinchedConference, clinchedDivision, clinchedPlayoffs
    FROM ibl_standings
    WHERE conference = 'Eastern'
    ORDER BY confGB ASC";
$resultEasternConference = $db->sql_query($queryEasternConference);
$limitEasternConference = $db->sql_numrows($resultEasternConference);

$content .= "
    <tr>
        <td colspan=3>
            <center><font color=#fd004d><b>Eastern Conference</b></font></center>
        </td>
    </tr>
    <tr bgcolor=#006cb3>
        <td>
            <center><font color=#ffffff><b>Team</b></font></center>
        </td>
        <td>
            <center><font color=#ffffff><b>W-L</b></font></center>
        </td>
        <td>
            <center><font color=#ffffff><b>GB</b></font></center>
        </td>
    </tr>";

$i = 0;
while ($i < $limitEasternConference) {
    $tid = $db->sql_result($resultEasternConference, $i, 'tid');
    $team_name = trim($db->sql_result($resultEasternConference, $i, 'team_name'));
    $leagueRecord = $db->sql_result($resultEasternConference, $i, 'leagueRecord');
    $confGB = $db->sql_result($resultEasternConference, $i, 'confGB');
    $clinchedConference = $db->sql_result($resultEasternConference, $i, 'clinchedConference');
    $clinchedDivision = $db->sql_result($resultEasternConference, $i, 'clinchedDivision');
    $clinchedPlayoffs = $db->sql_result($resultEasternConference, $i, 'clinchedPlayoffs');
    if ($clinchedConference == 1) {
        $team_name = "<b>Z</b>-" . $team_name;
    } elseif ($clinchedDivision == 1) {
        $team_name = "<b>Y</b>-" . $team_name;
    } elseif ($clinchedPlayoffs == 1) {
        $team_name = "<b>X</b>-" . $team_name;
    }

    $content .= "
        <tr>
            <td style=\"white-space: nowrap;\">
                <a href=\"modules.php?name=Team&op=team&teamID=$tid\">$team_name</a>
            </td>
            <td style=\"text-align: left;\">
                $leagueRecord
            </td>
            <td style=\"text-align: right;\">
                $confGB
            </td>
        </tr>";
    $i++;
}

$queryWesternConference = "SELECT tid, team_name, leagueRecord, confGB, clinchedConference, clinchedDivision, clinchedPlayoffs
    FROM ibl_standings
    WHERE conference = 'Western'
    ORDER BY confGB ASC";
$resultWesternConference = $db->sql_query($queryWesternConference);
$limitWesternConference = $db->sql_numrows($resultWesternConference);

$content .= "
    <tr>
        <td colspan=3>
            <hr>
        </td>
    </tr>
    <tr>
        <td colspan=3>
            <center><font color=#fd004d><b>Western Conference</b></font></center>
        </td>
    </tr>
    <tr bgcolor=#006cb3>
        <td>
            <center><font color=#ffffff><b>Team</b></font></center>
        </td>
        <td>
            <center><font color=#ffffff><b>W-L</b></font></center>
        </td>
        <td>
            <center><font color=#ffffff><b>GB</b></font></center>
        </td>
    </tr>";

$i = 0;
while ($i < $limitWesternConference) {
    $tid = $db->sql_result($resultWesternConference, $i, 'tid');
    $team_name = trim($db->sql_result($resultWesternConference, $i, 'team_name'));
    $leagueRecord = $db->sql_result($resultWesternConference, $i, 'leagueRecord');
    $confGB = $db->sql_result($resultWesternConference, $i, 'confGB');
    $clinchedConference = $db->sql_result($resultWesternConference, $i, 'clinchedConference');
    $clinchedDivision = $db->sql_result($resultWesternConference, $i, 'clinchedDivision');
    $clinchedPlayoffs = $db->sql_result($resultWesternConference, $i, 'clinchedPlayoffs');
    if ($clinchedConference == 1) {
        $team_name = "<b>Z</b>-" . $team_name;
    } elseif ($clinchedDivision == 1) {
        $team_name = "<b>Y</b>-" . $team_name;
    } elseif ($clinchedPlayoffs == 1) {
        $team_name = "<b>X</b>-" . $team_name;
    }

    $content .= "
        <tr>
            <td style=\"white-space: nowrap; width: 10px;\">
                <a href=\"modules.php?name=Team&op=team&teamID=$tid\">$team_name</a>
            </td>
            <td style=\"text-align: left;\">
                $leagueRecord
            </td>
            <td style=\"text-align: right;\">
                $confGB
            </td>
        </tr>";
    $i++;
}

$content .= "
    <tr>
        <td colspan=3>
            <center><a href=\"modules.php?name=Content&pa=showpage&pid=4\"><font color=#aaaaaa><i>-- Full Standings --</i></font></a></center>
        </td>
    </tr>
</table>";
