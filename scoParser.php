<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die( "Unable to select database");

$stringCurrentEndingYear = "SELECT value FROM nuke_ibl_settings WHERE name = 'Current Season Ending Year';";
$queryCurrentEndingYear = mysql_query($stringCurrentEndingYear);
$currentEndingYear = mysql_result($queryCurrentEndingYear, 0);
$currentStartingYear = $currentEndingYear-1;

$stringSeasonPhase = "SELECT value FROM nuke_ibl_settings WHERE name = 'Current Season Phase';";
$querySeasonPhase = mysql_query($stringSeasonPhase);
$seasonPhase = mysql_result($querySeasonPhase, 0);

$scoFile = fopen("IBL5.sco", "rb");
fseek($scoFile,1030000);

if ($seasonPhase == "HEAT") {
    $stringDeleteCurrentSeasonBoxScores = "DELETE FROM `ibl_box_scores` WHERE `Date` BETWEEN '$currentStartingYear-10-01' AND '$currentEndingYear-07-01';";
} else {
    $stringDeleteCurrentSeasonBoxScores = "DELETE FROM `ibl_box_scores` WHERE `Date` BETWEEN '$currentStartingYear-11-01' AND '$currentEndingYear-07-01';";
}

if (mysql_query($stringDeleteCurrentSeasonBoxScores)) {
    echo $stringDeleteCurrentSeasonBoxScores."<p>";
}

echo "[scoParser works silently now]<br>";

while (!feof($scoFile)) {
    $line = fgets($scoFile,2001);

    $gameYear = $currentEndingYear;
    $gameMonth = sprintf("%02u",substr($line,0,2)+10); // sprintf() prepends 0 if the result isn't in double-digits
    if ($gameMonth > 12 AND $gameMonth != 22) { // if $gameMonth === 22, it's the Playoffs
        $gameMonth = sprintf("%02u",$gameMonth-12);
    } elseif ($gameMonth == 22) {
        $gameMonth = sprintf("%02u",$gameMonth-17); // TODO: not have to hack the Playoffs to be in May
    } elseif ($gameMonth > 10) {
        $gameYear = $currentStartingYear;
        if ($seasonPhase == "HEAT") {
            $gameMonth = 10; // Puts HEAT games in October
        }
    }
    $gameDay = sprintf("%02u",substr($line,2,2)+1);
    $gameOfThatDay = substr($line,4,2)+1;
    $visitorTID = substr($line,6,2)+1;
    $homeTID = substr($line,8,2)+1;
    $visitorQ1pts = substr($line,28,3);
    $visitorQ2pts = substr($line,31,3);
    $visitorQ3pts = substr($line,34,3);
    $visitorQ4pts = substr($line,37,3);
    $visitorOTpts = substr($line,40,3);
    $homeQ1pts = substr($line,43,3);
    $homeQ2pts = substr($line,46,3);
    $homeQ3pts = substr($line,49,3);
    $homeQ4pts = substr($line,52,3);
    $homeOTpts = substr($line,55,3);

    $date = $gameYear.'-'.$gameMonth.'-'.$gameDay;

    for ($i = 0; $i < 30; $i++) {
        $x = $i*53; // 53 = amount of characters to skip to get to the next player's/team's data line

        $name = trim(substr($line,58+$x,16));
        $pos = trim(substr($line,74+$x,2));
        $pid = trim(substr($line,76+$x,6));
        $gameMIN = substr($line,82+$x,2);
        $game2GM = substr($line,84+$x,2);
        $game2GA = substr($line,86+$x,3);
        $gameFTM = substr($line,89+$x,2);
        $gameFTA = substr($line,91+$x,2);
        $game3GM = substr($line,93+$x,2);
        $game3GA = substr($line,95+$x,2);
        $gameORB = substr($line,97+$x,2);
        $gameDRB = substr($line,99+$x,2);
        $gameAST = substr($line,101+$x,2);
        $gameSTL = substr($line,103+$x,2);
        $gameTOV = substr($line,105+$x,2);
        $gameBLK = substr($line,107+$x,2);
        $gamePF = substr($line,109+$x,2);

        $entryUpdateQuery = "INSERT INTO ibl_box_scores (
            Date,
            name,
            pos,
            pid,
            visitorTID,
            homeTID,
            gameMIN,
            game2GM,
            game2GA,
            gameFTM,
            gameFTA,
            game3GM,
            game3GA,
            gameORB,
            gameDRB,
            gameAST,
            gameSTL,
            gameTOV,
            gameBLK,
            gamePF
        )
        VALUES (
            '$date',
            '$name',
            '$pos',
            $pid,
            $visitorTID,
            $homeTID,
            $gameMIN,
            $game2GM,
            $game2GA,
            $gameFTM,
            $gameFTA,
            $game3GM,
            $game3GA,
            $gameORB,
            $gameDRB,
            $gameAST,
            $gameSTL,
            $gameTOV,
            $gameBLK,
            $gamePF
        )";
        if ($name != NULL || $name != '') {
            if (mysql_query($entryUpdateQuery)) {
                $entryUpdateQuery = str_replace(array("\n", "\t", "\r"), '', $entryUpdateQuery);
            }
        }
    }
}

$queryLastSimDates = mysql_query("SELECT * FROM ibl_sim_dates ORDER BY Sim DESC LIMIT 1");
$lastSimNumber = mysql_result($queryLastSimDates, 0, "Sim");
$lastSimStartDate = mysql_result($queryLastSimDates, 0, "Start Date");
$lastSimEndDate = mysql_result($queryLastSimDates, 0, "End Date");
$newSimEndDate = mysql_result(mysql_query('SELECT Date FROM ibl_box_scores ORDER BY Date DESC LIMIT 1'),0);

if ($lastSimEndDate != $newSimEndDate) {
    $dateObjectForNewSimEndDate = date_create($lastSimEndDate);
    date_modify($dateObjectForNewSimEndDate, '+1 day');
    $newSimStartDate = date_format($dateObjectForNewSimEndDate, 'Y-m-d');
    $newSimNumber = $lastSimNumber + 1;

    $insertNewSimDates = mysql_query("INSERT INTO ibl_sim_dates (`Sim`, `Start Date`, `End Date`) VALUES ('$newSimNumber', '$newSimStartDate', '$newSimEndDate');");

    if ($insertNewSimDates) {
        echo "<p>Added box scores from $newSimStartDate through $newSimEndDate.";
    } else die('Invalid query: '.mysql_error());
} else {
    echo "<p>Looks like new box scores haven't been added.<br>Sim Start/End Dates will stay set to $lastSimStartDate and $lastSimEndDate.";
}

?>
