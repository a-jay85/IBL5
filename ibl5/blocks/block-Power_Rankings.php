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

if (!defined('BLOCK_FILE')) {
    Header("Location: ../index.php");
    die();
}

global $mysqli_db;

$query = "SELECT TeamID, Team, ranking, win, loss, color1, color2
    FROM ibl_power
    INNER JOIN ibl_team_info info USING (teamid)
    ORDER BY ranking DESC;";
$result = $mysqli_db->query($query);

$content .= "<table width=150>";

$i = 0;
while ($row = $result->fetch_assoc()) {
    $tid = $row['TeamID'];
    $Team = $row['Team'];
    $ranking = $row['ranking'];
    $wins = $row['win'];
    $losses = $row['loss'];
    $teamcolor1 = $row['color1'];
    $teamcolor2 = $row['color2'];

    $bgcolor = "$teamcolor1";

    $content .= "<tr>
        <td align=right valign=top>" . ($i + 1) . ".</td>
        <td bgcolor=$bgcolor align=center><a href=\"modules.php?name=Team&op=team&teamID=$tid\"><font color=#$teamcolor2>$Team</font></a></td>
        <td align=right valign=top>$ranking</td>
    </tr>";

    $i++;
}

$content .= "<tr>
    <td colspan=3><center><a href=\"modules.php?name=Power_Rankings\"><font color=#aaaaaa><i>-- Full Power Rankings --</i></font></a></center></td>
</tr>
</table>";
