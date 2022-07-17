<?php

global $prefix, $db, $sitename, $admin, $module_name, $user, $cookie;

include "header.php";
OpenTable();

error_reporting(E_ERROR);

require 'mainfile.php';

//*******************************
//*** CURRENT YEAR CAP TOTALS ***
//*******************************

echo '<table class=\"sortable\"><tr><th>Team</th><th>Cap</th></tr>';
$getRowsQuery = 'SELECT * FROM ibl_current_cap WHERE tid != 0 AND tid <= 24 ORDER BY tid ASC';
$getRowsResult = $db->sql_query($getRowsQuery);
$numOfTeams = $db->sql_numrows($getRowsResult);

$i = 1;
while ($i < $numOfTeams + 1) {
    $currentCapQuery = "SELECT currentCap FROM ibl_current_cap WHERE tid=$i";
    $currentCapResult = $db->sql_query($currentCapQuery);
    $teamCapTotal = $db->sql_result($currentCapResult, 0);

    $teamnameQuery = "SELECT teamname FROM ibl_plr WHERE tid=$i AND retired=0 LIMIT 1";
    $teamnameResult = $db->sql_query($teamnameQuery);
    $teamname = $db->sql_result($teamnameResult, 0);

    echo '<tr>';
    echo '<td>' . $teamname . '</td>';
    echo '<td>' . $teamCapTotal . '</td>';
    echo '</tr>';
    $i++;
}
echo '</table>';

CloseTable();
include "footer.php";
