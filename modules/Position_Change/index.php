<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2007 by Francisco Burzi                                */
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

poschange($pid);

function menu()
{
		echo "<center><b>
<a href=\"modules.php?name=Player&pa=search\">Player Search</a>  |
<a href=\"modules.php?name=Player&pa=awards\">Awards Search</a> |
<a href=\"modules.php?name=One-On-One\">One-On-One Game</a> |
<a href=\"modules.php?name=Player&pa=Leaderboards\">Career Leaderboards</a> (All Types)
</b><hr>";
}

function poschange($pid)
{
    global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;
    $pid = intval($pid);
    $playerinfo = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_iblplyr WHERE pid='$pid'"));
    $player_name = stripslashes(check_html($playerinfo['name'], "nohtml"));
    $player_pos = stripslashes(check_html($playerinfo['altpos'], "nohtml"));
    $player_team_name = stripslashes(check_html($playerinfo['teamname'], "nohtml"));

    include("header.php");

    OpenTable();

    menu();

		cookiedecode($user);

		$sql2 = "SELECT * FROM ".$prefix."_users WHERE username='$cookie[1]'";
		$result2 = $db->sql_query($sql2);
		$num2 = $db->sql_numrows($result2);
		$userinfo = $db->sql_fetchrow($result2);

		$userteam = stripslashes(check_html($userinfo['user_ibl_team'], "nohtml"));

		echo "<b>$player_pos $player_name</b> - Position Change:
		<br>";

		if ($can_renegotiate >= 0) {
		    if ($player_team_name == $userteam) {
				    $queryAllowPositionChanges = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_ibl_settings WHERE name='Allow Position Changes'"));
				    $isAllowPositionChanges = stripslashes(check_html($queryAllowPositionChanges['value'], "nohtml"));
				    $poschange_check = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_ibl_team_info WHERE team_name = '$player_team_name'"));
				    $pos_five = stripslashes(check_html($poschange_check['poschanges'], "nohtml"));

				    $player_check = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_iblplyr WHERE name = '$player_name'"));
				    $player_poschg = stripslashes(check_html($player_check['poschange'], "nohtml"));

						if ($isAllowPositionChanges != "Yes") {
						    echo "Sorry, the position change feature is only available between the start of H.E.A.T. and the trade deadline.";
						} elseif ($pos_five >= 5) {
						    echo "Sorry, your team has reached the maximum number of position changes.";
						} elseif ($player_poschg >= 1) {
						    echo "Sorry, this player has already had his position changed this season.";
						} else {
						    echo "<form name=\"PositionChange\" method=\"post\" action=\"poschange.php\">";

						    echo "Please change my position to one in which I can better dominate the IBL:
						        <table cellspacing=0 border=1><tr><td>My current position is:</td><td>$player_pos</td></tr>
						        <tr><td>My new position will be:</td><td>
						        ";

						    if ($player_pos == PG) {
						        echo "<select name=\"pos\">
						    <option value=\"\">Select...</option>
						    <option value=\"G\">G</option>
						    <option value=\"SG\">SG</option>
						    <option value=\"GF\">GF</option>
						    <option value=\"SF\">SF</option>
						    <option value=\"F\">F</option>
						    <option value=\"PF\">PF</option>
						    <option value=\"F\">FC</option>
						    <option value=\"C\">C</option>
						</select></td></tr>";
						    } elseif ($player_pos == G) {
						        echo "<select name=\"pos\">
						    <option value=\"PG\">PG</option>
						    <option value=\"SG\">SG</option>
						    <option value=\"GF\">GF</option>
						    <option value=\"SF\">SF</option>
						    <option value=\"F\">F</option>
						    <option value=\"PF\">PF</option>
						    <option value=\"FC\">FC</option>
						    <option value=\"C\">C</option>
						</select></td></tr>";
						    } elseif ($player_pos == SG) {
						        echo "<select name=\"pos\">
						    <option value=\"\">Select...</option>
						    <option value=\"PG\">PG</option>
						    <option value=\"G\">G</option>
						    <option value=\"GF\">GF</option>
						    <option value=\"SF\">SF</option>
						    <option value=\"F\">F</option>
						    <option value=\"PF\">PF</option>
						    <option value=\"FC\">FC</option>
						    <option value=\"C\">C</option>
						</select></td></tr>";
						    } elseif ($player_pos == GF) {
						        echo "<select name=\"pos\">
						    <option value=\"\">Select...</option>
						    <option value=\"PG\">PG</option>
						    <option value=\"G\">G</option>
						    <option value=\"SG\">SG</option>
						    <option value=\"SF\">SF</option>
						    <option value=\"F\">F</option>
						    <option value=\"PF\">PF</option>
						    <option value=\"FC\">FC</option>
						    <option value=\"C\">C</option>
						</select></td></tr>";
						    } elseif ($player_pos == SF) {
						        echo "<select name=\"pos\">
						    <option value=\"\">Select...</option>
						    <option value=\"PG\">PG</option>
						    <option value=\"G\">G</option>
						    <option value=\"SG\">SG</option>
						    <option value=\"GF\">GF</option>
						    <option value=\"F\">F</option>
						    <option value=\"PF\">PF</option>
						    <option value=\"FC\">FC</option>
						    <option value=\"C\">C</option>
						</select></td></tr>";
						    } elseif ($player_pos == F) {
						        echo "<select name=\"pos\">
						    <option value=\"\">Select...</option>
						    <option value=\"PG\">PG</option>
						    <option value=\"G\">G</option>
						    <option value=\"SG\">SG</option>
						    <option value=\"GF\">GF</option>
						    <option value=\"SF\">SF</option>
						    <option value=\"PF\">PF</option>
						    <option value=\"FC\">FC</option>
						    <option value=\"C\">C</option>
						</select></td></tr>";
						    } elseif ($player_pos == PF) {
						        echo "<select name=\"pos\">
						    <option value=\"\">Select...</option>
						    <option value=\"PG\">PG</option>
						    <option value=\"G\">G</option>
						    <option value=\"SG\">SG</option>
						    <option value=\"GF\">GF</option>
						    <option value=\"SF\">SF</option>
						    <option value=\"F\">F</option>
						    <option value=\"FC\">FC</option>
						    <option value=\"C\">C</option>
						</select></td></tr>";
						    } elseif ($player_pos == FC) {
						        echo "<select name=\"pos\">
						    <option value=\"\">Select...</option>
						    <option value=\"PG\">PG</option>
						    <option value=\"G\">G</option>
						    <option value=\"SG\">SG</option>
						    <option value=\"GF\">GF</option>
						    <option value=\"SF\">SF</option>
						    <option value=\"F\">F</option>
						    <option value=\"PF\">PF</option>
						    <option value=\"C\">C</option>
						</select></td></tr>";
						    } elseif ($player_pos == C) {
						        echo "<select name=\"pos\">
						    <option value=\"\">Select...</option>
						    <option value=\"PG\">PG</option>
						    <option value=\"G\">G</option>
						    <option value=\"SG\">SG</option>
						    <option value=\"GF\">GF</option>
						    <option value=\"SF\">SF</option>
						    <option value=\"F\">F</option>
						    <option value=\"PF\">PF</option>
						    <option value=\"FC\">FC</option>
						</select></td></tr>";
						    }

						    echo "<tr><td colspan=6><b>Notes/Reminders:</b> <ul>
						<li>Each player may only have his position changed once per season.</li>
						<li>You are limited to a maximum of 5 position changes per season. You have used $pos_five position change(s) this season.</li>

						";
						    echo "
						</ul></td></tr>
						<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
						<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
						<input type=\"hidden\" name=\"playerpos\" value=\"$player_pos\">
						</table>

						<input type=\"submit\" value=\"Change Position!\">
						</form>

						";
						}
				} else {
		    echo "Sorry, this player is not on your team.";
				}
		} else {
		    echo "Sorry, this player is not eligible for a position change at this time.";
		}

    CloseTable();

    include("footer.php");
}

?>
