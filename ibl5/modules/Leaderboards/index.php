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

if (!eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die ("You can't access this file directly...");
}

require_once("mainfile.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/sharedFunctions.php';

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Player Archives";

function menu()
{
    echo "<center><b>
    <a href=\"modules.php?name=Player&pa=search\">Player Search</a>  |
    <a href=\"modules.php?name=Player&pa=awards\">Awards Search</a> |
    <a href=\"modules.php?name=One-on-One\">One-on-One Game</a> |
    <a href=\"modules.php?name=Leaderboards\">Career Leaderboards</a> (All Types)
    </b><hr>";
}

function leaderboards()
{
    global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;
    include("header.php");
    OpenTable();

    menu();

    $boards_type = $_POST['boards_type'];
    $display = $_POST['display'];
    $active = $_POST['active'];
    $sort_cat = $_POST['sort_cat'];
    $submitted = $_POST['submitted'];

    echo "<form name=\"Leaderboards\" method=\"post\" action=\"modules.php?name=Leaderboards\">
        <center><table><tr><td>Type: <select name=\"boards_type\">";

    // TODO: continue refactoring this function to take up waaay less lines

    $typeArray = array(
        'Reg' => 'Regular Season Totals',
        'Rav' => 'Regular Season Averages',
        'Ply' => 'Playoff Totals',
        'PLA' => 'Playoff Averages',
        'HET' => 'H.E.A.T. Totals',
        'HEA' => 'H.E.A.T. Averages'
    );

    foreach ($typeArray as $key => $value) {
        echo "  <option value=\"$key\"";
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
        'pf' => 'Personal Fouls'
    );

    foreach ($sort_cat_array as $key => $value) {
        echo "  <option value=\"$value\"";
        if ($sort_cat == $value) {
            echo ' SELECTED';
        }
        echo ">$value</option>";
    }

    echo "</select></td><td>
          Search: <select name=\"active\">";

    if ($active == '0') {
        echo "  <option value=\"0\" SELECTED>All Players</option>";
    } else {
        echo "  <option value=\"0\">All Players</option>";
    }

    if ($active == '1') {
        echo "  <option value=\"1\" SELECTED>Active Players Only</option>";
    } else {
        echo "  <option value=\"1\">Active Players Only</option>";
    }

    echo "</select></td>
          <td>Limit: <input type=\"text\" name=\"display\" size=\"4\" value=\"$display\"> Records</td><td>
          <input type=\"hidden\" name=\"submitted\" value=\"1\">
          <input type=\"submit\" value=\"Display Leaderboards\"></form>
          </td></tr></table>";

    // ===== RUN QUERY IF FORM HAS BEEN SUBMITTED

    if ($submitted != NULL) {
        $tableforquery = "nuke_iblplyr";

        if ($boards_type == 'Reg') {
            $tableforquery = "nuke_iblplyr";
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

        if ($tableforquery == "nuke_iblplyr") {
            $sortby = "car_".$sortby;
            if ($sort_cat == 'GM') {
                 $sortby = "car_gm";
            }
            if ($sort_cat == 'MIN') {
                $sortby = "car_min";
            }
            if ($sort_cat == 'TVR') {
                $sortby = "car_to";
            }
        }

        $query = "SELECT * FROM $tableforquery WHERE $restriction1 $restriction2 ORDER BY $sortby DESC";
        $result = mysql_query($query);
        $num = mysql_numrows($result);

      echo "<center><h2>Leaderboards Display</h2><p><table class=\"sortable\">
            <tr><th><center>Rank</th></center><th><center>Name</center></th><th><center>Gm</center></th>
            <th><center>Min</center></th><th><center>FGM</a></center></th><th><center>FGA</center></th>
            <th><center>FG%</center><th><center>FTM</center></th><th><center>FTA</center></th>
            <th><center>FT%</center><th><center>3PTM</center></th><th><center>3PTA</center></th>
            <th><center>3P%</center><th><center>ORB</center></th><th><center>REB</center></th>
            <th><center>AST</center></th><th><center>STL</center></th><th><center>TVR</center></th>
            <th><center>BLK</center></th><th><center>FOULS</center></th><th><center>PTS</center></th></tr>";

        // ========== FILL ROWS

        if ($display == 0) {
            $numstop = $num;
        } else {
            $numstop = $display;
        }

        if ($display == NULL) {
            $numstop = $num;
        } else {
            $numstop = $display;
        }

        $i = 0;

        while ($i < $numstop) {
            $retired=0;
            if ($tableforquery == "nuke_iblplyr") {
                $retired=mysql_result($result,$i,"retired");
                $plyr_name=mysql_result($result,$i,"name");
                $pid=mysql_result($result,$i,"pid");
                $gm=number_format(mysql_result($result,$i,"car_gm"));
                $min=number_format(mysql_result($result,$i,"car_min"));
                $fgm=number_format(mysql_result($result,$i,"car_fgm"));
                $fga=number_format(mysql_result($result,$i,"car_fga"));
                $fgpct = number_format(mysql_result($result,$i,"car_fgm") / mysql_result($result,$i,"car_fga"), 3);
                $ftm=number_format(mysql_result($result,$i,"car_ftm"));
                $fta=number_format(mysql_result($result,$i,"car_fta"));
                $ftpct = number_format(mysql_result($result,$i,"car_ftm") / mysql_result($result,$i,"car_fta"), 3);
                $tgm=number_format(mysql_result($result,$i,"car_tgm"));
                $tga=number_format(mysql_result($result,$i,"car_tga"));
                $tpct = number_format(mysql_result($result,$i,"car_tgm") / mysql_result($result,$i,"car_tga"), 3);
                $orb=number_format(mysql_result($result,$i,"car_orb"));
                $reb=number_format(mysql_result($result,$i,"car_reb"));
                $ast=number_format(mysql_result($result,$i,"car_ast"));
                $stl=number_format(mysql_result($result,$i,"car_stl"));
                $to=number_format(mysql_result($result,$i,"car_to"));
                $blk=number_format(mysql_result($result,$i,"car_blk"));
                $pf=number_format(mysql_result($result,$i,"car_pf"));
                $pts=number_format(mysql_result($result,$i,"car_pts"));
            }

            if (
                $tableforquery == "ibl_season_career_avgs" OR
                $tableforquery == "ibl_heat_career_avgs" OR
                $tableforquery == "ibl_playoff_career_avgs"
            ) {
                $plyr_name = mysql_result($result, $i, "name");
                $pid = mysql_result($result, $i, "pid");
                $gm = number_format(mysql_result($result, $i, "games"));
                $min = number_format(mysql_result($result, $i, "minutes"), 1);
                $fgm = number_format(mysql_result($result, $i, "fgm"), 1);
                $fga = number_format(mysql_result($result, $i, "fga"), 1);
                $fgpct = number_format(mysql_result($result, $i, "fgpct"), 3);
                $ftm = number_format(mysql_result($result, $i, "ftm"), 1);
                $fta = number_format(mysql_result($result, $i, "fta"), 1);
                $ftpct = number_format(mysql_result($result, $i, "ftpct"), 3);
                $tgm = number_format(mysql_result($result, $i, "tgm"), 1);
                $tga = number_format(mysql_result($result, $i, "tga"), 1);
                $tpct = number_format(mysql_result($result, $i, "tpct"), 3);
                $orb = number_format(mysql_result($result, $i, "orb"), 1);
                $reb = number_format(mysql_result($result, $i, "reb"), 1);
                $ast = number_format(mysql_result($result, $i, "ast"), 1);
                $stl = number_format(mysql_result($result, $i, "stl"), 1);
                $to = number_format(mysql_result($result, $i, "tvr"), 1);
                $blk = number_format(mysql_result($result, $i, "blk"), 1);
                $pf = number_format(mysql_result($result, $i, "pf"), 1);
                $pts = number_format(mysql_result($result, $i, "pts"), 1);
            }

            if (
                $tableforquery == "ibl_heat_career_totals" OR
                $tableforquery == "ibl_playoff_career_totals"
            ) {
                $plyr_name=mysql_result($result,$i,"name");
                $pid=mysql_result($result,$i,"pid");
                $gm=number_format(mysql_result($result,$i,"games"));
                $min=number_format(mysql_result($result,$i,"minutes"));
                $fgm=number_format(mysql_result($result,$i,"fgm"));
                $fga=number_format(mysql_result($result,$i,"fga"));
                $fgpct = number_format($fgm / $fga, 3);
                $ftm=number_format(mysql_result($result,$i,"ftm"));
                $fta=number_format(mysql_result($result,$i,"fta"));
                $ftpct = number_format($ftm / $fta, 3);
                $tgm=number_format(mysql_result($result,$i,"tgm"));
                $tga=number_format(mysql_result($result,$i,"tga"));
                $tpct = number_format($tgm / $tga, 3);
                $orb=number_format(mysql_result($result,$i,"orb"));
                $reb=number_format(mysql_result($result,$i,"reb"));
                $ast=number_format(mysql_result($result,$i,"ast"));
                $stl=number_format(mysql_result($result,$i,"stl"));
                $to=number_format(mysql_result($result,$i,"tvr"));
                $blk=number_format(mysql_result($result,$i,"blk"));
                $pf=number_format(mysql_result($result,$i,"pf"));
                $pts=number_format(mysql_result($result,$i,"pts"));
            }

            $i++;

            echo "<tr><td><center>$i</center></td><td><center><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$plyr_name";

            if ($retired == 1) {
                echo "*";
            }

            echo "</center></td><td><center>$gm</center></td><td><center>$min</center></td><td><center>$fgm</center></td>
                <td><center>$fga</center></td><td><center>$fgpct</center></td><td><center>$ftm</center></td><td><center>$fta</center></td>
                <td><center>$ftpct</center><td><center>$tgm</center></td><td><center>$tga</center></td><td><center>$tpct</center></td>
                <td><center>$orb</center></td><td><center>$reb</center></td><td><center>$ast</center></td><td><center>$stl</center></td>
                <td><center>$to</center></td><td><center>$blk</center></td><td><center>$pf</center></td><td><center>$pts</center></td></tr>";
        }

        echo "</table></center></td></tr>";
    }

    CloseTable();
    include("footer.php");
}

leaderboards();

?>
