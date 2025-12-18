<?php

global $mysqli_db;
$season = new Season($mysqli_db);

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

echo "<script src=\"http://www.iblhoops.net/jslib/sorttable.js\"></script>
<center>
<h1> PLAYER TRANSACTIONS SINCE LAST SEASON</h1>
<i>Click the headings to sort the table</i>
<table border=1 class=\"sortable\">
	<tr>
		<th><b>Player</b></th>
		<th><b>New Team</b></th>
		<th><b>Old Team</b></th>
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

echo "</table></center>";
