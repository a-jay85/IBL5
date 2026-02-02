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

use Utilities\HtmlSanitizer;

global $mysqli_db;

$queryActiveTeamAccounts = "SELECT * FROM nuke_users WHERE user_ibl_team != '' ORDER BY user_ibl_team ASC";
$resultActiveTeamAccounts = $mysqli_db->query($queryActiveTeamAccounts);

// Collect teams needing attention
$teamsNeedingAttention = [];

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
        $newDepthChartNeeded = true;
    } else {
        $newDepthChartNeeded = false;
    }

    $querySimDepthChartTimestamp = "SELECT sim_depth FROM ibl_team_history WHERE team_name = '$teamname'";
    $resultSimDepthChartTimestamp = $mysqli_db->query($querySimDepthChartTimestamp);
    $depthRow = $resultSimDepthChartTimestamp->fetch_assoc();
    $simDepthChartTimestamp = $depthRow['sim_depth'];

    if ($waiversNeeded > 0 || ($newDepthChartNeeded && $simDepthChartTimestamp == "No Depth Chart")) {
        $teamsNeedingAttention[] = [
            'teamname' => $teamname,
            'healthyPlayers' => $numberOfHealthyPlayersOnTeam,
            'waiversNeeded' => $waiversNeeded,
            'newDepthChartNeeded' => $newDepthChartNeeded,
        ];
    }
}

// Build content
$content = '<div class="injury-block">
    <div class="injury-block__header">
        <div class="injury-block__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>
            </svg>
        </div>
        <h3 class="injury-block__title">Injury Alert</h3>
    </div>
    <div class="injury-block__description">
        Teams needing to submit a new lineup or sign player(s) from waivers due to injury:
    </div>';

if (empty($teamsNeedingAttention)) {
    $content .= '<div class="ibl-empty-state">
        <svg class="ibl-empty-state__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
        <p class="ibl-empty-state__text">All teams are good to go!</p>
    </div>';
} else {
    $content .= '<table class="injury-table">
        <thead>
            <tr>
                <th>Team Name</th>
                <th>Healthy Players</th>
                <th>Waivers Needed</th>
                <th>New Depth Chart</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($teamsNeedingAttention as $team) {
        $teamName = HtmlSanitizer::safeHtmlOutput($team['teamname']);
        $healthyPlayers = (int)$team['healthyPlayers'];
        $waiversNeeded = (int)$team['waiversNeeded'];
        $depthNeeded = $team['newDepthChartNeeded'];

        // Determine styling classes
        $healthyClass = $healthyPlayers < 12 ? 'injury-table__count--warning' : 'injury-table__count--ok';
        $waiversClass = $waiversNeeded > 0 ? 'injury-table__count--warning' : 'injury-table__count--ok';
        $depthStatusClass = $depthNeeded ? 'injury-table__status--yes' : 'injury-table__status--no';
        $depthStatusText = $depthNeeded ? 'Yes' : 'No';
        $depthStatusIcon = $depthNeeded
            ? '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';

        $content .= '<tr>
            <td data-label="Team Name">
                <span class="injury-table__team-name">' . $teamName . '</span>
            </td>
            <td data-label="Healthy Players">
                <span class="injury-table__count ' . $healthyClass . '">' . $healthyPlayers . '</span>
            </td>
            <td data-label="Waivers Needed">
                <span class="injury-table__count ' . $waiversClass . '">' . $waiversNeeded . '</span>
            </td>
            <td data-label="New Depth Chart">
                <span class="injury-table__status ' . $depthStatusClass . '">
                    ' . $depthStatusIcon . '
                    ' . $depthStatusText . '
                </span>
            </td>
        </tr>';
    }

    $content .= '</tbody>
    </table>';
}

$content .= '</div>';

?>
