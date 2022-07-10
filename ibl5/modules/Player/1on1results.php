<?php

$player2=str_replace("%20", " ", $player);

$query="SELECT * FROM nuke_one_on_one WHERE winner = '$player2' ORDER BY gameid ASC";
$result=$db->sql_query($query);
$num=$db->sql_numrows($result);

$wins=0;
$losses=0;

$i=0;

echo "<small>";

while ($i < $num)
{
$gameid=$db->sql_result($result,$i,"gameid");
$winner=$db->sql_result($result,$i,"winner");
$loser=$db->sql_result($result,$i,"loser");
$winscore=$db->sql_result($result,$i,"winscore");
$lossscore=$db->sql_result($result,$i,"lossscore");

echo "
* def. $loser, $winscore-$lossscore (# $gameid)<br>
";

$wins++;

$i++;
}

$query="SELECT * FROM nuke_one_on_one WHERE loser = '$player2' ORDER BY gameid ASC";
$result=$db->sql_query($query);
$num=$db->sql_numrows($result);
$i=0;

while ($i < $num)
{
$gameid=$db->sql_result($result,$i,"gameid");
$winner=$db->sql_result($result,$i,"winner");
$loser=$db->sql_result($result,$i,"loser");
$winscore=$db->sql_result($result,$i,"winscore");
$lossscore=$db->sql_result($result,$i,"lossscore");

echo "
* lost to $winner, $winscore-$lossscore (# $gameid)<br>
";

$losses++;

$i++;
}

echo "<b><center>Record: $wins - $losses</center></b></small><br>";

$db->sql_close();

?>
