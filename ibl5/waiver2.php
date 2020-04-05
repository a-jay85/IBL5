<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$sqlpriority = "SELECT * FROM nuke_ibl_waiver_priority ORDER BY pct ASC"; $resultpriority=mysql_query($sqlpriority); $numpriority=mysql_numrows($resultpriority);  $j=0;  while ($j < $numpriority) {   $teamgrab = mysql_result($resulttake,$j,"team");    $sqltake = "SELECT * FROM nuke_ibl_waiver_move WHERE `team` = '$teamgrab'";   $resulttake=mysql_query($sqltake);   $numtake=mysql_numrows($resulttake);    $Timestamp = intval(time());    $i=0;    while ($i < $numtake)   {     $pid = mysql_result($resulttake,$i,"pid");     $action = mysql_result($resulttake,$i,"action");      $i++;   }   $j++; }   ?>  <HTML><HEAD><TITLE>Waiver Processing</TITLE> <meta http-equiv="refresh" content="0;url=http://www.chibul.com/iblv2/waiver2.php"> </HEAD><BODY> Waiver moves processing... </BODY></HTML>
