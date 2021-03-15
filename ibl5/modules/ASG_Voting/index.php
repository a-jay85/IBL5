
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
/*                                                                      */
/* ibl College Scout Module added by Spencer Cooley                    */
/* 2/2/2005                                                             */
/*                                                                      */
/************************************************************************/

if (!eregi("modules.php", $_SERVER['PHP_SELF'])) {
	die ("You can't access this file directly...");
}

require_once("mainfile.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/sharedFunctions.php';
$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$userpage = 1;

//include("modules/$module_name/navbar.php");

function userinfo($username, $bypass = 0, $hid = 0, $url = 0) {
    global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $useset, $subscription_url;
    $sql = "SELECT * FROM " . $prefix . "_bbconfig";
    $result = $db->sql_query($sql);
    while ($row = $db->sql_fetchrow($result)) {
    	$board_config[$row['config_name']] = $row['config_value'];
    }
    $sql2 = "SELECT * FROM " . $user_prefix . "_users WHERE username = '$username'";
    $result2 = $db->sql_query($sql2);
    $num = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);
    if (!$bypass) {
		cookiedecode($user);
	}
    include("header.php");

// === CODE TO INSERT IBL DEPTH CHART ===

    OpenTable();

	$easternConferenceTids = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 25);
	$westernConferenceTids = array(13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 26);

	function formatTidsForSqlQuery($conferenceTids) {
		$tidsFormattedForQuery = join("','",$conferenceTids);
		return $tidsFormattedForQuery;
	}

	function getAllStarCandidates($positions, $conferenceTids, $votingCategory) {
		$query = "SELECT *
			FROM nuke_iblplyr
			WHERE pos IN ($positions)
				AND tid IN ('" . formatTidsForSqlQuery($conferenceTids) . "')
				AND retired != 1
				AND stats_gm > '14'
			ORDER BY name";
		$result = mysql_query($query);

		echo "<SCRIPT>
			function ShowAndHide$votingCategory() {
			    var x = document.getElementById('$votingCategory');
			    if (x.style.display == 'none') {
			        x.style.display = 'block';
			    } else {
			        x.style.display = 'none';
			    }
			}
		</SCRIPT>";

		$output = "<table id=\"$votingCategory\" style=\"display:none\" class=\"sortable\">
			<tbody>
				<tr>
					<th>Vote</th>
					<th>Name</th>
					<th>gm</th>
					<th>gs</th>
					<th>min</th>
					<th>fgp</th>
					<th>ftp</th>
					<th>3gp</th>
					<th>reb</th>
					<th>ast</th>
					<th>stl</th>
					<th>to</th>
					<th>blk</th>
					<th>pf</th>
					<th>pts</th>
				</tr>";

		$i = 0;
		while ($row = mysql_fetch_assoc($result)) {
			$name = $row['name'];
			$teamname = $row['teamname'];
			$gm = $row['stats_gm'];
			$gs = $row['stats_gs'];
			$mpg = number_format(($row['stats_min'] / $row['stats_gm']), 1);
			$fgp = number_format(($row['stats_fgm'] / $row['stats_fga']), 3);
			$ftp = number_format(($row['stats_ftm'] / $row['stats_fta']), 3);
			$tpp = number_format(($row['stats_3gm'] / $row['stats_3ga']), 3);
			$rpg = number_format((($row['stats_orb'] + $row['stats_drb']) / $row['stats_gm']), 1);
			$apg = number_format(($row['stats_ast'] / $row['stats_gm']), 1);
			$spg = number_format(($row['stats_stl'] / $row['stats_gm']), 1);
			$tpg = number_format(($row['stats_to'] / $row['stats_gm']), 1);
			$bpg = number_format(($row['stats_blk'] / $row['stats_gm']), 1);
			$pfg = number_format(($row['stats_pf'] / $row['stats_gm']), 1);
			$ppg = number_format(((($row['stats_3gm'] * 3) + (($row['stats_fgm'] - $row['stats_3gm']) * 2) + $row['stats_ftm']) / $row['stats_gm']), 1);

			(($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";

			$output .= "<tr bgcolor=$bgcolor>
					<td><center><input type=\"checkbox\" name=\"" . $votingCategory . "[]\" value=\"$name, $teamname\"></center></td>
					<td>$name, $teamname</td>
					<td>$gm</td>
					<td>$gs</td>
					<td>$mpg</td>
					<td>$fgp</td>
					<td>$ftp</td>
					<td>$tpp</td>
					<td>$rpg</td>
					<td>$apg</td>
					<td>$spg</td>
					<td>$tpg</td>
					<td>$bpg</td>
					<td>$pfg</td>
					<td>$ppg</td>
				</tr>";
			$i++;
		}

		$output .= "</tbody>
			</table><br><br>";

		return $output;
	}

    $teamlogo = $userinfo[user_ibl_team];
	$tid = getTidFromTeamname($teamlogo);

	echo "<form name=\"ASGVote\" method=\"post\" action=\"ASGVote.php\">
		<center>
			<img src=\"images/logo/$tid.jpg\"><br><br>";

	echo "<input type=\"submit\" value=\"Submit Votes!\">";

	$easternConferenceCenters .= getAllStarCandidates("'C'", $easternConferenceTids, 'ECC');
	$easternConferenceForwards .= getAllStarCandidates("'SF', 'PF'", $easternConferenceTids, 'ECF');
	$easternConferenceGuards .= getAllStarCandidates("'PG', 'SG'", $easternConferenceTids, 'ECG');

	$westernConferenceCenters .= getAllStarCandidates("'C'", $westernConferenceTids, 'WCC');
	$westernConferenceForwards .= getAllStarCandidates("'SF', 'PF'", $westernConferenceTids, 'WCF');
	$westernConferenceGuards .= getAllStarCandidates("'PG', 'SG'", $westernConferenceTids, 'WCG');

	echo "<div onclick=\"ShowAndHideECC()\">
			<h2>Select ONE Eastern Conference Center:</h2>
			<i>Tap/click here to reveal/hide nominees</i>
		</div>
		$easternConferenceCenters

		<div onclick=\"ShowAndHideECF()\">
			<h2>Select TWO Eastern Conference Forwards:</h2>
			<i>Tap/click here to reveal/hide nominees</i>
		</div>
		$easternConferenceForwards

		<div onclick=\"ShowAndHideECG()\">
			<h2>Select TWO Eastern Conference Guards:</h2>
			<i>Tap/click here to reveal/hide nominees</i>
		</div>
		$easternConferenceGuards

		<div onclick=\"ShowAndHideWCC()\">
			<h2>Select ONE Western Conference Center:</h2>
			<i>Tap/click here to reveal/hide nominees</i>
		</div>
		$westernConferenceCenters

		<div onclick=\"ShowAndHideWCF()\">
			<h2>Select TWO Western Conference Forwards:</h2>
			<i>Tap/click here to reveal/hide nominees</i>
		</div>
		$westernConferenceForwards

		<div onclick=\"ShowAndHideWCG()\">
			<h2>Select TWO Western Conference Guards:</h2>
			<i>Tap/click here to reveal/hide nominees</i>
		</div>
		$westernConferenceGuards

		<input type=\"hidden\" name=\"teamname\" value=\"$teamlogo\">

		<input type=\"submit\" value=\"Submit Votes!\">
	</center>
	</form>";

    CloseTable();

    include("footer.php");
}

function main($user) {
    global $stop, $module_name, $redirect, $mode, $t, $f, $gfx_chk;
    if (!is_user($user)) {
		include("header.php");
		if ($stop) {
		    OpenTable();
		    echo "<center><font class=\"title\"><b>" . _LOGININCOR . "</b></font></center>\n";
		    CloseTable();
		    echo "<br>\n";
		} else {
		    OpenTable();
		    echo "<center><font class=\"title\"><b>" . _USERREGLOGIN . "</b></font></center>\n";
		    CloseTable();
		    echo "<br>\n";
		}
		if (!is_user($user)) {
		    OpenTable();
		    mt_srand ((double)microtime() * 1000000);
		    $maxran = 1000000;
		    $random_num = mt_rand(0, $maxran);
		    echo "<form action=\"modules.php?name=$module_name\" method=\"post\">\n"
			."<b>" . _USERLOGIN . "</b><br><br>\n"
			."<table border=\"0\"><tr><td>\n"
			."" . _NICKNAME . ":</td><td><input type=\"text\" name=\"username\" size=\"15\" maxlength=\"25\"></td></tr>\n"
			."<tr><td>" . _PASSWORD . ":</td><td><input type=\"password\" name=\"user_password\" size=\"15\" maxlength=\"20\"></td></tr>\n";
		    if (extension_loaded("gd") AND ($gfx_chk == 2 OR $gfx_chk == 4 OR $gfx_chk == 5 OR $gfx_chk == 7)) {
				echo "<tr><td colspan='2'>"._SECURITYCODE.": <img src='modules.php?name=$module_name&op=gfx&random_num=$random_num' border='1' alt='"._SECURITYCODE."' title='"._SECURITYCODE."'></td></tr>\n"
				    ."<tr><td colspan='2'>"._TYPESECCODE.": <input type=\"text\" NAME=\"gfx_check\" SIZE=\"7\" MAXLENGTH=\"6\"></td></tr>\n"
				    ."<input type=\"hidden\" name=\"random_num\" value=\"$random_num\">\n";
		    }
		    echo "</table><input type=\"hidden\" name=\"redirect\" value=$redirect>\n"
				."<input type=\"hidden\" name=\"mode\" value=$mode>\n"
				."<input type=\"hidden\" name=\"f\" value=$f>\n"
				."<input type=\"hidden\" name=\"t\" value=$t>\n"
				."<input type=\"hidden\" name=\"op\" value=\"login\">\n"
				."<input type=\"submit\" value=\""._LOGIN."\"></form><br>\n\n"
				."<center><font class=\"content\">[ <a href=\"modules.php?name=$module_name&amp;op=pass_lost\">"._PASSWORDLOST."</a> | <a href=\"modules.php?name=$module_name&amp;op=new_user\">"._REGNEWUSER."</a> ]</font></center>\n";
		    CloseTable();
		}
		include("footer.php");
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        userinfo($cookie[1]);
    }
}

switch($op) {
    default:
	main($user);
	break;
}

?>
