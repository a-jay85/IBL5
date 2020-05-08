<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

include_once "sharedFunctions.php";

$arrayStatNames = array(
    'POINTS',
    'REBOUNDS',
    'ASSISTS',
    'STEALS',
    'BLOCKS',
    'TURNOVERS',
    'Field Goals Made',
    'Free Throws Made',
    'Three Pointers Made'
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
    '`game3GM`'
);

if ($_GET['seasonPhase'] == NULL) {
    $seasonPhase = getCurrentSeasonPhase();
} else {
    $seasonPhase = $_GET['seasonPhase'];
}

function seasonHighTable($queryForStat, $statName, $playerOrTeam, $seasonPhase)
{
    if ($playerOrTeam == 'player') {
        $isPlayer = 'pid != 0';
    } elseif ($playerOrTeam == 'team') {
        $isPlayer = 'pid = 0';
    }

    $playoffYear = getCurrentSeasonEndingYear();
    $seasonStartingYear = $playoffYear - 1;

    if ($seasonPhase == "Playoffs") {
        $query = "SELECT `name`, `date`, " . $queryForStat . " AS `" . $statName . "`
            FROM ibl_box_scores
            WHERE " . $isPlayer . "
            AND date >= '" . $playoffYear . "-05-01'
            ORDER BY `" . $statName . "` DESC, date ASC LIMIT 15;";
    } elseif ($seasonPhase == "Preseason") {
        $query = "SELECT `name`, `date`, " . $queryForStat . " AS `" . $statName . "`
            FROM ibl_box_scores
            WHERE " . $isPlayer . "
            AND date >= '" . $seasonStartingYear . "-09-01'
            ORDER BY `" . $statName . "` DESC, date ASC LIMIT 15;";
    } elseif ($seasonPhase == "HEAT") {
        $query = "SELECT `name`, `date`, " . $queryForStat . " AS `" . $statName . "`
            FROM ibl_box_scores
            WHERE " . $isPlayer . "
            AND date >= '" . $seasonStartingYear . "-10-01'
            ORDER BY `" . $statName . "` DESC, date ASC LIMIT 15;";
    } else {
        $query = "SELECT `name`, `date`, " . $queryForStat . " AS `" . $statName . "`
            FROM ibl_box_scores
            WHERE " . $isPlayer . "
            AND date >= '" . $seasonStartingYear . "-11-01'
            ORDER BY `" . $statName . "` DESC, date ASC LIMIT 15;";
    }
    $result = mysql_query($query);
    $numRows = mysql_num_rows($result);

    echo "\t\t\t<table border=1>\n";
    echo "\t\t\t\t<th colspan=4 align=center>$statName</th>\n";
    $i = 0;
    while ($i < $numRows) {
        echo "\t\t\t\t<tr>\n";
        echo "\t\t\t\t\t<td align=center>\n";
        echo "\t\t\t\t\t\t" . ($i + 1) . "\n";
        echo "\t\t\t\t\t</td>\n";
        $j = 0;
        while ($j < 3) {
            echo "\t\t\t\t\t<td>\n";
            echo "\t\t\t\t\t\t" . mysql_result($result, $i, $j) . "\n";
            echo "\t\t\t\t\t</td>\n";
            $j++;
        }
        echo "\t\t\t\t</tr>\n";
        $i++;
    }
    echo "\t\t\t</table>\n";
}

function nextTableColumn ()
{
    echo "\t\t</td>\n";
    echo "\t\t<td align=center>\n";
}

function startTableRow ()
{
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
}

function endTableRow ()
{
    echo "\t\t</td>\n";
    echo "\t</tr>\n";
}

if ($seasonPhase == "playoffs") {
    $phase = "Playoff";
    echo "<a href=\"statLeaders.php\"><i>(Click to switch to Season Highs)</i></a><p>";
} else {
    $phase = "Season";
    echo "<a href=\"statLeaders.php?seasonPhase=playoffs\"><i>(Click to switch to Playoff Highs)</i></a><p>";
}

echo "<html><head><title>$seasonPhase Stat Leaders</title></head>\n\n";
echo "<body>\n\n";

echo "<H1>Players' $seasonPhase Highs<H1>\n\n";

$playerOrTeam = 'player';

echo "<table cellpadding=5>\n";

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

echo "</table>\n\n";

echo "<H1>Teams' $seasonPhase Highs</H1>\n\n";
$playerOrTeam = 'team';

echo "<table cellpadding=5>\n";

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

echo "</table>\n\n";

echo "</body>\n";
echo "</html>";
