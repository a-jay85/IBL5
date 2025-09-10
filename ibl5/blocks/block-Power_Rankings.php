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

global $db;

$query = "SELECT TeamID, Team, ranking, win, loss, color1, color2
    FROM ibl_power
    INNER JOIN ibl_team_info info USING (teamid)
    ORDER BY ranking DESC;";
$result = $db->sql_query($query);
$num = $db->sql_numrows($result);

$content .= "<table width=150>";

$i = 0;
while ($i < $num) {
    $tid = $db->sql_result($result, $i, "TeamID");
    $Team = $db->sql_result($result, $i, "Team");
    $ranking = $db->sql_result($result, $i, "ranking");
    $wins = $db->sql_result($result, $i, "win");
    $losses = $db->sql_result($result, $i, "loss");
    $teamcolor1 = $db->sql_result($result, $i, "color1");
    $teamcolor2 = $db->sql_result($result, $i, "color2");

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
