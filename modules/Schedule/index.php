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

$min_date_query="SELECT MIN(Date) as mindate FROM IBL_Schedule";
$min_date_result=mysql_query($min_date_query);
$row = mysql_fetch_assoc($min_date_result);
$min_date=$row[mindate];

$max_date_query="SELECT MAX(Date) as maxdate FROM IBL_Schedule";
$max_date_result=mysql_query($max_date_query);
$row2 = mysql_fetch_assoc($max_date_result);
$max_date=$row2[maxdate];
$max_date=fnc_date_calc($max_date,0);

$chunk_start_date=$min_date;
$chunk_end_date=fnc_date_calc($min_date,6);


$i=0;
while ($chunk_start_date < $max_date)
{
	$i++;
	chunk ($chunk_start_date, $chunk_end_date, $i);
	if ($i == 13)
	{
		$chunk_start_date=fnc_date_calc($chunk_start_date,11);
		$chunk_end_date=fnc_date_calc($chunk_start_date,6);
	}else{
		$chunk_start_date=fnc_date_calc($chunk_start_date,7);
		$chunk_end_date=fnc_date_calc($chunk_start_date,6);
	}
}

	CloseTable();
	include("footer.php");

function chunk ($chunk_start_date, $chunk_end_date, $j) {
	$query="SELECT * FROM IBL_Schedule WHERE Date BETWEEN '$chunk_start_date' AND '$chunk_end_date' ORDER BY SchedID ASC";
	$result=mysql_query($query);
	$num=mysql_numrows($result);
	$date_base=mysql_result($result,0,"Date");
	$i = 0;
	$z=0;
	echo "<table width=\"500\" cellpadding=\"6\" cellspacing=\"0\" border=\"1\" align=center>";
	echo "<tr><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
		while ($i < $num) {
		$date=mysql_result($result,$i,"Date");
		$visitor=mysql_result($result,$i,"Visitor");
		$VScore=mysql_result($result,$i,"VScore");
		$home=mysql_result($result,$i,"Home");
		$HScore=mysql_result($result,$i,"HScore");
		$boxid=mysql_result($result,$i,"BoxID");
		$SchedID=mysql_result($result,$i,"SchedID");

		$vname=teamname($visitor);
		$hname=teamname($home);

		if(($i % 2)==0) {
			$bgcolor="FFFFFF";
		}else{
			$bgcolor="DDDDDD";
		}

		if(($z % 2)==0) {
			$bgcolor2="0070C0";
		}else{
			$bgcolor2="C00000";
		}

		if ($date == $datebase)
		{
			echo "<tr bgcolor=$bgcolor><td>$date</td><td><a href=\"http://www.iblhoops.net/modules.php?name=Team&op=team&tid=$visitor\">$vname</a></td><td align=right>$VScore</td><td><a href=\"http://www.iblhoops.net/modules.php?name=Team&op=team&tid=$home\">$hname</a></td><td align=right>$HScore</td><td><a href=\"http://www.iblhoops.net/ibl5/ibl/IBL/box$boxid.htm\">View</a></td></tr>";
		}else{
			echo "<tr><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
			echo "<tr bgcolor=$bgcolor2><td><font color=\"FFFFFF\"><b>Date</td><td><font color=\"FFFFFF\"><b>Visitor</td><td><font color=\"FFFFFF\"><b>Score</td><td><font color=\"FFFFFF\"><b>Home</td><td><font color=\"FFFFFF\"><b>Score</td><td><font color=\"FFFFFF\"><b>Box Score</td></tr>";
			echo "<tr bgcolor=$bgcolor><td>$date</td><td><a href=\"http://www.iblhoops.net/modules.php?name=Team&op=team&tid=$visitor\">$vname</a></td><td align=right>$VScore</td><td><a href=\"http://www.iblhoops.net/modules.php?name=Team&op=team&tid=$home\">$hname</a></td><td align=right>$HScore</td><td><a href=\"http://www.iblhoops.net/ibl5/ibl/IBL/box$boxid.htm\">View</a></td></tr>";
			$datebase=$date;
			$z++;
		}
		$i++;
	}
	echo "</table>";
	//return array($homewin, $homeloss, $visitorwin, $visitorloss);
}

function teamname ($teamid) {
	$query="SELECT * FROM nuke_ibl_team_info WHERE teamid = $teamid";
	$result=mysql_query($query);
	$name=mysql_result($result, 0, "team_name");
	return $name;
}

function fnc_date_calc($this_date,$num_days){

    $my_time = strtotime ($this_date); //converts date string to UNIX timestamp
    $timestamp = $my_time + ($num_days * 86400); //calculates # of days passed ($num_days) * # seconds in a day (86400)
     $return_date = date("Y/m/d",$timestamp);  //puts the UNIX timestamp back into string format

    return $return_date;//exit function and return string
}

?>