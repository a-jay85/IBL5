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

require_once("mainfile.php");
$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- $module_name";




	include("header.php");
	OpenTable();
	echo "<center><font class=\"storytitle\">1990 IBL Power Rankings</font></center><br><br>";
	$query="SELECT * FROM nuke_ibl_power WHERE TeamID BETWEEN 1 AND 32 ORDER BY ranking DESC";
	$result=mysql_query($query);
	$num=mysql_numrows($result);
	$i=0;
	echo "<table width=\"500\" cellpadding=\"4\" cellspacing=\"0\" border=\"0\" align=center>";
	echo "<tr><td align=right><font class=\"storytitle\">Rank</td><td align=center><font class=\"storytitle\">Team</td><td align=center><font class=\"storytitle\">Record</td><td align=center><font class=\"storytitle\">Home</td><td align=center><font class=\"storytitle\">Road</td><td align=right><font class=\"storytitle\">Rating</td></tr>";
	while ($i < $num)
	{
		$tid=mysql_result($result,$i,"TeamID");
		$Team=mysql_result($result,$i,"Team");
		$ranking=mysql_result($result,$i,"ranking");
		$wins=mysql_result($result,$i,"win");
		$losses=mysql_result($result,$i,"loss");

		list ($homewin, $homeloss, $visitorwin, $visitorloss)=record($tid);

		$i++;

		if(($i % 2)==0) {
			$bgcolor="FFFFFF";
		}else{
			$bgcolor="DDDDDD";
		}
		echo "<tr bgcolor=$bgcolor><td align=right><font class=\"option\">$i.</td><td align=center><img src=\"images/logo/$tid.jpg\"></td><td align=center><font class=\"option\">($wins - $losses)</td><td align=center><font class=\"option\">($homewin - $homeloss)</td><td align=center><font class=\"option\">($visitorwin - $visitorloss)</td><td align=right><font class=\"option\">$ranking</td></tr>";
	}

	CloseTable();
	include("footer.php");

function record ($tid) {
	$query="SELECT * FROM ibl_schedule WHERE (Visitor = $tid OR Home = $tid) AND BoxID > 0 ORDER BY Date ASC";
	$result=mysql_query($query);
	$num=mysql_numrows($result);
	$wins=0;
	$losses=0;
	$i = 0;

	$homewin = 0;
	$homeloss = 0;
	$visitorwin = 0;
	$visitorloss = 0;

	while ($i < $num) {
		$visitor=mysql_result($result,$i,"Visitor");
		$VScore=mysql_result($result,$i,"VScore");
		$home=mysql_result($result,$i,"Home");
		$HScore=mysql_result($result,$i,"HScore");

		if ($tid == $visitor) {
			if ($VScore > $HScore) {
				$visitorwin=$visitorwin+1;
			}else{
				$visitorloss=$visitorloss+1;
			}
		}else{
			if ($VScore > $HScore) {
				$homeloss=$homeloss+1;
			}else{
				$homewin=$homewin+1;
			}
		}
		$i++;
	}

	return array($homewin, $homeloss, $visitorwin, $visitorloss);
}

function chunks ()
{
	$max_chunk_query="SELECT MIN(date) as date FROM ibl_schedule";
	$max_chunk_result=mysql_query($max_chunk_query);
	$row = mysql_fetch_assoc($max_chunk_result);
	$date=$row[date];
	$date=strtotime(date("Y-m-d", strtotime($date)) . " +7 day");
	//echo "$date<br>";
}
?>