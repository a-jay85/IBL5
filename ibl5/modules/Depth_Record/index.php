<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2006 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
Nuke\Header::header();

$query2 = "SELECT * FROM ibl_team_history WHERE teamid != " . League::FREE_AGENTS_TEAMID . " ORDER BY teamid ASC";
$result2 = $db->sql_query($query2);
$num2 = $db->sql_numrows($result2);

OpenTable();
UI::displaytopmenu($db, 0);

$k = 0;
while ($k < $num2) {
    $teamname[$k] = $db->sql_result($result2, $k, "team_name");
    $teamcity[$k] = $db->sql_result($result2, $k, "team_city");
    $teamcolor1[$k] = $db->sql_result($result2, $k, "color1");
    $teamcolor2[$k] = $db->sql_result($result2, $k, "color2");
    $depth[$k] = $db->sql_result($result2, $k, "depth");
    $simdepth[$k] = $db->sql_result($result2, $k, "sim_depth");
    $asg_vote[$k] = $db->sql_result($result2, $k, "asg_vote");
    $eoy_vote[$k] = $db->sql_result($result2, $k, "eoy_vote");
    $teamID[$k] = (int) $db->sql_result($result2, $k, "teamid"); // Ensure teamID is an integer

    $table_echo .= "<tr>
		<td bgcolor=#" . $teamcolor1[$k] . "><a href=\"modules.php?name=Team&op=team&teamID=" . $teamID[$k] . "\"><font color=#" . $teamcolor2[$k] . ">" . $teamcity[$k] . " " . $teamname[$k] . "</a></td>
		<td>" . $simdepth[$k] . "</td>
		<td>" . $depth[$k] . "</td>
		<td>" . $asg_vote[$k] . "</td>
		<td>" . $eoy_vote[$k] . "</td>
	</tr>";

    $k++;
}

$text .= "<table class=\"sortable\" border=1>
	<tr>
	  	<th>Team</th>
		<th>Sim Depth Chart</th>
		<th>Last Depth Chart</th>
		<th>ASG Ballot</th>
		<th>EOY Ballot</th>
	</tr>$table_echo
</table>";

echo $text;

CloseTable();
Nuke\Footer::footer();
