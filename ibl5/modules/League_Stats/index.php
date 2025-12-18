<?php

global $cookie, $mysqli_db;

$sharedFunctions = new Shared($mysqli_db);
$commonRepository = new Services\CommonMysqliRepository($mysqli_db);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$pagetitle = "- $module_name";

$username = $cookie[1];
$userTeam = Team::initialize($mysqli_db, $commonRepository->getTeamnameFromUsername($username));

$league = new League($mysqli_db);
$resultAllTeams = $league->getAllTeamsResult();
$numteams = count($resultAllTeams);

function trHighlight($userTeamID, $operatingTeamID)
{
    if ($userTeamID == $operatingTeamID) {
        return "<tr bgcolor=#FFA align=right>";
    } else {
        return "<tr align=right>";
    }
}

$t = 0;
foreach ($resultAllTeams as $teamRow) {
    $team = Team::initialize($mysqli_db, $teamRow);
    $teamStats = TeamStats::withTeamName($mysqli_db, $team->name);

    $trHighlight = trHighlight($userTeam->teamID, $team->teamID);

    $offense_totals .= "$trHighlight
        <td bgcolor=\"$team->color1\">
            <a href=\"modules.php?name=Team&op=team&teamID=$team->teamID\"><font color=\"$team->color2\">$team->city $team->name Offense</font></a>
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

    $offense_averages .= "$trHighlight
        <td bgcolor=\"$team->color1\">
            <a href=\"modules.php?name=Team&op=team&teamID=$team->teamID\"><font color=\"$team->color2\">$team->city $team->name Offense</font></a>
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

    $defense_totals .= "$trHighlight
        <td bgcolor=\"$team->color1\">
            <a href=\"modules.php?name=Team&op=team&teamID=$team->teamID\"><font color=\"$team->color2\">$team->city $team->name Offense</font></a>
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

    $defense_averages .= "$trHighlight
        <td bgcolor=\"$team->color1\">
            <a href=\"modules.php?name=Team&op=team&teamID=$team->teamID\"><font color=\"$team->color2\">$team->city $team->name Defense</font></a>
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
        <a href=\"modules.php?name=Team&op=team&teamID=$team->teamID\"><font color=\"$team->color2\">$team->city $team->name Diff</font></a>
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

$leagueOffenseFieldGoalsMadePerGame = ($leagueOffenseGamesPlayed) ? number_format($leagueOffenseTotalFieldGoalsMade / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseFieldGoalsAttemptedPerGame = ($leagueOffenseGamesPlayed) ? number_format($leagueOffenseTotalFieldGoalsAttempted / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseFieldGoalPercentage = ($leagueOffenseGamesPlayed) ? number_format($leagueOffenseTotalFieldGoalsMade / $leagueOffenseTotalFieldGoalsAttempted, 3) : "0.000";
$leagueOffenseFreeThrowsMadePerGame = ($leagueOffenseGamesPlayed) ? number_format($leagueOffenseTotalFreeThrowsMade / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseFreeThrowsAttemptedPerGame = ($leagueOffenseGamesPlayed) ? number_format($leagueOffenseTotalFreeThrowsAttempted / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseFreeThrowPercentage = ($leagueOffenseGamesPlayed) ? number_format($leagueOffenseTotalFreeThrowsMade / $leagueOffenseTotalFreeThrowsAttempted, 3) : "0.000";
$leagueOffenseThreePointersMadePerGame = ($leagueOffenseGamesPlayed) ? number_format($leagueOffenseTotalThreePointersMade / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseThreePointersAttemptedPerGame = ($leagueOffenseGamesPlayed) ? number_format($leagueOffenseTotalThreePointersAttempted / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseThreePointPercentage = ($leagueOffenseGamesPlayed) ? number_format($leagueOffenseTotalThreePointersMade / $leagueOffenseTotalThreePointersAttempted, 3) : "0.000";
$leagueOffenseOffensiveReboundsPerGame = ($leagueOffenseGamesPlayed) ? number_format($leagueOffenseTotalOffensiveRebounds / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseTotalReboundsPerGame = ($leagueOffenseGamesPlayed) ? number_format($leagueOffenseTotalRebounds / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseAssistsPerGame = ($leagueOffenseGamesPlayed) ? number_format($leagueOffenseTotalAssists / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseStealsPerGame = ($leagueOffenseGamesPlayed) ? number_format($leagueOffenseTotalSteals / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseTurnoversPerGame = ($leagueOffenseGamesPlayed) ? number_format($leagueOffenseTotalTurnovers / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffenseBlocksPerGame = ($leagueOffenseGamesPlayed) ? number_format($leagueOffenseTotalBlocks / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffensePersonalFoulsPerGame = ($leagueOffenseGamesPlayed) ? number_format($leagueOffenseTotalPersonalFouls / $leagueOffenseGamesPlayed, 1) : "0.0";
$leagueOffensePointsPerGame = ($leagueOffenseGamesPlayed) ? number_format($leagueOffenseTotalPoints / $leagueOffenseGamesPlayed, 1) : "0.0";

$i = 0;
while ($i < $numteams) {
    $trHighlight = trHighlight($userTeam->name, $teamOffenseAveragesArray[$i][0]);

    $league_differentials .= $trHighlight;
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

require "view.php";