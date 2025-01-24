<?php

global $db, $cookie;
$sharedFunctions = new Shared($db);
$season = new Season($db);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$pagetitle = "- $module_name";

$username = $cookie[1];
$userTeam = Team::initialize($db, $sharedFunctions->getTeamnameFromUsername($username));
$userStartingPG = Player::withPlayerID($db, $userTeam->getLastSimStarterPlayerIDForPosition('PG') ?? 4040404);
$userStartingSG = Player::withPlayerID($db, $userTeam->getLastSimStarterPlayerIDForPosition('SG') ?? 4040404);
$userStartingSF = Player::withPlayerID($db, $userTeam->getLastSimStarterPlayerIDForPosition('SF') ?? 4040404);
$userStartingPF = Player::withPlayerID($db, $userTeam->getLastSimStarterPlayerIDForPosition('PF') ?? 4040404);
$userStartingC = Player::withPlayerID($db, $userTeam->getLastSimStarterPlayerIDForPosition('C') ?? 4040404);

$resultUserTeamProjectedGamesNextSim = Schedule\TeamSchedule::getProjectedGamesNextSimResult($db, $userTeam->teamID, $season->lastSimEndDate);

$i = 0;
foreach ($resultUserTeamProjectedGamesNextSim as $gameRow) {
    $rows[$i]['game'] = new Game($gameRow);
    $rows[$i]['opposingTeam'] = Team::initialize($db, $rows[$i]['game']->getOpposingTeamID($userTeam->teamID));
    $rows[$i]['opposingStartingPG'] = Player::withPlayerID($db, $rows[$i]['opposingTeam']->getLastSimStarterPlayerIDForPosition('PG') ?? 4040404);
    $rows[$i]['opposingStartingSG'] = Player::withPlayerID($db, $rows[$i]['opposingTeam']->getLastSimStarterPlayerIDForPosition('SG') ?? 4040404);
    $rows[$i]['opposingStartingSF'] = Player::withPlayerID($db, $rows[$i]['opposingTeam']->getLastSimStarterPlayerIDForPosition('SF') ?? 4040404);
    $rows[$i]['opposingStartingPF'] = Player::withPlayerID($db, $rows[$i]['opposingTeam']->getLastSimStarterPlayerIDForPosition('PF') ?? 4040404);
    $rows[$i]['opposingStartingC'] = Player::withPlayerID($db, $rows[$i]['opposingTeam']->getLastSimStarterPlayerIDForPosition('C') ?? 4040404);

    $i++;
}

?>

<?php
    Nuke\Header::header();
    OpenTable();
?>

<center>
    <h1>Next Sim</h1>

    <table>
        <?php for ($i = 0; $i <= Sim::LENGTH_IN_DAYS; $i++) : ?>
            <tr>
                <td><?= $rows[$i]['game']->date . $rows[$i]['opposingTeam']->name ?></td>
            </tr>
        <?php endfor; ?>
    </table>

<?php
    CloseTable();
    Nuke\Footer::footer();
?>