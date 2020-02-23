<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die( "Unable to select database");

$query = "SELECT * FROM nuke_ibl_power WHERE TeamID BETWEEN 1 AND 32 ORDER BY TeamID ASC";
$result = mysql_query($query);
$num = mysql_numrows($result);

$queryCurrentYear = 'SELECT value FROM nuke_ibl_settings WHERE name = "Current IBL Season Ending Year"';
$resultCurrentYear = mysql_query($queryCurrentYear);
$currentYear=mysql_result($resultCurrentYear, 0);

$i = 0;
while ($i < $num) {
	$tid = mysql_result($result, $i, "TeamID");
	$Team = mysql_result($result, $i, "Team");

	// Update nuke_ibl_power with each team's season win/loss info
	$queryGames = "SELECT * FROM ibl_schedule WHERE (Visitor = $tid OR Home = $tid) AND BoxID > 0 ORDER BY Date ASC";
	$resultGames = mysql_query($queryGames);
	$numGames = mysql_numrows($resultGames);

	$wins = 0;
	$losses = 0;
	$homewin = 0;
	$homeloss = 0;
	$visitorwin = 0;
	$visitorloss = 0;
	$winpoints = 0;
	$losspoints = 0;

	$j = 0;
	while ($j < $numGames) {
		$visitor = mysql_result($resultGames, $j, "Visitor");
		$VScore = mysql_result($resultGames, $j, "VScore");
		$home = mysql_result($resultGames, $j, "Home");
		$HScore = mysql_result($resultGames, $j, "HScore");

		if ($VScore !== $HScore) { // Ignore tied games since they're usually 0-0 games that haven't yet occurred
			if ($tid == $visitor) {
				// Get opponent's win/loss info for calculating power rankings
				$queryOpponentWinLoss = "SELECT * FROM nuke_ibl_power WHERE TeamID = $home";
				$resultOpponentWinLoss = mysql_query($queryOpponentWinLoss);
				$opponentWins = mysql_result($resultOpponentWinLoss, 0, "win");
				$opponentLosses = mysql_result($resultOpponentWinLoss, 0, "loss");

				if ($VScore > $HScore) {
					$wins++;
					$visitorwin++;
					$winpoints = $winpoints + $opponentWins;
				} else {
					$losses++;
					$visitorloss++;
					$losspoints = $losspoints + $opponentLosses;
				}
			} elseif ($tid == $home) {
				// Get opponent's win/loss info for calculating power rankings
				$queryOpponentWinLoss = "SELECT * FROM nuke_ibl_power WHERE TeamID = $visitor";
				$resultOpponentWinLoss = mysql_query($queryOpponentWinLoss);
				$opponentWins = mysql_result($resultOpponentWinLoss, 0, "win");
				$opponentLosses = mysql_result($resultOpponentWinLoss, 0, "loss");

				if ($VScore > $HScore) {
					$losses++;
					$homeloss++;
					$losspoints = $losspoints + $opponentLosses;
				} else {
					$wins++;
					$homewin++;
					$winpoints = $winpoints + $opponentWins;
				}
			}
		}
		$j++;
	}

	$gb = ($wins / 2) - ($losses / 2);

	$winpoints = $winpoints + $wins;
	$losspoints = $losspoints + $losses;
	$ranking = round(($winpoints / ($winpoints + $losspoints)) * 100, 1);

	$query3 = "UPDATE nuke_ibl_power SET
		win = $wins,
		loss = $losses,
		gb = $gb,
		home_win = $homewin,
		home_loss = $homeloss,
		road_win = $visitorwin,
		road_loss = $visitorloss
		WHERE TeamID = $tid;";
	$result3 = mysql_query($query3);

	// Update nuke_iblteam_win_loss with each team's season win/loss info
	$query4 = "UPDATE nuke_iblteam_win_loss a, nuke_ibl_power b SET
		a.wins = b.win,
		a.losses = b.loss
		WHERE a.currentname = b.Team AND a.year = '".$currentYear."';";
	$result4 = mysql_query($query4);

	// Update nuke_ibl_power with the wins and losses in each team's last 10 games
	list ($lastwins, $lastlosses) = last($tid);
	$query5 = "UPDATE nuke_ibl_power SET
		last_win = $lastwins,
		last_loss = $lastlosses
		WHERE TeamID = $tid;";
	$result5 = mysql_query($query5);

	// Reset Depth Chart sent status
	$query6 = "UPDATE ibl_team_history SET sim_depth = 'No Depth Chart'";
	$result6 = mysql_query($query6);

	// Update teams' total wins in ibl_team_history by summing up a team's wins in nuke_iblteam_win_loss
	$query8 = "UPDATE ibl_team_history a SET totwins = (SELECT SUM(b.wins) FROM nuke_iblteam_win_loss AS b WHERE a.team_name = b.currentname)";
	$result8 = mysql_query($query8);

	// Update teams' total losses in ibl_team_history by summing up a team's losses in nuke_iblteam_win_loss
	$query9 = "UPDATE ibl_team_history a SET totloss = (SELECT SUM(b.losses) FROM nuke_iblteam_win_loss AS b WHERE a.team_name = b.currentname)";
	$result9 = mysql_query($query9);

	// Update teams' win totals in ibl_team_history
	$query10 = "UPDATE ibl_team_history a, nuke_ibl_power b SET a.totwins = a.totwins + b.win where a.teamid = b.TeamID";
	$result10 = mysql_query($query10);

	// Update teams' loss totals in ibl_team_history
	$query11 = "UPDATE ibl_team_history a, nuke_ibl_power b SET a.totloss = a.totloss + b.loss where a.teamid = b.TeamID";
	$result11 = mysql_query($query11);

	// Update teams' win percentage in ibl_team_history
	$query12 = "UPDATE ibl_team_history a SET winpct = a.totwins / (a.totwins + a.totloss)";
	$result12 = mysql_query($query12);


	// Update teams' forum info block with their leading scorer's name
	$query13 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums.forum_stats.pts_lead = (SELECT name FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums.forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by ((stats_fgm-stats_3gm) * 2 + stats_3gm * 3 +stats_ftm) / stats_gm DESC LIMIT 1)";
	$result13 = mysql_query($query13);

	// Update teams' forum info block with their leading scorer's average points per game
	$query14 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums.forum_stats.pts_num = (SELECT round(((stats_fgm - stats_3gm) * 2 + stats_3gm * 3 + stats_ftm) / stats_gm, 1) FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums.forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname ORDER BY ((stats_fgm-stats_3gm) * 2 + stats_3gm * 3 + stats_ftm) / stats_gm DESC LIMIT 1)";
	$result14 = mysql_query($query14);

	// Update teams' forum info block with their leading scorer's player id
	$query15 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums.forum_stats.pts_pid = (SELECT pid FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums.forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname ORDER BY ((stats_fgm - stats_3gm) * 2 + stats_3gm * 3 + stats_ftm) / stats_gm DESC LIMIT 1)";
	$result15 = mysql_query($query15);


	// Update teams' forum info block with their leading rebounder's name
	$query16 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums. forum_stats.reb_lead = (SELECT name FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by (stats_orb+stats_drb)/stats_gm desc limit 1)";
	$result16 = mysql_query($query16);

	// Update teams' forum info block with their leading rebounder's average rebounds per game
	$query17 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums. forum_stats.reb_num = (select round((stats_orb+stats_drb)/stats_gm, 1) FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by (stats_orb+stats_drb)/stats_gm desc limit 1)";
	$result17 = mysql_query($query17);

	// Update teams' forum info block with their leading rebounder's player id
	$query18 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums.forum_stats.reb_pid = (SELECT pid from iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by (stats_orb+stats_drb)/stats_gm desc limit 1)";
	$result18 = mysql_query($query18);


	// Update teams' forum info block with their leading assister's name
	$query20 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums. forum_stats.ast_lead = (SELECT name FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by (stats_ast/stats_gm) desc limit 1)";
	$result20 = mysql_query($query20);

	// Update teams' forum info block with their leading assister's average assists per game
	$query21 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums. forum_stats.ast_num = (select round((stats_ast)/stats_gm, 1) FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by stats_ast/stats_gm desc limit 1)";
	$result21 = mysql_query($query21);

	// Update teams' forum info block with their leading assister's player id
	$query22 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.nuke_iblplyr
SET iblhoops_iblv2forums.forum_stats.ast_pid = (SELECT pid FROM iblhoops_ibl5.nuke_iblplyr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.nuke_iblplyr.teamname order by (stats_ast/stats_gm) desc limit 1)";
	$result22 = mysql_query($query22);


	// Update power ranking list with each team's power ranking score
	$query4 = "UPDATE nuke_ibl_power SET ranking = $ranking WHERE TeamID = $tid;";
	$result4 = mysql_query($query4);

	echo "Updating $Team wins $wins and losses $losses and ranking $ranking<br>";

	$i++;
}

function last($tid)
{
	$query = "SELECT * FROM ibl_schedule WHERE (Visitor = $tid OR Home = $tid) AND (BoxID > 0 AND BoxID != 100000) ORDER BY Date DESC limit 10";
	$result = mysql_query($query);
	$num = mysql_numrows($result);

	$lastwins = 0;
	$lastlosses = 0;

	$i = 0;
	while ($i < $num) {
		$visitor = mysql_result($result, $i, "Visitor");
		$VScore = mysql_result($result, $i, "VScore");
		$home = mysql_result($result, $i, "Home");
		$HScore = mysql_result($result, $i, "HScore");

		if ($VScore !== $HScore) { // Ignore tied games since they're usually 0-0 games that haven't yet occurred
			if ($tid == $visitor) {
				if ($VScore > $HScore) {
					$lastwins++;
				} else {
					$lastlosses++;
				}
			} elseif ($tid == $home) {
				if ($VScore > $HScore) {
					$lastlosses++;
				} else {
					$lastwins++;
				}
			}
		}
		$i++;
	}
	return array($lastwins, $lastlosses);
}
?>
