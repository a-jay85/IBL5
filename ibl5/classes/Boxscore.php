<?php

class Boxscore
{
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