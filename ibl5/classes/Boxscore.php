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

    protected function fillGameInfo($gameInfoLine, $seasonEndingYear, $seasonPhase)
    {
        $this->gameYear = $seasonEndingYear;
        @$this->gameMonth = sprintf("%02u", substr($gameInfoLine, 0, 2) + 10); // sprintf() prepends 0 if the result isn't in double-digits
        @$this->gameDay = sprintf("%02u", substr($gameInfoLine, 2, 2) + 1);
        @$this->gameOfThatDay = substr($gameInfoLine, 4, 2) + 1;
        @$this->visitorTeamID = substr($gameInfoLine, 6, 2) + 1;
        @$this->homeTeamID = substr($gameInfoLine, 8, 2) + 1;
        $this->attendance = substr($gameInfoLine, 10, 5);
        $this->capacity = substr($gameInfoLine, 15, 5);
        $this->visitorWins = substr($gameInfoLine, 20, 2);
        $this->visitorLosses = substr($gameInfoLine, 22, 2);
        $this->homeWins = substr($gameInfoLine, 24, 2);
        $this->homeLosses = substr($gameInfoLine, 26, 2);
        $this->visitorQ1points = substr($gameInfoLine, 28, 3);
        $this->visitorQ2points = substr($gameInfoLine, 31, 3);
        $this->visitorQ3points = substr($gameInfoLine, 34, 3);
        $this->visitorQ4points = substr($gameInfoLine, 37, 3);
        $this->visitorOTpoints = substr($gameInfoLine, 40, 3);
        $this->homeQ1points = substr($gameInfoLine, 43, 3);
        $this->homeQ2points = substr($gameInfoLine, 46, 3);
        $this->homeQ3points = substr($gameInfoLine, 49, 3);
        $this->homeQ4points = substr($gameInfoLine, 52, 3);
        $this->homeOTpoints = substr($gameInfoLine, 55, 3);

        $seasonStartingYear = $seasonEndingYear - 1;
        if ($this->gameMonth > 12 and $this->gameMonth != Season::JSB_PLAYOFF_MONTH) {
            $this->gameMonth = sprintf("%02u", $this->gameMonth - 12);
        } elseif ($this->gameMonth == Season::JSB_PLAYOFF_MONTH) {
            $this->gameMonth = sprintf("%02u", $this->gameMonth - 16); // TODO: not have to hack the Playoffs to be in June
        } elseif ($this->gameMonth > 10) {
            $this->gameYear = $seasonStartingYear;
            if ($seasonPhase == "HEAT") {
                $this->gameMonth = Season::IBL_HEAT_MONTH;
            }
            if ($seasonPhase == "Preseason") {
                $this->gameMonth = Season::IBL_PRESEASON_MONTH;
            }
        }

        $this->gameDate = $this->gameYear . '-' . $this->gameMonth . '-' . $this->gameDay;
    }

    public static function withGameInfoLine($gameInfoLine, $seasonEndingYear, $seasonPhase)
    {
        $instance = new self();
        $instance->fillGameInfo($gameInfoLine, $seasonEndingYear, $seasonPhase);
        return $instance;
    }

    public static function deletePreseasonBoxScores($db, $seasonStartingYear)
    {
        $queryDeletePreseasonBoxScores = "DELETE FROM `ibl_box_scores`
            WHERE `Date` BETWEEN '$seasonStartingYear-07-01' AND '$seasonStartingYear-09-01';";

        return $db->sql_query($queryDeletePreseasonBoxScores, 0);
    }

    public static function deleteHEATBoxScores($db, $seasonStartingYear)
    {
        $queryDeleteHEATBoxScores = "DELETE FROM `ibl_box_scores`
            WHERE `Date` BETWEEN '$seasonStartingYear-09-01' AND '$seasonStartingYear-11-01';";

        return $db->sql_query($queryDeleteHEATBoxScores, 0);
    }

    public static function deleteRegularSeasonAndPlayoffsBoxScores($db, $seasonStartingYear)
    {
        $seasonEndingYear = $seasonStartingYear + 1;

        $queryDeleteRegularSeasonAndPlayoffsBoxScores = "DELETE FROM `ibl_box_scores`
            WHERE `Date` BETWEEN '$seasonStartingYear-11-01' AND '$seasonEndingYear-07-01';";

        return $db->sql_query($queryDeleteRegularSeasonAndPlayoffsBoxScores, 0);
    }
}