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
$userTeam = Team::initialize($db, $userTeamID);
$wins = $losses = $winStreak = $lossStreak = 0;

//TODO: unify this code with the Schedule module's chunk function

$teamSchedule = Schedule\TeamSchedule::getSchedule($db, $userTeam->teamID);
$seasonRecords = $season->getSeasonRecordsArray();

$rows = array();
$lastMonthIteratedOver = "";
$i = 0;
foreach ($teamSchedule as $row) {
    $rows[$i]['game'] = new Game($row);

    $rows[$i]['currentMonthBeingIteratedOver'] = strval($rows[$i]['game']->dateObject->format('F'));

    $rows[$i]['opposingTeam'] = Team::initialize($db, $rows[$i]['game']->getOpposingTeamID($userTeamID));
    $rows[$i]['opponentText'] = $rows[$i]['game']->getUserTeamLocationPrefix($userTeamID) . " " . $rows[$i]['opposingTeam']->name . " (" . $rows[$i]['opposingTeam']->seasonRecord . ")";
    
    $rows[$i]['highlight'] = "";
    if ($rows[$i]['game']->isUnplayed) {
        $rows[$i]['highlight'] = ($rows[$i]['game']->dateObject <= $season->projectedNextSimEndDate) ? "bgcolor=#DDDD00" : "";
    } else {
        if ($userTeamID == $rows[$i]['game']->winningTeamID) {
            $rows[$i]['gameResult'] = "W";
            $wins++;
            $winstreak++;
            $rows[$i]['winStreak'] = $winstreak;
            $lossStreak = $rows[$i]['lossStreak'] = 0;
            $rows[$i]['winlosscolor'] = "green";
        } else {
            $rows[$i]['gameResult'] = "L";
            $losses++;
            $lossStreak++;
            $rows[$i]['lossStreak'] = $lossStreak;
            $winstreak = $rows[$i]['winStreak'] = 0;
            $rows[$i]['winlosscolor'] = "red";
        }
        $rows[$i]['wins'] = $wins;
        $rows[$i]['losses'] = $losses;
        $rows[$i]['streak'] = ($rows[$i]['winStreak'] > $rows[$i]['lossStreak']) ? "W " . $rows[$i]['winStreak'] : "L " . $rows[$i]['lossStreak'];
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
    <img src="./images/logo/<?= $userTeamID ?>.jpg">
</div>

    <table width=400 border=1 align=center>
        <tr bgcolor=<?= $userTeam->color1 ?> style="color:#<?= $userTeam->color2 ?>; text-align:center;">
            <td colspan=5>
                <h1>Team Schedule</h1>
                <p>
                <i>games highlighted in yellow are projected to be run next sim (<?= Sim::LENGTH_IN_DAYS ?> days)</i>
            </td>
        </tr>

<?php $lastMonthIteratedOver = "" ?>
<?php foreach ($rows as $row) : ?>
    <?php if ($row["currentMonthBeingIteratedOver"] !== $lastMonthIteratedOver) : ?>
        <tr bgcolor=<?= $userTeam->color1 ?> style="font-weight:bold; color:#<?= $userTeam->color2 ?>; text-align:center;">
            <td colspan=7><?= $row["currentMonthBeingIteratedOver"] ?></td>
        </tr>
        <tr bgcolor=<?= $userTeam->color1 ?> style="font-weight:bold; color:#<?= $userTeam->color2 ?>;">
            <td>Date</td>
            <td>Opponent</td>
            <td>Result</td>
            <td>W-L</td>
            <td>Streak</td>
        </tr>
    <?php endif; ?>
    
    <?php $lastMonthIteratedOver = $row["currentMonthBeingIteratedOver"]; ?>

    <?php if ($row['game']->visitorScore == $row['game']->homeScore) : ?>
        <tr <?= $row['highlight'] ?>>
            <td><?= $row['game']->date ?></td>
            <td><a href="modules.php?name=Team&op=team&tid=<?= $row['opposingTeam']->teamID ?>"><?= $row['opponentText'] ?></a></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    <?php else : ?>
        <tr bgcolor=FFFFFF>
            <td><a href="./ibl/IBL/box<?= $row['game']->boxScoreID ?>.htm"><?= $row['game']->date ?></a></td>
            <td><a href="modules.php?name=Team&op=team&tid=<?= $row['opposingTeam']->teamID ?>"><?= $row['opponentText'] ?></a></b></td>
            <td>
                <a href="./ibl/IBL/box<?= $row['game']->boxScoreID ?>.htm" style="color:<?= $row['winlosscolor'] ?>; font-weight:bold; font-family:monospace,monospace;">
                    <?= $row['gameResult'] . " " . $row['game']->visitorScore . " - " . $row['game']->homeScore ?>
                </a>
            </td>
            <td style="font-family:monospace,monospace;"><?= $row['wins'] . "-" . $row['losses'] ?></td>
            <td style="font-family:monospace,monospace;"><?= $row['streak'] ?></td>
        </tr>
    <?php endif; ?>
<?php endforeach; ?>

    </table>

<?php 
    CloseTable();
    Nuke\Footer::footer();
?>

</html>