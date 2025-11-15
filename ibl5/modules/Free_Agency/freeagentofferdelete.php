<?php

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

echo "<HTML><HEAD><TITLE>Free Agency Offer Deletion</TITLE></HEAD><BODY>";

$teamname = $_POST['teamname'];
$playername = $_POST['playername'];

$databaseService = new \Services\DatabaseService();

// Escape user input for SQL
$escaped_playername = $databaseService->escapeString($db, $playername);
$escaped_teamname = $databaseService->escapeString($db, $teamname);

// ==== ENTER OFFER INTO DATABASE ====

$querychunk = "DELETE FROM `ibl_fa_offers` WHERE `name` = '$escaped_playername' AND `team` = '$escaped_teamname'";
$resultchunk = $db->sql_query($querychunk);

echo "Your offers have been deleted.  This should show up immediately.  Please <a href=\"/ibl5/modules.php?name=Free_Agency\">click here to return to the Free Agency main page</a> (your offer should now be gone).</br>";
