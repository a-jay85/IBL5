<?php

declare(strict_types=1);

namespace Scripts;

use Scripts\Contracts\LeaderboardRepositoryInterface;

/**
 * LeaderboardRepository - Database operations for leaderboard updates
 *
 * Handles CRUD operations for career totals and averages across
 * H.E.A.T., Playoffs, and Season leaderboards using prepared statements.
 *
 * @see LeaderboardRepositoryInterface
 */
class LeaderboardRepository extends \BaseMysqliRepository implements LeaderboardRepositoryInterface
{
    /**
     * Allowed stats tables for whitelist validation
     * Includes both raw stats tables and career totals (used as source for averages)
     */
    private const ALLOWED_STATS_TABLES = [
        'ibl_heat_stats',
        'ibl_playoff_stats',
        'ibl_heat_career_totals',
        'ibl_playoff_career_totals',
    ];

    /**
     * Allowed career tables for whitelist validation
     */
    private const ALLOWED_CAREER_TABLES = [
        'ibl_heat_career_totals',
        'ibl_heat_career_avgs',
        'ibl_playoff_career_totals',
        'ibl_playoff_career_avgs',
        'ibl_season_career_avgs',
    ];

    /**
     * @see LeaderboardRepositoryInterface::getAllPlayers()
     */
    public function getAllPlayers(): array
    {
        return $this->fetchAll(
            "SELECT pid, name FROM ibl_plr",
            ""
        );
    }

    /**
     * @see LeaderboardRepositoryInterface::getPlayerStats()
     */
    public function getPlayerStats(string $playerName, string $statsTable): array
    {
        $this->validateStatsTable($statsTable);

        return $this->fetchAll(
            "SELECT * FROM {$statsTable} WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * @see LeaderboardRepositoryInterface::getPlayerCareerStats()
     */
    public function getPlayerCareerStats(string $playerName): ?array
    {
        return $this->fetchOne(
            "SELECT pid, name, car_gm, car_min, car_fgm, car_fga, car_ftm, car_fta,
                    car_tgm, car_tga, car_orb, car_reb, car_ast, car_stl, car_to,
                    car_blk, car_pf, car_pts
             FROM ibl_plr WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * @see LeaderboardRepositoryInterface::deletePlayerCareerTotals()
     */
    public function deletePlayerCareerTotals(string $playerName, string $table): bool
    {
        $this->validateCareerTable($table);

        $result = $this->execute(
            "DELETE FROM {$table} WHERE name = ?",
            "s",
            $playerName
        );

        return $result !== false;
    }

    /**
     * @see LeaderboardRepositoryInterface::insertPlayerCareerTotals()
     */
    public function insertPlayerCareerTotals(string $table, array $data): bool
    {
        $this->validateCareerTable($table);

        $columns = implode(', ', array_map(fn($col) => "`{$col}`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $types = $this->buildTypeString($data);

        $query = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";

        $result = $this->execute($query, $types, ...array_values($data));

        return $result !== false;
    }

    /**
     * @see LeaderboardRepositoryInterface::deletePlayerCareerAvgs()
     */
    public function deletePlayerCareerAvgs(string $playerName, string $table): bool
    {
        return $this->deletePlayerCareerTotals($playerName, $table);
    }

    /**
     * @see LeaderboardRepositoryInterface::insertPlayerCareerAvgs()
     */
    public function insertPlayerCareerAvgs(string $table, array $data): bool
    {
        return $this->insertPlayerCareerTotals($table, $data);
    }

    /**
     * Validate that the stats table is in the allowed list
     *
     * @param string $table Table name to validate
     * @throws \InvalidArgumentException If table is not allowed
     */
    private function validateStatsTable(string $table): void
    {
        if (!in_array($table, self::ALLOWED_STATS_TABLES, true)) {
            throw new \InvalidArgumentException("Invalid stats table: {$table}");
        }
    }

    /**
     * Validate that the career table is in the allowed list
     *
     * @param string $table Table name to validate
     * @throws \InvalidArgumentException If table is not allowed
     */
    private function validateCareerTable(string $table): void
    {
        if (!in_array($table, self::ALLOWED_CAREER_TABLES, true)) {
            throw new \InvalidArgumentException("Invalid career table: {$table}");
        }
    }

    /**
     * Build mysqli type string from data array
     *
     * @param array $data Data array
     * @return string Type string (e.g., "issd")
     */
    private function buildTypeString(array $data): string
    {
        $types = '';
        foreach ($data as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }
}
