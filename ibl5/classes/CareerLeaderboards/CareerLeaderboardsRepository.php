<?php

declare(strict_types=1);

namespace CareerLeaderboards;

use CareerLeaderboards\Contracts\CareerLeaderboardsRepositoryInterface;

/**
 * @see CareerLeaderboardsRepositoryInterface
 *
 * @phpstan-import-type CareerStatsRow from CareerLeaderboardsRepositoryInterface
 * @phpstan-import-type LeaderboardResult from CareerLeaderboardsRepositoryInterface
 */
class CareerLeaderboardsRepository extends \BaseMysqliRepository implements CareerLeaderboardsRepositoryInterface
{
    /**
     * Default safety limit when $limit = 0 (fetch all rows).
     * Must exceed the largest career table row count (currently ~1258).
     */
    private const DEFAULT_SAFETY_LIMIT = 5000;

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

    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * @see CareerLeaderboardsRepositoryInterface::getLeaderboards()
     *
     * SECURITY NOTE: $tableKey and $sortColumn are validated against whitelists
     * (VALID_TABLES and VALID_SORT_COLUMNS) before being used in SQL.
     * String concatenation is acceptable here because all values are validated.
     *
     * @return LeaderboardResult
     */
    public function getLeaderboards(
        string $tableKey,
        string $sortColumn,
        int $activeOnly,
        int $limit
    ): array {
        // Validate table name
        if (!in_array($tableKey, self::VALID_TABLES, true)) {
            throw new \InvalidArgumentException("Invalid table name: $tableKey");
        }

        // Validate sort column
        if (!in_array($sortColumn, self::VALID_SORT_COLUMNS, true)) {
            throw new \InvalidArgumentException("Invalid sort column: $sortColumn");
        }

        // Build WHERE clause
        $conditions = ["games > 0"];
        if ($activeOnly === 1) {
            $conditions[] = "p.retired = 0";
        }
        $whereClause = implode(' AND ', $conditions);

        // Special handling for ibl_hist table (aggregated by player)
        // NOTE: Table name and sort column are whitelisted above
        if ($tableKey === 'ibl_hist') {
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
                . " LIMIT " . ($limit > 0 ? $limit : self::DEFAULT_SAFETY_LIMIT) . ";";
        } else {
            $query = "SELECT h.*, p.retired
                FROM $tableKey h
                LEFT JOIN ibl_plr p ON h.pid = p.pid
                WHERE $whereClause
                ORDER BY $sortColumn DESC"
                . " LIMIT " . ($limit > 0 ? $limit : self::DEFAULT_SAFETY_LIMIT) . ";";
        }

        /** @var list<CareerStatsRow> $rows */
        $rows = $this->fetchAll($query);

        return [
            'result' => $rows,
            'count' => count($rows)
        ];
    }

    /**
     * @see CareerLeaderboardsRepositoryInterface::getTableType()
     */
    public function getTableType(string $tableKey): string
    {
        $avgTables = [
            'ibl_season_career_avgs',
            'ibl_playoff_career_avgs',
            'ibl_heat_career_avgs',
            'ibl_olympics_career_avgs',
        ];

        return in_array($tableKey, $avgTables, true) ? 'averages' : 'totals';
    }
}
