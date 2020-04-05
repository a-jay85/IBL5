<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_iblleague";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

$query1="SELECT * FROM nuke_iblplyr";
$result1=mysql_query($query1);
$num1=mysql_numrows($result1);

$counter=0;
$i=0;

echo "<HTML><HEAD><TITLE>SEASON LEADERBOARD UPDATE</TITLE></HEAD><BODY>";

  while ($i < $num1)
  {
  $playername = mysql_result($result1,$i,"name");
  $playerid = mysql_result($result1,$i,"pid");

  $query2="SELECT * FROM nuke_iblplyr WHERE name LIKE '$playername'";
  $result2=mysql_query($query2);
  @$num2=mysql_numrows($result2);

  $j=0;
  $tot_games = 0;
  $tot_minutes =0;
  $tot_fgm = 0;
  $tot_fga = 0;
  $tot_ftm = 0;
  $tot_fta = 0;
  $tot_tgm = 0;
  $tot_tga = 0;
  $tot_orb = 0;
  $tot_reb = 0;
  $tot_ast = 0;
  $tot_stl = 0;
  $tot_tvr = 0;
  $tot_blk = 0;
  $tot_pf = 0;
  $tot_pts = 0;

    while ($j < $num2)
    {

    $games = mysql_result($result2,$j,"car_gm");
    $minutes = mysql_result($result2,$j,"car_min");
    $fgm = mysql_result($result2,$j,"car_fgm");
    $fga = mysql_result($result2,$j,"car_fga");
    $ftm = mysql_result($result2,$j,"car_ftm");
    $fta = mysql_result($result2,$j,"car_fta");
    $tgm = mysql_result($result2,$j,"car_tgm");
    $tga = mysql_result($result2,$j,"car_tga");
    $orb = mysql_result($result2,$j,"car_orb");
    $reb = mysql_result($result2,$j,"car_reb");
    $ast = mysql_result($result2,$j,"car_ast");
    $stl = mysql_result($result2,$j,"car_stl");
    $tvr = mysql_result($result2,$j,"car_to");
    $blk = mysql_result($result2,$j,"car_blk");
    $pf = mysql_result($result2,$j,"car_pf");
    $pts = mysql_result($result2,$j,"car_pts");

    $tot_games = $tot_games+$games;
    $tot_minutes =$tot_minutes+$minutes/$tot_games;
    $tot_fgm = $tot_fgm+$fgm/$tot_games;
    $tot_fga = $tot_fga+$fga/$tot_games;
    $tot_fgpct = $fgm/$fga;
    $tot_ftm = $tot_ftm+$ftm/$tot_games;
    $tot_fta = $tot_fta+$fta/$tot_games;
    $tot_ftpct = $ftm/$fta;
    $tot_tgm = $tot_tgm+$tgm/$tot_games;
    $tot_tga = $tot_tga+$tga/$tot_games;
    $tot_tpct = $tgm/$tga;
    $tot_orb = $tot_orb+$orb/$tot_games;
    $tot_reb = $tot_reb+$reb/$tot_games;
    $tot_ast = $tot_ast+$ast/$tot_games;
    $tot_stl = $tot_stl+$stl/$tot_games;
    $tot_tvr = $tot_tvr+$tvr/$tot_games;
    $tot_blk = $tot_blk+$blk/$tot_games;
    $tot_pf = $tot_pf+$pf/$tot_games;
    $tot_pts = $tot_pts+$pts/$tot_games;

    $j++;
    }

  echo "Updating $playername's records... $tot_games total games.<br>";

  $query3="DELETE FROM ibl_season_career_avgs WHERE `name` = '$playername'";
  $result3=mysql_query($query3);

  if ($tot_games > 0)
    {

    $query4="INSERT INTO ibl_season_career_avgs (`pid` , `name` , `games` , `minutes` , `fgm` , `fga` , `fgpct` ,  `ftm` , `fta` , `ftpct` ,  `tgm` , `tga` , `tpct` ,  `orb` , `reb` , `ast` , `stl` , `tvr` , `blk` , `pf` , `pts` ) VALUES ( '$playerid' ,  '$playername' ,  '$tot_games' , '$tot_minutes' , '$tot_fgm' , '$tot_fga' , '$tot_fgpct' , '$tot_ftm' , '$tot_fta' , '$tot_ftpct' , '$tot_tgm' , '$tot_tga' , '$tot_tpct' , '$tot_orb' , '$tot_reb' , '$tot_ast' , '$tot_stl' , '$tot_tvr' , '$tot_blk' , '$tot_pf' , '$tot_pts' ) ";
    $result4=mysql_query($query4);
    $counter=$counter+1;
    }

  $i++;
  }

echo "Updated $counter records</BODY></HTML>";

?>