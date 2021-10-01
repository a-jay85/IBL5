<?php
/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2005 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if ( !defined('BLOCK_FILE') ) {
    Header("Location: ./index.php");
    die();
}

global $prefix, $multilingual, $currentlang, $db;
$content=$content."<center><a href=modules.php?name=Chunk_Stats&op=chunk>Sim Stats Search Engine</a></center><br>";

function getLastSimStatLeaders($statName, $query)
{
    $queryLastSimDates = mysql_query("SELECT * FROM ibl_sim_dates ORDER BY Sim DESC LIMIT 1");
    $lastSimNumber = mysql_result($queryLastSimDates, 0, "Sim");
    $lastSimStartDate = mysql_result($queryLastSimDates, 0, "Start Date");
    $lastSimEndDate = mysql_result($queryLastSimDates, 0, "End Date");

    $querySimStatLeaders = mysql_query("SELECT players.name, boxes.pid, teamname, players.tid, CAST(FORMAT(($query / COUNT(players.NAME)), 1) AS DECIMAL(3,1)) as `$statName`
        FROM ibl_box_scores boxes
        INNER JOIN nuke_iblplyr players USING(pid)
        WHERE Date BETWEEN '$lastSimStartDate' AND '$lastSimEndDate'
        GROUP BY name
        ORDER BY $statName DESC
        LIMIT 5;");

    $i = 1;
    while ($i <= mysql_num_rows($querySimStatLeaders)) {
        $row = mysql_fetch_assoc($querySimStatLeaders);
        $array[$i]["name"] = $row["name"];
        $array[$i]["pid"] = $row["pid"];
        $array[$i]["teamname"] = $row["teamname"];
        $array[$i]["tid"] = $row["tid"];
        $array[$i]["stat"] = $row["$statName"];

        $i++;
    }

    return $array;
}

function displayColumnLastSimStatLeaders($array, $statName, $content)
{
    $content = $content."<td>
        <table><tr><td colspan=2>
        <center><a href=modules.php?name=Player&pa=showpage&pid=" . $array[1]["pid"] . "><img src=\"./images/player/" . $array[1]["pid"] . ".jpg\" height=\"90\" width=\"65\"></a>&nbsp;
        <a href=modules.php?name=Team&op=team&tid=" . $array[1]["tid"] . "><img src=\"./images/logo/new" . $array[1]["tid"] . ".png\" height=\"75\" width=\"75\"></a></center></td></tr>
        <tr><td bgcolor=#000066 colspan=2><b><font color=#ffffff>$statName Per Game</td></tr>";
    $content = $content."<tr><td><b><a href=modules.php?name=Player&pa=showpage&pid=" . $array[1]["pid"] . "><font color=#000066>" . $array[1]["name"] . "</font></a><br>
    <font color=#000066><a href=modules.php?name=Team&op=team&tid=" . $array[1]["tid"] . ">" . $array[1]["teamname"] . "</a></font></td>
    <td valign=top>" . $array[1]["stat"] . "</td></tr>";
    $content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=" . $array[2]["pid"] . "><font color=#000066>" . $array[2]["name"] . "</font></a><br>
    <font color=#000066><a href=modules.php?name=Team&op=team&tid=" . $array[2]["tid"] . ">" . $array[2]["teamname"] . "</a></font></td>
    <td valign=top>" . $array[2]["stat"] . "</td></tr>";
    $content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=" . $array[3]["pid"] . "><font color=#000066>" . $array[3]["name"] . "</font></a><br>
    <font color=#000066><a href=modules.php?name=Team&op=team&tid=" . $array[3]["tid"] . ">" . $array[3]["teamname"] . "</a></font></td>
    <td valign=top>" . $array[3]["stat"] . "</td></tr>";
    $content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=" . $array[4]["pid"] . "><font color=#000066>" . $array[4]["name"] . "</font></a><br>
    <font color=#000066><a href=modules.php?name=Team&op=team&tid=" . $array[4]["tid"] . ">" . $array[4]["teamname"] . "</a></font></td>
    <td valign=top>" . $array[4]["stat"] . "</td></tr>";
    $content = $content."<tr><td><a href=modules.php?name=Player&pa=showpage&pid=" . $array[5]["pid"] . "><font color=#000066>" . $array[5]["name"] . "</font></a><br>
    <font color=#000066><a href=modules.php?name=Team&op=team&tid=" . $array[5]["tid"] . ">" . $array[5]["teamname"] . "</a></font></td>
    <td valign=top>" . $array[5]["stat"] . "</td></tr>";
    $content = $content."</table></td>";

    return $content;
}

$lastSimPointsLeaders = getLastSimStatLeaders('POINTS', '(2 * SUM(game2GM) + SUM(gameFTM) + 3 * SUM(game3GM))');
$lastSimReboundsLeaders = getLastSimStatLeaders('REBOUNDS', '(SUM(gameORB) + SUM(gameDRB))');
$lastSimAssistsLeaders = getLastSimStatLeaders('ASSISTS', 'SUM(gameAST)');
$lastSimStealsLeaders = getLastSimStatLeaders('STEALS', 'SUM(gameSTL)');
$lastSimBlocksLeaders = getLastSimStatLeaders('BLOCKS', 'SUM(gameBLK)');

$content = $content."<center><table border=1 bordercolor=#000066><tr>";

$content = displayColumnLastSimStatLeaders($lastSimPointsLeaders, "Points", $content);
$content = displayColumnLastSimStatLeaders($lastSimReboundsLeaders, "Rebounds", $content);
$content = displayColumnLastSimStatLeaders($lastSimAssistsLeaders, "Assists", $content);
$content = displayColumnLastSimStatLeaders($lastSimStealsLeaders, "Steals", $content);
$content = displayColumnLastSimStatLeaders($lastSimBlocksLeaders, "Blocks", $content);

$content = $content."</tr></table>";

?>
