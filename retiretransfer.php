<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_ibl5";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

$query1="SELECT * FROM ibl_playoff_career_totals";
$result1=mysql_query($query1);
$num1=mysql_numrows($result1);

$i=0;

echo "<HTML><HEAD><TITLE>UPDATE</TITLE></HEAD><BODY>";

while ($i < $num1) {
	$playername = mysql_result($result1,$i,"name");
	$playerid = mysql_result($result1,$i,"pid");

	$query2="SELECT * FROM nuke_iblplyr WHERE name = '$playername'";
	$result2=mysql_query($query2);
	$num2=mysql_numrows($result2);
	$retired = mysql_result($result2,0,"retired");
	echo "Updating $playername's records... retired value is $retired<br>";

	$query3="UPDATE ibl_playoff_career_totals SET `retired` = '$retired' WHERE `name` = '$playername'";
	$result3=mysql_query($query3);

	$query4="UPDATE ibl_worlds_totals SET `retired` = '$retired' WHERE `name` = '$playername'";
	$result4=mysql_query($query4);
	$i++;
}

echo "</BODY></HTML>";

?>