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

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

require_once "mainfile.php";
$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Team Pages";

function team($tid)
{
    global $db;
    $sharedFunctions = new Shared($db);

    $tid = intval($tid);

    $yr = $_REQUEST['yr'];

    $display = $_REQUEST['display'];
    if ($display == null) {
        $display = "ratings";
    }

    include "header.php";
    OpenTable();

    //============================
    // GRAB TEAM COLORS, ET AL
    //============================

    $queryteam = "SELECT * FROM ibl_team_info WHERE teamid = '$tid' ";
    $resultteam = $db->sql_query($queryteam);

    $team_name = $db->sql_result($resultteam, 0, "team_name");
    $color1 = $db->sql_result($resultteam, 0, "color1");
    $color2 = $db->sql_result($resultteam, 0, "color2");
    $owner_name = $db->sql_result($resultteam, 0, "owner_name");

    //=============================
    //DISPLAY TOP MENU
    //=============================

    $sharedFunctions->displaytopmenu($tid);

    //=============================
    //GET CONTRACT AMOUNTS CORRECT
    //=============================

    $queryfaon = "SELECT * FROM nuke_modules WHERE mid = '83' ORDER BY title ASC"; // THIS CHECKS IF FA IS ACTIVE AND HIDES FA PLAYERS IF IT IS
    $resultfaon = $db->sql_query($queryfaon);
    $faon = $db->sql_result($resultfaon, 0, "active");

    if ($tid == 0) { // Team 0 is the Free Agents; we want a query that will pick up all of their players.
        if ($faon == 0) {
            $query = "SELECT * FROM ibl_plr WHERE ordinal > '959' AND retired = 0 ORDER BY ordinal ASC";
            //            $query="SELECT * FROM ibl_plr WHERE tid = 0 AND retired = 0 ORDER BY ordinal ASC";
        } else {
            $query = "SELECT * FROM ibl_plr WHERE ordinal > '959' AND retired = 0 AND cyt != cy ORDER BY ordinal ASC";
            //            $query="SELECT * FROM ibl_plr WHERE tid = 0 AND retired = 0 AND cyt != cy ORDER BY ordinal ASC";
        }
        $result = $db->sql_query($query);
    } else if ($tid == "-1") { // SHOW ENTIRE LEAGUE
        $query = "SELECT * FROM ibl_plr WHERE retired = 0 AND name NOT LIKE '%Buyouts' ORDER BY ordinal ASC";
        $result = $db->sql_query($query);
    } else { // If not Free Agents, use the code below instead.
        if ($yr != "") {
            $query = "SELECT * FROM ibl_hist WHERE teamid = '$tid' AND year = '$yr' ORDER BY name ASC";
        } else if ($faon == 0) {
            $query = "SELECT * FROM ibl_plr WHERE tid = '$tid' AND retired = 0 ORDER BY name ASC";
        } else {
            $query = "SELECT * FROM ibl_plr WHERE tid = '$tid' AND retired = 0 AND cyt != cy ORDER BY name ASC";
        }
        $result = $db->sql_query($query);
    }

    echo "<table><tr><td align=center valign=top><img src=\"./images/logo/$tid.jpg\">";

    if ($yr != "") {
        echo "<center><h1>$yr $team_name</h1></center>";
        $insertyear = "&yr=$yr";
    } else {
        $insertyear = "";
    }

    if ($display == "ratings") {
        $showing = "Player Ratings";
        $table_ratings = $sharedFunctions->ratings($db, $result, $color1, $color2, $tid, $yr);
        $table_output = $table_ratings;
        $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
    } else {
        $tabs .= "<td>";
    }
    $tabs .= "<a href=\"modules.php?name=Team&op=team&tid=$tid&display=ratings$insertyear\">Ratings</a></td>";

    if ($display == "total_s") {
        $showing = "Season Totals";
        $table_totals = seasonTotals($db, $result, $color1, $color2, $tid, $yr, $team_name);
        $table_output = $table_totals;
        $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
    } else {
        $tabs .= "<td>";
    }
    $tabs .= "<a href=\"modules.php?name=Team&op=team&tid=$tid&display=total_s$insertyear\">Season Totals</a></td>";

    if ($display == "avg_s") {
        $showing = "Season Averages";
        $table_averages = seasonAverages($db, $result, $color1, $color2, $tid, $yr, $team_name);
        $table_output = $table_averages;
        $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
    } else {
        $tabs .= "<td>";
    }
    $tabs .= "<a href=\"modules.php?name=Team&op=team&tid=$tid&display=avg_s$insertyear\">Season Averages</a></td>";

    if ($display == "per36mins") {
        $showing = "Per 36 Minutes";
        $table_per36Minutes = per36Minutes($db, $result, $color1, $color2, $tid, $yr);
        $table_output = $table_per36Minutes;
        $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
    } else {
        $tabs .= "<td>";
    }
    $tabs .= "<a href=\"modules.php?name=Team&op=team&tid=$tid&display=per36mins$insertyear\">Per 36 Minutes</a></td>";

    if ($display == "chunk") {
        $showing = "Chunk Averages";
        $table_simAverages = simAverages($db, $sharedFunctions, $color1, $color2, $tid);
        $table_output = $table_simAverages;
        $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
    } else {
        $tabs .= "<td>";
    }
    $tabs .= "<a href=\"modules.php?name=Team&op=team&tid=$tid&display=chunk$insertyear\">Sim Averages</a></td>";

    if ($display == "contracts") {
        $showing = "Contracts";
        $table_contracts = contracts($db, $result, $color1, $color2, $tid, $faon);
        $table_output = $table_contracts;
        $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
    } else {
        $tabs .= "<td>";
    }
    $tabs .= "<a href=\"modules.php?name=Team&op=team&tid=$tid&display=contracts$insertyear\">Contracts</a></td>";

    if ($tid != 0 AND $yr == "") {
        $starters_table = lastSimsStarters($db, $result, $color1, $color2);
    }

    $table_draftpicks = draftPicks($db, $team_name);

    $inforight = team_info_right($team_name, $color1, $color2, $owner_name, $tid);
    $team_info_right = $inforight[0];
    $rafters = $inforight[1];

    echo "<table align=center>
        <tr bgcolor=$color1><td><font color=$color2><b><center>$showing (Sortable by clicking on Column Heading)</center></b></font></td></tr>
		<tr><td align=center><table><tr>$tabs</tr></table></td></tr>
		<tr><td align=center>$table_output</td></tr>
		<tr><td align=center>$starters_table</td></tr>
		<tr bgcolor=$color1><td><font color=$color2><b><center>Draft Picks</center></b></font></td></tr>
		<tr><td>$table_draftpicks</td></tr>
		<tr><td>$rafters</td></tr>
    </table>";

    // TRANSITIONS TO NEXT SIDE OF PAGE
    echo "</td><td valign=top>$team_info_right</td></tr></table>";

    CloseTable();
    include "footer.php";
}

function seasonTotals($db, $result, $color1, $color2, $tid, $yr, $team_name)
{
    $table_totals = "<table align=\"center\" class=\"sortable\">
        <thead>
            <tr bgcolor=$color1>
                <th><font color=$color2>Pos</font></th>
                <th colspan=3><font color=$color2>Player</font></th>
                <th><font color=$color2>g</font></th>
                <th><font color=$color2>gs</font></th>
                <th><font color=$color2>min</font></th>
                <td bgcolor=$color1 width=0></td>
                <th><font color=$color2>fgm</font></th>
                <th><font color=$color2>fga</font></th>
                <td bgcolor=#CCCCCC width=0></td>
                <th><font color=$color2>ftm</font></th>
                <th><font color=$color2>fta</font></th>
                <td bgcolor=#CCCCCC width=0></td>
                <th><font color=$color2>3gm</font></th>
                <th><font color=$color2>3ga</font></th>
                <td bgcolor=$color1 width=0></td>
                <th><font color=$color2>orb</font></th>
                <th><font color=$color2>reb</font></th>
                <th><font color=$color2>ast</font></th>
                <th><font color=$color2>stl</font></th>
                <th><font color=$color2>to</font></th>
                <th><font color=$color2>blk</font></th>
                <th><font color=$color2>pf</font></th>
                <th><font color=$color2>pts</font></th>
            </tr>
        </thead>
    <tbody>";

    $i = 0;
    $num = $db->sql_numrows($result);
    while ($i < $num) {
        $name = $db->sql_result($result, $i, "name");
        $pos = $db->sql_result($result, $i, "pos");
        $p_ord = $db->sql_result($result, $i, "ordinal");
        $pid = $db->sql_result($result, $i, "pid");
        $cy = $db->sql_result($result, $i, "cy");
        $cyt = $db->sql_result($result, $i, "cyt");

        $playerNameDecorated = Shared::decoratePlayerName($name, $tid, $p_ord, $cy, $cyt);

        if ($yr == "") {
            $stats_gm = $db->sql_result($result, $i, "stats_gm");
            $stats_gs = $db->sql_result($result, $i, "stats_gs");
            $stats_min = $db->sql_result($result, $i, "stats_min");
            $stats_fgm = $db->sql_result($result, $i, "stats_fgm");
            $stats_fga = $db->sql_result($result, $i, "stats_fga");
            $stats_ftm = $db->sql_result($result, $i, "stats_ftm");
            $stats_fta = $db->sql_result($result, $i, "stats_fta");
            $stats_tgm = $db->sql_result($result, $i, "stats_3gm");
            $stats_tga = $db->sql_result($result, $i, "stats_3ga");
            $stats_orb = $db->sql_result($result, $i, "stats_orb");
            $stats_drb = $db->sql_result($result, $i, "stats_drb");
            $stats_ast = $db->sql_result($result, $i, "stats_ast");
            $stats_stl = $db->sql_result($result, $i, "stats_stl");
            $stats_to = $db->sql_result($result, $i, "stats_to");
            $stats_blk = $db->sql_result($result, $i, "stats_blk");
            $stats_pf = $db->sql_result($result, $i, "stats_pf");
            $stats_reb = $stats_orb + $stats_drb;
            $stats_pts = 2 * $stats_fgm + $stats_ftm + $stats_tgm;
        } else {
            $stats_gm = $db->sql_result($result, $i, "gm");
            $stats_min = $db->sql_result($result, $i, "min");
            $stats_fgm = $db->sql_result($result, $i, "fgm");
            $stats_fga = $db->sql_result($result, $i, "fga");
            $stats_ftm = $db->sql_result($result, $i, "ftm");
            $stats_fta = $db->sql_result($result, $i, "fta");
            $stats_tgm = $db->sql_result($result, $i, "3gm");
            $stats_tga = $db->sql_result($result, $i, "3ga");
            $stats_orb = $db->sql_result($result, $i, "orb");
            $stats_ast = $db->sql_result($result, $i, "ast");
            $stats_stl = $db->sql_result($result, $i, "stl");
            $stats_to = $db->sql_result($result, $i, "tvr");
            $stats_blk = $db->sql_result($result, $i, "blk");
            $stats_pf = $db->sql_result($result, $i, "pf");
            $stats_reb = $db->sql_result($result, $i, "reb");
            $stats_pts = 2 * $stats_fgm + $stats_ftm + $stats_tgm;
        }

        (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";

        $table_totals .= "<tr bgcolor=$bgcolor>
            <td>$pos</td>
            <td colspan=3><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$playerNameDecorated</a></td>
            <td><center>$stats_gm</center></td>
            <td><center>$stats_gs</center></td>
            <td><center>$stats_min</center></td>
            <td bgcolor=$color1 width=0></td>
            <td><center>$stats_fgm</center></td>
            <td><center>$stats_fga</center></td>
            <td bgcolor=#CCCCCC width=0></td>
            <td><center>$stats_ftm</center></td>
            <td><center>$stats_fta</center></td>
            <td bgcolor=#CCCCCC width=0></td>
            <td><center>$stats_tgm</center></td>
            <td><center>$stats_tga</center></td>
            <td bgcolor=$color1 width=0></td>
            <td><center>$stats_orb</center></td>
            <td><center>$stats_reb</center></td>
            <td><center>$stats_ast</center></td>
            <td><center>$stats_stl</center></td>
            <td><center>$stats_to</center></td>
            <td><center>$stats_blk</center></td>
            <td><center>$stats_pf</center></td>
            <td><center>$stats_pts</center></td>
        </tr>";

        $i++;
    }

    $table_totals .= "</tbody>
        <tfoot>";

    // ==== INSERT TEAM OFFENSE AND DEFENSE TOTALS ====

    $queryTeamOffenseTotals = "SELECT * FROM ibl_team_offense_stats WHERE team = '$team_name' AND year = '1989'";
    $resultTeamOffenseTotals = $db->sql_query($queryTeamOffenseTotals);
    $numTeamOffenseTotals = $db->sql_numrows($resultTeamOffenseTotals);

    $t = 0;

    while ($t < $numTeamOffenseTotals) {
        $team_off_games = $db->sql_result($resultTeamOffenseTotals, $t, "games");
        $team_off_minutes = $db->sql_result($resultTeamOffenseTotals, $t, "minutes");
        $team_off_fgm = $db->sql_result($resultTeamOffenseTotals, $t, "fgm");
        $team_off_fga = $db->sql_result($resultTeamOffenseTotals, $t, "fga");
        $team_off_ftm = $db->sql_result($resultTeamOffenseTotals, $t, "ftm");
        $team_off_fta = $db->sql_result($resultTeamOffenseTotals, $t, "fta");
        $team_off_tgm = $db->sql_result($resultTeamOffenseTotals, $t, "tgm");
        $team_off_tga = $db->sql_result($resultTeamOffenseTotals, $t, "tga");
        $team_off_orb = $db->sql_result($resultTeamOffenseTotals, $t, "orb");
        $team_off_reb = $db->sql_result($resultTeamOffenseTotals, $t, "reb");
        $team_off_ast = $db->sql_result($resultTeamOffenseTotals, $t, "ast");
        $team_off_stl = $db->sql_result($resultTeamOffenseTotals, $t, "stl");
        $team_off_tvr = $db->sql_result($resultTeamOffenseTotals, $t, "tvr");
        $team_off_blk = $db->sql_result($resultTeamOffenseTotals, $t, "blk");
        $team_off_pf = $db->sql_result($resultTeamOffenseTotals, $t, "pf");
        $team_off_pts = $team_off_fgm + $team_off_fgm + $team_off_ftm + $team_off_tgm;

        if ($yr == "") {
            $table_totals .= "<tr>
                <td colspan=4><b>$team_name Offense</td>
                <td><center><b>$team_off_games</center></td>
                <td><center><b>$team_off_games</center></td>
                <td><center><b>$team_off_minutes</center></td>
                <td bgcolor=$color1 width=0></td>
                <td><center><b>$team_off_fgm</center></td>
                <td><center><b>$team_off_fga</b></center></td>
                <td bgcolor=#CCCCCC width=0></td>
                <td><center><b>$team_off_ftm</center></td>
                <td><center><b>$team_off_fta</b></center></td>
                <td bgcolor=#CCCCCC width=0></td>
                <td><center><b>$team_off_tgm</center></td>
                <td><center><b>$team_off_tga</b></center></td>
                <td bgcolor=$color1 width=0></td>
                <td><center><b>$team_off_orb</center></td>
                <td><center><b>$team_off_reb</center></td>
                <td><center><b>$team_off_ast</center></td>
                <td><center><b>$team_off_stl</center></td>
                <td><center><b>$team_off_tvr</center></td>
                <td><center><b>$team_off_blk</center></td>
                <td><center><b>$team_off_pf</center></td>
                <td><center><b>$team_off_pts</center></td>
            </tr>";
        }
        $t++;
    }

    $queryTeamDefenseTotals = "SELECT * FROM ibl_team_defense_stats WHERE team = '$team_name' AND year = '1989'";
    $resultTeamDefenseTotals = $db->sql_query($queryTeamDefenseTotals);
    $numTeamDefenseTotals = $db->sql_numrows($resultTeamDefenseTotals);

    $t = 0;

    while ($t < $numTeamDefenseTotals) {
        $team_def_games = $db->sql_result($resultTeamDefenseTotals, $t, "games");
        $team_def_minutes = $db->sql_result($resultTeamDefenseTotals, $t, "minutes");
        $team_def_fgm = $db->sql_result($resultTeamDefenseTotals, $t, "fgm");
        $team_def_fga = $db->sql_result($resultTeamDefenseTotals, $t, "fga");
        $team_def_ftm = $db->sql_result($resultTeamDefenseTotals, $t, "ftm");
        $team_def_fta = $db->sql_result($resultTeamDefenseTotals, $t, "fta");
        $team_def_tgm = $db->sql_result($resultTeamDefenseTotals, $t, "tgm");
        $team_def_tga = $db->sql_result($resultTeamDefenseTotals, $t, "tga");
        $team_def_orb = $db->sql_result($resultTeamDefenseTotals, $t, "orb");
        $team_def_reb = $db->sql_result($resultTeamDefenseTotals, $t, "reb");
        $team_def_ast = $db->sql_result($resultTeamDefenseTotals, $t, "ast");
        $team_def_stl = $db->sql_result($resultTeamDefenseTotals, $t, "stl");
        $team_def_tvr = $db->sql_result($resultTeamDefenseTotals, $t, "tvr");
        $team_def_blk = $db->sql_result($resultTeamDefenseTotals, $t, "blk");
        $team_def_pf = $db->sql_result($resultTeamDefenseTotals, $t, "pf");
        $team_def_pts = $team_def_fgm + $team_def_fgm + $team_def_ftm + $team_def_tgm;

        if ($yr == "") {
            $table_totals .= "<tr>
                <td colspan=4><b>$team_name Defense</td>
                <td><center><b>$team_def_games</center></td>
                <td><center><b>$team_def_games</center></td>
                <td><center><b>$team_def_minutes</center></td>
                <td bgcolor=$color1 width=0></td>
                <td><center><b>$team_def_fgm</center></td>
                <td><center><b>$team_def_fga</b></center></td>
                <td bgcolor=#CCCCCC width=0></td>
                <td><center><b>$team_def_ftm</center></td>
                <td><center><b>$team_def_fta</b></center></td>
                <td bgcolor=#CCCCCC width=0></td>
                <td><center><b>$team_def_tgm</b></center></td>
                <td><center><b>$team_def_tga</b></center></td>
                <td bgcolor=$color1 width=0></td>
                <td><center><b>$team_def_orb</center></td>
                <td><center><b>$team_def_reb</center></td>
                <td><center><b>$team_def_ast</center></td>
                <td><center><b>$team_def_stl</center></td>
                <td><center><b>$team_def_tvr</center></td>
                <td><center><b>$team_def_blk</center></td>
                <td><center><b>$team_def_pf</center></td>
                <td><center><b>$team_def_pts</center></td>
            </tr>";
        }

        $t++;
    }

    $table_totals .= "</tfoot>
        </table>";

    return $table_totals;
}

function seasonAverages($db, $result, $color1, $color2, $tid, $yr, $team_name)
{
    $table_averages = "<table align=\"center\" class=\"sortable\">
			<thead>
				<tr bgcolor=$color1>
					<th><font color=$color2>Pos</font></th>
					<th colspan=3><font color=$color2>Player</font></th>
					<th><font color=$color2>g</font></th>
					<th><font color=$color2>gs</font></th>
					<th><font color=$color2>min</font></th>
                    <td bgcolor=$color1 width=0></td>
					<th><font color=$color2>fgm</font></th>
					<th><font color=$color2>fga</font></th>
					<th><font color=$color2>fgp</font></th>
                    <td bgcolor=#CCCCCC width=0></td>
					<th><font color=$color2>ftm</font></th>
					<th><font color=$color2>fta</font></th>
					<th><font color=$color2>ftp</font></th>
                    <td bgcolor=#CCCCCC width=0></td>
					<th><font color=$color2>3gm</font></th>
					<th><font color=$color2>3ga</font></th>
					<th><font color=$color2>3gp</font></th>
                    <td bgcolor=$color1 width=0></td>
					<th><font color=$color2>orb</font></th>
					<th><font color=$color2>reb</font></th>
					<th><font color=$color2>ast</font></th>
					<th><font color=$color2>stl</font></th>
					<th><font color=$color2>to</font></th>
					<th><font color=$color2>blk</font></th>
					<th><font color=$color2>pf</font></th>
					<th><font color=$color2>pts</font></th>
				</tr>
			</thead>
		<tbody>";

    /* =======================AVERAGES */

    $i = 0;
    $num = $db->sql_numrows($result);
    while ($i < $num) {
        $name = $db->sql_result($result, $i, "name");
        $pos = $db->sql_result($result, $i, "pos");
        $p_ord = $db->sql_result($result, $i, "ordinal");
        $pid = $db->sql_result($result, $i, "pid");
        $cy = $db->sql_result($result, $i, "cy");
        $cyt = $db->sql_result($result, $i, "cyt");

        $playerNameDecorated = Shared::decoratePlayerName($name, $tid, $p_ord, $cy, $cyt);

        if ($yr == "") {
            $stats_gm = $db->sql_result($result, $i, "stats_gm");
            $stats_gs = $db->sql_result($result, $i, "stats_gs");
            $stats_min = $db->sql_result($result, $i, "stats_min");
            $stats_fgm = $db->sql_result($result, $i, "stats_fgm");
            $stats_fga = $db->sql_result($result, $i, "stats_fga");
            $stats_ftm = $db->sql_result($result, $i, "stats_ftm");
            $stats_fta = $db->sql_result($result, $i, "stats_fta");
            $stats_tgm = $db->sql_result($result, $i, "stats_3gm");
            $stats_tga = $db->sql_result($result, $i, "stats_3ga");
            $stats_orb = $db->sql_result($result, $i, "stats_orb");
            $stats_drb = $db->sql_result($result, $i, "stats_drb");
            $stats_ast = $db->sql_result($result, $i, "stats_ast");
            $stats_stl = $db->sql_result($result, $i, "stats_stl");
            $stats_to = $db->sql_result($result, $i, "stats_to");
            $stats_blk = $db->sql_result($result, $i, "stats_blk");
            $stats_pf = $db->sql_result($result, $i, "stats_pf");
            $stats_reb = $stats_orb + $stats_drb;
            $stats_pts = 2 * $stats_fgm + $stats_ftm + $stats_tgm;
        } else {
            $stats_gm = $db->sql_result($result, $i, "gm");
            $stats_min = $db->sql_result($result, $i, "min");
            $stats_fgm = $db->sql_result($result, $i, "fgm");
            $stats_fga = $db->sql_result($result, $i, "fga");
            $stats_ftm = $db->sql_result($result, $i, "ftm");
            $stats_fta = $db->sql_result($result, $i, "fta");
            $stats_tgm = $db->sql_result($result, $i, "3gm");
            $stats_tga = $db->sql_result($result, $i, "3ga");
            $stats_orb = $db->sql_result($result, $i, "orb");
            $stats_ast = $db->sql_result($result, $i, "ast");
            $stats_stl = $db->sql_result($result, $i, "stl");
            $stats_to = $db->sql_result($result, $i, "tvr");
            $stats_blk = $db->sql_result($result, $i, "blk");
            $stats_pf = $db->sql_result($result, $i, "pf");
            $stats_reb = $db->sql_result($result, $i, "reb");
            $stats_pts = 2 * $stats_fgm + $stats_ftm + $stats_tgm;
        }
        @$stats_fgm = number_format(($stats_fgm / $stats_gm), 1);
        @$stats_fga = number_format(($stats_fga / $stats_gm), 1);
        @$stats_fgp = number_format(($stats_fgm / $stats_fga), 3);
        @$stats_ftm = number_format(($stats_ftm / $stats_gm), 1);
        @$stats_fta = number_format(($stats_fta / $stats_gm), 1);
        @$stats_ftp = number_format(($stats_ftm / $stats_fta), 3);
        @$stats_tgm = number_format(($stats_tgm / $stats_gm), 1);
        @$stats_tga = number_format(($stats_tga / $stats_gm), 1);
        @$stats_tgp = number_format(($stats_tgm / $stats_tga), 3);
        @$stats_mpg = number_format(($stats_min / $stats_gm), 1);
        @$stats_opg = number_format(($stats_orb / $stats_gm), 1);
        @$stats_rpg = number_format(($stats_reb / $stats_gm), 1);
        @$stats_apg = number_format(($stats_ast / $stats_gm), 1);
        @$stats_spg = number_format(($stats_stl / $stats_gm), 1);
        @$stats_tpg = number_format(($stats_to / $stats_gm), 1);
        @$stats_bpg = number_format(($stats_blk / $stats_gm), 1);
        @$stats_fpg = number_format(($stats_pf / $stats_gm), 1);
        @$stats_ppg = number_format(($stats_pts / $stats_gm), 1);

        (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";

        $table_averages .= "<tr bgcolor=$bgcolor>
            <td>$pos</td>
            <td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$playerNameDecorated</a></td>
            <td><center>$stats_gm</center></td>
            <td><center>$stats_gs</center></td>
            <td><center>$stats_mpg</center></td>
            <td bgcolor=$color1 width=0></td>
            <td><center>$stats_fgm</center></td>
            <td><center>$stats_fga</center></td>
            <td><center>$stats_fgp</center></td>
            <td bgcolor=#CCCCCC width=0></td>
            <td><center>$stats_ftm</center></td>
            <td><center>$stats_fta</center></td>
            <td><center>$stats_ftp</center></td>
            <td bgcolor=#CCCCCC width=0></td>
            <td><center>$stats_tgm</center></td>
            <td><center>$stats_tga</center></td>
            <td><center>$stats_tgp</center></td>
            <td bgcolor=$color1 width=0></td>
            <td><center>$stats_opg</center></td>
            <td><center>$stats_rpg</center></td>
            <td><center>$stats_apg</center></td>
            <td><center>$stats_spg</center></td>
            <td><center>$stats_tpg</center></td>
            <td><center>$stats_bpg</center></td>
            <td><center>$stats_fpg</center></td>
            <td><center>$stats_ppg</center></td>
        </tr>";

        $i++;
    }

    // ========= TEAM AVERAGES DISPLAY

    $table_averages = $table_averages . "</tbody><tfoot>";

    $queryTeamOffenseTotals = "SELECT * FROM ibl_team_offense_stats WHERE team = '$team_name' AND year = '1989'";
    $resultTeamOffenseTotals = $db->sql_query($queryTeamOffenseTotals);
    $numTeamOffenseTotals = $db->sql_numrows($resultTeamOffenseTotals);

    $t = 0;

    while ($t < $numTeamOffenseTotals) {
        $team_off_games = $db->sql_result($resultTeamOffenseTotals, $t, "games");
        $team_off_minutes = $db->sql_result($resultTeamOffenseTotals, $t, "minutes");
        $team_off_fgm = $db->sql_result($resultTeamOffenseTotals, $t, "fgm");
        $team_off_fga = $db->sql_result($resultTeamOffenseTotals, $t, "fga");
        @$team_off_fgp = number_format(($team_off_fgm / $team_off_fga), 3);
        $team_off_ftm = $db->sql_result($resultTeamOffenseTotals, $t, "ftm");
        $team_off_fta = $db->sql_result($resultTeamOffenseTotals, $t, "fta");
        @$team_off_ftp = number_format(($team_off_ftm / $team_off_fta), 3);
        $team_off_tgm = $db->sql_result($resultTeamOffenseTotals, $t, "tgm");
        $team_off_tga = $db->sql_result($resultTeamOffenseTotals, $t, "tga");
        @$team_off_tgp = number_format(($team_off_tgm / $team_off_tga), 3);
        $team_off_orb = $db->sql_result($resultTeamOffenseTotals, $t, "orb");
        $team_off_reb = $db->sql_result($resultTeamOffenseTotals, $t, "reb");
        $team_off_ast = $db->sql_result($resultTeamOffenseTotals, $t, "ast");
        $team_off_stl = $db->sql_result($resultTeamOffenseTotals, $t, "stl");
        $team_off_tvr = $db->sql_result($resultTeamOffenseTotals, $t, "tvr");
        $team_off_blk = $db->sql_result($resultTeamOffenseTotals, $t, "blk");
        $team_off_pf = $db->sql_result($resultTeamOffenseTotals, $t, "pf");
        $team_off_pts = $team_off_fgm + $team_off_fgm + $team_off_ftm + $team_off_tgm;

        @$team_off_avgfgm = number_format(($team_off_fgm / $team_off_games), 1);
        @$team_off_avgfga = number_format(($team_off_fga / $team_off_games), 1);
        @$team_off_avgftm = number_format(($team_off_ftm / $team_off_games), 1);
        @$team_off_avgfta = number_format(($team_off_fta / $team_off_games), 1);
        @$team_off_avgtgm = number_format(($team_off_tgm / $team_off_games), 1);
        @$team_off_avgtga = number_format(($team_off_tga / $team_off_games), 1);
        @$team_off_avgmin = number_format(($team_off_minutes / $team_off_games), 1);
        @$team_off_avgorb = number_format(($team_off_orb / $team_off_games), 1);
        @$team_off_avgreb = number_format(($team_off_reb / $team_off_games), 1);
        @$team_off_avgast = number_format(($team_off_ast / $team_off_games), 1);
        @$team_off_avgstl = number_format(($team_off_stl / $team_off_games), 1);
        @$team_off_avgtvr = number_format(($team_off_tvr / $team_off_games), 1);
        @$team_off_avgblk = number_format(($team_off_blk / $team_off_games), 1);
        @$team_off_avgpf = number_format(($team_off_pf / $team_off_games), 1);
        @$team_off_avgpts = number_format(($team_off_pts / $team_off_games), 1);

        if ($yr == "") {
            $table_averages .= "<tr>
                <td colspan=4><b>$team_name Offense</td>
                <td><b><center>$team_off_games</center></td>
                <td><b><center>$team_off_games</center></td>
                <td><center><b>$team_off_avgmin</center></td>
                <td bgcolor=$color1 width=0></td>
                <td><center><b>$team_off_avgfgm</center></td>
                <td><center><b>$team_off_avgfga</center></td>
                <td><center><b>$team_off_fgp</center></td>
                <td bgcolor=#CCCCCC width=0></td>
                <td><center><b>$team_off_avgftm</center></td>
                <td><center><b>$team_off_avgfta</center></td>
                <td><center><b>$team_off_ftp</center></td>
                <td bgcolor=#CCCCCC width=0></td>
                <td><center><b>$team_off_avgtgm</center></td>
                <td><center><b>$team_off_avgtga</center></td>
                <td><center><b>$team_off_tgp</center></td>
                <td bgcolor=$color1 width=0></td>
                <td><center><b>$team_off_avgorb</center></td>
                <td><center><b>$team_off_avgreb</center></td>
                <td><center><b>$team_off_avgast</center></td>
                <td><center><b>$team_off_avgstl</center></td>
                <td><center><b>$team_off_avgtvr</center></td>
                <td><center><b>$team_off_avgblk</center></td>
                <td><center><b>$team_off_avgpf</center></td>
                <td><center><b>$team_off_avgpts</center></td>
            </tr>";
        }
        $t++;
    }

    $queryTeamDefenseTotals = "SELECT * FROM ibl_team_defense_stats WHERE team = '$team_name' AND year = '1989'";
    $resultTeamDefenseTotals = $db->sql_query($queryTeamDefenseTotals);
    $numTeamDefenseTotals = $db->sql_numrows($resultTeamDefenseTotals);

    $t = 0;

    while ($t < $numTeamDefenseTotals) {
        $team_def_games = $db->sql_result($resultTeamDefenseTotals, $t, "games");
        $team_def_minutes = $db->sql_result($resultTeamDefenseTotals, $t, "minutes");
        $team_def_fgm = $db->sql_result($resultTeamDefenseTotals, $t, "fgm");
        $team_def_fga = $db->sql_result($resultTeamDefenseTotals, $t, "fga");
        @$team_def_fgp = number_format(($team_def_fgm / $team_def_fga), 3);
        $team_def_ftm = $db->sql_result($resultTeamDefenseTotals, $t, "ftm");
        $team_def_fta = $db->sql_result($resultTeamDefenseTotals, $t, "fta");
        @$team_def_ftp = number_format(($team_def_ftm / $team_def_fta), 3);
        $team_def_tgm = $db->sql_result($resultTeamDefenseTotals, $t, "tgm");
        $team_def_tga = $db->sql_result($resultTeamDefenseTotals, $t, "tga");
        @$team_def_tgp = number_format(($team_def_tgm / $team_def_tga), 3);
        $team_def_orb = $db->sql_result($resultTeamDefenseTotals, $t, "orb");
        $team_def_reb = $db->sql_result($resultTeamDefenseTotals, $t, "reb");
        $team_def_ast = $db->sql_result($resultTeamDefenseTotals, $t, "ast");
        $team_def_stl = $db->sql_result($resultTeamDefenseTotals, $t, "stl");
        $team_def_tvr = $db->sql_result($resultTeamDefenseTotals, $t, "tvr");
        $team_def_blk = $db->sql_result($resultTeamDefenseTotals, $t, "blk");
        $team_def_pf = $db->sql_result($resultTeamDefenseTotals, $t, "pf");
        $team_def_pts = $team_def_fgm + $team_def_fgm + $team_def_ftm + $team_def_tgm;

        @$team_def_avgfgm = number_format(($team_def_fgm / $team_def_games), 1);
        @$team_def_avgfga = number_format(($team_def_fga / $team_def_games), 1);
        @$team_def_avgftm = number_format(($team_def_ftm / $team_def_games), 1);
        @$team_def_avgfta = number_format(($team_def_fta / $team_def_games), 1);
        @$team_def_avgtgm = number_format(($team_def_tgm / $team_def_games), 1);
        @$team_def_avgtga = number_format(($team_def_tga / $team_def_games), 1);
        @$team_def_avgmin = number_format(($team_def_minutes / $team_def_games), 1);
        @$team_def_avgorb = number_format(($team_def_orb / $team_def_games), 1);
        @$team_def_avgreb = number_format(($team_def_reb / $team_def_games), 1);
        @$team_def_avgast = number_format(($team_def_ast / $team_def_games), 1);
        @$team_def_avgstl = number_format(($team_def_stl / $team_def_games), 1);
        @$team_def_avgtvr = number_format(($team_def_tvr / $team_def_games), 1);
        @$team_def_avgblk = number_format(($team_def_blk / $team_def_games), 1);
        @$team_def_avgpf = number_format(($team_def_pf / $team_def_games), 1);
        @$team_def_avgpts = number_format(($team_def_pts / $team_def_games), 1);

        if ($yr == "") {
            $table_averages .= "<tr>
                <td colspan=4><b>$team_name Defense</td>
                <td><center><b>$team_def_games</center></td>
                <td><b>$team_def_games</td>
                <td><center><b>$team_def_avgmin</center></td>
                <td bgcolor=$color1 width=0></td>
                <td><center><b>$team_def_avgfgm</center></td>
                <td><center><b>$team_def_avgfga</center></td>
                <td><center><b>$team_def_fgp</center></td>
                <td bgcolor=#CCCCCC width=0></td>
                <td><center><b>$team_def_avgftm</center></td>
                <td><center><b>$team_def_avgfta</center></td>
                <td><center><b>$team_def_ftp</center></td>
                <td bgcolor=#CCCCCC width=0></td>
                <td><center><b>$team_def_avgtgm</center></td>
                <td><center><b>$team_def_avgtga</center></td>
                <td><center><b>$team_def_tgp</center></td>
                <td bgcolor=$color1 width=0></td>
                <td><center><b>$team_def_avgorb</center></td>
                <td><center><b>$team_def_avgreb</center></td>
                <td><center><b>$team_def_avgast</center></td>
                <td><center><b>$team_def_avgstl</center></td>
                <td><center><b>$team_def_avgtvr</center></td>
                <td><center><b>$team_def_avgblk</center></td>
                <td><center><b>$team_def_avgpf</center></td>
                <td><center><b>$team_def_avgpts</center></td>
            </tr>";
        }
        $t++;
    }

    $table_averages .= "</tfoot>
        </table>";

    return $table_averages;
}

function per36Minutes($db, $result, $color1, $color2, $tid, $yr)
{
    $table_per36Minutes = "<table align=\"center\" class=\"sortable\">
        <thead>
            <tr bgcolor=$color1>
                <th><font color=$color2>Pos</font></th>
                <th colspan=3><font color=$color2>Player</font></th>
                <th><font color=$color2>g</font></th>
                <th><font color=$color2>gs</font></th>
                <th><font color=$color2>mpg</font></th>
                <th><font color=$color2>36min</font></th>
                <td bgcolor=$color1 width=0></td>
                <th><font color=$color2>fgm</font></th>
                <th><font color=$color2>fga</font></th>
                <th><font color=$color2>fgp</font></th>
                <td bgcolor=#CCCCCC width=0></td>
                <th><font color=$color2>ftm</font></th>
                <th><font color=$color2>fta</font></th>
                <th><font color=$color2>ftp</font></th>
                <td bgcolor=#CCCCCC width=0></td>
                <th><font color=$color2>3gm</font></th>
                <th><font color=$color2>3ga</font></th>
                <th><font color=$color2>3gp</font></th>
                <td bgcolor=$color1 width=0></td>
                <th><font color=$color2>orb</font></th>
                <th><font color=$color2>reb</font></th>
                <th><font color=$color2>ast</font></th>
                <th><font color=$color2>stl</font></th>
                <th><font color=$color2>to</font></th>
                <th><font color=$color2>blk</font></th>
                <th><font color=$color2>pf</font></th>
                <th><font color=$color2>pts</font></th>
            </tr>
        </thead>
    <tbody>";

    /* =======================AVERAGES */

    $i = 0;
    $num = $db->sql_numrows($result);
    while ($i < $num) {
        $name = $db->sql_result($result, $i, "name");
        $pos = $db->sql_result($result, $i, "pos");
        $p_ord = $db->sql_result($result, $i, "ordinal");
        $pid = $db->sql_result($result, $i, "pid");
        $cy = $db->sql_result($result, $i, "cy");
        $cyt = $db->sql_result($result, $i, "cyt");

        $playerNameDecorated = Shared::decoratePlayerName($name, $tid, $p_ord, $cy, $cyt);

        if ($yr == "") {
            $stats_gm = $db->sql_result($result, $i, "stats_gm");
            $stats_gs = $db->sql_result($result, $i, "stats_gs");
            $stats_min = $db->sql_result($result, $i, "stats_min");
            $stats_fgm = $db->sql_result($result, $i, "stats_fgm");
            $stats_fga = $db->sql_result($result, $i, "stats_fga");
            $stats_ftm = $db->sql_result($result, $i, "stats_ftm");
            $stats_fta = $db->sql_result($result, $i, "stats_fta");
            $stats_tgm = $db->sql_result($result, $i, "stats_3gm");
            $stats_tga = $db->sql_result($result, $i, "stats_3ga");
            $stats_orb = $db->sql_result($result, $i, "stats_orb");
            $stats_drb = $db->sql_result($result, $i, "stats_drb");
            $stats_ast = $db->sql_result($result, $i, "stats_ast");
            $stats_stl = $db->sql_result($result, $i, "stats_stl");
            $stats_to = $db->sql_result($result, $i, "stats_to");
            $stats_blk = $db->sql_result($result, $i, "stats_blk");
            $stats_pf = $db->sql_result($result, $i, "stats_pf");
            $stats_reb = $stats_orb + $stats_drb;
            $stats_pts = 2 * $stats_fgm + $stats_ftm + $stats_tgm;
        } else {
            $stats_gm = $db->sql_result($result, $i, "gm");
            $stats_min = $db->sql_result($result, $i, "min");
            $stats_fgm = $db->sql_result($result, $i, "fgm");
            $stats_fga = $db->sql_result($result, $i, "fga");
            $stats_ftm = $db->sql_result($result, $i, "ftm");
            $stats_fta = $db->sql_result($result, $i, "fta");
            $stats_tgm = $db->sql_result($result, $i, "3gm");
            $stats_tga = $db->sql_result($result, $i, "3ga");
            $stats_orb = $db->sql_result($result, $i, "orb");
            $stats_ast = $db->sql_result($result, $i, "ast");
            $stats_stl = $db->sql_result($result, $i, "stl");
            $stats_to = $db->sql_result($result, $i, "tvr");
            $stats_blk = $db->sql_result($result, $i, "blk");
            $stats_pf = $db->sql_result($result, $i, "pf");
            $stats_reb = $db->sql_result($result, $i, "reb");
            $stats_pts = 2 * $stats_fgm + $stats_ftm + $stats_tgm;
        }
        @$stats_fgm = number_format((36 / $stats_min * $stats_fgm), 1);
        @$stats_fga = number_format((36 / $stats_min * $stats_fga), 1);
        @$stats_fgp = number_format(($stats_fgm / $stats_fga), 3);
        @$stats_ftm = number_format((36 / $stats_min * $stats_ftm), 1);
        @$stats_fta = number_format((36 / $stats_min * $stats_fta), 1);
        @$stats_ftp = number_format(($stats_ftm / $stats_fta), 3);
        @$stats_tgm = number_format((36 / $stats_min * $stats_tgm), 1);
        @$stats_tga = number_format((36 / $stats_min * $stats_tga), 1);
        @$stats_tgp = number_format(($stats_tgm / $stats_tga), 3);
        @$stats_mpg = number_format(($stats_min / $stats_gm), 1);
        @$stats_per36Min = number_format((36 / $stats_min * $stats_min), 1);
        @$stats_opg = number_format((36 / $stats_min * $stats_orb), 1);
        @$stats_rpg = number_format((36 / $stats_min * $stats_reb), 1);
        @$stats_apg = number_format((36 / $stats_min * $stats_ast), 1);
        @$stats_spg = number_format((36 / $stats_min * $stats_stl), 1);
        @$stats_tpg = number_format((36 / $stats_min * $stats_to), 1);
        @$stats_bpg = number_format((36 / $stats_min * $stats_blk), 1);
        @$stats_fpg = number_format((36 / $stats_min * $stats_pf), 1);
        @$stats_ppg = number_format((36 / $stats_min * $stats_pts), 1);

        (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";

        $table_per36Minutes .= "<tr bgcolor=$bgcolor>
            <td>$pos</td>
            <td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$playerNameDecorated</a></td>
            <td><center>$stats_gm</center></td>
            <td><center>$stats_gs</center></td>
            <td><center>$stats_mpg</center></td>
            <td><center>$stats_per36Min</center></td>
            <td bgcolor=$color1 width=0></td>
            <td><center>$stats_fgm</center></td>
            <td><center>$stats_fga</center></td>
            <td><center>$stats_fgp</center></td>
            <td bgcolor=#CCCCCC width=0></td>
            <td><center>$stats_ftm</center></td>
            <td><center>$stats_fta</center></td>
            <td><center>$stats_ftp</center></td>
            <td bgcolor=#CCCCCC width=0></td>
            <td><center>$stats_tgm</center></td>
            <td><center>$stats_tga</center></td>
            <td><center>$stats_tgp</center></td>
            <td bgcolor=$color1 width=0></td>
            <td><center>$stats_opg</center></td>
            <td><center>$stats_rpg</center></td>
            <td><center>$stats_apg</center></td>
            <td><center>$stats_spg</center></td>
            <td><center>$stats_tpg</center></td>
            <td><center>$stats_bpg</center></td>
            <td><center>$stats_fpg</center></td>
            <td><center>$stats_ppg</center></td>
        </tr>";

        $i++;
    }

    $table_per36Minutes .= "</tbody>
        </table>";

    return $table_per36Minutes;
}

function simAverages($db, $sharedFunctions, $color1, $color2, $tid)
{
    $table_simAverages = "<table align=\"center\" class=\"sortable\"><thead><tr bgcolor=$color1>
        <th><font color=$color2>Pos</font></th>
        <th colspan=3><font color=$color2>Player</font></th>
        <th><font color=$color2>g</font></th>
        <th><font color=$color2>min</font></th>
        <td bgcolor=$color1 width=0></td>
        <th><font color=$color2>fgm</font></th>
        <th><font color=$color2>fga</font></th>
        <th><font color=$color2>fgp</font></th>
        <td bgcolor=#CCCCCC width=0></td>
        <th><font color=$color2>ftm</font></th>
        <th><font color=$color2>fta</font></th>
        <th><font color=$color2>ftp</font></th>
        <td bgcolor=#CCCCCC width=0></td>
        <th><font color=$color2>3gm</font></th>
        <th><font color=$color2>3ga</font></th>
        <th><font color=$color2>3gp</font></th>
        <td bgcolor=$color1 width=0></td>
        <th><font color=$color2>orb</font></th>
        <th><font color=$color2>reb</font></th>
        <th><font color=$color2>ast</font></th>
        <th><font color=$color2>stl</font></th>
        <th><font color=$color2>to</font></th>
        <th><font color=$color2>blk</font></th>
        <th><font color=$color2>pf</font></th>
        <th><font color=$color2>pts</font></th>
    </tr></thead><tbody>";

    $arrayLastSimDates = $sharedFunctions->getLastSimDatesArray();

    $simStartDate = $arrayLastSimDates['Start Date'];
    $simEndDate = $arrayLastSimDates['End Date'];

    $playersOnTeam = $db->sql_query("SELECT pid
        FROM ibl_plr
        WHERE tid = $tid
        ORDER BY name ASC");
    $numberOfPlayersOnTeam = $db->sql_numrows($playersOnTeam);

    $i = 0;
    while ($i < $numberOfPlayersOnTeam) {
        $pid = $db->sql_result($playersOnTeam, $i);

        // TODO: refactor this so that I'm not cutting and pasting the Player module's Sim Stats code
        $resultPlayerSimBoxScores = $db->sql_query("SELECT *
            FROM ibl_box_scores
            WHERE pid = $pid
            AND Date BETWEEN '$simStartDate' AND '$simEndDate'
            AND gameMIN > 0
            ORDER BY Date ASC");

        $numberOfGamesPlayedInSim = $db->sql_numrows($resultPlayerSimBoxScores);
        $simTotalMIN = 0;
        $simTotal2GM = 0;
        $simTotal2GA = 0;
        $simTotalFTM = 0;
        $simTotalFTA = 0;
        $simTotal3GM = 0;
        $simTotal3GA = 0;
        $simTotalORB = 0;
        $simTotalDRB = 0;
        $simTotalAST = 0;
        $simTotalSTL = 0;
        $simTotalTOV = 0;
        $simTotalBLK = 0;
        $simTotalPF = 0;
        $simTotalPTS = 0;

        if ($numberOfGamesPlayedInSim > 0) {
            while ($row = $db->sql_fetch_assoc($resultPlayerSimBoxScores)) {
                $name = $row['name'];
                $pos = $row['pos'];

                $simTotalMIN += $row['gameMIN'];
                $simTotal2GM += $row['game2GM'];
                $simTotal2GA += $row['game2GA'];
                $simTotalFTM += $row['gameFTM'];
                $simTotalFTA += $row['gameFTA'];
                $simTotal3GM += $row['game3GM'];
                $simTotal3GA += $row['game3GA'];
                $simTotalORB += $row['gameORB'];
                $simTotalDRB += $row['gameDRB'];
                $simTotalAST += $row['gameAST'];
                $simTotalSTL += $row['gameSTL'];
                $simTotalTOV += $row['gameTOV'];
                $simTotalBLK += $row['gameBLK'];
                $simTotalPF += $row['gamePF'];
                $simTotalPTS += (2 * $row['game2GM']) + $row['gameFTM'] + (3 * $row['game3GM']);
            }

            @$simAverageMIN = number_format(($simTotalMIN / $numberOfGamesPlayedInSim), 1);
            @$simAverageFTM = number_format(($simTotalFTM / $numberOfGamesPlayedInSim), 1);
            @$simAverageFTA = number_format(($simTotalFTA / $numberOfGamesPlayedInSim), 1);
            @$simAverageFTP = number_format(($simTotalFTM / $simTotalFTA), 3);
            @$simAverage3GM = number_format(($simTotal3GM / $numberOfGamesPlayedInSim), 1);
            @$simAverage3GA = number_format(($simTotal3GA / $numberOfGamesPlayedInSim), 1);
            @$simAverage3GP = number_format(($simTotal3GM / $simTotal3GA), 3);
            @$simAverageFGM = number_format((($simTotal2GM + $simTotal3GM) / $numberOfGamesPlayedInSim), 1);
            @$simAverageFGA = number_format((($simTotal2GA + $simTotal3GA) / $numberOfGamesPlayedInSim), 1);
            @$simAverageFGP = number_format((($simTotal2GM + $simTotal3GM) / ($simTotal2GA + $simTotal3GA)), 3);
            @$simAverageORB = number_format(($simTotalORB / $numberOfGamesPlayedInSim), 1);
            @$simAverageREB = number_format((($simTotalORB + $simTotalDRB) / $numberOfGamesPlayedInSim), 1);
            @$simAverageAST = number_format(($simTotalAST / $numberOfGamesPlayedInSim), 1);
            @$simAverageSTL = number_format(($simTotalSTL / $numberOfGamesPlayedInSim), 1);
            @$simAverageTOV = number_format(($simTotalTOV / $numberOfGamesPlayedInSim), 1);
            @$simAverageBLK = number_format(($simTotalBLK / $numberOfGamesPlayedInSim), 1);
            @$simAveragePF = number_format(($simTotalPF / $numberOfGamesPlayedInSim), 1);
            @$simAveragePTS = number_format(($simTotalPTS / $numberOfGamesPlayedInSim), 1);

            (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";

            $table_simAverages .= "<tr bgcolor=$bgcolor>
                <td>$pos</td>
                <td colspan=3><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>
                <td><center>$numberOfGamesPlayedInSim</center></td>
                <td><center>$simAverageMIN</center></td>
                <td bgcolor=$color1 width=0></td>
                <td><center>$simAverageFGM</center></td>
                <td><center>$simAverageFGA</center></td>
                <td><center>$simAverageFGP</center></td>
                <td bgcolor=#CCCCCC width=0></td>
                <td><center>$simAverageFTM</center></td>
                <td><center>$simAverageFTA</center></td>
                <td><center>$simAverageFTP</center></td>
                <td bgcolor=#CCCCCC width=0></td>
                <td><center>$simAverage3GM</center></td>
                <td><center>$simAverage3GA</center></td>
                <td><center>$simAverage3GP</center></td>
                <td bgcolor=$color1 width=0></td>
                <td><center>$simAverageORB</center></td>
                <td><center>$simAverageREB</center></td>
                <td><center>$simAverageAST</center></td>
                <td><center>$simAverageSTL</center></td>
                <td><center>$simAverageTOV</center></td>
                <td><center>$simAverageBLK</center></td>
                <td><center>$simAveragePF</center></td>
                <td><center>$simAveragePTS</center></td>
            </tr>";
        }

        $i++;
    }

    $table_simAverages .= "</tbody>
        </table>";

    return $table_simAverages;
}

function contracts($db, $result, $color1, $color2, $tid, $faon)
{
    $table_contracts = "<table align=\"center\" class=\"sortable\">
        <thead>
            <tr bgcolor=$color1>
                <th><font color=$color2>Pos</font></th>
                <th colspan=2><font color=$color2>Player</font></th>
                <th><font color=$color2>Exp</font></th>
                <th><font color=$color2>Bird</font></th>
                <td bgcolor=$color1 width=0></td>
                <th><font color=$color2>Year1</font></th>
                <th><font color=$color2>Year2</font></th>
                <th><font color=$color2>Year3</font></th>
                <th><font color=$color2>Year4</font></th>
                <th><font color=$color2>Year5</font></th>
                <th><font color=$color2>Year6</font></th>
                <td bgcolor=$color1 width=0></td>
                <th><font color=$color2>Tal</font></th>
                <th><font color=$color2>Skl</font></th>
                <th><font color=$color2>Int</font></th>
                <td bgcolor=$color1 width=0></td>
                <th><font color=$color2>Loy</font></th>
                <th><font color=$color2>PFW</font></th>
                <th><font color=$color2>PT</font></th>
                <th><font color=$color2>Sec</font></th>
                <th><font color=$color2>Trad</font></th>
            </tr>
        </thead>
    <tbody>";

    $cap1 = 0;
    $cap2 = 0;
    $cap3 = 0;
    $cap4 = 0;
    $cap5 = 0;
    $cap6 = 0;

    $i = 0;
    $num = $db->sql_numrows($result);
    while ($i < $num) {
        $name = $db->sql_result($result, $i, "name");
        $pos = $db->sql_result($result, $i, "pos");
        $p_ord = $db->sql_result($result, $i, "ordinal");
        $pid = $db->sql_result($result, $i, "pid");
        $cy = $db->sql_result($result, $i, "cy");
        $cyt = $db->sql_result($result, $i, "cyt");
        $exp = $db->sql_result($result, $i, "exp");
        $bird = $db->sql_result($result, $i, "bird");
        $talent = $db->sql_result($result, $i, "talent");
        $skill = $db->sql_result($result, $i, "skill");
        $intangibles = $db->sql_result($result, $i, "intangibles");
        $loyalty = $db->sql_result($result, $i, "loyalty");
        $winner = $db->sql_result($result, $i, "winner");
        $playingTime = $db->sql_result($result, $i, "playingTime");
        $security = $db->sql_result($result, $i, "security");
        $tradition = $db->sql_result($result, $i, "tradition");

        $playerNameDecorated = Shared::decoratePlayerName($name, $tid, $p_ord, $cy, $cyt);

        if ($faon == 0) {
            $year1 = $cy;
            $year2 = $cy + 1;
            $year3 = $cy + 2;
            $year4 = $cy + 3;
            $year5 = $cy + 4;
            $year6 = $cy + 5;
        } else {
            $year1 = $cy + 1;
            $year2 = $cy + 2;
            $year3 = $cy + 3;
            $year4 = $cy + 4;
            $year5 = $cy + 5;
            $year6 = $cy + 6;
        }
        if ($cy == 0) {
            $year1 < 7 ? $con1 = $db->sql_result($result, $i, "cy1") : $con1 = 0;
            $year2 < 7 ? $con2 = $db->sql_result($result, $i, "cy2") : $con2 = 0;
            $year3 < 7 ? $con3 = $db->sql_result($result, $i, "cy3") : $con3 = 0;
            $year4 < 7 ? $con4 = $db->sql_result($result, $i, "cy4") : $con4 = 0;
            $year5 < 7 ? $con5 = $db->sql_result($result, $i, "cy5") : $con5 = 0;
            $year6 < 7 ? $con6 = $db->sql_result($result, $i, "cy6") : $con6 = 0;
        } else {
            $year1 < 7 ? $con1 = $db->sql_result($result, $i, "cy$year1") : $con1 = 0;
            $year2 < 7 ? $con2 = $db->sql_result($result, $i, "cy$year2") : $con2 = 0;
            $year3 < 7 ? $con3 = $db->sql_result($result, $i, "cy$year3") : $con3 = 0;
            $year4 < 7 ? $con4 = $db->sql_result($result, $i, "cy$year4") : $con4 = 0;
            $year5 < 7 ? $con5 = $db->sql_result($result, $i, "cy$year5") : $con5 = 0;
            $year6 < 7 ? $con6 = $db->sql_result($result, $i, "cy$year6") : $con6 = 0;
        }

        (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";

        $table_contracts .= "
            <tr bgcolor=$bgcolor>
            <td align=center>$pos</td>
            <td colspan=2><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$playerNameDecorated</a></td>
            <td align=center>$exp</td>
            <td align=center>$bird</td>
            <td bgcolor=$color1></td>
            <td>$con1</td>
            <td>$con2</td>
            <td>$con3</td>
            <td>$con4</td>
            <td>$con5</td>
            <td>$con6</td>
            <td bgcolor=$color1></td>
            <td align=center>$talent</td>
            <td align=center>$skill</td>
            <td align=center>$intangibles</td>
            <td bgcolor=$color1></td>
            <td align=center>$loyalty</td>
            <td align=center>$winner</td>
            <td align=center>$playingTime</td>
            <td align=center>$security</td>
            <td align=center>$tradition</td>
        </tr>";

        $cap1 += $con1;
        $cap2 += $con2;
        $cap3 += $con3;
        $cap4 += $con4;
        $cap5 += $con5;
        $cap6 += $con6;
        $i++;
    }
    $cap1 = number_format($cap1 / 100, 2);
    $cap2 = number_format($cap2 / 100, 2);
    $cap3 = number_format($cap3 / 100, 2);
    $cap4 = number_format($cap4 / 100, 2);
    $cap5 = number_format($cap5 / 100, 2);
    $cap6 = number_format($cap6 / 100, 2);

    $table_contracts .= "</tbody>
        <tfoot>
            <tr>
                <td></td>
                <td colspan=2><b>Cap Totals</td>
                <td></td>
                <td></td>
                <td bgcolor=$color1></td>
                <td><b>$cap1</td>
                <td><b>$cap2</td>
                <td><b>$cap3</td>
                <td><b>$cap4</td>
                <td><b>$cap5</td>
                <td><b>$cap6</td>
                <td bgcolor=$color1></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td colspan=19><i>Note:</i> Players whose names appear in parenthesis and with a trailing asterisk are waived players that still count against the salary cap.</td>
            </tr>
        </tfoot>
    </table>";

    return $table_contracts;
}

function lastSimsStarters($db, $result, $color1, $color2)
{
    $num = $db->sql_numrows($result);
    $i = 0;
    while ($i < $num) {
        if ($db->sql_result($result, $i, "PGDepth") == 1) {
            $startingPG = $db->sql_result($result, $i, "name");
            $startingPGpid = $db->sql_result($result, $i, "pid");
        }
        if ($db->sql_result($result, $i, "SGDepth") == 1) {
            $startingSG = $db->sql_result($result, $i, "name");
            $startingSGpid = $db->sql_result($result, $i, "pid");
        }
        if ($db->sql_result($result, $i, "SFDepth") == 1) {
            $startingSF = $db->sql_result($result, $i, "name");
            $startingSFpid = $db->sql_result($result, $i, "pid");
        }
        if ($db->sql_result($result, $i, "PFDepth") == 1) {
            $startingPF = $db->sql_result($result, $i, "name");
            $startingPFpid = $db->sql_result($result, $i, "pid");
        }
        if ($db->sql_result($result, $i, "CDepth") == 1) {
            $startingC = $db->sql_result($result, $i, "name");
            $startingCpid = $db->sql_result($result, $i, "pid");
        }
        $i++;
    }

    $starters_table = "<table align=\"center\" border=1 cellpadding=1 cellspacing=1>
        <tr bgcolor=$color1>
            <td colspan=5><font color=$color2><center><b>Last Sim's Starters</b></center></font></td>
        </tr>
        <tr>
            <td><center><b>PG</b><br><img src=\"./images/player/$startingPGpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingPGpid\">$startingPG</a></td>
            <td><center><b>SG</b><br><img src=\"./images/player/$startingSGpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingSGpid\">$startingSG</a></td>
            <td><center><b>SF</b><br><img src=\"./images/player/$startingSFpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingSFpid\">$startingSF</a></td>
            <td><center><b>PF</b><br><img src=\"./images/player/$startingPFpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingPFpid\">$startingPF</a></td>
            <td><center><b>C</b><br><img src=\"./images/player/$startingCpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingCpid\">$startingC</a></td>
        </tr>
    </table>";

    return $starters_table;
}

function draftPicks($db, $team_name)
{
    $table_draftpicks = "<table align=\"center\">";

    $querypicks = "SELECT * FROM ibl_draft_picks WHERE ownerofpick = '$team_name' ORDER BY year, round ASC";
    $resultpicks = $db->sql_query($querypicks);
    $numpicks = $db->sql_numrows($resultpicks);

    $query_all_team_colors = "SELECT * FROM ibl_team_info ORDER BY teamid ASC";
    $colors = $db->sql_query($query_all_team_colors);
    $num_all_team_colors = $db->sql_numrows($colors);

    $i = 0;
    while ($i < $num_all_team_colors) {
        $color_array[$i]['team_id'] = $db->sql_result($colors, $i, "teamid");
        $color_array[$i]['team_city'] = $db->sql_result($colors, $i, "team_city");
        $color_array[$i]['team_name'] = $db->sql_result($colors, $i, "team_name");
        $i++;
    }

    $hh = 0;
    while ($hh < $numpicks) {
        $teampick = $db->sql_result($resultpicks, $hh, "teampick");
        $year = $db->sql_result($resultpicks, $hh, "year");
        $round = $db->sql_result($resultpicks, $hh, "round");

        $j = 0;
        while ($j < $i) {
            $pick_team_name = $color_array[$j]['team_name'];
            if ($pick_team_name == $teampick) {
                $pick_team_id = $color_array[$j]['team_id'];
                $pick_team_city = $color_array[$j]['team_city'];
            }
            $j++;
        }
        $table_draftpicks .= "<tr>
            <td valign=\"center\"><a href=\"modules.php?name=Team&op=team&tid=$pick_team_id\"><img src=\"images/logo/$teampick.png\"></a></td>
            <td valign=\"center\"><a href=\"modules.php?name=Team&op=team&tid=$pick_team_id\">$year $pick_team_city $teampick (Round $round)</a></td>
        </tr>";

        $hh++;
    }

    $table_draftpicks .= "</table>";

    return $table_draftpicks;
}

function team_info_right($team_name, $color1, $color2, $owner_name, $tid)
{
    global $db;

    // ==== GET OWNER INFO

    $output = "<table bgcolor=#eeeeee width=220>";
    $output .= "<tr bgcolor=\"#$color1\"><td align=\"center\">
		<font color=\"#$color2\"><b>Current Season</b></font>
		</td></tr>
		<tr><td>";
    $output .= teamCurrentSeasonStandings($team_name);
    $output .= "</td></tr>";

    //==================
    // GM HISTORY
    //==================

    $owner_award_code = $owner_name . " (" . $team_name . ")";
    $querydec = "SELECT * FROM ibl_gm_history WHERE name LIKE '$owner_award_code' ORDER BY year ASC";
    $resultdec = $db->sql_query($querydec);
    $numdec = $db->sql_numrows($resultdec);
    if ($numdec > 0) {
        $dec = 0;
    }

    $output .= "<tr bgcolor=\"#$color1\"><td align=\"center\">
		<font color=\"#$color2\"><b>GM History</b></font>
		</td></tr>
		<tr><td>";
    while ($dec < $numdec) {
        $dec_year = $db->sql_result($resultdec, $dec, "year");
        $dec_Award = $db->sql_result($resultdec, $dec, "Award");
        $output .= "<table border=0 cellpadding=0 cellspacing=0><tr><td>$dec_year $dec_Award</td></tr></table>";
        $dec++;
    }
    $output .= "</td></tr>";

    // CHAMPIONSHIP BANNERS

    $querybanner = "SELECT * FROM ibl_banners WHERE currentname = '$team_name' ORDER BY year ASC";
    $resultbanner = $db->sql_query($querybanner);
    $numbanner = $db->sql_numrows($resultbanner);

    $j = 0;

    $championships = 0;
    $conference_titles = 0;
    $division_titles = 0;

    $champ_text = "";
    $conf_text = "";
    $div_text = "";

    $ibl_banner = "";
    $conf_banner = "";
    $div_banner = "";

    while ($j < $numbanner) {
        $banneryear = $db->sql_result($resultbanner, $j, "year");
        $bannername = $db->sql_result($resultbanner, $j, "bannername");
        $bannertype = $db->sql_result($resultbanner, $j, "bannertype");

        if ($bannertype == 1) {
            if ($championships % 5 == 0) {
                $ibl_banner .= "<tr><td align=\"center\"><table><tr>";
            }
            $ibl_banner .= "<td><table><tr bgcolor=$color1><td valign=top height=80 width=120 background=\"./images/banners/banner1.gif\"><font color=#$color2>
				<center><b>$banneryear<br>
				$bannername<br>IBL Champions</b></center></td></tr></table></td>";

            $championships++;

            if ($championships % 5 == 0) {
                $ibl_banner .= "</tr></td></table></tr>";
            }

            if ($champ_text == "") {
                $champ_text = "$banneryear";
            } else {
                $champ_text .= ", $banneryear";
            }
            if ($bannername != $team_name) {
                $champ_text .= " (as $bannername)";
            }
        } else if ($bannertype == 2 or $bannertype == 3) {
            if ($conference_titles % 5 == 0) {
                $conf_banner .= "<tr><td align=\"center\"><table><tr>";
            }

            $conf_banner .= "<td><table><tr bgcolor=$color1><td valign=top height=80 width=120 background=\"./images/banners/banner2.gif\"><font color=#$color2>
				<center><b>$banneryear<br>
				$bannername<br>";
            if ($bannertype == 2) {
                $conf_banner .= "Eastern Conf. Champions</b></center></td></tr></table></td>";
            } else {
                $conf_banner .= "Western Conf. Champions</b></center></td></tr></table></td>";
            }

            $conference_titles++;

            if ($conference_titles % 5 == 0) {
                $conf_banner .= "</tr></table></td></tr>";
            }

            if ($conf_text == "") {
                $conf_text = "$banneryear";
            } else {
                $conf_text .= ", $banneryear";
            }
            if ($bannername != $team_name) {
                $conf_text .= " (as $bannername)";
            }
        } else if ($bannertype == 4 or $bannertype == 5 or $bannertype == 6 or $bannertype == 7) {
            if ($division_titles % 5 == 0) {
                $div_banner .= "<tr><td align=\"center\"><table><tr>";
            }
            $div_banner .= "<td><table><tr bgcolor=$color1><td valign=top height=80 width=120><font color=#$color2>
				<center><b>$banneryear<br>
				$bannername<br>";
            if ($bannertype == 4) {
                $div_banner .= "Atlantic Div. Champions</b></center></td></tr></table></td>";
            } else if ($bannertype == 5) {
                $div_banner .= "Central Div. Champions</b></center></td></tr></table></td>";
            } else if ($bannertype == 6) {
                $div_banner .= "Midwest Div. Champions</b></center></td></tr></table></td>";
            } else if ($bannertype == 7) {
                $div_banner .= "Pacific Div. Champions</b></center></td></tr></table></td>";
            }

            $division_titles++;

            if ($division_titles % 5 == 0) {
                $div_banner .= "</tr></table></td></tr>";
            }

            if ($div_text == "") {
                $div_text = "$banneryear";
            } else {
                $div_text .= ", $banneryear";
            }
            if ($bannername != $team_name) {
                $div_text .= " (as $bannername)";
            }
        }
        $j++;
    }

    if (substr($ibl_banner, -23) != "</tr></table></td></tr>" and $ibl_banner != "") {
        $ibl_banner .= "</tr></table></td></tr>";
    }
    if (substr($conf_banner, -23) != "</tr></table></td></tr>" and $conf_banner != "") {
        $conf_banner .= "</tr></table></td></tr>";
    }
    if (substr($div_banner, -23) != "</tr></table></td></tr>" and $div_banner != "") {
        $div_banner .= "</tr></table></td></tr>";
    }

    $banner_output = "";
    if ($ibl_banner != "") {
        $banner_output .= $ibl_banner;
    }
    if ($conf_banner != "") {
        $banner_output .= $conf_banner;
    }
    if ($div_banner != "") {
        $banner_output .= $div_banner;
    }
    if ($banner_output != "") {
        $banner_output = "<center><table><tr><td bgcolor=\"#$color1\" align=\"center\"><font color=\"#$color2\"><h2>$team_name Banners</h2></font></td></tr>" . $banner_output . "</table></center>";
    }

    $ultimate_output[1] = $banner_output;

    /*

    $output=$output."<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"<b>Team Banners</b></font></td></tr>
    <tr><td>$championships IBL Championships: $champ_text</td></tr>
    <tr><td>$conference_titles Conference Championships: $conf_text</td></tr>
    <tr><td>$division_titles Division Titles: $div_text</td></tr>
    ";

     */

    //==================
    // TEAM ACCOMPLISHMENTS
    //==================

    $owner_award_code = $team_name . "";
    $querydec = "SELECT * FROM ibl_team_awards WHERE name LIKE '$owner_award_code' ORDER BY year DESC";
    $resultdec = $db->sql_query($querydec);
    $numdec = $db->sql_numrows($resultdec);
    if ($numdec > 0) {
        $dec = 0;
    }

    $output .= "<tr bgcolor=\"#$color1\"><td align=\"center\">
		<font color=\"#$color2\"><b>Team Accomplishments</b></font>
		</td></tr>
		<tr><td>";
    while ($dec < $numdec) {
        $dec_year = $db->sql_result($resultdec, $dec, "year");
        $dec_Award = $db->sql_result($resultdec, $dec, "Award");
        $output .= "<table border=0 cellpadding=0 cellspacing=0><tr><td>$dec_year $dec_Award</td></tr></table>";
        $dec++;
    }
    $output .= "</td></tr>";

    // REGULAR SEASON RESULTS

    $querywl = "SELECT * FROM ibl_team_win_loss WHERE currentname = '$team_name' ORDER BY year DESC";
    $resultwl = $db->sql_query($querywl);
    $numwl = $db->sql_numrows($resultwl);

    $h = 0;
    $wintot = 0;
    $lostot = 0;

    $output .= "<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>Regular Season History</b></font></td></tr>
		<tr><td><div id=\"History-R\" style=\"overflow:auto\">";

    while ($h < $numwl) {
        $yearwl = $db->sql_result($resultwl, $h, "year");
        $namewl = $db->sql_result($resultwl, $h, "namethatyear");
        $wins = $db->sql_result($resultwl, $h, "wins");
        $losses = $db->sql_result($resultwl, $h, "losses");
        $wintot += $wins;
        $lostot += $losses;
        @$winpct = number_format($wins / ($wins + $losses), 3);
        $output .= "<a href=\"./modules.php?name=Team&op=team&tid=$tid&yr=$yearwl\">" . ($yearwl - 1) . "-$yearwl $namewl</a>: $wins-$losses ($winpct)<br>";

        $h++;
    }
    @$wlpct = number_format($wintot / ($wintot + $lostot), 3);

    $output .= "</div></td></tr>
		<tr><td><b>Totals:</b> $wintot - $lostot ($wlpct)</td></tr>";

    // HEAT SEASON RESULTS

    $querywl = "SELECT * FROM ibl_heat_win_loss WHERE currentname = '$team_name' ORDER BY year DESC";
    $resultwl = $db->sql_query($querywl);
    $numwl = $db->sql_numrows($resultwl);
    $h = 0;
    $wintot = 0;
    $lostot = 0;

    $output .= "<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>H.E.A.T. History</b></font></td></tr>
		<tr><td><div id=\"History-R\" style=\"overflow:auto\">";

    while ($h < $numwl) {
        $yearwl = $db->sql_result($resultwl, $h, "year");
        $namewl = $db->sql_result($resultwl, $h, "namethatyear");
        $wins = $db->sql_result($resultwl, $h, "wins");
        $losses = $db->sql_result($resultwl, $h, "losses");
        $wintot += $wins;
        $lostot += $losses;
        @$winpct = number_format($wins / ($wins + $losses), 3);
        $output .= "<a href=\"./modules.php?name=Team&op=team&tid=$tid&yr=$yearwl\">$yearwl $namewl</a>: $wins-$losses ($winpct)<br>";

        $h++;
    }
    @$wlpct = number_format($wintot / ($wintot + $lostot), 3);

    $output .= "</div></td></tr>
		<tr><td><b>Totals:</b> $wintot - $lostot ($wlpct)</td></tr>";

    // POST-SEASON RESULTS

    $queryplayoffs = "SELECT * FROM ibl_playoff_results ORDER BY year DESC";
    $resultplayoffs = $db->sql_query($queryplayoffs);
    $numplayoffs = $db->sql_numrows($resultplayoffs);

    $pp = 0;
    $totalplayoffwins = 0;
    $totalplayofflosses = 0;
    $first_round_victories = 0;
    $second_round_victories = 0;
    $third_round_victories = 0;
    $fourth_round_victories = 0;
    $first_round_losses = 0;
    $second_round_losses = 0;
    $third_round_losses = 0;
    $fourth_round_losses = 0;

    $round_one_output = "";
    $round_two_output = "";
    $round_three_output = "";
    $round_four_output = "";

    $first_wins = 0;
    $second_wins = 0;
    $third_wins = 0;
    $fourth_wins = 0;
    $first_losses = 0;
    $second_losses = 0;
    $third_losses = 0;
    $fourth_losses = 0;

    while ($pp < $numplayoffs) {
        $playoffround = $db->sql_result($resultplayoffs, $pp, "round");
        $playoffyear = $db->sql_result($resultplayoffs, $pp, "year");
        $playoffwinner = $db->sql_result($resultplayoffs, $pp, "winner");
        $playoffloser = $db->sql_result($resultplayoffs, $pp, "loser");
        $playoffloser_games = $db->sql_result($resultplayoffs, $pp, "loser_games");

        if ($playoffround == 1) {
            if ($playoffwinner == $team_name) {
                $totalplayoffwins += 4;
                $totalplayofflosses += $playoffloser_games;
                $first_wins += 4;
                $first_losses += $playoffloser_games;
                $first_round_victories++;
                $round_one_output .= "$playoffyear - $team_name 4, $playoffloser $playoffloser_games<br>";
            } else if ($playoffloser == $team_name) {
                $totalplayofflosses += 4;
                $totalplayoffwins += $playoffloser_games;
                $first_losses += 4;
                $first_wins += $playoffloser_games;
                $first_round_losses++;
                $round_one_output .= "$playoffyear - $playoffwinner 4, $team_name $playoffloser_games<br>";
            }
        } else if ($playoffround == 2) {
            if ($playoffwinner == $team_name) {
                $totalplayoffwins += 4;
                $totalplayofflosses += $playoffloser_games;
                $second_wins += 4;
                $second_losses += $playoffloser_games;
                $second_round_victories++;
                $round_two_output .= "$playoffyear - $team_name 4, $playoffloser $playoffloser_games<br>";
            } else if ($playoffloser == $team_name) {
                $totalplayofflosses += 4;
                $totalplayoffwins += $playoffloser_games;
                $second_losses += 4;
                $second_wins += $playoffloser_games;
                $second_round_losses++;
                $round_two_output .= "$playoffyear - $playoffwinner 4, $team_name $playoffloser_games<br>";
            }
        } else if ($playoffround == 3) {
            if ($playoffwinner == $team_name) {
                $totalplayoffwins += 4;
                $totalplayofflosses += $playoffloser_games;
                $third_wins += 4;
                $third_losses += $playoffloser_games;
                $third_round_victories++;
                $round_three_output .= "$playoffyear - $team_name 4, $playoffloser $playoffloser_games<br>";
            } else if ($playoffloser == $team_name) {
                $totalplayofflosses += 4;
                $totalplayoffwins += $playoffloser_games;
                $third_losses += 4;
                $third_wins += $playoffloser_games;
                $third_round_losses++;
                $round_three_output .= "$playoffyear - $playoffwinner 4, $team_name $playoffloser_games<br>";
            }
        } else if ($playoffround == 4) {
            if ($playoffwinner == $team_name) {
                $totalplayoffwins += 4;
                $totalplayofflosses += $playoffloser_games;
                $fourth_wins += 4;
                $fourth_losses += $playoffloser_games;
                $fourth_round_victories++;
                $round_four_output .= "$playoffyear - $team_name 4, $playoffloser $playoffloser_games<br>";
            } else if ($playoffloser == $team_name) {
                $totalplayofflosses += 4;
                $totalplayoffwins += $playoffloser_games;
                $fourth_losses += 4;
                $fourth_wins += $playoffloser_games;
                $fourth_round_losses++;
                $round_four_output .= "$playoffyear - $playoffwinner 4, $team_name $playoffloser_games<br>";
            }
        }
        $pp++;
    }

    @$pwlpct = number_format($totalplayoffwins / ($totalplayoffwins + $totalplayofflosses), 3);
    @$r1wlpct = number_format($first_round_victories / ($first_round_victories + $first_round_losses), 3);
    @$r2wlpct = number_format($second_round_victories / ($second_round_victories + $second_round_losses), 3);
    @$r3wlpct = number_format($third_round_victories / ($third_round_victories + $third_round_losses), 3);
    @$r4wlpct = number_format($fourth_round_victories / ($fourth_round_victories + $fourth_round_losses), 3);
    $round_victories = $first_round_victories + $second_round_victories + $third_round_victories + $fourth_round_victories;
    $round_losses = $first_round_losses + $second_round_losses + $third_round_losses + $fourth_round_losses;
    @$swlpct = number_format($round_victories / ($round_victories + $round_losses), 3);
    @$firstpct = number_format($first_wins / ($first_wins + $first_losses), 3);
    @$secondpct = number_format($second_wins / ($second_wins + $second_losses), 3);
    @$thirdpct = number_format($third_wins / ($third_wins + $third_losses), 3);
    @$fourthpct = number_format($fourth_wins / ($fourth_wins + $fourth_losses), 3);

    if ($round_one_output != "") {
        $output .= "<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>First-Round Playoff Results</b></font></td></tr>
			<tr><td>
			<div id=\"History-P1\" style=\"overflow:auto\">" . $round_one_output . "</div></td></tr>
			<tr><td><b>Totals:</b> $first_wins - $first_losses ($firstpct)<br>
			<b>Series:</b> $first_round_victories - $first_round_losses ($r1wlpct)</td></tr>";
    }
    if ($round_two_output != "") {
        $output .= "<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>Conference Semis Playoff Results</b></font></td></tr>
			<tr><td>
			<div id=\"History-P2\" style=\"overflow:auto\">" . $round_two_output . "</div></td></tr>
			<tr><td><b>Totals:</b> $second_wins - $second_losses ($secondpct)<br>
			<b>Series:</b> $second_round_victories - $second_round_losses ($r2wlpct)</td></tr>";
    }
    if ($round_three_output != "") {
        $output .= "<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>Conference Finals Playoff Results</b></font></td></tr>
			<tr><td>
			<div id=\"History-P3\" style=\"overflow:auto\">" . $round_three_output . "</div></td></tr>
			<tr><td><b>Totals:</b> $third_wins - $third_losses ($thirdpct)<br>
			<b>Series:</b> $third_round_victories - $third_round_losses ($r3wlpct)</td></tr>";
    }
    if ($round_four_output != "") {
        $output .= "<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>IBL Finals Playoff Results</b></font></td></tr>
			<tr><td>
			<div id=\"History-P4\" style=\"overflow:auto\">" . $round_four_output . "</div></td></tr>
			<tr><td><b>Totals:</b> $fourth_wins - $fourth_losses ($fourthpct)<br>
			<b>Series:</b> $fourth_round_victories - $fourth_round_losses ($r4wlpct)</td></tr>";
    }

    $output .= "<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>Post-Season Totals</b></font></td></tr>
		<tr><td><b>Games:</b> $totalplayoffwins - $totalplayofflosses ($pwlpct)</td></tr>
		<tr><td><b>Series:</b> $round_victories - $round_losses ($swlpct)</td></tr>
		</table>";

    $ultimate_output[0] = $output;

    return $ultimate_output;
}

function teamCurrentSeasonStandings($team)
{
    global $db;

    $query = "SELECT * FROM ibl_power WHERE Team = '$team'";
    $result = $db->sql_query($query);
    $num = $db->sql_numrows($result);
    $Team = $db->sql_result($result, 0, "Team");
    $win = $db->sql_result($result, 0, "win");
    $loss = $db->sql_result($result, 0, "loss");
    $gb = $db->sql_result($result, 0, "gb");
    $division = $db->sql_result($result, 0, "Division");
    $conference = $db->sql_result($result, 0, "Conference");
    $home_win = $db->sql_result($result, 0, "home_win");
    $home_loss = $db->sql_result($result, 0, "home_loss");
    $road_win = $db->sql_result($result, 0, "road_win");
    $road_loss = $db->sql_result($result, 0, "road_loss");
    $last_win = $db->sql_result($result, 0, "last_win");
    $last_loss = $db->sql_result($result, 0, "last_loss");

    $query2 = "SELECT * FROM ibl_power WHERE Division = '$division' ORDER BY gb DESC";
    $result2 = $db->sql_query($query2);
    $num = $db->sql_numrows($result2);
    $i = 0;
    $gbbase = $db->sql_result($result2, $i, "gb");
    $gb = $gbbase - $gb;
    while ($i < $num) {
        $Team2 = $db->sql_result($result2, $i, "Team");
        if ($Team2 == $Team) {
            $Div_Pos = $i + 1;
        }
        $i++;
    }

    $query3 = "SELECT * FROM ibl_power WHERE Conference = '$conference' ORDER BY gb DESC";
    $result3 = $db->sql_query($query3);
    $num = $db->sql_numrows($result3);
    $i = 0;
    while ($i < $num) {
        $Team3 = $db->sql_result($result3, $i, "Team");
        if ($Team3 == $Team) {
            $Conf_Pos = $i + 1;
        }
        $i++;
    }

    $standings = "<table><tr><td align='right'><b>Team:</td><td>$team</td></tr>
		<tr><td align='right'><b>Record:</td><td>$win-$loss</td></tr>
		<tr><td align='right'><b>Conference:</td><td>$conference</td></tr>
		<tr><td align='right'><b>Conf Position:</td><td>$Conf_Pos</td></tr>
		<tr><td align='right'><b>Division:</td><td>$division</td></tr>
		<tr><td align='right'><b>Div Position:</td><td>$Div_Pos</td></tr>
		<tr><td align='right'><b>GB:</td><td>$gb</td></tr>
		<tr><td align='right'><b>Home Record:</td><td>$home_win-$home_loss</td></tr>
		<tr><td align='right'><b>Road Record:</td><td>$road_win-$road_loss</td></tr>
		<tr><td align='right'><b>Last 10:</td><td>$last_win-$last_loss</td></tr>
	</table>";
    return $standings;
}

function leaguestats()
{
    global $db;

    include "header.php";
    OpenTable();

    $queryteam = "SELECT * FROM ibl_team_info";
    $resultteam = $db->sql_query($queryteam);
    $numteams = $db->sql_numrows($resultteam);

    $n = 0;
    while ($n < $numteams) {
        $teamid[$n] = $db->sql_result($resultteam, $n, "teamid");
        $team_city[$n] = $db->sql_result($resultteam, $n, "team_city");
        $team_name[$n] = $db->sql_result($resultteam, $n, "team_name");
        $coach_pts[$n] = $db->sql_result($resultteam, $n, "Contract_Coach");
        $color1[$n] = $db->sql_result($resultteam, $n, "color1");
        $color2[$n] = $db->sql_result($resultteam, $n, "color2");
        $n++;
    }

    $queryTeamOffenseTotals = "SELECT * FROM ibl_team_offense_stats ORDER BY team ASC";
    $resultTeamOffenseTotals = $db->sql_query($queryTeamOffenseTotals);
    $numTeamOffenseTotals = $db->sql_numrows($resultTeamOffenseTotals);

    $t = 0;
    while ($t < $numTeamOffenseTotals) {
        $team_off_name = $db->sql_result($resultTeamOffenseTotals, $t, "team");
        $m = 0;
        while ($m < $n) {
            if ($team_off_name == $team_name[$m]) {
                $teamcolor1 = $color1[$m];
                $teamcolor2 = $color2[$m];
                $teamcity = $team_city[$m];
                $tid = $teamid[$m];
            }
            $m++;
        }

        $team_off_games = $db->sql_result($resultTeamOffenseTotals, $t, "games");
        $team_off_minutes = $db->sql_result($resultTeamOffenseTotals, $t, "minutes");
        $team_off_fgm = $db->sql_result($resultTeamOffenseTotals, $t, "fgm");
        $team_off_fga = $db->sql_result($resultTeamOffenseTotals, $t, "fga");
        $team_off_ftm = $db->sql_result($resultTeamOffenseTotals, $t, "ftm");
        $team_off_fta = $db->sql_result($resultTeamOffenseTotals, $t, "fta");
        $team_off_tgm = $db->sql_result($resultTeamOffenseTotals, $t, "tgm");
        $team_off_tga = $db->sql_result($resultTeamOffenseTotals, $t, "tga");
        $team_off_orb = $db->sql_result($resultTeamOffenseTotals, $t, "orb");
        $team_off_reb = $db->sql_result($resultTeamOffenseTotals, $t, "reb");
        $team_off_ast = $db->sql_result($resultTeamOffenseTotals, $t, "ast");
        $team_off_stl = $db->sql_result($resultTeamOffenseTotals, $t, "stl");
        $team_off_tvr = $db->sql_result($resultTeamOffenseTotals, $t, "tvr");
        $team_off_blk = $db->sql_result($resultTeamOffenseTotals, $t, "blk");
        $team_off_pf = $db->sql_result($resultTeamOffenseTotals, $t, "pf");
        $team_off_pts = $team_off_fgm + $team_off_fgm + $team_off_ftm + $team_off_tgm;

        @$team_off_avgfgm = number_format($team_off_fgm / $team_off_games, 2);
        @$team_off_avgfga = number_format($team_off_fga / $team_off_games, 2);
        @$team_off_fgp = number_format($team_off_fgm / $team_off_fga, 3);
        @$team_off_avgftm = number_format($team_off_ftm / $team_off_games, 2);
        @$team_off_avgfta = number_format($team_off_fta / $team_off_games, 2);
        @$team_off_ftp = number_format($team_off_ftm / $team_off_fta, 3);
        @$team_off_avgtgm = number_format($team_off_tgm / $team_off_games, 2);
        @$team_off_avgtga = number_format($team_off_tga / $team_off_games, 2);
        @$team_off_tgp = number_format($team_off_tgm / $team_off_tga, 3);
        @$team_off_avgorb = number_format($team_off_orb / $team_off_games, 2);
        @$team_off_avgreb = number_format($team_off_reb / $team_off_games, 2);
        @$team_off_avgast = number_format($team_off_ast / $team_off_games, 2);
        @$team_off_avgstl = number_format($team_off_stl / $team_off_games, 2);
        @$team_off_avgtvr = number_format($team_off_tvr / $team_off_games, 2);
        @$team_off_avgblk = number_format($team_off_blk / $team_off_games, 2);
        @$team_off_avgpf = number_format($team_off_pf / $team_off_games, 2);
        @$team_off_avgpts = number_format($team_off_pts / $team_off_games, 2);

        $lg_off_games += $team_off_games;
        $lg_off_minutes += $team_off_minutes;
        $lg_off_fgm += $team_off_fgm;
        $lg_off_fga += $team_off_fga;
        $lg_off_ftm += $team_off_ftm;
        $lg_off_fta += $team_off_fta;
        $lg_off_tgm += $team_off_tgm;
        $lg_off_tga += $team_off_tga;
        $lg_off_orb += $team_off_orb;
        $lg_off_reb += $team_off_reb;
        $lg_off_ast += $team_off_ast;
        $lg_off_stl += $team_off_stl;
        $lg_off_tvr += $team_off_tvr;
        $lg_off_blk += $team_off_blk;
        $lg_off_pf += $team_off_pf;
        $lg_off_pts += $team_off_pts;

        $offense_totals .= "<tr>
			<td bgcolor=\"$teamcolor1\"><a href=\"modules.php?name=Team&op=team&tid=$tid\"><font color=\"$teamcolor2\">$teamcity $team_off_name Offense</font></a></td>
			<td>$team_off_games</td>
			<td>$team_off_fgm</td>
			<td>$team_off_fga</td>
			<td>$team_off_ftm</td>
			<td>$team_off_fta</td>
			<td>$team_off_tgm</td>
			<td>$team_off_tga</td>
			<td>$team_off_orb</td>
			<td>$team_off_reb</td>
			<td>$team_off_ast</td>
			<td>$team_off_stl</td>
			<td>$team_off_tvr</td>
			<td>$team_off_blk</td>
			<td>$team_off_pf</td>
			<td>$team_off_pts</td>
		</tr>";

        $offense_averages .= "<tr>
			<td bgcolor=\"$teamcolor1\"><a href=\"modules.php?name=Team&op=team&tid=$tid\"><font color=\"$teamcolor2\">$teamcity $team_off_name Offense</font></a></td>
			<td>$team_off_avgfgm</td>
			<td>$team_off_avgfga</td>
			<td>$team_off_fgp</td>
			<td>$team_off_avgftm</td>
			<td>$team_off_avgfta</td>
			<td>$team_off_ftp</td>
			<td>$team_off_avgtgm</td>
			<td>$team_off_avgtga</td>
			<td>$team_off_tgp</td>
			<td>$team_off_avgorb</td>
			<td>$team_off_avgreb</td>
			<td>$team_off_avgast</td>
			<td>$team_off_avgstl</td>
			<td>$team_off_avgtvr</td>
			<td>$team_off_avgblk</td>
			<td>$team_off_avgpf</td>
			<td>$team_off_avgpts</td>
		</tr>";

        $teamHeaderCells[$t] = "<td bgcolor=\"$teamcolor1\"><a href=\"modules.php?name=Team&op=team&tid=$tid\"><font color=\"$teamcolor2\">$teamcity $team_off_name Offense</font></a></td>";
        $teamOffenseAveragesArray[$t] = array(
            $team_off_avgfgm,
			$team_off_avgfga,
			$team_off_fgp,
			$team_off_avgftm,
			$team_off_avgfta,
			$team_off_ftp,
			$team_off_avgtgm,
			$team_off_avgtga,
			$team_off_tgp,
			$team_off_avgorb,
			$team_off_avgreb,
			$team_off_avgast,
			$team_off_avgstl,
			$team_off_avgtvr,
			$team_off_avgblk,
			$team_off_avgpf,
			$team_off_avgpts
        );

        $t++;
    }

    $queryTeamDefenseTotals = "SELECT * FROM ibl_team_defense_stats ORDER BY team ASC";
    $resultTeamDefenseTotals = $db->sql_query($queryTeamDefenseTotals);
    $numTeamDefenseTotals = $db->sql_numrows($resultTeamDefenseTotals);

    $t = 0;
    while ($t < $numTeamDefenseTotals) {
        $team_def_name = $db->sql_result($resultTeamDefenseTotals, $t, "team");
        $m = 0;
        while ($m < $n) {
            if ($team_def_name == $team_name[$m]) {
                $teamcolor1 = $color1[$m];
                $teamcolor2 = $color2[$m];
                $teamcity = $team_city[$m];
                $tid = $teamid[$m];
            }
            $m++;
        }

        $team_def_games = $db->sql_result($resultTeamDefenseTotals, $t, "games");
        $team_def_fgm = $db->sql_result($resultTeamDefenseTotals, $t, "fgm");
        $team_def_fga = $db->sql_result($resultTeamDefenseTotals, $t, "fga");
        $team_def_ftm = $db->sql_result($resultTeamDefenseTotals, $t, "ftm");
        $team_def_fta = $db->sql_result($resultTeamDefenseTotals, $t, "fta");
        $team_def_tgm = $db->sql_result($resultTeamDefenseTotals, $t, "tgm");
        $team_def_tga = $db->sql_result($resultTeamDefenseTotals, $t, "tga");
        $team_def_orb = $db->sql_result($resultTeamDefenseTotals, $t, "orb");
        $team_def_reb = $db->sql_result($resultTeamDefenseTotals, $t, "reb");
        $team_def_ast = $db->sql_result($resultTeamDefenseTotals, $t, "ast");
        $team_def_stl = $db->sql_result($resultTeamDefenseTotals, $t, "stl");
        $team_def_tvr = $db->sql_result($resultTeamDefenseTotals, $t, "tvr");
        $team_def_blk = $db->sql_result($resultTeamDefenseTotals, $t, "blk");
        $team_def_pf = $db->sql_result($resultTeamDefenseTotals, $t, "pf");
        $team_def_pts = $team_def_fgm + $team_def_fgm + $team_def_ftm + $team_def_tgm;

        @$team_def_avgfgm = number_format($team_def_fgm / $team_def_games, 2);
        @$team_def_avgfga = number_format($team_def_fga / $team_def_games, 2);
        @$team_def_fgp = number_format($team_def_fgm / $team_def_fga, 3);
        @$team_def_avgftm = number_format($team_def_ftm / $team_def_games, 2);
        @$team_def_avgfta = number_format($team_def_fta / $team_def_games, 2);
        @$team_def_ftp = number_format($team_def_ftm / $team_def_fta, 3);
        @$team_def_avgtgm = number_format($team_def_tgm / $team_def_games, 2);
        @$team_def_avgtga = number_format($team_def_tga / $team_def_games, 2);
        @$team_def_tgp = number_format($team_def_tgm / $team_def_tga, 3);
        @$team_def_avgorb = number_format($team_def_orb / $team_def_games, 2);
        @$team_def_avgreb = number_format($team_def_reb / $team_def_games, 2);
        @$team_def_avgast = number_format($team_def_ast / $team_def_games, 2);
        @$team_def_avgstl = number_format($team_def_stl / $team_def_games, 2);
        @$team_def_avgtvr = number_format($team_def_tvr / $team_def_games, 2);
        @$team_def_avgblk = number_format($team_def_blk / $team_def_games, 2);
        @$team_def_avgpf = number_format($team_def_pf / $team_def_games, 2);
        @$team_def_avgpts = number_format($team_def_pts / $team_def_games, 2);

        $defense_totals .= "<tr>
			<td bgcolor=\"$teamcolor1\"><a href=\"modules.php?name=Team&op=team&tid=$tid\"><font color=\"$teamcolor2\">$teamcity $team_def_name Defense</font></a></td>
			<td>$team_def_games</td>
			<td>$team_def_fgm</td>
			<td>$team_def_fga</td>
			<td>$team_def_ftm</td>
			<td>$team_def_fta</td>
			<td>$team_def_tgm</td>
			<td>$team_def_tga</td>
			<td>$team_def_orb</td>
			<td>$team_def_reb</td>
			<td>$team_def_ast</td>
			<td>$team_def_stl</td>
			<td>$team_def_tvr</td>
			<td>$team_def_blk</td>
			<td>$team_def_pf</td>
			<td>$team_def_pts</td>
		</tr>";

        $defense_averages .= "<tr>
			<td bgcolor=\"$teamcolor1\"><a href=\"modules.php?name=Team&op=team&tid=$tid\"><font color=\"$teamcolor2\">$teamcity $team_def_name Defense</font></a></td>
			<td>$team_def_avgfgm</td>
			<td>$team_def_avgfga</td>
			<td>$team_def_fgp</td>
			<td>$team_def_avgftm</td>
			<td>$team_def_avgfta</td>
			<td>$team_def_ftp</td>
			<td>$team_def_avgtgm</td>
			<td>$team_def_avgtga</td>
			<td>$team_def_tgp</td>
			<td>$team_def_avgorb</td>
			<td>$team_def_avgreb</td>
			<td>$team_def_avgast</td>
			<td>$team_def_avgstl</td>
			<td>$team_def_avgtvr</td>
			<td>$team_def_avgblk</td>
			<td>$team_def_avgpf</td>
			<td>$team_def_avgpts</td>
		</tr>";

        $teamDefenseAveragesArray[$t] = array(
            $team_def_avgfgm,
			$team_def_avgfga,
			$team_def_fgp,
			$team_def_avgftm,
			$team_def_avgfta,
			$team_def_ftp,
			$team_def_avgtgm,
			$team_def_avgtga,
			$team_def_tgp,
			$team_def_avgorb,
			$team_def_avgreb,
			$team_def_avgast,
			$team_def_avgstl,
			$team_def_avgtvr,
			$team_def_avgblk,
			$team_def_avgpf,
			$team_def_avgpts
        );

        $t++;
    }

    @$lg_off_avgfgm = number_format($lg_off_fgm / $lg_off_games, 2);
    @$lg_off_avgfga = number_format($lg_off_fga / $lg_off_games, 2);
    @$lg_off_fgp = number_format($lg_off_fgm / $lg_off_fga, 3);
    @$lg_off_avgftm = number_format($lg_off_ftm / $lg_off_games, 2);
    @$lg_off_avgfta = number_format($lg_off_fta / $lg_off_games, 2);
    @$lg_off_ftp = number_format($lg_off_ftm / $lg_off_fta, 3);
    @$lg_off_avgtgm = number_format($lg_off_tgm / $lg_off_games, 2);
    @$lg_off_avgtga = number_format($lg_off_tga / $lg_off_games, 2);
    @$lg_off_tgp = number_format($lg_off_tgm / $lg_off_tga, 3);
    @$lg_off_avgorb = number_format($lg_off_orb / $lg_off_games, 2);
    @$lg_off_avgreb = number_format($lg_off_reb / $lg_off_games, 2);
    @$lg_off_avgast = number_format($lg_off_ast / $lg_off_games, 2);
    @$lg_off_avgstl = number_format($lg_off_stl / $lg_off_games, 2);
    @$lg_off_avgtvr = number_format($lg_off_tvr / $lg_off_games, 2);
    @$lg_off_avgblk = number_format($lg_off_blk / $lg_off_games, 2);
    @$lg_off_avgpf = number_format($lg_off_pf / $lg_off_games, 2);
    @$lg_off_avgpts = number_format($lg_off_pts / $lg_off_games, 2);

    $league_totals = "<tr style=\"font-weight:bold\">
		<td>LEAGUE TOTALS</td>
		<td>$lg_off_games</td>
		<td>$lg_off_fgm</td>
		<td>$lg_off_fga</td>
		<td>$lg_off_ftm</td>
		<td>$lg_off_fta</td>
		<td>$lg_off_tgm</td>
		<td>$lg_off_tga</td>
		<td>$lg_off_orb</td>
		<td>$lg_off_reb</td>
		<td>$lg_off_ast</td>
		<td>$lg_off_stl</td>
		<td>$lg_off_tvr</td>
		<td>$lg_off_blk</td>
		<td>$lg_off_pf</td>
		<td>$lg_off_pts</td>
	</tr>";

    $league_averages = "<tr style=\"font-weight:bold\">
		<td>LEAGUE AVERAGES</td>
		<td>$lg_off_avgfgm</td>
		<td>$lg_off_avgfga</td>
		<td>$lg_off_fgp</td>
		<td>$lg_off_avgftm</td>
		<td>$lg_off_avgfta</td>
		<td>$lg_off_ftp</td>
		<td>$lg_off_avgtgm</td>
		<td>$lg_off_avgtga</td>
		<td>$lg_off_tgp</td>
		<td>$lg_off_avgorb</td>
		<td>$lg_off_avgreb</td>
		<td>$lg_off_avgast</td>
		<td>$lg_off_avgstl</td>
		<td>$lg_off_avgtvr</td>
		<td>$lg_off_avgblk</td>
		<td>$lg_off_avgpf</td>
		<td>$lg_off_avgpts</td>
	</tr>";

    $i = 0;
    while ($i < $numteams - 1) {
        $league_differentials .= "<tr>";
        $league_differentials .= $teamHeaderCells[$i];

        $j = 0;
        while ($j < sizeof($teamOffenseAveragesArray[$i])) {
            $differential = $teamOffenseAveragesArray[$i][$j] - $teamDefenseAveragesArray[$i][$j];
            $league_differentials .= "<td align='right'>" . number_format($differential, 2) . "</td>";

            $j++;
        }
        $league_differentials .= "</tr>";

        $i++;
    }

    echo "<center>
		<h1>League-wide Statistics</h1>

		<h2>Team Offense Totals</h2>
		<table class=\"sortable\">
		<thead><tr><th>Team</th><th>Gm</th><th>FGM</th><th>FGA</th><th>FTM</th><th>FTA</th><th>3GM</th><th>3GA</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
		<tbody>$offense_totals</tbody>
		<tfoot>$league_totals</tfoot>
		</table>

		<h2>Team Defense Totals</h2>
		<table class=\"sortable\">
		<thead><tr><th>Team</th><th>Gm</th><th>FGM</th><th>FGA</th><th>FTM</th><th>FTA</th><th>3GM</th><th>3GA</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
		<tbody>$defense_totals</tbody>
		<tfoot>$league_totals</tfoot>
		</table>

		<h2>Team Offense Averages</h2>
		<table class=\"sortable\">
		<thead><tr><th>Team</th><th>FGM</th><th>FGA</th><th>FGP</th><th>FTM</th><th>FTA</th><th>FTP</th><th>3GM</th><th>3GA</th><th>3GP</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
		<tbody>$offense_averages</tbody>
		<tfoot>$league_averages</tfoot>
		</table>

		<h2>Team Defense Averages</h2>
		<table class=\"sortable\">
		<thead><tr><th>Team</th><th>FGM</th><th>FGA</th><th>FGP</th><th>FTM</th><th>FTA</th><th>FTP</th><th>3GM</th><th>3GA</th><th>3GP</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
		<tbody>$defense_averages</tbody>
		<tfoot>$league_averages</tfoot>
		</table>

		<h2>Team Off/Def Average Differentials</h2>
		<table class=\"sortable\">
		<thead><tr><th>Team</th><th>FGM</th><th>FGA</th><th>FGP</th><th>FTM</th><th>FTA</th><th>FTP</th><th>3GM</th><th>3GA</th><th>3GP</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
		<tbody>$league_differentials</tbody>
		</table>";

    CloseTable();
    include "footer.php";
}

function schedule($tid)
{
    global $db;
    $sharedFunctions = new Shared($db);

    $tid = intval($tid);
    include "header.php";
    OpenTable();
    //============================
    // GRAB TEAM COLORS, ET AL
    //============================
    $queryteam = "SELECT * FROM ibl_team_info WHERE teamid = '$tid';";
    $resultteam = $db->sql_query($queryteam);
    $color1 = $db->sql_result($resultteam, 0, "color1");
    $color2 = $db->sql_result($resultteam, 0, "color2");
    //=============================
    //DISPLAY TOP MENU
    //=============================
    $sharedFunctions->displaytopmenu($tid);
    $query = "SELECT * FROM `ibl_schedule` WHERE Visitor = $tid OR Home = $tid ORDER BY Date ASC;";
    $result = $db->sql_query($query);
    $year = $db->sql_result($result, 0, "Year");
    $year1 = $year + 1;
    $wins = 0;
    $losses = 0;
    echo "<center>
		<img src=\"./images/logo/$tid.jpg\">
		<table width=600 border=1>
			<tr bgcolor=$color1><td colspan=26><center><font color=$color2><h1>Team Schedule</h1><p><i>games highlighted in yellow are projected to be run next sim (7 days)</i></font></center></td></tr>
			<tr bgcolor=$color2><td colspan=26><font color=$color1><b><center>November</center></b></font></td></tr>
			<tr bgcolor=$color2><td><font color=$color1><b>Date</font></td><td><font color=$color1><b>Visitor</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Home</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Box Score</font></td><td><font color=$color1><b>Record</font></td><td><font color=$color1><b>Streak</font></td></tr>";
    list($wins, $losses, $winStreak, $lossStreak) = boxscore($year, '11', $tid, $wins, $losses, $winStreak, $lossStreak);
    echo "<tr bgcolor=$color1><td colspan=26><font color=$color2><b><center>December</center></b></font></td></tr>
		<tr bgcolor=$color1><td><font color=$color2><b>Date</font></td><td><font color=$color2><b>Visitor</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Home</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Box Score</b></font></td><td><font color=$color2><b>Record</font></td><td><font color=$color2><b>Streak</font></td></tr>";
    list($wins, $losses, $winStreak, $lossStreak) = boxscore($year, '12', $tid, $wins, $losses, $winStreak, $lossStreak);
    echo "<tr bgcolor=$color2><td colspan=26><font color=$color1><b><center>January</center></b></font></td></tr>
		<tr bgcolor=$color2><td><font color=$color1><b>Date</font></td><td><font color=$color1><b>Visitor</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Home</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Box Score</b></font></td><td><font color=$color1><b>Record</font></td><td><font color=$color1><b>Streak</font></td></tr>";
    list($wins, $losses, $winStreak, $lossStreak) = boxscore($year1, '01', $tid, $wins, $losses, $winStreak, $lossStreak);
    echo "<tr bgcolor=$color1><td colspan=26><font color=$color2><b><center>February</center></b></font></td></tr>
		<tr bgcolor=$color1><td><font color=$color2><b>Date</font></td><td><font color=$color2><b>Visitor</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Home</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Box Score</b></font></td><td><font color=$color2><b>Record</font></td><td><font color=$color2><b>Streak</font></td></tr>";
    list($wins, $losses, $winStreak, $lossStreak) = boxscore($year1, '02', $tid, $wins, $losses, $winStreak, $lossStreak);
    echo "<tr bgcolor=$color2><td colspan=26><font color=$color1><b><center>March</center></b></font></td></tr>
		<tr bgcolor=$color2><td><font color=$color1><b>Date</font></td><td><font color=$color1><b>Visitor</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Home</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Box Score</b></font></td><td><font color=$color1><b>Record</font></td><td><font color=$color1><b>Streak</font></td></tr>";
    list($wins, $losses, $winStreak, $lossStreak) = boxscore($year1, '03', $tid, $wins, $losses, $winStreak, $lossStreak);
    echo "<tr bgcolor=$color1><td colspan=26><font color=$color2><b><center>April</center></b></font></td></tr>
		<tr bgcolor=$color1><td><font color=$color2><b>Date</font></td><td><font color=$color2><b>Visitor</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Home</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Box Score</b></font></td><td><font color=$color2><b>Record</font></td><td><font color=$color2><b>Streak</font></td></tr>";
    list($wins, $losses, $winStreak, $lossStreak) = boxscore($year1, '04', $tid, $wins, $losses, $winStreak, $lossStreak);
    echo "<tr bgcolor=$color2><td colspan=26><font color=$color1><b><center>May</center></b></font></td></tr>
		<tr bgcolor=$color2><td><font color=$color1><b>Date</font></td><td><font color=$color1><b>Visitor</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Home</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Box Score</b></font></td><td><font color=$color1><b>Record</font></td><td><font color=$color1><b>Streak</font></td></tr>";
    list($wins, $losses, $winStreak, $lossStreak) = boxscore($year1, '05', $tid, $wins, $losses, $winStreak, $lossStreak);
    echo "<tr bgcolor=$color1><td colspan=26><font color=$color2><b><center>Playoffs</center></b></font></td></tr>
		<tr bgcolor=$color1><td><font color=$color2><b>Date</font></td><td><font color=$color2><b>Visitor</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Home</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Box Score</b></font></td><td><font color=$color2><b>Record</font></td><td><font color=$color2><b>Streak</font></td></tr>";
    list($wins, $losses, $winStreak, $lossStreak) = boxscore($year1, '06', $tid, $wins, $losses, $winStreak, $lossStreak);
    echo "</center>";
    CloseTable();

    CloseTable();
    include "footer.php";
}

function boxscore($year, $month, $tid, $wins, $losses, $winStreak, $lossStreak)
{
    global $db;
    $sharedFunctions = new Shared($db);

    //TODO: unify this code with the Schedule module's chunk function

    $query = "SELECT *
		FROM `ibl_schedule`
		WHERE (Visitor = $tid AND Date BETWEEN '$year-$month-01' AND '$year-$month-31')
			OR (Home = $tid AND Date BETWEEN '$year-$month-01' AND '$year-$month-31')
		ORDER BY Date ASC";
    $result = $db->sql_query($query);
    $num = $db->sql_numrows($result);
    $i = 0;

    $teamSeasonRecordsQuery = "SELECT tid, leagueRecord FROM ibl_standings ORDER BY tid ASC;";
    $teamSeasonRecordsResult = $db->sql_query($teamSeasonRecordsQuery);

    $arrayLastSimDates = $sharedFunctions->getLastSimDatesArray();
    $lastSimEndDate = date_create($arrayLastSimDates["End Date"]);
    $projectedNextSimEndDate = date_add($lastSimEndDate, date_interval_create_from_date_string('7 days'));
    $currentSeasonEndingYear = $sharedFunctions->getCurrentSeasonEndingYear();
    $currentSeasonBeginningYear = $currentSeasonEndingYear - 1;

    // override $projectedNextSimEndDate to account for the blank week at end of HEAT
    if (
        $projectedNextSimEndDate >= date_create("$currentSeasonBeginningYear-10-23")
        AND $projectedNextSimEndDate < date_create("$currentSeasonBeginningYear-11-01")
    ) {
        $projectedNextSimEndDate = date_create("$currentSeasonBeginningYear-11-08");
    }
    // override $projectedNextSimEndDate to account for the All-Star Break
    if (
        $projectedNextSimEndDate >= date_create("$currentSeasonEndingYear-02-01")
        AND $projectedNextSimEndDate < date_create("$currentSeasonEndingYear-02-11")
    ) {
        $projectedNextSimEndDate = date_create("$currentSeasonEndingYear-02-11");
    }

    while ($i < $num) {
        $date = $db->sql_result($result, $i, "Date");
        $visitor = $db->sql_result($result, $i, "Visitor");
        $visitorScore = $db->sql_result($result, $i, "VScore");
        $home = $db->sql_result($result, $i, "Home");
        $homeScore = $db->sql_result($result, $i, "HScore");
        $boxID = $db->sql_result($result, $i, "BoxID");

        $visitorTeamname = $sharedFunctions->getTeamnameFromTid($visitor);
        $homeTeamname = $sharedFunctions->getTeamnameFromTid($home);
        $visitorRecord = $db->sql_result($teamSeasonRecordsResult, $visitor - 1, "leagueRecord");
        $homeRecord = $db->sql_result($teamSeasonRecordsResult, $home - 1, "leagueRecord");

        if ($visitorScore == $homeScore) {
            if (date_create($date) <= $projectedNextSimEndDate) {
                echo "<tr bgcolor=#DDDD00>";
            } else {
                echo "<tr>";
            }
            echo "<td>$date</td>
				<td><a href=\"modules.php?name=Team&op=team&tid=$visitor\">$visitorTeamname ($visitorRecord)</a></td>
				<td></td>
				<td><a href=\"modules.php?name=Team&op=team&tid=$home\">$homeTeamname ($homeRecord)</a></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
			</tr>";
        } else {
            if ($tid == $visitor) {
                if ($visitorScore > $homeScore) {
                    $wins++;
                    $winStreak++;
                    $lossStreak = 0;
                    $winlosscolor = "green";
                } else {
                    $losses++;
                    $lossStreak++;
                    $winStreak = 0;
                    $winlosscolor = "red";
                }
            } else {
                if ($visitorScore > $homeScore) {
                    $losses++;
                    $lossStreak++;
                    $winStreak = 0;
                    $winlosscolor = "red";
                } else {
                    $wins++;
                    $winStreak++;
                    $lossStreak = 0;
                    $winlosscolor = "green";
                }
            }

            if ($winStreak > $lossStreak) {
                $streak = "W $winStreak";
            } else {
                $streak = "L $lossStreak";
            }

            (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";

            if ($visitorScore > $homeScore) {
                echo "<tr bgcolor=$bgcolor>
					<td>$date</td>
					<td><b><a href=\"modules.php?name=Team&op=team&tid=$visitor\">$visitorTeamname ($visitorRecord)</a></b></td>
					<td><b><font color=$winlosscolor>$visitorScore</font></b></td>
					<td><a href=\"modules.php?name=Team&op=team&tid=$home\">$homeTeamname ($homeRecord)</a></td>
					<td><b><font color=$winlosscolor>$homeScore</font></b></td>
					<td><a href=\"./ibl/IBL/box$boxID.htm\">View</a></td>
					<td>$wins - $losses</td>
					<td>$streak</td>
				</tr>";
            } else if ($visitorScore < $homeScore) {
                echo "<tr bgcolor=$bgcolor>
					<td>$date</td>
					<td><a href=\"modules.php?name=Team&op=team&tid=$visitor\">$visitorTeamname ($visitorRecord)</a></td>
					<td><b><font color=$winlosscolor>$visitorScore</font></b></td>
					<td><b><a href=\"modules.php?name=Team&op=team&tid=$home\">$homeTeamname ($homeRecord)</a></b></td>
					<td><b><font color=$winlosscolor>$homeScore</font></b></td>
					<td><a href=\"./ibl/IBL/box$boxID.htm\">View</a></td>
					<td>$wins - $losses</td>
					<td>$streak</td>
				</tr>";
            }
        }

        $i++;
    }

    return array($wins, $losses, $winStreak, $lossStreak);
}

function viewinjuries($tid)
{
    global $db;
    $sharedFunctions = new Shared($db);

    include "header.php";
    OpenTable();

    $sharedFunctions->displaytopmenu($tid);

    $query = "SELECT * FROM ibl_plr WHERE injured > 0 AND retired = 0 ORDER BY ordinal ASC";

    $result = $db->sql_query($query);
    $num = $db->sql_numrows($result);

    echo "<center><h2>INJURED PLAYERS</h2></center>
		<table><tr><td valign=top>
		<table class=\"sortable\">
		<tr><th>Pos</th><th>Player</th><th>Team</th><th>Days Injured</th>";

    $query_all_team_colors = "SELECT * FROM ibl_team_info ORDER BY teamid ASC";
    $colors = $db->sql_query($query_all_team_colors);
    $num_all_team_colors = $db->sql_numrows($colors);

    $k = 0;
    while ($k < $num_all_team_colors) {
        $color_array[$k]['team_id'] = $db->sql_result($colors, $k, "teamid");
        $color_array[$k]['team_city'] = $db->sql_result($colors, $k, "team_city");
        $color_array[$k]['team_name'] = $db->sql_result($colors, $k, "team_name");
        $color_array[$k]['color1'] = $db->sql_result($colors, $k, "color1");
        $color_array[$k]['color2'] = $db->sql_result($colors, $k, "color2");
        $k++;
    }

    $i = 0;

    while ($i < $num) {
        (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "DDDDDD";

        $name = $db->sql_result($result, $i, "name");
        $team = $db->sql_result($result, $i, "teamname");
        $pid = $db->sql_result($result, $i, "pid");
        $tid = $db->sql_result($result, $i, "tid");
        $pos = $db->sql_result($result, $i, "pos");
        $inj = $db->sql_result($result, $i, "injured");

        $j = 0;
        while ($j < $k) {
            $pick_team_name = $color_array[$j]['team_name'];
            if ($pick_team_name == $team) {
                $pick_team_city = $color_array[$j]['team_city'];
                $pick_team_color1 = $color_array[$j]['color1'];
                $pick_team_color2 = $color_array[$j]['color2'];
            }
            $j++;
        }

        echo "<tr bgcolor=$bgcolor><td>$pos</td><td><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td><td bgcolor=\"#$pick_team_color1\"><a href=\"./modules.php?name=Team&op=team&tid=$tid\"><font color=\"#$pick_team_color2\">$pick_team_city $team</font></a></td><td>$inj</td></tr>";

        $i++;
    }

    echo "</table></table>";

    CloseTable();
    include "footer.php";
}

function drafthistory($tid)
{
    global $db;
    $sharedFunctions = new Shared($db);

    include "header.php";
    OpenTable();
    $sharedFunctions->displaytopmenu($tid);

    $sqlc = "SELECT * FROM ibl_team_info WHERE teamid = $tid";
    $resultc = $db->sql_query($sqlc);
    $rowc = $db->sql_fetchrow($resultc);
    $teamname = $rowc['team_name'];

    $sqld = "SELECT * FROM ibl_plr WHERE draftedby LIKE '$teamname' ORDER BY draftyear DESC, draftround, draftpickno ASC ";
    $resultd = $db->sql_query($sqld);

    echo "$teamname Draft History<table class=\"sortable\"><tr><th>Player</th><th>Pos</th><th>Year</th><th>Round</th><th>Pick</th></tr>";

    while ($rowd = $db->sql_fetchrow($resultd)) {
        $player_pid = $rowd['pid'];
        $player_name = $rowd['name'];
        $player_pos = $rowd['pos'];
        $player_draftyear = $rowd['draftyear'];
        $player_draftround = $rowd['draftround'];
        $player_draftpickno = $rowd['draftpickno'];
        $player_retired = $rowd['retired'];

        if ($player_retired == 1) {
            echo "<tr><td><a href=\"./modules.php?name=Player&pa=showpage&pid=$player_pid\">$player_name</a> (retired)</td><td>$player_pos</td><td>$player_draftyear</td><td>$player_draftround</td><td>$player_draftpickno</td></tr>";
        } else {
            echo "<tr><td><a href=\"./modules.php?name=Player&pa=showpage&pid=$player_pid\">$player_name</a></td><td>$player_pos</td><td>$player_draftyear</td><td>$player_draftround</td><td>$player_draftpickno</td></tr>";
        }
    }

    echo "</table>";

    CloseTable();
    include "footer.php";
}

function eoy_voters()
{
    global $db;

    include "header.php";
    $query2 = "SELECT * FROM ibl_team_history WHERE teamid != 35 ORDER BY teamid ASC";
    $result2 = $db->sql_query($query2);
    $num2 = $db->sql_numrows($result2);

    OpenTable();
    $k = 0;
    while ($k < $num2) {
        $teamname[$k] = $db->sql_result($result2, $k, "team_name");
        $teamcity[$k] = $db->sql_result($result2, $k, "team_city");
        $teamcolor1[$k] = $db->sql_result($result2, $k, "color1");
        $teamcolor2[$k] = $db->sql_result($result2, $k, "color2");
        $eoy_vote[$k] = $db->sql_result($result2, $k, "eoy_vote");
        $teamid[$k] = $db->sql_result($result2, $k, "teamid");

        $table_echo .= "<tr><td bgcolor=#" . $teamcolor1[$k] . "><a href=\"./modules.php?name=Team&op=team&tid=" . $teamid[$k] . "\"><font color=#" . $teamcolor2[$k] . ">" . $teamcity[$k] . " " . $teamname[$k] . "</a></td><td>" . $eoy_vote[$k] . "</td></tr>";
        $k++;
    }
    $text .= "<table class=\"sortable\" border=1><tr><th>Team</th><th>Vote Received</th></tr>$table_echo</table>";
    echo $text;
    CloseTable();
    include "footer.php";
}

function asg_voters()
{
    global $db;

    include "header.php";
    $query2 = "SELECT * FROM ibl_team_history WHERE teamid != 35 ORDER BY teamid ASC";
    $result2 = $db->sql_query($query2);
    $num2 = $db->sql_numrows($result2);

    OpenTable();
    $k = 0;
    while ($k < $num2) {
        $teamname[$k] = $db->sql_result($result2, $k, "team_name");
        $teamcity[$k] = $db->sql_result($result2, $k, "team_city");
        $teamcolor1[$k] = $db->sql_result($result2, $k, "color1");
        $teamcolor2[$k] = $db->sql_result($result2, $k, "color2");
        $asg_vote[$k] = $db->sql_result($result2, $k, "asg_vote");
        $teamid[$k] = $db->sql_result($result2, $k, "teamid");

        $table_echo .= "<tr><td bgcolor=#" . $teamcolor1[$k] . "><a href=\"./modules.php?name=Team&op=team&tid=" . $teamid[$k] . "\"><font color=#" . $teamcolor2[$k] . ">" . $teamcity[$k] . " " . $teamname[$k] . "</a></td><td>" . $asg_vote[$k] . "</td></tr>";
        $k++;
    }
    $text .= "<table class=\"sortable\" border=1><tr><th>Team</th><th>Vote Received</th></tr>$table_echo</table>";
    echo $text;
    CloseTable();
    include "footer.php";
}

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

switch ($op) {
    case "team":
        team($tid);
        break;

    case "leaguestats":
        leaguestats();
        break;

    case "schedule":
        schedule($tid);
        break;

    case "injuries":
        viewinjuries($tid);
        break;

    case "drafthistory":
        drafthistory($tid);
        break;

    case "eoy_voters":
        eoy_voters();
        break;

    case "asg_voters":
        asg_voters();
        break;

    default:
        menu();
        break;
}
