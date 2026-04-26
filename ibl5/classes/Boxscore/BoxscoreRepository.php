<?php

declare(strict_types=1);

namespace Boxscore;

use Boxscore\Contracts\BoxscoreRepositoryInterface;
use League\League;
use League\LeagueContext;

use Season\Season;
/**
 * BoxscoreRepository - Data access layer for boxscore management
 *
 * Handles deletion of boxscore records for different season phases.
 * Operates on both ibl_box_scores (player stats) and ibl_box_scores_teams tables.
 *
 * @see BoxscoreRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class BoxscoreRepository extends \BaseMysqliRepository implements BoxscoreRepositoryInterface
{
    private string $playerTable;
    private string $teamTable;

    /**
     * Constructor
     *
     * @param \mysqli $db Active mysqli connection
     */
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->playerTable = $this->resolveTable('ibl_box_scores');
        $this->teamTable = $this->resolveTable('ibl_box_scores_teams');
    }

    /**
     * @see BoxscoreRepositoryInterface::deletePreseasonBoxScores()
     */
    public function deletePreseasonBoxScores(int $seasonBeginningYear): bool
    {
        $startDate = "{$seasonBeginningYear}-09-01";
        $endDate = "{$seasonBeginningYear}-10-31";

        return $this->deleteBoxScoresForDateRange($startDate, $endDate);
    }

    /**
     * @see BoxscoreRepositoryInterface::deleteHeatBoxScores()
     */
    public function deleteHeatBoxScores(int $seasonStartingYear): bool
    {
        $heatMonth = str_pad((string) Season::IBL_HEAT_MONTH, 2, '0', STR_PAD_LEFT);
        $startDate = "{$seasonStartingYear}-{$heatMonth}-01";
        $endDate = "{$seasonStartingYear}-{$heatMonth}-31";

        return $this->deleteBoxScoresForDateRange($startDate, $endDate);
    }

    /**
     * @see BoxscoreRepositoryInterface::deleteRegularSeasonAndPlayoffsBoxScores()
     */
    public function deleteRegularSeasonAndPlayoffsBoxScores(int $seasonStartingYear): bool
    {
        $seasonEndingYear = $seasonStartingYear + 1;
        $regularSeasonMonth = str_pad((string) Season::IBL_REGULAR_SEASON_STARTING_MONTH, 2, '0', STR_PAD_LEFT);
        $playoffMonth = str_pad((string) Season::IBL_PLAYOFF_MONTH, 2, '0', STR_PAD_LEFT);

        $startDate = "{$seasonStartingYear}-{$regularSeasonMonth}-01";
        $endDate = "{$seasonEndingYear}-{$playoffMonth}-30";

        return $this->deleteBoxScoresForDateRange($startDate, $endDate);
    }

    /**
     * Delete boxscores for both players and teams within a date range
     *
     * @param string $startDate Start date in Y-m-d format
     * @param string $endDate End date in Y-m-d format
     * @return true Always returns true since execute() returns int on success
     */
    private function deleteBoxScoresForDateRange(string $startDate, string $endDate): true
    {
        $this->transactional(function () use ($startDate, $endDate): void {
            $this->execute(
                "DELETE FROM {$this->playerTable} WHERE game_date BETWEEN ? AND ?",
                "ss",
                $startDate,
                $endDate
            );

            $this->execute(
                "DELETE FROM {$this->teamTable} WHERE game_date BETWEEN ? AND ?",
                "ss",
                $startDate,
                $endDate
            );
        });

        return true;
    }

    /**
     * @see BoxscoreRepositoryInterface::findTeamBoxscore()
     */
    public function findTeamBoxscore(string $date, int $visitor_teamid, int $home_teamid, int $game_of_that_day): ?array
    {
        return $this->fetchOne(
            "SELECT visitor_q1_points, visitor_q2_points, visitor_q3_points, visitor_q4_points, visitor_ot_points,
                    home_q1_points, home_q2_points, home_q3_points, home_q4_points, home_ot_points
             FROM {$this->teamTable}
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? AND game_of_that_day = ?
             LIMIT 1",
            "siii",
            $date,
            $visitor_teamid,
            $home_teamid,
            $game_of_that_day
        );
    }

    /**
     * @see BoxscoreRepositoryInterface::deleteTeamBoxscoresByGame()
     */
    public function deleteTeamBoxscoresByGame(string $date, int $visitor_teamid, int $home_teamid, int $game_of_that_day): int
    {
        return $this->execute(
            "DELETE FROM {$this->teamTable}
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? AND game_of_that_day = ?",
            "siii",
            $date,
            $visitor_teamid,
            $home_teamid,
            $game_of_that_day
        );
    }

    /**
     * @see BoxscoreRepositoryInterface::deletePlayerBoxscoresByGame()
     */
    public function deletePlayerBoxscoresByGame(string $date, int $visitor_teamid, int $home_teamid): int
    {
        return $this->execute(
            "DELETE FROM {$this->playerTable}
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?",
            "sii",
            $date,
            $visitor_teamid,
            $home_teamid
        );
    }

    /**
     * @see BoxscoreRepositoryInterface::hasNullTeamIdPlayerBoxscores()
     */
    public function hasNullTeamIdPlayerBoxscores(string $date, int $visitor_teamid, int $home_teamid): bool
    {
        /** @var array{cnt: int}|null $row */
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$this->playerTable}
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? AND pid <> 0 AND teamid IS NULL
             LIMIT 1",
            "sii",
            $date,
            $visitor_teamid,
            $home_teamid
        );

        return $row !== null && $row['cnt'] > 0;
    }

    /**
     * @see BoxscoreRepositoryInterface::findAllStarTeamNames()
     */
    public function findAllStarTeamNames(string $date): ?array
    {
        /** @var list<array{name: string}> $rows */
        $rows = $this->fetchAll(
            "SELECT name FROM {$this->teamTable}
             WHERE game_date = ? AND visitor_teamid = " . League::ALL_STAR_AWAY_TEAMID . " AND home_teamid = " . League::ALL_STAR_HOME_TEAMID . "
             ORDER BY id ASC
             LIMIT 2",
            "s",
            $date
        );

        if (count($rows) < 2) {
            return null;
        }

        return [
            'awayName' => $rows[0]['name'],
            'homeName' => $rows[1]['name'],
        ];
    }

    /**
     * @see BoxscoreRepositoryInterface::findAllStarGamesWithDefaultNames()
     */
    public function findAllStarGamesWithDefaultNames(): array
    {
        /** @var list<array{id: int, game_date: string, name: string, visitor_teamid: int, home_teamid: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT id, game_date, name, visitor_teamid, home_teamid
             FROM {$this->teamTable}
             WHERE name IN ('Team Away', 'Team Home')
               AND visitor_teamid = " . League::ALL_STAR_AWAY_TEAMID . " AND home_teamid = " . League::ALL_STAR_HOME_TEAMID . "
             ORDER BY game_date ASC, id ASC",
            ""
        );

        return $rows;
    }

    /**
     * @see BoxscoreRepositoryInterface::getPlayersForAllStarTeam()
     */
    public function getPlayersForAllStarTeam(string $date, int $teamid): array
    {
        /** @var list<array{name: string}> $rows */
        $rows = $this->fetchAll(
            "SELECT COALESCE(p.name, bs.name) AS name
             FROM {$this->playerTable} bs
             LEFT JOIN ibl_plr p ON bs.pid = p.pid
             WHERE bs.game_date = ? AND bs.visitor_teamid = " . League::ALL_STAR_AWAY_TEAMID . " AND bs.home_teamid = " . League::ALL_STAR_HOME_TEAMID . " AND bs.teamid = ?
             ORDER BY bs.id ASC",
            "si",
            $date,
            $teamid
        );

        $names = [];
        foreach ($rows as $row) {
            $names[] = $row['name'];
        }

        return $names;
    }

    /**
     * @see BoxscoreRepositoryInterface::renameAllStarTeam()
     */
    public function renameAllStarTeam(int $recordId, string $newName): int
    {
        return $this->execute(
            "UPDATE {$this->teamTable} SET name = ? WHERE id = ?",
            "si",
            $newName,
            $recordId
        );
    }

    /**
     * @see BoxscoreRepositoryInterface::insertTeamBoxscore()
     */
    public function insertTeamBoxscore(
        string $date,
        string $name,
        int $game_of_that_day,
        int $visitor_teamid,
        int $home_teamid,
        int $attendance,
        int $capacity,
        int $visitor_wins,
        int $visitor_losses,
        int $home_wins,
        int $home_losses,
        int $visitor_q1_points,
        int $visitor_q2_points,
        int $visitor_q3_points,
        int $visitor_q4_points,
        int $visitor_ot_points,
        int $home_q1_points,
        int $home_q2_points,
        int $home_q3_points,
        int $home_q4_points,
        int $home_ot_points,
        int $fieldGoalsMade,
        int $fieldGoalsAttempted,
        int $freeThrowsMade,
        int $freeThrowsAttempted,
        int $threePointersMade,
        int $threePointersAttempted,
        int $offensiveRebounds,
        int $defensiveRebounds,
        int $assists,
        int $steals,
        int $turnovers,
        int $blocks,
        int $personalFouls,
    ): int {
        return $this->execute(
            Boxscore::teamInsertSql($this->teamTable),
            "ssiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii",
            $date,
            $name,
            $game_of_that_day,
            $visitor_teamid,
            $home_teamid,
            $attendance,
            $capacity,
            $visitor_wins,
            $visitor_losses,
            $home_wins,
            $home_losses,
            $visitor_q1_points,
            $visitor_q2_points,
            $visitor_q3_points,
            $visitor_q4_points,
            $visitor_ot_points,
            $home_q1_points,
            $home_q2_points,
            $home_q3_points,
            $home_q4_points,
            $home_ot_points,
            $fieldGoalsMade,
            $fieldGoalsAttempted,
            $freeThrowsMade,
            $freeThrowsAttempted,
            $threePointersMade,
            $threePointersAttempted,
            $offensiveRebounds,
            $defensiveRebounds,
            $assists,
            $steals,
            $turnovers,
            $blocks,
            $personalFouls,
        );
    }

    /**
     * @see BoxscoreRepositoryInterface::insertPlayerBoxscore()
     */
    public function insertPlayerBoxscore(
        string $date,
        string $uuid,
        string $name,
        string $position,
        int $playerID,
        int $visitor_teamid,
        int $home_teamid,
        int $game_of_that_day,
        int $attendance,
        int $capacity,
        int $visitor_wins,
        int $visitor_losses,
        int $home_wins,
        int $home_losses,
        int $teamid,
        int $minutesPlayed,
        int $fieldGoalsMade,
        int $fieldGoalsAttempted,
        int $freeThrowsMade,
        int $freeThrowsAttempted,
        int $threePointersMade,
        int $threePointersAttempted,
        int $offensiveRebounds,
        int $defensiveRebounds,
        int $assists,
        int $steals,
        int $turnovers,
        int $blocks,
        int $personalFouls,
    ): int {
        return $this->execute(
            Boxscore::playerInsertSql($this->playerTable),
            "ssssiiiiiiiiiiiiiiiiiiiiiiiii",
            $date,
            $uuid,
            $name,
            $position,
            $playerID,
            $visitor_teamid,
            $home_teamid,
            $game_of_that_day,
            $attendance,
            $capacity,
            $visitor_wins,
            $visitor_losses,
            $home_wins,
            $home_losses,
            $teamid,
            $minutesPlayed,
            $fieldGoalsMade,
            $fieldGoalsAttempted,
            $freeThrowsMade,
            $freeThrowsAttempted,
            $threePointersMade,
            $threePointersAttempted,
            $offensiveRebounds,
            $defensiveRebounds,
            $assists,
            $steals,
            $turnovers,
            $blocks,
            $personalFouls,
        );
    }
}
