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

//TODO: unify this code with the Schedule module's chunk function

$teamSchedule = Schedule\TeamSchedule::getSchedule($db, $userTeam->teamID);
$seasonRecords = $season->getSeasonRecordsArray();

$teamScheduleRows = array();
$lastMonthIteratedOver = "";
$i = 0;
foreach ($teamSchedule as $row) {
    $teamScheduleRows[$i]['game'] = new Game($row);
    $teamScheduleRows[$i]['currentMonthBeingIteratedOver'] = strval($teamScheduleRows[$i]['game']->dateObject->format('F'));
    $teamScheduleRows[$i]['opposingTeam'] = new OpposingTeam($db, $teamScheduleRows[$i]['game']->getOpposingTeamID($userTeamID), $sharedFunctions, $seasonRecords);
    $teamScheduleRows[$i]['opponentText'] = $teamScheduleRows[$i]['game']->getUserTeamLocationPrefix($userTeamID) . " " . $teamScheduleRows[$i]['opposingTeam']->name . " (" . $teamScheduleRows[$i]['opposingTeam']->seasonRecord . ")";
    $teamScheduleRows[$i]['highlight'] = "";
    if ($teamScheduleRows[$i]['game']->visitorScore == $teamScheduleRows[$i]['game']->homeScore) {
        $teamScheduleRows[$i]['highlight'] = ($teamScheduleRows[$i]['game']->dateObject <= $season->projectedNextSimEndDate) ? "bgcolor=#DDDD00" : "";
    } else {
        if ($userTeamID == $teamScheduleRows[$i]['game']->winningTeamID) {
            $teamScheduleRows[$i]['gameResult'] = "W";
            $wins++;
            $winstreak++;
            $teamScheduleRows[$i]['winStreak'] = $winstreak;
            $lossStreak = $teamScheduleRows[$i]['lossStreak'] = 0;
            $teamScheduleRows[$i]['winlosscolor'] = "green";
        } else {
            $teamScheduleRows[$i]['gameResult'] = "L";
            $losses++;
            $lossStreak++;
            $teamScheduleRows[$i]['lossStreak'] = $lossStreak;
            $winstreak = $teamScheduleRows[$i]['winStreak'] = 0;
            $teamScheduleRows[$i]['winlosscolor'] = "red";
        }
        $teamScheduleRows[$i]['wins'] = $wins;
        $teamScheduleRows[$i]['losses'] = $losses;
        $teamScheduleRows[$i]['streak'] = ($teamScheduleRows[$i]['winStreak'] > $teamScheduleRows[$i]['lossStreak']) ? "W " . $teamScheduleRows[$i]['winStreak'] : "L " . $teamScheduleRows[$i]['lossStreak'];
    }

    $i++;
}

?>

<?php
    Nuke\Header::header();
    OpenTable();
    UI::displaytopmenu($db, $userTeamID);
?>

<div style="text-align: center;">
    <img src="./images/logo/<?php echo $userTeamID; ?>.jpg">
</div>

    <table width=400 border=1 align=center>
        <tr bgcolor=<?php echo $userTeam->color1 ?> style="color:#<?php echo $userTeam->color2 ?>; text-align:center;">
            <td colspan=5>
                <h1>Team Schedule</h1>
                <p>
                <i>games highlighted in yellow are projected to be run next sim (<?php echo Sim::LENGTH_IN_DAYS; ?> days)</i>
            </td>
        </tr>

<?php $lastMonthIteratedOver = "" ?>
<?php foreach ($teamScheduleRows as $row) : ?>
    <?php if ($row["currentMonthBeingIteratedOver"] !== $lastMonthIteratedOver) : ?>
        <tr bgcolor=<?php echo $userTeam->color1 ?> style="font-weight:bold; color:#<?php echo $userTeam->color2 ?>; text-align:center;">
            <td colspan=7><?php echo $row["currentMonthBeingIteratedOver"] ?></td>
        </tr>
        <tr bgcolor=<?php echo $userTeam->color1 ?> style="font-weight:bold; color:#<?php echo $userTeam->color2 ?>;">
            <td>Date</td>
            <td>Opponent</td>
            <td>Result</td>
            <td>W-L</td>
            <td>Streak</td>
        </tr>
    <?php endif; ?>
    
    <?php $lastMonthIteratedOver = $row["currentMonthBeingIteratedOver"]; ?>

    <?php if ($row['game']->visitorScore == $row['game']->homeScore) { ?>
        <tr <?php echo $row['highlight'] ?>>
            <td><?php echo $row['game']->date ?></td>
            <td><a href="modules.php?name=Team&op=team&tid=<?php echo $row['opposingTeam']->teamID; ?>"><?php echo $row['opponentText'] ?></a></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    <?php } else { ?>
        <tr bgcolor=FFFFFF>
            <td><a href="./ibl/IBL/box<?php echo $row['game']->boxScoreID; ?>.htm"><?php echo $row['game']->date; ?></a></td>
            <td><a href="modules.php?name=Team&op=team&tid=<?php echo $row['opposingTeam']->teamID; ?>"><?php echo $row['opponentText'] ?></a></b></td>
            <td>
                <a href="./ibl/IBL/box<?php echo $row['game']->boxScoreID; ?>.htm" style="color:<?php echo $row['winlosscolor']; ?>; font-weight:bold; font-family:monospace,monospace;">
                    <?php echo $row['gameResult'] . " " . $row['game']->visitorScore . " - " . $row['game']->homeScore;?>
                </a>
            </td>
            <td style="font-family:monospace,monospace;"><?php echo $row['wins'] . "-" . $row['losses']; ?></td>
            <td style="font-family:monospace,monospace;"><?php echo $row['streak']; ?></td>
        </tr>
    <?php } ?>

<?php endforeach; ?>

    </table>

<?php 
    CloseTable();
    Nuke\Footer::footer();
?>

</html>