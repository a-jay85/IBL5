<?php

declare(strict_types=1);

namespace PlrParser;

use League\LeagueContext;
use PlrParser\Contracts\PlrBoxScoreRepositoryInterface;

/**
 * Aggregates ibl_box_scores into per-player season-stat totals.
 *
 * @see PlrBoxScoreRepositoryInterface
 */
class PlrBoxScoreRepository extends \BaseMysqliRepository implements PlrBoxScoreRepositoryInterface
{
    private string $boxScoresTable;
    private string $boxScoresTeamsTable;
    private string $simDatesTable;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->boxScoresTable = $this->resolveTable('ibl_box_scores');
        $this->boxScoresTeamsTable = $this->resolveTable('ibl_box_scores_teams');
        $this->simDatesTable = $this->resolveTable('ibl_sim_dates');
    }

    /**
     * @see PlrBoxScoreRepositoryInterface::sumStatsByGameTypeThroughDate()
     */
    public function sumStatsByGameTypeThroughDate(int $seasonYear, int $gameType, string $endDate): array
    {
        // `gp` counts only games where the player actually played (game_min > 0).
        // Box scores include DNP rows (game_min = 0) for roster players who didn't suit up;
        // those don't count toward seasonGamesPlayed in the .plr file.
        $sql = "
            SELECT
                pid,
                SUM(CASE WHEN game_min > 0 THEN 1 ELSE 0 END) AS gp,
                SUM(game_min) AS min,
                SUM(game_2gm) AS two_gm,
                SUM(game_2ga) AS two_ga,
                SUM(game_ftm) AS ftm,
                SUM(game_fta) AS fta,
                SUM(game_3gm) AS three_gm,
                SUM(game_3ga) AS three_ga,
                SUM(game_orb) AS orb,
                SUM(game_drb) AS drb,
                SUM(game_ast) AS ast,
                SUM(game_stl) AS stl,
                SUM(game_tov) AS tov,
                SUM(game_blk) AS blk,
                SUM(game_pf) AS pf
            FROM {$this->boxScoresTable}
            WHERE season_year = ?
              AND game_type = ?
              AND game_date <= ?
              AND pid IS NOT NULL
            GROUP BY pid
        ";

        /** @var list<array{pid: int, gp: int|null, min: int|null, two_gm: int|null, two_ga: int|null, ftm: int|null, fta: int|null, three_gm: int|null, three_ga: int|null, orb: int|null, drb: int|null, ast: int|null, stl: int|null, tov: int|null, blk: int|null, pf: int|null}> $rows */
        $rows = $this->fetchAll($sql, 'iis', $seasonYear, $gameType, $endDate);

        $byPid = [];
        foreach ($rows as $row) {
            $byPid[$row['pid']] = [
                'gp' => (int) ($row['gp'] ?? 0),
                'min' => (int) ($row['min'] ?? 0),
                'two_gm' => (int) ($row['two_gm'] ?? 0),
                'two_ga' => (int) ($row['two_ga'] ?? 0),
                'ftm' => (int) ($row['ftm'] ?? 0),
                'fta' => (int) ($row['fta'] ?? 0),
                'three_gm' => (int) ($row['three_gm'] ?? 0),
                'three_ga' => (int) ($row['three_ga'] ?? 0),
                'orb' => (int) ($row['orb'] ?? 0),
                'drb' => (int) ($row['drb'] ?? 0),
                'ast' => (int) ($row['ast'] ?? 0),
                'stl' => (int) ($row['stl'] ?? 0),
                'tov' => (int) ($row['tov'] ?? 0),
                'blk' => (int) ($row['blk'] ?? 0),
                'pf' => (int) ($row['pf'] ?? 0),
            ];
        }

        return $byPid;
    }

    /**
     * @see PlrBoxScoreRepositoryInterface::getSingleGameMaximumsThroughDate()
     */
    public function getSingleGameMaximumsThroughDate(int $seasonYear, int $gameType, string $endDate): array
    {
        // Double-double = exactly 2 of {pts>=10, reb>=10, ast>=10, stl>=10, blk>=10}.
        // Triple+ = 3 or more. DNP rows excluded via game_min > 0.
        $sql = "
            SELECT
                pid,
                MAX(calc_points) AS high_pts,
                MAX(calc_rebounds) AS high_reb,
                MAX(game_ast) AS high_ast,
                MAX(game_stl) AS high_stl,
                MAX(game_blk) AS high_blk,
                SUM(CASE WHEN (
                    (calc_points >= 10)
                    + (calc_rebounds >= 10)
                    + (game_ast >= 10)
                    + (game_stl >= 10)
                    + (game_blk >= 10)
                ) = 2 THEN 1 ELSE 0 END) AS doubles,
                SUM(CASE WHEN (
                    (calc_points >= 10)
                    + (calc_rebounds >= 10)
                    + (game_ast >= 10)
                    + (game_stl >= 10)
                    + (game_blk >= 10)
                ) >= 3 THEN 1 ELSE 0 END) AS triples
            FROM {$this->boxScoresTable}
            WHERE season_year = ?
              AND game_type = ?
              AND game_date <= ?
              AND pid IS NOT NULL
              AND game_min > 0
            GROUP BY pid
        ";

        /** @var list<array{pid: int, high_pts: int|null, high_reb: int|null, high_ast: int|null, high_stl: int|null, high_blk: int|null, doubles: int|null, triples: int|null}> $rows */
        $rows = $this->fetchAll($sql, 'iis', $seasonYear, $gameType, $endDate);

        $byPid = [];
        foreach ($rows as $row) {
            $byPid[$row['pid']] = [
                'high_pts' => (int) ($row['high_pts'] ?? 0),
                'high_reb' => (int) ($row['high_reb'] ?? 0),
                'high_ast' => (int) ($row['high_ast'] ?? 0),
                'high_stl' => (int) ($row['high_stl'] ?? 0),
                'high_blk' => (int) ($row['high_blk'] ?? 0),
                'doubles' => (int) ($row['doubles'] ?? 0),
                'triples' => (int) ($row['triples'] ?? 0),
            ];
        }

        return $byPid;
    }

    /**
     * @see PlrBoxScoreRepositoryInterface::latestGameDate()
     */
    public function latestGameDate(int $seasonYear, int $gameType): ?string
    {
        /** @var array{last_date: string|null}|null $row */
        $row = $this->fetchOne(
            "SELECT MAX(game_date) AS last_date FROM {$this->boxScoresTable} WHERE season_year = ? AND game_type = ?",
            'ii',
            $seasonYear,
            $gameType,
        );
        if ($row === null) {
            return null;
        }
        return $row['last_date'];
    }

    /**
     * @see PlrBoxScoreRepositoryInterface::cumulativeRegularSeasonStatsByDate()
     */
    public function cumulativeRegularSeasonStatsByDate(int $pid, int $seasonYear): array
    {
        $sql = "
            SELECT
                game_date AS date,
                SUM(CASE WHEN game_min > 0 THEN 1 ELSE 0 END) AS gp,
                SUM(game_min) AS min,
                SUM(game_2gm) AS two_gm,
                SUM(game_2ga) AS two_ga,
                SUM(game_ftm) AS ftm,
                SUM(game_fta) AS fta,
                SUM(game_3gm) AS three_gm,
                SUM(game_3ga) AS three_ga,
                SUM(game_orb) AS orb,
                SUM(game_drb) AS drb,
                SUM(game_ast) AS ast,
                SUM(game_stl) AS stl,
                SUM(game_tov) AS tov,
                SUM(game_blk) AS blk,
                SUM(game_pf) AS pf
            FROM {$this->boxScoresTable}
            WHERE pid = ? AND season_year = ? AND game_type = 1
            GROUP BY game_date
            ORDER BY game_date
        ";

        /** @var list<array{date: string, gp: int|null, min: int|null, two_gm: int|null, two_ga: int|null, ftm: int|null, fta: int|null, three_gm: int|null, three_ga: int|null, orb: int|null, drb: int|null, ast: int|null, stl: int|null, tov: int|null, blk: int|null, pf: int|null}> $rows */
        $rows = $this->fetchAll($sql, 'ii', $pid, $seasonYear);

        $cumulative = [
            'gp' => 0, 'min' => 0, 'two_gm' => 0, 'two_ga' => 0,
            'ftm' => 0, 'fta' => 0, 'three_gm' => 0, 'three_ga' => 0,
            'orb' => 0, 'drb' => 0, 'ast' => 0, 'stl' => 0,
            'tov' => 0, 'blk' => 0, 'pf' => 0,
        ];
        $result = [];
        foreach ($rows as $row) {
            $cumulative['gp'] += (int) ($row['gp'] ?? 0);
            $cumulative['min'] += (int) ($row['min'] ?? 0);
            $cumulative['two_gm'] += (int) ($row['two_gm'] ?? 0);
            $cumulative['two_ga'] += (int) ($row['two_ga'] ?? 0);
            $cumulative['ftm'] += (int) ($row['ftm'] ?? 0);
            $cumulative['fta'] += (int) ($row['fta'] ?? 0);
            $cumulative['three_gm'] += (int) ($row['three_gm'] ?? 0);
            $cumulative['three_ga'] += (int) ($row['three_ga'] ?? 0);
            $cumulative['orb'] += (int) ($row['orb'] ?? 0);
            $cumulative['drb'] += (int) ($row['drb'] ?? 0);
            $cumulative['ast'] += (int) ($row['ast'] ?? 0);
            $cumulative['stl'] += (int) ($row['stl'] ?? 0);
            $cumulative['tov'] += (int) ($row['tov'] ?? 0);
            $cumulative['blk'] += (int) ($row['blk'] ?? 0);
            $cumulative['pf'] += (int) ($row['pf'] ?? 0);
            $result[] = array_merge(['date' => $row['date']], $cumulative);
        }
        return $result;
    }

    /**
     * @see PlrBoxScoreRepositoryInterface::sumTeamRegularSeasonStatsThroughDate()
     */
    public function sumTeamRegularSeasonStatsThroughDate(int $seasonYear, string $endDate): array
    {
        return $this->sumTeamStatsByGameType($seasonYear, 1, $endDate);
    }

    /**
     * @see PlrBoxScoreRepositoryInterface::sumTeamPlayoffStatsThroughDate()
     */
    public function sumTeamPlayoffStatsThroughDate(int $seasonYear, string $endDate): array
    {
        return $this->sumTeamStatsByGameType($seasonYear, 2, $endDate);
    }

    /**
     * Common team-stats aggregation for any game type.
     *
     * ibl_box_scores_teams stores two rows per game: visitor stats (lower id),
     * home stats (higher id). ROW_NUMBER deduplicates so each team's own stats
     * are counted exactly once. The `name` column is unreliable — team identity
     * comes from visitor_teamid/home_teamid cross-referenced with row order.
     *
     * @return array<int, array{gp: int, gpAlt: int, twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}>
     */
    private function sumTeamStatsByGameType(int $seasonYear, int $gameType, string $endDate): array
    {
        $sql = "
            WITH ranked AS (
                SELECT *,
                    ROW_NUMBER() OVER (
                        PARTITION BY game_date, game_of_that_day, visitor_teamid, home_teamid
                        ORDER BY id
                    ) AS rn
                FROM {$this->boxScoresTeamsTable}
                WHERE season_year = ?
                  AND game_type = ?
                  AND game_date <= ?
            )
            SELECT
                team_id,
                COUNT(*) AS gp,
                COUNT(*) AS gpAlt,
                SUM(game_2gm) AS twoGM,
                SUM(game_2ga) AS twoGA,
                SUM(game_ftm) AS ftm,
                SUM(game_fta) AS fta,
                SUM(game_3gm) AS threeGM,
                SUM(game_3ga) AS threeGA,
                SUM(game_orb) AS orb,
                SUM(game_drb) AS drb,
                SUM(game_ast) AS ast,
                SUM(game_stl) AS stl,
                SUM(game_tov) AS tov,
                SUM(game_blk) AS blk,
                SUM(game_pf) AS pf
            FROM (
                SELECT visitor_teamid AS team_id,
                       game_2gm, game_2ga, game_ftm, game_fta, game_3gm, game_3ga,
                       game_orb, game_drb, game_ast, game_stl, game_tov, game_blk, game_pf
                FROM ranked WHERE rn = 1
                UNION ALL
                SELECT home_teamid AS team_id,
                       game_2gm, game_2ga, game_ftm, game_fta, game_3gm, game_3ga,
                       game_orb, game_drb, game_ast, game_stl, game_tov, game_blk, game_pf
                FROM ranked WHERE rn = 2
            ) AS team_stats
            GROUP BY team_id
        ";

        /** @var list<array{team_id: int, gp: int|null, gpAlt: int|null, twoGM: int|null, twoGA: int|null, ftm: int|null, fta: int|null, threeGM: int|null, threeGA: int|null, orb: int|null, drb: int|null, ast: int|null, stl: int|null, tov: int|null, blk: int|null, pf: int|null}> $rows */
        $rows = $this->fetchAll($sql, 'iis', $seasonYear, $gameType, $endDate);

        $byTeamId = [];
        foreach ($rows as $row) {
            $byTeamId[$row['team_id']] = [
                'gp' => (int) ($row['gp'] ?? 0),
                'gpAlt' => (int) ($row['gpAlt'] ?? 0),
                'twoGM' => (int) ($row['twoGM'] ?? 0),
                'twoGA' => (int) ($row['twoGA'] ?? 0),
                'ftm' => (int) ($row['ftm'] ?? 0),
                'fta' => (int) ($row['fta'] ?? 0),
                'threeGM' => (int) ($row['threeGM'] ?? 0),
                'threeGA' => (int) ($row['threeGA'] ?? 0),
                'orb' => (int) ($row['orb'] ?? 0),
                'drb' => (int) ($row['drb'] ?? 0),
                'ast' => (int) ($row['ast'] ?? 0),
                'stl' => (int) ($row['stl'] ?? 0),
                'tov' => (int) ($row['tov'] ?? 0),
                'blk' => (int) ($row['blk'] ?? 0),
                'pf' => (int) ($row['pf'] ?? 0),
            ];
        }

        return $byTeamId;
    }

    /**
     * @see PlrBoxScoreRepositoryInterface::simEndDatesForSeason()
     */
    public function simEndDatesForSeason(int $seasonYear): array
    {
        // Season window: Oct (year - 1) through Jul (year). Matches the season_year
        // generated column convention in ibl_box_scores.
        $start = ($seasonYear - 1) . '-10-01';
        $end = $seasonYear . '-07-31';

        /** @var list<array{end_date: string}> $rows */
        $rows = $this->fetchAll(
            "SELECT end_date FROM {$this->simDatesTable}
             WHERE end_date BETWEEN ? AND ? ORDER BY sim",
            'ss',
            $start,
            $end,
        );
        return array_map(static fn (array $row): string => $row['end_date'], $rows);
    }
}
