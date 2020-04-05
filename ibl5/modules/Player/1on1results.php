<?php

require '../../config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$player2=str_replace("%20", " ", $player);

$query="SELECT * FROM nuke_one_on_one WHERE winner = '$player2' ORDER BY gameid ASC";
$result=mysql_query($query);
$num=mysql_numrows($result);

$wins=0;
$losses=0;

$i=0;

echo "<small>";

while ($i < $num)
{
$gameid=mysql_result($result,$i,"gameid");
$winner=mysql_result($result,$i,"winner");
$loser=mysql_result($result,$i,"loser");
$winscore=mysql_result($result,$i,"winscore");
$lossscore=mysql_result($result,$i,"lossscore");

echo "
* def. $loser, $winscore-$lossscore (# $gameid)<br>
";

$wins++;

$i++;
}

$query="SELECT * FROM nuke_one_on_one WHERE loser = '$player2' ORDER BY gameid ASC";
$result=mysql_query($query);
$num=mysql_numrows($result);
$i=0;

while ($i < $num)
{
$gameid=mysql_result($result,$i,"gameid");
$winner=mysql_result($result,$i,"winner");
$loser=mysql_result($result,$i,"loser");
$winscore=mysql_result($result,$i,"winscore");
$lossscore=mysql_result($result,$i,"lossscore");

echo "
* lost to $winner, $winscore-$lossscore (# $gameid)<br>
";

$losses++;

$i++;
}

echo "<b><center>Record: $wins - $losses</center></b></small><br>";

mysql_close();

?>
