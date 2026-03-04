<?php

declare(strict_types=1);

namespace Boxscore;

use Boxscore\Contracts\BoxscoreRepositoryInterface;
use League\LeagueContext;

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
    private ?LeagueContext $leagueContext;
    private string $playerTable;
    private string $teamTable;

    /**
     * Constructor
     *
     * @param \mysqli $db Active mysqli connection
     */
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db);
        $this->leagueContext = $leagueContext;
        $this->playerTable = $this->resolveTable('ibl_box_scores');
        $this->teamTable = $this->resolveTable('ibl_box_scores_teams');
    }

    /**
     * Resolve a table name through LeagueContext (if set), else return as-is
     */
    private function resolveTable(string $iblTableName): string
    {
        return $this->leagueContext !== null
            ? $this->leagueContext->getTableName($iblTableName)
            : $iblTableName;
    }

    /**
     * @see BoxscoreRepositoryInterface::deletePreseasonBoxScores()
     */
    public function deletePreseasonBoxScores(): bool
    {
        $preseasonYear = \Season::IBL_PRESEASON_YEAR;
        $startDate = "{$preseasonYear}-11-01";
        $endDate = "{$preseasonYear}-11-30";

        return $this->deleteBoxScoresForDateRange($startDate, $endDate);
    }

    /**
     * @see BoxscoreRepositoryInterface::deleteHeatBoxScores()
     */
    public function deleteHeatBoxScores(int $seasonStartingYear): bool
    {
        $heatMonth = str_pad((string) \Season::IBL_HEAT_MONTH, 2, '0', STR_PAD_LEFT);
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
        $regularSeasonMonth = str_pad((string) \Season::IBL_REGULAR_SEASON_STARTING_MONTH, 2, '0', STR_PAD_LEFT);
        $playoffMonth = str_pad((string) \Season::IBL_PLAYOFF_MONTH, 2, '0', STR_PAD_LEFT);

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
        // Delete player boxscores
        $this->execute(
            "DELETE FROM {$this->playerTable} WHERE Date BETWEEN ? AND ?",
            "ss",
            $startDate,
            $endDate
        );

        // Delete team boxscores
        $this->execute(
            "DELETE FROM {$this->teamTable} WHERE Date BETWEEN ? AND ?",
            "ss",
            $startDate,
            $endDate
        );

        return true;
    }

    /**
     * @see BoxscoreRepositoryInterface::findTeamBoxscore()
     */
    public function findTeamBoxscore(string $date, int $visitorTeamID, int $homeTeamID, int $gameOfThatDay): ?array
    {
        return $this->fetchOne(
            "SELECT visitorQ1points, visitorQ2points, visitorQ3points, visitorQ4points, visitorOTpoints,
                    homeQ1points, homeQ2points, homeQ3points, homeQ4points, homeOTpoints
             FROM {$this->teamTable}
             WHERE Date = ? AND visitorTeamID = ? AND homeTeamID = ? AND gameOfThatDay = ?
             LIMIT 1",
            "siii",
            $date,
            $visitorTeamID,
            $homeTeamID,
            $gameOfThatDay
        );
    }

    /**
     * @see BoxscoreRepositoryInterface::deleteTeamBoxscoresByGame()
     */
    public function deleteTeamBoxscoresByGame(string $date, int $visitorTeamID, int $homeTeamID, int $gameOfThatDay): int
    {
        return $this->execute(
            "DELETE FROM {$this->teamTable}
             WHERE Date = ? AND visitorTeamID = ? AND homeTeamID = ? AND gameOfThatDay = ?",
            "siii",
            $date,
            $visitorTeamID,
            $homeTeamID,
            $gameOfThatDay
        );
    }

    /**
     * @see BoxscoreRepositoryInterface::deletePlayerBoxscoresByGame()
     */
    public function deletePlayerBoxscoresByGame(string $date, int $visitorTID, int $homeTID): int
    {
        return $this->execute(
            "DELETE FROM {$this->playerTable}
             WHERE Date = ? AND visitorTID = ? AND homeTID = ?",
            "sii",
            $date,
            $visitorTID,
            $homeTID
        );
    }

    /**
     * @see BoxscoreRepositoryInterface::hasNullTeamIdPlayerBoxscores()
     */
    public function hasNullTeamIdPlayerBoxscores(string $date, int $visitorTID, int $homeTID): bool
    {
        /** @var array{cnt: int}|null $row */
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$this->playerTable}
             WHERE Date = ? AND visitorTID = ? AND homeTID = ? AND pid <> 0 AND teamID IS NULL
             LIMIT 1",
            "sii",
            $date,
            $visitorTID,
            $homeTID
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
             WHERE Date = ? AND visitorTeamID = 50 AND homeTeamID = 51
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
        /** @var list<array{id: int, Date: string, name: string, visitorTeamID: int, homeTeamID: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT id, Date, name, visitorTeamID, homeTeamID
             FROM {$this->teamTable}
             WHERE name IN ('Team Away', 'Team Home')
               AND visitorTeamID = 50 AND homeTeamID = 51
             ORDER BY Date ASC, id ASC",
            ""
        );

        return $rows;
    }

    /**
     * @see BoxscoreRepositoryInterface::getPlayersForAllStarTeam()
     */
    public function getPlayersForAllStarTeam(string $date, int $teamID): array
    {
        /** @var list<array{name: string}> $rows */
        $rows = $this->fetchAll(
            "SELECT COALESCE(p.name, bs.name) AS name
             FROM {$this->playerTable} bs
             LEFT JOIN ibl_plr p ON bs.pid = p.pid
             WHERE bs.Date = ? AND bs.visitorTID = 50 AND bs.homeTID = 51 AND bs.teamID = ?
             ORDER BY bs.id ASC",
            "si",
            $date,
            $teamID
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
        int $gameOfThatDay,
        int $visitorTeamID,
        int $homeTeamID,
        int $attendance,
        int $capacity,
        int $visitorWins,
        int $visitorLosses,
        int $homeWins,
        int $homeLosses,
        int $visitorQ1points,
        int $visitorQ2points,
        int $visitorQ3points,
        int $visitorQ4points,
        int $visitorOTpoints,
        int $homeQ1points,
        int $homeQ2points,
        int $homeQ3points,
        int $homeQ4points,
        int $homeOTpoints,
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
            \Boxscore::teamInsertSql($this->teamTable),
            "ssiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii",
            $date,
            $name,
            $gameOfThatDay,
            $visitorTeamID,
            $homeTeamID,
            $attendance,
            $capacity,
            $visitorWins,
            $visitorLosses,
            $homeWins,
            $homeLosses,
            $visitorQ1points,
            $visitorQ2points,
            $visitorQ3points,
            $visitorQ4points,
            $visitorOTpoints,
            $homeQ1points,
            $homeQ2points,
            $homeQ3points,
            $homeQ4points,
            $homeOTpoints,
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
        int $visitorTeamID,
        int $homeTeamID,
        int $gameOfThatDay,
        int $attendance,
        int $capacity,
        int $visitorWins,
        int $visitorLosses,
        int $homeWins,
        int $homeLosses,
        int $teamID,
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
            \Boxscore::playerInsertSql($this->playerTable),
            "ssssiiiiiiiiiiiiiiiiiiiiiiiii",
            $date,
            $uuid,
            $name,
            $position,
            $playerID,
            $visitorTeamID,
            $homeTeamID,
            $gameOfThatDay,
            $attendance,
            $capacity,
            $visitorWins,
            $visitorLosses,
            $homeWins,
            $homeLosses,
            $teamID,
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
