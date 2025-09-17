<?php

require '../../mainfile.php';

$query = "SELECT * FROM ibl_plr WHERE retired = 0 AND exp > 0 AND cy = 0 AND teamname <> 'Free Agents' ORDER BY ordinal ASC";
$result = $db->sql_query($query);
$num = $db->sql_numrows($result);

echo "<table>";

$i = 0;

while ($i < $num) {
    $name = $db->sql_result($result, $i, "name");
    $team = $db->sql_result($result, $i, "teamname");
    $pos = $db->sql_result($result, $i, "pos");

    $cy = $db->sql_result($result, $i, "cy");
    $cyt = $db->sql_result($result, $i, "cyt");
    $cy1 = $db->sql_result($result, $i, "cy1");
    $cy2 = $db->sql_result($result, $i, "cy2");
    $cy3 = $db->sql_result($result, $i, "cy3");
    $cy4 = $db->sql_result($result, $i, "cy4");
    $cy5 = $db->sql_result($result, $i, "cy5");
    $cy6 = $db->sql_result($result, $i, "cy6");

    echo "<tr><td>$name</td><td>$team</td><td>$pos</td><td>$cy</td><td>$cy1</td><td>$cy2</td><td>$cy3</td><td>$cy4</td><td>$cy5</td><td>$cy6</td></tr>
";

    $i++;

}

echo "</table>
</body></html>";

$db->sql_close();
