<?php
// TODO: Prevent broken SQL strings from being sent in schedule parser

error_reporting(E_ALL);
libxml_use_internal_errors(true);

//*****************************************************************************
//*** ibl_schedule DB UPDATE
//*****************************************************************************
//This section automates the following steps from Gates' simming instructions:
//#8.) From the IBL HTML, open "Schedule.htm" IN INTERNET EXPLORER. Select the entire content of this page and copy it. Then paste into A1 of the "Schedule" tab.
//#9.) In the Schedule tab, copy Column Q and paste into the database and run it.

require 'config.php';
require 'mainfile.php';
$sharedFunctions = new Shared($db);
$season = new Season($db);

$scheduleFilePath = 'ibl/IBL/Schedule.htm';

$schedule = new DOMDocument();
$schedule->loadHTMLFile($scheduleFilePath);
$schedule->preserveWhiteSpace = false;

$rows = $schedule->getElementsByTagName('tr');

function extractDate($rawDate, Season $season)
{
    if ($rawDate != false) {
        if (substr($rawDate, 0, 4) === "Post") {
            $rawDate = substr_replace($rawDate, 'June', 0, 4); // TODO: recognize "Post" instead of hacking it into June
        }
        
        $month = ltrim(date('m', strtotime($rawDate)), '0');
        $day = ltrim(date('d', strtotime($rawDate)), '0');
        $year = date('Y', strtotime($rawDate));

        if ($season->phase == "Preseason") {
            $month = Season::IBL_PRESEASON_MONTH;
        } elseif ($season->phase == "HEAT") {
            $month = Season::IBL_HEAT_MONTH;
        }
        
        $date = $year . "-" . $month . "-" . $day;
        
        $dateArray = array(
            "date" => $date,
            "year" => $year,
            "month" => $month,
            "day" => $day);
        return $dateArray;
    }
}

function extractBoxID($boxHREF)
{
    $boxID = ltrim(rtrim($boxHREF, '.htm'), 'box');
    return $boxID;
}

function assignGroupingsFor($region)
{
    if (in_array($region, array("Eastern", "Western"))) {
        $grouping = 'conference';
        $groupingGB = 'confGB';
        $groupingMagicNumber = 'confMagicNumber';
    }
    if (in_array($region, array("Atlantic", "Central", "Midwest", "Pacific"))) {
        $grouping = 'division';
        $groupingGB = 'divGB';
        $groupingMagicNumber = 'divMagicNumber';
    }
    return array($grouping, $groupingGB, $groupingMagicNumber);
}

echo 'Updating the ibl_schedule database table...<p>';
if ($db->sql_query('TRUNCATE TABLE ibl_schedule')) {
    echo 'TRUNCATE TABLE ibl_schedule<p>';
}

foreach ($rows as $row) {
    $checkThirdCell = $row->childNodes->item(2)->nodeValue;
    $checkSecondCell = $row->childNodes->item(1)->nodeValue;
    $checkFirstCell = $row->childNodes->item(0)->nodeValue;

    if ($checkSecondCell === null/*AND substr($checkFirstCell,0,4) !== "Post"*/) {
        $fullDate = extractDate($row->textContent, $season);
        $date = $fullDate['date'];
        $year = $fullDate['year'];
    }

    if ($checkThirdCell !== null and $checkThirdCell !== "" and $checkFirstCell !== "visitor") {
        if ($row->childNodes->item(1)->getElementsByTagName('a')->length !== 0) {
            $boxLink = $row->childNodes->item(1)->getElementsByTagName('a')->item(0)->getAttribute('href');
            $boxID = extractBoxID($boxLink);
        }

        $visitorName = rtrim($row->childNodes->item(0)->textContent);
        $vScore = $row->childNodes->item(1)->textContent;
        $homeName = rtrim($row->childNodes->item(2)->textContent);
        $hScore = $row->childNodes->item(3)->textContent;

        if ($row->childNodes->item(1)->nodeValue === null or $row->childNodes->item(1)->nodeValue === "") {
            $vScore = 0;
            $hScore = 0;
            if ($boxID > 99999 or $boxID === null) {
                $boxID = $boxID + 1;
            } else {
                $boxID = 100000;
            }
        }

        $visitorTID = $sharedFunctions->getTidFromTeamname($visitorName);
        $homeTID = $sharedFunctions->getTidFromTeamname($homeName);
    }
    
    if (
        $vScore != 0
        AND $hScore != 0
        AND $boxID == NULL
    ) {
        echo "<b><font color=red>Script Error: box scores for games haven't been generated.<br>
            Please delete and reupload the JSB HTML export with the box scores, then try again.</font></b>";
        die();
    }

    $sqlQueryString = "INSERT INTO ibl_schedule (
		Year,
		BoxID,
		Date,
		Visitor,
		Vscore,
		Home,
		Hscore
	)
	VALUES (
		$year,
		$boxID,
		'$date',
		$visitorTID,
		$vScore,
		$homeTID,
		$hScore
	)";
    /* ON DUPLICATE KEY UPDATE
    Year = $year,
    Date = '$date',
    Visitor = $visitorTID,
    Vscore = $vScore,
    Home = $homeTID,
    Hscore = $hScore
    "; */

    if ($visitorTID != NULL AND $homeTID != NULL) {
        if ($db->sql_query($sqlQueryString)) {
            echo $sqlQueryString . '<br>';
        }
    }

    unset($visitorName,
        $homeName,
        $boxLink,
        $hScore,
        $vScore,
        $homeName,
        $visitorName,
        $homeTID,
        $visitorTID);
}

echo 'ibl_schedule database table has been updated.<p>';

// TODO:
// Standings variables to derive from Schedule: last 10, streak
// New variables: rival conf w/l, >.500 w/l, <.500 w/l

//*****************************************************************************
//*** ibl_standings DB UPDATE
//*****************************************************************************
//This section stores Standings values in a database table called 'ibl_standings' so that they can be retrieved quickly.
//The file 'block-AJstandings.php' relies on 'ibl_standings' to automate the sidebar standings display.

function extractWins($var)
{
    $var = rtrim(substr($var, 0, 2), '-');
    return $var;
}

function extractLosses($var)
{
    $var = ltrim(substr($var, -2, 2), '-');
    return $var;
}

echo '<p>Updating the ibl_standings database table...<p>';
if ($db->sql_query('TRUNCATE TABLE ibl_standings')) {
    echo 'TRUNCATE TABLE ibl_standings<p>';
}

function extractStandingsValues()
{
    global $db, $sharedFunctions;

    echo '<p>Updating the conference standings for all teams...<p>';

    $standingsFilePath = 'ibl/IBL/Standings.htm';

    $standings = new DOMDocument();
    $standings->loadHTMLFile($standingsFilePath);
    $standings->preserveWhiteSpace = false;

    $getRows = $standings->getElementsByTagName('tr');
    $rowsByConference = $getRows->item(0)->childNodes->item(0)->childNodes->item(0)->childNodes;
    $rowsByDivision = $getRows->item(0)->childNodes->item(1)->childNodes->item(0)->childNodes;

    foreach ($rowsByConference as $row) {
        if (!is_null($row->childNodes)) {
            $teamName = $row->childNodes->item(0)->nodeValue;
            if (in_array($teamName, array("Eastern", "Western"))) {
                $conference = $teamName;
            }
            if (!in_array($teamName, array("Eastern", "Western", "team", ""))) {
                $tid = $sharedFunctions->getTidFromTeamname($teamName);
                $leagueRecord = $row->childNodes->item(1)->nodeValue;
                $pct = $row->childNodes->item(2)->nodeValue;
                $confGB = $row->childNodes->item(3)->nodeValue;
                $confRecord = $row->childNodes->item(4)->nodeValue;
                $divRecord = $row->childNodes->item(5)->nodeValue;
                $homeRecord = $row->childNodes->item(6)->nodeValue;
                $awayRecord = $row->childNodes->item(7)->nodeValue;

                $confWins = extractWins($confRecord);
                $confLosses = extractLosses($confRecord);
                $divWins = extractWins($divRecord);
                $divLosses = extractLosses($divRecord);
                $homeWins = extractWins($homeRecord);
                $homeLosses = extractLosses($homeRecord);
                $awayWins = extractWins($awayRecord);
                $awayLosses = extractLosses($awayRecord);

                $gamesUnplayed = 82 - $homeWins - $homeLosses - $awayWins - $awayLosses; // TODO: make number of games in season dynamic

                $sqlQueryString = "INSERT INTO ibl_standings (
					tid,
					team_name,
					leagueRecord,
					pct,
					gamesUnplayed,
					conference,
					confGB,
					confRecord,
					divRecord,
					homeRecord,
					awayRecord,
					confWins,
					confLosses,
					divWins,
					divLosses,
					homeWins,
					homeLosses,
					awayWins,
					awayLosses
				)
				VALUES (
					'" . $tid . "',
					'" . rtrim($teamName) . "',
					'" . $leagueRecord . "',
					'" . $pct . "',
					'" . $gamesUnplayed . "',
					'" . $conference . "',
					'" . $confGB . "',
					'" . $confRecord . "',
					'" . $divRecord . "',
					'" . $homeRecord . "',
					'" . $awayRecord . "',
					'" . $confWins . "',
					'" . $confLosses . "',
					'" . $divWins . "',
					'" . $divLosses . "',
					'" . $homeWins . "',
					'" . $homeLosses . "',
					'" . $awayWins . "',
					'" . $awayLosses . "'
				)
				ON DUPLICATE KEY UPDATE
					tid = '" . $tid . "',
					leagueRecord = '" . $leagueRecord . "',
					pct = '" . $pct . "',
					gamesUnplayed = '" . $gamesUnplayed . "',
					conference = '" . $conference . "',
					confGB = '" . $confGB . "',
					confRecord = '" . $confRecord . "',
					divRecord = '" . $divRecord . "',
					homeRecord = '" . $homeRecord . "',
					awayRecord = '" . $awayRecord . "',
					confWins = '" . $confWins . "',
					confLosses = '" . $confLosses . "',
					divWins = '" . $divWins . "',
					divlosses = '" . $divLosses . "',
					homeWins = '" . $homeWins . "',
					homeLosses = '" . $homeLosses . "',
					awayWins = '" . $awayWins . "',
					awayLosses = '" . $awayLosses . "'
				";

                if ($db->sql_query($sqlQueryString)) {
                    echo $sqlQueryString . '<br>';
                } else {
                    die('Invalid query: ' . $db->sql_error());
                }
            }
        }
    }
    echo '<p>Conference standings have been updated.<p>';

    echo '<p>Updating the division games back for all teams...<br>';
    foreach ($rowsByDivision as $row) {
        if (!is_null($row->childNodes)) {
            $teamName = $row->childNodes->item(0)->nodeValue;
            $teamID = $sharedFunctions->getTidFromTeamname($teamName);

            if (in_array($teamName, array("Atlantic", "Central", "Midwest", "Pacific"))) {
                $division = $teamName;
            }
            if (!in_array($teamName, array("Atlantic", "Central", "Midwest", "Pacific", "team", ""))) {
                $divGB = $row->childNodes->item(3)->nodeValue;

                $sqlQueryString = "INSERT INTO ibl_standings (
                    tid,
					team_name,
					division,
					divGB
				)
				VALUES (
                    '$teamID',
					'$teamName',
					'$division',
					'$divGB'
				)
				ON DUPLICATE KEY UPDATE
					division = '$division',
					divGB = '$divGB'";

                if ($db->sql_query($sqlQueryString)) {
                    echo $sqlQueryString . '<br>';
                } else {
                    die('Invalid query: ' . $db->sql_error());
                }
            }
        }
    }
    echo 'Division standings have been updated.<p>';
}

function checkIfRegionIsClinched($region)
{
    global $db, $sharedFunctions;

    list($grouping, $groupingGB, $groupingMagicNumber) = assignGroupingsFor($region);
    echo "<p>Checking if the $region $grouping has been clinched...<br>";

    $queryWinningestTeam = "SELECT team_name, homeWins + awayWins AS wins
		FROM ibl_standings
		WHERE $grouping = '$region'
		ORDER BY wins DESC
		LIMIT 1;";
    $resultWinningestTeam = $db->sql_query($queryWinningestTeam);
    $winningestTeamName = $db->sql_result($resultWinningestTeam, 0, "team_name");
    $winningestTeamWins = $db->sql_result($resultWinningestTeam, 0, "wins");

    $queryLeastLosingestTeam = "SELECT homeLosses + awayLosses AS losses
		FROM ibl_standings
		WHERE $grouping = '$region'
			AND team_name != '$winningestTeamName'
		ORDER BY losses ASC
		LIMIT 1;";
    $resultLeastLosingestTeam = $db->sql_query($queryLeastLosingestTeam);
    $leastLosingestTeamLosses = $db->sql_result($resultLeastLosingestTeam, 0, "losses");

    $magicNumber = 82 + 1 - $winningestTeamWins - $leastLosingestTeamLosses;

    if ($magicNumber <= 0) {
        $querySetTeamToClinched = "UPDATE ibl_standings
			SET clinched" . ucfirst($grouping) . " = 1
			WHERE team_name = '$winningestTeamName';";

        if ($db->sql_query($querySetTeamToClinched)) {
            echo "The $winningestTeamName have clinched the $region $grouping!";
        }
    } else {
        echo "Nope: the $region $grouping is still up for grabs!<p>";
    }
}

function checkIfPlayoffsClinched($conference)
{
    global $db, $sharedFunctions;

    echo "<p>Checking if any teams have clinched playoff spots in the $conference Conference...<br>";

    $queryEightWinningestTeams = "SELECT team_name, homeWins + awayWins AS wins
		FROM ibl_standings
		WHERE conference = '$conference'
		ORDER BY wins DESC
		LIMIT 8;";
    $resultEightWinningestTeams = $db->sql_query($queryEightWinningestTeams);

    $querySixLosingestTeams = "SELECT homeLosses + awayLosses AS losses
		FROM ibl_standings
		WHERE conference = '$conference'
		ORDER BY losses DESC
		LIMIT 6;";
    $resultSixLosingestTeams = $db->sql_query($querySixLosingestTeams);

    $i = 0;
    while ($i < 8) {
        $contendingTeamName = $db->sql_result($resultEightWinningestTeams, $i, "team_name");
        $contendingTeamWins = $db->sql_result($resultEightWinningestTeams, $i, "wins");
        $teamsEliminated = 0;

        $j = 0;
        while ($j < 6) {
            $bottomTeamLosses = $db->sql_result($resultSixLosingestTeams, $j, "losses");

            $magicNumber = 82 + 1 - $contendingTeamWins - $bottomTeamLosses;

            if ($magicNumber <= 0) {
                $teamsEliminated++;
            }

            $j++;
        }

        if ($teamsEliminated == 6) {
            $querySetTeamToClinched = "UPDATE ibl_standings
				SET clinchedPlayoffs = 1
				WHERE team_name = '$contendingTeamName';";

            if ($db->sql_query($querySetTeamToClinched)) {
                echo "The $contendingTeamName have clinched a playoff spot!<br>";
            }
        }

        $i++;
    }
}

function updateMagicNumbers($region)
{
    global $db;

    echo "<p>Updating the magic numbers for the $region...<br>";
    list($grouping, $groupingGB, $groupingMagicNumber) = assignGroupingsFor($region);

    $query = "SELECT tid, team_name, homeWins, homeLosses, awayWins, awayLosses
		FROM ibl_standings
		WHERE $grouping = '$region'
		ORDER BY pct DESC";
    $result = $db->sql_query($query);
    $limit = $db->sql_numrows($result);

    $i = 0;
    while ($i < $limit) {
        $teamID = $db->sql_result($result, $i, 0);
        $teamName = $db->sql_result($result, $i, 1);
        $teamTotalWins = $db->sql_result($result, $i, 2) + $db->sql_result($result, $i, 4);
        if ($i + 1 != $limit) {
            $belowTeamTotalLosses = $db->sql_result($result, $i + 1, 3) + $db->sql_result($result, $i + 1, 5);
        } else {
            $belowTeamTotalLosses = 0; // This results in an inaccurate Magic Number for the bottom team in the $region, but prevents query errors
        }
        $magicNumber = 82 + 1 - $teamTotalWins - $belowTeamTotalLosses; // TODO: Make number of games in a season dynamic

        $sqlQueryString = "INSERT INTO ibl_standings (
            tid,
			team_name,
			$groupingMagicNumber
		)
		VALUES (
            '$teamID',
			'$teamName',
			'$magicNumber'
		)
		ON DUPLICATE KEY UPDATE
			$groupingMagicNumber = '$magicNumber'";

        if ($db->sql_query($sqlQueryString)) {
            echo $sqlQueryString . '<br>';
        } else {
            die('Invalid query: ' . $db->sql_error());
        }

        $i++;
    }

    checkIfRegionIsClinched($region);
    if ($grouping == 'conference') {
        checkIfPlayoffsClinched($region);
    }

    echo "<p>Magic numbers for the $region $grouping have been updated.<p>";
}

extractStandingsValues();

updateMagicNumbers('Eastern');
updateMagicNumbers('Western');
updateMagicNumbers('Atlantic');
updateMagicNumbers('Central');
updateMagicNumbers('Midwest');
updateMagicNumbers('Pacific');
echo '<p>Magic numbers for all teams have been updated.<p>';

echo '<p>The ibl_schedule and ibl_standings table have been updated.<p>';

//*****************************************************************************
//*** POWER RANKINGS UPDATE
//*****************************************************************************
//This section updates ibl_power. This replaces power_ranking_update.php.

echo '<p>Updating the ibl_power database table...<p>';

$queryTeams = "SELECT TeamID, Team, streak_type, streak
	FROM ibl_power
	WHERE TeamID
	BETWEEN 1 AND 32
	ORDER BY TeamID ASC";
$resultTeams = $db->sql_query($queryTeams);
$numTeams = $db->sql_numrows($resultTeams);

$i = 0;
while ($i < $numTeams) {
    $tid = $db->sql_result($resultTeams, $i, "TeamID");
    $teamName = $db->sql_result($resultTeams, $i, "Team");

    if ($season->phase == "Preseason") {
        $month = " " . Season::IBL_PRESEASON_MONTH;
    } elseif ($season->phase == "HEAT") {
        $month = Season::IBL_HEAT_MONTH;
    } else {
        $month = Season::IBL_REGULAR_SEASON_STARTING_MONTH;
    }

    $queryGames = "SELECT Visitor, VScore, Home, HScore
		FROM ibl_schedule
		WHERE (Visitor = $tid OR Home = $tid)
		AND (BoxID > 0 AND BoxID < 100000)
		AND Date BETWEEN '" . ($season->beginningYear) . "-$month-01' AND '$season->endingYear-05-30'
		ORDER BY Date ASC";

    $resultGames = $db->sql_query($queryGames);
    $numGames = $db->sql_numrows($resultGames);

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
    $streak = 0;

    $j = 0;
    while ($j < $numGames) {
        $awayTeam = $db->sql_result($resultGames, $j, "Visitor");
        $awayTeamScore = $db->sql_result($resultGames, $j, "VScore");
        $homeTeam = $db->sql_result($resultGames, $j, "Home");
        $homeTeamScore = $db->sql_result($resultGames, $j, "HScore");
        if ($awayTeamScore !== $homeTeamScore) { // Ignore tied games since they're usually 0-0 games that haven't yet occurred
            if ($tid == $awayTeam) {
                $queryOpponentWinLoss = "SELECT win, loss
					FROM ibl_power
					WHERE TeamID = $homeTeam";
                $resultOpponentWinLoss = $db->sql_query($queryOpponentWinLoss);
                $opponentWins = $db->sql_result($resultOpponentWinLoss, 0, "win");
                $opponentLosses = $db->sql_result($resultOpponentWinLoss, 0, "loss");

                if ($awayTeamScore > $homeTeamScore) {
                    $wins++;
                    $awayWins++;
                    $winPoints = $winPoints + $opponentWins;
                    if ($j >= $numGames - 10) {
                        $winsInLast10Games++;
                    }
                    $streak = ($streakType == "W") ? ++$streak : 1;
                    $streakType = "W";
                } else {
                    $losses++;
                    $awayLosses++;
                    $lossPoints = $lossPoints + $opponentLosses;
                    if ($j >= $numGames - 10) {
                        $lossesInLast10Games++;
                    }
                    $streak = ($streakType == "L") ? ++$streak : 1;
                    $streakType = "L";
                }
            } elseif ($tid == $homeTeam) {
                $queryOpponentWinLoss = "SELECT win, loss
					FROM ibl_power
					WHERE TeamID = $awayTeam";
                $resultOpponentWinLoss = $db->sql_query($queryOpponentWinLoss);
                $opponentWins = $db->sql_result($resultOpponentWinLoss, 0, "win");
                $opponentLosses = $db->sql_result($resultOpponentWinLoss, 0, "loss");

                if ($awayTeamScore > $homeTeamScore) {
                    $losses++;
                    $homeLosses++;
                    $lossPoints = $lossPoints + $opponentLosses;
                    if ($j >= $numGames - 10) {
                        $lossesInLast10Games++;
                    }
                    $streak = ($streakType == "L") ? ++$streak : 1;
                    $streakType = "L";
                } else {
                    $wins++;
                    $homeWins++;
                    $winPoints = $winPoints + $opponentWins;
                    if ($j >= $numGames - 10) {
                        $winsInLast10Games++;
                    }
                    $streak = ($streakType == "W") ? ++$streak : 1;
                    $streakType = "W";
                }
            }
        }
        $j++;
    }

    $gb = ($wins / 2) - ($losses / 2);

    $winPoints = $winPoints + $wins;
    $lossPoints = $lossPoints + $losses;
    $ranking = ($winPoints + $lossPoints) ? round(($winPoints / ($winPoints + $lossPoints)) * 100, 1) : 0;

    // Update ibl_power with each team's win/loss info and current power ranking score
    $query3 = "UPDATE ibl_power
		SET win = $wins,
			loss = $losses,
			gb = $gb,
			home_win = $homeWins,
			home_loss = $homeLosses,
			road_win = $awayWins,
			road_loss = $awayLosses,
			last_win = $winsInLast10Games,
			last_loss = $lossesInLast10Games,
			streak_type = '$streakType',
			streak = $streak,
			ranking = $ranking
		WHERE TeamID = $tid;";
    $result3 = $db->sql_query($query3);

    echo "Updating $teamName: $wins wins, $losses losses, $gb games back, $homeWins home wins, $homeLosses home losses, $awayWins away wins, $awayLosses away losses, streak = $streakType$streak, last 10 = $winsInLast10Games-$lossesInLast10Games, ranking score = $ranking<br>";

    // Update ibl_team_win_loss with each team's season win/loss info
    $query4 = "UPDATE ibl_team_win_loss a, ibl_power b
		SET a.wins = b.win,
			a.losses = b.loss
		WHERE a.currentname = b.Team AND a.year = '" . $season->endingYear . "';";
    $result4 = $db->sql_query($query4);

    // IF HEAT, update ibl_heat_win_loss with each team's HEAT win/loss info
    if ($season->phase == "HEAT"
        AND $wins != 0
        AND $losses != 0) {
        $queryUpdateHeatWinLoss = "UPDATE ibl_heat_win_loss a, ibl_power b
        SET a.wins = b.win,
            a.losses = b.loss
        WHERE a.currentname = b.Team AND a.year = '" . ($season->beginningYear) . "';";
        if ($db->sql_query($queryUpdateHeatWinLoss)) {
            echo $queryUpdateHeatWinLoss . "<p>";
        } else {
            echo "<b>`ibl_heat_win_loss` update FAILED for $teamName! Have you <A HREF=\"leagueControlPanel.php\">inserted new database rows</A> for the new HEAT season?</b><p>";
        }
    }

    // Update teams' total wins in ibl_team_history by summing up a team's wins in ibl_team_win_loss
    $query8 = "UPDATE ibl_team_history a
		SET totwins = (SELECT SUM(b.wins)
		    FROM ibl_team_win_loss AS b
		    WHERE a.team_name = b.currentname)
        WHERE a.team_name != 'Free Agents';";
    $result8 = $db->sql_query($query8);

    // Update teams' total losses in ibl_team_history by summing up a team's losses in ibl_team_win_loss
    $query9 = "UPDATE ibl_team_history a
		SET totloss = (SELECT SUM(b.losses)
		    FROM ibl_team_win_loss AS b
		    WHERE a.team_name = b.currentname)
        WHERE a.team_name != 'Free Agents';";
    $result9 = $db->sql_query($query9);

    // Update teams' win percentage in ibl_team_history
    $query12 = "UPDATE ibl_team_history a 
        SET winpct = a.totwins / (a.totwins + a.totloss)
        WHERE a.team_name != 'Free Agents';";
    $result12 = $db->sql_query($query12);

    $i++;
}

echo '<p>Power Rankings have been updated.<p>';

// Reset the sim's Depth Chart sent status
$query7 = "UPDATE ibl_team_history SET sim_depth = 'No Depth Chart'";
$result7 = $db->sql_query($query7);

//*****************************************************************************
//*** STANDINGS PAGE UPDATE
//*****************************************************************************
//This section automates the following steps from Gates' simming instructions:
#10.) Click the "Standings" tab. Select from A1:T33, and click "Sort & Filter", then "Custom Sort". Sort by Conference in ascending order, and percentage in descending order.
#11.) Go to the admin panel on the website (admin.php), and click "Content". The first thing that will pop up is "IBL Standings" - click the EDIT function on the far right. Scroll down until you see a box full of text, click the "HTML" button. A window will pop up. Delete all the text within.
#12.) Click "Standings HTML" on the SQL spreadsheet. Select from A83:A121, copy, and paste into the popped up window. DON'T HIT UPDATE YET.
#13.) Now go back to the Standings tab, and again select from A1:T33, then the sort. Change the first sort from Conference to "Division".
#14.) Back on "Standings HTML", this time copy from A37:A82, and paste into the popped up window. NOW hit update, and on the admin page, save changes.
#15.) On the admin page, click "Blocks". Scroll down to IBL Standings, and click the edit function. Same thing as before - scroll down to the box of text, click HTML, delete all text within.
#16.) On the Standings tab, copy from U2:U47. Paste into this box, hit update, and save changes.

$standingsHTML = "<script src=\"sorttable.js\"></script>";

function displayStandings($region)
{
    global $db, $standingsHTML;

    list($grouping, $groupingGB, $groupingMagicNumber) = assignGroupingsFor($region);

    $query = "SELECT tid, team_name, leagueRecord, pct, $groupingGB, confRecord, divRecord, homeRecord, awayRecord, gamesUnplayed, $groupingMagicNumber, clinchedConference, clinchedDivision, clinchedPlayoffs, (homeWins + homeLosses) AS homeGames, (awayWins + awayLosses) AS awayGames
		FROM ibl_standings
		WHERE $grouping = '$region' ORDER BY $groupingGB ASC";
    $result = $db->sql_query($query);
    $limit = $db->sql_numrows($result);

    $standingsHTML .= '<font color=#fd004d><b>' . $region . ' ' . ucfirst($grouping) . '</b></font>';
    $standingsHTML .= '<table class="sortable">';
    $standingsHTML .= '<tr>
		<td><font color=#ffffff><b>Team</b></font></td>
		<td><font color=#ffffff><b>W-L</b></font></td>
		<td><font color=#ffffff><b>Pct</b></font></td>
		<td><center><font color=#ffffff><b>GB</b></font></center></td>
		<td><center><font color=#ffffff><b>Magic#</b></font></center></td>
		<td><font color=#ffffff><b>Left</b></font></td>
		<td><font color=#ffffff><b>Conf.</b></font></td>
		<td><font color=#ffffff><b>Div.</b></font></td>
		<td><font color=#ffffff><b>Home</b></font></td>
		<td><font color=#ffffff><b>Away</b></font></td>
		<td><center><font color=#ffffff><b>Home<br>Played</b></font></center></td>
		<td><center><font color=#ffffff><b>Away<br>Played</b></font></center></td>
		<td><font color=#ffffff><b>Last 10</b></font></td>
		<td><font color=#ffffff><b>Streak</b></font></td>
	</tr>';

    $i = 0;
    while ($i < $limit) {
        $tid = $db->sql_result($result, $i, 0);
        $team_name = $db->sql_result($result, $i, 1);
        $leagueRecord = $db->sql_result($result, $i, 2);
        $pct = $db->sql_result($result, $i, 3);
        $GB = $db->sql_result($result, $i, 4);
        $confRecord = $db->sql_result($result, $i, 5);
        $divRecord = $db->sql_result($result, $i, 6);
        $homeRecord = $db->sql_result($result, $i, 7);
        $awayRecord = $db->sql_result($result, $i, 8);
        $gamesUnplayed = $db->sql_result($result, $i, 9);
        $magicNumber = $db->sql_result($result, $i, 10);
        $clinchedConference = $db->sql_result($result, $i, 11);
        $clinchedDivision = $db->sql_result($result, $i, 12);
        $clinchedPlayoffs = $db->sql_result($result, $i, 13);
        $homeGames = $db->sql_result($result, $i, "homeGames");
        $awayGames = $db->sql_result($result, $i, "awayGames");
        if ($clinchedConference == 1) {
            $team_name = "<b>Z</b>-" . $team_name;
        } elseif ($clinchedDivision == 1) {
            $team_name = "<b>Y</b>-" . $team_name;
        } elseif ($clinchedPlayoffs == 1) {
            $team_name = "<b>X</b>-" . $team_name;
        }

        $queryLast10Games = "SELECT last_win, last_loss, streak_type, streak FROM ibl_power WHERE TeamID = $tid";
        $resultLast10Games = $db->sql_query($queryLast10Games);
        $winsInLast10Games = $db->sql_result($resultLast10Games, 0, 0);
        $lossesInLast10Games = $db->sql_result($resultLast10Games, 0, 1);
        $streakType = $db->sql_result($resultLast10Games, 0, 2);
        $streak = $db->sql_result($resultLast10Games, 0, 3);

        $standingsHTML .= '<tr><td><a href="modules.php?name=Team&op=team&tid=' . $tid . '">' . $team_name . '</td>
			<td>' . $leagueRecord . '</td>
			<td>' . $pct . '</td>
			<td><center>' . $GB . '</center></td>
			<td><center>' . $magicNumber . '</center></td>
			<td>' . $gamesUnplayed . '</td>
			<td>' . $confRecord . '</td>
			<td>' . $divRecord . '</td>
			<td>' . $homeRecord . '</td>
			<td>' . $awayRecord . '</td>
			<td><center>' . $homeGames . '</center></td>
			<td><center>' . $awayGames . '</center></td>
			<td>' . $winsInLast10Games . '-' . $lossesInLast10Games . '</td>
			<td>' . $streakType . ' ' . $streak . '</td></tr>';
        $i++;
    }
    $standingsHTML .= '<tr><td colspan=10><hr></td></tr></table><p>';
}

echo '<p>Updating the Standings page...<p>';
displayStandings('Eastern');
displayStandings('Western');
$standingsHTML .= '<p>';

displayStandings('Atlantic');
displayStandings('Central');
displayStandings('Midwest');
displayStandings('Pacific');

$sqlQueryString = "UPDATE nuke_pages SET text = '$standingsHTML' WHERE pid = 4";
if ($db->sql_query($sqlQueryString)) {
    echo $sqlQueryString . '<p>';
    echo '<p>Full standings page has been updated.<p>';
} else {
    die('Invalid query: ' . $db->sql_error());
}

$resetExtensionQueryString = 'UPDATE ibl_team_info SET Used_Extension_This_Chunk = 0';
if ($db->sql_query($resetExtensionQueryString)) {
    echo $resetExtensionQueryString . '<p>';
    echo '<p>Contract Extension usages have been reset.<p>';
} else {
    die('Invalid query: ' . $db->sql_error());
}

if (
    $season->phase == "Playoffs"
    OR $season->phase == "Draft"
    OR $season->phase == "Free Agency"
) {
    echo '<p>Re-applying postseason trades made during the playoffs...</p>';

    $postseasonTradeQueueQuery = "SELECT * FROM ibl_trade_queue;";
    $postseasonTradeQueueResult = $db->sql_query($postseasonTradeQueueQuery);
    $i = 0;
    while ($i < $db->sql_numrows($postseasonTradeQueueResult)) {
        $queuedTradeQuery = $db->sql_result($postseasonTradeQueueResult, $i);
        $tradeLine = $db->sql_result($postseasonTradeQueueResult, $i, 1);
        if ($db->sql_query($queuedTradeQuery)) {
            echo $tradeLine . "\n";
        }
        $i++;
    }
    echo '<p>Postseason trades have been re-applied!';
} elseif ($season->phase == "Preseason") {
    if ($db->sql_query("TRUNCATE TABLE ibl_trade_queue;")) {
        echo "<p>TRUNCATE TABLE ibl_trade_queue;";
    }
}

echo '<p>All the things have been updated!<p>';

echo '<a href="index.php">Return to the IBL homepage</a>';
