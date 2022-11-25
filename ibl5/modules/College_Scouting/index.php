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
/* ibl College Scout Module added by Spencer Cooley                     */
/* 3/22/2005                                                            */
/*                                                                      */
/************************************************************************/

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

require_once "mainfile.php";

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$userpage = 1;

function userinfo($username, $bypass = 0, $hid = 0, $url = 0)
{
    global $user, $prefix, $user_prefix, $db;
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

    OpenTable();

    $teamlogo = $userinfo['user_ibl_team'];
    $tid = $sharedFunctions->getTidFromTeamname($teamlogo);

    $sharedFunctions->displaytopmenu($tid);

    // ========== DISPLAY ROOKIES
    $draft_year = $sharedFunctions->getCurrentSeasonEndingYear();
    $draft_sql = "SELECT * from ibl_draft WHERE player = '' ORDER BY round ASC, pick ASC";
    $draft_result = $db->sql_query($draft_sql);

    $draft_team = $db->sql_result($draft_result, 0, "team");
    $draft_round = $db->sql_result($draft_result, 0, "round");
    $draft_pick = $db->sql_result($draft_result, 0, "pick");

    $queryTeamID = "SELECT teamid FROM ibl_team_info WHERE team_name = '$teamlogo'";
    $tid = $db->sql_result($db->sql_query($queryTeamID), 0);

    echo "<center><img src=\"images/logo/$tid.jpg\"><br>
	<table>
		<tr>
			<th colspan=26>
				<center>Welcome to the $draft_year IBL Draft!
			</th>
		</tr>
	</table>

    <table class=\"sortable\">
    	<tr>
			<th>Draft</th>
			<th>Pos</th>
			<th>Name</th>
			<th>College</th>
			<th>Age</th>
			<th>fga</th>
			<th>fgp</th>
			<th>fta</th>
			<th>ftp</th>
			<th>tga</th>
			<th>tgp</th>
			<th>orb</th>
			<th>drb</th>
			<th>ast</th>
			<th>stl</th>
			<th>to</th>
			<th>blk</th>
			<th>oo</th>
			<th>do</th>
			<th>po</th>
			<th>to</th>
			<th>od</th>
			<th>dd</th>
			<th>pd</th>
			<th>td</th>
			<th>Tal</th>
			<th>Skl</th>
			<th>Int</th>
			<th>Stamina</th>
		</tr>";

    echo "<form name='draft_form' action='online/draft_selection.php' method='POST'>";
    echo "<input type='hidden' name='teamname' value='$teamlogo'>";
    echo "<input type='hidden' name='draft_round' value='$draft_round'>";
    echo "<input type='hidden' name='draft_pick' value='$draft_pick'>";

    // ==========
    // START GENERAL DECLARANTS
    // ==========
    
    $sql3 = "SELECT * FROM ibl_scout_rookieratings ORDER BY drafted, name";
    $result3 = $db->sql_query($sql3);

    $i = 0;
    while ($row3 = $db->sql_fetchrow($result3)) {
        (($i % 2) == 0) ? $bgcolor = "EEEEEE" : $bgcolor = "DDDDDD";
        $i++;

        $player_pos = $row3['pos'];
        $player_name = $row3['name'];
        $player_team = $row3['team'];
        $player_age = $row3['age'];
        $display_sta = $row3['sta'];
        $display_fga = $row3['fga'];
        $display_fgp = $row3['fgp'];
        $display_fta = $row3['fta'];
        $display_ftp = $row3['ftp'];
        $display_tga = $row3['tga'];
        $display_tgp = $row3['tgp'];
        $display_orb = $row3['orb'];
        $display_drb = $row3['drb'];
        $display_ast = $row3['ast'];
        $display_stl = $row3['stl'];
        $display_tvr = $row3['tvr'];
        $display_blk = $row3['blk'];
        $display_offo = $row3['offo'];
        $display_offd = $row3['offd'];
        $display_offp = $row3['offp'];
        $display_offt = $row3['offt'];
        $display_defo = $row3['defo'];
        $display_defd = $row3['defd'];
        $display_defp = $row3['defp'];
        $display_deft = $row3['deft'];
        $display_tal = $row3['tal'];
        $display_skl = $row3['skl'];
        $display_int = $row3['int'];
        $player_drafted_db = $row3['drafted'];

        $draftedPlayersQuery = "
            SELECT iblhoops_draft.player.`player_name`
              FROM iblhoops_draft.pick
        INNER JOIN iblhoops_draft.player
                ON iblhoops_draft.pick.player_id =
                   iblhoops_draft.player.player_id;";
        $draftedResult = $db->sql_query($draftedPlayersQuery);

        $j = 0;
        while ($draftedPlayersArray[$j] = $db->sql_result($draftedResult, $j)) {
            $j++;
        }

        if (in_array($player_name, $draftedPlayersArray)) {
            $player_drafted = 1;
            $db->sql_query("UPDATE ibl_scout_rookieratings SET drafted = 1 WHERE name = '$player_name';");
            // This query should really be executed in the Draft-O-Matic when the player is drafted, but this works for now.
        } else {
            $player_drafted = 0;
        }

        if ($teamlogo == $draft_team && $player_drafted == 0) {
            echo "<tr bgcolor = $bgcolor><td><input type='radio' name='player' value='$player_name'><td>$player_pos</td><td nowrap>";
        } elseif ($player_drafted == 1 OR $player_drafted_db == 1) {
            echo "<tr><td><td>$player_pos</td><td nowrap>";
        } else {
            echo "<tr bgcolor = $bgcolor><td><td>$player_pos</td><td nowrap>";
        }

        // ====
        // SHOW PLAYER NAME, STRIKE OUT IF DRAFTED ALREADY
        // ====
        if ($player_drafted == 1 OR $player_drafted_db == 1) {
            echo "<strike><i>";
        }

        echo "$player_name";

        if ($player_drafted == 1 OR $player_drafted_db == 1) {
            echo "</i></strike>";
        }

        echo "</td><td>$player_team</td><td>$player_age</td><td>$display_fga</td><td>$display_fgp</td><td>$display_fta</td><td>$display_ftp</td><td>$display_tga</td><td>$display_tgp</td><td>$display_orb</td><td>$display_drb</td><td>$display_ast</td><td>$display_stl</td><td>$display_tvr</td><td>$display_blk</td>";

        if ($display_Off != null) {
            echo "<td colspan=4 border=1><center>$display_Off</center></td>";
        } else {
            echo "<td>$display_offo</td><td>$display_offd</td><td>$display_offp</td><td>$display_offt</td>";
        }

        if ($display_Def != null) {
            echo "<td colspan=4 border=1><center>$display_Def</center></td>";
        } else {
            echo "<td>$display_defo</td><td>$display_defd</td><td>$display_defp</td><td>$display_deft</td>";
        }

        if ($display_Pot != null) {
            echo "<td colspan=3 border=1><center>$display_Pot</center></td><td>$display_sta</td>";
        } else {
            echo "<td>$display_tal</td><td>$display_skl</td><td>$display_int</td><td>$display_sta</td>";
        }

        echo "</tr>";
    }

    echo "</table>";

    // ========
    // END GENERAL DECLARANTS
    // ========

    if ($teamlogo == $draft_team && $player_drafted == 0) {
        echo "</table><input type='submit' value='Draft'></form>";
    } else {
        echo "</table></form>";
    }

    CloseTable();
    include "footer.php";
}

function main($user)
{
    global $stop;
    if (!is_user($user)) {
        include "header.php";
        OpenTable();
        echo "<center><font class=\"title\"><b>" . ($stop ? _LOGININCOR : _USERREGLOGIN) . "</b></font></center>";
        CloseTable();
        echo "<br>";
        if (!is_user($user)) {
            OpenTable();
            loginbox();
            CloseTable();
        }
        include "footer.php";
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        userinfo($cookie[1]);
    }
}

switch ($op) {

    default:
        main($user);
        break;

}
