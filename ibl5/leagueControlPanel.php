<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';
$season = new Season($mysqli_db);

$queryString = "";
$successText = "";
$querySuccessful = false;

if (isset($_POST['query'])) {
    switch ($_POST['query']) {
        case 'Activate Player and Season Leaders modules after Trivia':
            $queryString = "UPDATE nuke_modules SET active = 1 WHERE title = 'Player' OR title = 'Season_Leaders';";
            $successText = "Player and Season Leaders modules have been activated.";
            break;
        case 'Deactivate Player and Season Leaders modules for Trivia':
            $queryString = "UPDATE nuke_modules SET active = 0 WHERE title = 'Player' OR title = 'Season_Leaders';";
            $successText = "Player and Season Leaders modules have been deactivated.";
            break;
        case 'Insert new `ibl_heat_win_loss` database entries':
            $currentSeasonHEATYear = $season->beginningYear;
            // Check if entries already exist for this season
            $stmtCheck = $mysqli_db->prepare("SELECT currentname FROM ibl_heat_win_loss WHERE year = ?");
            $stmtCheck->bind_param("i", $currentSeasonHEATYear);
            $stmtCheck->execute();
            $resultCheck = $stmtCheck->get_result();
            $stmtCheck->close();

            if ($resultCheck->num_rows == 0) {
                // Fetch all team names
                $stmtTeams = $mysqli_db->prepare("SELECT team_name FROM ibl_team_info WHERE teamid != ? ORDER BY teamid ASC");
                $freeAgentsTeamId = League::FREE_AGENTS_TEAMID;
                $stmtTeams->bind_param("i", $freeAgentsTeamId);
                $stmtTeams->execute();
                $resultTeams = $stmtTeams->get_result();
                $stmtTeams->close();

                $values = '';
                $i = 0;
                while ($row = $resultTeams->fetch_assoc()) {
                    $teamName = $row['team_name'];
                    $values .= "($currentSeasonHEATYear, '$teamName', '$teamName', 0, 0)";
                    if ($resultTeams->num_rows > $i + 1) {
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
        case 'Reset All Contract Extensions':
            $queryString = "UPDATE ibl_team_info SET Used_Extension_This_Season = 0;";
            $successText = "All teams' contract extensions have been reset.";
            break;
        case 'Reset All MLEs/LLEs':
            $queryString = "UPDATE ibl_team_info SET HasMLE = 1, HasLLE = 1;";
            $successText = "All teams' MLEs and LLEs have been reset.";
            break;
        case 'Reset All-Star Voting':
            $stmtASG = $mysqli_db->prepare("UPDATE ibl_votes_ASG SET East_F1 = NULL, East_F2 = NULL, East_F3 = NULL, East_F4 = NULL,
                West_F1 = NULL, West_F2 = NULL, West_F3 = NULL, West_F4 = NULL,
                East_B1 = NULL, East_B2 = NULL, East_B3 = NULL, East_B4 = NULL,
                West_B1 = NULL, West_B2 = NULL, West_B3 = NULL, West_B4 = NULL");
            $stmtASG->execute();
            $stmtASG->close();
            
            $stmtSettings = $mysqli_db->prepare("UPDATE ibl_settings SET value = 'Yes' WHERE name = 'ASG Voting'");
            $stmtSettings->execute();
            $stmtSettings->close();
            
            $stmtTeamHistory = $mysqli_db->prepare("UPDATE ibl_team_history SET asg_vote = 'No Vote'");
            $stmtTeamHistory->execute();
            $stmtTeamHistory->close();
            
            $successText = "ASG Voting has been reset!";
            $querySuccessful = true;
            break;
        case 'Reset End of the Year Voting':
            $stmtEOY = $mysqli_db->prepare("UPDATE ibl_votes_EOY SET MVP_1 = NULL, MVP_2 = NULL, MVP_3 = NULL,
                Six_1 = NULL, Six_2 = NULL, Six_3 = NULL,
                ROY_1 = NULL, ROY_2 = NULL, ROY_3 = NULL,
                GM_1 = NULL, GM_2 = NULL, GM_3 = NULL");
            $stmtEOY->execute();
            $stmtEOY->close();
            
            $stmtSettings2 = $mysqli_db->prepare("UPDATE ibl_settings SET value = 'Yes' WHERE name = 'EOY Voting'");
            $stmtSettings2->execute();
            $stmtSettings2->close();
            
            $stmtTeamHistory2 = $mysqli_db->prepare("UPDATE ibl_team_history SET eoy_vote = 'No Vote'");
            $stmtTeamHistory2->execute();
            $stmtTeamHistory2->close();
            
            $successText = "EOY Voting has been reset!";
            $querySuccessful = true;
            break;
        case 'Set all players on waivers to Free Agents and reset their Bird years':
            $queryString = "UPDATE ibl_plr SET teamname = 'Free Agents', bird = 0 WHERE retired != 1 AND ordinal > " . JSB::WAIVERS_ORDINAL . ";";
            $successText = "All players currently on waivers have their teamname set to Free Agents and 0 Bird years.";
            break;
        case 'Set Allow Trades Status':
            if (isset($_POST['Trades'])) {
                $trades = $_POST['Trades'];
                $stmtTrades = $mysqli_db->prepare("UPDATE ibl_settings SET value = ? WHERE name = 'Allow Trades'");
                $stmtTrades->bind_param("s", $trades);
                $stmtTrades->execute();
                $stmtTrades->close();
                $querySuccessful = true;
            }
            $successText = "Allow Trades Status has been set to {$_POST['Trades']}.";
            break;
        case 'Set Free Agency factors for PFW':
            if ($season->phase == 'Draft' or $season->phase == 'Free Agency') {
                $queryString = "UPDATE ibl_team_info info, ibl_power power SET Contract_Wins = power.win, Contract_Losses = power.loss WHERE power.TeamID = info.teamid;";
                $successText = "The columns that affect each team's Play For Winner demand factor have been updated to match this past season's ($season->endingYear) win/loss records.";
            } else {
                $failureText = "Sorry, that button can only be used during the Draft or Free Agency.<br>The FA demands formula requires the current season to be finished before calculating factors.";
            }
            break;
        case 'Set Season Phase':
            if (isset($_POST['SeasonPhase'])) {
                $phase = $_POST['SeasonPhase'];
                $stmtPhase = $mysqli_db->prepare("UPDATE ibl_settings SET value = ? WHERE name = 'Current Season Phase'");
                $stmtPhase->bind_param("s", $phase);
                $stmtPhase->execute();
                $stmtPhase->close();
                $querySuccessful = true;
            }
            $successText = "Season Phase has been set to {$_POST['SeasonPhase']}.";
            break;
        case 'Set Sim Length in Days':
            if (isset($_POST['SimLengthInDays'])) {
                $simLength = (int)$_POST['SimLengthInDays'];
                $stmtSim = $mysqli_db->prepare("UPDATE ibl_settings SET value = ? WHERE name = 'Sim Length in Days'");
                $stmtSim->bind_param("i", $simLength);
                $stmtSim->execute();
                $stmtSim->close();
                $querySuccessful = true;
            }
            $successText = "Sim Length in Days has been set to {$_POST['SimLengthInDays']}.";
            break;
        case 'Set Allow Waiver Moves Status':
            if (isset($_POST['Waivers'])) {
                $waivers = $_POST['Waivers'];
                $stmtWaivers = $mysqli_db->prepare("UPDATE ibl_settings SET value = ? WHERE name = 'Allow Waiver Moves'");
                $stmtWaivers->bind_param("s", $waivers);
                $stmtWaivers->execute();
                $stmtWaivers->close();
                $querySuccessful = true;
            }
            $successText = "Allow Waiver Moves Status has been set to {$_POST['Waivers']}.";
            break;
        case 'Toggle Free Agency Notifications':
            if (isset($_POST['FANotifs'])) {
                $notifs = $_POST['FANotifs'];
                $stmtNotifs = $mysqli_db->prepare("UPDATE ibl_settings SET value = ? WHERE name = 'Free Agency Notifications'");
                $stmtNotifs->bind_param("s", $notifs);
                $stmtNotifs->execute();
                $stmtNotifs->close();
                $querySuccessful = true;
            }
            $successText = "Free Agency Notifications are now {$_POST['FANotifs']}.";
            break;
    }

    if ($queryString != NULL) {
        $stmt = $mysqli_db->prepare($queryString);
        if ($stmt === false) {
            $querySuccessful = false;
        } else {
            $querySuccessful = $stmt->execute();
            $stmt->close();
        }
    }
    
    // Reload the season object to reflect the updated database values
    if ($querySuccessful) {
        if (isset($_POST['FANotifs'])) {
            Discord::postToChannel('#free-agency', $successText);
        }
        $season = new Season($mysqli_db);
    }
}

echo "
<HTML>
<HEAD>
    <TITLE>IBLv5 Control Panel</TITLE>
    <style>
        .league-switcher-admin {
            background-color: #f0f0f0;
            border: 2px solid #333;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .league-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 3px;
            font-weight: bold;
            margin-right: 10px;
        }
        .league-badge-ibl {
            background-color: #336699;
            color: white;
        }
        .league-badge-olympics {
            background-color: #d4af37;
            color: black;
        }
    </style>
</HEAD>
<BODY>";

// League switcher
$leagueConfig = $leagueContext->getConfig();
$currentLeague = $leagueContext->getCurrentLeague();
$leagueBadgeClass = $currentLeague === 'ibl' ? 'league-badge-ibl' : 'league-badge-olympics';

ob_start();
?>
<div class='league-switcher-admin'>
    <strong>Current League:</strong>
    <span class='league-badge <?= $leagueBadgeClass ?>'><?= strtoupper($leagueConfig['short_name']) ?></span>
    <span style='margin-left: 20px;'>Switch to: </span>
    <select onchange='window.location.href=this.value' style='padding: 5px; font-size: 14px;'>
        <option value='leagueControlPanel.php?league=ibl'<?= $currentLeague === 'ibl' ? ' selected' : '' ?>>IBL</option>
        <option value='leagueControlPanel.php?league=olympics'<?= $currentLeague === 'olympics' ? ' selected' : '' ?>>Olympics</option>
    </select>
</div>
<?php
echo ob_get_clean();

echo "<FORM action=\"leagueControlPanel.php\" method=\"POST\">";

if ($currentLeague === 'ibl') {
    echo "<select name=\"SeasonPhase\">
        <option value = \"Preseason\"" . ($season->phase == "Preseason" ? " SELECTED" : "") . ">Preseason</option>
        <option value = \"HEAT\"" . ($season->phase == "HEAT" ? " SELECTED" : "") . ">HEAT</option>
        <option value = \"Regular Season\"" . ($season->phase == "Regular Season" ? " SELECTED" : "") . ">Regular Season</option>
        <option value = \"Playoffs\"" . ($season->phase == "Playoffs" ? " SELECTED" : "") . ">Playoffs</option>
        <option value = \"Draft\"" . ($season->phase == "Draft" ? " SELECTED" : "") . ">Draft</option>
        <option value = \"Free Agency\"" . ($season->phase == "Free Agency" ? " SELECTED" : "") . ">Free Agency</option>
    </select>
    <INPUT type='submit' name='query' value='Set Season Phase'><p>";
}

echo "<A HREF=\"/ibl5/pages/seasonHighs.php\">Season Highs</A><p>";

switch ($season->phase) {
    case 'Preseason':
        echo "<A HREF=\"/ibl5/scripts/plrParser.php\">Run plrParser.php</A>
                <br><b>(but make sure you've uploaded the updated PLR file before you run this!)</b><p>
            <A HREF=\"/ibl5/scripts/updateAllTheThings.php\">Update All The Things</A><p>
            <A HREF=\"/ibl5/scripts/scoParser.php\">Run scoParser.php</A><p>
            <select name=\"Waivers\">
                <option value = \"Yes\"" . ($season->allowWaivers == "Yes" ? " SELECTED" : "") . ">Yes</option>
                <option value = \"No\"" . ($season->allowWaivers == "No" ? " SELECTED" : "") . ">No</option>
            </select>
            <INPUT type='submit' name='query' value='Set Allow Waiver Moves Status'><p>
            <INPUT type='submit' name='query' value='Set all players on waivers to Free Agents and reset their Bird years'><p>
            <INPUT type='submit' name='query' value='Reset All Contract Extensions'><p>
            <INPUT type='submit' name='query' value='Reset All MLEs/LLEs'><p>";
        break;
    case 'HEAT':
        echo "<A HREF=\"/ibl5/scripts/plrParser.php\">Run plrParser.php</A>
                <br><b>(but make sure you've uploaded the updated PLR file before you run this!)</b><p>
            <A HREF=\"/ibl5/scripts/updateAllTheThings.php\">Update All The Things</A><p>
            <A HREF=\"/ibl5/scripts/scoParser.php\">Run scoParser.php</A><p>
            <A HREF=\"/ibl5/scripts/heatupdateboth.php\">Update HEAT Leaderboards</A><p>
            <A HREF=\"/ibl5/scripts/history_update.php\">IBL History Update</A><p>
            <INPUT type='submit' name='query' value='Insert new `ibl_heat_win_loss` database entries'><p>";
        break;
    case 'Regular Season':
        echo "<A HREF=\"/ibl5/scripts/plrParser.php\">Run plrParser.php</A>
                <br><b>(but make sure you've uploaded the updated PLR file before you run this!)</b><p>
            <A HREF=\"/ibl5/scripts/updateAllTheThings.php\">Update All The Things</A><p>
            <A HREF=\"/ibl5/scripts/scoParser.php\">Run scoParser.php</A><p>";
        $league = new League($mysqli_db);
        echo "<INPUT type='number' name='SimLengthInDays' min=1 max=180 size=3 value='" . $league->getSimLengthInDays() . "'>
            <INPUT type='submit' name='query' value='Set Sim Length in Days'> <i>
                <br>(you HAVE to CLICK to set the days – you unfortunately can't just hit Return/Enter)<p>";

        if ($currentLeague === 'ibl') {
            echo "<INPUT type='submit' name='query' value='Reset All-Star Voting'><p>
                <INPUT type='submit' name='query' value='Reset End of the Year Voting'><p>
                <select name=\"Trades\">
                    <option value = \"Yes\"" . ($season->allowTrades == "Yes" ? " SELECTED" : "") . ">Yes</option>
                    <option value = \"No\"" . ($season->allowTrades == "No" ? " SELECTED" : "") . ">No</option>
                </select>
                <INPUT type='submit' name='query' value='Set Allow Trades Status'><p>";
        }

        break;
    case 'Playoffs':
        echo "<A HREF=\"/ibl5/scripts/plrParser.php\">Run plrParser.php</A>
                <br><b>(but make sure you've uploaded the updated PLR file before you run this!)</b><p>
            <A HREF=\"/ibl5/scripts/updateAllTheThings.php\">Update All The Things</A><p>
            <A HREF=\"/ibl5/scripts/scoParser.php\">Run scoParser.php</A><p>
            <INPUT type='submit' name='query' value='Reset End of the Year Voting'><p>
            <select name=\"Trades\">
                <option value = \"Yes\"" . ($season->allowTrades == "Yes" ? " SELECTED" : "") . ">Yes</option>
                <option value = \"No\"" . ($season->allowTrades == "No" ? " SELECTED" : "") . ">No</option>
            </select>
            <INPUT type='submit' name='query' value='Set Allow Trades Status'><p>";
        break;
    case 'Draft':
        echo "<A HREF=\"/ibl5/scripts/playoffupdate.php\">Playoff Leaderboard Update #1</A><p>
            <A HREF=\"/ibl5/scripts/playofflbupdate.php\">Playoff Leaderboard Update #2</A><p>
            <A HREF=\"/ibl5/scripts/seasonlbupdate.php\">Season Leaderboard Update</A><p>
            <A HREF=\"/ibl5/scripts/history_update.php\">IBL History Update</A><p>
            <select name=\"Waivers\">
                <option value = \"Yes\"" . ($season->allowWaivers == "Yes" ? " SELECTED" : "") . ">Yes</option>
                <option value = \"No\"" . ($season->allowWaivers == "No" ? " SELECTED" : "") . ">No</option>
            </select>
            <INPUT type='submit' name='query' value='Set Allow Waiver Moves Status'><p>";
        break;
    case 'Free Agency':
        echo "<INPUT type='submit' name='query' value='Reset All Contract Extensions'><p>
            <INPUT type='submit' name='query' value='Reset All MLEs/LLEs'><p>
            <INPUT type='submit' name='query' value='Set Free Agency factors for PFW'><p>
            <A HREF=\"/ibl5/scripts/tradition.php\">Set Free Agency factors for Tradition</A><p>
            <select name=\"FANotifs\">
                <option value = \"On\"" . ($season->freeAgencyNotificationsState == "On" ? " SELECTED" : "") . ">On</option>
                <option value = \"Off\"" . ($season->freeAgencyNotificationsState == "Off" ? " SELECTED" : "") . ">Off</option>
            </select>
            <INPUT type='submit' name='query' value='Toggle Free Agency Notifications'><p>
            <select name=\"Waivers\">
                <option value = \"Yes\"" . ($season->allowWaivers == "Yes" ? " SELECTED" : "") . ">Yes</option>
                <option value = \"No\"" . ($season->allowWaivers == "No" ? " SELECTED" : "") . ">No</option>
            </select>
            <INPUT type='submit' name='query' value='Set Allow Waiver Moves Status'><p>
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

echo "
</BODY>
</HTML>";
