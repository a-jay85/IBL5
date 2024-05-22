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
        $boxscoreGameInfo = Boxscore::withGameInfoLine($gameInfoLine, $operatingSeasonEndingYear, $operatingSeasonPhase);

        for ($i = 0; $i < 30; $i++) {
            $x = $i * 53; // 53 = amount of characters to skip to get to the next player's/team's data line
            $playerInfoLine = substr($line, 58 + $x, 53);
            $playerStats = PlayerStats::withBoxscoreInfoLine($db, $playerInfoLine);

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
                '$boxscoreGameInfo->gameDate',
                '$playerStats->name',
                '$playerStats->position',
                $playerStats->playerID,
                $boxscoreGameInfo->visitorTeamID,
                $boxscoreGameInfo->homeTeamID,
                $playerStats->gameMinutesPlayed,
                $playerStats->gameFieldGoalsMade,
                $playerStats->gameFieldGoalsAttempted,
                $playerStats->gameFreeThrowsMade,
                $playerStats->gameFreeThrowsAttempted,
                $playerStats->gameThreePointersMade,
                $playerStats->gameThreePointersAttempted,
                $playerStats->gameOffensiveRebounds,
                $playerStats->gameDefensiveRebounds,
                $playerStats->gameAssists,
                $playerStats->gameSteals,
                $playerStats->gameTurnovers,
                $playerStats->gameBlocks,
                $playerStats->gamePersonalFouls
            )";
            if ($playerStats->name != null || $playerStats->name != '') {
                if ($db->sql_query($entryInsertQuery)) {
                    $numberOfLinesProcessed++;
                    // $entryInsertQuery = str_replace(array("\n", "\t", "\r"), '', $entryInsertQuery); // LOG LINES
                    // echo $entryInsertQuery . "<br>";
                }
            }
        }
    }

    echo "<p>Number of .sco lines processed: $numberOfLinesProcessed";

    $newSimEndDate = $season->getLastBoxScoreDate();

    if ($season->lastSimEndDate) {
        if ($season->lastSimEndDate != $newSimEndDate) {
            $dateObjectForNewSimEndDate = date_create($season->lastSimEndDate);
            date_modify($dateObjectForNewSimEndDate, '+1 day');
            $newSimStartDate = date_format($dateObjectForNewSimEndDate, 'Y-m-d');

            $newSimNumber = $season->lastSimNumber + 1;

            $insertNewSimDates = $season->setLastSimDatesArray($newSimNumber, $newSimStartDate, $newSimEndDate);
        } else {
            echo "<p>Looks like new box scores haven't been added.
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
