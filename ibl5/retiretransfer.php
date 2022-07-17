<?php

require 'mainfile.php';

$query1 = "SELECT * FROM ibl_playoff_career_totals";
$result1 = $db->sql_query($query1);
$num1 = $db->sql_numrows($result1);

$i = 0;

echo "<HTML><HEAD><TITLE>UPDATE</TITLE></HEAD><BODY>";

while ($i < $num1) {
    $playername = $db->sql_result($result1, $i, "name");
    $playerid = $db->sql_result($result1, $i, "pid");

    $query2 = "SELECT * FROM ibl_plr WHERE name = '$playername'";
    $result2 = $db->sql_query($query2);
    $num2 = $db->sql_numrows($result2);
    $retired = $db->sql_result($result2, 0, "retired");
    echo "Updating $playername's records... retired value is $retired<br>";

    $query3 = "UPDATE ibl_playoff_career_totals SET `retired` = '$retired' WHERE `name` = '$playername'";
    $result3 = $db->sql_query($query3);

    $query4 = "UPDATE ibl_worlds_totals SET `retired` = '$retired' WHERE `name` = '$playername'";
    $result4 = $db->sql_query($query4);
    $i++;
}

echo "</BODY></HTML>";
