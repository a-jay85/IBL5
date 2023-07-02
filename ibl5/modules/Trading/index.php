<?php

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Team Pages";

function menu()
{
    global $db;
    $sharedFunctions = new Shared($db);

    include "header.php";
    OpenTable();

    $sharedFunctions->displaytopmenu(0);

    CloseTable();
    include "footer.php";
}

function buildTeamFutureSalary($resultTeamPlayers, $k)
{
    global $db;
    $sharedFunctions = new Shared($db);

    $seasonPhase = $sharedFunctions->getCurrentSeasonPhase();

    $future_salary_array[][] = "";
    
    while ($rowTeamPlayers = $db->sql_fetch_assoc($resultTeamPlayers)) {
        $player_pos = $rowTeamPlayers["pos"];
        $player_name = $rowTeamPlayers["name"];
        $player_pid = $rowTeamPlayers["pid"];
        $player_ordinal = $rowTeamPlayers["ordinal"];
        $contract_year = $rowTeamPlayers["cy"];
        if ($seasonPhase == "Playoffs" or $seasonPhase == "Draft" or $seasonPhase == "Free Agency") {
            $contract_year++;
        }
        if ($contract_year == 0) {
            $contract_year = 1;
        }
        $player_contract = $rowTeamPlayers["cy$contract_year"];
        if ($contract_year == 7) {
            $player_contract = 0;
        }

        $i = 0;
        while ($contract_year < 7) {
            $future_salary_array['player'][$i] += $rowTeamPlayers["cy$contract_year"];
            if ($rowTeamPlayers["cy$contract_year"] > 0) {
                $future_salary_array['hold'][$i]++;
            }
            $contract_year++;
            $i++;
        }

        echo "<tr>
            <input type=\"hidden\" name=\"index$k\" value=\"$player_pid\">
			<input type=\"hidden\" name=\"contract$k\" value=\"$player_contract\">
			<input type=\"hidden\" name=\"type$k\" value=\"1\">";

        if ($player_contract != 0 AND $player_ordinal < 960) { // "ordinal < 960" prevents trading of waived players and Buyouts
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

    $future_salary_array['k'] = $k;

    return $future_salary_array;
}

function buildTeamFuturePicks($resultTeamPicks, $future_salary_array)
{
    global $db;
    $sharedFunctions = new Shared($db);

    $k = $future_salary_array['k'];
    while ($rowTeamDraftPicks = $db->sql_fetch_assoc($resultTeamPicks)) {
        $currentSeasonEndingYear = $sharedFunctions->getCurrentSeasonEndingYear();
        $pick_year = $rowTeamDraftPicks["year"];
        $pick_team = $rowTeamDraftPicks["teampick"];
        $pick_round = $rowTeamDraftPicks["round"];
        $pick_notes = $rowTeamDraftPicks["notes"];
        $pick_id = $rowTeamDraftPicks["pickid"];

        $y = $pick_year - $currentSeasonEndingYear + 1;
        if ($pick_round == 1) {
            $future_salary_array['picks'][$y] += 75;
            $future_salary_array['hold'][$y]++;
            //$future_salary_array[$y]=$future_salary_array[$y]+321;
            //$future_roster_sports[$y]=$future_roster_sports[$y]+1;
            $y++;
            $future_salary_array['picks'][$y] += 75;
            $future_salary_array['hold'][$y]++;
            //$future_salary_array[$y]=$future_salary_array[$y]+345;
            //$future_roster_sports[$y]=$future_roster_sports[$y]+1;
            $y++;
            $future_salary_array['picks'][$y] += 75;
            $future_salary_array['hold'][$y]++;
            //$future_salary_array[$y]=$future_salary_array[$y]+369;
            //$future_roster_sports[$y]=$future_roster_sports[$y]+1;
        } else {
            $future_salary_array['picks'][$y] += 75;
            $future_salary_array['hold'][$y]++;
            //$future_salary_array[$y]=$future_salary_array[$y]+35;
            //$future_roster_sports[$y]=$future_roster_sports[$y]+1;
            $y++;
            $future_salary_array['picks'][$y] += 75;
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
        if ($pick_notes != NULL) {
            echo "<tr>
                <td colspan=3 width=150>$pick_notes</td>
            </tr>";
        }

        $k++;
    }

    $future_salary_array['k'] = $k;

    return $future_salary_array;
}

function tradeoffer($username, $bypass = 0, $hid = 0, $url = 0)
{
    global $user, $prefix, $user_prefix, $db, $partner;
    $sharedFunctions = new Shared($db);

    $sql = "SELECT * FROM " . $prefix . "_bbconfig";
    $result = $db->sql_query($sql);
    while ($row = $db->sql_fetchrow($result)) {
        $board_config[$row['config_name']] = $row['config_value'];
    }

    $sql2 = "SELECT * FROM " . $user_prefix . "_users WHERE username = '$username'";
    $result2 = $db->sql_query($sql2);
    $userinfo = $db->sql_fetchrow($result2);
    if (!$bypass) {
        cookiedecode($user);
    }

    include "header.php";

    $currentSeasonEndingYear = $sharedFunctions->getCurrentSeasonEndingYear();

    OpenTable();

    $teamlogo = $userinfo['user_ibl_team'];
    $tid = $sharedFunctions->getTidFromTeamname($teamlogo);
    $sharedFunctions->displaytopmenu($tid);

    $queryUserTeamPlayers = "SELECT pos, name, pid, ordinal, cy, cy1, cy2, cy3, cy4, cy5, cy6
		FROM ibl_plr
		WHERE tid = $tid
		AND retired = '0'
		ORDER BY ordinal ASC ";
    $resultUserTeamPlayers = $db->sql_query($queryUserTeamPlayers);

    $queryUserTeamDraftPicks = "SELECT *
		FROM ibl_draft_picks
		WHERE ownerofpick = '$teamlogo'
		ORDER BY year, round ASC ";
    $resultUserTeamDraftPicks = $db->sql_query($queryUserTeamDraftPicks);

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
								<td valign=top><b>Select</b></td>
								<td valign=top><b>Pos</b></td>
								<td valign=top><b>Name</b></td>
								<td valign=top><b>Salary</b></td>";

    $future_salary_array = buildTeamFutureSalary($resultUserTeamPlayers, 0);
    $future_salary_array = buildTeamFuturePicks($resultUserTeamDraftPicks, $future_salary_array);
    $k = $future_salary_array['k']; // pull $k value out to populate $Fields_Counter in maketradeoffer.php

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

    $partnerTid = $sharedFunctions->getTidFromTeamname($partner);
    $queryPartnerTeamPlayers = "SELECT pos, name, pid, ordinal, cy, cy1, cy2, cy3, cy4, cy5, cy6
		FROM ibl_plr
		WHERE tid = $partnerTid
		AND retired = '0'
		ORDER BY ordinal ASC ";
    $resultPartnerTeamPlayers = $db->sql_query($queryPartnerTeamPlayers);

    $queryPartnerTeamDraftPicks = "SELECT *
		FROM ibl_draft_picks
		WHERE ownerofpick = '$partner'
		ORDER BY year, round ASC ";
    $resultPartnerTeamDraftPicks = $db->sql_query($queryPartnerTeamDraftPicks);

    $future_salary_arrayb = buildTeamFutureSalary($resultPartnerTeamPlayers, $k);
    $future_salary_arrayb = buildTeamFuturePicks($resultPartnerTeamDraftPicks, $future_salary_arrayb);
    $k = $future_salary_arrayb['k']; // pull $k value out to populate $Fields_Counter in maketradeoffer.php

    $k--;
    echo "</table>
		</td>
		<td valign=top>
			<table>
				<tr>
					<td valign=top><center><b><u>Make Trade Offer To...</u></b></center>";

    $queryListOfAllTeams = "SELECT team_name, team_city FROM ibl_team_info ORDER BY team_city ASC";
    $resultListOfAllTeams = $db->sql_query($queryListOfAllTeams);
    while ($rowInListOfAllTeams = $db->sql_fetchrow($resultListOfAllTeams)) {
        $team_name = $rowInListOfAllTeams['team_name'];
        $team_city = $rowInListOfAllTeams['team_city'];

        if ($team_name != 'Free Agents') {
            echo "<a href=\"modules.php?name=Trading&op=offertrade&partner=$team_name\">$team_city $team_name</a><br>";
        }
    }

    echo "</td></tr></table>";
    $z = 0;
    while ($z < 6) {
        echo "<tr>
            <td>
                <b>$teamlogo Cap Total in " . ($currentSeasonEndingYear + $z) . ":</b> " . $future_salary_array['player'][$z] . "
            </td>
            <td align=right>
                <b>$partner Cap Total in " . ($currentSeasonEndingYear + $z) . ":</b> " . $future_salary_arrayb['player'][$z] . "
            </td>";
        $z++;
    }

    $i = 1;
    while ($i < 7) {
        echo "<tr>
            <td>
                <b>$teamlogo send
                <input type=\"number\" name=\"userSendsCash$i\" value =\"0\" min=\"0\" max =\"2000\">
                for " . ($currentSeasonEndingYear + $i - 1) . "</b>
            </td>
            <td align=right>
                <b>$partner send
                <input type=\"number\" name=\"partnerSendsCash$i\" value =\"0\" min=\"0\" max =\"2000\">
                for " . ($currentSeasonEndingYear + $i - 1) . "</b>
            </td>
        </tr>";
        $i++;
    }

    echo "<tr>
            <td colspan=3 align=center>
                <input type=\"hidden\" name=\"counterfields\" value=\"$k\">
                <input type=\"submit\" value=\"Make Trade Offer\">
            </td>
        </tr>
    </form></center></table>";

    CloseTable();

    include "footer.php";
}

function tradereview($username, $bypass = 0, $hid = 0, $url = 0)
{
    global $user, $prefix, $user_prefix, $db;
    $sharedFunctions = new Shared($db);

    $sql = "SELECT * FROM " . $prefix . "_bbconfig";
    $result = $db->sql_query($sql);
    while ($row = $db->sql_fetchrow($result)) {
        $board_config[$row['config_name']] = $row['config_value'];
    }

    // ==== PICKUP LOGGED-IN USER INFO

    $sql2 = "SELECT * FROM " . $user_prefix . "_users WHERE username = '$username'";
    $result2 = $db->sql_query($sql2);
    $userinfo = $db->sql_fetchrow($result2);
    if (!$bypass) {
        cookiedecode($user);
    }

    // ===== END OF INFO PICKUP

    include "header.php";

    OpenTable();

    $teamlogo = $userinfo['user_ibl_team'];
    $tid = $sharedFunctions->getTidFromTeamname($teamlogo);
    $sharedFunctions->displaytopmenu($tid);

    echo "<center><img src=\"images/logo/$tid.jpg\"><br>";

    $sql3 = "SELECT * FROM ibl_trade_info ORDER BY tradeofferid ASC";
    $result3 = $db->sql_query($sql3);

    $tradeworkingonnow = 0;

    echo "<table>
		<th>
			<tr>
				<td valign=top>REVIEW TRADE OFFERS";

    while ($row3 = $db->sql_fetchrow($result3)) {
        $isinvolvedintrade = 0;
        $hashammer = 0;
        $offerid = $row3['tradeofferid'];
        $itemid = $row3['itemid'];

        // For itemtype (1 = player, 0 = pick, cash = cash)
        $itemtype = $row3['itemtype'];
        $from = $row3['from'];
        $to = $row3['to'];
        $approval = $row3['approval'];

        if ($from == $teamlogo) {
            $isinvolvedintrade = 1;
            $oppositeTeam = $to;
        }
        if ($to == $teamlogo) {
            $isinvolvedintrade = 1;
            $oppositeTeam = $from;
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
                                <input type=\"hidden\" name=\"teamRejecting\" value=\"$teamlogo\">
                                <input type=\"hidden\" name=\"teamReceiving\" value=\"$oppositeTeam\">
								<input type=\"submit\" value=\"Reject\">
							</form>
						</td>
					</tr>
				</table>";
            }

            if ($itemtype == 'cash') {
                $queryCashDetails = "SELECT * FROM ibl_trade_cash WHERE tradeOfferID = $offerid AND sendingTeam = '$from';";
                $cashDetails = $db->sql_fetchrow($db->sql_query($queryCashDetails));

                $cashYear[1] = $cashDetails['cy1'];
                $cashYear[2] = $cashDetails['cy2'];
                $cashYear[3] = $cashDetails['cy3'];
                $cashYear[4] = $cashDetails['cy4'];
                $cashYear[5] = $cashDetails['cy5'];
                $cashYear[6] = $cashDetails['cy6'];

                echo "The $from send 
                $cashYear[1] $cashYear[2] $cashYear[3] $cashYear[4] $cashYear[5] $cashYear[6]
                in cash to the $to.<br>";
            } elseif ($itemtype == 0) {
                $sqlgetpick = "SELECT * FROM ibl_draft_picks WHERE pickid = '$itemid'";
                $resultgetpick = $db->sql_query($sqlgetpick);
                $rowsgetpick = $db->sql_fetchrow($resultgetpick);

                $pickteam = $rowsgetpick['teampick'];
                $pickyear = $rowsgetpick['year'];
                $pickround = $rowsgetpick['round'];
                $picknotes = $rowsgetpick['notes'];

                echo "The $from send the $pickteam $pickyear Round $pickround draft pick to the $to.<br>";
                if ($picknotes != NULL) {
                    echo "<i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . $picknotes . "</i><br>";
                }
            } elseif ($itemtype == 1) {
                $sqlgetplyr = "SELECT * FROM ibl_plr WHERE pid = '$itemid'";
                $resultgetplyr = $db->sql_query($sqlgetplyr);
                $rowsgetplyr = $db->sql_fetchrow($resultgetplyr);

                $plyrname = $rowsgetplyr['name'];
                $plyrpos = $rowsgetplyr['pos'];

                echo "The $from send $plyrpos $plyrname to the $to.<br>";
            }

            $tradeworkingonnow = $offerid;
        }
    }

    echo "</td>
		<td valign=top><center><b><u>Make Trade Offer To...</u></b></center>";

    $queryListOfAllTeams = "SELECT team_name, team_city FROM ibl_team_info ORDER BY team_city ASC ";
    $resultListOfAllTeams = $db->sql_query($queryListOfAllTeams);

    while ($rowInListOfAllTeams = $db->sql_fetchrow($resultListOfAllTeams)) {
        $team_name = $rowInListOfAllTeams['team_name'];
        $team_city = $rowInListOfAllTeams['team_city'];

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
    include "footer.php";
}

function reviewtrade($user)
{
    global $db, $stop;
    $sharedFunctions = new Shared($db);

    if (!is_user($user)) {
        include "header.php";
        if ($stop) {
            OpenTable();
            echo "<center><font class=\"title\"><b>" . _LOGININCOR . "</b></font></center>\n";
            CloseTable();
        } else {
            OpenTable();
            echo "<center><font class=\"title\"><b>" . _USERREGLOGIN . "</b></font></center>\n";
            CloseTable();
        }
        if (!is_user($user)) {
            OpenTable();
            $sharedFunctions->displaytopmenu($tid);
            loginbox();
            CloseTable();
        }
        include "footer.php";
    } elseif (is_user($user)) {
        $allow_trades = $sharedFunctions->getAllowTradesStatus();
        $allow_waiver_moves = $sharedFunctions->getWaiverWireStatus();

        if ($allow_trades == 'Yes') {
            global $cookie;
            cookiedecode($user);
            tradereview($cookie[1]);
        } else {
            include "header.php";
            OpenTable();
            $sharedFunctions->displaytopmenu($tid);
            echo "Sorry, but trades are not allowed right now.";
            if ($allow_waiver_moves == 'Yes') {
                echo "<br>
				Players may still be <a href=\"modules.php?name=Waivers&action=add\">Added From Waivers</a> or they may be <a href=\"modules.php?name=Waivers&action=drop\">Dropped to Waivers</a>.";
            } else {
                echo "<br>The waiver wire is also closed.";
            }
            CloseTable();
            include "footer.php";
        }
    }
}

function offertrade($user)
{
    global $db, $stop;
    $sharedFunctions = new Shared($db);

    if (!is_user($user)) {
        include "header.php";
        if ($stop) {
            OpenTable();
            echo "<center><font class=\"title\"><b>" . _LOGININCOR . "</b></font></center>\n";
            CloseTable();
        } else {
            OpenTable();
            echo "<center><font class=\"title\"><b>" . _USERREGLOGIN . "</b></font></center>\n";
            CloseTable();
        }
        if (!is_user($user)) {
            OpenTable();
            $sharedFunctions->displaytopmenu($tid);
            loginbox();
            CloseTable();
        }
        include "footer.php";
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        tradeoffer($cookie[1]);
    }
}

switch ($op) {
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
