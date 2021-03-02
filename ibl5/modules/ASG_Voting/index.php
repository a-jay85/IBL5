
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
/* 2/2/2005                                                             */
/*                                                                      */
/************************************************************************/

if (!eregi("modules.php", $_SERVER['PHP_SELF'])) {
	die ("You can't access this file directly...");
}

require_once("mainfile.php");
$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$userpage = 1;

//include("modules/$module_name/navbar.php");

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

// === CODE TO INSERT IBL DEPTH CHART ===

    OpenTable();
    $teamlogo = $userinfo[user_ibl_team];
	$queryTeamID = "SELECT teamid FROM nuke_ibl_team_info WHERE team_name = '$teamlogo'";
	$tid = mysql_result(mysql_query($queryTeamID), 0);

echo "
      <form name=\"ASGVote\" method=\"post\" action=\"ASGVote.php\"><center><img src=\"images/logo/$tid.jpg\"><br><br>";

$query = "SELECT * FROM nuke_iblplyr where pos = 'C' and (tid = '1' or tid = '2' or tid = '3' or tid = '4' or tid = '5' or tid = '6' or tid = '7' or tid = '8' or tid = '9' or tid = '10' or tid = '11' or tid = '12') and retired != 1 and stats_gm > '14' order by name";
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
    $gm = floatval($row['stats_gm']);
    $gs = floatval($row['stats_gs']);
    $dd .= "<option value='".$row['name'].", ".$row['teamname']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
}

$query1 = "SELECT * FROM nuke_iblplyr where (pos = 'PF' or pos = 'SF') and (tid = '1' or tid = '2' or tid = '3' or tid = '4' or tid = '5' or tid = '6' or tid = '7' or tid = '8' or tid = '9' or tid = '10' or tid = '11' or tid = '12') and retired != 1 and stats_gm > '14' order by name";
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
    $gm = floatval($row['stats_gm']);
    $gs = floatval($row['stats_gs']);
    $ff .= "<option value='".$row['name'].", ".$row['teamname']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
}

$query2 = "SELECT * FROM nuke_iblplyr where (pos = 'PG' or pos = 'SG') and (tid = '1' or tid = '2' or tid = '3' or tid = '4' or tid = '5' or tid = '6' or tid = '7' or tid = '8' or tid = '9' or tid = '10' or tid = '11' or tid = '12') and retired != 1 and stats_gm > '14' order by name";
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
    $gm = floatval($row['stats_gm']);
    $gs = floatval($row['stats_gs']);
    $hh .= "<option value='".$row['name'].", ".$row['teamname']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
}

$query3 = "SELECT * FROM nuke_iblplyr where pos = 'C' and (tid = '13' or tid = '14' or tid = '15' or tid = '16' or tid = '17' or tid = '18' or tid = '19' or tid = '20' or tid = '21' or tid = '22' or tid = '23' or tid = '24') and retired != 1 and stats_gm > '14' order by name";
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
    $gm = floatval($row['stats_gm']);
    $gs = floatval($row['stats_gs']);
    $ii .= "<option value='".$row['name'].", ".$row['teamname']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
}

$query4 = "SELECT * FROM nuke_iblplyr where (pos = 'PF' or pos = 'SF') and (tid = '13' or tid = '14' or tid = '15' or tid = '16' or tid = '17' or tid = '18' or tid = '19' or tid = '20' or tid = '21' or tid = '22' or tid = '23' or tid = '24') and retired != 1 and stats_gm > '14' order by name";
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
    $gm = floatval($row['stats_gm']);
    $gs = floatval($row['stats_gs']);
    $jj .= "<option value='".$row['name'].", ".$row['teamname']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
}

$query5 = "SELECT * FROM nuke_iblplyr where (pos = 'PG' or pos = 'SG') and (tid = '13' or tid = '14' or tid = '15' or tid = '16' or tid = '17' or tid = '18' or tid = '19' or tid = '20' or tid = '21' or tid = '22' or tid = '23' or tid = '24') and retired != 1 and stats_gm > '14' order by name";
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
    $gm = floatval($row['stats_gm']);
    $gs = floatval($row['stats_gs']);
    $kk .= "<option value='".$row['name'].", ".$row['teamname']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
}

echo "<select name=\"ECC\">
  <option value=\"\">Select Your Eastern Conference Center...</option>
  $dd
</select><br><br>

<select name=\"ECF1\">
  <option value=\"\">Select Your First Eastern Conference Forward...</option>
  $ff
</select><br><br>

<select name=\"ECF2\">
  <option value=\"\">Select Your Second Eastern Conference Forward...</option>
  $ff
</select><br><br>

<select name=\"ECG1\">
  <option value=\"\">Select Your First Eastern Conference Guard...</option>
  $hh<
</select><br><br>

<select name=\"ECG2\">
  <option value=\"\">Select Your Second Eastern Conference Guard...</option>
  $hh
</select><br><br><br>

<select name=\"WCC\">
  <option value=\"\">Select Your Western Conference Center...</option>
  $ii
</select><br><br>

<select name=\"WCF1\">
  <option value=\"\">Select Your First Western Conference Forward...</option>
  $jj
</select><br><br>

<select name=\"WCF2\">
  <option value=\"\">Select Your Second Western Conference Forward...</option>
  $jj
</select><br><br>

<select name=\"WCG1\">
  <option value=\"\">Select Your First Western Conference Guard...</option>
  $kk
</select><br><br>

<select name=\"WCG2\">
  <option value=\"\">Select Your Second Western Conference Guard...</option>
  $kk
</select>




</td></tr>







<input type=\"hidden\" name=\"teamname\" value=\"$teamlogo\">
<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
<input type=\"hidden\" name=\"playerpos\" value=\"$player_pos\">
</table>

<center><input type=\"submit\" value=\"Submit Votes!\"></center>
</form>

";




    CloseTable();


    include("footer.php");
}

function main($user) {
    global $stop, $module_name, $redirect, $mode, $t, $f, $gfx_chk;
    if(!is_user($user)) {
	include("header.php");
	if ($stop) {
	    OpenTable();
	    echo "<center><font class=\"title\"><b>"._LOGININCOR."</b></font></center>\n";
	    CloseTable();
	    echo "<br>\n";
	} else {
	    OpenTable();
	    echo "<center><font class=\"title\"><b>"._USERREGLOGIN."</b></font></center>\n";
	    CloseTable();
	    echo "<br>\n";
	}
	if (!is_user($user)) {
	    OpenTable();
	    mt_srand ((double)microtime()*1000000);
	    $maxran = 1000000;
	    $random_num = mt_rand(0, $maxran);
	    echo "<form action=\"modules.php?name=$module_name\" method=\"post\">\n"
		."<b>"._USERLOGIN."</b><br><br>\n"
		."<table border=\"0\"><tr><td>\n"
		.""._NICKNAME.":</td><td><input type=\"text\" name=\"username\" size=\"15\" maxlength=\"25\"></td></tr>\n"
		."<tr><td>"._PASSWORD.":</td><td><input type=\"password\" name=\"user_password\" size=\"15\" maxlength=\"20\"></td></tr>\n";
	    if (extension_loaded("gd") AND ($gfx_chk == 2 OR $gfx_chk == 4 OR $gfx_chk == 5 OR $gfx_chk == 7)) {
		echo "<tr><td colspan='2'>"._SECURITYCODE.": <img src='modules.php?name=$module_name&op=gfx&random_num=$random_num' border='1' alt='"._SECURITYCODE."' title='"._SECURITYCODE."'></td></tr>\n"
		    ."<tr><td colspan='2'>"._TYPESECCODE.": <input type=\"text\" NAME=\"gfx_check\" SIZE=\"7\" MAXLENGTH=\"6\"></td></tr>\n"
		    ."<input type=\"hidden\" name=\"random_num\" value=\"$random_num\">\n";
	    }
	    echo "</table><input type=\"hidden\" name=\"redirect\" value=$redirect>\n"
		."<input type=\"hidden\" name=\"mode\" value=$mode>\n"
		."<input type=\"hidden\" name=\"f\" value=$f>\n"
		."<input type=\"hidden\" name=\"t\" value=$t>\n"
		."<input type=\"hidden\" name=\"op\" value=\"login\">\n"
		."<input type=\"submit\" value=\""._LOGIN."\"></form><br>\n\n"
		."<center><font class=\"content\">[ <a href=\"modules.php?name=$module_name&amp;op=pass_lost\">"._PASSWORDLOST."</a> | <a href=\"modules.php?name=$module_name&amp;op=new_user\">"._REGNEWUSER."</a> ]</font></center>\n";
	    CloseTable();
	}
	include("footer.php");
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        userinfo($cookie[1]);
    }

}

switch($op) {

    default:
	main($user);
	break;
}

?>
