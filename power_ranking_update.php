<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die( "Unable to select database");

$query = "SELECT TeamID, Team
	FROM nuke_ibl_power
	WHERE TeamID
	BETWEEN 1 AND 32
	ORDER BY TeamID ASC";
$result = mysql_query($query);
$num = mysql_numrows($result);

$queryCurrentYear = 'SELECT value
	FROM nuke_ibl_settings
	WHERE name = "Current IBL Season Ending Year"';
$resultCurrentYear = mysql_query($queryCurrentYear);
$currentYear=mysql_result($resultCurrentYear, 0);

$i = 0;
while ($i < $num) {
	$tid = mysql_result($result, $i, "TeamID");
	$teamName = mysql_result($result, $i, "Team");

	$queryGames = "SELECT Visitor, Vscore, Home, HScore
		FROM ibl_schedule
		WHERE (Visitor = $tid OR Home = $tid)
		AND (BoxID > 0 AND BoxID != 100000)
		ORDER BY Date ASC";
	$resultGames = mysql_query($queryGames);
	$numGames = mysql_numrows($resultGames);

	$wins = 0;
	$losses = 0;
	$homeWins = 0;
	$homeLosses = 0;
	$awayWins = 0;
	$awayLosses = 0;
	$winPoints = 0;
	$lossPoints = 0;
	$winsInLast10Games = 0;
	$lossesInLast10Games = 0;

	$j = 0;
	while ($j < $numGames) {
		$awayTeam = mysql_result($resultGames, $j, "Visitor");
		$awayTeamScore = mysql_result($resultGames, $j, "VScore");
		$homeTeam = mysql_result($resultGames, $j, "Home");
		$homeTeamScore = mysql_result($resultGames, $j, "HScore");

		if ($awayTeamScore !== $homeTeamScore) { // Ignore tied games since they're usually 0-0 games that haven't yet occurred
			if ($tid == $awayTeam) {
				$queryOpponentWinLoss = "SELECT win, loss
					FROM nuke_ibl_power
					WHERE TeamID = $homeTeam";
				$resultOpponentWinLoss = mysql_query($queryOpponentWinLoss);
				$opponentWins = mysql_result($resultOpponentWinLoss, 0, "win");
				$opponentLosses = mysql_result($resultOpponentWinLoss, 0, "loss");

				if ($awayTeamScore > $homeTeamScore) {
					$wins++;
					$awayWins++;
					$winPoints = $winPoints + $opponentWins;
					if ($j >= $numGames - 10) {
						$winsInLast10Games++;
					}
				} else {
					$losses++;
					$awayLosses++;
					$lossPoints = $lossPoints + $opponentLosses;
					if ($j >= $numGames - 10) {
						$lossesInLast10Games++;
					}
				}
			} elseif ($tid == $homeTeam) {
				$queryOpponentWinLoss = "SELECT win, loss
					FROM nuke_ibl_power
					WHERE TeamID = $awayTeam";
				$resultOpponentWinLoss = mysql_query($queryOpponentWinLoss);
				$opponentWins = mysql_result($resultOpponentWinLoss, 0, "win");
				$opponentLosses = mysql_result($resultOpponentWinLoss, 0, "loss");

				if ($awayTeamScore > $homeTeamScore) {
					$losses++;
					$homeLosses++;
					$lossPoints = $lossPoints + $opponentLosses;
					if ($j >= $numGames - 10) {
						$lossesInLast10Games++;
					}
				} else {
					$wins++;
					$homeWins++;
					$winPoints = $winPoints + $opponentWins;
					if ($j >= $numGames - 10) {
						$winsInLast10Games++;
					}
				}
			}
		}
		$j++;
	}

	$gb = ($wins / 2) - ($losses / 2);

	$winPoints = $winPoints + $wins;
	$lossPoints = $lossPoints + $losses;
	$ranking = round(($winPoints / ($winPoints + $lossPoints)) * 100, 1);

	// Update nuke_ibl_power with each team's win/loss info and current power ranking score
	$query3 = "UPDATE nuke_ibl_power
		SET win = $wins,
			loss = $losses,
			gb = $gb,
			home_win = $homeWins,
			home_loss = $homeLosses,
			road_win = $awayWins,
			road_loss = $awayLosses,
			last_win = $winsInLast10Games,
			last_loss = $lossesInLast10Games,
			ranking = $ranking
		WHERE TeamID = $tid;";
	$result3 = mysql_query($query3);

	echo "Updating $teamName wins $wins and losses $losses and ranking $ranking<br>";

	// Reset Depth Chart sent status
	$query7 = "UPDATE ibl_team_history SET sim_depth = 'No Depth Chart'";
	$result7 = mysql_query($query7);

	// Update nuke_iblteam_win_loss with each team's season win/loss info
	$query4 = "UPDATE nuke_iblteam_win_loss a, nuke_ibl_power b
		SET a.wins = b.win,
			a.losses = b.loss
		WHERE a.currentname = b.Team AND a.year = '".$currentYear."';";
	$result4 = mysql_query($query4);

	// Update teams' total wins in ibl_team_history by summing up a team's wins in nuke_iblteam_win_loss
	$query8 = "UPDATE ibl_team_history a
		SET totwins = (SELECT SUM(b.wins)
		FROM nuke_iblteam_win_loss AS b
		WHERE a.team_name = b.currentname)";
	$result8 = mysql_query($query8);

	// Update teams' total losses in ibl_team_history by summing up a team's losses in nuke_iblteam_win_loss
	$query9 = "UPDATE ibl_team_history a
		SET totloss = (SELECT SUM(b.losses)
		FROM nuke_iblteam_win_loss AS b
		WHERE a.team_name = b.currentname)";
	$result9 = mysql_query($query9);

	// Update teams' win totals in ibl_team_history
	$query10 = "UPDATE ibl_team_history a, nuke_ibl_power b
		SET a.totwins = a.totwins + b.win
		WHERE a.teamid = b.TeamID";
	$result10 = mysql_query($query10);

	// Update teams' loss totals in ibl_team_history
	$query11 = "UPDATE ibl_team_history a, nuke_ibl_power b
		SET a.totloss = a.totloss + b.loss
		WHERE a.teamid = b.TeamID";
	$result11 = mysql_query($query11);

	// Update teams' win percentage in ibl_team_history
	$query12 = "UPDATE ibl_team_history a SET winpct = a.totwins / (a.totwins + a.totloss)";
	$result12 = mysql_query($query12);

	$i++;
}
?>
