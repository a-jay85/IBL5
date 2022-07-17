<?php

require 'mainfile.php';

$teamname = $_POST['teamname'];
$player = $_POST['player'];
$draft_round = $_POST['draft_round'];
$draft_pick = $_POST['draft_pick'];

$query = "UPDATE ibl_draft SET `player` = '$player' WHERE `round` = '$draft_round' AND `pick` = '$draft_pick'";
$result = $db->sql_query($query);

$query2 = "UPDATE `nuke_scout_rookieratings` SET `drafted` = '1' WHERE `name` = '$player'";
$result2 = $db->sql_query($query2);
//$system_date = time() + (7 * 24 * 60 * 60);
//echo 'Now:       '. date('Y-m-d h:m:s') ."<br>";

echo "With pick number $draft_pick in round $draft_round $teamname select $player!<br>
Go back to <a href='../'>home page</a>";
