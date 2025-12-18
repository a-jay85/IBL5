<?php

use Player\Player;

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

$teamID = isset($_GET['teamID']) ? (int) $_GET['teamID'] : 0;

Nuke\Header::header();
OpenTable();
UI::displaytopmenu($mysqli_db, $teamID);

$team = Team::initialize($mysqli_db, $teamID);

echo "$team->name Draft History
    <table class=\"sortable\">
        <tr>
            <th>Player</th>
            <th>Pos</th>
            <th>Year</th>
            <th>Round</th>
            <th>Pick</th>
        </tr>";

foreach ($team->getDraftHistoryResult() as $playerRow) {
    $player = Player::withPlrRow($db, $playerRow);

    echo "<tr>";

    if ($player->isRetired) {
        echo "<td><a href=\"/ibl5/modules.php?name=Player&pa=showpage&pid=$player->playerID\">$player->name</a> (retired)</td>";
    } else {
        echo "<td><a href=\"/ibl5/modules.php?name=Player&pa=showpage&pid=$player->playerID\">$player->name</a></td>";
    }

    echo "
        <td>$player->position</td>
        <td>$player->draftYear</td>
        <td>$player->draftRound</td>
        <td>$player->draftPickNumber</td>
    </tr>";
}

echo "</table>";

CloseTable();
Nuke\Footer::footer();
