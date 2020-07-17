<?php

require $_SERVER['DOCUMENT_ROOT'] . '/sharedFunctions.php';
require $_SERVER['DOCUMENT_ROOT'] . '/config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$currentSeasonEndingYear = getCurrentSeasonEndingYear();
$previousSeasonEndingYear = $currentSeasonEndingYear - 1;

$query="SELECT a.name, a.teamid, a.team, b.tid, b.teamname FROM nuke_iblhist a, nuke_iblplyr b WHERE a.pid = b.pid AND a.year = $previousSeasonEndingYear AND a.teamid != b.tid ORDER BY b.teamname";
$result=mysql_query($query);
$num=mysql_numrows($result);

echo "<script src=\"http://www.iblhoops.net/jslib/sorttable.js\"></script>
<center><h1>OFFSEASON MOVEMENT</h1>
<i>Click the headings to sort the table</i>
<table border=1><tr><td>";
echo "<table border=1 class=\"sortable\">
	<tr>
		<th><b>Player</th>
		<th><b>New Team</th>
		<th><b>Old Team</th>
	</tr>";

$i=0;

while ($i < $num)
{
	$playername=mysql_result($result,$i,"a.name");
	$oldteam=mysql_result($result,$i,"a.team");
	$newteam=mysql_result($result,$i,"b.teamname");
	echo "<tr><td>$playername</td><td>$newteam</td><td>$oldteam</td></tr>";
	$i++;
}
echo "</table></center>";
?>
