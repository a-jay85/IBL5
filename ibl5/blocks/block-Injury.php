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

$queryActiveTeamAccounts = "SELECT * FROM nuke_users WHERE user_ibl_team != '' ORDER BY user_ibl_team ASC";
$resultActiveTeamAccounts = $db->sql_query($queryActiveTeamAccounts);
$numberOfActiveTeamAccounts = $db->sql_numrows($resultActiveTeamAccounts);

$content = "<table border=0>
    <tr>
        <td colspan=4>
            <b>The following teams need to submit a new lineup or sign player(s) from waivers due to injury:</b>
        </td>
    </tr>
    <tr>
        <td bgcolor=#000066>
            <font color=#ffffff><b>TEAM NAME</b></font>
        </td>
        <td bgcolor=#000066>
            <font color=#ffffff><b>HEALTHY PLAYERS</b></font>
        </td>
        <td bgcolor=#000066>
            <font color=#ffffff><b>WAIVERS NEEDED</b></font>
        </td>
        <td bgcolor=#000066>
            <font color=#ffffff><b>NEW DEPTH CHART NEEDED</b></font>
        </td>
    </tr>";

$i = 0;
while ($i < $numberOfActiveTeamAccounts) {
    $teamname = $db->sql_result($resultActiveTeamAccounts, $i, "user_ibl_team");

    $queryHealthyPlayersOnTeam = "SELECT *
        FROM ibl_plr
        WHERE teamname = '$teamname'
        AND retired = '0'
        AND injured < 7
        AND ordinal < 960
        AND name NOT LIKE '%|%'"; // "ordinal < 960" excludes waived players from this query
    $resultHealthyPlayersOnTeam = $db->sql_query($queryHealthyPlayersOnTeam);
    $numberOfHealthyPlayersOnTeam = $db->sql_numrows($resultHealthyPlayersOnTeam);

    $queryInjuredActivePlayersOnTeam = "SELECT *
        FROM ibl_plr
        WHERE teamname = '$teamname'
        AND retired = '0'
        AND injured > 7
        AND active = '1'";
    $resultInjuredActivePlayersOnTeam = $db->sql_query($queryInjuredActivePlayersOnTeam);
    $numberOfInjuredActivePlayersOnTeam = $db->sql_numrows($resultInjuredActivePlayersOnTeam);

    $waiversNeeded = 12;
    $waiversNeeded -= $numberOfHealthyPlayersOnTeam;

    if ($numberOfInjuredActivePlayersOnTeam > 0) {
        $newDepthChartNeeded = 'Yes';
    } else {
        $newDepthChartNeeded = 'No';
    }
  
    $querySimDepthChartTimestamp = "SELECT sim_depth FROM ibl_team_history WHERE team_name = '$teamname'";
    $resultSimDepthChartTimestamp = $db->sql_query($querySimDepthChartTimestamp);
    $simDepthChartTimestamp = $db->sql_result($resultSimDepthChartTimestamp, 0, "chart");

    if ($waiversNeeded > 0 || $newDepthChartNeeded == 'Yes' && $simDepthChartTimestamp == "No Depth Chart") {
        $content .= "<tr><td>$teamname</td><td>$numberOfHealthyPlayersOnTeam</td><td>$waiversNeeded</td><td>$newDepthChartNeeded</td></tr>";
    }

    $i++;
}

$content .= "</table>";
