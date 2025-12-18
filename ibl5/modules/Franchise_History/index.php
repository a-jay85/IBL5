<?php

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

global $mysqli_db;
$sharedFunctions = new Shared($mysqli_db);
$season = new Season($mysqli_db);

Nuke\Header::header();

$query2 = "SELECT *,
	SUM(ibl_team_win_loss.wins) as five_season_wins,
	SUM(ibl_team_win_loss.losses) as five_season_losses,
	(SUM(ibl_team_win_loss.wins) + SUM(ibl_team_win_loss.losses)) as totalgames,
	ROUND((SUM(ibl_team_win_loss.wins) / (SUM(ibl_team_win_loss.wins) + SUM(ibl_team_win_loss.losses))), 3) as five_season_winpct
FROM ibl_team_history
INNER JOIN ibl_team_win_loss ON ibl_team_win_loss.currentname = ibl_team_history.team_name
WHERE teamid != ?
AND year BETWEEN ? AND ?
GROUP BY currentname
ORDER BY teamid ASC";

$stmt = $mysqli_db->prepare($query2);
if ($stmt === false) {
    throw new \RuntimeException('Failed to prepare query: ' . $mysqli_db->error);
}

$freeAgentsTeamId = \League::FREE_AGENTS_TEAMID;
$fiveSeasonsAgoEndingYear = $season->endingYear - 4;
$endingYear = $season->endingYear;
$stmt->bind_param('iii', $freeAgentsTeamId, $fiveSeasonsAgoEndingYear, $endingYear);
$stmt->execute();
$result2 = $stmt->get_result();

OpenTable();

$k = 0;
$table_echo = '';
while ($row = $result2->fetch_assoc()) {
    $teamid[$k] = $row["teamid"];
    $teamname[$k] = $row["team_name"];
    $teamcity[$k] = $row["team_city"];
    $teamcolor1[$k] = $row["color1"];
    $teamcolor2[$k] = $row["color2"];

    $totwins[$k] = $row["totwins"];
    $totloss[$k] = $row["totloss"];
    $pct[$k] = $row["winpct"];

    $lastFiveSeasonsWins[$k] = $row["five_season_wins"];
    $lastFiveSeasonsLosses[$k] = $row["five_season_losses"];
    $lastFiveSeasonsWinPct[$k] = $row["five_season_winpct"];

    $playoffs[$k] = $row["playoffs"];
    $heat[$k] = $row["heat_titles"];
    $div[$k] = $row["div_titles"];
    $conf[$k] = $row["conf_titles"];
    $ibl[$k] = $row["ibl_titles"];

    $table_echo .= "<tr>
		<td bgcolor=#" . htmlspecialchars($teamcolor1[$k]) . "><a href=\"modules.php?name=Team&op=team&teamID=" . htmlspecialchars($teamid[$k]) . "\"><font color=#" . htmlspecialchars($teamcolor2[$k]) . ">" . htmlspecialchars($teamcity[$k]) . " " . htmlspecialchars($teamname[$k]) . "</a></td>
		<td>" . htmlspecialchars($totwins[$k]) . "</td>
		<td>" . htmlspecialchars($totloss[$k]) . "</td>
		<td>" . htmlspecialchars($pct[$k]) . "</td>
		<td bgcolor=#ddd>" . htmlspecialchars($lastFiveSeasonsWins[$k]) . "</td>
		<td bgcolor=#ddd>" . htmlspecialchars($lastFiveSeasonsLosses[$k]) . "</td>
		<td bgcolor=#ddd>" . htmlspecialchars($lastFiveSeasonsWinPct[$k]) . "</td>
		<td>" . htmlspecialchars($playoffs[$k]) . "</td>
		<td>" . htmlspecialchars($sharedFunctions->getNumberOfTitles($teamname[$k], 'HEAT')) . "</td>
		<td>" . htmlspecialchars($sharedFunctions->getNumberOfTitles($teamname[$k], 'Division')) . "</td>
		<td>" . htmlspecialchars($sharedFunctions->getNumberOfTitles($teamname[$k], 'Conference')) . "</td>
		<td>" . htmlspecialchars($sharedFunctions->getNumberOfTitles($teamname[$k], 'IBL Champions')) . "</td>
	</tr>";

    $k++;
}

$stmt->close();

$text .= "
	<table class=\"sortable\" border=1>
		<tr>
			<th>Team</th>
			<th>All-Time<br>Wins</th>
			<th>All-Time<br>Losses</th>
			<th>All-Time<br>Pct.</th>
			<th bgcolor=#ddd>Last Five<br>Seasons<br>Wins</th>
			<th bgcolor=#ddd>Last Five<br>Seasons<br>Losses</th>
			<th bgcolor=#ddd>Last Five<br>Seasons<br>Pct.</th>
			<th>Playoffs</th>
			<th>H.E.A.T.<br>Titles</th>
			<th>Div.<br>Titles</th>
			<th>Conf.<br>Titles</th>
			<th>IBL<br>Titles</th>
		</tr>
		$table_echo
	</table>";
echo $text;

CloseTable();
Nuke\Footer::footer();
