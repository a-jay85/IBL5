<?php

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "Season Stats";

{
    Nuke\Header::header();
    OpenTable();
    $year = $_POST['year'];
    $team = $_POST['team'];
    $position = $_POST['position'];
    $sortby = $_POST['sortby'];

    if ($year == '') {
        $argument = $argument . "";
    } else {
        $argument = $argument . "AND year= '$year'";
    }

    if ($team == 0) {
        $argument = $argument . "";
    } else {
        $argument = $argument . " AND teamid = $team";
    }

    if ($sortby == "1") {
        $sort = "((2*`fgm`+`ftm`+`tgm`)/`games`)";
    } else if ($sortby == "2") {
        $sort = "((reb)/`games`)";
    } else if ($sortby == "3") {
        $sort = "((orb)/`games`)";
    } else if ($sortby == "4") {
        $sort = "((ast)/`games`)";
    } else if ($sortby == "5") {
        $sort = "((stl)/`games`)";
    } else if ($sortby == "6") {
        $sort = "((blk)/`games`)";
    } else if ($sortby == "7") {
        $sort = "((tvr)/`games`)";
    } else if ($sortby == "8") {
        $sort = "((pf)/`games`)";
    } else if ($sortby == "9") {
        $sort = "((((2*fgm+ftm+tgm)+reb+(2*ast)+(2*stl)+(2*blk))-((fga-fgm)+(fta-ftm)+tvr+pf))/gm)";
    } else if ($sortby == "10") {
        $sort = "((fgm)/`games`)";
    } else if ($sortby == "11") {
        $sort = "((fga)/`games`)";
    } else if ($sortby == "12") {
        $sort = "(fgm/fga)";
    } else if ($sortby == "13") {
        $sort = "((ftm)/`games`)";
    } else if ($sortby == "14") {
        $sort = "((fta)/`games`)";
    } else if ($sortby == "15") {
        $sort = "(ftm/fta)";
    } else if ($sortby == "16") {
        $sort = "((tgm)/`games`)";
    } else if ($sortby == "17") {
        $sort = "((tga)/`games`)";
    } else if ($sortby == "18") {
        $sort = "(tgm/tga)";
    } else if ($sortby == "19") {
        $sort = "(gm)";
    } else if ($sortby == "20") {
        $sort = "((min)/`games`)";
    } else {
        $sort = "((2*`fgm`+`ftm`+`tgm`)/`games`)";
    }

    $query = "SELECT * FROM ibl_hist where name is not null $argument ORDER BY $sort DESC";
    $result = $db->sql_query($query);
    $num = $db->sql_numrows($result);

    echo "<form name=\"Leaderboards\" method=\"post\" action=\"modules.php?name=Season_Leaders\">";
    echo "<table border=1>";
    echo "<tr><td><b>Team</td><td><select name=\"team\">";
    team_option($team);
    echo "</select></td>";
    echo "<td><b>Year</td><td><select name=\"year\">";
    year_option($year);
    echo "<td><b>Sort By</td><td><select name=\"sortby\">";
    sort_option($sortby);
    echo "</select></td>";
    echo "</select></td><td><input type=\"submit\" value=\"Search Season Data\"></td>";
    echo "</tr></table>";

    echo "<table cellpadding=3 CELLSPACING=0 border=0><tr bgcolor=C2D69A><td><b>Rank</td><td><b>Year</td><td><b>Name</td><td><b>Team</td><td><b>G</td><td><b>Min</td><td allign=right><b>fgm</td><td><b>fga</td><td allign=right><b>fg%</td><td><b>ftm</td><td allign=right><b>fta</td><td><b>ft%</td><td allign=right><b>tgm</td><td><b>tga</td><td allign=right><b>tg%</td><td><b>orb</td><td allign=right><b>reb</td><td><b>ast</td><td allign=right><b>stl</td><td><b>to</td><td allign=right><b>blk</td><td><b>pf</td><td allign=right><b>ppg</td><td allign=right><b>qa</td></tr>";

    while ($i < $num) {
        $pid = $db->sql_result($result, $i, "pid");
        $pos = $db->sql_result($result, $i, "pos");
        $name = $db->sql_result($result, $i, "name");
        $yr = $db->sql_result($result, $i, "year");
        $teamname = $db->sql_result($result, $i, "team");
        $teamid = $db->sql_result($result, $i, "teamid");
        //$chunknumber=$db->sql_result($result,$i,"chunk");
        //$qa=$db->sql_result($result,$i,"qa");
        $stats_gm = $db->sql_result($result, $i, "games");
        $stats_min = $db->sql_result($result, $i, "min");
        $stats_fgm = $db->sql_result($result, $i, "fgm");
        $stats_fga = $db->sql_result($result, $i, "fga");
        @$stats_fgp = number_format(($stats_fgm / $stats_fga * 100), 1);
        $stats_ftm = $db->sql_result($result, $i, "ftm");
        $stats_fta = $db->sql_result($result, $i, "fta");
        @$stats_ftp = number_format(($stats_ftm / $stats_fta * 100), 1);
        $stats_tgm = $db->sql_result($result, $i, "tgm");
        $stats_tga = $db->sql_result($result, $i, "tga");
        @$stats_tgp = number_format(($stats_tgm / $stats_tga * 100), 1);
        $stats_orb = $db->sql_result($result, $i, "orb");
        $stats_reb = $db->sql_result($result, $i, "reb");
        $stats_drb = $stats_reb - $stats_orb;
        $stats_ast = $db->sql_result($result, $i, "ast");
        $stats_stl = $db->sql_result($result, $i, "stl");
        $stats_to = $db->sql_result($result, $i, "tvr");
        $stats_blk = $db->sql_result($result, $i, "blk");
        $stats_pf = $db->sql_result($result, $i, "pf");
        $stats_pts = 2 * $stats_fgm + $stats_ftm + $stats_tgm;

        @$stats_mpg = number_format(($stats_min / $stats_gm), 1);
        @$stats_fgmpg = number_format(($stats_fgm / $stats_gm), 1);
        @$stats_fgapg = number_format(($stats_fga / $stats_gm), 1);
        @$stats_ftmpg = number_format(($stats_ftm / $stats_gm), 1);
        @$stats_ftapg = number_format(($stats_fta / $stats_gm), 1);
        @$stats_tgmpg = number_format(($stats_tgm / $stats_gm), 1);
        @$stats_tgapg = number_format(($stats_tga / $stats_gm), 1);
        @$stats_orbpg = number_format(($stats_orb / $stats_gm), 1);
        @$stats_rpg = number_format(($stats_reb / $stats_gm), 1);
        @$stats_apg = number_format(($stats_ast / $stats_gm), 1);
        @$stats_spg = number_format(($stats_stl / $stats_gm), 1);
        @$stats_tpg = number_format(($stats_to / $stats_gm), 1);
        @$stats_bpg = number_format(($stats_blk / $stats_gm), 1);
        @$stats_fpg = number_format(($stats_pf / $stats_gm), 1);
        @$stats_ppg = number_format(($stats_pts / $stats_gm), 1);

        if ($stats_gm > 0) {
            $qa = number_format((($stats_pts + $stats_reb + (2 * $stats_ast) + (2 * $stats_stl) + (2 * $stats_blk)) - (($stats_fga - $stats_fgm) + ($stats_fta - $stats_ftm) + $stats_to + $stats_pf)) / $stats_gm, 1);
        } else {
            $qa = number_format(0, 1);
        }

        if (($i % 2) == 0) {
            $bgcolor = "DDDDDD";
        } else {
            $bgcolor = "FFFFFF";
        }

        $i++;
        echo "<tr bgcolor=$bgcolor><td>$i.</td><td>$yr</td><td><a href=modules.php?name=Player&pa=showpage&pid=$pid>$name</a></td><td><a href=modules.php?name=Team&op=team&teamID=$teamid>$teamname</a></td><td>$stats_gm</td><td align=right>$stats_mpg</td><td align=right>$stats_fgmpg</td><td align=right>$stats_fgapg</td><td align=right>$stats_fgp</td><td align=right>$stats_ftmpg</td><td align=right>$stats_ftapg</td><td align=right>$stats_ftp</td><td align=right>$stats_tgmpg</td><td align=right>$stats_tgapg</td><td align=right>$stats_tgp</td><td align=right>$stats_orbpg</td><td align=right>$stats_rpg</td><td align=right>$stats_apg</td><td align=right>$stats_spg</td><td align=right>$stats_tpg</td><td align=right>$stats_bpg</td><td align=right>$stats_fpg</td><td align=right>$stats_ppg</td><td>$qa</td></tr>";

    }

    echo "</table></form>";
    CloseTable();
    Nuke\Footer::footer();
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
        if ($team_selected == $Team) {
            echo "<option value=$tid SELECTED>$Team</option>";
        } else {
            echo "<option value=$tid>$Team</option>";
        }
    }
}

//function year_option ($year_selected)
//{
//    $query="SELECT distinct year FROM ibl_hist WHERE teamid BETWEEN 1 AND 32 ORDER BY teamid ASC";
//    $result=$db->sql_query($query);
//    $num=$db->sql_numrows($result);
//    echo "<option value=0>All</option>";
//    $i=0;
//    while ($i < $num)
//    {
//        $year=$db->sql_result($result,$i,"year");
//
//        $i++;
//        if ($year_selected == $year)
//        {
//            echo "<option value=$year SELECTED>$year</option>";
//        }else{
//            echo "<option value=$year>$year</option>";
//        }
//    }
//}

function year_option($year_selected)
{
    global $db;

    $years = $db->sql_query("SELECT DISTINCT year FROM ibl_hist;");
    $yearsArray = array();
    $i = 0;
    while ($i < $db->sql_numrows($years)) {
        $yearsArray[] = $db->sql_result($years, $i, 'year');
        $i++;
    }

    echo "<option value=''>All</option>";

    $i = 0;
    while ($i < sizeof($yearsArray)) {
        $year = $yearsArray[$i];

        if ($year_selected == $year) {
            echo "<option value='$year' SELECTED>$year</option>";
        } else {
            echo "<option value='$year'>$year</option>";
        }

        $i++;
    }
}

function sort_option($sort_selected)
{
    $arr = array("PPG", "REB", "OREB", "AST", "STL", "BLK", "TO", "FOUL", "QA", "FGM", "FGA", "FG%", "FTM", "FTA", "FT%", "TGM", "TGA", "TG%", "GAMES", "MIN");
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
