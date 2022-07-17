<?php

require 'mainfile.php';

$query1 = "UPDATE IBL_ASG_Votes SET East_C = NULL, East_F1 = NULL, East_F2 = NULL, East_G1 = NULL, East_G2 = NULL, West_C = NULL, West_F1 = NULL, West_F2 = NULL, West_G1 = NULL, West_G2 = NULL";
$result1 = $db->sql_query($query1);

$query2 = "UPDATE ibl_settings SET value = 'Yes' where name = 'ASG Voting'";
$result2 = $db->sql_query($query2);

$query3 = "UPDATE ibl_team_history SET asg_vote = 'No Vote'";
$result3 = $db->sql_query($query3);

echo "ASG Voting has been reset!<br>";
