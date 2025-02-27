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

$queryo = "SELECT * FROM nuke_users WHERE user_ibl_team != '' ORDER BY user_ibl_team ASC";
$resulto = $db->sql_query($queryo);
$numo = $db->sql_numrows($resulto);

$content = "<table border=0><tr><td colspan=4><b>The following teams need to submit a new lineup or sign player(s) from waivers due to injury:</b></td></tr><tr><td bgcolor=#000066><font color=#ffffff><b>TEAM NAME</td><td bgcolor=#000066><font color=#ffffff><b>HEALTHY PLAYERS</td><td bgcolor=#000066><font color=#ffffff><b>WAIVERS NEEDED</td><td bgcolor=#000066><font color=#ffffff><b>NEW LINEUP NEEDED</td></tr>";

$j = 0;
while ($j < $numo) {
    $user_team = $db->sql_result($resulto, $j, "user_ibl_team");

    $sql = "SELECT * FROM ibl_plr WHERE teamname='$user_team' AND retired = '0' AND ordinal < '961' AND injured = '0' ORDER BY ordinal ASC ";
    $result1 = $db->sql_query($sql);
    $num1 = $db->sql_numrows($result1);

    $sql2 = "SELECT * FROM ibl_plr WHERE teamname='$user_team' AND retired = '0' AND ordinal < '961' AND injured = '0' AND active = '1' ORDER BY ordinal ASC ";
    $result2 = $db->sql_query($sql2);
    $num2 = $db->sql_numrows($result2);

    if ($num2 < 12) {
        $new_lineups = 'Yes';
    } else {
        $new_lineups = 'No';
    }

    $waivers_needed = 12;
    $healthy = 0;
    $i = 0;
    while ($i < $num1) {
        $healthy++;
        $i++;
    }

    $waivers_needed = $waivers_needed - $healthy;
    if ($waivers_needed < 0) {
        $waivers_needed = 0;
    }
    $sql3 = "SELECT chart FROM ibl_team_info WHERE team_name='$user_team'";
    $result3 = $db->sql_query($sql3);
    $chart = $db->sql_result($result3, 0, "chart");

    if ($waivers_needed > 0 || $new_lineups == 'Yes' && $chart == 0) {
        $content .= "<tr><td>$user_team</td><td>$healthy</td><td>$waivers_needed</td><td>$new_lineups</td></tr>";
    }
    $j++;
}

$content .= "</table>";
