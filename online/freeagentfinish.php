<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_iblleague";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

$query="SELECT * FROM nuke_iblplyr WHERE retired = 0 AND exp > 0 AND cy = 0 AND teamname <> 'Free Agents' ORDER BY ordinal ASC";
$result=mysql_query($query);
$num=mysql_numrows($result);

echo "<table>";

$i=0;

while ($i < $num)
{
$name=mysql_result($result,$i,"name");
$team=mysql_result($result,$i,"teamname");
$pos=mysql_result($result,$i,"pos");

$cy=mysql_result($result,$i,"cy");
$cyt=mysql_result($result,$i,"cyt");
$cy1=mysql_result($result,$i,"cy1");
$cy2=mysql_result($result,$i,"cy2");
$cy3=mysql_result($result,$i,"cy3");
$cy4=mysql_result($result,$i,"cy4");
$cy5=mysql_result($result,$i,"cy5");
$cy6=mysql_result($result,$i,"cy6");

echo "<tr><td>$name</td><td>$team</td><td>$pos</td><td>$cy</td><td>$cy1</td><td>$cy2</td><td>$cy3</td><td>$cy4</td><td>$cy5</td><td>$cy6</td></tr>
";

$i++;

}


echo "</table>
</body></html>";

mysql_close();

?>