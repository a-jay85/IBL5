<?php

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';
$sharedFunctions = new Shared($mysqli_db);
$season = new Season($mysqli_db);

$arrayStatNames = array(
    'POINTS',
    'REBOUNDS',
    'ASSISTS',
    'STEALS',
    'BLOCKS',
    'TURNOVERS',
    'Field Goals Made',
    'Free Throws Made',
    'Three Pointers Made',
);

$arrayStatQueries = array(
    '(`game2GM`*2) + `gameFTM` + (`game3GM`*3)',
    '(`gameORB` + `gameDRB`)',
    '`gameAST`',
    '`gameSTL`',
    '`gameBLK`',
    '`gameTOV`',
    '(`game2GM` + `game3GM`)',
    '`gameFTM`',
    '`game3GM`',
);

if ($_GET['seasonPhase'] == null) {
    $seasonPhase = $season->phase;
} else {
    $seasonPhase = $_GET['seasonPhase'];
}

function seasonHighTable($queryForStat, $statName, $playerOrTeam, $seasonPhase)
{
    global $mysqli_db;
    $season = new Season($mysqli_db);

    if ($playerOrTeam == 'team') {
        $isTeam = '_teams';
    }

    if ($seasonPhase == "Playoffs") {
        $queryBeginningYear = $season->endingYear;
        $queryBeginningMonth = Season::IBL_PLAYOFF_MONTH;
        $queryEndingYear = $season->endingYear;
        $queryEndingMonth = Season::IBL_PLAYOFF_MONTH;
    } elseif ($seasonPhase == "Preseason") {
        $queryBeginningYear = Season::IBL_PRESEASON_YEAR;
        $queryBeginningMonth = Season::IBL_REGULAR_SEASON_STARTING_MONTH;
        $queryEndingYear = Season::IBL_PRESEASON_YEAR + 1;
        $queryEndingMonth = Season::IBL_REGULAR_SEASON_ENDING_MONTH;
    } elseif ($seasonPhase == "HEAT") {
        $queryBeginningYear = $season->beginningYear;
        $queryBeginningMonth = Season::IBL_HEAT_MONTH;
        $queryEndingYear = $season->beginningYear;
        $queryEndingMonth = Season::IBL_HEAT_MONTH;
    } else {
        $queryBeginningYear = $season->beginningYear;
        $queryBeginningMonth = Season::IBL_REGULAR_SEASON_STARTING_MONTH;
        $queryEndingYear = $season->endingYear;
        $queryEndingMonth = Season::IBL_REGULAR_SEASON_ENDING_MONTH;
    }

    $query = "SELECT `name`, `date`, " . $queryForStat . " AS `" . $statName . "`
            FROM ibl_box_scores" . $isTeam . "
            WHERE date BETWEEN '" . $queryBeginningYear . "-" . $queryBeginningMonth . "-01' AND '" . $queryEndingYear . "-" . $queryEndingMonth . "-30'
            ORDER BY `" . $statName . "` DESC, date ASC LIMIT 15;";
    $result = $mysqli_db->query($query);
    $numRows = ($result instanceof mysqli_result) ? $result->num_rows : 0;

    echo "<table border=1>";
    echo "<th colspan=4 align=center>$statName</th>";
    $i = 0;
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_row()) {
            echo "<tr>";
            echo "<td align=center>";
            echo ($i + 1);
            echo "</td>";
            for ($j = 0; $j < 3 && $j < count($row); $j++) {
                echo "<td>";
                echo htmlspecialchars($row[$j] ?? '', ENT_QUOTES, 'UTF-8');
                echo "</td>";
            }
            echo "</tr>";
            $i++;
        }
    }
    echo "</table>";
}

function nextTableColumn()
{
    echo "</td>";
    echo "<td align=center>";
}

function startTableRow()
{
    echo "<tr>";
    echo "<td align=center>";
}

function endTableRow()
{
    echo "</td>";
    echo "</tr>";
}

echo "<html><head><title>$seasonPhase Stat Leaders</title></head>";
echo "<body>";

echo "<H1>Players' $seasonPhase Highs<H1>";

$playerOrTeam = 'player';

echo "<table cellpadding=5>";

startTableRow();
seasonHighTable(reset($arrayStatQueries), reset($arrayStatNames), $playerOrTeam, $seasonPhase);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam, $seasonPhase);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam, $seasonPhase);
endTableRow();

startTableRow();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam, $seasonPhase);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam, $seasonPhase);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam, $seasonPhase);
endTableRow();

startTableRow();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam, $seasonPhase);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam, $seasonPhase);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam, $seasonPhase);
endTableRow();

echo "</table>";

echo "<H1>Teams' $seasonPhase Highs</H1>";
$playerOrTeam = 'team';

echo "<table cellpadding=5>";

startTableRow();
seasonHighTable(reset($arrayStatQueries), reset($arrayStatNames), $playerOrTeam, $seasonPhase);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam, $seasonPhase);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam, $seasonPhase);
endTableRow();

startTableRow();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam, $seasonPhase);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam, $seasonPhase);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam, $seasonPhase);
endTableRow();

startTableRow();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam, $seasonPhase);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam, $seasonPhase);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam, $seasonPhase);
endTableRow();

echo "</table>";

echo "</body>";
echo "</html>";
