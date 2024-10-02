<?php

global $db, $cookie;

$sharedFunctions = new Shared($db);
$season = new Season($db);

$userTeamID = intval($_GET['teamID']);
if (!$userTeamID) {
    if ($cookie[1]) {
        $userTeamName = $sharedFunctions->getTeamnameFromUsername($cookie[1]);
        $userTeamID = $sharedFunctions->getTidFromTeamname($userTeamName);
    } else {
        $userTeamID = 0;
    }
}
$userTeam = Team::withTeamID($db, $userTeamID);
$wins = $losses = $winStreak = $lossStreak = 0;

Nuke\Header::header();
OpenTable();
UI::displaytopmenu($db, $userTeamID);

echo "<center>
    <img src=\"./images/logo/$userTeamID.jpg\">
    <table width=400 border=1>
        <tr bgcolor=$userTeam->color1 style=\"color:#$userTeam->color2; text-align:center\">
            <td colspan=5>
                <h1>Team Schedule</h1>
                <p>
                <i>games highlighted in yellow are projected to be run next sim (" . Sim::LENGTH_IN_DAYS . " days)</i>
            </td>
        </tr>";

//TODO: unify this code with the Schedule module's chunk function

$teamSchedule = Schedule\TeamSchedule::getSchedule($db, $userTeam->teamID);
$seasonRecords = $season->getSeasonRecordsArray();

$lastMonthIteratedOver = "";
foreach ($teamSchedule as $row) {
    $game = new Game($row);
    
    $currentMonthBeingIteratedOver = $game->dateObject->format('m');
    if ($currentMonthBeingIteratedOver != $lastMonthIteratedOver) {
        $fullMonthName = $game->dateObject->format('F');
        echo "<tr bgcolor=$userTeam->color1 style=\"font-weight:bold; color:#$userTeam->color2; text-align:center\">
        <td colspan=7>$fullMonthName</td>
        </tr>
        <tr bgcolor=$userTeam->color1 style=\"font-weight:bold; color:#$userTeam->color2\">
        <td>Date</td>
        <td>Opponent</td>
        <td>Result</td>
        <td>W-L</td>
        <td>Streak</td>
        </tr>";
    }
    
    $opposingTeam = new OpposingTeam($db, $game->getOpposingTeamID($userTeamID), $sharedFunctions, $seasonRecords);
    
    $opponentText = $game->getUserTeamLocationPrefix($userTeamID) . " $opposingTeam->name ($opposingTeam->seasonRecord)";
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
        if ($userTeamID == $game->winningTeamID) {
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




?>



</table></center>

<?php 

CloseTable();
Nuke\Footer::footer();
?>
</html>