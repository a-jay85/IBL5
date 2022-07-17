<?php

require 'mainfile.php';

$player = $_REQUEST['player'];
$query = "SELECT * FROM nuke_stories WHERE hometext LIKE '%$player%' OR bodytext LIKE '%$player%' ORDER BY time DESC";
$result = $db->sql_query($query);
$num = $db->sql_numrows($result);

$i = 0;

echo "<small>";

while ($i < $num) {
    $sid = $db->sql_result($result, $i, "sid");
    $title = $db->sql_result($result, $i, "title");
    $time = $db->sql_result($result, $i, "time");

    echo "
* <a href=\"modules.php?name=News&file=article&sid=$sid&mode=&order=0&thold=0\">$title</a> ($time)<br>";

    $i++;
}

echo "</small>";

$db->sql_close();
