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


$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Player Archives";

NukeHeader::header();
OpenTable();
UI::playerMenu();

$boards_type = $_POST['boards_type'];
$display = $_POST['display'];
$active = $_POST['active'];
$sort_cat = $_POST['sort_cat'];
$submitted = $_POST['submitted'];

echo "<form name=\"Leaderboards\" method=\"post\" action=\"modules.php?name=Leaderboards\">
    <center><table>
        <tr>
            <td>Type: <select name=\"boards_type\">";

// TODO: continue refactoring this function to take up waaay less lines

$typeArray = array(
    'Reg' => 'Regular Season Totals',
    'Rav' => 'Regular Season Averages',
    'Ply' => 'Playoff Totals',
    'PLA' => 'Playoff Averages',
    'HET' => 'H.E.A.T. Totals',
    'HEA' => 'H.E.A.T. Averages',
);

foreach ($typeArray as $key => $value) {
    echo "<option value=\"$key\"";
    if ($boards_type == $key) {
        echo ' SELECTED';
    }
    echo ">$value</option>";
}

echo "</select></td><td>
        Category: <select name=\"sort_cat\">";

$sort_cat_array = array(
    'pts' => 'Points',
    'games' => 'Games',
    'minutes' => 'Minutes',
    'fgm' => 'Field Goals Made',
    'fga' => 'Field Goals Attempted',
    'fgpct' => 'FG Percentage (avgs only)',
    'ftm' => 'Free Throws Made',
    'fta' => 'Free Throws Attempted',
    'ftpct' => 'FT Percentage (avgs only)',
    'tgm' => 'Three-Pointers Made',
    'tga' => 'Three-Pointers Attempted',
    'tpct' => '3P Percentage (avgs only)',
    'orb' => 'Offensive Rebounds',
    'reb' => 'Total Rebounds',
    'ast' => 'Assists',
    'stl' => 'Steals',
    'tvr' => 'Turnovers',
    'blk' => 'Blocked Shots',
    'pf' => 'Personal Fouls',
);

foreach ($sort_cat_array as $key => $value) {
    echo "<option value=\"$value\"";
    if ($sort_cat == $value) {
        echo ' SELECTED';
    }
    echo ">$value</option>";
}

echo "</select></td><td>
        Search: <select name=\"active\">";

if ($active == '0') {
    echo "<option value=\"0\" SELECTED>All Players</option>";
} else {
    echo "<option value=\"0\">All Players</option>";
}

if ($active == '1') {
    echo "<option value=\"1\" SELECTED>Active Players Only</option>";
} else {
    echo "<option value=\"1\">Active Players Only</option>";
}

echo "</select></td>
    <td>Limit: <input type=\"text\" name=\"display\" size=\"4\" value=\"$display\"> Records</td>
    <td>
        <input type=\"hidden\" name=\"submitted\" value=\"1\">
        <input type=\"submit\" value=\"Display Leaderboards\"></form>
    </td></tr></table>";

// ===== RUN QUERY IF FORM HAS BEEN SUBMITTED

if ($submitted != null) {
    $tableforquery = "ibl_plr";

    if ($boards_type == 'Reg') {
        $tableforquery = "ibl_plr";
        $restriction2 = "car_gm > 0 ";
    }

    if ($boards_type == 'Rav') {
        $tableforquery = "ibl_season_career_avgs";
        $restriction2 = "games > 0";
    }

    if ($boards_type == 'Ply') {
        $tableforquery = "ibl_playoff_career_totals";
        $restriction2 = "games > 0";
    }

    if ($boards_type == 'PLA') {
        $tableforquery = "ibl_playoff_career_avgs";
        $restriction2 = "games > 0";
    }

    if ($boards_type == 'HET') {
        $tableforquery = "ibl_heat_career_totals";
        $restriction2 = "games > 0";
    }

    if ($boards_type == 'HEA') {
        $tableforquery = "ibl_heat_career_avgs";
        $restriction2 = "games > 0";
    }

    if ($active == 1) {
        $restriction1 = " retired = '0' AND ";
    }

    $sortby = "pts";
    foreach ($sort_cat_array as $key => $value) {
        if ($sort_cat == $value) {
            $sortby = $key;
        }
    }

    if ($tableforquery == "ibl_plr") {
        $sortby = "car_" . $sortby;
        if ($sort_cat == 'Games') {
            $sortby = "car_gm";
        }
        if ($sort_cat == 'Minutes') {
            $sortby = "car_min";
        }
        if ($sort_cat == 'Turnovers') {
            $sortby = "car_to";
        }
    }

    $query = "SELECT * FROM $tableforquery WHERE $restriction1 $restriction2 ORDER BY $sortby DESC";
    $result = $db->sql_query($query);
    $num = $db->sql_numrows($result);

    echo "<center><h2>Leaderboards Display</h2><p>
        <table class=\"sortable\">
            <tr>
                <th><center>Rank</center></th>
                <th><center>Name</center></th>
                <th><center>Gm</center></th>
                <th><center>Min</center></th>
                <th><center>FGM</center></th>
                <th><center>FGA</center></th>
                <th><center>FG%</center></th>
                <th><center>FTM</center></th>
                <th><center>FTA</center></th>
                <th><center>FT%</center></th>
                <th><center>3PTM</center></th>
                <th><center>3PTA</center></th>
                <th><center>3P%</center></th>
                <th><center>ORB</center></th>
                <th><center>REB</center></th>
                <th><center>AST</center></th>
                <th><center>STL</center></th>
                <th><center>TVR</center></th>
                <th><center>BLK</center></th>
                <th><center>FOULS</center></th>
                <th><center>PTS</center></th>
            </tr>";

    // ========== FILL ROWS

    if ($display == 0) {
        $numstop = $num;
    } else {
        $numstop = $display;
    }

    if ($display == null) {
        $numstop = $num;
    } else {
        $numstop = $display;
    }

    $i = 0;

    while ($i < $numstop) {
        $retired = 0;
        if ($tableforquery == "ibl_plr") {
            $retired = $db->sql_result($result, $i, "retired");
            if ($retired == 0) {
                $plyr_name = $db->sql_result($result, $i, "name");
                $pid = $db->sql_result($result, $i, "pid");
                $gm = number_format($db->sql_result($result, $i, "car_gm"));
                $min = number_format($db->sql_result($result, $i, "car_min"));
                $fgm = number_format($db->sql_result($result, $i, "car_fgm"));
                $fga = number_format($db->sql_result($result, $i, "car_fga"));
                $fgpct = ($db->sql_result($result, $i, "car_fga")) ? number_format($db->sql_result($result, $i, "car_fgm") / $db->sql_result($result, $i, "car_fga"), 3) : "0.000";
                $ftm = number_format($db->sql_result($result, $i, "car_ftm"));
                $fta = number_format($db->sql_result($result, $i, "car_fta"));
                $ftpct = ($db->sql_result($result, $i, "car_fta")) ? number_format($db->sql_result($result, $i, "car_ftm") / $db->sql_result($result, $i, "car_fta"), 3) : "0.000";
                $tgm = number_format($db->sql_result($result, $i, "car_tgm"));
                $tga = number_format($db->sql_result($result, $i, "car_tga"));
                $tpct = ($db->sql_result($result, $i, "car_tga")) ? number_format($db->sql_result($result, $i, "car_tgm") / $db->sql_result($result, $i, "car_tga"), 3) : "0.000";
                $orb = number_format($db->sql_result($result, $i, "car_orb"));
                $reb = number_format($db->sql_result($result, $i, "car_reb"));
                $ast = number_format($db->sql_result($result, $i, "car_ast"));
                $stl = number_format($db->sql_result($result, $i, "car_stl"));
                $to = number_format($db->sql_result($result, $i, "car_to"));
                $blk = number_format($db->sql_result($result, $i, "car_blk"));
                $pf = number_format($db->sql_result($result, $i, "car_pf"));
                $pts = number_format($db->sql_result($result, $i, "car_pts"));
            } else {
                $plyr_name = $db->sql_result($result, $i, "name");
                $pid = $db->sql_result($result, $i, "pid");
                $result_iblhist = $db->sql_query("SELECT
                    sum(gm) as gm,
                    sum(min) as min,
                    sum(fgm) as fgm,
                    sum(fga) as fga,
                    sum(ftm) as ftm,
                    sum(fta) as fta,
                    sum(3gm) as 3gm,
                    sum(3ga) as 3ga,
                    sum(orb) as orb,
                    sum(reb) as reb,
                    sum(ast) as ast,
                    sum(stl) as stl,
                    sum(blk) as blk,
                    sum(tvr) as tvr,
                    sum(pf) as pf,
                    sum(ftm) + sum(3gm) + (2 * sum(fgm)) as pts
                    FROM ibl_hist
                    WHERE pid = $pid;");
                $gm = number_format($db->sql_result($result_iblhist, 0, "gm"));
                $min = number_format($db->sql_result($result_iblhist, 0, "min"));
                $fgm = number_format($db->sql_result($result_iblhist, 0, "fgm"));
                $fga = number_format($db->sql_result($result_iblhist, 0, "fga"));
                $fgpct = ($db->sql_result($result_iblhist, 0, "fga")) ? number_format($db->sql_result($result_iblhist, 0, "fgm") / $db->sql_result($result_iblhist, 0, "fga"), 3) : "0.000";
                $ftm = number_format($db->sql_result($result_iblhist, 0, "ftm"));
                $fta = number_format($db->sql_result($result_iblhist, 0, "fta"));
                $ftpct = ($db->sql_result($result_iblhist, 0, "fta")) ? number_format($db->sql_result($result_iblhist, 0, "ftm") / $db->sql_result($result_iblhist, 0, "fta"), 3) : "0.000";
                $tgm = number_format($db->sql_result($result_iblhist, 0, "3gm"));
                $tga = number_format($db->sql_result($result_iblhist, 0, "3ga"));
                $tpct = ($db->sql_result($result_iblhist, 0, "3ga")) ? number_format($db->sql_result($result_iblhist, 0, "3gm") / $db->sql_result($result_iblhist, 0, "3ga"), 3) : "0.000";
                $orb = number_format($db->sql_result($result_iblhist, 0, "orb"));
                $reb = number_format($db->sql_result($result_iblhist, 0, "reb"));
                $ast = number_format($db->sql_result($result_iblhist, 0, "ast"));
                $stl = number_format($db->sql_result($result_iblhist, 0, "stl"));
                $to = number_format($db->sql_result($result_iblhist, 0, "tvr"));
                $blk = number_format($db->sql_result($result_iblhist, 0, "blk"));
                $pf = number_format($db->sql_result($result_iblhist, 0, "pf"));
                $pts = number_format($db->sql_result($result_iblhist, 0, "pts"));
            }
        }

        if (
            $tableforquery == "ibl_season_career_avgs" or
            $tableforquery == "ibl_heat_career_avgs" or
            $tableforquery == "ibl_playoff_career_avgs"
        ) {
            $plyr_name = $db->sql_result($result, $i, "name");
            $pid = $db->sql_result($result, $i, "pid");
            $gm = number_format($db->sql_result($result, $i, "games"));
            $min = number_format($db->sql_result($result, $i, "minutes"), 1);
            $fgm = number_format($db->sql_result($result, $i, "fgm"), 1);
            $fga = number_format($db->sql_result($result, $i, "fga"), 1);
            $fgpct = number_format($db->sql_result($result, $i, "fgpct"), 3);
            $ftm = number_format($db->sql_result($result, $i, "ftm"), 1);
            $fta = number_format($db->sql_result($result, $i, "fta"), 1);
            $ftpct = number_format($db->sql_result($result, $i, "ftpct"), 3);
            $tgm = number_format($db->sql_result($result, $i, "tgm"), 1);
            $tga = number_format($db->sql_result($result, $i, "tga"), 1);
            $tpct = number_format($db->sql_result($result, $i, "tpct"), 3);
            $orb = number_format($db->sql_result($result, $i, "orb"), 1);
            $reb = number_format($db->sql_result($result, $i, "reb"), 1);
            $ast = number_format($db->sql_result($result, $i, "ast"), 1);
            $stl = number_format($db->sql_result($result, $i, "stl"), 1);
            $to = number_format($db->sql_result($result, $i, "tvr"), 1);
            $blk = number_format($db->sql_result($result, $i, "blk"), 1);
            $pf = number_format($db->sql_result($result, $i, "pf"), 1);
            $pts = number_format($db->sql_result($result, $i, "pts"), 1);
        }

        if (
            $tableforquery == "ibl_heat_career_totals" or
            $tableforquery == "ibl_playoff_career_totals"
        ) {
            $plyr_name = $db->sql_result($result, $i, "name");
            $pid = $db->sql_result($result, $i, "pid");
            $gm = number_format($db->sql_result($result, $i, "games"));
            $min = number_format($db->sql_result($result, $i, "minutes"));
            $fgm = number_format($db->sql_result($result, $i, "fgm"));
            $fga = number_format($db->sql_result($result, $i, "fga"));
            $fgpct = ($db->sql_result($result, $i, "fga")) ? number_format(($db->sql_result($result, $i, "fgm")) / ($db->sql_result($result, $i, "fga")), 3) : "0.000";
            $ftm = number_format($db->sql_result($result, $i, "ftm"));
            $fta = number_format($db->sql_result($result, $i, "fta"));
            $ftpct = ($db->sql_result($result, $i, "fta")) ? number_format(($db->sql_result($result, $i, "ftm")) / ($db->sql_result($result, $i, "fta")), 3) : "0.000";
            $tgm = number_format($db->sql_result($result, $i, "tgm"));
            $tga = number_format($db->sql_result($result, $i, "tga"));
            $tpct = ($db->sql_result($result, $i, "tga")) ? number_format(($db->sql_result($result, $i, "tgm")) / ($db->sql_result($result, $i, "tga")), 3) : "0.000";
            $orb = number_format($db->sql_result($result, $i, "orb"));
            $reb = number_format($db->sql_result($result, $i, "reb"));
            $ast = number_format($db->sql_result($result, $i, "ast"));
            $stl = number_format($db->sql_result($result, $i, "stl"));
            $to = number_format($db->sql_result($result, $i, "tvr"));
            $blk = number_format($db->sql_result($result, $i, "blk"));
            $pf = number_format($db->sql_result($result, $i, "pf"));
            $pts = number_format($db->sql_result($result, $i, "pts"));
        }

        $i++;

        echo "<tr>
            <td><center>$i</center></td>
            <td><center><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$plyr_name";

        if ($retired == 1) {
            echo "*";
        }

        echo "</center></td>
            <td><center>$gm</center></td>
            <td><center>$min</center></td>
            <td><center>$fgm</center></td>
            <td><center>$fga</center></td>
            <td><center>$fgpct</center></td>
            <td><center>$ftm</center></td>
            <td><center>$fta</center></td>
            <td><center>$ftpct</center></td>
            <td><center>$tgm</center></td>
            <td><center>$tga</center></td>
            <td><center>$tpct</center></td>
            <td><center>$orb</center></td>
            <td><center>$reb</center></td>
            <td><center>$ast</center></td>
            <td><center>$stl</center></td>
            <td><center>$to</center></td>
            <td><center>$blk</center></td>
            <td><center>$pf</center></td>
            <td><center>$pts</center></td>
        </tr>";
    }

    echo "</table></center></td></tr>";
}

CloseTable();
include "footer.php";