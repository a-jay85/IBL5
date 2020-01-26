<?php
// TODO: Prevent broken SQL strings from being sent in schedule parser

error_reporting(E_ALL & ~E_NOTICE);
libxml_use_internal_errors(true);

//*****************************************************************************
//*** IBL_SCHEDULE DB UPDATE
//*****************************************************************************
//This section automates the following steps from Gates' simming instructions:
//#8.) From the IBL HTML, open "Schedule.htm" IN INTERNET EXPLORER. Select the entire content of this page and copy it. Then paste into A1 of the "Schedule" tab.
//#9.) In the Schedule tab, copy Column Q and paste into the database and run it.

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$scheduleFilePath = 'ibl/IBL/Schedule.htm';

$schedule = new DOMDocument();
$schedule->loadHTMLFile($scheduleFilePath);
$schedule->preserveWhiteSpace = false;

$rows = $schedule->getElementsByTagName('tr');

function extractDate($rawDate)
{
	if ($rawDate != FALSE) {
		if (substr($rawDate,0,4) === "Post") {
			$rawDate = substr_replace($rawDate,'May',0,4); // TODO: recognize "Post" instead of hacking it into May
		}

		$month = ltrim(date('m', strtotime($rawDate)),'0');
		$day = ltrim(date('d', strtotime($rawDate)),'0');
		$year = date('Y', strtotime($rawDate));
		$date = $year."-".$month."-".$day;

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
	$boxID = ltrim(rtrim($boxHREF,'.htm'),'box');
	return $boxID;
}

function groupingSort($region)
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
	return array ($grouping, $groupingGB, $groupingMagicNumber);
}

echo 'Updating the IBL_Schedule database table...<p>';
if (mysql_query('TRUNCATE TABLE IBL_Schedule')) echo 'TRUNCATE TABLE IBL_Schedule<p>';

foreach ($rows as $row) {
	$checkThirdCell = $row->childNodes->item(2)->nodeValue;
	$checkSecondCell = $row->childNodes->item(1)->nodeValue;
	$checkFirstCell = $row->childNodes->item(0)->nodeValue;
	$vScore = "";
	$hScore = "";
	$visitorTID = "";
	$homeTID = "";
	$boxID = "";

	if ($checkSecondCell === NULL /*AND substr($checkFirstCell,0,4) !== "Post"*/) {
		$fullDate = extractDate($row->textContent);
		$date = $fullDate['date'];
		$year = $fullDate['year'];
	}

	if ($checkThirdCell !== NULL AND $checkThirdCell !== "" AND $checkFirstCell !== "visitor") {

		if ($row->childNodes->item(1)->getElementsByTagName('a')->length !== 0) {
			$boxLink = $row->childNodes->item(1)->getElementsByTagName('a')->item(0)->getAttribute('href');
			$boxID = extractBoxID($boxLink);
		}

		$visitorName = rtrim($row->childNodes->item(0)->textContent);
		$vScore = $row->childNodes->item(1)->textContent;
		$homeName = rtrim($row->childNodes->item(2)->textContent);
		$hScore = $row->childNodes->item(3)->textContent;

		if ($row->childNodes->item(1)->nodeValue === NULL OR $row->childNodes->item(1)->nodeValue === "") {
			$vScore = 0;
			$hScore = 0;
			if ($boxID > 99999 OR $boxID === NULL) {
				$boxID = $boxID + 1;
			} else $boxID = 100000;
		}

		$visitorTID = mysql_result(mysql_query("SELECT teamid FROM nuke_ibl_team_info WHERE team_name = '".$visitorName."';"),0);
		$homeTID = mysql_result(mysql_query("SELECT teamid FROM nuke_ibl_team_info WHERE team_name = '".$homeName."';"),0);
	}

	$sqlQueryString = "INSERT INTO IBL_Schedule (Year,BoxID,Date,Visitor,Vscore,Home,Hscore)
		VALUES ($year,$boxID,'$date',$visitorTID,$vScore,$homeTID,$hScore)"; /*
		ON DUPLICATE KEY UPDATE
			Year = $year,
			Date = '$date',
			Visitor = $visitorTID,
			Vscore = $vScore,
			Home = $homeTID,
			Hscore = $hScore
		"; */

	if (mysql_query($sqlQueryString)) {
		echo $sqlQueryString.'<br>';
	} // DO NOT use 'else die('Invalid query: '.mysql_error()' here -- script depends on being able to pass broken SQL strings for now.
}

unset($visitorName,$homeName,$boxLink,$hScore,$vScore,$homeName,$visitorName,$homeTID,$visitorTID);


// TODO:
// Standings variables to derive from Schedule: last 10, streak
// New variables: rival conf w/l, >.500 w/l, <.500 w/l

//*****************************************************************************
//*** IBL_STANDINGS DB UPDATE
//*****************************************************************************
//This section stores Standings values in a database table called 'IBL_Standings' so that they can be retrieved quickly.
//The file 'block-AJstandings.php' relies on 'IBL_Standings' to automate the sidebar standings display.

$standingsFilePath = 'ibl/IBL/Standings.htm';

$standings = new DOMDocument();
$standings->loadHTMLFile($standingsFilePath);
$standings->preserveWhiteSpace = false;

$getRows = $standings->getElementsByTagName('tr');
$rowsByConference = $getRows->item(0)->childNodes->item(0)->childNodes->item(0)->childNodes;
$rowsByDivision = $getRows->item(0)->childNodes->item(1)->childNodes->item(0)->childNodes;

function extractWins($var)
{
	$var = rtrim(substr($var,0,2),'-');
	return $var;
}

function extractLosses($var)
{
	$var = ltrim(substr($var,-2,2),'-');
	return $var;
}

echo '<p>Updating the IBL_Standings database table...<p>';

function extractStandingsValues($confVar,$divVar)
{
	echo '<p>Updating the conference standings for all teams...<p>';
	foreach ($confVar as $row) {
		$teamName = $row->childNodes->item(0)->nodeValue;
		if (in_array($teamName, array("Eastern", "Western"))) {
			$conference = $teamName;
		}
		if (!in_array($teamName, array("Eastern", "Western", "team", ""))) {
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

			$gamesUnplayed = 68 - $homeWins - $homeLosses - $awayWins - $awayLosses; // TODO: make number of games in season dynamic

			$sqlQueryString = "INSERT INTO IBL_Standings (
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
				'".rtrim($teamName)."',
				'".$leagueRecord."',
				'".$pct."',
				'".$gamesUnplayed."',
				'".$conference."',
				'".$confGB."',
				'".$confRecord."',
				'".$divRecord."',
				'".$homeRecord."',
				'".$awayRecord."',
				'".$confWins."',
				'".$confLosses."',
				'".$divWins."',
				'".$divLosses."',
				'".$homeWins."',
				'".$homeLosses."',
				'".$awayWins."',
				'".$awayLosses."'
			)

			ON DUPLICATE KEY UPDATE
				leagueRecord = '".$leagueRecord."',
				pct = '".$pct."',
				gamesUnplayed = '".$gamesUnplayed."',
				conference = '".$conference."',
				confGB = '".$confGB."',
				confRecord = '".$confRecord."',
				divRecord = '".$divRecord."',
				homeRecord = '".$homeRecord."',
				awayRecord = '".$awayRecord."',
				confWins = '".$confWins."',
				confLosses = '".$confLosses."',
				divWins = '".$divWins."',
				divlosses = '".$divLosses."',
				homeWins = '".$homeWins."',
				homeLosses = '".$homeLosses."',
				awayWins = '".$awayWins."',
				awayLosses = '".$awayLosses."'
			";

			if (mysql_query($sqlQueryString)) {
				echo $sqlQueryString.'<br>';
			} else die('Invalid query: '.mysql_error());
		}
	}

	echo '<p>Updating the division games back for all teams...<p>';
	foreach ($divVar as $row) {
		$teamName = $row->childNodes->item(0)->nodeValue;
		if (in_array($teamName, array("Atlantic", "Central", "Midwest", "Pacific"))) {
			$division = $teamName;
		}
		if (!in_array($teamName, array("Atlantic", "Central", "Midwest", "Pacific", "team", ""))) {
			$divGB = $row->childNodes->item(3)->nodeValue;

			$sqlQueryString = "INSERT INTO IBL_Standings (team_name,division,divGB) VALUES ('".$teamName."','".$division."','".$divGB."')
				ON DUPLICATE KEY UPDATE	division = '".$division."',divGB = '".$divGB."'";

			if (mysql_query($sqlQueryString)) {
				echo $sqlQueryString.'<br>';
			} else die('Invalid query: '.mysql_error());
		}
	}
}

function updateMagicNumbers ($region)
{
	list ($grouping,$groupingGB,$groupingMagicNumber) = groupingSort($region);

	$query = "SELECT team_name,homeWins,homeLosses,awayWins,awayLosses FROM IBL_Standings WHERE ".$grouping." = '".$region."' ORDER BY pct DESC";
	$result = mysql_query($query);
	$limit = mysql_num_rows($result);

	$i = 0;
	while ($i+1 < $limit) {
		$teamName = mysql_result($result,$i,0);
		$teamTotalWins = mysql_result($result,$i,1) + mysql_result($result,$i,3);
		$belowTeamTotalLosses = mysql_result($result,$i+1,2) + mysql_result($result,$i+1,4);
		$magicNumber = 68 + 1 - $teamTotalWins - $belowTeamTotalLosses;

		$sqlQueryString = "INSERT INTO IBL_Standings (team_name,".$groupingMagicNumber.") VALUES ('".$teamName."','".$magicNumber."')
			ON DUPLICATE KEY UPDATE ".$groupingMagicNumber." = '".$magicNumber."'";

		if (mysql_query($sqlQueryString)) {
			echo $sqlQueryString.'<br>';
		} else die('Invalid query: '.mysql_error());
		$i++;
	}
}

extractStandingsValues($rowsByConference,$rowsByDivision);

echo '<p>Updating the magic numbers for all teams...<p>';

updateMagicNumbers('Eastern');
updateMagicNumbers('Western');
updateMagicNumbers('Atlantic');
updateMagicNumbers('Central');
updateMagicNumbers('Midwest');
updateMagicNumbers('Pacific');

echo '<p>The IBL_Schedule and IBL_Standings table have been updated.<p>';

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

$standingsHTML = "";

function displayStandings($region)
{
	global $standingsHTML;

	list ($grouping,$groupingGB,$groupingMagicNumber) = groupingSort($region);

	$query = "SELECT tid,team_name,leagueRecord,pct,".$groupingGB.",confRecord,divRecord,homeRecord,awayRecord,gamesUnplayed,".$groupingMagicNumber." FROM IBL_Standings WHERE ".$grouping." = '".$region."' ORDER BY ".$groupingGB." ASC";
	$result = mysql_query($query);
	$limit = mysql_num_rows($result);

	$standingsHTML=$standingsHTML.'<tr><td colspan=10><font color=#fd004d><b>'.$region.' '.ucfirst($grouping).'</b></font></td></tr>';
	$standingsHTML=$standingsHTML.'<tr bgcolor=#006cb3><td><font color=#ffffff><b>Team</b></font></td>
		<td><font color=#ffffff><b>W-L</b></font></td>
		<td><font color=#ffffff><b>Pct</b></font></td>
		<td><center><font color=#ffffff><b>GB</b></font></center></td>
		<td><center><font color=#ffffff><b>Magic#</b></font></center></td>
		<td><font color=#ffffff><b>Left</b></font></td>
		<td><font color=#ffffff><b>Conf.</b></font></td>
		<td><font color=#ffffff><b>Div.</b></font></td>
		<td><font color=#ffffff><b>Home</b></font></td>
		<td><font color=#ffffff><b>Away</b></font></td>
		<td><font color=#ffffff><b>Last 10</b></font></td>
		<td><font color=#ffffff><b>Streak</b></font></td></tr>';

	$i = 0;
	while ($i < $limit) {
		$tid = mysql_result($result,$i,0);
		$team_name = mysql_result($result,$i,1);
		$leagueRecord = mysql_result($result,$i,2);
		$pct = mysql_result($result,$i,3);
		$GB = mysql_result($result,$i,4);
		$confRecord = mysql_result($result,$i,5);
		$divRecord = mysql_result($result,$i,6);
		$homeRecord = mysql_result($result,$i,7);
		$awayRecord = mysql_result($result,$i,8);
		$gamesUnplayed = mysql_result($result,$i,9);
		$magicNumber = mysql_result($result,$i,10);

		$standingsHTML=$standingsHTML.'<tr><td><a href="modules.php?name=Team&op=team&tid='.$tid.'">'.$team_name.'</td>
			<td>'.$leagueRecord.'</td>
			<td>'.$pct.'</td>
			<td><center>'.$GB.'</center></td>
			<td><center>'.$magicNumber.'</center></td>
			<td>'.$gamesUnplayed.'</td>
			<td>'.$confRecord.'</td>
			<td>'.$divRecord.'</td>
			<td>'.$homeRecord.'</td>
			<td>'.$awayRecord.'</td>
			<td></td>
			<td></td></tr>';
		$i++;
	}
	$standingsHTML=$standingsHTML.'<tr><td colspan=10><hr></td></tr>';
}

$standingsHTML=$standingsHTML.'<table>';
displayStandings('Eastern');
displayStandings('Western');
$standingsHTML=$standingsHTML.'</table>';
$standingsHTML=$standingsHTML.'<p>';

$standingsHTML=$standingsHTML.'<table>';
displayStandings('Atlantic');
displayStandings('Central');
displayStandings('Midwest');
displayStandings('Pacific');
$standingsHTML=$standingsHTML.'</table>';

$sqlQueryString = "UPDATE nuke_pages SET text='".$standingsHTML."' WHERE pid=4";
if (mysql_query($sqlQueryString)) {
	echo $sqlQueryString.'<p>';
} else die('Invalid query: '.mysql_error());

$resetExtensionQueryString = 'UPDATE nuke_ibl_team_info SET Used_Extension_This_Chunk=0';
if (mysql_query($resetExtensionQueryString)) {
	echo $resetExtensionQueryString.'<p>';
} else die('Invalid query: '.mysql_error());

echo 'Full standings page has been updated.<br>';
echo 'Sidebar standings have been updated.<p>';
echo '<a href="/">Return to the IBL homepage</a>';

?>
