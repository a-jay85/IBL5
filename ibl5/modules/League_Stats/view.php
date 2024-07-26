<?php

NukeHeader::header();
OpenTable();

$totalsHeaderRow = "<tr>
    <th>Team</th>
    <th>Gm</th>
    <th>FGM</th>
    <th>FGA</th>
    <th>FTM</th>
    <th>FTA</th>
    <th>3GM</th>
    <th>3GA</th>
    <th>ORB</th>
    <th>REB</th>
    <th>AST</th>
    <th>STL</th>
    <th>TVR</th>
    <th>BLK</th>
    <th>PF</th>
    <th>PTS</th>
</tr>";

$averagesHeaderRow = "<tr>
    <th>Team</th>
    <th>FGM</th>
    <th>FGA</th>
    <th>FGP</th>
    <th>FTM</th>
    <th>FTA</th>
    <th>FTP</th>
    <th>3GM</th>
    <th>3GA</th>
    <th>3GP</th>
    <th>ORB</th>
    <th>REB</th>
    <th>AST</th>
    <th>STL</th>
    <th>TVR</th>
    <th>BLK</th>
    <th>PF</th>
    <th>PTS</th>
</tr>";

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

echo "<center>
    <h1>League-wide Statistics</h1>

    <h2>Team Offense Totals</h2>
    <table class=\"sortable\">
        <thead>$totalsHeaderRow</thead>
        <tbody>$offense_totals</tbody>
        <tfoot>$league_totals</tfoot>
    </table>

    <h2>Team Defense Totals</h2>
    <table class=\"sortable\">
        <thead>$totalsHeaderRow</thead>
        <tbody>$defense_totals</tbody>
        <tfoot>$league_totals</tfoot>
    </table>

    <h2>Team Offense Averages</h2>
    <table class=\"sortable\">
        <thead>$averagesHeaderRow</thead>
        <tbody>$offense_averages</tbody>
        <tfoot>$league_averages</tfoot>
    </table>

    <h2>Team Defense Averages</h2>
    <table class=\"sortable\">
        <thead>$averagesHeaderRow</thead>
        <tbody>$defense_averages</tbody>
        <tfoot>$league_averages</tfoot>
    </table>

    <h2>Team Off/Def Average Differentials</h2>
    <table class=\"sortable\">
        <thead>$averagesHeaderRow</thead>
        <tbody>$league_differentials</tbody>
    </table>";

CloseTable();
include "footer.php";