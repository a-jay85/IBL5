<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$query1="UPDATE IBL_ASG_Votes SET East_C = NULL, East_F1 = NULL, East_F2 = NULL, East_G1 = NULL, East_G2 = NULL, West_C = NULL, West_F1 = NULL, West_F2 = NULL, West_G1 = NULL, West_G2 = NULL";
$result1=mysql_query($query1);

$query2="UPDATE nuke_ibl_settings SET value = 'Yes' where sid = 23";
$result2=mysql_query($query2);

$query3="UPDATE ibl_team_history SET asg_vote = 'No Vote'";
$result3=mysql_query($query3);

echo "ASG Voting has been reset!<br>";



?>
