<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_iblleague";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

$query1="SELECT * FROM nuke_ibl_team_info";
$result1=mysql_query($query1);
$num1=mysql_numrows($result1);

$i=0;

echo "<HTML><HEAD><TITLE>UPDATE</TITLE></HEAD><BODY>";

while ($i < $num1) {
	$teamname = mysql_result($result1,$i,"team_name");
	$query2="SELECT * FROM nuke_iblteam_win_loss WHERE currentname = '$teamname' ORDER BY year DESC LIMIT 7";
	$result2=mysql_query($query2);
	$num2=mysql_numrows($result2);
	$j=0;
	$totw = 0;
	$totl = 0;
	
	while ($j < $num2) {
		$wins = mysql_result($result2,$j,"wins");
		$losses = mysql_result($result2,$j,"losses");
		$totw = $totw+$wins;
		$totl = $totl+$losses;
		$j++;
	}
	
	echo "Updating $teamname Tradition Information... $totw wins, $totl losses, in $num2 seasons.<br>";

	$tradw = round($totw/$num2,0);
	$tradl = round($totl/$num2,0);

	echo " Tradition: $tradw - $tradl<br>";

	$query3="UPDATE nuke_ibl_team_info SET `Contract_AvgW` = '$tradw' WHERE `team_name` = '$teamname'";
	$result3=mysql_query($query3);
	$query4="UPDATE nuke_ibl_team_info SET `Contract_AvgL` = '$tradl' WHERE `team_name` = '$teamname'";
	$result4=mysql_query($query4);
	$i++;
}

echo "</BODY></HTML>";

?>

