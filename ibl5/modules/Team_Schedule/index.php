<?php

global $db, $cookie;

$sharedFunctions = new Shared($db);
$season = new Season($db);

$teamID = intval($_GET['teamID']);
if (!$teamID) {
    if ($cookie[1]) {
        $userTeamName = $sharedFunctions->getTeamnameFromUsername($cookie[1]);
        $userTeamID = $sharedFunctions->getTidFromTeamname($userTeamName);
        $teamID = $userTeamID;
    } else {
        $teamID = 0;
    }
}
$team = Team::withTeamID($db, $teamID);
$wins = $losses = $winStreak = $lossStreak = 0;

Nuke\Header::header();
OpenTable();
UI::displaytopmenu($db, $teamID);

echo "<center>
    <img src=\"./images/logo/$teamID.jpg\">
    <table width=400 border=1>
        <tr bgcolor=$team->color1 style=\"color:#$team->color2; text-align:center\">
            <td colspan=5>
                <h1>Team Schedule</h1>
                <p>
                <i>games highlighted in yellow are projected to be run next sim (" . Sim::LENGTH_IN_DAYS . " days)</i>
            </td>
        </tr>";

//TODO: unify this code with the Schedule module's chunk function

$teamSchedule = Schedule\TeamSchedule::getSchedule($db, $team->teamID);
$seasonRecords = $season->getSeasonRecordsArray();

$lastMonthIteratedOver = "";
foreach ($teamSchedule as $row) {
    $game = new Game($db, $row);
    $opponentTeamID = $game->visitorTeamID == $team->teamID ? $game->homeTeamID : $game->visitorTeamID;
    $opponentTeamName = $sharedFunctions->getTeamnameFromTid($opponentTeamID);
    $opponentRecord = $db->sql_result($teamSeasonRecordsResult, $opponentTeamID - 1, "leagueRecord");
    $opponentLocation = $game->visitorTeamID == $team->teamID ? "@" : "vs";
    $opponentText = $opponentLocation . " $opponentTeamName ($opponentRecord)";
    
    $currentMonthBeingIteratedOver = $game->dateObject->format('m');
    if ($currentMonthBeingIteratedOver != $lastMonthIteratedOver) {
        $fullMonthName = $game->dateObject->format('F');
        echo "<tr bgcolor=$team->color1 style=\"font-weight:bold; color:#$team->color2; text-align:center\">
            <td colspan=7>$fullMonthName</td>
        </tr>
        <tr bgcolor=$team->color1 style=\"font-weight:bold; color:#$team->color2\">
            <td>Date</td>
            <td>Opponent</td>
            <td>Result</td>
            <td>W-L</td>
            <td>Streak</td>
        </tr>";
    }

    if ($game->visitorScore == $game->homeScore) {
        $highlight = ($game->dateObject <= $season->projectedNextSimEndDate) ? "bgcolor=#DDDD00" : "";
        echo "<tr $highlight>
            <td>$game->date</td>
            <td><a href=\"modules.php?name=Team&op=team&tid=$opponentTeamID\">$opponentText</a></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>";
    } else {
        if ($teamID == $game->winningTeamID) {
            $gameResult = "W";
            $wins++;
            $winStreak++;
            $lossStreak = 0;
            $winlosscolor = "green";
        } else {
            $gameResult = "L";
            $losses++;
            $lossStreak++;
            $winStreak = 0;
            $winlosscolor = "red";
        }

        $streak = ($winStreak > $lossStreak) ? "W $winStreak" : "L $lossStreak";

        echo "<tr bgcolor=FFFFFF>
                <td><a href=\"./ibl/IBL/box$game->boxScoreID.htm\">$game->date</a></td>
                <td><a href=\"modules.php?name=Team&op=team&tid=$game->visitorTeamID\">$opponentText</a></b></td>
                <td>
                    <a href=\"./ibl/IBL/box$game->boxScoreID.htm\" style=\"color:$winlosscolor; font-weight:bold; font-family:monospace,monospace;\">
                        $gameResult $game->visitorScore - $game->homeScore
                    </a>
                </td>
                <td style=\"font-family:monospace,monospace;\">$wins-$losses</td>
                <td style=\"font-family:monospace,monospace;\">$streak</td>
            </tr>";
    }

    $lastMonthIteratedOver = $currentMonthBeingIteratedOver;
}

echo "</table></center>";
CloseTable();
Nuke\Footer::footer();