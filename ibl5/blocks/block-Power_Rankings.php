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

$query = "SELECT TeamID, Team, ranking, win, loss, color1, color2
    FROM ibl_power
    INNER JOIN ibl_team_info info USING (teamid)
    ORDER BY ranking DESC;";
$result = $mysqli_db->query($query);

$content .= '<style>
.power-block {
    font-family: var(--font-sans, \'Inter\', -apple-system, BlinkMacSystemFont, sans-serif);
    width: 100%;
}
.power-block__table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.power-block__row {
    transition: background-color 150ms ease;
}
.power-block__row:hover {
    background-color: var(--gray-100, #f3f4f6);
}
.power-block__rank {
    font-family: var(--font-display, \'Poppins\', sans-serif);
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--gray-500, #6b7280);
    text-align: right;
    padding: 0.25rem;
    width: 20px;
    vertical-align: middle;
}
.power-block__team {
    text-align: center;
    padding: 0.25rem 0.375rem;
    border-radius: var(--radius-sm, 0.25rem);
    vertical-align: middle;
}
.power-block__team a {
    font-family: var(--font-display, \'Poppins\', sans-serif);
    font-size: 0.6875rem;
    font-weight: 600;
    text-decoration: none;
    transition: opacity 150ms ease;
}
.power-block__team a:hover {
    opacity: 0.8;
}
.power-block__rating {
    font-family: var(--font-display, \'Poppins\', sans-serif);
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--accent-500, #f97316);
    text-align: right;
    padding: 0.25rem;
    width: 30px;
    vertical-align: middle;
}
.power-block__footer {
    text-align: center;
    padding-top: 0.5rem;
    border-top: 1px solid var(--gray-200, #e5e7eb);
    margin-top: 0.5rem;
}
.power-block__footer a {
    font-size: 0.625rem;
    color: var(--gray-400, #9ca3af);
    font-style: italic;
    text-decoration: none;
    transition: color 150ms ease;
}
.power-block__footer a:hover {
    color: var(--accent-500, #f97316);
}
</style>';

$content .= '<div class="power-block">';
$content .= '<table class="power-block__table"><tbody>';

$i = 0;
while ($row = $result->fetch_assoc()) {
    $tid = (int)$row['TeamID'];
    $Team = HtmlSanitizer::safeHtmlOutput($row['Team']);
    $ranking = HtmlSanitizer::safeHtmlOutput($row['ranking']);
    $teamcolor1 = HtmlSanitizer::safeHtmlOutput($row['color1']);
    $teamcolor2 = HtmlSanitizer::safeHtmlOutput($row['color2']);

    $content .= '<tr class="power-block__row">';
    $content .= '<td class="power-block__rank">' . ($i + 1) . '.</td>';
    $content .= '<td class="power-block__team" style="background-color: #' . $teamcolor1 . ';"><a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $tid . '" style="color: #' . $teamcolor2 . ';">' . $Team . '</a></td>';
    $content .= '<td class="power-block__rating">' . $ranking . '</td>';
    $content .= '</tr>';

    $i++;
}

$content .= '</tbody></table>';
$content .= '<div class="power-block__footer"><a href="modules.php?name=Power_Rankings">-- Full Power Rankings --</a></div>';
$content .= '</div>';
