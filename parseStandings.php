<?php

//This section stores Standings values in a database table called 'IBL_Standings' so that they can be retrieved quickly.
//The file 'block-AJstandings.php' relies on 'IBL_Standings' to automate the sidebar standings display.

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$standingsFilePath = 'ibl/IBL/Standings.htm';

$standings = new DOMDocument();
$standings->loadHTMLFile($standingsFilePath);
$standings->preserveWhiteSpace = false;

$getRows = $standings->getElementsByTagName('tr');
$rowsByConference = $getRows->item(0)->childNodes->item(0)->childNodes->item(0)->childNodes;
$rowsByDivision = $getRows->item(0)->childNodes->item(1)->childNodes->item(0)->childNodes;

function stripLeadingZeros($var) {
	$var = ltrim($var,'0');
	return $var;
}

function stripTrailingSpaces($var) {
	$var = rtrim($var,' ');
	return $var;
}

function extractWins($var) {
	$var = rtrim(substr($var,0,2),'-');
	return $var;
}

function extractLosses($var) {
	$var = ltrim(substr($var,-2,2),'-');
	return $var;
}

function extractStandingsValues($confVar,$divVar) {
	echo '<br>';
	echo 'Updating the conference standings for all teams...';
	foreach ($confVar as $row) {
		$teamName = $row->childNodes->item(0)->nodeValue;
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

			$sqlQueryString = "INSERT INTO IBL_Standings (team_name,leagueRecord,pct,confGB,confRecord,divRecord,homeRecord,awayRecord,
				confWins,confLosses,divWins,divLosses,homeWins,homeLosses,awayWins,awayLosses)
				VALUES ('".$teamName."','".$leagueRecord."','".$pct."','".$confGB."','".$confRecord."','".$divRecord."','".$homeRecord."','".$awayRecord."',
				'".$confWins."','".$confLosses."','".$divWins."','".$divLosses."','".$homeWins."','".$homeLosses."','".$awayWins."','".$awayLosses."')

				ON DUPLICATE KEY UPDATE
				leagueRecord = '".$leagueRecord."',
				pct = '".$pct."',
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

			$rowUpdate = mysql_query($sqlQueryString);
			if (!$sqlQueryString) {
				die('Invalid query: ' . mysql_error());
			}
		}
	}

	echo '<br>';
	echo 'Updating division games back for all teams...';
	foreach ($divVar as $row) {
		$teamName = $row->childNodes->item(0)->nodeValue;
		if (!in_array($teamName, array("Atlantic", "Central", "Midwest", "Pacific", "team", ""))) {
			$divGB = $row->childNodes->item(3)->nodeValue;

			$sqlQueryString = "INSERT INTO IBL_Standings (team_name,divGB)
				VALUES ('".$teamName."','".$divGB."')

				ON DUPLICATE KEY UPDATE
				divGB = '".$divGB."'
			";

			$rowUpdate = mysql_query($sqlQueryString);
			if (!$sqlQueryString) {
				die('Invalid query: ' . mysql_error());
			}
		}
	}
}

extractStandingsValues($rowsByConference,$rowsByDivision);

echo '<p>';
echo 'IBL_Standings table has successfully been updated.';

?>
