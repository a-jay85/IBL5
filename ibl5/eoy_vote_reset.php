<?php

require 'mainfile.php';

$query1 = "UPDATE IBL_EOY_Votes SET MVP_1 = NULL, MVP_2 = NULL, MVP_3 = NULL, Six_1 = NULL, Six_2 = NULL, Six_3 = NULL, ROY_1 = NULL, ROY_2 = NULL, ROY_3 = NULL, GM_1 = NULL, GM_2 = NULL, GM_3 = NULL";
$result1 = $db->sql_query($query1);

$query2 = "UPDATE ibl_settings SET value = 'Yes' where name = 'EOY Voting'";
$result2 = $db->sql_query($query2);

$query3 = "UPDATE ibl_team_history SET eoy_vote = 'No Vote'";
$result3 = $db->sql_query($query3);

echo "EOY Voting has been reset!<br>";
