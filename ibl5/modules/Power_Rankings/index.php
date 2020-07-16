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

if (!defined('MODULE_FILE')) {
	die ("You can't access this file directly...");
}

require $_SERVER['DOCUMENT_ROOT'] . '/sharedFunctions.php';
require_once("mainfile.php");
$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- $module_name";

$currentSeasonEndingYear = getCurrentSeasonEndingYear();

include("header.php");

OpenTable();
echo "<center><font class=\"storytitle\">" . ($currentSeasonEndingYear - 1) . "-$currentSeasonEndingYear IBL Power Rankings</font></center>\n\n";
echo "<p>\n\n";

$query = "SELECT * FROM nuke_ibl_power WHERE TeamID BETWEEN 1 AND 32 ORDER BY ranking DESC";
$result = mysql_query($query);
$num = mysql_numrows($result);

echo "<table width=\"500\" cellpadding=\"4\" cellspacing=\"0\" border=\"0\" align=center>\n";
echo "<tr>\n";
echo "\t<td align=right>\n";
echo "\t\t<font class=\"storytitle\">Rank\n";
echo "\t</td>\n";
echo "\t<td align=center>\n";
echo "\t\t<font class=\"storytitle\">Team\n";
echo "\t</td>\n";
echo "\t<td align=center>\n";
echo "\t\t<font class=\"storytitle\">Record\n";
echo "\t</td>\n";
echo "\t<td align=center>\n";
echo "\t\t<font class=\"storytitle\">Home\n";
echo "\t</td>\n";
echo "\t<td align=center>\n";
echo "\t\t<font class=\"storytitle\">Away\n";
echo "\t</td>\n";
echo "\t<td align=right>\n";
echo "\t\t<font class=\"storytitle\">Rating\n";
echo "\t</td>\n";
echo "</tr>\n";

$i = 0;
while ($i < $num) {
	$tid = mysql_result($result, $i, "TeamID");
	$Team = mysql_result($result, $i, "Team");
	$ranking = mysql_result($result, $i, "ranking");
	$wins = mysql_result($result, $i, "win");
	$losses = mysql_result($result, $i, "loss");
	$homeWins = mysql_result($result, $i, "home_win");
	$homeLosses = mysql_result($result, $i, "home_loss");
	$awayWins = mysql_result($result, $i, "road_win");
	$awayLosses = mysql_result($result, $i, "road_loss");

	$i++;

	if(($i % 2)==0) {
		$bgcolor="FFFFFF";
	} else {
		$bgcolor="DDDDDD";
	}

	echo "<tr bgcolor=$bgcolor>";
	echo "\t<td align=right>";
	echo "\t\t<font class=\"option\">$i.</td>";
	echo "\t<td align=center>";
	echo "\t\t<a href=\"modules.php?name=Team&op=team&tid=$tid\"><img src=\"images/logo/$tid.jpg\"></a>";
	echo "\t</td>";
	echo "\t<td align=center>";
	echo "\t\t<font class=\"option\">$wins-$losses";
	echo "\t</td>";
	echo "\t<td align=center>";
	echo "\t\t$homeWins-$homeLosses";
	echo "\t</td>";
	echo "\t<td align=center>";
	echo "\t\t$awayWins-$awayLosses";
	echo "\t</td>";
	echo "\t<td align=center>";
	echo "\t\t<font class=\"option\">$ranking";
	echo "\t</td>";
	echo "</tr>";
}

CloseTable();

include("footer.php");

?>
