<?php

if (!defined('BLOCK_FILE')) {
    Header("Location: ./index.php");
    die();
}

use Player\PlayerImageHelper;

global $db;

$queryTopFiveInSeasonStatAverages = "SELECT *
    FROM (
        SELECT
            pid,
            tid,
            name,
            teamname,
            ROUND((2 * `stats_fgm` + `stats_ftm` + `stats_3gm`) / `stats_gm`, 1) AS stat_value,
            'Points' AS stat_type,
            ROW_NUMBER() OVER (ORDER BY (2 * `stats_fgm` + `stats_ftm` + `stats_3gm`) / `stats_gm` DESC) AS rn
        FROM ibl_plr
        WHERE retired = 0 AND stats_gm > 0 AND name NOT LIKE '%Buyouts%'

        UNION ALL

        SELECT
            pid,
            tid,
            name,
            teamname,
            ROUND((`stats_orb` + `stats_drb`) / `stats_gm`, 1) AS stat_value,
            'Rebounds' AS stat_type,
            ROW_NUMBER() OVER (ORDER BY (`stats_orb` + `stats_drb`) / `stats_gm` DESC) AS rn
        FROM ibl_plr
        WHERE retired = 0 AND stats_gm > 0 AND name NOT LIKE '%Buyouts%'

        UNION ALL

        SELECT
            pid,
            tid,
            name,
            teamname,
            ROUND(`stats_ast` / `stats_gm`, 1) AS stat_value,
            'Assists' AS stat_type,
            ROW_NUMBER() OVER (ORDER BY `stats_ast` / `stats_gm` DESC) AS rn
        FROM ibl_plr
        WHERE retired = 0 AND stats_gm > 0 AND name NOT LIKE '%Buyouts%'

        UNION ALL

        SELECT
            pid,
            tid,
            name,
            teamname,
            ROUND(`stats_stl` / `stats_gm`, 1) AS stat_value,
            'Steals' AS stat_type,
            ROW_NUMBER() OVER (ORDER BY `stats_stl` / `stats_gm` DESC) AS rn
        FROM ibl_plr
        WHERE retired = 0 AND stats_gm > 0 AND name NOT LIKE '%Buyouts%'

        UNION ALL

        SELECT
            pid,
            tid,
            name,
            teamname,
            ROUND(`stats_blk` / `stats_gm`, 1) AS stat_value,
            'Blocks' AS stat_type,
            ROW_NUMBER() OVER (ORDER BY `stats_blk` / `stats_gm` DESC) AS rn
        FROM ibl_plr
        WHERE retired = 0 AND stats_gm > 0 AND name NOT LIKE '%Buyouts%'
    ) t
    WHERE rn <= 5
    ORDER BY FIELD(stat_type, 'Points', 'Rebounds', 'Assists', 'Steals', 'Blocks'), rn;";
$resultTopFiveInSeasonStatAverages = $db->sql_query($queryTopFiveInSeasonStatAverages);

$rows = $resultTopFiveInSeasonStatAverages->fetch_all(MYSQLI_ASSOC);
$rowNumber = 0;

$content = '<table style="border:1px solid #000066; margin: 0 auto;">
    <tr>';
for ($i = 1; $i <= 5; $i++) {
    $content .= '<td style="border:1px solid #000066;">
                <table>
                    <tr>
                        <td style="min-width:155px;" colspan=2>
                            <div style="text-align:center;">
                                <img src="' . PlayerImageHelper::getImageUrl($rows[$rowNumber]['pid']) . '" height="90" width="65">
                                <img src="./images/logo/new' . $rows[$rowNumber]['tid'] . '.png" height="75" width="75">
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="background-color:#000066; color:#ffffff; font-weight:bold;">
                            ' . $rows[$rowNumber]['stat_type'] . ' Per Game
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight:bold;">
                            <a href="modules.php?name=Player&pa=showpage&pid=' . $rows[$rowNumber]['pid'] . '" style="color:#000066;">
                                ' . $rows[$rowNumber]['name'] . '
                            </a>
                            <br>
                            <a href="modules.php?name=Team&op=team&teamID=' . $rows[$rowNumber]['tid'] . '" style="color:#000066;">
                                ' . $rows[$rowNumber]['teamname'] . '
                            </a>
                        </td>
                        <td valign="top" style="font-weight:bold;">
                            ' . $rows[$rowNumber]['stat_value'] . '
                        </td>
                    </tr>';
    $rowNumber++;
    for ($j = 2; $j <= 5; $j++) {
        $content .= '<tr>
                        <td>
                            <a href="modules.php?name=Player&pa=showpage&pid=' . $rows[$rowNumber]['pid'] . '" style="color:#000066;">
                                ' . $rows[$rowNumber]['name'] . '
                            </a>
                            <br>
                            <a href="modules.php?name=Team&op=team&teamID=' . $rows[$rowNumber]['tid'] . '" style="color:#000066;">
                                ' . $rows[$rowNumber]['teamname'] . '
                            </a>
                        </td>
                        <td valign="top">
                            ' . $rows[$rowNumber]['stat_value'] . '
                        </td>
                    </tr>';
        $rowNumber++;
    }
    $content .= '</table>
            </td>';
}
$content .= '</tr>
</table>';

?>