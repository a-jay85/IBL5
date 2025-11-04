<?php

use Player\Player;

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Injured Players";

$teamID = isset($teamID) ? (int) $teamID : 0;

$league = new League($db);

Nuke\Header::header();
OpenTable();

UI::displaytopmenu($db, $teamID);

echo "<center><h2>INJURED PLAYERS</h2></center>
    <table>
        <tr>
            <td valign=top>
                <table class=\"sortable\">
                    <tr>
                        <th>Pos</th>
                        <th>Player</th>
                        <th>Team</th>
                        <th>Days Injured</th>
                    </tr>";

$i = 0;
foreach ($league->getInjuredPlayersResult() as $injuredPlayer) {
    $player = Player::withPlrRow($db, $injuredPlayer);
    $team = Team::initialize($db, $player->teamID);

    (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "DDDDDD";

    echo "<tr bgcolor=$bgcolor>
        <td>$player->position</td>
        <td><a href=\"./modules.php?name=Player&pa=showpage&pid=$player->playerID\">$player->name</a></td>
        <td bgcolor=\"#$team->color1\">
            <font color=\"#$team->color2\"><a href=\"./modules.php?name=Team&op=team&teamID=$player->teamID\">$team->city $player->teamName</a></font>
        </td>
        <td>$player->daysRemainingForInjury</td>
    </tr>";

    $i++;
}

echo "</table></table>";

CloseTable();
Nuke\Footer::footer();
