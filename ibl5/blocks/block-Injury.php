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

global $mysqli_db;

$queryActiveTeamAccounts = "SELECT * FROM nuke_users WHERE user_ibl_team != '' ORDER BY user_ibl_team ASC";
$resultActiveTeamAccounts = $mysqli_db->query($queryActiveTeamAccounts);

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

while ($accountRow = $resultActiveTeamAccounts->fetch_assoc()) {
    $teamname = $accountRow['user_ibl_team'];

    $queryHealthyPlayersOnTeam = "SELECT *
        FROM ibl_plr
        WHERE teamname = '$teamname'
        AND retired = '0'
        AND injured < 7
        AND ordinal <= " . JSB::WAIVERS_ORDINAL . "
        AND name NOT LIKE '%|%'";
    $resultHealthyPlayersOnTeam = $mysqli_db->query($queryHealthyPlayersOnTeam);
    $numberOfHealthyPlayersOnTeam = $resultHealthyPlayersOnTeam->num_rows;

    $queryInjuredActivePlayersOnTeam = "SELECT *
        FROM ibl_plr
        WHERE teamname = '$teamname'
        AND retired = '0'
        AND injured > 7
        AND active = '1'";
    $resultInjuredActivePlayersOnTeam = $mysqli_db->query($queryInjuredActivePlayersOnTeam);
    $numberOfInjuredActivePlayersOnTeam = $resultInjuredActivePlayersOnTeam->num_rows;

    $waiversNeeded = 12;
    $waiversNeeded -= $numberOfHealthyPlayersOnTeam;

    if ($numberOfInjuredActivePlayersOnTeam > 0) {
        $newDepthChartNeeded = 'Yes';
    } else {
        $newDepthChartNeeded = 'No';
    }
  
    $querySimDepthChartTimestamp = "SELECT sim_depth FROM ibl_team_history WHERE team_name = '$teamname'";
    $resultSimDepthChartTimestamp = $mysqli_db->query($querySimDepthChartTimestamp);
    $depthRow = $resultSimDepthChartTimestamp->fetch_assoc();
    $simDepthChartTimestamp = $depthRow['sim_depth'];

    if ($waiversNeeded > 0 || $newDepthChartNeeded == 'Yes' && $simDepthChartTimestamp == "No Depth Chart") {
        $content .= "<tr><td>$teamname</td><td>$numberOfHealthyPlayersOnTeam</td><td>$waiversNeeded</td><td>$newDepthChartNeeded</td></tr>";
    }
}

$content .= "</table>";
