<?php

require '../config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$teamname = $_POST['teamname'];
$player = $_POST['player'];
$draft_round = $_POST['draft_round'];
$draft_pick = $_POST['draft_pick'];

$query="UPDATE nuke_ibl_draft SET `player` = '$player' WHERE `round` = '$draft_round' AND `pick` = '$draft_pick'";
$result=mysql_query($query);

$query2="UPDATE `nuke_scout_rookieratings` SET `drafted` = '1' WHERE `name` = '$player'";
$result2=mysql_query($query2);
//$system_date = time() + (7 * 24 * 60 * 60);
//echo 'Now:       '. date('Y-m-d h:m:s') ."<br>";

echo "With pick number $draft_pick in round $draft_round $teamname select $player!<br>
Go back to <a href='../'>home page</a>";

?>
