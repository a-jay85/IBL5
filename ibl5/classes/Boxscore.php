<?php

declare(strict_types=1);

class Boxscore
{
    public string $gameDate;
    public int $gameYear;
    public string $gameMonth;
    public string $gameDay;
    public int $gameOfThatDay;

    public int $visitorTeamID;
    public int $homeTeamID;

    public string $attendance;
    public string $capacity;

    public string $visitorWins;
    public string $visitorLosses;
    public string $homeWins;
    public string $homeLosses;

    public string $visitorQ1points;
    public string $visitorQ2points;
    public string $visitorQ3points;
    public string $visitorQ4points;
    public string $visitorOTpoints;

    public string $homeQ1points;
    public string $homeQ2points;
    public string $homeQ3points;
    public string $homeQ4points;
    public string $homeOTpoints;

    const PLAYERSTATEMENT_PREPARE = "INSERT INTO ibl_box_scores (
        Date,
        uuid,
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
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"; 

    const TEAMSTATEMENT_PREPARE = "INSERT INTO ibl_box_scores_teams (
        Date,
        name,
        gameOfThatDay,
        visitorTeamID,
        homeTeamID,
        attendance,
        capacity,
        visitorWins,
        visitorLosses,
        homeWins,
        homeLosses,
        visitorQ1points,
        visitorQ2points,
        visitorQ3points,
        visitorQ4points,
        visitorOTpoints,
        homeQ1points,
        homeQ2points,
        homeQ3points,
        homeQ4points,
        homeOTpoints,
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
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    protected function fillGameInfo(string $gameInfoLine, int $seasonEndingYear, string $seasonPhase): void
    {
        $this->gameYear = $seasonEndingYear;
        $this->gameMonth = sprintf("%02u", intval(substr($gameInfoLine, 0, 2)) + 10); // sprintf() prepends 0 if the result isn't in double-digits
        $this->gameDay = sprintf("%02u", intval(substr($gameInfoLine, 2, 2)) + 1);
        $this->gameOfThatDay = intval(substr($gameInfoLine, 4, 2)) + 1;
        $this->visitorTeamID = intval(substr($gameInfoLine, 6, 2)) + 1;
        $this->homeTeamID = intval(substr($gameInfoLine, 8, 2)) + 1;
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
        if ((int)$this->gameMonth > 12 && (int)$this->gameMonth !== JSB::PLAYOFF_MONTH) {
            $this->gameMonth = sprintf("%02u", (int)$this->gameMonth - 12);
        } elseif ((int)$this->gameMonth === JSB::PLAYOFF_MONTH) {
            $this->gameMonth = sprintf("%02u", (int)$this->gameMonth - 16); // This hacks the Playoffs to be in "June"
        } elseif ((int)$this->gameMonth > 10) {
            $this->gameYear = $seasonStartingYear;
            if ($seasonPhase === "HEAT") {
                $this->gameMonth = (string) Season::IBL_HEAT_MONTH;
            }
        }

        $this->gameDate = $this->gameYear . '-' . $this->gameMonth . '-' . $this->gameDay;
    }

    public static function withGameInfoLine(string $gameInfoLine, int $seasonEndingYear, string $seasonPhase): self
    {
        $instance = new self();
        $instance->fillGameInfo($gameInfoLine, $seasonEndingYear, $seasonPhase);
        return $instance;
    }

    /**
     * Delete preseason boxscores for both players and teams
     *
     * @param object $db Active mysqli connection
     * @return bool True if both deletions succeeded
     */
    public static function deletePreseasonBoxScores(object $db): bool
    {
        $repository = new \Boxscore\BoxscoreRepository($db);
        return $repository->deletePreseasonBoxScores();
    }

    /**
     * Delete H.E.A.T. tournament boxscores for both players and teams
     *
     * @param object $db Active mysqli connection
     * @param int $seasonStartingYear The year the season starts
     * @return bool True if both deletions succeeded
     */
    public static function deleteHEATBoxScores(object $db, int $seasonStartingYear): bool
    {
        $repository = new \Boxscore\BoxscoreRepository($db);
        return $repository->deleteHeatBoxScores($seasonStartingYear);
    }

    /**
     * Delete regular season and playoff boxscores for both players and teams
     *
     * @param object $db Active mysqli connection
     * @param int $seasonStartingYear The year the season starts
     * @return bool True if both deletions succeeded
     */
    public static function deleteRegularSeasonAndPlayoffsBoxScores(object $db, int $seasonStartingYear): bool
    {
        $repository = new \Boxscore\BoxscoreRepository($db);
        return $repository->deleteRegularSeasonAndPlayoffsBoxScores($seasonStartingYear);
    }
}