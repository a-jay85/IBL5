<?php

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

function userinfo($username, $bypass = 0, $hid = 0, $url = 0)
{
    global $user, $prefix, $user_prefix, $db;
    $sharedFunctions = new Shared($db);

    $sql = "SELECT * FROM " . $prefix . "_bbconfig";
    $result = $db->sql_query($sql);
    while ($row = $db->sql_fetchrow($result)) {
        $board_config[$row['config_name']] = $row['config_value'];
    }
    $sql2 = "SELECT * FROM " . $user_prefix . "_users WHERE username = '$username'";
    $result2 = $db->sql_query($sql2);
    $userinfo = $db->sql_fetchrow($result2);
    if (!$bypass) {
        cookiedecode($user);
    }

    $teamlogo = $userinfo['user_ibl_team'];
    $tid = $sharedFunctions->getTidFromTeamname($teamlogo);

    Nuke\Header::header();
    OpenTable();
    UI::displaytopmenu($db, $tid);

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
    global $db;

    $query = "SELECT teamid, team_city, team_name, color1, color2
		FROM ibl_team_info
		WHERE teamid != 99 AND teamid != " . League::FREE_AGENTS_TEAMID . "
		ORDER BY teamid ASC;";
    $result = $db->sql_query($query);
    return $result;
}

function querySeriesRecords()
{
    global $db;

    $query = "SELECT self, opponent, SUM(wins) AS wins, SUM(losses) AS losses
				FROM (
					SELECT home AS self, visitor AS opponent, COUNT(*) AS wins, 0 AS losses
					FROM ibl_schedule
					WHERE HScore > VScore
					GROUP BY self, opponent

					UNION ALL

					SELECT visitor AS self, home AS opponent, COUNT(*) AS wins, 0 AS losses
					FROM ibl_schedule
					WHERE VScore > HScore
					GROUP BY self, opponent

					UNION ALL

					SELECT home AS self, visitor AS opponent, 0 AS wins, COUNT(*) AS losses
					FROM ibl_schedule
					WHERE HScore < VScore
					GROUP BY self, opponent

					UNION ALL

					SELECT visitor AS self, home AS opponent, 0 AS wins, COUNT(*) AS losses
					FROM ibl_schedule
					WHERE VScore < HScore
					GROUP BY self, opponent
				) t
				GROUP BY self, opponent;";
    $result = $db->sql_query($query);
    return $result;
}

function displaySeriesRecords($tid)
{
    global $db;

    $numteams = $db->sql_result($db->sql_query("SELECT MAX(Visitor) FROM ibl_schedule;"), 0);

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
        $team = $db->sql_fetch_assoc($resultTeamInfo);
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
                $row = $db->sql_fetch_assoc($resultSeriesRecords);
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
                    mysqli_data_seek($resultSeriesRecords, $pointer); // Bring the pointer back since no record was found
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
