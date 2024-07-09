<?php

global $db, $cookie;
$sharedFunctions = new Shared($db);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$pagetitle = "- $module_name";

$username = $cookie[1];
$userTeam = Team::withTeamName($db, $sharedFunctions->getTeamnameFromUsername($username));

$queryAllTeams = "SELECT * FROM ibl_team_info WHERE teamid != 35;";
$resultAllTeams = $db->sql_query($queryAllTeams);
$numteams = $db->sql_numrows($resultAllTeams);

$t = 0;
foreach ($resultAllTeams as $teamRow) {
    $team = Team::withTeamRow($db, $teamRow);
    $teamStats = TeamStats::withTeamName($db, $team->name);

    if ($userTeam->teamID == $team->teamID) {
        $trSubstitute = "<tr bgcolor=#DDDD00 align=right>";
    } else {
        $trSubstitute = "<tr align=right>";
    }

    $offense_totals .= "$trSubstitute
        <td bgcolor=\"$team->color1\">
            <a href=\"modules.php?name=Team&op=team&tid=$team->teamID\"><font color=\"$team->color2\">$team->city $team->name Offense</font></a>
        </td>
        <td>$teamStats->seasonOffenseGamesPlayed</td>
        <td>$teamStats->seasonOffenseTotalFieldGoalsMade</td>
        <td>$teamStats->seasonOffenseTotalFieldGoalsAttempted</td>
        <td>$teamStats->seasonOffenseTotalFreeThrowsMade</td>
        <td>$teamStats->seasonOffenseTotalFreeThrowsAttempted</td>
        <td>$teamStats->seasonOffenseTotalThreePointersMade</td>
        <td>$teamStats->seasonOffenseTotalThreePointersAttempted</td>
        <td>$teamStats->seasonOffenseTotalOffensiveRebounds</td>
        <td>$teamStats->seasonOffenseTotalRebounds</td>
        <td>$teamStats->seasonOffenseTotalAssists</td>
        <td>$teamStats->seasonOffenseTotalSteals</td>
        <td>$teamStats->seasonOffenseTotalTurnovers</td>
        <td>$teamStats->seasonOffenseTotalBlocks</td>
        <td>$teamStats->seasonOffenseTotalPersonalFouls</td>
        <td>$teamStats->seasonOffenseTotalPoints</td>
    </tr>";

    $offense_averages .= "$trSubstitute
        <td bgcolor=\"$team->color1\">
            <a href=\"modules.php?name=Team&op=team&tid=$team->teamID\"><font color=\"$team->color2\">$team->city $team->name Offense</font></a>
        </td>
        <td>$teamStats->seasonOffenseFieldGoalsMadePerGame</td>
        <td>$teamStats->seasonOffenseFieldGoalsAttemptedPerGame</td>
        <td>$teamStats->seasonOffenseFieldGoalPercentage</td>
        <td>$teamStats->seasonOffenseFreeThrowsMadePerGame</td>
        <td>$teamStats->seasonOffenseFreeThrowsAttemptedPerGame</td>
        <td>$teamStats->seasonOffenseFreeThrowPercentage</td>
        <td>$teamStats->seasonOffenseThreePointersMadePerGame</td>
        <td>$teamStats->seasonOffenseThreePointersAttemptedPerGame</td>
        <td>$teamStats->seasonOffenseThreePointPercentage</td>
        <td>$teamStats->seasonOffenseOffensiveReboundsPerGame</td>
        <td>$teamStats->seasonOffenseTotalReboundsPerGame</td>
        <td>$teamStats->seasonOffenseAssistsPerGame</td>
        <td>$teamStats->seasonOffenseStealsPerGame</td>
        <td>$teamStats->seasonOffenseTurnoversPerGame</td>
        <td>$teamStats->seasonOffenseBlocksPerGame</td>
        <td>$teamStats->seasonOffensePersonalFoulsPerGame</td>
        <td>$teamStats->seasonOffensePointsPerGame</td>
    </tr>";

    $defense_totals .= "$trSubstitute
        <td bgcolor=\"$team->color1\">
            <a href=\"modules.php?name=Team&op=team&tid=$team->teamID\"><font color=\"$team->color2\">$team->city $team->name Offense</font></a>
        </td>
        <td>$teamStats->seasonDefenseGamesPlayed</td>
        <td>$teamStats->seasonDefenseTotalFieldGoalsMade</td>
        <td>$teamStats->seasonDefenseTotalFieldGoalsAttempted</td>
        <td>$teamStats->seasonDefenseTotalFreeThrowsMade</td>
        <td>$teamStats->seasonDefenseTotalFreeThrowsAttempted</td>
        <td>$teamStats->seasonDefenseTotalThreePointersMade</td>
        <td>$teamStats->seasonDefenseTotalThreePointersAttempted</td>
        <td>$teamStats->seasonDefenseTotalOffensiveRebounds</td>
        <td>$teamStats->seasonDefenseTotalRebounds</td>
        <td>$teamStats->seasonDefenseTotalAssists</td>
        <td>$teamStats->seasonDefenseTotalSteals</td>
        <td>$teamStats->seasonDefenseTotalTurnovers</td>
        <td>$teamStats->seasonDefenseTotalBlocks</td>
        <td>$teamStats->seasonDefenseTotalPersonalFouls</td>
        <td>$teamStats->seasonDefenseTotalPoints</td>
    </tr>";

    $defense_averages .= "$trSubstitute
        <td bgcolor=\"$team->color1\">
            <a href=\"modules.php?name=Team&op=team&tid=$team->teamID\"><font color=\"$team->color2\">$team->city $team->name Defense</font></a>
        </td>
        <td>$teamStats->seasonDefenseFieldGoalsMadePerGame</td>
        <td>$teamStats->seasonDefenseFieldGoalsAttemptedPerGame</td>
        <td>$teamStats->seasonDefenseFieldGoalPercentage</td>
        <td>$teamStats->seasonDefenseFreeThrowsMadePerGame</td>
        <td>$teamStats->seasonDefenseFreeThrowsAttemptedPerGame</td>
        <td>$teamStats->seasonDefenseFreeThrowPercentage</td>
        <td>$teamStats->seasonDefenseThreePointersMadePerGame</td>
        <td>$teamStats->seasonDefenseThreePointersAttemptedPerGame</td>
        <td>$teamStats->seasonDefenseThreePointPercentage</td>
        <td>$teamStats->seasonDefenseOffensiveReboundsPerGame</td>
        <td>$teamStats->seasonDefenseTotalReboundsPerGame</td>
        <td>$teamStats->seasonDefenseAssistsPerGame</td>
        <td>$teamStats->seasonDefenseStealsPerGame</td>
        <td>$teamStats->seasonDefenseTurnoversPerGame</td>
        <td>$teamStats->seasonDefenseBlocksPerGame</td>
        <td>$teamStats->seasonDefensePersonalFoulsPerGame</td>
        <td>$teamStats->seasonDefensePointsPerGame</td>
    </tr>";

    $teamHeaderCells[$t] = "<td bgcolor=\"$team->color1\">
        <a href=\"modules.php?name=Team&op=team&tid=$team->teamID\"><font color=\"$team->color2\">$team->city $team->name Diff</font></a>
    </td>";

    $teamOffenseAveragesArray[$t] = array(
        $team->name,
        $teamStats->seasonOffenseFieldGoalsMadePerGame,
        $teamStats->seasonOffenseFieldGoalsAttemptedPerGame,
        $teamStats->seasonOffenseFieldGoalPercentage,
        $teamStats->seasonOffenseFreeThrowsMadePerGame,
        $teamStats->seasonOffenseFreeThrowsAttemptedPerGame,
        $teamStats->seasonOffenseFreeThrowPercentage,
        $teamStats->seasonOffenseThreePointersMadePerGame,
        $teamStats->seasonOffenseThreePointersAttemptedPerGame,
        $teamStats->seasonOffenseThreePointPercentage,
        $teamStats->seasonOffenseOffensiveReboundsPerGame,
        $teamStats->seasonOffenseTotalReboundsPerGame,
        $teamStats->seasonOffenseAssistsPerGame,
        $teamStats->seasonOffenseStealsPerGame,
        $teamStats->seasonOffenseTurnoversPerGame,
        $teamStats->seasonOffenseBlocksPerGame,
        $teamStats->seasonOffensePersonalFoulsPerGame,
        $teamStats->seasonOffensePointsPerGame
    );

    $teamDefenseAveragesArray[$t] = array(
        $team->name,
        $teamStats->seasonDefenseFieldGoalsMadePerGame,
        $teamStats->seasonDefenseFieldGoalsAttemptedPerGame,
        $teamStats->seasonDefenseFieldGoalPercentage,
        $teamStats->seasonDefenseFreeThrowsMadePerGame,
        $teamStats->seasonDefenseFreeThrowsAttemptedPerGame,
        $teamStats->seasonDefenseFreeThrowPercentage,
        $teamStats->seasonDefenseThreePointersMadePerGame,
        $teamStats->seasonDefenseThreePointersAttemptedPerGame,
        $teamStats->seasonDefenseThreePointPercentage,
        $teamStats->seasonDefenseOffensiveReboundsPerGame,
        $teamStats->seasonDefenseTotalReboundsPerGame,
        $teamStats->seasonDefenseAssistsPerGame,
        $teamStats->seasonDefenseStealsPerGame,
        $teamStats->seasonDefenseTurnoversPerGame,
        $teamStats->seasonDefenseBlocksPerGame,
        $teamStats->seasonDefensePersonalFoulsPerGame,
        $teamStats->seasonDefensePointsPerGame
    );

    $leagueOffenseGamesPlayed += $teamStats->seasonOffenseGamesPlayed;
    $leagueOffenseTotalFieldGoalsMade += $teamStats->seasonOffenseTotalFieldGoalsMade;
    $leagueOffenseTotalFieldGoalsAttempted += $teamStats->seasonOffenseTotalFieldGoalsAttempted;
    $leagueOffenseTotalFreeThrowsMade += $teamStats->seasonOffenseTotalFreeThrowsMade;
    $leagueOffenseTotalFreeThrowsAttempted += $teamStats->seasonOffenseTotalFreeThrowsAttempted;
    $leagueOffenseTotalThreePointersMade += $teamStats->seasonOffenseTotalThreePointersMade;
    $leagueOffenseTotalThreePointersAttempted += $teamStats->seasonOffenseTotalThreePointersAttempted;
    $leagueOffenseTotalOffensiveRebounds += $teamStats->seasonOffenseTotalOffensiveRebounds;
    $leagueOffenseTotalRebounds += $teamStats->seasonOffenseTotalRebounds;
    $leagueOffenseTotalAssists += $teamStats->seasonOffenseTotalAssists;
    $leagueOffenseTotalSteals += $teamStats->seasonOffenseTotalSteals;
    $leagueOffenseTotalTurnovers += $teamStats->seasonOffenseTotalTurnovers;
    $leagueOffenseTotalBlocks += $teamStats->seasonOffenseTotalBlocks;
    $leagueOffenseTotalPersonalFouls += $teamStats->seasonOffenseTotalPersonalFouls;
    $leagueOffenseTotalPoints += $teamStats->seasonOffenseTotalPoints;

    $t++;
}

$leagueOffenseFieldGoalsMadePerGame = ($leagueOffenseGamesPlayed != 0) ? number_format($leagueOffenseTotalFieldGoalsMade / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseFieldGoalsAttemptedPerGame = ($leagueOffenseGamesPlayed != 0) ? number_format($leagueOffenseTotalFieldGoalsAttempted / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseFieldGoalPercentage = ($leagueOffenseGamesPlayed != 0) ? number_format($leagueOffenseTotalFieldGoalsMade / $leagueOffenseTotalFieldGoalsAttempted, 3) : "0.00";
$leagueOffenseFreeThrowsMadePerGame = ($leagueOffenseGamesPlayed != 0) ? number_format($leagueOffenseTotalFreeThrowsMade / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseFreeThrowsAttemptedPerGame = ($leagueOffenseGamesPlayed != 0) ? number_format($leagueOffenseTotalFreeThrowsAttempted / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseFreeThrowPercentage = ($leagueOffenseGamesPlayed != 0) ? number_format($leagueOffenseTotalFreeThrowsMade / $leagueOffenseTotalFreeThrowsAttempted, 3) : "0.00";
$leagueOffenseThreePointersMadePerGame = ($leagueOffenseGamesPlayed != 0) ? number_format($leagueOffenseTotalThreePointersMade / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseThreePointersAttemptedPerGame = ($leagueOffenseGamesPlayed != 0) ? number_format($leagueOffenseTotalThreePointersAttempted / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseThreePointPercentage = ($leagueOffenseGamesPlayed != 0) ? number_format($leagueOffenseTotalThreePointersMade / $leagueOffenseTotalThreePointersAttempted, 3) : "0.00";
$leagueOffenseOffensiveReboundsPerGame = ($leagueOffenseGamesPlayed != 0) ? number_format($leagueOffenseTotalOffensiveRebounds / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseTotalReboundsPerGame = ($leagueOffenseGamesPlayed != 0) ? number_format($leagueOffenseTotalRebounds / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseAssistsPerGame = ($leagueOffenseGamesPlayed != 0) ? number_format($leagueOffenseTotalAssists / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseStealsPerGame = ($leagueOffenseGamesPlayed != 0) ? number_format($leagueOffenseTotalSteals / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseTurnoversPerGame = ($leagueOffenseGamesPlayed != 0) ? number_format($leagueOffenseTotalTurnovers / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseBlocksPerGame = ($leagueOffenseGamesPlayed != 0) ? number_format($leagueOffenseTotalBlocks / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffensePersonalFoulsPerGame = ($leagueOffenseGamesPlayed != 0) ? number_format($leagueOffenseTotalPersonalFouls / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffensePointsPerGame = ($leagueOffenseGamesPlayed != 0) ? number_format($leagueOffenseTotalPoints / $leagueOffenseGamesPlayed, 1) : "0.0";

$league_totals = "<tr style=\"font-weight:bold\">
    <td>LEAGUE TOTALS</td>
    <td>$leagueOffenseGamesPlayed</td>
    <td>$leagueOffenseTotalFieldGoalsMade</td>
    <td>$leagueOffenseTotalFieldGoalsAttempted</td>
    <td>$leagueOffenseTotalFreeThrowsMade</td>
    <td>$leagueOffenseTotalFreeThrowsAttempted</td>
    <td>$leagueOffenseTotalThreePointersMade</td>
    <td>$leagueOffenseTotalThreePointersAttempted</td>
    <td>$leagueOffenseTotalOffensiveRebounds</td>
    <td>$leagueOffenseTotalRebounds</td>
    <td>$leagueOffenseTotalAssists</td>
    <td>$leagueOffenseTotalSteals</td>
    <td>$leagueOffenseTotalTurnovers</td>
    <td>$leagueOffenseTotalBlocks</td>
    <td>$leagueOffenseTotalPersonalFouls</td>
    <td>$leagueOffenseTotalPoints</td>
</tr>";

$league_averages = "<tr style=\"font-weight:bold\">
    <td>LEAGUE AVERAGES</td>
    <td>$leagueOffenseFieldGoalsMadePerGame</td>
    <td>$leagueOffenseFieldGoalsAttemptedPerGame</td>
    <td>$leagueOffenseFieldGoalPercentage</td>
    <td>$leagueOffenseFreeThrowsMadePerGame</td>
    <td>$leagueOffenseFreeThrowsAttemptedPerGame</td>
    <td>$leagueOffenseFreeThrowPercentage</td>
    <td>$leagueOffenseThreePointersMadePerGame</td>
    <td>$leagueOffenseThreePointersAttemptedPerGame</td>
    <td>$leagueOffenseThreePointPercentage</td>
    <td>$leagueOffenseOffensiveReboundsPerGame</td>
    <td>$leagueOffenseTotalReboundsPerGame</td>
    <td>$leagueOffenseAssistsPerGame</td>
    <td>$leagueOffenseStealsPerGame</td>
    <td>$leagueOffenseTurnoversPerGame</td>
    <td>$leagueOffenseBlocksPerGame</td>
    <td>$leagueOffensePersonalFoulsPerGame</td>
    <td>$leagueOffensePointsPerGame</td>
</tr>";

$i = 0;
while ($i < $numteams) {
    if ($userTeam->name == $teamOffenseAveragesArray[$i][0]) {
        $trSubstitute = "<tr bgcolor=#DDDD00 align=right>";
    } else {
        $trSubstitute = "<tr align=right>";
    }

    $league_differentials .= $trSubstitute;
    $league_differentials .= $teamHeaderCells[$i];

    $j = 1;
    while ($j < sizeof($teamOffenseAveragesArray[$i])) {
        $differential = $teamOffenseAveragesArray[$i][$j] - $teamDefenseAveragesArray[$i][$j];
        $league_differentials .= "<td align='right'>" . number_format($differential, 2) . "</td>";

        $j++;
    }

    $league_differentials .= "</tr>";

    $i++;
}

NukeHeader::header();
OpenTable();

echo "<center>
    <h1>League-wide Statistics</h1>

    <h2>Team Offense Totals</h2>
    <table class=\"sortable\">
    <thead><tr><th>Team</th><th>Gm</th><th>FGM</th><th>FGA</th><th>FTM</th><th>FTA</th><th>3GM</th><th>3GA</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
    <tbody>$offense_totals</tbody>
    <tfoot>$league_totals</tfoot>
    </table>

    <h2>Team Defense Totals</h2>
    <table class=\"sortable\">
    <thead><tr><th>Team</th><th>Gm</th><th>FGM</th><th>FGA</th><th>FTM</th><th>FTA</th><th>3GM</th><th>3GA</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
    <tbody>$defense_totals</tbody>
    <tfoot>$league_totals</tfoot>
    </table>

    <h2>Team Offense Averages</h2>
    <table class=\"sortable\">
    <thead><tr><th>Team</th><th>FGM</th><th>FGA</th><th>FGP</th><th>FTM</th><th>FTA</th><th>FTP</th><th>3GM</th><th>3GA</th><th>3GP</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
    <tbody>$offense_averages</tbody>
    <tfoot>$league_averages</tfoot>
    </table>

    <h2>Team Defense Averages</h2>
    <table class=\"sortable\">
    <thead><tr><th>Team</th><th>FGM</th><th>FGA</th><th>FGP</th><th>FTM</th><th>FTA</th><th>FTP</th><th>3GM</th><th>3GA</th><th>3GP</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
    <tbody>$defense_averages</tbody>
    <tfoot>$league_averages</tfoot>
    </table>

    <h2>Team Off/Def Average Differentials</h2>
    <table class=\"sortable\">
    <thead><tr><th>Team</th><th>FGM</th><th>FGA</th><th>FGP</th><th>FTM</th><th>FTA</th><th>FTP</th><th>3GM</th><th>3GA</th><th>3GP</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
    <tbody>$league_differentials</tbody>
    </table>";

CloseTable();
include "footer.php";
