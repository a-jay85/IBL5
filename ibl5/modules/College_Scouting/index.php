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
/* 3/22/2005                                                             */
/*                                                                      */
/************************************************************************/

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

require_once "mainfile.php";

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$userpage = 1;

//include("modules/$module_name/navbar.php");

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
    $num = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);
    if (!$bypass) {
        cookiedecode($user);
    }

    include "header.php";

//=== TRACK CLICKS TO PICK UP AND MODIFY PLAYER BEING SCOUTED
    if ($step != null) {
        if ($attrib != null) {
            if ($player != null) {

                $sql4 = "SELECT * FROM ibl_scout_points_spent WHERE name LIKE '%$player%' AND teamname = '$userinfo[user_ibl_team]' ORDER BY name DESC";
                $result4 = $db->sql_query($sql4);
                $num4 = $db->sql_numrows($result4);
                $row4 = $db->sql_fetchrow($result4);
                if ($num4 == 0) {
                    if ($userinfo['scoutingpoints'] < 2) {
                        echo "Sorry, not enough scouting points remain to scout that attribute.";
                    } else {

                        $attrib_fga = 0;
                        $attrib_sta = 0;
                        $attrib_fgp = 0;
                        $attrib_fta = 0;
                        $attrib_ftp = 0;
                        $attrib_tga = 0;
                        $attrib_tgp = 0;
                        $attrib_orb = 0;
                        $attrib_drb = 0;
                        $attrib_ast = 0;
                        $attrib_stl = 0;
                        $attrib_tvr = 0;
                        $attrib_blk = 0;
                        $attrib_offo = 0;
                        $attrib_offd = 0;
                        $attrib_offp = 0;
                        $attrib_offt = 0;
                        $attrib_defo = 0;
                        $attrib_defd = 0;
                        $attrib_defp = 0;
                        $attrib_deft = 0;
                        $attrib_Tal = 0;
                        $attrib_Skl = 0;
                        $attrib_Int = 0;

                        if ($attrib == 'fga') {
                            $attrib_fga = 2;
                        } else if ($attrib == 'sta') {
                            $attrib_sta = 2;
                        } else if ($attrib == 'fgp') {
                            $attrib_fgp = 2;
                        } else if ($attrib == 'fta') {
                            $attrib_fta = 2;
                        } else if ($attrib == 'ftp') {
                            $attrib_ftp = 2;
                        } else if ($attrib == 'tga') {
                            $attrib_tga = 2;
                        } else if ($attrib == 'tgp') {
                            $attrib_tgp = 2;
                        } else if ($attrib == 'orb') {
                            $attrib_orb = 2;
                        } else if ($attrib == 'drb') {
                            $attrib_drb = 2;
                        } else if ($attrib == 'ast') {
                            $attrib_ast = 2;
                        } else if ($attrib == 'stl') {
                            $attrib_stl = 2;
                        } else if ($attrib == 'tvr') {
                            $attrib_tvr = 2;
                        } else if ($attrib == 'blk') {
                            $attrib_blk = 2;
                        } else if ($attrib == 'offo') {
                            $attrib_offo = 2;
                            $attrib_offd = 2;
                            $attrib_offp = 2;
                            $attrib_offt = 2;
                        } else if ($attrib == 'defo') {
                            $attrib_defo = 2;
                            $attrib_defd = 2;
                            $attrib_defp = 2;
                            $attrib_deft = 2;
                        } else if ($attrib == 'tal') {
                            $attrib_Tal = 2;
                            $attrib_Skl = 2;
                            $attrib_Int = 2;
                        }

                        $db->sql_query("insert into " . $prefix . "_scout_points_spent values ('$player', '$userinfo[user_ibl_team]', '$attrib_sta', '$attrib_fga', '$attrib_fgp', '$attrib_fta', '$attrib_ftp', '$attrib_tga', '$attrib_tgp', '$attrib_orb', '$attrib_drb', '$attrib_ast', '$attrib_stl', '$attrib_tvr', '$attrib_blk', '$attrib_offo', '$attrib_offd', '$attrib_offp', '$attrib_offt', '$attrib_defo', '$attrib_defd', '$attrib_defp', '$attrib_deft', '$attrib_Tal', '$attrib_Skl', '$attrib_Int')");
                        $newscoutingpoints = $userinfo['scoutingpoints'] - 2;
                        $db->sql_query("update " . $prefix . "_users set scoutingpoints='$newscoutingpoints' where user_id = '$userinfo[user_id]'");
                    }
                } else {
                    if ($step == 1) {

                        if ($userinfo['scoutingpoints'] < 2) {
                            echo "Sorry, not enough scouting points remain to scout that attribute.";
                        } else {

                            if ($row4[$attrib] == 0) {
                                $userinfo['scoutingpoints'] = $userinfo['scoutingpoints'] - 2;
                                $db->sql_query("update " . $prefix . "_users set scoutingpoints='$userinfo[scoutingpoints]' where user_id = '$userinfo[user_id]'");
                                $sql_info = "UPDATE ibl_scout_points_spent SET $attrib = 2 WHERE name = '$player' AND teamname ='$userinfo[user_ibl_team]'";
                                $process = $db->sql_query($sql_info, BEGIN_TRANSACTION);
                                if ($attrib == 'offo') {
                                    $sql_info = "UPDATE ibl_scout_points_spent SET offd = 2 WHERE name = '$player' AND teamname ='$userinfo[user_ibl_team]'";
                                    $process = $db->sql_query($sql_info, BEGIN_TRANSACTION);
                                    $sql_info = "UPDATE ibl_scout_points_spent SET offp = 2 WHERE name = '$player' AND teamname ='$userinfo[user_ibl_team]'";
                                    $process = $db->sql_query($sql_info, BEGIN_TRANSACTION);
                                    $sql_info = "UPDATE ibl_scout_points_spent SET offt = 2 WHERE name = '$player' AND teamname ='$userinfo[user_ibl_team]'";
                                    $process = $db->sql_query($sql_info, BEGIN_TRANSACTION);
                                }
                                if ($attrib == 'defo') {
                                    $sql_info = "UPDATE ibl_scout_points_spent SET defd = 2 WHERE name = '$player' AND teamname ='$userinfo[user_ibl_team]'";
                                    $process = $db->sql_query($sql_info, BEGIN_TRANSACTION);
                                    $sql_info = "UPDATE ibl_scout_points_spent SET defp = 2 WHERE name = '$player' AND teamname ='$userinfo[user_ibl_team]'";
                                    $process = $db->sql_query($sql_info, BEGIN_TRANSACTION);
                                    $sql_info = "UPDATE ibl_scout_points_spent SET deft = 2 WHERE name = '$player' AND teamname ='$userinfo[user_ibl_team]'";
                                    $process = $db->sql_query($sql_info, BEGIN_TRANSACTION);
                                }
                                if ($attrib == 'tal') {
                                    $sql_info = "UPDATE ibl_scout_points_spent SET skl = 2 WHERE name = '$player' AND teamname ='$userinfo[user_ibl_team]'";
                                    $process = $db->sql_query($sql_info, BEGIN_TRANSACTION);
                                    $sql_info = "UPDATE ibl_scout_points_spent SET int = 2 WHERE name = '$player' AND teamname ='$userinfo[user_ibl_team]'";
                                    $process = $db->sql_query($sql_info, BEGIN_TRANSACTION);
                                }
                            } else {
                            }
                        }
                    } else if ($step == 2) {

                        if ($userinfo['scoutingpoints'] < 1) {
                            echo "Sorry, not enough scouting points remain to scout that attribute.";
                        } else {

                            if ($row4[$attrib] == 2) {
                                $userinfo['scoutingpoints'] = $userinfo['scoutingpoints'] - 1;
                                $db->sql_query("update " . $prefix . "_users set scoutingpoints='$userinfo[scoutingpoints]' where user_id = '$userinfo[user_id]'");
                                $sql_info = "UPDATE ibl_scout_points_spent SET $attrib = 3 WHERE name = '$player' AND teamname ='$userinfo[user_ibl_team]'";
                                $process = $db->sql_query($sql_info, BEGIN_TRANSACTION);
                            } else {
                            }
                        }
                    }
                }
            }
        }
    }

//=== END PICK UP AND MODIFY PLAYER BEING SCOUTED

    OpenTable();

    $teamlogo = $userinfo['user_ibl_team'];
    $tid = $sharedFunctions->getTidFromTeamname($teamlogo);

    $sharedFunctions->displaytopmenu($tid);

// ========== DISPLAY ROOKIES

    $scoutingpoints = $userinfo['scoutingpoints'];

    $draft_year = $sharedFunctions->getCurrentSeasonEndingYear();
    $draft_sql = "SELECT * from ibl_draft WHERE player = '' ORDER BY round ASC, pick ASC";
    $draft_result = $db->sql_query($draft_sql);

    $draft_team = $db->sql_result($draft_result, 0, "team");
    $draft_player = $db->sql_result($draft_result, 0, "player");
    $draft_round = $db->sql_result($draft_result, 0, "round");
    $draft_pick = $db->sql_result($draft_result, 0, "pick");

    $queryTeamID = "SELECT teamid FROM ibl_team_info WHERE team_name = '$teamlogo'";
    $tid = $db->sql_result($db->sql_query($queryTeamID), 0);

    echo "<center><img src=\"images/logo/$tid.jpg\"><br>
	<table>
		<tr>
			<th colspan=26>
				<center>Welcome to the $draft_year IBL Draft<br><br><br>SCOUTING CENTRAL - $teamlogo Scouting - $scoutingpoints Scout Points Remaining</center><br>
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

    $sql3 = "SELECT * FROM ibl_scout_rookieratings WHERE invite = '' ORDER BY drafted, name";
    $result3 = $db->sql_query($sql3);

    $i = 0;
    while ($row3 = $db->sql_fetchrow($result3)) {
        (($i % 2) == 0) ? $bgcolor = "EEEEEE" : $bgcolor = "DDDDDD";
        $i++;

        $player_pos = $row3['pos'];
        $player_name = $row3['name'];
        $player_team = $row3['team'];
        $player_age = $row3['age'];
        $player_sta = $row3['sta'];
        $player_fga = $row3['fga'];
        $player_fgp = $row3['fgp'];
        $player_fta = $row3['fta'];
        $player_ftp = $row3['ftp'];
        $player_tga = $row3['tga'];
        $player_tgp = $row3['tgp'];
        $player_orb = $row3['orb'];
        $player_drb = $row3['drb'];
        $player_ast = $row3['ast'];
        $player_stl = $row3['stl'];
        $player_tvr = $row3['tvr'];
        $player_blk = $row3['blk'];
        $player_offo = $row3['offo'];
        $player_offd = $row3['offd'];
        $player_offp = $row3['offp'];
        $player_offt = $row3['offt'];
        $player_defo = $row3['defo'];
        $player_defd = $row3['defd'];
        $player_defp = $row3['defp'];
        $player_deft = $row3['deft'];
        $player_tal = $row3['tal'];
        $player_skl = $row3['skl'];
        $player_int = $row3['int'];
        $player_drafted_db = $row3['drafted'];

        $draftedPlayersQuery = "SELECT iblhoops_draft.player.`player_name`
FROM   iblhoops_draft.pick
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
            $db->sql_query("UPDATE ibl_scout_rookieratings
SET drafted = 1
WHERE name = '$player_name';"); // This query should really be executed in the Draft-O-Matic when the player is drafted, but this works for now.
        } else {
            $player_drafted = 0;
        }

// === GRAB SCOUTING POINT EXPENDITURES ===

        $sqlscouted = "SELECT * FROM ibl_scout_points_spent WHERE name LIKE '%$row3[name]%' AND teamname = '$teamlogo' ORDER BY name ASC ";
        $resultscouted = $db->sql_query($sqlscouted);
        $numscouted = $db->sql_numrows($resultscouted);
        $rowscouted = $db->sql_fetchrow($resultscouted);

// MASKED RATINGS
        // $splayer_sta = $rowscouted[sta];
        // $splayer_fga = $rowscouted[fga];
        // $splayer_fgp = $rowscouted[fgp];
        // $splayer_fta = $rowscouted[fta];
        // $splayer_ftp = $rowscouted[ftp];
        // $splayer_tga = $rowscouted[tga];
        // $splayer_tgp = $rowscouted[tgp];
        // $splayer_orb = $rowscouted[orb];
        // $splayer_drb = $rowscouted[drb];
        // $splayer_ast = $rowscouted[ast];
        // $splayer_stl = $rowscouted[stl];
        // $splayer_tvr = $rowscouted[tvr];
        // $splayer_blk = $rowscouted[blk];
        // $splayer_offo = $rowscouted[offo];
        // $splayer_offd = $rowscouted[offd];
        // $splayer_offp = $rowscouted[offp];
        // $splayer_offt = $rowscouted[offt];
        // $splayer_defo = $rowscouted[defo];
        // $splayer_defd = $rowscouted[defd];
        // $splayer_defp = $rowscouted[defp];
        // $splayer_deft = $rowscouted[deft];
        // $splayer_tal = $rowscouted[tal];
        // $splayer_skl = $rowscouted[skl];
        // $splayer_int = $rowscouted[int];

// UNMASKED RATINGS
        $splayer_sta = 2;
        $splayer_fga = 3;
        $splayer_fgp = 2;
        $splayer_fta = 3;
        $splayer_ftp = 2;
        $splayer_tga = 3;
        $splayer_tgp = 2;
        $splayer_orb = 3;
        $splayer_drb = 3;
        $splayer_ast = 3;
        $splayer_stl = 3;
        $splayer_tvr = 3;
        $splayer_blk = 3;
        $splayer_offo = 3;
        $splayer_offd = 3;
        $splayer_offp = 3;
        $splayer_offt = 3;
        $splayer_defo = 3;
        $splayer_defd = 3;
        $splayer_defp = 3;
        $splayer_deft = 3;
        $splayer_tal = 2;
        $splayer_skl = 2;
        $splayer_int = 2;

        $display_pos = $player_pos;
        $display_name = $player_name;

// ===== BEGIN CODE FOR MASKING THE 1-100 RATINGS

        if ($splayer_fga == 2) {
            $display_fga = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=fga&player=$display_name\">" . ceil($player_fga / 10) . "/10</a>";
        } else if ($splayer_fga == 3) {
            $display_fga = $player_fga;
        } else {
            if ($player_fga < 20) {
                $display_fga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fga&player=$display_name\">F</a>";
            } else if ($player_fga < 40) {
                $display_fga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fga&player=$display_name\">D</a>";
            } else if ($player_fga < 60) {
                $display_fga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fga&player=$display_name\">C</a>";
            } else if ($player_fga < 80) {
                $display_fga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fga&player=$display_name\">B</a>";
            } else {
                $display_fga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fga&player=$display_name\">A</a>";
            }
        }

        if ($splayer_fta == 2) {
            $display_fta = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=fta&player=$display_name\">" . ceil($player_fta / 10) . "/10</a>";
        } else if ($splayer_fta == 3) {
            $display_fta = $player_fta;
        } else {
            if ($player_fta < 20) {
                $display_fta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fta&player=$display_name\">F</a>";
            } else if ($player_fta < 40) {
                $display_fta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fta&player=$display_name\">D</a>";
            } else if ($player_fta < 60) {
                $display_fta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fta&player=$display_name\">C</a>";
            } else if ($player_fta < 80) {
                $display_fta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fta&player=$display_name\">B</a>";
            } else {
                $display_fta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fta&player=$display_name\">A</a>";
            }
        }

        if ($splayer_tga == 2) {
            $display_tga = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=tga&player=$display_name\">" . ceil($player_tga / 10) . "/10</a>";
        } else if ($splayer_tga == 3) {
            $display_tga = $player_tga;
        } else {
            if ($player_tga < 20) {
                $display_tga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tga&player=$display_name\">F</a>";
            } else if ($player_tga < 40) {
                $display_tga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tga&player=$display_name\">D</a>";
            } else if ($player_tga < 60) {
                $display_tga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tga&player=$display_name\">C</a>";
            } else if ($player_tga < 80) {
                $display_tga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tga&player=$display_name\">B</a>";
            } else {
                $display_tga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tga&player=$display_name\">A</a>";
            }
        }

        if ($splayer_orb == 2) {
            $display_orb = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=orb&player=$display_name\">" . ceil($player_orb / 10) . "/10</a>";
        } else if ($splayer_orb == 3) {
            $display_orb = $player_orb;
        } else {
            if ($player_orb < 20) {
                $display_orb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=orb&player=$display_name\">F</a>";
            } else if ($player_orb < 40) {
                $display_orb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=orb&player=$display_name\">D</a>";
            } else if ($player_orb < 60) {
                $display_orb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=orb&player=$display_name\">C</a>";
            } else if ($player_orb < 80) {
                $display_orb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=orb&player=$display_name\">B</a>";
            } else {
                $display_orb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=orb&player=$display_name\">A</a>";
            }
        }

        if ($splayer_drb == 2) {
            $display_drb = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=drb&player=$display_name\">" . ceil($player_drb / 10) . "/10</a>";
        } else if ($splayer_drb == 3) {
            $display_drb = $player_drb;
        } else {
            if ($player_drb < 20) {
                $display_drb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=drb&player=$display_name\">F</a>";
            } else if ($player_drb < 40) {
                $display_drb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=drb&player=$display_name\">D</a>";
            } else if ($player_drb < 60) {
                $display_drb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=drb&player=$display_name\">C</a>";
            } else if ($player_drb < 80) {
                $display_drb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=drb&player=$display_name\">B</a>";
            } else {
                $display_drb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=drb&player=$display_name\">A</a>";
            }
        }

        if ($splayer_ast == 2) {
            $display_ast = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=ast&player=$display_name\">" . ceil($player_ast / 10) . "/10</a>";
        } else if ($splayer_ast == 3) {
            $display_ast = $player_ast;
        } else {
            if ($player_ast < 20) {
                $display_ast = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ast&player=$display_name\">F</a>";
            } else if ($player_ast < 40) {
                $display_ast = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ast&player=$display_name\">D</a>";
            } else if ($player_ast < 60) {
                $display_ast = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ast&player=$display_name\">C</a>";
            } else if ($player_ast < 80) {
                $display_ast = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ast&player=$display_name\">B</a>";
            } else {
                $display_ast = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ast&player=$display_name\">A</a>";
            }
        }

        if ($splayer_stl == 2) {
            $display_stl = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=stl&player=$display_name\">" . ceil($player_stl / 10) . "/10</a>";
        } else if ($splayer_stl == 3) {
            $display_stl = $player_stl;
        } else {
            if ($player_stl < 20) {
                $display_stl = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=stl&player=$display_name\">F</a>";
            } else if ($player_stl < 40) {
                $display_stl = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=stl&player=$display_name\">D</a>";
            } else if ($player_stl < 60) {
                $display_stl = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=stl&player=$display_name\">C</a>";
            } else if ($player_stl < 80) {
                $display_stl = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=stl&player=$display_name\">B</a>";
            } else {
                $display_stl = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=stl&player=$display_name\">A</a>";
            }
        }

        if ($splayer_tvr == 2) {
            $display_tvr = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=tvr&player=$display_name\">" . ceil($player_tvr / 10) . "/10</a>";
        } else if ($splayer_tvr == 3) {
            $display_tvr = $player_tvr;
        } else {
            if ($player_tvr < 20) {
                $display_tvr = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tvr&player=$display_name\">F</a>";
            } else if ($player_tvr < 40) {
                $display_tvr = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tvr&player=$display_name\">D</a>";
            } else if ($player_tvr < 60) {
                $display_tvr = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tvr&player=$display_name\">C</a>";
            } else if ($player_tvr < 80) {
                $display_tvr = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tvr&player=$display_name\">B</a>";
            } else {
                $display_tvr = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tvr&player=$display_name\">A</a>";
            }
        }

        if ($splayer_blk == 2) {
            $display_blk = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=blk&player=$display_name\">" . ceil($player_blk / 10) . "/10</a>";
        } else if ($splayer_blk == 3) {
            $display_blk = $player_blk;
        } else {
            if ($player_blk < 20) {
                $display_blk = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=blk&player=$display_name\">F</a>";
            } else if ($player_blk < 40) {
                $display_blk = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=blk&player=$display_name\">D</a>";
            } else if ($player_blk < 60) {
                $display_blk = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=blk&player=$display_name\">C</a>";
            } else if ($player_blk < 80) {
                $display_blk = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=blk&player=$display_name\">B</a>";
            } else {
                $display_blk = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=blk&player=$display_name\">A</a>";
            }
        }

// === END CODE FOR MASKING THE 1-100 RATINGS
        // === BEGIN CODE FOR MASKING SHOOTING PERCENTAGES

        if ($splayer_fgp == 2) {
            $display_fgp = $player_fgp;
        } else {
            if ($player_fgp < 41) {
                $display_fgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fgp&player=$display_name\">F</a>";
            } else if ($player_fgp < 44) {
                $display_fgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fgp&player=$display_name\">D</a>";
            } else if ($player_fgp < 47) {
                $display_fgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fgp&player=$display_name\">C</a>";
            } else if ($player_fgp < 50) {
                $display_fgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fgp&player=$display_name\">B</a>";
            } else {
                $display_fgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fgp&player=$display_name\">A</a>";
            }
        }

        if ($splayer_ftp == 2) {
            $display_ftp = $player_ftp;
        } else {
            if ($player_ftp < 69) {
                $display_ftp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ftp&player=$display_name\">F</a>";
            } else if ($player_ftp < 73) {
                $display_ftp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ftp&player=$display_name\">D</a>";
            } else if ($player_ftp < 77) {
                $display_ftp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ftp&player=$display_name\">C</a>";
            } else if ($player_ftp < 81) {
                $display_ftp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ftp&player=$display_name\">B</a>";
            } else {
                $display_ftp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ftp&player=$display_name\">A</a>";
            }
        }

        if ($splayer_tgp == 2) {
            $display_tgp = $player_tgp;
        } else {
            if ($player_tgp < 28) {
                $display_tgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tgp&player=$display_name\">F</a>";
            } else if ($player_tgp < 32) {
                $display_tgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tgp&player=$display_name\">D</a>";
            } else if ($player_tgp < 36) {
                $display_tgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tgp&player=$display_name\">C</a>";
            } else if ($player_tgp < 40) {
                $display_tgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tgp&player=$display_name\">B</a>";
            } else {
                $display_tgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tgp&player=$display_name\">A</a>";
            }
        }

        if ($splayer_sta == 2) {
            $display_sta = $player_sta;
        } else {
            if ($player_sta < 11) {
                $display_sta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=sta&player=$display_name\">F</a>";
            } else if ($player_sta < 16) {
                $display_sta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=sta&player=$display_name\">D</a>";
            } else if ($player_sta < 20) {
                $display_sta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=sta&player=$display_name\">C</a>";
            } else if ($player_sta < 25) {
                $display_sta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=sta&player=$display_name\">B</a>";
            } else {
                $display_sta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=sta&player=$display_name\">A</a>";
            }
        }
// === END CODE FOR MASKING SHOOTING PERCENTAGES
        // === BEGIN CODE FOR MASKING OFFENSE AND DEFENSE RATINGS

        $Total_Off = $row3['offo'] + $row3['offd'] + $row3['offp'] + $row3['offt'];
        $Total_Def = $row3['defo'] + $row3['defd'] + $row3['defp'] + $row3['deft'];
        $display_Off = null;
        $display_Def = null;

        if ($splayer_offo == 2) {
            if ($player_offo < 3) {
                $display_offo = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offo&player=$display_name\">D</a>";
            } else if ($player_offo < 6) {
                $display_offo = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offo&player=$display_name\">C</a>";
            } else if ($player_offo < 8) {
                $display_offo = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offo&player=$display_name\">B</a>";
            } else {
                $display_offo = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offo&player=$display_name\">A</a>";
            }
        } else if ($splayer_offo == 3) {
            $display_offo = $player_offo;
        } else {
            if ($Total_Off < 10) {
                $display_Off = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=offo&player=$display_name\">F</a>";
            } else if ($Total_Off < 17) {
                $display_Off = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=offo&player=$display_name\">D</a>";
            } else if ($Total_Off < 24) {
                $display_Off = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=offo&player=$display_name\">C</a>";
            } else if ($Total_Off < 30) {
                $display_Off = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=offo&player=$display_name\">B</a>";
            } else {
                $display_Off = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=offo&player=$display_name\">A</a>";
            }
        }

        if ($splayer_offd == 2) {
            if ($player_offd < 3) {
                $display_offd = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offd&player=$display_name\">D</a>";
            } else if ($player_offd < 6) {
                $display_offd = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offd&player=$display_name\">C</a>";
            } else if ($player_offd < 8) {
                $display_offd = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offd&player=$display_name\">B</a>";
            } else {
                $display_offd = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offd&player=$display_name\">A</a>";
            }
        } else if ($splayer_offd == 3) {
            $display_offd = $player_offd;
        } else {
        }

        if ($splayer_offp == 2) {
            if ($player_offp < 3) {
                $display_offp = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offp&player=$display_name\">D</a>";
            } else if ($player_offp < 6) {
                $display_offp = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offp&player=$display_name\">C</a>";
            } else if ($player_offp < 8) {
                $display_offp = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offp&player=$display_name\">B</a>";
            } else {
                $display_offp = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offp&player=$display_name\">A</a>";
            }
        } else if ($splayer_offp == 3) {
            $display_offp = $player_offp;
        } else {
        }

        if ($splayer_offt == 2) {
            if ($player_offt < 3) {
                $display_offt = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offt&player=$display_name\">D</a>";
            } else if ($player_offt < 6) {
                $display_offt = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offt&player=$display_name\">C</a>";
            } else if ($player_offt < 8) {
                $display_offt = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offt&player=$display_name\">B</a>";
            } else {
                $display_offt = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offt&player=$display_name\">A</a>";
            }
        } else if ($splayer_offt == 3) {
            $display_offt = $player_offt;
        } else {
        }

        if ($splayer_defo == 2) {
            if ($player_defo < 3) {
                $display_defo = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defo&player=$display_name\">D</a>";
            } else if ($player_defo < 6) {
                $display_defo = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defo&player=$display_name\">C</a>";
            } else if ($player_defo < 8) {
                $display_defo = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defo&player=$display_name\">B</a>";
            } else {
                $display_defo = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defo&player=$display_name\">A</a>";
            }
        } else if ($splayer_defo == 3) {
            $display_defo = $player_defo;
        } else {
            if ($Total_Def < 10) {
                $display_Def = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=defo&player=$display_name\">F</a>";
            } else if ($Total_Def < 17) {
                $display_Def = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=defo&player=$display_name\">D</a>";
            } else if ($Total_Def < 24) {
                $display_Def = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=defo&player=$display_name\">C</a>";
            } else if ($Total_Def < 30) {
                $display_Def = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=defo&player=$display_name\">B</a>";
            } else {
                $display_Def = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=defo&player=$display_name\">A</a>";
            }
        }

        if ($splayer_defd == 2) {
            if ($player_defd < 3) {
                $display_defd = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defd&player=$display_name\">D</a>";
            } else if ($player_defd < 6) {
                $display_defd = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defd&player=$display_name\">C</a>";
            } else if ($player_defd < 8) {
                $display_defd = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defd&player=$display_name\">B</a>";
            } else {
                $display_defd = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defd&player=$display_name\">A</a>";
            }
        } else if ($splayer_defd == 3) {
            $display_defd = $player_defd;
        } else {
        }

        if ($splayer_defp == 2) {
            if ($player_defp < 3) {
                $display_defp = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defp&player=$display_name\">D</a>";
            } else if ($player_defp < 6) {
                $display_defp = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defp&player=$display_name\">C</a>";
            } else if ($player_defp < 8) {
                $display_defp = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defp&player=$display_name\">B</a>";
            } else {
                $display_defp = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defp&player=$display_name\">A</a>";
            }
        } else if ($splayer_defp == 3) {
            $display_defp = $player_defp;
        } else {
        }

        if ($splayer_deft == 2) {
            if ($player_deft < 3) {
                $display_deft = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=deft&player=$display_name\">D</a>";
            } else if ($player_deft < 6) {
                $display_deft = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=deft&player=$display_name\">C</a>";
            } else if ($player_deft < 8) {
                $display_deft = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=deft&player=$display_name\">B</a>";
            } else {
                $display_deft = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=deft&player=$display_name\">A</a>";
            }
        } else if ($splayer_deft == 3) {
            $display_deft = $player_deft;
        } else {
        }

        $Total_Pot = $row3['tal'] + $row3['skl'] + $row3['int'];
        $display_Pot = null;

        if ($splayer_tal == 2) {
            $display_tal = $player_tal;
            $display_skl = $player_skl;
            $display_int = $player_int;
        } else {
            if ($Total_Pot < 5) {
                $display_Pot = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tal&player=$display_name\">F</a>";
            } else if ($Total_Pot < 8) {
                $display_Pot = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tal&player=$display_name\">D</a>";
            } else if ($Total_Pot < 11) {
                $display_Pot = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tal&player=$display_name\">C</a>";
            } else if ($Total_Pot < 14) {
                $display_Pot = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tal&player=$display_name\">B</a>";
            } else {
                $display_Pot = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tal&player=$display_name\">A</a>";
            }
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

        echo "</tr>
";

    }

    echo "</table>";

// ========
    // END GENERAL DECLARANTS
    // ========

// ==========
    // START INVITED DECLARANTS
    // ==========

    echo "<tr><th colspan=28><center>Players Your Team has invited</th></tr>
";

    $sql3 = "SELECT * FROM ibl_scout_rookieratings WHERE invite LIKE '%$teamlogo%' ORDER BY ranking DESC";
    $result3 = $db->sql_query($sql3);

    while ($row3 = $db->sql_fetchrow($result3)) {

        $player_pos = $row3['pos'];
        $player_name = $row3['name'];
        $player_team = $row3['team'];
        $player_sta = $row3['sta'];
        $player_age = $row3['age'];
        $player_fga = $row3['fga'];
        $player_fgp = $row3['fgp'];
        $player_fta = $row3['fta'];
        $player_ftp = $row3['ftp'];
        $player_tga = $row3['tga'];
        $player_tgp = $row3['tgp'];
        $player_orb = $row3['orb'];
        $player_drb = $row3['drb'];
        $player_ast = $row3['ast'];
        $player_stl = $row3['stl'];
        $player_tvr = $row3['tvr'];
        $player_blk = $row3['blk'];
        $player_offo = $row3['offo'];
        $player_offd = $row3['offd'];
        $player_offp = $row3['offp'];
        $player_offt = $row3['offt'];
        $player_defo = $row3['defo'];
        $player_defd = $row3['defd'];
        $player_defp = $row3['defp'];
        $player_deft = $row3['deft'];
        $player_tal = $row3['tal'];
        $player_skl = $row3['skl'];
        $player_int = $row3['int'];
        $player_drafted = $row3['drafted'];

// === GRAB SCOUTING POINT EXPENDITURES ===

        $sqlscouted = "SELECT * FROM ibl_scout_points_spent WHERE name = '$row3[name]' AND teamname = '$teamlogo' ORDER BY name ASC ";
        $resultscouted = $db->sql_query($sqlscouted);
        $numscouted = $db->sql_numrows($resultscouted);
        $rowscouted = $db->sql_fetchrow($resultscouted);

        $splayer_sta = $rowscouted['sta'];
        $splayer_fga = $rowscouted['fga'];
        $splayer_fgp = $rowscouted['fgp'];
        $splayer_fta = $rowscouted['fta'];
        $splayer_ftp = $rowscouted['ftp'];
        $splayer_tga = $rowscouted['tga'];
        $splayer_tgp = $rowscouted['tgp'];
        $splayer_orb = $rowscouted['orb'];
        $splayer_drb = $rowscouted['drb'];
        $splayer_ast = $rowscouted['ast'];
        $splayer_stl = $rowscouted['stl'];
        $splayer_tvr = $rowscouted['tvr'];
        $splayer_blk = $rowscouted['blk'];
        $splayer_offo = $rowscouted['offo'];
        $splayer_offd = $rowscouted['offd'];
        $splayer_offp = $rowscouted['offp'];
        $splayer_offt = $rowscouted['offt'];
        $splayer_defo = $rowscouted['defo'];
        $splayer_defd = $rowscouted['defd'];
        $splayer_defp = $rowscouted['defp'];
        $splayer_deft = $rowscouted['deft'];
        $splayer_tal = $rowscouted['tal'];
        $splayer_skl = $rowscouted['skl'];
        $splayer_int = $rowscouted['int'];

        $display_pos = $player_pos;
        $display_name = $player_name;

// ===== BEGIN CODE FOR MASKING THE 1-100 RATINGS

        if ($splayer_fga == 2) {
            $display_fga = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=fga&player=$display_name\">" . ceil($player_fga / 10) . "/10</a>";
        } else if ($splayer_fga == 3) {
            $display_fga = $player_fga;
        } else {
            if ($player_fga < 20) {
                $display_fga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fga&player=$display_name\">F</a>";
            } else if ($player_fga < 40) {
                $display_fga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fga&player=$display_name\">D</a>";
            } else if ($player_fga < 60) {
                $display_fga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fga&player=$display_name\">C</a>";
            } else if ($player_fga < 80) {
                $display_fga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fga&player=$display_name\">B</a>";
            } else {
                $display_fga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fga&player=$display_name\">A</a>";
            }
        }

        if ($splayer_fta == 2) {
            $display_fta = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=fta&player=$display_name\">" . ceil($player_fta / 10) . "/10</a>";
        } else if ($splayer_fta == 3) {
            $display_fta = $player_fta;
        } else {
            if ($player_fta < 20) {
                $display_fta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fta&player=$display_name\">F</a>";
            } else if ($player_fta < 40) {
                $display_fta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fta&player=$display_name\">D</a>";
            } else if ($player_fta < 60) {
                $display_fta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fta&player=$display_name\">C</a>";
            } else if ($player_fta < 80) {
                $display_fta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fta&player=$display_name\">B</a>";
            } else {
                $display_fta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fta&player=$display_name\">A</a>";
            }
        }

        if ($splayer_tga == 2) {
            $display_tga = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=tga&player=$display_name\">" . ceil($player_tga / 10) . "/10</a>";
        } else if ($splayer_tga == 3) {
            $display_tga = $player_tga;
        } else {
            if ($player_tga < 20) {
                $display_tga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tga&player=$display_name\">F</a>";
            } else if ($player_tga < 40) {
                $display_tga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tga&player=$display_name\">D</a>";
            } else if ($player_tga < 60) {
                $display_tga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tga&player=$display_name\">C</a>";
            } else if ($player_tga < 80) {
                $display_tga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tga&player=$display_name\">B</a>";
            } else {
                $display_tga = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tga&player=$display_name\">A</a>";
            }
        }

        if ($splayer_orb == 2) {
            $display_orb = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=orb&player=$display_name\">" . ceil($player_orb / 10) . "/10</a>";
        } else if ($splayer_orb == 3) {
            $display_orb = $player_orb;
        } else {
            if ($player_orb < 20) {
                $display_orb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=orb&player=$display_name\">F</a>";
            } else if ($player_orb < 40) {
                $display_orb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=orb&player=$display_name\">D</a>";
            } else if ($player_orb < 60) {
                $display_orb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=orb&player=$display_name\">C</a>";
            } else if ($player_orb < 80) {
                $display_orb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=orb&player=$display_name\">B</a>";
            } else {
                $display_orb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=orb&player=$display_name\">A</a>";
            }
        }

        if ($splayer_drb == 2) {
            $display_drb = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=drb&player=$display_name\">" . ceil($player_drb / 10) . "/10</a>";
        } else if ($splayer_drb == 3) {
            $display_drb = $player_drb;
        } else {
            if ($player_drb < 20) {
                $display_drb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=drb&player=$display_name\">F</a>";
            } else if ($player_drb < 40) {
                $display_drb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=drb&player=$display_name\">D</a>";
            } else if ($player_drb < 60) {
                $display_drb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=drb&player=$display_name\">C</a>";
            } else if ($player_drb < 80) {
                $display_drb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=drb&player=$display_name\">B</a>";
            } else {
                $display_drb = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=drb&player=$display_name\">A</a>";
            }
        }

        if ($splayer_ast == 2) {
            $display_ast = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=ast&player=$display_name\">" . ceil($player_ast / 10) . "/10</a>";
        } else if ($splayer_ast == 3) {
            $display_ast = $player_ast;
        } else {
            if ($player_ast < 20) {
                $display_ast = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ast&player=$display_name\">F</a>";
            } else if ($player_ast < 40) {
                $display_ast = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ast&player=$display_name\">D</a>";
            } else if ($player_ast < 60) {
                $display_ast = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ast&player=$display_name\">C</a>";
            } else if ($player_ast < 80) {
                $display_ast = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ast&player=$display_name\">B</a>";
            } else {
                $display_ast = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ast&player=$display_name\">A</a>";
            }
        }

        if ($splayer_stl == 2) {
            $display_stl = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=stl&player=$display_name\">" . ceil($player_stl / 10) . "/10</a>";
        } else if ($splayer_stl == 3) {
            $display_stl = $player_stl;
        } else {
            if ($player_stl < 20) {
                $display_stl = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=stl&player=$display_name\">F</a>";
            } else if ($player_stl < 40) {
                $display_stl = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=stl&player=$display_name\">D</a>";
            } else if ($player_stl < 60) {
                $display_stl = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=stl&player=$display_name\">C</a>";
            } else if ($player_stl < 80) {
                $display_stl = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=stl&player=$display_name\">B</a>";
            } else {
                $display_stl = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=stl&player=$display_name\">A</a>";
            }
        }

        if ($splayer_tvr == 2) {
            $display_tvr = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=tvr&player=$display_name\">" . ceil($player_tvr / 10) . "/10</a>";
        } else if ($splayer_tvr == 3) {
            $display_tvr = $player_tvr;
        } else {
            if ($player_tvr < 20) {
                $display_tvr = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tvr&player=$display_name\">F</a>";
            } else if ($player_tvr < 40) {
                $display_tvr = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tvr&player=$display_name\">D</a>";
            } else if ($player_tvr < 60) {
                $display_tvr = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tvr&player=$display_name\">C</a>";
            } else if ($player_tvr < 80) {
                $display_tvr = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tvr&player=$display_name\">B</a>";
            } else {
                $display_tvr = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tvr&player=$display_name\">A</a>";
            }
        }

        if ($splayer_blk == 2) {
            $display_blk = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=blk&player=$display_name\">" . ceil($player_blk / 10) . "/10</a>";
        } else if ($splayer_blk == 3) {
            $display_blk = $player_blk;
        } else {
            if ($player_blk < 20) {
                $display_blk = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=blk&player=$display_name\">F</a>";
            } else if ($player_blk < 40) {
                $display_blk = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=blk&player=$display_name\">D</a>";
            } else if ($player_blk < 60) {
                $display_blk = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=blk&player=$display_name\">C</a>";
            } else if ($player_blk < 80) {
                $display_blk = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=blk&player=$display_name\">B</a>";
            } else {
                $display_blk = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=blk&player=$display_name\">A</a>";
            }
        }

// === END CODE FOR MASKING THE 1-100 RATINGS
        // === BEGIN CODE FOR MASKING SHOOTING PERCENTAGES

        if ($splayer_fgp == 2) {
            $display_fgp = $player_fgp;
        } else {
            if ($player_fgp < 41) {
                $display_fgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fgp&player=$display_name\">F</a>";
            } else if ($player_fgp < 44) {
                $display_fgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fgp&player=$display_name\">D</a>";
            } else if ($player_fgp < 47) {
                $display_fgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fgp&player=$display_name\">C</a>";
            } else if ($player_fgp < 50) {
                $display_fgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fgp&player=$display_name\">B</a>";
            } else {
                $display_fgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=fgp&player=$display_name\">A</a>";
            }
        }

        if ($splayer_ftp == 2) {
            $display_ftp = $player_ftp;
        } else {
            if ($player_ftp < 69) {
                $display_ftp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ftp&player=$display_name\">F</a>";
            } else if ($player_ftp < 73) {
                $display_ftp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ftp&player=$display_name\">D</a>";
            } else if ($player_ftp < 77) {
                $display_ftp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ftp&player=$display_name\">C</a>";
            } else if ($player_ftp < 81) {
                $display_ftp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ftp&player=$display_name\">B</a>";
            } else {
                $display_ftp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=ftp&player=$display_name\">A</a>";
            }
        }

        if ($splayer_tgp == 2) {
            $display_tgp = $player_tgp;
        } else {
            if ($player_tgp < 28) {
                $display_tgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tgp&player=$display_name\">F</a>";
            } else if ($player_tgp < 32) {
                $display_tgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tgp&player=$display_name\">D</a>";
            } else if ($player_tgp < 36) {
                $display_tgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tgp&player=$display_name\">C</a>";
            } else if ($player_tgp < 40) {
                $display_tgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tgp&player=$display_name\">B</a>";
            } else {
                $display_tgp = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tgp&player=$display_name\">A</a>";
            }
        }

        if ($splayer_sta == 2) {
            $display_sta = $player_sta;
        } else {
            if ($player_sta < 11) {
                $display_sta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=sta&player=$display_name\">F</a>";
            } else if ($player_sta < 16) {
                $display_sta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=sta&player=$display_name\">D</a>";
            } else if ($player_sta < 20) {
                $display_sta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=sta&player=$display_name\">C</a>";
            } else if ($player_sta < 25) {
                $display_sta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=sta&player=$display_name\">B</a>";
            } else {
                $display_sta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=sta&player=$display_name\">A</a>";
            }
        }
// === END CODE FOR MASKING SHOOTING PERCENTAGES
        // === BEGIN CODE FOR MASKING OFFENSE AND DEFENSE RATINGS

        $Total_Off = $row3['offo'] + $row3['offd'] + $row3['offp'] + $row3['offt'];
        $Total_Def = $row3['defo'] + $row3['defd'] + $row3['defp'] + $row3['deft'];
        $display_Off = null;
        $display_Def = null;

        if ($splayer_offo == 2) {
            if ($player_offo < 3) {
                $display_offo = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offo&player=$display_name\">D</a>";
            } else if ($player_offo < 6) {
                $display_offo = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offo&player=$display_name\">C</a>";
            } else if ($player_offo < 8) {
                $display_offo = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offo&player=$display_name\">B</a>";
            } else {
                $display_offo = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offo&player=$display_name\">A</a>";
            }
        } else if ($splayer_offo == 3) {
            $display_offo = $player_offo;
        } else {
            if ($Total_Off < 10) {
                $display_Off = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=offo&player=$display_name\">F</a>";
            } else if ($Total_Off < 17) {
                $display_Off = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=offo&player=$display_name\">D</a>";
            } else if ($Total_Off < 24) {
                $display_Off = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=offo&player=$display_name\">C</a>";
            } else if ($Total_Off < 30) {
                $display_Off = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=offo&player=$display_name\">B</a>";
            } else {
                $display_Off = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=offo&player=$display_name\">A</a>";
            }
        }

        if ($splayer_offd == 2) {
            if ($player_offd < 3) {
                $display_offd = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offd&player=$display_name\">D</a>";
            } else if ($player_offd < 6) {
                $display_offd = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offd&player=$display_name\">C</a>";
            } else if ($player_offd < 8) {
                $display_offd = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offd&player=$display_name\">B</a>";
            } else {
                $display_offd = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offd&player=$display_name\">A</a>";
            }
        } else if ($splayer_offd == 3) {
            $display_offd = $player_offd;
        } else {
        }

        if ($splayer_offp == 2) {
            if ($player_offp < 3) {
                $display_offp = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offp&player=$display_name\">D</a>";
            } else if ($player_offp < 6) {
                $display_offp = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offp&player=$display_name\">C</a>";
            } else if ($player_offp < 8) {
                $display_offp = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offp&player=$display_name\">B</a>";
            } else {
                $display_offp = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offp&player=$display_name\">A</a>";
            }
        } else if ($splayer_offp == 3) {
            $display_offp = $player_offp;
        } else {
        }

        if ($splayer_offt == 2) {
            if ($player_offt < 3) {
                $display_offt = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offt&player=$display_name\">D</a>";
            } else if ($player_offt < 6) {
                $display_offt = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offt&player=$display_name\">C</a>";
            } else if ($player_offt < 8) {
                $display_offt = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offt&player=$display_name\">B</a>";
            } else {
                $display_offt = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=offt&player=$display_name\">A</a>";
            }
        } else if ($splayer_offt == 3) {
            $display_offt = $player_offt;
        } else {
        }

        if ($splayer_defo == 2) {
            if ($player_defo < 3) {
                $display_defo = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defo&player=$display_name\">D</a>";
            } else if ($player_defo < 6) {
                $display_defo = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defo&player=$display_name\">C</a>";
            } else if ($player_defo < 8) {
                $display_defo = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defo&player=$display_name\">B</a>";
            } else {
                $display_defo = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defo&player=$display_name\">A</a>";
            }
        } else if ($splayer_defo == 3) {
            $display_defo = $player_defo;
        } else {
            if ($Total_Def < 10) {
                $display_Def = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=defo&player=$display_name\">F</a>";
            } else if ($Total_Def < 17) {
                $display_Def = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=defo&player=$display_name\">D</a>";
            } else if ($Total_Def < 24) {
                $display_Def = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=defo&player=$display_name\">C</a>";
            } else if ($Total_Def < 30) {
                $display_Def = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=defo&player=$display_name\">B</a>";
            } else {
                $display_Def = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=defo&player=$display_name\">A</a>";
            }
        }

        if ($splayer_defd == 2) {
            if ($player_defd < 3) {
                $display_defd = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defd&player=$display_name\">D</a>";
            } else if ($player_defd < 6) {
                $display_defd = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defd&player=$display_name\">C</a>";
            } else if ($player_defd < 8) {
                $display_defd = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defd&player=$display_name\">B</a>";
            } else {
                $display_defd = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defd&player=$display_name\">A</a>";
            }
        } else if ($splayer_defd == 3) {
            $display_defd = $player_defd;
        } else {
        }

        if ($splayer_defp == 2) {
            if ($player_defp < 3) {
                $display_defp = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defp&player=$display_name\">D</a>";
            } else if ($player_defp < 6) {
                $display_defp = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defp&player=$display_name\">C</a>";
            } else if ($player_defp < 8) {
                $display_defp = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defp&player=$display_name\">B</a>";
            } else {
                $display_defp = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=defp&player=$display_name\">A</a>";
            }
        } else if ($splayer_defp == 3) {
            $display_defp = $player_defp;
        } else {
        }

        if ($splayer_deft == 2) {
            if ($player_deft < 3) {
                $display_deft = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=deft&player=$display_name\">D</a>";
            } else if ($player_deft < 6) {
                $display_deft = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=deft&player=$display_name\">C</a>";
            } else if ($player_deft < 8) {
                $display_deft = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=deft&player=$display_name\">B</a>";
            } else {
                $display_deft = "<a href=\"modules.php?name=College_Scouting&step=2&attrib=deft&player=$display_name\">A</a>";
            }
        } else if ($splayer_deft == 3) {
            $display_deft = $player_deft;
        } else {
        }

        $Total_Pot = $row3['tal'] + $row3['skl'] + $row3['int'];
        $display_Pot = null;

        if ($splayer_tal == 2) {
            $display_tal = $player_tal;
            $display_skl = $player_skl;
            $display_int = $player_int;
        } else {
            if ($Total_Pot < 5) {
                $display_Pot = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tal&player=$display_name\">F</a>";
            } else if ($Total_Pot < 8) {
                $display_Pot = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tal&player=$display_name\">D</a>";
            } else if ($Total_Pot < 11) {
                $display_Pot = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tal&player=$display_name\">C</a>";
            } else if ($Total_Pot < 14) {
                $display_Pot = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tal&player=$display_name\">B</a>";
            } else {
                $display_Pot = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=tal&player=$display_name\">A</a>";
            }
        }

        echo "<tr><td>$player_pos</td><td nowrap>";

// ====
        // SHOW PLAYER NAME, STRIKE OUT IF DRAFTED ALREADY
        // ====

        if ($player_drafted == 1 OR $player_drafted_db == 1) {
            echo "<strike>";
        }

        echo "$player_name";

        if ($player_drafted == 1 OR $player_drafted_db == 1) {
            echo "</strike>";
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

        echo "</tr>
";

    }

// ========
    // END INVITED DECLARANTS
    // ========

// ==========
    // START NON-INVITED DECLARANTS
    // ==========

    echo "<tr><th colspan=28><center>Players Invited by Other Teams (cannot be scouted)</th></tr>
";

    $sql3 = "SELECT * FROM ibl_scout_rookieratings WHERE invite NOT LIKE '%$teamlogo%' AND invite != '' ORDER BY ranking DESC";
    $result3 = $db->sql_query($sql3);

    while ($row3 = $db->sql_fetchrow($result3)) {

        $player_pos = $row3['pos'];
        $player_name = $row3['name'];
        $player_team = $row3['team'];
        $player_sta = $row3['sta'];
        $player_age = $row3['age'];
        $player_fga = $row3['fga'];
        $player_fgp = $row3['fgp'];
        $player_fta = $row3['fta'];
        $player_ftp = $row3['ftp'];
        $player_tga = $row3['tga'];
        $player_tgp = $row3['tgp'];
        $player_orb = $row3['orb'];
        $player_drb = $row3['drb'];
        $player_ast = $row3['ast'];
        $player_stl = $row3['stl'];
        $player_tvr = $row3['tvr'];
        $player_blk = $row3['blk'];
        $player_offo = $row3['offo'];
        $player_offd = $row3['offd'];
        $player_offp = $row3['offp'];
        $player_offt = $row3['offt'];
        $player_defo = $row3['defo'];
        $player_defd = $row3['defd'];
        $player_defp = $row3['defp'];
        $player_deft = $row3['deft'];
        $player_tal = $row3['tal'];
        $player_skl = $row3['skl'];
        $player_int = $row3['int'];

        $display_pos = $player_pos;
        $display_name = $player_name;
        $player_drafted = $row3['drafted'];

// ===== BEGIN CODE FOR MASKING THE 1-100 RATINGS

        if ($player_fga < 20) {
            $display_fga = "F";
        } else if ($player_fga < 40) {
            $display_fga = "D";
        } else if ($player_fga < 60) {
            $display_fga = "C";
        } else if ($player_fga < 80) {
            $display_fga = "B";
        } else {
            $display_fga = "A";
        }

        if ($player_fta < 20) {
            $display_fta = "F";
        } else if ($player_fta < 40) {
            $display_fta = "D";
        } else if ($player_fta < 60) {
            $display_fta = "C";
        } else if ($player_fta < 80) {
            $display_fta = "B";
        } else {
            $display_fta = "A";
        }

        if ($player_tga < 20) {
            $display_tga = "F";
        } else if ($player_tga < 40) {
            $display_tga = "D";
        } else if ($player_tga < 60) {
            $display_tga = "C";
        } else if ($player_tga < 80) {
            $display_tga = "B";
        } else {
            $display_tga = "A";
        }

        if ($player_orb < 20) {
            $display_orb = "F";
        } else if ($player_orb < 40) {
            $display_orb = "D";
        } else if ($player_orb < 60) {
            $display_orb = "C";
        } else if ($player_orb < 80) {
            $display_orb = "B";
        } else {
            $display_orb = "A";
        }

        if ($player_drb < 20) {
            $display_drb = "F";
        } else if ($player_drb < 40) {
            $display_drb = "D";
        } else if ($player_drb < 60) {
            $display_drb = "C";
        } else if ($player_drb < 80) {
            $display_drb = "B";
        } else {
            $display_drb = "A";
        }

        if ($player_ast < 20) {
            $display_ast = "F";
        } else if ($player_ast < 40) {
            $display_ast = "D";
        } else if ($player_ast < 60) {
            $display_ast = "C";
        } else if ($player_ast < 80) {
            $display_ast = "B";
        } else {
            $display_ast = "A";
        }

        if ($player_stl < 20) {
            $display_stl = "F";
        } else if ($player_stl < 40) {
            $display_stl = "D";
        } else if ($player_stl < 60) {
            $display_stl = "C";
        } else if ($player_stl < 80) {
            $display_stl = "B";
        } else {
            $display_stl = "A";
        }

        if ($player_tvr < 20) {
            $display_tvr = "F";
        } else if ($player_tvr < 40) {
            $display_tvr = "D";
        } else if ($player_tvr < 60) {
            $display_tvr = "C";
        } else if ($player_tvr < 80) {
            $display_tvr = "B";
        } else {
            $display_tvr = "A";
        }

        if ($player_blk < 20) {
            $display_blk = "F";
        } else if ($player_blk < 40) {
            $display_blk = "D";
        } else if ($player_blk < 60) {
            $display_blk = "C";
        } else if ($player_blk < 80) {
            $display_blk = "B";
        } else {
            $display_blk = "A";
        }

// === END CODE FOR MASKING THE 1-100 RATINGS
        // === BEGIN CODE FOR MASKING SHOOTING PERCENTAGES

        if ($player_fgp < 41) {
            $display_fgp = "F";
        } else if ($player_fgp < 44) {
            $display_fgp = "D";
        } else if ($player_fgp < 47) {
            $display_fgp = "C";
        } else if ($player_fgp < 50) {
            $display_fgp = "B";
        } else {
            $display_fgp = "A";
        }

        if ($player_ftp < 69) {
            $display_ftp = "F";
        } else if ($player_ftp < 73) {
            $display_ftp = "D";
        } else if ($player_ftp < 77) {
            $display_ftp = "C";
        } else if ($player_ftp < 81) {
            $display_ftp = "B";
        } else {
            $display_ftp = "A";
        }

        if ($player_tgp < 28) {
            $display_tgp = "F";
        } else if ($player_tgp < 32) {
            $display_tgp = "D";
        } else if ($player_tgp < 36) {
            $display_tgp = "C";
        } else if ($player_tgp < 40) {
            $display_tgp = "B";
        } else {
            $display_tgp = "A";
        }

        if ($player_sta < 11) {
            $display_sta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=sta&player=$display_name\">F</a>";
        } else if ($player_sta < 16) {
            $display_sta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=sta&player=$display_name\">D</a>";
        } else if ($player_sta < 20) {
            $display_sta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=sta&player=$display_name\">C</a>";
        } else if ($player_sta < 25) {
            $display_sta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=sta&player=$display_name\">B</a>";
        } else {
            $display_sta = "<a href=\"modules.php?name=College_Scouting&step=1&attrib=sta&player=$display_name\">A</a>";
        }

// === END CODE FOR MASKING SHOOTING PERCENTAGES
        // === BEGIN CODE FOR MASKING OFFENSE AND DEFENSE RATINGS

        $Total_Off = $row3['offo'] + $row3['offd'] + $row3['offp'] + $row3['offt'];
        $Total_Def = $row3['defo'] + $row3['defd'] + $row3['defp'] + $row3['deft'];
        $display_Off = null;
        $display_Def = null;

        if ($Total_Off < 10) {
            $display_Off = "F";
        } else if ($Total_Off < 17) {
            $display_Off = "D";
        } else if ($Total_Off < 24) {
            $display_Off = "C";
        } else if ($Total_Off < 30) {
            $display_Off = "B";
        } else {
            $display_Off = "A";
        }

        if ($Total_Def < 10) {
            $display_Def = "F";
        } else if ($Total_Def < 17) {
            $display_Def = "D";
        } else if ($Total_Def < 24) {
            $display_Def = "C";
        } else if ($Total_Def < 30) {
            $display_Def = "B";
        } else {
            $display_Def = "A";
        }

        $Total_Pot = $row3['tal'] + $row3['skl'] + $row3['int'];
        $display_Pot = null;

        if ($Total_Pot < 5) {
            $display_Pot = "F";
        } else if ($Total_Pot < 8) {
            $display_Pot = "D";
        } else if ($Total_Pot < 11) {
            $display_Pot = "C";
        } else if ($Total_Pot < 14) {
            $display_Pot = "B";
        } else {
            $display_Pot = "A";
        }

        echo "<tr><td>$player_pos</td><td nowrap>";

// ====
        // SHOW PLAYER NAME, STRIKE OUT IF DRAFTED ALREADY
        // ====

        if ($player_drafted == 1) {
            echo "<strike>";
        }

        echo "$player_name";

        if ($player_drafted == 1) {
            echo "</strike>";
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

        echo "</tr>
";

    }

// ========
    // END NON-INVITED DECLARANTS
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
