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

if (!eregi("modules.php", $_SERVER['PHP_SELF'])) {
	die ("You can't access this file directly...");
}

require_once("mainfile.php");
$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$userpage = 1;
$current_ibl_season=mysql_result(mysql_query("SELECT value FROM nuke_ibl_settings WHERE name = 'Current IBL Season Ending Year' LIMIT 1"),0,"value");
include("header.php");

OpenTable();


$query = "SELECT * FROM nuke_iblplyr where pos = 'C' and (tid = '1' or tid = '26' or tid = '4' or tid = '3' or tid = '21' or tid = '2' or tid = '24' or tid = '29' or tid = '6' or tid = '27' or tid = '9' or tid = '7' or tid = '10' or tid = '5' or tid = '8' or tid = '31') and teamname != 'Retired' order by teamname";
$result = mysql_query($query);
while($row = mysql_fetch_assoc($result))
{
    $ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
    $ppg = round($ppg,1);
    $rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
    $rpg = round($rpg,1);
    $apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
    $apg = round($apg,1);
    $spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
    $spg = round($spg,1);
    $tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
    $tpg = round($tpg,1);
    $bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
    $bpg = round($bpg,1);
    $fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
    $fgp = round($fgp,3);
    $ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
    $ftp = round($ftp,3);
    $tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
    $tpp = round($tpp,3);
    $dd .= "<option value='".$row['name']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl, , ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp</option>";
}


echo"<label for=\'ECCenters[]\'>Select One (1) Eastern Conference Center:</label><br>
<select multiple=\"multiple\" name=\"ECCenters[]\">
<option value=\"$dd\">$dd</option>
</select><br><br>";

$query1 = "SELECT * FROM nuke_iblplyr where (pos = 'PF' or pos = 'SF') and (tid = '1' or tid = '26' or tid = '4' or tid = '3' or tid = '21' or tid = '2' or tid = '24' or tid = '29' or tid = '6' or tid = '27' or tid = '9' or tid = '7' or tid = '10' or tid = '5' or tid = '8' or tid = '31') and teamname != 'Retired' order by teamname";
$result1 = mysql_query($query1);
while($row = mysql_fetch_assoc($result1))
{
    $ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
    $ppg = round($ppg,1);
    $rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
    $rpg = round($rpg,1);
    $apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
    $apg = round($apg,1);
    $spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
    $spg = round($spg,1);
    $tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
    $tpg = round($tpg,1);
    $bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
    $bpg = round($bpg,1);
    $fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
    $fgp = round($fgp,3);
    $ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
    $ftp = round($ftp,3);
    $tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
    $tpp = round($tpp,3);
    $ff .= "<option value='".$row['name']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl, , ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp</option>";
}


echo"<label for=\'ECForwards[]\'>Select Two (2) Eastern Conference Forwards:</label><br>
<select multiple=\"multiple\" name=\"ECForwards[]\">
<option value=\"$ff\">$ff</option>
</select><br><br>";

$query1 = "SELECT * FROM nuke_iblplyr where (pos = 'PG' or pos = 'SG') and (tid = '1' or tid = '26' or tid = '4' or tid = '3' or tid = '21' or tid = '2' or tid = '24' or tid = '29' or tid = '6' or tid = '27' or tid = '9' or tid = '7' or tid = '10' or tid = '5' or tid = '8' or tid = '31') and teamname != 'Retired' order by teamname";
$result1 = mysql_query($query1);
while($row = mysql_fetch_assoc($result1))
{
    $ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
    $ppg = round($ppg,1);
    $rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
    $rpg = round($rpg,1);
    $apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
    $apg = round($apg,1);
    $spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
    $spg = round($spg,1);
    $tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
    $tpg = round($tpg,1);
    $bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
    $bpg = round($bpg,1);
    $fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
    $fgp = round($fgp,3);
    $ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
    $ftp = round($ftp,3);
    $tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
    $tpp = round($tpp,3);
    $hh .= "<option value='".$row['name']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl, , ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp</option>";
}


echo"<label for=\'ECGuards[]\'>Select Two (2) Eastern Conference Guards:</label><br>
<select multiple=\"multiple\" name=\"ECGuards[]\">
<option value=\"$hh\">$hh</option>
</select><br><br>";





CloseTable();
include("footer.php");

?>
