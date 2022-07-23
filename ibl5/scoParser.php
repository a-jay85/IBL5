<?php

require 'mainfile.php';
$sharedFunctions = new Shared($db);

function scoParser($uploadedFilePath, $seasonEndingYear, $seasonPhase)
{
    global $db, $sharedFunctions;

    $scoFilePath = ($uploadedFilePath) ? $uploadedFilePath : "IBL5.sco";
    $currentSeasonEndingYear = ($seasonEndingYear) ? $seasonEndingYear : $sharedFunctions->getCurrentSeasonEndingYear();
    $currentSeasonStartingYear = $currentSeasonEndingYear - 1;
    $seasonPhase = ($seasonPhase) ? $seasonPhase : $sharedFunctions->getCurrentSeasonPhase();

    echo "<h2>Parse Log</h2>
        <b>Parsing .sco file for the $currentSeasonStartingYear-$currentSeasonEndingYear $seasonPhase...</b><p>";

    $scoFile = fopen("$scoFilePath", "rb");
    fseek($scoFile, 1000000);

    if ($seasonPhase == "Preseason") {
        $stringDeleteCurrentSeasonBoxScores = "DELETE FROM `ibl_box_scores` WHERE `Date` BETWEEN '$currentSeasonStartingYear-07-01' AND '$currentSeasonStartingYear-09-01';";
    } elseif ($seasonPhase == "HEAT") {
        $stringDeleteCurrentSeasonBoxScores = "DELETE FROM `ibl_box_scores` WHERE `Date` BETWEEN '$currentSeasonStartingYear-09-01' AND '$currentSeasonStartingYear-11-01';";
    } else {
        $stringDeleteCurrentSeasonBoxScores = "DELETE FROM `ibl_box_scores` WHERE `Date` BETWEEN '$currentSeasonStartingYear-11-01' AND '$currentSeasonEndingYear-07-01';";
    }

    if ($db->sql_query($stringDeleteCurrentSeasonBoxScores)) {
        echo $stringDeleteCurrentSeasonBoxScores . "<p>";
    }

    echo "<i>[scoParser works silently now]</i><br>";

    $numberOfLinesProcessed = 0;
    while (!feof($scoFile)) {
        $line = fgets($scoFile, 2001);

        $gameYear = $currentSeasonEndingYear;
        $gameMonth = sprintf("%02u", substr($line, 0, 2) + 10); // sprintf() prepends 0 if the result isn't in double-digits
        if ($gameMonth > 12 and $gameMonth != 22) { // if $gameMonth === 22, it's the Playoffs
            $gameMonth = sprintf("%02u", $gameMonth - 12);
        } elseif ($gameMonth == 22) {
            $gameMonth = sprintf("%02u", $gameMonth - 16); // TODO: not have to hack the Playoffs to be in June
        } elseif ($gameMonth > 10) {
            $gameYear = $currentSeasonStartingYear;
            if ($seasonPhase == "HEAT") {
                $gameMonth = 10; // Puts HEAT games in October
            }
            if ($seasonPhase == "Preseason") {
                $gameMonth = 9; // Puts preseason games in September
            }
        }
        $gameDay = sprintf("%02u", substr($line, 2, 2) + 1);
        $gameOfThatDay = substr($line, 4, 2) + 1;
        $visitorTID = substr($line, 6, 2) + 1;
        $homeTID = substr($line, 8, 2) + 1;
        $visitorQ1pts = substr($line, 28, 3);
        $visitorQ2pts = substr($line, 31, 3);
        $visitorQ3pts = substr($line, 34, 3);
        $visitorQ4pts = substr($line, 37, 3);
        $visitorOTpts = substr($line, 40, 3);
        $homeQ1pts = substr($line, 43, 3);
        $homeQ2pts = substr($line, 46, 3);
        $homeQ3pts = substr($line, 49, 3);
        $homeQ4pts = substr($line, 52, 3);
        $homeOTpts = substr($line, 55, 3);

        $date = $gameYear . '-' . $gameMonth . '-' . $gameDay;

        for ($i = 0; $i < 30; $i++) {
            $x = $i * 53; // 53 = amount of characters to skip to get to the next player's/team's data line

            $name = trim(substr($line, 58 + $x, 16));
            $pos = trim(substr($line, 74 + $x, 2));
            $pid = trim(substr($line, 76 + $x, 6));
            $gameMIN = substr($line, 82 + $x, 2);
            $game2GM = substr($line, 84 + $x, 2);
            $game2GA = substr($line, 86 + $x, 3);
            $gameFTM = substr($line, 89 + $x, 2);
            $gameFTA = substr($line, 91 + $x, 2);
            $game3GM = substr($line, 93 + $x, 2);
            $game3GA = substr($line, 95 + $x, 2);
            $gameORB = substr($line, 97 + $x, 2);
            $gameDRB = substr($line, 99 + $x, 2);
            $gameAST = substr($line, 101 + $x, 2);
            $gameSTL = substr($line, 103 + $x, 2);
            $gameTOV = substr($line, 105 + $x, 2);
            $gameBLK = substr($line, 107 + $x, 2);
            $gamePF = substr($line, 109 + $x, 2);

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

    $newSimEndDate = $db->sql_result($db->sql_query('SELECT Date FROM ibl_box_scores ORDER BY Date DESC LIMIT 1'), 0);

    $queryLastSimDates = $db->sql_query("SELECT * FROM ibl_sim_dates ORDER BY Sim DESC LIMIT 1");

    if ($db->sql_numrows($queryLastSimDates) != 0) {
        $lastSimNumber = $db->sql_result($queryLastSimDates, 0, "Sim");
        $lastSimStartDate = $db->sql_result($queryLastSimDates, 0, "Start Date");
        $lastSimEndDate = $db->sql_result($queryLastSimDates, 0, "End Date");

        if ($lastSimEndDate != $newSimEndDate) {
            $dateObjectForNewSimEndDate = date_create($lastSimEndDate);
            date_modify($dateObjectForNewSimEndDate, '+1 day');
            $newSimStartDate = date_format($dateObjectForNewSimEndDate, 'Y-m-d');

            $newSimNumber = $lastSimNumber + 1;

            $insertNewSimDates = $db->sql_query("INSERT INTO ibl_sim_dates (`Sim`, `Start Date`, `End Date`) VALUES ('$newSimNumber', '$newSimStartDate', '$newSimEndDate');");
        } else {
            echo "<p>Number of .sco lines processed: $numberOfLinesProcessed
            <p>Looks like new box scores haven't been added.<br>Sim Start/End Dates will stay set to $lastSimStartDate and $lastSimEndDate.";
            die();
        }
    } else {
        $newSimNumber = 1;
        $newSimStartDate = $db->sql_result($db->sql_query('SELECT Date FROM ibl_box_scores ORDER BY Date ASC LIMIT 1'), 0);

        $insertNewSimDates = $db->sql_query("INSERT INTO ibl_sim_dates (`Sim`, `Start Date`, `End Date`) VALUES ('$newSimNumber', '$newSimStartDate', '$newSimEndDate');");
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
    $seasonEndingYear = $_POST['seasonEndingYear'];
    $seasonPhase = $_POST['seasonPhase'];
}

scoParser($uploadedFilePath, $seasonEndingYear, $seasonPhase);
