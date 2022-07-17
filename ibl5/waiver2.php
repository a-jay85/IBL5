<?php

require 'mainfile.php';

$sqlpriority = "SELECT * FROM nuke_ibl_waiver_priority ORDER BY pct ASC";
$resultpriority = $db->sql_query($sqlpriority);
$numpriority = $db->sql_numrows($resultpriority);
$j = 0;while ($j < $numpriority) {$teamgrab = $db->sql_result($resulttake, $j, "team");
    $sqltake = "SELECT * FROM nuke_ibl_waiver_move WHERE `team` = '$teamgrab'";
    $resulttake = $db->sql_query($sqltake);
    $numtake = $db->sql_numrows($resulttake);
    $Timestamp = intval(time());
    $i = 0;while ($i < $numtake) {$pid = $db->sql_result($resulttake, $i, "pid");
        $action = $db->sql_result($resulttake, $i, "action");
        $i++;}
    $j++;}?>  <HTML><HEAD><TITLE>Waiver Processing</TITLE> <meta http-equiv="refresh" content="0;url=http://www.chibul.com/iblv2/waiver2.php"> </HEAD><BODY> Waiver moves processing... </BODY></HTML>
