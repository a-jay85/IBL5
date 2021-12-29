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

/* DIVISIONAL STANDINGS
$queryNLWest = "SELECT tid,team_name,leagueRecord,divGB FROM ibl_standings WHERE division = 'NL West' ORDER BY divGB ASC";
$resultNLWest = mysql_query($queryNLWest);
$limitNLWest = mysql_num_rows($resultNLWest);

$queryNLEast = "SELECT tid,team_name,leagueRecord,divGB FROM ibl_standings WHERE division = 'NL East' ORDER BY divGB ASC";
$resultNLEast = mysql_query($queryNLEast);
$limitNLEast = mysql_num_rows($resultNLEast);

$queryALWest = "SELECT tid,team_name,leagueRecord,divGB FROM ibl_standings WHERE division = 'AL West' ORDER BY divGB ASC";
$resultALWest = mysql_query($queryALWest);
$limitALWest = mysql_num_rows($resultALWest);

$queryALEast = "SELECT tid,team_name,leagueRecord,divGB FROM ibl_standings WHERE division = 'AL East' ORDER BY divGB ASC";
$resultALEast = mysql_query($queryALEast);
$limitALEast = mysql_num_rows($resultALEast);
*/

$arrayLastSimDates = Shared::getLastSimDatesArray();
$lastSimStartDate = $arrayLastSimDates["Start Date"];
$lastSimEndDate = $arrayLastSimDates["End Date"];

$content=$content.'<table width=150>';
$content=$content."<center><u>Recent Sim Dates:</u></center>";
$content=$content."<center><strong>$lastSimStartDate</strong></center>";
$content=$content."<center>-to-</center>";
$content=$content."<center><strong>$lastSimEndDate</strong></center>";
$content=$content.'<tr><td colspan=2><hr></td></tr>';

$queryEasternConference = "SELECT tid,team_name,leagueRecord,confGB,clinchedConference,clinchedDivision,clinchedPlayoffs FROM ibl_standings WHERE conference = 'Eastern' ORDER BY confGB ASC";
$resultEasternConference = $db->sql_query($queryEasternConference);
$limitEasternConference = $db->sql_numrows($resultEasternConference);

$content=$content.'
<tr><td colspan=2><center><font color=#fd004d><b>Eastern Conference</b></font></center></td></tr>
<tr bgcolor=#006cb3><td><center><font color=#ffffff><b>Team (W-L)</b></font></center></td><td><center><font color=#ffffff><b>GB</b></font></center></td></tr>';

$i = 0;
while ($i < $limitEasternConference) {
	$tid = $db->sql_result($resultEasternConference,$i,0);
	$team_name = trim($db->sql_result($resultEasternConference,$i,1));
	$leagueRecord = $db->sql_result($resultEasternConference,$i,2);
	$confGB = $db->sql_result($resultEasternConference,$i,3);
	$clinchedConference = $db->sql_result($resultEasternConference,$i,4);
	$clinchedDivision = $db->sql_result($resultEasternConference,$i,5);
	$clinchedPlayoffs = $db->sql_result($resultEasternConference,$i,6);
    if ($clinchedConference == 1) {
        $team_name = "<b>Z</b>-" . $team_name;
    } elseif ($clinchedDivision == 1) {
        $team_name = "<b>Y</b>-" . $team_name;
    } elseif ($clinchedPlayoffs == 1) {
        $team_name = "<b>X</b>-" . $team_name;
    }

	$content=$content.'<tr><td nowrap><a href="modules.php?name=Team&op=team&tid='.$tid.'">'.$team_name.'</a> ('.$leagueRecord.')</td><td>'.$confGB.'</td></tr>';
	$i++;
}

$queryWesternConference = "SELECT tid,team_name,leagueRecord,confGB,clinchedConference,clinchedDivision,clinchedPlayoffs FROM ibl_standings WHERE conference = 'Western' ORDER BY confGB ASC";
$resultWesternConference = $db->sql_query($queryWesternConference);
$limitWesternConference = $db->sql_numrows($resultWesternConference);

$content=$content.'
<tr><td colspan=2><hr></td></tr>
<tr><td colspan=2><center><font color=#fd004d><b>Western Conference</b></font></center></td></tr>
<tr bgcolor=#006cb3><td><center><font color=#ffffff><b>Team (W-L)</b></font></center></td><td><center><font color=#ffffff><b>GB</b></font></center></td></tr>';

$i = 0;
while ($i < $limitWesternConference) {
	$tid = $db->sql_result($resultWesternConference,$i,0);
	$team_name = trim($db->sql_result($resultWesternConference,$i,1));
	$leagueRecord = $db->sql_result($resultWesternConference,$i,2);
	$confGB = $db->sql_result($resultWesternConference,$i,3);
	$clinchedConference = $db->sql_result($resultWesternConference,$i,4);
	$clinchedDivision = $db->sql_result($resultWesternConference,$i,5);
	$clinchedPlayoffs = $db->sql_result($resultWesternConference,$i,6);
    if ($clinchedConference == 1) {
        $team_name = "<b>Z</b>-" . $team_name;
    } elseif ($clinchedDivision == 1) {
        $team_name = "<b>Y</b>-" . $team_name;
    } elseif ($clinchedPlayoffs == 1) {
        $team_name = "<b>X</b>-" . $team_name;
    }

	$content=$content.'<tr><td nowrap><a href="modules.php?name=Team&op=team&tid='.$tid.'">'.$team_name.'</a> ('.$leagueRecord.')</td><td>'.$confGB.'</td></tr>';
	$i++;
}

/* DIVISIONAL STANDINGS
$content=$content.'
<tr><td colspan=2><center><font color=#fd004d><b>NL West Division</b></font></center></td></tr>
<tr bgcolor=#006cb3><td><center><font color=#ffffff><b>Team (W-L)</b></font></center></td><td><center><font color=#ffffff><b>GB</b></font></center></td></tr>';

$i = 0;
while ($i < $limitNLWest) {
	$tid = $db->sql_result($resultNLWest,$i,0);
	$team_name = $db->sql_result($resultNLWest,$i,1);
	$leagueRecord = $db->sql_result($resultNLWest,$i,2);
	$divGB = $db->sql_result($resultNLWest,$i,3);

	$content=$content.'<tr><td nowrap><a href="modules.php?name=Team&op=team&tid='.$tid.'">'.$team_name.'</a> ('.$leagueRecord.')</td><td>'.$divGB.'</td></tr>';
	$i++;
}

$content=$content.'
<tr><td colspan=2><hr></td></tr>
<tr><td colspan=2><center><font color=#fd004d><b>NL East Division</b></font></center></td></tr>
<tr bgcolor=#006cb3><td><center><font color=#ffffff><b>Team (W-L)</b></font></center></td><td><center><font color=#ffffff><b>GB</b></font></center></td></tr>';

$i = 0;
while ($i < $limitNLEast) {
	$tid = $db->sql_result($resultNLEast,$i,0);
	$team_name = $db->sql_result($resultNLEast,$i,1);
	$leagueRecord = $db->sql_result($resultNLEast,$i,2);
	$divGB = $db->sql_result($resultNLEast,$i,3);

	$content=$content.'<tr><td nowrap><a href="modules.php?name=Team&op=team&tid='.$tid.'">'.$team_name.'</a> ('.$leagueRecord.')</td><td>'.$divGB.'</td></tr>';
	$i++;
}

$content=$content.'
<tr><td colspan=2><hr></td></tr>
<tr><td colspan=2><center><font color=#fd004d><b>AL West Division</b></font></center></td></tr>
<tr bgcolor=#006cb3><td><center><font color=#ffffff><b>Team (W-L)</b></font></center></td><td><center><font color=#ffffff><b>GB</b></font></center></td></tr>';

$i = 0;
while ($i < $limitALWest) {
	$tid = $db->sql_result($resultALWest,$i,0);
	$team_name = $db->sql_result($resultALWest,$i,1);
	$leagueRecord = $db->sql_result($resultALWest,$i,2);
	$divGB = $db->sql_result($resultALWest,$i,3);

	$content=$content.'<tr><td nowrap><a href="modules.php?name=Team&op=team&tid='.$tid.'">'.$team_name.'</a> ('.$leagueRecord.')</td><td>'.$divGB.'</td></tr>';
	$i++;
}

$content=$content.'
<tr><td colspan=2><hr></td></tr>
<tr><td colspan=2><center><font color=#fd004d><b>AL East Division</b></font></center></td></tr>
<tr bgcolor=#006cb3><td><center><font color=#ffffff><b>Team (W-L)</b></font></center></td><td><center><font color=#ffffff><b>GB</b></font></center></td></tr>';

$i = 0;
while ($i < $limitALEast) {
	$tid = $db->sql_result($resultALEast,$i,0);
	$team_name = $db->sql_result($resultALEast,$i,1);
	$leagueRecord = $db->sql_result($resultALEast,$i,2);
	$divGB = $db->sql_result($resultALEast,$i,3);

	$content=$content.'<tr><td nowrap><a href="modules.php?name=Team&op=team&tid='.$tid.'">'.$team_name.'</a> ('.$leagueRecord.')</td><td>'.$divGB.'</td></tr>';
	$i++;
}
*/

$content=$content.'
<tr><td colspan=2><center><a href="modules.php?name=Content&pa=showpage&pid=4"><font color=#aaaaaa><i>-- Full Standings --</i></font></a></center></td></tr>
</table>';

?>
