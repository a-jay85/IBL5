<?php

declare(strict_types=1);

namespace RecordHolders;

use League\League;
use League\LeagueContext;
use RecordHolders\Contracts\RecordHoldersRepositoryInterface;

/**
 * Queries for franchise-level records: playoff appearances and title counts.
 *
 * @phpstan-import-type FranchiseTitleRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type PlayoffAppearanceRecord from RecordHoldersRepositoryInterface
 */
final class FranchiseRecordRepository extends \BaseMysqliRepository
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * Fetch top franchises by playoff appearance count.
     *
     * @return list<PlayoffAppearanceRecord>
     */
    public function getMostPlayoffAppearances(): array
    {
        $query = "SELECT
                t.team_name,
                COUNT(DISTINCT pr.year) AS count,
                GROUP_CONCAT(DISTINCT pr.year ORDER BY pr.year ASC SEPARATOR ', ') AS years
            FROM vw_playoff_series_results pr
            JOIN `ibl_team_info` t ON t.teamid = pr.winner_tid OR t.teamid = pr.loser_tid
            WHERE t.teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
            GROUP BY t.team_name
            ORDER BY count DESC, t.team_name ASC
            LIMIT 5";

        $rows = $this->fetchAll($query);

        // Find the max count to include ties
        $maxCount = 0;
        foreach ($rows as $countRow) {
            /** @var array{team_name: string, count: int, years: string} $countRow */
            if ($countRow['count'] > $maxCount) {
                $maxCount = $countRow['count'];
            }
        }

        /** @var list<PlayoffAppearanceRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{team_name: string, count: int, years: string} $row */
            if ($row['count'] === $maxCount) {
                $records[] = [
                    'team_name' => $row['team_name'],
                    'count' => $row['count'],
                    'years' => $row['years'],
                ];
            }
        }

        return $records;
    }

    /**
     * Fetch top franchises by title count for a given title pattern.
     *
     * @return list<FranchiseTitleRecord>
     */
    public function getMostTitlesByType(string $titlePattern): array
    {
        $rows = $this->fetchAll(
            self::buildMostTitlesByTypeQuery(),
            's',
            '%' . $titlePattern . '%'
        );

        // Find the max count to include ties
        $maxCount = 0;
        foreach ($rows as $countRow) {
            /** @var array{team_name: string, count: int, years: string} $countRow */
            if ($countRow['count'] > $maxCount) {
                $maxCount = $countRow['count'];
            }
        }

        /** @var list<FranchiseTitleRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{team_name: string, count: int, years: string} $row */
            if ($row['count'] === $maxCount) {
                $records[] = [
                    'team_name' => $row['team_name'],
                    'count' => $row['count'],
                    'years' => $row['years'],
                ];
            }
        }

        return $records;
    }

    /**
     * Inlined team awards query with optimized Champions/HEAT branches.
     *
     * Uses window functions instead of correlated subqueries. The HEAT-champion
     * branch reads from the `vw_heat_champions` view (migration 149) rather than
     * an inline CTE.
     */
    private static function buildMostTitlesByTypeQuery(): string
    {
        return "SELECT
                name AS team_name,
                COUNT(*) AS count,
                GROUP_CONCAT(year ORDER BY year ASC SEPARATOR ', ') AS years
            FROM (
                SELECT year, name, award
                FROM `ibl_team_awards`

                UNION ALL

                SELECT ranked.year, ranked.name, 'IBL Champions' AS award
                FROM (
                    SELECT
                        psr.year,
                        psr.winner AS name,
                        psr.round,
                        MAX(psr.round) OVER (PARTITION BY psr.year) AS max_round,
                        COUNT(*) OVER (PARTITION BY psr.year, psr.round) AS series_in_round
                    FROM vw_playoff_series_results psr
                ) ranked
                WHERE ranked.round = ranked.max_round AND ranked.series_in_round = 1

                UNION ALL

                SELECT year, name, award
                FROM `vw_heat_champions`
            ) all_awards
            WHERE award LIKE ?
            GROUP BY name
            ORDER BY count DESC, name ASC
            LIMIT 5";
    }
}
