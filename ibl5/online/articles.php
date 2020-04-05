<?php

require '../config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$player = $_REQUEST['player'];
$query="SELECT * FROM nuke_stories WHERE hometext LIKE '%$player%' OR bodytext LIKE '%$player%' ORDER BY time DESC";
$result=mysql_query($query);
$num=mysql_numrows($result);

$i=0;

echo "<small>";

while ($i < $num)
{
$sid=mysql_result($result,$i,"sid");
$title=mysql_result($result,$i,"title");
$time=mysql_result($result,$i,"time");

echo "
* <a href=\"modules.php?name=News&file=article&sid=$sid&mode=&order=0&thold=0\">$title</a> ($time)<br>";

$i++;
}

echo "</small>";

mysql_close();

?>
