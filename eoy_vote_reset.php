<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_iblleague";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");


$query1="UPDATE nuke_eoy_votes SET MVP_1 = NULL, MVP_2 = NULL, MVP_3 = NULL, Six_1 = NULL, Six_2 = NULL, Six_3 = NULL, ROY_1 = NULL, ROY_2 = NULL, ROY_3 = NULL, GM_1 = NULL, GM_2 = NULL, GM_3 = NULL";
$result1=mysql_query($query1);

$query2="UPDATE nuke_ibl_settings SET value = 'Yes' where sid = 24";
$result2=mysql_query($query2);

$query3="UPDATE ibl_team_history SET eoy_vote = 'No Vote'";
$result3=mysql_query($query3);

echo "EOY Voting has been reset!<br>";



?>