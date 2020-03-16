<?php

if (!eregi("modules.php", $_SERVER['PHP_SELF'])) {
	die ("You can't access this file directly...");
}

require_once("mainfile.php");
$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Team Pages";

function menu()
{
	global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;
	$tid = intval($tid);

	include("header.php");
	OpenTable();

	displaytopmenu($tid);

	CloseTable();
	include("footer.php");
}

function tradeoffer($username, $bypass=0, $hid=0, $url=0)
{
	global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $subscription_url, $partner;
	$sql = "SELECT * FROM ".$prefix."_bbconfig";
	$result = $db->sql_query($sql);
	while ( $row = $db->sql_fetchrow($result) ) {
		$board_config[$row['config_name']] = $row['config_value'];
	}

	$sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
	$result2 = $db->sql_query($sql2);
	$num = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);
	if(!$bypass) cookiedecode($user);

	include("header.php");

	$query="SELECT * FROM nuke_ibl_settings WHERE name = 'Current Season Ending Year' ";
	$result=mysql_query($query);
	$current_ibl_season=mysql_result($result,0,"value");

	OpenTable();

	$teamlogo = $userinfo[user_ibl_team];
	$tid = mysql_result(mysql_query("SELECT * FROM nuke_ibl_team_info WHERE team_name = '$teamlogo' LIMIT 1"), 0, "teamid");

	displaytopmenu($tid);

	$sql7 = "SELECT * FROM nuke_ibl_team_info ORDER BY teamid ASC ";
	$result7 = $db->sql_query($sql7);

	$sql8 = "SELECT * FROM nuke_iblplyr WHERE teamname='$userinfo[user_ibl_team]' AND retired = '0' ORDER BY ordinal ASC ";
	$result8 = $db->sql_query($sql8);
	$sql8a = "SELECT * FROM ibl_draft_picks WHERE ownerofpick='$userinfo[user_ibl_team]' ORDER BY year,round ASC ";
	$result8a = $db->sql_query($sql8a);

	echo "<hr>
		<form name=\"Trade_Offer\" method=\"post\" action=\"./maketradeoffer.php\">
		<input type=\"hidden\" name=\"Team_Name\" value=\"$teamlogo\">
		<center><img src=\"images/logo/$tid.jpg\"><br><table border=1 cellspacing=0 cellpadding=0><tr><th colspan=4><center>TRADING MENU</center></th></tr><tr><td valign=top>
		<table cellspacing=3>
		<tr><td valign=top colspan=4><center><B><u>$userinfo[user_ibl_team]</u></b></center></td></tr><tr><td valign=top><b>Select</td><td valign=top><b>Pos</td><td valign=top><b>Name</td><td valign=top><b>Salary</td>";

	$k=0;
	$total_salary_teama = 0;
	while($row8 = $db->sql_fetchrow($result8)) {
		$player_pos = $row8[pos];
		$player_name = $row8[name];
		$player_pid = $row8[pid];
		$contract_year = $row8[cy];
		$bird_years = $row8[bird];
		$player_contract = $row8["cy$contract_year"];

		//ARRAY TO BUILD FUTURE SALARY
		$i=$contract_year;
		$z=0;
		while ($i < 7) {
			$future_salary_array['player'][$z]=$future_salary_array['player'][$z]+$row8["cy$i"];
			if ($row8["cy$i"]>0) {
				$future_salary_array['hold'][$z]=$future_salary_array['hold'][$z]+1;
			}
			$i++;
			$z++;
		}

		//END OF ARRAY

		echo "<input type=\"hidden\" name=\"index$k\" value=\"$player_pid\"><input type=\"hidden\" name=\"contract$k\" value=\"$player_contract\"><input type=\"hidden\" name=\"type$k\" value=\"1\">";
		if ($bird_years > -1) {
			echo"<tr><td wdith=20><input type=\"checkbox\" name=\"check$k\"></td> <td>$player_pos</td> <td>$player_name</td><td>$player_contract</td></tr>";
		} else {
			echo"<tr><td>$player_pos</td> <td>$player_name</td><td>$player_contract</td></tr>";
		}
		$k++;
	}

	while($row8a = $db->sql_fetchrow($result8a)) {
		$pick_year = $row8a[year];
		$pick_team = $row8a[teampick];
		$pick_round = $row8a[round];
		$pick_id = $row8a[pickid];
		$y=$pick_year-$current_ibl_season+1;
		if ($pick_round==1) {
			$future_salary_array['picks'][$y]=$future_salary_array['picks'][$y]+75;
			$future_salary_array['hold'][$y]=$future_salary_array['hold'][$y]+1;
			//$future_salary_array[$y]=$future_salary_array[$y]+321;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
			$y=$y+1;
			$future_salary_array['picks'][$y]=$future_salary_array['picks'][$y]+75;
			$future_salary_array['hold'][$y]=$future_salary_array['hold'][$y]+1;
			//$future_salary_array[$y]=$future_salary_array[$y]+345;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
			$y=$y+1;
			$future_salary_array['picks'][$y]=$future_salary_array['picks'][$y]+75;
			$future_salary_array['hold'][$y]=$future_salary_array['hold'][$y]+1;
			//$future_salary_array[$y]=$future_salary_array[$y]+369;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
		} else {
			$future_salary_array['picks'][$y]=$future_salary_array['picks'][$y]+75;
			$future_salary_array['hold'][$y]=$future_salary_array['hold'][$y]+1;
			//$future_salary_array[$y]=$future_salary_array[$y]+35;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
			$y=$y+1;
			$future_salary_array['picks'][$y]=$future_salary_array['picks'][$y]+75;
			$future_salary_array['hold'][$y]=$future_salary_array['hold'][$y]+1;
			//$future_salary_array[$y]=$future_salary_array[$y]+51;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
		}

		echo "<tr><td wdith=20><input type=\"hidden\" name=\"index$k\" value=\"$pick_id\"><input type=\"hidden\" name=\"type$k\" value=\"0\"><input type=\"checkbox\" name=\"check$k\"></td> <td colspan=3>$pick_year $pick_team Round $pick_round</td></tr>";
		$k++;
	}

	echo "</table></td><td valign=top><table cellspacing=3><tr><td valign=top colspan=4><input type=\"hidden\" name=\"half\" value=\"$k\"><input type=\"hidden\" name=\"Team_Name2\" value=\"$partner\">
	<center><B><U>$partner</U></B></center></td></tr><tr><td valign=top><b>Select</td><td valign=top><b>Pos</td><td valign=top><b>Name</td><td valign=top><b>Salary</td>";

	$sql9 = "SELECT * FROM nuke_iblplyr WHERE teamname='$partner' AND retired = '0' ORDER BY ordinal ASC ";
	$result9 = $db->sql_query($sql9);
	$sql9a = "SELECT * FROM ibl_draft_picks WHERE ownerofpick='$partner' ORDER BY year,round ASC ";

	$result9a = $db->sql_query($sql9a);
	$total_salary_teamb = 0;
	$roster_hold_teamb = (15 - mysql_numrows($result9)) * 75;
	while($row9 = $db->sql_fetchrow($result9)) {
		$player_pos = $row9[pos];
		$player_name = $row9[name];
		$player_pid = $row9[pid];
		$contract_year = $row9[cy];
		$bird_years = $row9[bird];
		$player_contract = $row9["cy$contract_year"];

		//ARRAY TO BUILD FUTURE SALARY
		$i=$contract_year;
		$z=0;
		while ($i < 7) {
			//$future_salary_arrayb[$z]=$future_salary_arrayb[$z]+$row9["cy$i"];
			$future_salary_arrayb['player'][$z]=$future_salary_arrayb['player'][$z]+$row9["cy$i"];
			if ($row9["cy$i"]>0) {
				//$future_roster_sportsb[$z]=$future_roster_sportsb[$z]+1;
				$future_salary_arrayb['hold'][$z]=$future_salary_arrayb['hold'][$z]+1;
			}
			$i++;
			$z++;
		}

		//END OF ARRAY

		echo "<input type=\"hidden\" name=\"index$k\" value=\"$player_pid\"><input type=\"hidden\" name=\"contract$k\" value=\"$player_contract\"><input type=\"hidden\" name=\"type$k\" value=\"1\">";
		if ($bird_years > -1) {
			echo"<tr><td wdith=20><input type=\"checkbox\" name=\"check$k\"></td> <td>$player_pos</td> <td>$player_name</td><td>$player_contract</td></tr>";
		} else {
			echo"<tr><td>$player_pos</td> <td>$player_name</td><td>$player_contract</td></tr>";
		}
	$k++;
	}

	while($row9a = $db->sql_fetchrow($result9a)) {
		$pick_year = $row9a[year];
		$pick_team = $row9a[teampick];
		$pick_round = $row9a[round];
		$pick_id = $row9a[pickid];

		$y=$pick_year-$current_ibl_season+1;
		if ($pick_round==1) {
			$future_salary_arrayb['picks'][$y]=$future_salary_arrayb['picks'][$y]+75;
			$future_salary_arrayb['hold'][$y]=$future_salary_arrayb['hold'][$y]+1;
			//$future_salary_array[$y]=$future_salary_array[$y]+321;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
			$y=$y+1;
			$future_salary_arrayb['picks'][$y]=$future_salary_arrayb['picks'][$y]+75;
			$future_salary_arrayb['hold'][$y]=$future_salary_arrayb['hold'][$y]+1;
			//$future_salary_array[$y]=$future_salary_array[$y]+345;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
			$y=$y+1;
			$future_salary_arrayb['picks'][$y]=$future_salary_arrayb['picks'][$y]+75;
			$future_salary_arrayb['hold'][$y]=$future_salary_arrayb['hold'][$y]+1;
			//$future_salary_array[$y]=$future_salary_array[$y]+369;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
		} else {
			$future_salary_arrayb['picks'][$y]=$future_salary_arrayb['picks'][$y]+75;
			$future_salary_arrayb['hold'][$y]=$future_salary_arrayb['hold'][$y]+1;
			//$future_salary_array[$y]=$future_salary_array[$y]+35;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
			$y=$y+1;
			$future_salary_arrayb['picks'][$y]=$future_salary_arrayb['picks'][$y]+75;
			$future_salary_arrayb['hold'][$y]=$future_salary_arrayb['hold'][$y]+1;
			//$future_salary_array[$y]=$future_salary_array[$y]+51;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
		}

		echo "<tr><td wdith=20><input type=\"hidden\" name=\"index$k\" value=\"$pick_id\"><input type=\"hidden\" name=\"type$k\" value=\"0\"><input type=\"checkbox\" name=\"check$k\"></td> <td colspan=3>$pick_year $pick_team Round $pick_round</td></tr>";
		$k++;
	}

	$k--;
	echo "</table></td><td valign=top><table><tr><input type=\"hidden\" name=\"counterfields\" value=\"$k\"><td valign=top><center><b><u>Make Trade Offer To...</u></b></center>";

	while($row7 = $db->sql_fetchrow($result7)) {
		$team_name = $row7[team_name];
		$team_city = $row7[team_city];
		$team_id = $row7[teamid];

		//-------Deadline Code---------
		echo "<a href=\"./modules.php?name=Trading&op=offertrade&partner=$team_name\">$team_city $team_name</a><br>";
	}

	echo "</td></tr></table>";
	$z=0;
	while ($z<6) {
		$pass_future_salary_player[$z]=$pass_future_salary_array[$z]+$future_salary_array['player'][$z];
		$pass_future_salary_hold[$z]=$pass_future_salary_array[$z]+$future_salary_array['hold'][$z];
		$pass_future_salary_picks[$z]=$pass_future_salary_array[$z]+$future_salary_array['picks'][$z];
		$pass_future_salary_playerb[$z]=$pass_future_salary_arrayb[$z];
		$pass_future_salary_holdb[$z]=$pass_future_salary_arrayb[$z]+$future_salary_arrayb['hold'][$z];
		$pass_future_salary_picksb[$z]=$pass_future_salary_arrayb[$z]+$future_salary_arrayb['picks'][$z];
		$future_salary_array['player'][$z]=$future_salary_array['player'][$z];
		$future_salary_arrayb['player'][$z]=$future_salary_arrayb['player'][$z];
		echo "<tr><td><b>
			Total Year: $current_ibl_season:
			Salary: $".$future_salary_array['player'][$z]."</td>";
		echo "<td><b>
			Salary: $".$future_salary_arrayb['player'][$z]."</td>";
			$current_ibl_season=$current_ibl_season+1;
		$z++;
	}

	$pass_future_salary_player = implode(",", $pass_future_salary_player);
	$pass_future_salary_hold = implode(",", $pass_future_salary_hold);
	$pass_future_salary_picks = implode(",", $pass_future_salary_picks);
	$pass_future_salary_playerb = implode(",", $pass_future_salary_playerb);
	$pass_future_salary_holdb = implode(",", $pass_future_salary_holdb);
	$pass_future_salary_picksb = implode(",", $pass_future_salary_picksb);
	echo "<input type=\"hidden\" name=\"pass_future_salary_player\" value=\"".htmlspecialchars($pass_future_salary_player)."\">
		<input type=\"hidden\" name=\"pass_future_salary_hold\" value=\"".htmlspecialchars($pass_future_salary_hold)."\">
		<input type=\"hidden\" name=\"pass_future_salary_picks\" value=\"".htmlspecialchars($pass_future_salary_picks)."\">
		<input type=\"hidden\" name=\"pass_future_salary_playerb\" value=\"".htmlspecialchars($pass_future_salary_playerb)."\">
		<input type=\"hidden\" name=\"pass_future_salary_holdb\" value=\"".htmlspecialchars($pass_future_salary_holdb)."\">
		<input type=\"hidden\" name=\"pass_future_salary_picksb\" value=\"".htmlspecialchars($pass_future_salary_picksb)."\">
		<tr><td colspan=3><center>
		<input type=\"submit\" value=\"Make Trade Offer\"></td></tr></form></center></table></td></tr></table></center>";

	CloseTable();

	include("footer.php");
}

function tradereview($username, $bypass=0, $hid=0, $url=0)
{
	global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $subscription_url, $attrib, $step, $player;
	$sql = "SELECT * FROM ".$prefix."_bbconfig";
	$result = $db->sql_query($sql);
	while ( $row = $db->sql_fetchrow($result) ) {
		$board_config[$row['config_name']] = $row['config_value'];
	}

	// ==== PICKUP LOGGED-IN USER INFO

	$sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
	$result2 = $db->sql_query($sql2);
	$num = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);
	if(!$bypass) cookiedecode($user);

	// ===== END OF INFO PICKUP

	include("header.php");

	OpenTable();

	$teamlogo = $userinfo[user_ibl_team];

	$tid = mysql_result(mysql_query("SELECT * FROM nuke_ibl_team_info WHERE team_name = '$teamlogo' LIMIT 1"), 0, "teamid");

	displaytopmenu($tid);

	// BOOKMARK = NEW TRADING AREA

	include("capandtrade.php");
	include("teamcap.php");

	$get_action=$_POST["action"];
	$get_team_from=$_POST["team_from"];
	$get_team_to=$_POST["team_to"];
	$get_trade_id=$_POST["trade_id"];
	$get_item_id=$_POST["item_id"];
	$get_item_type=$_POST["item_type"];

	if ($get_trade_id != 0) {
		// DISPLAY TRADE CURRENTLY REVIEWING
		$querytext="SELECT * FROM ibl_trade_approval WHERE trade_id = '$get_trade_id' AND team_id = '$tid' ";
		$mine_result=mysql_query($querytext);
		if (mysql_num_rows($mine_result) > 0) {
			// THIS PLAYER IS INVOLVED IN THE TRADE. ALLOW HIM TO VIEW IT.
			$result_teams=mysql_query("SELECT * FROM ibl_trade_approval WHERE trade_id = '$get_trade_id' ");
			$num_teams=mysql_num_rows($result_teams);
			for ($l=0;$l<$num_teams;$l++) {
				$this_team_id=mysql_result($result_teams,$l,"team_id");
				$this_team_approved=mysql_result($result_teams,$l,"approval");
			}
		}
	}

	// GET LIST OF OTHER TEAMS - NEW OFFERS

	$sql7 = "SELECT * FROM nuke_ibl_team_info WHERE teamid != '$tid' AND teamid != 0 ORDER BY team_name ASC ";
	$result7 = $db->sql_query($sql7);

	while($row7 = $db->sql_fetchrow($result7)) {
		$team_name = $row7[team_name];
		$team_city = $row7[team_city];
		$team_id = $row7[teamid];
		$team_dropdown=$team_dropdown."<option value=\"teamid\">$team_name</option>";
	}

	$new_trade_form="<form name=\"New Trade\" method=\"post\" action=\"\"><input type=\"hidden\" name=\"action\" value=\"new trade\"><input type=\"hidden\" name=\"team_from\" value=\"$tid\"><input type=\"submit\" value=\"Propose New Trade to...\"><select name=\"team_to\">$team_dropdown</select></form>";

	// END BOOKMARK

	echo "<hr><center><img src=\"images/logo/$tid.jpg\"><br>";

	$sql3 = "SELECT * FROM nuke_ibl_trade_info ORDER BY tradeofferid ASC";
	$result3 = $db->sql_query($sql3);
	$num3 = $db->sql_numrows($result3);

	$tradeworkingonnow=0;

	echo "<table><th><tr><td valign=top>REVIEW TRADE OFFERS";

	while($row3 = $db->sql_fetchrow($result3)) {
		$isinvolvedintrade=0;
		$hashammer=0;
		$offerid = $row3[tradeofferid];
		$itemid = $row3[itemid];

		// For itemtype (1 = player, 0 = pick)

		$itemtype = $row3[itemtype];
		$from = $row3[from];
		$to = $row3[to];
		$approval = $row3[approval];

		if ($from == $teamlogo) {
			$isinvolvedintrade=1;
		}
		if ($to == $teamlogo) {
			$isinvolvedintrade=1;
		}
		if ($approval == $teamlogo) {
			$hashammer=1;
		}

		if ($isinvolvedintrade == 1) {
			if ($offerid == $tradeworkingonnow) {
			} else {
				echo "</td></tr></th></table><table border=1 valign=top align=center><tr><td><b><u>TRADE OFFER</u></b><br><table align=right border=1 cellspacing=0 cellpadding=0><tr><td valign=center>";
				if ($hashammer == 1) {
					echo "<form name=\"tradeaccept\" method=\"post\" action=\"accepttradeoffer.php\"><input type=\"hidden\" name=\"offer\" value=\"$offerid\"><input type=\"submit\" value=\"Accept\"></form>";
				} else {
					echo "(Awaiting Approval)";
				}
				echo "</td><td valign=center><form name=\"tradereject\" method=\"post\" action=\"rejecttradeoffer.php\"><input type=\"hidden\" name=\"offer\" value=\"$offerid\"><input type=\"submit\" value=\"Reject\"></form></td></tr></table>";
			}

			if ($itemtype == 0) {
				$sqlgetpick = "SELECT * FROM ibl_draft_picks WHERE pickid = '$itemid'";
				$resultgetpick = $db->sql_query($sqlgetpick);
				$numsgetpick = $db->sql_numrows($resultsgetpick);
				$rowsgetpick = $db->sql_fetchrow($resultgetpick);

				$pickteam = $rowsgetpick[teampick];
				$pickyear = $rowsgetpick[year];
				$pickround = $rowsgetpick[round];

				echo "The $from send the $pickteam $pickyear Round $pickround draft pick to the $to.<br>";
			} else {
				$sqlgetplyr = "SELECT * FROM nuke_iblplyr WHERE pid = '$itemid'";
				$resultgetplyr = $db->sql_query($sqlgetplyr);
				$numsgetplyr = $db->sql_numrows($resultsgetplyr);
				$rowsgetplyr = $db->sql_fetchrow($resultgetplyr);

				$plyrname = $rowsgetplyr[name];
				$plyrpos = $rowsgetplyr[pos];

				echo "The $from send $plyrpos $plyrname to the $to.<br>";
			}
			$tradeworkingonnow=$offerid;
		}
	}

	echo "</td><td valign=top><center><b><u>Make Trade Offer To...</u></b></center>";

	$sql7 = "SELECT * FROM nuke_ibl_team_info ORDER BY team_name ASC ";
	$result7 = $db->sql_query($sql7);

	while($row7 = $db->sql_fetchrow($result7)) {
		$team_name = $row7[team_name];
		$team_city = $row7[team_city];
		$team_id = $row7[teamid];

		if ($team_name == 'Free Agents') {
		} else {
			//------Trade Deadline Code---------
			echo "<a href=\"./modules.php?name=Trading&op=offertrade&partner=$team_name\">$team_city $team_name</a><br>";
		}
	}
	/* -----NOSY'S NEW CODE FOR MULTI-TEAM TRADES-----
	echo "</td><td bgcolor=#bbbbbb>ALPHA TESTING - NEW TRADE AREA - DO NOT USE YET! $new_trade_form </td></tr>
	<tr><td colspan=2>
	<a href=\"./modules.php?name=Waivers&action=drop\">Drop a player to Waivers</a>
	<br>
	<a href=\"./modules.php?name=Waivers&action=add\">Add a player from Waivers</a>
	<br>
	</td></tr></table>";
	*/

	echo "</td></tr><tr><td colspan=2><center>
		<a href=\"./modules.php?name=Waivers&action=drop\">Drop a player to Waivers</a>
		<br>
		<a href=\"./modules.php?name=Waivers&action=add\">Add a player from Waivers</a>
		<br>
		</center></td></tr></table>";

	CloseTable();
	include("footer.php");
}

function reviewtrade($user)
{
	global $stop, $module_name, $redirect, $mode, $t, $f, $gfx_chk;
	if(!is_user($user)) {
		include("header.php");
		if ($stop) {
			OpenTable();
			echo "<center><font class=\"title\"><b>"._LOGININCOR."</b></font></center>\n";
			CloseTable();
		} else {
			OpenTable();
			echo "<center><font class=\"title\"><b>"._USERREGLOGIN."</b></font></center>\n";
			CloseTable();
		}
		if (!is_user($user)) {
			OpenTable();
			displaytopmenu($tid);
			loginbox();
			CloseTable();
		}
		include("footer.php");
	} elseif (is_user($user)) {

		$query="SELECT * FROM nuke_ibl_settings WHERE name = 'Allow Trades' ";
		$result=mysql_query($query);

		$allow_trades=mysql_result($result,0,"value");
		$query2="SELECT * FROM nuke_ibl_settings WHERE name = 'Allow Waiver Moves' ";
		$result2=mysql_query($query2);

		$allow_trades=mysql_result($result,0,"value");
		$allow_waiver_moves=mysql_result($result2,0,"value");

		if ($allow_trades == 'Yes') {
			global $cookie;
			cookiedecode($user);
			tradereview($cookie[1]);
		} else {
			include ("header.php");
			OpenTable();
			displaytopmenu($tid);
			echo "Sorry, but players may not be traded at the present time.";
			if ($allow_waiver_moves == 'Yes') {
				echo "<br>
				Players may still be <a href=\"./modules.php?name=Waivers&action=add\">Added From Waivers</a> or they may be <a href=\"./modules.php?name=Waivers&action=drop\">Dropped to Waivers</a>.";
			} else {
				echo "<br>Please note that the waiver wire has also closed.";
			}
			CloseTable();
			include ("footer.php");
		}
	}
}

function offertrade($user)
{
	global $stop, $module_name, $redirect, $mode, $t, $f, $gfx_chk;
	if(!is_user($user)) {
		include("header.php");
		if ($stop) {
			OpenTable();
			echo "<center><font class=\"title\"><b>"._LOGININCOR."</b></font></center>\n";
			CloseTable();
		} else {
			OpenTable();
			echo "<center><font class=\"title\"><b>"._USERREGLOGIN."</b></font></center>\n";
			CloseTable();
		}
		if (!is_user($user)) {
			OpenTable();
			displaytopmenu($tid);
			loginbox();
			CloseTable();
		}
		include("footer.php");
	} elseif (is_user($user)) {
		global $cookie;
		cookiedecode($user);
		tradeoffer($cookie[1]);
	}
}

switch($op) {

	case "reviewtrade":
	reviewtrade($user);
	break;

	case "offertrade":
	offertrade($user);
	break;

	default:
	menu();
	break;
}

?>
