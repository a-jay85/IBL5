<?php

declare(strict_types=1);

namespace Leaderboards;

use Services\DatabaseService;

/**
 * LeaderboardsRepository - Handles all database operations for leaderboards
 * 
 * Following the Repository pattern, this class encapsulates all SQL queries
 * and database interactions for career statistics across multiple table types.
 */
class LeaderboardsRepository
{
    private $db;

    // Whitelist of valid table names to prevent SQL injection
    private const VALID_TABLES = [
        'ibl_hist',
        'ibl_season_career_avgs',
        'ibl_playoff_career_totals',
        'ibl_playoff_career_avgs',
        'ibl_heat_career_totals',
        'ibl_heat_career_avgs',
        'ibl_olympics_career_totals',
        'ibl_olympics_career_avgs',
    ];

    // Whitelist of valid sort columns
    private const VALID_SORT_COLUMNS = [
        'pts', 'games', 'minutes', 'fgm', 'fga', 'fgpct', 
        'ftm', 'fta', 'ftpct', 'tgm', 'tga', 'tpct',
        'orb', 'reb', 'ast', 'stl', 'tvr', 'blk', 'pf'
    ];

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get leaderboard data based on filters
     * 
     * @param string $tableKey Key from the type array
     * @param string $sortColumn Column name to sort by
     * @param int $activeOnly 1 to exclude retired players, 0 to include all
     * @param int $limit Maximum number of records to return (0 for unlimited)
     * @return array Query result resource and row count
     */
    public function getLeaderboards(
        string $tableKey,
        string $sortColumn,
        int $activeOnly,
        int $limit
    ): array {
        // Validate table name
        if (!in_array($tableKey, self::VALID_TABLES)) {
            throw new \InvalidArgumentException("Invalid table name: $tableKey");
        }

        // Validate sort column
        if (!in_array($sortColumn, self::VALID_SORT_COLUMNS)) {
            throw new \InvalidArgumentException("Invalid sort column: $sortColumn");
        }

        // Build WHERE clause
        $conditions = ["games > 0"];
        if ($activeOnly == 1) {
            $conditions[] = "p.retired = '0'";
        }
        $whereClause = implode(' AND ', $conditions);

        // Special handling for ibl_hist table (aggregated by player)
        if ($tableKey == 'ibl_hist') {
            $query = "SELECT
                h.pid,
                h.name,
                sum(h.games) as games,
                sum(h.minutes) as minutes,
                sum(h.fgm) as fgm,
                sum(h.fga) as fga,
                sum(h.ftm) as ftm,
                sum(h.fta) as fta,
                sum(h.tgm) as tgm,
                sum(h.tga) as tga,
                sum(h.orb) as orb,
                sum(h.reb) as reb,
                sum(h.ast) as ast,
                sum(h.stl) as stl,
                sum(h.blk) as blk,
                sum(h.tvr) as tvr,
                sum(h.pf) as pf,
                sum(h.pts) as pts,
                p.retired
                FROM ibl_hist h
                LEFT JOIN ibl_plr p ON h.pid = p.pid
                WHERE $whereClause
                GROUP BY pid
                ORDER BY $sortColumn DESC" 
                . ($limit > 0 ? " LIMIT $limit" : "") . ";";
        } else {
            $query = "SELECT h.*, p.retired
                FROM $tableKey h
                LEFT JOIN ibl_plr p ON h.pid = p.pid
                WHERE $whereClause
                ORDER BY $sortColumn DESC"
                . ($limit > 0 ? " LIMIT $limit" : "") . ";";
        }

        $result = $this->db->sql_query($query);
        $numRows = $this->db->sql_numrows($result);

        return [
            'result' => $result,
            'count' => $numRows
        ];
    }

    /**
     * Check if a table contains totals or averages
     * 
     * @param string $tableKey Table name
     * @return string 'totals' or 'averages'
     */
    public function getTableType(string $tableKey): string
    {
        $avgTables = [
            'ibl_season_career_avgs',
            'ibl_playoff_career_avgs',
            'ibl_heat_career_avgs',
            'ibl_olympics_career_avgs',
        ];

        return in_array($tableKey, $avgTables) ? 'averages' : 'totals';
    }
}
