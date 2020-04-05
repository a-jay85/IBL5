<?php

/************************************************************************/
/* PHP-NUKE: Advanced Content Management System                         */
/* ============================================                         */
/*                                                                      */
/* Copyright (c) 2007 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if (stristr(htmlentities($_SERVER['PHP_SELF']), "header.php")) {
	Header("Location: index.php");
	die();
}

define('NUKE_HEADER', true);
require_once("mainfile.php");

##################################################
# Include some common header for HTML generation #
##################################################

function head() {
	global $slogan, $sitename, $banners, $nukeurl, $Version_Num, $artpage, $topic, $hlpfile, $user, $hr, $theme, $cookie, $bgcolor1, $bgcolor2, $bgcolor3, $bgcolor4, $textcolor1, $textcolor2, $forumpage, $adminpage, $userpage, $pagetitle;
	$ThemeSel = get_theme();
	include_once("themes/$ThemeSel/theme.php");
	echo "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n";
	echo "<html>\n";
	echo "<head>\n";
	echo "<title>$sitename $pagetitle</title>\n";
	include("includes/meta.php");
	include("includes/javascript.php");



	if (file_exists("themes/$ThemeSel/images/favicon.ico")) {
		echo "<link REL=\"shortcut icon\" HREF=\"themes/$ThemeSel/images/favicon.ico\" TYPE=\"image/x-icon\">\n";
	}
	echo "<link rel=\"alternate\" type=\"application/rss+xml\" title=\"RSS\" href=\"backend.php\">\n";
	echo "<LINK REL=\"StyleSheet\" HREF=\"themes/$ThemeSel/style/style.css\" TYPE=\"text/css\">\n\n\n";


	if (file_exists("includes/custom_files/custom_head.php")) {
		include_once("includes/custom_files/custom_head.php");
	}

    echo "<script src=\"http://www.iblhoops.net/jslib/sorttable.js\"></script>";

	echo "\n\n\n</head>\n\n";
	if (file_exists("includes/custom_files/custom_header.php")) {
		include_once("includes/custom_files/custom_header.php");
	}
	themeheader();
}

function displaytopmenu($tid) {
	$queryteam="SELECT * FROM nuke_ibl_team_info WHERE teamid = '$tid' ";
	$resultteam=mysql_query($queryteam);
	$color1=mysql_result($resultteam,0,"color1");
	$color2=mysql_result($resultteam,0,"color2");

	echo "<table width=600 border=0><tr>";
?>
<!-- START A-JAY'S NAV BAR -->
<p>
<b><font color=red><b>NEW!</b></font> Team Pages: </b>
<select name="teamSelectCity" onchange="location = this.options[this.selectedIndex].value;">
<option value="">Location</option>
<option value="../modules.php?name=Team&op=team&tid=8">Atlanta	Hawks</option>
<option value="../modules.php?name=Team&op=team&tid=2">Boston	Celtics</option>
<option value="../modules.php?name=Team&op=team&tid=4">Brooklyn	Nets</option>
<option value="../modules.php?name=Team&op=team&tid=29">Charlotte	Bobcats</option>
<option value="../modules.php?name=Team&op=team&tid=6">Chicago	Bulls</option>
<option value="../modules.php?name=Team&op=team&tid=7">Cleveland	Cavaliers</option>
<option value="../modules.php?name=Team&op=team&tid=12">Dallas	Mavericks</option>
<option value="../modules.php?name=Team&op=team&tid=13">Denver	Nuggets</option>
<option value="../modules.php?name=Team&op=team&tid=10">Detroit	Pistons</option>
<option value="../modules.php?name=Team&op=team&tid=20">Golden State	Warriors</option>
<option value="../modules.php?name=Team&op=team&tid=14">Houston	Rockets</option>
<option value="../modules.php?name=Team&op=team&tid=9">Indiana	Pacers</option>
<option value="../modules.php?name=Team&op=team&tid=16">Los Angeles	Clippers</option>
<option value="../modules.php?name=Team&op=team&tid=18">Los Angeles	Lakers</option>
<option value="../modules.php?name=Team&op=team&tid=31">Memphis	Tigers</option>
<option value="../modules.php?name=Team&op=team&tid=24">Miami	Heat</option>
<option value="../modules.php?name=Team&op=team&tid=5">Milwaukee	Bucks</option>
<option value="../modules.php?name=Team&op=team&tid=30">Minnesota	Timberwolves</option>
<option value="../modules.php?name=Team&op=team&tid=25">New Orleans	Hornets</option>
<option value="../modules.php?name=Team&op=team&tid=3">New York	Knicks</option>
<option value="../modules.php?name=Team&op=team&tid=32">Oklahoma City	Thunder</option>
<option value="../modules.php?name=Team&op=team&tid=26">Orlando	Magic</option>
<option value="../modules.php?name=Team&op=team&tid=1">Philadelphia	76ers</option>
<option value="../modules.php?name=Team&op=team&tid=19">Phoenix	Suns</option>
<option value="../modules.php?name=Team&op=team&tid=23">Portland	Trailblazers</option>
<option value="../modules.php?name=Team&op=team&tid=17">Sacramento	Kings</option>
<option value="../modules.php?name=Team&op=team&tid=15">San Antonio	Spurs</option>
<option value="../modules.php?name=Team&op=team&tid=22">Seattle	Supersonics</option>
<option value="../modules.php?name=Team&op=team&tid=27">Toronto	Raptors</option>
<option value="../modules.php?name=Team&op=team&tid=11">Utah	Jazz</option>
<option value="../modules.php?name=Team&op=team&tid=28">Vancouver	Grizzlies</option>
<option value="../modules.php?name=Team&op=team&tid=21">Washington	Bullets</option>
</select>

<select name="teamSelectName" onchange="location = this.options[this.selectedIndex].value;">
<option value="">Namesake</option>
<option value="../modules.php?name=Team&op=team&tid=1">76ers</option>
<option value="../modules.php?name=Team&op=team&tid=29">Bobcats</option>
<option value="../modules.php?name=Team&op=team&tid=5">Bucks</option>
<option value="../modules.php?name=Team&op=team&tid=21">Bullets</option>
<option value="../modules.php?name=Team&op=team&tid=6">Bulls</option>
<option value="../modules.php?name=Team&op=team&tid=7">Cavaliers</option>
<option value="../modules.php?name=Team&op=team&tid=2">Celtics</option>
<option value="../modules.php?name=Team&op=team&tid=16">Clippers</option>
<option value="../modules.php?name=Team&op=team&tid=28">Grizzlies</option>
<option value="../modules.php?name=Team&op=team&tid=8">Hawks</option>
<option value="../modules.php?name=Team&op=team&tid=24">Heat</option>
<option value="../modules.php?name=Team&op=team&tid=25">Hornets</option>
<option value="../modules.php?name=Team&op=team&tid=11">Jazz</option>
<option value="../modules.php?name=Team&op=team&tid=17">Kings</option>
<option value="../modules.php?name=Team&op=team&tid=3">Knicks</option>
<option value="../modules.php?name=Team&op=team&tid=18">Lakers</option>
<option value="../modules.php?name=Team&op=team&tid=26">Magic</option>
<option value="../modules.php?name=Team&op=team&tid=12">Mavericks</option>
<option value="../modules.php?name=Team&op=team&tid=4">Nets</option>
<option value="../modules.php?name=Team&op=team&tid=13">Nuggets</option>
<option value="../modules.php?name=Team&op=team&tid=9">Pacers</option>
<option value="../modules.php?name=Team&op=team&tid=10">Pistons</option>
<option value="../modules.php?name=Team&op=team&tid=27">Raptors</option>
<option value="../modules.php?name=Team&op=team&tid=14">Rockets</option>
<option value="../modules.php?name=Team&op=team&tid=15">Spurs</option>
<option value="../modules.php?name=Team&op=team&tid=19">Suns</option>
<option value="../modules.php?name=Team&op=team&tid=22">Supersonics</option>
<option value="../modules.php?name=Team&op=team&tid=32">Thunder</option>
<option value="../modules.php?name=Team&op=team&tid=31">Tigers</option>
<option value="../modules.php?name=Team&op=team&tid=30">Timberwolves</option>
<option value="../modules.php?name=Team&op=team&tid=23">Trailblazers</option>
<option value="../modules.php?name=Team&op=team&tid=20">Warriors</option>
</select>

<select name="teamSelectID" onchange="location = this.options[this.selectedIndex].value;">
<option value="">ID#</option>
<option value="../modules.php?name=Team&op=team&tid=1">1 Philadelphia	76ers</option>
<option value="../modules.php?name=Team&op=team&tid=2">2 Boston	Celtics</option>
<option value="../modules.php?name=Team&op=team&tid=3">3 New York	Knicks</option>
<option value="../modules.php?name=Team&op=team&tid=4">4 Brooklyn	Nets</option>
<option value="../modules.php?name=Team&op=team&tid=5">5 Milwaukee	Bucks</option>
<option value="../modules.php?name=Team&op=team&tid=6">6 Chicago	Bulls</option>
<option value="../modules.php?name=Team&op=team&tid=7">7 Cleveland	Cavaliers</option>
<option value="../modules.php?name=Team&op=team&tid=8">8 Atlanta	Hawks</option>
<option value="../modules.php?name=Team&op=team&tid=9">9 Indiana	Pacers</option>
<option value="../modules.php?name=Team&op=team&tid=10">10 Detroit	Pistons</option>
<option value="../modules.php?name=Team&op=team&tid=11">11 Utah	Jazz</option>
<option value="../modules.php?name=Team&op=team&tid=12">12 Dallas	Mavericks</option>
<option value="../modules.php?name=Team&op=team&tid=13">13 Denver	Nuggets</option>
<option value="../modules.php?name=Team&op=team&tid=14">14 Houston	Rockets</option>
<option value="../modules.php?name=Team&op=team&tid=15">15 San Antonio	Spurs</option>
<option value="../modules.php?name=Team&op=team&tid=16">16 Los Angeles	Clippers</option>
<option value="../modules.php?name=Team&op=team&tid=17">17 Sacramento	Kings</option>
<option value="../modules.php?name=Team&op=team&tid=18">18 Los Angeles	Lakers</option>
<option value="../modules.php?name=Team&op=team&tid=19">19 Phoenix	Suns</option>
<option value="../modules.php?name=Team&op=team&tid=20">20 Golden State	Warriors</option>
<option value="../modules.php?name=Team&op=team&tid=21">21 Washington	Bullets</option>
<option value="../modules.php?name=Team&op=team&tid=22">22 Seattle	Supersonics</option>
<option value="../modules.php?name=Team&op=team&tid=23">23 Portland	Trailblazers</option>
<option value="../modules.php?name=Team&op=team&tid=24">24 Miami	Heat</option>
<option value="../modules.php?name=Team&op=team&tid=25">25 New Orleans	Hornets</option>
<option value="../modules.php?name=Team&op=team&tid=26">26 Orlando	Magic</option>
<option value="../modules.php?name=Team&op=team&tid=27">27 Toronto	Raptors</option>
<option value="../modules.php?name=Team&op=team&tid=28">28 Vancouver	Grizzlies</option>
<option value="../modules.php?name=Team&op=team&tid=29">29 Charlotte	Bobcats</option>
<option value="../modules.php?name=Team&op=team&tid=30">30 Minnesota	Timberwolves</option>
<option value="../modules.php?name=Team&op=team&tid=31">31 Memphis	Tigers</option>
<option value="../modules.php?name=Team&op=team&tid=32">32 Oklahoma City	Thunder</option>
</select>
<!-- END A-JAY'S NAV BAR -->
<?
	echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=team&tid=$tid\">Team Page</a></td>";
	echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=drafthistory&tid=$tid\">Draft History</a></td>";
	echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=schedule&tid=$tid\">Schedule</a></td>";
	echo "<td nowrap=\"nowrap\" valign=center><font style=\"font:bold 14px Helvetica;text-decoration: none;\"> | </td>";
	echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Depth_Chart_Entry\">Depth Chart Entry</a></td>";
	echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Depth_Record\">Depth Chart Status</a></td>";
	echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=reviewtrades\">Trades/Waiver Moves</a></td>";
	echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=seteditor\">Offensive Set Editor</a></td>";
	echo "<td nowrap=\"nowrap\" valign=center><font style=\"font:bold 14px Helvetica;text-decoration: none;\"> | </td>";
	echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=team&tid=0\">Free Agent List</a></td>";
	//echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=injuries&tid=$tid\">Injuries</a></td></tr>";
	echo "</tr></table>";
	echo "<hr>";

	// Use double-slashes to disable the Offense Set Editor and Training Preference links during season.
}

online();
head();
include("includes/counter.php");
if(defined('HOME_FILE')) {
	message_box();
	blocks("Center");
}

?>