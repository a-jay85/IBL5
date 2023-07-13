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

if (!defined('BLOCK_FILE')) {
    Header("Location: ./index.php");
    die();
}

global $db;

$query = "SELECT * FROM ibl_plr WHERE retired = 0 AND name NOT LIKE \"%Buyouts%\" ORDER BY ordinal ASC";
$result = $db->sql_query($query);
$num = $db->sql_numrows($result);

$name1 = $name2 = $name3 = $name4 = $name5 = "";
$ppg1 = $ppg2 = $ppg3 = $ppg4 = $ppg5 = 0;
$reb1 = $reb2 = $reb3 = $reb4 = $reb5 = 0;
$ast1 = $ast2 = $ast3 = $ast4 = $ast5 = 0;
$stl1 = $stl2 = $stl3 = $stl4 = $stl5 = 0;
$blk1 = $blk2 = $blk3 = $blk4 = $blk5 = 0;

$i = 0;
while ($i < $num) {
    $name = $db->sql_result($result, $i, "name");
    $p_ord = $db->sql_result($result, $i, "ordinal");
    $pid = $db->sql_result($result, $i, "pid");
    $tid = $db->sql_result($result, $i, "tid");
    $teamname = $db->sql_result($result, $i, "teamname");

    $stats_gm = $db->sql_result($result, $i, "stats_gm");

    $stats_fgm = $db->sql_result($result, $i, "stats_fgm");
    $stats_ftm = $db->sql_result($result, $i, "stats_ftm");
    $stats_tgm = $db->sql_result($result, $i, "stats_3gm");

    $stats_orb = $db->sql_result($result, $i, "stats_orb");
    $stats_drb = $db->sql_result($result, $i, "stats_drb");
    $stats_ast = $db->sql_result($result, $i, "stats_ast");
    $stats_stl = $db->sql_result($result, $i, "stats_stl");
    $stats_blk = $db->sql_result($result, $i, "stats_blk");

    $stats_reb = $stats_orb + $stats_drb;
    $stats_pts = 2 * $stats_fgm + $stats_ftm + $stats_tgm;

    @$stats_ppg = ($stats_pts / $stats_gm);
    @$stats_reb = ($stats_reb / $stats_gm);
    @$stats_ast = ($stats_ast / $stats_gm);
    @$stats_stl = ($stats_stl / $stats_gm);
    @$stats_blk = ($stats_blk / $stats_gm);

    $stats_ppg = round($stats_ppg, 2);
    $stats_reb = round($stats_reb, 2);
    $stats_ast = round($stats_ast, 2);
    $stats_stl = round($stats_stl, 2);
    $stats_blk = round($stats_blk, 2);

    if ($stats_ppg > $ppg1) {
//----PPG---

        $ppg5 = $ppg4;
        $ppg4 = $ppg3;
        $ppg3 = $ppg2;
        $ppg2 = $ppg1;
        $ppg1 = $stats_ppg;
        $name5 = $name4;
        $name4 = $name3;
        $name3 = $name2;
        $name2 = $name1;
        $name1 = $name;
        $teamname5 = $teamname4;
        $teamname4 = $teamname3;
        $teamname3 = $teamname2;
        $teamname2 = $teamname1;
        $teamname1 = $teamname;
        $pid5 = $pid4;
        $pid4 = $pid3;
        $pid3 = $pid2;
        $pid2 = $pid1;
        $pid1 = $pid;
        $tid5 = $tid4;
        $tid4 = $tid3;
        $tid3 = $tid2;
        $tid2 = $tid1;
        $tid1 = $tid;
    } elseif ($stats_ppg > $ppg2) {
        $ppg5 = $ppg4;
        $ppg4 = $ppg3;
        $ppg3 = $ppg2;
        $ppg2 = $stats_ppg;
        $name5 = $name4;
        $name4 = $name3;
        $name3 = $name2;
        $name2 = $name;
        $teamname5 = $teamname4;
        $teamname4 = $teamname3;
        $teamname3 = $teamname2;
        $teamname2 = $teamname;
        $pid5 = $pid4;
        $pid4 = $pid3;
        $pid3 = $pid2;
        $pid2 = $pid;
        $tid5 = $tid4;
        $tid4 = $tid3;
        $tid3 = $tid2;
        $tid2 = $tid;
    } elseif ($stats_ppg > $ppg3) {
        $ppg5 = $ppg4;
        $ppg4 = $ppg3;
        $ppg3 = $stats_ppg;
        $name5 = $name4;
        $name4 = $name3;
        $name3 = $name;
        $teamname5 = $teamname4;
        $teamname4 = $teamname3;
        $teamname3 = $teamname;
        $pid5 = $pid4;
        $pid4 = $pid3;
        $pid3 = $pid;
        $tid5 = $tid4;
        $tid4 = $tid3;
        $tid3 = $tid;
    } elseif ($stats_ppg > $ppg4) {
        $ppg5 = $ppg4;
        $ppg4 = $stats_ppg;
        $name5 = $name4;
        $name4 = $name;
        $teamname5 = $teamname4;
        $teamname4 = $teamname;
        $pid5 = $pid4;
        $pid4 = $pid;
        $tid5 = $tid4;
        $tid4 = $tid;
    } elseif ($stats_ppg > $ppg5) {
        $ppg5 = $stats_ppg;
        $name5 = $name;
        $teamname5 = $teamname;
        $pid5 = $pid;
        $tid5 = $tid;
    }

//----REB---

    if ($stats_reb > $reb1) {
        $reb5 = $reb4;
        $reb4 = $reb3;
        $reb3 = $reb2;
        $reb2 = $reb1;
        $reb1 = $stats_reb;
        $name_reb5 = $name_reb4;
        $name_reb4 = $name_reb3;
        $name_reb3 = $name_reb2;
        $name_reb2 = $name_reb1;
        $name_reb1 = $name;
        $teamname_reb5 = $teamname_reb4;
        $teamname_reb4 = $teamname_reb3;
        $teamname_reb3 = $teamname_reb2;
        $teamname_reb2 = $teamname_reb1;
        $teamname_reb1 = $teamname;
        $pidreb5 = $pidreb4;
        $pidreb4 = $pidreb3;
        $pidreb3 = $pidreb2;
        $pidreb2 = $pidreb1;
        $pidreb1 = $pid;
        $tidreb5 = $tidreb4;
        $tidreb4 = $tidreb3;
        $tidreb3 = $tidreb2;
        $tidreb2 = $tidreb1;
        $tidreb1 = $tid;
    } elseif ($stats_reb > $reb2) {
        $reb5 = $reb4;
        $reb4 = $reb3;
        $reb3 = $reb2;
        $reb2 = $stats_reb;
        $name_reb5 = $name_reb4;
        $name_reb4 = $name_reb3;
        $name_reb3 = $name_reb2;
        $name_reb2 = $name;
        $teamname_reb5 = $teamname_reb4;
        $teamname_reb4 = $teamname_reb3;
        $teamname_reb3 = $teamname_reb2;
        $teamname_reb2 = $teamname;
        $pidreb5 = $pidreb4;
        $pidreb4 = $pidreb3;
        $pidreb3 = $pidreb2;
        $pidreb2 = $pid;
        $tidreb5 = $tidreb4;
        $tidreb4 = $tidreb3;
        $tidreb3 = $tidreb2;
        $tidreb2 = $tid;
    } elseif ($stats_reb > $reb3) {
        $reb5 = $reb4;
        $reb4 = $reb3;
        $reb3 = $stats_reb;
        $name_reb5 = $name_reb4;
        $name_reb4 = $name_reb3;
        $name_reb3 = $name;
        $teamname_reb5 = $teamname_reb4;
        $teamname_reb4 = $teamname_reb3;
        $teamname_reb3 = $teamname;
        $pidreb5 = $pidreb4;
        $pidreb4 = $pidreb3;
        $pidreb3 = $pid;
        $tidreb5 = $tidreb4;
        $tidreb4 = $tidreb3;
        $tidreb3 = $tid;
    } elseif ($stats_reb > $reb4) {
        $reb5 = $reb4;
        $reb4 = $stats_reb;
        $name_reb5 = $name_reb4;
        $name_reb4 = $name;
        $teamname_reb5 = $teamname_reb4;
        $teamname_reb4 = $teamname;
        $pidreb5 = $pidreb4;
        $pidreb4 = $pid;
        $tidreb5 = $tidreb4;
        $tidreb4 = $tid;
    } elseif ($stats_reb > $reb5) {
        $reb5 = $stats_reb;
        $name_reb5 = $name;
        $teamname_reb5 = $teamname;
        $pidreb5 = $pid;
        $tidreb5 = $tid;
    }

//----AST---

    if ($stats_ast > $ast1) {
        $ast5 = $ast4;
        $ast4 = $ast3;
        $ast3 = $ast2;
        $ast2 = $ast1;
        $ast1 = $stats_ast;
        $name_ast5 = $name_ast4;
        $name_ast4 = $name_ast3;
        $name_ast3 = $name_ast2;
        $name_ast2 = $name_ast1;
        $name_ast1 = $name;
        $teamname_ast5 = $teamname_ast4;
        $teamname_ast4 = $teamname_ast3;
        $teamname_ast3 = $teamname_ast2;
        $teamname_ast2 = $teamname_ast1;
        $teamname_ast1 = $teamname;
        $pidast5 = $pidast4;
        $pidast4 = $pidast3;
        $pidast3 = $pidast2;
        $pidast2 = $pidast1;
        $pidast1 = $pid;
        $tidast5 = $tidast4;
        $tidast4 = $tidast3;
        $tidast3 = $tidast2;
        $tidast2 = $tidast1;
        $tidast1 = $tid;
    } elseif ($stats_ast > $ast2) {
        $ast5 = $ast4;
        $ast4 = $ast3;
        $ast3 = $ast2;
        $ast2 = $stats_ast;
        $name_ast5 = $name_ast4;
        $name_ast4 = $name_ast3;
        $name_ast3 = $name_ast2;
        $name_ast2 = $name;
        $teamname_ast5 = $teamname_ast4;
        $teamname_ast4 = $teamname_ast3;
        $teamname_ast3 = $teamname_ast2;
        $teamname_ast2 = $teamname;
        $pidast5 = $pidast4;
        $pidast4 = $pidast3;
        $pidast3 = $pidast2;
        $pidast2 = $pid;
        $tidast5 = $tidast4;
        $tidast4 = $tidast3;
        $tidast3 = $tidast2;
        $tidast2 = $tid;
    } elseif ($stats_ast > $ast3) {
        $ast5 = $ast4;
        $ast4 = $ast3;
        $ast3 = $stats_ast;
        $name_ast5 = $name_ast4;
        $name_ast4 = $name_ast3;
        $name_ast3 = $name;
        $teamname_ast5 = $teamname_ast4;
        $teamname_ast4 = $teamname_ast3;
        $teamname_ast3 = $teamname;
        $pidast5 = $pidast4;
        $pidast4 = $pidast3;
        $pidast3 = $pid;
        $tidast5 = $tidast4;
        $tidast4 = $tidast3;
        $tidast3 = $tid;
    } elseif ($stats_ast > $ast4) {
        $ast5 = $ast4;
        $ast4 = $stats_ast;
        $name_ast5 = $name_ast4;
        $name_ast4 = $name;
        $teamname_ast5 = $teamname_ast4;
        $teamname_ast4 = $teamname;
        $pidast5 = $pidast4;
        $pidast4 = $pid;
        $tidast5 = $tidast4;
        $tidast4 = $tid;
    } elseif ($stats_ast > $ast5) {
        $ast5 = $stats_ast;
        $name_ast5 = $name;
        $teamname_ast5 = $teamname;
        $pidast5 = $pid;
        $tidast5 = $tid;
    }

//----STL---

    if ($stats_stl > $stl1) {
        $stl5 = $stl4;
        $stl4 = $stl3;
        $stl3 = $stl2;
        $stl2 = $stl1;
        $stl1 = $stats_stl;
        $name_stl5 = $name_stl4;
        $name_stl4 = $name_stl3;
        $name_stl3 = $name_stl2;
        $name_stl2 = $name_stl1;
        $name_stl1 = $name;
        $teamname_stl5 = $teamname_stl4;
        $teamname_stl4 = $teamname_stl3;
        $teamname_stl3 = $teamname_stl2;
        $teamname_stl2 = $teamname_stl1;
        $teamname_stl1 = $teamname;
        $pidstl5 = $pidstl4;
        $pidstl4 = $pidstl3;
        $pidstl3 = $pidstl2;
        $pidstl2 = $pidstl1;
        $pidstl1 = $pid;
        $tidstl5 = $tidstl4;
        $tidstl4 = $tidstl3;
        $tidstl3 = $tidstl2;
        $tidstl2 = $tidstl1;
        $tidstl1 = $tid;
    } elseif ($stats_stl > $stl2) {
        $stl5 = $stl4;
        $stl4 = $stl3;
        $stl3 = $stl2;
        $stl2 = $stats_stl;
        $name_stl5 = $name_stl4;
        $name_stl4 = $name_stl3;
        $name_stl3 = $name_stl2;
        $name_stl2 = $name;
        $teamname_stl5 = $teamname_stl4;
        $teamname_stl4 = $teamname_stl3;
        $teamname_stl3 = $teamname_stl2;
        $teamname_stl2 = $teamname;
        $pidstl5 = $pidstl4;
        $pidstl4 = $pidstl3;
        $pidstl3 = $pidstl2;
        $pidstl2 = $pid;
        $tidstl5 = $tidstl4;
        $tidstl4 = $tidstl3;
        $tidstl3 = $tidstl2;
        $tidstl2 = $tid;
    } elseif ($stats_stl > $stl3) {
        $stl5 = $stl4;
        $stl4 = $stl3;
        $stl3 = $stats_stl;
        $name_stl5 = $name_stl4;
        $name_stl4 = $name_stl3;
        $name_stl3 = $name;
        $teamname_stl5 = $teamname_stl4;
        $teamname_stl4 = $teamname_stl3;
        $teamname_stl3 = $teamname;
        $pidstl5 = $pidstl4;
        $pidstl4 = $pidstl3;
        $pidstl3 = $pid;
        $tidstl5 = $tidstl4;
        $tidstl4 = $tidstl3;
        $tidstl3 = $tid;
    } elseif ($stats_stl > $stl4) {
        $stl5 = $stl4;
        $stl4 = $stats_stl;
        $name_stl5 = $name_stl4;
        $name_stl4 = $name;
        $teamname_stl5 = $teamname_stl4;
        $teamname_stl4 = $teamname;
        $pidstl5 = $pidstl4;
        $pidstl4 = $pid;
        $tidstl5 = $tidstl4;
        $tidstl4 = $tid;
    } elseif ($stats_stl > $stl5) {
        $stl5 = $stats_stl;
        $name_stl5 = $name;
        $teamname_stl5 = $teamname;
        $pidstl5 = $pid;
        $tidstl5 = $tid;
    }

//----BLK---

    if ($stats_blk > $blk1) {
        $blk5 = $blk4;
        $blk4 = $blk3;
        $blk3 = $blk2;
        $blk2 = $blk1;
        $blk1 = $stats_blk;
        $name_blk5 = $name_blk4;
        $name_blk4 = $name_blk3;
        $name_blk3 = $name_blk2;
        $name_blk2 = $name_blk1;
        $name_blk1 = $name;
        $teamname_blk5 = $teamname_blk4;
        $teamname_blk4 = $teamname_blk3;
        $teamname_blk3 = $teamname_blk2;
        $teamname_blk2 = $teamname_blk1;
        $teamname_blk1 = $teamname;
        $pidblk5 = $pidblk4;
        $pidblk4 = $pidblk3;
        $pidblk3 = $pidblk2;
        $pidblk2 = $pidblk1;
        $pidblk1 = $pid;
        $tidblk5 = $tidblk4;
        $tidblk4 = $tidblk3;
        $tidblk3 = $tidblk2;
        $tidblk2 = $tidblk1;
        $tidblk1 = $tid;
    } elseif ($stats_blk > $blk2) {
        $blk5 = $blk4;
        $blk4 = $blk3;
        $blk3 = $blk2;
        $blk2 = $stats_blk;
        $name_blk5 = $name_blk4;
        $name_blk4 = $name_blk3;
        $name_blk3 = $name_blk2;
        $name_blk2 = $name;
        $teamname_blk5 = $teamname_blk4;
        $teamname_blk4 = $teamname_blk3;
        $teamname_blk3 = $teamname_blk2;
        $teamname_blk2 = $teamname;
        $pidblk5 = $pidblk4;
        $pidblk4 = $pidblk3;
        $pidblk3 = $pidblk2;
        $pidblk2 = $pid;
        $tidblk5 = $tidblk4;
        $tidblk4 = $tidblk3;
        $tidblk3 = $tidblk2;
        $tidblk2 = $tid;
    } elseif ($stats_blk > $blk3) {
        $blk5 = $blk4;
        $blk4 = $blk3;
        $blk3 = $stats_blk;
        $name_blk5 = $name_blk4;
        $name_blk4 = $name_blk3;
        $name_blk3 = $name;
        $teamname_blk5 = $teamname_blk4;
        $teamname_blk4 = $teamname_blk3;
        $teamname_blk3 = $teamname;
        $pidblk5 = $pidblk4;
        $pidblk4 = $pidblk3;
        $pidblk3 = $pid;
        $tidblk5 = $tidblk4;
        $tidblk4 = $tidblk3;
        $tidblk3 = $tid;
    } elseif ($stats_blk > $blk4) {
        $blk5 = $blk4;
        $blk4 = $stats_blk;
        $name_blk5 = $name_blk4;
        $name_blk4 = $name;
        $teamname_blk5 = $teamname_blk4;
        $teamname_blk4 = $teamname;
        $pidblk5 = $pidblk4;
        $pidblk4 = $pid;
        $tidblk5 = $tidblk4;
        $tidblk4 = $tid;
    } elseif ($stats_blk > $blk5) {
        $blk5 = $stats_blk;
        $name_blk5 = $name;
        $teamname_blk5 = $teamname;
        $pidblk5 = $pid;
        $tidblk5 = $tid;
    }

    $i++;
}

$ppg1 = sprintf('%4.1f', $ppg1);
$ppg2 = sprintf('%4.1f', $ppg2);
$ppg3 = sprintf('%4.1f', $ppg3);
$ppg4 = sprintf('%4.1f', $ppg4);
$ppg5 = sprintf('%4.1f', $ppg5);

$reb1 = sprintf('%4.1f', $reb1);
$reb2 = sprintf('%4.1f', $reb2);
$reb3 = sprintf('%4.1f', $reb3);
$reb4 = sprintf('%4.1f', $reb4);
$reb5 = sprintf('%4.1f', $reb5);

$ast1 = sprintf('%4.1f', $ast1);
$ast2 = sprintf('%4.1f', $ast2);
$ast3 = sprintf('%4.1f', $ast3);
$ast4 = sprintf('%4.1f', $ast4);
$ast5 = sprintf('%4.1f', $ast5);

$stl1 = sprintf('%4.1f', $stl1);
$stl2 = sprintf('%4.1f', $stl2);
$stl3 = sprintf('%4.1f', $stl3);
$stl4 = sprintf('%4.1f', $stl4);
$stl5 = sprintf('%4.1f', $stl5);

$blk1 = sprintf('%4.1f', $blk1);
$blk2 = sprintf('%4.1f', $blk2);
$blk3 = sprintf('%4.1f', $blk3);
$blk4 = sprintf('%4.1f', $blk4);
$blk5 = sprintf('%4.1f', $blk5);

$content .= "<center><table border=1 bordercolor=#000066><tr><td><table><tr><td colspan=2><center><img src=\"./images/player/$pid1.jpg\" height=\"90\" width=\"65\"> <img src=\"./images/logo/new$tid1.png\" height=\"75\" width=\"75\"></center></td></tr>
<tr><td bgcolor=#000066 colspan=2><b><font color=#ffffff>Points</td></tr>
<tr><td><b><a href=modules.php?name=Player&pa=showpage&pid=$pid1><font color=#000066>$name1</font></a><br><font color=#000066>$teamname1</font></td><td valign=top>$ppg1</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pid2><font color=#000066>$name2</font></a><br><font color=#000066>$teamname2</font></td><td valign=top>$ppg2</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pid3><font color=#000066>$name3</font></a><br><font color=#000066>$teamname3</font></td><td valign=top>$ppg3</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pid4><font color=#000066>$name4</font></a><br><font color=#000066>$teamname4</font></td><td valign=top>$ppg4</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pid5><font color=#000066>$name5</font></a><br><font color=#000066>$teamname5</font></td><td valign=top>$ppg5</td></tr>
</table></td>";

$content .= "<td><table><tr><td colspan=2><center><img src=\"./images/player/$pidreb1.jpg\" height=\"90\" width=\"65\"> <img src=\"./images/logo/new$tidreb1.png\" height=\"75\" width=\"75\"></center></td></tr>
<tr><td bgcolor=#000066 colspan=2><b><font color=#ffffff>Rebounds</td></tr>
<tr><td><b><a href=modules.php?name=Player&pa=showpage&pid=$pidreb1><font color=#000066>$name_reb1</font></a><br><font color=#000066>$teamname_reb1</font></td><td valign=top>$reb1</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidreb2><font color=#000066>$name_reb2</font></a><br><font color=#000066>$teamname_reb2</font></td><td valign=top>$reb2</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidreb3><font color=#000066>$name_reb3</font></a><br><font color=#000066>$teamname_reb3</font></td><td valign=top>$reb3</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidreb4><font color=#000066>$name_reb4</font></a><br><font color=#000066>$teamname_reb4</font></td><td valign=top>$reb4</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidreb5><font color=#000066>$name_reb5</font></a><br><font color=#000066>$teamname_reb5</font></td><td valign=top>$reb5</td></tr>
</table></td>";

$content .= "<td><table><tr><td colspan=2><center><img src=\"./images/player/$pidast1.jpg\" height=\"90\" width=\"65\"> <img src=\"./images/logo/new$tidast1.png\" height=\"75\" width=\"75\"></center></td></tr>
<tr><td bgcolor=#000066 colspan=2><b><font color=#ffffff>Assists</td></tr>
<tr><td><b><a href=modules.php?name=Player&pa=showpage&pid=$pidast1><font color=#000066>$name_ast1</font></a><br><font color=#000066>$teamname_ast1</font></td><td valign=top>$ast1</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidast2><font color=#000066>$name_ast2</font></a><br><font color=#000066>$teamname_ast2</font></td><td valign=top>$ast2</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidast3><font color=#000066>$name_ast3</font></a><br><font color=#000066>$teamname_ast3</font></td><td valign=top>$ast3</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidast4><font color=#000066>$name_ast4</font></a><br><font color=#000066>$teamname_ast4</font></td><td valign=top>$ast4</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidast5><font color=#000066>$name_ast5</font></a><br><font color=#000066>$teamname_ast5</font></td><td valign=top>$ast5</td></tr>
</table></td>";

$content .= "<td><table><tr><td colspan=2><center><img src=\"./images/player/$pidstl1.jpg\" height=\"90\" width=\"65\"> <img src=\"./images/logo/new$tidstl1.png\" height=\"75\" width=\"75\"></center></td></tr>
<tr><td bgcolor=#000066 colspan=2><b><font color=#ffffff>Steals</td></tr>
<tr><td><b><a href=modules.php?name=Player&pa=showpage&pid=$pidstl1><font color=#000066>$name_stl1</font></a><br><font color=#000066>$teamname_stl1</font></td><td valign=top>$stl1</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidstl2><font color=#000066>$name_stl2</font></a><br><font color=#000066>$teamname_stl2</font></td><td valign=top>$stl2</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidstl3><font color=#000066>$name_stl3</font></a><br><font color=#000066>$teamname_stl3</font></td><td valign=top>$stl3</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidstl4><font color=#000066>$name_stl4</font></a><br><font color=#000066>$teamname_stl4</font></td><td valign=top>$stl4</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidstl5><font color=#000066>$name_stl5</font></a><br><font color=#000066>$teamname_stl5</font></td><td valign=top>$stl5</td></tr>
</table></td>";

$content .= "<td><table><tr><td colspan=2><center><img src=\"./images/player/$pidblk1.jpg\" height=\"90\" width=\"65\"> <img src=\"./images/logo/new$tidblk1.png\" height=\"75\" width=\"75\"></center></td></tr>
<tr><td bgcolor=#000066 colspan=2><b><font color=#ffffff>Blocks</td></tr>
<tr><td><b><a href=modules.php?name=Player&pa=showpage&pid=$pidblk1><font color=#000066>$name_blk1</font></a><br><font color=#000066>$teamname_blk1</font></td><td valign=top>$blk1</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidblk2><font color=#000066>$name_blk2</font></a><br><font color=#000066>$teamname_blk2</font></td><td valign=top>$blk2</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidblk3><font color=#000066>$name_blk3</font></a><br><font color=#000066>$teamname_blk3</font></td><td valign=top>$blk3</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidblk4><font color=#000066>$name_blk4</font></a><br><font color=#000066>$teamname_blk4</font></td><td valign=top>$blk4</td></tr>
<tr><td><a href=modules.php?name=Player&pa=showpage&pid=$pidblk5><font color=#000066>$name_blk5</font></a><br><font color=#000066>$teamname_blk5</font></td><td valign=top>$blk5</td></tr>
</table></td></tr></table>";
