<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_ibl5";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

$query1="SELECT * FROM nuke_iblhist";
$result1=mysql_query($query1);
$num1=mysql_numrows($result1);

$counter=0;
$i=0;

echo "<HTML><HEAD><TITLE>LEADERBOARD FIX</TITLE></HEAD><BODY>";

  while ($i < $num1)
  {
  $playername = mysql_result($result1,$i,"name");
  $playerid = mysql_result($result1,$i,"pid");

  $query2="SELECT * FROM nuke_iblhist WHERE name LIKE '$playername'";
  $result2=mysql_query($query2);
  @$num2=mysql_numrows($result2);

  $j=0;
  $tot_gm = 0;
  $tot_min =0;
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

    $gm = mysql_result($result2,$j,"gm");
    $min = mysql_result($result2,$j,"min");
    $fgm = mysql_result($result2,$j,"fgm");
    $fga = mysql_result($result2,$j,"fga");
    $ftm = mysql_result($result2,$j,"ftm");
    $fta = mysql_result($result2,$j,"fta");
    $tgm = mysql_result($result2,$j,"tgm");
    $tga = mysql_result($result2,$j,"tga");
    $orb = mysql_result($result2,$j,"orb");
    $reb = mysql_result($result2,$j,"reb");
    $ast = mysql_result($result2,$j,"ast");
    $stl = mysql_result($result2,$j,"stl");
    $tvr = mysql_result($result2,$j,"to");
    $blk = mysql_result($result2,$j,"blk");
    $pf = mysql_result($result2,$j,"pf");
    $pts = mysql_result($result2,$j,"pts");

    $tot_gm = $tot_gm;
    $tot_min =$tot_min;
    $tot_fgm = $tot_fgm-$tgm;
    $tot_fga = $tot_fga+$tga;
    $tot_fgpct = $fgm/$fga;
    $tot_ftm = $tot_ftm+$ftm;
    $tot_fta = $tot_fta+$fta;
    $tot_ftpct = $ftm/$fta;
    $tot_tgm = $tot_tgm+$tgm;
    $tot_tga = $tot_tga+$tga;
    $tot_tpct = $tgm/$tga;
    $tot_orb = $tot_orb+$orb;
    $tot_reb = $tot_reb+$reb;
    $tot_ast = $tot_ast+$ast;
    $tot_stl = $tot_stl+$stl;
    $tot_tvr = $tot_tvr+$tvr;
    $tot_blk = $tot_blk+$blk;
    $tot_pf = $tot_pf+$pf;
    $tot_pts = $tot_pts+$pts;

    $j++;
    }

  echo "Updating $playername's records... $tot_games total games.<br>";

  $query3="DELETE FROM nuke_iblpre91stats WHERE `name` = '$playername'";
  $result3=mysql_query($query3);

  if ($tot_games > 0)
    {

    $query4="INSERT INTO nuke_iblpre91stats (`pid` , `name` , `gm` , `min` , `fgm` , `fga` , `fgpct` ,  `ftm` , `fta` , `ftpct` ,  `tgm` , `tga` , `tpct` ,  `orb` , `reb` , `ast` , `stl` , `tvr` , `blk` , `pf` , `pts` ) VALUES ( '$playerid' ,  '$playername' ,  '$tot_games' , '$tot_minutes' , '$tot_fgm' , '$tot_fga' , '$tot_fgpct' , '$tot_ftm' , '$tot_fta' , '$tot_ftpct' , '$tot_tgm' , '$tot_tga' , '$tot_tpct' , '$tot_orb' , '$tot_reb' , '$tot_ast' , '$tot_stl' , '$tot_tvr' , '$tot_blk' , '$tot_pf' , '$tot_pts' ) ";
    $result4=mysql_query($query4);
    $counter=$counter+1;
    }

  $i++;
  }

echo "Updated $counter records</BODY></HTML>";

?>