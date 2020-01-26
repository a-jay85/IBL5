<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_ibl5";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

$query1="SELECT * FROM nuke_iblplyr WHERE `retired` = '0'";
$result1=mysql_query($query1);
$num1=mysql_numrows($result1);

$counter=0;
$i=0;

echo "<HTML><HEAD><TITLE>PRESEASON MINUTES UPDATER</TITLE></HEAD><BODY>";

  while ($i < $num1)
  {
  $playername = mysql_result($result1,$i,"name");
  $playerid = mysql_result($result1,$i,"pid");

  $query2="SELECT * FROM nuke_ibl_preseason_hist WHERE pid LIKE '$playerid'";
  $result2=mysql_query($query2);
  @$num2=mysql_numrows($result2);

  $j=0;
  $tot_minutes =0;

    while ($j < $num2)
    {

    $minutes = mysql_result($result2,$j,"min");
    $tot_minutes =$tot_minutes+$minutes;

    $j++;
    }

  echo "Updating $playername's records... $tot_minutes total minutes.<br>";

  $query3="UPDATE nuke_iblplyr SET `car_preseason_min` = '$tot_minutes' WHERE pid = '$playerid'";
  $result3=mysql_query($query3);

  $i++;
  }

echo "Updated $counter records</BODY></HTML>";

?>