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

$pagetitle = "Season Stats";




{
	include("header.php");
	OpenTable();
	$year= $_POST['year'];
	$team = $_POST['team'];
	$position = $_POST['position'];
	$sortby = $_POST['sortby'];

	if ($year== '')
	{
		$argument =$argument."";
	}else{
		$argument=$argument."AND year= '$year'";
	}

	if ($team == 0)
	{
		$argument =$argument."";
	}else{
		$argument=$argument." AND tid= $team";
	}


	if ($sortby== "1")
	{
		$sort="((2*`fgm`+`ftm`+`3gm`)/`gm`)";
	}else if ($sortby== "2"){
		$sort="((reb)/`gm`)";
	}else if ($sortby == "3"){
		$sort="((orb)/`gm`)";
	}else if ($sortby == "4"){
		$sort="((ast)/`gm`)";
	}else if ($sortby == "5"){
		$sort="((stl)/`gm`)";
	}else if ($sortby == "6"){
		$sort="((blk)/`gm`)";
	}else if ($sortby == "7"){
		$sort="((tvr)/`gm`)";
	}else if ($sortby == "8"){
		$sort="((pf)/`gm`)";
	}else if ($sortby == "9"){
		$sort="((((2*fgm+ftm+3gm)+reb+(2*ast)+(2*stl)+(2*blk))-((fga-fgm)+(fta-ftm)+tvr+pf))/gm)";
	}else if ($sortby == "10"){
		$sort="((fgm)/`gm`)";
	}else if ($sortby == "11"){
		$sort="((fga)/`gm`)";
	}else if ($sortby == "12"){
		$sort="(fgm/fga)";
	}else if ($sortby == "13"){
		$sort="((ftm)/`gm`)";
	}else if ($sortby == "14"){
		$sort="((fta)/`gm`)";
	}else if ($sortby == "15"){
		$sort="(ftm/fta)";
	}else if ($sortby == "16"){
		$sort="((3gm)/`gm`)";
	}else if ($sortby == "17"){
		$sort="((3ga)/`gm`)";
	}else if ($sortby == "18"){
		$sort="(3gm/3ga)";
	}else if ($sortby == "19"){
		$sort="(gm)";
	}else if ($sortby == "20"){
		$sort="((min)/`gm`)";
	}else{
		$sort="((2*`fgm`+`ftm`+`3gm`)/`gm`)";
	}

	$query="SELECT * FROM nuke_iblplyr where tid != 0";
	$result=mysql_query($query);
	$num=mysql_numrows($result);

	echo "<form name=\"Leaderboards\" method=\"post\" action=\"http://www.iblhoops.net/modules.php?name=ASG_Stats\">";
	echo "<table border=1>";
	echo "<tr><td><b>Team</td><td><select name=\"team\">";
	team_option($team);
	echo "</select></td>";
	echo "<td><b>Year</td><td><select name=\"year\">";
	year_option($year);
	echo "<td><b>Sort By</td><td><select name=\"sortby\">";
	sort_option($sortby);
	echo "</select></td>";
	echo "</select></td><td><input type=\"submit\" value=\"Search Season Data\"></td>";
	echo "</tr></table>";

	echo "<table cellpadding=3 CELLSPACING=0 border=0><tr bgcolor=C2D69A><td><b>Rank</td><td><b>Year</td><td><b>Name</td><td><b>Team</td><td><b>G</td><td><b>Min</td><td allign=right><b>fgm</td><td><b>fga</td><td allign=right><b>fg%</td><td><b>ftm</td><td allign=right><b>fta</td><td><b>ft%</td><td allign=right><b>tgm</td><td><b>tga</td><td allign=right><b>tg%</td><td><b>orb</td><td allign=right><b>reb</td><td><b>ast</td><td allign=right><b>stl</td><td><b>to</td><td allign=right><b>blk</td><td><b>pf</td><td allign=right><b>ppg</td><td allign=right><b>qa</td></tr>";

	while ($i < $num)
	{
		$pid=mysql_result($result,$i,"pid");
		$pos=mysql_result($result,$i,"pos");
		$name=mysql_result($result,$i,"name");
                $yr=mysql_result($result,$i,"year");
		$teamname=mysql_result($result,$i,"teamname");
		$teamid=mysql_result($result,$i,"tid");
		//$chunknumber=mysql_result($result,$i,"chunk");
		//$qa=mysql_result($result,$i,"qa");
		$stats_gm=mysql_result($result,$i,"stats_gm");
		$stats_min=mysql_result($result,$i,"min");
		$stats_fgm=mysql_result($result,$i,"fgm");
		$stats_fga=mysql_result($result,$i,"fga");
		@$stats_fgp=number_format(($stats_fgm/$stats_fga*100),1);
		$stats_ftm=mysql_result($result,$i,"ftm");
		$stats_fta=mysql_result($result,$i,"fta");
		@$stats_ftp=number_format(($stats_ftm/$stats_fta*100),1);
		$stats_tgm=mysql_result($result,$i,"3gm");
		$stats_tga=mysql_result($result,$i,"3ga");
		@$stats_tgp=number_format(($stats_tgm/$stats_tga*100),1);
		$stats_orb=mysql_result($result,$i,"orb");
		$stats_reb=mysql_result($result,$i,"reb");
		$stats_drb=$stats_reb-$stats_orb;
		$stats_ast=mysql_result($result,$i,"ast");
		$stats_stl=mysql_result($result,$i,"stl");
		$stats_to=mysql_result($result,$i,"tvr");
		$stats_blk=mysql_result($result,$i,"blk");
		$stats_pf=mysql_result($result,$i,"pf");
		$stats_pts=2*$stats_fgm+$stats_ftm+$stats_tgm;



		@$stats_mpg=number_format(($stats_min/$stats_gm),1);
		@$stats_fgmpg=number_format(($stats_fgm/$stats_gm),1);
		@$stats_fgapg=number_format(($stats_fga/$stats_gm),1);
		@$stats_ftmpg=number_format(($stats_ftm/$stats_gm),1);
		@$stats_ftapg=number_format(($stats_fta/$stats_gm),1);
		@$stats_tgmpg=number_format(($stats_tgm/$stats_gm),1);
		@$stats_tgapg=number_format(($stats_tga/$stats_gm),1);
		@$stats_orbpg=number_format(($stats_orb/$stats_gm),1);
		@$stats_rpg=number_format(($stats_reb/$stats_gm),1);
		@$stats_apg=number_format(($stats_ast/$stats_gm),1);
		@$stats_spg=number_format(($stats_stl/$stats_gm),1);
		@$stats_tpg=number_format(($stats_to/$stats_gm),1);
		@$stats_bpg=number_format(($stats_blk/$stats_gm),1);
		@$stats_fpg=number_format(($stats_pf/$stats_gm),1);
		@$stats_ppg=number_format(($stats_pts/$stats_gm),1);

		if ($stats_gm > 0 )
		{
			$qa=number_format((($stats_pts+$stats_reb+(2*$stats_ast)+(2*$stats_stl)+(2*$stats_blk))-(($stats_fga-$stats_fgm)+($stats_fta-$stats_ftm)+$stats_to+$stats_pf))/$stats_gm,1);
		}else{
			$qa=number_format(0,1);
		}

		if(($i % 2)==0) {
			$bgcolor="DDDDDD";
		}else{
			$bgcolor="FFFFFF";
		}

		$i++;
		echo "<tr bgcolor=$bgcolor><td>$i.</td><td>$yr</td><td><a href=http://www.iblhoops.net/modules.php?name=Player&pa=showpage&pid=$pid>$name</a></td><td><a href=http://www.iblhoops.net/modules.php?name=Team&op=team&tid=$teamid>$teamname</a></td><td>$stats_gm</td><td align=right>$stats_mpg</td><td align=right>$stats_fgmpg</td><td align=right>$stats_fgapg</td><td align=right>$stats_fgp</td><td align=right>$stats_ftmpg</td><td align=right>$stats_ftapg</td><td align=right>$stats_ftp</td><td align=right>$stats_tgmpg</td><td align=right>$stats_tgapg</td><td align=right>$stats_tgp</td><td align=right>$stats_orbpg</td><td align=right>$stats_rpg</td><td align=right>$stats_apg</td><td align=right>$stats_spg</td><td align=right>$stats_tpg</td><td align=right>$stats_bpg</td><td align=right>$stats_fpg</td><td align=right>$stats_ppg</td><td>$qa</td></tr>";

	}

	echo "</table></form>";
	CloseTable();
	include("footer.php");
}

function team_option ($team_selected)
{
	$query="SELECT * FROM nuke_ibl_power WHERE TeamID BETWEEN 1 AND 32 ORDER BY TeamID ASC";
	$result=mysql_query($query);
	$num=mysql_numrows($result);
	echo "<option value=0>All</option>";
	$i=0;
	while ($i < $num)
	{
		$tid=mysql_result($result,$i,"TeamID");
		$Team=mysql_result($result,$i,"Team");

		$i++;
		if ($team_selected == $Team)
		{
			echo "<option value=$tid SELECTED>$Team</option>";
		}else{
			echo "<option value=$tid>$Team</option>";
		}
	}
}

//function year_option ($year_selected)
//{
//	$query="SELECT distinct year FROM nuke_iblhist WHERE teamid BETWEEN 1 AND 32 ORDER BY teamid ASC";
//	$result=mysql_query($query);
//	$num=mysql_numrows($result);
//	echo "<option value=0>All</option>";
//	$i=0;
//	while ($i < $num)
//	{
//		$year=mysql_result($result,$i,"year");
//
//		$i++;
//		if ($year_selected == $year)
//		{
//			echo "<option value=$year SELECTED>$year</option>";
//		}else{
//			echo "<option value=$year>$year</option>";
//		}
//	}
//}

function year_option ($year_selected)
{
	$arr = array("1997", "1998", "1999", "2000");
	$num=sizeof($arr);
	echo "<option value=''>All</option>";
	$i=0;
	while ($i < $num)
	{
		$year=$arr[$i];

		$i++;
		if ($year_selected == $year)
		{
			echo "<option value='$year' SELECTED>$year</option>";
		}else{
			echo "<option value='$year'>$year</option>";
		}
	}
}

function sort_option($sort_selected)
{
	$arr = array("PPG", "REB", "OREB", "AST", "STL", "BLK", "TO", "FOUL", "QA", "FGM", "FGA", "FG%", "FTM", "FTA", "FT%", "TGM", "TGA", "TG%", "GAMES", "MIN");	
	$num=sizeof($arr);
	$i=0;
	while ($i < $num)
	{
		$sortby=$arr[$i];

		$i++;
		if ($i == $sort_selected)
		{
			echo "<option value='$i' SELECTED>$sortby</option>";
		}else{
			echo "<option value='$i'>$sortby</option>";
		}
	}
}






?>