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

if (!eregi("modules.php", $_SERVER['PHP_SELF'])) {
	die ("You can't access this file directly...");
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/sharedFunctions.php';
require_once("mainfile.php");
$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$userpage = 1;
include("header.php");

$currentSeasonEndingYear = getCurrentSeasonEndingYear();
$fiveSeasonsAgoEndingYear = $currentSeasonEndingYear - 4;

$query2 = "SELECT *,
	SUM(nuke_iblteam_win_loss.wins) as five_season_wins,
	SUM(nuke_iblteam_win_loss.losses) as five_season_losses,
	(SUM(nuke_iblteam_win_loss.wins) + SUM(nuke_iblteam_win_loss.losses)) as totalgames,
	ROUND((SUM(nuke_iblteam_win_loss.wins) / (SUM(nuke_iblteam_win_loss.wins) + SUM(nuke_iblteam_win_loss.losses))), 3) as five_season_winpct
FROM ibl_team_history
INNER JOIN nuke_iblteam_win_loss ON nuke_iblteam_win_loss.currentname = ibl_team_history.team_name
WHERE teamid != 35
AND year BETWEEN $fiveSeasonsAgoEndingYear AND $currentSeasonEndingYear
GROUP BY currentname
ORDER BY teamid ASC;";
$result2 = mysql_query($query2);
$num2 = mysql_num_rows($result2);

function getNumberOfTitles($teamname, $titleName)
{
	$queryCountTitles = "SELECT COUNT(name)
	FROM nuke_ibl_teamawards
	WHERE name = '$teamname'
	AND Award LIKE '%$titleName%';";

	return mysql_result(mysql_query($queryCountTitles), 0);
}

OpenTable();

$k = 0;
while ($k < $num2) {
	$teamid[$k] = mysql_result($result2, $k, "teamid");
	$teamname[$k] = mysql_result($result2, $k, "team_name");
	$teamcity[$k] = mysql_result($result2, $k, "team_city");
	$teamcolor1[$k] = mysql_result($result2, $k, "color1");
	$teamcolor2[$k] = mysql_result($result2, $k, "color2");

    $totwins[$k] = mysql_result($result2, $k, "totwins");
    $totloss[$k] = mysql_result($result2, $k, "totloss");
    $pct[$k] = mysql_result($result2, $k, "winpct");

	$lastFiveSeasonsWins[$k] = mysql_result($result2, $k, "five_season_wins");
	$lastFiveSeasonsLosses[$k] = mysql_result($result2, $k, "five_season_losses");
	$lastFiveSeasonsWinPct[$k] = mysql_result($result2, $k, "five_season_winpct");

    $playoffs[$k] = mysql_result($result2, $k, "playoffs");
    $heat[$k] = mysql_result($result2, $k, "heat_titles");
    $div[$k] = mysql_result($result2, $k, "div_titles");
    $conf[$k] = mysql_result($result2, $k, "conf_titles");
    $ibl[$k] = mysql_result($result2, $k, "ibl_titles");

	$table_echo .= "<tr>
		<td bgcolor=#" . $teamcolor1[$k] . "><a href=\"modules.php?name=Team&op=team&tid=" . $teamid[$k] . "\"><font color=#" . $teamcolor2[$k] . ">" . $teamcity[$k] . " " . $teamname[$k] . "</a></td>
		<td>" . $totwins[$k] . "</td>
		<td>" . $totloss[$k] . "</td>
		<td>" . $pct[$k] . "</td>
		<td bgcolor=#ddd>" . $lastFiveSeasonsWins[$k] . "</td>
		<td bgcolor=#ddd>" . $lastFiveSeasonsLosses[$k] . "</td>
		<td bgcolor=#ddd>" . $lastFiveSeasonsWinPct[$k] . "</td>
		<td>" . $playoffs[$k] . "</td>
		<td>" . getNumberOfTitles($teamname[$k], 'HEAT') . "</td>
		<td>" . getNumberOfTitles($teamname[$k], 'Division') . "</td>
		<td>" . getNumberOfTitles($teamname[$k], 'Conference') . "</td>
		<td>" . getNumberOfTitles($teamname[$k], 'IBL Champions') . "</td>
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
include("footer.php");

?>
