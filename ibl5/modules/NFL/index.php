<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2006 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

require_once "mainfile.php";
$module_name = basename(dirname(__FILE__));
get_lang($module_name);
NukeHeader::header();

$query2 = "SELECT * FROM nfl order by tot_score DESC";
$result2 = $db->sql_query($query2);
$num2 = $db->sql_numrows($result2);

OpenTable();
$k = 0;
while ($k < $num2) {
    $name[$k] = $db->sql_result($result2, $k, "name");
    $wk1_pick[$k] = $db->sql_result($result2, $k, "wk1_pick");
    $wk1_score[$k] = $db->sql_result($result2, $k, "wk1_score");
    $wk2_pick[$k] = $db->sql_result($result2, $k, "wk2_pick");
    $wk2_score[$k] = $db->sql_result($result2, $k, "wk2_score");
    $wk3_pick[$k] = $db->sql_result($result2, $k, "wk3_pick");
    $wk3_score[$k] = $db->sql_result($result2, $k, "wk3_score");
    $wk4_pick[$k] = $db->sql_result($result2, $k, "wk4_pick");
    $wk4_score[$k] = $db->sql_result($result2, $k, "wk4_score");
    $wk5_pick[$k] = $db->sql_result($result2, $k, "wk5_pick");
    $wk5_score[$k] = $db->sql_result($result2, $k, "wk5_score");
    $wk6_pick[$k] = $db->sql_result($result2, $k, "wk6_pick");
    $wk6_score[$k] = $db->sql_result($result2, $k, "wk6_score");
    $wk7_pick[$k] = $db->sql_result($result2, $k, "wk7_pick");
    $wk7_score[$k] = $db->sql_result($result2, $k, "wk7_score");
    $wk8_pick[$k] = $db->sql_result($result2, $k, "wk8_pick");
    $wk8_score[$k] = $db->sql_result($result2, $k, "wk8_score");
    $wk9_pick[$k] = $db->sql_result($result2, $k, "wk9_pick");
    $wk9_score[$k] = $db->sql_result($result2, $k, "wk9_score");
    $wk10_pick[$k] = $db->sql_result($result2, $k, "wk10_pick");
    $wk10_score[$k] = $db->sql_result($result2, $k, "wk10_score");
    $wk11_pick[$k] = $db->sql_result($result2, $k, "wk11_pick");
    $wk11_score[$k] = $db->sql_result($result2, $k, "wk11_score");
    $wk12_pick[$k] = $db->sql_result($result2, $k, "wk12_pick");
    $wk12_score[$k] = $db->sql_result($result2, $k, "wk12_score");
    $wk13_pick[$k] = $db->sql_result($result2, $k, "wk13_pick");
    $wk13_score[$k] = $db->sql_result($result2, $k, "wk13_score");
    $wk14_pick[$k] = $db->sql_result($result2, $k, "wk14_pick");
    $wk14_score[$k] = $db->sql_result($result2, $k, "wk14_score");
    $wk15_pick[$k] = $db->sql_result($result2, $k, "wk15_pick");
    $wk15_score[$k] = $db->sql_result($result2, $k, "wk15_score");
    $wk16_pick[$k] = $db->sql_result($result2, $k, "wk16_pick");
    $wk16_score[$k] = $db->sql_result($result2, $k, "wk16_score");
    $wk17_pick[$k] = $db->sql_result($result2, $k, "wk17_pick");
    $wk17_score[$k] = $db->sql_result($result2, $k, "wk17_score");
    $tot_score[$k] = $db->sql_result($result2, $k, "tot_score");
    $player[$k] = $db->sql_result($result2, $k, "name");

    $table_echo = $table_echo . "<tr><td>" . $player[$k] . "</td><td>" . $tot_score[$k] . "</td><td>" . $wk1_pick[$k] . "</td><td>" . $wk1_score[$k] . "</td><td>" . $wk2_pick[$k] . "</td><td>" . $wk2_score[$k] . "</td><td>" . $wk3_pick[$k] . "</td><td>" . $wk3_score[$k] . "</td><td>" . $wk4_pick[$k] . "</td><td>" . $wk4_score[$k] . "</td><td>" . $wk5_pick[$k] . "</td><td>" . $wk5_score[$k] . "</td><td>" . $wk6_pick[$k] . "</td><td>" . $wk6_score[$k] . "</td><td>" . $wk7_pick[$k] . "</td><td>" . $wk7_score[$k] . "</td><td>" . $wk8_pick[$k] . "</td><td>" . $wk8_score[$k] . "</td><td>" . $wk9_pick[$k] . "</td><td>" . $wk9_score[$k] . "</td><td>" . $wk10_pick[$k] . "</td><td>" . $wk10_score[$k] . "</td><td>" . $wk11_pick[$k] . "</td><td>" . $wk11_score[$k] . "</td><td>" . $wk12_pick[$k] . "</td><td>" . $wk12_score[$k] . "</td><td>" . $wk13_pick[$k] . "</td><td>" . $wk13_score[$k] . "</td><td>" . $wk14_pick[$k] . "</td><td>" . $wk14_score[$k] . "</td><td>" . $wk15_pick[$k] . "</td><td>" . $wk15_score[$k] . "</td><td>" . $wk16_pick[$k] . "</td><td>" . $wk16_score[$k] . "</td><td>" . $wk17_pick[$k] . "</td><td>" . $wk17_score[$k] . "</td></tr>";

    $k++;
}

$text = $text . "<table class=\"sortable\" border=1>
		  <tr><th>Player</th><th> Total</th><th>Wk1</th><th>Score</th><th>Wk2</th><th>Score</th><th>Wk3</th><th>Score</th><th>Wk4</th><th>Score</th><th>Wk5</th><th>Score</th><th>Wk6</th><th>Score</th><th>Wk7</th><th>Score</th><th>Wk8</th><th>Score</th><th>Wk9</th><th>Score</th><th>Wk10</th><th>Score</th><th>Wk11</th><th>Score</th><th>Wk12</th><th>Score</th><th>Wk13</th><th>Score</th><th>Wk14</th><th>Score</th><th>Wk15</th><th>Score</th><th>Wk16</th><th>Score</th><th>Wk17</th><th>Score</th></tr>$table_echo</table>";
echo $text;

CloseTable();
include "footer.php";
