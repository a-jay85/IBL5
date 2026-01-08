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
 */
class PlayerStatsRepository extends BaseMysqliRepository implements PlayerStatsRepositoryInterface
{
    /**
     * @see PlayerStatsRepositoryInterface::getPlayerStats()
     */
    public function getPlayerStats(int $playerID): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_plr WHERE pid = ? LIMIT 1",
            "i",
            $playerID
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getHistoricalStats()
     */
    public function getHistoricalStats(int $playerID): array
    {
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
     */
    public function getPlayoffCareerTotals(string $playerName): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_playoff_career_totals WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getPlayoffCareerAverages()
     */
    public function getPlayoffCareerAverages(string $playerName): ?array
    {
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
     */
    public function getHeatCareerTotals(string $playerName): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_heat_career_totals WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getHeatCareerAverages()
     */
    public function getHeatCareerAverages(string $playerName): ?array
    {
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
     */
    public function getOlympicsCareerTotals(string $playerName): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_olympics_career_totals WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getOlympicsCareerAverages()
     */
    public function getOlympicsCareerAverages(string $playerName): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_olympics_career_avgs WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getSeasonCareerAverages()
     */
    public function getSeasonCareerAverages(string $playerName): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_season_career_avgs WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getSeasonCareerAveragesById()
     */
    public function getSeasonCareerAveragesById(int $playerID): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_season_career_avgs WHERE pid = ?",
            "i",
            $playerID
        );
    }
}
