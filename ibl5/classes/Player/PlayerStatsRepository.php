<?php

declare(strict_types=1);

namespace Player;

use BaseMysqliRepository;
use Player\Contracts\PlayerStatsRepositoryInterface;

/**
 * PlayerStatsRepository - Database operations for player statistics
 *
 * Extends BaseMysqliRepository for standardized prepared statement handling.
 * Provides access to current season stats, historical stats, box scores,
 * and specialized stats (playoffs, HEAT, Olympics).
 *
 * @see PlayerStatsRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type StatsRow from \Player\Contracts\PlayerStatsRepositoryInterface
 * @phpstan-import-type CareerTotalsRow from \Player\Contracts\PlayerStatsRepositoryInterface
 * @phpstan-import-type CareerAveragesRow from \Player\Contracts\PlayerStatsRepositoryInterface
 */
class PlayerStatsRepository extends BaseMysqliRepository implements PlayerStatsRepositoryInterface
{
    /**
     * @see PlayerStatsRepositoryInterface::getPlayerStats()
     * @return PlayerRow|null
     */
    public function getPlayerStats(int $playerID): ?array
    {
        /** @var PlayerRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_plr WHERE pid = ? LIMIT 1",
            "i",
            $playerID
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getHistoricalStats()
     * @return list<StatsRow>
     */
    public function getHistoricalStats(int $playerID): array
    {
        /** @var list<StatsRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_hist WHERE pid = ? ORDER BY year ASC",
            "i",
            $playerID
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getBoxScoresBetweenDates()
     */
    public function getBoxScoresBetweenDates(int $playerID, string $startDate, string $endDate): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_box_scores WHERE pid = ? AND Date BETWEEN ? AND ? ORDER BY Date ASC",
            "iss",
            $playerID,
            $startDate,
            $endDate
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getSimDates()
     */
    public function getSimDates(int $limit = 20): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_sim_dates ORDER BY sim DESC LIMIT ?",
            "i",
            $limit
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getPlayoffStats()
     */
    public function getPlayoffStats(string $playerName): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_playoff_stats WHERE name = ? ORDER BY year ASC",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getPlayoffCareerTotals()
     * @return CareerTotalsRow|null
     */
    public function getPlayoffCareerTotals(string $playerName): ?array
    {
        /** @var CareerTotalsRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_playoff_career_totals WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getPlayoffCareerAverages()
     * @return CareerAveragesRow|null
     */
    public function getPlayoffCareerAverages(string $playerName): ?array
    {
        /** @var CareerAveragesRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_playoff_career_avgs WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getHeatStats()
     */
    public function getHeatStats(string $playerName): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_heat_stats WHERE name = ? ORDER BY year ASC",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getHeatCareerTotals()
     * @return CareerTotalsRow|null
     */
    public function getHeatCareerTotals(string $playerName): ?array
    {
        /** @var CareerTotalsRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_heat_career_totals WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getHeatCareerAverages()
     * @return CareerAveragesRow|null
     */
    public function getHeatCareerAverages(string $playerName): ?array
    {
        /** @var CareerAveragesRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_heat_career_avgs WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getOlympicsStats()
     */
    public function getOlympicsStats(string $playerName): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_olympics_stats WHERE name = ? ORDER BY year ASC",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getOlympicsCareerTotals()
     * @return CareerTotalsRow|null
     */
    public function getOlympicsCareerTotals(string $playerName): ?array
    {
        /** @var CareerTotalsRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_olympics_career_totals WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getOlympicsCareerAverages()
     * @return CareerAveragesRow|null
     */
    public function getOlympicsCareerAverages(string $playerName): ?array
    {
        /** @var CareerAveragesRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_olympics_career_avgs WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getSeasonCareerAverages()
     * @return CareerAveragesRow|null
     */
    public function getSeasonCareerAverages(string $playerName): ?array
    {
        /** @var CareerAveragesRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_season_career_avgs WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getSeasonCareerAveragesById()
     * @return CareerAveragesRow|null
     */
    public function getSeasonCareerAveragesById(int $playerID): ?array
    {
        /** @var CareerAveragesRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_season_career_avgs WHERE pid = ?",
            "i",
            $playerID
        );
    }
}
