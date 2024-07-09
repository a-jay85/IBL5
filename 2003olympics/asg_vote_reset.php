<?php

require 'mainfile.php';

$query1 = "UPDATE ibl_votes_ASG 
    SET East_F1 = NULL, East_F2 = NULL, East_F3 = NULL, East_F4 = NULL,
        West_F1 = NULL, West_F2 = NULL, West_F3 = NULL, West_F4 = NULL,
        East_B1 = NULL, East_B2 = NULL, East_B3 = NULL, East_B4 = NULL,
        West_B1 = NULL, West_B2 = NULL, West_B3 = NULL, West_B4 = NULL;";
$result1 = $db->sql_query($query1);

$query2 = "UPDATE ibl_settings SET value = 'Yes' where name = 'ASG Voting'";
$result2 = $db->sql_query($query2);

$query3 = "UPDATE ibl_team_history SET asg_vote = 'No Vote'";
$result3 = $db->sql_query($query3);

echo "ASG Voting has been reset!<br>";
