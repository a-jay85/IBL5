<?php

use Player\Player;
use Player\PlayerRepository;

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

$teamID = isset($_GET['teamID']) ? (int) $_GET['teamID'] : 0;

Nuke\Header::header();
OpenTable();
UI::displaytopmenu($db, $teamID);

$team = Team::initialize($db, $teamID);

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
    $playerRepository = new PlayerRepository($db);
    $playerData = $playerRepository->fillFromCurrentRow($playerRow);

    echo "<tr>";

    if ($playerData->isRetired) {
        echo "<td><a href=\"./modules.php?name=Player&pa=showpage&pid=$playerData->playerID\">$playerData->name</a> (retired)</td>";
    } else {
        echo "<td><a href=\"./modules.php?name=Player&pa=showpage&pid=$playerData->playerID\">$playerData->name</a></td>";
    }

    echo "
        <td>$playerData->position</td>
        <td>$playerData->draftYear</td>
        <td>$playerData->draftRound</td>
        <td>$playerData->draftPickNumber</td>
    </tr>";
}

echo "</table>";

CloseTable();
Nuke\Footer::footer();
