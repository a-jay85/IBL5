<?php

require 'mainfile.php';

$teamname = $_POST['teamname'];
$player = $_POST['player'];
$draft_round = $_POST['draft_round'];
$draft_pick = $_POST['draft_pick'];
$date = date('Y-m-d h:m:s');

if ($player != NULL) {
    $query = "UPDATE ibl_draft 
        SET `player` = '$player', 
            `date` = '$date' 
        WHERE `round` = '$draft_round' 
           AND `pick` = '$draft_pick'";
    $result = $db->sql_query($query);
    
    $query2 = "UPDATE `ibl_scout_rookieratings`
        SET `team` = '$teamname', 
            `drafted` = '1'
        WHERE `name` = '$player'";
    $result2 = $db->sql_query($query2);
    
    echo "With pick number $draft_pick in round $draft_round $teamname select $player!<br>
    <a href=\"modules.php?name=College_Scouting\">Go back to the Draft module</a>";
} else {
    echo "Oops, you didn't select a player.<p>
        Please <a href=\"modules.php?name=College_Scouting\">go back to the Draft module</a> and select a player before hitting the Draft button.";
}