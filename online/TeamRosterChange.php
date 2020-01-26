<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_ibl5";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

$progression_constant = 1215;

$query="SELECT a.name, a.teamid, a.team, b.tid, b.teamname FROM nuke_iblhist a, nuke_iblplyr b WHERE a.pid = b.pid AND a.year = 1997 AND a.teamid != b.tid ORDER BY b.tid";
$result=mysql_query($query);
$num=mysql_numrows($result);
echo "<table border=1><tr><td>";
echo "<table border=1><tr><td colspan=3><b>GAINS</td></tr><tr><td><b>Player</td><td><b>New Team</td><td><b>Old Team</td></tr>";

$i=0;

while ($i < $num)
{
	$playername=mysql_result($result,$i,"a.name");
	$oldteam=mysql_result($result,$i,"a.team");
	$newteam=mysql_result($result,$i,"b.teamname");
	echo "<tr><td>$playername</td><td>$newteam</td><td>$oldteam</td></tr>";
	$i++;
}
echo "</table></td><td width=50></td><td>";
echo "<table border=1><tr><td colspan=3><b>LOSSES</td></tr><tr><td><b>Player</td><td><b>Old Team</td><td><b>New Team</td></tr>";


$query2="SELECT a.name, a.teamid, a.team, b.tid, b.teamname FROM nuke_iblhist a, nuke_iblplyr b WHERE a.pid = b.pid AND a.year = 1997 AND a.teamid != b.tid ORDER BY a.teamid";
$result2=mysql_query($query2);
$num2=mysql_numrows($result2);
$j=0;

while ($j < $num2)
{
	$playername2=mysql_result($result2,$j,"a.name");
	$oldteam2=mysql_result($result2,$j,"a.team");
	$newteam2=mysql_result($result2,$j,"b.teamname");
	echo "<tr><td>$playername2</td><td>$oldteam2</td><td>$newteam2</td></tr>";
	$j++;
}
echo "</td></tr></table>";
?>