<?php

declare(strict_types=1);

/**
 * Player_Movement Module - Display player transactions since last season
 *
 * Shows players who changed teams between seasons.
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

global $mysqli_db;
$season = new Season($mysqli_db);

$pagetitle = "- Player Movement";

$previousSeasonEndingYear = $season->endingYear - 1;

// Query with JOINs to get team colors for both old and new teams
// Old team uses ibl_team_history (historical data), new team uses ibl_team_info (current)
$stmt = $mysqli_db->prepare("
    SELECT
        a.pid,
        a.name,
        a.teamid AS old_teamid,
        a.team AS old_team,
        b.tid AS new_teamid,
        b.teamname AS new_team,
        old_hist.team_city AS old_city,
        old_hist.color1 AS old_color1,
        old_hist.color2 AS old_color2,
        new_info.team_city AS new_city,
        new_info.color1 AS new_color1,
        new_info.color2 AS new_color2
    FROM ibl_hist a
    JOIN ibl_plr b ON a.pid = b.pid
    LEFT JOIN ibl_team_history old_hist ON a.teamid = old_hist.teamid
    LEFT JOIN ibl_team_info new_info ON b.tid = new_info.teamid
    WHERE a.year = ?
    AND a.teamid != b.tid
    ORDER BY b.teamname
");
if (!$stmt) {
    throw new RuntimeException("Prepare failed: " . $mysqli_db->error);
}
$stmt->bind_param("i", $previousSeasonEndingYear);
$stmt->execute();
$result = $stmt->get_result();

// Render page
Nuke\Header::header();

echo '<h2 class="ibl-title">Player Movement</h2>
<p style="text-align: center;"><em>Click the headings to sort the table</em></p>
<table class="sortable ibl-data-table">
    <thead>
        <tr>
            <th>Player</th>
            <th>Old</th>
            <th>New</th>
        </tr>
    </thead>
    <tbody>';

while ($row = $result->fetch_assoc()) {
    $pid = (int) $row['pid'];
    $playerName = Utilities\HtmlSanitizer::safeHtmlOutput($row['name']);
    $playerImage = "images/player/{$pid}.jpg";

    // New team data
    $newTeamId = (int) $row['new_teamid'];
    $newTeam = Utilities\HtmlSanitizer::safeHtmlOutput($row['new_team']);
    $newCity = Utilities\HtmlSanitizer::safeHtmlOutput($row['new_city'] ?? '');
    $newColor1 = $row['new_color1'] ?? '333333';
    $newColor2 = $row['new_color2'] ?? 'FFFFFF';

    // Old team data
    $oldTeamId = (int) $row['old_teamid'];
    $oldTeam = Utilities\HtmlSanitizer::safeHtmlOutput($row['old_team']);
    $oldCity = Utilities\HtmlSanitizer::safeHtmlOutput($row['old_city'] ?? '');
    $oldColor1 = $row['old_color1'] ?? '333333';
    $oldColor2 = $row['old_color2'] ?? 'FFFFFF';

    // Build team display names (city + name, or just name if city missing)
    $newTeamDisplay = trim("{$newCity} {$newTeam}");
    $oldTeamDisplay = trim("{$oldCity} {$oldTeam}");

    echo "<tr>
        <td class=\"ibl-player-cell\"><a href=\"modules.php?name=Player&amp;pa=showpage&amp;pid={$pid}\"><img src=\"{$playerImage}\" alt=\"\" class=\"ibl-player-photo\" width=\"24\" height=\"24\">{$playerName}</a></td>
        <td class=\"ibl-team-cell--colored\" style=\"background-color: #{$oldColor1};\">
            <a href=\"modules.php?name=Team&amp;op=team&amp;teamID={$oldTeamId}\" class=\"ibl-team-cell__name\" style=\"color: #{$oldColor2};\">
                <img src=\"images/logo/new{$oldTeamId}.png\" alt=\"\" class=\"ibl-team-cell__logo\" width=\"24\" height=\"24\" loading=\"lazy\">
                <span class=\"ibl-team-cell__text\">{$oldTeamDisplay}</span>
            </a>
        </td>
        <td class=\"ibl-team-cell--colored\" style=\"background-color: #{$newColor1};\">
            <a href=\"modules.php?name=Team&amp;op=team&amp;teamID={$newTeamId}\" class=\"ibl-team-cell__name\" style=\"color: #{$newColor2};\">
                <img src=\"images/logo/new{$newTeamId}.png\" alt=\"\" class=\"ibl-team-cell__logo\" width=\"24\" height=\"24\" loading=\"lazy\">
                <span class=\"ibl-team-cell__text\">{$newTeamDisplay}</span>
            </a>
        </td>
    </tr>";
}

$stmt->close();

echo '</tbody></table>';

Nuke\Footer::footer();
