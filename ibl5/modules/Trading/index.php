<?php

if (!eregi("modules.php", $_SERVER['PHP_SELF'])) {
	die ("You can't access this file directly...");
}

require_once("mainfile.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/sharedFunctions.php';

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

function tradeoffer($username, $bypass = 0, $hid = 0, $url = 0)
{
	global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $subscription_url, $partner;
	$sql = "SELECT * FROM ".$prefix."_bbconfig";
	$result = $db->sql_query($sql);
	while ($row = $db->sql_fetchrow($result)) {
		$board_config[$row['config_name']] = $row['config_value'];
	}

	$sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username = '$username'";
	$result2 = $db->sql_query($sql2);
	$num = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);
	if(!$bypass) cookiedecode($user);

	include("header.php");

	$currentSeasonEndingYear = getCurrentSeasonEndingYear();
	$seasonPhase = getCurrentSeasonPhase();

	OpenTable();

	$teamlogo = $userinfo[user_ibl_team];
	$tid = mysql_result(mysql_query("SELECT * FROM nuke_ibl_team_info WHERE team_name = '$teamlogo' LIMIT 1"), 0, "teamid");

	displaytopmenu($tid);

	$queryListOfAllTeams = "SELECT * FROM nuke_ibl_team_info ORDER BY team_city ASC ";
	$resultListOfAllTeams = $db->sql_query($queryListOfAllTeams);

	$queryOfferingTeamPlayers = "SELECT *
		FROM nuke_iblplyr
		WHERE teamname = '$userinfo[user_ibl_team]'
		AND retired = '0'
		ORDER BY ordinal ASC ";
	$resultOfferingTeamPlayers = $db->sql_query($queryOfferingTeamPlayers);

	$queryOfferingTeamDraftPicks = "SELECT *
		FROM ibl_draft_picks
		WHERE ownerofpick = '$userinfo[user_ibl_team]'
		ORDER BY year, round ASC ";
	$resultOfferingTeamDraftPicks = $db->sql_query($queryOfferingTeamDraftPicks);

	echo "<form name=\"Trade_Offer\" method=\"post\" action=\"maketradeoffer.php\">
		<input type=\"hidden\" name=\"Team_Name\" value=\"$teamlogo\">
		<center>
			<img src=\"images/logo/$tid.jpg\"><br>
			<table border=1 cellspacing=0 cellpadding=5>
				<tr>
					<th colspan=4><center>TRADING MENU</center></th>
				</tr>
				<tr>
					<td valign=top>
						<table cellspacing=3>
							<tr>
								<td valign=top colspan=4>
									<center><b><u>$teamlogo</u></b></center>
								</td>
							</tr>
							<tr>
								<td valign=top>
									<b>Select</b>
								</td>
								<td valign=top>
									<b>Pos</b>
								</td>
								<td valign=top>
									<b>Name</b>
								</td>
								<td valign=top>
									<b>Salary</b>
								</td>";

	$k = 0;
	while($rowOfferingTeamPlayers = $db->sql_fetchrow($resultOfferingTeamPlayers)) {
		$player_pos = $rowOfferingTeamPlayers[pos];
		$player_name = $rowOfferingTeamPlayers[name];
		$player_pid = $rowOfferingTeamPlayers[pid];
		$contract_year = $rowOfferingTeamPlayers[cy];
		if ($seasonPhase == "Playoffs" OR $seasonPhase == "Draft" OR $seasonPhase == "Free Agency") {
			$contract_year++;
		}
		$player_contract = $rowOfferingTeamPlayers["cy$contract_year"];
		if ($contract_year == 7) {
			$player_contract = 0;
		}

		//ARRAY TO BUILD FUTURE SALARY
		$z = 0;
		while ($contract_year < 7) {
			$future_salary_array['player'][$z] = $future_salary_array['player'][$z] + $rowOfferingTeamPlayers["cy$contract_year"];
			if ($rowOfferingTeamPlayers["cy$contract_year"] > 0) {
				$future_salary_array['hold'][$z]++;
			}
			$contract_year++;
			$z++;
		}

		//END OF ARRAY

		echo "<input type=\"hidden\" name=\"index$k\" value=\"$player_pid\">
			<input type=\"hidden\" name=\"contract$k\" value=\"$player_contract\">
			<input type=\"hidden\" name=\"type$k\" value=\"1\">
		<tr>";

		if ($player_contract != 0) {
			echo "<td align=\"center\"><input type=\"checkbox\" name=\"check$k\"></td>";
		} else {
			echo "<td align=\"center\"><input type=\"hidden\" name=\"check$k\"></td>";
		}

		echo "
			<td>$player_pos</td>
			<td>$player_name</td>
			<td align=\"right\">$player_contract</td>
		</tr>";

		$k++;
	}

	while ($rowOfferingTeamDraftPicks = $db->sql_fetchrow($resultOfferingTeamDraftPicks)) {
		$pick_year = $rowOfferingTeamDraftPicks[year];
		$pick_team = $rowOfferingTeamDraftPicks[teampick];
		$pick_round = $rowOfferingTeamDraftPicks[round];
		$pick_id = $rowOfferingTeamDraftPicks[pickid];

		$y = $pick_year - $currentSeasonEndingYear + 1;
		if ($pick_round == 1) {
			$future_salary_array['picks'][$y] = $future_salary_array['picks'][$y] + 75;
			$future_salary_array['hold'][$y]++;
			//$future_salary_array[$y]=$future_salary_array[$y]+321;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
			$y++;
			$future_salary_array['picks'][$y] = $future_salary_array['picks'][$y] + 75;
			$future_salary_array['hold'][$y]++;
			//$future_salary_array[$y]=$future_salary_array[$y]+345;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
			$y++;
			$future_salary_array['picks'][$y] = $future_salary_array['picks'][$y] + 75;
			$future_salary_array['hold'][$y]++;
			//$future_salary_array[$y]=$future_salary_array[$y]+369;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
		} else {
			$future_salary_array['picks'][$y] = $future_salary_array['picks'][$y] + 75;
			$future_salary_array['hold'][$y]++;
			//$future_salary_array[$y]=$future_salary_array[$y]+35;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
			$y++;
			$future_salary_array['picks'][$y] = $future_salary_array['picks'][$y] + 75;
			$future_salary_array['hold'][$y]++;
			//$future_salary_array[$y]=$future_salary_array[$y]+51;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
		}

		echo "<tr>
			<td align=\"center\">
				<input type=\"hidden\" name=\"index$k\" value=\"$pick_id\">
				<input type=\"hidden\" name=\"type$k\" value=\"0\">
				<input type=\"checkbox\" name=\"check$k\">
			</td>
			<td colspan=3>
				$pick_year $pick_team Round $pick_round
			</td>
		</tr>";

		$k++;
	}

	echo "</table>
		</td>
		<td valign=top>
			<table cellspacing=3>
				<tr>
					<td valign=top align=center colspan=4>
						<input type=\"hidden\" name=\"half\" value=\"$k\">
						<input type=\"hidden\" name=\"Team_Name2\" value=\"$partner\">
						<b><u>$partner</u></b>
					</td>
				</tr>
				<tr>
					<td valign=top><b>Select</b></td>
					<td valign=top><b>Pos</b></td>
					<td valign=top><b>Name</b></td>
					<td valign=top><b>Salary</b></td>
				</tr>";

	$queryOtherTeamPlayers = "SELECT *
		FROM nuke_iblplyr
		WHERE teamname = '$partner'
		AND retired = '0'
		ORDER BY ordinal ASC ";
	$resultOtherTeamPlayers = $db->sql_query($queryOtherTeamPlayers);

	$queryOtherTeamDraftPicks = "SELECT *
		FROM ibl_draft_picks
		WHERE ownerofpick = '$partner'
		ORDER BY year, round ASC ";
	$resultOtherTeamDraftPicks = $db->sql_query($queryOtherTeamDraftPicks);

	$roster_hold_teamb = (15 - mysql_numrows($resultOtherTeamPlayers)) * 75;
	while ($rowOtherTeamPlayers = $db->sql_fetchrow($resultOtherTeamPlayers)) {
		$player_pos = $rowOtherTeamPlayers[pos];
		$player_name = $rowOtherTeamPlayers[name];
		$player_pid = $rowOtherTeamPlayers[pid];
		$contract_year = $rowOtherTeamPlayers[cy];
		if (getCurrentSeasonPhase() == "Draft" OR getCurrentSeasonPhase() == "Free Agency") {
			$contract_year++;
		}
		$bird_years = $rowOtherTeamPlayers[bird];
		$player_contract = $rowOtherTeamPlayers["cy$contract_year"];
		if ($contract_year == 7) {
			$player_contract = 0;
		}

		//ARRAY TO BUILD FUTURE SALARY
		$i = $contract_year;
		$z = 0;
		while ($i < 7) {
			//$future_salary_arrayb[$z]=$future_salary_arrayb[$z]+$rowOtherTeamPlayers["cy$i"];
			$future_salary_arrayb['player'][$z] = $future_salary_arrayb['player'][$z] + $rowOtherTeamPlayers["cy$i"];
			if ($rowOtherTeamPlayers["cy$i"] > 0) {
				//$future_roster_sportsb[$z]=$future_roster_sportsb[$z]+1;
				$future_salary_arrayb['hold'][$z] = $future_salary_arrayb['hold'][$z] + 1;
			}
			$i++;
			$z++;
		}

		//END OF ARRAY

		echo "<input type=\"hidden\" name=\"index$k\" value=\"$player_pid\">
			<input type=\"hidden\" name=\"contract$k\" value=\"$player_contract\">
			<input type=\"hidden\" name=\"type$k\" value=\"1\">
		<tr>";

		if ($player_contract != 0) {
			echo "<td align=center><input type=\"checkbox\" name=\"check$k\"></td>";
		} else {
			echo "<td align=center><input type=\"hidden\" name=\"check$k\"></td>";
		}

		echo "
			<td>$player_pos</td>
			<td>$player_name</td>
			<td align=\"right\">$player_contract</td>
		</tr>";

		$k++;
	}

	while($rowOtherTeamDraftPicks = $db->sql_fetchrow($resultOtherTeamDraftPicks)) {
		$pick_year = $rowOtherTeamDraftPicks[year];
		$pick_team = $rowOtherTeamDraftPicks[teampick];
		$pick_round = $rowOtherTeamDraftPicks[round];
		$pick_id = $rowOtherTeamDraftPicks[pickid];

		$y = $pick_year - $currentSeasonEndingYear + 1;
		if ($pick_round == 1) {
			$future_salary_arrayb['picks'][$y] = $future_salary_arrayb['picks'][$y] + 75;
			$future_salary_arrayb['hold'][$y] = $future_salary_arrayb['hold'][$y] + 1;
			//$future_salary_array[$y]=$future_salary_array[$y]+321;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
			$y++;
			$future_salary_arrayb['picks'][$y] = $future_salary_arrayb['picks'][$y] + 75;
			$future_salary_arrayb['hold'][$y] = $future_salary_arrayb['hold'][$y] + 1;
			//$future_salary_array[$y]=$future_salary_array[$y]+345;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
			$y++;
			$future_salary_arrayb['picks'][$y] = $future_salary_arrayb['picks'][$y] + 75;
			$future_salary_arrayb['hold'][$y] = $future_salary_arrayb['hold'][$y] + 1;
			//$future_salary_array[$y]=$future_salary_array[$y]+369;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
		} else {
			$future_salary_arrayb['picks'][$y] = $future_salary_arrayb['picks'][$y] + 75;
			$future_salary_arrayb['hold'][$y] = $future_salary_arrayb['hold'][$y] + 1;
			//$future_salary_array[$y]=$future_salary_array[$y]+35;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
			$y++;
			$future_salary_arrayb['picks'][$y] = $future_salary_arrayb['picks'][$y] + 75;
			$future_salary_arrayb['hold'][$y] = $future_salary_arrayb['hold'][$y] + 1;
			//$future_salary_array[$y]=$future_salary_array[$y]+51;
			//$future_roster_sports[$y]=$future_roster_sports[$y]+1;
		}

		echo "<tr>
			<td align=\"center\">
				<input type=\"hidden\" name=\"index$k\" value=\"$pick_id\">
				<input type=\"hidden\" name=\"type$k\" value=\"0\">
				<input type=\"checkbox\" name=\"check$k\">
			</td>
			<td colspan=3>
				$pick_year $pick_team Round $pick_round
			</td>
		</tr>";

		$k++;
	}

	$k--;
	echo "</table>
		</td>
		<td valign=top>
			<table>
				<tr>
					<input type=\"hidden\" name=\"counterfields\" value=\"$k\">
					<td valign=top><center><b><u>Make Trade Offer To...</u></b></center>";

	while ($rowInListOfAllTeams = $db->sql_fetchrow($resultListOfAllTeams)) {
		$team_name = $rowInListOfAllTeams[team_name];
		$team_city = $rowInListOfAllTeams[team_city];

		if ($team_name != 'Free Agents') {
			//------Trade Deadline Code---------
			echo "<a href=\"modules.php?name=Trading&op=offertrade&partner=$team_name\">$team_city $team_name</a><br>";
		}
	}

	echo "</td></tr></table>";
	$z = 0;
	while ($z < 6) {
		$pass_future_salary_player[$z] = $pass_future_salary_array[$z] + $future_salary_array['player'][$z];
		$pass_future_salary_hold[$z] = $pass_future_salary_array[$z] + $future_salary_array['hold'][$z];
		$pass_future_salary_picks[$z] = $pass_future_salary_array[$z] + $future_salary_array['picks'][$z];
		$pass_future_salary_playerb[$z] = $pass_future_salary_arrayb[$z];
		$pass_future_salary_holdb[$z] = $pass_future_salary_arrayb[$z] + $future_salary_arrayb['hold'][$z];
		$pass_future_salary_picksb[$z] = $pass_future_salary_arrayb[$z] + $future_salary_arrayb['picks'][$z];
		echo "<tr><td><b>
			Total Year: " . ($currentSeasonEndingYear + $z) . ":
			Salary: $" . $future_salary_array['player'][$z] . "</b></td>";
		echo "<td align=right><b>
			Salary: $" . $future_salary_arrayb['player'][$z] . "</b></td>";
		$z++;
	}

	$pass_future_salary_player = implode(",", $pass_future_salary_player);
	$pass_future_salary_hold = implode(",", $pass_future_salary_hold);
	$pass_future_salary_picks = implode(",", $pass_future_salary_picks);
	$pass_future_salary_playerb = implode(",", $pass_future_salary_playerb);
	$pass_future_salary_holdb = implode(",", $pass_future_salary_holdb);
	$pass_future_salary_picksb = implode(",", $pass_future_salary_picksb);
	echo "<input type=\"hidden\" name=\"pass_future_salary_player\" value=\"" . htmlspecialchars($pass_future_salary_player) . "\">
		<input type=\"hidden\" name=\"pass_future_salary_hold\" value=\"" . htmlspecialchars($pass_future_salary_hold) . "\">
		<input type=\"hidden\" name=\"pass_future_salary_picks\" value=\"" . htmlspecialchars($pass_future_salary_picks) . "\">
		<input type=\"hidden\" name=\"pass_future_salary_playerb\" value=\"" . htmlspecialchars($pass_future_salary_playerb) . "\">
		<input type=\"hidden\" name=\"pass_future_salary_holdb\" value=\"" . htmlspecialchars($pass_future_salary_holdb) . "\">
		<input type=\"hidden\" name=\"pass_future_salary_picksb\" value=\"" . htmlspecialchars($pass_future_salary_picksb) . "\">
		<tr><td colspan=3 align=center>
		<input type=\"submit\" value=\"Make Trade Offer\"></td></tr></form></center></table></td></tr></table></center>";

	CloseTable();

	include("footer.php");
}

function tradereview($username, $bypass = 0, $hid = 0, $url = 0)
{
	global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $subscription_url, $attrib, $step, $player;
	$sql = "SELECT * FROM " . $prefix . "_bbconfig";
	$result = $db->sql_query($sql);
	while ($row = $db->sql_fetchrow($result)) {
		$board_config[$row['config_name']] = $row['config_value'];
	}

	// ==== PICKUP LOGGED-IN USER INFO

	$sql2 = "SELECT * FROM " . $user_prefix."_users WHERE username = '$username'";
	$result2 = $db->sql_query($sql2);
	$num = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);
	if (!$bypass) cookiedecode($user);

	// ===== END OF INFO PICKUP

	include("header.php");

	OpenTable();

	$teamlogo = $userinfo[user_ibl_team];
	$tid = mysql_result(mysql_query("SELECT * FROM nuke_ibl_team_info WHERE team_name = '$teamlogo' LIMIT 1"), 0, "teamid");
	displaytopmenu($tid);

	echo "<center><img src=\"images/logo/$tid.jpg\"><br>";

	$sql3 = "SELECT * FROM nuke_ibl_trade_info ORDER BY tradeofferid ASC";
	$result3 = $db->sql_query($sql3);
	$num3 = $db->sql_numrows($result3);

	$tradeworkingonnow = 0;

	echo "<table>
		<th>
			<tr>
				<td valign=top>REVIEW TRADE OFFERS";

	while ($row3 = $db->sql_fetchrow($result3)) {
		$isinvolvedintrade = 0;
		$hashammer = 0;
		$offerid = $row3[tradeofferid];
		$itemid = $row3[itemid];

		// For itemtype (1 = player, 0 = pick)
		$itemtype = $row3[itemtype];
		$from = $row3[from];
		$to = $row3[to];
		$approval = $row3[approval];

		if ($from == $teamlogo) {
			$isinvolvedintrade = 1;
		}
		if ($to == $teamlogo) {
			$isinvolvedintrade = 1;
		}
		if ($approval == $teamlogo) {
			$hashammer = 1;
		}

		if ($isinvolvedintrade == 1) {
			if ($offerid == $tradeworkingonnow) {
			} else {
				echo "				</td>
							</tr>
						</th>
					</table>
					<table border=1 valign=top align=center>
						<tr>
							<td>
								<b><u>TRADE OFFER</u></b><br>
								<table align=right border=1 cellspacing=0 cellpadding=0>
									<tr>
										<td valign=center>";
				if ($hashammer == 1) {
					echo "<form name=\"tradeaccept\" method=\"post\" action=\"accepttradeoffer.php\">
						<input type=\"hidden\" name=\"offer\" value=\"$offerid\">
						<input type=\"submit\" value=\"Accept\">
					</form>";
				} else {
					echo "(Awaiting Approval)";
				}
				echo "</td>
						<td valign=center>
							<form name=\"tradereject\" method=\"post\" action=\"rejecttradeoffer.php\">
								<input type=\"hidden\" name=\"offer\" value=\"$offerid\">
								<input type=\"submit\" value=\"Reject\">
							</form>
						</td>
					</tr>
				</table>";
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
			$tradeworkingonnow = $offerid;
		}
	}

	echo "</td>
		<td valign=top><center><b><u>Make Trade Offer To...</u></b></center>";

	$queryListOfAllTeams = "SELECT * FROM nuke_ibl_team_info ORDER BY team_city ASC ";
	$resultListOfAllTeams = $db->sql_query($queryListOfAllTeams);

	while($rowInListOfAllTeams = $db->sql_fetchrow($resultListOfAllTeams)) {
		$team_name = $rowInListOfAllTeams[team_name];
		$team_city = $rowInListOfAllTeams[team_city];

		if ($team_name != 'Free Agents') {
			//------Trade Deadline Code---------
			echo "<a href=\"modules.php?name=Trading&op=offertrade&partner=$team_name\">$team_city $team_name</a><br>";
		}
	}

	echo "</td>
		</tr>
		<tr>
			<td colspan=2 align=center>
				<a href=\"modules.php?name=Waivers&action=drop\">Drop a player to Waivers</a><br>
				<a href=\"modules.php?name=Waivers&action=add\">Add a player from Waivers</a><br>
			</td>
		</tr>
	</table>";

	CloseTable();
	include("footer.php");
}

function reviewtrade($user)
{
	global $stop, $module_name, $redirect, $mode, $t, $f, $gfx_chk;
	if (!is_user($user)) {
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
		$queryAllowTrades = "SELECT * FROM nuke_ibl_settings WHERE name = 'Allow Trades' ";
		$resultAllowTrades = mysql_query($queryAllowTrades);
		$allow_trades = mysql_result($resultAllowTrades, 0, "value");

		$queryAllowWaiverMoves = "SELECT * FROM nuke_ibl_settings WHERE name = 'Allow Waiver Moves' ";
		$resultAllowWaiverMoves = mysql_query($queryAllowWaiverMoves);
		$allow_waiver_moves = mysql_result($resultAllowWaiverMoves, 0, "value");

		if ($allow_trades == 'Yes') {
			global $cookie;
			cookiedecode($user);
			tradereview($cookie[1]);
		} else {
			include ("header.php");
			OpenTable();
			displaytopmenu($tid);
			echo "Sorry, but trades are not allowed right now.";
			if ($allow_waiver_moves == 'Yes') {
				echo "<br>
				Players may still be <a href=\"modules.php?name=Waivers&action=add\">Added From Waivers</a> or they may be <a href=\"modules.php?name=Waivers&action=drop\">Dropped to Waivers</a>.";
			} else {
				echo "<br>The waiver wire is also closed.";
			}
			CloseTable();
			include ("footer.php");
		}
	}
}

function offertrade($user)
{
	global $stop, $module_name, $redirect, $mode, $t, $f, $gfx_chk;
	if (!is_user($user)) {
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
