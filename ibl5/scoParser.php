<?php

require 'mainfile.php';

function scoParser($uploadedFilePath, $operatingSeasonEndingYear, $operatingSeasonPhase)
{
    global $db;
    $season = new Season($db);

    $scoFilePath = ($uploadedFilePath) ? $uploadedFilePath : "IBL5.sco";
    $operatingSeasonEndingYear = ($operatingSeasonEndingYear) ? $operatingSeasonEndingYear : $season->endingYear;
    $operatingSeasonStartingYear = $operatingSeasonEndingYear - 1;
    $operatingSeasonPhase = ($operatingSeasonPhase) ? $operatingSeasonPhase : $season->phase;

    echo "<h2>Parse Log</h2>
        <b>Parsing .sco file for the $operatingSeasonStartingYear-$operatingSeasonEndingYear $operatingSeasonPhase...</b><p>";

    $scoFile = fopen("$scoFilePath", "rb");
    fseek($scoFile, 1000000);

    if ($operatingSeasonPhase == "Preseason") {
        if (Boxscore::deletePreseasonBoxScores($db, $operatingSeasonStartingYear)) {
            echo "Deleted any existing Preseason box scores." . "<p>";
        } else {
            echo "<b><font color=#F00>Failed to delete existing Preseason box scores!</font></b>";
        }
    } elseif ($operatingSeasonPhase == "HEAT") {
        if (Boxscore::deleteHEATBoxScores($db, $operatingSeasonStartingYear)) {
            echo "Deleted any existing HEAT box scores." . "<p>";
        } else {
            echo "<b><font color=#F00>Failed to delete existing HEAT box scores!</font></b>";
        }
    } else {
        if (Boxscore::deleteRegularSeasonAndPlayoffsBoxScores($db, $operatingSeasonStartingYear)) {
            echo "Deleted any existing regular season and playoffs box scores." . "<p>";
        } else {
            echo "<b><font color=#F00>Failed to delete existing regular season and playoffs box scores!</font></b>";
        }
    }

    echo "<i>[scoParser works silently now]</i><br>";

    $numberOfLinesProcessed = 0;
    while (!feof($scoFile)) {
        $line = fgets($scoFile, 2001);

        $gameInfoLine = substr($line, 0, 58);
        $gameYear = $operatingSeasonEndingYear;
        @$gameMonth = sprintf("%02u", substr($gameInfoLine, 0, 2) + 10); // sprintf() prepends 0 if the result isn't in double-digits
        @$gameDay = sprintf("%02u", substr($gameInfoLine, 2, 2) + 1);
        @$gameOfThatDay = substr($gameInfoLine, 4, 2) + 1;
        @$visitorTID = substr($gameInfoLine, 6, 2) + 1;
        @$homeTID = substr($gameInfoLine, 8, 2) + 1;
        $attendance = substr($gameInfoLine, 10, 5);
        $capacity = substr($gameInfoLine, 15, 5);
        $visitorWins = substr($gameInfoLine, 20, 2);
        $visitorLosses = substr($gameInfoLine, 22, 2);
        $homeWins = substr($gameInfoLine, 24, 2);
        $homeLosses = substr($gameInfoLine, 26, 2);
        $visitorQ1pts = substr($gameInfoLine, 28, 3);
        $visitorQ2pts = substr($gameInfoLine, 31, 3);
        $visitorQ3pts = substr($gameInfoLine, 34, 3);
        $visitorQ4pts = substr($gameInfoLine, 37, 3);
        $visitorOTpts = substr($gameInfoLine, 40, 3);
        $homeQ1pts = substr($gameInfoLine, 43, 3);
        $homeQ2pts = substr($gameInfoLine, 46, 3);
        $homeQ3pts = substr($gameInfoLine, 49, 3);
        $homeQ4pts = substr($gameInfoLine, 52, 3);
        $homeOTpts = substr($gameInfoLine, 55, 3);

        if ($gameMonth > 12 and $gameMonth != Season::JSB_PLAYOFF_MONTH) {
            $gameMonth = sprintf("%02u", $gameMonth - 12);
        } elseif ($gameMonth == Season::JSB_PLAYOFF_MONTH) {
            $gameMonth = sprintf("%02u", $gameMonth - 16); // TODO: not have to hack the Playoffs to be in June
        } elseif ($gameMonth > 10) {
            $gameYear = $operatingSeasonStartingYear;
            if ($operatingSeasonPhase == "HEAT") {
                $gameMonth = Season::IBL_HEAT_MONTH;
            }
            if ($operatingSeasonPhase == "Preseason") {
                $gameMonth = Season::IBL_PRESEASON_MONTH;
            }
        }

        $date = $gameYear . '-' . $gameMonth . '-' . $gameDay;

        for ($i = 0; $i < 30; $i++) {
            $x = $i * 53; // 53 = amount of characters to skip to get to the next player's/team's data line
            $playerInfoLine = substr($line, 58 + $x, 53);
            $name = trim(substr($playerInfoLine, 0, 16));
            $pos = trim(substr($playerInfoLine, 16, 2));
            $pid = trim(substr($playerInfoLine, 18, 6));
            $gameMIN = substr($playerInfoLine, 24, 2);
            $game2GM = substr($playerInfoLine, 26, 2);
            $game2GA = substr($playerInfoLine, 28, 3);
            $gameFTM = substr($playerInfoLine, 31, 2);
            $gameFTA = substr($playerInfoLine, 33, 2);
            $game3GM = substr($playerInfoLine, 35, 2);
            $game3GA = substr($playerInfoLine, 37, 2);
            $gameORB = substr($playerInfoLine, 39, 2);
            $gameDRB = substr($playerInfoLine, 41, 2);
            $gameAST = substr($playerInfoLine, 43, 2);
            $gameSTL = substr($playerInfoLine, 45, 2);
            $gameTOV = substr($playerInfoLine, 47, 2);
            $gameBLK = substr($playerInfoLine, 49, 2);
            $gamePF = substr($playerInfoLine, 51, 2);

            $entryInsertQuery = "INSERT INTO ibl_box_scores (
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
            if ($name != null || $name != '') {
                if ($db->sql_query($entryInsertQuery)) {
                    $numberOfLinesProcessed++;
                    // $entryInsertQuery = str_replace(array("\n", "\t", "\r"), '', $entryInsertQuery); // LOG LINES
                    // echo $entryInsertQuery . "<br>";
                }
            }
        }
    }

    $newSimEndDate = $season->getLastBoxScoreDate();

    if ($season->lastSimEndDate) {
        if ($season->lastSimEndDate != $newSimEndDate) {
            $dateObjectForNewSimEndDate = date_create($season->lastSimEndDate);
            date_modify($dateObjectForNewSimEndDate, '+1 day');
            $newSimStartDate = date_format($dateObjectForNewSimEndDate, 'Y-m-d');

            $newSimNumber = $season->lastSimNumber + 1;

            $insertNewSimDates = $season->setLastSimDatesArray($newSimNumber, $newSimStartDate, $newSimEndDate);
        } else {
            echo "<p>Number of .sco lines processed: $numberOfLinesProcessed
            <p>Looks like new box scores haven't been added.
            <br>Sim Start/End Dates will stay set to $season->lastSimStartDate and $season->lastSimEndDate.";
            die();
        }
    } else {
        $newSimNumber = 1;
        $newSimStartDate = $season->getFirstBoxScoreDate();

        $insertNewSimDates = $season->setLastSimDatesArray($newSimNumber, $newSimStartDate, $newSimEndDate);
    }

    if ($insertNewSimDates) {
        echo "<p>Added box scores from $newSimStartDate through $newSimEndDate.";
    } else {
        die('Invalid query: ' . $db->sql_error());
    }
}

echo "<h1>JSB .sco File Parser</h1>
<h2>Uploader</h2>
<form enctype=\"multipart/form-data\" action=\"scoParser.php\" method=\"POST\">
    <input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"14000000\" />
    <label for=\"scoFile\">Upload Old Season's .sco: </label><input name=\"scoFile\" type=\"file\" /><p>
    <label for=\"seasonPhase\">Season Phase for Uploaded .sco: </label><select name=\"seasonPhase\">
        <option value = \"Preseason\">Preseason</option>
        <option value = \"HEAT\">HEAT</option>
        <option value = \"Regular Season/Playoffs\">Regular Season</option>
    </select><p>
    <label for=\"seasonEndingYear\">Season <b><u>Ending</u></b> Year for Uploaded .sco: </label><input type=\"text\" name=\"seasonEndingYear\" maxlength=4 minlength=4 size=4 /><br>
    <i>e.g. HEAT before the 1990-1991 season</i> = <code>1991</code><br>
    <i>e.g. 1984-1985 Preseason or Regular Season</i> = <code>1985</code><p>
    <input type=\"submit\" value=\"Parse Uploaded .sco File\" />
</form>
<hr>
<br>";

if ($_FILES['scoFile']['tmp_name']) {
    $uploadedFilePath = $_FILES['scoFile']['tmp_name'];
    $operatingSeasonEndingYear = $_POST['seasonEndingYear'];
    $operatingSeasonPhase = $_POST['seasonPhase'];
}

scoParser($uploadedFilePath, $operatingSeasonEndingYear, $operatingSeasonPhase);
