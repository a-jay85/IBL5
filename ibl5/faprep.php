<?php

require 'mainfile.php';

$query = "SELECT * FROM nuke_iblplyr WHERE retired = 0 ORDER BY ordinal ASC";
$result = $db->sql_query($query);
$num = $db->sql_numrows($result);

$j = 0;

echo "<HTML><HEAD><TITLE>Free Agent Prep</TITLE></HEAD><BODY>
<table><tr><th>ordinal</th><th>name</th><th>age</th><th>teamname</th><th>pos</th><th>coach</th><th>loyalty</th><th>playingTime</th><th>winner</th><th>tradition</th><th>security</th><th>exp</th><th>Sta</th></tr>
";

while ($j < $num) {
    $ordinal = $db->sql_result($result, $j, "ordinal");
    $name = $db->sql_result($result, $j, "name");
    $age = $db->sql_result($result, $j, "age");
    $Stamina = $db->sql_result($result, $j, "Sta");
    $teamname = $db->sql_result($result, $j, "teamname");
    $pos = $db->sql_result($result, $j, "pos");
    $coach = $db->sql_result($result, $j, "coach");
    $loyalty = $db->sql_result($result, $j, "loyalty");
    $playingTime = $db->sql_result($result, $j, "playingTime");
    $winner = $db->sql_result($result, $j, "winner");
    $tradition = $db->sql_result($result, $j, "tradition");
    $security = $db->sql_result($result, $j, "security");
    $exp = $db->sql_result($result, $j, "exp");

    echo "<tr><td>$ordinal</td><td>$name</td><td>$age</td><td>$teamname</td><td>$pos</td><td>$coach</td><td>$loyalty</td><td>$playingTime</td><td>$winner</td><td>$tradition</td><td>$security</td><td>$exp</td><td>$Stamina</td></tr>
";

    $j++;
}

echo "</table>
</BODY></HTML>";

$db->sql_close();
