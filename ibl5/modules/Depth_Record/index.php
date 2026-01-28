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

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
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
    $teamName = Utilities\HtmlSanitizer::safeHtmlOutput($row['team_name']);
    $teamCity = Utilities\HtmlSanitizer::safeHtmlOutput($row['team_city']);
    $color1 = Utilities\HtmlSanitizer::safeHtmlOutput($row['color1']);
    $color2 = Utilities\HtmlSanitizer::safeHtmlOutput($row['color2']);
    $depth = Utilities\HtmlSanitizer::safeHtmlOutput($row['depth']);
    $simDepth = Utilities\HtmlSanitizer::safeHtmlOutput($row['sim_depth']);
    $asgVote = Utilities\HtmlSanitizer::safeHtmlOutput($row['asg_vote']);
    $eoyVote = Utilities\HtmlSanitizer::safeHtmlOutput($row['eoy_vote']);

    $tableRows .= "<tr>
        <td class=\"ibl-team-cell--colored\" style=\"background-color: #{$color1};\">
            <a href=\"modules.php?name=Team&amp;op=team&amp;teamID={$teamId}\" class=\"ibl-team-cell__name\" style=\"color: #{$color2};\">
                <img src=\"images/logo/new{$teamId}.png\" alt=\"\" class=\"ibl-team-cell__logo\" width=\"24\" height=\"24\" loading=\"lazy\">
                {$teamCity} {$teamName}
            </a>
        </td>
        <td>{$simDepth}</td>
        <td>{$depth}</td>
        <td>{$asgVote}</td>
        <td>{$eoyVote}</td>
    </tr>";
}

$stmt->close();

$html = '<h2 class="ibl-table-title">Depth Chart &amp; Voting Record</h2>
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
