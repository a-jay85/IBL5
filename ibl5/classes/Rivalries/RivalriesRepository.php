<?php

declare(strict_types=1);

namespace Rivalries;

use League\League;
use Rivalries\Contracts\RivalriesRepositoryInterface;

/**
 * RivalriesRepository - Game-level reads for franchise and GM rivalries.
 *
 * W/L totals are never computed here; the service takes them from the H2H
 * matrix. The `unique_games` dedupe and the GM tenure rule are copied verbatim
 * from HeadToHeadRecordsRepository so the two pages always agree.
 *
 * @phpstan-import-type PairStat from RivalriesRepositoryInterface
 * @phpstan-import-type GameRow from RivalriesRepositoryInterface
 * @phpstan-import-type SeriesRow from RivalriesRepositoryInterface
 * @phpstan-import-type PerformerTotal from RivalriesRepositoryInterface
 * @phpstan-import-type PerformerHigh from RivalriesRepositoryInterface
 *
 * @see RivalriesRepositoryInterface
 */
final class RivalriesRepository extends \Database\BaseMysqliRepository implements RivalriesRepositoryInterface
{
    private const TENURE_SUBQUERY = '(SELECT t.gm_display_name FROM `ibl_gm_tenures` t
          WHERE t.franchise_id = %s
            AND %s BETWEEN (t.start_season_year + t.is_mid_season_start)
                                  AND COALESCE(t.end_season_year, 9999)
          ORDER BY t.start_season_year ASC, t.id ASC LIMIT 1)';

    // Pre-dedupe pair filter: lets unique_games group only this pair's rows. Binds 4 `i` params.
    private const FRANCHISE_PAIR_FILTER = ' AND ((b.visitor_teamid = ? AND b.home_teamid = ?) OR (b.visitor_teamid = ? AND b.home_teamid = ?))';

    private const GAME_COLUMNS = 'game_date, game_of_that_day, season_year, game_type, visitor_teamid, home_teamid,
    visitor_label, home_label, visitor_pts AS visitor_points, home_pts AS home_points';

    /**
     * @see RivalriesRepositoryInterface::getFranchisePairStats()
     */
    public function getFranchisePairStats(int $recentFromSeasonYear): array
    {
        $sql = $this->gamesCte()
            . ' SELECT LEAST(visitor_teamid, home_teamid) AS a, GREATEST(visitor_teamid, home_teamid) AS b,
       SUM(ABS(visitor_pts - home_pts) <= ?) AS close_games,
       SUM(season_year >= ?) AS recent_games,
       SUM(game_type = 2) AS playoff_games
FROM games
GROUP BY a, b
ORDER BY a, b';
        $rows = $this->fetchAll($sql, 'ii', RivalryScorer::CLOSE_GAME_MARGIN, $recentFromSeasonYear);

        $seriesRows = $this->fetchAll(
            'SELECT LEAST(winner_tid, loser_tid) AS a, GREATEST(winner_tid, loser_tid) AS b, COUNT(*) AS n
FROM `ibl_playoff_series_results`
WHERE winner_tid BETWEEN 1 AND ? AND loser_tid BETWEEN 1 AND ?
GROUP BY a, b
ORDER BY a, b',
            'ii',
            League::MAX_REAL_TEAMID,
            League::MAX_REAL_TEAMID,
        );

        return $this->mergeStats($rows, $seriesRows);
    }

    /**
     * @see RivalriesRepositoryInterface::getGmPairStats()
     */
    public function getGmPairStats(int $recentFromSeasonYear): array
    {
        $sql = $this->gmCte()
            . ' SELECT LEAST(v_gm, h_gm) AS a, GREATEST(v_gm, h_gm) AS b,
       SUM(ABS(visitor_pts - home_pts) <= ?) AS close_games,
       SUM(season_year >= ?) AS recent_games,
       SUM(game_type = 2) AS playoff_games
FROM gm_games
WHERE v_gm IS NOT NULL AND h_gm IS NOT NULL AND v_gm <> h_gm
GROUP BY a, b
ORDER BY a, b';
        $rows = $this->fetchAll($sql, 'ii', RivalryScorer::CLOSE_GAME_MARGIN, $recentFromSeasonYear);

        $seriesRows = $this->fetchAll(
            $this->gmSeriesCte() . '
SELECT LEAST(w_gm, l_gm) AS a, GREATEST(w_gm, l_gm) AS b, COUNT(*) AS n
FROM gm_series
WHERE w_gm IS NOT NULL AND l_gm IS NOT NULL AND w_gm <> l_gm
GROUP BY a, b
ORDER BY a, b',
            'ii',
            League::MAX_REAL_TEAMID,
            League::MAX_REAL_TEAMID,
        );

        return $this->mergeStats($rows, $seriesRows);
    }

    /**
     * @see RivalriesRepositoryInterface::getFranchisePairGames()
     */
    public function getFranchisePairGames(int $a, int $b): array
    {
        $sql = $this->gamesCte(self::FRANCHISE_PAIR_FILTER)
            // @phpstan-ignore ibl.orderByMissingTiebreaker (no doubleheaders: game_date + game_of_that_day + visitor_teamid is unique)
            . ' SELECT ' . self::GAME_COLUMNS . ',
       CAST(visitor_teamid AS CHAR) AS visitor_side, CAST(home_teamid AS CHAR) AS home_side
FROM games
WHERE (visitor_teamid = ? AND home_teamid = ?) OR (visitor_teamid = ? AND home_teamid = ?)
ORDER BY game_date ASC, game_of_that_day ASC, visitor_teamid ASC';

        return $this->mapGames($this->fetchAll($sql, 'iiiiiiii', $a, $b, $b, $a, $a, $b, $b, $a));
    }

    /**
     * @see RivalriesRepositoryInterface::getGmPairGames()
     */
    public function getGmPairGames(string $a, string $b): array
    {
        $filter = $this->pairTenureFilter($a, $b, 'b.visitor_teamid', 'b.home_teamid', 'b.season_year');
        if ($filter === null) {
            return [];
        }

        $sql = $this->gmCte($filter['sql'])
            // @phpstan-ignore ibl.orderByMissingTiebreaker (no doubleheaders: game_date + game_of_that_day + visitor_teamid is unique)
            . ' SELECT ' . self::GAME_COLUMNS . ', v_gm AS visitor_side, h_gm AS home_side
FROM gm_games
WHERE v_gm <> h_gm AND ((v_gm = ? AND h_gm = ?) OR (v_gm = ? AND h_gm = ?))
ORDER BY game_date ASC, game_of_that_day ASC, visitor_teamid ASC';

        return $this->mapGames($this->fetchAll($sql, $filter['types'] . 'ssss', ...$filter['params'], ...[$a, $b, $b, $a]));
    }

    /**
     * @see RivalriesRepositoryInterface::getFranchisePlayoffSeries()
     */
    public function getFranchisePlayoffSeries(int $a, int $b): array
    {
        $rows = $this->fetchAll(
            // @phpstan-ignore ibl.orderByMissingTiebreaker (year + round + both teams identify one series)
            'SELECT pr.year, pr.round, pr.winner_tid, pr.loser_tid, pr.winner_games, pr.loser_games,
       CAST(pr.winner_tid AS CHAR) AS winner_side
FROM `ibl_playoff_series_results` pr
WHERE (pr.winner_tid = ? AND pr.loser_tid = ?) OR (pr.winner_tid = ? AND pr.loser_tid = ?)
ORDER BY pr.year ASC, pr.round ASC, pr.winner_tid ASC, pr.loser_tid ASC',
            'iiii',
            $a,
            $b,
            $b,
            $a,
        );

        return $this->mapSeries($rows);
    }

    /**
     * @see RivalriesRepositoryInterface::getGmPlayoffSeries()
     */
    public function getGmPlayoffSeries(string $a, string $b): array
    {
        $filter = $this->pairTenureFilter($a, $b, 'pr.winner_tid', 'pr.loser_tid', 'pr.year');
        if ($filter === null) {
            return [];
        }

        $rows = $this->fetchAll(
            $this->gmSeriesCte($filter['sql'])
            // @phpstan-ignore ibl.orderByMissingTiebreaker (year + round + both teams identify one series)
            . ' SELECT year, round, winner_tid, loser_tid, winner_games, loser_games, w_gm AS winner_side
FROM gm_series
WHERE w_gm <> l_gm AND ((w_gm = ? AND l_gm = ?) OR (w_gm = ? AND l_gm = ?))
ORDER BY year ASC, round ASC, winner_tid ASC, loser_tid ASC',
            'ii' . $filter['types'] . 'ssss',
            League::MAX_REAL_TEAMID,
            League::MAX_REAL_TEAMID,
            ...$filter['params'],
            ...[$a, $b, $b, $a],
        );

        return $this->mapSeries($rows);
    }

    /**
     * @see RivalriesRepositoryInterface::getFranchiseTopPerformers()
     */
    public function getFranchiseTopPerformers(int $a, int $b): array
    {
        $pairCte = $this->gamesCte(self::FRANCHISE_PAIR_FILTER) . ',
pair_games AS (
    SELECT * FROM games
    WHERE (visitor_teamid = ? AND home_teamid = ?) OR (visitor_teamid = ? AND home_teamid = ?)
)';

        return $this->topPerformers($pairCte, 'CAST(bs.teamid AS CHAR)', 'iiiiiiii', [$a, $b, $b, $a, $a, $b, $b, $a]);
    }

    /**
     * @see RivalriesRepositoryInterface::getGmTopPerformers()
     */
    public function getGmTopPerformers(string $a, string $b): array
    {
        $filter = $this->pairTenureFilter($a, $b, 'b.visitor_teamid', 'b.home_teamid', 'b.season_year');
        if ($filter === null) {
            return ['totals' => [], 'highs' => []];
        }

        $pairCte = $this->gmCte($filter['sql']) . ',
pair_games AS (
    SELECT * FROM gm_games
    WHERE v_gm <> h_gm AND ((v_gm = ? AND h_gm = ?) OR (v_gm = ? AND h_gm = ?))
)';

        return $this->topPerformers(
            $pairCte,
            'IF(bs.teamid = g.visitor_teamid, g.v_gm, g.h_gm)',
            $filter['types'] . 'ssss',
            [...$filter['params'], $a, $b, $b, $a],
        );
    }

    /**
     * @see RivalriesRepositoryInterface::findGmName()
     */
    public function findGmName(string $gmName): ?string
    {
        // An exact-case match wins if two stored names differ only by case.
        /** @var array{gm_display_name: string}|null $row */
        $row = $this->fetchOne(
            'SELECT gm_display_name FROM `ibl_gm_tenures` WHERE gm_display_name = ?
             ORDER BY BINARY gm_display_name = BINARY ? DESC, id ASC LIMIT 1',
            'ss',
            $gmName,
            $gmName,
        );

        return $row !== null ? $row['gm_display_name'] : null;
    }

    /**
     * H2H `unique_games` + `games` CTEs, phase `all` (HEAT counts), plus dates and era labels.
     *
     * @param string $extraWhere Extra `AND ...` filter on `ibl_box_scores_teams b`, applied before the dedupe.
     */
    private function gamesCte(string $extraWhere = ''): string
    {
        $maxTid = League::MAX_REAL_TEAMID;

        return sprintf(
            'WITH unique_games AS (
    SELECT MIN(id) AS id
    FROM `ibl_box_scores_teams` b
    WHERE b.game_type IN (1, 2, 3)
      AND b.visitor_teamid BETWEEN 1 AND %d
      AND b.home_teamid   BETWEEN 1 AND %d%s
    GROUP BY b.game_date, b.visitor_teamid, b.home_teamid, b.game_of_that_day
),
games AS (
    SELECT b.season_year, b.visitor_teamid, b.home_teamid, b.game_date, b.game_of_that_day, b.game_type,
           (b.visitor_q1_points + b.visitor_q2_points + b.visitor_q3_points + b.visitor_q4_points
            + COALESCE(b.visitor_ot_points, 0)) AS visitor_pts,
           (b.home_q1_points + b.home_q2_points + b.home_q3_points + b.home_q4_points
            + COALESCE(b.home_ot_points, 0)) AS home_pts,
           COALESCE(CONCAT(vfs.team_city, \' \', vfs.team_name), CONCAT(vti.team_city, \' \', vti.team_name)) AS visitor_label,
           COALESCE(CONCAT(hfs.team_city, \' \', hfs.team_name), CONCAT(hti.team_city, \' \', hti.team_name)) AS home_label
    FROM `ibl_box_scores_teams` b
    JOIN unique_games u ON u.id = b.id
    LEFT JOIN `ibl_franchise_seasons` vfs ON vfs.franchise_id = b.visitor_teamid AND vfs.season_ending_year = b.season_year
    LEFT JOIN `ibl_franchise_seasons` hfs ON hfs.franchise_id = b.home_teamid AND hfs.season_ending_year = b.season_year
    LEFT JOIN `ibl_team_info` vti ON vti.teamid = b.visitor_teamid
    LEFT JOIN `ibl_team_info` hti ON hti.teamid = b.home_teamid
)',
            $maxTid,
            $maxTid,
            $extraWhere,
        );
    }

    /**
     * Games CTE plus each side's GM resolved with the H2H tenure rule.
     */
    private function gmCte(string $extraWhere = ''): string
    {
        return $this->gamesCte($extraWhere) . ',
gm_games AS (
    SELECT g.*,
        ' . sprintf(self::TENURE_SUBQUERY, 'g.visitor_teamid', 'g.season_year') . ' AS v_gm,
        ' . sprintf(self::TENURE_SUBQUERY, 'g.home_teamid', 'g.season_year') . ' AS h_gm
    FROM games g
)';
    }

    /**
     * Playoff series rows with each side's GM resolved by `pr.year`. Binds two `i` params,
     * then any params of `$extraWhere`.
     */
    private function gmSeriesCte(string $extraWhere = ''): string
    {
        return 'WITH gm_series AS (
    SELECT pr.year, pr.round, pr.winner_tid, pr.loser_tid, pr.winner_games, pr.loser_games,
        ' . sprintf(self::TENURE_SUBQUERY, 'pr.winner_tid', 'pr.year') . ' AS w_gm,
        ' . sprintf(self::TENURE_SUBQUERY, 'pr.loser_tid', 'pr.year') . ' AS l_gm
    FROM `ibl_playoff_series_results` pr
    WHERE pr.winner_tid BETWEEN 1 AND ? AND pr.loser_tid BETWEEN 1 AND ?' . $extraWhere . '
)';
    }

    /**
     * Pre-filter for one GM pair: both sides must sit inside a tenure of either GM.
     *
     * The tenure subquery still decides each side's GM, so this only drops rows that
     * could never resolve to the pair. It lets the indexed teamid + season filter run
     * before the per-row tenure lookups. Null when either GM has no tenure.
     *
     * @return array{sql: string, types: string, params: list<int>}|null
     */
    private function pairTenureFilter(string $a, string $b, string $sideOne, string $sideTwo, string $yearCol): ?array
    {
        $rows = $this->fetchAll(
            'SELECT gm_display_name, franchise_id, start_season_year + is_mid_season_start AS from_year,
       COALESCE(end_season_year, 9999) AS to_year
FROM `ibl_gm_tenures`
WHERE gm_display_name IN (?, ?)
ORDER BY id ASC',
            'ss',
            $a,
            $b,
        );

        $names = [];
        $spans = [];
        $params = [];
        foreach ($rows as $row) {
            $names[self::str($row['gm_display_name'] ?? null)] = true;
            $spans[] = '(%1$s = ? AND %2$s BETWEEN ? AND ?)';
            $params[] = self::int($row['franchise_id'] ?? null);
            $params[] = self::int($row['from_year'] ?? null);
            $params[] = self::int($row['to_year'] ?? null);
        }
        if (!isset($names[$a], $names[$b])) {
            return null;
        }

        $any = '(' . implode(' OR ', $spans) . ')';

        return [
            'sql' => "\n      AND " . sprintf($any, $sideOne, $yearCol) . "\n      AND " . sprintf($any, $sideTwo, $yearCol),
            'types' => str_repeat('iii', 2 * count($spans)),
            'params' => [...$params, ...$params],
        ];
    }

    /**
     * @param list<int|string> $params
     * @return array{totals: list<PerformerTotal>, highs: list<PerformerHigh>}
     */
    private function topPerformers(string $pairCte, string $sideExpr, string $types, array $params): array
    {
        $perGame = $pairCte . ',
per_game AS (
    SELECT bs.pid, bs.teamid, bs.game_date, bs.game_of_that_day, MAX(bs.calc_points) AS pts, ' . $sideExpr . ' AS side
    FROM `ibl_box_scores` bs
    JOIN pair_games g ON g.game_date = bs.game_date AND g.visitor_teamid = bs.visitor_teamid
        AND g.home_teamid = bs.home_teamid AND g.game_of_that_day = bs.game_of_that_day
    WHERE bs.pid > 0 AND bs.game_min > 0 AND bs.teamid IN (g.visitor_teamid, g.home_teamid)
    GROUP BY bs.pid, bs.teamid, bs.game_date, bs.game_of_that_day, side
)';

        // One pass over the CTE chain; the top-5 totals and top-3 highs are both derived from these rows.
        $rows = $this->fetchAll(
            $perGame . '
SELECT pg.pid, p.name, pg.side, pg.pts AS points, pg.game_date
FROM per_game pg
JOIN `ibl_plr` p ON p.pid = pg.pid
ORDER BY pg.pts DESC, pg.game_date ASC, pg.pid ASC',
            $types,
            ...$params,
        );

        $highs = [];
        $totalsByKey = [];
        foreach ($rows as $row) {
            $pid = self::int($row['pid'] ?? null);
            $name = self::str($row['name'] ?? null);
            $side = self::str($row['side'] ?? null);
            $points = self::int($row['points'] ?? null);

            if (count($highs) < 3) {
                $highs[] = [
                    'pid' => $pid,
                    'name' => $name,
                    'side' => $side,
                    'points' => $points,
                    'game_date' => self::str($row['game_date'] ?? null),
                ];
            }

            $key = $pid . '|' . $side;
            if (!isset($totalsByKey[$key])) {
                $totalsByKey[$key] = [
                    'pid' => $pid,
                    'name' => $name,
                    'side' => $side,
                    'games' => 0,
                    'total_points' => 0,
                ];
            }
            $totalsByKey[$key]['games']++;
            $totalsByKey[$key]['total_points'] += $points;
        }

        $totals = array_values($totalsByKey);
        usort(
            $totals,
            static fn (array $x, array $y): int => [$y['total_points'], $x['pid']] <=> [$x['total_points'], $y['pid']],
        );

        return ['totals' => array_slice($totals, 0, 5), 'highs' => $highs];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $seriesRows
     * @return list<PairStat>
     */
    private function mergeStats(array $rows, array $seriesRows): array
    {
        $series = [];
        foreach ($seriesRows as $row) {
            $series[self::str($row['a'] ?? null) . "\0" . self::str($row['b'] ?? null)] = self::int($row['n'] ?? null);
        }

        $stats = [];
        foreach ($rows as $row) {
            $a = self::str($row['a'] ?? null);
            $b = self::str($row['b'] ?? null);
            $stats[] = [
                'a' => $a,
                'b' => $b,
                'close_games' => self::int($row['close_games'] ?? null),
                'recent_games' => self::int($row['recent_games'] ?? null),
                'playoff_games' => self::int($row['playoff_games'] ?? null),
                'playoff_series' => $series[$a . "\0" . $b] ?? 0,
            ];
        }

        return $stats;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<GameRow>
     */
    private function mapGames(array $rows): array
    {
        $games = [];
        foreach ($rows as $row) {
            $games[] = [
                'game_date' => self::str($row['game_date'] ?? null),
                'game_of_that_day' => self::int($row['game_of_that_day'] ?? null),
                'season_year' => self::int($row['season_year'] ?? null),
                'game_type' => self::int($row['game_type'] ?? null),
                'visitor_teamid' => self::int($row['visitor_teamid'] ?? null),
                'home_teamid' => self::int($row['home_teamid'] ?? null),
                'visitor_label' => self::str($row['visitor_label'] ?? null),
                'home_label' => self::str($row['home_label'] ?? null),
                'visitor_points' => self::int($row['visitor_points'] ?? null),
                'home_points' => self::int($row['home_points'] ?? null),
                'visitor_side' => self::str($row['visitor_side'] ?? null),
                'home_side' => self::str($row['home_side'] ?? null),
            ];
        }

        return $games;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<SeriesRow>
     */
    private function mapSeries(array $rows): array
    {
        $series = [];
        foreach ($rows as $row) {
            $series[] = [
                'year' => self::int($row['year'] ?? null),
                'round' => self::int($row['round'] ?? null),
                'winner_tid' => self::int($row['winner_tid'] ?? null),
                'loser_tid' => self::int($row['loser_tid'] ?? null),
                'winner_games' => self::int($row['winner_games'] ?? null),
                'loser_games' => self::int($row['loser_games'] ?? null),
                'winner_side' => self::str($row['winner_side'] ?? null),
            ];
        }

        return $series;
    }

    private static function int(mixed $v): int
    {
        return is_numeric($v) ? (int) $v : 0;
    }

    private static function str(mixed $v): string
    {
        if (is_string($v)) {
            return $v;
        }

        return is_int($v) ? (string) $v : '';
    }
}
