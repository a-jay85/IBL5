<?php

require 'mainfile.php';
$season = new Season($db);

$queryString = "";
$successText = "";

if (isset($_POST['query'])) {
    switch ($_POST['query']) {
        case 'Insert new `ibl_heat_win_loss` database entries':
            $currentSeasonHEATYear = $season->beginningYear;
            $queryHEATEntriesAlreadyExist = "SELECT currentname FROM ibl_heat_win_loss WHERE year = $currentSeasonHEATYear;";
            $resultHEATEntriesAlreadyExist = $db->sql_query($queryHEATEntriesAlreadyExist);

            if ($db->sql_numrows($resultHEATEntriesAlreadyExist) == 0) {
                $queryTeamNames = "SELECT team_name FROM ibl_team_info WHERE teamid != 35 ORDER BY teamid ASC;";
                $resultTeamNames = $db->sql_query($queryTeamNames);
                $numTeamNames = $db->sql_numrows($resultTeamNames);
    
                $i = 0;
                while ($i < $numTeamNames) {
                    $values .= "($currentSeasonHEATYear, '" . $db->sql_result($resultTeamNames, $i) . "', '" . $db->sql_result($resultTeamNames, $i) . "', 0, 0)";
                    if ($i < $numTeamNames - 1) {
                        $values .= ", ";
                    }
                    $i++;
                }
    
                $queryString = "INSERT INTO ibl_heat_win_loss (`year`, `currentname`, `namethatyear`, `wins`, `losses`) VALUES $values;";
                $successText = "New `ibl_heat_win_loss` database entries were inserted for each team for the $currentSeasonHEATYear season.";
            } else {
                $failureText = "`ibl_heat_win_loss` database entries already exist for the $currentSeasonHEATYear season! New entries were NOT inserted.";
            }
            break;
        case 'Set Season Phase':
            if (isset($_POST['SeasonPhase'])) {
                $queryString = "UPDATE ibl_settings SET value = '{$_POST['SeasonPhase']}' WHERE name = 'Current Season Phase';";
            }
            $successText = "Season Phase has been set to {$_POST['SeasonPhase']}.";
            break;
        case 'Set Waiver Wire Status':
            if (isset($_POST['Waivers'])) {
                $queryString = "UPDATE ibl_settings SET value = '{$_POST['Waivers']}' WHERE name = 'Allow Waiver Moves';";
            }
            $successText = "Waiver Wire Status has been set to {$_POST['Waivers']}.";
            break;
        case 'Set Allow Trades Status':
            if (isset($_POST['Trades'])) {
                $queryString = "UPDATE ibl_settings SET value = '{$_POST['Trades']}' WHERE name = 'Allow Trades';";
            }
            $successText = "Allow Trades Status has been set to {$_POST['Trades']}.";
            break;
        case 'Reset All Contract Extensions':
            $queryString = "UPDATE ibl_team_info SET Used_Extension_This_Season = 0;";
            $successText = "All teams' contract extensions have been reset.";
            break;
        case 'Reset All MLEs/LLEs':
            $queryString = "UPDATE ibl_team_info SET HasMLE = 1, HasLLE = 1;";
            $successText = "All teams' MLEs and LLEs have been reset.";
            break;
        case 'Set all players on waivers to Free Agents and reset their Bird years':
            $queryString = "UPDATE ibl_plr SET teamname = 'Free Agents', bird = 0 WHERE retired != 1 AND ordinal >= 960;";
            $successText = "All players currently on waivers have their teamname set to Free Agents and 0 Bird years.";
            break;
        case 'Set Free Agency factors for PFW':
            if ($season->phase == 'Draft' or $season->phase == 'Free Agency') {
                $queryString = "UPDATE ibl_team_info info, ibl_power power
                    SET Contract_Wins = power.win,
                    	Contract_Losses = power.loss
                    WHERE power.TeamID = info.teamid;";
                $successText = "The columns that affect each team's Play For Winner demand factor have been updated to match this past season's ($season->endingYear) win/loss records.";
            } else {
                $failureText = "Sorry, that button can only be used during the Draft or Free Agency.<br>
                    The FA demands formula requires the current season to be finished before calculating factors.";
            }
            break;
        case 'Deactivate Player and Season Leaders modules for Trivia':
            $queryString = "UPDATE nuke_modules SET active = 0 WHERE title = 'Player' OR title = 'Season_Leaders';";
            $successText = "Player and Season Leaders modules have been deactivated.";
            break;
        case 'Activate Player and Season Leaders modules after Trivia':
            $queryString = "UPDATE nuke_modules SET active = 1 WHERE title = 'Player' OR title = 'Season_Leaders';";
            $successText = "Player and Season Leaders modules have been activated.";
            break;
    }

    if ($db->sql_query($queryString)) {
        $querySuccessful = true;
        if (isset($_POST['SeasonPhase'])) {
            $season->phase = $_POST['SeasonPhase'];
        }
        if (isset($_POST['Waivers'])) {
            $season->allowWaivers = $_POST['Waivers'];
        }
        if (isset($_POST['Trades'])) {
            $season->allowTrades = $_POST['Trades'];
        }
    } else {
        $querySuccessful = false;
    }
    ;
}

echo "
<HTML>
<HEAD>
    <TITLE>IBLv5 Control Panel</TITLE>
</HEAD>
<BODY>";

echo "<FORM action=\"leagueControlPanel.php\" method=\"POST\">
    <select name=\"SeasonPhase\">
        <option value = \"Preseason\"" . ($season->phase == "Preseason" ? " SELECTED" : "") . ">Preseason</option>
        <option value = \"HEAT\"" . ($season->phase == "HEAT" ? " SELECTED" : "") . ">HEAT</option>
        <option value = \"Regular Season\"" . ($season->phase == "Regular Season" ? " SELECTED" : "") . ">Regular Season</option>
        <option value = \"Playoffs\"" . ($season->phase == "Playoffs" ? " SELECTED" : "") . ">Playoffs</option>
        <option value = \"Draft\"" . ($season->phase == "Draft" ? " SELECTED" : "") . ">Draft</option>
        <option value = \"Free Agency\"" . ($season->phase == "Free Agency" ? " SELECTED" : "") . ">Free Agency</option>
    </select>
    <INPUT type='submit' name='query' value='Set Season Phase'><p>
    <A HREF=\"statLeaders.php\">Season Highs</A><p>";

switch ($season->phase) {
    case 'Preseason':
        echo "<A HREF=\"updateAllTheThings.php\">Update All The Things</A><p>
            <A HREF=\"scoParser.php\">Run scoParser.php</A><p>
            <select name=\"Waivers\">
                <option value = \"Yes\"" . ($season->allowWaivers == "Yes" ? " SELECTED" : "") . ">Yes</option>
                <option value = \"No\"" . ($season->allowWaivers == "No" ? " SELECTED" : "") . ">No</option>
            </select>
            <INPUT type='submit' name='query' value='Set Waiver Wire Status'><p>
            <INPUT type='submit' name='query' value='Set all players on waivers to Free Agents and reset their Bird years'><p>";
        break;
    case 'HEAT':
        echo "<A HREF=\"updateAllTheThings.php\">Update All The Things</A><p>
            <A HREF=\"scoParser.php\">Run scoParser.php</A><p>
            <A HREF=\"heatupdateboth.php\">Update HEAT Leaderboards</A><p>
            <A HREF=\"history_update.php\">IBL History Update</A><p>
            <INPUT type='submit' name='query' value='Insert new `ibl_heat_win_loss` database entries'><p>";
        break;
    case 'Regular Season':
        echo "<A HREF=\"updateAllTheThings.php\">Update All The Things</A><p>
            <A HREF=\"scoParser.php\">Run scoParser.php</A><p>
            <A HREF=\"asg_vote_reset.php\">Reset All-Star Voting</A><p>
            <A HREF=\"eoy_vote_reset.php\">Reset End of the Year Voting</A><p>
            <select name=\"Trades\">
                <option value = \"Yes\"" . ($season->allowTrades == "Yes" ? " SELECTED" : "") . ">Yes</option>
                <option value = \"No\"" . ($season->allowTrades == "No" ? " SELECTED" : "") . ">No</option>
            </select>
            <INPUT type='submit' name='query' value='Set Allow Trades Status'><p>";
        break;
    case 'Playoffs':
        echo "<A HREF=\"updateAllTheThings.php\">Update All The Things</A><p>
            <A HREF=\"scoParser.php\">Run scoParser.php</A><p>
            <A HREF=\"eoy_vote_reset.php\">Reset End of the Year Voting</A><p>
            <select name=\"Trades\">
                <option value = \"Yes\"" . ($season->allowTrades == "Yes" ? " SELECTED" : "") . ">Yes</option>
                <option value = \"No\"" . ($season->allowTrades == "No" ? " SELECTED" : "") . ">No</option>
            </select>
            <INPUT type='submit' name='query' value='Set Allow Trades Status'><p>";
        break;
    case 'Draft':
        echo "<A HREF=\"playoffupdate.php\">Playoff Leaderboard Update #1</A><p>
            <A HREF=\"playofflbupdate.php\">Playoff Leaderboard Update #2</A><p>
            <A HREF=\"seasonlbupdate.php\">Season Leaderboard Update</A><p>
            <A HREF=\"history_update.php\">IBL History Update</A><p>";
        break;
    case 'Free Agency':
        echo "<INPUT type='submit' name='query' value='Reset All Contract Extensions'><p>
            <INPUT type='submit' name='query' value='Reset All MLEs/LLEs'><p>
            <INPUT type='submit' name='query' value='Set Free Agency factors for PFW'><p>
            <A HREF=\"tradition.php\">Set Free Agency factors for Tradition</A><p>
            <INPUT type='submit' name='query' value='Set all players on waivers to Free Agents and reset their Bird years'><p>";
        break;
}

echo "<INPUT type='submit' name='query' value='Deactivate Player and Season Leaders modules for Trivia'><p>
    <INPUT type='submit' name='query' value='Activate Player and Season Leaders modules after Trivia'><p>
    </FORM><p><hr><p>";

if ($querySuccessful == true) {
    echo "<code>" . $queryString . "</code>";
    echo "<p>";
    echo "<b>" . $successText . "</b>";
} elseif (!isset($_POST['query'])) {
    // Do/display nothing on an initial page load
} else {
    echo "Oops, something went wrong:<p>
    <FONT color=red>$failureText</FONT><p>
    Let A-Jay know what you were trying to do and he'll look into it.";
}
;

echo "
</BODY>
</HTML>";
