<?php

require 'mainfile.php';

$queryi="UPDATE ibl_team_history SET div_titles = (SELECT COUNT(*) FROM nuke_ibl_teamawards WHERE nuke_ibl_teamawards.Award like '%Div.%' and ibl_team_history.team_name = nuke_ibl_teamawards.name)";
$resulti=$db->sql_query($queryi);

$queryj="UPDATE ibl_team_history SET conf_titles = (SELECT COUNT(*) FROM nuke_ibl_teamawards WHERE nuke_ibl_teamawards.Award like '%Conf.%' and ibl_team_history.team_name = nuke_ibl_teamawards.name)";
$resultj=$db->sql_query($queryj);

$queryk="UPDATE ibl_team_history SET ibl_titles = (SELECT COUNT(*) FROM nuke_ibl_teamawards WHERE nuke_ibl_teamawards.Award like '%World%' and ibl_team_history.team_name = nuke_ibl_teamawards.name)";
$resultk=$db->sql_query($queryk);

$queryl="UPDATE ibl_team_history SET heat_titles = (SELECT COUNT(*) FROM nuke_ibl_teamawards WHERE nuke_ibl_teamawards.Award like '%H.E.A.T.%' and ibl_team_history.team_name = nuke_ibl_teamawards.name)";
$resultl=$db->sql_query($queryl);


$query="SELECT * FROM nuke_ibl_power WHERE TeamID BETWEEN 1 AND 32 ORDER BY TeamID ASC";
$result=$db->sql_query($query);
$num=$db->sql_numrows($result);

$i=0;

while ($i < $num)
{
	$tid=$db->sql_result($result,$i,"TeamID");
	$Team=$db->sql_result($result,$i,"Team");
	$i++;
	list ($wins, $losses, $gb, $homewin, $homeloss, $visitorwin, $visitorloss)=record($tid);
	$query3="UPDATE nuke_ibl_power SET win = $wins, loss = $losses, gb = $gb, home_win = $homewin, home_loss = $homeloss, road_win = $visitorwin, road_loss = $visitorloss WHERE TeamID = $tid;";
	$result3=$db->sql_query($query3);

	$query3a="UPDATE nuke_iblteam_win_loss a, nuke_ibl_power b  SET a.wins = b.win, a.losses = b.loss WHERE a.currentname = b.Team and a.year = '2015';";
	$result3a=$db->sql_query($query3a);

	list ($lastwins, $lastlosses)=last($tid);
	$query5="UPDATE nuke_ibl_power SET last_win = $lastwins, last_loss = $lastlosses WHERE TeamID = $tid;";
	$result5=$db->sql_query($query5);


	$query8="UPDATE ibl_team_history a SET totwins = (SELECT SUM(b.wins)FROM nuke_iblteam_win_loss AS b WHERE a.team_name = b.currentname)";
	$result8=$db->sql_query($query8);

	$query9="UPDATE ibl_team_history a SET totloss = (SELECT SUM(b.losses)FROM nuke_iblteam_win_loss AS b WHERE a.team_name = b.currentname)";
	$result9=$db->sql_query($query9);

	$query10="UPDATE ibl_team_history a SET winpct = a.totwins/(a.totwins+a.totloss)";
	$result10=$db->sql_query($query10);

	$query11="UPDATE ibl_team_history a, nuke_ibl_power b SET a.totwins = a.totwins + b.win where a.teamid = b.TeamID";
	$result11=$db->sql_query($query11);

	$query12="UPDATE ibl_team_history a, nuke_ibl_power b SET a.totloss = a.totloss + b.loss where a.teamid = b.TeamID";
	$result12=$db->sql_query($query12);


	$query13="UPDATE iblhoops_iblv2forums.v4_forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums.v4_forum_stats.pts_lead = (SELECT name FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums.v4_forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by ((stats_fgm-stats_3gm)*2+stats_3gm*3+stats_ftm)/stats_gm desc limit 1)";
	$result13=$db->sql_query($query13);

	$query14="UPDATE iblhoops_iblv2forums.v4_forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums.v4_forum_stats.pts_num = (SELECT round(((stats_fgm-stats_3gm)*2+stats_3gm*3+stats_ftm)/stats_gm, 1)FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums.v4_forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by ((stats_fgm-stats_3gm)*2+stats_3gm*3+stats_ftm)/stats_gm desc limit 1)";
	$result14=$db->sql_query($query14);

	$query15="UPDATE iblhoops_iblv2forums.v4_forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums.v4_forum_stats.pts_pid= (SELECT pid FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums.v4_forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by ((stats_fgm-stats_3gm)*2+stats_3gm*3+stats_ftm)/stats_gm desc limit 1)";
	$result15=$db->sql_query($query15);




	$query16="UPDATE iblhoops_iblv2forums.v4_forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums. v4_forum_stats.reb_lead = (SELECT name FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums. v4_forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by (stats_orb+stats_drb)/stats_gm desc limit 1)";
	$result16=$db->sql_query($query16);

	$query17="UPDATE iblhoops_iblv2forums.v4_forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums. v4_forum_stats.reb_num = (select round((stats_orb+stats_drb)/stats_gm, 1) FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums. v4_forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by (stats_orb+stats_drb)/stats_gm desc limit 1)";
	$result17=$db->sql_query($query17);

	$query18="UPDATE iblhoops_iblv2forums.v4_forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums.v4_forum_stats.reb_pid= (SELECT pid from iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums.v4_forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by (stats_orb+stats_drb)/stats_gm desc limit 1)";
	$result18=$db->sql_query($query18);

	$query20="UPDATE iblhoops_iblv2forums.v4_forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums. v4_forum_stats.ast_lead = (SELECT name FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums.v4_forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by (stats_ast/stats_gm) desc limit 1)";
	$result20=$db->sql_query($query20);

	$query21="UPDATE iblhoops_iblv2forums.v4_forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums. v4_forum_stats.ast_num = (select round((stats_ast)/stats_gm, 1) FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums. v4_forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by stats_ast/stats_gm desc limit 1)";
	$result21=$db->sql_query($query21);

	$query22="UPDATE iblhoops_iblv2forums.v4_forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums.v4_forum_stats.ast_pid= (SELECT pid FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums. v4_forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by (stats_ast/stats_gm) desc limit 1)";
	$result22=$db->sql_query($query22);

	$ranking=ranking($tid, $wins, $losses);
	$query4="UPDATE nuke_ibl_power SET ranking = $ranking WHERE TeamID = $tid;";
	$result4=$db->sql_query($query4);

	echo "Updating $Team wins $wins and losses $losses and ranking $ranking<br>";
}

function record ($tid)
{
	global $db;

	$query="SELECT * FROM ibl_schedule WHERE (Visitor = $tid OR Home = $tid) AND BoxID < 100000 ORDER BY Date ASC";
	$result=$db->sql_query($query);
	$num=$db->sql_numrows($result);
	$wins=0;
	$losses=0;
	$homewin = 0;
	$homeloss = 0;
	$visitorwin = 0;
	$visitorloss = 0;
	$i = 0;
	while ($i < $num) {
		$visitor=$db->sql_result($result,$i,"Visitor");
		$VScore=$db->sql_result($result,$i,"VScore");
		$home=$db->sql_result($result,$i,"Home");
		$HScore=$db->sql_result($result,$i,"HScore");

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

function last ($tid)
{
	global $db;

	$query="SELECT * FROM ibl_schedule WHERE (Visitor = $tid OR Home = $tid) AND BoxID < 100000 ORDER BY Date DESC limit 10";
	$result=$db->sql_query($query);
	$num=$db->sql_numrows($result);
	$lastwins=0;
	$lastlosses=0;
	$i = 0;
	while ($i < $num) {
		$visitor=$db->sql_result($result,$i,"Visitor");
		$VScore=$db->sql_result($result,$i,"VScore");
		$home=$db->sql_result($result,$i,"Home");
		$HScore=$db->sql_result($result,$i,"HScore");

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


function ranking ($tid, $wins, $losses)
{
	global $db;

	$query="SELECT * FROM ibl_schedule WHERE Visitor = $tid AND BoxID < 100000 ORDER BY Date ASC";
	$result=$db->sql_query($query);
	$num=$db->sql_numrows($result);
	$winpoints=0;
	$losspoints=0;
	$i = 0;
	while ($i < $num) {
		$visitor=$db->sql_result($result,$i,"Visitor");
		$VScore=$db->sql_result($result,$i,"VScore");
		$home=$db->sql_result($result,$i,"Home");
		$HScore=$db->sql_result($result,$i,"HScore");

		$query2="SELECT * FROM nuke_ibl_power WHERE TeamID = $home";
		$result2=$db->sql_query($query2);
		$oppwins=$db->sql_result($result2,0,"win");
		$opploss=$db->sql_result($result2,0,"loss");

		if ($VScore > $HScore) {
			$winpoints=$winpoints+$oppwins;
		}else{
			$losspoints=$losspoints+$opploss;
		}
		$i++;
	}

	$query="SELECT * FROM ibl_schedule WHERE Home = $tid AND BoxID < 100000 ORDER BY Date ASC";
	$result=$db->sql_query($query);
	$num=$db->sql_numrows($result);
	$i = 0;
	while ($i < $num) {
		$visitor=$db->sql_result($result,$i,"Visitor");
		$VScore=$db->sql_result($result,$i,"VScore");
		$home=$db->sql_result($result,$i,"Home");
		$HScore=$db->sql_result($result,$i,"HScore");

		$query2="SELECT * FROM nuke_ibl_power WHERE TeamID = $visitor";
		$result2=$db->sql_query($query2);
		$oppwins=$db->sql_result($result2,0,"win");
		$opploss=$db->sql_result($result2,0,"loss");

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
