<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_ibl5";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

if ($id == NULL)
{

if ($year == NULL)
{
echo "<html><head><title>IBL Master Player List</title></head><body>
<table border=1 cellspacing=1 cellpadding=1><tr><td colspan=2><center><h2>IBL Master Player List</h2>";

$j=1980;

while ($j < 2009)
{
echo "<a href=\"player.php?year=$j\">$j Roster</a> | ";
$j++;
}

echo "</center></td></tr>
<tr><td valign=top><center><h3>Active Players</h3></center><ul>";

$query="SELECT * FROM nuke_iblplyr WHERE retired = '0' ORDER BY name ASC";
$result=mysql_query($query);
$num=mysql_numrows($result);

$i=0;

while ($i < $num)
{
$name=mysql_result($result,$i,"name");
$pos=mysql_result($result,$i,"pos");
$team=mysql_result($result,$i,"teamname");
$tid=mysql_result($result,$i,"tid");
$pid=mysql_result($result,$i,"pid");

echo "
<li><a href=\"player.php?id=$pid\">$name</a> ($pos, <a href=\"team.php?tid=$tid\">$team</a>)</li>";

$i++;
}
echo "</ul></td>
<td valign=top><center><h3>Retired Players</h3></center><ul>";

$query="SELECT * FROM nuke_iblplyr WHERE retired = '1' ORDER BY name ASC";
$result=mysql_query($query);
$num=mysql_numrows($result);

$i=0;

while ($i < $num)
{
$name=mysql_result($result,$i,"name");
$pos=mysql_result($result,$i,"pos");
$team=mysql_result($result,$i,"teamname");
$tid=mysql_result($result,$i,"tid");
$pid=mysql_result($result,$i,"pid");

echo "
<li><a href=\"player.php?id=$pid\">$name </a> ($pos)</li>";

$i++;
}

echo "</ul></td></tr></table></body></html>";

/* === DISPLAY PLAYERS SPECIFIC TO A GIVEN YEAR === */

} else {

echo "<html><head><title>IBL Player List - $year</title></head><body>
<table border=1 cellspacing=1 cellpadding=1><tr><td colspan=2><center><h2>IBL Player List - $year</h2></center></td></tr>
<tr><td>";

$j=2000;

while ($j < 2018)
{
echo "<a href=\"player.php?year=$j\">$j Roster</a> | ";
$j++;
}

echo "</td></tr>
<tr><td valign=top><ul>";

$query="SELECT * FROM nuke_iblhist WHERE year = $year ORDER BY name ASC";
$result=mysql_query($query);
$num=mysql_numrows($result);

$i=0;

while ($i < $num)
{
$name=mysql_result($result,$i,"name");
$team=mysql_result($result,$i,"team");
$tid=mysql_result($result,$i,"teamid");
$pid=mysql_result($result,$i,"pid");

echo "
<li><a href=\"player.php?id=$pid\">$name </a> (<a href=\"team.php?tid=$tid&yr=$year\">$team</a>)</li>";

$i++;
}

echo "</td></tr></table></body></html>";
}
} else {

$query="SELECT * FROM nuke_iblplyr WHERE pid = '$id'";
$result=mysql_query($query);
$num=mysql_numrows($result);

$query2="SELECT * FROM nuke_iblhist WHERE pid = '$id' ORDER BY year ASC";
$result2=mysql_query($query2);
$num2=mysql_numrows($result2);

$i=0;

while ($i < $num)
{
$name=mysql_result($result,$i,"name");
$year=mysql_result($result,$i,"draftyear")+mysql_result($result,$i,"exp");
$tid=mysql_result($result,$i,"tid");
$team=mysql_result($result,$i,"teamname");
$pos=mysql_result($result,$i,"pos");
$stats_gm=mysql_result($result,$i,"stats_gm");
$stats_min=mysql_result($result,$i,"stats_min");
$htft=mysql_result($result,$i,"htft");
$htin=mysql_result($result,$i,"htin");
$wt=mysql_result($result,$i,"wt");

$stats_fgm=mysql_result($result,$i,"stats_fgm");
$stats_fga=mysql_result($result,$i,"stats_fga");
@$stats_fgp=($stats_fgm/$stats_fga);
$stats_ftm=mysql_result($result,$i,"stats_ftm");
$stats_fta=mysql_result($result,$i,"stats_fta");
@$stats_ftp=($stats_ftm/$stats_fta);
$stats_tgm=mysql_result($result,$i,"stats_3gm");
$stats_tga=mysql_result($result,$i,"stats_3ga");
@$stats_tgp=($stats_tgm/$stats_tga);
$stats_orb=mysql_result($result,$i,"stats_orb");
$stats_drb=mysql_result($result,$i,"stats_drb");
$stats_reb=$stats_orb+$stats_drb;
$stats_ast=mysql_result($result,$i,"stats_ast");
$stats_stl=mysql_result($result,$i,"stats_stl");
$stats_to=mysql_result($result,$i,"stats_to");
$stats_blk=mysql_result($result,$i,"stats_blk");
$stats_pf=mysql_result($result,$i,"stats_pf");
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

$age=mysql_result($result,$i,"age");
$skill=mysql_result($result,$i,"talent");
$talent=mysql_result($result,$i,"skill");
$intangibles=mysql_result($result,$i,"intangibles");
$Clutch=mysql_result($result,$i,"Clutch");
$Consistency=mysql_result($result,$i,"Consistency");
$draftyear=mysql_result($result,$i,"draftyear");
$draftedby=mysql_result($result,$i,"draftedby");
$draftround=mysql_result($result,$i,"draftround");
$draftpickno=mysql_result($result,$i,"draftpickno");

$cy=mysql_result($result,$i,"cy");
$cy1=mysql_result($result,$i,"cy1");
$cy2=mysql_result($result,$i,"cy2");
$cy3=mysql_result($result,$i,"cy3");
$cy4=mysql_result($result,$i,"cy4");
$cy5=mysql_result($result,$i,"cy5");
$cy6=mysql_result($result,$i,"cy6");
$bird=mysql_result($result,$i,"bird");
$loyalty=mysql_result($result,$i,"loyalty");
$winner=mysql_result($result,$i,"winner");
$playingTime=mysql_result($result,$i,"playingTime");
$security=mysql_result($result,$i,"security");
$coach=mysql_result($result,$i,"coach");
$tradition=mysql_result($result,$i,"tradition");

$r_2ga=mysql_result($result,$i,"r_fga");
$r_2gp=mysql_result($result,$i,"r_fgp");
$r_fta=mysql_result($result,$i,"r_fta");
$r_ftp=mysql_result($result,$i,"r_ftp");
$r_3ga=mysql_result($result,$i,"r_tga");
$r_3gp=mysql_result($result,$i,"r_tgp");
$r_orb=mysql_result($result,$i,"r_orb");
$r_drb=mysql_result($result,$i,"r_drb");
$r_ast=mysql_result($result,$i,"r_ast");
$r_stl=mysql_result($result,$i,"r_stl");
$r_blk=mysql_result($result,$i,"r_blk");
$r_tvr=mysql_result($result,$i,"r_to");
$r_sta=mysql_result($result,$i,"sta");
$r_foul=mysql_result($result,$i,"r_foul");
$r_totoff=mysql_result($result,$i,"oo")+mysql_result($result,$i,"do")+mysql_result($result,$i,"po")+mysql_result($result,$i,"to");
$r_totdef=mysql_result($result,$i,"od")+mysql_result($result,$i,"dd")+mysql_result($result,$i,"pd")+mysql_result($result,$i,"td");
$r_oo=mysql_result($result,$i,"oo");
$r_do=mysql_result($result,$i,"do");
$r_po=mysql_result($result,$i,"po");
$r_to=mysql_result($result,$i,"to");
$r_od=mysql_result($result,$i,"od");
$r_dd=mysql_result($result,$i,"dd");
$r_pd=mysql_result($result,$i,"pd");
$r_td=mysql_result($result,$i,"td");
$nation=mysql_result($result,$i,"nation");
$retired=mysql_result($result,$i,"retired");

$contract_code1=NULL;
$contract_code2=NULL;
$contract_code3=NULL;
$contract_code4=NULL;
$contract_code5=NULL;
$contract_code6=NULL;

if ($cy == 1)
{
$contract_code1=" bgcolor=#dddddd";
} else {
$contract_code1=" bgcolor=#ffffff";
}
if ($cy == 2) {
$contract_code2=" bgcolor=#dddddd";
} else {
$contract_code2=" bgcolor=#ffffff";
}
if ($cy == 3) {
$contract_code3=" bgcolor=#dddddd";
} else {
$contract_code3=" bgcolor=#ffffff";
}
if ($cy == 4) {
$contract_code4=" bgcolor=#dddddd";
} else {
$contract_code4=" bgcolor=#ffffff";
}
if ($cy == 5) {
$contract_code5=" bgcolor=#dddddd";
} else {
$contract_code5=" bgcolor=#ffffff";
}
if ($cy == 6) {
$contract_code6=" bgcolor=#dddddd";
} else {
$contract_code6=" bgcolor=#ffffff";
}

echo "<html><head><title>IBL Player Page: $name</title></head><body>
<style>th{ font-size: 9pt; font-family:Arial; color:white; background-color: navy}td      { text-align: Left; font-size: 9pt; font-family:Arial; color:black; }.tdp { font-weight: bold; text-align: Left; font-size: 9pt; color:black; } </style>

<table><tr><td valign=top>
      <table>
      <tr><td colspan=9 bgcolor=#aaaaff><b>$name | $pos | ";

/* FORK FOR RETIRED PLAYERS */

if ($retired > 0)
{
echo "Retired</b></td></tr>
      <tr><td colspan=2><img align=center src=\"../images/player/$id.jpg\"></td><th>Awards</th></tr>
    <hr>
      <table>
";
} else {
echo "<a href=\"team.php?tid=$tid\">$team</a></b></td></tr>
      <tr><td rowspan=6 colspan=2 valign=center><img align=center src=\"../images/player/$id.jpg\"></td><th colspan=2><center>ATTRIBUTES</center></th><th colspan=2><center>CONTRACT</center></th><th colspan=2><center>PREFERENCES</center></th><th><center>RATINGS</center></th></tr>
      <tr><td><b>Talent:</b></td><td>$talent</td><td><b>Year 1:</b></td><td$contract_code1>$cy1</td><td><b>Loyalty:</b></td><td>$loyalty</td><td rowspan=8 bgcolor=#000000>
        <table bgcolor=#ffffff align=center valign=center cellpadding=0 cellspacing=0 border=1>
          <tr><td><b>2ga</b></td><td>$r_2ga</td><td><b>orb</b></td><td>$r_orb</td><td bgcolor=#dddddd><b>oo</b></td><td bgcolor=#dddddd>$r_oo</td></tr>
          <tr><td><b>2gp</b></td><td>$r_2gp</td><td><b>drb</b></td><td>$r_drb</td><td bgcolor=#dddddd><b>do</b></td><td bgcolor=#dddddd>$r_do</td></tr>
          <tr><td><b>fta</b></td><td>$r_fta</td><td><b>ast</b></td><td>$r_ast</td><td bgcolor=#dddddd><b>po</b></td><td bgcolor=#dddddd>$r_po</td></tr>
          <tr><td><b>ftp</b></td><td>$r_ftp</td><td><b>stl</b></td><td>$r_stl</td><td bgcolor=#dddddd><b>to</b></td><td bgcolor=#dddddd>$r_to</td></tr>
          <tr><td><b>3ga</b></td><td>$r_3ga</td><td><b>blk</b></td><td>$r_blk</td><td bgcolor=#dddddd><b>od</b></td><td bgcolor=#dddddd>$r_od</td></tr>
          <tr><td><b>3gp</b></td><td>$r_3gp</td><td><b>tvr</b></td><td>$r_tvr</td><td bgcolor=#dddddd><b>dd</b></td><td bgcolor=#dddddd>$r_dd</td></tr>
          <tr><td><b>STA</b></td><td>$r_sta</td><td><b>foul</b></td><td>$r_foul</td><td bgcolor=#dddddd><b>pd</b></td><td bgcolor=#dddddd>$r_pd</td></tr>
          <tr><th><b>OFF</b></th><th>$r_totoff</td><th><b>DEF</b></th><th>$r_totdef</td><td bgcolor=#dddddd><b>td</b></td><td bgcolor=#dddddd>$r_td</td></tr>
        </table>
      </tr>
      <tr><td><b>Skill:</b></td><td>$skill</td><td><b>Year 2:</b></td><td$contract_code2>$cy2</td><td><b>Play for Winner:</b></td><td>$winner</td></tr>
      <tr><td><b>Intangibles:</b></td><td>$intangibles</td><td><b>Year 3:</b></td><td$contract_code3>$cy3</td><td><b>Playing Time:</b></td><td>$playingTime</td></tr>
      <tr><td><b>Clutch:</b></td><td>$Clutch</td><td><b>Year 4:</b></td><td$contract_code4>$cy4</td><td><b>Security:</b></td><td>$security</td></tr>
      <tr><td><b>Consistency:</b></td><td>$Consistency</td><td><b>Year 5:</b></td><td$contract_code5>$cy5</td><td><b>Coach:</b></td><td>$coach</td></tr>
      <tr><td bgcolor=#0000bb><font color=#ffffff><b>Age:</b></font></td><td bgcolor=#0000bb><font color=#ffffff>$age</td><td><b>Draft Year</b></td><td>$draftyear</td><td><b>Year 6:</b></td><td$contract_code6>$cy6</td><td><b>Tradition:</b></td><td>$tradition</td></tr>
      <tr><td bgcolor=#0000bb><font color=#ffffff><b>Height:</b></font></td><td bgcolor=#0000bb><font color=#ffffff>$htft - $htin</td><td><b>Drafted By</b></td><td>$draftedby</td><th colspan=2><center>Bird Status</center></th><th colspan=2><center>Nationality</center></th></tr>
      <tr><td bgcolor=#0000bb><b><font color=#ffffff>Weight:</b></font></td><td bgcolor=#0000bb><font color=#ffffff>$wt</font></td><td><b>Drafted:</b></td><td>";
if ($draftpickno == 0)
{
echo "Undrafted";
} else {
echo "Round $draftround, No. $draftpickno";
}
echo "</td><td><b>Bird Years:</b></td><td>$bird</td><td colspan=2><center><a href=\"nation.php\">$nation</a></center></td></tr></table>
    <hr>
      <table>
";
}

/* =======================CAREER TOTALS */


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
$car_ast=0;
$car_stl=0;
$car_blk=0;
$car_tvr=0;
$car_pf=0;
$car_pts=0;

echo "      <tr bgcolor=#ddddbb><th colspan=15><center>Career Totals</center></th></tr>
      <tr><th>year</th><th>team</th><th>g</th><th>min</th><th>FGM - FGA</th><th>FTM - FTA</th><th>3GM - 3GA</th><th>orb</th><th>reb</th><th>ast</th><th>stl</th><th>to</th><th>blk</th><th>pf</th><th>pts</th></tr>
";

/* =======================Loop player historical totals */

$k=0;
while ($k < $num2)
{

$hist_year=mysql_result($result2,$k,"year");
$hist_team=mysql_result($result2,$k,"team");
$hist_gm=mysql_result($result2,$k,"gm");
$hist_min=mysql_result($result2,$k,"min");
$hist_fgm=mysql_result($result2,$k,"fgm");
$hist_fga=mysql_result($result2,$k,"fga");
@$hist_fgp=($hist_fgm/$hist_fga);
$hist_ftm=mysql_result($result2,$k,"ftm");
$hist_fta=mysql_result($result2,$k,"fta");
@$hist_ftp=($hist_ftm/$hist_fta);
$hist_tgm=mysql_result($result2,$k,"3gm");
$hist_tga=mysql_result($result2,$k,"3ga");
@$hist_tgp=($hist_tgm/$hist_tga);
$hist_orb=mysql_result($result2,$k,"orb");
$hist_reb=mysql_result($result2,$k,"reb");
$hist_ast=mysql_result($result2,$k,"ast");
$hist_stl=mysql_result($result2,$k,"stl");
$hist_tvr=mysql_result($result2,$k,"tvr");
$hist_blk=mysql_result($result2,$k,"blk");
$hist_pf=mysql_result($result2,$k,"pf");
$hist_pts=mysql_result($result2,$k,"fgm")+mysql_result($result2,$k,"fgm")+mysql_result($result2,$k,"ftm")+mysql_result($result2,$k,"3gm");

@$hist_mpg=($hist_min/$hist_gm);
@$hist_opg=($hist_orb/$hist_gm);
@$hist_rpg=($hist_reb/$hist_gm);
@$hist_apg=($hist_ast/$hist_gm);
@$hist_spg=($hist_stl/$hist_gm);
@$hist_tpg=($hist_to/$hist_gm);
@$hist_bpg=($hist_blk/$hist_gm);
@$hist_fpg=($hist_pf/$hist_gm);
@$hist_ppg=($hist_pts/$hist_gm);

if ($hist_year % 2)
{
echo "      <tr bgcolor=#ffffff align=center>";
} else {
echo "      <tr bgcolor=#e6e7e2 align=center>";
}
echo "      <td><center>$hist_year</center></td><td><center>$hist_team</center></td><td><center>$hist_gm</center></td><td><center>$hist_min</center></td><td><center>$hist_fgm - $hist_fga</center></td><td><center>$hist_ftm - $hist_fta</center></td><td><center>$hist_tgm - $hist_tga</center></td><td><center>$hist_orb</center></td><td><center>$hist_reb</center></td><td><center>$hist_ast</center></td><td><center>$hist_stl</center></td><td><center>$hist_tvr</center></td><td><center>$hist_blk</center></td><td><center>$hist_pf</center></td><td><center>$hist_pts</td></tr>
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

$k++;

}

/* =======================Display player current year totals */
/* FORK FOR RETIRED PLAYERS */

if ($retired > 0)
{
} else {

if ($year % 2)
{
echo "      <tr bgcolor=#ffffff align=center>";
} else {
echo "      <tr bgcolor=#e6e7e2 align=center>";
}
echo "      <td><center>$year</center></td><td><center>$team</center></td><td><center>$stats_gm</center></td><td><center>$stats_min</center></td><td><center>$stats_fgm - $stats_fga</center></td><td><center>$stats_ftm - $stats_fta</center></td><td><center>$stats_tgm - $stats_tga</center></td><td><center>$stats_orb</center></td><td><center>$stats_reb</center></td><td><center>$stats_ast</center></td><td><center>$stats_stl</center></td><td><center>$stats_to</center></td><td><center>$stats_blk</center></td><td><center>$stats_pf</center></td><td><center>$stats_pts</td></tr>
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

echo "      <tr bgcolor=#eeffff><td colspan=2>Career Totals</td><td><center>$car_gm</center></td><td><center>$car_min</center></td><td><center>$car_fgm - $car_fga</center></td><td><center>$car_ftm - $car_fta</center></td><td><center>$car_3gm - $car_3ga</center></td><td><center>$car_orb</center></td><td><center>$car_reb</center></td><td><center>$car_ast</center></td><td><center>$car_stl</center></td><td><center>$car_tvr</center></td><td><center>$car_blk</center></td><td><center>$car_pf</center></td><td><center>$car_pts</td></tr>
";


/* =======================CAREER AVERAGES */

echo "      <tr bgcolor=#ddddbb><th colspan=15><center>Career Averages</center></th></tr>
      <tr><th>year</th><th>team</th><th>g</th><th>min</th><th>FGP</th><th>FTP</th><th>3GP</th><th>orb</th><th>reb</th><th>ast</th><th>stl</th><th>to</th><th>blk</th><th>pf</th><th>pts</th></tr>
";


/* =======================Loop player historical averages */

$k=0;
while ($k < $num2)
{

$hist_year=mysql_result($result2,$k,"year");
$hist_team=mysql_result($result2,$k,"team");
$hist_gm=mysql_result($result2,$k,"gm");
$hist_min=mysql_result($result2,$k,"min");
$hist_fgm=mysql_result($result2,$k,"fgm");
$hist_fga=mysql_result($result2,$k,"fga");
@$hist_fgp=($hist_fgm/$hist_fga);
$hist_ftm=mysql_result($result2,$k,"ftm");
$hist_fta=mysql_result($result2,$k,"fta");
@$hist_ftp=($hist_ftm/$hist_fta);
$hist_tgm=mysql_result($result2,$k,"3gm");
$hist_tga=mysql_result($result2,$k,"3ga");
@$hist_tgp=($hist_tgm/$hist_tga);
$hist_orb=mysql_result($result2,$k,"orb");
$hist_reb=mysql_result($result2,$k,"reb");
$hist_ast=mysql_result($result2,$k,"ast");
$hist_stl=mysql_result($result2,$k,"stl");
$hist_tvr=mysql_result($result2,$k,"tvr");
$hist_blk=mysql_result($result2,$k,"blk");
$hist_pf=mysql_result($result2,$k,"pf");
$hist_pts=mysql_result($result2,$k,"fgm")+mysql_result($result2,$k,"fgm")+mysql_result($result2,$k,"ftm")+mysql_result($result2,$k,"3gm");

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
echo "      <tr bgcolor=#ffffff align=center>";
} else {
echo "      <tr bgcolor=#e6e7e2 align=center>";
}
echo "      <td><center>$hist_year</center></td><td><center>$hist_team</center></td><td><center>$hist_gm</center></td><td><center>";
printf('%01.2f', $hist_mpg);
echo "</center></td><td><center>";
printf('%01.3f', $hist_fgp);
echo "</center></td><td><center>";
printf('%01.3f', $hist_ftp);
echo "</center></td><td><center>";
printf('%01.3f', $hist_tgp);
echo "</center></td><td><center>";
printf('%01.2f', $hist_opg);
echo "</center></td><td><center>";
printf('%01.2f', $hist_rpg);
echo "</center></td><td><center>";
printf('%01.2f', $hist_apg);
echo "</center></td><td><center>";
printf('%01.2f', $hist_spg);
echo "</center></td><td><center>";
printf('%01.2f', $hist_tpg);
echo "</center></td><td><center>";
printf('%01.2f', $hist_bpg);
echo "</center></td><td><center>";
printf('%01.2f', $hist_fpg);
echo "</center></td><td><center>";
printf('%01.2f', $hist_ppg);
echo "</center></td></tr>";

$k++;

}


/* =======================Display player current year averages */
/* FORK FOR RETIRED PLAYERS */

if ($retired > 0)
{
} else {

if ($year % 2)
{
echo "      <tr bgcolor=#ffffff align=center>";
} else {
echo "      <tr bgcolor=#e6e7e2 align=center>";
}
echo "      <td><center>$year</center></td><td><center>$team</center></td><td><center>$stats_gm</center></td><td><center>";
printf('%01.2f', $stats_mpg);
echo "</center></td><td><center>";
printf('%01.3f', $stats_fgp);
echo "</center></td><td><center>";
printf('%01.3f', $stats_ftp);
echo "</center></td><td><center>";
printf('%01.3f', $stats_tgp);
echo "</center></td><td><center>";
printf('%01.2f', $stats_opg);
echo "</center></td><td><center>";
printf('%01.2f', $stats_rpg);
echo "</center></td><td><center>";
printf('%01.2f', $stats_apg);
echo "</center></td><td><center>";
printf('%01.2f', $stats_spg);
echo "</center></td><td><center>";
printf('%01.2f', $stats_tpg);
echo "</center></td><td><center>";
printf('%01.2f', $stats_bpg);
echo "</center></td><td><center>";
printf('%01.2f', $stats_fpg);
echo "</center></td><td><center>";
printf('%01.2f', $stats_ppg);
echo "</center></td></tr>";
}
echo "      <tr bgcolor=#eeffff><td colspan=2>Career Averages</td><td><center>$car_gm</center></td><td><center>";
printf('%01.2f', $car_avgm);
echo "</center></td><td><center>";
printf('%01.3f', $car_fgp);
echo "</center></td><td><center>";
printf('%01.3f', $car_ftp);
echo "</center></td><td><center>";
printf('%01.3f', $car_tgp);
echo "</center></td><td><center>";
printf('%01.2f', $car_avgo);
echo "</center></td><td><center>";
printf('%01.2f', $car_avgr);
echo "</center></td><td><center>";
printf('%01.2f', $car_avga);
echo "</center></td><td><center>";
printf('%01.2f', $car_avgs);
echo "</center></td><td><center>";
printf('%01.2f', $car_avgt);
echo "</center></td><td><center>";
printf('%01.2f', $car_avgb);
echo "</center></td><td><center>";
printf('%01.2f', $car_avgf);
echo "</center></td><td><center>";
printf('%01.2f', $car_avgp);
echo "</center></td></tr>";


/* =======================PLAYOFF TOTALS */


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
$car_ast=0;
$car_stl=0;
$car_blk=0;
$car_tvr=0;
$car_pf=0;
$car_pts=0;

echo "      <tr><td colspan=15><hr></td></tr>
            <tr bgcolor=#88dd88><td colspan=15><center><b>Playoff Totals</b></center></td></tr>
      <tr><th>year</th><th>team</th><th>g</th><th>min</th><th>FGM - FGA</th><th>FTM - FTA</th><th>3GM - 3GA</th><th>orb</th><th>reb</th><th>ast</th><th>stl</th><th>to</th><th>blk</th><th>pf</th><th>pts</th></tr>
";

/* ========== Add Slashes in front of Apostrophes in Player Name for Queries ==========  */

$name=mysql_real_escape_string($name);

/* ========== Loop player historical playoff totals ========== */

$query4="SELECT * FROM ibl_playoff_stats WHERE name = '$name' ORDER BY year ASC";
$result4=mysql_query($query4);
@$num4=mysql_numrows($result4);


$rowcolor=0;
$k=0;
while ($k < $num4)
{

$hist_year=mysql_result($result4,$k,"year");
$hist_team=mysql_result($result4,$k,"team");
$hist_gm=mysql_result($result4,$k,"games");
$hist_min=mysql_result($result4,$k,"minutes");
$hist_fgm=mysql_result($result4,$k,"fgm");
$hist_fga=mysql_result($result4,$k,"fga");
@$hist_fgp=($hist_fgm/$hist_fga);
$hist_ftm=mysql_result($result4,$k,"ftm");
$hist_fta=mysql_result($result4,$k,"fta");
@$hist_ftp=($hist_ftm/$hist_fta);
$hist_tgm=mysql_result($result4,$k,"tgm");
$hist_tga=mysql_result($result4,$k,"tga");
@$hist_tgp=($hist_tgm/$hist_tga);
$hist_orb=mysql_result($result4,$k,"orb");
$hist_reb=mysql_result($result4,$k,"reb");
$hist_ast=mysql_result($result4,$k,"ast");
$hist_stl=mysql_result($result4,$k,"stl");
$hist_tvr=mysql_result($result4,$k,"tvr");
$hist_blk=mysql_result($result4,$k,"blk");
$hist_pf=mysql_result($result4,$k,"pf");
$hist_pts=mysql_result($result4,$k,"fgm")+mysql_result($result4,$k,"fgm")+mysql_result($result4,$k,"ftm")+mysql_result($result4,$k,"tgm");

@$hist_mpg=($hist_min/$hist_gm);
@$hist_opg=($hist_orb/$hist_gm);
@$hist_rpg=($hist_reb/$hist_gm);
@$hist_apg=($hist_ast/$hist_gm);
@$hist_spg=($hist_stl/$hist_gm);
@$hist_tpg=($hist_tvr/$hist_gm);
@$hist_bpg=($hist_blk/$hist_gm);
@$hist_fpg=($hist_pf/$hist_gm);
@$hist_ppg=($hist_pts/$hist_gm);

if ($rowcolor == 0)
{
echo "      <tr bgcolor=#ffffff align=center>";
$rowcolor=1;
} else {
echo "      <tr bgcolor=#e6e7e2 align=center>";
$rowcolor=0;
}
echo "      <td><center>$hist_year</center></td><td><center>$hist_team</center></td><td><center>$hist_gm</center></td><td><center>$hist_min</center></td><td><center>$hist_fgm - $hist_fga</center></td><td><center>$hist_ftm - $hist_fta</center></td><td><center>$hist_tgm - $hist_tga</center></td><td><center>$hist_orb</center></td><td><center>$hist_reb</center></td><td><center>$hist_ast</center></td><td><center>$hist_stl</center></td><td><center>$hist_tvr</center></td><td><center>$hist_blk</center></td><td><center>$hist_pf</center></td><td><center>$hist_pts</td></tr>
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

$k++;

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

echo "      <tr bgcolor=#eeffff><td colspan=2>Playoff Totals</td><td><center>$car_gm</center></td><td><center>$car_min</center></td><td><center>$car_fgm - $car_fga</center></td><td><center>$car_ftm - $car_fta</center></td><td><center>$car_3gm - $car_3ga</center></td><td><center>$car_orb</center></td><td><center>$car_reb</center></td><td><center>$car_ast</center></td><td><center>$car_stl</center></td><td><center>$car_tvr</center></td><td><center>$car_blk</center></td><td><center>$car_pf</center></td><td><center>$car_pts</td></tr>
";

/* =======================CAREER PLAYOFF AVERAGES */

echo "      <tr bgcolor=#88dd88><td colspan=15><center><b>Playoff Averages</b></center></td></tr>
      <tr><th>year</th><th>team</th><th>g</th><th>min</th><th>FGP</th><th>FTP</th><th>3GP</th><th>orb</th><th>reb</th><th>ast</th><th>stl</th><th>to</th><th>blk</th><th>pf</th><th>pts</th></tr>
";


/* =======================Loop player historical averages */

$k=0;
$rowcolor=0;
while ($k < $num4)
{

$hist_year=mysql_result($result4,$k,"year");
$hist_team=mysql_result($result4,$k,"team");
$hist_gm=mysql_result($result4,$k,"games");
$hist_min=mysql_result($result4,$k,"minutes");
$hist_fgm=mysql_result($result4,$k,"fgm");
$hist_fga=mysql_result($result4,$k,"fga");
@$hist_fgp=($hist_fgm/$hist_fga);
$hist_ftm=mysql_result($result4,$k,"ftm");
$hist_fta=mysql_result($result4,$k,"fta");
@$hist_ftp=($hist_ftm/$hist_fta);
$hist_tgm=mysql_result($result4,$k,"tgm");
$hist_tga=mysql_result($result4,$k,"tga");
@$hist_tgp=($hist_tgm/$hist_tga);
$hist_orb=mysql_result($result4,$k,"orb");
$hist_reb=mysql_result($result4,$k,"reb");
$hist_ast=mysql_result($result4,$k,"ast");
$hist_stl=mysql_result($result4,$k,"stl");
$hist_tvr=mysql_result($result4,$k,"tvr");
$hist_blk=mysql_result($result4,$k,"blk");
$hist_pf=mysql_result($result4,$k,"pf");
$hist_pts=mysql_result($result4,$k,"fgm")+mysql_result($result4,$k,"fgm")+mysql_result($result4,$k,"ftm")+mysql_result($result4,$k,"tgm");

@$hist_mpg=($hist_min/$hist_gm);
@$hist_opg=($hist_orb/$hist_gm);
@$hist_rpg=($hist_reb/$hist_gm);
@$hist_apg=($hist_ast/$hist_gm);
@$hist_spg=($hist_stl/$hist_gm);
@$hist_tpg=($hist_tvr/$hist_gm);
@$hist_bpg=($hist_blk/$hist_gm);
@$hist_fpg=($hist_pf/$hist_gm);
@$hist_ppg=($hist_pts/$hist_gm);

if ($rowcolor == 0)
{
echo "      <tr bgcolor=#ffffff align=center>";
$rowcolor=1;
} else {
echo "      <tr bgcolor=#e6e7e2 align=center>";
$rowcolor=0;
}
echo "      <td><center>$hist_year</center></td><td><center>$hist_team</center></td><td><center>$hist_gm</center></td><td><center>";
printf('%01.2f', $hist_mpg);
echo "</center></td><td><center>";
printf('%01.3f', $hist_fgp);
echo "</center></td><td><center>";
printf('%01.3f', $hist_ftp);
echo "</center></td><td><center>";
printf('%01.3f', $hist_tgp);
echo "</center></td><td><center>";
printf('%01.2f', $hist_opg);
echo "</center></td><td><center>";
printf('%01.2f', $hist_rpg);
echo "</center></td><td><center>";
printf('%01.2f', $hist_apg);
echo "</center></td><td><center>";
printf('%01.2f', $hist_spg);
echo "</center></td><td><center>";
printf('%01.2f', $hist_tpg);
echo "</center></td><td><center>";
printf('%01.2f', $hist_bpg);
echo "</center></td><td><center>";
printf('%01.2f', $hist_fpg);
echo "</center></td><td><center>";
printf('%01.2f', $hist_ppg);
echo "</center></td></tr>";

$k++;

}

echo "      <tr bgcolor=#eeffff><td colspan=2>Playoff Averages</td><td><center>$car_gm</center></td><td><center>";
printf('%01.2f', $car_avgm);
echo "</center></td><td><center>";
printf('%01.3f', $car_fgp);
echo "</center></td><td><center>";
printf('%01.3f', $car_ftp);
echo "</center></td><td><center>";
printf('%01.3f', $car_tgp);
echo "</center></td><td><center>";
printf('%01.2f', $car_avgo);
echo "</center></td><td><center>";
printf('%01.2f', $car_avgr);
echo "</center></td><td><center>";
printf('%01.2f', $car_avga);
echo "</center></td><td><center>";
printf('%01.2f', $car_avgs);
echo "</center></td><td><center>";
printf('%01.2f', $car_avgt);
echo "</center></td><td><center>";
printf('%01.2f', $car_avgb);
echo "</center></td><td><center>";
printf('%01.2f', $car_avgf);
echo "</center></td><td><center>";
printf('%01.2f', $car_avgp);
echo "</center></td></tr>";

/* =======================END PLAYOFF STATS */


/* =======================START OF COLLEGE CAREER OUTPUT */

$query4="SELECT * FROM ibl_college_stats WHERE name = '$name' ORDER BY year ASC";
$result4=mysql_query($query4);
@$num4=mysql_numrows($result4);

if ($num4 > 0)
{
echo "      <tr><td colspan=15><hr></td></tr><tr><td colspan=15>
<center><table border=1><tr bgcolor=#cc0000><td colspan=14><center><font color=#ffffff><b>College Totals</b></font></center></td></tr>
      <tr><th>year</th><th>g</th><th>min</th><th>FGM - FGA</th><th>FTM - FTA</th><th>3GM - 3GA</th><th>orb</th><th>reb</th><th>ast</th><th>stl</th><th>to</th><th>blk</th><th>pf</th><th>pts</th></tr>
";
$c=0;

$college_career_gm=0;
$college_career_min=0;
$college_career_fgm=0;
$college_career_fga=0;
$college_career_ftm=0;
$college_career_fta=0;
$college_career_tgm=0;
$college_career_tga=0;
$college_career_orb=0;
$college_career_reb=0;
$college_career_ast=0;
$college_career_stl=0;
$college_career_tvr=0;
$college_career_blk=0;
$college_career_pf=0;
$college_career_pts=0;

while ($c < $num4)
{
$college_year=mysql_result($result4,$c,"year");
$college_gm=mysql_result($result4,$c,"games");
$college_min=mysql_result($result4,$c,"minutes");
$college_fgm=mysql_result($result4,$c,"fgm");
$college_fga=mysql_result($result4,$c,"fga");
@$college_fgp=($college_fgm/$college_fga);
$college_ftm=mysql_result($result4,$c,"ftm");
$college_fta=mysql_result($result4,$c,"fta");
@$college_ftp=($college_ftm/$college_fta);
$college_tgm=mysql_result($result4,$c,"tgm");
$college_tga=mysql_result($result4,$c,"tga");
@$college_tgp=($college_tgm/$college_tga);
$college_orb=mysql_result($result4,$c,"orb");
$college_reb=mysql_result($result4,$c,"reb");
$college_ast=mysql_result($result4,$c,"ast");
$college_stl=mysql_result($result4,$c,"stl");
$college_tvr=mysql_result($result4,$c,"tvr");
$college_blk=mysql_result($result4,$c,"blk");
$college_pf=mysql_result($result4,$c,"pf");
$college_pts=mysql_result($result4,$c,"fgm")+mysql_result($result4,$c,"fgm")+mysql_result($result4,$c,"ftm")+mysql_result($result4,$c,"tgm");

if (($college_year % 2) == 1)
{
echo "      <tr bgcolor=#ffffff align=center>";
} else {
echo "      <tr bgcolor=#e6e7e2 align=center>";
}
echo "      <td><center>$college_year</center></td><td><center>$college_gm</center></td><td><center>$college_min</center></td><td><center>$college_fgm - $college_fga</center></td><td><center>$college_ftm - $college_fta</center></td><td><center>$college_tgm - $college_tga</center></td><td><center>$college_orb</center></td><td><center>$college_reb</center></td><td><center>$college_ast</center></td><td><center>$college_stl</center></td><td><center>$college_tvr</center></td><td><center>$college_blk</center></td><td><center>$college_pf</center></td><td><center>$college_pts</td></tr>
";

$college_career_gm=$college_career_gm+$college_gm;
$college_career_min=$college_career_min+$college_min;
$college_career_fgm=$college_career_fgm+$college_fgm;
$college_career_fga=$college_career_fga+$college_fga;
$college_career_ftm=$college_career_ftm+$college_ftm;
$college_career_fta=$college_career_fta+$college_fta;
$college_career_tgm=$college_career_tgm+$college_tgm;
$college_career_tga=$college_career_tga+$college_tga;
$college_career_orb=$college_career_orb+$college_orb;
$college_career_reb=$college_career_reb+$college_reb;
$college_career_ast=$college_career_ast+$college_ast;
$college_career_stl=$college_career_stl+$college_stl;
$college_career_tvr=$college_career_tvr+$college_tvr;
$college_career_blk=$college_career_blk+$college_blk;
$college_career_pf=$college_career_pf+$college_pf;
$college_career_pts=$college_career_pts+$college_pts;

$c++;

}

echo "      <tr bgcolor=#eeffff><td>College Totals</td><td><center>$college_career_gm</center></td><td><center>$college_career_min</center></td><td><center>$college_career_fgm - $college_career_fga</center></td><td><center>$college_career_ftm - $college_career_fta</center></td><td><center>$college_career_tgm - $college_career_tga</center></td><td><center>$college_career_orb</center></td><td><center>$college_career_reb</center></td><td><center>$college_career_ast</center></td><td><center>$college_career_stl</center></td><td><center>$college_career_tvr</center></td><td><center>$college_career_blk</center></td><td><center>$college_career_pf</center></td><td><center>$college_career_pts</td></tr>
      <tr bgcolor=#cc0000><td colspan=14><center><font color=#ffffff><b>College Averages</b></font></center></td></tr>
      <tr><th>year</th><th>g</th><th>min</th><th>FGP</th><th>FTP</th><th>3GP</th><th>orb</th><th>reb</th><th>ast</th><th>stl</th><th>to</th><th>blk</th><th>pf</th><th>pts</th></tr>
";
$c=0;

$college_career_gm=0;
$college_career_min=0;
$college_career_fgm=0;
$college_career_fga=0;
$college_career_ftm=0;
$college_career_fta=0;
$college_career_tgm=0;
$college_career_tga=0;
$college_career_orb=0;
$college_career_reb=0;
$college_career_ast=0;
$college_career_stl=0;
$college_career_tvr=0;
$college_career_blk=0;
$college_career_pf=0;
$college_career_pts=0;

while ($c < $num4)
{
$college_year=mysql_result($result4,$c,"year");
$college_gm=mysql_result($result4,$c,"games");
$college_min=mysql_result($result4,$c,"minutes");
$college_fgm=mysql_result($result4,$c,"fgm");
$college_fga=mysql_result($result4,$c,"fga");
@$college_fgp=($college_fgm/$college_fga);
$college_ftm=mysql_result($result4,$c,"ftm");
$college_fta=mysql_result($result4,$c,"fta");
@$college_ftp=($college_ftm/$college_fta);
$college_tgm=mysql_result($result4,$c,"tgm");
$college_tga=mysql_result($result4,$c,"tga");
@$college_tgp=($college_tgm/$college_tga);
$college_orb=mysql_result($result4,$c,"orb");
$college_reb=mysql_result($result4,$c,"reb");
$college_ast=mysql_result($result4,$c,"ast");
$college_stl=mysql_result($result4,$c,"stl");
$college_tvr=mysql_result($result4,$c,"tvr");
$college_blk=mysql_result($result4,$c,"blk");
$college_pf=mysql_result($result4,$c,"pf");
$college_pts=mysql_result($result4,$c,"fgm")+mysql_result($result4,$c,"fgm")+mysql_result($result4,$c,"ftm")+mysql_result($result4,$c,"tgm");

@$college_mpg=($college_min/$college_gm);
@$college_opg=($college_orb/$college_gm);
@$college_rpg=($college_reb/$college_gm);
@$college_apg=($college_ast/$college_gm);
@$college_spg=($college_stl/$college_gm);
@$college_tpg=($college_tvr/$college_gm);
@$college_bpg=($college_blk/$college_gm);
@$college_fpg=($college_pf/$college_gm);
@$college_ppg=($college_pts/$college_gm);

if (($college_year % 2) == 1)
{
echo "      <tr bgcolor=#ffffff align=center>";
} else {
echo "      <tr bgcolor=#e6e7e2 align=center>";
}
echo "      <td><center>$college_year</center></td><td><center>$college_gm</center></td><td><center>";
printf('%01.2f', $college_mpg);
echo "</center></td><td><center>";
printf('%01.3f', $college_fgp);
echo "</center></td><td><center>";
printf('%01.3f', $college_ftp);
echo "</center></td><td><center>";
printf('%01.3f', $college_tgp);
echo "</center></td><td><center>";
printf('%01.2f', $college_opg);
echo "</center></td><td><center>";
printf('%01.2f', $college_rpg);
echo "</center></td><td><center>";
printf('%01.2f', $college_apg);
echo "</center></td><td><center>";
printf('%01.2f', $college_spg);
echo "</center></td><td><center>";
printf('%01.2f', $college_tpg);
echo "</center></td><td><center>";
printf('%01.2f', $college_bpg);
echo "</center></td><td><center>";
printf('%01.2f', $college_fpg);
echo "</center></td><td><center>";
printf('%01.2f', $college_ppg);
echo "</center></td></tr>";

$college_career_gm=$college_career_gm+$college_gm;
$college_career_min=$college_career_min+$college_min;
$college_career_fgm=$college_career_fgm+$college_fgm;
$college_career_fga=$college_career_fga+$college_fga;
$college_career_ftm=$college_career_ftm+$college_ftm;
$college_career_fta=$college_career_fta+$college_fta;
$college_career_tgm=$college_career_tgm+$college_tgm;
$college_career_tga=$college_career_tga+$college_tga;
$college_career_orb=$college_career_orb+$college_orb;
$college_career_reb=$college_career_reb+$college_reb;
$college_career_ast=$college_career_ast+$college_ast;
$college_career_stl=$college_career_stl+$college_stl;
$college_career_tvr=$college_career_tvr+$college_tvr;
$college_career_blk=$college_career_blk+$college_blk;
$college_career_pf=$college_career_pf+$college_pf;
$college_career_pts=$college_career_pts+$college_pts;

$c++;
}

@$college_career_mpg=($college_career_min/$college_career_gm);
@$college_career_opg=($college_career_orb/$college_career_gm);
@$college_career_rpg=($college_career_reb/$college_career_gm);
@$college_career_apg=($college_career_ast/$college_career_gm);
@$college_career_spg=($college_career_stl/$college_career_gm);
@$college_career_tpg=($college_career_tvr/$college_career_gm);
@$college_career_bpg=($college_career_blk/$college_career_gm);
@$college_career_fpg=($college_career_pf/$college_career_gm);
@$college_career_ppg=($college_career_pts/$college_career_gm);
@$college_career_fgp=($college_career_fgm/$college_career_fga);
@$college_career_ftp=($college_career_ftm/$college_career_fta);
@$college_career_tgp=($college_career_tgm/$college_career_tga);

echo "      <tr bgcolor=#eeffff><td>College Averages</td><td><center>$college_career_gm</center></td><td><center>";
printf('%01.2f', $college_career_mpg);
echo "</center></td><td><center>";
printf('%01.3f', $college_career_fgp);
echo "</center></td><td><center>";
printf('%01.3f', $college_career_ftp);
echo "</center></td><td><center>";
printf('%01.3f', $college_career_tgp);
echo "</center></td><td><center>";
printf('%01.2f', $college_career_opg);
echo "</center></td><td><center>";
printf('%01.2f', $college_career_rpg);
echo "</center></td><td><center>";
printf('%01.2f', $college_career_apg);
echo "</center></td><td><center>";
printf('%01.2f', $college_career_spg);
echo "</center></td><td><center>";
printf('%01.2f', $college_career_tpg);
echo "</center></td><td><center>";
printf('%01.2f', $college_career_bpg);
echo "</center></td><td><center>";
printf('%01.2f', $college_career_fpg);
echo "</center></td><td><center>";
printf('%01.2f', $college_career_ppg);
echo "</center></td></tr></table></td></tr>";

} else {
}

/*=============== CLOSE OF COLLEGE STATS OUTPUT === */

/* =======================START OF COLLEGE TOURNEY OUTPUT */

$query4="SELECT * FROM ibl_college_tourney_stats WHERE name = '$name' ORDER BY year ASC";
$result4=mysql_query($query4);
@$num4=mysql_numrows($result4);

if ($num4 > 0)
{
echo "      <tr><td colspan=15><hr></td></tr><tr><td colspan=15>
<center><table border=1><tr bgcolor=#cc0000><td colspan=14><center><font color=#ffffff><b>College Tournament Totals</b></font></center></td></tr>
      <tr><th>year</th><th>g</th><th>min</th><th>FGM - FGA</th><th>FTM - FTA</th><th>3GM - 3GA</th><th>orb</th><th>reb</th><th>ast</th><th>stl</th><th>to</th><th>blk</th><th>pf</th><th>pts</th></tr>
";
$c=0;

$college_career_gm=0;
$college_career_min=0;
$college_career_fgm=0;
$college_career_fga=0;
$college_career_ftm=0;
$college_career_fta=0;
$college_career_tgm=0;
$college_career_tga=0;
$college_career_orb=0;
$college_career_reb=0;
$college_career_ast=0;
$college_career_stl=0;
$college_career_tvr=0;
$college_career_blk=0;
$college_career_pf=0;
$college_career_pts=0;

while ($c < $num4)
{
$college_year=mysql_result($result4,$c,"year");
$college_gm=mysql_result($result4,$c,"games");
$college_min=mysql_result($result4,$c,"minutes");
$college_fgm=mysql_result($result4,$c,"fgm");
$college_fga=mysql_result($result4,$c,"fga");
@$college_fgp=($college_fgm/$college_fga);
$college_ftm=mysql_result($result4,$c,"ftm");
$college_fta=mysql_result($result4,$c,"fta");
@$college_ftp=($college_ftm/$college_fta);
$college_tgm=mysql_result($result4,$c,"tgm");
$college_tga=mysql_result($result4,$c,"tga");
@$college_tgp=($college_tgm/$college_tga);
$college_orb=mysql_result($result4,$c,"orb");
$college_reb=mysql_result($result4,$c,"reb");
$college_ast=mysql_result($result4,$c,"ast");
$college_stl=mysql_result($result4,$c,"stl");
$college_tvr=mysql_result($result4,$c,"tvr");
$college_blk=mysql_result($result4,$c,"blk");
$college_pf=mysql_result($result4,$c,"pf");
$college_pts=mysql_result($result4,$c,"fgm")+mysql_result($result4,$c,"fgm")+mysql_result($result4,$c,"ftm")+mysql_result($result4,$c,"tgm");

if (($college_year % 2) == 1)
{
echo "      <tr bgcolor=#ffffff align=center>";
} else {
echo "      <tr bgcolor=#e6e7e2 align=center>";
}
echo "      <td><center>$college_year</center></td><td><center>$college_gm</center></td><td><center>$college_min</center></td><td><center>$college_fgm - $college_fga</center></td><td><center>$college_ftm - $college_fta</center></td><td><center>$college_tgm - $college_tga</center></td><td><center>$college_orb</center></td><td><center>$college_reb</center></td><td><center>$college_ast</center></td><td><center>$college_stl</center></td><td><center>$college_tvr</center></td><td><center>$college_blk</center></td><td><center>$college_pf</center></td><td><center>$college_pts</td></tr>
";

$college_career_gm=$college_career_gm+$college_gm;
$college_career_min=$college_career_min+$college_min;
$college_career_fgm=$college_career_fgm+$college_fgm;
$college_career_fga=$college_career_fga+$college_fga;
$college_career_ftm=$college_career_ftm+$college_ftm;
$college_career_fta=$college_career_fta+$college_fta;
$college_career_tgm=$college_career_tgm+$college_tgm;
$college_career_tga=$college_career_tga+$college_tga;
$college_career_orb=$college_career_orb+$college_orb;
$college_career_reb=$college_career_reb+$college_reb;
$college_career_ast=$college_career_ast+$college_ast;
$college_career_stl=$college_career_stl+$college_stl;
$college_career_tvr=$college_career_tvr+$college_tvr;
$college_career_blk=$college_career_blk+$college_blk;
$college_career_pf=$college_career_pf+$college_pf;
$college_career_pts=$college_career_pts+$college_pts;

$c++;

}

echo "      <tr bgcolor=#eeffff><td>Tourney Totals</td><td><center>$college_career_gm</center></td><td><center>$college_career_min</center></td><td><center>$college_career_fgm - $college_career_fga</center></td><td><center>$college_career_ftm - $college_career_fta</center></td><td><center>$college_career_tgm - $college_career_tga</center></td><td><center>$college_career_orb</center></td><td><center>$college_career_reb</center></td><td><center>$college_career_ast</center></td><td><center>$college_career_stl</center></td><td><center>$college_career_tvr</center></td><td><center>$college_career_blk</center></td><td><center>$college_career_pf</center></td><td><center>$college_career_pts</td></tr>
      <tr bgcolor=#cc0000><td colspan=14><center><font color=#ffffff><b>College Tournament Averages</b></font></center></td></tr>
      <tr><th>year</th><th>g</th><th>min</th><th>FGP</th><th>FTP</th><th>3GP</th><th>orb</th><th>reb</th><th>ast</th><th>stl</th><th>to</th><th>blk</th><th>pf</th><th>pts</th></tr>
";
$c=0;

$college_career_gm=0;
$college_career_min=0;
$college_career_fgm=0;
$college_career_fga=0;
$college_career_ftm=0;
$college_career_fta=0;
$college_career_tgm=0;
$college_career_tga=0;
$college_career_orb=0;
$college_career_reb=0;
$college_career_ast=0;
$college_career_stl=0;
$college_career_tvr=0;
$college_career_blk=0;
$college_career_pf=0;
$college_career_pts=0;

while ($c < $num4)
{
$college_year=mysql_result($result4,$c,"year");
$college_gm=mysql_result($result4,$c,"games");
$college_min=mysql_result($result4,$c,"minutes");
$college_fgm=mysql_result($result4,$c,"fgm");
$college_fga=mysql_result($result4,$c,"fga");
@$college_fgp=($college_fgm/$college_fga);
$college_ftm=mysql_result($result4,$c,"ftm");
$college_fta=mysql_result($result4,$c,"fta");
@$college_ftp=($college_ftm/$college_fta);
$college_tgm=mysql_result($result4,$c,"tgm");
$college_tga=mysql_result($result4,$c,"tga");
@$college_tgp=($college_tgm/$college_tga);
$college_orb=mysql_result($result4,$c,"orb");
$college_reb=mysql_result($result4,$c,"reb");
$college_ast=mysql_result($result4,$c,"ast");
$college_stl=mysql_result($result4,$c,"stl");
$college_tvr=mysql_result($result4,$c,"tvr");
$college_blk=mysql_result($result4,$c,"blk");
$college_pf=mysql_result($result4,$c,"pf");
$college_pts=mysql_result($result4,$c,"fgm")+mysql_result($result4,$c,"fgm")+mysql_result($result4,$c,"ftm")+mysql_result($result4,$c,"tgm");

@$college_mpg=($college_min/$college_gm);
@$college_opg=($college_orb/$college_gm);
@$college_rpg=($college_reb/$college_gm);
@$college_apg=($college_ast/$college_gm);
@$college_spg=($college_stl/$college_gm);
@$college_tpg=($college_tvr/$college_gm);
@$college_bpg=($college_blk/$college_gm);
@$college_fpg=($college_pf/$college_gm);
@$college_ppg=($college_pts/$college_gm);

if (($college_year % 2) == 1)
{
echo "      <tr bgcolor=#ffffff align=center>";
} else {
echo "      <tr bgcolor=#e6e7e2 align=center>";
}
echo "      <td><center>$college_year</center></td><td><center>$college_gm</center></td><td><center>";
printf('%01.2f', $college_mpg);
echo "</center></td><td><center>";
printf('%01.3f', $college_fgp);
echo "</center></td><td><center>";
printf('%01.3f', $college_ftp);
echo "</center></td><td><center>";
printf('%01.3f', $college_tgp);
echo "</center></td><td><center>";
printf('%01.2f', $college_opg);
echo "</center></td><td><center>";
printf('%01.2f', $college_rpg);
echo "</center></td><td><center>";
printf('%01.2f', $college_apg);
echo "</center></td><td><center>";
printf('%01.2f', $college_spg);
echo "</center></td><td><center>";
printf('%01.2f', $college_tpg);
echo "</center></td><td><center>";
printf('%01.2f', $college_bpg);
echo "</center></td><td><center>";
printf('%01.2f', $college_fpg);
echo "</center></td><td><center>";
printf('%01.2f', $college_ppg);
echo "</center></td></tr>";

$college_career_gm=$college_career_gm+$college_gm;
$college_career_min=$college_career_min+$college_min;
$college_career_fgm=$college_career_fgm+$college_fgm;
$college_career_fga=$college_career_fga+$college_fga;
$college_career_ftm=$college_career_ftm+$college_ftm;
$college_career_fta=$college_career_fta+$college_fta;
$college_career_tgm=$college_career_tgm+$college_tgm;
$college_career_tga=$college_career_tga+$college_tga;
$college_career_orb=$college_career_orb+$college_orb;
$college_career_reb=$college_career_reb+$college_reb;
$college_career_ast=$college_career_ast+$college_ast;
$college_career_stl=$college_career_stl+$college_stl;
$college_career_tvr=$college_career_tvr+$college_tvr;
$college_career_blk=$college_career_blk+$college_blk;
$college_career_pf=$college_career_pf+$college_pf;
$college_career_pts=$college_career_pts+$college_pts;

$c++;
}

@$college_career_mpg=($college_career_min/$college_career_gm);
@$college_career_opg=($college_career_orb/$college_career_gm);
@$college_career_rpg=($college_career_reb/$college_career_gm);
@$college_career_apg=($college_career_ast/$college_career_gm);
@$college_career_spg=($college_career_stl/$college_career_gm);
@$college_career_tpg=($college_career_tvr/$college_career_gm);
@$college_career_bpg=($college_career_blk/$college_career_gm);
@$college_career_fpg=($college_career_pf/$college_career_gm);
@$college_career_ppg=($college_career_pts/$college_career_gm);
@$college_career_fgp=($college_career_fgm/$college_career_fga);
@$college_career_ftp=($college_career_ftm/$college_career_fta);
@$college_career_tgp=($college_career_tgm/$college_career_tga);

echo "      <tr bgcolor=#eeffff><td>Tourney Averages</td><td><center>$college_career_gm</center></td><td><center>";
printf('%01.2f', $college_career_mpg);
echo "</center></td><td><center>";
printf('%01.3f', $college_career_fgp);
echo "</center></td><td><center>";
printf('%01.3f', $college_career_ftp);
echo "</center></td><td><center>";
printf('%01.3f', $college_career_tgp);
echo "</center></td><td><center>";
printf('%01.2f', $college_career_opg);
echo "</center></td><td><center>";
printf('%01.2f', $college_career_rpg);
echo "</center></td><td><center>";
printf('%01.2f', $college_career_apg);
echo "</center></td><td><center>";
printf('%01.2f', $college_career_spg);
echo "</center></td><td><center>";
printf('%01.2f', $college_career_tpg);
echo "</center></td><td><center>";
printf('%01.2f', $college_career_bpg);
echo "</center></td><td><center>";
printf('%01.2f', $college_career_fpg);
echo "</center></td><td><center>";
printf('%01.2f', $college_career_ppg);
echo "</center></td></tr></table></td></tr>";

} else {
}

/*=============== CLOSE OF COLLEGE TOURNEY STATS OUTPUT === */



/* =======================CAREER RATINGS BY YEAR */

echo "      <tr><td colspan=15><hr></td></tr><tr><td colspan=15>
<center><table cellspacing=1><tr bgcolor=#dd88dd><td colspan=23><center><b>(Past) Career Ratings by Year</b></center></td></tr>
      <tr><th>year</th><th>2ga</th><th>2gp</th><th>fta</th><th>ftp</th><th>3ga</th><th>3gp</th><th>orb</th><th>drb</th><th>ast</th><th>stl</th><th>tvr</th><th>blk</th><th>oo</th><th>do</th><th>po</th><th>to</th><th>od</th><th>dd</th><th>pd</th><th>td</th><th>Off</th><th>Def</th></tr>
";

/* =======================Loop player historical ratings */

$queryratings="SELECT * FROM nuke_iblhist WHERE name = '$name' ORDER BY year ASC";
$resultratings=mysql_query($queryratings);
@$numratings=mysql_numrows($resultratings);

$kk=0;
$rowcolor=0;
while ($kk < $numratings)
{

$r_year=mysql_result($resultratings,$kk,"year");
$r_2ga=mysql_result($resultratings,$kk,"r_2ga");
$r_2gp=mysql_result($resultratings,$kk,"r_2gp");
$r_fta=mysql_result($resultratings,$kk,"r_fta");
$r_ftp=mysql_result($resultratings,$kk,"r_ftp");
$r_3ga=mysql_result($resultratings,$kk,"r_3ga");
$r_3gp=mysql_result($resultratings,$kk,"r_3gp");
$r_orb=mysql_result($resultratings,$kk,"r_orb");
$r_drb=mysql_result($resultratings,$kk,"r_drb");
$r_ast=mysql_result($resultratings,$kk,"r_ast");
$r_stl=mysql_result($resultratings,$kk,"r_stl");
$r_tvr=mysql_result($resultratings,$kk,"r_tvr");
$r_blk=mysql_result($resultratings,$kk,"r_blk");
$r_oo=mysql_result($resultratings,$kk,"r_oo");
$r_do=mysql_result($resultratings,$kk,"r_do");
$r_po=mysql_result($resultratings,$kk,"r_po");
$r_to=mysql_result($resultratings,$kk,"r_to");
$r_od=mysql_result($resultratings,$kk,"r_od");
$r_dd=mysql_result($resultratings,$kk,"r_dd");
$r_pd=mysql_result($resultratings,$kk,"r_pd");
$r_td=mysql_result($resultratings,$kk,"r_td");
$r_Off=$r_oo+$r_do+$r_po+$r_to;
$r_Def=$r_od+$r_dd+$r_pd+$r_td;

if ($rowcolor == 0)
{
echo "      <tr bgcolor=#ffffff align=center>";
$rowcolor=1;
} else {
echo "      <tr bgcolor=#e6e7e2 align=center>";
$rowcolor=0;
}
echo "      <td><center>$r_year</center></td><td><center>$r_2ga</center></td><td><center>$r_2gp</center></td><td><center>$r_fta</center></td><td><center>$r_ftp</center></td><td><center>$r_3ga</center></td><td><center>$r_3gp</center></td><td><center>$r_orb</center></td><td><center>$r_drb</center></td><td><center>$r_ast</center></td><td><center>$r_stl</center></td><td><center>$r_tvr</center></td><td><center>$r_blk</center></td><td><center>$r_oo</center></td><td><center>$r_do</center></td><td><center>$r_po</center></td><td><center>$r_to</center></td><td><center>$r_od</center></td><td><center>$r_dd</center></td><td><center>$r_pd</center></td><td><center>$r_td</center></td><td><center>$r_Off</center></td><td><center>$r_Def</center></td><tr>
";

$kk++;

}

echo" </table></td></tr>";

/* =======================END RATINGS */


$i++;


}

echo "
</table></td><td bgcolor=#cccccc width=200 valign=top><center><u><b>AWARDS</b></u></center>";

$query4="SELECT * FROM nuke_ibl_awards WHERE name = '$name' ORDER BY year ASC";
$result4=mysql_query($query4);
@$num4=mysql_numrows($result4);

$m=0;

if ($num4 > 0)
{
while ($m < $num4)
{
$award_year=mysql_result($result4,$m,"year");
$award_type=mysql_result($result4,$m,"Award");
echo "$award_year $award_type<br>";

$m++;
}
} else {
echo "No Awards Earned";
}

echo "</td></tr></table>
</body></html>";

}

mysql_close();

?>