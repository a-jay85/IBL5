<?php

$season = new Season($db);

$previousSeasonEndingYear = $season->endingYear - 1;

$query = "SELECT a.name, a.teamid, a.team, b.tid, b.teamname FROM ibl_hist a, ibl_plr b WHERE a.pid = b.pid AND a.year = $previousSeasonEndingYear AND a.teamid != b.tid ORDER BY b.teamname";
$result = $db->sql_query($query);
$num = $db->sql_numrows($result);

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

$i = 0;
while ($i < $num) {
    $playername = $db->sql_result($result, $i, "name");
    $oldteam = $db->sql_result($result, $i, "team");
    $newteam = $db->sql_result($result, $i, "teamname");
    echo "<tr>
		<td>$playername</td>
		<td>$newteam</td>
		<td>$oldteam</td>
	</tr>";
    $i++;
}

echo "</table></center>";
