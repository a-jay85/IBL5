<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

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

function seasonHighTable($queryForStat, $statName, $playerOrTeam)
{
    if ($playerOrTeam == 'player') {
        $isPlayer = 'pid != 0';
    } elseif ($playerOrTeam == 'team') {
        $isPlayer = 'pid = 0';
    }

    $query = "SELECT `name`, `date`, " . $queryForStat . " AS `" . $statName . "`
        FROM ibl_box_scores
        WHERE " . $isPlayer . "
        ORDER BY `" . $statName . "` DESC, date ASC LIMIT 15;";
    $result = mysql_query($query);
    $numRows = mysql_num_rows($result);

    echo "<table border=1>\n";
    echo "\t<th colspan=4 align=center>$statName</th>\n";
    $i = 0;
    while ($i < $numRows) {
        echo "\t<tr>\n";
        echo "\t\t<td align=center>\n";
        echo "\t\t\t" . ($i + 1) . "\n";
        echo "\t\t</td>\n";
        $j = 0;
        while ($j < 3) {
            echo "\t\t<td>\n";
            echo "\t\t\t" . mysql_result($result, $i, $j) . "\n";
            echo "\t\t</td>\n";
            $j++;
        }
        echo "\t</tr>\n";
        $i++;
    }
    echo "</table>\n";
    echo "<p>\n";
}

function nextTableColumn ()
{
    echo "\n";
    echo "\t\t</td>\n";
    echo "\t\t<td align=center>\n";
    echo "\n";
}

function startTableRow ()
{
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
    echo "\n";
}

function endTableRow ()
{
    echo "\n";
    echo "\t\t</td>\n";
    echo "\t</tr>\n";
}


echo "<html><head><title>Season Stat Leaders</title></head><body>";

echo "<H1>Players' Season Highs<H1>";
$playerOrTeam = 'player';

echo "<table>\n";

startTableRow();
seasonHighTable(reset($arrayStatQueries), reset($arrayStatNames), $playerOrTeam);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam);
endTableRow();

startTableRow();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam);
endTableRow();

startTableRow();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam);
endTableRow();

echo "</table>\n";

echo "<H1>Teams' Season Highs</H1>";
$playerOrTeam = 'team';

echo "<table>\n";

startTableRow();
seasonHighTable(reset($arrayStatQueries), reset($arrayStatNames), $playerOrTeam);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam);
endTableRow();

startTableRow();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam);
endTableRow();

startTableRow();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam);
nextTableColumn();
seasonHighTable(next($arrayStatQueries), next($arrayStatNames), $playerOrTeam);
endTableRow();

echo "</table>\n";
