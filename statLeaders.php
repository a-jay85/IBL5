<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

echo "<H1>Players' Season Highs<H1>";

$top10PointsQuery = "SELECT `name`, `date`, (`game2GM`*2) + `gameFTM` + (`game3GM`*3) AS points
    FROM ibl_box_scores
    WHERE pid != 0
    ORDER BY points DESC LIMIT 10;";
$top10PointsResult = mysql_query($top10PointsQuery);
$top10PointsRows = mysql_num_rows($top10PointsResult);

echo "<table>\n";
echo "\t<tr>\n";
echo "\t\t<td align=center>\n";
echo "\n";

echo "<table border=1>\n";
echo "\t<th colspan=4 align=center>POINTS</th>\n";
$i = 0;
while ($i < $top10PointsRows) {
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
    echo "\t\t\t" . ($i + 1) . "\n";
    echo "\t\t</td>\n";
    $j = 0;
    while ($j < 3) {
        echo "\t\t<td>\n";
        echo "\t\t\t" . mysql_result($top10PointsResult, $i, $j) . "\n";
        echo "\t\t</td>\n";
        $j++;
    }
    echo "\t</tr>\n";
    $i++;
}
echo "</table>\n";
echo "<p>\n";

echo "\n";
echo "\t\t</td>\n";
echo "\t\t<td align=center>\n";
echo "\n";

$top10ReboundsQuery = "SELECT `name`, `date`, (`gameORB` + `gameDRB`) AS rebounds
    FROM ibl_box_scores
    WHERE pid != 0
    ORDER BY rebounds DESC LIMIT 10;";
$top10ReboundsResult = mysql_query($top10ReboundsQuery);
$top10ReboundsRows = mysql_num_rows($top10ReboundsResult);

echo "<table border=1>\n";
echo "\t<th colspan=4 align=center>REBOUNDS</th>\n";
$i = 0;
while ($i < $top10ReboundsRows) {
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
    echo "\t\t\t" . ($i + 1) . "\n";
    echo "\t\t</td>\n";
    $j = 0;
    while ($j < 3) {
        echo "\t\t<td>\n";
        echo "\t\t\t" . mysql_result($top10ReboundsResult, $i, $j) . "\n";
        echo "\t\t</td>\n";
        $j++;
    }
    echo "\t</tr>\n";
    $i++;
}
echo "</table>\n";
echo "<p>\n";

echo "\n";
echo "\t\t</td>\n";
echo "\t\t<td align=center>\n";
echo "\n";

$top10AssistsQuery = "SELECT `name`, `date`, `gameAST` AS assists
    FROM ibl_box_scores
    WHERE pid != 0
    ORDER BY assists DESC LIMIT 10;";
$top10AssistsResult = mysql_query($top10AssistsQuery);
$top10AssistsRows = mysql_num_rows($top10AssistsResult);

echo "<table border=1>\n";
echo "\t<th colspan=4 align=center>ASSISTS</th>\n";
$i = 0;
while ($i < $top10AssistsRows) {
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
    echo "\t\t\t" . ($i + 1) . "\n";
    echo "\t\t</td>\n";
    $j = 0;
    while ($j < 3) {
        echo "\t\t<td>\n";
        echo "\t\t\t" . mysql_result($top10AssistsResult, $i, $j) . "\n";
        echo "\t\t</td>\n";
        $j++;
    }
    echo "\t</tr>\n";
    $i++;
}
echo "</table>\n";
echo "<p>\n";

echo "\n";
echo "\t\t</td>\n";
echo "\t</tr>\n";
echo "\t<tr>\n";
echo "\t\t<td align=center>\n";
echo "\n";

$top10StealsQuery = "SELECT `name`, `date`, `gameSTL` AS steals
    FROM ibl_box_scores
    WHERE pid != 0
    ORDER BY steals DESC LIMIT 10;";
$top10StealsResult = mysql_query($top10StealsQuery);
$top10StealsRows = mysql_num_rows($top10StealsResult);

echo "<table border=1>\n";
echo "\t<th colspan=4 align=center>STEALS</th>\n";
$i = 0;
while ($i < $top10StealsRows) {
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
    echo "\t\t\t" . ($i + 1) . "\n";
    echo "\t\t</td>\n";
    $j = 0;
    while ($j < 3) {
        echo "\t\t<td>\n";
        echo "\t\t\t" . mysql_result($top10StealsResult, $i, $j) . "\n";
        echo "\t\t</td>\n";
        $j++;
    }
    echo "\t</tr>\n";
    $i++;
}
echo "</table>\n";
echo "<p>\n";

echo "\n";
echo "\t\t</td>\n";
echo "\t\t<td align=center>\n";
echo "\n";

$top10BlocksQuery = "SELECT `name`, `date`, `gameBLK` AS blocks
    FROM ibl_box_scores
    WHERE pid != 0
    ORDER BY blocks DESC LIMIT 10;";
$top10BlocksResult = mysql_query($top10BlocksQuery);
$top10BlocksRows = mysql_num_rows($top10BlocksResult);

echo "<table border=1>\n";
echo "\t<th colspan=4 align=center>BLOCKS</th>\n";
$i = 0;
while ($i < $top10BlocksRows) {
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
    echo "\t\t\t" . ($i + 1) . "\n";
    echo "\t\t</td>\n";
    $j = 0;
    while ($j < 3) {
        echo "\t\t<td>\n";
        echo "\t\t\t" . mysql_result($top10BlocksResult, $i, $j) . "\n";
        echo "\t\t</td>\n";
        $j++;
    }
    echo "\t</tr>\n";
    $i++;
}
echo "</table>\n";
echo "<p>\n";

echo "\n";
echo "\t\t</td>\n";
echo "\t</tr>\n";
echo "\t<tr>\n";
echo "\t\t<td align=center>\n";
echo "\n";

$top10FGMQuery = "SELECT `name`, `date`, (`game2GM` + `game3GM`) AS FGM
    FROM ibl_box_scores
    WHERE pid != 0
    ORDER BY FGM DESC LIMIT 10;";
$top10FGMResult = mysql_query($top10FGMQuery);
$top10FGMRows = mysql_num_rows($top10FGMResult);

echo "<table border=1>\n";
echo "\t<th colspan=4 align=center>Field Goals Made</th>\n";
$i = 0;
while ($i < $top10FGMRows) {
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
    echo "\t\t\t" . ($i + 1) . "\n";
    echo "\t\t</td>\n";
    $j = 0;
    while ($j < 3) {
        echo "\t\t<td>\n";
        echo "\t\t\t" . mysql_result($top10FGMResult, $i, $j) . "\n";
        echo "\t\t</td>\n";
        $j++;
    }
    echo "\t</tr>\n";
    $i++;
}
echo "</table>\n";
echo "<p>\n";

echo "\n";
echo "\t\t</td>\n";
echo "\t\t<td align=center>\n";
echo "\n";

$top10FTMQuery = "SELECT `name`, `date`, `gameFTM` AS FTM
    FROM ibl_box_scores
    WHERE pid != 0
    ORDER BY FTM DESC LIMIT 10;";
$top10FTMResult = mysql_query($top10FTMQuery);
$top10FTMRows = mysql_num_rows($top10FTMResult);

echo "<table border=1>\n";
echo "\t<th colspan=4 align=center>Free Throws Made</th>\n";
$i = 0;
while ($i < $top10FTMRows) {
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
    echo "\t\t\t" . ($i + 1) . "\n";
    echo "\t\t</td>\n";
    $j = 0;
    while ($j < 3) {
        echo "\t\t<td>\n";
        echo "\t\t\t" . mysql_result($top10FTMResult, $i, $j) . "\n";
        echo "\t\t</td>\n";
        $j++;
    }
    echo "\t</tr>\n";
    $i++;
}
echo "</table>\n";
echo "<p>\n";

echo "\n";
echo "\t\t</td>\n";
echo "\t\t<td align=center>\n";
echo "\n";

$top103GMQuery = "SELECT `name`, `date`, `game3GM` AS 3GM
    FROM ibl_box_scores
    WHERE pid != 0
    ORDER BY 3GM DESC LIMIT 10;";
$top103GMResult = mysql_query($top103GMQuery);
$top103GMRows = mysql_num_rows($top103GMResult);

echo "<table border=1>\n";
echo "\t<th colspan=4 align=center>Three Pointers Made</th>\n";
$i = 0;
while ($i < $top103GMRows) {
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
    echo "\t\t\t" . ($i + 1) . "\n";
    echo "\t\t</td>\n";
    $j = 0;
    while ($j < 3) {
        echo "\t\t<td>\n";
        echo "\t\t\t" . mysql_result($top103GMResult, $i, $j) . "\n";
        echo "\t\t</td>\n";
        $j++;
    }
    echo "\t</tr>\n";
    $i++;
}
echo "</table>\n";
echo "<p>\n";

echo "\n";
echo "\t\t</td>\n";
echo "\t</tr>\n";
echo "</table>";

echo "<H1>Teams' Season Highs</H1>";

function teamname ($teamid)
{
	$query = "SELECT team_name FROM nuke_ibl_team_info WHERE teamid = $teamid LIMIT 1";
	$result = mysql_query($query);
	$name = mysql_result($result, 0);
	return $name;
}

$top10PointsQuery = "SELECT `name`, `date`, (`game2GM`*2) + `gameFTM` + (`game3GM`*3) AS points
    FROM ibl_box_scores
    WHERE pid = 0
    ORDER BY points DESC LIMIT 10;";
$top10PointsResult = mysql_query($top10PointsQuery);
$top10PointsRows = mysql_num_rows($top10PointsResult);

echo "<table>\n";
echo "\t<tr>\n";
echo "\t\t<td align=center>\n";
echo "\n";

echo "<table border=1>\n";
echo "\t<th colspan=4 align=center>POINTS</th>\n";
$i = 0;
while ($i < $top10PointsRows) {
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
    echo "\t\t\t" . ($i + 1) . "\n";
    echo "\t\t</td>\n";
    $j = 0;
    while ($j < 3) {
        echo "\t\t<td>\n";
        echo "\t\t\t" . mysql_result($top10PointsResult, $i, $j) . "\n";
        echo "\t\t</td>\n";
        $j++;
    }
    echo "\t</tr>\n";
    $i++;
}
echo "</table>\n";
echo "<p>\n";

echo "\n";
echo "\t\t</td>\n";
echo "\t\t<td align=center>\n";
echo "\n";

$top10ReboundsQuery = "SELECT `name`, `date`, (`gameORB` + `gameDRB`) AS rebounds
    FROM ibl_box_scores
    WHERE pid = 0
    ORDER BY rebounds DESC LIMIT 10;";
$top10ReboundsResult = mysql_query($top10ReboundsQuery);
$top10ReboundsRows = mysql_num_rows($top10ReboundsResult);

echo "<table border=1>\n";
echo "\t<th colspan=4 align=center>REBOUNDS</th>\n";
$i = 0;
while ($i < $top10ReboundsRows) {
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
    echo "\t\t\t" . ($i + 1) . "\n";
    echo "\t\t</td>\n";
    $j = 0;
    while ($j < 3) {
        echo "\t\t<td>\n";
        echo "\t\t\t" . mysql_result($top10ReboundsResult, $i, $j) . "\n";
        echo "\t\t</td>\n";
        $j++;
    }
    echo "\t</tr>\n";
    $i++;
}
echo "</table>\n";
echo "<p>\n";

echo "\n";
echo "\t\t</td>\n";
echo "\t\t<td align=center>\n";
echo "\n";

$top10AssistsQuery = "SELECT `name`, `date`, `gameAST` AS assists
    FROM ibl_box_scores
    WHERE pid = 0
    ORDER BY assists DESC LIMIT 10;";
$top10AssistsResult = mysql_query($top10AssistsQuery);
$top10AssistsRows = mysql_num_rows($top10AssistsResult);

echo "<table border=1>\n";
echo "\t<th colspan=4 align=center>ASSISTS</th>\n";
$i = 0;
while ($i < $top10AssistsRows) {
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
    echo "\t\t\t" . ($i + 1) . "\n";
    echo "\t\t</td>\n";
    $j = 0;
    while ($j < 3) {
        echo "\t\t<td>\n";
        echo "\t\t\t" . mysql_result($top10AssistsResult, $i, $j) . "\n";
        echo "\t\t</td>\n";
        $j++;
    }
    echo "\t</tr>\n";
    $i++;
}
echo "</table>\n";
echo "<p>\n";

echo "\n";
echo "\t\t</td>\n";
echo "\t</tr>\n";
echo "\t<tr>\n";
echo "\t\t<td align=center>\n";
echo "\n";

$top10StealsQuery = "SELECT `name`, `date`, `gameSTL` AS steals
    FROM ibl_box_scores
    WHERE pid = 0
    ORDER BY steals DESC LIMIT 10;";
$top10StealsResult = mysql_query($top10StealsQuery);
$top10StealsRows = mysql_num_rows($top10StealsResult);

echo "<table border=1>\n";
echo "\t<th colspan=4 align=center>STEALS</th>\n";
$i = 0;
while ($i < $top10StealsRows) {
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
    echo "\t\t\t" . ($i + 1) . "\n";
    echo "\t\t</td>\n";
    $j = 0;
    while ($j < 3) {
        echo "\t\t<td>\n";
        echo "\t\t\t" . mysql_result($top10StealsResult, $i, $j) . "\n";
        echo "\t\t</td>\n";
        $j++;
    }
    echo "\t</tr>\n";
    $i++;
}
echo "</table>\n";
echo "<p>\n";

echo "\n";
echo "\t\t</td>\n";
echo "\t\t<td align=center>\n";
echo "\n";

$top10BlocksQuery = "SELECT `name`, `date`, `gameBLK` AS blocks
    FROM ibl_box_scores
    WHERE pid = 0
    ORDER BY blocks DESC LIMIT 10;";
$top10BlocksResult = mysql_query($top10BlocksQuery);
$top10BlocksRows = mysql_num_rows($top10BlocksResult);

echo "<table border=1>\n";
echo "\t<th colspan=4 align=center>BLOCKS</th>\n";
$i = 0;
while ($i < $top10BlocksRows) {
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
    echo "\t\t\t" . ($i + 1) . "\n";
    echo "\t\t</td>\n";
    $j = 0;
    while ($j < 3) {
        echo "\t\t<td>\n";
        echo "\t\t\t" . mysql_result($top10BlocksResult, $i, $j) . "\n";
        echo "\t\t</td>\n";
        $j++;
    }
    echo "\t</tr>\n";
    $i++;
}
echo "</table>\n";
echo "<p>\n";

echo "\n";
echo "\t\t</td>\n";
echo "\t</tr>\n";
echo "\t<tr>\n";
echo "\t\t<td align=center>\n";
echo "\n";

$top10FGMQuery = "SELECT `name`, `date`, (`game2GM` + `game3GM`) AS FGM
    FROM ibl_box_scores
    WHERE pid = 0
    ORDER BY FGM DESC LIMIT 10;";
$top10FGMResult = mysql_query($top10FGMQuery);
$top10FGMRows = mysql_num_rows($top10FGMResult);

echo "<table border=1>\n";
echo "\t<th colspan=4 align=center>Field Goals Made</th>\n";
$i = 0;
while ($i < $top10FGMRows) {
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
    echo "\t\t\t" . ($i + 1) . "\n";
    echo "\t\t</td>\n";
    $j = 0;
    while ($j < 3) {
        echo "\t\t<td>\n";
        echo "\t\t\t" . mysql_result($top10FGMResult, $i, $j) . "\n";
        echo "\t\t</td>\n";
        $j++;
    }
    echo "\t</tr>\n";
    $i++;
}
echo "</table>\n";
echo "<p>\n";

echo "\n";
echo "\t\t</td>\n";
echo "\t\t<td align=center>\n";
echo "\n";

$top10FTMQuery = "SELECT `name`, `date`, `gameFTM` AS FTM
    FROM ibl_box_scores
    WHERE pid = 0
    ORDER BY FTM DESC LIMIT 10;";
$top10FTMResult = mysql_query($top10FTMQuery);
$top10FTMRows = mysql_num_rows($top10FTMResult);

echo "<table border=1>\n";
echo "\t<th colspan=4 align=center>Free Throws Made</th>\n";
$i = 0;
while ($i < $top10FTMRows) {
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
    echo "\t\t\t" . ($i + 1) . "\n";
    echo "\t\t</td>\n";
    $j = 0;
    while ($j < 3) {
        echo "\t\t<td>\n";
        echo "\t\t\t" . mysql_result($top10FTMResult, $i, $j) . "\n";
        echo "\t\t</td>\n";
        $j++;
    }
    echo "\t</tr>\n";
    $i++;
}
echo "</table>\n";
echo "<p>\n";

echo "\n";
echo "\t\t</td>\n";
echo "\t\t<td align=center>\n";
echo "\n";

$top103GMQuery = "SELECT `name`, `date`, `game3GM` AS 3GM
    FROM ibl_box_scores
    WHERE pid = 0
    ORDER BY 3GM DESC LIMIT 10;";
$top103GMResult = mysql_query($top103GMQuery);
$top103GMRows = mysql_num_rows($top103GMResult);

echo "<table border=1>\n";
echo "\t<th colspan=4 align=center>Three Pointers Made</th>\n";
$i = 0;
while ($i < $top103GMRows) {
    echo "\t<tr>\n";
    echo "\t\t<td align=center>\n";
    echo "\t\t\t" . ($i + 1) . "\n";
    echo "\t\t</td>\n";
    $j = 0;
    while ($j < 3) {
        echo "\t\t<td>\n";
        echo "\t\t\t" . mysql_result($top103GMResult, $i, $j) . "\n";
        echo "\t\t</td>\n";
        $j++;
    }
    echo "\t</tr>\n";
    $i++;
}
echo "</table>\n";
echo "<p>\n";

echo "\n";
echo "\t\t</td>\n";
echo "\t</tr>\n";
echo "</table>";
