<?php

require 'mainfile.php';

global $db;

$teamname = $_POST['teamname'];
$playerToBeDrafted = $_POST['player'];
$draft_round = $_POST['draft_round'];
$draft_pick = $_POST['draft_pick'];
$date = date('Y-m-d h:m:s');

$sharedFunctions = new Shared($db);
$currentSeasonEndingYear = $sharedFunctions->getCurrentSeasonEndingYear();

$queryCurrentDraftSelection = "SELECT `player`
    FROM ibl_draft
    WHERE `round` = '$draft_round' 
       AND `pick` = '$draft_pick';";
$resultCurrentDraftSelection = $db->sql_query($queryCurrentDraftSelection);
$currentDraftSelection = $db->sql_result($resultCurrentDraftSelection, 0);

if (($currentDraftSelection == NULL OR $currentDraftSelection == "") AND $playerToBeDrafted != NULL) {
    // NOTE: $queryUpdateDraftTable and $queryUpdateRookieTable are formatted with single quotes to allow for apostrophes in player names.
    $queryUpdateDraftTable = 'UPDATE ibl_draft 
         SET `player` = "' . $playerToBeDrafted . '", 
               `date` = "' . $date . '" 
        WHERE `round` = "' . $draft_round . '" 
           AND `pick` = "' . $draft_pick . '"';
    $resultUpdateDraftTable = $db->sql_query($queryUpdateDraftTable);
    
    $queryUpdateRookieTable = 'UPDATE `ibl_scout_rookieratings`
          SET `team` = "' . $teamname . '", 
           `drafted` = "1"
        WHERE `name` = "' . $playerToBeDrafted . '"';
    $resultUpdateRookieTable = $db->sql_query($queryUpdateRookieTable);

    if ($resultUpdateDraftTable AND $resultUpdateRookieTable) {
        $message = "With pick #$draft_pick in round $draft_round of the $currentSeasonEndingYear IBL Draft, the **" . $teamname . "** select **" . $playerToBeDrafted . "!**";
        echo "$message<p>
        <a href=\"modules.php?name=College_Scouting\">Go back to the Draft module</a>";
    
        $queryTeamOnTheClock = "SELECT team from ibl_draft WHERE player = '' ORDER BY round ASC, pick ASC LIMIT 1";
        $resultTeamOnTheClock = $db->sql_query($queryTeamOnTheClock);
        $teamOnTheClock = $db->sql_result($resultTeamOnTheClock, 0);

        if ($teamOnTheClock != NULL) {
            $queryDiscordIDOfTeamOnTheClock = "SELECT discordID from ibl_team_info WHERE team_name = '$teamOnTheClock' LIMIT 1;";
            $resultDiscordIDOfTeamOnTheClock = $db->sql_query($queryDiscordIDOfTeamOnTheClock);
            $discordIDOfTeamOnTheClock = $db->sql_result($resultDiscordIDOfTeamOnTheClock, 0);
    
            $message .= '
    **<@!' . $discordIDOfTeamOnTheClock . '>** is on the clock!';
        } else {
            $message .= "
    **🏁 __The $currentSeasonEndingYear IBL Draft has officially concluded!__ 🏁**";
        }

        Discord::postToChannel('#draft-picks', $message);
    } else {
        echo "Oops, something went wrong, and at least one of the draft database tables wasn't updated.<p>
            Let A-Jay know what happened and he'll look into it.<p>
            
            <a href=\"modules.php?name=College_Scouting\">Go back to the Draft module</a>";
    }
} elseif ($playerToBeDrafted == NULL) {
    echo "Oops, you didn't select a player.<p>
        <a href=\"modules.php?name=College_Scouting\">Click here to return to the Draft module</a> and please select a player before hitting the Draft button.";
} elseif ($currentDraftSelection != NULL) {
    echo "Oops, it looks like you've already drafted a player with this draft pick.<p>
        <a href=\"modules.php?name=College_Scouting\">Click here to return to the Draft module</a> and if it's your turn, try drafting again.";
}