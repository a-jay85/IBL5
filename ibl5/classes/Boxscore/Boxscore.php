<?php

declare(strict_types=1);

namespace Boxscore;

use JSB;
use Season\Season;

class Boxscore
{
    /** Maximum player name length in ibl_box_scores.name (varchar(16)) */
    public const MAX_PLAYER_NAME_LENGTH = 16;

    public string $gameDate;
    public int $gameYear;
    public string $gameMonth;
    public string $gameDay;
    public int $game_of_that_day;

    public int $visitor_teamid;
    public int $home_teamid;

    public string $attendance;
    public string $capacity;

    public string $visitor_wins;
    public string $visitor_losses;
    public string $home_wins;
    public string $home_losses;

    public string $visitor_q1_points;
    public string $visitor_q2_points;
    public string $visitor_q3_points;
    public string $visitor_q4_points;
    public string $visitor_ot_points;

    public string $home_q1_points;
    public string $home_q2_points;
    public string $home_q3_points;
    public string $home_q4_points;
    public string $home_ot_points;

    const PLAYERSTATEMENT_PREPARE = "INSERT INTO ibl_box_scores (
        game_date,
        uuid,
        name,
        pos,
        pid,
        visitor_teamid,
        home_teamid,
        game_of_that_day,
        attendance,
        capacity,
        visitor_wins,
        visitor_losses,
        home_wins,
        home_losses,
        teamid,
        game_min,
        game_2gm,
        game_2ga,
        game_ftm,
        game_fta,
        game_3gm,
        game_3ga,
        game_orb,
        game_drb,
        game_ast,
        game_stl,
        game_tov,
        game_blk,
        game_pf
    )
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    const TEAMSTATEMENT_PREPARE = "INSERT INTO ibl_box_scores_teams (
        game_date,
        name,
        game_of_that_day,
        visitor_teamid,
        home_teamid,
        attendance,
        capacity,
        visitor_wins,
        visitor_losses,
        home_wins,
        home_losses,
        visitor_q1_points,
        visitor_q2_points,
        visitor_q3_points,
        visitor_q4_points,
        visitor_ot_points,
        home_q1_points,
        home_q2_points,
        home_q3_points,
        home_q4_points,
        home_ot_points,
        game_2gm,
        game_2ga,
        game_ftm,
        game_fta,
        game_3gm,
        game_3ga,
        game_orb,
        game_drb,
        game_ast,
        game_stl,
        game_tov,
        game_blk,
        game_pf
    )
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    public static function playerInsertSql(string $table): string
    {
        return "INSERT INTO {$table} (
        game_date,
        uuid,
        name,
        pos,
        pid,
        visitor_teamid,
        home_teamid,
        game_of_that_day,
        attendance,
        capacity,
        visitor_wins,
        visitor_losses,
        home_wins,
        home_losses,
        teamid,
        game_min,
        game_2gm,
        game_2ga,
        game_ftm,
        game_fta,
        game_3gm,
        game_3ga,
        game_orb,
        game_drb,
        game_ast,
        game_stl,
        game_tov,
        game_blk,
        game_pf
    )
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    }

    public static function teamInsertSql(string $table): string
    {
        return "INSERT INTO {$table} (
        game_date,
        name,
        game_of_that_day,
        visitor_teamid,
        home_teamid,
        attendance,
        capacity,
        visitor_wins,
        visitor_losses,
        home_wins,
        home_losses,
        visitor_q1_points,
        visitor_q2_points,
        visitor_q3_points,
        visitor_q4_points,
        visitor_ot_points,
        home_q1_points,
        home_q2_points,
        home_q3_points,
        home_q4_points,
        home_ot_points,
        game_2gm,
        game_2ga,
        game_ftm,
        game_fta,
        game_3gm,
        game_3ga,
        game_orb,
        game_drb,
        game_ast,
        game_stl,
        game_tov,
        game_blk,
        game_pf
    )
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    }

    protected function fillGameInfo(string $gameInfoLine, int $seasonEndingYear, string $seasonPhase, string $league = 'ibl'): void
    {
        $this->gameYear = $seasonEndingYear;
        $this->gameMonth = sprintf("%02u", intval(substr($gameInfoLine, 0, 2)) + 10); // sprintf() prepends 0 if the result isn't in double-digits
        $this->gameDay = sprintf("%02u", intval(substr($gameInfoLine, 2, 2)) + 1);
        $this->game_of_that_day = intval(substr($gameInfoLine, 4, 2)) + 1;
        $this->visitor_teamid = intval(substr($gameInfoLine, 6, 2)) + 1;
        $this->home_teamid = intval(substr($gameInfoLine, 8, 2)) + 1;
        $this->attendance = substr($gameInfoLine, 10, 5);
        $this->capacity = substr($gameInfoLine, 15, 5);
        $this->visitor_wins = substr($gameInfoLine, 20, 2);
        $this->visitor_losses = substr($gameInfoLine, 22, 2);
        $this->home_wins = substr($gameInfoLine, 24, 2);
        $this->home_losses = substr($gameInfoLine, 26, 2);
        $this->visitor_q1_points = substr($gameInfoLine, 28, 3);
        $this->visitor_q2_points = substr($gameInfoLine, 31, 3);
        $this->visitor_q3_points = substr($gameInfoLine, 34, 3);
        $this->visitor_q4_points = substr($gameInfoLine, 37, 3);
        $this->visitor_ot_points = substr($gameInfoLine, 40, 3);
        $this->home_q1_points = substr($gameInfoLine, 43, 3);
        $this->home_q2_points = substr($gameInfoLine, 46, 3);
        $this->home_q3_points = substr($gameInfoLine, 49, 3);
        $this->home_q4_points = substr($gameInfoLine, 52, 3);
        $this->home_ot_points = substr($gameInfoLine, 55, 3);

        // Olympics: all games occur in August of the ending year
        if (strtolower($league) === 'olympics') {
            $this->gameMonth = sprintf("%02u", Season::IBL_OLYMPICS_MONTH);
            $this->gameYear = $seasonEndingYear;
        } else {
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
        }

        $this->gameDate = $this->gameYear . '-' . $this->gameMonth . '-' . $this->gameDay;
    }

    public static function withGameInfoLine(string $gameInfoLine, int $seasonEndingYear, string $seasonPhase, string $league = 'ibl'): self
    {
        $instance = new self();
        $instance->fillGameInfo($gameInfoLine, $seasonEndingYear, $seasonPhase, $league);
        return $instance;
    }

    /**
     * Override game metadata after parsing (used for All-Star Weekend games)
     */
    public function overrideGameContext(
        string $gameDate,
        int $visitor_teamid,
        int $home_teamid,
        int $game_of_that_day,
    ): void {
        $this->gameDate = $gameDate;
        $this->visitor_teamid = $visitor_teamid;
        $this->home_teamid = $home_teamid;
        $this->game_of_that_day = $game_of_that_day;
    }

    /**
     * Check whether the parsed game scores match an existing database row
     *
     * Compares the sum of visitor and home quarter scores from $this (strings from .sco file)
     * against the same sums from $dbRow (ints from DB with native types enabled).
     *
     * @param array{visitor_q1_points: int, visitor_q2_points: int, visitor_q3_points: int, visitor_q4_points: int, visitor_ot_points: int, home_q1_points: int, home_q2_points: int, home_q3_points: int, home_q4_points: int, home_ot_points: int} $dbRow
     * @return bool True if both visitor and home totals match
     */
    public function scoresMatchDatabase(array $dbRow): bool
    {
        $parsedVisitorTotal = (int) $this->visitor_q1_points + (int) $this->visitor_q2_points
            + (int) $this->visitor_q3_points + (int) $this->visitor_q4_points + (int) $this->visitor_ot_points;
        $parsedHomeTotal = (int) $this->home_q1_points + (int) $this->home_q2_points
            + (int) $this->home_q3_points + (int) $this->home_q4_points + (int) $this->home_ot_points;

        $dbVisitorTotal = $dbRow['visitor_q1_points'] + $dbRow['visitor_q2_points']
            + $dbRow['visitor_q3_points'] + $dbRow['visitor_q4_points'] + $dbRow['visitor_ot_points'];
        $dbHomeTotal = $dbRow['home_q1_points'] + $dbRow['home_q2_points']
            + $dbRow['home_q3_points'] + $dbRow['home_q4_points'] + $dbRow['home_ot_points'];

        return $parsedVisitorTotal === $dbVisitorTotal && $parsedHomeTotal === $dbHomeTotal;
    }

    /**
     * Delete preseason boxscores for both players and teams
     *
     * @param \mysqli $db Active mysqli connection
     * @return bool True if both deletions succeeded
     */
    public static function deletePreseasonBoxScores(\mysqli $db, int $seasonBeginningYear): bool
    {
        $repository = new BoxscoreRepository($db);
        return $repository->deletePreseasonBoxScores($seasonBeginningYear);
    }

    /**
     * Delete H.E.A.T. tournament boxscores for both players and teams
     *
     * @param \mysqli $db Active mysqli connection
     * @param int $seasonStartingYear The year the season starts
     * @return bool True if both deletions succeeded
     */
    public static function deleteHEATBoxScores(\mysqli $db, int $seasonStartingYear): bool
    {
        $repository = new BoxscoreRepository($db);
        return $repository->deleteHeatBoxScores($seasonStartingYear);
    }

    /**
     * Delete regular season and playoff boxscores for both players and teams
     *
     * @param \mysqli $db Active mysqli connection
     * @param int $seasonStartingYear The year the season starts
     * @return bool True if both deletions succeeded
     */
    public static function deleteRegularSeasonAndPlayoffsBoxScores(\mysqli $db, int $seasonStartingYear): bool
    {
        $repository = new BoxscoreRepository($db);
        return $repository->deleteRegularSeasonAndPlayoffsBoxScores($seasonStartingYear);
    }
}