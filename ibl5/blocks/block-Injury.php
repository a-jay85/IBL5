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

global $prefix, $multilingual, $currentlang, $db;

$queryo = "SELECT * FROM nuke_users WHERE user_ibl_team != '' ORDER BY user_ibl_team ASC";
$resulto = mysql_query($queryo);
$numo = mysql_numrows($resulto);

$content = "<table border=0>
    <tr>
        <td colspan=4>
            <b>The following teams need to submit a new lineup or sign player(s) from waivers due to injury:</b>
        </td>
    </tr>
    <tr>
        <td bgcolor=#000066>
            <font color=#ffffff><b>TEAM NAME</b></font>
        </td>
        <td bgcolor=#000066>
            <font color=#ffffff><b>HEALTHY PLAYERS</b></font>
        </td>
        <td bgcolor=#000066>
            <font color=#ffffff><b>WAIVERS NEEDED</b></font>
        </td>
        <td bgcolor=#000066>
            <font color=#ffffff><b>NEW LINEUP NEEDED</b></font>
        </td>
    </tr>";

$j = 0;
while ($j < $numo) {
	$user_team = mysql_result($resulto, $j, "user_ibl_team");

	$sql = "SELECT *
        FROM nuke_iblplyr
        WHERE teamname = '$user_team'
        AND retired = '0'
        AND injured = '0'
        AND droptime = '0'
        AND ordinal < 960
        AND name NOT LIKE '%Buyout%'"; // "ordinal < 960" excludes waived players from this query
	$result1 = mysql_query($sql);
	$num1 = mysql_numrows($result1);

	$sql2 = "SELECT *
        FROM nuke_iblplyr
        WHERE teamname = '$user_team'
        AND retired = '0'
        AND injured > '0'
        AND active = '1'";
	$result2 = mysql_query($sql2);
	$num2 = mysql_numrows($result2);

	if ($num2 > 0) {
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
	$sql3 = "SELECT chart FROM nuke_ibl_team_info WHERE team_name = '$user_team'";
	$result3 = mysql_query($sql3);
	$chart = mysql_result($result3, 0, "chart");

	if ($waivers_needed > 0 || $new_lineups == 'Yes' && $chart == 0) {
		$content .= "<tr><td>$user_team</td><td>$healthy</td><td>$waivers_needed</td><td>$new_lineups</td></tr>";
	}

    $j++;
}

$content .= "</table>";

?>
