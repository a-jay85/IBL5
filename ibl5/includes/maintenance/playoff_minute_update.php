<?php

require '../../mainfile.php';

$query = "SELECT * FROM ibl_plr WHERE `retired` = '0' ORDER BY ordinal ASC";
$result = $db->sql_query($query);
$num = $db->sql_numrows($result);

$i = 0;
echo "<table><tr><td>Name</td><td>Year</td><td>Playoff Minures</td></tr>";
while ($i < $num) {
    $name = $db->sql_result($result, $i, "name");
    $query2 = "SELECT * FROM ibl_playoff_stats WHERE name='$name' ORDER BY year ASC";
    $result2 = $db->sql_query($query2);
    $num2 = $db->sql_numrows($result2);

    $j = 0;
    $total_minutes = 0;
    while ($j < $num2) {
        $year = $db->sql_result($result2, $j, "year");
        $minutes = $db->sql_result($result2, $j, "minutes");
        $total_minutes = $total_minutes + $minutes;
        echo "<tr><td>$name</td><td>$year</td><td>$minutes</td></tr>";
        $j++;
    }
    echo "<tr><td></td><td></td><td>$total_minutes</td></tr>";
    echo "Updating $name's records... $total_minutes total minutes.<br>";
    $query3 = "UPDATE ibl_plr SET car_playoff_min = '$total_minutes' WHERE name = '$name'";
    $result3 = $db->sql_query($query3);

    $i++;
}
