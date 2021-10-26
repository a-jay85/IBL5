<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2002 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/
/*         Additional security & Abstraction layer conversion           */
/*                           2003 chatserv                              */
/*      http://www.nukefixes.com -- http://www.nukeresources.com        */
/************************************************************************/
/*                     IBL Free Agency Module                           */
/*               (c) July 22, 2005 by Spencer Cooley                    */
/************************************************************************/

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) die ("You can't access this file directly...");

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Free Agency System";

function main($user) {
	global $stop;
	if (!is_user($user)) {
		include("header.php");
		OpenTable();
		echo "<center><font class=\"title\"><b>".($stop ? _LOGININCOR : _USERREGLOGIN)."</b></font></center>";
		CloseTable();
		echo "<br>";
		if (!is_user($user)) {
			OpenTable();
			loginbox();
			CloseTable();
		}
		include("footer.php");
	} elseif (is_user($user)) {
		global $cookie;
		cookiedecode($user);
		userinfo($cookie[1]);
	}
}

function userinfo($username, $bypass=0, $hid=0, $url=0)
	{
	global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $useset, $subscription_url;
	$sql = "SELECT * FROM ".$prefix."_bbconfig";
	$result = $db->sql_query($sql);
	while ( $row = $db->sql_fetchrow($result) )	{
		$board_config[$row['config_name']] = $row['config_value'];
	}
	$sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
	$result2 = $db->sql_query($sql2);
	$num = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);
	if(!$bypass) cookiedecode($user);
	display();
}

function display() {
	global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;
	include("header.php");
	OpenTable();

	$sql2 = "SELECT * FROM ".$prefix."_users WHERE username='$cookie[1]'";
	$result2 = $db->sql_query($sql2);
	$num2 = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);

	$userteam = stripslashes(check_html($userinfo['user_ibl_team'], "nohtml"));
	$tid = Shared::getTidFromTeamname($userteam);

	Shared::displaytopmenu($tid);

	$currentSeasonEndingYear = Shared::getCurrentSeasonEndingYear();

	/*
	// ==== COMPUTE PLAYER SALARIES FOR NEXT YEAR TO GET SOFT AND HARD CAP NUMBERS

	$salary = $db->sql_query("SELECT * FROM ".$prefix."_iblplyr WHERE teamname='$userteam'");

	$tf_millions=0;

	while ($capcounter = $db->sql_fetchrow($salary)) {
		$millionscy = stripslashes(check_html($capcounter['cy'], "nohtml"));
		$millionscy1 = stripslashes(check_html($capcounter['cy1'], "nohtml"));
		$millionscy2 = stripslashes(check_html($capcounter['cy2'], "nohtml"));
		$millionscy3 = stripslashes(check_html($capcounter['cy3'], "nohtml"));
		$millionscy4 = stripslashes(check_html($capcounter['cy4'], "nohtml"));
		$millionscy5 = stripslashes(check_html($capcounter['cy5'], "nohtml"));
		$millionscy6 = stripslashes(check_html($capcounter['cy6'], "nohtml"));

		// === LOOK AT SALARY COMMITTED NEXT YEAR, NOT THIS YEAR

		if ($millionscy == 0) {
			$tf_millions = $tf_millions+$millionscy1;
		}
		if ($millionscy == 1) {
			$tf_millions = $tf_millions+$millionscy2;
		}
		if ($millionscy == 2) {
			$tf_millions = $tf_millions+$millionscy3;
		}
		if ($millionscy == 3) {
			$tf_millions = $tf_millions+$millionscy4;
		}
		if ($millionscy == 4) {
			$tf_millions = $tf_millions+$millionscy5;
		}
		if ($millionscy == 5) {
			$tf_millions = $tf_millions+$millionscy6;
		}
	}

	// ==== END SUMMING OF SALARIES NEXT YEAR; DETERMINE HARD AND SOFT CAP AMOUNTS

	$softcap = 5000 - $tf_millions;
	$hardcap = 7000 - $tf_millions;
	*/

	$conttot1=0;
	$conttot2=0;
	$conttot3=0;
	$conttot4=0;
	$conttot5=0;
	$conttot6=0;

	$rosterspots1 = 15;
	$rosterspots2 = 15;
	$rosterspots3 = 15;
	$rosterspots4 = 15;
	$rosterspots5 = 15;
	$rosterspots6 = 15;

	echo "<center><img src=\"images/logo/$tid.jpg\"></center><p>";

	// ==== DISPLAY PLAYERS CURRENTLY UNDER CONTRACT FOR TEAM

	echo "<table border=1 cellspacing=0 class=\"sortable\">
		<caption style=\"background-color: #0000cc\">
			<center><b><font color=white>$userteam Players Under Contract</font></b></center>
		</caption>
		<colgroup>
			<col span=5>
			<col span=6 style=\"background-color: #ddd\">
			<col span=7>
			<col span=8 style=\"background-color: #ddd\">
			<col span=3>
			<col span=6 style=\"background-color: #ddd\">
			<col span=5>
		</colgroup>
		<thead>
			<tr>
				<td><b>Options</b></td>
				<td><b>Pos</b></td>
				<td><b>Player</b></td>
				<td><b>Team</b></td>
				<td><b>Age</b></td>
				<td><b>2ga</b></td>
				<td><b>2g%</b></td>
				<td><b>fta</b></td>
				<td><b>ft%</b></td>
				<td><b>3ga</b></td>
				<td><b>3g%</b></td>
				<td><b>orb</b></td>
				<td><b>drb</b></td>
				<td><b>ast</b></td>
				<td><b>stl</b></td>
				<td><b>to</b></td>
				<td><b>blk</b></td>
				<td><b>foul</b></td>
				<td><b>oo</b></td>
				<td><b>do</b></td>
				<td><b>po</b></td>
				<td><b>to</b></td>
				<td><b>od</b></td>
				<td><b>dd</b></td>
				<td><b>pd</b></td>
				<td><b>td</b></td>
				<td><b>T</b></td>
				<td><b>S</b></td>
				<td><b>I</b></td>
				<td><b>Yr1</b></td>
				<td><b>Yr2</b></td>
				<td><b>Yr3</b></td>
				<td><b>Yr4</b></td>
				<td><b>Yr5</b></td>
				<td><b>Yr6</b></td>
				<td><b>Loy</b></td>
				<td><b>PFW</b></td>
				<td><b>PT</b></td>
				<td><b>Sec</b></td>
				<td><b>Trad</b></td>
			</tr>
		</thead>
		<tbody>";

	$showteam = $db->sql_query("SELECT * FROM ".$prefix."_iblplyr WHERE teamname='$userteam' AND retired='0' ORDER BY ordinal ASC");
	while ($teamlist = $db->sql_fetchrow($showteam)) {
		$ordinal = stripslashes(check_html($teamlist['ordinal'], "nohtml"));
		$draftyear = stripslashes(check_html($teamlist['draftyear'], "nohtml"));
		$exp = stripslashes(check_html($teamlist['exp'], "nohtml"));
		$cy = stripslashes(check_html($teamlist['cy'], "nohtml"));
		$cyt = stripslashes(check_html($teamlist['cyt'], "nohtml"));

		$yearoffreeagency=$draftyear+$exp+$cyt-$cy;

		if ($yearoffreeagency != $currentSeasonEndingYear) {
			$name = stripslashes(check_html($teamlist['name'], "nohtml"));
			$team = stripslashes(check_html($teamlist['teamname'], "nohtml"));
			$tid = stripslashes(check_html($teamlist['tid'], "nohtml"));
			$pid = stripslashes(check_html($teamlist['pid'], "nohtml"));
			$pos = stripslashes(check_html($teamlist['pos'], "nohtml"));
			$age = stripslashes(check_html($teamlist['age'], "nohtml"));
			$inj = stripslashes(check_html($teamlist['injured'], "nohtml"));
			$draftround = stripslashes(check_html($teamlist['draftround'], "nohtml"));

			$r_2ga = stripslashes(check_html($teamlist['r_fga'], "nohtml"));
			$r_2gp = stripslashes(check_html($teamlist['r_fgp'], "nohtml"));
			$r_fta = stripslashes(check_html($teamlist['r_fta'], "nohtml"));
			$r_ftp = stripslashes(check_html($teamlist['r_ftp'], "nohtml"));
			$r_3ga = stripslashes(check_html($teamlist['r_tga'], "nohtml"));
			$r_3gp = stripslashes(check_html($teamlist['r_tgp'], "nohtml"));
			$r_orb = stripslashes(check_html($teamlist['r_orb'], "nohtml"));
			$r_drb = stripslashes(check_html($teamlist['r_drb'], "nohtml"));
			$r_ast = stripslashes(check_html($teamlist['r_ast'], "nohtml"));
			$r_stl = stripslashes(check_html($teamlist['r_stl'], "nohtml"));
			$r_blk = stripslashes(check_html($teamlist['r_blk'], "nohtml"));
			$r_tvr = stripslashes(check_html($teamlist['r_to'], "nohtml"));
			$r_foul = stripslashes(check_html($teamlist['r_foul'], "nohtml"));
			$r_oo = stripslashes(check_html($teamlist['oo'], "nohtml"));
			$r_do = stripslashes(check_html($teamlist['do'], "nohtml"));
			$r_po = stripslashes(check_html($teamlist['po'], "nohtml"));
			$r_to = stripslashes(check_html($teamlist['to'], "nohtml"));
			$r_od = stripslashes(check_html($teamlist['od'], "nohtml"));
			$r_dd = stripslashes(check_html($teamlist['dd'], "nohtml"));
			$r_pd = stripslashes(check_html($teamlist['pd'], "nohtml"));
			$r_td = stripslashes(check_html($teamlist['td'], "nohtml"));

			$talent = $teamlist['talent'];
			$skill = $teamlist['skill'];
			$intangibles = $teamlist['intangibles'];

			$loy = $teamlist['loyalty'];
			$pfw = $teamlist['winner'];
			$pt = $teamlist['playingTime'];
			$sec = $teamlist['security'];
			$trad = $teamlist['tradition'];

			// === MATCH UP CONTRACT AMOUNTS WITH FUTURE YEARS BASED ON CURRENT YEAR OF CONTRACT

			$millionscy = stripslashes(check_html($teamlist['cy'], "nohtml"));
			$millionscy1 = stripslashes(check_html($teamlist['cy1'], "nohtml"));
			$millionscy2 = stripslashes(check_html($teamlist['cy2'], "nohtml"));
			$millionscy3 = stripslashes(check_html($teamlist['cy3'], "nohtml"));
			$millionscy4 = stripslashes(check_html($teamlist['cy4'], "nohtml"));
			$millionscy5 = stripslashes(check_html($teamlist['cy5'], "nohtml"));
			$millionscy6 = stripslashes(check_html($teamlist['cy6'], "nohtml"));

			$contract1 = 0;
			$contract2 = 0;
			$contract3 = 0;
			$contract4 = 0;
			$contract5 = 0;
			$contract6 = 0;

			if ($millionscy == 0) {
				$contract1 = $millionscy1;
				$contract2 = $millionscy2;
				$contract3 = $millionscy3;
				$contract4 = $millionscy4;
				$contract5 = $millionscy5;
				$contract6 = $millionscy6;

				if ($millionscy1 != 0) {
					$rosterspots1 -= 1;
				}
				if ($millionscy2 != 0) {
					$rosterspots2 -= 1;
				}
				if ($millionscy3 != 0) {
					$rosterspots3 -= 1;
				}
				if ($millionscy4 != 0) {
					$rosterspots4 -= 1;
				}
				if ($millionscy5 != 0) {
					$rosterspots5 -= 1;
				}
				if ($millionscy6 != 0) {
					$rosterspots6 -= 1;
				}
			}
			if ($millionscy == 1) {
				$contract1 = $millionscy2;
				$contract2 = $millionscy3;
				$contract3 = $millionscy4;
				$contract4 = $millionscy5;
				$contract5 = $millionscy6;

				if ($millionscy2 != 0) {
					$rosterspots1 -= 1;
				}
				if ($millionscy3 != 0) {
					$rosterspots2 -= 1;
				}
				if ($millionscy4 != 0) {
					$rosterspots3 -= 1;
				}
				if ($millionscy5 != 0) {
					$rosterspots4 -= 1;
				}
				if ($millionscy6 != 0) {
					$rosterspots5 -= 1;
				}
			}
			if ($millionscy == 2) {
				$contract1 = $millionscy3;
				$contract2 = $millionscy4;
				$contract3 = $millionscy5;
				$contract4 = $millionscy6;

				if ($millionscy3 != 0) {
					$rosterspots1 -= 1;
				}
				if ($millionscy4 != 0) {
					$rosterspots2 -= 1;
				}
				if ($millionscy5 != 0) {
					$rosterspots3 -= 1;
				}
				if ($millionscy6 != 0) {
					$rosterspots4 -= 1;
				}
			}
			if ($millionscy == 3) {
				$contract1 = $millionscy4;
				$contract2 = $millionscy5;
				$contract3 = $millionscy6;

				if ($millionscy4 != 0) {
					$rosterspots1 -= 1;
				}
				if ($millionscy5 != 0) {
					$rosterspots2 -= 1;
				}
				if ($millionscy6 != 0) {
					$rosterspots3 -= 1;
				}
			}
			if ($millionscy == 4) {
				$contract1 = $millionscy5;
				$contract2 = $millionscy6;

				if ($millionscy5 != 0) {
					$rosterspots1 -= 1;
				}
				if ($millionscy6 != 0) {
					$rosterspots2 -= 1;
				}
			}
			if ($millionscy == 5) {
				$contract1 = $millionscy6;

				if ($millionscy6 != 0) {
					$rosterspots1 -= 1;
				}
			}

			echo "<tr>
				<td>";

			// ==== ROOKIE OPTIONS
			if (($draftround == 1 && $exp == 2 && $millionscy4 == 0) OR
				($draftround == 2 && $exp == 1 && $millionscy3 == 0)) {
				echo "<a href=\"modules.php?name=Player&pa=rookieoption&pid=$pid\">Rookie Option</a>";
			}

			// ==== CHECK FOR ROOKIE POSITION MIGRATION POSSIBILITY
			// if ($exp == 0) {
			// 	echo "<a href=\"modules.php?name=Free_Agency&pa=positionmigration&pid=$pid\">Migrate Position</a>";
			// }

			if ($ordinal > 960) {
				$name .= "*";
			}

			echo "</td>
				<td>$pos</td>
				<td><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>
				<td><a href=\"modules.php?name=Team&op=team&tid=$tid\">$team</a></td>
				<td>$age</td>
				<td>$r_2ga</td>
				<td>$r_2gp</td>
				<td>$r_fta</td>
				<td>$r_ftp</td>
				<td>$r_3ga</td>
				<td>$r_3gp</td>
				<td>$r_orb</td>
				<td>$r_drb</td>
				<td>$r_ast</td>
				<td>$r_stl</td>
				<td>$r_tvr</td>
				<td>$r_blk</td>
				<td>$r_foul</td>
				<td>$r_oo</td>
				<td>$r_do</td>
				<td>$r_po</td>
				<td>$r_to</td>
				<td>$r_od</td>
				<td>$r_dd</td>
				<td>$r_pd</td>
				<td>$r_td</td>
				<td>$talent</td>
				<td>$skill</td>
				<td>$intangibles</td>
				<td>$contract1</td>
				<td>$contract2</td>
				<td>$contract3</td>
				<td>$contract4</td>
				<td>$contract5</td>
				<td>$contract6</td>
				<td>$loy</td>
				<td>$pfw</td>
				<td>$pt</td>
				<td>$sec</td>
				<td>$trad</td>
			</tr>";

			$conttot1 += $contract1;
			$conttot2 += $contract2;
			$conttot3 += $contract3;
			$conttot4 += $contract4;
			$conttot5 += $contract5;
			$conttot6 += $contract6;
		}
	}

	echo "</tbody>
		<tfoot>
			<tr>
				<td colspan=29 align=right><b><i>$userteam Total Committed Contracts</i></b></td>
				<td><b><i>$conttot1</i></b></td>
				<td><b><i>$conttot2</i></b></td>
				<td><b><i>$conttot3</i></b></td>
				<td><b><i>$conttot4</i></b></td>
				<td><b><i>$conttot5</i></b></td>
				<td><b><i>$conttot6</i></b></td>
			</tr>
		</tfoot>
	</table>

	<p>";

	// ==== END LIST OF PLAYERS CURRENTLY UNDER CONTRACT

	// ==== INSERT LIST OF PLAYERS WITH OFFERS

	echo "<table border=1 cellspacing=0 class=\"sortable\">
		<caption style=\"background-color: #0000cc\">
			<center><b><font color=white>$userteam Outstanding Contract Offers</font></b></center>
		</caption>
		<colgroup>
			<col span=5>
			<col span=6 style=\"background-color: #ddd\">
			<col span=7>
			<col span=8 style=\"background-color: #ddd\">
			<col span=3>
			<col span=6 style=\"background-color: #ddd\">
			<col span=5>
		</colgroup>
		<thead>
			<tr>
				<td><b>Negotiate</b></td>
				<td><b>Pos</b></td>
				<td><b>Player</b></td>
				<td><b>Team</b></td>
				<td><b>Age</b></td>
				<td><b>2ga</b></td>
				<td><b>2g%</b></td>
				<td><b>fta</b></td>
				<td><b>ft%</b></td>
				<td><b>3ga</b></td>
				<td><b>3g%</b></td>
				<td><b>orb</b></td>
				<td><b>drb</b></td>
				<td><b>ast</b></td>
				<td><b>stl</b></td>
				<td><b>to</b></td>
				<td><b>blk</b></td>
				<td><b>foul</b></td>
				<td><b>oo</b></td>
				<td><b>do</b></td>
				<td><b>po</b></td>
				<td><b>to</b></td>
				<td><b>od</b></td>
				<td><b>dd</b></td>
				<td><b>pd</b></td>
				<td><b>td</b></td>
				<td><b>T</b></td>
				<td><b>S</b></td>
				<td><b>I</b></td>
				<td><b>Yr1</b></td>
				<td><b>Yr2</b></td>
				<td><b>Yr3</b></td>
				<td><b>Yr4</b></td>
				<td><b>Yr5</b></td>
				<td><b>Yr6</b></td>
				<td><b>Loy</b></td>
				<td><b>PFW</b></td>
				<td><b>PT</b></td>
				<td><b>Sec</b></td>
				<td><b>Trad</b></td>
			</tr>
		</thead>
		<tbody>";

	$showteam = $db->sql_query("SELECT * FROM ".$prefix."_iblplyr WHERE retired='0' ORDER BY ordinal ASC");
	while ($teamlist = $db->sql_fetchrow($showteam)) {
		$name = stripslashes(check_html($teamlist['name'], "nohtml"));

		$numoffers = $db->sql_numrows($db->sql_query("SELECT * FROM ".$prefix."_ibl_fa_offers WHERE name='$name' AND team='$userteam'"));
		if ($numoffers == 1) {
			$team = stripslashes(check_html($teamlist['teamname'], "nohtml"));
			$tid = stripslashes(check_html($teamlist['tid'], "nohtml"));
			$pid = stripslashes(check_html($teamlist['pid'], "nohtml"));
			$pos = stripslashes(check_html($teamlist['pos'], "nohtml"));
			$age = stripslashes(check_html($teamlist['age'], "nohtml"));
			$inj = stripslashes(check_html($teamlist['injured'], "nohtml"));

			$getoffers = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_ibl_fa_offers WHERE name='$name' AND team='$userteam'"));

			$offer1 = stripslashes(check_html($getoffers['offer1'], "nohtml"));
			$offer2 = stripslashes(check_html($getoffers['offer2'], "nohtml"));
			$offer3 = stripslashes(check_html($getoffers['offer3'], "nohtml"));
			$offer4 = stripslashes(check_html($getoffers['offer4'], "nohtml"));
			$offer5 = stripslashes(check_html($getoffers['offer5'], "nohtml"));
			$offer6 = stripslashes(check_html($getoffers['offer6'], "nohtml"));

			$r_2ga = stripslashes(check_html($teamlist['r_fga'], "nohtml"));
			$r_2gp = stripslashes(check_html($teamlist['r_fgp'], "nohtml"));
			$r_fta = stripslashes(check_html($teamlist['r_fta'], "nohtml"));
			$r_ftp = stripslashes(check_html($teamlist['r_ftp'], "nohtml"));
			$r_3ga = stripslashes(check_html($teamlist['r_tga'], "nohtml"));
			$r_3gp = stripslashes(check_html($teamlist['r_tgp'], "nohtml"));
			$r_orb = stripslashes(check_html($teamlist['r_orb'], "nohtml"));
			$r_drb = stripslashes(check_html($teamlist['r_drb'], "nohtml"));
			$r_ast = stripslashes(check_html($teamlist['r_ast'], "nohtml"));
			$r_stl = stripslashes(check_html($teamlist['r_stl'], "nohtml"));
			$r_blk = stripslashes(check_html($teamlist['r_blk'], "nohtml"));
			$r_tvr = stripslashes(check_html($teamlist['r_to'], "nohtml"));
			$r_foul = stripslashes(check_html($teamlist['r_foul'], "nohtml"));
			$r_oo = stripslashes(check_html($teamlist['oo'], "nohtml"));
			$r_do = stripslashes(check_html($teamlist['do'], "nohtml"));
			$r_po = stripslashes(check_html($teamlist['po'], "nohtml"));
			$r_to = stripslashes(check_html($teamlist['to'], "nohtml"));
			$r_od = stripslashes(check_html($teamlist['od'], "nohtml"));
			$r_dd = stripslashes(check_html($teamlist['dd'], "nohtml"));
			$r_pd = stripslashes(check_html($teamlist['pd'], "nohtml"));
			$r_td = stripslashes(check_html($teamlist['td'], "nohtml"));

			$talent = $teamlist['talent'];
			$skill = $teamlist['skill'];
			$intangibles = $teamlist['intangibles'];

			$loy = $teamlist['loyalty'];
			$pfw = $teamlist['winner'];
			$pt = $teamlist['playingTime'];
			$sec = $teamlist['security'];
			$trad = $teamlist['tradition'];

			echo "<tr>
				<td><a href=\"modules.php?name=Free_Agency&pa=negotiate&pid=$pid\">Negotiate</a></td>
				<td>$pos</td>
				<td><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>
				<td><a href=\"modules.php?name=Team&op=team&tid=$tid\">$team</a></td>
				<td>$age</td>
				<td>$r_2ga</td>
				<td>$r_2gp</td>
				<td>$r_fta</td>
				<td>$r_ftp</td>
				<td>$r_3ga</td>
				<td>$r_3gp</td>
				<td>$r_orb</td>
				<td>$r_drb</td>
				<td>$r_ast</td>
				<td>$r_stl</td>
				<td>$r_tvr</td>
				<td>$r_blk</td>
				<td>$r_foul</td>
				<td>$r_oo</td>
				<td>$r_do</td>
				<td>$r_po</td>
				<td>$r_to</td>
				<td>$r_od</td>
				<td>$r_dd</td>
				<td>$r_pd</td>
				<td>$r_td</td>
				<td>$talent</td>
				<td>$skill</td>
				<td>$intangibles</td>
				<td>$offer1</td>
				<td>$offer2</td>
				<td>$offer3</td>
				<td>$offer4</td>
				<td>$offer5</td>
				<td>$offer6</td>
				<td>$loy</td>
				<td>$pfw</td>
				<td>$pt</td>
				<td>$sec</td>
				<td>$trad</td>
			</tr>";

			$conttot1=$conttot1+$offer1;
			$conttot2=$conttot2+$offer2;
			$conttot3=$conttot3+$offer3;
			$conttot4=$conttot4+$offer4;
			$conttot5=$conttot5+$offer5;
			$conttot6=$conttot6+$offer6;

			if ($offer1 != 0) {
				$rosterspots1 -= 1;
			}
			if ($offer2 != 0) {
				$rosterspots2 -= 1;
			}
			if ($offer3 != 0) {
				$rosterspots3 -= 1;
			}
			if ($offer4 != 0) {
				$rosterspots4 -= 1;
			}
			if ($offer5 != 0) {
				$rosterspots5 -= 1;
			}
			if ($offer6 != 0) {
				$rosterspots6 -= 1;
			}
		}
	}

	echo "</tbody>
		<tfoot>
			<tr>
				<td colspan=29 align=right><b><i>$userteam Total Committed Plus Offered Contracts</i></b></td>
				<td><b><i>$conttot1</i></b></td>
				<td><b><i>$conttot2</i></b></td>
				<td><b><i>$conttot3</i></b></td>
				<td><b><i>$conttot4</i></b></td>
				<td><b><i>$conttot5</i></b></td>
				<td><b><i>$conttot6</i></b></td>
			</tr>";

	// ==== END INSERT OF PLAYERS WITH OFFERS

	$softcap = 5000 - $conttot1;
	$hardcap = 7000 - $conttot1;
	$softcap2 = 5000 - $conttot2;
	$hardcap2 = 7000 - $conttot2;
	$softcap3 = 5000 - $conttot3;
	$hardcap3 = 7000 - $conttot3;
	$softcap4 = 5000 - $conttot4;
	$hardcap4 = 7000 - $conttot4;
	$softcap5 = 5000 - $conttot5;
	$hardcap5 = 7000 - $conttot5;
	$softcap6 = 5000 - $conttot6;
	$hardcap6 = 7000 - $conttot6;

	// ===== CAP AND ROSTER SLOT INFO =====

	$exceptioninfo = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_ibl_team_info WHERE team_name='$userteam'"));

	$HasMLE = stripslashes(check_html($exceptioninfo['HasMLE'], "nohtml"));
	$HasLLE = stripslashes(check_html($exceptioninfo['HasLLE'], "nohtml"));

	echo "<tr bgcolor=#cc0000>
		<td colspan=21 bgcolor=#eeeeee></td>
		<td colspan=8 align=right><font color=white><b>Soft Cap Space</b></font></td>
		<td>$softcap</td>
		<td>$softcap2</td>
		<td>$softcap3</td>
		<td>$softcap4</td>
		<td>$softcap5</td>
		<td>$softcap6</td>
	</tr>";
	echo "<tr bgcolor=#cc0000>
		<td colspan=21 bgcolor=#eeeeee></td>
		<td colspan=8 align=right><font color=white><b>Hard Cap Space</b></font></td>
		<td>$hardcap</td>
		<td>$hardcap2</td>
		<td>$hardcap3</td>
		<td>$hardcap4</td>
		<td>$hardcap5</td>
		<td>$hardcap6</td>
	</tr>";
	echo "<tr bgcolor=#cc0000>
		<td colspan=21 bgcolor=#eeeeee></td>
		<td colspan=8 align=right><font color=white><b>Empty Roster Slots</b></font></td>
		<td>$rosterspots1</td>
		<td>$rosterspots2</td>
		<td>$rosterspots3</td>
		<td>$rosterspots4</td>
		<td>$rosterspots5</td>
		<td>$rosterspots6</td>
	</tr>";

	echo "<tr bgcolor=#cc0000><td colspan=35><font color=white><b>";

	if ($HasMLE == 1) {
		echo "Your team has access to the Mid-Level Exception (MLE) and hasn't signed a player with it (but you may have offered it to someone above).</b></font></td></tr>";
	} else {
		echo "Your team does NOT have access to the Mid-Level Exception - you either used it or didn't have sufficient cap space at the start of free agency.</b></font></td></tr>";
	}

	echo "                <tr bgcolor=#cc0000><td colspan=35><font color=white><b>";

	if ($HasLLE == 1) {
		echo "Your team has access to the Lower-Level Exception (LLE) and hasn't signed a player with it (but you may have offered it to someone above).</b></font></td></tr>";
	} else {
		echo "Your team does not have access to the Lower-Level Exception; you have already used it to sign a free agent.</b></font></td></tr>";
	}

	echo "</tfoot>
	</table>

	<p>
	<hr>
	<p>";

	// ==== INSERT LIST OF FREE AGENTS FROM TEAM

	echo "<table border=1 cellspacing=0 class=\"sortable\">
		<caption style=\"background-color: #0000cc\">
			<center><b><font color=white>$userteam Unsigned Free Agents</b><br>
			(Note: * and <i>italicized</i> indicates player has Bird Rights)</font></b></center>
		</caption>
		<colgroup>
			<col span=5>
			<col span=6 style=\"background-color: #ddd\">
			<col span=7>
			<col span=8 style=\"background-color: #ddd\">
			<col span=3>
			<col span=6 style=\"background-color: #ddd\">
			<col span=5>
		</colgroup>
		<thead>
			<tr>
				<td><b>Negotiate</b></td>
				<td><b>Pos</b></td>
				<td><b>Player</b></td>
				<td><b>Team</b></td>
				<td><b>Age</b></td>
				<td><b>2ga</b></td>
				<td><b>2g%</b></td>
				<td><b>fta</b></td>
				<td><b>ft%</b></td>
				<td><b>3ga</b></td>
				<td><b>3g%</b></td>
				<td><b>orb</b></td>
				<td><b>drb</b></td>
				<td><b>ast</b></td>
				<td><b>stl</b></td>
				<td><b>to</b></td>
				<td><b>blk</b></td>
				<td><b>foul</b></td>
				<td><b>oo</b></td>
				<td><b>do</b></td>
				<td><b>po</b></td>
				<td><b>to</b></td>
				<td><b>od</b></td>
				<td><b>dd</b></td>
				<td><b>pd</b></td>
				<td><b>td</b></td>
				<td><b>T</b></td>
				<td><b>S</b></td>
				<td><b>I</b></td>
				<td><b>Yr1</b></td>
				<td><b>Yr2</b></td>
				<td><b>Yr3</b></td>
				<td><b>Yr4</b></td>
				<td><b>Yr5</b></td>
				<td><b>Yr6</b></td>
				<td><b>Loy</b></td>
				<td><b>PFW</b></td>
				<td><b>PT</b></td>
				<td><b>Sec</b></td>
				<td><b>Trad</b></td>
			</tr>
		</thead>
		<tbody>";

	$showteam = $db->sql_query("SELECT * FROM ".$prefix."_iblplyr WHERE teamname='$userteam' AND retired='0' ORDER BY ordinal ASC");
	while ($teamlist = $db->sql_fetchrow($showteam)) {
		$draftyear = stripslashes(check_html($teamlist['draftyear'], "nohtml"));
		$exp = stripslashes(check_html($teamlist['exp'], "nohtml"));
		$cy = stripslashes(check_html($teamlist['cy'], "nohtml"));
		$cyt = stripslashes(check_html($teamlist['cyt'], "nohtml"));
		$yearoffreeagency=$draftyear+$exp+$cyt-$cy;

		if ($yearoffreeagency == $currentSeasonEndingYear) {
			$name = stripslashes(check_html($teamlist['name'], "nohtml"));
			$team = stripslashes(check_html($teamlist['teamname'], "nohtml"));
			$tid = stripslashes(check_html($teamlist['tid'], "nohtml"));
			$pid = stripslashes(check_html($teamlist['pid'], "nohtml"));
			$pos = stripslashes(check_html($teamlist['pos'], "nohtml"));
			$age = stripslashes(check_html($teamlist['age'], "nohtml"));
			$inj = stripslashes(check_html($teamlist['injured'], "nohtml"));
			$bird = stripslashes(check_html($teamlist['bird'], "nohtml"));

			$getdemands = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_ibl_demands WHERE name='$name'"));

			$dem1 = stripslashes(check_html($getdemands['dem1'], "nohtml"));
			$dem2 = stripslashes(check_html($getdemands['dem2'], "nohtml"));
			$dem3 = stripslashes(check_html($getdemands['dem3'], "nohtml"));
			$dem4 = stripslashes(check_html($getdemands['dem4'], "nohtml"));
			$dem5 = stripslashes(check_html($getdemands['dem5'], "nohtml"));
			$dem6 = stripslashes(check_html($getdemands['dem6'], "nohtml"));

			$r_2ga = stripslashes(check_html($teamlist['r_fga'], "nohtml"));
			$r_2gp = stripslashes(check_html($teamlist['r_fgp'], "nohtml"));
			$r_fta = stripslashes(check_html($teamlist['r_fta'], "nohtml"));
			$r_ftp = stripslashes(check_html($teamlist['r_ftp'], "nohtml"));
			$r_3ga = stripslashes(check_html($teamlist['r_tga'], "nohtml"));
			$r_3gp = stripslashes(check_html($teamlist['r_tgp'], "nohtml"));
			$r_orb = stripslashes(check_html($teamlist['r_orb'], "nohtml"));
			$r_drb = stripslashes(check_html($teamlist['r_drb'], "nohtml"));
			$r_ast = stripslashes(check_html($teamlist['r_ast'], "nohtml"));
			$r_stl = stripslashes(check_html($teamlist['r_stl'], "nohtml"));
			$r_blk = stripslashes(check_html($teamlist['r_blk'], "nohtml"));
			$r_tvr = stripslashes(check_html($teamlist['r_to'], "nohtml"));
			$r_foul = stripslashes(check_html($teamlist['r_foul'], "nohtml"));
			$r_oo = stripslashes(check_html($teamlist['oo'], "nohtml"));
			$r_do = stripslashes(check_html($teamlist['do'], "nohtml"));
			$r_po = stripslashes(check_html($teamlist['po'], "nohtml"));
			$r_to = stripslashes(check_html($teamlist['to'], "nohtml"));
			$r_od = stripslashes(check_html($teamlist['od'], "nohtml"));
			$r_dd = stripslashes(check_html($teamlist['dd'], "nohtml"));
			$r_pd = stripslashes(check_html($teamlist['pd'], "nohtml"));
			$r_td = stripslashes(check_html($teamlist['td'], "nohtml"));

			$talent = $teamlist['talent'];
			$skill = $teamlist['skill'];
			$intangibles = $teamlist['intangibles'];

			$loy = $teamlist['loyalty'];
			$pfw = $teamlist['winner'];
			$pt = $teamlist['playingTime'];
			$sec = $teamlist['security'];
			$trad = $teamlist['tradition'];

			echo "<tr>
				<td>";

			if ($rosterspots1 > 0) {
				echo "<a href=\"modules.php?name=Free_Agency&pa=negotiate&pid=$pid\">Negotiate</a>";
			}

			echo "</td><td>$pos</td><td><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">";

			// ==== NOTE PLAYERS ON TEAM WITH BIRD RIGHTS

			if ($bird > 2) echo "*<i>";
			echo "$name";
			if ($bird > 2) echo "</i>*";

			// ==== END NOTE BIRD RIGHTS

			echo "</a></td>
				<td><a href=\"modules.php?name=Team&op=team&tid=$tid\">$team</a></td>
				<td>$age</td>
				<td>$r_2ga</td>
				<td>$r_2gp</td>
				<td>$r_fta</td>
				<td>$r_ftp</td>
				<td>$r_3ga</td>
				<td>$r_3gp</td>
				<td>$r_orb</td>
				<td>$r_drb</td>
				<td>$r_ast</td>
				<td>$r_stl</td>
				<td>$r_tvr</td>
				<td>$r_blk</td>
				<td>$r_foul</td>
				<td>$r_oo</td>
				<td>$r_do</td>
				<td>$r_po</td>
				<td>$r_to</td>
				<td>$r_od</td>
				<td>$r_dd</td>
				<td>$r_pd</td>
				<td>$r_td</td>
				<td>$talent</td>
				<td>$skill</td>
				<td>$intangibles</td>
				<td>$dem1</td>
				<td>$dem2</td>
				<td>$dem3</td>
				<td>$dem4</td>
				<td>$dem5</td>
				<td>$dem6</td>
				<td>$loy</td>
				<td>$pfw</td>
				<td>$pt</td>
				<td>$sec</td>
				<td>$trad</td>
			</tr>";
		}
	}

	echo "</table>

	<p>";

	// ==== END INSERT OF LIST OF FREE AGENTS FROM TEAM

	// ==== INSERT ALL OTHER FREE AGENTS

	echo "<table border=1 cellspacing=0 class=\"sortable\">
		<caption style=\"background-color: #0000cc\">
			<center><b><font color=white>All Other Free Agents</font></b></center>
		</caption>
		<colgroup>
			<col span=5>
			<col span=6 style=\"background-color: #ddd\">
			<col span=7>
			<col span=8 style=\"background-color: #ddd\">
			<col span=3>
			<col span=6 style=\"background-color: #ddd\">
			<col span=5>
		</colgroup>
		<thead>
			<tr>
				<td><b>Negotiate</b></td>
				<td><b>Pos</b></td>
				<td><b>Player</b></td>
				<td><b>Team</b></td>
				<td><b>Age</b></td>
				<td><b>2ga</b></td>
				<td><b>2g%</b></td>
				<td><b>fta</b></td>
				<td><b>ft%</b></td>
				<td><b>3ga</b></td>
				<td><b>3g%</b></td>
				<td><b>orb</b></td>
				<td><b>drb</b></td>
				<td><b>ast</b></td>
				<td><b>stl</b></td>
				<td><b>to</b></td>
				<td><b>blk</b></td>
				<td><b>foul</b></td>
				<td><b>oo</b></td>
				<td><b>do</b></td>
				<td><b>po</b></td>
				<td><b>to</b></td>
				<td><b>od</b></td>
				<td><b>dd</b></td>
				<td><b>pd</b></td>
				<td><b>td</b></td>
				<td><b>T</b></td>
				<td><b>S</b></td>
				<td><b>I</b></td>
				<td><b>Yr1</b></td>
				<td><b>Yr2</b></td>
				<td><b>Yr3</b></td>
				<td><b>Yr4</b></td>
				<td><b>Yr5</b></td>
				<td><b>Yr6</b></td>
				<td><b>Loy</b></td>
				<td><b>PFW</b></td>
				<td><b>PT</b></td>
				<td><b>Sec</b></td>
				<td><b>Trad</b></td>
			</tr>
		</thead>
		<tbody>";

	$showteam = $db->sql_query("SELECT * FROM ".$prefix."_iblplyr WHERE teamname!='$userteam' AND retired='0' ORDER BY ordinal ASC");
	while ($teamlist = $db->sql_fetchrow($showteam)) {
		$draftyear = stripslashes(check_html($teamlist['draftyear'], "nohtml"));
		$exp = stripslashes(check_html($teamlist['exp'], "nohtml"));
		$cy = stripslashes(check_html($teamlist['cy'], "nohtml"));
		$cyt = stripslashes(check_html($teamlist['cyt'], "nohtml"));
		$yearoffreeagency = $draftyear + $exp + $cyt - $cy;

		if ($yearoffreeagency == $currentSeasonEndingYear) {
			$name = stripslashes(check_html($teamlist['name'], "nohtml"));
			$team = stripslashes(check_html($teamlist['teamname'], "nohtml"));
			$tid = stripslashes(check_html($teamlist['tid'], "nohtml"));
			$pid = stripslashes(check_html($teamlist['pid'], "nohtml"));
			$pos = stripslashes(check_html($teamlist['pos'], "nohtml"));
			$age = stripslashes(check_html($teamlist['age'], "nohtml"));
			$inj = stripslashes(check_html($teamlist['injured'], "nohtml"));

			$getdemands = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_ibl_demands WHERE name='$name'"));

			$dem1 = stripslashes(check_html($getdemands['dem1'], "nohtml"));
			$dem2 = stripslashes(check_html($getdemands['dem2'], "nohtml"));
			$dem3 = stripslashes(check_html($getdemands['dem3'], "nohtml"));
			$dem4 = stripslashes(check_html($getdemands['dem4'], "nohtml"));
			$dem5 = stripslashes(check_html($getdemands['dem5'], "nohtml"));
			$dem6 = stripslashes(check_html($getdemands['dem6'], "nohtml"));

			$r_2ga = stripslashes(check_html($teamlist['r_fga'], "nohtml"));
			$r_2gp = stripslashes(check_html($teamlist['r_fgp'], "nohtml"));
			$r_fta = stripslashes(check_html($teamlist['r_fta'], "nohtml"));
			$r_ftp = stripslashes(check_html($teamlist['r_ftp'], "nohtml"));
			$r_3ga = stripslashes(check_html($teamlist['r_tga'], "nohtml"));
			$r_3gp = stripslashes(check_html($teamlist['r_tgp'], "nohtml"));
			$r_orb = stripslashes(check_html($teamlist['r_orb'], "nohtml"));
			$r_drb = stripslashes(check_html($teamlist['r_drb'], "nohtml"));
			$r_ast = stripslashes(check_html($teamlist['r_ast'], "nohtml"));
			$r_stl = stripslashes(check_html($teamlist['r_stl'], "nohtml"));
			$r_blk = stripslashes(check_html($teamlist['r_blk'], "nohtml"));
			$r_tvr = stripslashes(check_html($teamlist['r_to'], "nohtml"));
			$r_foul = stripslashes(check_html($teamlist['r_foul'], "nohtml"));
			$r_oo = stripslashes(check_html($teamlist['oo'], "nohtml"));
			$r_do = stripslashes(check_html($teamlist['do'], "nohtml"));
			$r_po = stripslashes(check_html($teamlist['po'], "nohtml"));
			$r_to = stripslashes(check_html($teamlist['to'], "nohtml"));
			$r_od = stripslashes(check_html($teamlist['od'], "nohtml"));
			$r_dd = stripslashes(check_html($teamlist['dd'], "nohtml"));
			$r_pd = stripslashes(check_html($teamlist['pd'], "nohtml"));
			$r_td = stripslashes(check_html($teamlist['td'], "nohtml"));

			$talent = $teamlist['talent'];
			$skill = $teamlist['skill'];
			$intangibles = $teamlist['intangibles'];

			$loy = $teamlist['loyalty'];
			$pfw = $teamlist['winner'];
			$pt = $teamlist['playingTime'];
			$sec = $teamlist['security'];
			$trad = $teamlist['tradition'];

			echo "<tr>
				<td>";

			if ($rosterspots1 > 0) {
				echo "<a href=\"modules.php?name=Free_Agency&pa=negotiate&pid=$pid\">Negotiate</a>";
			}

			echo "</td>
				<td>$pos</td>
				<td><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>
				<td><a href=\"modules.php?name=Team&op=team&tid=$tid\">$team</a></td>
				<td>$age</td>
				<td>$r_2ga</td>
				<td>$r_2gp</td>
				<td>$r_fta</td>
				<td>$r_ftp</td>
				<td>$r_3ga</td>
				<td>$r_3gp</td>
				<td>$r_orb</td>
				<td>$r_drb</td>
				<td>$r_ast</td>
				<td>$r_stl</td>
				<td>$r_tvr</td>
				<td>$r_blk</td>
				<td>$r_foul</td>
				<td>$r_oo</td>
				<td>$r_do</td>
				<td>$r_po</td>
				<td>$r_to</td>
				<td>$r_od</td>
				<td>$r_dd</td>
				<td>$r_pd</td>
				<td>$r_td</td>
				<td>$talent</td>
				<td>$skill</td>
				<td>$intangibles</td>";

			if ($exp > 0) {
				echo "<td>$dem1</td>
				<td>$dem2</td>
				<td>$dem3</td>
				<td>$dem4</td>
				<td>$dem5</td>
				<td>$dem6</td>";
			} else {
				// Limit undrafted rookie FA contracts to two years by only displaying their demands for years 3 and 4
				// this is hacky and assumes that the demands table always contains demands for years 3 and 4 instead of recalculating demands appropriately
				echo "<td>$dem3</td>
				<td>$dem4</td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>";
			}

			echo "
				<td>$loy</td>
				<td>$pfw</td>
				<td>$pt</td>
				<td>$sec</td>
				<td>$trad</td>
			</tr>";
		}
	}

	// ==== END INSERT OF ALL OTHER FREE AGENTS

	echo "</table>";

	CloseTable();
	include("footer.php");
}

// === START NEGOTIATE FUNCTION ===
function negotiate($pid) {
	global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;
	$pid = intval($pid);

	cookiedecode($user);

	$sql2 = "SELECT * FROM ".$prefix."_users WHERE username='$cookie[1]'";
	$result2 = $db->sql_query($sql2);
	$num2 = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);

	$userteam = stripslashes(check_html($userinfo['user_ibl_team'], "nohtml"));

	$exceptioninfo = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_ibl_team_info WHERE team_name='$userteam'"));

	$HasMLE = stripslashes(check_html($exceptioninfo['HasMLE'], "nohtml"));
	$HasLLE = stripslashes(check_html($exceptioninfo['HasLLE'], "nohtml"));

	$playerinfo = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_iblplyr WHERE pid='$pid'"));

	$player_name = stripslashes(check_html($playerinfo['name'], "nohtml"));
	$player_pos = stripslashes(check_html($playerinfo['pos'], "nohtml"));
	$player_team_name = stripslashes(check_html($playerinfo['teamname'], "nohtml"));
	$player_loyalty = stripslashes(check_html($playerinfo['loyalty'], "nohtml"));
	$player_winner = stripslashes(check_html($playerinfo['winner'], "nohtml"));
	$player_playingtime = stripslashes(check_html($playerinfo['playingTime'], "nohtml"));
	$player_security = stripslashes(check_html($playerinfo['security'], "nohtml"));
	$player_coach = stripslashes(check_html($playerinfo['coach'], "nohtml"));
	$player_tradition = stripslashes(check_html($playerinfo['tradition'], "nohtml"));

	include("header.php");
	OpenTable();

	$player_exp = stripslashes(check_html($playerinfo['exp'], "nohtml"));
	$player_bird = stripslashes(check_html($playerinfo['bird'], "nohtml"));
	$player_cy = stripslashes(check_html($playerinfo['cy'], "nohtml"));
	$player_cy1 = stripslashes(check_html($playerinfo['cy1'], "nohtml"));
	$player_cy2 = stripslashes(check_html($playerinfo['cy2'], "nohtml"));
	$player_cy3 = stripslashes(check_html($playerinfo['cy3'], "nohtml"));
	$player_cy4 = stripslashes(check_html($playerinfo['cy4'], "nohtml"));
	$player_cy5 = stripslashes(check_html($playerinfo['cy5'], "nohtml"));
	$player_cy6 = stripslashes(check_html($playerinfo['cy6'], "nohtml"));

	$offer1 = 0;
	$offer2 = 0;
	$offer3 = 0;
	$offer4 = 0;
	$offer5 = 0;
	$offer6 = 0;

	echo "<b>$player_pos $player_name</b> - Contract Demands:
	<br>";

	$demands = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_ibl_demands WHERE name='$player_name'"));
	$dem1 = stripslashes(check_html($demands['dem1'], "nohtml"));
	$dem2 = stripslashes(check_html($demands['dem2'], "nohtml"));
	$dem3 = stripslashes(check_html($demands['dem3'], "nohtml"));
	$dem4 = stripslashes(check_html($demands['dem4'], "nohtml"));
	$dem5 = stripslashes(check_html($demands['dem5'], "nohtml"));
	$dem6 = stripslashes(check_html($demands['dem6'], "nohtml"));

	$teamfactors = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_ibl_team_info WHERE team_name='$userteam'"));
	$tf_wins = stripslashes(check_html($teamfactors['Contract_Wins'], "nohtml"));
	$tf_loss = stripslashes(check_html($teamfactors['Contract_Losses'], "nohtml"));
	$tf_trdw = stripslashes(check_html($teamfactors['Contract_AvgW'], "nohtml"));
	$tf_trdl = stripslashes(check_html($teamfactors['Contract_AvgL'], "nohtml"));
	$tf_coach = stripslashes(check_html($teamfactors['Contract_Coach'], "nohtml"));

	$millionsatposition = $db->sql_query("SELECT * FROM ".$prefix."_iblplyr WHERE teamname='$userteam' AND pos='$player_pos' AND name!='$player_name'");

	// LOOP TO GET MILLIONS COMMITTED AT POSITION

	$tf_millions = 0;

	while ($millionscounter = $db->sql_fetchrow($millionsatposition)) {
		$millionscy = stripslashes(check_html($millionscounter['cy'], "nohtml"));
		$millionscy1 = stripslashes(check_html($millionscounter['cy1'], "nohtml"));
		$millionscy2 = stripslashes(check_html($millionscounter['cy2'], "nohtml"));
		$millionscy3 = stripslashes(check_html($millionscounter['cy3'], "nohtml"));
		$millionscy4 = stripslashes(check_html($millionscounter['cy4'], "nohtml"));
		$millionscy5 = stripslashes(check_html($millionscounter['cy5'], "nohtml"));
		$millionscy6 = stripslashes(check_html($millionscounter['cy6'], "nohtml"));

		// LOOK AT SALARY COMMITTED IN PROPER YEAR

		if ($millionscy == 0) $tf_millions = $tf_millions+$millionscy1;
		if ($millionscy == 1) $tf_millions = $tf_millions+$millionscy2;
		if ($millionscy == 2) $tf_millions = $tf_millions+$millionscy3;
		if ($millionscy == 3) $tf_millions = $tf_millions+$millionscy4;
		if ($millionscy == 4) $tf_millions = $tf_millions+$millionscy5;
		if ($millionscy == 5) $tf_millions = $tf_millions+$millionscy6;
	}

	// END LOOP

	$demyrs = 6;
	if ($dem6 == 0){
		$demyrs = 5;
		if ($dem5 == 0){
			$demyrs = 4;
			if ($dem4 == 0){
				$demyrs = 3;
				if ($dem3 == 0){
					$demyrs = 2;
					if ($dem2 == 0){
						$demyrs = 1;
					}
				}
			}
		}
	}

	$modfactor1 = (0.0005*($tf_wins-$tf_losses)*($player_winner-1));
	$modfactor2 = (0.00125*($tf_trdw-$tf_trdl)*($player_tradition-1));
	$modfactor3 = (.01*($tf_coach)*($player_coach=1));
	$modfactor4 = (.025*($player_loyalty-1));
	$modfactor5 = (.01*($demyrs-1)-0.025)*($player_security-1);
	$modfactor6 = -(.0035*$tf_millions/100-0.028)*($player_playingtime-1);

	$modifier = 1+$modfactor1+$modfactor2+$modfactor3+$modfactor4+$modfactor5+$modfactor6-0.20;

	$demtot = round(($dem1+$dem2+$dem3+$dem4+$dem5+$dem6)/100,2);
	$demavg = ($dem1+$dem2+$dem3+$dem4+$dem5+$dem6)/$demyrs;

	if ($player_exp > 0) {
		$demand_display = $dem1;
		if ($dem2 != 0) $demand_display = $demand_display."</td><td>".$dem2;
		if ($dem3 != 0) $demand_display = $demand_display."</td><td>".$dem3;
		if ($dem4 != 0) $demand_display = $demand_display."</td><td>".$dem4;
		if ($dem5 != 0) $demand_display = $demand_display."</td><td>".$dem5;
		if ($dem6 != 0) $demand_display = $demand_display."</td><td>".$dem6;
		$demand_display .= "</td><td></td>";
	} else {
		// Limit undrafted rookie FA contracts to two years by only displaying their demands for years 3 and 4
		// this is hacky and assumes that the demands table always contains demands for years 3 and 4 instead of recalculating demands appropriately
		$demand_display = $dem3;
		if ($dem4 != 0) $demand_display = $demand_display."</td><td>".$dem4;
		$demand_display .= "</td><td></td>";
	}



	// LOOP TO GET SOFT CAP SPACE

	$capnumber = 5000;
	$capnumber2 = 5000;
	$capnumber3 = 5000;
	$capnumber4 = 5000;
	$capnumber5 = 5000;
	$capnumber6 = 5000;

	$rosterspots=15;

	$capquery = "SELECT * FROM ".$prefix."_iblplyr WHERE teamname='$userteam' AND retired = '0'";
	$capresult = $db->sql_query($capquery);

	while($capdecrementer = $db->sql_fetchrow($capresult)) {
		$ordinal = stripslashes(check_html($capdecrementer['ordinal'], "nohtml"));
		$capcy = stripslashes(check_html($capdecrementer['cy'], "nohtml"));
		$capcyt = stripslashes(check_html($capdecrementer['cyt'], "nohtml"));
		$capcy1 = stripslashes(check_html($capdecrementer['cy1'], "nohtml"));
		$capcy2 = stripslashes(check_html($capdecrementer['cy2'], "nohtml"));
		$capcy3 = stripslashes(check_html($capdecrementer['cy3'], "nohtml"));
		$capcy4 = stripslashes(check_html($capdecrementer['cy4'], "nohtml"));
		$capcy5 = stripslashes(check_html($capdecrementer['cy5'], "nohtml"));
		$capcy6 = stripslashes(check_html($capdecrementer['cy6'], "nohtml"));

		// LOOK AT SALARY COMMITTED IN PROPER YEAR

		if ($capcy == 0) {
			$capnumber = $capnumber-$capcy1;
			$capnumber2 = $capnumber2-$capcy2;
			$capnumber3 = $capnumber3-$capcy3;
			$capnumber4 = $capnumber4-$capcy4;
			$capnumber5 = $capnumber5-$capcy5;
			$capnumber6 = $capnumber6-$capcy6;
		}
		if ($capcy == 1) {
			$capnumber = $capnumber-$capcy2;
			$capnumber2 = $capnumber2-$capcy3;
			$capnumber3 = $capnumber3-$capcy4;
			$capnumber4 = $capnumber4-$capcy5;
			$capnumber5 = $capnumber5-$capcy6;
		}
		if ($capcy == 2) {
			$capnumber = $capnumber-$capcy3;
			$capnumber2 = $capnumber2-$capcy4;
			$capnumber3 = $capnumber3-$capcy5;
			$capnumber4 = $capnumber4-$capcy6;
		}
		if ($capcy == 3) {
			$capnumber = $capnumber-$capcy4;
			$capnumber2 = $capnumber2-$capcy5;
			$capnumber3 = $capnumber3-$capcy6;
		}
		if ($capcy == 4) {
			$capnumber = $capnumber-$capcy5;
			$capnumber2 = $capnumber2-$capcy6;
		}
		if ($capcy == 5) {
		$capnumber = $capnumber-$capcy6;
		}

		if ($capcy != $capcyt && $ordinal <= 960) $rosterspots=$rosterspots-1;
	}

	$capquery2 = "SELECT * FROM ".$prefix."_ibl_fa_offers WHERE team='$userteam'";
	$capresult2 = $db->sql_query($capquery2);

	while($capdecrementer2 = $db->sql_fetchrow($capresult2)) {
		$offer1 = stripslashes(check_html($capdecrementer2['offer1'], "nohtml"));
		$offer2 = stripslashes(check_html($capdecrementer2['offer2'], "nohtml"));
		$offer3 = stripslashes(check_html($capdecrementer2['offer3'], "nohtml"));
		$offer4 = stripslashes(check_html($capdecrementer2['offer4'], "nohtml"));
		$offer5 = stripslashes(check_html($capdecrementer2['offer5'], "nohtml"));
		$offer6 = stripslashes(check_html($capdecrementer2['offer6'], "nohtml"));
		$capnumber = $capnumber-$offer1;
		$capnumber2 = $capnumber2-$offer2;
		$capnumber3 = $capnumber3-$offer3;
		$capnumber4 = $capnumber4-$offer4;
		$capnumber5 = $capnumber5-$offer5;
		$capnumber6 = $capnumber6-$offer6;
		$offer1 = 0;

		$rosterspots=$rosterspots-1;
	}

	$hardcapnumber = $capnumber+2000;
	$hardcapnumber2 = $capnumber2+2000;
	$hardcapnumber3 = $capnumber3+2000;
	$hardcapnumber4 = $capnumber4+2000;
	$hardcapnumber5 = $capnumber5+2000;
	$hardcapnumber6 = $capnumber6+2000;

	// END LOOP

	$offergrabber = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_ibl_fa_offers WHERE team='$userteam' AND name='$player_name'"));

	$offer1 = stripslashes(check_html($offergrabber['offer1'], "nohtml"));
	$offer2 = stripslashes(check_html($offergrabber['offer2'], "nohtml"));
	$offer3 = stripslashes(check_html($offergrabber['offer3'], "nohtml"));
	$offer4 = stripslashes(check_html($offergrabber['offer4'], "nohtml"));
	$offer5 = stripslashes(check_html($offergrabber['offer5'], "nohtml"));
	$offer6 = stripslashes(check_html($offergrabber['offer6'], "nohtml"));

	if ($offer1 == 0) {
		$prefill1=$dem1;
		$prefill2=$dem2;
		$prefill3=$dem3;
		$prefill4=$dem4;
		$prefill5=$dem5;
		$prefill6=$dem6;
	} else {
		$prefill1=$offer1;
		$prefill2=$offer2;
		$prefill3=$offer3;
		$prefill4=$offer4;
		$prefill5=$offer5;
		$prefill6=$offer6;
	}

	if ($player_exp > 9) {
		$vetmin=103;
		$maxstartsat=1451;
	} elseif ($player_exp > 8) {
		$vetmin=100;
		$maxstartsat=1275;
	} elseif ($player_exp > 7) {
		$vetmin=89;
		$maxstartsat=1275;
	} elseif ($player_exp > 6) {
		$vetmin=82;
		$maxstartsat=1275;
	} elseif ($player_exp > 5) {
		$vetmin=76;
		$maxstartsat=1063;
	} elseif ($player_exp > 4) {
		$vetmin=70;
		$maxstartsat=1063;
	} elseif ($player_exp > 3) {
		$vetmin=64;
		$maxstartsat=1063;
	} elseif ($player_exp > 2) {
		$vetmin=61;
		$maxstartsat=1063;
	} elseif ($player_exp > 1) {
		$vetmin=51;
		$maxstartsat=1063;
	} else {
		$vetmin=35;
		$maxstartsat=1063;
	}

	// ==== CALCULATE MAX OFFER ====
	$Offer_max_increase = round($maxstartsat*0.1,0);
	$Offer_max_increase_bird = round($maxstartsat*0.125,0);

	$maxstartsat2=$maxstartsat+$Offer_max_increase;
	$maxstartsat3=$maxstartsat2+$Offer_max_increase;
	$maxstartsat4=$maxstartsat3+$Offer_max_increase;
	$maxstartsat5=$maxstartsat4+$Offer_max_increase;
	$maxstartsat6=$maxstartsat5+$Offer_max_increase;

	$maxstartsatbird2=$maxstartsat+$Offer_max_increase_bird;
	$maxstartsatbird3=$maxstartsatbird2+$Offer_max_increase_bird;
	$maxstartsatbird4=$maxstartsatbird3+$Offer_max_increase_bird;
	$maxstartsatbird5=$maxstartsatbird4+$Offer_max_increase_bird;
	$maxstartsatbird6=$maxstartsatbird5+$Offer_max_increase_bird;

	echo "<img align=left src=\"images/player/$pid.jpg\">";

	echo "Here are my demands (note these are not adjusted for your team's attributes; I will adjust the offer you make to me accordingly):";

	if ($rosterspots < 1 AND $offer1 == 0) {
		echo "<table cellspacing=0 border=1><tr><td colspan=8>Sorry, you have no roster spots remaining and cannot offer me a contract!</td>";
	} else {
		echo "<table cellspacing=0 border=1><tr><td>My demands are:</td><td>$demand_display</td></tr>

		<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
		<tr><td>Please enter your offer in this row:</td><td>";
		if ($player_exp > 0) {
			echo "<INPUT TYPE=\"text\" NAME=\"offeryear1\" SIZE=\"4\" VALUE=\"$prefill1\"></td><td>
			<INPUT TYPE=\"text\" NAME=\"offeryear2\" SIZE=\"4\" VALUE=\"$prefill2\"></td><td>
			<INPUT TYPE=\"text\" NAME=\"offeryear3\" SIZE=\"4\" VALUE=\"$prefill3\"></td><td>
			<INPUT TYPE=\"text\" NAME=\"offeryear4\" SIZE=\"4\" VALUE=\"$prefill4\"></td><td>
			<INPUT TYPE=\"text\" NAME=\"offeryear5\" SIZE=\"4\" VALUE=\"$prefill5\"></td><td>
			<INPUT TYPE=\"text\" NAME=\"offeryear6\" SIZE=\"4\" VALUE=\"$prefill6\"></td>";
		} else { // Limit undrafted rookie FA contracts to two years
			echo "<INPUT TYPE=\"text\" NAME=\"offeryear1\" SIZE=\"4\" VALUE=\"$prefill3\"></td><td>
			<INPUT TYPE=\"text\" NAME=\"offeryear2\" SIZE=\"4\" VALUE=\"$prefill4\"></td>";
		}
		$amendedCapSpaceYear1 = $capnumber + $offer1;
		echo "<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
		<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
		<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
		<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
		<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
		<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
		<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
		<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
		<input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
		<input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
		<input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
		<input type=\"hidden\" name=\"capnumber5\" value=\"$capnumber5\">
		<input type=\"hidden\" name=\"capnumber6\" value=\"$capnumber6\">
		<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
		<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
		<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
		<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
		<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
		<input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
		<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
		<input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
		<td><input type=\"submit\" value=\"Offer/Amend Free Agent Contract!\"></form></td></tr>

		<tr><td colspan=8><center><b>MAX SALARY OFFERS:</b></center></td></tr>

		<td>Max Level Contract 10%(click the button that corresponds to the final year you wish to offer):</td>

		<td>
		<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
		<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
		<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
		<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
		<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
		<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
		<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
		<input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
		<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
		<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
		<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
		<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
		<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
		<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
		<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
		<input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
		<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
		<input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
		<input type=\"submit\" value=\"$maxstartsat\"></form></td>

		<td>
		<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
		<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
		<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
		<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
		<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
		<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
		<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
		<input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
		<input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsat2\">
		<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
		<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
		<input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
		<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
		<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
		<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
		<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
		<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
		<input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
		<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
		<input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
		<input type=\"submit\" value=\"$maxstartsat2\"></form></td>";

		if ($player_exp > 0) {
			echo "<td>
			<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
			<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
			<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
			<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
			<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
			<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
			<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
			<input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsat2\">
			<input type=\"hidden\" name=\"offeryear3\" value=\"$maxstartsat3\">
			<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
			<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
			<input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
			<input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
			<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
			<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
			<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
			<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
			<input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
			<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
			<input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
			<input type=\"submit\" value=\"$maxstartsat3\"></form></td>

			<td>
			<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
			<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
			<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
			<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
			<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
			<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
			<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
			<input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsat2\">
			<input type=\"hidden\" name=\"offeryear3\" value=\"$maxstartsat3\">
			<input type=\"hidden\" name=\"offeryear4\" value=\"$maxstartsat4\">
			<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
			<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
			<input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
			<input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
			<input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
			<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
			<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
			<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
			<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
			<input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
			<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
			<input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
			<input type=\"submit\" value=\"$maxstartsat4\"></form></td>

			<td>
			<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
			<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
			<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
			<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
			<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
			<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
			<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
			<input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsat2\">
			<input type=\"hidden\" name=\"offeryear3\" value=\"$maxstartsat3\">
			<input type=\"hidden\" name=\"offeryear4\" value=\"$maxstartsat4\">
			<input type=\"hidden\" name=\"offeryear5\" value=\"$maxstartsat5\">
			<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
			<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
			<input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
			<input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
			<input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
			<input type=\"hidden\" name=\"capnumber5\" value=\"$capnumber5\">
			<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
			<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
			<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
			<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
			<input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
			<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
			<input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
			<input type=\"submit\" value=\"$maxstartsat5\"></form></td>

			<td>
			<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
			<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
			<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
			<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
			<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
			<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
			<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
			<input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsat2\">
			<input type=\"hidden\" name=\"offeryear3\" value=\"$maxstartsat3\">
			<input type=\"hidden\" name=\"offeryear4\" value=\"$maxstartsat4\">
			<input type=\"hidden\" name=\"offeryear5\" value=\"$maxstartsat5\">
			<input type=\"hidden\" name=\"offeryear6\" value=\"$maxstartsat6\">
			<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
			<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
			<input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
			<input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
			<input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
			<input type=\"hidden\" name=\"capnumber5\" value=\"$capnumber5\">
			<input type=\"hidden\" name=\"capnumber6\" value=\"$capnumber6\">
			<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
			<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
			<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
			<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
			<input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
			<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
			<input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
			<input type=\"submit\" value=\"$maxstartsat6\"></form></td>";
		} else { // Limit undrafted rookie FA contracts to two years
			echo "";
		}

		echo "<td></td></tr>";

		// ===== CHECK TO SEE IF MAX BIRD RIGHTS IS AVAILABLE =====

		if ($player_bird > 2 && $player_team_name == $userteam) {
			echo "<tr><td><b>Max Bird Level Contract 12.5%(click the button that corresponds to the final year you wish to offer):</b></td>
			<td>
			<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
			<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
			<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
			<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
			<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
			<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
			<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
			<input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
			<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
			<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
			<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
			<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
			<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
			<input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
			<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
			<input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
			<input type=\"submit\" value=\"$maxstartsat\"></form></td>

			<td>
			<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
			<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
			<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
			<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
			<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
			<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
			<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
			<input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsatbird2\">
			<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
			<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
			<input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
			<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
			<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
			<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
			<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
			<input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
			<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
			<input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
			<input type=\"submit\" value=\"$maxstartsatbird2\"></form></td>

			<td>
			<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
			<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
			<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
			<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
			<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
			<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
			<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
			<input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsatbird2\">
			<input type=\"hidden\" name=\"offeryear3\" value=\"$maxstartsatbird3\">
			<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
			<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
			<input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
			<input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
			<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
			<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
			<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
			<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
			<input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
			<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
			<input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
			<input type=\"submit\" value=\"$maxstartsatbird3\"></form></td>

			<td>
			<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
			<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
			<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
			<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
			<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
			<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
			<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
			<input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsatbird2\">
			<input type=\"hidden\" name=\"offeryear3\" value=\"$maxstartsatbird3\">
			<input type=\"hidden\" name=\"offeryear4\" value=\"$maxstartsatbird4\">
			<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
			<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
			<input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
			<input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
			<input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
			<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
			<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
			<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
			<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
			<input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
			<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
			<input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
			<input type=\"submit\" value=\"$maxstartsatbird4\"></form></td>

			<td>
			<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
			<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
			<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
			<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
			<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
			<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
			<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
			<input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsatbird2\">
			<input type=\"hidden\" name=\"offeryear3\" value=\"$maxstartsatbird3\">
			<input type=\"hidden\" name=\"offeryear4\" value=\"$maxstartsatbird4\">
			<input type=\"hidden\" name=\"offeryear5\" value=\"$maxstartsatbird5\">
			<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
			<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
			<input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
			<input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
			<input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
			<input type=\"hidden\" name=\"capnumber5\" value=\"$capnumber5\">
			<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
			<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
			<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
			<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
			<input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
			<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
			<input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
			<input type=\"submit\" value=\"$maxstartsatbird5\"></form></td>

			<td>
			<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
			<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
			<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
			<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
			<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
			<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
			<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
			<input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsatbird2\">
			<input type=\"hidden\" name=\"offeryear3\" value=\"$maxstartsatbird3\">
			<input type=\"hidden\" name=\"offeryear4\" value=\"$maxstartsatbird4\">
			<input type=\"hidden\" name=\"offeryear5\" value=\"$maxstartsatbird5\">
			<input type=\"hidden\" name=\"offeryear6\" value=\"$maxstartsatbird6\">
			<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
			<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
			<input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
			<input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
			<input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
			<input type=\"hidden\" name=\"capnumber5\" value=\"$capnumber5\">
			<input type=\"hidden\" name=\"capnumber6\" value=\"$capnumber6\">
			<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
			<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
			<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
			<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
			<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
			<input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
			<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
			<input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
			<input type=\"submit\" value=\"$maxstartsatbird6\"></form></td>";
		}

		echo "<tr><td colspan=8><center><b>SALARY CAP EXCEPTIONS:</b></center></td></tr>";

		// ===== CHECK TO SEE IF MLE IS AVAILABLE =====

		if ($HasMLE == 1) {
			$MLEoffers = $db->sql_numrows($db->sql_query("SELECT * FROM ".$prefix."_ibl_fa_offers WHERE MLE='1' AND team='$userteam'"));
			if ($MLEoffers == 0) {
				echo "<tr><td>Mid-Level Exception (click the button that corresponds to the final year you wish to offer):</td>

				<td>
				<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
				<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
				<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
				<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
				<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
				<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
				<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
				<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
				<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
				<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
				<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
				<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
				<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
				<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
				<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
				<input type=\"hidden\" name=\"MLEyrs\" value=\"1\">
				<input type=\"submit\" value=\"450\"></form></td>

				<td>
				<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
				<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
				<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
				<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
				<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
				<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
				<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
				<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
				<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
				<input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
				<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
				<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
				<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
				<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
				<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
				<input type=\"hidden\" name=\"MLEyrs\" value=\"2\">
				<input type=\"submit\" value=\"495\"></form></td>";

				if ($player_exp > 0) {
					echo "<td>
					<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
					<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
					<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
					<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
					<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
					<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
					<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
					<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
					<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
					<input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
					<input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
					<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
					<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
					<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
					<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
					<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
					<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
					<input type=\"hidden\" name=\"MLEyrs\" value=\"3\">
					<input type=\"submit\" value=\"540\"></form></td>

					<td>
					<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
					<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
					<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
					<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
					<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
					<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
					<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
					<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
					<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
					<input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
					<input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
					<input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
					<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
					<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
					<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
					<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
					<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
					<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
					<input type=\"hidden\" name=\"MLEyrs\" value=\"4\">
					<input type=\"submit\" value=\"585\"></form></td>

					<td>
					<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
					<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
					<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
					<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
					<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
					<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
					<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
					<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
					<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
					<input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
					<input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
					<input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
					<input type=\"hidden\" name=\"capnumber5\" value=\"$capnumber5\">
					<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
					<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
					<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
					<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
					<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
					<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
					<input type=\"hidden\" name=\"MLEyrs\" value=\"5\">
					<input type=\"submit\" value=\"630\"></form></td>

					<td>
					<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
					<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
					<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
					<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
					<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
					<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
					<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
					<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
					<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
					<input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
					<input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
					<input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
					<input type=\"hidden\" name=\"capnumber5\" value=\"$capnumber5\">
					<input type=\"hidden\" name=\"capnumber6\" value=\"$capnumber6\">
					<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
					<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
					<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
					<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
					<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
					<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
					<input type=\"hidden\" name=\"MLEyrs\" value=\"6\">
					<input type=\"submit\" value=\"675\"></form></td>";
				} else { // Limit undrafted rookie FA contracts to two years
					echo "";
				}

				echo "<td></td></tr>";
			}
		}
		// ===== CHECK TO SEE IF LLE IS AVAILABLE =====

		if ($HasLLE == 1) {
			$LLEoffers = $db->sql_numrows($db->sql_query("SELECT * FROM ".$prefix."_ibl_fa_offers WHERE LLE='1' AND team='$userteam'"));
			if ($LLEoffers == 0) {
				echo "<tr><td>Lower-Level Exception:</td>
				<td>
				<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
				<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
				<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
				<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
				<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
				<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
				<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
				<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
				<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
				<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
				<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
				<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
				<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
				<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
				<input type=\"hidden\" name=\"MLEyrs\" value=\"7\">
				<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
				<input type=\"submit\" value=\"145\"></form></td>
				<td colspan=6></td></tr>";
			}
		}

		// ===== VETERANS EXCEPTION (ALWAYS AVAILABLE) =====

		echo "<tr><td>Veterans Exception:</td>
		<td>
		<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
		<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
		<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
		<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
		<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
		<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
		<input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
		<input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
		<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
		<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
		<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
		<input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
		<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
		<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
		<input type=\"hidden\" name=\"MLEyrs\" value=\"8\">
		<input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
		<input type=\"submit\" value=\"$vetmin\"></form></td>
		<td colspan=6></td></tr>";
	}

	echo "
		<tr><td colspan=8><b>Notes/Reminders:</b> <ul>
		<li>The maximum contract permitted for me (based on my years of service) starts at $maxstartsat in Year 1.
		<li>You have <b>$amendedCapSpaceYear1</b> in <b>soft cap</b> space available; the amount you offer in year 1 cannot exceed this unless you are using one of the exceptions.</li>
		<li>You have <b>$capnumber2</b> in <b>soft cap</b> space available; the amount you offer in year 2 cannot exceed this unless you are using one of the exceptions.</li>
		<li>You have <b>$capnumber3</b> in <b>soft cap</b> space available; the amount you offer in year 3 cannot exceed this unless you are using one of the exceptions.</li>
		<li>You have <b>$capnumber4</b> in <b>soft cap</b> space available; the amount you offer in year 4 cannot exceed this unless you are using one of the exceptions.</li>
		<li>You have <b>$capnumber5</b> in <b>soft cap</b> space available; the amount you offer in year 5 cannot exceed this unless you are using one of the exceptions.</li>
		<li>You have <b>$capnumber6</b> in <b>soft cap</b> space available; the amount you offer in year 6 cannot exceed this unless you are using one of the exceptions.</li>
		<li>You have <b>$hardcapnumber</b> in <b>hard cap</b> space available; the amount you offer in year 1 cannot exceed this, period.</li>
		<li>You have <b>$hardcapnumber2</b> in <b>hard cap</b> space available; the amount you offer in year 2 cannot exceed this, period.</li>
		<li>You have <b>$hardcapnumber3</b> in <b>hard cap</b> space available; the amount you offer in year 3 cannot exceed this, period.</li>
		<li>You have <b>$hardcapnumber4</b> in <b>hard cap</b> space available; the amount you offer in year 4 cannot exceed this, period.</li>
		<li>You have <b>$hardcapnumber5</b> in <b>hard cap</b> space available; the amount you offer in year 5 cannot exceed this, period.</li>
		<li>You have <b>$hardcapnumber6</b> in <b>hard cap</b> space available; the amount you offer in year 6 cannot exceed this, period.</li>
		<li>Enter \"0\" for years you do not want to offer a contract.</li>
		<li>The amounts offered each year must equal or exceed the previous year.</li>
		<li>The first year of the contract must be at least the veteran's minimum ($vetmin for this player).</li>
		<li><b>For Players who do not have Bird Rights with your team:</b> You may add no more than 10% of your the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than 50 between any two subsequent years.)</li>
		<li><b>Bird Rights Player on Your Team:</b> You may add no more than 12.5% of your the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than 62 between any two subsequent years.)</li>
		<li>For reference, \"100\" entered in the fields above corresponds to 1 million dollars; the 50 million dollar soft cap thus means you have 5000 to play with.</li>
		</ul></td></tr>
		</table>

		</form>
	";

	echo "<form name=\"FAOfferDelete\" method=\"post\" action=\"freeagentofferdelete.php\">
		<input type=\"submit\" value=\"Retract All Offers to this Player!\">
		<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
		<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
		</form>";

	CloseTable();
	include("footer.php");
}
// === END OF NEGOTIATE FUNCTION ===

function positionmigration($pid) {
	global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;
	$pid = intval($pid);

	cookiedecode($user);

	$sql2 = "SELECT * FROM ".$prefix."_users WHERE username='$cookie[1]'";
	$result2 = $db->sql_query($sql2);
	$num2 = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);

	$userteam = stripslashes(check_html($userinfo['user_ibl_team'], "nohtml"));

	$playerinfo = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_iblplyr WHERE pid='$pid'"));

	$player_name = stripslashes(check_html($playerinfo['name'], "nohtml"));
	$player_base_pos = stripslashes(check_html($playerinfo['pos'], "nohtml"));
	//$player_pos = stripslashes(check_html($playerinfo['altpos'], "nohtml"));
	$player_team_name = stripslashes(check_html($playerinfo['teamname'], "nohtml"));
	$player_draftround = stripslashes(check_html($playerinfo['draftround'], "nohtml"));
	$player_exp = stripslashes(check_html($playerinfo['exp'], "nohtml"));

	if ($player_exp == 0) {
		if ($player_draftround != 0) {
			if ($player_base_pos != $player_pos) {
				$eligible_PG=0;
				$eligible_G=0;
				$eligible_SG=0;
				$eligible_GF=0;
				$eligible_SF=0;
				$eligible_F=0;
				$eligible_PF=0;
				$eligible_FC=0;
				$eligible_C=0;

				echo "<img align=left src=\"images/player/$pid.jpg\">";

				echo "You may migrate $player_name from their current position.  Once you choose a new position, it cannot be undone.
					<form name=\"Migrate\" method=\"post\" action=\"migrate.php\">
					<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
					<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
					<select name=\"NewPos\">
				";

				if ($player_base_pos == 'PG') {
					$eligible_PG=1;
					$eligible_G=1;
					$eligible_SG=1;
				} elseif ($player_base_pos == 'SG') {
					$eligible_PG=1;
					$eligible_G=1;
					$eligible_SG=1;
					$eligible_GF=1;
				} elseif ($player_base_pos == 'SF') {
					$eligible_SG=1;
					$eligible_FG=1;
					$eligible_SF=1;
					$eligible_F=1;
				} elseif ($player_base_pos == 'PF') {
					$eligible_SF=1;
					$eligible_F=1;
					$eligible_PF=1;
					$eligible_FC=1;
				} else {
					$eligible_PF=1;
					$eligible_FC=1;
					$eligible_C=1;
				}

				if ($eligible_PG == 1) echo "  <option value=\"PG\" ".($player_base_pos == 'PG' ? "SELECTED " : "").">PG</option>";
				if ($eligible_G == 1) echo "  <option value=\"G\">G</option>";
				if ($eligible_SG == 1) echo "  <option value=\"SG\" ".($player_base_pos == 'SG' ? "SELECTED " : "").">SG</option>";
				if ($eligible_GF == 1) echo "  <option value=\"GF\">GF</option>";
				if ($eligible_SF == 1) echo "  <option value=\"SF\" ".($player_base_pos == 'SF' ? "SELECTED " : "").">SF</option>";
				if ($eligible_F == 1) echo "  <option value=\"F\">F</option>";
				if ($eligible_PF == 1) echo "  <option value=\"PF\" ".($player_base_pos == 'PF' ? "SELECTED " : "").">PF</option>";
				if ($eligible_FC == 1) echo "  <option value=\"FC\">FC</option>";
				if ($eligible_C == 1) echo "  <option value=\"C\" ".($player_base_pos == 'C' ? "SELECTED " : "").">C</option>";

				echo "</select>
					<input type=\"submit\" value=\"Migrate Player!\"></form>";

			} else echo "Sorry, $player_name has already been migrated to $player_pos.  He cannot be migrated again.";
		} else echo "Sorry, $player_name was not drafted; only draft picks may be migrated.";
	} else echo "Sorry, $player_name is no longer a rookie; only rookies may be migrated.";
}

function teamdisplay($pid) {
	global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;
	$pid = intval($pid);

	cookiedecode($user);
	include("header.php");
	OpenTable();

	echo "<center><h1>Cap Space and Roster Spots for all teams</h1></center>
		<table border=1 cellspacing=0><tr bgcolor=#0000cc><td><font color=#ffffff>Team</font></td><td><font color=#ffffff>Soft Cap Space</font></td><td><font color=#ffffff>Hard Cap Space</font></td><td><font color=#ffffff>Roster Slots</font></td><td><font color=#ffffff>MLE</font></td><td><font color=#ffffff>LLE</font></td></tr>";

	$showcapteam = $db->sql_query("SELECT * FROM ".$prefix."_ibl_team_info WHERE teamid>'0' ORDER BY teamid ASC");

	while ($teamcaplist = $db->sql_fetchrow($showcapteam)) {
		$currentSeasonEndingYear = Shared::getCurrentSeasonEndingYear();

		$capteam = stripslashes(check_html($teamcaplist['team_name'], "nohtml"));
		$HasMLE = stripslashes(check_html($teamcaplist['HasMLE'], "nohtml"));
		$HasLLE = stripslashes(check_html($teamcaplist['HasLLE'], "nohtml"));
		$conttot1=0;
		$conttot2=0;
		$conttot3=0;
		$conttot4=0;
		$conttot5=0;
		$conttot6=0;
		$rosterspots=15;

		// ==== NOTE PLAYERS CURRENTLY UNDER CONTRACT FOR TEAM

		$showteam = $db->sql_query("SELECT * FROM ".$prefix."_iblplyr WHERE teamname='$capteam' AND retired='0' ORDER BY ordinal ASC");

		while ($teamlist = $db->sql_fetchrow($showteam)) {
			$ordinal = stripslashes(check_html($teamlist['ordinal'], "nohtml"));
			$draftyear = stripslashes(check_html($teamlist['draftyear'], "nohtml"));
			$exp = stripslashes(check_html($teamlist['exp'], "nohtml"));
			$cy = stripslashes(check_html($teamlist['cy'], "nohtml"));
			$cyt = stripslashes(check_html($teamlist['cyt'], "nohtml"));

			$yearoffreeagency=$draftyear+$exp+$cyt-$cy;

			if ($yearoffreeagency != $currentSeasonEndingYear) {
				// === MATCH UP CONTRACT AMOUNTS WITH FUTURE YEARS BASED ON CURRENT YEAR OF CONTRACT

				$millionscy = stripslashes(check_html($teamlist['cy'], "nohtml"));
				$millionscy1 = stripslashes(check_html($teamlist['cy1'], "nohtml"));
				$millionscy2 = stripslashes(check_html($teamlist['cy2'], "nohtml"));
				$millionscy3 = stripslashes(check_html($teamlist['cy3'], "nohtml"));
				$millionscy4 = stripslashes(check_html($teamlist['cy4'], "nohtml"));
				$millionscy5 = stripslashes(check_html($teamlist['cy5'], "nohtml"));
				$millionscy6 = stripslashes(check_html($teamlist['cy6'], "nohtml"));

				$contract1 = 0;
				$contract2 = 0;
				$contract3 = 0;
				$contract4 = 0;
				$contract5 = 0;
				$contract6 = 0;

				if ($millionscy == 0) {
					$contract1 = $millionscy1;
					$contract2 = $millionscy2;
					$contract3 = $millionscy3;
					$contract4 = $millionscy4;
					$contract5 = $millionscy5;
					$contract6 = $millionscy6;
				}
				if ($millionscy == 1) {
					$contract1 = $millionscy2;
					$contract2 = $millionscy3;
					$contract3 = $millionscy4;
					$contract4 = $millionscy5;
					$contract5 = $millionscy6;
				}
				if ($millionscy == 2) {
					$contract1 = $millionscy3;
					$contract2 = $millionscy4;
					$contract3 = $millionscy5;
					$contract4 = $millionscy6;
				}
				if ($millionscy == 3) {
					$contract1 = $millionscy4;
					$contract2 = $millionscy5;
					$contract3 = $millionscy6;
				}
				if ($millionscy == 4) {
					$contract1 = $millionscy5;
					$contract2 = $millionscy6;
				}
				if ($millionscy == 5) {
					$contract1 = $millionscy6;
				}

				$conttot1=$conttot1+$contract1;
				$conttot2=$conttot2+$contract2;
				$conttot3=$conttot3+$contract3;
				$conttot4=$conttot4+$contract4;
				$conttot5=$conttot5+$contract5;
				$conttot6=$conttot6+$contract6;

				if ($ordinal <= 960) {
					$rosterspots -= 1;
				}
			}
		}
		// ==== END LIST OF PLAYERS CURRENTLY UNDER CONTRACT

		$softcap = 5000 - $conttot1;
		$hardcap = 7000 - $conttot1;

		echo "<tr><td>$capteam</td><td>$softcap</td><td>$hardcap</td><td>$rosterspots</td>";

		echo "<td>".($HasMLE==1 ? "MLE" : "")."</td>";
		echo "<td>".($HasLLE==1 ? "LLE" : "")."</td>";

		echo "</tr>";
	}

	echo "</table>";

	CloseTable();
	include("footer.php");
}

switch($pa) {
	case "display":
	display(1);
	break;

	case "teamdisplay":
	teamdisplay(1);
	break;

	case "negotiate":
	negotiate($pid);
	break;

	case "positionmigration":
	positionmigration($pid);
	break;

	default:
	main($user);
	break;
}

?>
