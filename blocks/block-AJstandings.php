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

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_iblleague";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

$queryNLWest = "SELECT tid,team_name,leagueRecord,divGB FROM IBL_Standings WHERE division = 'Atlantic' ORDER BY divGB ASC";
$resultNLWest = mysql_query($queryNLWest);
$limitNLWest = mysql_num_rows($resultNLWest);

$queryNLEast = "SELECT tid,team_name,leagueRecord,divGB FROM IBL_Standings WHERE division = 'Midwest' ORDER BY divGB ASC";
$resultNLEast = mysql_query($queryNLEast);
$limitNLEast = mysql_num_rows($resultNLEast);

$queryALWest = "SELECT tid,team_name,leagueRecord,divGB FROM IBL_Standings WHERE division = 'Central' ORDER BY divGB ASC";
$resultALWest = mysql_query($queryALWest);
$limitALWest = mysql_num_rows($resultALWest);

$queryALEast = "SELECT tid,team_name,leagueRecord,divGB FROM IBL_Standings WHERE division = 'Pacific' ORDER BY divGB ASC";
$resultALEast = mysql_query($queryALEast);
$limitALEast = mysql_num_rows($resultALEast);

/* CONFERENCE STANDINGS
$queryNationalConference = "SELECT tid,team_name,leagueRecord,confGB FROM IBL_Standings WHERE conference = 'National' ORDER BY confGB ASC";
$resultNationalConference = mysql_query($queryNationalConference);
$limitNationalConference = mysql_num_rows($resultNationalConference);

$queryAmericanConference = "SELECT tid,team_name,leagueRecord,confGB FROM IBL_Standings WHERE conference = 'American' ORDER BY confGB ASC";
$resultAmericanConference = mysql_query($queryAmericanConference);
$limitAmericanConference = mysql_num_rows($resultAmericanConference);

$lastChunkStartDate = mysql_result(mysql_query("SELECT value FROM nuke_ibl_settings WHERE name='Chunk Start Date' LIMIT 1;"),0);
$lastChunkEndDate = mysql_result(mysql_query("SELECT value FROM nuke_ibl_settings WHERE name='Chunk End Date' LIMIT 1;"),0);
*/

$content=$content.'<table width=150>';
//$content=$content."<center><u>Last Sim Dates:</u></center>";
//$content=$content."<center><strong>$lastChunkStartDate</strong></center>";
//$content=$content."<center>-to-</center>";
//$content=$content."<center><strong>$lastChunkEndDate</strong></center>";
//$content=$content.'<tr><td colspan=2><hr></td></tr>';

/* CONFERENCE STANDINGS
$content=$content.'
<tr><td colspan=2><center><font color=#fd004d><b>National Conference</b></font></center></td></tr>
<tr bgcolor=#006cb3><td><center><font color=#ffffff><b>Team (W-L)</b></font></center></td><td><center><font color=#ffffff><b>GB</b></font></center></td></tr>';

$i = 0;
while ($i < $limitNationalConference) {
	$tid = mysql_result($resultNationalConference,$i,0);
	$team_name = mysql_result($resultNationalConference,$i,1);
	$leagueRecord = mysql_result($resultNationalConference,$i,2);
	$confGB = mysql_result($resultNationalConference,$i,3);

	$content=$content.'<tr><td nowrap><a href="modules.php?name=Team&op=team&tid='.$tid.'">'.$team_name.'</a> ('.$leagueRecord.')</td><td>'.$confGB.'</td></tr>';
	$i++;
}

$content=$content.'
<tr><td colspan=2><hr></td></tr>
<tr><td colspan=2><center><font color=#fd004d><b>American Conference</b></font></center></td></tr>
<tr bgcolor=#006cb3><td><center><font color=#ffffff><b>Team (W-L)</b></font></center></td><td><center><font color=#ffffff><b>GB</b></font></center></td></tr>';

$i = 0;
while ($i < $limitAmericanConference) {
	$tid = mysql_result($resultAmericanConference,$i,0);
	$team_name = mysql_result($resultAmericanConference,$i,1);
	$leagueRecord = mysql_result($resultAmericanConference,$i,2);
	$confGB = mysql_result($resultAmericanConference,$i,3);

	$content=$content.'<tr><td nowrap><a href="modules.php?name=Team&op=team&tid='.$tid.'">'.$team_name.'</a> ('.$leagueRecord.')</td><td>'.$confGB.'</td></tr>';
	$i++;
}
*/

$content=$content.'
<tr><td colspan=2><center><font color=#fd004d><b>Atlantic Division</b></font></center></td></tr>
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
<tr><td colspan=2><center><font color=#fd004d><b>Midwest Division</b></font></center></td></tr>
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
<tr><td colspan=2><center><font color=#fd004d><b>Central Division</b></font></center></td></tr>
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
<tr><td colspan=2><center><font color=#fd004d><b>Pacific Division</b></font></center></td></tr>
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

$content=$content.'
<tr><td colspan=2><center><a href="modules.php?name=Content&pa=showpage&pid=4"><font color=#aaaaaa><i>-- Full Standings --</i></font></a></center></td></tr>
</table>';

?>