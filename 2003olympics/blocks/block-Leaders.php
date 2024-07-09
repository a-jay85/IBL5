<?php

if (!defined('BLOCK_FILE')) {
    Header("Location: ./index.php");
    die();
}

global $db;

function topFiveSeasonPerGameAverage($db, $statName, $statQuery) {
    $queryStatPerGame= "SELECT
        pid,
        tid,
        name,
        teamname,
        ROUND(" . $statQuery . " / `stats_gm`, 1) as " . $statName . "
    FROM ibl_plr 
    WHERE 
        retired = 0
        AND stats_gm > 0
        AND name NOT LIKE \"%Buyouts%\"
    ORDER BY " . $statName . " DESC
    LIMIT 5;";
    $resultStatPerGame = $db->sql_query($queryStatPerGame);

    $i = 1;
    foreach ($resultStatPerGame as $row) {
        $array[$i] = $row;
        $i++;
    }

    $content = "<td>
        <table>
            <tr>
                <td style=\"min-width:155px\" colspan=2>
                    <center>
                        <img src=\"./images/player/" . $array[1]['pid'] . ".jpg\" height=\"90\" width=\"65\">
                        <img src=\"./images/logo/new" . $array[1]['tid'] . ".png\" height=\"75\" width=\"75\">
                    </center>
                </td>
            </tr>
            <tr>
                <td bgcolor=#000066 colspan=2><b><font color=#ffffff>$statName Per Game</td>
            </tr>
            <tr>
                <td>
                    <b>
                        <a href=modules.php?name=Player&pa=showpage&pid=" . $array[1]['pid'] . "><font color=#000066>" . $array[1]['name'] . "</font></a>
                        <br>
                        <font color=#000066>" . $array[1]['teamname'] . "</font>
                    </b>
                </td>
                <td valign=top><b>" . $array[1][$statName] . "</b></td>
            </tr>";

    $j = 2;
    while ($j <= 5) {
        $content .= "<tr>
            <td>
                <a href=modules.php?name=Player&pa=showpage&pid=" . $array[$j]['pid'] . "><font color=#000066>" . $array[$j]['name'] . "</font></a>
                <br>
                <font color=#000066>" . $array[$j]['teamname'] . "</font>
            </td>
            <td valign=top>" . $array[$j][$statName] . "</td>
        </tr>";
        $j++;
    }

    $content .= "</table></td>";

    return $content;
}

$content .= "<center>
    <table border=1 bordercolor=#000066>
        <tr>";
$content .= topFiveSeasonPerGameAverage($db, 'Points', '(2 * `stats_fgm` + `stats_ftm` + `stats_3gm`)');
$content .= topFiveSeasonPerGameAverage($db, 'Rebounds', '(`stats_orb` + `stats_drb`)');
$content .= topFiveSeasonPerGameAverage($db, 'Assists', '`stats_ast`');
$content .= topFiveSeasonPerGameAverage($db, 'Steals', '`stats_stl`');
$content .= topFiveSeasonPerGameAverage($db, 'Blocks', '`stats_blk`');
$content .= "
        </tr>
    </table>";
