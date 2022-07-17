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

$sharedFunctions = new Shared($db);

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$userpage = 1;
include "header.php";

$currentSeasonEndingYear = $sharedFunctions->getCurrentSeasonEndingYear();
$fiveSeasonsAgoEndingYear = $currentSeasonEndingYear - 4;

$query2 = "SELECT *,
	SUM(ibl_team_win_loss.wins) as five_season_wins,
	SUM(ibl_team_win_loss.losses) as five_season_losses,
	(SUM(ibl_team_win_loss.wins) + SUM(ibl_team_win_loss.losses)) as totalgames,
	ROUND((SUM(ibl_team_win_loss.wins) / (SUM(ibl_team_win_loss.wins) + SUM(ibl_team_win_loss.losses))), 3) as five_season_winpct
FROM ibl_team_history
INNER JOIN ibl_team_win_loss ON ibl_team_win_loss.currentname = ibl_team_history.team_name
WHERE teamid != 35
AND year BETWEEN $fiveSeasonsAgoEndingYear AND $currentSeasonEndingYear
GROUP BY currentname
ORDER BY teamid ASC;";
$result2 = $db->sql_query($query2);
$num2 = $db->sql_numrows($result2);

OpenTable();

$k = 0;
while ($k < $num2) {
    $teamid[$k] = $db->sql_result($result2, $k, "teamid");
    $teamname[$k] = $db->sql_result($result2, $k, "team_name");
    $teamcity[$k] = $db->sql_result($result2, $k, "team_city");
    $teamcolor1[$k] = $db->sql_result($result2, $k, "color1");
    $teamcolor2[$k] = $db->sql_result($result2, $k, "color2");

    $totwins[$k] = $db->sql_result($result2, $k, "totwins");
    $totloss[$k] = $db->sql_result($result2, $k, "totloss");
    $pct[$k] = $db->sql_result($result2, $k, "winpct");

    $lastFiveSeasonsWins[$k] = $db->sql_result($result2, $k, "five_season_wins");
    $lastFiveSeasonsLosses[$k] = $db->sql_result($result2, $k, "five_season_losses");
    $lastFiveSeasonsWinPct[$k] = $db->sql_result($result2, $k, "five_season_winpct");

    $playoffs[$k] = $db->sql_result($result2, $k, "playoffs");
    $heat[$k] = $db->sql_result($result2, $k, "heat_titles");
    $div[$k] = $db->sql_result($result2, $k, "div_titles");
    $conf[$k] = $db->sql_result($result2, $k, "conf_titles");
    $ibl[$k] = $db->sql_result($result2, $k, "ibl_titles");

    $table_echo .= "<tr>
		<td bgcolor=#" . $teamcolor1[$k] . "><a href=\"modules.php?name=Team&op=team&tid=" . $teamid[$k] . "\"><font color=#" . $teamcolor2[$k] . ">" . $teamcity[$k] . " " . $teamname[$k] . "</a></td>
		<td>" . $totwins[$k] . "</td>
		<td>" . $totloss[$k] . "</td>
		<td>" . $pct[$k] . "</td>
		<td bgcolor=#ddd>" . $lastFiveSeasonsWins[$k] . "</td>
		<td bgcolor=#ddd>" . $lastFiveSeasonsLosses[$k] . "</td>
		<td bgcolor=#ddd>" . $lastFiveSeasonsWinPct[$k] . "</td>
		<td>" . $playoffs[$k] . "</td>
		<td>" . $sharedFunctions->getNumberOfTitles($teamname[$k], 'HEAT') . "</td>
		<td>" . $sharedFunctions->getNumberOfTitles($teamname[$k], 'Division') . "</td>
		<td>" . $sharedFunctions->getNumberOfTitles($teamname[$k], 'Conference') . "</td>
		<td>" . $sharedFunctions->getNumberOfTitles($teamname[$k], 'IBL Champions') . "</td>
	</tr>";

    $k++;
}

$text .= "
	<table class=\"sortable\" border=1>
		<tr>
			<th>Team</th>
			<th>All-Time<br>Wins</th>
			<th>All-Time<br>Losses</th>
			<th>All-Time<br>Pct.</th>
			<th bgcolor=#ddd>Last Five<br>Seasons<br>Wins</th>
			<th bgcolor=#ddd>Last Five<br>Seasons<br>Losses</th>
			<th bgcolor=#ddd>Last Five<br>Seasons<br>Pct.</th>
			<th>Playoffs</th>
			<th>H.E.A.T.<br>Titles</th>
			<th>Div.<br>Titles</th>
			<th>Conf.<br>Titles</th>
			<th>IBL<br>Titles</th>
		</tr>
		$table_echo
	</table>";
echo $text;

CloseTable();
include "footer.php";
