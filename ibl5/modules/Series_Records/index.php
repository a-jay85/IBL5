<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2002 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if (!eregi("modules.php", $_SERVER['PHP_SELF'])) die ("You can't access this file directly...");

require_once("mainfile.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/sharedFunctions.php';

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$userpage = 1;

function userinfo($username, $bypass = 0, $hid = 0, $url = 0)
{
	global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $useset, $subscription_url;
	$sql = "SELECT * FROM " . $prefix . "_bbconfig";
	$result = $db->sql_query($sql);
	while ($row = $db->sql_fetchrow($result)) {
		$board_config[$row['config_name']] = $row['config_value'];
	}
	$sql2 = "SELECT * FROM " . $user_prefix . "_users WHERE username = '$username'";
	$result2 = $db->sql_query($sql2);
	$userinfo = $db->sql_fetchrow($result2);
	if (!$bypass) {
		cookiedecode($user);
	}

	$teamlogo = $userinfo[user_ibl_team];
	$tid = getTidFromTeamname($teamlogo);

	include("header.php");
	OpenTable();
	displaytopmenu($tid);

	displaySeriesRecords();

	CloseTable();
	include("footer.php");
}

function main($user) {
	global $stop;
	if (!is_user($user)) {
		include("header.php");
		OpenTable();
		echo "<center><font class=\"title\"><b>" . ($stop ? _LOGININCOR : _USERREGLOGIN) . "</b></font></center>";
		CloseTable();
		echo "<br>";
		if (!is_user($user)) {
			OpenTable();
			loginbox();
			CloseTable();
		}
		include("footer.php");
	} elseif (is_user($user)) {
		global $cookie;
		cookiedecode($user);
		userinfo($cookie[1]);
	}
}

function queryTeamInfo()
{
	$query = "SELECT teamid, team_city, team_name, color1, color2
		FROM nuke_ibl_team_info
		WHERE teamid != 99 AND teamid != 35
		ORDER BY teamid ASC;";
	$result = mysql_query($query);
	return $result;
}

function querySeriesRecords()
{
	$query = "SELECT self, opponent, SUM(wins) AS wins, SUM(losses) AS losses
				FROM (
					SELECT home AS self, visitor AS opponent, COUNT(*) AS wins, 0 AS losses
					FROM ibl_schedule
					WHERE HScore > VScore
					GROUP BY self, opponent

					UNION ALL

					SELECT visitor AS self, home AS opponent, COUNT(*) AS wins, 0 AS losses
					FROM ibl_schedule
					WHERE VScore > HScore
					GROUP BY self, opponent

					UNION ALL

					SELECT home AS self, visitor AS opponent, 0 AS wins, COUNT(*) AS losses
					FROM ibl_schedule
					WHERE HScore < VScore
					GROUP BY self, opponent

					UNION ALL

					SELECT visitor AS self, home AS opponent, 0 AS wins, COUNT(*) AS losses
					FROM ibl_schedule
					WHERE VScore < HScore
					GROUP BY self, opponent
				) t
				GROUP BY self, opponent;";
	$result = mysql_query($query);
	return $result;
}

function displaySeriesRecords()
{
	$numteams = mysql_result(mysql_query("SELECT MAX(Visitor) FROM ibl_schedule;"), 0);

	echo "<table border=1 class=\"sortable\">
		<tr>
			<th>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&rarr;&rarr;<br>
			vs.<br>
			&uarr;</th>";
	$i = 1;
	while ($i <= $numteams) {
		echo "<th align=\"center\">
			<img src=\"images/logo/new$i.png\" width=50 height=50>
		</th>";
		$i++;
	}
	echo "</tr>";

	$resultSeriesRecords = querySeriesRecords();
	$resultTeamInfo = queryTeamInfo();

	$pointer = 0;
	$tidRow = 1;
	while ($tidRow <= $numteams) {
		$team = mysql_fetch_assoc($resultTeamInfo);
		echo "<tr>
			<td bgcolor=$team[color1]>
				<a href=\"modules.php?name=Team&op=team&tid=$team[teamid]\">
					<font color=\"$team[color2]\">
						$team[team_city] $team[team_name]
					</font>
				</a>
			</td>";
		$tidColumn = 1;
		while ($tidColumn <= $numteams) {
			if ($tidRow == $tidColumn) {
				echo "<td align=\"center\">x</td>";
			} else {
				$row = mysql_fetch_assoc($resultSeriesRecords);
				if ($row['self'] == $tidRow AND $row['opponent'] == $tidColumn) {
					if ($row['wins'] > $row['losses']) {
						$bgcolor = "#8f8";
					} elseif ($row['wins'] < $row['losses']) {
						$bgcolor = "#f88";
					} else {
						$bgcolor = "#bbb";
					}
					echo "<td align=\"center\" bgcolor=\"$bgcolor\">$row[wins] - $row[losses]</td>";
					$pointer++;
				} else {
					echo "<td align=\"center\">0 - 0</td>";
					mysql_data_seek($resultSeriesRecords, $pointer);
				}
			}
			$tidColumn++;
		}
		echo "</tr>";
		$tidRow++;
	}

	echo "</table>";
}

switch($op) {
	default:
		main($user);
	break;
}

?>
