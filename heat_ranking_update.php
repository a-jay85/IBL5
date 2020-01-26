<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_ibl5";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

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

	$query3a="UPDATE nuke_heat_win_loss a, nuke_ibl_power b  SET a.wins = b.win, a.losses = b.loss WHERE a.currentname = b.Team and a.year = '2001';";
	$result3a=mysql_query($query3a);


	list ($lastwins, $lastlosses)=last($tid);
	$query5="UPDATE nuke_ibl_power SET last_win = $lastwins, last_loss = $lastlosses WHERE TeamID = $tid;";
	$result5=mysql_query($query5);

	$query6="UPDATE ibl_team_history SET sim_depth = 'No Depth Chart'";
	$result6=mysql_query($query6);



	$query13="UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums.forum_stats.pts_lead = (SELECT name FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums.forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by ((stats_fgm-stats_3gm)*2+stats_3gm*3+stats_ftm)/stats_gm desc limit 1)";
	$result13=mysql_query($query13);

	$query14="UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums.forum_stats.pts_num = (SELECT round(((stats_fgm-stats_3gm)*2+stats_3gm*3+stats_ftm)/stats_gm, 1)FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums.forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by ((stats_fgm-stats_3gm)*2+stats_3gm*3+stats_ftm)/stats_gm desc limit 1)";
	$result14=mysql_query($query14);

	$query15="UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums.forum_stats.pts_pid= (SELECT pid FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums.forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by ((stats_fgm-stats_3gm)*2+stats_3gm*3+stats_ftm)/stats_gm desc limit 1)";
	$result15=mysql_query($query15);










	$query16="UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums. forum_stats.reb_lead = (SELECT name FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by (stats_orb+stats_drb)/stats_gm desc limit 1)";
	$result16=mysql_query($query16);

	$query17="UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums. forum_stats.reb_num = (select round((stats_orb+stats_drb)/stats_gm, 1) FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by (stats_orb+stats_drb)/stats_gm desc limit 1)";
	$result17=mysql_query($query17);

	$query18="UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums.forum_stats.reb_pid= (SELECT pid from iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by (stats_orb+stats_drb)/stats_gm desc limit 1)";
	$result18=mysql_query($query18);



	$query20="UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums. forum_stats.ast_lead = (SELECT name FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by (stats_ast/stats_gm) desc limit 1)";
	$result20=mysql_query($query20);

	$query21="UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums. forum_stats.ast_num = (select round((stats_ast)/stats_gm, 1) FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by stats_ast/stats_gm desc limit 1)";
	$result21=mysql_query($query21);

	$query22="UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums.forum_stats.ast_pid= (SELECT pid FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by (stats_ast/stats_gm) desc limit 1)";
	$result22=mysql_query($query22);


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