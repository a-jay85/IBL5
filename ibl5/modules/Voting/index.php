
<?php

use Player\Player;
use Player\PlayerStats;

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2002 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/*                                                                      */
/* ibl College Scout Module added by Spencer Cooley                    */
/* 2/2/2005                                                             */
/*                                                                      */
/************************************************************************/

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

function userinfo($username)
{
    global $prefix, $db, $mysqli_db;
    $commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
    $season = new Season($mysqli_db);

    $sql2 = "SELECT * FROM " . $prefix . "_users WHERE username = '$username'";
    $result2 = $db->sql_query($sql2);
    $userinfo = $db->sql_fetchrow($result2);

    Nuke\Header::header();

// === CODE TO INSERT IBL DEPTH CHART ===

    OpenTable();

    function getCandidates($votingCategory, $voterTeamName)
    {
        global $db, $mysqli_db;
        $league = new League($mysqli_db);
        $season = new Season($mysqli_db);

        if ($season->phase == "Regular Season") {
            $result = $league->getAllStarCandidatesResult($votingCategory);
        } else {
            if (strpos($votingCategory, 'MVP') !== false) {
                $result = $league->getMVPCandidatesResult();
            } elseif (strpos($votingCategory, 'Six') !== false) {
                $result = $league->getSixthPersonOfTheYearCandidatesResult();
            } elseif (strpos($votingCategory, 'ROY') !== false) {
                $result = $league->getRookieOfTheYearCandidatesResult();
            } elseif (strpos($votingCategory, 'GM') !== false) {
                $result = $league->getGMOfTheYearCandidatesResult();
            }
        }

        echo "<SCRIPT>
			function ShowAndHide$votingCategory() {
				var x = document.getElementById('$votingCategory');
				if (x.style.display == 'none') {
					x.style.display = 'block';
				} else {
					x.style.display = 'none';
				}
			}
		</SCRIPT>";

        $output = "<table id=\"$votingCategory\" style=\"display:none\" class=\"sortable ibl-data-table voting-form-table\">
			<thead>
				<tr>";

        if ($season->phase == "Regular Season") {
            $output .= "<th>Vote</th>";
        } else {
            $output .= "<th>1st</th>
						<th>2nd</th>
						<th>3rd</th>";
        }

        if ($votingCategory != "GM") {
            $output .= "<th>Name</th>
						<th>GM</th>
						<th>GS</th>
						<th>MIN</th>
						<th>FGM</th>
						<th>FGA</th>
						<th>FG%</th>
						<th>FTM</th>
						<th>FTA</th>
						<th>FT%</th>
						<th>3PM</th>
						<th>3PA</th>
						<th>3P%</th>
						<th>ORB</th>
						<th>DRB</th>
						<th>REB</th>
						<th>AST</th>
						<th>STL</th>
						<th>TO</th>
						<th>BLK</th>
						<th>PF</th>
						<th>PTS</th>
						<th>2x2</th>
						<th>3x2</th>
					</tr>
				</thead>
				<tbody>";
        } else {
            $output .= "<th>Name</th>
						<th>Team</th>
					</tr>
				</thead>
				<tbody>";
        }

        $i = 0;
        foreach ($result as $row) {
            if ($votingCategory != "GM") {
                $player = Player::withPlrRow($db, $row);
                $playerStats = PlayerStats::withPlrRow($db, $row);
                $teamname = $player->teamName;
            } else {
                $name = $row['owner_name'];
                $teamname = $row['team_city'] . " " . $row['team_name'];
            }

            $output .= "<tr>";

            if (!str_contains($teamname, $voterTeamName)) {
                if ($season->phase == "Regular Season") {
                    $output .= "<td><input type=\"checkbox\" name=\"" . $votingCategory . "[]\" value=\"$player->name, $player->teamName\"></td>";
                } else {
                    if ($votingCategory == "GM") {
                        $output .= "<td><input type=\"radio\" name=\"" . $votingCategory . "[1]\" value=\"$name, $teamname\"></td>
                                    <td><input type=\"radio\" name=\"" . $votingCategory . "[2]\" value=\"$name, $teamname\"></td>
                                    <td><input type=\"radio\" name=\"" . $votingCategory . "[3]\" value=\"$name, $teamname\"></td>";
                    } else {
                        $output .= "<td><input type=\"radio\" name=\"" . $votingCategory . "[1]\" value=\"$player->name, $player->teamName\"></td>
                                    <td><input type=\"radio\" name=\"" . $votingCategory . "[2]\" value=\"$player->name, $player->teamName\"></td>
                                    <td><input type=\"radio\" name=\"" . $votingCategory . "[3]\" value=\"$player->name, $player->teamName\"></td>";
                    }
                }
            } else {
                if ($season->phase == "Regular Season") {
                    $output .= "<td></td>";
                } else {
                    $output .= "<td></td>
								<td></td>
								<td></td>";
                }
            }

            if ($votingCategory != "GM") {
                $output .= "<td>$player->name, $player->teamName</td>
							<td>$playerStats->seasonGamesPlayed</td>
							<td>$playerStats->seasonGamesStarted</td>
							<td>$playerStats->seasonMinutesPerGame</td>
							<td>$playerStats->seasonFieldGoalsMadePerGame</td>
							<td>$playerStats->seasonFieldGoalsAttemptedPerGame</td>
							<td>$playerStats->seasonFieldGoalPercentage</td>
							<td>$playerStats->seasonFreeThrowsMadePerGame</td>
							<td>$playerStats->seasonFreeThrowsAttemptedPerGame</td>
							<td>$playerStats->seasonFreeThrowPercentage</td>
							<td>$playerStats->seasonThreePointersMadePerGame</td>
							<td>$playerStats->seasonThreePointersAttemptedPerGame</td>
							<td>$playerStats->seasonThreePointPercentage</td>
							<td>$playerStats->seasonOffensiveReboundsPerGame</td>
							<td>$playerStats->seasonDefensiveReboundsPerGame</td>
							<td>$playerStats->seasonTotalReboundsPerGame</td>
							<td>$playerStats->seasonAssistsPerGame</td>
							<td>$playerStats->seasonStealsPerGame</td>
							<td>$playerStats->seasonTurnoversPerGame</td>
							<td>$playerStats->seasonBlocksPerGame</td>
							<td>$playerStats->seasonPersonalFoulsPerGame</td>
							<td>$playerStats->seasonPointsPerGame</td>
							<td>$playerStats->seasonDoubleDoubles</td>
							<td>$playerStats->seasonTripleDoubles</td>
						</tr>";
            } else {
                $output .= "<td>$name</td>
							<td>$teamname</td>
						</tr>";
            }

            $i++;
        }

        $output .= "</tbody>
			</table>";

        return $output;
    }

    $formName = ($season->phase == "Regular Season") ? "ASGVote" : "EOYVote";
    $voterTeamName = $userinfo['user_ibl_team'];
    $tid = $commonRepository->getTidFromTeamname($voterTeamName);

    $formAction = ($season->phase == "Regular Season") ? "modules/Voting/ASGVote.php" : "modules/Voting/EOYVote.php";
    echo "<form name=\"$formName\" method=\"post\" action=\"$formAction\">
		<div class=\"voting-form-container\">
			<img src=\"images/logo/$tid.jpg\" alt=\"Team Logo\" class=\"team-logo-banner\">";

    echo "<button type=\"submit\" class=\"voting-submit-btn\">Submit Votes!</button>";

    $easternConferenceFrontcourt = $easternConferenceBackcourt = "";
    $westernConferenceFrontcourt = $westernConferenceBackcourt = "";
    $mostValuablePlayers = $sixthPersons = $rookiesOfTheYear = $generalManagers = "";

    if ($season->phase == "Regular Season") {
        $easternConferenceFrontcourt .= getCandidates('ECF', $voterTeamName);
        $easternConferenceBackcourt .= getCandidates('ECB', $voterTeamName);

        $westernConferenceFrontcourt .= getCandidates('WCF', $voterTeamName);
        $westernConferenceBackcourt .= getCandidates('WCB', $voterTeamName);

        echo "<div class=\"voting-category\" onclick=\"ShowAndHideECF()\">
				<h2 class=\"ibl-title voting-category-title\">Eastern Conference Frontcourt</h2>
				<p class=\"voting-category-instruction\">Select FOUR players. Tap/click to reveal/hide nominees.</p>
			</div>
			$easternConferenceFrontcourt

			<div class=\"voting-category\" onclick=\"ShowAndHideECB()\">
				<h2 class=\"ibl-title voting-category-title\">Eastern Conference Backcourt</h2>
				<p class=\"voting-category-instruction\">Select FOUR players. Tap/click to reveal/hide nominees.</p>
			</div>
			$easternConferenceBackcourt

			<div class=\"voting-category\" onclick=\"ShowAndHideWCF()\">
				<h2 class=\"ibl-title voting-category-title\">Western Conference Frontcourt</h2>
				<p class=\"voting-category-instruction\">Select FOUR players. Tap/click to reveal/hide nominees.</p>
			</div>
			$westernConferenceFrontcourt

			<div class=\"voting-category\" onclick=\"ShowAndHideWCB()\">
				<h2 class=\"ibl-title voting-category-title\">Western Conference Backcourt</h2>
				<p class=\"voting-category-instruction\">Select FOUR players. Tap/click to reveal/hide nominees.</p>
			</div>
			$westernConferenceBackcourt";
    } else {
        $mostValuablePlayers .= getCandidates('MVP', $voterTeamName);
        $sixthPersons .= getCandidates('Six', $voterTeamName);
        $rookiesOfTheYear .= getCandidates('ROY', $voterTeamName);
        $generalManagers .= getCandidates('GM', $voterTeamName);

        echo "<div class=\"voting-category\" onclick=\"ShowAndHideMVP()\">
				<h2 class=\"ibl-title voting-category-title\">Most Valuable Player</h2>
				<p class=\"voting-category-instruction\">Select your top THREE choices. Tap/click to reveal/hide nominees.</p>
			</div>
			$mostValuablePlayers

			<div class=\"voting-category\" onclick=\"ShowAndHideSix()\">
				<h2 class=\"ibl-title voting-category-title\">Sixth-Person of the Year</h2>
				<p class=\"voting-category-instruction\">Select your top THREE choices. Tap/click to reveal/hide nominees.</p>
			</div>
			$sixthPersons

			<div class=\"voting-category\" onclick=\"ShowAndHideROY()\">
				<h2 class=\"ibl-title voting-category-title\">Rookie of the Year</h2>
				<p class=\"voting-category-instruction\">Select your top THREE choices. Tap/click to reveal/hide nominees.</p>
			</div>
			$rookiesOfTheYear

			<div class=\"voting-category\" onclick=\"ShowAndHideGM()\">
				<h2 class=\"ibl-title voting-category-title\">General Manager of the Year</h2>
				<p class=\"voting-category-instruction\">Select your top THREE choices. Tap/click to reveal/hide nominees.</p>
			</div>
			$generalManagers";
    }

    echo "<input type=\"hidden\" name=\"teamname\" value=\"$voterTeamName\">

		<button type=\"submit\" class=\"voting-submit-btn\">Submit Votes!</button>
	</div>
	</form>";

    CloseTable();

    Nuke\Footer::footer();
}

function main($user)
{
    global $stop;
    if (!is_user($user)) {
        Nuke\Header::header();
        if ($stop) {
            OpenTable();
            echo "<center><font class=\"title\"><b>" . _LOGININCOR . "</b></font></center>\n";
            CloseTable();
            echo "<br>\n";
        } else {
            OpenTable();
            echo "<center><font class=\"title\"><b>" . _USERREGLOGIN . "</b></font></center>\n";
            CloseTable();
            echo "<br>\n";
        }
        if (!is_user($user)) {
            loginbox();
        }
        Nuke\Footer::footer();
    } elseif (is_user($user)) {
        global $cookie;
        userinfo($cookie[1]);
    }
}

switch ($op) {
    default:
        main($user);
        break;
}

?>
