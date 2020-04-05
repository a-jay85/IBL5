<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_iblleague";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");


$queryi="UPDATE ibl_team_history SET div_titles = (SELECT COUNT(*) FROM nuke_ibl_teamawards WHERE nuke_ibl_teamawards.Award like '%Div.%' and ibl_team_history.team_name = nuke_ibl_teamawards.name)";
$resulti=mysql_query($queryi);

$queryj="UPDATE ibl_team_history SET conf_titles = (SELECT COUNT(*) FROM nuke_ibl_teamawards WHERE nuke_ibl_teamawards.Award like '%Conf.%' and ibl_team_history.team_name = nuke_ibl_teamawards.name)";
$resultj=mysql_query($queryj);

$queryk="UPDATE ibl_team_history SET ibl_titles = (SELECT COUNT(*) FROM nuke_ibl_teamawards WHERE nuke_ibl_teamawards.Award like '%World%' and ibl_team_history.team_name = nuke_ibl_teamawards.name)";
$resultk=mysql_query($queryk);

$queryl="UPDATE ibl_team_history SET heat_titles = (SELECT COUNT(*) FROM nuke_ibl_teamawards WHERE nuke_ibl_teamawards.Award like '%H.E.A.T.%' and ibl_team_history.team_name = nuke_ibl_teamawards.name)";
$resultl=mysql_query($queryl);


$query="SELECT * FROM nuke_ibl_power WHERE TeamID BETWEEN 1 AND 32 ORDER BY TeamID ASC";
$result=mysql_query($query);
$num=mysql_numrows($result);

$i=0;

while ($i < $num)
{
	$tid=mysql_result($result,$i,"TeamID");
	$Team=mysql_result($result,$i,"Team");
	$i++;
	list ($wins, $losses, $gb, $homewin, $homeloss, $visitorwin, $visitorloss)=record($tid);
	$query3="UPDATE nuke_ibl_power SET win = $wins, loss = $losses, gb = $gb, home_win = $homewin, home_loss = $homeloss, road_win = $visitorwin, road_loss = $visitorloss WHERE TeamID = $tid;";
	$result3=mysql_query($query3);



	list ($lastwins, $lastlosses)=last($tid);
	$query5="UPDATE nuke_ibl_power SET last_win = $lastwins, last_loss = $lastlosses WHERE TeamID = $tid;";
	$result5=mysql_query($query5);


	$query8="UPDATE ibl_team_history a SET totwins = (SELECT SUM(b.wins)FROM nuke_iblteam_win_loss AS b WHERE a.team_name = b.currentname)";
	$result8=mysql_query($query8);

	$query9="UPDATE ibl_team_history a SET totloss = (SELECT SUM(b.losses)FROM nuke_iblteam_win_loss AS b WHERE a.team_name = b.currentname)";
	$result9=mysql_query($query9);

	$query10="UPDATE ibl_team_history a SET winpct = a.totwins/(a.totwins+a.totloss)";
	$result10=mysql_query($query10);

	$query11="UPDATE ibl_team_history a, nuke_ibl_power b SET a.totwins = a.totwins + b.win where a.teamid = b.TeamID";
	$result11=mysql_query($query11);

	$query12="UPDATE ibl_team_history a, nuke_ibl_power b SET a.totloss = a.totloss + b.loss where a.teamid = b.TeamID";
	$result12=mysql_query($query12);

	$ranking=ranking($tid, $wins, $losses);
	$query4="UPDATE nuke_ibl_power SET ranking = $ranking WHERE TeamID = $tid;";
	$result4=mysql_query($query4);
        
	echo "Updating $Team wins $wins and losses $losses and ranking $ranking<br>";
}

function record ($tid) {
	$query="SELECT * FROM IBL_Schedule WHERE (Visitor = $tid OR Home = $tid) AND BoxID > 0 ORDER BY Date ASC";
	$result=mysql_query($query);
	$num=mysql_numrows($result);
	$wins=0;
	$losses=0;
	$homewin = 0;
	$homeloss = 0;
	$visitorwin = 0;
	$visitorloss = 0;
	$i = 0;
	while ($i < $num) {
		$visitor=mysql_result($result,$i,"Visitor");
		$VScore=mysql_result($result,$i,"VScore");
		$home=mysql_result($result,$i,"Home");
		$HScore=mysql_result($result,$i,"HScore");

		if ($tid == $visitor) {
			if ($VScore > $HScore) {
				$wins=$wins+1;
				$visitorwin=$visitorwin+1;

			}else{
				$losses=$losses+1;
				$visitorloss=$visitorloss+1;
			}
		}else{
			if ($VScore > $HScore) {
				$losses=$losses+1;
				$homeloss=$homeloss+1;
			}else{
				$wins=$wins+1;
				$homewin=$homewin+1;
			}
		}
		$i++;
	}
	$gb=($wins/2)-($losses/2);


	return array($wins,$losses,$gb, $homewin, $homeloss, $visitorwin, $visitorloss);
}

function last ($tid) {
	$query="SELECT * FROM IBL_Schedule WHERE (Visitor = $tid OR Home = $tid) AND BoxID > 0 ORDER BY Date DESC limit 10";
	$result=mysql_query($query);
	$num=mysql_numrows($result);
	$lastwins=0;
	$lastlosses=0;
	$i = 0;
	while ($i < $num) {
		$visitor=mysql_result($result,$i,"Visitor");
		$VScore=mysql_result($result,$i,"VScore");
		$home=mysql_result($result,$i,"Home");
		$HScore=mysql_result($result,$i,"HScore");

		if ($tid == $visitor) {
			if ($VScore > $HScore) {
				$lastwins=$lastwins+1;
			}else{
				$lastlosses=$lastlosses+1;
			}
		}else{
			if ($VScore > $HScore) {
				$lastlosses=$lastlosses+1;
			}else{
				$lastwins=$lastwins+1;
			}
		}
		$i++;
	}
	return array($lastwins,$lastlosses);
}


function ranking ($tid, $wins, $losses) {
	$query="SELECT * FROM IBL_Schedule WHERE Visitor = $tid AND BoxID > 0 ORDER BY Date ASC";
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

	$query="SELECT * FROM IBL_Schedule WHERE Home = $tid AND BoxID > 0 ORDER BY Date ASC";
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