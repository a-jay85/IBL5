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

$stmt = $mysqli_db->prepare("SELECT a.name, a.teamid, a.team, b.tid, b.teamname
	FROM ibl_hist a, ibl_plr b
	WHERE a.pid = b.pid
	AND a.year = ?
	AND a.teamid != b.tid
	ORDER BY b.teamname");
if (!$stmt) {
    throw new RuntimeException("Prepare failed: " . $mysqli_db->error);
}
$stmt->bind_param("i", $previousSeasonEndingYear);
$stmt->execute();
$result = $stmt->get_result();

// Render page
Nuke\Header::header();

echo "<script src=\"/ibl5/jslib/sorttable.js\"></script>
<h2 class=\"ibl-title\">Player Transactions Since Last Season</h2>
<p style=\"text-align: center;\"><em>Click the headings to sort the table</em></p>
<div class=\"table-scroll-wrapper\">
<div class=\"table-scroll-container\">
<table class=\"sortable ibl-data-table responsive-table\">
	<tr>
		<th>Player</th>
		<th>New Team</th>
		<th>Old Team</th>
	</tr>";

while ($row = $result->fetch_assoc()) {
    $playername = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
    $oldteam = htmlspecialchars($row['team'], ENT_QUOTES, 'UTF-8');
    $newteam = htmlspecialchars($row['teamname'], ENT_QUOTES, 'UTF-8');
    echo "<tr>
		<td>$playername</td>
		<td>$newteam</td>
		<td>$oldteam</td>
	</tr>";
}

$stmt->close();

echo "</table></div></div>";

Nuke\Footer::footer();
