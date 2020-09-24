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

if ( !defined('BLOCK_FILE') ) {
    Header("Location: ../index.php");
    die();
}

global $prefix, $multilingual, $currentlang, $db;

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$result=mysql_query($query);
$num=mysql_numrows($result);
$i=0;
$content=$content."<table width=150>";
while ($i < $num)
{
	$tid=mysql_result($result,$i,"TeamID");
	$Team=mysql_result($result,$i,"Team");
	$ranking=mysql_result($result,$i,"ranking");
	$wins=mysql_result($result,$i,"win");
	$losses=mysql_result($result,$i,"loss");
	$i++;
$query = "SELECT TeamID, Team, ranking, win, loss, color1, color2
    FROM nuke_ibl_power rankings
    INNER JOIN nuke_ibl_team_info info USING (teamid)
    ORDER BY ranking DESC;";
	$teamcolor1 = mysql_result($result, $i, "color1");
	$teamcolor2 = mysql_result($result, $i, "color2");

	$bgcolor = "$teamcolor1";

	$content .= "<tr>
        <td align=right valign=top>$i.</td>
        <td bgcolor=$bgcolor align=center><a href=\"modules.php?name=Team&op=team&tid=$tid\"><font color=#$teamcolor2>$Team</font></a></td>
        <td align=right valign=top>$ranking</td>
    </tr>";
}

$content=$content."<tr><td colspan=3><center><a href=\"modules.php?name=Power_Rankings\"><font color=#aaaaaa><i>-- Full Power Rankings --</i></font></a></center></table>";

?>
