<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_iblleague";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

$query="SELECT * FROM nuke_iblplyr WHERE retired = 0 ORDER BY ordinal ASC";
$result=mysql_query($query);
$num=mysql_numrows($result);

$j=0;

echo "<HTML><HEAD><TITLE>Free Agent Prep</TITLE></HEAD><BODY>
<table><tr><th>ordinal</th><th>name</th><th>age</th><th>teamname</th><th>pos</th><th>coach</th><th>loyalty</th><th>playingTime</th><th>winner</th><th>tradition</th><th>security</th><th>exp</th><th>Sta</th></tr>
";

while ($j < $num)
{
$ordinal=mysql_result($result,$j,"ordinal");
$name=mysql_result($result,$j,"name");
$age=mysql_result($result,$j,"age");
$Stamina=mysql_result($result,$j,"Sta");
$teamname=mysql_result($result,$j,"teamname");
$pos=mysql_result($result,$j,"pos");
$coach=mysql_result($result,$j,"coach");
$loyalty=mysql_result($result,$j,"loyalty");
$playingTime=mysql_result($result,$j,"playingTime");
$winner=mysql_result($result,$j,"winner");
$tradition=mysql_result($result,$j,"tradition");
$security=mysql_result($result,$j,"security");
$exp=mysql_result($result,$j,"exp");

echo "<tr><td>$ordinal</td><td>$name</td><td>$age</td><td>$teamname</td><td>$pos</td><td>$coach</td><td>$loyalty</td><td>$playingTime</td><td>$winner</td><td>$tradition</td><td>$security</td><td>$exp</td><td>$Stamina</td></tr>
";

$j++;
}

echo "</table>
</BODY></HTML>";


mysql_close();

?>