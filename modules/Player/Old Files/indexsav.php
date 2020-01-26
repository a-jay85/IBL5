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
$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Player Archives";

function showmenu()
{
    include("header.php");
    OpenTable();

    menu();

    CloseTable();
    include("footer.php");
}

function menu()
{
echo "<center><b>
<a href=\"modules.php?name=Player&pa=search\">Player Search</a>  |
<a href=\"modules.php?name=Player&pa=awards\">Awards Search</a> |
<a href=\"modules.php?name=Player&pa=oneonone\">One-On-One Game</a> |
<a href=\"modules.php?name=Player&pa=Leaderboards\">Career Leaderboards</a> (All Types)
</b><hr>";
}

// ========================================================
//
// LEADERBOARDS
//
// ========================================================

function leaderboards($type)
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

echo "
<form name=\"Leaderboards\" method=\"post\" action=\"modules.php?name=Player&pa=Leaderboards\">

<center><table><tr><td>Type: <select name=\"boards_type\">
";

if ($boards_type == 'Reg')
{
echo "  <option value=\"Reg\" SELECTED>Regular Season Totals</option>
";
} else {
echo "  <option value=\"Reg\">Regular Season Totals</option>
";
}

if ($boards_type == 'Rav')
{
echo "  <option value=\"Rav\" SELECTED>Regular Season Averages</option>
";
} else {
echo "  <option value=\"Rav\">Regular Season Averages</option>
";
}

if ($boards_type == 'Ply')
{
echo "  <option value=\"Ply\" SELECTED>Playoff Totals</option>
";
} else {
echo "  <option value=\"Ply\">Playoffs Totals</option>
";
}

if ($boards_type == 'PLA')
{
echo "  <option value=\"PLA\" SELECTED>Playoff Averages</option>
";
} else {
echo "  <option value=\"PLA\">Playoff Averages</option>
";
}


if ($boards_type == 'HET')
{
echo "  <option value=\"HET\" SELECTED>H.E.A.T. Totals</option>
";
} else {
echo "  <option value=\"HET\">H.E.A.T. Totals</option>
";
}

if ($boards_type == 'HEA')
{
echo "  <option value=\"HEA\" SELECTED>H.E.A.T. Averages</option>
";
} else {
echo "  <option value=\"HEA\">H.E.A.T. Averages</option>
";
}

echo "</select></td><td>
Category: <select name=\"sort_cat\">
";

if ($sort_cat == 'PTS')
{
echo "  <option value=\"PTS\" SELECTED>Points</option>
";
} else {
echo "  <option value=\"PTS\">Points</option>
";
}

if ($sort_cat == 'GM')
{
echo "  <option value=\"GM\" SELECTED>Games</option>
";
} else {
echo "  <option value=\"GM\">Games</option>
";
}

if ($sort_cat == 'MIN')
{
echo "  <option value=\"MIN\" SELECTED>Minutes</option>
";
} else {
echo "  <option value=\"MIN\">Minutes</option>
";
}

if ($sort_cat == 'FGM')
{
echo "  <option value=\"FGM\" SELECTED>Field Goals Made</option>
";
} else {
echo "  <option value=\"FGM\">Field Goals Made</option>
";
}

if ($sort_cat == 'FGA')
{
echo "  <option value=\"FGA\" SELECTED>Field Goals Attempted</option>
";
} else {
echo "  <option value=\"FGA\">Field Goals Attempted</option>
";
}

if ($sort_cat == 'FGPCT')
{
echo "  <option value=\"FGPCT\" SELECTED>FG Percentage (avgs only)</option>
";
} else {
echo "  <option value=\"FGPCT\">FG Percentage (avgs only)</option>
";
}

if ($sort_cat == 'FTM')
{
echo "  <option value=\"FTM\" SELECTED>Free Throws Made</option>
";
} else {
echo "  <option value=\"FTM\">Free Throws Made</option>
";
}

if ($sort_cat == 'FTA')
{
echo "  <option value=\"FTA\" SELECTED>Free Throws Attempted</option>
";
} else {
echo "  <option value=\"FTA\">Free Throws Attempted</option>
";
}

if ($sort_cat == 'FTPCT')
{
echo "  <option value=\"FTPCT\" SELECTED>FT Percentage (avgs only)</option>
";
} else {
echo "  <option value=\"FTPCT\">FT Percentage (avgs only)</option>
";
}

if ($sort_cat == 'TGM')
{
echo "  <option value=\"TGM\" SELECTED>Three-Pointers Made</option>
";
} else {
echo "  <option value=\"TGM\">Three-Pointers Made</option>
";
}

if ($sort_cat == 'TGA')
{
echo "  <option value=\"TGA\" SELECTED>Three-Pointers Attempted</option>
";
} else {
echo "  <option value=\"TGA\">Three-Pointers Attempted</option>
";
}

if ($sort_cat == 'TPCT')
{
echo "  <option value=\"TPCT\" SELECTED>3P Percentage (avgs only)</option>
";
} else {
echo "  <option value=\"TPCT\">3P Percentage (avgs only)</option>
";
}

if ($sort_cat == 'ORB')
{
echo "  <option value=\"ORB\" SELECTED>Offensive Rebounds</option>
";
} else {
echo "  <option value=\"ORB\">Offensive Rebounds</option>
";
}

if ($sort_cat == 'REB')
{
echo "  <option value=\"REB\" SELECTED>Total Rebounds</option>
";
} else {
echo "  <option value=\"REB\">Total Rebounds</option>
";
}

if ($sort_cat == 'AST')
{
echo "  <option value=\"AST\" SELECTED>Assists</option>
";
} else {
echo "  <option value=\"AST\">Assists</option>
";
}

if ($sort_cat == 'STL')
{
echo "  <option value=\"STL\" SELECTED>Steals</option>
";
} else {
echo "  <option value=\"STL\">Steals</option>
";
}

if ($sort_cat == 'TVR')
{
echo "  <option value=\"TVR\" SELECTED>Turnovers</option>
";
} else {
echo "  <option value=\"TVR\">Turnovers</option>
";
}

if ($sort_cat == 'BLK')
{
echo "  <option value=\"BLK\" SELECTED>Blocked Shots</option>
";
} else {
echo "  <option value=\"BLK\">Blocked Shots</option>
";
}

if ($sort_cat == 'PFL')
{
echo "  <option value=\"PFL\" SELECTED>Personal Fouls</option>
";
} else {
echo "  <option value=\"PFL\">Personal Fouls</option>
";
}

echo "</select></td><td>
Search: <select name=\"active\">
";

if ($active == '0')
{
echo "  <option value=\"0\" SELECTED>All Players</option>
";
} else {
echo "  <option value=\"0\">All Players</option>
";
}

if ($active == '1')
{
echo "  <option value=\"1\" SELECTED>Active Players Only</option>
";
} else {
echo "  <option value=\"1\">Active Players Only</option>
";
}

echo "</select></td>
<td>Limit: <input type=\"text\" name=\"display\" size=\"4\" value=\"$display\"> Records</td><td>
<input type=\"hidden\" name=\"submitted\" value=\"1\">
<input type=\"submit\" value=\"Display Leaderboards\"></form>
</td></tr></table>
";

// ===== RUN QUERY IF FORM HAS BEEN SUBMITTED

if ($submitted != NULL)
{

  $tableforquery="nuke_iblplyr";

  if ($boards_type == 'Reg')
  {
  $tableforquery="nuke_iblplyr";
  $restriction2="car_gm > 0 ";
  }

    if ($boards_type == 'Rav')
    {
    $tableforquery="ibl_season_career_avgs";
    $restriction2="games > 0";
  }

  if ($boards_type == 'Ply')
  {
  $tableforquery="ibl_playoff_career_totals";
  $restriction2="games > 0";
  }

    if ($boards_type == 'PLA')
    {
    $tableforquery="ibl_playoff_career_avgs";
    $restriction2="games > 0";
  }

  if ($boards_type == 'HET')
  {
  $tableforquery="ibl_heat_career_totals";
  $restriction2="games > 0";
  }

    if ($boards_type == 'HEA')
    {
    $tableforquery="ibl_heat_career_avgs";
    $restriction2="games > 0";
  }

  if ($active == 1)
  {
  $restriction1=" retired = '0' AND ";
  }

  $sortby="pts";
  if ($sort_cat == 'PTS')
  {
  $sortby="pts";
  }
  if ($sort_cat == 'GM')
  {
  $sortby="games";
  }
  if ($sort_cat == 'MIN')
  {
  $sortby="minutes";
  }
  if ($sort_cat == 'FGM')
  {
  $sortby="fgm";
  }
  if ($sort_cat == 'FGA')
  {
  $sortby="fga";
  }
  if ($sort_cat == 'FGPCT')
  {
  $sortby="fgpct";
  }
  if ($sort_cat == 'FTM')
  {
  $sortby="ftm";
  }
  if ($sort_cat == 'FTA')
  {
  $sortby="fta";
  }
  if ($sort_cat == 'FTPCT')
  {
  $sortby="ftpct";
  }
  if ($sort_cat == 'TGM')
  {
  $sortby="tgm";
  }
  if ($sort_cat == 'TGA')
  {
  $sortby="tga";
  }
  if ($sort_cat == 'TPCT')
  {
  $sortby="tpct";
  }
  if ($sort_cat == 'ORB')
  {
  $sortby="orb";
  }
  if ($sort_cat == 'REB')
  {
  $sortby="reb";
  }
  if ($sort_cat == 'AST')
  {
  $sortby="ast";
  }
  if ($sort_cat == 'STL')
  {
  $sortby="stl";
  }
  if ($sort_cat == 'TVR')
  {
  $sortby="tvr";
  }
  if ($sort_cat == 'BLK')
  {
  $sortby="blk";
  }
  if ($sort_cat == 'PFL')
  {
  $sortby="pf";
  }

  if ($tableforquery == "nuke_iblplyr")
  {
  $sortby="car_".$sortby;
   if ($sort_cat == 'GM')
   {
   $sortby="car_gm";
   }
   if ($sort_cat == 'MIN')
   {
   $sortby="car_min";
   }
   if ($sort_cat == 'TVR')
   {
   $sortby="car_to";
   }
  }

$query="SELECT * FROM $tableforquery WHERE $restriction1 $restriction2 ORDER BY $sortby DESC";
$result=mysql_query($query);
$num=mysql_numrows($result);

echo "<center><table><tr><th colspan=18><center>Leaderboards Display</center></th></tr>
<tr><th><center>Rank</th></center><th><center>Name</center></th><th><center>Gm</center></th><th><center>Min</center></th><th><center>FGM</a></center></th><th><center>FGA</center></th><th><center>FG%</center><th><center>FTM</center></th><th><center>FTA</center></th><th><center>FT%</center><th><center>3PTM</center></th><th><center>3PTA</center></th><th><center>3P%</center><th><center>ORB</center></th><th><center>REB</center></th><th><center>AST</center></th><th><center>STL</center></th><th><center>TVR</center></th><th><center>BLK</center></th><th><center>FOULS</center></th><th><center>PTS</center></th></tr>
";

// ========== FILL ROWS

if ($display == 0)
{
$numstop=$num;
} else {
$numstop=$display;
}

if ($display == NULL)
{
$numstop=$num;
} else {
$numstop=$display;
}

$i=0;

while ($i < $numstop)
{

$retired=0;
if ($tableforquery == "nuke_iblplyr")
{
$retired=mysql_result($result,$i,"retired");
$plyr_name=mysql_result($result,$i,"name");
$pid=mysql_result($result,$i,"pid");
$gm=number_format(mysql_result($result,$i,"car_gm"));
$min=number_format(mysql_result($result,$i,"car_min"));
$fgm=number_format(mysql_result($result,$i,"car_fgm"));
$fga=number_format(mysql_result($result,$i,"car_fga"));
$ftm=number_format(mysql_result($result,$i,"car_ftm"));
$fta=number_format(mysql_result($result,$i,"car_fta"));
$tgm=number_format(mysql_result($result,$i,"car_tgm"));
$tga=number_format(mysql_result($result,$i,"car_tga"));
$orb=number_format(mysql_result($result,$i,"car_orb"));
$reb=number_format(mysql_result($result,$i,"car_reb"));
$ast=number_format(mysql_result($result,$i,"car_ast"));
$stl=number_format(mysql_result($result,$i,"car_stl"));
$to=number_format(mysql_result($result,$i,"car_to"));
$blk=number_format(mysql_result($result,$i,"car_blk"));
$pf=number_format(mysql_result($result,$i,"car_pf"));
$pts=number_format(mysql_result($result,$i,"car_pts"));
}

if ($tableforquery == "ibl_season_career_avgs")
{
$plyr_name=mysql_result($result,$i,"name");
$pid=mysql_result($result,$i,"pid");
$gm=number_format(mysql_result($result,$i,"games"));
$min=(mysql_result($result,$i,"minutes"));
$fgm=(mysql_result($result,$i,"fgm"));
$fga=(mysql_result($result,$i,"fga"));
$fgpct=(mysql_result($result,$i,"fgpct"));
$ftm=(mysql_result($result,$i,"ftm"));
$fta=(mysql_result($result,$i,"fta"));
$ftpct=(mysql_result($result,$i,"ftpct"));
$tgm=(mysql_result($result,$i,"tgm"));
$tga=(mysql_result($result,$i,"tga"));
$tpct=(mysql_result($result,$i,"tpct"));
$orb=(mysql_result($result,$i,"orb"));
$reb=(mysql_result($result,$i,"reb"));
$ast=(mysql_result($result,$i,"ast"));
$stl=(mysql_result($result,$i,"stl"));
$to=(mysql_result($result,$i,"tvr"));
$blk=(mysql_result($result,$i,"blk"));
$pf=(mysql_result($result,$i,"pf"));
$pts=(mysql_result($result,$i,"pts"));
}

if ($tableforquery == "ibl_heat_career_totals")
{
$plyr_name=mysql_result($result,$i,"name");
$pid=mysql_result($result,$i,"pid");
$gm=number_format(mysql_result($result,$i,"games"));
$min=number_format(mysql_result($result,$i,"minutes"));
$fgm=number_format(mysql_result($result,$i,"fgm"));
$fga=number_format(mysql_result($result,$i,"fga"));
$ftm=number_format(mysql_result($result,$i,"ftm"));
$fta=number_format(mysql_result($result,$i,"fta"));
$tgm=number_format(mysql_result($result,$i,"tgm"));
$tga=number_format(mysql_result($result,$i,"tga"));
$orb=number_format(mysql_result($result,$i,"orb"));
$reb=number_format(mysql_result($result,$i,"reb"));
$ast=number_format(mysql_result($result,$i,"ast"));
$stl=number_format(mysql_result($result,$i,"stl"));
$to=number_format(mysql_result($result,$i,"tvr"));
$blk=number_format(mysql_result($result,$i,"blk"));
$pf=number_format(mysql_result($result,$i,"pf"));
$pts=number_format(mysql_result($result,$i,"pts"));
}

if ($tableforquery == "ibl_playoff_career_totals")
{
$plyr_name=mysql_result($result,$i,"name");
$pid=mysql_result($result,$i,"pid");
$gm=number_format(mysql_result($result,$i,"games"));
$min=number_format(mysql_result($result,$i,"minutes"));
$fgm=number_format(mysql_result($result,$i,"fgm"));
$fga=number_format(mysql_result($result,$i,"fga"));
$ftm=number_format(mysql_result($result,$i,"ftm"));
$fta=number_format(mysql_result($result,$i,"fta"));
$tgm=number_format(mysql_result($result,$i,"tgm"));
$tga=number_format(mysql_result($result,$i,"tga"));
$orb=number_format(mysql_result($result,$i,"orb"));
$reb=number_format(mysql_result($result,$i,"reb"));
$ast=number_format(mysql_result($result,$i,"ast"));
$stl=number_format(mysql_result($result,$i,"stl"));
$to=number_format(mysql_result($result,$i,"tvr"));
$blk=number_format(mysql_result($result,$i,"blk"));
$pf=number_format(mysql_result($result,$i,"pf"));
$pts=number_format(mysql_result($result,$i,"pts"));
}

if ($tableforquery == "ibl_heat_career_avgs")
{
$plyr_name=mysql_result($result,$i,"name");
$pid=mysql_result($result,$i,"pid");
$gm=number_format(mysql_result($result,$i,"games"));
$min=(mysql_result($result,$i,"minutes"));
$fgm=(mysql_result($result,$i,"fgm"));
$fga=(mysql_result($result,$i,"fga"));
$fgpct=(mysql_result($result,$i,"fgpct"));
$ftm=(mysql_result($result,$i,"ftm"));
$fta=(mysql_result($result,$i,"fta"));
$ftpct=(mysql_result($result,$i,"ftpct"));
$tgm=(mysql_result($result,$i,"tgm"));
$tga=(mysql_result($result,$i,"tga"));
$tpct=(mysql_result($result,$i,"tpct"));
$orb=(mysql_result($result,$i,"orb"));
$reb=(mysql_result($result,$i,"reb"));
$ast=(mysql_result($result,$i,"ast"));
$stl=(mysql_result($result,$i,"stl"));
$to=(mysql_result($result,$i,"tvr"));
$blk=(mysql_result($result,$i,"blk"));
$pf=(mysql_result($result,$i,"pf"));
$pts=(mysql_result($result,$i,"pts"));
}

if ($tableforquery == "ibl_playoff_career_avgs")
{
$plyr_name=mysql_result($result,$i,"name");
$pid=mysql_result($result,$i,"pid");
$gm=number_format(mysql_result($result,$i,"games"));
$min=(mysql_result($result,$i,"minutes"));
$fgm=(mysql_result($result,$i,"fgm"));
$fga=(mysql_result($result,$i,"fga"));
$fgpct=(mysql_result($result,$i,"fgpct"));
$ftm=(mysql_result($result,$i,"ftm"));
$fta=(mysql_result($result,$i,"fta"));
$ftpct=(mysql_result($result,$i,"ftpct"));
$tgm=(mysql_result($result,$i,"tgm"));
$tga=(mysql_result($result,$i,"tga"));
$tpct=(mysql_result($result,$i,"tpct"));
$orb=(mysql_result($result,$i,"orb"));
$reb=(mysql_result($result,$i,"reb"));
$ast=(mysql_result($result,$i,"ast"));
$stl=(mysql_result($result,$i,"stl"));
$to=(mysql_result($result,$i,"tvr"));
$blk=(mysql_result($result,$i,"blk"));
$pf=(mysql_result($result,$i,"pf"));
$pts=(mysql_result($result,$i,"pts"));
}


$i++;

echo "<tr><td><center>$i</center></td><td><center><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$plyr_name";

if ($retired == 1)
{
echo "*";
}

echo "</center></td><td><center>$gm</center></td><td><center>$min</center></td><td><center>$fgm</center></td><td><center>$fga</center></td><td><center>$fgpct</center><td><center>$ftm</center></td><td><center>$fta</center></td><td><center>$ftpct</center><td><center>$tgm</center></td><td><center>$tga</center></td><td><center>$tpct</center><td><center>$orb</center></td><td><center>$reb</center></td><td><center>$ast</center></td><td><center>$stl</center></td><td><center>$to</center></td><td><center>$blk</center></td><td><center>$pf</center></td><td><center>$pts</center></td></tr>";

}

echo "</table></center>
</td></tr></table>
";

} // Close of "if" statement that only displays leaderboards if the form was submitted.

    CloseTable();
    include("footer.php");
}

// ========================================================
//
// END LEADERBOARDS
//
// ========================================================

// ========================================================
//
// PLAYER SEARCH
//
// ========================================================

function search()
{
  global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;
    include("header.php");
    OpenTable();

    menu();


// ============== GET POST DATA

$pos = $_POST['pos'];
$age = $_POST['age'];
$form_submitted_check = $_POST['submitted'];
$search_name = $_POST['search_name'];
$college = $_POST['college'];
$exp = $_POST['exp'];
$bird = $_POST['bird'];
$exp_max = $_POST['exp_max'];
$bird_max = $_POST['bird_max'];

$r_fga = $_POST['r_fga'];
$r_fgp = $_POST['r_fgp'];
$r_fta = $_POST['r_fta'];
$r_ftp = $_POST['r_ftp'];
$r_tga = $_POST['r_tga'];
$r_tgp = $_POST['r_tgp'];
$r_orb = $_POST['r_orb'];
$r_drb = $_POST['r_drb'];
$r_ast = $_POST['r_ast'];
$r_stl = $_POST['r_stl'];
$r_blk = $_POST['r_blk'];
$r_to = $_POST['r_to'];
$r_foul = $_POST['r_foul'];

$Stamina = $_POST['sta'];
$Clutch = $_POST['Clutch'];
$Consistency = $_POST['Consistency'];
$talent = $_POST['talent'];
$skill = $_POST['skill'];
$intangibles = $_POST['intangibles'];

$active = $_POST['active'];

$oo = $_POST['oo'];
$do = $_POST['do'];
$po = $_POST['po'];
$to = $_POST['to'];
$od = $_POST['od'];
$dd = $_POST['dd'];
$pd = $_POST['pd'];
$td = $_POST['td'];

// ========= SEARCH PARAMETERS

echo "Please enter your search parameters (Age is less than or equal to the age entered; all other fields are greater than or equal to the amount entered).  Partial matches on a name or college are okay and are <b>not</b> case sensitive (e.g., entering \"Dard\" will match with \"Darden\" and \"Bedard\").
<br><br>
Warning: Searches that may return a lot of players may take a long time to load!

<form name=\"Search\" method=\"post\" action=\"modules.php?name=Player&pa=search\">
<table border=1><tr><td>
Position: <select name=\"pos\">
  <option value=\"\">-</option>
";

if ($pos == 'PG')
{
echo "  <option value=\"PG\" SELECTED>PG</option>
";
} else {
echo "  <option value=\"PG\">PG</option>
";
}
if ($pos == 'SG')
{
echo "  <option value=\"SG\" SELECTED>SG</option>
";
} else {
echo "  <option value=\"SG\">SG</option>
";
}
if ($pos == 'SF')
{
echo "  <option value=\"SF\" SELECTED>SF</option>
";
} else {
echo "  <option value=\"SF\">SF</option>
";
}
if ($pos == 'PF')
{
echo "  <option value=\"PF\" SELECTED>PF</option>
";
} else {
echo "  <option value=\"PF\">PF</option>
";
}
if ($pos == 'C')
{
echo "  <option value=\"C\" SELECTED>C</option>
";
} else {
echo "  <option value=\"C\">C</option>
";
}

echo "</select>
</td>
<td>Age: <input type=\"text\" name=\"age\" size=\"2\" value=\"$age\"></td>
<td>Stamina: <input type=\"text\" name=\"sta\" size=\"2\" value=\"$Stamina\"></td>
<td>Talent: <input type=\"text\" name=\"talent\" size=\"1\" value=\"$talent\"></td>
<td>Skill: <input type=\"text\" name=\"skill\" size=\"1\" value=\"$skill\"></td>
<td>Intangibles: <input type=\"text\" name=\"intangibles\" size=\"1\" value=\"$intangibles\"></td>
<td>clutch: <input type=\"text\" name=\"Clutch\" size=\"1\" value=\"$Clutch\"></td>
<td>Consistency: <input type=\"text\" name=\"Consistency\" size=\"1\" value=\"$Consistency\"></td>
<td>College: <input type=\"text\" name=\"college\" size=\"16\" value=\"$college\"></td>
</tr>
<tr>
<td colspan=8>Include Retired Players in search? <select name=\"active\">
";
if ($active == '1')
{
echo "  <option value=\"1\" SELECTED>Yes</option>
";
} else {
echo "  <option value=\"1\">Yes</option>
";
}
if ($active == '0')
{
echo "  <option value=\"0\" SELECTED>No</option>
";
} else {
echo "  <option value=\"0\">No</option>
";
}


echo "</td></tr>
<tr>
<td colspan=2>Minimum Years In League:<input type=\"text\" name=\"exp\" size=\"2\" value=\"$exp\"></td>
<td colspan=2>Maximum Years In League:<input type=\"text\" name=\"exp_max\" size=\"2\" value=\"$exp\"></td>
<td colspan=2>Minimum Bird Years:<input type=\"text\" name=\"bird\" size=\"2\" value=\"$bird\"></td>
<td colspan=2>Maximum Bird Years:<input type=\"text\" name=\"bird_max\" size=\"2\" value=\"$bird\"></td>

</tr>
</table><table border=1><tr>
<td>2ga:<input type=\"text\" name=\"r_fga\" size=\"2\" value=\"$r_fga\"></td>
<td>2gp:<input type=\"text\" name=\"r_fgp\" size=\"2\" value=\"$r_fgp\"></td>
<td>fta:<input type=\"text\" name=\"r_fta\" size=\"2\" value=\"$r_fta\"></td>
<td>ftp:<input type=\"text\" name=\"r_ftp\" size=\"2\" value=\"$r_ftp\"></td>
<td>3ga:<input type=\"text\" name=\"r_tga\" size=\"2\" value=\"$r_tga\"></td>
<td>3gp:<input type=\"text\" name=\"r_tgp\" size=\"2\" value=\"$r_tgp\"></td>
<td>orb:<input type=\"text\" name=\"r_orb\" size=\"2\" value=\"$r_orb\"></td>
<td>drb:<input type=\"text\" name=\"r_drb\" size=\"2\" value=\"$r_drb\"></td>
<td>ast:<input type=\"text\" name=\"r_ast\" size=\"2\" value=\"$r_ast\"></td>
<td>stl:<input type=\"text\" name=\"r_stl\" size=\"2\" value=\"$r_stl\"></td>
<td>blk:<input type=\"text\" name=\"r_blk\" size=\"2\" value=\"$r_blk\"></td>
<td>tvr:<input type=\"text\" name=\"r_to\" size=\"2\" value=\"$r_to\"></td>
<td>foul:<input type=\"text\" name=\"r_foul\" size=\"2\" value=\"$r_foul\"></td>
</tr></table><table border=1><tr>
<td>NAME: <input type=\"text\" name=\"search_name\" size=\"32\" value=\"$search_name\"></td>
<td>oo: <input type=\"text\" name=\"oo\" size=\"1\" value=\"$oo\"></td>
<td>do: <input type=\"text\" name=\"do\" size=\"1\" value=\"$do\"></td>
<td>po: <input type=\"text\" name=\"po\" size=\"1\" value=\"$po\"></td>
<td>to: <input type=\"text\" name=\"to\" size=\"1\" value=\"$to\"></td>
<td>od: <input type=\"text\" name=\"od\" size=\"1\" value=\"$od\"></td>
<td>dd: <input type=\"text\" name=\"dd\" size=\"1\" value=\"$dd\"></td>
<td>pd: <input type=\"text\" name=\"pd\" size=\"1\" value=\"$pd\"></td>
<td>td: <input type=\"text\" name=\"td\" size=\"1\" value=\"$td\"></td>
</tr></table>

<input type=\"hidden\" name=\"submitted\" value=\"1\">
<input type=\"submit\" value=\"Search for Player!\"></form>
";

// ========= SET QUERY BASED ON SEARCH PARAMETERS


$query="SELECT * FROM nuke_iblplyr WHERE pid > '0'";

if ($active == 0)
{
$query=$query." AND retired = '0'";
}
if ($search_name != NULL)
{
$query=$query." AND name LIKE '%$search_name%'";
}
if ($college != NULL)
{
$query=$query." AND college LIKE '%$college%'";
}
if ($pos != NULL)
{
$query=$query." AND pos = '$pos'";
}
if ($age != NULL)
{
$age=$age+1;
$query=$query." AND age < '$age'";
}
if ($Clutch != NULL)
{
$Clutch=$Clutch-1;
$query=$query." AND Clutch > '$Clutch'";
}
if ($Stamina != NULL)
{
$Stamina=$Stamina-1;
$query=$query." AND sta > '$Stamina'";
}
if ($Consistency != NULL)
{
$Consistency=$Consistency-1;
$query=$query." AND Consistency > '$Consistency'";
}

if ($oo != NULL)
{
$oo=$oo-1;
$query=$query." AND oo > '$oo'";
}
if ($do != NULL)
{
$do=$do-1;
$query=$query." AND do > '$do'";
}
if ($po != NULL)
{
$po=$po-1;
$query=$query." AND po > '$po'";
}

if ($to != NULL)
{
$to=$to-1;
$query=$query." AND to > '$to'";
}

if ($od != NULL)
{
$od=$od-1;
$query=$query." AND od > '$od'";
}
if ($dd != NULL)
{
$dd=$dd-1;
$query=$query." AND dd > '$dd'";
}
if ($pd != NULL)
{
$pd=$pd-1;
$query=$query." AND pd > '$pd'";
}
if ($td != NULL)
{
$td=$td-1;
$query=$query." AND td > '$td'";
}

if ($exp != NULL)
{
$exp=$exp-1;
$query=$query." AND exp > '$exp'";
}
if ($bird != NULL)
{
$bird=$bird-1;
$query=$query." AND bird > '$bird'";
}

if ($exp_max != NULL)
{
$exp_max=$exp_max+1;
$query=$query." AND exp < '$exp_max'";
}
if ($bird != NULL)
{
$bird_max=$bird_max+1;
$query=$query." AND bird < '$bird_max'";
}

if ($talent != NULL)
{
$talent=$talent-1;
$query=$query." AND talent > '$talent'";
}
if ($skill != NULL)
{
$skill=$skill-1;
$query=$query." AND skill > '$skill'";
}
if ($intangibles != NULL)
{
$intangibles=$intangibles-1;
$query=$query." AND intangibles > '$intangibles'";
}

if ($coach != NULL)
{
$coach=$coach-1;
$query=$query." AND coach > '$coach'";
}
if ($loyalty != NULL)
{
$loyalty=$loyalty-1;
$query=$query." AND loyalty > '$loyalty'";
}
if ($playingTime != NULL)
{
$playingTime=$playingTime-1;
$query=$query." AND playingTime > '$playingTime'";
}
if ($winner != NULL)
{
$winner=$winner-1;
$query=$query." AND winner > '$winner'";
}
if ($tradition != NULL)
{
$tradition=$tradition-1;
$query=$query." AND tradition > '$tradition'";
}
if ($security != NULL)
{
$security=$security-1;
$query=$query." AND security > '$security'";
}

if ($exp != NULL)
{
$exp=$exp-1;
$query=$query." AND exp > '$exp'";
}
if ($bird != NULL)
{
$bird=$bird-1;
$query=$query." AND bird > '$bird'";
}

if ($r_fga != NULL)
{
$r_fga=$r_fga-1;
$query=$query." AND r_fga > '$r_fga'";
}
if ($r_fgp != NULL)
{
$r_fgp=$r_fgp-1;
$query=$query." AND r_fgp > '$r_fgp'";
}
if ($r_fta != NULL)
{
$r_fta=$r_fta-1;
$query=$query." AND r_fta > '$r_fta'";
}
if ($r_ftp != NULL)
{
$r_ftp=$r_ftp-1;
$query=$query." AND r_ftp > '$r_ftp'";
}
if ($r_tga != NULL)
{
$r_tga=$r_tga-1;
$query=$query." AND r_tga > '$r_tga'";
}
if ($r_tgp != NULL)
{
$r_tgp=$r_tgp-1;
$query=$query." AND r_tgp > '$r_tgp'";
}
if ($r_orb != NULL)
{
$r_orb=$r_orb-1;
$query=$query." AND r_orb > '$r_orb'";
}
if ($r_drb != NULL)
{
$r_drb=$r_drb-1;
$query=$query." AND r_drb > '$r_drb'";
}
if ($r_ast != NULL)
{
$r_ast=$r_ast-1;
$query=$query." AND r_ast > '$r_ast'";
}
if ($r_stl != NULL)
{
$r_stl=$r_stl-1;
$query=$query." AND r_stl > '$r_stl'";
}
if ($r_to != NULL)
{
$r_to=$r_to-1;
$query=$query." AND r_to > '$r_to'";
}
if ($r_blk != NULL)
{
$r_blk=$r_blk-1;
$query=$query." AND r_blk > '$r_blk'";
}
if ($r_foul != NULL)
{
$r_foul=$r_foul-1;
$query=$query." AND r_foul > '$r_foul'";
}


$query=$query." ORDER BY retired, ordinal ASC";

// =============== EXECUTE QUERY

if ($form_submitted_check==1)
{
$result=mysql_query($query);
@$num=mysql_numrows($result);
}

echo "<table border=1 cellpadding=0 cellspacing=0>
<tr><td colspan=31><center><i>Players Matching all Criteria</i></center></td></tr>
<tr><th>Pos</th><th>Player</th><th>Age</th><th>Stamina</th><th>Team</th><th>Exp</th><th>Bird</th><th>2ga</th><th>2gp</th><th>fta</th><th>ftp</th><th>3ga</th><th>3gp</th><th>orb</th><th>drb</th><th>ast</th><th>stl</th><th>tvr</th><th>blk</th><th>foul</th><th>oo</th><th>do</th><th>po</th><th>to</th><th>od</th><th>dd</th><th>pd</th><th>td</th><th>Talent</th><th>Skill</th><th>Intangibles</th><th>Clutch</th><th>consistency</th><th>College</th></tr>
";

// ========== FILL PLAYING RATINGS

if ($form_submitted_check==1)
{
$i=0;

while ($i < $num)
{
$retired=mysql_result($result,$i,"retired");
$name=mysql_result($result,$i,"name");
$pos=mysql_result($result,$i,"pos");
$pid=mysql_result($result,$i,"pid");
$tid=mysql_result($result,$i,"tid");
$age=mysql_result($result,$i,"age");
$teamname=mysql_result($result,$i,"teamname");
$college=mysql_result($result,$i,"college");
$collegeid=mysql_result($result,$i,"collegeid");
$exp=mysql_result($result,$i,"exp");
$bird=mysql_result($result,$i,"bird");

$r_sta=mysql_result($result,$i,"sta");
$r_fga=mysql_result($result,$i,"r_fga");
$r_fgp=mysql_result($result,$i,"r_fgp");
$r_fta=mysql_result($result,$i,"r_fta");
$r_ftp=mysql_result($result,$i,"r_ftp");
$r_tga=mysql_result($result,$i,"r_tga");
$r_tgp=mysql_result($result,$i,"r_tgp");
$r_orb=mysql_result($result,$i,"r_orb");
$r_drb=mysql_result($result,$i,"r_drb");
$r_ast=mysql_result($result,$i,"r_ast");
$r_stl=mysql_result($result,$i,"r_stl");
$r_tvr=mysql_result($result,$i,"r_to");
$r_blk=mysql_result($result,$i,"r_blk");
$r_foul=mysql_result($result,$i,"r_foul");
$oo=mysql_result($result,$i,"oo");
$do=mysql_result($result,$i,"do");
$po=mysql_result($result,$i,"po");
$to=mysql_result($result,$i,"to");
$od=mysql_result($result,$i,"od");
$dd=mysql_result($result,$i,"dd");
$pd=mysql_result($result,$i,"pd");
$td=mysql_result($result,$i,"td");

$Clutch=mysql_result($result,$i,"Clutch");
$Consistency=mysql_result($result,$i,"Consistency");
$talent=mysql_result($result,$i,"talent");
$skill=mysql_result($result,$i,"skill");
$intangibles=mysql_result($result,$i,"intangibles");

if ($i % 2)
{
echo "
<tr bgcolor=#ffffff>";
} else {
echo "
<tr bgcolor=#e6e7e2>";
}

$i++;

if ($retired == 1)
{
echo "<td><center>$pos</center></td><td><center><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></center></td><td colspan=30><center> --- Retired --- </center></td><td><a href=\"http://college.iblhoops.net/rosters/roster$collegeid.htm\">$college</td></tr>
";
} else {
echo "<td><center>$pos</center></td><td><center><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></center></td><td><center>$age</center></td><td><center>$r_sta</center></td><td><center><a href=\"team.php?tid=$tid\">$teamname</a></center></td><td><center>$exp</center></td><td><center>$bird</center></td><td><center>$r_fga</center></td><td><center>$r_fgp</center></td><td><center>$r_fta</center></td><td><center>$r_ftp</center></td><td><center>$r_tga</center></td><td><center>$r_tgp</center></td><td><center>$r_orb</center></td><td><center>$r_drb</center></td><td><center>$r_ast</center></td><td><center>$r_stl</center></td><td><center>$r_tvr</center></td><td><center>$r_blk</center></td><td><center>$r_foul</center></td><td><center>$oo</center></td><td><center>$do</center></td><td><center>$po</center></td><td><center>$to</center></td><td><center>$od</center></td><td><center>$dd</center></td><td><center>$pd</center></td><td><center>$td</center></td><td><center>$talent</center></td><td><center>$skill</center></td><td><center>$intangibles</center></td><td><center>$Clutch</center></td><td><center>$Consistency</center></td><td><a href=\"http://college.iblhoops.net/rosters/roster$collegeid.htm\">$college</td></tr>
";
}
} // Matches up with form submitted check variable


}


echo "</table></center>
";

    CloseTable();
    include("footer.php");

}

// ========================================================
//
// END PLAYER SEARCH
//
// ========================================================

// ========================================================
//
// AWARDS SEARCH
//
// ========================================================

function awards()
{
  global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;
    include("header.php");
    OpenTable();

    menu();

// ============== GET POST DATA

$as_name = $_POST['aw_name'];
$as_Award = $_POST['aw_Award'];
$as_year = $_POST['aw_year'];
$as_sortby = $_POST['aw_sortby'];

// ========= SEARCH PARAMETERS

echo "Please enter your search parameters Partial matches on a name or award are okay and are <b>not</b> case sensitive (e.g., entering \"Dard\" will match with \"Darden\" and \"Bedard\").

<form name=\"Search\" method=\"post\" action=\"modules.php?name=Player&pa=awards\">
<table border=1><tr><td>
<td>NAME: <input type=\"text\" name=\"aw_name\" size=\"32\" value=\"$as_name\"></td>
<td>AWARD: <input type=\"text\" name=\"aw_Award\" size=\"32\" value=\"$as_Award\"></td>
<td>Year: <input type=\"text\" name=\"aw_year\" size=\"4\" value=\"$as_year\"></td></tr>
<tr><td colspan=3>SORT BY:
";

if ($as_sortby == NULL)
{
$sortby=3;
}

if ($as_sortby == 1)
{
echo "<input type=\"radio\" name=\"aw_sortby\" value=\"1\" checked> Name |
";
} else {
echo "<input type=\"radio\" name=\"aw_sortby\" value=\"1\"> Name |
";
}

if ($as_sortby == 2)
{
echo "<input type=\"radio\" name=\"aw_sortby\" value=\"2\" checked> Award Name |
";
} else {
echo "<input type=\"radio\" name=\"aw_sortby\" value=\"2\"> Award Name |
";
}

if ($as_sortby == 3)
{
echo "<input type=\"radio\" name=\"aw_sortby\" value=\"3\" checked> Year |
";
} else {
echo "<input type=\"radio\" name=\"aw_sortby\" value=\"3\"> Year |
";
}

echo "</td></tr></table>

<input type=\"submit\" value=\"Search for Matches!\"></form>
";

// ========= SET QUERY BASED ON SEARCH PARAMETERS

$continuequery=0;
$query="SELECT * FROM nuke_ibl_awards";

if ($as_year != NULL)
{
$query=$query." WHERE year = '$as_year'";
$continuequery=1;
}

if ($continuequery == 0)
{
if ($as_Award != NULL)
{
$query=$query." WHERE Award LIKE '%$as_Award%'";
$continuequery=1;
}
} else {
if ($as_Award != NULL)
{
$query=$query." AND Award LIKE '%$as_Award%'";
}
}

if ($continuequery == 0)
{
if ($as_name != NULL)
{
$query=$query." WHERE name LIKE '%$as_name%'";
$continuequery=1;
}
} else {
if ($as_name != NULL)
{
$query=$query." AND name LIKE '%$as_name%'";
}
}

$orderby='Year';

if ($as_sortby == 1)
{
$orderby='name';
}

if ($as_sortby == 2)
{
$orderby='Award';
}

if ($as_sortby == 3)
{
$orderby='year';
}

$query=$query." ORDER BY $orderby ASC";

// =============== EXECUTE QUERY

$result=mysql_query($query);
@$num=mysql_numrows($result);

echo "<table border=1 cellpadding=0 cellspacing=0>
<tr><td colspan=3><center><i>Search Results</i></center></td></tr>
<tr><th>Year</th><th>Player</th><th>Award</th></tr>
";

// ========== FILL RESULTS

$i=0;

while ($i < $num)
{
$a_name=mysql_result($result,$i,"name");
$a_Award=mysql_result($result,$i,"Award");
$a_year=mysql_result($result,$i,"year");
if ($i % 2)
{
echo "
<tr bgcolor=#ffffff>";
} else {
echo "
<tr bgcolor=#e6e7e2>";
}

$i++;

echo "<tr><td><center>$a_year</center></td><td><center>$a_name</a></center></td><td><center>$a_Award</center></td></tr>
";

}

echo "</table></center>
";


    CloseTable();
    include("footer.php");
}

// ========================================================
//
// END AWARDS SEARCH
//
// ========================================================

// ========================================================
//
// MAIN PLAYER PAGE
//
// ========================================================

function showpage($pid,$spec) {
    global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;
    $pid = intval($pid);
    $spec = intval($spec);
    $playerinfo = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_iblplyr WHERE pid='$pid'"));
    $player_name = stripslashes(check_html($playerinfo['name'], "nohtml"));
    $player_nickname = stripslashes(check_html($playerinfo['nickname'], "nohtml"));
    $player_pos = stripslashes(check_html($playerinfo['altpos'], "nohtml"));
    $player_team_name = stripslashes(check_html($playerinfo['teamname'], "nohtml"));
    $player_team_id = stripslashes(check_html($playerinfo['tid'], "nohtml"));
    $player_ht_ft = stripslashes(check_html($playerinfo['htft'], "nohtml"));
    $player_ht_in = stripslashes(check_html($playerinfo['htin'], "nohtml"));
    $player_wt = stripslashes(check_html($playerinfo['wt'], "nohtml"));
    $player_age = stripslashes(check_html($playerinfo['age'], "nohtml"));
    $player_nation = stripslashes(check_html($playerinfo['nation'], "nohtml"));
    $player_drafted_by = stripslashes(check_html($playerinfo['draftedby'], "nohtml"));
    $player_draft_pick = stripslashes(check_html($playerinfo['draftpickno'], "nohtml"));
    $player_draft_round = stripslashes(check_html($playerinfo['draftround'], "nohtml"));
    $player_draft_year = stripslashes(check_html($playerinfo['draftyear'], "nohtml"));
    $player_college = stripslashes(check_html($playerinfo['college'], "nohtml"));
    $player_collegeid = stripslashes(check_html($playerinfo['collegeid'], "nohtml"));

    $player_exp = stripslashes(check_html($playerinfo['exp'], "nohtml"));
    $year = stripslashes(check_html($playerinfo['draftyear'], "nohtml"))+stripslashes(check_html($playerinfo['exp'], "nohtml"));
    $player_talent = stripslashes(check_html($playerinfo['talent'], "nohtml"));
    $player_skill = stripslashes(check_html($playerinfo['skill'], "nohtml"));
    $player_intangibles = stripslashes(check_html($playerinfo['intangibles'], "nohtml"));
    $player_clutch = stripslashes(check_html($playerinfo['Clutch'], "nohtml"));
    $player_consistency = stripslashes(check_html($playerinfo['Consistency'], "nohtml"));
    $player_loyalty = stripslashes(check_html($playerinfo['loyalty'], "nohtml"));
    $player_winner = stripslashes(check_html($playerinfo['winner'], "nohtml"));
    $player_playingtime = stripslashes(check_html($playerinfo['playingTime'], "nohtml"));
    $player_security = stripslashes(check_html($playerinfo['security'], "nohtml"));
    $player_coach = stripslashes(check_html($playerinfo['coach'], "nohtml"));
    $player_tradition = stripslashes(check_html($playerinfo['tradition'], "nohtml"));
    $player_retired = stripslashes(check_html($playerinfo['retired'], "nohtml"));

    $player_rating_2ga = stripslashes(check_html($playerinfo['r_fga'], "nohtml"));
    $player_rating_2gp = stripslashes(check_html($playerinfo['r_fgp'], "nohtml"));
    $player_rating_fta = stripslashes(check_html($playerinfo['r_fta'], "nohtml"));
    $player_rating_ftp = stripslashes(check_html($playerinfo['r_ftp'], "nohtml"));
    $player_rating_3ga = stripslashes(check_html($playerinfo['r_tga'], "nohtml"));
    $player_rating_3gp = stripslashes(check_html($playerinfo['r_tgp'], "nohtml"));
    $player_rating_orb = stripslashes(check_html($playerinfo['r_orb'], "nohtml"));
    $player_rating_drb = stripslashes(check_html($playerinfo['r_drb'], "nohtml"));
    $player_rating_ast = stripslashes(check_html($playerinfo['r_ast'], "nohtml"));
    $player_rating_stl = stripslashes(check_html($playerinfo['r_stl'], "nohtml"));
    $player_rating_tvr = stripslashes(check_html($playerinfo['r_to'], "nohtml"));
    $player_rating_blk = stripslashes(check_html($playerinfo['r_blk'], "nohtml"));
    $player_rating_oo = stripslashes(check_html($playerinfo['oo'], "nohtml"));
    $player_rating_do = stripslashes(check_html($playerinfo['do'], "nohtml"));
    $player_rating_po = stripslashes(check_html($playerinfo['po'], "nohtml"));
    $player_rating_to = stripslashes(check_html($playerinfo['to'], "nohtml"));
    $player_rating_od = stripslashes(check_html($playerinfo['od'], "nohtml"));
    $player_rating_dd = stripslashes(check_html($playerinfo['dd'], "nohtml"));
    $player_rating_pd = stripslashes(check_html($playerinfo['pd'], "nohtml"));
    $player_rating_td = stripslashes(check_html($playerinfo['td'], "nohtml"));

    $stats_gm = stripslashes(check_html($playerinfo['stats_gm'], "nohtml"));
    $stats_min = stripslashes(check_html($playerinfo['stats_min'], "nohtml"));
    $stats_fgm = stripslashes(check_html($playerinfo['stats_fgm'], "nohtml"));
    $stats_fga = stripslashes(check_html($playerinfo['stats_fga'], "nohtml"));
@$stats_fgp=($stats_fgm/$stats_fga);
    $stats_ftm = stripslashes(check_html($playerinfo['stats_ftm'], "nohtml"));
    $stats_fta = stripslashes(check_html($playerinfo['stats_fta'], "nohtml"));
@$stats_ftp=($stats_ftm/$stats_fta);
    $stats_tgm = stripslashes(check_html($playerinfo['stats_3gm'], "nohtml"));
    $stats_tga = stripslashes(check_html($playerinfo['stats_3ga'], "nohtml"));
@$stats_tgp=($stats_tgm/$stats_tga);
    $stats_orb = stripslashes(check_html($playerinfo['stats_orb'], "nohtml"));
    $stats_drb = stripslashes(check_html($playerinfo['stats_drb'], "nohtml"));
$stats_reb=$stats_orb+$stats_drb;
    $stats_ast = stripslashes(check_html($playerinfo['stats_ast'], "nohtml"));
    $stats_stl = stripslashes(check_html($playerinfo['stats_stl'], "nohtml"));
    $stats_to = stripslashes(check_html($playerinfo['stats_to'], "nohtml"));
    $stats_blk = stripslashes(check_html($playerinfo['stats_blk'], "nohtml"));
    $stats_pf = stripslashes(check_html($playerinfo['stats_pf'], "nohtml"));
$stats_pts=2*$stats_fgm+$stats_ftm+$stats_tgm;

@$stats_mpg=($stats_min/$stats_gm);
@$stats_opg=($stats_orb/$stats_gm);
@$stats_rpg=($stats_reb/$stats_gm);
@$stats_apg=($stats_ast/$stats_gm);
@$stats_spg=($stats_stl/$stats_gm);
@$stats_tpg=($stats_to/$stats_gm);
@$stats_bpg=($stats_blk/$stats_gm);
@$stats_fpg=($stats_pf/$stats_gm);
@$stats_ppg=($stats_pts/$stats_gm);


    $player_bird = stripslashes(check_html($playerinfo['bird'], "nohtml"));
    $player_cy = stripslashes(check_html($playerinfo['cy'], "nohtml"));
    $player_cy1 = stripslashes(check_html($playerinfo['cy1'], "nohtml"));
    $player_cy2 = stripslashes(check_html($playerinfo['cy2'], "nohtml"));
    $player_cy3 = stripslashes(check_html($playerinfo['cy3'], "nohtml"));
    $player_cy4 = stripslashes(check_html($playerinfo['cy4'], "nohtml"));
    $player_cy5 = stripslashes(check_html($playerinfo['cy5'], "nohtml"));
    $player_cy6 = stripslashes(check_html($playerinfo['cy6'], "nohtml"));

// CONTRACT FORMATTER

$can_renegotiate = 0;

if ($player_cy == 1) {
$contract_display = $player_cy1;
  if ($player_cy2 != 0) {
    $contract_display = $contract_display."/".$player_cy2;
    if ($player_cy3 != 0) {
      $contract_display = $contract_display."/".$player_cy3;
      if ($player_cy4 != 0) {
        $contract_display = $contract_display."/".$player_cy4;
        if ($player_cy5 != 0) {
          $contract_display = $contract_display."/".$player_cy5;
          if ($player_cy6 != 0) {
            $contract_display = $contract_display."/".$player_cy6;
          } else {
          }
        } else {
        }
      } else {
      }
    } else {
    }
  } else {
  $can_renegotiate = 1;
  }
} else if ($player_cy == 2) {
  $contract_display = $player_cy2;
  if ($player_cy3 != 0) {
    $contract_display = $contract_display."/".$player_cy3;
    if ($player_cy4 != 0) {
      $contract_display = $contract_display."/".$player_cy4;
      if ($player_cy5 != 0) {
        $contract_display = $contract_display."/".$player_cy5;
        if ($player_cy6 != 0) {
          $contract_display = $contract_display."/".$player_cy6;
        } else {
        }
      } else {
      }
    } else {
    }
  } else {
  $can_renegotiate = 1;
  }
} else if ($player_cy == 3) {
  $contract_display = $player_cy3;
  if ($player_cy4 != 0) {
    $contract_display = $contract_display."/".$player_cy4;
    if ($player_cy5 != 0) {
      $contract_display = $contract_display."/".$player_cy5;
      if ($player_cy6 != 0) {
        $contract_display = $contract_display."/".$player_cy6;
      } else {
      }
    } else {
    }
  } else {
  $can_renegotiate = 1;
  }
} else if ($player_cy == 4) {
  $contract_display = $player_cy4;
  if ($player_cy5 != 0) {
    $contract_display = $contract_display."/".$player_cy5;
    if ($player_cy6 != 0) {
      $contract_display = $contract_display."/".$player_cy6;
    } else {
    }
  } else {
  $can_renegotiate = 1;
  }
} else if ($player_cy == 5) {
  $contract_display = $player_cy5;
  if ($player_cy6 != 0) {
    $contract_display = $contract_display."/".$player_cy6;
  } else {
  $can_renegotiate = 1;
  }
} else if ($player_cy == 6) {
  $contract_display = $player_cy6;
  $can_renegotiate = 1;
} else {
$contract_display = "not under contract";
}

// END CONTRACT FORMATTER

// DISPLAY PAGE

    include("header.php");
    OpenTable();

    menu();

	echo "<table><tr><td valign=top>
<font class=\"title\">$player_pos $player_name ";

if ($player_nickname != NULL)
{
echo "- Nickname: \"$player_nickname\" ";
}

echo "(<a href=\"online/team.php?tid=$player_team_id\">$player_team_name</a>)</font><hr>

<table><tr><td valign=center><img src=\"images/player/$pid.jpg\"></td><td>";

// RENEGOTIATION BUTTON START

cookiedecode($user);

$sql2 = "SELECT * FROM ".$prefix."_users WHERE username='$cookie[1]'";
$result2 = $db->sql_query($sql2);
$num2 = $db->sql_numrows($result2);
$userinfo = $db->sql_fetchrow($result2);

$userteam = stripslashes(check_html($userinfo['user_ibl_team'], "nohtml"));
if ($player_exp == 4)
{
  if ($player_draft_round == 1)
  {
    if (2*$player_cy3 == $player_cy4 and $player_cy4 <> 0)
    {
		echo "<table align=right bgcolor=#ff0000><tr><td align=center>ROOKIE OPTION<br>USED; RENEGOTIATION<br>IMPOSSIBLE</td></tr></table>";
		$can_renegotiate = 0;
    }
  }
}

if ($can_renegotiate == 1) {
  if ($player_team_name == $userteam) {
echo "<table align=right bgcolor=#ff0000><tr><td align=center><a href=\"modules.php?name=Player&pa=negotiate&pid=$pid\">RENEGOTIATE<BR>CONTRACT</a></td></tr></table>";
  } else {
  }
} else {
}

// RENEGOTIATION BUTTON END

        echo "<font class=\"content\">Age: $player_age | Height: $player_ht_ft-$player_ht_in | Weight: $player_wt | College: <a href=\"http://college.iblhoops.net/rosters/roster$player_collegeid.htm\">$player_college</a>
                  <br><i>Drafted by the $player_drafted_by with the # $player_draft_pick pick of round $player_draft_round in the <a href=\"online/draft.php?year=$player_draft_year\">$player_draft_year Draft</a></i>
                  <br><center><table><tr><td align=center><b>2ga</b></td><td align=center><b>2gp</b></td><td align=center><b>fta</b></td><td align=center><b>ftp</b></td><td align=center><b>3ga</b></td><td align=center><b>3gp</b></td><td align=center><b>orb</b></td><td align=center><b>drb</b></td><td align=center><b>ast</b></td><td align=center><b>stl</b></td><td align=center><b>blk</b></td><td align=center><b>tvr</b></td><td align=center><b>oo</b></td><td align=center><b>do</b></td><td align=center><b>po</b></td><td align=center><b>to</b></td><td align=center><b>od</b></td><td align=center><b>dd</b></td><td align=center><b>pd</b></td><td align=center><b>td</b></td></tr>
                  <tr><td align=center>$player_rating_2ga</td><td align=center>$player_rating_2gp</td><td align=center>$player_rating_fta</td><td align=center>$player_rating_ftp</td><td align=center>$player_rating_3ga</td><td align=center>$player_rating_3gp</td><td align=center>$player_rating_orb</td><td align=center>$player_rating_drb</td><td align=center>$player_rating_ast</td><td align=center>$player_rating_stl</td><td align=center>$player_rating_blk</td><td align=center>$player_rating_tvr</td><td align=center>$player_rating_oo</td><td align=center>$player_rating_do</td><td align=center>$player_rating_po</td><td align=center>$player_rating_to</td><td align=center>$player_rating_od</td><td align=center>$player_rating_dd</td><td align=center>$player_rating_pd</td><td align=center>$player_rating_td</td></tr></table>
                  </center>

                  <b>BIRD YEARS:</b> $player_bird | <b>Remaining Contract:</b> $contract_display </td>";

if ($spec == NULL) {

// ==== PLAYER SEASON AND CAREER HIGHS ====

    $retired = stripslashes(check_html($playerinfo['retired'], "nohtml"));

    $sh_pts = stripslashes(check_html($playerinfo['sh_pts'], "nohtml"));
    $sh_reb = stripslashes(check_html($playerinfo['sh_reb'], "nohtml"));
    $sh_ast = stripslashes(check_html($playerinfo['sh_ast'], "nohtml"));
    $sh_stl = stripslashes(check_html($playerinfo['sh_stl'], "nohtml"));
    $sh_blk = stripslashes(check_html($playerinfo['sh_blk'], "nohtml"));
    $s_dd = stripslashes(check_html($playerinfo['s_dd'], "nohtml"));
    $s_td = stripslashes(check_html($playerinfo['s_td'], "nohtml"));

    $sp_pts = stripslashes(check_html($playerinfo['sp_pts'], "nohtml"));
    $sp_reb = stripslashes(check_html($playerinfo['sp_reb'], "nohtml"));
    $sp_ast = stripslashes(check_html($playerinfo['sp_ast'], "nohtml"));
    $sp_stl = stripslashes(check_html($playerinfo['sp_stl'], "nohtml"));
    $sp_blk = stripslashes(check_html($playerinfo['sp_blk'], "nohtml"));

    $ch_pts = stripslashes(check_html($playerinfo['ch_pts'], "nohtml"));
    $ch_reb = stripslashes(check_html($playerinfo['ch_reb'], "nohtml"));
    $ch_ast = stripslashes(check_html($playerinfo['ch_ast'], "nohtml"));
    $ch_stl = stripslashes(check_html($playerinfo['ch_stl'], "nohtml"));
    $ch_blk = stripslashes(check_html($playerinfo['ch_blk'], "nohtml"));
    $c_dd = stripslashes(check_html($playerinfo['c_dd'], "nohtml"));
    $c_td = stripslashes(check_html($playerinfo['c_td'], "nohtml"));

    $cp_pts = stripslashes(check_html($playerinfo['cp_pts'], "nohtml"));
    $cp_reb = stripslashes(check_html($playerinfo['cp_reb'], "nohtml"));
    $cp_ast = stripslashes(check_html($playerinfo['cp_ast'], "nohtml"));
    $cp_stl = stripslashes(check_html($playerinfo['cp_stl'], "nohtml"));
    $cp_blk = stripslashes(check_html($playerinfo['cp_blk'], "nohtml"));

echo "
<td rowspan=3 valign=top>

<table border=1 cellspacing=0 cellpadding=0>
<tr bgcolor=#0000cc><td align=center colspan=3><font color=#ffffff><b>PLAYER HIGHS</b></font></td></tr>
<tr bgcolor=#0000cc><td align=center colspan=3><font color=#ffffff><b>Regular-Season</b></font></td></tr>
<tr bgcolor=#0000cc><td></td><td><font color=#ffffff>Ssn</font></td><td><font color=#ffffff>Car</td></tr>
<tr><td><b>Points</b></td><td>$sh_pts</td><td>$ch_pts</td></tr>
<tr><td><b>Rebounds</b></td><td>$sh_reb</td><td>$ch_reb</td></tr>
<tr><td><b>Assists</b></td><td>$sh_ast</td><td>$ch_ast</td></tr>
<tr><td><b>Steals</b></td><td>$sh_stl</td><td>$ch_stl</td></tr>
<tr><td><b>Blocks</b></td><td>$sh_blk</td><td>$ch_blk</td></tr>
<tr><td>Double-Doubles</td><td>$s_dd</td><td>$c_dd</td></tr>
<tr><td>Triple-Doubles</td><td>$s_td</td><td>$c_td</td></tr>

<tr bgcolor=#0000cc><td align=center colspan=3><font color=#ffffff><b>Playoffs</b></font></td></tr>
<tr bgcolor=#0000cc><td></td><td><font color=#ffffff>Ssn</font></td><td><font color=#ffffff>Car</td></tr>
<tr><td><b>Points</b></td><td>$sp_pts</td><td>$cp_pts</td></tr>
<tr><td><b>Rebounds</b></td><td>$sp_reb</td><td>$cp_reb</td></tr>
<tr><td><b>Assists</b></td><td>$sp_ast</td><td>$cp_ast</td></tr>
<tr><td><b>Steals</b></td><td>$sp_stl</td><td>$cp_stl</td></tr>
<tr><td><b>Blocks</b></td><td>$sp_blk</td><td>$cp_blk</td></tr>
</table>
              </td></tr>";

// ==== END PLAYER SEASON AND CAREER HIGHS ====

}

echo "                  <tr><td colspan=2><hr></td></tr>";


echo "                  <tr><td colspan=2><center>PLAYER MENU</center><b><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">Player Overview</a> | <a href=\"modules.php?name=Player&pa=showpage&pid=$pid&spec=1\">Bio (Awards, News)</a> | <a href=\"modules.php?name=Player&pa=showpage&pid=$pid&spec=2\">One-on-one Results</a> | <a href=\"modules.php?name=Player&pa=showpage&pid=$pid&spec=10\">Last Sim Stats</a>
<br><a href=\"modules.php?name=Player&pa=showpage&pid=$pid&spec=3\">Regular-Season Totals</a> | <a href=\"modules.php?name=Player&pa=showpage&pid=$pid&spec=4\">Regular-Season Averages</a> | <a href=\"modules.php?name=Player&pa=showpage&pid=$pid&spec=5\">Playoff Totals</a> | <a href=\"modules.php?name=Player&pa=showpage&pid=$pid&spec=6\">Playoff Averages</a> | <a href=\"modules.php?name=Player&pa=showpage&pid=$pid&spec=7\">H.E.A.T. Totals</a> | <a href=\"modules.php?name=Player&pa=showpage&pid=$pid&spec=8\">H.E.A.T. Averages</a> | <a href=\"modules.php?name=Player&pa=showpage&pid=$pid&spec=9\">Ratings and Salary History</a> </td></tr>
                  <tr><td colspan=3><hr></td></tr>
";


// PLAYER OVERVIEW

if ($spec == NULL) {

// NOTE ALL-STAR WEEKEND APPEARANCES

echo "                  <tr><td colspan=3>";

echo "<table align=left cellspacing=1 cellpadding=0 border=1><th colspan=2><center>All-Star Activity</center></th></tr>
";

    $allstarquery = $db->sql_query("SELECT * FROM ".$prefix."_ibl_awards WHERE name='$player_name' AND Award LIKE '%Conference All-Star'");
    $allstarresult = $db->sql_query($allstarquery);
    $asg = $db->sql_numrows($allstarquery);
    echo "<tr><td><b>All Star Games:</b></td><td> $asg</td></tr>
";

    $allstarquery2 = $db->sql_query("SELECT * FROM ".$prefix."_ibl_awards WHERE name='$player_name' AND Award LIKE 'Three-Point Contest%'");
    $allstarresult2 = $db->sql_query($allstarquery2);
    $threepointcontests = $db->sql_numrows($allstarquery2);

    echo "<tr><td><b>Three-Point<br>Contests:</b></td><td> $threepointcontests</td></tr>
";


    $allstarquery3 = $db->sql_query("SELECT * FROM ".$prefix."_ibl_awards WHERE name='$player_name' AND Award LIKE 'Slam Dunk Competition%'");
    $allstarresult3 = $db->sql_query($allstarquery3);
    $dunkcontests = $db->sql_numrows($allstarquery3);

    echo "<tr><td><b>Slam Dunk<br>Competitions:</b></td><td> $dunkcontests</td></tr>
";

    $allstarquery4 = $db->sql_query("SELECT * FROM ".$prefix."_ibl_awards WHERE name='$player_name' AND Award LIKE 'Rookie-Sophomore Challenge'");
    $allstarresult4 = $db->sql_query($allstarquery4);
    $rooksoph = $db->sql_numrows($allstarquery4);

    echo "<tr><td><b>Rookie-Sophomore<br>Challenges:</b></td><td> $rooksoph</td></tr>";

echo "</table>
";

// END ALL-STAR WEEKEND ACTIVITY SCRIPT


echo "                  <center><table><tr align=center><td><b>Talent</b></td><td><b>Skill</b></td><td><b>Intangibles</b></td><td><b>Clutch</b></td><td><b>Consistency</b></td></tr>
                  <tr  align=center><td>$player_talent</td><td>$player_skill</td><td>$player_intangibles</td><td>$player_clutch</td><td>$player_consistency</td></tr></table>
                  <table><tr ><td><b>Loyalty</b></td><td><b>Play for Winner</b></td><td><b>Playing Time</b></td><td><b>Security</b></td><td><b>Coach</b></td><td><b>Tradition</b></td></tr>
                  <tr  align=center><td>$player_loyalty</td><td>$player_winner</td><td>$player_playingtime</td><td>$player_security</td><td>$player_coach</td><td>$player_tradition</td></tr></table></center>

                  </td></tr></table>
<table>";

}

echo "</table><table>";

// CHUNK STATS

if ($spec == 10) {


// GET PAST STATS

$car_gm=0;
$car_min=0;
$car_fgm=0;
$car_fga=0;
$car_ftm=0;
$car_fta=0;
$car_3gm=0;
$car_3ga=0;
$car_orb=0;
$car_drb=0;
$car_reb=0;
$car_ast=0;
$car_stl=0;
$car_blk=0;
$car_tvr=0;
$car_pf=0;
$car_pts=0;

echo "<table border=1 cellspacing=0><tr><td colspan=16><center><b><font class=\"content\">Sim Averages</font></b></center></td></tr>
      <tr><td>sim</td><td>team</td><td>g</td><td>min</td><td>FGP</td><td>FTP</td><td>3GP</td><td>orb</td><td>reb</td><td>ast</td><td>stl</td><td>to</td><td>blk</td><td>pf</td><td>pts</td><td>qa</td></tr>
";

    $result44 = $db->sql_query("SELECT * FROM ".$prefix."_iblplyr_chunk WHERE pid=$pid AND active = 1 ORDER BY chunk ASC");
    while ($row44 = $db->sql_fetchrow($result44)) {


    $hist_chunk = stripslashes(check_html($row44['chunk'], "nohtml"));
    $hist_team = stripslashes(check_html($row44['teamname'], "nohtml"));
    $hist_tid = stripslashes(check_html($row44['teamid'], "nohtml"));
    $hist_gm = stripslashes(check_html($row44['stats_gm'], "nohtml"));
    $hist_min = stripslashes(check_html($row44['stats_min'], "nohtml"));
    $hist_fgm = stripslashes(check_html($row44['stats_fgm'], "nohtml"));
    $hist_fga = stripslashes(check_html($row44['stats_fga'], "nohtml"));
@$hist_fgp=($hist_fgm/$hist_fga);
    $hist_ftm = stripslashes(check_html($row44['stats_ftm'], "nohtml"));
    $hist_fta = stripslashes(check_html($row44['stats_fta'], "nohtml"));
@$hist_ftp=($hist_ftm/$hist_fta);
    $hist_tgm = stripslashes(check_html($row44['stats_3gm'], "nohtml"));
    $hist_tga = stripslashes(check_html($row44['stats_3ga'], "nohtml"));
@$hist_tgp=($hist_tgm/$hist_tga);
    $hist_orb = stripslashes(check_html($row44['stats_orb'], "nohtml"));
    $hist_drb = stripslashes(check_html($row44['stats_drb'], "nohtml"));
@$hist_reb=$hist_orb+$hist_drb;
    $hist_ast = stripslashes(check_html($row44['stats_ast'], "nohtml"));
    $hist_stl = stripslashes(check_html($row44['stats_stl'], "nohtml"));
    $hist_tvr = stripslashes(check_html($row44['stats_to'], "nohtml"));
    $hist_blk = stripslashes(check_html($row44['stats_blk'], "nohtml"));
    $hist_pf = stripslashes(check_html($row44['stats_pf'], "nohtml"));
    $hist_qa = stripslashes(check_html($row44['qa'], "nohtml"));
    $hist_pts = $hist_fgm+$hist_fgm+$hist_ftm+$hist_tgm;

@$hist_mpg=($hist_min/$hist_gm);
@$hist_opg=($hist_orb/$hist_gm);
@$hist_rpg=($hist_reb/$hist_gm);
@$hist_apg=($hist_ast/$hist_gm);
@$hist_spg=($hist_stl/$hist_gm);
@$hist_tpg=($hist_tvr/$hist_gm);
@$hist_bpg=($hist_blk/$hist_gm);
@$hist_fpg=($hist_pf/$hist_gm);
@$hist_ppg=($hist_pts/$hist_gm);

if ($hist_year % 2)
{
echo "      <tr align=center bgcolor=$bgcolor>";
} else {
echo "      <tr align=center bgcolor=$bgcolor>";
}
echo "      <td><center>$hist_chunk</center></td><td><center>$hist_team</center></td><td><center>$hist_gm</center></td><td><center>";
printf('%01.1f', $hist_mpg);
echo "</center></td><td><center>";
printf('%01.3f', $hist_fgp);
echo "</center></td><td><center>";
printf('%01.3f', $hist_ftp);
echo "</center></td><td><center>";
printf('%01.3f', $hist_tgp);
echo "</center></td><td><center>";
printf('%01.1f', $hist_opg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_rpg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_apg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_spg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_tpg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_bpg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_fpg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_ppg);
echo "</center></td><td>$hist_qa</td></tr>";


$car_gm=$car_gm+$hist_gm;
$car_min=$car_min+$hist_min;
$car_fgm=$car_fgm+$hist_fgm;
$car_fga=$car_fga+$hist_fga;
$car_ftm=$car_ftm+$hist_ftm;
$car_fta=$car_fta+$hist_fta;
$car_3gm=$car_3gm+$hist_tgm;
$car_3ga=$car_3ga+$hist_tga;
$car_orb=$car_orb+$hist_orb;
$car_reb=$car_reb+$hist_reb;
$car_ast=$car_ast+$hist_ast;
$car_stl=$car_stl+$hist_stl;
$car_blk=$car_blk+$hist_blk;
$car_tvr=$car_tvr+$hist_tvr;
$car_pf=$car_pf+$hist_pf;
$car_pts=$car_pts+$hist_pts;
$car_qa=$car_qa+($hist_qa*$hist_gm);

}


@$car_fgp=$car_fgm/$car_fga;
@$car_ftp=$car_ftm/$car_fta;
@$car_tgp=$car_3gm/$car_3ga;
@$car_avgm=$car_min/$car_gm;
@$car_avgo=$car_orb/$car_gm;
@$car_avgr=$car_reb/$car_gm;
@$car_avga=$car_ast/$car_gm;
@$car_avgs=$car_stl/$car_gm;
@$car_avgb=$car_blk/$car_gm;
@$car_avgt=$car_tvr/$car_gm;
@$car_avgf=$car_pf/$car_gm;
@$car_avgp=$car_pts/$car_gm;
@$car_avqa=$car_qa/$car_gm;


echo "      <tr><td colspan=2><b>SeasonAverages</td><td><center><b>$car_gm</center></td><td><center><b>";
printf('%01.1f', $car_avgm);
echo "</center></td><td><center><b>";
printf('%01.3f', $car_fgp);
echo "</center></td><td><center><b>";
printf('%01.3f', $car_ftp);
echo "</center></td><td><center><b>";
printf('%01.3f', $car_tgp);
echo "</center></td><td><center><b>";
printf('%01.1f', $car_avgo);
echo "</center></td><td><center><b>";
printf('%01.1f', $car_avgr);
echo "</center></td><td><center><b>";
printf('%01.1f', $car_avga);
echo "</center></td><td><center><b>";
printf('%01.1f', $car_avgs);
echo "</center></td><td><center><b>";
printf('%01.1f', $car_avgt);
echo "</center></td><td><center><b>";
printf('%01.1f', $car_avgb);
echo "</center></td><td><center><b>";
printf('%01.1f', $car_avgf);
echo "</center></td><td><center><b>";
printf('%01.1f', $car_avgp);
echo "</center></td><td><center><b>";
printf('%01.1f', $car_avqa);
echo "</center></td></tr></table>";

}

// CAREER TOTALS

if ($spec == 3) {


// GET PAST STATS

$car_gm=0;
$car_min=0;
$car_fgm=0;
$car_fga=0;
$car_ftm=0;
$car_fta=0;
$car_3gm=0;
$car_3ga=0;
$car_orb=0;
$car_drb=0;
$car_reb=0;
$car_ast=0;
$car_stl=0;
$car_blk=0;
$car_tvr=0;
$car_pf=0;
$car_pts=0;

echo " <tr><td valign=top>
      <table border=1 cellspacing=0><tr><td colspan=15><center><font class=\"content\" color=\"#000000\"><b>Career Totals</b></font></center></td></tr>
      <tr><td>year</td><td>team</td><td>g</td><td>min</td><td>FGM-FGA</td><td>FTM-FTA</td><td>3GM-3GA</td><td>orb</td><td>reb</td><td>ast</td><td>stl</td><td>to</td><td>blk</td><td>pf</td><td>pts</td></tr>
";

    $result44 = $db->sql_query("SELECT * FROM ".$prefix."_iblhist WHERE pid=$pid ORDER BY year ASC");
    while ($row44 = $db->sql_fetchrow($result44)) {

    $hist_year = stripslashes(check_html($row44['year'], "nohtml"));
    $hist_team = stripslashes(check_html($row44['team'], "nohtml"));
    $hist_tid = stripslashes(check_html($row44['teamid'], "nohtml"));
    $hist_gm = stripslashes(check_html($row44['gm'], "nohtml"));
    $hist_min = stripslashes(check_html($row44['min'], "nohtml"));
    $hist_fgm = stripslashes(check_html($row44['fgm'], "nohtml"));
    $hist_fga = stripslashes(check_html($row44['fga'], "nohtml"));
@$hist_fgp=($hist_fgm/$hist_fga);
    $hist_ftm = stripslashes(check_html($row44['ftm'], "nohtml"));
    $hist_fta = stripslashes(check_html($row44['fta'], "nohtml"));
@$hist_ftp=($hist_ftm/$hist_fta);
    $hist_tgm = stripslashes(check_html($row44['3gm'], "nohtml"));
    $hist_tga = stripslashes(check_html($row44['3ga'], "nohtml"));
@$hist_tgp=($hist_tgm/$hist_tga);
    $hist_orb = stripslashes(check_html($row44['orb'], "nohtml"));
    $hist_reb = stripslashes(check_html($row44['reb'], "nohtml"));
    $hist_ast = stripslashes(check_html($row44['ast'], "nohtml"));
    $hist_stl = stripslashes(check_html($row44['stl'], "nohtml"));
    $hist_tvr = stripslashes(check_html($row44['tvr'], "nohtml"));
    $hist_blk = stripslashes(check_html($row44['blk'], "nohtml"));
    $hist_pf = stripslashes(check_html($row44['pf'], "nohtml"));
    $hist_pts = $hist_fgm+$hist_fgm+$hist_ftm+$hist_tgm;

if ($hist_year % 2)
{
echo "      <tr align=center>";
} else {
echo "      <tr align=center>";
}
echo "      <td><center>$hist_year</center></td><td><center><a href=\"online/team.php?tid=$hist_tid&yr=$hist_year\">$hist_team</a></center></td><td><center>$hist_gm</center></td><td><center>$hist_min</center></td><td><center>$hist_fgm-$hist_fga</center></td><td><center>$hist_ftm-$hist_fta</center></td><td><center>$hist_tgm-$hist_tga</center></td><td><center>$hist_orb</center></td><td><center>$hist_reb</center></td><td><center>$hist_ast</center></td><td><center>$hist_stl</center></td><td><center>$hist_tvr</center></td><td><center>$hist_blk</center></td><td><center>$hist_pf</center></td><td><center>$hist_pts</td></tr>
";

$car_gm=$car_gm+$hist_gm;
$car_min=$car_min+$hist_min;
$car_fgm=$car_fgm+$hist_fgm;
$car_fga=$car_fga+$hist_fga;
$car_ftm=$car_ftm+$hist_ftm;
$car_fta=$car_fta+$hist_fta;
$car_3gm=$car_3gm+$hist_tgm;
$car_3ga=$car_3ga+$hist_tga;
$car_orb=$car_orb+$hist_orb;
$car_reb=$car_reb+$hist_reb;
$car_ast=$car_ast+$hist_ast;
$car_stl=$car_stl+$hist_stl;
$car_blk=$car_blk+$hist_blk;
$car_tvr=$car_tvr+$hist_tvr;
$car_pf=$car_pf+$hist_pf;
$car_pts=$car_pts+$hist_pts;

}

// CURRENT YEAR TOTALS

if ($player_retired > 0)
{
} else {

if ($year % 2)
{
echo "      <tr align=center>";
} else {
echo "      <tr align=center>";
}
echo "      <td><center>$year</center></td><td><center>$player_team_name</center></td><td><center>$stats_gm</center></td><td><center>$stats_min</center></td><td><center>$stats_fgm-$stats_fga</center></td><td><center>$stats_ftm-$stats_fta</center></td><td><center>$stats_tgm-$stats_tga</center></td><td><center>$stats_orb</center></td><td><center>$stats_reb</center></td><td><center>$stats_ast</center></td><td><center>$stats_stl</center></td><td><center>$stats_to</center></td><td><center>$stats_blk</center></td><td><center>$stats_pf</center></td><td><center>$stats_pts</td></tr>
";
}
$car_gm=$car_gm+$stats_gm;
$car_min=$car_min+$stats_min;
$car_fgm=$car_fgm+$stats_fgm;
$car_fga=$car_fga+$stats_fga;
$car_ftm=$car_ftm+$stats_ftm;
$car_fta=$car_fta+$stats_fta;
$car_3gm=$car_3gm+$stats_tgm;
$car_3ga=$car_3ga+$stats_tga;
$car_orb=$car_orb+$stats_orb;
$car_reb=$car_reb+$stats_reb;
$car_ast=$car_ast+$stats_ast;
$car_stl=$car_stl+$stats_stl;
$car_blk=$car_blk+$stats_blk;
$car_tvr=$car_tvr+$stats_to;
$car_pf=$car_pf+$stats_pf;
$car_pts=$car_pts+$stats_pts;

@$car_fgp=$car_fgm/$car_fga;
@$car_ftp=$car_ftm/$car_fta;
@$car_tgp=$car_3gm/$car_3ga;
@$car_avgm=$car_min/$car_gm;
@$car_avgo=$car_orb/$car_gm;
@$car_avgr=$car_reb/$car_gm;
@$car_avga=$car_ast/$car_gm;
@$car_avgs=$car_stl/$car_gm;
@$car_avgb=$car_blk/$car_gm;
@$car_avgt=$car_tvr/$car_gm;
@$car_avgf=$car_pf/$car_gm;
@$car_avgp=$car_pts/$car_gm;


echo "      <tr><td colspan=2>Career Totals</td><td><center>$car_gm</center></td><td><center>$car_min</center></td><td><center>$car_fgm-$car_fga</center></td><td><center>$car_ftm-$car_fta</center></td><td><center>$car_3gm-$car_3ga</center></td><td><center>$car_orb</center></td><td><center>$car_reb</center></td><td><center>$car_ast</center></td><td><center>$car_stl</center></td><td><center>$car_tvr</center></td><td><center>$car_blk</center></td><td><center>$car_pf</center></td><td><center>$car_pts</td></tr></table>
";

}

// CAREER TOTALS

if ($spec == 4) {


// SWITCH FROM CAREER TOTALS TO CAREER AVERAGES

echo "<table border=1 cellspacing=0><tr><td colspan=15><center><b><font class=\"content\">Career Averages</font></b></center></td></tr>
      <tr><td>year</td><td>team</td><td>g</td><td>min</td><td>FGP</td><td>FTP</td><td>3GP</td><td>orb</td><td>reb</td><td>ast</td><td>stl</td><td>to</td><td>blk</td><td>pf</td><td>pts</td></tr>
";

$car_gm=0;
$car_min=0;
$car_fgm=0;
$car_fga=0;
$car_ftm=0;
$car_fta=0;
$car_3gm=0;
$car_3ga=0;
$car_orb=0;
$car_drb=0;
$car_reb=0;
$car_ast=0;
$car_stl=0;
$car_blk=0;
$car_tvr=0;
$car_pf=0;
$car_pts=0;


    $result44 = $db->sql_query("SELECT * FROM ".$prefix."_iblhist WHERE pid=$pid ORDER BY year ASC");
    while ($row44 = $db->sql_fetchrow($result44)) {

    $hist_year = stripslashes(check_html($row44['year'], "nohtml"));
    $hist_team = stripslashes(check_html($row44['team'], "nohtml"));
    $hist_gm = stripslashes(check_html($row44['gm'], "nohtml"));
    $hist_min = stripslashes(check_html($row44['min'], "nohtml"));
    $hist_fgm = stripslashes(check_html($row44['fgm'], "nohtml"));
    $hist_fga = stripslashes(check_html($row44['fga'], "nohtml"));
@$hist_fgp=($hist_fgm/$hist_fga);
    $hist_ftm = stripslashes(check_html($row44['ftm'], "nohtml"));
    $hist_fta = stripslashes(check_html($row44['fta'], "nohtml"));
@$hist_ftp=($hist_ftm/$hist_fta);
    $hist_tgm = stripslashes(check_html($row44['3gm'], "nohtml"));
    $hist_tga = stripslashes(check_html($row44['3ga'], "nohtml"));
@$hist_tgp=($hist_tgm/$hist_tga);
    $hist_orb = stripslashes(check_html($row44['orb'], "nohtml"));
    $hist_reb = stripslashes(check_html($row44['reb'], "nohtml"));
    $hist_ast = stripslashes(check_html($row44['ast'], "nohtml"));
    $hist_stl = stripslashes(check_html($row44['stl'], "nohtml"));
    $hist_tvr = stripslashes(check_html($row44['tvr'], "nohtml"));
    $hist_blk = stripslashes(check_html($row44['blk'], "nohtml"));
    $hist_pf = stripslashes(check_html($row44['pf'], "nohtml"));
    $hist_pts = $hist_fgm+$hist_fgm+$hist_ftm+$hist_tgm;

@$hist_mpg=($hist_min/$hist_gm);
@$hist_opg=($hist_orb/$hist_gm);
@$hist_rpg=($hist_reb/$hist_gm);
@$hist_apg=($hist_ast/$hist_gm);
@$hist_spg=($hist_stl/$hist_gm);
@$hist_tpg=($hist_tvr/$hist_gm);
@$hist_bpg=($hist_blk/$hist_gm);
@$hist_fpg=($hist_pf/$hist_gm);
@$hist_ppg=($hist_pts/$hist_gm);

$car_gm=$car_gm+$hist_gm;
$car_min=$car_min+$hist_min;
$car_fgm=$car_fgm+$hist_fgm;
$car_fga=$car_fga+$hist_fga;
$car_ftm=$car_ftm+$hist_ftm;
$car_fta=$car_fta+$hist_fta;
$car_3gm=$car_3gm+$hist_tgm;
$car_3ga=$car_3ga+$hist_tga;
$car_orb=$car_orb+$hist_orb;
$car_reb=$car_reb+$hist_reb;
$car_ast=$car_ast+$hist_ast;
$car_stl=$car_stl+$hist_stl;
$car_blk=$car_blk+$hist_blk;
$car_tvr=$car_tvr+$hist_tvr;
$car_pf=$car_pf+$hist_pf;
$car_pts=$car_pts+$hist_pts;

if ($hist_year % 2)
{
echo "      <tr align=center>";
} else {
echo "      <tr align=center>";
}
echo "      <td><center>$hist_year</center></td><td><center>$hist_team</center></td><td><center>$hist_gm</center></td><td><center>";
printf('%01.1f', $hist_mpg);
echo "</center></td><td><center>";
printf('%01.3f', $hist_fgp);
echo "</center></td><td><center>";
printf('%01.3f', $hist_ftp);
echo "</center></td><td><center>";
printf('%01.3f', $hist_tgp);
echo "</center></td><td><center>";
printf('%01.1f', $hist_opg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_rpg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_apg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_spg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_tpg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_bpg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_fpg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_ppg);
echo "</center></td></tr>";

}

// CURRENT YEAR AVERAGES

if ($retired == 1)
{
} else {

echo "      <tr align=center><td><center>$year</center></td><td><center>$player_team_name</center></td><td><center>$stats_gm</center></td><td><center>";
printf('%01.1f', $stats_mpg);
echo "</center></td><td><center>";
printf('%01.3f', $stats_fgp);
echo "</center></td><td><center>";
printf('%01.3f', $stats_ftp);
echo "</center></td><td><center>";
printf('%01.3f', $stats_tgp);
echo "</center></td><td><center>";
printf('%01.1f', $stats_opg);
echo "</center></td><td><center>";
printf('%01.1f', $stats_rpg);
echo "</center></td><td><center>";
printf('%01.1f', $stats_apg);
echo "</center></td><td><center>";
printf('%01.1f', $stats_spg);
echo "</center></td><td><center>";
printf('%01.1f', $stats_tpg);
echo "</center></td><td><center>";
printf('%01.1f', $stats_bpg);
echo "</center></td><td><center>";
printf('%01.1f', $stats_fpg);
echo "</center></td><td><center>";
printf('%01.1f', $stats_ppg);
echo "</center></td></tr>";
}

$car_gm=$car_gm+$stats_gm;
$car_min=$car_min+$stats_min;
$car_fgm=$car_fgm+$stats_fgm;
$car_fga=$car_fga+$stats_fga;
$car_ftm=$car_ftm+$stats_ftm;
$car_fta=$car_fta+$stats_fta;
$car_3gm=$car_3gm+$stats_tgm;
$car_3ga=$car_3ga+$stats_tga;
$car_orb=$car_orb+$stats_orb;
$car_reb=$car_reb+$stats_reb;
$car_ast=$car_ast+$stats_ast;
$car_stl=$car_stl+$stats_stl;
$car_blk=$car_blk+$stats_blk;
$car_tvr=$car_tvr+$stats_to;
$car_pf=$car_pf+$stats_pf;
$car_pts=$car_pts+$stats_pts;

@$car_fgp=$car_fgm/$car_fga;
@$car_ftp=$car_ftm/$car_fta;
@$car_tgp=$car_3gm/$car_3ga;
@$car_avgm=$car_min/$car_gm;
@$car_avgo=$car_orb/$car_gm;
@$car_avgr=$car_reb/$car_gm;
@$car_avga=$car_ast/$car_gm;
@$car_avgs=$car_stl/$car_gm;
@$car_avgb=$car_blk/$car_gm;
@$car_avgt=$car_tvr/$car_gm;
@$car_avgf=$car_pf/$car_gm;
@$car_avgp=$car_pts/$car_gm;

echo "      <tr><td colspan=2>Career Averages</td><td><center>$car_gm</center></td><td><center>";
printf('%01.1f', $car_avgm);
echo "</center></td><td><center>";
printf('%01.3f', $car_fgp);
echo "</center></td><td><center>";
printf('%01.3f', $car_ftp);
echo "</center></td><td><center>";
printf('%01.3f', $car_tgp);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgo);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgr);
echo "</center></td><td><center>";
printf('%01.1f', $car_avga);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgs);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgt);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgb);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgf);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgp);
echo "</center></td></tr></table>";

// END PAST STATS GRAB

}

// ================================================================

// CAREER PLAYOFF TOTALS

if ($spec == 5) {

// GET PAST PLAYOFF STATS

$car_gm=0;
$car_min=0;
$car_fgm=0;
$car_fga=0;
$car_ftm=0;
$car_fta=0;
$car_3gm=0;
$car_3ga=0;
$car_orb=0;
$car_drb=0;
$car_reb=0;
$car_ast=0;
$car_stl=0;
$car_blk=0;
$car_tvr=0;
$car_pf=0;
$car_pts=0;

echo " <table border=1 cellspacing=0><tr><td colspan=15><center><font class=\"content\" color=\"#000000\"><b>Playoff Career Totals</b></font></center></td></tr>
      <tr><td>year</td><td>team</td><td>g</td><td>min</td><td>FGM-FGA</td><td>FTM-FTA</td><td>3GM-3GA</td><td>orb</td><td>reb</td><td>ast</td><td>stl</td><td>to</td><td>blk</td><td>pf</td><td>pts</td></tr>
";

    $resultplayoff4 = $db->sql_query("SELECT * FROM ".$prefix."_ibl_playoff_stats WHERE name='$player_name' ORDER BY year ASC");
    while ($rowplayoff4 = $db->sql_fetchrow($resultplayoff4)) {

    $hist_year = stripslashes(check_html($rowplayoff4['year'], "nohtml"));
    $hist_team = stripslashes(check_html($rowplayoff4['team'], "nohtml"));
    $hist_gm = stripslashes(check_html($rowplayoff4['games'], "nohtml"));
    $hist_min = stripslashes(check_html($rowplayoff4['minutes'], "nohtml"));
    $hist_fgm = stripslashes(check_html($rowplayoff4['fgm'], "nohtml"));
    $hist_fga = stripslashes(check_html($rowplayoff4['fga'], "nohtml"));
@$hist_fgp=($hist_fgm/$hist_fga);
    $hist_ftm = stripslashes(check_html($rowplayoff4['ftm'], "nohtml"));
    $hist_fta = stripslashes(check_html($rowplayoff4['fta'], "nohtml"));
@$hist_ftp=($hist_ftm/$hist_fta);
    $hist_tgm = stripslashes(check_html($rowplayoff4['tgm'], "nohtml"));
    $hist_tga = stripslashes(check_html($rowplayoff4['tga'], "nohtml"));
@$hist_tgp=($hist_tgm/$hist_tga);
    $hist_orb = stripslashes(check_html($rowplayoff4['orb'], "nohtml"));
    $hist_reb = stripslashes(check_html($rowplayoff4['reb'], "nohtml"));
    $hist_ast = stripslashes(check_html($rowplayoff4['ast'], "nohtml"));
    $hist_stl = stripslashes(check_html($rowplayoff4['stl'], "nohtml"));
    $hist_tvr = stripslashes(check_html($rowplayoff4['tvr'], "nohtml"));
    $hist_blk = stripslashes(check_html($rowplayoff4['blk'], "nohtml"));
    $hist_pf = stripslashes(check_html($rowplayoff4['pf'], "nohtml"));
    $hist_pts = $hist_fgm+$hist_fgm+$hist_ftm+$hist_tgm;

echo "      <td><center>$hist_year</center></td><td><center>$hist_team</center></td><td><center>$hist_gm</center></td><td><center>$hist_min</center></td><td><center>$hist_fgm-$hist_fga</center></td><td><center>$hist_ftm-$hist_fta</center></td><td><center>$hist_tgm-$hist_tga</center></td><td><center>$hist_orb</center></td><td><center>$hist_reb</center></td><td><center>$hist_ast</center></td><td><center>$hist_stl</center></td><td><center>$hist_tvr</center></td><td><center>$hist_blk</center></td><td><center>$hist_pf</center></td><td><center>$hist_pts</td></tr>
";

$car_gm=$car_gm+$hist_gm;
$car_min=$car_min+$hist_min;
$car_fgm=$car_fgm+$hist_fgm;
$car_fga=$car_fga+$hist_fga;
$car_ftm=$car_ftm+$hist_ftm;
$car_fta=$car_fta+$hist_fta;
$car_3gm=$car_3gm+$hist_tgm;
$car_3ga=$car_3ga+$hist_tga;
$car_orb=$car_orb+$hist_orb;
$car_reb=$car_reb+$hist_reb;
$car_ast=$car_ast+$hist_ast;
$car_stl=$car_stl+$hist_stl;
$car_blk=$car_blk+$hist_blk;
$car_tvr=$car_tvr+$hist_tvr;
$car_pf=$car_pf+$hist_pf;
$car_pts=$car_pts+$hist_pts;

}

@$car_fgp=$car_fgm/$car_fga;
@$car_ftp=$car_ftm/$car_fta;
@$car_tgp=$car_3gm/$car_3ga;
@$car_avgm=$car_min/$car_gm;
@$car_avgo=$car_orb/$car_gm;
@$car_avgr=$car_reb/$car_gm;
@$car_avga=$car_ast/$car_gm;
@$car_avgs=$car_stl/$car_gm;
@$car_avgb=$car_blk/$car_gm;
@$car_avgt=$car_tvr/$car_gm;
@$car_avgf=$car_pf/$car_gm;
@$car_avgp=$car_pts/$car_gm;


echo "      <tr><td colspan=2>Playoff Totals</td><td><center>$car_gm</center></td><td><center>$car_min</center></td><td><center>$car_fgm-$car_fga</center></td><td><center>$car_ftm-$car_fta</center></td><td><center>$car_3gm-$car_3ga</center></td><td><center>$car_orb</center></td><td><center>$car_reb</center></td><td><center>$car_ast</center></td><td><center>$car_stl</center></td><td><center>$car_tvr</center></td><td><center>$car_blk</center></td><td><center>$car_pf</center></td><td><center>$car_pts</td></tr></table>
";

}

// CAREER PLAYOFF AVERAGES

if ($spec == 6) {

// SWITCH FROM CAREER TOTALS TO CAREER AVERAGES

echo "<table border=1 cellspacing=0><tr><td colspan=15><center><b><font class=\"content\">Playoffs Career Averages</font></b></center></td></tr>
      <tr><td>year</td><td>team</td><td>g</td><td>min</td><td>FGP</td><td>FTP</td><td>3GP</td><td>orb</td><td>reb</td><td>ast</td><td>stl</td><td>to</td><td>blk</td><td>pf</td><td>pts</td></tr>
";

$car_gm=0;
$car_min=0;
$car_fgm=0;
$car_fga=0;
$car_ftm=0;
$car_fta=0;
$car_3gm=0;
$car_3ga=0;
$car_orb=0;
$car_drb=0;
$car_reb=0;
$car_ast=0;
$car_stl=0;
$car_blk=0;
$car_tvr=0;
$car_pf=0;
$car_pts=0;

    $resultplayoff4 = $db->sql_query("SELECT * FROM ".$prefix."_ibl_playoff_stats WHERE name='$player_name' ORDER BY year ASC");
    while ($rowplayoff4 = $db->sql_fetchrow($resultplayoff4)) {

    $hist_year = stripslashes(check_html($rowplayoff4['year'], "nohtml"));
    $hist_team = stripslashes(check_html($rowplayoff4['team'], "nohtml"));
    $hist_gm = stripslashes(check_html($rowplayoff4['games'], "nohtml"));
    $hist_min = stripslashes(check_html($rowplayoff4['minutes'], "nohtml"));
    $hist_fgm = stripslashes(check_html($rowplayoff4['fgm'], "nohtml"));
    $hist_fga = stripslashes(check_html($rowplayoff4['fga'], "nohtml"));
@$hist_fgp=($hist_fgm/$hist_fga);
    $hist_ftm = stripslashes(check_html($rowplayoff4['ftm'], "nohtml"));
    $hist_fta = stripslashes(check_html($rowplayoff4['fta'], "nohtml"));
@$hist_ftp=($hist_ftm/$hist_fta);
    $hist_tgm = stripslashes(check_html($rowplayoff4['tgm'], "nohtml"));
    $hist_tga = stripslashes(check_html($rowplayoff4['tga'], "nohtml"));
@$hist_tgp=($hist_tgm/$hist_tga);
    $hist_orb = stripslashes(check_html($rowplayoff4['orb'], "nohtml"));
    $hist_reb = stripslashes(check_html($rowplayoff4['reb'], "nohtml"));
    $hist_ast = stripslashes(check_html($rowplayoff4['ast'], "nohtml"));
    $hist_stl = stripslashes(check_html($rowplayoff4['stl'], "nohtml"));
    $hist_tvr = stripslashes(check_html($rowplayoff4['tvr'], "nohtml"));
    $hist_blk = stripslashes(check_html($rowplayoff4['blk'], "nohtml"));
    $hist_pf = stripslashes(check_html($rowplayoff4['pf'], "nohtml"));
    $hist_pts = $hist_fgm+$hist_fgm+$hist_ftm+$hist_tgm;

@$hist_mpg=($hist_min/$hist_gm);
@$hist_opg=($hist_orb/$hist_gm);
@$hist_rpg=($hist_reb/$hist_gm);
@$hist_apg=($hist_ast/$hist_gm);
@$hist_spg=($hist_stl/$hist_gm);
@$hist_tpg=($hist_tvr/$hist_gm);
@$hist_bpg=($hist_blk/$hist_gm);
@$hist_fpg=($hist_pf/$hist_gm);
@$hist_ppg=($hist_pts/$hist_gm);

echo "      <td><center>$hist_year</center></td><td><center>$hist_team</center></td><td><center>$hist_gm</center></td><td><center>";
printf('%01.1f', $hist_mpg);
echo "</center></td><td><center>";
printf('%01.3f', $hist_fgp);
echo "</center></td><td><center>";
printf('%01.3f', $hist_ftp);
echo "</center></td><td><center>";
printf('%01.3f', $hist_tgp);
echo "</center></td><td><center>";
printf('%01.1f', $hist_opg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_rpg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_apg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_spg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_tpg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_bpg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_fpg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_ppg);
echo "</center></td></tr>";

$car_gm=$car_gm+$hist_gm;
$car_min=$car_min+$hist_min;
$car_fgm=$car_fgm+$hist_fgm;
$car_fga=$car_fga+$hist_fga;
$car_ftm=$car_ftm+$hist_ftm;
$car_fta=$car_fta+$hist_fta;
$car_3gm=$car_3gm+$hist_tgm;
$car_3ga=$car_3ga+$hist_tga;
$car_orb=$car_orb+$hist_orb;
$car_reb=$car_reb+$hist_reb;
$car_ast=$car_ast+$hist_ast;
$car_stl=$car_stl+$hist_stl;
$car_blk=$car_blk+$hist_blk;
$car_tvr=$car_tvr+$hist_tvr;
$car_pf=$car_pf+$hist_pf;
$car_pts=$car_pts+$hist_pts;

}

@$car_fgp=$car_fgm/$car_fga;
@$car_ftp=$car_ftm/$car_fta;
@$car_tgp=$car_3gm/$car_3ga;
@$car_avgm=$car_min/$car_gm;
@$car_avgo=$car_orb/$car_gm;
@$car_avgr=$car_reb/$car_gm;
@$car_avga=$car_ast/$car_gm;
@$car_avgs=$car_stl/$car_gm;
@$car_avgb=$car_blk/$car_gm;
@$car_avgt=$car_tvr/$car_gm;
@$car_avgf=$car_pf/$car_gm;
@$car_avgp=$car_pts/$car_gm;

echo "      <tr><td colspan=2>Playoff Averages</td><td><center>$car_gm</center></td><td><center>";
printf('%01.1f', $car_avgm);
echo "</center></td><td><center>";
printf('%01.3f', $car_fgp);
echo "</center></td><td><center>";
printf('%01.3f', $car_ftp);
echo "</center></td><td><center>";
printf('%01.3f', $car_tgp);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgo);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgr);
echo "</center></td><td><center>";
printf('%01.1f', $car_avga);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgs);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgt);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgb);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgf);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgp);
echo "</center></td></tr></table>";

// END PAST PLAYOFF STATS GRAB

}

// ================================================================

// CAREER H.E.A.T. TOTALS

if ($spec == 7) {

// GET PAST H.E.A.T. STATS

$car_gm=0;
$car_min=0;
$car_fgm=0;
$car_fga=0;
$car_ftm=0;
$car_fta=0;
$car_3gm=0;
$car_3ga=0;
$car_orb=0;
$car_drb=0;
$car_reb=0;
$car_ast=0;
$car_stl=0;
$car_blk=0;
$car_tvr=0;
$car_pf=0;
$car_pts=0;

echo " <table border=1 cellspacing=0><tr><td colspan=15><center><font class=\"content\" color=\"#000000\"><b>H.E.A.T. Career Totals</b></font></center></td></tr>
      <tr><td>year</td><td>team</td><td>g</td><td>min</td><td>FGM-FGA</td><td>FTM-FTA</td><td>3GM-3GA</td><td>orb</td><td>reb</td><td>ast</td><td>stl</td><td>to</td><td>blk</td><td>pf</td><td>pts</td></tr>
";

    $resultplayoff4 = $db->sql_query("SELECT * FROM ".$prefix."_ibl_heat_stats WHERE name='$player_name' ORDER BY year ASC");
    while ($rowplayoff4 = $db->sql_fetchrow($resultplayoff4)) {

    $hist_year = stripslashes(check_html($rowplayoff4['year'], "nohtml"));
    $hist_team = stripslashes(check_html($rowplayoff4['team'], "nohtml"));
    $hist_gm = stripslashes(check_html($rowplayoff4['games'], "nohtml"));
    $hist_min = stripslashes(check_html($rowplayoff4['minutes'], "nohtml"));
    $hist_fgm = stripslashes(check_html($rowplayoff4['fgm'], "nohtml"));
    $hist_fga = stripslashes(check_html($rowplayoff4['fga'], "nohtml"));
@$hist_fgp=($hist_fgm/$hist_fga);
    $hist_ftm = stripslashes(check_html($rowplayoff4['ftm'], "nohtml"));
    $hist_fta = stripslashes(check_html($rowplayoff4['fta'], "nohtml"));
@$hist_ftp=($hist_ftm/$hist_fta);
    $hist_tgm = stripslashes(check_html($rowplayoff4['tgm'], "nohtml"));
    $hist_tga = stripslashes(check_html($rowplayoff4['tga'], "nohtml"));
@$hist_tgp=($hist_tgm/$hist_tga);
    $hist_orb = stripslashes(check_html($rowplayoff4['orb'], "nohtml"));
    $hist_reb = stripslashes(check_html($rowplayoff4['reb'], "nohtml"));
    $hist_ast = stripslashes(check_html($rowplayoff4['ast'], "nohtml"));
    $hist_stl = stripslashes(check_html($rowplayoff4['stl'], "nohtml"));
    $hist_tvr = stripslashes(check_html($rowplayoff4['tvr'], "nohtml"));
    $hist_blk = stripslashes(check_html($rowplayoff4['blk'], "nohtml"));
    $hist_pf = stripslashes(check_html($rowplayoff4['pf'], "nohtml"));
    $hist_pts = $hist_fgm+$hist_fgm+$hist_ftm+$hist_tgm;

echo "      <td><center>$hist_year</center></td><td><center>$hist_team</center></td><td><center>$hist_gm</center></td><td><center>$hist_min</center></td><td><center>$hist_fgm-$hist_fga</center></td><td><center>$hist_ftm-$hist_fta</center></td><td><center>$hist_tgm-$hist_tga</center></td><td><center>$hist_orb</center></td><td><center>$hist_reb</center></td><td><center>$hist_ast</center></td><td><center>$hist_stl</center></td><td><center>$hist_tvr</center></td><td><center>$hist_blk</center></td><td><center>$hist_pf</center></td><td><center>$hist_pts</td></tr>
";

$car_gm=$car_gm+$hist_gm;
$car_min=$car_min+$hist_min;
$car_fgm=$car_fgm+$hist_fgm;
$car_fga=$car_fga+$hist_fga;
$car_ftm=$car_ftm+$hist_ftm;
$car_fta=$car_fta+$hist_fta;
$car_3gm=$car_3gm+$hist_tgm;
$car_3ga=$car_3ga+$hist_tga;
$car_orb=$car_orb+$hist_orb;
$car_reb=$car_reb+$hist_reb;
$car_ast=$car_ast+$hist_ast;
$car_stl=$car_stl+$hist_stl;
$car_blk=$car_blk+$hist_blk;
$car_tvr=$car_tvr+$hist_tvr;
$car_pf=$car_pf+$hist_pf;
$car_pts=$car_pts+$hist_pts;

}

@$car_fgp=$car_fgm/$car_fga;
@$car_ftp=$car_ftm/$car_fta;
@$car_tgp=$car_3gm/$car_3ga;
@$car_avgm=$car_min/$car_gm;
@$car_avgo=$car_orb/$car_gm;
@$car_avgr=$car_reb/$car_gm;
@$car_avga=$car_ast/$car_gm;
@$car_avgs=$car_stl/$car_gm;
@$car_avgb=$car_blk/$car_gm;
@$car_avgt=$car_tvr/$car_gm;
@$car_avgf=$car_pf/$car_gm;
@$car_avgp=$car_pts/$car_gm;


echo "      <tr><td colspan=2>H.E.A.T. Totals</td><td><center>$car_gm</center></td><td><center>$car_min</center></td><td><center>$car_fgm-$car_fga</center></td><td><center>$car_ftm-$car_fta</center></td><td><center>$car_3gm-$car_3ga</center></td><td><center>$car_orb</center></td><td><center>$car_reb</center></td><td><center>$car_ast</center></td><td><center>$car_stl</center></td><td><center>$car_tvr</center></td><td><center>$car_blk</center></td><td><center>$car_pf</center></td><td><center>$car_pts</td></tr></table>
";

}

// CAREER H.E.A.T. AVERAGES

if ($spec == 8) {

// SWITCH FROM CAREER TOTALS TO CAREER AVERAGES

echo "<table border=1 cellspacing=0><tr><td colspan=15><center><b><font class=\"content\">H.E.A.T. Career Averages</font></b></center></td></tr>
      <tr><td>year</td><td>team</td><td>g</td><td>min</td><td>FGP</td><td>FTP</td><td>3GP</td><td>orb</td><td>reb</td><td>ast</td><td>stl</td><td>to</td><td>blk</td><td>pf</td><td>pts</td></tr>
";

$car_gm=0;
$car_min=0;
$car_fgm=0;
$car_fga=0;
$car_ftm=0;
$car_fta=0;
$car_3gm=0;
$car_3ga=0;
$car_orb=0;
$car_drb=0;
$car_reb=0;
$car_ast=0;
$car_stl=0;
$car_blk=0;
$car_tvr=0;
$car_pf=0;
$car_pts=0;

    $resultplayoff4 = $db->sql_query("SELECT * FROM ".$prefix."_ibl_heat_stats WHERE name='$player_name' ORDER BY year ASC");
    while ($rowplayoff4 = $db->sql_fetchrow($resultplayoff4)) {

    $hist_year = stripslashes(check_html($rowplayoff4['year'], "nohtml"));
    $hist_team = stripslashes(check_html($rowplayoff4['team'], "nohtml"));
    $hist_gm = stripslashes(check_html($rowplayoff4['games'], "nohtml"));
    $hist_min = stripslashes(check_html($rowplayoff4['minutes'], "nohtml"));
    $hist_fgm = stripslashes(check_html($rowplayoff4['fgm'], "nohtml"));
    $hist_fga = stripslashes(check_html($rowplayoff4['fga'], "nohtml"));
@$hist_fgp=($hist_fgm/$hist_fga);
    $hist_ftm = stripslashes(check_html($rowplayoff4['ftm'], "nohtml"));
    $hist_fta = stripslashes(check_html($rowplayoff4['fta'], "nohtml"));
@$hist_ftp=($hist_ftm/$hist_fta);
    $hist_tgm = stripslashes(check_html($rowplayoff4['tgm'], "nohtml"));
    $hist_tga = stripslashes(check_html($rowplayoff4['tga'], "nohtml"));
@$hist_tgp=($hist_tgm/$hist_tga);
    $hist_orb = stripslashes(check_html($rowplayoff4['orb'], "nohtml"));
    $hist_reb = stripslashes(check_html($rowplayoff4['reb'], "nohtml"));
    $hist_ast = stripslashes(check_html($rowplayoff4['ast'], "nohtml"));
    $hist_stl = stripslashes(check_html($rowplayoff4['stl'], "nohtml"));
    $hist_tvr = stripslashes(check_html($rowplayoff4['tvr'], "nohtml"));
    $hist_blk = stripslashes(check_html($rowplayoff4['blk'], "nohtml"));
    $hist_pf = stripslashes(check_html($rowplayoff4['pf'], "nohtml"));
    $hist_pts = $hist_fgm+$hist_fgm+$hist_ftm+$hist_tgm;

@$hist_mpg=($hist_min/$hist_gm);
@$hist_opg=($hist_orb/$hist_gm);
@$hist_rpg=($hist_reb/$hist_gm);
@$hist_apg=($hist_ast/$hist_gm);
@$hist_spg=($hist_stl/$hist_gm);
@$hist_tpg=($hist_tvr/$hist_gm);
@$hist_bpg=($hist_blk/$hist_gm);
@$hist_fpg=($hist_pf/$hist_gm);
@$hist_ppg=($hist_pts/$hist_gm);

echo "      <td><center>$hist_year</center></td><td><center>$hist_team</center></td><td><center>$hist_gm</center></td><td><center>";
printf('%01.1f', $hist_mpg);
echo "</center></td><td><center>";
printf('%01.3f', $hist_fgp);
echo "</center></td><td><center>";
printf('%01.3f', $hist_ftp);
echo "</center></td><td><center>";
printf('%01.3f', $hist_tgp);
echo "</center></td><td><center>";
printf('%01.1f', $hist_opg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_rpg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_apg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_spg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_tpg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_bpg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_fpg);
echo "</center></td><td><center>";
printf('%01.1f', $hist_ppg);
echo "</center></td></tr>";

$car_gm=$car_gm+$hist_gm;
$car_min=$car_min+$hist_min;
$car_fgm=$car_fgm+$hist_fgm;
$car_fga=$car_fga+$hist_fga;
$car_ftm=$car_ftm+$hist_ftm;
$car_fta=$car_fta+$hist_fta;
$car_3gm=$car_3gm+$hist_tgm;
$car_3ga=$car_3ga+$hist_tga;
$car_orb=$car_orb+$hist_orb;
$car_reb=$car_reb+$hist_reb;
$car_ast=$car_ast+$hist_ast;
$car_stl=$car_stl+$hist_stl;
$car_blk=$car_blk+$hist_blk;
$car_tvr=$car_tvr+$hist_tvr;
$car_pf=$car_pf+$hist_pf;
$car_pts=$car_pts+$hist_pts;

}

@$car_fgp=$car_fgm/$car_fga;
@$car_ftp=$car_ftm/$car_fta;
@$car_tgp=$car_3gm/$car_3ga;
@$car_avgm=$car_min/$car_gm;
@$car_avgo=$car_orb/$car_gm;
@$car_avgr=$car_reb/$car_gm;
@$car_avga=$car_ast/$car_gm;
@$car_avgs=$car_stl/$car_gm;
@$car_avgb=$car_blk/$car_gm;
@$car_avgt=$car_tvr/$car_gm;
@$car_avgf=$car_pf/$car_gm;
@$car_avgp=$car_pts/$car_gm;

echo "      <tr><td colspan=2>H.E.A.T. Averages</td><td><center>$car_gm</center></td><td><center>";
printf('%01.1f', $car_avgm);
echo "</center></td><td><center>";
printf('%01.3f', $car_fgp);
echo "</center></td><td><center>";
printf('%01.3f', $car_ftp);
echo "</center></td><td><center>";
printf('%01.3f', $car_tgp);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgo);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgr);
echo "</center></td><td><center>";
printf('%01.1f', $car_avga);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgs);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgt);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgb);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgf);
echo "</center></td><td><center>";
printf('%01.1f', $car_avgp);
echo "</center></td></tr></table>";

// END PAST H.E.A.T. STATS GRAB

// ==========================================

}

// CAREER TOTALS

if ($spec == 10) {

// BEGIN WORLD CHAMPIONSHIPS DISPLAY

$cw_gm=0;
$cw_min=0;
$cw_fgm=0;
$cw_fga=0;
$cw_ftm=0;
$cw_fta=0;
$cw_tgm=0;
$cw_tga=0;
$cw_orb=0;
$cw_reb=0;
$cw_ast=0;
$cw_stl=0;
$cw_tvr=0;
$cw_blk=0;
$cw_pf=0;
$cw_pts=0;

$worlds_header_done_yet=0;
$world_color=0;

    $result45 = $db->sql_query("SELECT * FROM ".$prefix."_iblworlds WHERE name='$player_name' ORDER BY year ASC");
    while ($row45 = $db->sql_fetchrow($result45)) {

if ($worlds_header_done_yet == 0)
{
echo "      <table border=1 cellspacing=0><tr><td colspan=14><center><b><font  class=\"content\">World Championships Totals</font></b></center></td></tr>
      <tr><td>year</td><td>g</td><td>min</td><td>FGM - FGA</td><td>FTM - FTA</td><td>3GM - 3GA</td><td>orb</td><td>reb</td><td>ast</td><td>stl</td><td>to</td><td>blk</td><td>pf</td><td>pts</td></tr>
";
$worlds_header_done_yet=1;
}

    $world_year = stripslashes(check_html($row45['year'], "nohtml"));
    $world_gm = stripslashes(check_html($row45['games'], "nohtml"));
    $world_min = stripslashes(check_html($row45['minutes'], "nohtml"));
    $world_fgm = stripslashes(check_html($row45['fgm'], "nohtml"));
    $world_fga = stripslashes(check_html($row45['fga'], "nohtml"));
@$world_fgp=($world_fgm/$world_fga);
    $world_ftm = stripslashes(check_html($row45['ftm'], "nohtml"));
    $world_fta = stripslashes(check_html($row45['fta'], "nohtml"));
@$world_ftp=($world_ftm/$world_fta);
    $world_tgm = stripslashes(check_html($row45['tgm'], "nohtml"));
    $world_tga = stripslashes(check_html($row45['tga'], "nohtml"));
@$world_tgp=($world_tgm/$world_tga);
    $world_orb = stripslashes(check_html($row45['orb'], "nohtml"));
    $world_reb = stripslashes(check_html($row45['reb'], "nohtml"));
    $world_ast = stripslashes(check_html($row45['ast'], "nohtml"));
    $world_stl = stripslashes(check_html($row45['stl'], "nohtml"));
    $world_tvr = stripslashes(check_html($row45['tvr'], "nohtml"));
    $world_blk = stripslashes(check_html($row45['blk'], "nohtml"));
    $world_pf = stripslashes(check_html($row45['pf'], "nohtml"));
$world_pts=$world_fgm+$world_fgm+$world_ftm+$world_tgm;

if ($world_color == 1)
{
echo "      <tr align=center>";
$world_color=0;
} else {
echo "      <tr align=center>";
$world_color=1;
}
echo "      <td><center>$world_year</center></td><td><center>$world_gm</center></td><td><center>$world_min</center></td><td><center>$world_fgm - $world_fga</center></td><td><center>$world_ftm - $world_fta</center></td><td><center>$world_tgm - $world_tga</center></td><td><center>$world_orb</center></td><td><center>$world_reb</center></td><td><center>$world_ast</center></td><td><center>$world_stl</center></td><td><center>$world_tvr</center></td><td><center>$world_blk</center></td><td><center>$world_pf</center></td><td><center>$world_pts</td></tr>
";

$cw_gm=$cw_gm+$world_gm;
$cw_min=$cw_min+$world_min;
$cw_fgm=$cw_fgm+$world_fgm;
$cw_fga=$cw_fga+$world_fga;
$cw_ftm=$cw_ftm+$world_ftm;
$cw_fta=$cw_fta+$world_fta;
$cw_tgm=$cw_tgm+$world_tgm;
$cw_tga=$cw_tga+$world_tga;
$cw_orb=$cw_orb+$world_orb;
$cw_reb=$cw_reb+$world_reb;
$cw_ast=$cw_ast+$world_ast;
$cw_stl=$cw_stl+$world_stl;
$cw_tvr=$cw_tvr+$world_tvr;
$cw_blk=$cw_blk+$world_blk;
$cw_pf=$cw_pf+$world_pf;
$cw_pts=$cw_pts+$world_pts;

}

if ($worlds_header_done_yet == 1)
{
echo "      <tr ><td>Career Totals</td><td><center>$cw_gm</center></td><td><center>$cw_min</center></td><td><center>$cw_fgm - $cw_fga</center></td><td><center>$cw_ftm - $cw_fta</center></td><td><center>$cw_tgm - $cw_tga</center></td><td><center>$cw_orb</center></td><td><center>$cw_reb</center></td><td><center>$cw_ast</center></td><td><center>$cw_stl</center></td><td><center>$cw_tvr</center></td><td><center>$cw_blk</center></td><td><center>$cw_pf</center></td><td><center>$cw_pts</td></tr></table>
";
}

// END WORLD CHAMPIONSHIPS DISPLAY

}

// PLAYER RATINGS

if ($spec == 9) {

// PLAYER RATINGS BY YEAR

$rowcolor=0;

echo "      <table border=1 cellspacing=0><tr><td colspan=24><center><b><font  class=\"content\">(Past) Career Ratings by Year</font></b></center></td></tr>
      <tr><td>year</td><td>2ga</td><td>2gp</td><td>fta</td><td>ftp</td><td>3ga</td><td>3gp</td><td>orb</td><td>drb</td><td>ast</td><td>stl</td><td>tvr</td><td>blk</td><td>oo</td><td>do</td><td>po</td><td>to</td><td>od</td><td>dd</td><td>pd</td><td>td</td><td>Off</td><td>Def</td><td>Salary</td></tr>";


$totalsalary=0;

    $result44 = $db->sql_query("SELECT * FROM ".$prefix."_iblhist WHERE pid=$pid ORDER BY year ASC");
    while ($row44 = $db->sql_fetchrow($result44)) {

    $r_year = stripslashes(check_html($row44['year'], "nohtml"));
    $r_2ga = stripslashes(check_html($row44['r_2ga'], "nohtml"));
    $r_2gp = stripslashes(check_html($row44['r_2gp'], "nohtml"));
    $r_fta = stripslashes(check_html($row44['r_fta'], "nohtml"));
    $r_ftp = stripslashes(check_html($row44['r_ftp'], "nohtml"));
    $r_3ga = stripslashes(check_html($row44['r_3ga'], "nohtml"));
    $r_3gp = stripslashes(check_html($row44['r_3gp'], "nohtml"));
    $r_orb = stripslashes(check_html($row44['r_orb'], "nohtml"));
    $r_drb = stripslashes(check_html($row44['r_drb'], "nohtml"));
    $r_ast = stripslashes(check_html($row44['r_ast'], "nohtml"));
    $r_stl = stripslashes(check_html($row44['r_stl'], "nohtml"));
    $r_tvr = stripslashes(check_html($row44['r_tvr'], "nohtml"));
    $r_blk = stripslashes(check_html($row44['r_blk'], "nohtml"));
    $r_oo = stripslashes(check_html($row44['r_oo'], "nohtml"));
    $r_do = stripslashes(check_html($row44['r_do'], "nohtml"));
    $r_po = stripslashes(check_html($row44['r_po'], "nohtml"));
    $r_to = stripslashes(check_html($row44['r_to'], "nohtml"));
    $r_od = stripslashes(check_html($row44['r_od'], "nohtml"));
    $r_dd = stripslashes(check_html($row44['r_dd'], "nohtml"));
    $r_pd = stripslashes(check_html($row44['r_pd'], "nohtml"));
    $r_td = stripslashes(check_html($row44['r_td'], "nohtml"));
    $salary = stripslashes(check_html($row44['salary'], "nohtml"));
    $r_Off=$r_oo+$r_do+$r_po+$r_to;
    $r_Def=$r_od+$r_dd+$r_pd+$r_td;

$totalsalary=$totalsalary+$salary;

if ($rowcolor == 0)
{
echo "      <tr align=center>";
$rowcolor=1;
} else {
echo "      <tr align=center>";
$rowcolor=0;
}
echo "      <td><center>$r_year</center></td><td><center>$r_2ga</center></td><td><center>$r_2gp</center></td><td><center>$r_fta</center></td><td><center>$r_ftp</center></td><td><center>$r_3ga</center></td><td><center>$r_3gp</center></td><td><center>$r_orb</center></td><td><center>$r_drb</center></td><td><center>$r_ast</center></td><td><center>$r_stl</center></td><td><center>$r_tvr</center></td><td><center>$r_blk</center></td><td><center>$r_oo</center></td><td><center>$r_do</center></td><td><center>$r_po</center></td><td><center>$r_to</center></td><td><center>$r_od</center></td><td><center>$r_dd</center></td><td><center>$r_pd</center></td><td><center>$r_td</center></td><td><center>$r_Off</center></td><td><center>$r_Def</center></td><td><center>$salary</center></td></tr>
";

}

$totalsalary=$totalsalary/100;

echo "<tr><td colspan=24><center><b>Total Career Salary Earned:</b> $totalsalary million dollars</td></tr>
";

// END PLAYER RATINGS SECTION

}

// START AWARDS SCRIPT

if ($spec == 1) {

// START AWARDS SCRIPT

    $awardsquery = $db->sql_query("SELECT * FROM ".$prefix."_ibl_awards WHERE name='$player_name' ORDER BY year ASC");
    $awardsresult = $db->sql_query($awardsquery);
    $awardswon = $db->sql_numrows($awardsquery);

echo "<table border=1 cellspacing=0 cellpadding=0 valign=top><tr><td bgcolor=#0000cc align=center><b><font color=#ffffff>AWARDS</font></b></td></tr>";

    while ($awardsrow = $db->sql_fetchrow($awardsquery)) {

    $award_year = stripslashes(check_html($awardsrow['year'], "nohtml"));
    $award_type = stripslashes(check_html($awardsrow['Award'], "nohtml"));

    echo "<tr><td>$award_year $award_type</td></tr>
";

    }


// END AWARDS SCRIPT


// START NEWS ARTICLE PICKUP

echo "<tr><td bgcolor=#0000cc align=center><b><font color=#ffffff>ARTICLES MENTIONING THIS PLAYER</font></b></td></tr>
<tr><td>";

$urlwanted=str_replace(" ", "%20", $player_name);

$arttext=readfile("online/articles.php?player=$urlwanted");

echo "$arttext";

// END NEWS ARTICLE PICKUP

}

if ($spec == 2) {

// OPEN ONE-ON-ONE RESULTS

echo "<tr><td bgcolor=#0000cc align=center><b><font color=#ffffff>ONE-ON-ONE RESULTS</font></b></td></tr>
<tr><td>";

$oneononeurlwanted=str_replace(" ", "%20", $player_name);

echo (readfile("online/1on1results.php?player=$oneononeurlwanted"));

// END ONE-ON-ONE RESULTS

}

echo "</td></tr>";


echo "</table>
";

echo "
</table>";

    CloseTable();
    include("footer.php");

// END OF DISPLAY PAGE

}













// ==================== CONTRACT NEGOTIATION FUNCTION ============================


function negotiate($pid) {
    global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;
    $pid = intval($pid);
    $playerinfo = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_iblplyr WHERE pid='$pid'"));
    $player_name = stripslashes(check_html($playerinfo['name'], "nohtml"));
    $player_pos = stripslashes(check_html($playerinfo['altpos'], "nohtml"));
    $player_team_name = stripslashes(check_html($playerinfo['teamname'], "nohtml"));

    $player_loyalty = stripslashes(check_html($playerinfo['loyalty'], "nohtml"));
    $player_winner = stripslashes(check_html($playerinfo['winner'], "nohtml"));
    $player_playingtime = stripslashes(check_html($playerinfo['playingTime'], "nohtml"));
    $player_security = stripslashes(check_html($playerinfo['security'], "nohtml"));
    $player_coach = stripslashes(check_html($playerinfo['coach'], "nohtml"));
    $player_tradition = stripslashes(check_html($playerinfo['tradition'], "nohtml"));

    include("header.php");
    OpenTable();

    menu();

// RENEGOTIATION STUFF

cookiedecode($user);

$sql2 = "SELECT * FROM ".$prefix."_users WHERE username='$cookie[1]'";
$result2 = $db->sql_query($sql2);
$num2 = $db->sql_numrows($result2);
$userinfo = $db->sql_fetchrow($result2);

$userteam = stripslashes(check_html($userinfo['user_ibl_team'], "nohtml"));

    $player_exp = stripslashes(check_html($playerinfo['exp'], "nohtml"));
    $player_bird = stripslashes(check_html($playerinfo['bird'], "nohtml"));
    $player_cy = stripslashes(check_html($playerinfo['cy'], "nohtml"));
    $player_cy1 = stripslashes(check_html($playerinfo['cy1'], "nohtml"));
    $player_cy2 = stripslashes(check_html($playerinfo['cy2'], "nohtml"));
    $player_cy3 = stripslashes(check_html($playerinfo['cy3'], "nohtml"));
    $player_cy4 = stripslashes(check_html($playerinfo['cy4'], "nohtml"));
    $player_cy5 = stripslashes(check_html($playerinfo['cy5'], "nohtml"));
    $player_cy6 = stripslashes(check_html($playerinfo['cy6'], "nohtml"));

// CONTRACT CHECKER

$can_renegotiate = 0;

if ($player_cy == 1) {
  if ($player_cy2 != 0) {
  } else {
  $can_renegotiate = 1;
  }
} else if ($player_cy == 2) {
  if ($player_cy3 != 0) {
  } else {
  $can_renegotiate = 1;
  }
} else if ($player_cy == 3) {
  if ($player_cy4 != 0) {
  } else {
  $can_renegotiate = 1;
  }
} else if ($player_cy == 4) {
  if ($player_cy5 != 0) {
  } else {
  $can_renegotiate = 1;
  }
} else if ($player_cy == 5) {
  if ($player_cy6 != 0) {
  } else {
  $can_renegotiate = 1;
  }
} else if ($player_cy == 6) {
  $can_renegotiate = 1;
} else {
$contract_display = "not under contract";
}

// END CONTRACT CHECKER

echo "<b>$player_pos $player_name</b> - Contract Demands:
<br>";

if ($player_pos == 'PG')
{
 $adjpos="'G', 'PG'";
}
if ($player_pos == 'G')
{
 $adjpos="'PG', 'SG', 'G'";
}
if ($player_pos == 'SG')
{
 $adjpos="'G', 'GF', 'SG'";
}
if ($player_pos == 'GF')
{
 $adjpos="'SG', 'SF', 'GF'";
}
if ($player_pos == 'SF')
{
 $adjpos="'GF', 'F', 'SF'";
}
if ($player_pos == 'F')
{
 $adjpos="'SF', 'PF', 'F'";
}
if ($player_pos == 'PF')
{
 $adjpos="'F', 'FC', 'PF'";
}
if ($player_pos == 'FC')
{
 $adjpos="'PF', 'FC', 'C'";
}
if ($player_pos == 'C')
{
 $adjpos="'FC', 'C'";
}


if ($can_renegotiate == 1) {
  if ($player_team_name == $userteam) {

    $demands = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_ibl_demands WHERE name='$player_name'"));
    $dem1 = stripslashes(check_html($demands['dem1'], "nohtml"));
    $dem2 = stripslashes(check_html($demands['dem2'], "nohtml"));
    $dem3 = stripslashes(check_html($demands['dem3'], "nohtml"));
    $dem4 = stripslashes(check_html($demands['dem4'], "nohtml"));
    $dem5 = stripslashes(check_html($demands['dem5'], "nohtml"));
// The sixth year is zero for extensions only; remove the line below and uncomment the regular line in the FA module.
    $dem6 = 0;
//    $dem6 = stripslashes(check_html($demands['dem6'], "nohtml"));

    $teamfactors = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_ibl_team_info WHERE team_name='$userteam'"));
    $tf_wins = stripslashes(check_html($teamfactors['Contract_Wins'], "nohtml"));
    $tf_loss = stripslashes(check_html($teamfactors['Contract_Losses'], "nohtml"));
    $tf_trdw = stripslashes(check_html($teamfactors['Contract_AvgW'], "nohtml"));
    $tf_trdl = stripslashes(check_html($teamfactors['Contract_AvgL'], "nohtml"));
    $tf_coach = stripslashes(check_html($teamfactors['Contract_Coach'], "nohtml"));

    $millionsatposition = $db->sql_query("SELECT * FROM ".$prefix."_iblplyr WHERE teamname='$userteam' AND pos IN ($adjpos) AND name!='$player_name'");
// LOOP TO GET MILLIONS COMMITTED AT POSITION

$tf_millions = 0;

    while ($millionscounter = $db->sql_fetchrow($millionsatposition)) {

    $millionscy = stripslashes(check_html($millionscounter['cy'], "nohtml"));
    $millionscy1 = stripslashes(check_html($millionscounter['cy1'], "nohtml"));
    $millionscy2 = stripslashes(check_html($millionscounter['cy2'], "nohtml"));
    $millionscy3 = stripslashes(check_html($millionscounter['cy3'], "nohtml"));
    $millionscy4 = stripslashes(check_html($millionscounter['cy4'], "nohtml"));
    $millionscy5 = stripslashes(check_html($millionscounter['cy5'], "nohtml"));
    $millionscy6 = stripslashes(check_html($millionscounter['cy6'], "nohtml"));


// FOR AN EXTENSION, LOOK AT SALARY COMMITTED NEXT YEAR, NOT THIS YEAR

if ($millionscy == 1) {
$tf_millions = $tf_millions+$millionscy2;
}
if ($millionscy == 2) {
$tf_millions = $tf_millions+$millionscy3;
}
if ($millionscy == 3) {
$tf_millions = $tf_millions+$millionscy4;
}
if ($millionscy == 4) {
$tf_millions = $tf_millions+$millionscy5;
}
if ($millionscy == 5) {
$tf_millions = $tf_millions+$millionscy6;
}


}

// END LOOP

$demyrs = 6;
if ($dem6 == 0){
$demyrs = 5;
if ($dem5 == 0){
$demyrs = 4;
if ($dem4 == 0){
$demyrs = 3;
if ($dem3 == 0){
$demyrs = 2;
if ($dem2 == 0){
$demyrs = 1;
}
}
}
}
}

//$modfactor1 = (0.0005*($tf_wins-$tf_losses)*($player_winner-1));
$modfactor1 = (0.000153*($tf_wins-$tf_loss)*($player_winner-1));
//$modfactor2 = (0.00125*($tf_trdw-$tf_trdl)*($player_tradition-1));
$modfactor2 = (0.000153*($tf_trdw-$tf_trdl)*($player_tradition-1));
//$modfactor3 = (.01*($tf_coach)*($player_coach=1));
$modfactor3 = (0.0025*($tf_coach)*($player_coach-1));
//$modfactor4 = (.025*($player_loyalty-1));
$modfactor4 = (.025*($player_loyalty-1));
//$modfactor5 = (.01*($demyrs-1)-0.025)*($player_security-1);
//$modfactor5 = (.01*$demyrs-0.025)*($player_security-1);
//$modfactor6 = -(.0035*$tf_millions/100-0.028)*($player_playingtime-1);
$modfactor6 = -(.0025*$tf_millions/100-0.025)*($player_playingtime-1);

$modifier = 1+$modfactor1+$modfactor2+$modfactor3+$modfactor4+$modfactor6-0.10;
//echo "Wins: $tf_wins<br>Loses: $tf_loss<br>Tradition Wins: $tf_trdw<br> Tradition Loses: $tf_trdl<br>Coach: $tf_coach<br>Loyalty: $player_loyalty<br>Play Time: $tf_millions<br>ModW: $modfactor1<br>ModT: $modfactor2<br>ModC: $modfactor3<br>ModL: $modfactor4<br>ModS: $modfactor5<br>ModP: $modfactor6<br>Mod: $modifier<br>Demand 1: $dem1<br>Demand 2: $dem2<br>Demand 3: $dem3<br>Demand 4: $dem4<br>Demand 5: $dem5<br>";

$dem1 = round($dem1/$modifier);
$dem2 = round($dem2/$modifier);
$dem3 = round($dem3/$modifier);
$dem4 = round($dem4/$modifier);
$dem5 = round($dem5/$modifier);
// The sixth year is zero for extensions only; remove the line below and uncomment the regular line in the FA module.
$dem6=0;
// $dem6 = round($dem6/$modifier);

$demtot = round(($dem1+$dem2+$dem3+$dem4+$dem5+$dem6)/100,2);
$demavg = ($dem1+$dem2+$dem3+$dem4+$dem5+$dem6)/$demyrs;

$demand_display = $dem1;
if ($dem2 == 0) {
} else {
$demand_display = $demand_display."</td><td>".$dem2;
}
if ($dem3 == 0) {
} else {
$demand_display = $demand_display."</td><td>".$dem3;
}
if ($dem4 == 0) {
} else {
$demand_display = $demand_display."</td><td>".$dem4;
}
if ($dem5 == 0) {
} else {
$demand_display = $demand_display."</td><td>".$dem5;
}
if ($dem6 == 0) {
} else {
$demand_display = $demand_display."</td><td>".$dem6;
}

// LOOP TO GET HARD CAP SPACE

$capnumber = 7000;

    $capquery = "SELECT * FROM ".$prefix."_iblplyr WHERE teamname='$userteam' AND retired = '0'";
    $capresult = $db->sql_query($capquery);
    while($capdecrementer = $db->sql_fetchrow($capresult)) {

    $capcy = stripslashes(check_html($capdecrementer['cy'], "nohtml"));
    $capcy1 = stripslashes(check_html($capdecrementer['cy1'], "nohtml"));
    $capcy2 = stripslashes(check_html($capdecrementer['cy2'], "nohtml"));
    $capcy3 = stripslashes(check_html($capdecrementer['cy3'], "nohtml"));
    $capcy4 = stripslashes(check_html($capdecrementer['cy4'], "nohtml"));
    $capcy5 = stripslashes(check_html($capdecrementer['cy5'], "nohtml"));
    $capcy6 = stripslashes(check_html($capdecrementer['cy6'], "nohtml"));

// LOOK AT SALARY COMMITTED NEXT YEAR, NOT THIS YEAR

if ($capcy == 1) {
$capnumber = $capnumber-$capcy2;
}
if ($capcy == 2) {
$capnumber = $capnumber-$capcy3;
}
if ($capcy == 3) {
$capnumber = $capnumber-$capcy4;
}
if ($capcy == 4) {
$capnumber = $capnumber-$capcy5;
}
if ($capcy == 5) {
$capnumber = $capnumber-$capcy6;
}

}

// END LOOP

// ======= BEGIN HTML OUTPUT FOR RENEGOTIATION FUNCTION ======

    $fa_activecheck = $db->sql_fetchrow($db->sql_query("SELECT * FROM ".$prefix."_modules WHERE title='Free_Agency'"));
    $fa_active = stripslashes(check_html($fa_activecheck['active'], "nohtml"));

if ($fa_active == 1)
{
echo "Sorry, the contract extension feature is not available during free agency.";
} else {

echo "<form name=\"ExtensionOffer\" method=\"post\" action=\"extension.php\">";

$maxyr1=1063;
if ($player_exp > 6) {
$maxyr1=1275;
}
if ($player_exp > 9) {
$maxyr1=1361;
}


echo "Here are my contract demands (note that if you offer the max and I refuse, it means I am opting for Free Agency at the end of the season):
<table cellspacing=0 border=1><tr><td>My demands are:</td><td>$demand_display</td></tr>
<tr><td>Please enter your offer in this row:</td><td>
";

if ($dem1 < $maxyr1) {
echo "<INPUT TYPE=\"text\" NAME=\"offeryear1\" SIZE=\"4\" VALUE=\"$dem1\"></td><td>
<INPUT TYPE=\"text\" NAME=\"offeryear2\" SIZE=\"4\" VALUE=\"$dem2\"></td><td>
<INPUT TYPE=\"text\" NAME=\"offeryear3\" SIZE=\"4\" VALUE=\"$dem3\"></td><td>
<INPUT TYPE=\"text\" NAME=\"offeryear4\" SIZE=\"4\" VALUE=\"$dem4\"></td><td>
<INPUT TYPE=\"text\" NAME=\"offeryear5\" SIZE=\"4\" VALUE=\"$dem5\"></td></tr>
";
} else {

$maxraise=round($maxyr1*0.1);
$maxyr2=0;
$maxyr3=0;
$maxyr4=0;
$maxyr5=0;

  if ($dem2 != 0) {
    $maxyr2=$maxyr1+$maxraise;
  }
  if ($dem3 != 0) {
    $maxyr3=$maxyr2+$maxraise;
  }
  if ($dem4 != 0) {
    $maxyr4=$maxyr3+$maxraise;
  }
  if ($dem5 != 0) {
    $maxyr5=$maxyr4+$maxraise;
  }


echo "<INPUT TYPE=\"text\" NAME=\"offeryear1\" SIZE=\"4\" VALUE=\"$maxyr1\"></td><td>
<INPUT TYPE=\"text\" NAME=\"offeryear2\" SIZE=\"4\" VALUE=\"$maxyr2\"></td><td>
<INPUT TYPE=\"text\" NAME=\"offeryear3\" SIZE=\"4\" VALUE=\"$maxyr3\"></td><td>
<INPUT TYPE=\"text\" NAME=\"offeryear4\" SIZE=\"4\" VALUE=\"$maxyr4\"></td><td>
<INPUT TYPE=\"text\" NAME=\"offeryear5\" SIZE=\"4\" VALUE=\"$maxyr5\"></td></tr>
";
}


echo "<tr><td colspan=6><b>Notes/Reminders:</b> <ul>
<li>You have $capnumber in cap space available; the amount you offer in year 1 cannot exceed this.</li>
<li>Based on my years of service, the maximum amount you can offer me in year 1 is $maxyr1.</li>
<li>Enter \"0\" for years you do not want to offer a contract.</li>
<li>Contract extensions must be at least three years in length.</li>
<li>The amounts offered each year must equal or exceed the previous year.</li>
";

if ($player_bird > 2){
echo "<li>Because this player has Bird Rights, you may add no more than 12.5% of your the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than 75 between any two subsequent years.)</li>
";
} else {
echo "<li>Because this player does not have Bird Rights, you may add no more than 10% of your the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than 50 between any two subsequent years.)</li>
";
}
echo "<li>For reference, \"100\" entered in the fields above corresponds to 1 million dollars; the 55 million dollar soft cap thus means you have 5500 to play with. When re-signing your own players, you can go over the soft cap and up to the hard cap (7500).</li>
</ul></td></tr>
<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
<input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
<input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
<input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
<input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
<input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
<input type=\"hidden\" name=\"maxyr1\" value=\"$maxyr1\">
<input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
<input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
<input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
</table>

<input type=\"submit\" value=\"Offer Extension!\">
</form>

";
}

  } else {
echo "Sorry, this player is not on your team.";
  }
} else {
echo "Sorry, this player is not eligible for a contract extension at this time.";
}


// RENEGOTIATION STUFF END

    CloseTable();
    include("footer.php");
}


// ================================================================================
//
// One-On-One Code
//
// ================================================================================

function oneonone()
{

  global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;
  include("header.php");

cookiedecode($user);

$sql2 = "SELECT * FROM ".$prefix."_users WHERE username='$cookie[1]'";
$result2 = $db->sql_query($sql2);
$num2 = $db->sql_numrows($result2);
$userinfo = $db->sql_fetchrow($result2);

$ownerplaying = stripslashes(check_html($userinfo['username'], "nohtml"));


  OpenTable();

    menu();

    $player1 = $_POST['pid1'];
    $player2 = $_POST['pid2'];
    $gameid = $_POST['gameid'];

    echo "<center><table><tr><th>One-on-One Match</th></tr></table></center>
";

    // ===== SET FORM =====

    echo "<form name=\"OneOnOne\" method=\"post\" action=\"modules.php?name=Player&pa=oneonone\">
    Player One: <select name=\"pid1\">
    ";

    $query="SELECT * FROM nuke_iblplyr WHERE retired = '0' ORDER BY name ASC";
    $result=mysql_query($query);
    $num=mysql_numrows($result);

    $i=0;
    while ($i < $num)
    {
      $playername=mysql_result($result,$i,"name");
      $pida=mysql_result($result,$i,"pid");
      if ($pida==$player1)
        {
        echo "<option value=\"$pida\" SELECTED>$playername</option>
      ";
        } else {
        echo "<option value=\"$pida\">$playername</option>
      ";
        }
      $i=$i+1;
    }

    echo "</select> | Player Two: <select name=\"pid2\">
";
    $i=0;
    while ($i < $num)
    {
    $playername=mysql_result($result,$i,"name");
    $pida=mysql_result($result,$i,"pid");
    if ($pida==$player2)
      {
      echo "<option value=\"$pida\" SELECTED>$playername</option>
    ";
      } else {
      echo "<option value=\"$pida\">$playername</option>
    ";
      }
    $i=$i+1;
    }

echo "</select><input type=\"submit\" value=\"Begin One-on-One Match\"></form>

<form name=\"LookUpOldGame\" method=\"post\" action=\"modules.php?name=Player&pa=oneonone\">
Review Old Game (Input Game ID): <input type=\"text\" name=\"gameid\" size=\"11\"><input type=\"submit\" value=\"Review Old Game\">
</form>

<hr>
";

// ===== END FORM

// ===== PRINT THE RESULTS OF AN OLD GAME

if ($gameid == NULL)
{
// ===== IF VALUES ARE FOUND FOR P1 AND P2, AND THEY ARE DIFFERENT, RUN THE GAME

$runstop=0;
if ($player1 == NULL)
{
echo "Please select a Player from the Player 1 Category.<br>";
$runstop=1;
}
if ($player2 == NULL)
{
echo "Please select a Player from the Player 2 Category.<br>";
$runstop=1;
}
if ($player1 == $player2)
{
echo "Please do not select the same player for both Player 1 and Player 2.<br>";
$runstop=1;
}

if ($runstop == 0)
{
rungame($player1, $player2, $ownerplaying);
}

// ===== ABOVE DISPLAYS A NEW GAME; ELSE PRINT THE OLD GAME RESULTS

} else {
printgame ($gameid);
}

    CloseTable();
    include("footer.php");
}


// ==================================
//
// BELOW ARE THE FUNCTIONS USED TO RUN ONE-ON-ONE GAME
//
// ==================================

// ===== A RETURN VALUE OF 1 INDICATES THE ATTEMPT IS BLOCKED

function shotblock ($blockrating, $attemptrating)
  {
    $block=0;
    $makeblock1=$blockrating+rand(1,100)+rand(1,100);
    $makeblock2=$blockrating+rand(1,100)+rand(1,100);
    $makeblock3=$blockrating+rand(1,100)+rand(1,100);
    $avoidblock1=$attemptrating+rand(1,100)+rand(1,100);
    $avoidblock2=$attemptrating+rand(1,100)+rand(1,100);
    $avoidblock3=$attemptrating+rand(1,100)+rand(1,100);
    if ($makeblock1>$avoidblock1) {
    $block=$block+1;
    }
    if ($makeblock2>$avoidblock2) {
    $block=$block+1;
    }
    if ($makeblock3>$avoidblock3) {
    $block=$block+1;
    }
    if ($block == 3)
    {
    return 1;
    } else {
    return 0;
    }
  }

// ===== A RETURN VALUE OF 1 INDICATES THE BALL IS STOLEN

function stealcheck ($stealrating, $turnoverrating)
  {
    $steal=0;
    $makesteal1=$stealrating+rand(1,100)+rand(1,100);
    $makesteal2=$stealrating+rand(1,100)+rand(1,100);
    $makesteal3=$stealrating+rand(1,100)+rand(1,100);
    $avoidsteal1=$turnoverrating+rand(1,100)+rand(1,100);
    $avoidsteal2=$turnoverrating+rand(1,100)+rand(1,100);
    $avoidsteal3=$turnoverrating+rand(1,100)+rand(1,100);
    if ($makesteal1>$avoidsteal1) {
    $steal=$steal+1;
    }
    if ($makesteal2>$avoidsteal2) {
    $steal=$steal+1;
    }
    if ($makesteal3>$avoidsteal3) {
    $steal=$steal+1;
    }
    if ($steal == 2)
    {
    return 1;
    } else {
    return 0;
    }
  }

// ===== A RETURN VALUE OF 1 INDICATES A FOUL

function foulcheck ($foulrating, $drawfoulrating)
  {
    $foul=0;
    $drawfoul1=$drawfoulrating+rand(1,100)+rand(1,100);
    $drawfoul2=$drawfoulrating+rand(1,100)+rand(1,100);
    $drawfoul3=$drawfoulrating+rand(1,100)+rand(1,100);
    $drawfoul4=$drawfoulrating+rand(1,100)+rand(1,100);
    $drawfoul5=$drawfoulrating+rand(1,100)+rand(1,100);
    $avoidfoul1=$foulrating+rand(1,100)+rand(1,100);
    $avoidfoul2=$foulrating+rand(1,100)+rand(1,100);
    $avoidfoul3=$foulrating+rand(1,100)+rand(1,100);
    $avoidfoul4=$foulrating+rand(1,100)+rand(1,100);
    $avoidfoul5=$foulrating+rand(1,100)+rand(1,100);
    if ($drawfoul1>$avoidfoul1) {
    $foul=$foul+1;
    }
    if ($drawfoul2>$avoidfoul2) {
    $foul=$foul+1;
    }
    if ($drawfoul3>$avoidfoul3) {
    $foul=$foul+1;
    }
    if ($drawfoul4>$avoidfoul4) {
    $foul=$foul+1;
    }
    if ($drawfoul5>$avoidfoul5) {
    $foul=$foul+1;
    }
    if ($foul > 3)
    {
    return 1;
    } else {
    return 0;
    }
  }

// ===== A RETURN OF 1 INDICATES A MADE BASKET

function shootball ($basepercent, $offenserating, $defenserating)
  {
    $shotchance=$basepercent+$offenserating-$defenserating*2;
    $shotresult=rand(1,100);
    if ($shotresult > $shotchance)
     {
     return 0;
     } else {
     return 1;
     }

  }

// ===== A RETURN OF 0 INDICATES A DEFENSIVE BOARD, A RETURN OF 1 AN OFFENSIVE BOARD

function rebound ($offrebound, $defrebound)
  {
   $reboundmatrix=$offrebound+$defrebound+50;
   $reboundresult=rand(1,$reboundmatrix);
   if ($reboundresult > $offrebound)
    {
    return 0;
    } else {
    return 1;
    }
  }

// ===== THREE-POINTER IS 0, OUTSIDE TWO IS 1, DRIVE IS 2, POST IS 3

function selectshottype ($outside, $drive, $post, $twochance, $threechance)
  {
    $shotselection=$oustide+$drive+$post;
    $shottype=rand(0, $shotselection-1);
    if ($shottype < $outside) {
      $twoorthree=$twochance+$threechance;
      $picktwoorthree=rand(0,$twoorthree-1);
      if ($picktwoorthree > $twochance) {
        return 0;
        } else {
        return 1;
        }
    } elseif ($shottype < ($outside+$drive)) {
    return 2;
    } else {
    return 3;
    }
  }

// ===== RESULTS KEY =====
// 1 - FOUL
// 2 - STEAL
// 3 - BLOCKED THREE
// 4 - MISSED THREE
// 5 - MADE THREE
// 6 - BLOCKED OUTSIDE TWO
// 7 - MISSED OUTSIDE TWO
// 8 - MADE OUTSIDE TWO
// 9 - BLOCKED DRIVE TWO
// 10 - MISSED DRIVE TWO
// 11 - MADE DRIVE TWO
// 12 - BLOCKED POST TWO
// 13 - MISSED POST TWO
// 14 - MADE POST TWO
// ===== END RESULTS KEY =====

function runpossession ($off_player_2ga, $off_player_2gp, $off_player_fta, $off_player_3ga, $off_player_3gp, $off_player_tvr, $off_player_oo, $off_player_do, $off_player_po, $def_player_stl, $def_player_blk, $def_player_foul, $def_player_od, $def_player_dd, $def_player_pd )
{

  $fouldifficulty=5;

  if (foulcheck($def_player_foul, $off_player_fta))
   {
   return 1;
   }

  if (stealcheck($def_player_steal, $off_player_tvr))
  {
  return 2;
  }

  $shotroutine=selectshottype($off_player_oo, $off_player_do, $off_player_po, $off_player_2ga, $off_player_3ga);

  if ($shotroutine==0)
  {
    if (shotblock($def_player_blk, $off_player_3ga)) {
    return 3;
    }

    if (foulcheck($def_player_foul, $off_player_fta)) {
      if (shootball($off_player_3gp-$fouldifficulty, $off_player_oo, $def_player_od)) {
      return 5;
      } else {
      return 1;
      }
    } else {
      if (shootball ($off_player_3gp, $off_player_oo, $def_player_od)) {
      return 5;
      } else {
      return 4;
      }
    }

  } elseif ($shotroutine==1) {

    if (shotblock($def_player_blk, $off_player_2ga)) {
    return 6;
    }

    if (foulcheck($def_player_foul, $off_player_fta)) {
      if (shootball($off_player_2gp-$fouldifficulty, $off_player_oo, $def_player_od)) {
      return 8;
      } else {
      return 1;
      }
    } else {
      if (shootball ($off_player_2gp, $off_player_oo, $def_player_od)) {
      return 8;
      } else {
      return 7;
      }
    }

  } elseif ($shotroutine==2) {

    if (shotblock($def_player_blk, $off_player_2ga)) {
    return 9;
    }

    if (foulcheck($def_player_foul, $off_player_fta)) {
      if (shootball($off_player_2gp-$fouldifficulty, $off_player_do, $def_player_dd)) {
      return 11;
      } else {
      return 1;
      }
    } else {
      if (shootball ($off_player_2gp, $off_player_do, $def_player_dd)) {
      return 11;
      } else {
      return 10;
      }
    }

  } elseif ($shotroutine==3) {

    if (shotblock($def_player_blk, $off_player_2ga)) {
    return 12;
    }

    if (foulcheck($def_player_foul, $off_player_fta)) {
      if (shootball($off_player_2gp-$fouldifficulty, $off_player_po, $def_player_pd)) {
      return 14;
      } else {
      return 1;
      }
    } else {
      if (shootball ($off_player_2gp, $off_player_po, $def_player_pd)) {
      return 14;
      } else {
      return 13;
      }
    }

  }

}

// ===== FUN TEXTUAL DESCRIPTIONS =====

function threetext ()
  {
  $ThreeArray[] = "launches a three";
  $ThreeArray[] = "fires from downtown";
  $ThreeArray[] = "shoots from beyond the arc";
  $ThreeArray[] = "tosses up a trey";
  $ThreeArray[] = "attempts a trifecta";
  $ThreeArray[] = "guns up a three-pointer";
  $ThreeArray[] = "chucks a long-range bomb";
  $ThreeArray[] = "takes a shot from outside the arc";
  $descriptionnumber=rand(0,7);
  return $ThreeArray[$descriptionnumber];
  }

function outsidetwotext ()
  {
  $OutsideArray[] = "takes a perimeter shot";
  $OutsideArray[] = "from just inside the arc";
  $OutsideArray[] = "gets off a long two";
  $OutsideArray[] = "sets, fires, ";
  $OutsideArray[] = "throws up a long jump shot";
  $OutsideArray[] = "fires a shot from near the top of the key";
  $OutsideArray[] = "pulls up along the baseline";
  $OutsideArray[] = "elevates for a J";
  $descriptionnumber=rand(0,7);
  return $OutsideArray[$descriptionnumber];
  }

function drivetext ()
  {
  $DriveArray[] = "slashes into the lane";
  $DriveArray[] = "drives to the basket";
  $DriveArray[] = "lifts up a teardrop on the drive";
  $DriveArray[] = "gets free for a drive with a nifty crossover";
  $DriveArray[] = "fakes left and drives right";
  $DriveArray[] = "floats a hanging jumper on the move";
  $DriveArray[] = "squeezes off a leaping leaner";
  $DriveArray[] = "spins into the paint on a drive";
  $descriptionnumber=rand(0,7);
  return $DriveArray[$descriptionnumber];
  }

function posttext ()
  {
  $PostArray[] = "with a jump hook from the low block";
  $PostArray[] = "takes a sweeping skyhook as he powers into the lane";
  $PostArray[] = "backs down and takes a little turnaround jumper";
  $PostArray[] = "uses a drop-step to try a layup";
  $PostArray[] = "elevates inside for a dunk";
  $PostArray[] = "goes to the up-and-under move";
  $PostArray[] = "lofts up a soft fadeaway from the paint";
  $PostArray[] = "flips up a finger roll";
  $descriptionnumber=rand(0,7);
  return $PostArray[$descriptionnumber];
  }

function madeshottext ()
  {
  $MadeShotArray[] = "and connects!";
  $MadeShotArray[] = "and hits!";
  $MadeShotArray[] = "and scores!";
  $MadeShotArray[] = "and knocks it down!";
  $MadeShotArray[] = "and knocks down the shot!";
  $MadeShotArray[] = "that rattles around an in!";
  $MadeShotArray[] = "that hits nothing but net!";
  $MadeShotArray[] = "that tickles the twine as it goes in!";
  $MadeShotArray[] = "that swishes cleanly through the net!";
  $MadeShotArray[] = "and it drops through the bucket!";
  $MadeShotArray[] = "and gets it to drop!";
  $MadeShotArray[] = "and practically wills it home!";
  $MadeShotArray[] = "and hits the shot!";
  $MadeShotArray[] = "that bounces off the front of the rim, off the back of the rim, then drops!";
  $MadeShotArray[] = "that hangs on the lip of the rim, but drops!";
  $MadeShotArray[] = "that drops through the hoop!";
  $MadeShotArray[] = "and makes the basket!";
  $MadeShotArray[] = "and gets the bucket!";
  $descriptionnumber=rand(0,17);
  return $MadeShotArray[$descriptionnumber];
  }

function missedshottext ()
  {
  $MissedShotArray[] = "and misses.";
  $MissedShotArray[] = "and clanks it off the front of the iron.";
  $MissedShotArray[] = "but the shot is off-line.";
  $MissedShotArray[] = "and it's an airball!";
  $MissedShotArray[] = "but comes up empty.";
  $MissedShotArray[] = "and the shot is a bit long.";
  $MissedShotArray[] = "and it rattles around and out.";
  $MissedShotArray[] = "that hangs on the lip of the rim before falling out.";
  $MissedShotArray[] = "that caroms off the rim.";
  $MissedShotArray[] = "but can't connect.";
  $MissedShotArray[] = "and comes up dry.";
  $MissedShotArray[] = "but can't get it to fall.";
  $MissedShotArray[] = "and the shot comes up short.";
  $MissedShotArray[] = "but it's no good.";
  $MissedShotArray[] = "and bounces it off the glass and out.";
  $MissedShotArray[] = "that spins out.";
  $MissedShotArray[] = "and the ball just won't stay down.";
  $MissedShotArray[] = "and somehow the ball stays out.";
  $descriptionnumber=rand(0,17);
  return $MissedShotArray[$descriptionnumber];
  }

function blocktext ()
  {
  $BlockArray[] = "knocks it away.";
  $BlockArray[] = "deflects the shot.";
  $BlockArray[] = "swats it away.";
  $BlockArray[] = "gets a hand on the shot.";
  $BlockArray[] = "slaps the ball away.";
  $BlockArray[] = "tips the shot attempt away.";
  $BlockArray[] = "comes up with the block.";
  $BlockArray[] = "recovers in time to get a piece of the shot.";
  $descriptionnumber=rand(0,7);
  return $BlockArray[$descriptionnumber];
  }

function stealtext ()
  {
  $StealArray[] = "swipes the ball from";
  $StealArray[] = "gets a clean pick of";
  $StealArray[] = "grabs the ball right out of the hands of";
  $StealArray[] = "steals the ball from";
  $StealArray[] = "comes up with a steal from";
  $StealArray[] = "strips the ball away from";
  $StealArray[] = "pokes the ball away and gets the steal from";
  $StealArray[] = "pilfers the ball from";
  $descriptionnumber=rand(0,7);
  return $StealArray[$descriptionnumber];
  }

// ===== THIS IS THE MAIN FUNCTION THAT RUNS THE GAME =====

function rungame ($p1, $p2, $owner)
 {

$query1="SELECT * FROM nuke_iblplyr WHERE pid = $p1";
$result1=mysql_query($query1);

$p1_name=mysql_result($result1,0,"name");
$p1_oo=mysql_result($result1,0,"oo");
$p1_do=mysql_result($result1,0,"do");
$p1_po=mysql_result($result1,0,"po");
$p1_od=mysql_result($result1,0,"od");
$p1_dd=mysql_result($result1,0,"dd");
$p1_pd=mysql_result($result1,0,"pd");

$p1_2ga=mysql_result($result1,0,"r_fga");
$p1_2gp=mysql_result($result1,0,"r_fgp");
$p1_fta=mysql_result($result1,0,"r_fta");
$p1_3ga=mysql_result($result1,0,"r_tga");
$p1_3gp=mysql_result($result1,0,"r_tgp");
$p1_orb=mysql_result($result1,0,"r_orb");
$p1_drb=mysql_result($result1,0,"r_drb");
$p1_stl=mysql_result($result1,0,"r_stl");
$p1_tvr=mysql_result($result1,0,"r_to");
$p1_blk=mysql_result($result1,0,"r_blk");
$p1_foul=mysql_result($result1,0,"r_foul");

$query2="SELECT * FROM nuke_iblplyr WHERE pid = $p2";
$result2=mysql_query($query2);

$p2_name=mysql_result($result2,0,"name");
$p2_oo=mysql_result($result2,0,"oo");
$p2_do=mysql_result($result2,0,"do");
$p2_po=mysql_result($result2,0,"po");
$p2_od=mysql_result($result2,0,"od");
$p2_dd=mysql_result($result2,0,"dd");
$p2_pd=mysql_result($result2,0,"pd");
$p2_2ga=mysql_result($result2,0,"r_fga");
$p2_2gp=mysql_result($result2,0,"r_fgp");
$p2_3ga=mysql_result($result2,0,"r_tga");
$p2_3gp=mysql_result($result2,0,"r_tgp");
$p2_fta=mysql_result($result2,0,"r_fta");
$p2_orb=mysql_result($result2,0,"r_orb");
$p2_drb=mysql_result($result2,0,"r_drb");
$p2_stl=mysql_result($result2,0,"r_stl");
$p2_tvr=mysql_result($result2,0,"r_to");
$p2_blk=mysql_result($result2,0,"r_blk");
$p2_foul=mysql_result($result2,0,"r_foul");

$p1_stats_fgm=0;
$p1_stats_fga=0;
$p1_stats_3gm=0;
$p1_stats_3ga=0;
$p1_stats_orb=0;
$p1_stats_reb=0;
$p1_stats_stl=0;
$p1_stats_blk=0;
$p1_stats_to=0;
$p1_stats_foul=0;

$p2_stats_fgm=0;
$p2_stats_fga=0;
$p2_stats_3gm=0;
$p2_stats_3ga=0;
$p2_stats_orb=0;
$p2_stats_reb=0;
$p2_stats_stl=0;
$p2_stats_blk=0;
$p2_stats_to=0;
$p2_stats_foul=0;

// === COIN TOSS

$possession=1;
$coinflip=rand(1,2);
echo "The opening coin flip is ";
if ($coinflip==1) {
echo " heads, so $p1_name gets the ball to start.<br>
";
$possession=1;
} else {
echo " tails, so $p2_name gets the ball to start.<br>
";
$possession=2;
}

// ===== SET SCORES TO ZERO

$hiscore=0;
$score1=0;
$score2=0;

// ===== ONE-ON-ONE GAME START
$playbyplay=NULL;
while ($hiscore < 21)
 {
 if ($possession==1)
  {
  $possessionresult=runpossession($p1_2ga, $p1_2gp, $p1_fta, $p1_3ga, $p1_3gp, $p1_tvr, $p1_oo, $p1_do, $p1_po, $p2_stl, $p2_blk, $p2_foul, $p2_od, $p2_dd, $p2_pd);
  if ($possessionresult==1)
    {
    $playbyplay=$playbyplay."$p2_name fouls $p1_name.<br>";
    $p2_stats_foul=$p2_stats_foul+1;
    $looseball=0;
    $possession=1;
    } elseif ($possessionresult==2) {
    $playbyplay=$playbyplay."$p2_name ".stealtext()." $p1_name.<br>";
    $looseball=0;
    $possession=2;
    $p1_stats_to=$p1_stats_to+1;
    $p2_stats_stl=$p2_stats_stl+1;
    } elseif ($possessionresult==3) {
    $playbyplay=$playbyplay."$p1_name ".threetext()." but $p2_name ".blocktext()."<br>";
    $p1_stats_fga=$p1_stats_fga+1;
    $p1_stats_3ga=$p1_stats_3ga+1;
    $p2_stats_blk=$p2_stats_blk+1;
    $looseball=1;
    } elseif ($possessionresult==4) {
    $playbyplay=$playbyplay."$p1_name ".threetext()." ".missedshottext()."<br>";
    $p1_stats_fga=$p1_stats_fga+1;
    $p1_stats_3ga=$p1_stats_3ga+1;
    $looseball=1;
    } elseif ($possessionresult==5) {
    $playbyplay=$playbyplay."$p1_name ".threetext()." ".madeshottext()."<br>";
    $p1_stats_fga=$p1_stats_fga+1;
    $p1_stats_3ga=$p1_stats_3ga+1;
    $p1_stats_fgm=$p1_stats_fgm+1;
    $p1_stats_3gm=$p1_stats_3gm+1;
    $looseball=0;
    $possession=2;
    $score1=$score1+3;
    } elseif ($possessionresult==6) {
    $playbyplay=$playbyplay."$p1_name ".outsidetwotext()." but $p2_name ".blocktext()."<br>";
    $p1_stats_fga=$p1_stats_fga+1;
    $p2_stats_blk=$p2_stats_blk+1;
    $looseball=1;
    } elseif ($possessionresult==7) {
    $playbyplay=$playbyplay."$p1_name ".outsidetwotext()." ".missedshottext()."<br>";
    $p1_stats_fga=$p1_stats_fga+1;
    $looseball=1;
    } elseif ($possessionresult==8) {
    $playbyplay=$playbyplay."$p1_name ".outsidetwotext()." ".madeshottext()."<br>";
    $p1_stats_fga=$p1_stats_fga+1;
    $p1_stats_fgm=$p1_stats_fgm+1;
    $looseball=0;
    $possession=2;
    $score1=$score1+2;
    } elseif ($possessionresult==9) {
    $playbyplay=$playbyplay."$p1_name ".drivetext()." but $p2_name ".blocktext()."<br>";
    $p1_stats_fga=$p1_stats_fga+1;
    $p2_stats_blk=$p2_stats_blk+1;
    $looseball=1;
    } elseif ($possessionresult==10) {
    $playbyplay=$playbyplay."$p1_name ".drivetext()." ".missedshottext()."<br>";
    $p1_stats_fga=$p1_stats_fga+1;
    $looseball=1;
    } elseif ($possessionresult==11) {
    $playbyplay=$playbyplay."$p1_name ".drivetext()." ".madeshottext()."<br>";
    $p1_stats_fga=$p1_stats_fga+1;
    $p1_stats_fgm=$p1_stats_fgm+1;
    $looseball=0;
    $possession=2;
    $score1=$score1+2;
    } elseif ($possessionresult==12) {
    $playbyplay=$playbyplay."$p1_name ".posttext()." but $p2_name ".blocktext()."<br>";
    $p1_stats_fga=$p1_stats_fga+1;
    $p2_stats_blk=$p2_stats_blk+1;
    $looseball=1;
    } elseif ($possessionresult==13) {
    $playbyplay=$playbyplay."$p1_name ".posttext()." ".missedshottext()."<br>";
    $p1_stats_fga=$p1_stats_fga+1;
    $looseball=1;
    } elseif ($possessionresult==14) {
    $playbyplay=$playbyplay."$p1_name ".posttext()." ".madeshottext()."<br>";
    $p1_stats_fga=$p1_stats_fga+1;
    $p1_stats_fgm=$p1_stats_fgm+1;
    $looseball=0;
    $possession=2;
    $score1=$score1+2;
    }

  } else {

  $possessionresult=runpossession($p2_2ga, $p2_2gp, $p2_fta, $p2_3ga, $p2_3gp, $p2_tvr, $p2_oo, $p2_do, $p2_po, $p1_stl, $p1_blk, $p1_foul, $p1_od, $p1_dd, $p1_pd);
  if ($possessionresult==1)
    {
    $playbyplay=$playbyplay."$p1_name fouls $p2_name.<br>";
    $p1_stats_foul=$p1_stats_foul+1;
    $looseball=0;
    $possession=2;
    } elseif ($possessionresult==2) {
    $playbyplay=$playbyplay."$p1_name ".stealtext()." $p2_name.<br>";
    $looseball=0;
    $possession=1;
    $p2_stats_to=$p2_stats_to+1;
    $p1_stats_stl=$p1_stats_stl+1;
    } elseif ($possessionresult==3) {
    $playbyplay=$playbyplay."$p2_name ".threetext()." but $p1_name ".blocktext()."<br>";
    $p2_stats_fga=$p2_stats_fga+1;
    $p2_stats_3ga=$p2_stats_3ga+1;
    $p1_stats_blk=$p1_stats_blk+1;
    $looseball=1;
    } elseif ($possessionresult==4) {
    $playbyplay=$playbyplay."$p2_name ".threetext()." ".missedshottext().".<br>";
    $p2_stats_fga=$p2_stats_fga+1;
    $p2_stats_3ga=$p2_stats_3ga+1;
    $looseball=1;
    } elseif ($possessionresult==5) {
    $playbyplay=$playbyplay."$p2_name ".threetext()." ".madeshottext()."<br>";
    $p2_stats_fga=$p2_stats_fga+1;
    $p2_stats_3ga=$p2_stats_3ga+1;
    $p2_stats_fgm=$p2_stats_fgm+1;
    $p2_stats_3gm=$p2_stats_3gm+1;
    $looseball=0;
    $possession=1;
    $score2=$score2+3;
    } elseif ($possessionresult==6) {
    $playbyplay=$playbyplay."$p2_name ".outsidetwotext()." but $p1_name ".blocktext()."<br>";
    $p2_stats_fga=$p2_stats_fga+1;
    $p1_stats_blk=$p1_stats_blk+1;
    $looseball=1;
    } elseif ($possessionresult==7) {
    $playbyplay=$playbyplay."$p2_name ".outsidetwotext()." ".missedshottext()."<br>";
    $p2_stats_fga=$p2_stats_fga+1;
    $looseball=1;
    } elseif ($possessionresult==8) {
    $playbyplay=$playbyplay."$p2_name ".outsidetwotext()." ".madeshottext()."<br>";
    $p2_stats_fga=$p2_stats_fga+1;
    $p2_stats_fgm=$p2_stats_fgm+1;
    $looseball=0;
    $possession=1;
    $score2=$score2+2;
    } elseif ($possessionresult==9) {
    $playbyplay=$playbyplay."$p2_name ".drivetext()." but $p1_name ".blocktext()."<br>";
    $p2_stats_fga=$p2_stats_fga+1;
    $p1_stats_blk=$p1_stats_blk+1;
    $looseball=1;
    } elseif ($possessionresult==10) {
    $playbyplay=$playbyplay."$p2_name ".drivetext()." ".missedshottext()."<br>";
    $p2_stats_fga=$p2_stats_fga+1;
    $looseball=1;
    } elseif ($possessionresult==11) {
    $playbyplay=$playbyplay."$p2_name ".drivetext()." ".madeshottext()."<br>";
    $p2_stats_fga=$p2_stats_fga+1;
    $p2_stats_fgm=$p2_stats_fgm+1;
    $looseball=0;
    $possession=1;
    $score2=$score2+2;
    } elseif ($possessionresult==12) {
    $playbyplay=$playbyplay."$p2_name ".posttext()." but $p1_name ".blocktext()."<br>";
    $p2_stats_fga=$p2_stats_fga+1;
    $p1_stats_blk=$p1_stats_blk+1;
    $looseball=1;
    } elseif ($possessionresult==13) {
    $playbyplay=$playbyplay."$p2_name ".posttext()." ".missedshottext()."<br>";
    $p2_stats_fga=$p2_stats_fga+1;
    $looseball=1;
    } elseif ($possessionresult==14) {
    $playbyplay=$playbyplay."$p2_name ".posttext()." ".madeshottext()."<br>";
    $p2_stats_fga=$p2_stats_fga+1;
    $p2_stats_fgm=$p2_stats_fgm+1;
    $looseball=0;
    $possession=1;
    $score2=$score2+2;
    }

 }

 // === END SHOOTING ROUTINE, CHECK FOR LOOSE BALL
 if ($looseball == 1)
  {
  if ($possession == 1)
   {
   if (rebound($p1_orb, $p2_drb)) {
     $playbyplay=$playbyplay."$p1_name gets the (offensive) rebound.<br>";
     $possession=1;
     $p1_stats_orb=$p1_stats_orb+1;
     $p1_stats_reb=$p1_stats_reb+1;
     } else {
     $playbyplay=$playbyplay."$p2_name gets the rebound.<br>";
     $possession=2;
     $p2_stats_reb=$p2_stats_reb+1;
     }
   } else {
   if (rebound($p2_orb, $p1_drb)) {
     $playbyplay=$playbyplay."$p2_name gets the (offensive) rebound.<br>";
     $possession=2;
     $p2_stats_orb=$p2_stats_orb+1;
     $p2_stats_reb=$p2_stats_reb+1;
     } else {
     $playbyplay=$playbyplay."$p1_name gets the rebound.<br>";
     $possession=1;
     $p1_stats_reb=$p1_stats_reb+1;
     }
   }
  }

 // === UPDATE HISCORE VARIABLE

 if ($score1 > $score2)
  {
  $hiscore=$score1;
  } else {
  $hiscore=$score2;
  }
  $playbyplay=$playbyplay."<B>SCORE: $p1_name $score1, $p2_name $score2</B><p>";
// ===== END OF POSSESSION
}

$playbyplay=$playbyplay."<table border=1><tr><td colspan=11><font color=#ff0000><b>FINAL SCORE: $p1_name $score1, $p2_name $score2</b></font></td></tr>
<tr><td>Name</td><td>FGM</td><td>FGA</td><td>3GM</td><td>3GA</td><td>ORB</td><td>REB</td><td>STL</td><td>BLK</td><td>TVR</td><td>FOUL</td></tr>
<tr><td>$p1_name</td><td>$p1_stats_fgm</td><td>$p1_stats_fga</td><td>$p1_stats_3gm</td><td>$p1_stats_3ga</td><td>$p1_stats_orb</td><td>$p1_stats_reb</td><td>$p1_stats_stl</td><td>$p1_stats_blk</td><td>$p1_stats_to</td><td>$p1_stats_foul</td></tr>
<tr><td>$p2_name</td><td>$p2_stats_fgm</td><td>$p2_stats_fga</td><td>$p2_stats_3gm</td><td>$p2_stats_3ga</td><td>$p2_stats_orb</td><td>$p2_stats_reb</td><td>$p2_stats_stl</td><td>$p2_stats_blk</td><td>$p2_stats_to</td><td>$p2_stats_foul</td></tr>
</table>
";

echo "$playbyplay";

$querygetgameid="SELECT * FROM nuke_one_on_one";
$resultgetgameid=mysql_query($querygetgameid);
$gameid=mysql_numrows($resultgetgameid)+1;

if ($score1 > $score2)
{
$winner=addslashes($p1_name);
$loser=addslashes($p2_name);
$winscore=$score1;
$lossscore=$score2;
} else {
$winner=addslashes($p2_name);
$loser=addslashes($p1_name);
$winscore=$score2;
$lossscore=$score1;
}

$playbyplay2=addslashes($playbyplay);

$queryinsertgame="INSERT INTO nuke_one_on_one (gameid,playbyplay,winner,loser,winscore,lossscore,owner ) VALUES ('$gameid','$playbyplay2','$winner','$loser','$winscore','$lossscore','$owner')";
$resultinsert=mysql_query($queryinsertgame);

echo "GAME ID: $gameid";

}

function printgame ($gameid)
{
  $querygetgameid="SELECT * FROM nuke_one_on_one WHERE gameid = '$gameid'";
  $resultgetgameid=mysql_query($querygetgameid);

  $gametext=stripslashes(mysql_result($resultgetgameid,0,"playbyplay"));
  $gamewinner=stripslashes(mysql_result($resultgetgameid,0,"winner"));
  $gameloser=stripslashes(mysql_result($resultgetgameid,0,"loser"));
  $gamewinscore=stripslashes(mysql_result($resultgetgameid,0,"winscore"));
  $gamelossscore=stripslashes(mysql_result($resultgetgameid,0,"lossscore"));
  $owner=stripslashes(mysql_result($resultgetgameid,0,"owner"));

  echo "<center><h2>Replay of Game Number $gameid<br>$gamewinner $gamewinscore, $gameloser $gamelossscore<br><small>(Game played by $owner)</small></h2></center> $gametext";

}

switch($pa) {

    case "oneonone":
    oneonone();
    break;

    case "negotiate":
    negotiate($pid);
    break;

    case "awards":
    awards();
    break;

    case "showpage":
    showpage($pid,$spec);
    break;

    case "search":
    search();
    break;

    case "Leaderboards":
    leaderboards($type);
    break;

    default:
    showmenu();
    break;

}

?>