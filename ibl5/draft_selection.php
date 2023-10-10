<?php

require 'mainfile.php';

global $db;

$teamname = $_POST['teamname'];
$player = $_POST['player'];
$draft_round = $_POST['draft_round'];
$draft_pick = $_POST['draft_pick'];
$date = date('Y-m-d h:m:s');

$sharedFunctions = new Shared($db);
$currentSeasonEndingYear = $sharedFunctions->getCurrentSeasonEndingYear();

if ($player != NULL) {
    $queryUpdateDraftTable = "UPDATE ibl_draft 
         SET `player` = '$player', 
               `date` = '$date' 
        WHERE `round` = '$draft_round' 
           AND `pick` = '$draft_pick'";
    $resultUpdateDraftTable = $db->sql_query($queryUpdateDraftTable);
    
    $queryUpdateRookieTable = "UPDATE `ibl_scout_rookieratings`
          SET `team` = '$teamname', 
           `drafted` = '1'
        WHERE `name` = '$player'";
    $resultUpdateRookieTable = $db->sql_query($queryUpdateRookieTable);

    if ($resultUpdateDraftTable AND $resultUpdateRookieTable) {
        $message = "With pick number $draft_pick in round $draft_round of the $currentSeasonEndingYear IBL Draft, the **" . $teamname . "** select **" . $player . "!**";
        echo "$message<br>
        <a href=\"modules.php?name=College_Scouting\">Go back to the Draft module</a>";
    
        $queryTeamOnTheClock = "SELECT team from ibl_draft WHERE player = '' ORDER BY round ASC, pick ASC LIMIT 1";
        $resultTeamOnTheClock = $db->sql_query($queryTeamOnTheClock);
        $teamOnTheClock = $db->sql_result($resultTeamOnTheClock, 0);

        $queryDiscordIDOfTeamOnTheClock = "SELECT discordID from ibl_team_info WHERE team_name = '$teamOnTheClock' LIMIT 1;";
        $resultDiscordIDOfTeamOnTheClock = $db->sql_query($queryDiscordIDOfTeamOnTheClock);
        $discordIDOfTeamOnTheClock = $db->sql_result($resultDiscordIDOfTeamOnTheClock, 0);

        $message .= '
**<@!' . $discordIDOfTeamOnTheClock . '>** is on the clock!';

        Discord::postToChannel('#draft-picks', $message);
    } else {
        echo "Oops, something went wrong, and at least one of the draft database tables wasn't updated.<p>
            Let A-Jay know what happened and he'll look into it.<p>
            
            <a href=\"modules.php?name=College_Scouting\">Go back to the Draft module</a>";
    }
} else {
    echo "Oops, you didn't select a player.<p>
        Please <a href=\"modules.php?name=College_Scouting\">go back to the Draft module</a> and select a player before hitting the Draft button.";
}