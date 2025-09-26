<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2005 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "Sim Stats";

function chunkstats()
{
    global $db;
    Nuke\Header::header();
    OpenTable();
    echo "<center><font class=\"storytitle\">Sim Stats</font></center><br><br>";

    $max_chunk_query = "SELECT MAX(chunk) as maxchunk FROM ibl_plr_chunk";
    $max_chunk_result = $db->sql_query($max_chunk_query);
    $row = $db->sql_fetch_assoc($max_chunk_result);

    $chunk = $_POST['chunk'];
    $team = $_POST['team'];
    $position = $_POST['position'];
    $sortby = $_POST['sortby'];
    $argument = "";

    if ($chunk == '') {
        $argument = $argument . "chunk = $row[maxchunk]";
    } else if ($chunk == 0) {
        $argument = $argument . "chunk != 0";
    } else {
        $argument = $argument . "chunk = $chunk";
    }

    if ($position == '') {
        $argument = $argument . "";
    } else {
        $argument = $argument . " AND pos = '$position'";
    }

    if ($team == 0) {
        $argument = $argument . "";
    } else {
        $argument = $argument . " AND tid = $team";
    }

    if ($sortby == "1") {
        $sort = "((2*`stats_fgm`+`stats_ftm`+`stats_3gm`)/`stats_gm`)";
    } else if ($sortby == "2") {
        $sort = "((stats_orb+stats_drb)/`stats_gm`)";
    } else if ($sortby == "3") {
        $sort = "((stats_ast)/`stats_gm`)";
    } else if ($sortby == "4") {
        $sort = "((stats_stl)/`stats_gm`)";
    } else if ($sortby == "5") {
        $sort = "((stats_blk)/`stats_gm`)";
    } else if ($sortby == "6") {
        $sort = "((stats_to)/`stats_gm`)";
    } else if ($sortby == "7") {
        $sort = "((stats_pf)/`stats_gm`)";
    } else {
        $sort = "qa";
    }

    echo "<form name=\"Leaderboards\" method=\"post\" action=\"modules.php?name=Chunk_Stats&op=chunk\">";
    echo "<table border=1>";
    echo "<tr><td><b>Sim</td>
		<td><select name=\"chunk\">";
    chunk_option($row['maxchunk'], $chunk);
    echo "</select></td>";
    echo "<td><b>Team</td>
		<td><select name=\"team\">";
    team_option($team);
    echo "</select></td>";
    echo "<td><b>Pos</td>
		<td><select name=\"position\">";
    position_option($position);
    echo "<td><b>Sort By</td>
		<td><select name=\"sortby\">";
    sort_option($sortby);
    echo "</select></td>";
    echo "</select></td>
		<td><input type=\"submit\" value=\"Search Sim Data\"></td>";
    echo "</tr></table>";

    echo "<table class=\"sortable\" cellpadding=3 CELLSPACING=0 border=0>
		<tr bgcolor=D99795>
			<th><b>Rank</td>
			<th><b>Name</td>
			<th><b>Pos</td>
			<th><b>Team</td>
			<th><b>Sim</td>
			<th><b>G</td>
			<th><b>Min</td>
			<th align=right><b>fgm</td>
			<th><b>fga</td>
			<th align=right><b>fg%</td>
			<th><b>ftm</td>
			<th align=right><b>fta</td>
			<th><b>ft%</td>
			<th align=right><b>tgm</td>
			<th><b>tga</td>
			<th align=right><b>tg%</td>
			<th><b>orb</td>
			<th align=right><b>reb</td>
			<th><b>ast</td>
			<th align=right><b>stl</td>
			<th><b>to</td>
			<th align=right><b>blk</td>
			<th><b>pf</td>
			<th align=right><b>ppg</td>
			<th><b>QA</td>
		</tr>";

    $query = "SELECT * FROM ibl_plr_chunk WHERE $argument AND qa !=0 ORDER BY $sort DESC";
    $result = $db->sql_query($query);
    $num = $db->sql_numrows($result);

    $i = 0;
    while ($i < $num) {
        $pid = $db->sql_result($result, $i, "pid");
        $pos = $db->sql_result($result, $i, "pos");
        $name = $db->sql_result($result, $i, "name");
        $teamname = $db->sql_result($result, $i, "teamname");
        $teamid = $db->sql_result($result, $i, "tid");
        $chunknumber = $db->sql_result($result, $i, "chunk");
        $qa = $db->sql_result($result, $i, "qa");
        $stats_gm = $db->sql_result($result, $i, "stats_gm");
        $stats_min = $db->sql_result($result, $i, "stats_min");
        $stats_fgm = $db->sql_result($result, $i, "stats_fgm");
        $stats_fga = $db->sql_result($result, $i, "stats_fga");
        $stats_fgp = $stats_fga ? number_format(($stats_fgm / $stats_fga * 100), 1) : "0.000";
        $stats_ftm = $db->sql_result($result, $i, "stats_ftm");
        $stats_fta = $db->sql_result($result, $i, "stats_fta");
        $stats_ftp = $stats_fta ? number_format(($stats_ftm / $stats_fta * 100), 1) : "0.000";
        $stats_tgm = $db->sql_result($result, $i, "stats_3gm");
        $stats_tga = $db->sql_result($result, $i, "stats_3ga");
        $stats_tgp = $stats_tga ? number_format(($stats_tgm / $stats_tga * 100), 1) : "0.000";
        $stats_orb = $db->sql_result($result, $i, "stats_orb");
        $stats_drb = $db->sql_result($result, $i, "stats_drb");
        $stats_reb = $stats_orb + $stats_drb;
        $stats_ast = $db->sql_result($result, $i, "stats_ast");
        $stats_stl = $db->sql_result($result, $i, "stats_stl");
        $stats_to = $db->sql_result($result, $i, "stats_to");
        $stats_blk = $db->sql_result($result, $i, "stats_blk");
        $stats_pf = $db->sql_result($result, $i, "stats_pf");
        $stats_pts = 2 * $stats_fgm + $stats_ftm + $stats_tgm;

        $stats_mpg = $stats_gm ? number_format(($stats_min / $stats_gm), 1) : "0.00";
        $stats_fgmpg = $stats_gm ? number_format(($stats_fgm / $stats_gm), 1) : "0.00";
        $stats_fgapg = $stats_gm ? number_format(($stats_fga / $stats_gm), 1) : "0.00";
        $stats_ftmpg = $stats_gm ? number_format(($stats_ftm / $stats_gm), 1) : "0.00";
        $stats_ftapg = $stats_gm ? number_format(($stats_fta / $stats_gm), 1) : "0.00";
        $stats_tgmpg = $stats_gm ? number_format(($stats_tgm / $stats_gm), 1) : "0.00";
        $stats_tgapg = $stats_gm ? number_format(($stats_tga / $stats_gm), 1) : "0.00";
        $stats_orbpg = $stats_gm ? number_format(($stats_orb / $stats_gm), 1) : "0.00";
        $stats_rpg = $stats_gm ? number_format(($stats_reb / $stats_gm), 1) : "0.00";
        $stats_apg = $stats_gm ? number_format(($stats_ast / $stats_gm), 1) : "0.00";
        $stats_spg = $stats_gm ? number_format(($stats_stl / $stats_gm), 1) : "0.00";
        $stats_tpg = $stats_gm ? number_format(($stats_to / $stats_gm), 1) : "0.00";
        $stats_bpg = $stats_gm ? number_format(($stats_blk / $stats_gm), 1) : "0.00";
        $stats_fpg = $stats_gm ? number_format(($stats_pf / $stats_gm), 1) : "0.00";
        $stats_ppg = $stats_gm ? number_format(($stats_pts / $stats_gm), 1) : "0.00";

        if (($i % 2) == 0) {
            $bgcolor = "DDDDDD";
        } else {
            $bgcolor = "FFFFFF";
        }

        echo "<tr bgcolor=$bgcolor>
			<td>$i.</td>
			<td><a href=modules.php?name=Player&pa=showpage&pid=$pid>$name</a></td>
			<td>$pos</td>
			<td><a href=modules.php?name=Team&op=team&teamID=$teamid>$teamname</a></td>
			<td>$chunknumber</td>
			<td>$stats_gm</td>
			<td align=right>$stats_mpg</td>
			<td align=right>$stats_fgmpg</td>
			<td align=right>$stats_fgapg</td>
			<td align=right>$stats_fgp</td>
			<td align=right>$stats_ftmpg</td>
			<td align=right>$stats_ftapg</td>
			<td align=right>$stats_ftp</td>
			<td align=right>$stats_tgmpg</td>
			<td align=right>$stats_tgapg</td>
			<td align=right>$stats_tgp</td>
			<td align=right>$stats_orbpg</td>
			<td align=right>$stats_rpg</td>
			<td align=right>$stats_apg</td>
			<td align=right>$stats_spg</td>
			<td align=right>$stats_tpg</td>
			<td align=right>$stats_bpg</td>
			<td align=right>$stats_fpg</td>
			<td align=right>$stats_ppg</td>
			<td align=right>$qa</td>
		</tr>";

        $i++;
    }

    echo "</table></form>";
    CloseTable();
    Nuke\Footer::footer();
}

function seasonstats()
{
    global $db;
    Nuke\Header::header();
    OpenTable();
    $team = $_POST['team'];
    $position = $_POST['position'];
    $sortby = $_POST['sortby'];
    $argument = "";

    if ($position == '') {
        $argument = $argument . "";
    } else {
        $argument = $argument . "AND pos = '$position'";
    }
    if ($team == 0) {
        $argument = $argument . "";
    } else {
        $argument = $argument . " AND tid = $team";
    }
    if ($sortby == "1") {
        $sort = "((2*`stats_fgm`+`stats_ftm`+`stats_3gm`)/`stats_gm`)";
    } else if ($sortby == "2") {
        $sort = "((stats_orb+stats_drb)/`stats_gm`)";
    } else if ($sortby == "3") {
        $sort = "((stats_ast)/`stats_gm`)";
    } else if ($sortby == "4") {
        $sort = "((stats_stl)/`stats_gm`)";
    } else if ($sortby == "5") {
        $sort = "((stats_blk)/`stats_gm`)";
    } else if ($sortby == "6") {
        $sort = "((stats_to)/`stats_gm`)";
    } else if ($sortby == "7") {
        $sort = "((stats_pf)/`stats_gm`)";
    } else if ($sortby == "8") {
        $sort = "((((2*stats_fgm+stats_ftm+stats_3gm)+stats_orb+stats_drb+(2*stats_ast)+(2*stats_stl)+(2*stats_blk))-((stats_fga-stats_fgm)+(stats_fta-stats_ftm)+stats_to+stats_pf))/stats_gm)";
    } else {
        $sort = "((2*`stats_fgm`+`stats_ftm`+`stats_3gm`)/`stats_gm`)";
    }

    $query = "SELECT * FROM ibl_plr WHERE retired = 0 $argument ORDER BY $sort DESC";
    $result = $db->sql_query($query);
    $num = $db->sql_numrows($result);

    echo "<form name=\"Leaderboards\" method=\"post\" action=\"modules.php?name=Chunk_Stats&op=season\">";
    echo "<table border=1>";
    echo "<tr><td><b>Team</td><td><select name=\"team\">";
    team_option($team);
    echo "</select></td>";
    echo "<td><b>Pos</td><td><select name=\"position\">";
    position_option($position);
    echo "<td><b>Sort By</td><td><select name=\"sortby\">";
    sort_option($sortby);
    echo "</select></td>";
    echo "</select></td><td><input type=\"submit\" value=\"Search Season Data\"></td>";
    echo "</tr></table>";

    echo "<table class=\"sortable\" cellpadding=3 CELLSPACING=0 border=0>
        <tr bgcolor=C2D69A>
            <th><b>Rank</td>
            <th><b>Name</td>
            <th><b>Pos</td>
            <th><b>Team</td>
            <th><b>G</td>
            <th><b>Min</td>
            <th align=right><b>fgm</td>
            <th><b>fga</td>
            <th align=right><b>fg%</td>
            <th><b>ftm</td>
            <th align=right><b>fta</td>
            <th><b>ft%</td>
            <th align=right><b>tgm</td>
            <th><b>tga</td>
            <th align=right><b>tg%</td>
            <th><b>orb</td>
            <th align=right><b>reb</td>
            <th><b>ast</td>
            <th align=right><b>stl</td>
            <th><b>to</td>
            <th align=right><b>blk</td>
            <th><b>pf</td>
            <th align=right><b>ppg</td>
            <th align=right><b>qa</td>
        </tr>";

    $i = 0;
    while ($i < $num) {
        $pid = $db->sql_result($result, $i, "pid");
        $pos = $db->sql_result($result, $i, "pos");
        $name = $db->sql_result($result, $i, "name");
        $teamname = $db->sql_result($result, $i, "teamname");
        $teamid = $db->sql_result($result, $i, "tid");
        //$chunknumber=$db->sql_result($result,$i,"chunk");
        //$qa=$db->sql_result($result,$i,"qa");
        $stats_gm = $db->sql_result($result, $i, "stats_gm");
        $stats_min = $db->sql_result($result, $i, "stats_min");
        $stats_fgm = $db->sql_result($result, $i, "stats_fgm");
        $stats_fga = $db->sql_result($result, $i, "stats_fga");
        $stats_fgp = $stats_fga ? number_format(($stats_fgm / $stats_fga * 100), 1) : "0.000";
        $stats_ftm = $db->sql_result($result, $i, "stats_ftm");
        $stats_fta = $db->sql_result($result, $i, "stats_fta");
        $stats_ftp = $stats_fta ? number_format(($stats_ftm / $stats_fta * 100), 1) : "0.000";
        $stats_tgm = $db->sql_result($result, $i, "stats_3gm");
        $stats_tga = $db->sql_result($result, $i, "stats_3ga");
        $stats_tgp = $stats_tga ? number_format(($stats_tgm / $stats_tga * 100), 1) : "0.000";
        $stats_orb = $db->sql_result($result, $i, "stats_orb");
        $stats_drb = $db->sql_result($result, $i, "stats_drb");
        $stats_reb = $stats_orb + $stats_drb;
        $stats_ast = $db->sql_result($result, $i, "stats_ast");
        $stats_stl = $db->sql_result($result, $i, "stats_stl");
        $stats_to = $db->sql_result($result, $i, "stats_to");
        $stats_blk = $db->sql_result($result, $i, "stats_blk");
        $stats_pf = $db->sql_result($result, $i, "stats_pf");
        $stats_pts = 2 * $stats_fgm + $stats_ftm + $stats_tgm;

        @$stats_mpg = $stats_gm ? number_format(($stats_min / $stats_gm), 1) : "0.00";
        @$stats_fgmpg = $stats_gm ? number_format(($stats_fgm / $stats_gm), 1) : "0.00";
        @$stats_fgapg = $stats_gm ? number_format(($stats_fga / $stats_gm), 1) : "0.00";
        @$stats_ftmpg = $stats_gm ? number_format(($stats_ftm / $stats_gm), 1) : "0.00";
        @$stats_ftapg = $stats_gm ? number_format(($stats_fta / $stats_gm), 1) : "0.00";
        @$stats_tgmpg = $stats_gm ? number_format(($stats_tgm / $stats_gm), 1) : "0.00";
        @$stats_tgapg = $stats_gm ? number_format(($stats_tga / $stats_gm), 1) : "0.00";
        @$stats_orbpg = $stats_gm ? number_format(($stats_orb / $stats_gm), 1) : "0.00";
        @$stats_rpg = $stats_gm ? number_format(($stats_reb / $stats_gm), 1) : "0.00";
        @$stats_apg = $stats_gm ? number_format(($stats_ast / $stats_gm), 1) : "0.00";
        @$stats_spg = $stats_gm ? number_format(($stats_stl / $stats_gm), 1) : "0.00";
        @$stats_tpg = $stats_gm ? number_format(($stats_to / $stats_gm), 1) : "0.00";
        @$stats_bpg = $stats_gm ? number_format(($stats_blk / $stats_gm), 1) : "0.00";
        @$stats_fpg = $stats_gm ? number_format(($stats_pf / $stats_gm), 1) : "0.00";
        @$stats_ppg = $stats_gm ? number_format(($stats_pts / $stats_gm), 1) : "0.00";

        if ($stats_gm > 0) {
            $qa = number_format((($stats_pts + $stats_orb + $stats_drb + (2 * $stats_ast) + (2 * $stats_stl) + (2 * $stats_blk)) - (($stats_fga - $stats_fgm) + ($stats_fta - $stats_ftm) + $stats_to + $stats_pf)) / $stats_gm, 1);
        } else {
            $qa = number_format(0, 1);
        }

        if (($i % 2) == 0) {
            $bgcolor = "DDDDDD";
        } else {
            $bgcolor = "FFFFFF";
        }

        $i++;
        echo "<tr bgcolor=$bgcolor><td>$i.</td><td><a href=modules.php?name=Player&pa=showpage&pid=$pid>$name</a></td><td>$pos</td><td><a href=modules.php?name=Team&op=team&teamID=$teamid>$teamname</a></td><td>$stats_gm</td><td align=right>$stats_mpg</td><td align=right>$stats_fgmpg</td><td align=right>$stats_fgapg</td><td align=right>$stats_fgp</td><td align=right>$stats_ftmpg</td><td align=right>$stats_ftapg</td><td align=right>$stats_ftp</td><td align=right>$stats_tgmpg</td><td align=right>$stats_tgapg</td><td align=right>$stats_tgp</td><td align=right>$stats_orbpg</td><td align=right>$stats_rpg</td><td align=right>$stats_apg</td><td align=right>$stats_spg</td><td align=right>$stats_tpg</td><td align=right>$stats_bpg</td><td align=right>$stats_fpg</td><td align=right>$stats_ppg</td><td>$qa</td></tr>";

    }

    echo "</table></form>";
    CloseTable();
    Nuke\Footer::footer();
}

function chunk_option($num, $chunk_selected)
{
    $i = 0;
    echo "<option value='0'>All</option>";
    while ($i < $num) {
        $i++;
        if ($chunk_selected == $i) {
            echo "<option value=$i SELECTED>$i</option>";
        } else {
            echo "<option value=$i>$i</option>";
        }
    }
}

function team_option($team_selected)
{
    global $db;

    $query = "SELECT * FROM ibl_power WHERE TeamID BETWEEN 1 AND 32 ORDER BY TeamID ASC";
    $result = $db->sql_query($query);
    $num = $db->sql_numrows($result);
    echo "<option value=0>All</option>";
    $i = 0;
    while ($i < $num) {
        $tid = $db->sql_result($result, $i, "TeamID");
        $Team = $db->sql_result($result, $i, "Team");

        $i++;
        if ($team_selected == $tid) {
            echo "<option value=$tid SELECTED>$Team</option>";
        } else {
            echo "<option value=$tid>$Team</option>";
        }
    }
}

function position_option($position_selected)
{
    $arr = array("C", "PF", "SF", "SG", "PG");
    $num = sizeof($arr);
    echo "<option value=''>All</option>";
    $i = 0;
    while ($i < $num) {
        $position = $arr[$i];

        $i++;
        if ($position_selected == $position) {
            echo "<option value='$position' SELECTED>$position</option>";
        } else {
            echo "<option value='$position'>$position</option>";
        }
    }
}

function sort_option($sort_selected)
{
    $arr = array("PPG", "REB", "AST", "STL", "BLK", "TO", "FOUL", "QA");
    $num = sizeof($arr);
    $i = 0;
    while ($i < $num) {
        $sortby = $arr[$i];

        $i++;
        if ($i == $sort_selected) {
            echo "<option value='$i' SELECTED>$sortby</option>";
        } else {
            echo "<option value='$i'>$sortby</option>";
        }
    }
}

function test()
{
    echo "TEST";
}

switch ($op) {
    case "chunk":
        chunkstats();
        break;

    case "season":
        seasonstats();
        break;

    default:
        menu();
        break;
}
