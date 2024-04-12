<?php

class Boxscore
{
    public $gameDate;
    public $gameYear;
    public $gameMonth;
    public $gameDay;
    public $gameOfThatDay;

    public $visitorTeamID;
    public $homeTeamID;

    public $attendance;
    public $capacity;

    public $visitorWins;
    public $visitorLosses;
    public $homeWins;
    public $homeLosses;

    public $visitorQ1points;
    public $visitorQ2points;
    public $visitorQ3points;
    public $visitorQ4points;
    public $visitorOTpoints;

    public $homeQ1points;
    public $homeQ2points;
    public $homeQ3points;
    public $homeQ4points;
    public $homeOTpoints;

    public $name;
    public $position;
    public $playerID;
    public $gameMinutes;
    public $gameFieldGoalsMade;
    public $gameFieldGoalsAttempted;
    public $gameFreeThrowsMade;
    public $gameFreeThrowsAttempted;
    public $gameThreePointersMade;
    public $gameThreePointersAttempted;
    public $gameOffensiveRebounds;
    public $gameDefensiveRebounds;
    public $gameAssists;
    public $gameSteals;
    public $gameTurnovers;
    public $gameBlocks;
    public $gamePersonalFouls;

    const JSB_PLAYOFF_MONTH = 22;

    public static function deletePreseasonBoxScores($db, $seasonStartingYear)
    {
        $queryDeletePreseasonBoxScores = "DELETE FROM `ibl_box_scores`
            WHERE `Date` BETWEEN '$seasonStartingYear-07-01' AND '$seasonStartingYear-09-01';";

        return $db->sql_result($queryDeletePreseasonBoxScores, 0);
    }

    public static function deleteHEATBoxScores($db, $seasonStartingYear)
    {
        $queryDeleteHEATBoxScores = "DELETE FROM `ibl_box_scores`
            WHERE `Date` BETWEEN '$seasonStartingYear-09-01' AND '$seasonStartingYear-11-01';";

        return $db->sql_result($queryDeleteHEATBoxScores, 0);
    }

    public static function deleteRegularSeasonAndPlayoffsBoxScores($db, $seasonStartingYear)
    {
        $seasonEndingYear = $seasonStartingYear + 1;

        $queryDeleteRegularSeasonAndPlayoffsBoxScores = "DELETE FROM `ibl_box_scores`
            WHERE `Date` BETWEEN '$seasonStartingYear-11-01' AND '$seasonEndingYear-07-01';";

        return $db->sql_result($queryDeleteRegularSeasonAndPlayoffsBoxScores, 0);
    }
}