<?php

global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;

include("header.php");
OpenTable();

error_reporting(E_ERROR);

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

//*******************************
//*** CURRENT YEAR CAP TOTALS ***
//*******************************

echo '<table class=\"sortable\"><tr><th>Team</th><th>Cap</th></tr>';
$getRowsQuery = 'SELECT * FROM IBL_Current_Cap WHERE tid != 0 AND tid <= 24 ORDER BY tid ASC';
$getRowsResult = mysql_query($getRowsQuery);
$numOfTeams = mysql_num_rows($getRowsResult);

$i = 1;
while ($i < $numOfTeams+1) {
	$currentCapQuery = "SELECT currentCap FROM IBL_Current_Cap WHERE tid=$i";
	$currentCapResult = mysql_query($currentCapQuery);
	$teamCapTotal = mysql_result($currentCapResult,0);

	$teamnameQuery = "SELECT teamname FROM nuke_iblplyr WHERE tid=$i AND retired=0 LIMIT 1";
	$teamnameResult = mysql_query($teamnameQuery);
	$teamname = mysql_result($teamnameResult,0);

	echo '<tr>';
	echo '<td>'.$teamname.'</td>';
	echo '<td>'.$teamCapTotal.'</td>';
	echo '</tr>';
	$i++;
}
echo '</table>';

CloseTable();
include("footer.php");
?>
