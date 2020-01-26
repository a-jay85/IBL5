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
mysql_connect($dbhost,$dbuname,$dbpass) or die("Unable to connect");
@mysql_select_db($dbname) or die("Unable to select database");

/* DIVISIONAL STANDINGS
$queryNLWest = "SELECT tid,team_name,leagueRecord,divGB FROM IBL_Standings WHERE division = 'NL West' ORDER BY divGB ASC";
$resultNLWest = mysql_query($queryNLWest);
$limitNLWest = mysql_num_rows($resultNLWest);

$queryNLEast = "SELECT tid,team_name,leagueRecord,divGB FROM IBL_Standings WHERE division = 'NL East' ORDER BY divGB ASC";
$resultNLEast = mysql_query($queryNLEast);
$limitNLEast = mysql_num_rows($resultNLEast);

$queryALWest = "SELECT tid,team_name,leagueRecord,divGB FROM IBL_Standings WHERE division = 'AL West' ORDER BY divGB ASC";
$resultALWest = mysql_query($queryALWest);
$limitALWest = mysql_num_rows($resultALWest);

$queryALEast = "SELECT tid,team_name,leagueRecord,divGB FROM IBL_Standings WHERE division = 'AL East' ORDER BY divGB ASC";
$resultALEast = mysql_query($queryALEast);
$limitALEast = mysql_num_rows($resultALEast);
*/

$queryEasternConference = "SELECT tid,team_name,leagueRecord,confGB FROM IBL_Standings WHERE conference = 'Eastern' ORDER BY confGB ASC";
$resultEasternConference = mysql_query($queryEasternConference);
$limitEasternConference = mysql_num_rows($resultEasternConference);

$queryWesternConference = "SELECT tid,team_name,leagueRecord,confGB FROM IBL_Standings WHERE conference = 'Western' ORDER BY confGB ASC";
$resultWesternConference = mysql_query($queryWesternConference);
$limitWesternConference = mysql_num_rows($resultWesternConference);

$lastChunkStartDate = mysql_result(mysql_query("SELECT value FROM nuke_ibl_settings WHERE name='Chunk Start Date' LIMIT 1;"),0);
$lastChunkEndDate = mysql_result(mysql_query("SELECT value FROM nuke_ibl_settings WHERE name='Chunk End Date' LIMIT 1;"),0);

$content=$content.'<table width=150>';
$content=$content."<center><u>Recent Sim Dates:</u></center>";
$content=$content."<center><strong>$lastChunkStartDate</strong></center>";
$content=$content."<center>-to-</center>";
$content=$content."<center><strong>$lastChunkEndDate</strong></center>";
$content=$content.'<tr><td colspan=2><hr></td></tr>';
$content=$content.'
<tr><td colspan=2><center><font color=#fd004d><b>Eastern Conference</b></font></center></td></tr>
<tr bgcolor=#006cb3><td><center><font color=#ffffff><b>Team (W-L)</b></font></center></td><td><center><font color=#ffffff><b>GB</b></font></center></td></tr>';

$i = 0;
while ($i < $limitEasternConference) {
	$tid = mysql_result($resultEasternConference,$i,0);
	$team_name = mysql_result($resultEasternConference,$i,1);
	$leagueRecord = mysql_result($resultEasternConference,$i,2);
	$confGB = mysql_result($resultEasternConference,$i,3);

	$content=$content.'<tr><td nowrap><a href="modules.php?name=Team&op=team&tid='.$tid.'">'.$team_name.'</a> ('.$leagueRecord.')</td><td>'.$confGB.'</td></tr>';
	$i++;
}

$content=$content.'
<tr><td colspan=2><hr></td></tr>
<tr><td colspan=2><center><font color=#fd004d><b>Western Conference</b></font></center></td></tr>
<tr bgcolor=#006cb3><td><center><font color=#ffffff><b>Team (W-L)</b></font></center></td><td><center><font color=#ffffff><b>GB</b></font></center></td></tr>';

$i = 0;
while ($i < $limitWesternConference) {
	$tid = mysql_result($resultWesternConference,$i,0);
	$team_name = mysql_result($resultWesternConference,$i,1);
	$leagueRecord = mysql_result($resultWesternConference,$i,2);
	$confGB = mysql_result($resultWesternConference,$i,3);

	$content=$content.'<tr><td nowrap><a href="modules.php?name=Team&op=team&tid='.$tid.'">'.$team_name.'</a> ('.$leagueRecord.')</td><td>'.$confGB.'</td></tr>';
	$i++;
}

/* DIVISIONAL STANDINGS
$content=$content.'
<tr><td colspan=2><center><font color=#fd004d><b>NL West Division</b></font></center></td></tr>
<tr bgcolor=#006cb3><td><center><font color=#ffffff><b>Team (W-L)</b></font></center></td><td><center><font color=#ffffff><b>GB</b></font></center></td></tr>';

$i = 0;
while ($i < $limitNLWest) {
	$tid = mysql_result($resultNLWest,$i,0);
	$team_name = mysql_result($resultNLWest,$i,1);
	$leagueRecord = mysql_result($resultNLWest,$i,2);
	$divGB = mysql_result($resultNLWest,$i,3);

	$content=$content.'<tr><td nowrap><a href="modules.php?name=Team&op=team&tid='.$tid.'">'.$team_name.'</a> ('.$leagueRecord.')</td><td>'.$divGB.'</td></tr>';
	$i++;
}

$content=$content.'
<tr><td colspan=2><hr></td></tr>
<tr><td colspan=2><center><font color=#fd004d><b>NL East Division</b></font></center></td></tr>
<tr bgcolor=#006cb3><td><center><font color=#ffffff><b>Team (W-L)</b></font></center></td><td><center><font color=#ffffff><b>GB</b></font></center></td></tr>';

$i = 0;
while ($i < $limitNLEast) {
	$tid = mysql_result($resultNLEast,$i,0);
	$team_name = mysql_result($resultNLEast,$i,1);
	$leagueRecord = mysql_result($resultNLEast,$i,2);
	$divGB = mysql_result($resultNLEast,$i,3);

	$content=$content.'<tr><td nowrap><a href="modules.php?name=Team&op=team&tid='.$tid.'">'.$team_name.'</a> ('.$leagueRecord.')</td><td>'.$divGB.'</td></tr>';
	$i++;
}

$content=$content.'
<tr><td colspan=2><hr></td></tr>
<tr><td colspan=2><center><font color=#fd004d><b>AL West Division</b></font></center></td></tr>
<tr bgcolor=#006cb3><td><center><font color=#ffffff><b>Team (W-L)</b></font></center></td><td><center><font color=#ffffff><b>GB</b></font></center></td></tr>';

$i = 0;
while ($i < $limitALWest) {
	$tid = mysql_result($resultALWest,$i,0);
	$team_name = mysql_result($resultALWest,$i,1);
	$leagueRecord = mysql_result($resultALWest,$i,2);
	$divGB = mysql_result($resultALWest,$i,3);

	$content=$content.'<tr><td nowrap><a href="modules.php?name=Team&op=team&tid='.$tid.'">'.$team_name.'</a> ('.$leagueRecord.')</td><td>'.$divGB.'</td></tr>';
	$i++;
}

$content=$content.'
<tr><td colspan=2><hr></td></tr>
<tr><td colspan=2><center><font color=#fd004d><b>AL East Division</b></font></center></td></tr>
<tr bgcolor=#006cb3><td><center><font color=#ffffff><b>Team (W-L)</b></font></center></td><td><center><font color=#ffffff><b>GB</b></font></center></td></tr>';

$i = 0;
while ($i < $limitALEast) {
	$tid = mysql_result($resultALEast,$i,0);
	$team_name = mysql_result($resultALEast,$i,1);
	$leagueRecord = mysql_result($resultALEast,$i,2);
	$divGB = mysql_result($resultALEast,$i,3);

	$content=$content.'<tr><td nowrap><a href="modules.php?name=Team&op=team&tid='.$tid.'">'.$team_name.'</a> ('.$leagueRecord.')</td><td>'.$divGB.'</td></tr>';
	$i++;
}
*/

$content=$content.'
<tr><td colspan=2><center><a href="modules.php?name=Content&pa=showpage&pid=4"><font color=#aaaaaa><i>-- Full Standings --</i></font></a></center></td></tr>
</table>';

?>
