<?php

declare(strict_types=1);

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2006 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
Nuke\Header::header();

// Use modern mysqli connection with prepared statement
$query = "SELECT teamid, team_name, team_city, color1, color2, depth, sim_depth, asg_vote, eoy_vote
          FROM ibl_team_history
          WHERE teamid != ?
          ORDER BY teamid ASC";

$stmt = $mysqli_db->prepare($query);
$freeAgentsId = League::FREE_AGENTS_TEAMID;
$stmt->bind_param("i", $freeAgentsId);
$stmt->execute();
$result = $stmt->get_result();

$tableRows = '';
while ($row = $result->fetch_assoc()) {
    $teamId = (int) $row['teamid'];
    $teamDisplay = trim(($row['team_city'] ?? '') . ' ' . ($row['team_name'] ?? ''));
    $color1 = $row['color1'] ?? '333333';
    $color2 = $row['color2'] ?? 'FFFFFF';
    /** @var string $depth */
    $depth = Utilities\HtmlSanitizer::safeHtmlOutput($row['depth']);
    /** @var string $simDepth */
    $simDepth = Utilities\HtmlSanitizer::safeHtmlOutput($row['sim_depth']);
    /** @var string $asgVote */
    $asgVote = Utilities\HtmlSanitizer::safeHtmlOutput($row['asg_vote']);
    /** @var string $eoyVote */
    $eoyVote = Utilities\HtmlSanitizer::safeHtmlOutput($row['eoy_vote']);

    $teamCell = UI\TeamCellHelper::renderTeamCell($teamId, $teamDisplay, $color1, $color2);

    $tableRows .= "<tr data-team-id=\"{$teamId}\">"
        . $teamCell
        . "<td>{$simDepth}</td>"
        . "<td>{$depth}</td>"
        . "<td>{$asgVote}</td>"
        . "<td>{$eoyVote}</td>"
        . '</tr>';
}

$stmt->close();

$html = '<h2 class="ibl-title">Activity Tracker</h2>
<table class="sortable ibl-data-table">
    <thead>
        <tr>
            <th>Team</th>
            <th>Sim Depth Chart</th>
            <th>Last Depth Chart</th>
            <th>ASG Ballot</th>
            <th>EOY Ballot</th>
        </tr>
    </thead>
    <tbody>' . $tableRows . '</tbody>
</table>';

echo $html;

Nuke\Footer::footer();
