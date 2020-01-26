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
$current_ibl_season=mysql_result(mysql_query("SELECT value FROM nuke_ibl_settings WHERE name = 'Current IBL Season' LIMIT 1"),0,"value");
include("header.php");

function userinfo($username, $bypass=0, $hid=0, $url=0) {
    global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $useset, $subscription_url;
    $sql = "SELECT * FROM ".$prefix."_bbconfig";
    $result = $db->sql_query($sql);
    while ( $row = $db->sql_fetchrow($result) )
    {
    $board_config[$row['config_name']] = $row['config_value'];
    }
    $sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
    $result2 = $db->sql_query($sql2);
    $num = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);
    if(!$bypass) cookiedecode($user);
    include("header.php");
}
OpenTable();



echo "<form name=\"ASGVote\" method=\"post\" action=\"ASGVote.php\">";

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

$query2 = "SELECT * FROM nuke_iblplyr where (pos = 'PG' or pos = 'SG') and (tid = '1' or tid = '26' or tid = '4' or tid = '3' or tid = '21' or tid = '2' or tid = '24' or tid = '29' or tid = '6' or tid = '27' or tid = '9' or tid = '7' or tid = '10' or tid = '5' or tid = '8' or tid = '31') and teamname != 'Retired' order by teamname";
$result2 = mysql_query($query2);
while($row = mysql_fetch_assoc($result2))
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

$query3 = "SELECT * FROM nuke_iblplyr where pos = 'C' and (tid = '11' or tid = '25' or tid = '30' or tid = '13' or tid = '14' or tid = '15' or tid = '12' or tid = '32' or tid = '16' or tid = '17' or tid = '22' or tid = '20' or tid = '19' or tid = '23' or tid = '18' or tid = '28') and teamname != 'Retired' order by teamname";
$result3 = mysql_query($query3);
while($row = mysql_fetch_assoc($result3))
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
    $ii .= "<option value='".$row['name']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl, , ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp</option>";
} 

$query4 = "SELECT * FROM nuke_iblplyr where (pos = 'PF' or pos = 'SF') and (tid = '11' or tid = '25' or tid = '30' or tid = '13' or tid = '14' or tid = '15' or tid = '12' or tid = '32' or tid = '16' or tid = '17' or tid = '22' or tid = '20' or tid = '19' or tid = '23' or tid = '18' or tid = '28') and teamname != 'Retired' order by teamname";
$result4 = mysql_query($query4);
while($row = mysql_fetch_assoc($result4))
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
    $jj .= "<option value='".$row['name']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl, , ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp</option>";
} 

$query5 = "SELECT * FROM nuke_iblplyr where (pos = 'PG' or pos = 'SG') and (tid = '11' or tid = '25' or tid = '30' or tid = '13' or tid = '14' or tid = '15' or tid = '12' or tid = '32' or tid = '16' or tid = '17' or tid = '22' or tid = '20' or tid = '19' or tid = '23' or tid = '18' or tid = '28') and teamname != 'Retired' order by teamname";
$result5 = mysql_query($query5);
while($row = mysql_fetch_assoc($result5))
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
    $kk .= "<option value='".$row['name']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl, , ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp</option>";
} 

echo "<select name=\"ECC\">
  <option value=\"\">Select Your Eastern Conference Center...</option>
  <option value=\"$dd\">$dd</option>
</select><br><br>

<select name=\"ECF1\">
  <option value=\"\">Select Your First Eastern Conference Forward...</option>
  <option value=\"$ff\">$ff</option>
</select><br><br>

<select name=\"ECF2\">
  <option value=\"\">Select Your Second Eastern Conference Forward...</option>
  <option value=\"$ff\">$ff</option>
</select><br><br>

<select name=\"ECG1\">
  <option value=\"\">Select Your First Eastern Conference Guard...</option>
  <option value=\"$hh\">$hh</option>
</select><br><br>

<select name=\"ECG2\">
  <option value=\"\">Select Your Second Eastern Conference Guard...</option>
  <option value=\"$hh\">$hh</option>
</select><br><br>

<select name=\"WCC\">
  <option value=\"\">Select Your Western Conference Center...</option>
  <option value=\"$ii\">$ii</option>
</select><br><br>

<select name=\"WCF1\">
  <option value=\"\">Select Your First Western Conference Forward...</option>
  <option value=\"$jj\">$jj</option>
</select><br><br>

<select name=\"WCF2\">
  <option value=\"\">Select Your Second Western Conference Forward...</option>
  <option value=\"$jj\">$jj</option>
</select><br><br>

<select name=\"WCG1\">
  <option value=\"\">Select Your First Western Conference Guard...</option>
  <option value=\"$kk\">$kk</option>
</select><br><br>

<select name=\"WCG2\">
  <option value=\"\">Select Your Second Western Conference Guard...</option>
  <option value=\"$kk\">$kk</option>
</select>




</td></tr>";





echo "

<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
<input type=\"hidden\" name=\"playerpos\" value=\"$player_pos\">
</table>

<input type=\"submit\" value=\"Submit Votes!\">
</form>

";

CloseTable();
include("footer.php");

?>