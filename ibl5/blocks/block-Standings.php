<?php

if (!defined('BLOCK_FILE')) {
    Header("Location: ../index.php");
    die();
}

use Utilities\HtmlSanitizer;

global $mysqli_db;
$season = new Season($mysqli_db);

$content .= '<style>
.standings-block {
    font-family: var(--font-sans, \'Inter\', -apple-system, BlinkMacSystemFont, sans-serif);
    width: 100%;
}
.standings-block__sim-dates {
    text-align: center;
    margin-bottom: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--gray-200, #e5e7eb);
}
.standings-block__sim-label {
    font-size: 0.6875rem;
    color: var(--gray-500, #6b7280);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 0.25rem;
}
.standings-block__sim-date {
    font-family: var(--font-display, \'Poppins\', sans-serif);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--navy-800, #1e293b);
}
.standings-block__conf-title {
    font-family: var(--font-display, \'Poppins\', sans-serif);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--accent-500, #f97316);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    text-align: center;
    margin: 0.75rem 0 0.5rem;
}
.standings-block__table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border-radius: var(--radius-md, 0.375rem);
    overflow: hidden;
    margin-bottom: 0.5rem;
}
.standings-block__header {
    background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
}
.standings-block__header th {
    color: white;
    font-family: var(--font-display, \'Poppins\', sans-serif);
    font-weight: 600;
    font-size: 0.5625rem;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    padding: 0.375rem 0.25rem;
    text-align: center;
}
.standings-block__row {
    transition: background-color 150ms ease;
}
.standings-block__row:nth-child(odd) {
    background-color: white;
}
.standings-block__row:nth-child(even) {
    background-color: var(--gray-50, #f9fafb);
}
.standings-block__row:hover {
    background-color: var(--gray-100, #f3f4f6);
}
.standings-block__row td {
    font-size: 0.6875rem;
    padding: 0.25rem;
    color: var(--gray-800, #1f2937);
}
.standings-block__team {
    white-space: nowrap;
}
.standings-block__record {
    text-align: center;
}
.standings-block__gb {
    text-align: right;
}
.standings-block a {
    color: var(--gray-800, #1f2937);
    text-decoration: none;
    font-weight: 500;
    transition: color 150ms ease;
}
.standings-block a:hover {
    color: var(--accent-500, #f97316);
}
.standings-block__clinched {
    color: var(--accent-600, #ea580c);
    font-weight: 700;
}
.standings-block__footer {
    text-align: center;
    padding-top: 0.5rem;
    border-top: 1px solid var(--gray-200, #e5e7eb);
    margin-top: 0.5rem;
}
.standings-block__footer a {
    font-size: 0.625rem;
    color: var(--gray-400, #9ca3af);
    font-style: italic;
}
.standings-block__footer a:hover {
    color: var(--accent-500, #f97316);
}
</style>';

$content .= '<div class="standings-block">';
$content .= '<div class="standings-block__sim-dates">';
$content .= '<div class="standings-block__sim-label">Recent Sim Dates</div>';
$content .= '<div class="standings-block__sim-date">' . HtmlSanitizer::safeHtmlOutput($season->lastSimStartDate) . '</div>';
$content .= '<div style="font-size: 0.5625rem; color: var(--gray-400);">to</div>';
$content .= '<div class="standings-block__sim-date">' . HtmlSanitizer::safeHtmlOutput($season->lastSimEndDate) . '</div>';
$content .= '</div>';

// Eastern Conference
$queryEasternConference = "SELECT tid, team_name, leagueRecord, confGB, clinchedConference, clinchedDivision, clinchedPlayoffs
    FROM ibl_standings
    WHERE conference = 'Eastern'
    ORDER BY confGB ASC";
$resultEasternConference = $mysqli_db->query($queryEasternConference);

$content .= '<div class="standings-block__conf-title">Eastern Conference</div>';
$content .= '<table class="standings-block__table">';
$content .= '<thead><tr class="standings-block__header"><th>Team</th><th>W-L</th><th>GB</th></tr></thead>';
$content .= '<tbody>';

while ($row = $resultEasternConference->fetch_assoc()) {
    $tid = (int)$row['tid'];
    $team_name = HtmlSanitizer::safeHtmlOutput(trim($row['team_name']));
    $leagueRecord = HtmlSanitizer::safeHtmlOutput($row['leagueRecord']);
    $confGB = HtmlSanitizer::safeHtmlOutput($row['confGB']);
    $clinchedConference = $row['clinchedConference'];
    $clinchedDivision = $row['clinchedDivision'];
    $clinchedPlayoffs = $row['clinchedPlayoffs'];

    $prefix = '';
    if ($clinchedConference == 1) {
        $prefix = '<span class="standings-block__clinched">Z</span>-';
    } elseif ($clinchedDivision == 1) {
        $prefix = '<span class="standings-block__clinched">Y</span>-';
    } elseif ($clinchedPlayoffs == 1) {
        $prefix = '<span class="standings-block__clinched">X</span>-';
    }

    $content .= '<tr class="standings-block__row">';
    $content .= '<td class="standings-block__team"><a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $tid . '">' . $prefix . $team_name . '</a></td>';
    $content .= '<td class="standings-block__record">' . $leagueRecord . '</td>';
    $content .= '<td class="standings-block__gb">' . $confGB . '</td>';
    $content .= '</tr>';
}

$content .= '</tbody></table>';

// Western Conference
$queryWesternConference = "SELECT tid, team_name, leagueRecord, confGB, clinchedConference, clinchedDivision, clinchedPlayoffs
    FROM ibl_standings
    WHERE conference = 'Western'
    ORDER BY confGB ASC";
$resultWesternConference = $mysqli_db->query($queryWesternConference);

$content .= '<div class="standings-block__conf-title">Western Conference</div>';
$content .= '<table class="standings-block__table">';
$content .= '<thead><tr class="standings-block__header"><th>Team</th><th>W-L</th><th>GB</th></tr></thead>';
$content .= '<tbody>';

while ($row = $resultWesternConference->fetch_assoc()) {
    $tid = (int)$row['tid'];
    $team_name = HtmlSanitizer::safeHtmlOutput(trim($row['team_name']));
    $leagueRecord = HtmlSanitizer::safeHtmlOutput($row['leagueRecord']);
    $confGB = HtmlSanitizer::safeHtmlOutput($row['confGB']);
    $clinchedConference = $row['clinchedConference'];
    $clinchedDivision = $row['clinchedDivision'];
    $clinchedPlayoffs = $row['clinchedPlayoffs'];

    $prefix = '';
    if ($clinchedConference == 1) {
        $prefix = '<span class="standings-block__clinched">Z</span>-';
    } elseif ($clinchedDivision == 1) {
        $prefix = '<span class="standings-block__clinched">Y</span>-';
    } elseif ($clinchedPlayoffs == 1) {
        $prefix = '<span class="standings-block__clinched">X</span>-';
    }

    $content .= '<tr class="standings-block__row">';
    $content .= '<td class="standings-block__team"><a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $tid . '">' . $prefix . $team_name . '</a></td>';
    $content .= '<td class="standings-block__record">' . $leagueRecord . '</td>';
    $content .= '<td class="standings-block__gb">' . $confGB . '</td>';
    $content .= '</tr>';
}

$content .= '</tbody></table>';

$content .= '<div class="standings-block__footer"><a href="modules.php?name=Standings">-- Full Standings --</a></div>';
$content .= '</div>';
