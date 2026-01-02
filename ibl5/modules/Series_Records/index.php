<?php

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

global $leagueContext;
$teamInfoTable = isset($leagueContext) ? $leagueContext->getTableName('ibl_team_info') : 'ibl_team_info';
$scheduleTable = isset($leagueContext) ? $leagueContext->getTableName('ibl_schedule') : 'ibl_schedule';

function userinfo($username, $bypass = 0)
{
    global $user, $user_prefix, $mysqli_db;
    $commonRepository = new \Services\CommonMysqliRepository($mysqli_db);

    $stmt = $mysqli_db->prepare("SELECT * FROM " . $user_prefix . "_users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result2 = $stmt->get_result();
    $userinfo = $result2->fetch_assoc();
    if (!$bypass) {
        cookiedecode($user);
    }

    $teamlogo = $userinfo['user_ibl_team'];
    $tid = $commonRepository->getTidFromTeamname($teamlogo);

    Nuke\Header::header();
    OpenTable();
    UI::displaytopmenu($mysqli_db, $tid);

    displaySeriesRecords($tid);

    CloseTable();
    Nuke\Footer::footer();
}

function main($user)
{
    global $stop;
    if (!is_user($user)) {
        Nuke\Header::header();
        OpenTable();
        echo "<center><font class=\"title\"><b>" . ($stop ? _LOGININCOR : _USERREGLOGIN) . "</b></font></center>";
        CloseTable();
        echo "<br>";
        if (!is_user($user)) {
            OpenTable();
            loginbox();
            CloseTable();
        }
        Nuke\Footer::footer();
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        userinfo($cookie[1]);
    }
}

function queryTeamInfo()
{
    global $mysqli_db, $teamInfoTable;

    $query = "SELECT teamid, team_city, team_name, color1, color2
		FROM {$teamInfoTable}
		WHERE teamid != 99 AND teamid != " . League::FREE_AGENTS_TEAMID . "
		ORDER BY teamid ASC;";
    $result = $mysqli_db->query($query);
    return $result;
}

function querySeriesRecords()
{
    global $mysqli_db, $scheduleTable;

    $query = "SELECT self, opponent, SUM(wins) AS wins, SUM(losses) AS losses
				FROM (
					SELECT home AS self, visitor AS opponent, COUNT(*) AS wins, 0 AS losses
					FROM {$scheduleTable}
					WHERE HScore > VScore
					GROUP BY self, opponent

					UNION ALL

					SELECT visitor AS self, home AS opponent, COUNT(*) AS wins, 0 AS losses
					FROM {$scheduleTable}
					WHERE VScore > HScore
					GROUP BY self, opponent

					UNION ALL

					SELECT home AS self, visitor AS opponent, 0 AS wins, COUNT(*) AS losses
					FROM {$scheduleTable}
					WHERE HScore < VScore
					GROUP BY self, opponent

					UNION ALL

					SELECT visitor AS self, home AS opponent, 0 AS wins, COUNT(*) AS losses
					FROM {$scheduleTable}
					WHERE VScore < HScore
					GROUP BY self, opponent
				) t
				GROUP BY self, opponent
                ORDER BY self, opponent;";
    $result = $mysqli_db->query($query);
    return $result;
}

function displaySeriesRecords($tid)
{
    global $mysqli_db, $scheduleTable;

    $result = $mysqli_db->query("SELECT MAX(Visitor) as max_visitor FROM {$scheduleTable}");
    $row = $result->fetch_assoc();
    $numteams = $row['max_visitor'];

    echo "<table border=1 class=\"sortable\">
		<tr>
			<th>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&rarr;&rarr;<br>
			vs.<br>
			&uarr;</th>";
    $i = 1;
    while ($i <= $numteams) {
        echo "<th align=\"center\">
			<img src=\"images/logo/new$i.png\" width=50 height=50>
		</th>";
        $i++;
    }
    echo "</tr>";

    $resultSeriesRecords = querySeriesRecords();
    $resultTeamInfo = queryTeamInfo();

    $pointer = 0;
    $tidRow = 1;
    while ($tidRow <= $numteams) {
        $team = $resultTeamInfo->fetch_assoc();
        echo "<tr>
			<td bgcolor=$team[color1]>
				<a href=\"modules.php?name=Team&op=team&teamID=$team[teamid]\">
					<font color=\"$team[color2]\">", ($tid == $tidRow ? "<b>" : ""), "
						$team[team_city] $team[team_name]
					</font>", ($tid == $tidRow ? "</b>" : ""), "
				</a>
			</td>";
        $tidColumn = 1;
        while ($tidColumn <= $numteams) {
            if ($tidRow == $tidColumn) {
                echo "<td align=\"center\">", ($tid == $tidRow ? "<b>" : ""), "x", ($tid == $tidRow ? "</b>" : ""), "</td>";
            } else {
                $row = $resultSeriesRecords->fetch_assoc();
                if ($row['self'] == $tidRow and $row['opponent'] == $tidColumn) {
                    if ($row['wins'] > $row['losses']) {
                        $bgcolor = "#8f8";
                    } elseif ($row['wins'] < $row['losses']) {
                        $bgcolor = "#f88";
                    } else {
                        $bgcolor = "#bbb";
                    }
                    echo "<td align=\"center\" bgcolor=\"$bgcolor\">",
                    (($tid == $tidRow or $tid == $tidColumn) ? "<b>" : ""),
                    "$row[wins] - $row[losses]",
                    (($tid == $tidRow or $tid == $tidColumn) ? "</b>" : ""),
                        "</td>";
                    $pointer++;
                } else {
                    echo "<td align=\"center\">0 - 0</td>";
                    $resultSeriesRecords->data_seek($pointer); // Bring the pointer back since no record was found
                }
            }
            $tidColumn++;
        }
        echo "</tr>";
        $tidRow++;
    }

    echo "</table>";
}

switch ($op) {
    default:
        main($user);
        break;
}
