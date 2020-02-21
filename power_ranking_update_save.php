<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$query="SELECT * FROM nuke_ibl_power WHERE TeamID BETWEEN 1 AND 32 ORDER BY TeamID ASC";
$result=mysql_query($query);
$num=mysql_numrows($result);

$i=0;

while ($i < $num)
{
	$tid=mysql_result($result,$i,"TeamID");
	$Team=mysql_result($result,$i,"Team");
	$i++;
	list ($wins, $losses, $gb)=record($tid);
	$query3="UPDATE nuke_ibl_power SET win = $wins, loss = $losses, gb = $gb WHERE TeamID = $tid;";

	$result3=mysql_query($query3);
	$ranking=ranking($tid, $wins, $losses);
	$query4="UPDATE nuke_ibl_power SET ranking = $ranking WHERE TeamID = $tid;";
	$result4=mysql_query($query4);
	echo "Updating $Team wins $wins and losses $losses and ranking $ranking<br>";
}

function record ($tid) {
	$query="SELECT * FROM ibl_schedule WHERE (Visitor = $tid OR Home = $tid) AND BoxID > 0 ORDER BY Date ASC";
	$result=mysql_query($query);
	$num=mysql_numrows($result);
	$wins=0;
	$losses=0;
	$i = 0;
	while ($i < $num) {
		$visitor=mysql_result($result,$i,"Visitor");
		$VScore=mysql_result($result,$i,"VScore");
		$home=mysql_result($result,$i,"Home");
		$HScore=mysql_result($result,$i,"HScore");

		if ($tid == $visitor) {
			if ($VScore > $HScore) {
				$wins=$wins+1;
			}else{
				$losses=$losses+1;
			}
		}else{
			if ($VScore > $HScore) {
				$losses=$losses+1;
			}else{
				$wins=$wins+1;
			}
		}
		$i++;
	}
	$gb=($wins/2)-($losses/2);
	return array($wins,$losses,$gb);
}

function ranking ($tid, $wins, $losses) {
	$query="SELECT * FROM ibl_schedule WHERE Visitor = $tid AND BoxID > 0 ORDER BY Date ASC";
	$result=mysql_query($query);
	$num=mysql_numrows($result);
	$winpoints=0;
	$losspoints=0;
	$i = 0;
	while ($i < $num) {
		$visitor=mysql_result($result,$i,"Visitor");
		$VScore=mysql_result($result,$i,"VScore");
		$home=mysql_result($result,$i,"Home");
		$HScore=mysql_result($result,$i,"HScore");

		$query2="SELECT * FROM nuke_ibl_power WHERE TeamID = $home";
		$result2=mysql_query($query2);
		$oppwins=mysql_result($result2,0,"win");
		$opploss=mysql_result($result2,0,"loss");

		if ($VScore > $HScore) {
			$winpoints=$winpoints+$oppwins;
		}else{
			$losspoints=$losspoints+$opploss;
		}
		$i++;
	}

	$query="SELECT * FROM ibl_schedule WHERE Home = $tid AND BoxID > 0 ORDER BY Date ASC";
	$result=mysql_query($query);
	$num=mysql_numrows($result);
	$i = 0;
	while ($i < $num) {
		$visitor=mysql_result($result,$i,"Visitor");
		$VScore=mysql_result($result,$i,"VScore");
		$home=mysql_result($result,$i,"Home");
		$HScore=mysql_result($result,$i,"HScore");

		$query2="SELECT * FROM nuke_ibl_power WHERE TeamID = $visitor";
		$result2=mysql_query($query2);
		$oppwins=mysql_result($result2,0,"win");
		$opploss=mysql_result($result2,0,"loss");

		if ($VScore > $HScore) {
			$losspoints=$losspoints+$opploss;
		}else{
			$winpoints=$winpoints+$oppwins;
		}
		$i++;
	}
	$winpoints=$winpoints+$wins;
	$losspoints=$losspoints+$losses;
	$ranking=round(($winpoints/($winpoints+$losspoints))*100,1);
	return $ranking;
}
?>
