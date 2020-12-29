<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$query = "SELECT name, COUNT(*) as appearances
FROM nuke_ibl_awards
WHERE Award LIKE '%Conference All-Star'
GROUP BY name;";
$result = mysql_query($query);

echo "<html><head><title>All-Star Appearances</title></head>";
echo "<body>";
echo "<H1>All-Star Appearances</H1>";

echo "<table cellpadding=5 border=1>";

while ($row = mysql_fetch_array($result)) {
echo "<tr>
        <td>".$row[name]."</td>
        <td>".$row[appearances]."</td>
    </tr>";
}

echo "</table>";
echo "</body>";
echo "</html>";
